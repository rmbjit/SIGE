<?php
/**
 * SIGE SoftGenial - WhatsApp Engine
 * Ficheiro: includes/whatsapp-engine.php
 * 
 * Motor de envio de WhatsApp: normalização, API, templates, queue.
 * 
 * @since 10.0
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// HELPERS DE NÚMERO
// ============================================================================
if (!function_exists('sige_wpp_normalizar_numero')) {
    function sige_wpp_normalizar_numero($raw) {
        // (v11.0) Delegado para sige_telefone_normalizar() em core-helpers.php
        return function_exists('sige_telefone_normalizar')
            ? sige_telefone_normalizar($raw)
            : preg_replace('/[^0-9]/', '', (string)$raw);
    }
}

// ============================================================================
// API POST JSON
// ----------------------------------------------------------------------------
// (v12.9.8.3) HARDENING contra falsos-positivos da Z-API.
//
// Antes desta versão a função considerava sucesso QUALQUER HTTP 2xx, mas a
// Z-API responde HTTP 200 mesmo com a sessão WhatsApp desligada (body do tipo
// {"value":false,"error":"You need to be connected"}). Resultado: a queue
// enchia de status='enviado' enquanto na verdade os destinatários não
// recebiam nada - exactamente o sintoma reportado em 27/04/2026.
//
// Agora validamos:
//   1. HTTP 2xx (condição necessária mas não suficiente)
//   2. Body parseável como JSON
//   3. Body NÃO pode conter marcadores de erro (value:false, error:..., etc.)
//   4. Em caso de sucesso, body deve ter messageId/id/zaapId
// ============================================================================
if (!function_exists('sige_wpp_post_json')) {
    function sige_wpp_post_json($url, array $payload, $token) {
        $url = esc_url_raw($url);
        if (empty($url)) return ['ok' => false, 'error' => 'URL Vazio', 'http_code' => 0, 'body' => ''];

        $headers = ['Content-Type' => 'application/json'];
        if (!empty($token)) {
            $headers['Client-Token'] = $token;
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body'    => wp_json_encode($payload),
            'timeout' => 15,
            'reject_unsafe_urls' => true,
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'error' => $response->get_error_message(), 'http_code' => 0, 'body' => ''];
        }

        $http = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        // ── Validação semântica do body ────────────────────────────────────
        $http_ok    = ($http >= 200 && $http < 300);
        $body_ok    = false;
        $reason     = '';

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            // Marcadores de FALHA explícita (Z-API + outros gateways comuns)
            $has_error_field    = !empty($decoded['error']) || !empty($decoded['errorMessage']) || !empty($decoded['errorDescription']);
            $value_is_false     = array_key_exists('value', $decoded)   && $decoded['value']   === false;
            $success_is_false   = array_key_exists('success', $decoded) && $decoded['success'] === false;
            $sent_is_false      = array_key_exists('sent', $decoded)    && $decoded['sent']    === false;

            // Marcadores de SUCESSO (Z-API devolve messageId/zaapId/id)
            $has_msg_id = !empty($decoded['messageId'])
                       || !empty($decoded['zaapId'])
                       || !empty($decoded['id']);

            if ($has_error_field || $value_is_false || $success_is_false || $sent_is_false) {
                $body_ok = false;
                $reason  = 'API devolveu falha: ' . substr(
                    (string)($decoded['error'] ?? $decoded['errorMessage'] ?? $decoded['errorDescription'] ?? 'sem detalhe'),
                    0, 160
                );
            } elseif ($has_msg_id) {
                $body_ok = true;
            } else {
                // Body sem marcadores claros: aceitamos se HTTP for 2xx mas
                // sinalizamos no log para investigação posterior.
                $body_ok = $http_ok;
                $reason  = $http_ok ? '' : 'HTTP não 2xx e body sem messageId';
            }
        } else {
            // Body não-JSON ou vazio: confiamos apenas no HTTP code
            $body_ok = $http_ok;
            if (!$http_ok) {
                $reason = 'HTTP ' . $http . ' (body não-JSON)';
            }
        }

        $ok = $http_ok && $body_ok;

        $result = [
            'ok'        => $ok,
            'http_code' => $http,
            'body'      => $body,
        ];
        if (!$ok) {
            $result['error'] = $reason ?: ('HTTP ' . $http);
        }
        return $result;
    }
}

// ============================================================================
// AJAX: SALVAR CONFIG WHATSAPP
// ============================================================================
add_action('wp_ajax_sige_salvar_whatsapp_isolado', 'sige_salvar_whatsapp_isolado');
if (!function_exists('sige_salvar_whatsapp_isolado')) {
    function sige_salvar_whatsapp_isolado() {
        global $wpdb;
        
        $url = isset($_POST['api_url']) ? trim($_POST['api_url']) : '';
        $token = isset($_POST['api_token']) ? trim($_POST['api_token']) : '';
        
        if (empty($url) || empty($token)) {
            wp_send_json_error("Erro: URL e Token obrigatórios!");
        }
        
        $tabela = $wpdb->prefix . 'sige_config';
        $eid = sige_require_escola_id('whatsapp_isolado');
        $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tabela} WHERE escola_id = %d LIMIT 1", $eid));
        
        if (!$id) { 
            $wpdb->insert($tabela, ['id' => 1, 'escola_id' => $eid]); 
            $id = $wpdb->insert_id ?: 1; 
        }
        
        if (function_exists('sige_wpp_ensure_destinatarios_column')) { sige_wpp_ensure_destinatarios_column(); }
        $dados_update = [
            'whatsapp_url' => $url, 
            'whatsapp_token' => $token
        ];
        if (isset($_POST['whatsapp_destinatarios_padrao'])) {
            $dados_update['whatsapp_destinatarios_padrao'] = function_exists('sige_wpp_destinatarios_normalize')
                ? sige_wpp_destinatarios_normalize($_POST['whatsapp_destinatarios_padrao'])
                : sanitize_key($_POST['whatsapp_destinatarios_padrao']);
        }
        $res = $wpdb->update($tabela, $dados_update, ['id' => $id]);
        
        if ($res === false) { 
            error_log("SIGE SQL Error: " . $wpdb->last_error); 
            wp_send_json_error("Ocorreu um erro interno."); 
        } else {
            wp_send_json_success("✅ Salvo!");
        }
    }
}

// ============================================================================
// AJAX: SALVAR TEMPLATES WHATSAPP FINANCEIRO
// ============================================================================
add_action('wp_ajax_sige_salvar_templates_whatsapp_financeiro', 'sige_salvar_templates_whatsapp_financeiro');
if (!function_exists('sige_salvar_templates_whatsapp_financeiro')) {
    function sige_salvar_templates_whatsapp_financeiro() {
        if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director')
            && !current_user_can('sige_admin') && !current_user_can('sige_financeiro') 
            && !current_user_can('sige_secretario')) {
            wp_send_json_error('Sem permissão.');
        }
        
        $nonce = sanitize_text_field($_POST['sige_cfg_nonce'] ?? $_POST['nonce'] ?? '');
        if (!$nonce || (!wp_verify_nonce($nonce, 'sige_cfg_global') 
            && !wp_verify_nonce($nonce, 'sige_testar_wpp_nonce'))) {
            wp_send_json_error('Sessão expirada (nonce inválido). Recarregue a página.');
        }
        
        global $wpdb;
        $tabela = $wpdb->prefix . 'sige_config';
        $eid = sige_require_escola_id('whatsapp_templates');
        
        $id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tabela} WHERE escola_id = %d LIMIT 1", $eid));
        if (!$id) {
            $wpdb->insert($tabela, ['id' => 1, 'escola_id' => $eid]);
            $id = $wpdb->insert_id ?: 1;
        }
        
        $msg_nova_fatura = isset($_POST['msg_nova_fatura']) ? wp_kses_post(wp_unslash($_POST['msg_nova_fatura'])) : '';
        $msg_recibo_pago = isset($_POST['msg_recibo_pago']) ? wp_kses_post(wp_unslash($_POST['msg_recibo_pago'])) : '';
        $msg_cobranca    = isset($_POST['msg_cobranca'])    ? wp_kses_post(wp_unslash($_POST['msg_cobranca']))    : '';
        
        $res = $wpdb->update($tabela, [
            'msg_nova_fatura' => $msg_nova_fatura,
            'msg_recibo_pago' => $msg_recibo_pago,
            'msg_cobranca'    => $msg_cobranca,
        ], ['id' => $id]);
        
        if ($res === false) {
            wp_send_json_error('Falha ao gravar templates (BD).');
        }
        
        wp_send_json_success('Templates gravados.');
    }
}

// ============================================================================
// AJAX: TESTE WHATSAPP (dois handlers para compatibilidade)
// ============================================================================
add_action('wp_ajax_sige_wpp_teste_directo', 'sige_ajax_wpp_teste_directo');
if (!function_exists('sige_ajax_wpp_teste_directo')) {
    function sige_ajax_wpp_teste_directo() {
        if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director')
            && !current_user_can('sige_financeiro') && !current_user_can('sige_admin') 
            && !current_user_can('sige_secretario')) {
            wp_send_json_error('Sem permissão.');
        }
        
        $numero = isset($_POST['numero']) ? sanitize_text_field($_POST['numero']) : '';
        $url    = isset($_POST['url'])    ? esc_url_raw(trim($_POST['url']))       : '';
        $token  = isset($_POST['token'])  ? sanitize_text_field(trim($_POST['token'])) : '';
        
        if (!$numero || !$url || !$token) {
            wp_send_json_error('Dados incompletos.');
        }
        
        $numero = sige_telefone_normalizar($numero);
        
        $resp = sige_wpp_post_json($url, [
            'phone' => $numero, 
            'message' => '🔔 Teste SIGE Confirmado!'
        ], $token);
        
        if ($resp['ok']) {
            wp_send_json_success('Mensagem Entregue!');
        } else {
            wp_send_json_error('Erro API: ' . substr($resp['body'], 0, 200));
        }
    }
}

add_action('wp_ajax_sige_testar_wpp_config', 'sige_ajax_testar_wpp');
if (!function_exists('sige_ajax_testar_wpp')) {
    function sige_ajax_testar_wpp() {
        $nonce_raw = '';
        foreach (['nonce','sige_cfg_nonce','_wpnonce','sige_wpp_nonce'] as $_nf) {
            if (!empty($_POST[$_nf])) { 
                $nonce_raw = sanitize_text_field($_POST[$_nf]); 
                break; 
            }
        }
        
        $nonce_ok = $nonce_raw && (
            wp_verify_nonce($nonce_raw, 'sige_testar_wpp_nonce') ||
            wp_verify_nonce($nonce_raw, 'sige_cfg_global') ||
            wp_verify_nonce($nonce_raw, 'sige_wpp_test')
        );
        
        if (!$nonce_ok) {
            $can = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director')
                || current_user_can('sige_financeiro') || current_user_can('sige_admin') 
                || current_user_can('sige_secretario');
            if (!$can) {
                wp_send_json_error('Sessão expirada. Recarregue a página.');
            }
        }
        
        if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director')
            && !current_user_can('sige_financeiro') && !current_user_can('sige_admin') 
            && !current_user_can('sige_secretario')) {
            wp_send_json_error('Sem permissão.');
        }
        
        $numero = isset($_POST['numero']) ? sanitize_text_field($_POST['numero']) : '';
        $url    = isset($_POST['url'])    ? esc_url_raw(trim($_POST['url']))      : '';
        $token  = isset($_POST['token'])  ? sanitize_text_field(trim($_POST['token'])) : '';
        
        if (!$numero || !$url || !$token) {
            wp_send_json_error('Dados incompletos.');
        }
        
        $numero = sige_telefone_normalizar($numero);
        
        $resp = sige_wpp_post_json($url, [
            'phone' => $numero, 
            'message' => '🔔 Teste SIGE Confirmado!'
        ], $token);
        
        if ($resp['ok']) {
            wp_send_json_success("Mensagem Entregue!");
        } else {
            wp_send_json_error("Erro API: " . substr($resp['body'], 0, 100));
        }
    }
}

// ============================================================================
// TEMPLATE RENDERING
// ----------------------------------------------------------------------------
// v12.9.53 - quando o motor conversacional v2 estiver carregado
// (whatsapp-templates-conversacional.php), delegamos integralmente para ele.
// O fluxo legado abaixo permanece como fallback se o ficheiro v2 não existir
// (ex: instalação parcial). Se o admin tiver definido um template custom em
// wp_sige_config (msg_nova_fatura/msg_recibo_pago/msg_cobranca), respeitamos
// esse template - a migração v2 limpa esses campos por defeito uma vez,
// mas o admin pode voltar a preenchê-los e aí ganham prioridade.
// ============================================================================
if (!function_exists('sige_wpp_render_finance_template')) {
    function sige_wpp_render_finance_template($tipo_mensagem, $config_row, $aluno_row, $dados_variaveis = []) {
        $tipo_mensagem = strtolower(trim((string)$tipo_mensagem));

        // Escolher template persistido (custom da escola)
        if ($tipo_mensagem === 'fatura') {
            $texto = (string)($config_row->msg_nova_fatura ?? '');
        } elseif ($tipo_mensagem === 'recibo') {
            $texto = (string)($config_row->msg_recibo_pago ?? '');
        } else {
            $texto = (string)($config_row->msg_cobranca ?? '');
        }

        // ── v12.9.53: Delegação para motor conversacional v2 ────────────────
        // Se o template persistido está vazio E o renderer v2 está disponível,
        // devolvemos directamente a mensagem v2 (já totalmente composta com
        // saudação, detalhe, valor formatado, vencimento e fecho). Curto-circuito.
        if (trim($texto) === '' && function_exists('sige_wpp_tpl_v2_render')) {
            $msg_v2 = sige_wpp_tpl_v2_render($tipo_mensagem, $config_row, $aluno_row, (array)$dados_variaveis);
            // Anexar link de recibo se foi passado e ainda não está no texto
            if (!empty($dados_variaveis['link']) && strpos($msg_v2, (string)$dados_variaveis['link']) === false) {
                $msg_v2 .= "\n\nComprovativo digital: " . $dados_variaveis['link'];
            }
            return $msg_v2;
        }

        // Defaults legacy (apenas se v2 não disponível) - mantidos por segurança
        if (trim($texto) === '') {
            if ($tipo_mensagem === 'recibo') {
                $texto = "Bom dia, {encarregado}.\n\nA secretaria confirma o pagamento dos serviços escolares de {aluno}.\n\nItens pagos:\n{detalhes}\n\nTotal pago: {valor} " . sige_moeda() . "\nRecibo: #{id}\nData: {vencimento}\n\n{link}\n\nObrigado.";
            } elseif ($tipo_mensagem === 'fatura') {
                $texto = "Bom dia, {encarregado}.\n\nA secretaria partilha a informação financeira dos serviços escolares de {aluno}:\n\n{detalhes}\n\nValor: {valor} " . sige_moeda() . "\nReferência/Data: {vencimento}\n\nQuando puder, confirme com a secretaria se a informação está correcta.\n\nObrigado.";
            } else {
                $texto = "Bom dia, {encarregado}.\n\nA secretaria gostaria de confirmar consigo a situação financeira dos serviços escolares de {aluno}.\n\n{detalhes}\n\nValor em aberto: {valor} " . sige_moeda() . "\nReferência/Data: {vencimento}\n\nSe já regularizou ou se houver alguma diferença, responda por aqui para a secretaria confirmar.\n\nObrigado.";
            }
        }
        
        // Preparar variáveis
        $primeiro_nome = '';
        if (!empty($aluno_row->nome_completo)) {
            $primeiro_nome = explode(' ', trim((string)$aluno_row->nome_completo))[0];
        }
        
        $encarregado = '';
        if (!empty($dados_variaveis['encarregado'])) {
            $encarregado = (string)$dados_variaveis['encarregado'];
        } elseif (!empty($aluno_row->contacto_encarregado)) {
            $encarregado = (string)$aluno_row->contacto_encarregado;
        } elseif (!empty($aluno_row->nome_pai)) {
            $encarregado = (string)$aluno_row->nome_pai;
        } elseif (!empty($aluno_row->nome_mae)) {
            $encarregado = (string)$aluno_row->nome_mae;
        } else {
            $encarregado = 'Encarregado de Educação';
        }
        
        $valor = isset($dados_variaveis['valor']) ? (float)$dados_variaveis['valor'] : 0.0;
        
        $venc_raw = $dados_variaveis['vencimento'] ?? null;
        $venc_fmt = '';
        if (!empty($venc_raw)) {
            $ts = strtotime((string)$venc_raw);
            $venc_fmt = $ts ? wp_date('d/m/Y', $ts) : (string)$venc_raw;
        }
        
        $mes = (string)($dados_variaveis['mes'] ?? wp_date('M'));
        $id  = (string)($dados_variaveis['id'] ?? ($dados_variaveis['recibo_id'] ?? '000'));
        $servico = (string)($dados_variaveis['servico'] ?? '');
        
        $escola_nome = get_bloginfo('name');
        if (function_exists('sige_get_escola_perfil')) {
            $perfil = sige_get_escola_perfil();
            if (!empty($perfil->nome_escola)) {
                $escola_nome = $perfil->nome_escola;
            }
        }
        
        $vars = [
            '{aluno}' => $primeiro_nome,
            '{encarregado}' => $encarregado,
            '{valor}' => number_format($valor, 2, '.', ''),
            '{vencimento}' => $venc_fmt,
            '{mes}' => $mes,
            '{id}' => $id,
            '{servico}' => $servico,
            '{detalhes}' => (string)($dados_variaveis['detalhes'] ?? ''),
            '{escola}' => $escola_nome,
            '{link}' => !empty($dados_variaveis['link']) ? ("Comprovativo digital: " . $dados_variaveis['link']) : '',
            '{aluno_completo}' => (string)($aluno_row->nome_completo ?? $primeiro_nome),
        ];
        
        foreach ($vars as $k => $v) {
            $texto = str_replace($k, (string)$v, $texto);
        }
        
        // Anexar link se não foi incluído
        if (!empty($dados_variaveis['link']) && strpos($texto, (string)$dados_variaveis['link']) === false) {
            $texto .= "\n\nComprovativo digital: " . $dados_variaveis['link'];
        }
        
        if (function_exists('sige_notify_humanize_outbound_whatsapp')) {
            $texto = sige_notify_humanize_outbound_whatsapp($texto, $tipo_mensagem, [
                'aluno_id' => isset($aluno_row->id) ? (int)$aluno_row->id : 0,
            ]);
        }
        return $texto;
    }
}

// ============================================================================
// ENVIAR WHATSAPP (função principal)
// ============================================================================
if (!function_exists('sige_enviar_whatsapp')) {
    function sige_enviar_whatsapp($aluno_id, $tipo_mensagem, $dados_variaveis = []) {
        global $wpdb;

        // v12.9.53 - Guard anti-cron: tipos financeiros só com origem humana.
        // Defesa em profundidade caso algum hook futuro chame esta função
        // a partir de wp_cron sem passar pelo motor central.
        if (function_exists('sige_wpp_tpl_block_cron_origin')
            && sige_wpp_tpl_block_cron_origin((string)$tipo_mensagem, (int)$aluno_id)) {
            return false;
        }

        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        $config = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1", $eid));
        if (empty($config) || empty($config->whatsapp_url) || empty($config->whatsapp_token)) {
            return false;
        }
        
        $aluno = $wpdb->get_row($wpdb->prepare("
            SELECT id, nome_completo, whatsapp_notificacoes, telemovel_pai, telemovel_mae, 
                   contacto_encarregado, nome_pai, nome_mae
            FROM {$wpdb->prefix}sige_alunos
            WHERE id=%d AND escola_id=%d
            LIMIT 1
        ", (int)$aluno_id, $eid));
        
        if (!$aluno) return false;
        
        $numero_raw = $aluno->whatsapp_notificacoes ?: ($aluno->telemovel_pai ?: $aluno->telemovel_mae);
        $numero = preg_replace('/[^0-9]/', '', $numero_raw);
        if (!$numero) return false;
        $numero = sige_telefone_normalizar($numero);

        // Quando se usa o número genérico de notificações, identificar pelo
        // próprio número se o destinatário é pai ou mãe. Isto evita tratar a
        // mãe pelo nome/título do pai quando ambos estão cadastrados.
        if (empty($dados_variaveis['encarregado']) && function_exists('sige_wpp_policy_identificar_por_numero')) {
            $r = sige_wpp_policy_identificar_por_numero($aluno, $numero_raw, 'encarregado', 'Encarregado de Educação');
            $dados_variaveis['encarregado'] = $r['nome'] ?? '';
            $dados_variaveis['encarregado_tipo'] = $r['tipo'] ?? '';
        }
        
        $texto = sige_wpp_render_finance_template($tipo_mensagem, $config, $aluno, $dados_variaveis);

        // v12.9.31 - Segurança WhatsApp: nunca enviar directo por esta função.
        // Tudo entra na fila protegida pelo Guardian/Recovery Mode.
        if (function_exists('sige_fin_queue_whatsapp')) {
            return sige_fin_queue_whatsapp((int)$aluno_id, $numero, (string)$tipo_mensagem, $texto);
        }

        return false;
    }
}
