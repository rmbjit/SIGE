<?php
/**
 * SIGE SoftGenial - Notificações aos Encarregados (dual-send)
 * Ficheiro: includes/notificacoes-encarregados.php
 *
 * Generaliza o padrão dual-send (pai + mãe) introduzido no módulo de Alertas
 * para TODAS as comunicações financeiras com os encarregados de educação:
 * lançamentos, recibos, cobranças, lembretes, etc.
 *
 * ┌────────────────────────────────────────────────────────────────────┐
 * │  Política da Casa Colorida:                                        │
 * │   "Ambos os encarregados devem saber o que se passa                │
 * │    financeiramente com o aluno."                                   │
 * │                                                                    │
 * │  Implementação:                                                    │
 * │   • Todos os contactos válidos do pai E da mãe recebem             │
 * │     (telemovel_pai, telemovel_mae, email_pai, email_mae)           │
 * │   • Fallbacks para encarregado único: whatsapp_notificacoes,       │
 * │     contacto_encarregado, email_encarregado                        │
 * │   • Deduplicação por número normalizado / email em lowercase       │
 * │   • Render personalizado por destinatário ({encarregado})          │
 * └────────────────────────────────────────────────────────────────────┘
 *
 * API pública:
 *   • sige_encarregados_contactos(int $aluno_id): array
 *       Devolve ['whatsapp' => [...], 'email' => [...]]
 *
 *   • sige_fin_wpp_notificar_encarregados(int $aluno_id, string $tipo, array $dados): array
 *       Envia WhatsApp para todos os contactos válidos.
 *       Devolve ['enviados' => N, 'falhas' => M, 'detalhes' => [...]]
 *
 *   • sige_fin_email_notificar_encarregados(int $aluno_id, string $tipo, string $det, float $total, string $venc = ''): array
 *       Envia e-mail para todos os emails válidos.
 *       Devolve ['enviados' => N, 'falhas' => M, 'detalhes' => [...]]
 *
 * @since   13.5.1
 * @version 12.9.69 (2026-05-05)
 * @author  RMBJ Consultoria
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// CONTACTOS - lista dedulplicada de todos os canais válidos do aluno
// ============================================================================
/**
 * Devolve todos os contactos de encarregado disponíveis para um aluno.
 *
 * Fontes por ordem de preferência visual (mas TODAS são tentadas, não só a primeira):
 *
 * WhatsApp:
 *   1. telemovel_pai      (+ nome_pai)
 *   2. telemovel_mae      (+ nome_mae)
 *   3. whatsapp_notificacoes  (fallback - aluno antigo sem discriminação pai/mãe)
 *   4. contacto_encarregado   (fallback final)
 *
 * E-mail:
 *   1. email_pai   (+ nome_pai)
 *   2. email_mae   (+ nome_mae)
 *   3. email_encarregado     (fallback)
 *
 * Deduplicação: números normalizados com sige_telefone_normalizar e
 * emails em lowercase. Evita envio duplicado quando por ex.
 * whatsapp_notificacoes == telemovel_pai.
 *
 * @param int $aluno_id
 * @return array { whatsapp: [{tipo, nome, numero}], email: [{tipo, nome, email}] }
 */
if (!function_exists('sige_encarregados_contactos')) {
    function sige_encarregados_contactos(int $aluno_id): array {
        global $wpdb;
        $out = ['whatsapp' => [], 'email' => []];
        if ($aluno_id <= 0) return $out;

        $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;

        $aluno = $wpdb->get_row($wpdb->prepare(
            "SELECT *
             FROM {$wpdb->prefix}sige_alunos
             WHERE id = %d AND escola_id = %d
             LIMIT 1",
            $aluno_id, $escola_id
        ));
        if (!$aluno) return $out;

        $allow_whatsapp = function_exists('sige_guardian_adv_bool')
            ? sige_guardian_adv_bool($aluno, 'consent_whatsapp', true)
            : (!property_exists($aluno, 'consent_whatsapp') || (int)$aluno->consent_whatsapp === 1);
        $allow_email = function_exists('sige_guardian_adv_bool')
            ? sige_guardian_adv_bool($aluno, 'consent_email', true)
            : (!property_exists($aluno, 'consent_email') || (int)$aluno->consent_email === 1);

        // ── WhatsApp ─────────────────────────────────────────────────────
        // [v12.11.9.49] Além da política global, respeita consentimento explícito
        // da ficha avançada. Ausência da coluna mantém comportamento legado.
        if (!$allow_whatsapp) {
            $out['whatsapp'] = [];
        } elseif (function_exists('sige_wpp_contactos_whatsapp_aluno_row')) {
            $out['whatsapp'] = sige_wpp_contactos_whatsapp_aluno_row($aluno);
        } else {
            // Fallback legado, preservado defensivamente caso o helper não carregue.
            $vistos_tel = [];
            $notif = function_exists('sige_wpp_policy_identificar_por_numero')
                ? sige_wpp_policy_identificar_por_numero($aluno, (string)$aluno->whatsapp_notificacoes, 'whatsapp_notificacoes', 'Encarregado de Educação')
                : ['tipo' => 'encarregado', 'nome' => 'Encarregado de Educação'];
            $enc = function_exists('sige_wpp_policy_identificar_por_numero')
                ? sige_wpp_policy_identificar_por_numero($aluno, (string)$aluno->contacto_encarregado, 'contacto_encarregado', 'Encarregado de Educação')
                : ['tipo' => 'encarregado', 'nome' => 'Encarregado de Educação'];
            $candidatos_wa = [
                ['tipo' => 'pai',          'nome' => (string)$aluno->nome_pai, 'raw' => (string)$aluno->telemovel_pai],
                ['tipo' => 'mae',          'nome' => (string)$aluno->nome_mae, 'raw' => (string)$aluno->telemovel_mae],
                ['tipo' => (string)$notif['tipo'], 'nome' => (string)$notif['nome'], 'raw' => (string)$aluno->whatsapp_notificacoes],
                ['tipo' => (string)$enc['tipo'],   'nome' => (string)$enc['nome'],   'raw' => (string)$aluno->contacto_encarregado],
            ];
            foreach ($candidatos_wa as $c) {
                $raw = trim($c['raw']);
                if ($raw === '') continue;
                $num = function_exists('sige_telefone_normalizar')
                    ? sige_telefone_normalizar($raw)
                    : preg_replace('/[^0-9]/', '', $raw);
                if (!$num || strlen($num) < 9) continue;
                if (isset($vistos_tel[$num])) continue;  // dedup
                $vistos_tel[$num] = true;
                $out['whatsapp'][] = [
                    'tipo'   => $c['tipo'],
                    'nome'   => $c['nome'] ?: 'Encarregado(a)',
                    'numero' => $num,
                ];
            }
        }

        if ($allow_whatsapp) {
            $vistos_tel_adv = [];
            foreach ((array)$out['whatsapp'] as $row) {
                $num0 = function_exists('sige_telefone_normalizar') ? sige_telefone_normalizar((string)($row['numero'] ?? '')) : preg_replace('/[^0-9]/', '', (string)($row['numero'] ?? ''));
                if ($num0) $vistos_tel_adv[$num0] = true;
            }
            $principal_tipo_adv = (string)($aluno->encarregado_principal_tipo ?? '');
            $candidatos_adv = [
                ['tipo' => 'pai', 'nome' => (string)($aluno->nome_pai ?? ''), 'raw' => (string)($aluno->telemovel_pai_2 ?? '')],
                ['tipo' => 'mae', 'nome' => (string)($aluno->nome_mae ?? ''), 'raw' => (string)($aluno->telemovel_mae_2 ?? '')],
            ];
            if ($principal_tipo_adv === 'outro') {
                $candidatos_adv[] = ['tipo' => 'encarregado', 'nome' => (string)($aluno->encarregado_principal_nome ?? 'Encarregado(a)'), 'raw' => (string)($aluno->encarregado_principal_telemovel ?? '')];
            }
            // [v12.11.9.51] Smoke hardening / privacidade:
            // contacto_alternativo e pessoa autorizada a buscar NÃO recebem notificações financeiras por defeito.
            // Esses contactos ficam disponíveis na Ficha 360º para operação/emergência, mas não entram no dual-send financeiro
            // sem uma autorização/scope específico em fase futura.
            foreach ($candidatos_adv as $c) {
                $raw = trim((string)$c['raw']);
                if ($raw === '') continue;
                $num = function_exists('sige_telefone_normalizar') ? sige_telefone_normalizar($raw) : preg_replace('/[^0-9]/', '', $raw);
                if (!$num || strlen($num) < 9) continue;
                if (isset($vistos_tel_adv[$num])) continue;
                $vistos_tel_adv[$num] = true;
                $out['whatsapp'][] = [
                    'tipo' => $c['tipo'],
                    'nome' => $c['nome'] ?: 'Encarregado(a)',
                    'numero' => $num,
                ];
            }
        }

        // ── E-mail ───────────────────────────────────────────────────────
        if ($allow_email) {
            $vistos_email = [];
            $candidatos_em = [
                ['tipo' => 'pai',         'nome' => (string)($aluno->nome_pai ?? ''), 'raw' => (string)($aluno->email_pai ?? '')],
                ['tipo' => 'mae',         'nome' => (string)($aluno->nome_mae ?? ''), 'raw' => (string)($aluno->email_mae ?? '')],
                ['tipo' => 'encarregado', 'nome' => (string)(($aluno->nome_pai ?? '') ?: ($aluno->nome_mae ?? '') ?: ''),
                 'raw' => (string)($aluno->email_encarregado ?? '')],
            ];
            if ((string)($aluno->encarregado_principal_tipo ?? '') === 'outro') {
                $candidatos_em[] = ['tipo' => 'encarregado', 'nome' => (string)($aluno->encarregado_principal_nome ?? 'Encarregado(a)'), 'raw' => (string)($aluno->encarregado_principal_email ?? '')];
            }
            foreach ($candidatos_em as $c) {
                $email = strtolower(trim((string)$c['raw']));
                if ($email === '' || !is_email($email)) continue;
                if (isset($vistos_email[$email])) continue; // dedup
                $vistos_email[$email] = true;
                $out['email'][] = [
                    'tipo'  => $c['tipo'],
                    'nome'  => $c['nome'] ?: 'Encarregado(a)',
                    'email' => $email,
                ];
            }
        }

        return $out;
    }
}

// ============================================================================
// CONTAGEM - quantos contactos únicos tem este aluno
// ============================================================================
// Útil para diagnóstico: "aluno X tem 2 WA + 1 email = 3 canais".
// ============================================================================
if (!function_exists('sige_encarregados_contar_canais')) {
    function sige_encarregados_contar_canais(int $aluno_id): array {
        $c = sige_encarregados_contactos($aluno_id);
        return [
            'whatsapp' => count($c['whatsapp']),
            'email'    => count($c['email']),
            'total'    => count($c['whatsapp']) + count($c['email']),
        ];
    }
}

// ============================================================================
// DUAL-SEND WhatsApp - envia para TODOS os contactos WA do aluno
// ============================================================================
/**
 * Envia uma mensagem WhatsApp a TODOS os encarregados do aluno.
 *
 * A mensagem é renderizada via sige_wpp_render_finance_template com o nome
 * de cada destinatário específico em {encarregado}, garantindo personalização
 * real ("Exmo João" vs "Exma Maria").
 *
 * Usa sige_fin_queue_whatsapp() por destinatário - herda comportamento de
 * envio imediato + fallback queue + retry automático.
 *
 * @param int    $aluno_id
 * @param string $tipo              'fatura' | 'recibo' | 'cobranca' | 'lembrete'
 * @param array  $dados_variaveis   Placeholders do template (valor, vencimento, etc.)
 * @return array ['enviados' => int, 'falhas' => int, 'detalhes' => [...]]
 */
if (!function_exists('sige_fin_wpp_notificar_encarregados')) {
    function sige_fin_wpp_notificar_encarregados(
        int $aluno_id,
        string $tipo,
        array $dados_variaveis = []
    ): array {
        global $wpdb;
        $resultado = ['enviados' => 0, 'falhas' => 0, 'detalhes' => []];

        if ($aluno_id <= 0) return $resultado;

        $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;

        // Pré-requisito: sige_fin_queue_whatsapp tem de existir
        if (!function_exists('sige_fin_queue_whatsapp')) {
            $resultado['detalhes'][] = ['erro' => 'sige_fin_queue_whatsapp indisponível'];
            return $resultado;
        }

        $contactos = sige_encarregados_contactos($aluno_id);
        if (empty($contactos['whatsapp'])) {
            $resultado['detalhes'][] = ['erro' => 'Aluno sem números WhatsApp válidos'];
            return $resultado;
        }

        // Carregar config uma vez (não por destinatário)
        $cfg_wpp = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1",
            $escola_id
        ));

        // Linha completa do aluno para o renderer (tem nome_completo, etc.)
        $aluno_row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sige_alunos WHERE id = %d AND escola_id = %d LIMIT 1",
            $aluno_id, $escola_id
        ));

        foreach ($contactos['whatsapp'] as $c) {
            // Renderizar mensagem com nome do destinatário actual em {encarregado}
            $vars_personalizadas = $dados_variaveis;
            $vars_personalizadas['encarregado'] = $c['nome'];
            $vars_personalizadas['encarregado_tipo'] = $c['tipo'] ?? '';

            if (function_exists('sige_wpp_render_finance_template') && $cfg_wpp && $aluno_row) {
                $msg = sige_wpp_render_finance_template($tipo, $cfg_wpp, $aluno_row, $vars_personalizadas);

                // [v12.9.58] Normaliza saudações remanescentes em templates LEGACY
                // guardados em BD pela escola (msg_recibo_pago/msg_nova_fatura/
                // msg_cobranca). Substitui "Encarregado(a)" pelo nome do
                // encarregado registado no formulário do aluno, ou pelo
                // fallback "Encarregado de Educação" quando vazio.
                if (function_exists('sige_notify_normalize_saudacao_encarregado')) {
                    $msg = sige_notify_normalize_saudacao_encarregado($msg, (int)$aluno_id);
                }
            } else {
                // Fallback minimalista - não deveria ocorrer em produção
                $tratamento = function_exists('sige_notify_human_treatment')
                    ? sige_notify_human_treatment((string)($c['nome'] ?? ''))
                    : (($c['nome'] ?? '') ?: 'Encarregado(a)');
                $msg = sprintf(
                    '%s, %s. A secretaria da escola tem uma informação sobre %s. Pode responder por aqui se precisar de algum esclarecimento.',
                    function_exists('sige_notify_human_mz_saudacao') ? sige_notify_human_mz_saudacao() : 'Bom dia',
                    $tratamento,
                    $tipo
                );
            }

            // [v12.9.61] AUTO-RESPONDER opt-in: para mensagens de RECIBO, regista
            // o link como "pending_receipt" (transient 48h) ANTES de enfileirar
            // a mensagem com a pergunta. Quando o encarregado responder
            // "sim/quero/pode/ok/recibo/pago/aceito/autorizo", o webhook
            // (sige_wpp_webhook_handler) usa este transient para enfileirar
            // automaticamente o link com delay humano (90-420s) e variação
            // natural de aberturas. Funciona para pai E mãe (cada telefone
            // tem o seu pending_receipt independente).
            if (
                $tipo === 'recibo'
                && function_exists('sige_wpp_store_pending_receipt')
                && !empty($dados_variaveis['link_recibo_pendente'])
            ) {
                sige_wpp_store_pending_receipt(
                    $escola_id,
                    $c['numero'],
                    $aluno_id,
                    [(string)$dados_variaveis['link_recibo_pendente']],
                    $msg
                );
            }

            $ok = sige_fin_queue_whatsapp($aluno_id, $c['numero'], $tipo, $msg);

            if ($ok) {
                $resultado['enviados']++;
            } else {
                $resultado['falhas']++;
            }
            $resultado['detalhes'][] = [
                'canal'   => 'whatsapp',
                'tipo'    => $c['tipo'],
                'numero'  => $c['numero'],
                'status'  => $ok ? 'sucesso' : 'falha',
            ];
        }

        // Log consolidado (opcional; não intrusivo)
        if (function_exists('sige_fin_log') && ($resultado['enviados'] + $resultado['falhas']) > 0) {
            sige_fin_log('wpp_dual_send', [
                'aluno_id'  => $aluno_id,
                'tipo'      => $tipo,
                'enviados'  => $resultado['enviados'],
                'falhas'    => $resultado['falhas'],
            ]);
        }

        return $resultado;
    }
}

// ============================================================================
// DUAL-SEND E-mail - envia para TODOS os emails do aluno
// ============================================================================
/**
 * Envia e-mail financeiro a TODOS os encarregados do aluno.
 *
 * Internamente chama sige_enviar_email_financeiro() passando o email alvo
 * explícito. Reutiliza todo o layout HTML já existente (cabeçalho com cor
 * por tipo, tabela de detalhes, rodapé com nome da escola).
 *
 * @param int    $aluno_id
 * @param string $tipo       'cobranca' | 'fatura' | 'lembrete' | 'recibo'
 * @param string $detalhes   Texto com linhas de detalhe (\n para separar)
 * @param float  $total
 * @param string $vencimento Y-m-d opcional
 * @return array ['enviados' => int, 'falhas' => int, 'detalhes' => [...]]
 */
if (!function_exists('sige_fin_email_notificar_encarregados')) {
    function sige_fin_email_notificar_encarregados(
        int $aluno_id,
        string $tipo,
        string $detalhes,
        float $total,
        string $vencimento = ''
    ): array {
        $resultado = ['enviados' => 0, 'falhas' => 0, 'detalhes' => []];

        if ($aluno_id <= 0) return $resultado;

        if (!function_exists('sige_enviar_email_financeiro_para')) {
            $resultado['detalhes'][] = ['erro' => 'sige_enviar_email_financeiro_para indisponível'];
            return $resultado;
        }

        $contactos = sige_encarregados_contactos($aluno_id);
        if (empty($contactos['email'])) {
            $resultado['detalhes'][] = ['erro' => 'Aluno sem e-mails válidos'];
            return $resultado;
        }

        foreach ($contactos['email'] as $c) {
            $ok = sige_enviar_email_financeiro_para(
                $aluno_id,
                $tipo,
                $detalhes,
                $total,
                $vencimento,
                $c['email'],
                $c['nome']
            );

            if ($ok) {
                $resultado['enviados']++;
            } else {
                $resultado['falhas']++;
            }
            $resultado['detalhes'][] = [
                'canal'   => 'email',
                'tipo'    => $c['tipo'],
                'email'   => $c['email'],
                'status'  => $ok ? 'sucesso' : 'falha',
            ];
        }

        if (function_exists('sige_fin_log') && ($resultado['enviados'] + $resultado['falhas']) > 0) {
            sige_fin_log('email_dual_send', [
                'aluno_id'  => $aluno_id,
                'tipo'      => $tipo,
                'enviados'  => $resultado['enviados'],
                'falhas'    => $resultado['falhas'],
            ]);
        }

        return $resultado;
    }
}

// ============================================================================
// DUAL-SEND E-mail RECIBO - variante específica para recibos
// ============================================================================
/**
 * Envia recibo por e-mail a TODOS os encarregados (usa sige_enviar_recibo_email
 * internamente, que já tem layout específico de recibo).
 *
 * @return array ['enviados' => int, 'falhas' => int, 'detalhes' => [...]]
 */
if (!function_exists('sige_fin_email_notificar_recibo_encarregados')) {
    function sige_fin_email_notificar_recibo_encarregados(
        int $aluno_id,
        string $recibo,
        float $total,
        int $qtd_pagos = 1
    ): array {
        $resultado = ['enviados' => 0, 'falhas' => 0, 'detalhes' => []];

        if ($aluno_id <= 0) return $resultado;

        if (!function_exists('sige_enviar_recibo_email_para')) {
            $resultado['detalhes'][] = ['erro' => 'sige_enviar_recibo_email_para indisponível'];
            return $resultado;
        }

        $contactos = sige_encarregados_contactos($aluno_id);
        if (empty($contactos['email'])) {
            $resultado['detalhes'][] = ['erro' => 'Aluno sem e-mails válidos'];
            return $resultado;
        }

        foreach ($contactos['email'] as $c) {
            $ok = sige_enviar_recibo_email_para(
                $aluno_id,
                $recibo,
                $total,
                $qtd_pagos,
                $c['email'],
                $c['nome']
            );

            if ($ok) {
                $resultado['enviados']++;
            } else {
                $resultado['falhas']++;
            }
            $resultado['detalhes'][] = [
                'canal'   => 'email',
                'tipo'    => $c['tipo'],
                'email'   => $c['email'],
                'status'  => $ok ? 'sucesso' : 'falha',
            ];
        }

        if (function_exists('sige_fin_log') && ($resultado['enviados'] + $resultado['falhas']) > 0) {
            sige_fin_log('email_recibo_dual_send', [
                'aluno_id'  => $aluno_id,
                'recibo'    => $recibo,
                'enviados'  => $resultado['enviados'],
                'falhas'    => $resultado['falhas'],
            ]);
        }

        return $resultado;
    }
}
