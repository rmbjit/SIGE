<?php
/**
 * SIGE SoftGenial - Email Engine
 * Ficheiro: includes/email-engine.php
 * 
 * Motor de envio de emails: recibos, facturas, cobranças, lembretes.
 * Inclui handler de recibo público (link sem login).
 * 
 * @since 10.0
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// SMTP NATIVO DO SIGE (v12.9.41)
// ============================================================================
// Objectivo: permitir envio profissional por SMTP (Zoho, Google, Microsoft, etc.)
// sem dependência de plugins externos. Credenciais ficam guardadas em wp_options,
// com palavra-passe encriptada usando o mesmo cofre já usado pelo SIGE.

if (!function_exists('sige_smtp_default_config')) {
    function sige_smtp_default_config(): array {
        return [
            'enabled'    => '0',
            'host'       => 'smtp.zoho.com',
            'port'       => 465,
            'encryption' => 'ssl',
            'auth'       => '1',
            'username'   => '',
            'password'   => '',
            'from_email' => '',
            'from_name'  => 'SoftGenial',
            'reply_to'   => '',
        ];
    }
}

if (!function_exists('sige_smtp_is_encrypted_secret')) {
    function sige_smtp_is_encrypted_secret(string $value): bool {
        return strpos($value, 'sige2:') === 0 || strpos($value, 'gcm1:') === 0;
    }
}

if (!function_exists('sige_smtp_request_payload')) {
    /**
     * Aceita os formatos usados no SIGE: campos directos legacy,
     * array smtp[...] e formulário novo settings[comunicacao.smtp_config][...].
     */
    function sige_smtp_request_payload(array $src): array {
        if (isset($src['settings']) && is_array($src['settings'])) {
            $settings = function_exists('wp_unslash') ? wp_unslash($src['settings']) : $src['settings'];
            if (isset($settings['comunicacao.smtp_config']) && is_array($settings['comunicacao.smtp_config'])) {
                return $settings['comunicacao.smtp_config'];
            }
        }

        if (isset($src['smtp']) && is_array($src['smtp'])) {
            return function_exists('wp_unslash') ? wp_unslash($src['smtp']) : $src['smtp'];
        }

        $direct = [];
        foreach (['enabled','host','port','encryption','auth','username','password','from_email','from_name','reply_to'] as $field) {
            if (array_key_exists($field, $src)) {
                $direct[$field] = function_exists('wp_unslash') ? wp_unslash($src[$field]) : $src[$field];
            }
        }

        // Compatibilidade com formulários antigos: smtp_host, smtp_port, etc.
        $legacy = [
            'enabled'    => 'smtp_enabled',
            'host'       => 'smtp_host',
            'port'       => 'smtp_port',
            'encryption' => 'smtp_encryption',
            'auth'       => 'smtp_auth',
            'username'   => 'smtp_username',
            'password'   => 'smtp_password',
            'from_email' => 'smtp_from_email',
            'from_name'  => 'smtp_from_name',
            'reply_to'   => 'smtp_reply_to',
        ];
        foreach ($legacy as $field => $old_key) {
            if (array_key_exists($old_key, $src) && !array_key_exists($field, $direct)) {
                $direct[$field] = function_exists('wp_unslash') ? wp_unslash($src[$old_key]) : $src[$old_key];
            }
        }

        return $direct;
    }
}

if (!function_exists('sige_smtp_get_config')) {
    function sige_smtp_get_config(bool $with_plain_password = false): array {
        $cfg = get_option('sige_smtp_config', []);
        if (!is_array($cfg)) $cfg = [];
        $cfg = array_merge(sige_smtp_default_config(), $cfg);

        if ($with_plain_password) {
            $enc = (string)($cfg['password'] ?? '');
            if ($enc !== '' && function_exists('sige_decrypt_token')) {
                $cfg['password_plain'] = (string)sige_decrypt_token($enc);
            } else {
                $cfg['password_plain'] = '';
            }
            $cfg['password_available'] = ($cfg['password_plain'] !== '');
            $cfg['password_needs_reentry'] = ($enc !== '' && $cfg['password_plain'] === '');
        }
        return $cfg;
    }
}

if (!function_exists('sige_smtp_save_config_from_request')) {
    function sige_smtp_save_config_from_request(array $src): array {
        $src = sige_smtp_request_payload($src);
        $current = sige_smtp_get_config(false);

        $enabled = !empty($src['enabled']) ? '1' : '0';
        $host = sanitize_text_field($src['host'] ?? '');
        $port = (int)($src['port'] ?? 0);
        $encryption = sanitize_key($src['encryption'] ?? 'ssl');
        $auth = !empty($src['auth']) ? '1' : '0';
        $username = sanitize_text_field($src['username'] ?? '');
        $from_email = sanitize_email($src['from_email'] ?? '');
        $from_name = sanitize_text_field($src['from_name'] ?? '');
        $reply_to = sanitize_email($src['reply_to'] ?? '');

        if (!in_array($encryption, ['ssl', 'tls', 'none'], true)) $encryption = 'ssl';
        if ($port <= 0 || $port > 65535) $port = ($encryption === 'tls') ? 587 : 465;
        if ($host === '') $host = 'smtp.zoho.com';
        if ($from_name === '') $from_name = get_bloginfo('name') ?: 'SoftGenial';
        if ($from_email === '' && is_email($username)) $from_email = $username;

        $password_raw = isset($src['password']) ? (string)wp_unslash($src['password']) : '';
        $password_enc = (string)($current['password'] ?? '');
        if ($password_raw !== '') {
            $password_enc = sige_smtp_is_encrypted_secret($password_raw)
                ? $password_raw
                : (function_exists('sige_encrypt_token') ? sige_encrypt_token($password_raw) : base64_encode($password_raw));
        }

        $cfg = [
            'enabled'    => $enabled,
            'host'       => $host,
            'port'       => $port,
            'encryption' => $encryption,
            'auth'       => $auth,
            'username'   => $username,
            'password'   => $password_enc,
            'from_email' => $from_email,
            'from_name'  => $from_name,
            'reply_to'   => $reply_to,
            'updated_at' => current_time('mysql'),
            'updated_by' => get_current_user_id(),
        ];

        update_option('sige_smtp_config', $cfg, false);
        return $cfg;
    }
}

if (!function_exists('sige_smtp_is_enabled')) {
    function sige_smtp_is_enabled(): bool {
        $cfg = sige_smtp_get_config(false);
        return !empty($cfg['enabled']) && !empty($cfg['host']) && !empty($cfg['username']);
    }
}

if (!function_exists('sige_smtp_apply_phpmailer')) {
    function sige_smtp_apply_phpmailer($phpmailer): void {
        $cfg = sige_smtp_get_config(true);
        if (empty($cfg['enabled']) || empty($cfg['host']) || empty($cfg['username'])) {
            return;
        }

        $password = (string)($cfg['password_plain'] ?? '');
        if ($password === '') return;

        $phpmailer->isSMTP();
        $phpmailer->Host       = (string)$cfg['host'];
        $phpmailer->Port       = (int)$cfg['port'];
        $phpmailer->SMTPAuth   = !empty($cfg['auth']);
        $phpmailer->Username   = (string)$cfg['username'];
        $phpmailer->Password   = $password;
        $phpmailer->CharSet    = 'UTF-8';
        $phpmailer->Timeout    = 25;
        $phpmailer->SMTPAutoTLS = true;

        $sender = isset($cfg['from_email']) ? sanitize_email((string)$cfg['from_email']) : '';
        if ($sender !== '' && is_email($sender)) {
            $phpmailer->Sender = $sender;
        }

        if ($cfg['encryption'] === 'ssl') {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ($cfg['encryption'] === 'tls') {
            $phpmailer->SMTPSecure = 'tls';
        } else {
            $phpmailer->SMTPSecure = '';
            $phpmailer->SMTPAutoTLS = false;
        }

        // v12.9.41 - Reply-To real no PHPMailer.
        // Nota: filtros wp_mail_from/wp_mail_from_name alteram o remetente,
        // mas o Reply-To precisa ser aplicado directamente ao PHPMailer
        // para garantir que clientes como Gmail/Outlook respondam ao endereço certo.
        $reply_to = isset($cfg['reply_to']) ? sanitize_email((string)$cfg['reply_to']) : '';
        if ($reply_to !== '' && is_email($reply_to)) {
            $reply_name = !empty($cfg['from_name']) ? (string)$cfg['from_name'] : '';
            try {
                if (method_exists($phpmailer, 'clearReplyTos')) {
                    $phpmailer->clearReplyTos();
                }
                $phpmailer->addReplyTo($reply_to, $reply_name);
            } catch (Exception $e) {
                // Não bloqueia o envio por falha de Reply-To; apenas regista para diagnóstico.
                update_option('sige_smtp_last_replyto_error', [
                    'time' => current_time('mysql'),
                    'reply_to' => $reply_to,
                    'message' => $e->getMessage(),
                ], false);
            }
        }
    }
}
add_action('phpmailer_init', 'sige_smtp_apply_phpmailer', 20);

if (!function_exists('sige_smtp_wp_mail_from')) {
    function sige_smtp_wp_mail_from($email): string {
        $cfg = sige_smtp_get_config(false);
        if (!empty($cfg['enabled']) && !empty($cfg['from_email']) && is_email($cfg['from_email'])) {
            return (string)$cfg['from_email'];
        }
        return (string)$email;
    }
}
add_filter('wp_mail_from', 'sige_smtp_wp_mail_from', 20);

if (!function_exists('sige_smtp_wp_mail_from_name')) {
    function sige_smtp_wp_mail_from_name($name): string {
        $cfg = sige_smtp_get_config(false);
        if (!empty($cfg['enabled']) && !empty($cfg['from_name'])) {
            return (string)$cfg['from_name'];
        }
        return (string)$name;
    }
}
add_filter('wp_mail_from_name', 'sige_smtp_wp_mail_from_name', 20);

if (!function_exists('sige_smtp_mail_failed_log')) {
    function sige_smtp_mail_failed_log($wp_error): void {
        if (!is_wp_error($wp_error)) return;
        update_option('sige_smtp_last_error', [
            'time'    => current_time('mysql'),
            'message' => $wp_error->get_error_message(),
            'data'    => $wp_error->get_error_data(),
        ], false);
    }
}
add_action('wp_mail_failed', 'sige_smtp_mail_failed_log', 10, 1);

if (!function_exists('sige_ajax_salvar_smtp_config')) {
    function sige_ajax_salvar_smtp_config(): void {
        if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) wp_send_json_error('Sem permissão para alterar SMTP.');
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!$nonce || !wp_verify_nonce($nonce, 'sige_cfg_global')) wp_send_json_error('Sessão expirada. Recarregue a página.');

        $cfg = sige_smtp_save_config_from_request($_POST);
        if (!empty($cfg['enabled']) && (!is_email($cfg['from_email']) || empty($cfg['username']))) {
            wp_send_json_error('SMTP gravado parcialmente, mas falta validar o e-mail remetente/utilizador.');
        }

        wp_send_json_success('Configuração SMTP gravada com sucesso.');
    }
}
add_action('wp_ajax_sige_salvar_smtp_config', 'sige_ajax_salvar_smtp_config');

if (!function_exists('sige_ajax_testar_smtp_config')) {
    function sige_ajax_testar_smtp_config(): void {
        if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) {
            wp_send_json_error(['message' => 'Sem permissão para testar SMTP.'], 403);
        }

        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        $nonce_ok = $nonce && (
            wp_verify_nonce($nonce, 'sige_cfg_global')
            || (class_exists('SIGE_Settings_Controller') && wp_verify_nonce($nonce, SIGE_Settings_Controller::NONCE_ACTION))
        );
        if (!$nonce_ok) {
            wp_send_json_error(['message' => 'Sessão expirada. Recarregue a página.'], 403);
        }

        // Permite testar a configuração que está no ecrã, gravando-a antes do teste.
        $payload = sige_smtp_request_payload($_POST);
        if (!empty($payload)) {
            $cfg = sige_smtp_save_config_from_request(['smtp' => $payload]);
        } else {
            $cfg = sige_smtp_get_config(false);
        }

        delete_option('sige_smtp_last_error');

        $to = sanitize_email($_POST['test_to'] ?? '');
        if (!$to || !is_email($to)) {
            $to = get_option('admin_email');
        }
        if (!$to || !is_email($to)) {
            wp_send_json_error(['message' => 'Informe um e-mail válido para teste.'], 400);
        }

        $cfg_plain = sige_smtp_get_config(true);
        $diagnostics = [
            'host'        => (string)($cfg_plain['host'] ?? ''),
            'port'        => (int)($cfg_plain['port'] ?? 0),
            'encryption'  => (string)($cfg_plain['encryption'] ?? ''),
            'auth'        => !empty($cfg_plain['auth']) ? 'Sim' : 'Não',
            'username'    => !empty($cfg_plain['username']) ? preg_replace('/(^.).*(@.*$)/', '$1••••$2', (string)$cfg_plain['username']) : '',
            'from_email'  => (string)($cfg_plain['from_email'] ?? ''),
            'reply_to'    => (string)($cfg_plain['reply_to'] ?? ''),
            'time'        => current_time('mysql'),
            'timezone'    => wp_timezone_string(),
        ];

        if (empty($cfg_plain['enabled'])) {
            wp_send_json_error(['message' => 'Active o SMTP antes de testar.', 'diagnostics' => $diagnostics], 400);
        }
        if (empty($cfg_plain['host'])) {
            wp_send_json_error(['message' => 'Informe o servidor SMTP antes de testar.', 'diagnostics' => $diagnostics], 400);
        }
        if (!empty($cfg_plain['auth']) && empty($cfg_plain['username'])) {
            wp_send_json_error(['message' => 'Informe o utilizador SMTP antes de testar.', 'diagnostics' => $diagnostics], 400);
        }
        if (!empty($cfg_plain['auth']) && empty($cfg_plain['password_plain'])) {
            $msg = !empty($cfg_plain['password_needs_reentry'])
                ? 'A palavra-passe SMTP gravada não está legível. Reintroduza a palavra-passe do Zoho e teste novamente.'
                : 'Informe a palavra-passe SMTP antes de testar.';
            wp_send_json_error(['message' => $msg, 'diagnostics' => $diagnostics], 400);
        }
        if (empty($cfg_plain['from_email']) || !is_email((string)$cfg_plain['from_email'])) {
            wp_send_json_error(['message' => 'Informe um e-mail remetente válido. Para Zoho, use normalmente o mesmo e-mail autenticado ou um alias autorizado.', 'diagnostics' => $diagnostics], 400);
        }

        $school = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
        $nome_escola = $school->nome_escola ?? get_bloginfo('name');
        $assunto = 'Teste SMTP SoftGenial - ' . $nome_escola;
        $corpo = '<div style="font-family:Arial,sans-serif;max-width:620px;margin:0 auto;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;background:#ffffff">'
            . '<div style="background:#5a3fd6;color:#fff;padding:18px 22px"><h2 style="margin:0;font-size:20px">Teste SMTP enviado com sucesso</h2></div>'
            . '<div style="padding:20px;color:#334155;background:#fff;font-size:14px;line-height:1.65"><p>Este é um e-mail de teste enviado pelo SoftGenial.</p>'
            . '<p>Se recebeu esta mensagem, o WordPress conseguiu entregar o pedido de envio ao servidor SMTP configurado para <strong>' . esc_html($nome_escola) . '</strong>.</p>'
            . '<p><strong>Servidor:</strong> ' . esc_html((string)$cfg_plain['host']) . ':' . esc_html((string)$cfg_plain['port']) . ' / ' . esc_html(strtoupper((string)$cfg_plain['encryption'])) . '</p>'
            . '<p style="font-size:12px;color:#64748b">Data/Hora: ' . esc_html(current_time('mysql')) . ' (' . esc_html(wp_timezone_string()) . ')</p>'
            . '<p style="font-size:12px;color:#64748b">Nota: se este e-mail cair no spam, confirme SPF, DKIM, DMARC e alinhamento do remetente no domínio usado pelo Zoho.</p>'
            . '</div></div>';
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        if (!empty($cfg_plain['reply_to']) && is_email((string)$cfg_plain['reply_to'])) {
            $headers[] = 'Reply-To: ' . sanitize_email((string)$cfg_plain['reply_to']);
        }

        $started = microtime(true);
        $ok = wp_mail($to, $assunto, $corpo, $headers);
        $diagnostics['elapsed_ms'] = (int)round((microtime(true) - $started) * 1000);

        if ($ok) {
            $last = [
                'ok'          => true,
                'time'        => current_time('mysql'),
                'to'          => $to,
                'host'        => (string)($cfg_plain['host'] ?? ''),
                'port'        => (int)($cfg_plain['port'] ?? 0),
                'encryption'  => (string)($cfg_plain['encryption'] ?? ''),
                'elapsed_ms'  => $diagnostics['elapsed_ms'],
            ];
            update_option('sige_smtp_last_success', $last, false);
            update_option('sige_smtp_last_test', $last, false);
            wp_send_json_success([
                'message' => 'Email de teste enviado para ' . $to . '. Confirme também a caixa de entrada e a pasta SPAM.',
                'diagnostics' => $diagnostics,
            ]);
        }

        $err = get_option('sige_smtp_last_error', []);
        $msg = is_array($err) && !empty($err['message']) ? (string)$err['message'] : 'wp_mail devolveu false.';
        $last = [
            'ok'          => false,
            'time'        => current_time('mysql'),
            'to'          => $to,
            'message'     => $msg,
            'host'        => (string)($cfg_plain['host'] ?? ''),
            'port'        => (int)($cfg_plain['port'] ?? 0),
            'encryption'  => (string)($cfg_plain['encryption'] ?? ''),
            'elapsed_ms'  => $diagnostics['elapsed_ms'],
        ];
        update_option('sige_smtp_last_test', $last, false);
        wp_send_json_error([
            'message' => 'Falha no envio SMTP: ' . $msg,
            'diagnostics' => $diagnostics,
        ], 400);
    }
}
add_action('wp_ajax_sige_testar_smtp_config', 'sige_ajax_testar_smtp_config');


// ============================================================================
// RECIBO PÚBLICO (link sem login)
// ============================================================================
// v12.11.9.24 - Fase 1 / Blindagem P0.1
// - links WhatsApp continuam públicos e sem login;
// - o recibo deixa de depender de fallback para escola 1;
// - token passa a ser tenant-aware e, quando possível, aluno-aware;
// - links antigos continuam aceites apenas quando não criam ambiguidade;
// - o renderer recebe escola_id explícito para não usar contexto global.
//
// Formato novo em wp_options: TOKEN|ALUNO_ID|CREATED_AT|ESCOLA_ID|v2
// Chave nova: sige_rtk_e{ESCOLA}_a{ALUNO}_{RECIBO_SANITIZADO}
// Chave legacy preservada: sige_rtk_{RECIBO_SANITIZADO}
// ============================================================================

if (!function_exists('sige_recibo_token_slug')) {
    function sige_recibo_token_slug(string $recibo_numero): string {
        return preg_replace('/[^A-Za-z0-9_]/', '_', trim($recibo_numero));
    }
}

if (!function_exists('sige_recibo_token_key')) {
    function sige_recibo_token_key(string $recibo_numero, int $escola_id = 0, int $aluno_id = 0): string {
        $slug = sige_recibo_token_slug($recibo_numero);
        $escola_id = absint($escola_id);
        $aluno_id  = absint($aluno_id);
        if ($escola_id > 0 || $aluno_id > 0) {
            return 'sige_rtk_e' . $escola_id . '_a' . $aluno_id . '_' . $slug;
        }
        return 'sige_rtk_' . $slug;
    }
}

if (!function_exists('sige_recibo_lookup_context')) {
    /**
     * Resolve a escola/aluno real de um recibo a partir da própria BD financeira.
     * Não usa sige_get_escola_id() como verdade em links públicos.
     */
    function sige_recibo_lookup_context(string $recibo_numero, int $aluno_id = 0, int $escola_id = 0): array {
        global $wpdb;
        $out = [
            'found'           => false,
            'ambiguous'       => false,
            'multiple_alunos' => false,
            'escola_id'       => 0,
            'aluno_id'        => 0,
            'pagamentos'      => 0,
        ];
        $recibo_numero = trim($recibo_numero);
        if ($recibo_numero === '') return $out;

        $t = $wpdb->prefix . 'sige_fin_pagamentos';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t));
        if ((string)$exists !== $t) return $out;

        $where = ['recibo_numero = %s'];
        $args  = [$recibo_numero];
        $aluno_id = absint($aluno_id);
        $escola_id = absint($escola_id);
        if ($escola_id > 0) {
            $where[] = 'escola_id = %d';
            $args[] = $escola_id;
        }
        if ($aluno_id > 0) {
            $where[] = 'aluno_id = %d';
            $args[] = $aluno_id;
        }

        $sql = "SELECT escola_id, aluno_id, COUNT(*) AS qtd
                FROM {$t}
                WHERE " . implode(' AND ', $where) . "
                GROUP BY escola_id, aluno_id
                ORDER BY MIN(id) ASC
                LIMIT 10";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $args));
        if (empty($rows)) return $out;

        $escolas = [];
        $alunos  = [];
        $total   = 0;
        foreach ($rows as $r) {
            $eid = (int)($r->escola_id ?? 0);
            $aid = (int)($r->aluno_id ?? 0);
            if ($eid > 0) $escolas[$eid] = $eid;
            if ($aid > 0) $alunos[$aid] = $aid;
            $total += (int)($r->qtd ?? 0);
        }

        $out['found'] = true;
        $out['pagamentos'] = $total;
        $out['ambiguous'] = count($escolas) !== 1;
        $out['multiple_alunos'] = count($alunos) > 1;
        $out['escola_id'] = count($escolas) === 1 ? (int)reset($escolas) : 0;
        $out['aluno_id'] = count($alunos) === 1 ? (int)reset($alunos) : 0;
        return $out;
    }
}

if (!function_exists('sige_recibo_gerar_token')) {
    function sige_recibo_gerar_token(string $recibo_numero, int $aluno_id = 0): string {
        $recibo_numero = trim($recibo_numero);
        $aluno_id = absint($aluno_id);
        $ctx = sige_recibo_lookup_context($recibo_numero, $aluno_id, 0);
        $escola_id = (int)($ctx['escola_id'] ?? 0);
        if ($aluno_id <= 0 && !empty($ctx['aluno_id'])) {
            $aluno_id = (int)$ctx['aluno_id'];
        }
        if ($escola_id <= 0 && function_exists('sige_get_escola_id')) {
            $escola_id = (int)sige_get_escola_id();
        }

        $key    = sige_recibo_token_key($recibo_numero, $escola_id, $aluno_id);
        $stored = (string)get_option($key, '');
        $parts  = $stored !== '' ? explode('|', $stored) : [];
        $token  = $parts[0] ?? '';
        $created_at = isset($parts[2]) ? (int)$parts[2] : 0;

        if (empty($token) || (function_exists('sige_sec_recibo_token_expired') && sige_sec_recibo_token_expired($created_at))) {
            $token = bin2hex(random_bytes(24)); // 48 chars hex
            $created_at = time();
        }
        if ($created_at <= 0) $created_at = time();

        $novo_valor = $token . '|' . max(0, $aluno_id) . '|' . $created_at . '|' . max(0, $escola_id) . '|v2';
        if ($stored === '') {
            add_option($key, $novo_valor, '', 'no');
        } elseif ($novo_valor !== $stored) {
            update_option($key, $novo_valor, 'no');
        }
        return $token;
    }
}

if (!function_exists('sige_recibo_revogar_token')) {
    function sige_recibo_revogar_token(string $recibo_numero): bool {
        global $wpdb;
        $slug = sige_recibo_token_slug($recibo_numero);
        $deleted = delete_option(sige_recibo_token_key($recibo_numero));
        // Revogação ampla: remove também chaves v2 desse recibo, independentemente de escola/aluno.
        if ($slug !== '') {
            $like = $wpdb->esc_like('sige_rtk_e') . '%' . $wpdb->esc_like('_' . $slug);
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like));
            $deleted = true;
        }
        return (bool)$deleted;
    }
}

if (!function_exists('sige_recibo_revogar_token_contextual')) {
    function sige_recibo_revogar_token_contextual(string $recibo_numero, int $escola_id, int $aluno_id = 0): bool {
        return delete_option(sige_recibo_token_key($recibo_numero, absint($escola_id), absint($aluno_id)));
    }
}

if (!function_exists('sige_recibo_parse_stored_token')) {
    function sige_recibo_parse_stored_token(string $stored): array {
        $parts = $stored !== '' ? explode('|', $stored) : [];
        return [
            'token'      => $parts[0] ?? '',
            'aluno_id'   => isset($parts[1]) ? (int)$parts[1] : 0,
            'created_at' => isset($parts[2]) ? (int)$parts[2] : 0,
            'escola_id'  => isset($parts[3]) ? (int)$parts[3] : 0,
            'version'    => $parts[4] ?? 'legacy',
        ];
    }
}

if (!function_exists('sige_recibo_validar_token')) {
    function sige_recibo_validar_token(string $recibo_numero, string $token, int $escola_id_hint = 0, int $aluno_id_hint = 0): array {
        $resultado = [
            'valid'      => false,
            'aluno_id'   => 0,
            'escola_id'  => 0,
            'expired'    => false,
            'legacy'     => false,
        ];
        $recibo_numero = trim($recibo_numero);
        $token = trim($token);
        $escola_id_hint = absint($escola_id_hint);
        $aluno_id_hint  = absint($aluno_id_hint);

        $candidate_keys = [];
        if ($escola_id_hint > 0 || $aluno_id_hint > 0) {
            $candidate_keys[] = sige_recibo_token_key($recibo_numero, $escola_id_hint, $aluno_id_hint);
            if ($aluno_id_hint > 0) {
                $ctx_hint = sige_recibo_lookup_context($recibo_numero, $aluno_id_hint, $escola_id_hint);
                if (!empty($ctx_hint['escola_id'])) {
                    $candidate_keys[] = sige_recibo_token_key($recibo_numero, (int)$ctx_hint['escola_id'], $aluno_id_hint);
                }
            }
        }
        $candidate_keys[] = sige_recibo_token_key($recibo_numero); // legacy
        $candidate_keys = array_values(array_unique(array_filter($candidate_keys)));

        foreach ($candidate_keys as $key) {
            $stored = (string)get_option($key, '');
            if ($stored === '') continue;
            $parsed = sige_recibo_parse_stored_token($stored);
            $token_bd = (string)($parsed['token'] ?? '');
            if ($token_bd !== '' && hash_equals($token_bd, $token)) {
                if (function_exists('sige_sec_recibo_token_expired') && sige_sec_recibo_token_expired((int)$parsed['created_at'])) {
                    $resultado['expired'] = true;
                    return $resultado;
                }
                $resultado['valid']     = true;
                $resultado['aluno_id']  = (int)($parsed['aluno_id'] ?: $aluno_id_hint);
                $resultado['escola_id'] = (int)($parsed['escola_id'] ?: $escola_id_hint);
                $resultado['legacy']    = (($parsed['version'] ?? '') !== 'v2');
                return $resultado;
            }
        }

        // Retrocompatibilidade: HMAC antigo (janela de 60 dias já existente).
        foreach ([0, -1] as $offset) {
            $epoch    = (string) floor(time() / (30 * 86400) + $offset);
            $hmac_old = substr(hash_hmac('sha256', $recibo_numero . $epoch, wp_salt('auth')), 0, 16);
            if (hash_equals($hmac_old, $token)) {
                $resultado['valid']     = true;
                $resultado['legacy']    = true;
                $resultado['aluno_id']  = $aluno_id_hint;
                $resultado['escola_id'] = $escola_id_hint;
                return $resultado;
            }
        }

        return $resultado;
    }
}

if (!function_exists('sige_recibo_url_publica')) {
    function sige_recibo_url_publica(string $recibo_numero, int $aluno_id = 0): string {
        $recibo_numero = trim($recibo_numero);
        $aluno_id = absint($aluno_id);
        $ctx = sige_recibo_lookup_context($recibo_numero, $aluno_id, 0);
        $escola_id = (int)($ctx['escola_id'] ?? 0);

        // v12.11.9.25 - nunca gerar novo link público ambíguo.
        // Recibos familiares/agrupados devem receber aluno_id explícito.
        if (empty($ctx['found'])) return '';
        if (!empty($ctx['ambiguous']) && $escola_id <= 0) return '';
        if ($aluno_id <= 0 && !empty($ctx['multiple_alunos'])) return '';

        if ($aluno_id <= 0 && !empty($ctx['aluno_id'])) $aluno_id = (int)$ctx['aluno_id'];
        if ($escola_id <= 0 && function_exists('sige_get_escola_id')) $escola_id = (int)sige_get_escola_id();
        if ($escola_id <= 0 || $aluno_id <= 0) return '';

        $token = sige_recibo_gerar_token($recibo_numero, $aluno_id);
        if ($token === '') return '';
        return add_query_arg([
            'sige_recibo' => $recibo_numero,
            'e'           => max(0, $escola_id),
            'a'           => max(0, $aluno_id),
            'tk'          => $token,
        ], site_url('/'));
    }
}

add_action('template_redirect', 'sige_recibo_publico_handler');
if (!function_exists('sige_recibo_publico_handler')) {
    function sige_recibo_publico_handler() {
        if (!isset($_GET['sige_recibo']) || !isset($_GET['tk'])) return;

        if (function_exists('sige_sec_rate_limit') && !sige_sec_rate_limit('recibo_publico', 80, 300)) {
            wp_die('Muitas tentativas. Tente mais tarde.', 'Acesso Limitado', ['response' => 429]);
        }

        $recibo = sanitize_text_field(wp_unslash($_GET['sige_recibo']));
        $token  = sanitize_text_field(wp_unslash($_GET['tk']));
        $escola_id_hint = isset($_GET['e']) ? absint($_GET['e']) : (isset($_GET['escola_id']) ? absint($_GET['escola_id']) : 0);
        $aluno_id_hint  = isset($_GET['a']) ? absint($_GET['a']) : (isset($_GET['aluno_id']) ? absint($_GET['aluno_id']) : 0);

        if (empty($recibo) || empty($token)) {
            wp_die('Link inválido.');
        }

        $validacao = sige_recibo_validar_token($recibo, $token, $escola_id_hint, $aluno_id_hint);
        if (!empty($validacao['expired'])) {
            wp_die('Este link de recibo expirou. Solicite uma nova emissão à secretaria.', 'Link Expirado', ['response' => 403]);
        }
        if (!$validacao['valid']) {
            wp_die('Link de recibo inválido.', 'Acesso Negado', ['response' => 403]);
        }

        $ctx = sige_recibo_lookup_context(
            $recibo,
            (int)($validacao['aluno_id'] ?: $aluno_id_hint),
            (int)($validacao['escola_id'] ?: $escola_id_hint)
        );
        if (empty($ctx['found'])) {
            wp_die('Recibo não encontrado.', 'Não Encontrado', ['response' => 404]);
        }
        if (!empty($ctx['ambiguous'])) {
            wp_die('Este link não identifica a escola com segurança. Peça à secretaria para reenviar o recibo.', 'Link Ambíguo', ['response' => 403]);
        }

        $escola_id = (int)$ctx['escola_id'];
        $aluno_id  = (int)($validacao['aluno_id'] ?: $aluno_id_hint ?: $ctx['aluno_id']);
        if (!empty($validacao['escola_id']) && (int)$validacao['escola_id'] !== $escola_id) {
            wp_die('Link de recibo não corresponde à escola do pagamento.', 'Acesso Negado', ['response' => 403]);
        }
        if ($aluno_id <= 0 && !empty($ctx['multiple_alunos'])) {
            wp_die('Este link antigo não identifica o aluno com segurança. Peça à secretaria para reenviar o recibo.', 'Link Antigo', ['response' => 403]);
        }
        if ($aluno_id <= 0) {
            $aluno_id = (int)$ctx['aluno_id'];
        }

        if (function_exists('sige_audit_log')) {
            sige_audit_log('recibo_publico_aberto', [
                'recibo'    => $recibo,
                'escola_id' => $escola_id,
                'aluno_id'  => $aluno_id,
                'legacy'    => !empty($validacao['legacy']) ? 1 : 0,
                'ip'        => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string)$_SERVER['REMOTE_ADDR']) : '',
            ], 'financeiro');
        }

        if (function_exists('sige_gerar_html_recibo_agrupado')) {
            sige_gerar_html_recibo_agrupado($recibo, $aluno_id, $escola_id);
            exit;
        }

        wp_die('Módulo de recibos não está disponível.');
    }
}

// ============================================================================
// ENVIAR RECIBO POR EMAIL
// ============================================================================
if (!function_exists('sige_enviar_recibo_email')) {
    function sige_enviar_recibo_email(int $aluno_id, string $recibo_numero, float $valor_pago, int $qtd_itens = 1): bool {
        global $wpdb;

        $aluno = $wpdb->get_row($wpdb->prepare(
            "SELECT nome_completo, email_encarregado, email_pai, email_mae FROM {$wpdb->prefix}sige_alunos WHERE id=%d AND escola_id=%d",
            $aluno_id,
            sige_get_escola_id()
        ));
        if (!$aluno) return false;

        // Encontrar um email válido, mantendo a prioridade histórica do SIGE.
        $email = '';
        foreach (['email_encarregado', 'email_pai', 'email_mae'] as $campo) {
            if (!empty($aluno->$campo) && is_email($aluno->$campo)) {
                $email = sanitize_email((string)$aluno->$campo);
                break;
            }
        }
        if ($email === '') return false;

        $escola = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
        $nome_escola = is_object($escola) && !empty($escola->nome_escola) ? (string)$escola->nome_escola : (get_bloginfo('name') ?: 'SoftGenial');
        $nome_aluno = trim((string)($aluno->nome_completo ?? ''));
        $primeiro_nome = $nome_aluno !== '' ? explode(' ', $nome_aluno)[0] : 'aluno(a)';

        $link = function_exists('sige_recibo_url_publica') ? sige_recibo_url_publica($recibo_numero, $aluno_id) : '';
        $valor_fmt = number_format((float)$valor_pago, 2, ',', '.') . ' ' . (function_exists('sige_moeda') ? sige_moeda() : 'MT');
        $descricao = $qtd_itens . ' ' . ((int)$qtd_itens === 1 ? 'item pago' : 'itens pagos');
        $link_html = '';
        if ($link !== '') {
            $link_html = '<a href="' . esc_url($link) . '" style="display:inline-block;background:#0e4194;color:#ffffff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:600">Ver e descarregar o recibo</a>'
                . '<br><span style="font-size:13px;color:#64748b;display:inline-block;margin-top:8px">Se o botão não abrir, copie este endereço no seu navegador:<br>'
                . esc_url($link) . '</span>';
        }

        $vars = [
            'nome_encarregado' => 'Encarregado(a)',
            'nome_aluno'       => $nome_aluno,
            'primeiro_nome'    => $primeiro_nome,
            'nome_escola'      => $nome_escola,
            'valor'            => $valor_fmt,
            'descricao'        => $descricao,
            'recibo_numero'    => (string)$recibo_numero,
            'link_recibo'      => $link_html,
            'link_recibo_url'  => (string)$link,
            'data'             => current_time('d/m/Y'),
            'hora'             => current_time('H:i'),
        ];

        // v12.9.43 - o recibo de pagamento passa a usar o motor de templates + fila PRO.
        // Fallback preservado apenas para ambientes onde o módulo de fila ainda não esteja carregado.
        if (function_exists('sige_email_enqueue_template')) {
            $queue_id = sige_email_enqueue_template('recibo', $email, $vars, [
                'context'        => 'recibo_pagamento',
                'priority'       => 2,
                'recipient_name' => $nome_aluno,
                'max_attempts'   => 3,
            ]);

            if ($queue_id) {
                // Mantém a experiência prática do pagamento: tenta processar logo, mas continua seguro se o cron assumir depois.
                if (function_exists('sige_email_queue_process')) {
                    sige_email_queue_process(5);
                }

                if (function_exists('sige_fin_log')) {
                    sige_fin_log('email_recibo_enfileirado_template', [
                        'aluno_id' => $aluno_id,
                        'email'    => $email,
                        'recibo'   => $recibo_numero,
                        'queue_id' => (int)$queue_id,
                    ]);
                }
                return true;
            }
        }

        // Fallback legado controlado: só usado se a fila/templates não estiverem disponíveis.
        $assunto = "Recibo de Pagamento #{$recibo_numero} - {$nome_escola}";
        $corpo = "<p>Exmo(a) Encarregado(a) de <strong>" . esc_html($primeiro_nome) . "</strong>,</p>"
            . "<p>Confirmamos a recepção do pagamento no valor de <strong>" . esc_html($valor_fmt) . "</strong>.</p>"
            . "<p>Recibo Nº: <strong>" . esc_html($recibo_numero) . "</strong></p>"
            . ($link_html ? "<p>{$link_html}</p>" : '')
            . "<p>Atenciosamente,<br>" . esc_html($nome_escola) . "</p>";

        if (function_exists('sige_notify_humanize_outbound_email_html')) {
            $assunto = sige_notify_humanize_outbound_email_html($assunto);
            $corpo = sige_notify_humanize_outbound_email_html($corpo);
        }
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $enviado = wp_mail($email, $assunto, $corpo, $headers);

        if (function_exists('sige_fin_log')) {
            sige_fin_log('email_recibo_enviado_fallback', [
                'aluno_id' => $aluno_id,
                'email'    => $email,
                'recibo'   => $recibo_numero,
                'enviado'  => $enviado,
            ]);
        }

        return (bool)$enviado;
    }
}

// ============================================================================
// ENVIAR EMAIL FINANCEIRO GENÉRICO
// ============================================================================
if (!function_exists('sige_enviar_email_financeiro')) {
    /**
     * Envio financeiro genérico.
     *
     * v12.9.44 - Lançamento de mensalidades, cobranças e lembretes passam a usar
     * o motor de templates + fila PRO quando disponível. O envio legado fica apenas
     * como fallback seguro para evitar regressões em instalações incompletas.
     */
    function sige_enviar_email_financeiro(int $aluno_id, string $tipo, string $detalhes_str, float $valor_total, string $vencimento = ''): bool {
        global $wpdb;

        $aluno = $wpdb->get_row($wpdb->prepare(
            "SELECT nome_completo, email_encarregado, email_pai, email_mae FROM {$wpdb->prefix}sige_alunos WHERE id=%d AND escola_id=%d",
            $aluno_id,
            sige_get_escola_id()
        ));
        if (!$aluno) return false;

        $email = '';
        foreach (['email_encarregado', 'email_pai', 'email_mae'] as $campo) {
            if (!empty($aluno->$campo) && is_email($aluno->$campo)) {
                $email = sanitize_email((string)$aluno->$campo);
                break;
            }
        }
        if ($email === '') return false;

        $escola = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
        $nome_escola = is_object($escola) && !empty($escola->nome_escola) ? (string)$escola->nome_escola : (get_bloginfo('name') ?: 'SoftGenial');
        $tel_escola  = is_object($escola) && !empty($escola->telefone_oficial) ? (string)$escola->telefone_oficial : '';
        $nome_aluno = trim((string)($aluno->nome_completo ?? ''));
        $primeiro_nome = $nome_aluno !== '' ? explode(' ', $nome_aluno)[0] : 'aluno(a)';

        $tipo_norm = sanitize_key($tipo ?: 'fatura');

        // Formatar vencimento.
        $venc_fmt = '';
        if ($vencimento !== '') {
            $ts = strtotime($vencimento);
            $venc_fmt = $ts ? wp_date('d/m/Y', $ts) : $vencimento;
        }

        // Normalizar detalhes para texto simples + HTML leve.
        // v12.9.45: evita regex frágil em strings com barras invertidas e impede null em explode().
        $detalhes_txt = trim((string)($detalhes_str ?? ''));
        if ($detalhes_txt !== '') {
            $detalhes_txt = str_replace(["\r\n", "\r", '\\n'], "\n", $detalhes_txt);
        }

        $linhas_limpas = [];
        if ($detalhes_txt !== '') {
            foreach (explode("\n", (string)$detalhes_txt) as $linha) {
                $linha = trim((string)$linha);
                if ($linha === '') continue;
                $linha = preg_replace('/^\x{2022}\s*|^[\-]\s*/u', '', $linha);
                $linha = trim((string)$linha);
                if ($linha !== '') $linhas_limpas[] = $linha;
            }
        }

        $descricao = $linhas_limpas ? implode('; ', $linhas_limpas) : 'Serviços escolares';
        $detalhes_html = $linhas_limpas
            ? '<ul style="margin:8px 0 0 18px;padding:0;">' . implode('', array_map(function($linha) {
                return '<li style="margin:4px 0;">' . esc_html($linha) . '</li>';
            }, $linhas_limpas)) . '</ul>'
            : '';

        $valor_fmt = number_format((float)$valor_total, 2, ',', '.') . ' ' . (function_exists('sige_moeda') ? sige_moeda() : 'MT');
        $mes_label = '';
        if ($vencimento !== '' && ($ts_mes = strtotime($vencimento))) {
            $mes_label = wp_date('F Y', $ts_mes);
        }
        if ($mes_label === '') {
            $mes_label = wp_date('F Y');
        }

        $template_key = 'mensalidade_emitida';
        $context = 'mensalidade_emitida';
        $priority = 5;

        if ($tipo_norm === 'cobranca') {
            $template_key = 'cobranca_amigavel';
            $context = 'cobranca_devedores';
            $priority = 3;
        } elseif ($tipo_norm === 'lembrete') {
            $template_key = 'cobranca_amigavel';
            $context = 'lembrete_pagamento';
            $priority = 4;
        } else {
            $template_key = 'mensalidade_emitida';
            $context = 'lancamento_mensalidade';
            $priority = 5;
        }

        $vars = [
            'nome_encarregado' => 'Encarregado(a)',
            'nome_aluno'       => $nome_aluno,
            'primeiro_nome'    => $primeiro_nome,
            'nome_escola'      => $nome_escola,
            'telefone_escola'  => $tel_escola,
            'valor'            => $valor_fmt,
            'valor_numero'     => number_format((float)$valor_total, 2, ',', '.'),
            'descricao'        => $descricao,
            'detalhes'         => $detalhes_html,
            'vencimento'       => $venc_fmt,
            'mes'              => $mes_label,
            'data'             => current_time('d/m/Y'),
            'hora'             => current_time('H:i'),
            'tipo'             => $tipo_norm,
        ];

        // Motor PRO: templates + fila. Processa logo um lote pequeno para manter a experiência actual do utilizador.
        if (function_exists('sige_email_enqueue_template')) {
            $queue_id = sige_email_enqueue_template($template_key, $email, $vars, [
                'context'        => $context,
                'priority'       => $priority,
                'recipient_name' => $nome_aluno,
                'max_attempts'   => 3,
            ]);

            if ($queue_id) {
                if (function_exists('sige_email_queue_process')) {
                    sige_email_queue_process(5);
                }

                if (function_exists('sige_fin_log')) {
                    sige_fin_log('email_financeiro_enfileirado_template', [
                        'tipo'         => $tipo_norm,
                        'template_key' => $template_key,
                        'context'      => $context,
                        'aluno_id'     => $aluno_id,
                        'email'        => $email,
                        'valor'        => $valor_total,
                        'queue_id'     => (int)$queue_id,
                    ]);
                }
                return true;
            }
        }

        // Fallback legado controlado: usado apenas se a fila/templates não estiverem disponíveis.
        $configs = [
            'cobranca' => [
                'cor'      => '#dc2626',
                'icone'    => '',
                'titulo'   => 'Ponto de situação financeira',
                'assunto'  => "Ponto de situação financeira - {$nome_escola}",
                'intro'    => "A secretaria da escola identificou valores pendentes relacionados com o(a) aluno(a) <strong>" . esc_html($nome_aluno) . "</strong>.",
                'label_v'  => 'Total em dívida',
                'label_d'  => 'Vencimento mais antigo',
                'cta'      => 'Quando for possível, pedimos que contacte a secretaria para alinharmos a melhor forma de regularizar.',
            ],
            'fatura' => [
                'cor'      => '#2563eb',
                'icone'    => '',
                'titulo'   => 'Informação de mensalidade',
                'assunto'  => "Informação de mensalidade - {$nome_escola}",
                'intro'    => "A secretaria partilha a informação financeira actual do(a) aluno(a) <strong>" . esc_html($nome_aluno) . "</strong>.",
                'label_v'  => 'Valor total',
                'label_d'  => 'Data de vencimento',
                'cta'      => 'Agradecemos a sua atenção. Se precisar de esclarecer algum detalhe ou combinar uma data, responda a este e-mail ou contacte a secretaria.',
            ],
            'lembrete' => [
                'cor'      => '#d97706',
                'icone'    => '',
                'titulo'   => 'Lembrete da secretaria',
                'assunto'  => "Lembrete da secretaria - {$nome_escola}",
                'intro'    => "Partilhamos um lembrete sobre valores com vencimento próximo relacionados com o(a) aluno(a) <strong>" . esc_html($nome_aluno) . "</strong>.",
                'label_v'  => 'Valor total',
                'label_d'  => 'Data de vencimento',
                'cta'      => 'Agradecemos a sua atenção. Caso precise de algum esclarecimento, a secretaria está disponível para ajudar.',
            ],
        ];

        $cfg = $configs[$tipo_norm] ?? $configs['fatura'];
        $detalhes_table = '';
        if ($linhas_limpas) {
            foreach ($linhas_limpas as $linha) {
                $detalhes_table .= '<tr><td style="padding:6px 10px;border-bottom:1px solid #f1f5f9;font-size:13px;">' . esc_html($linha) . '</td></tr>';
            }
        }

        $corpo = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <div style='background:{$cfg['cor']};color:white;padding:20px;border-radius:8px 8px 0 0;text-align:center;'>
                <h2 style='margin:0;'>{$cfg['titulo']}</h2>
                <p style='margin:5px 0 0 0;opacity:0.9;'>" . esc_html($nome_escola) . "</p>
            </div>
            <div style='background:#f8fafc;padding:25px;border:1px solid #e2e8f0;'>
                <p>Exmo(a) Encarregado(a) de <strong>" . esc_html($primeiro_nome) . "</strong>,</p>
                <p>{$cfg['intro']}</p>
                " . ($detalhes_table ? "
                <table style='width:100%;border-collapse:collapse;margin:15px 0;background:white;border-radius:6px;overflow:hidden;border:1px solid #e2e8f0;'>
                    <tr><td style='padding:8px 10px;background:#f8fafc;font-weight:bold;font-size:12px;color:#64748b;border-bottom:1px solid #e2e8f0;'>Serviços</td></tr>
                    {$detalhes_table}
                </table>" : "") . "
                <table style='width:100%;border-collapse:collapse;margin:15px 0;'>
                    <tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;color:#64748b;'>{$cfg['label_v']}</td>
                        <td style='padding:8px;border-bottom:1px solid #e2e8f0;font-weight:bold;color:{$cfg['cor']};'>" . esc_html($valor_fmt) . "</td></tr>
                    " . ($venc_fmt ? "<tr><td style='padding:8px;color:#64748b;'>{$cfg['label_d']}</td>
                        <td style='padding:8px;font-weight:bold;'>" . esc_html($venc_fmt) . "</td></tr>" : "") . "
                </table>
                <p style='margin-top:15px;'>{$cfg['cta']}</p>
            </div>
            <div style='background:#f1f5f9;padding:15px;border-radius:0 0 8px 8px;text-align:center;font-size:12px;color:#64748b;border:1px solid #e2e8f0;border-top:0;'>
                <p style='margin:0;'>" . esc_html($nome_escola) . ($tel_escola ? " | Tel: " . esc_html($tel_escola) : "") . "</p>
                <p style='margin:5px 0 0 0;'>Comunicação enviada pela secretaria da escola.</p>
            </div>
        </div>";

        $assunto_final = (string)$cfg['assunto'];
        if (function_exists('sige_notify_humanize_outbound_email_html')) {
            $assunto_final = sige_notify_humanize_outbound_email_html($assunto_final);
            $corpo = sige_notify_humanize_outbound_email_html($corpo);
        }
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $enviado = wp_mail($email, $assunto_final, $corpo, $headers);

        if (function_exists('sige_fin_log')) {
            sige_fin_log('email_financeiro_enviado_fallback', [
                'tipo'     => $tipo_norm,
                'aluno_id' => $aluno_id,
                'email'    => $email,
                'valor'    => $valor_total,
                'enviado'  => $enviado,
            ]);
        }

        return (bool)$enviado;
    }
}
