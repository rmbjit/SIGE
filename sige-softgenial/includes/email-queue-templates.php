<?php
/**
 * SIGE SoftGenial - Email Queue + Templates Inteligentes
 * Ficheiro: includes/email-queue-templates.php
 *
 * v12.9.42 - Motor de templates + fila profissional de e-mail.
 * - Não substitui o SMTP nativo validado; usa-o como canal final via wp_mail().
 * - Cria fila persistente para envios controlados, com tentativas e logs.
 * - Expõe funções reutilizáveis para módulos financeiros/académicos chamarem no futuro.
 */

if (!defined('ABSPATH')) exit;

if (!defined('SIGE_EMAIL_QUEUE_SCHEMA_VERSION')) {
    define('SIGE_EMAIL_QUEUE_SCHEMA_VERSION', '12.9.42');
}

if (!function_exists('sige_email_queue_table')) {
    function sige_email_queue_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sige_email_queue';
    }
}

if (!function_exists('sige_email_queue_install')) {
    function sige_email_queue_install(): void {
        global $wpdb;
        $installed = (string)get_option('sige_email_queue_schema_version', '');
        if ($installed === SIGE_EMAIL_QUEUE_SCHEMA_VERSION) return;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = sige_email_queue_table();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            context VARCHAR(80) NOT NULL DEFAULT 'geral',
            recipient VARCHAR(190) NOT NULL,
            recipient_name VARCHAR(190) DEFAULT NULL,
            subject TEXT NOT NULL,
            body LONGTEXT NOT NULL,
            headers_json LONGTEXT DEFAULT NULL,
            vars_json LONGTEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            priority TINYINT UNSIGNED NOT NULL DEFAULT 5,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
            scheduled_at DATETIME NOT NULL,
            locked_at DATETIME DEFAULT NULL,
            sent_at DATETIME DEFAULT NULL,
            last_error TEXT DEFAULT NULL,
            created_by BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status_scheduled (status, scheduled_at),
            KEY escola_context (escola_id, context),
            KEY recipient_status (recipient, status)
        ) {$charset};";

        dbDelta($sql);
        update_option('sige_email_queue_schema_version', SIGE_EMAIL_QUEUE_SCHEMA_VERSION, false);
    }
}
add_action('init', 'sige_email_queue_install', 8);

if (!function_exists('sige_email_templates_default')) {
    function sige_email_templates_default(): array {
        // v12.11.2 - Templates B2C, sem emojis e sem marcas de automação.
        return [
            'boas_vindas' => [
                'label' => 'Boas-vindas',
                'subject' => 'Bem-vindo(a) à {nome_escola}',
                'body' => "<p>Estimado(a) {primeiro_nome},</p><p>Seja bem-vindo(a) à comunicação da <strong>{nome_escola}</strong>.</p><p>Por este canal, a secretaria poderá partilhar consigo informações importantes, confirmações de pagamento, avisos e documentos escolares.</p><p>Qualquer dúvida, pode responder a este e-mail ou contactar a secretaria.</p><p>Com os melhores cumprimentos,<br>{nome_escola}</p>",
            ],
            'mensalidade_emitida' => [
                'label' => 'Mensalidade emitida',
                'subject' => 'Mensalidade de {mes} - {nome_escola}',
                'body' => "<p>Estimado(a) {primeiro_nome},</p><p>A secretaria da <strong>{nome_escola}</strong> partilha consigo a informação da mensalidade de <strong>{mes}</strong>, no valor de <strong>{valor}</strong>.</p><p>Se precisar de esclarecer algum detalhe ou combinar uma data, estamos disponíveis para acompanhar consigo.</p><p>Com os melhores cumprimentos,<br>{nome_escola}</p>",
            ],
            'confirmacao_pagamento' => [
                'label' => 'Confirmação de pagamento',
                'subject' => 'Recebemos o seu pagamento, com um obrigado - {nome_escola}',
                'body' => "<p>Estimado(a) Encarregado(a),</p>"
                    . "<p>Recebemos com sucesso o pagamento referente a <strong>{nome_aluno}</strong>. Muito obrigado(a) pela sua confiança e pontualidade. É com este cuidado da vossa parte que conseguimos acompanhar cada aluno com a atenção que merece.</p>"
                    . "<p>Guardamos aqui o comprovativo, para o que precisar:</p>"
                    . "<p><strong>Valor pago:</strong> {valor}<br>"
                    . "<strong>Referente a:</strong> {descricao}<br>"
                    . "<strong>Recibo n.&ordm;:</strong> {recibo_numero}<br>"
                    . "<strong>Data:</strong> {data}</p>"
                    . "<p>{link_recibo}</p>"
                    . "<p>Ficamos sempre ao dispor para qualquer esclarecimento. &Eacute; um gosto ter a vossa fam&iacute;lia connosco.</p>"
                    . "<p>Com apre&ccedil;o,<br>{nome_escola}</p>",
            ],
            'cobranca_amigavel' => [
                'label' => 'Cobrança amigável',
                'subject' => 'Ponto de situação financeira - {nome_escola}',
                'body' => "<p>Estimado(a) {primeiro_nome},</p><p>A secretaria da <strong>{nome_escola}</strong> gostaria de confirmar consigo um valor que aparece em aberto.</p><p><strong>Valor em aberto:</strong> {valor}<br><strong>Referência:</strong> {descricao}</p><p>Se já regularizou, pedimos desculpa pelo incómodo; responda por favor para conferirmos. Se ainda não foi possível, podemos conversar sobre a melhor forma de regularizar.</p><p>Com consideração,<br>{nome_escola}</p>",
            ],
            'recibo' => [
                'label' => 'Recibo',
                'subject' => 'Recebemos o seu pagamento, com um obrigado - {nome_escola}',
                'body' => "<p>Estimado(a) Encarregado(a),</p>"
                    . "<p>Recebemos com sucesso o pagamento referente a <strong>{nome_aluno}</strong>. Muito obrigado(a) pela sua confian&ccedil;a e pontualidade. &Eacute; com este cuidado da vossa parte que conseguimos acompanhar cada aluno com a aten&ccedil;&atilde;o que merece.</p>"
                    . "<p>Guardamos aqui o comprovativo, para o que precisar:</p>"
                    . "<p><strong>Valor pago:</strong> {valor}<br>"
                    . "<strong>Referente a:</strong> {descricao}<br>"
                    . "<strong>Recibo n.&ordm;:</strong> {recibo_numero}<br>"
                    . "<strong>Data:</strong> {data}</p>"
                    . "<p>{link_recibo}</p>"
                    . "<p>Ficamos sempre ao dispor para qualquer esclarecimento. &Eacute; um gosto ter a vossa fam&iacute;lia connosco.</p>"
                    . "<p>Com apre&ccedil;o,<br>{nome_escola}</p>",
            ],
        ];
    }
}

if (!function_exists('sige_email_templates_get')) {
    function sige_email_templates_get(): array {
        $defaults = sige_email_templates_default();
        $saved = get_option('sige_email_templates_config', []);
        if (!is_array($saved)) $saved = [];

        foreach ($defaults as $key => $tpl) {
            if (!isset($saved[$key]) || !is_array($saved[$key])) {
                $saved[$key] = $tpl;
                continue;
            }
            $saved[$key] = array_merge($tpl, [
                'subject' => (string)($saved[$key]['subject'] ?? $tpl['subject']),
                'body' => (string)($saved[$key]['body'] ?? $tpl['body']),
            ]);
        }
        return $saved;
    }
}

if (!function_exists('sige_email_templates_save_from_request')) {
    function sige_email_templates_save_from_request(array $src): array {
        $defaults = sige_email_templates_default();
        $out = [];
        foreach ($defaults as $key => $tpl) {
            $subject = isset($src['subject'][$key]) ? sanitize_text_field(wp_unslash($src['subject'][$key])) : $tpl['subject'];
            $body_raw = isset($src['body'][$key]) ? (string)wp_unslash($src['body'][$key]) : $tpl['body'];
            $body = wp_kses_post($body_raw);
            if ($subject === '') $subject = $tpl['subject'];
            if ($body === '') $body = $tpl['body'];
            $out[$key] = [
                'label' => $tpl['label'],
                'subject' => $subject,
                'body' => $body,
            ];
        }
        update_option('sige_email_templates_config', $out, false);
        return $out;
    }
}

if (!function_exists('sige_email_base_vars')) {
    function sige_email_base_vars(): array {
        $school = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
        $nome_escola = '';
        if (is_object($school) && !empty($school->nome_escola)) {
            $nome_escola = (string)$school->nome_escola;
        }
        if ($nome_escola === '') $nome_escola = get_bloginfo('name') ?: 'SoftGenial';

        return [
            'nome_escola' => $nome_escola,
            'data' => current_time('d/m/Y'),
            'hora' => current_time('H:i'),
            'ano' => current_time('Y'),
            'site_url' => home_url('/'),
        ];
    }
}

if (!function_exists('sige_email_render_template')) {
    function sige_email_render_template(string $template_key, array $vars = []): array {
        $templates = sige_email_templates_get();
        $tpl = $templates[$template_key] ?? null;
        if (!$tpl) {
            $tpl = [
                'subject' => (string)($vars['subject'] ?? 'Mensagem da escola'),
                'body' => (string)($vars['body'] ?? ''),
            ];
        }

        $vars = array_merge(sige_email_base_vars(), $vars);

        // Substituicao de placeholders TOLERANTE a formatacao: {nome_escola},
        // {nomeescola}, {Nome Escola} e {NOME-ESCOLA} resolvem todos para a mesma
        // variavel. Impede que um template guardado com placeholders sem
        // underscore (ou com espacos/maiusculas) apareca literal no e-mail.
        $exato = [];
        $norm  = [];
        foreach ($vars as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $val = (string)$v;
                $exato['{' . $k . '}'] = $val;
                $nk = strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$k));
                if ($nk !== '') $norm[$nk] = $val;
            }
        }
        $tolerante = static function (string $texto) use ($exato, $norm): string {
            $texto = strtr($texto, $exato); // passagem exacta (rapida)
            return (string)preg_replace_callback('/\{([a-z0-9 _\-]{1,60})\}/i', static function ($m) use ($norm) {
                $nk = strtolower(preg_replace('/[^a-z0-9]/i', '', $m[1]));
                return array_key_exists($nk, $norm) ? $norm[$nk] : $m[0];
            }, $texto);
        };

        $subject = $tolerante((string)$tpl['subject']);
        $body = $tolerante((string)$tpl['body']);
        if (function_exists('sige_notify_humanize_outbound_email_html')) {
            $subject = sige_notify_humanize_outbound_email_html($subject);
            $body = sige_notify_humanize_outbound_email_html($body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
            'vars' => $vars,
        ];
    }
}

if (!function_exists('sige_email_wrap_html')) {
    function sige_email_wrap_html(string $body, string $title = ''): string {
        $cfg = function_exists('sige_smtp_get_config') ? sige_smtp_get_config(false) : [];
        $from_name = !empty($cfg['from_name']) ? (string)$cfg['from_name'] : (sige_email_base_vars()['nome_escola'] ?? 'SoftGenial');
        $title = $title !== '' ? $title : $from_name;
        return '<div style="font-family:Arial,sans-serif;max-width:680px;margin:0 auto;background:#f8fafc;padding:18px">'
            . '<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden">'
            . '<div style="background:#0e4194;color:#ffffff;padding:18px 22px"><h2 style="margin:0;font-size:19px;line-height:1.35">' . esc_html($title) . '</h2></div>'
            . '<div style="padding:22px;color:#334155;font-size:14px;line-height:1.65">' . wp_kses_post($body) . '</div>'
            . '<div style="padding:14px 22px;background:#f1f5f9;color:#64748b;font-size:11px">Comunicação enviada pela secretaria da escola. Se houver alguma dúvida, responda a este e-mail ou contacte a secretaria.</div>'
            . '</div></div>';
    }
}

if (!function_exists('sige_email_queue_enqueue')) {
    function sige_email_queue_enqueue(string $to, string $subject, string $body, array $args = []) {
        global $wpdb;
        if (!is_email($to)) return false;
        sige_email_queue_install();

        $school = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
        $escola_id = isset($args['escola_id']) ? (int)$args['escola_id'] : (is_object($school) && isset($school->id) ? (int)$school->id : 0);
        $headers = $args['headers'] ?? ['Content-Type: text/html; charset=UTF-8'];
        if (!is_array($headers)) $headers = [$headers];

        $now = current_time('mysql');
        $scheduled_at = !empty($args['scheduled_at']) ? sanitize_text_field((string)$args['scheduled_at']) : $now;

        $inserted = $wpdb->insert(sige_email_queue_table(), [
            'escola_id' => $escola_id,
            'context' => sanitize_key($args['context'] ?? 'geral'),
            'recipient' => sanitize_email($to),
            'recipient_name' => sanitize_text_field($args['recipient_name'] ?? ''),
            'subject' => wp_strip_all_tags($subject),
            'body' => (string)$body,
            'headers_json' => wp_json_encode($headers),
            'vars_json' => wp_json_encode($args['vars'] ?? []),
            'status' => 'pending',
            'priority' => max(1, min(9, (int)($args['priority'] ?? 5))),
            'attempts' => 0,
            'max_attempts' => max(1, min(10, (int)($args['max_attempts'] ?? 3))),
            'scheduled_at' => $scheduled_at,
            'created_by' => get_current_user_id() ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%d','%s','%s','%s','%s','%s','%s','%s','%s','%d','%d','%d','%s','%d','%s','%s']);

        return $inserted ? (int)$wpdb->insert_id : false;
    }
}

if (!function_exists('sige_email_enqueue_template')) {
    function sige_email_enqueue_template(string $template_key, string $to, array $vars = [], array $args = []) {
        $rendered = sige_email_render_template($template_key, $vars);
        $body = sige_email_wrap_html($rendered['body'], $rendered['subject']);
        $args['context'] = $args['context'] ?? $template_key;
        $args['vars'] = $rendered['vars'];
        return sige_email_queue_enqueue($to, $rendered['subject'], $body, $args);
    }
}

if (!function_exists('sige_email_queue_process')) {
    function sige_email_queue_process(int $limit = 20): array {
        global $wpdb;
        sige_email_queue_install();
        $table = sige_email_queue_table();
        $limit = max(1, min(50, $limit));
        $now = current_time('mysql');
        $sent = 0; $failed = 0; $skipped = 0;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'pending' AND scheduled_at <= %s ORDER BY priority ASC, id ASC LIMIT %d",
            $now,
            $limit
        ));

        foreach ($rows as $row) {
            $locked = $wpdb->update($table, [
                'status' => 'sending',
                'locked_at' => $now,
                'updated_at' => $now,
            ], [
                'id' => (int)$row->id,
                'status' => 'pending',
            ], ['%s','%s','%s'], ['%d','%s']);

            if (!$locked) { $skipped++; continue; }

            delete_option('sige_smtp_last_error');
            $headers = json_decode((string)$row->headers_json, true);
            if (!is_array($headers) || empty($headers)) $headers = ['Content-Type: text/html; charset=UTF-8'];

            $ok = wp_mail((string)$row->recipient, (string)$row->subject, (string)$row->body, $headers);
            if ($ok) {
                $wpdb->update($table, [
                    'status' => 'sent',
                    'sent_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                    'last_error' => null,
                ], ['id' => (int)$row->id], ['%s','%s','%s','%s'], ['%d']);
                $sent++;
            } else {
                $attempts = ((int)$row->attempts) + 1;
                $err = get_option('sige_smtp_last_error', []);
                $msg = is_array($err) && !empty($err['message']) ? (string)$err['message'] : 'wp_mail devolveu false.';
                $status = ($attempts >= (int)$row->max_attempts) ? 'failed' : 'pending';
                $delay_minutes = min(60, 5 * $attempts);
                $next = date('Y-m-d H:i:s', current_time('timestamp') + ($delay_minutes * 60));
                $wpdb->update($table, [
                    'status' => $status,
                    'attempts' => $attempts,
                    'scheduled_at' => $next,
                    'updated_at' => current_time('mysql'),
                    'last_error' => $msg,
                ], ['id' => (int)$row->id], ['%s','%d','%s','%s','%s'], ['%d']);
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => $skipped, 'processed' => count($rows)];
    }
}

if (!function_exists('sige_email_queue_stats')) {
    function sige_email_queue_stats(): array {
        global $wpdb;
        sige_email_queue_install();
        $table = sige_email_queue_table();
        $stats = ['pending'=>0,'sending'=>0,'sent'=>0,'failed'=>0];
        $rows = $wpdb->get_results("SELECT status, COUNT(*) total FROM {$table} GROUP BY status");
        foreach ($rows as $r) {
            $stats[(string)$r->status] = (int)$r->total;
        }
        return $stats;
    }
}

add_filter('cron_schedules', function($schedules) {
    if (!isset($schedules['sige_email_5min'])) {
        $schedules['sige_email_5min'] = ['interval' => 300, 'display' => 'SIGE Email Queue 5min'];
    }
    return $schedules;
});

add_action('init', function() {
    if (!wp_next_scheduled('sige_processar_email_queue')) {
        wp_schedule_event(time() + 120, 'sige_email_5min', 'sige_processar_email_queue');
    }
});
add_action('sige_processar_email_queue', function() { sige_email_queue_process(20); });

if (!function_exists('sige_ajax_salvar_email_templates')) {
    function sige_ajax_salvar_email_templates(): void {
        if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) wp_send_json_error('Sem permissão para alterar modelos de email.');
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!$nonce || !wp_verify_nonce($nonce, 'sige_cfg_global')) wp_send_json_error('Sessão expirada. Recarregue a página.');
        sige_email_templates_save_from_request($_POST);
        wp_send_json_success('Modelos de email gravados com sucesso.');
    }
}
add_action('wp_ajax_sige_salvar_email_templates', 'sige_ajax_salvar_email_templates');

if (!function_exists('sige_ajax_testar_email_template_queue')) {
    function sige_ajax_testar_email_template_queue(): void {
        if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) wp_send_json_error('Sem permissão para testar modelos de email.');
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!$nonce || !wp_verify_nonce($nonce, 'sige_cfg_global')) wp_send_json_error('Sessão expirada. Recarregue a página.');
        sige_email_templates_save_from_request($_POST);

        $to = sanitize_email($_POST['test_to'] ?? '');
        if (!$to || !is_email($to)) $to = get_option('admin_email');
        if (!$to || !is_email($to)) wp_send_json_error('Informe um e-mail válido para teste.');

        $template = sanitize_key($_POST['template_key'] ?? 'mensalidade_emitida');
        $id = sige_email_enqueue_template($template, $to, [
            'nome_encarregado' => 'Encarregado de Teste',
            'nome_aluno' => 'Aluno de Teste',
            'mes' => current_time('F'),
            'valor' => '1.500,00 MZN',
            'descricao' => 'Teste de comunicação automática',
            'recibo_numero' => 'REC-TESTE',
            'link_recibo' => '<a href="' . esc_url(home_url('/')) . '">Abrir recibo</a>',
        ], ['context' => 'teste_template', 'priority' => 1]);

        if (!$id) wp_send_json_error('Não foi possível colocar o e-mail na fila.');
        $result = sige_email_queue_process(5);
        wp_send_json_success('Modelo colocado na fila e processado. Enviados: ' . (int)$result['sent'] . ' · Falhas: ' . (int)$result['failed'] . '.');
    }
}
add_action('wp_ajax_sige_testar_email_template_queue', 'sige_ajax_testar_email_template_queue');

if (!function_exists('sige_ajax_processar_email_queue_now')) {
    function sige_ajax_processar_email_queue_now(): void {
        if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) wp_send_json_error('Sem permissão para processar a fila.');
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!$nonce || !wp_verify_nonce($nonce, 'sige_cfg_global')) wp_send_json_error('Sessão expirada. Recarregue a página.');
        $result = sige_email_queue_process(30);
        wp_send_json_success('Fila processada. Enviados: ' . (int)$result['sent'] . ' · Falhas: ' . (int)$result['failed'] . ' · Ignorados: ' . (int)$result['skipped'] . '.');
    }
}
add_action('wp_ajax_sige_processar_email_queue_now', 'sige_ajax_processar_email_queue_now');
