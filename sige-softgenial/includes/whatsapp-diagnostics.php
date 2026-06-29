<?php
/**
 * SIGE SoftGenial - WhatsApp Diagnostics (AJAX handlers)
 * Ficheiro: includes/whatsapp-diagnostics.php
 *
 * Suporta a página admin/whatsapp_diag-view.php - fornece:
 *   - sige_wpp_diag_status      → liga ao /status da Z-API
 *   - sige_wpp_diag_force_cron  → corre o handler do cron imediatamente
 *
 * @since 12.9.8.3
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// HELPER: derivar URL de /status a partir do URL de /send-text
// ============================================================================
if (!function_exists('sige_wpp_diag_derivar_status_url')) {
    function sige_wpp_diag_derivar_status_url(string $send_text_url): string {
        // Z-API: https://api.z-api.io/instances/{INST}/token/{TOK}/send-text
        // Status: https://api.z-api.io/instances/{INST}/token/{TOK}/status
        $url = trim($send_text_url);
        if ($url === '') return '';
        // Remove trailing /send-text (ou outros endpoints comuns)
        $url = preg_replace('#/(send-text|send-message|send-messages|send|send-image|send-document)/?$#i', '/status', $url);
        return $url;
    }
}

// ============================================================================
// AJAX: VERIFICAR SESSÃO Z-API
// ============================================================================
add_action('wp_ajax_sige_wpp_diag_status', 'sige_wpp_diag_status');
if (!function_exists('sige_wpp_diag_status')) {
    function sige_wpp_diag_status() {
        if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
            && !current_user_can('sige_director')
            && !current_user_can('sige_admin')
            && !current_user_can('sige_financeiro')
            && !current_user_can('sige_secretario')) {
            wp_send_json_error('Sem permissão.');
        }

        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!$nonce || !wp_verify_nonce($nonce, 'sige_wpp_diag')) {
            wp_send_json_error('Nonce inválido. Recarregue a página.');
        }

        global $wpdb;
        $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
        $cfg = $wpdb->get_row($wpdb->prepare(
            "SELECT whatsapp_url, whatsapp_token FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1",
            $eid
        ));
        if (!$cfg || empty($cfg->whatsapp_url)) {
            wp_send_json_error('Sem configuração WhatsApp para esta escola.');
        }

        $token = function_exists('sige_decrypt_token')
            ? sige_decrypt_token($cfg->whatsapp_token)
            : $cfg->whatsapp_token;

        $status_url = sige_wpp_diag_derivar_status_url($cfg->whatsapp_url);
        if ($status_url === '') {
            wp_send_json_error('URL inválido na configuração.');
        }

        $headers = ['Content-Type' => 'application/json'];
        if (!empty($token)) {
            $headers['Client-Token']  = $token;
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_get($status_url, [
            'headers' => $headers,
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error('Erro de ligação: ' . $response->get_error_message());
        }

        $http = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        // Z-API /status devolve (entre outros):
        //  {"connected":true,"session":"...","smartphoneConnected":true}
        //  {"connected":false,"error":"You are already disconnected"}
        $decoded   = json_decode($body, true);
        $connected = null;
        if (is_array($decoded)) {
            if (array_key_exists('connected', $decoded)) {
                $connected = (bool) $decoded['connected'];
            } elseif (array_key_exists('isConnected', $decoded)) {
                $connected = (bool) $decoded['isConnected'];
            }
        }

        wp_send_json_success([
            'connected' => $connected,
            'http'      => $http,
            'url'       => $status_url,
            'body'      => $body,
        ]);
    }
}

// ============================================================================
// AJAX: FORÇAR PROCESSAMENTO DA QUEUE
// ============================================================================
add_action('wp_ajax_sige_wpp_diag_force_cron', 'sige_wpp_diag_force_cron');
if (!function_exists('sige_wpp_diag_force_cron')) {
    function sige_wpp_diag_force_cron() {
        if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
            && !current_user_can('sige_director')
            && !current_user_can('sige_admin')
            && !current_user_can('sige_financeiro')
            && !current_user_can('sige_secretario')) {
            wp_send_json_error('Sem permissão.');
        }

        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!$nonce || !wp_verify_nonce($nonce, 'sige_wpp_diag')) {
            wp_send_json_error('Nonce inválido. Recarregue a página.');
        }

        global $wpdb;
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';

        // Snapshot ANTES do processamento
        $antes = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tQ} WHERE status='pendente' AND tentativas < 3"
        );

        // Disparar o handler do cron registado em cron-tasks.php
        do_action('sige_processar_whatsapp_queue');

        // Snapshot DEPOIS
        $depois_pendentes = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tQ} WHERE status='pendente' AND tentativas < 3"
        );
        $enviadas_recentes = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tQ}
              WHERE status='enviado' AND enviado_em >= DATE_SUB(NOW(), INTERVAL 60 SECOND)"
        );
        $falhas_recentes = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tQ}
              WHERE status IN ('falhou','pendente') AND tentativas > 0
                AND criado_em >= DATE_SUB(NOW(), INTERVAL 60 SECOND)"
        );

        $processadas = max(0, (int)$antes - $depois_pendentes) + $falhas_recentes;

        wp_send_json_success([
            'antes_pendentes'  => (int) $antes,
            'depois_pendentes' => $depois_pendentes,
            'processadas'      => $processadas,
            'enviadas'         => $enviadas_recentes,
            'falhas'           => $falhas_recentes,
        ]);
    }
}

// ============================================================================
// AJAX: TESTE REAL CONTROLADO DE ENVIO WHATSAPP
// ----------------------------------------------------------------------------
// Envia uma mensagem curta para um número indicado pelo administrador usando a
// configuração activa da escola (whatsapp_url + whatsapp_token) e o mesmo
// cliente HTTP/validação semântica usado pelo motor da fila: sige_wpp_post_json.
//
// Importante:
// - Não cria cobrança, recibo, lançamento, mensalidade ou movimento financeiro.
// - Não altera a fila normal nem a cadência anti-restrição.
// - Serve para confirmar conectividade real com a Z-API e recepção no telefone.
// ==========================================================================
if (!function_exists('sige_wpp_diag_user_can')) {
    function sige_wpp_diag_user_can(): bool {
        if ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) return true;
        if (class_exists('SIGE_Settings_Policy') && !SIGE_Settings_Policy::is_technical_mode_active()) {
            return false;
        }
        if (function_exists('sige_page_guard_allows')) {
            return (bool) sige_page_guard_allows(['configuracoes.editar'], []);
        }
        return current_user_can('sige_admin');
    }
}

if (!function_exists('sige_wpp_diag_mask_phone')) {
    function sige_wpp_diag_mask_phone(string $phone): string {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $len = strlen($phone);
        if ($len <= 6) return str_repeat('•', max(0, $len));
        return substr($phone, 0, 3) . str_repeat('•', max(0, $len - 6)) . substr($phone, -3);
    }
}

add_action('wp_ajax_sige_wpp_diag_send_test', 'sige_wpp_diag_send_test');
if (!function_exists('sige_wpp_diag_send_test')) {
    function sige_wpp_diag_send_test() {
        if (!sige_wpp_diag_user_can()) {
            wp_send_json_error(['message' => 'Sem permissão.'], 403);
        }

        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!$nonce || !wp_verify_nonce($nonce, 'sige_wpp_diag')) {
            wp_send_json_error(['message' => 'Nonce inválido. Recarregue a página.'], 403);
        }

        if (!function_exists('sige_wpp_post_json')) {
            wp_send_json_error(['message' => 'Motor WhatsApp indisponível: função sige_wpp_post_json não carregada.'], 500);
        }

        global $wpdb;
        $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
        $cfg = $wpdb->get_row($wpdb->prepare(
            "SELECT whatsapp_url, whatsapp_token FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1",
            $eid
        ));
        if (!$cfg || empty($cfg->whatsapp_url) || empty($cfg->whatsapp_token)) {
            wp_send_json_error(['message' => 'Sem configuração WhatsApp completa para esta escola. Confirme URL e token.'], 400);
        }

        $raw_phone = sanitize_text_field(wp_unslash($_POST['numero'] ?? ''));
        $phone = function_exists('sige_wpp_normalizar_numero')
            ? sige_wpp_normalizar_numero($raw_phone)
            : preg_replace('/[^0-9]/', '', $raw_phone);
        $phone = preg_replace('/[^0-9]/', '', (string)$phone);

        if (strlen($phone) < 8 || strlen($phone) > 18) {
            wp_send_json_error(['message' => 'Número inválido. Informe um número WhatsApp com indicativo do país, por exemplo 25884xxxxxxx.'], 400);
        }

        $custom_msg = isset($_POST['mensagem']) ? trim((string) wp_unslash($_POST['mensagem'])) : '';
        $custom_msg = wp_strip_all_tags($custom_msg);
        if ($custom_msg !== '') {
            $message = function_exists('mb_substr') ? mb_substr($custom_msg, 0, 700) : substr($custom_msg, 0, 700);
        } else {
            $escola_nome = get_bloginfo('name');
            if (function_exists('sige_get_escola_perfil')) {
                $perfil = sige_get_escola_perfil();
                if (!empty($perfil->nome_escola)) $escola_nome = (string) $perfil->nome_escola;
            }
            $message = "Teste de envio WhatsApp - " . $escola_nome . "\n"
                     . "Data/hora: " . wp_date('d/m/Y H:i:s') . "\n"
                     . "Se recebeu esta mensagem, o envio directo pela configuração activa está funcional.";
        }

        $token = function_exists('sige_decrypt_token') ? sige_decrypt_token($cfg->whatsapp_token) : $cfg->whatsapp_token;
        $started = microtime(true);
        $resp = sige_wpp_post_json($cfg->whatsapp_url, [
            'phone'   => $phone,
            'message' => $message,
        ], $token);
        $elapsed_ms = (int) round((microtime(true) - $started) * 1000);

        $body = (string)($resp['body'] ?? '');
        $decoded = json_decode($body, true);
        $message_id = '';
        if (is_array($decoded)) {
            foreach (['messageId', 'zaapId', 'id'] as $k) {
                if (!empty($decoded[$k])) { $message_id = (string)$decoded[$k]; break; }
            }
        }

        $payload = [
            'ok'          => !empty($resp['ok']),
            'http'        => (int)($resp['http_code'] ?? 0),
            'message_id'  => $message_id,
            'phone_mask'  => sige_wpp_diag_mask_phone($phone),
            'sent_at'     => wp_date('d/m/Y H:i:s'),
            'elapsed_ms'  => $elapsed_ms,
            'body_preview'=> function_exists('mb_substr') ? mb_substr($body, 0, 800) : substr($body, 0, 800),
            'error'       => (string)($resp['error'] ?? ''),
            'user'        => wp_get_current_user()->user_login,
        ];

        update_option('sige_wpp_last_send_test_' . $eid, $payload, false);

        if (function_exists('sige_fin_log')) {
            sige_fin_log('whatsapp_teste_envio_controlado', [
                'ok'         => $payload['ok'],
                'http'       => $payload['http'],
                'message_id' => $message_id,
                'telefone'   => $payload['phone_mask'],
                'elapsed_ms' => $elapsed_ms,
            ]);
        }

        if (!empty($resp['ok'])) {
            wp_send_json_success($payload);
        }

        $msg = $payload['error'] ?: ('Falha no envio. HTTP ' . $payload['http']);
        $payload['message'] = $msg;
        wp_send_json_error($payload, 200);
    }
}

