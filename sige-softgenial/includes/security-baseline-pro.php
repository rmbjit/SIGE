<?php
/**
 * SIGE SoftGenial v12.10.141 - Security Baseline PRO
 *
 * Camada transversal de hardening: segredos por instalação, redução de
 * superfície REST, assinatura HMAC, rate limit, headers de segurança,
 * reset de senha por link único e validação de updates.
 *
 * Escopo: segurança. Não altera fórmulas financeiras/académicas.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_sec_log')) {
    function sige_sec_log(string $evento, array $ctx = []): void {
        $ctx_limpo = [];
        foreach ($ctx as $k => $v) {
            $ks = strtolower((string)$k);
            if (preg_match('/password|senha|token|secret|authorization|cookie|nonce|key/i', $ks)) {
                $ctx_limpo[$k] = '[redacted]';
            } else {
                $ctx_limpo[$k] = is_scalar($v) ? (string)$v : wp_json_encode($v, JSON_UNESCAPED_UNICODE);
            }
        }
        $msg = '[SIGE SECURITY BASELINE] ' . $evento . (!empty($ctx_limpo) ? ' ' . wp_json_encode($ctx_limpo, JSON_UNESCAPED_UNICODE) : '');
        if (function_exists('sige_security_log')) {
            try { sige_security_log($evento, $msg); return; } catch (Throwable $e) {}
        }
        error_log($msg);
    }
}

if (!function_exists('sige_sec_random_hex')) {
    function sige_sec_random_hex(int $bytes = 32): string {
        try { return bin2hex(random_bytes(max(16, $bytes))); }
        catch (Throwable $e) { return wp_generate_password(max(48, $bytes * 2), true, true); }
    }
}

if (!function_exists('sige_sec_secret_option')) {
    function sige_sec_secret_option(string $context): string {
        return 'sige_sec_secret_' . sanitize_key($context);
    }
}

if (!function_exists('sige_sec_get_secret')) {
    function sige_sec_get_secret(string $context, int $bytes = 32): string {
        $option = sige_sec_secret_option($context);
        $secret = (string)get_option($option, '');
        if (strlen($secret) < 32) {
            $secret = sige_sec_random_hex($bytes);
            update_option($option, $secret, false);
            sige_sec_log('segredo_instalacao_gerado', ['context' => sanitize_key($context)]);
        }
        return $secret;
    }
}

if (!function_exists('sige_sec_get_cron_key')) {
    function sige_sec_get_cron_key(): string {
        $key = (string)get_option('sige_wpp_queue_cron_key', '');
        if (strlen($key) < 32) {
            $legacy = (string)get_option('sige_cron_key', '');
            if (strlen($legacy) >= 32 && $legacy !== 'SGQ-2026-MZ-7f42c9e6d1b54a8f') {
                $key = $legacy;
            } else {
                $key = 'SGQ-' . sige_sec_random_hex(24);
            }
            update_option('sige_wpp_queue_cron_key', $key, false);
            update_option('sige_cron_key', $key, false);
            sige_sec_log('cron_key_unica_instalacao_gerada');
        }
        return $key;
    }
}

if (!function_exists('sige_sec_ip')) {
    function sige_sec_ip(): string {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return preg_replace('/[^0-9a-fA-F:\.]/', '', $ip) ?: '0.0.0.0';
    }
}

if (!function_exists('sige_sec_rate_limit')) {
    function sige_sec_rate_limit(string $scope, int $max = 30, int $window = 300): bool {
        $key = 'sige_sec_rl_' . md5($scope . '|' . sige_sec_ip());
        $count = (int)get_transient($key);
        if ($count >= $max) {
            sige_sec_log('rate_limit_bloqueado', ['scope' => $scope, 'ip' => sige_sec_ip(), 'max' => $max]);
            return false;
        }
        set_transient($key, $count + 1, $window);
        return true;
    }
}


// -----------------------------------------------------------------------------
// v12.10.141 - Redução de superfície REST: se a escola não usa webhook, o
// endpoint de WhatsApp fica desligado por defeito. Isto remove uma porta pública
// desnecessária e evita colisões entre guardian/recovery mode.
// -----------------------------------------------------------------------------
if (!function_exists('sige_sec_bool')) {
    function sige_sec_bool($value): bool {
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return ((int)$value) === 1;
        $v = strtolower(trim((string)$value));
        return in_array($v, ['1','true','yes','sim','on','enabled','activo','ativo'], true);
    }
}

if (!function_exists('sige_sec_whatsapp_webhook_enabled')) {
    function sige_sec_whatsapp_webhook_enabled(): bool {
        if (defined('SIGE_WPP_WEBHOOK_ENABLED')) {
            return sige_sec_bool(SIGE_WPP_WEBHOOK_ENABLED);
        }
        $enabled = get_option('sige_wpp_webhook_enabled', '0');
        return (bool)apply_filters('sige_wpp_webhook_enabled', sige_sec_bool($enabled));
    }
}

if (!function_exists('sige_sec_maybe_block_disabled_whatsapp_webhook')) {
    function sige_sec_maybe_block_disabled_whatsapp_webhook(): void {
        if (sige_sec_whatsapp_webhook_enabled()) return;
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($uri === '' || strpos($uri, '/wp-json/sige/v1/whatsapp-webhook') === false) return;
        sige_sec_log('webhook_whatsapp_bloqueado_por_configuracao', ['ip' => sige_sec_ip()]);
        status_header(404);
        nocache_headers();
        wp_send_json(['ok' => false, 'error' => 'whatsapp_webhook_disabled'], 404);
    }
}
add_action('parse_request', 'sige_sec_maybe_block_disabled_whatsapp_webhook', 1);

if (!function_exists('sige_sec_allow_cron_query_key')) {
    function sige_sec_allow_cron_query_key(): bool {
        if (defined('SIGE_ALLOW_CRON_KEY_IN_QUERY')) {
            return sige_sec_bool(SIGE_ALLOW_CRON_KEY_IN_QUERY);
        }
        // Bancário por defeito: segredos não devem circular em URL/logs.
        return (bool)apply_filters('sige_allow_cron_key_in_query', sige_sec_bool(get_option('sige_allow_cron_key_in_query', '0')));
    }
}

if (!function_exists('sige_sec_cron_key_from_request')) {
    function sige_sec_cron_key_from_request($request = null): string {
        $header = (string)($_SERVER['HTTP_X_SIGE_CRON_KEY'] ?? '');
        if ($header !== '') return trim($header);
        $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if ($auth && preg_match('/Bearer\s+(.+)/i', $auth, $m)) return trim((string)$m[1]);
        if ($request instanceof WP_REST_Request) {
            $h = (string)$request->get_header('x-sige-cron-key');
            if ($h !== '') return trim($h);
            $a = (string)$request->get_header('authorization');
            if ($a && preg_match('/Bearer\s+(.+)/i', $a, $m)) return trim((string)$m[1]);
            if (sige_sec_allow_cron_query_key()) return trim((string)$request->get_param('key'));
            return '';
        }
        if (sige_sec_allow_cron_query_key()) {
            return isset($_REQUEST['key']) ? trim(sanitize_text_field((string)$_REQUEST['key'])) : '';
        }
        return '';
    }
}

if (!function_exists('sige_sec_rest_body')) {
    function sige_sec_rest_body(WP_REST_Request $request): string {
        $body = (string)$request->get_body();
        if ($body !== '') return $body;
        $params = $request->get_json_params();
        if (!is_array($params) || empty($params)) $params = $request->get_params();
        if (!is_array($params)) $params = [];
        ksort($params);
        return wp_json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('sige_sec_validate_timestamp')) {
    function sige_sec_validate_timestamp($ts, int $skew = 300): bool {
        $ts = (int)$ts;
        return $ts > 0 && abs(time() - $ts) <= $skew;
    }
}

if (!function_exists('sige_sec_validate_hmac')) {
    /**
     * Valida assinatura HMAC nos headers:
     * X-SIGE-Timestamp: unix timestamp
     * X-SIGE-Signature: hex(hmac_sha256(timestamp + '.' + body, secret))
     */
    function sige_sec_validate_hmac(WP_REST_Request $request, string $secret, string $scope = 'generic') {
        if (!sige_sec_rate_limit('rest_' . sanitize_key($scope), 60, 300)) {
            return new WP_Error('sige_rate_limited', 'Demasiadas tentativas. Tente mais tarde.', ['status' => 429]);
        }
        $ts  = (string)$request->get_header('x-sige-timestamp');
        $sig = (string)$request->get_header('x-sige-signature');
        if ($ts === '' || $sig === '') {
            return new WP_Error('sige_signature_missing', 'Assinatura de segurança em falta.', ['status' => 401]);
        }
        if (!sige_sec_validate_timestamp($ts)) {
            return new WP_Error('sige_signature_expired', 'Assinatura expirada ou relógio desalinhado.', ['status' => 401]);
        }
        $body = sige_sec_rest_body($request);
        $expected = hash_hmac('sha256', $ts . '.' . $body, $secret);
        if (!hash_equals($expected, strtolower(trim($sig)))) {
            sige_sec_log('assinatura_hmac_invalida', ['scope' => $scope, 'ip' => sige_sec_ip()]);
            return new WP_Error('sige_signature_invalid', 'Assinatura inválida.', ['status' => 401]);
        }
        $nonce = (string)$request->get_header('x-sige-nonce');
        if ($nonce !== '') {
            $rkey = 'sige_sec_replay_' . md5($scope . '|' . $nonce . '|' . $sig);
            if (get_transient($rkey)) {
                return new WP_Error('sige_replay_blocked', 'Pedido repetido bloqueado.', ['status' => 409]);
            }
            set_transient($rkey, 1, 10 * MINUTE_IN_SECONDS);
        }
        return true;
    }
}

if (!function_exists('sige_sec_validate_rest_secret_or_hmac')) {
    function sige_sec_validate_rest_secret_or_hmac(WP_REST_Request $request, string $context) {
        $context = sanitize_key($context);
        $secret  = sige_sec_get_secret($context, 32);

        $hmac = sige_sec_validate_hmac($request, $secret, $context);
        if ($hmac === true) return true;

        // Compatibilidade controlada para provedores que só permitem secret simples.
        $provided = (string)$request->get_header('x-sige-webhook-secret');
        if ($provided === '') $provided = (string)$request->get_param('sige_webhook_key');
        if ($provided !== '' && hash_equals($secret, $provided)) {
            if (!sige_sec_rate_limit('rest_secret_' . $context, 60, 300)) {
                return new WP_Error('sige_rate_limited', 'Demasiadas tentativas. Tente mais tarde.', ['status' => 429]);
            }
            return true;
        }
        return $hmac instanceof WP_Error ? $hmac : new WP_Error('sige_signature_required', 'Assinatura obrigatória.', ['status' => 401]);
    }
}

if (!function_exists('sige_sec_hub_headers')) {
    function sige_sec_hub_headers(string $api_key, string $body = '', string $domain = ''): array {
        $ts = (string)time();
        $payload = $ts . '.' . $body;
        return [
            'Authorization' => 'Bearer ' . $api_key,
            'X-SIGE-Client' => $domain,
            'X-SIGE-Timestamp' => $ts,
            'X-SIGE-Signature' => hash_hmac('sha256', $payload, $api_key),
        ];
    }
}

if (!function_exists('sige_sec_create_password_reset_link')) {
    function sige_sec_create_password_reset_link(WP_User $user) {
        $key = get_password_reset_key($user);
        if (is_wp_error($key)) return $key;
        return network_site_url('wp-login.php?action=rp&key=' . rawurlencode($key) . '&login=' . rawurlencode($user->user_login), 'login');
    }
}

if (!function_exists('sige_sec_send_password_reset_email')) {
    function sige_sec_send_password_reset_email(WP_User $user, string $school_name = 'SIGE SoftGenial', string $from_email = ''): bool {
        $link = sige_sec_create_password_reset_link($user);
        if (is_wp_error($link)) return false;
        $nome = esc_html($user->display_name ?: $user->user_login);
        $school_name_clean = sanitize_text_field($school_name ?: 'SIGE SoftGenial');
        $body = '<html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937">'
            . '<div style="max-width:640px;margin:0 auto;padding:20px">'
            . '<h2 style="color:#1e293b">Definir nova senha de acesso</h2>'
            . '<p>Olá <strong>' . $nome . '</strong>,</p>'
            . '<p>A administração solicitou a redefinição da sua senha no sistema <strong>' . esc_html($school_name_clean) . '</strong>.</p>'
            . '<p>Por segurança, a sua nova senha não é enviada por e-mail nem mostrada no painel. Use o botão abaixo para definir uma senha pessoal.</p>'
            . '<p style="margin:24px 0"><a href="' . esc_url($link) . '" style="background:#172554;color:#fff;text-decoration:none;padding:12px 18px;border-radius:10px;display:inline-block">Definir nova senha</a></p>'
            . '<p style="font-size:13px;color:#64748b">Se não reconhece este pedido, ignore este e-mail e informe a secretaria.</p>'
            . '</div></body></html>';
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $from_email = sanitize_email($from_email);
        if ($from_email) $headers[] = 'From: ' . $school_name_clean . ' <' . $from_email . '>';
        return (bool)wp_mail($user->user_email, 'Definir nova senha - ' . $school_name_clean, $body, $headers);
    }
}

if (!function_exists('sige_sec_validate_backup_upload')) {
    function sige_sec_validate_backup_upload(array $file) {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('sige_backup_missing', 'Ficheiro de backup inválido.');
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 2 * 1024 * 1024) {
            return new WP_Error('sige_backup_size', 'Backup demasiado grande ou vazio. Limite: 2 MB.');
        }
        $name = (string)($file['name'] ?? '');
        if (!preg_match('/\.json$/i', $name)) {
            return new WP_Error('sige_backup_type', 'Apenas ficheiros JSON de configuração são aceites.');
        }
        $json = file_get_contents($file['tmp_name']);
        if ($json === false || strlen($json) > 2 * 1024 * 1024) {
            return new WP_Error('sige_backup_read', 'Não foi possível ler o backup.');
        }
        $dados = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($dados)) {
            return new WP_Error('sige_backup_json', 'JSON inválido.');
        }
        return $dados;
    }
}

if (!function_exists('sige_sec_recibo_ttl_days')) {
    function sige_sec_recibo_ttl_days(): int {
        $days = (int)get_option('sige_recibo_public_link_ttl_days', 30);
        return max(1, min(365, $days));
    }
}

if (!function_exists('sige_sec_recibo_token_expired')) {
    function sige_sec_recibo_token_expired(int $created_at): bool {
        if ($created_at <= 0) return false; // links legados sem timestamp ficam em compatibilidade transitória
        return time() > ($created_at + sige_sec_recibo_ttl_days() * DAY_IN_SECONDS);
    }
}

if (!function_exists('sige_sec_is_portaria_camera_request')) {
    /**
     * v12.11.9.79 - Detecta a Portaria Digital e o leitor dedicado da câmara.
     *
     * A política de segurança global continua bloqueando câmara em todo o SIGE,
     * mas a Portaria precisa de camera=(self) para leitura dos crachás.
     * O leitor dedicado usa ?sige_portaria_camera=1 para isolar a leitura dos crachás
     * e manter a experiência da Portaria estável em mobile/tablet.
     */
    function sige_sec_is_portaria_camera_request(): bool {
        $safe_camera = isset($_GET['sige_portaria_camera']) ? sanitize_key((string)wp_unslash($_GET['sige_portaria_camera'])) : '';
        if ($safe_camera === '1') {
            return true;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string)wp_unslash($_SERVER['REQUEST_URI']) : '';
        if ($request_uri !== '') {
            $query = (string)wp_parse_url($request_uri, PHP_URL_QUERY);
            parse_str($query, $params);
            $uri_safe = isset($params['sige_portaria_camera']) ? sanitize_key((string)$params['sige_portaria_camera']) : '';
            if ($uri_safe === '1') {
                return true;
            }
        }

        if (!function_exists('is_admin') || !is_admin()) return false;

        $page = isset($_GET['page']) ? sanitize_key((string)wp_unslash($_GET['page'])) : '';
        $view = isset($_GET['view']) ? sanitize_key((string)wp_unslash($_GET['view'])) : '';

        if ($page === 'sige-app' && $view === 'portaria') {
            return true;
        }

        // Defesa adicional: alguns proxies/caches reescrevem a query antes de
        // plugins tardios inspeccionarem $_GET. O request_uri mantém o URL real
        // pedido pelo browser e evita voltar a enviar camera=() na Portaria.
        if ($request_uri !== '') {
            $query = (string)wp_parse_url($request_uri, PHP_URL_QUERY);
            parse_str($query, $params);
            $uri_page = isset($params['page']) ? sanitize_key((string)$params['page']) : '';
            $uri_view = isset($params['view']) ? sanitize_key((string)$params['view']) : '';
            if ($uri_page === 'sige-app' && $uri_view === 'portaria') {
                return true;
            }
        }

        return false;
    }
}
if (!function_exists('sige_sec_permissions_policy_header')) {
    /**
     * v12.11.9.75 - Fonte única para Permissions-Policy.
     *
     * Mantém princípio de menor privilégio: câmara só fica liberada na tela de
     * Portaria Digital, onde existe gesto explícito do utilizador e permissão
     * própria portaria.validar_acesso.
     */
    function sige_sec_permissions_policy_header(): string {
        if (sige_sec_is_portaria_camera_request()) {
            return 'camera=(self), microphone=(), geolocation=(), payment=()';
        }
        return 'camera=(), microphone=(), geolocation=(), payment=()';
    }
}

if (!function_exists('sige_sec_filter_wp_headers')) {
    /**
     * v12.11.9.75 - Garante a mesma política no pipeline nativo de headers do WP.
     *
     * header(..., true) substitui cabeçalhos emitidos por PHP, mas alguns stacks
     * montam a lista final através do filtro wp_headers. Esta camada reduz a
     * probabilidade de o mobile receber camera=() por uma política antiga/cacheada.
     */
    function sige_sec_filter_wp_headers(array $headers): array {
        $headers['Permissions-Policy'] = sige_sec_permissions_policy_header();
        return $headers;
    }
}
add_filter('wp_headers', 'sige_sec_filter_wp_headers', 999, 1);

if (!function_exists('sige_sec_add_headers')) {
    function sige_sec_add_headers(): void {
        if (headers_sent()) return;
        header('X-Content-Type-Options: nosniff', true);
        header('X-Frame-Options: SAMEORIGIN', true);
        header('Referrer-Policy: strict-origin-when-cross-origin', true);
        header('Permissions-Policy: ' . sige_sec_permissions_policy_header(), true);
        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains', true);
        }
        // CSP report-only para não partir UIs com inline scripts herdados.
        header("Content-Security-Policy-Report-Only: default-src 'self' https: data: blob:; img-src 'self' https: data: blob:; object-src 'none'; base-uri 'self'; frame-ancestors 'self'", true);
    }
}
add_action('send_headers', 'sige_sec_add_headers', 1);
add_action('admin_init', 'sige_sec_add_headers', 1);
// v12.11.9.75: reforço tardio para substituir políticas rígidas carregadas cedo,
// mantendo camera=(self) apenas na Portaria Digital.
add_action('send_headers', 'sige_sec_add_headers', 999);
add_action('admin_init', 'sige_sec_add_headers', 999);

if (!function_exists('sige_sec_is_remote_url')) {
    /**
     * v12.10.142 - Detecta apenas pacotes remotos reais.
     *
     * O WordPress também usa o filtro upgrader_pre_download em alguns fluxos
     * de instalação manual por upload. Nesses casos o pacote pode ser um
     * caminho local/temporário. A v12.10.141 era demasiado agressiva: se o
     * nome do ZIP contivesse "sige-softgenial", o upload local era tratado
     * como download remoto e acabava bloqueado por não ter host.
     */
    function sige_sec_is_remote_url(string $package): bool {
        $scheme = wp_parse_url($package, PHP_URL_SCHEME);
        return in_array(strtolower((string)$scheme), ['http', 'https'], true);
    }
}

if (!function_exists('sige_sec_update_allowed_hosts')) {
    function sige_sec_update_allowed_hosts(): array {
        $hosts = ['softgenial.edu.mz', 'www.softgenial.edu.mz'];

        $extra = get_option('sige_allowed_update_hosts', []);
        if (is_string($extra) && $extra !== '') {
            $extra = preg_split('/[,\s]+/', $extra);
        }
        if (is_array($extra)) {
            $hosts = array_merge($hosts, $extra);
        }

        if (defined('SIGE_ALLOWED_UPDATE_HOSTS') && is_string(SIGE_ALLOWED_UPDATE_HOSTS)) {
            $hosts = array_merge($hosts, preg_split('/[,\s]+/', SIGE_ALLOWED_UPDATE_HOSTS));
        }

        $hosts = apply_filters('sige_allowed_update_hosts', $hosts);
        $hosts = array_filter(array_map(static function($host) {
            $host = strtolower(trim((string)$host));
            $host = preg_replace('#^https?://#', '', $host);
            $host = explode('/', $host)[0] ?? $host;
            $host = preg_replace('/:\d+$/', '', $host);
            return $host;
        }, (array)$hosts));

        return array_values(array_unique($hosts));
    }
}

if (!function_exists('sige_sec_get_update_manifest_for_package')) {
    /**
     * Só aplicamos validação forte quando o pacote vem do nosso mecanismo de
     * update remoto. Instalações manuais pelo painel continuam permitidas.
     */
    function sige_sec_get_update_manifest_for_package(string $package) {
        foreach (['sige_hub_update_check', 'sige_update_check'] as $tk) {
            $info = get_transient($tk);
            if ($info instanceof stdClass) {
                $remote_pkg = (string)($info->download_url ?? '');
                if ($remote_pkg !== '' && hash_equals($remote_pkg, $package)) {
                    return $info;
                }
            }
        }
        return null;
    }
}

if (!function_exists('sige_sec_validate_update_package')) {
    function sige_sec_validate_update_package($reply, $package, $upgrader, $hook_extra = []) {
        if (!is_string($package) || trim($package) === '') return $reply;

        // Não bloquear upload/manual install, caminhos locais ou pacotes temporários do WordPress.
        if (!sige_sec_is_remote_url($package)) return $reply;

        // Emergency bypass explícito para recuperação operacional controlada.
        if (defined('SIGE_DISABLE_UPDATE_ORIGIN_GUARD') && SIGE_DISABLE_UPDATE_ORIGIN_GUARD) {
            return $reply;
        }

        $manifest = sige_sec_get_update_manifest_for_package($package);

        // Se não é pacote anunciado pelo mecanismo SoftGenial, não interferimos.
        if (!$manifest) return $reply;

        $host = strtolower((string)wp_parse_url($package, PHP_URL_HOST));
        $allowed_hosts = sige_sec_update_allowed_hosts();
        if (!$host || !in_array($host, $allowed_hosts, true)) {
            return new WP_Error(
                'sige_update_host_blocked',
                'Actualização bloqueada: origem do pacote não autorizada. Configure o host no Hub/manifesto ou em SIGE_ALLOWED_UPDATE_HOSTS.'
            );
        }

        $checksum = (string)($manifest->sha256 ?? $manifest->checksum_sha256 ?? $manifest->checksum ?? '');
        $require_checksum = (bool)apply_filters('sige_security_require_signed_updates', true);
        if ($require_checksum && !preg_match('/^[a-f0-9]{64}$/i', $checksum)) {
            return new WP_Error('sige_update_unsigned', 'Actualização bloqueada: o manifesto não contém checksum SHA-256 válido.');
        }
        if ($checksum === '') return $reply;

        if (!function_exists('download_url')) require_once ABSPATH . 'wp-admin/includes/file.php';
        $file = download_url($package, 30);
        if (is_wp_error($file)) return $file;
        $actual = hash_file('sha256', $file);
        if (!hash_equals(strtolower($checksum), strtolower($actual))) {
            @unlink($file);
            return new WP_Error('sige_update_checksum_mismatch', 'Actualização bloqueada: checksum SHA-256 não confere.');
        }
        return $file;
    }
}
add_filter('upgrader_pre_download', 'sige_sec_validate_update_package', 10, 4);

if (!function_exists('sige_security_baseline_install')) {
    function sige_security_baseline_install(): void {
        sige_sec_get_cron_key();
        sige_sec_get_secret('hub_instant_refresh', 32);
        if (sige_sec_whatsapp_webhook_enabled()) {
            sige_sec_get_secret('whatsapp_webhook', 32);
        }
        if (get_option('sige_recibo_public_link_ttl_days', null) === null) {
            update_option('sige_recibo_public_link_ttl_days', 30, false);
        }
    }
}
add_action('admin_init', function () {
    if ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !get_option('sige_security_baseline_141_installed')) {
        sige_security_baseline_install();
        update_option('sige_security_baseline_141_installed', time(), false);
    }
}, 2);
