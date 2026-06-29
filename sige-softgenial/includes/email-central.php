<?php
/**
 * SIGE SoftGenial - Central de Comunicacoes: controlo da fila de E-MAIL
 * Ficheiro: includes/email-central.php
 *
 * Espelha o Central de WhatsApp (whatsapp-central.php) para o canal de e-mail,
 * com o mesmo nivel de rigor:
 *   - Multi-tenant: TODAS as queries filtram por escola_id (sige_get_escola_id()).
 *   - Nonce dedicado (sige_emc_nonce) em todos os endpoints.
 *   - Capability verificada em todos os endpoints.
 *   - Prepared statements em todas as queries.
 *   - Registo de auditoria (sige_fin_log) em cada accao de estado.
 *   - Nao toca em valor / aluno / lancamento - apenas no estado da fila e nos
 *     modelos de texto. Nenhuma regra financeira ou academica e alterada.
 *
 * Endpoints AJAX (wp_ajax_*):
 *   - sige_emc_cancel          pendente            -> cancelled
 *   - sige_emc_retry           failed|cancelled    -> pending (attempts=0, agora)
 *   - sige_emc_delete          sent|failed|cancelled -> elimina a linha
 *   - sige_emc_get_full        devolve assunto+corpo para pre-visualizacao
 *   - sige_emc_process_now     processa a fila imediatamente (limite seguro)
 *   - sige_emc_save_templates  grava os modelos de e-mail da escola
 *   - sige_emc_reset_template  repoe um modelo ao default (remove o override)
 *   - sige_emc_test_template   envia um e-mail de teste de um modelo
 */

if (!defined('ABSPATH')) exit;

/* ============================================================================
 * Seguranca / helpers
 * ========================================================================== */

if (!function_exists('sige_emc_user_can')) {
    function sige_emc_user_can(): bool {
        if (function_exists('sige_can') && sige_can('comunicacao.central_ver')) return true;
        return (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
            || current_user_can('sige_director')
            || current_user_can('sige_admin')
            || current_user_can('sige_admin_ti')
            || current_user_can('sige_financeiro')
            || current_user_can('sige_secretario')
            || current_user_can('sige_secretaria_geral');
    }
}

if (!function_exists('sige_emc_send_json_error')) {
    function sige_emc_send_json_error(string $msg, int $code = 400): void {
        wp_send_json_error(['message' => $msg], $code);
    }
}

if (!function_exists('sige_emc_check_nonce_or_die')) {
    function sige_emc_check_nonce_or_die(): void {
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field((string) wp_unslash($_POST['_wpnonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'sige_emc_nonce')) {
            sige_emc_send_json_error('Nonce inválido ou expirado. Recarregue a página.', 403);
        }
        if (!sige_emc_user_can()) {
            sige_emc_send_json_error('Sem permissão para esta operação.', 403);
        }
    }
}

if (!function_exists('sige_emc_eid')) {
    function sige_emc_eid(): int {
        return function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
    }
}

if (!function_exists('sige_emc_table')) {
    function sige_emc_table(): string {
        return function_exists('sige_email_queue_table')
            ? sige_email_queue_table()
            : $GLOBALS['wpdb']->prefix . 'sige_email_queue';
    }
}

// Carrega 1 linha da fila (validando escola_id) ou null se nao existe.
if (!function_exists('sige_emc_load_row')) {
    function sige_emc_load_row(int $id): ?object {
        global $wpdb;
        $t = sige_emc_table();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$t} WHERE id = %d AND escola_id = %d LIMIT 1",
            $id,
            sige_emc_eid()
        ));
        return $row ?: null;
    }
}

// Estatisticas por estado (escola actual). Usado pela vista.
if (!function_exists('sige_emc_stats')) {
    function sige_emc_stats(): array {
        global $wpdb;
        $t = sige_emc_table();
        $eid = sige_emc_eid();
        $out = ['pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0, 'total' => 0];
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) AS n FROM {$t} WHERE escola_id = %d GROUP BY status",
            $eid
        ));
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $st = (string) $r->status;
                if (isset($out[$st])) $out[$st] = (int) $r->n;
                $out['total'] += (int) $r->n;
            }
        }
        return $out;
    }
}

/* ============================================================================
 * AJAX: cancelar (so pendentes)
 * ========================================================================== */
add_action('wp_ajax_sige_emc_cancel', 'sige_emc_ajax_cancel');
if (!function_exists('sige_emc_ajax_cancel')) {
    function sige_emc_ajax_cancel() {
        sige_emc_check_nonce_or_die();
        global $wpdb;

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if ($id <= 0) sige_emc_send_json_error('ID inválido.');

        $row = sige_emc_load_row($id);
        if (!$row) sige_emc_send_json_error('E-mail não encontrado nesta escola.', 404);

        // So pendente. 'sending' esta a meio do envio (locked) e nao se interrompe.
        if ((string) $row->status !== 'pending') {
            sige_emc_send_json_error('Só é possível cancelar e-mails pendentes (estado actual: ' . esc_html((string) $row->status) . ').');
        }

        $ok = $wpdb->update(
            sige_emc_table(),
            [
                'status'     => 'cancelled',
                'last_error' => 'Cancelado manualmente por ' . wp_get_current_user()->user_login . ' em ' . current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id, 'escola_id' => sige_emc_eid()],
            ['%s', '%s', '%s'],
            ['%d', '%d']
        );
        if ($ok === false) sige_emc_send_json_error('Falha ao cancelar (BD).', 500);

        if (function_exists('sige_fin_log')) {
            sige_fin_log('emc_cancel', ['id' => $id, 'context' => $row->context, 'recipient' => $row->recipient]);
        }
        wp_send_json_success(['id' => $id, 'novo_status' => 'cancelled']);
    }
}

/* ============================================================================
 * AJAX: reenviar (falhado/cancelado -> pendente)
 * ========================================================================== */
add_action('wp_ajax_sige_emc_retry', 'sige_emc_ajax_retry');
if (!function_exists('sige_emc_ajax_retry')) {
    function sige_emc_ajax_retry() {
        sige_emc_check_nonce_or_die();
        global $wpdb;

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if ($id <= 0) sige_emc_send_json_error('ID inválido.');

        $row = sige_emc_load_row($id);
        if (!$row) sige_emc_send_json_error('E-mail não encontrado nesta escola.', 404);

        if (!in_array((string) $row->status, ['failed', 'cancelled'], true)) {
            sige_emc_send_json_error('Só é possível re-enviar e-mails falhados ou cancelados.');
        }

        $ok = $wpdb->update(
            sige_emc_table(),
            [
                'status'       => 'pending',
                'attempts'     => 0,
                'last_error'   => null,
                'locked_at'    => null,
                'scheduled_at' => current_time('mysql'),
                'updated_at'   => current_time('mysql'),
            ],
            ['id' => $id, 'escola_id' => sige_emc_eid()],
            ['%s', '%d', '%s', '%s', '%s', '%s'],
            ['%d', '%d']
        );
        if ($ok === false) sige_emc_send_json_error('Falha ao re-enviar (BD).', 500);

        if (function_exists('sige_fin_log')) {
            sige_fin_log('emc_retry', ['id' => $id, 'context' => $row->context, 'recipient' => $row->recipient]);
        }
        wp_send_json_success(['id' => $id, 'novo_status' => 'pending']);
    }
}

/* ============================================================================
 * AJAX: eliminar (so resolvidos)
 * ========================================================================== */
add_action('wp_ajax_sige_emc_delete', 'sige_emc_ajax_delete');
if (!function_exists('sige_emc_ajax_delete')) {
    function sige_emc_ajax_delete() {
        sige_emc_check_nonce_or_die();
        global $wpdb;

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if ($id <= 0) sige_emc_send_json_error('ID inválido.');

        $row = sige_emc_load_row($id);
        if (!$row) sige_emc_send_json_error('E-mail não encontrado nesta escola.', 404);

        if (!in_array((string) $row->status, ['sent', 'failed', 'cancelled'], true)) {
            sige_emc_send_json_error('Não é possível eliminar e-mails em curso. Cancele primeiro.');
        }

        $ok = $wpdb->delete(sige_emc_table(), ['id' => $id, 'escola_id' => sige_emc_eid()], ['%d', '%d']);
        if ($ok === false) sige_emc_send_json_error('Falha ao eliminar (BD).', 500);

        if (function_exists('sige_fin_log')) {
            sige_fin_log('emc_delete', ['id' => $id, 'context' => $row->context]);
        }
        wp_send_json_success(['id' => $id, 'eliminado' => true]);
    }
}

/* ============================================================================
 * AJAX: pre-visualizar (assunto + corpo)
 * ========================================================================== */
add_action('wp_ajax_sige_emc_get_full', 'sige_emc_ajax_get_full');
if (!function_exists('sige_emc_ajax_get_full')) {
    function sige_emc_ajax_get_full() {
        sige_emc_check_nonce_or_die();

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if ($id <= 0) sige_emc_send_json_error('ID inválido.');

        $row = sige_emc_load_row($id);
        if (!$row) sige_emc_send_json_error('E-mail não encontrado nesta escola.', 404);

        wp_send_json_success([
            'id'           => (int) $row->id,
            'status'       => (string) $row->status,
            'context'      => (string) $row->context,
            'recipient'    => (string) $row->recipient,
            'recipient_name' => (string) ($row->recipient_name ?? ''),
            'subject'      => (string) $row->subject,
            'body'         => wp_kses_post((string) $row->body),
            'attempts'     => (int) $row->attempts,
            'max_attempts' => (int) $row->max_attempts,
            'scheduled_at' => (string) $row->scheduled_at,
            'sent_at'      => (string) ($row->sent_at ?? ''),
            'last_error'   => (string) ($row->last_error ?? ''),
        ]);
    }
}

/* ============================================================================
 * AJAX: processar a fila agora
 * ========================================================================== */
add_action('wp_ajax_sige_emc_process_now', 'sige_emc_ajax_process_now');
if (!function_exists('sige_emc_ajax_process_now')) {
    function sige_emc_ajax_process_now() {
        sige_emc_check_nonce_or_die();

        if (!function_exists('sige_email_queue_process')) {
            sige_emc_send_json_error('Motor de fila de e-mail indisponível.', 500);
        }
        $res = sige_email_queue_process(15);
        if (function_exists('sige_fin_log')) {
            sige_fin_log('emc_process_now', is_array($res) ? $res : []);
        }
        wp_send_json_success(['resultado' => is_array($res) ? $res : [], 'stats' => sige_emc_stats()]);
    }
}

/* ============================================================================
 * AJAX: gravar modelos de e-mail
 * ========================================================================== */
add_action('wp_ajax_sige_emc_save_templates', 'sige_emc_ajax_save_templates');
if (!function_exists('sige_emc_ajax_save_templates')) {
    function sige_emc_ajax_save_templates() {
        sige_emc_check_nonce_or_die();

        if (!function_exists('sige_email_templates_save_from_request')) {
            sige_emc_send_json_error('Motor de modelos indisponível.', 500);
        }
        // sige_email_templates_save_from_request faz wp_unslash + sanitize/kses
        // e so aceita as chaves dos defaults (nao injecta chaves novas).
        $src = [
            'subject' => isset($_POST['subject']) && is_array($_POST['subject']) ? (array) $_POST['subject'] : [],
            'body'    => isset($_POST['body']) && is_array($_POST['body']) ? (array) $_POST['body'] : [],
        ];
        $out = sige_email_templates_save_from_request($src);

        if (function_exists('sige_fin_log')) {
            sige_fin_log('emc_save_templates', ['chaves' => array_keys($out)]);
        }
        wp_send_json_success(['guardado' => true, 'total' => count($out)]);
    }
}

/* ============================================================================
 * AJAX: repor um modelo ao default (remove o override gravado)
 * ========================================================================== */
add_action('wp_ajax_sige_emc_reset_template', 'sige_emc_ajax_reset_template');
if (!function_exists('sige_emc_ajax_reset_template')) {
    function sige_emc_ajax_reset_template() {
        sige_emc_check_nonce_or_die();

        $key = isset($_POST['key']) ? sanitize_key((string) wp_unslash($_POST['key'])) : '';
        $defaults = function_exists('sige_email_templates_default') ? sige_email_templates_default() : [];
        if ($key === '' || !isset($defaults[$key])) {
            sige_emc_send_json_error('Modelo inválido.');
        }

        $cfg = get_option('sige_email_templates_config', []);
        if (!is_array($cfg)) $cfg = [];
        unset($cfg[$key]);
        update_option('sige_email_templates_config', $cfg, false);

        if (function_exists('sige_fin_log')) {
            sige_fin_log('emc_reset_template', ['key' => $key]);
        }
        wp_send_json_success([
            'key'     => $key,
            'subject' => (string) $defaults[$key]['subject'],
            'body'    => (string) $defaults[$key]['body'],
        ]);
    }
}

/* ============================================================================
 * AJAX: enviar e-mail de teste de um modelo
 * ========================================================================== */
add_action('wp_ajax_sige_emc_test_template', 'sige_emc_ajax_test_template');
if (!function_exists('sige_emc_ajax_test_template')) {
    function sige_emc_ajax_test_template() {
        sige_emc_check_nonce_or_die();

        $key = isset($_POST['key']) ? sanitize_key((string) wp_unslash($_POST['key'])) : '';
        $to  = isset($_POST['to']) ? sanitize_email((string) wp_unslash($_POST['to'])) : '';
        if ($to === '' || !is_email($to)) {
            $u = wp_get_current_user();
            $to = is_object($u) && !empty($u->user_email) ? sanitize_email($u->user_email) : '';
        }
        if ($to === '' || !is_email($to)) sige_emc_send_json_error('Indique um e-mail de teste válido.');

        $defaults = function_exists('sige_email_templates_default') ? sige_email_templates_default() : [];
        if ($key === '' || !isset($defaults[$key])) sige_emc_send_json_error('Modelo inválido.');

        if (!function_exists('sige_email_enqueue_template')) {
            sige_emc_send_json_error('Motor de modelos indisponível.', 500);
        }

        // Variaveis de amostra para a pre-visualizacao real.
        $vars = [
            'nome_aluno'       => 'Aluno(a) de Teste',
            'primeiro_nome'    => 'Teste',
            'nome_encarregado' => 'Encarregado(a)',
            'valor'            => '500,00 MT',
            'descricao'        => '1 item pago',
            'recibo_numero'    => 'REC-TESTE',
            'mes'              => current_time('F'),
            'link_recibo'      => '<a href="' . esc_url(home_url('/')) . '">Ver e descarregar o recibo</a>',
        ];
        $qid = sige_email_enqueue_template($key, $to, $vars, [
            'context'      => 'teste_modelo',
            'priority'     => 1,
            'max_attempts' => 1,
        ]);
        if (!$qid) sige_emc_send_json_error('Não foi possível enfileirar o e-mail de teste.', 500);

        if (function_exists('sige_email_queue_process')) sige_email_queue_process(3);
        if (function_exists('sige_fin_log')) sige_fin_log('emc_test_template', ['key' => $key, 'to' => $to]);

        wp_send_json_success(['enviado' => true, 'to' => $to]);
    }
}

/* ============================================================================
 * Saude do e-mail: teste real de SMTP (envia um e-mail simples e reporta erro)
 * ========================================================================== */
add_action('wp_ajax_sige_emc_smtp_test', 'sige_emc_ajax_smtp_test');
if (!function_exists('sige_emc_ajax_smtp_test')) {
    function sige_emc_ajax_smtp_test() {
        sige_emc_check_nonce_or_die();
        $pode = function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options');
        if (!$pode) sige_emc_send_json_error('Apenas o administrador principal pode testar o SMTP.', 403);

        $to = isset($_POST['to']) ? sanitize_email((string) wp_unslash($_POST['to'])) : '';
        if ($to === '' || !is_email($to)) {
            $cur = wp_get_current_user();
            $to = $cur ? (string) $cur->user_email : '';
        }
        if ($to === '' || !is_email($to)) sige_emc_send_json_error('Indique um e-mail válido para o teste.');

        $erro = '';
        $cap = static function ($wp_error) use (&$erro) {
            if (is_wp_error($wp_error)) $erro = $wp_error->get_error_message();
        };
        add_action('wp_mail_failed', $cap);

        $cfg = function_exists('sige_smtp_get_config') ? sige_smtp_get_config() : [];
        $assunto = 'Teste de e-mail - SIGE SoftGenial';
        $corpo = "Esta é uma mensagem de teste da Central de Comunicações.\n\n"
               . "Se a recebeu, o envio de e-mail da escola está a funcionar correctamente.";
        $ok = wp_mail($to, $assunto, $corpo);

        remove_action('wp_mail_failed', $cap);

        if (function_exists('sige_fin_log')) sige_fin_log('emc_smtp_test', ['to' => $to, 'ok' => $ok ? 1 : 0]);
        if ($ok) wp_send_json_success(['to' => $to, 'from' => (string) ($cfg['from_email'] ?? '')]);
        sige_emc_send_json_error($erro !== '' ? $erro : 'O envio falhou. Verifique a configuração SMTP da escola.');
    }
}
