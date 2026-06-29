<?php
/**
 * SIGE SoftGenial v12.12.7 - Critical Actions Lockdown.
 *
 * Central runtime for request surfaces. v12.12.7 adds critical action lockdown primitives: view_action
 * dispatch, delegated/retired modes, object guards, token/HMAC intent
 * validation and stronger tenant-aware runtime controls.
 */
if (!defined('ABSPATH') && !defined('SIGE_SECURITY_KERNEL_TEST_MODE')) exit;

if (!defined('SIGE_SECURITY_KERNEL_EARLY_PRIORITY')) {
    // Run before legacy handlers registered at priority 0. WordPress accepts
    // negative priorities; this is intentional for query/runtime guards.
    define('SIGE_SECURITY_KERNEL_EARLY_PRIORITY', -1000);
}

if (defined('SIGE_PATH') && file_exists(SIGE_PATH . 'includes/security-kernel-rules.php')) {
    require_once SIGE_PATH . 'includes/security-kernel-rules.php';
} elseif (file_exists(__DIR__ . '/security-kernel-rules.php')) {
    require_once __DIR__ . '/security-kernel-rules.php';
}

if (!function_exists('sige_security_kernel_all_rules')) {
    function sige_security_kernel_all_rules(): array {
        $raw = function_exists('sige_security_kernel_rules') ? sige_security_kernel_rules() : [];
        $rules = [];
        foreach ((array)$raw as $key => $rule) {
            if (!is_array($rule)) continue;
            $id = isset($rule['id']) ? (string)$rule['id'] : (is_string($key) ? $key : '');
            if ($id === '') continue;
            $rule['id'] = $id;
            $rules[$id] = $rule;
        }
        ksort($rules);
        return $rules;
    }
}

if (!function_exists('sige_security_kernel_get_rule')) {
    function sige_security_kernel_get_rule(string $surface_id): ?array {
        $rules = sige_security_kernel_all_rules();
        return isset($rules[$surface_id]) && is_array($rules[$surface_id]) ? $rules[$surface_id] : null;
    }
}


if (!function_exists('sige_security_kernel_rest_route_from_rule')) {
    function sige_security_kernel_rest_route_from_rule(array $rule): string {
        $explicit = (string)($rule['route'] ?? '');
        if ($explicit === '') $explicit = (string)($rule['extra'] ?? '');
        $namespace = (string)($rule['name'] ?? '');
        if ($explicit === '') {
            $id = (string)($rule['id'] ?? '');
            if (preg_match('/^rest_route:([^:]+):(.+)$/', $id, $m)) {
                $namespace = $namespace !== '' ? $namespace : $m[1];
                $explicit = $m[2];
            }
        }
        if ($namespace === '' || $explicit === '') return '';
        return '/' . trim($namespace, '/') . '/' . ltrim($explicit, '/');
    }
}

if (!function_exists('sige_security_kernel_rest_surface_id_for_route')) {
    function sige_security_kernel_rest_surface_id_for_route(string $route): string {
        $route = '/' . trim($route, '/');
        foreach (sige_security_kernel_all_rules() as $surface_id => $rule) {
            if (($rule['type'] ?? '') !== 'rest_route') continue;
            $expected = sige_security_kernel_rest_route_from_rule($rule);
            if ($expected !== '' && rtrim($route, '/') === rtrim($expected, '/')) return $surface_id;
        }
        return '';
    }
}

if (!function_exists('sige_security_kernel_current_surface')) {
    function sige_security_kernel_current_surface(): ?array {
        $action_raw = isset($_REQUEST['action']) && !is_array($_REQUEST['action']) ? (function_exists('wp_unslash') ? wp_unslash((string)$_REQUEST['action']) : (string)$_REQUEST['action']) : '';
        $action = $action_raw !== '' ? (function_exists('sanitize_key') ? sanitize_key($action_raw) : preg_replace('/[^a-z0-9_\-]+/i', '', strtolower($action_raw))) : '';
        if ($action !== '') {
            if ((function_exists('wp_doing_ajax') && wp_doing_ajax()) || (defined('DOING_AJAX') && DOING_AJAX)) {
                $type = function_exists('is_user_logged_in') && is_user_logged_in() ? 'wp_ajax' : 'wp_ajax_nopriv';
                return ['id' => $type . ':' . $action, 'type' => $type, 'name' => $action];
            }
            if ((function_exists('is_admin') && is_admin()) || (defined('WP_ADMIN') && WP_ADMIN)) {
                $type = function_exists('is_user_logged_in') && is_user_logged_in() ? 'admin_post' : 'admin_post_nopriv';
                return ['id' => $type . ':' . $action, 'type' => $type, 'name' => $action];
            }
        }
        foreach (sige_security_kernel_all_rules() as $id => $rule) {
            if (($rule['type'] ?? '') !== 'query_handler') continue;
            $name = (string)($rule['name'] ?? '');
            if ($name !== '' && (isset($_GET[$name]) || isset($_REQUEST[$name]))) {
                return ['id' => $id, 'type' => 'query_handler', 'name' => $name];
            }
        }
        foreach (sige_security_kernel_matching_view_actions() as $surface_id => $rule) {
            return ['id' => $surface_id, 'type' => 'view_action', 'name' => (string)($rule['name'] ?? '')];
        }
        return null;
    }
}

if (!function_exists('sige_security_kernel_request_value')) {
    function sige_security_kernel_request_value(string $field, string $source = 'request'): string {
        if ($field === '') return '';
        if ($source === 'get') {
            $raw = $_GET[$field] ?? '';
        } elseif ($source === 'post') {
            $raw = $_POST[$field] ?? '';
        } else {
            $raw = $_REQUEST[$field] ?? '';
        }
        if (is_array($raw)) return '';
        $value = function_exists('wp_unslash') ? wp_unslash((string)$raw) : (string)$raw;
        return trim($value);
    }
}

if (!function_exists('sige_security_kernel_token_from_context')) {
    function sige_security_kernel_token_from_context(array $rule, array $context = []): string {
        $intent = isset($rule['intent']) && is_array($rule['intent']) ? $rule['intent'] : [];
        $field = (string)($intent['field'] ?? 'token');
        $source = (string)($intent['source'] ?? 'request');
        if ($source === 'rest_param_or_header' && isset($context['rest_request']) && is_object($context['rest_request'])) {
            $req = $context['rest_request'];
            $token = '';
            if (method_exists($req, 'get_param')) $token = (string)$req->get_param($field);
            if ($token === '' && method_exists($req, 'get_header')) {
                $header = (string)($intent['header'] ?? 'x-sige-token');
                $token = (string)$req->get_header($header);
            }
            return trim($token);
        }
        return sige_security_kernel_request_value($field, $source === 'post' || $source === 'get' ? $source : 'request');
    }
}

if (!function_exists('sige_security_kernel_validate_mobile_token')) {
    function sige_security_kernel_validate_mobile_token(string $provider, string $token, array $context = []): bool {
        $provider = strtolower(trim($provider));
        if (!in_array($provider, ['mpesa', 'emola'], true) || trim($token) === '') return false;
        $school_hint = 0;
        if (isset($context['rest_request']) && is_object($context['rest_request']) && method_exists($context['rest_request'], 'get_param')) {
            $req = $context['rest_request'];
            $school_hint = (int)($req->get_param('escola_id') ?: $req->get_param('school_id'));
        } else {
            $school_hint = isset($_REQUEST['escola_id']) && !is_array($_REQUEST['escola_id']) ? (int)$_REQUEST['escola_id'] : 0;
        }
        $school_id = function_exists('sige_mobile_payment_find_school_by_token')
            ? (int)sige_mobile_payment_find_school_by_token($provider, $token, $school_hint)
            : 0;
        if ($school_id <= 0) return false;
        if (isset($context['rest_request']) && is_object($context['rest_request']) && method_exists($context['rest_request'], 'set_param')) {
            $context['rest_request']->set_param('_sige_escola_id', $school_id);
        }
        $_REQUEST['_sige_escola_id'] = $school_id;
        return true;
    }
}

if (!function_exists('sige_security_kernel_verify_intent')) {
    function sige_security_kernel_verify_intent(array $rule, array $context = []): bool {
        $intent = isset($rule['intent']) && is_array($rule['intent']) ? $rule['intent'] : [];
        $type = (string)($intent['type'] ?? 'none');
        $mode = (string)($rule['mode'] ?? 'observe');
        if ($type === 'declared_later' || $type === 'documented_public_token_or_hmac') {
            return $mode !== 'enforce';
        }
        if ($type === 'none' || $type === '') return !in_array($mode, ['enforce','delegated'], true);
        if ($type === 'defer' || $type === 'domain' || $type === 'delegated') return true;
        if ($type === 'nonce') {
            if (!function_exists('wp_verify_nonce')) return false;
            $field = (string)($intent['field'] ?? '_wpnonce');
            $action = (string)($intent['action'] ?? '');
            $source = (string)($intent['source'] ?? 'request');
            if ($field === '' || $action === '') return false;
            $nonce = sige_security_kernel_request_value($field, $source);
            return $nonce !== '' && (bool)wp_verify_nonce($nonce, $action);
        }
        if ($type === 'token' || $type === 'hmac' || $type === 'hmac_or_token') {
            $token = sige_security_kernel_token_from_context($rule, $context);
            if ($token === '') return false;
            $validator = (string)($intent['validator'] ?? '');
            if ($validator === 'mobile_payment_webhook') {
                return sige_security_kernel_validate_mobile_token((string)($intent['provider'] ?? ''), $token, $context);
            }
            if (!empty($intent['expected_option'])) {
                $expected = (string)get_option((string)$intent['expected_option'], '');
                return $expected !== '' && function_exists('hash_equals') && hash_equals($expected, $token);
            }
            return false;
        }
        return false;
    }
}


if (!function_exists('sige_security_kernel_require_tenant')) {
    function sige_security_kernel_require_tenant(array $rule): bool {
        if (empty($rule['tenant_required'])) return true;
        if (isset($_REQUEST['_sige_escola_id']) && !is_array($_REQUEST['_sige_escola_id']) && (int)$_REQUEST['_sige_escola_id'] > 0) return true;
        if (!function_exists('sige_get_escola_id')) return false;
        return (int)sige_get_escola_id() > 0;
    }
}

if (!function_exists('sige_security_kernel_require_permissions')) {
    function sige_security_kernel_require_permissions(array $rule): bool {
        if (!empty($rule['public'])) return true;
        $auth_mode = (string)($rule['authorization_mode'] ?? ($rule['permission_mode'] ?? 'permissions'));
        if ($auth_mode === 'delegated' || $auth_mode === 'defer_to_domain_policy') return true;
        $permissions = isset($rule['permissions']) && is_array($rule['permissions']) ? array_values(array_filter(array_map('strval', $rule['permissions']))) : [];
        $legacy_caps = isset($rule['legacy_caps']) && is_array($rule['legacy_caps']) ? array_values(array_filter(array_map('strval', $rule['legacy_caps']))) : [];
        if (empty($permissions) && empty($legacy_caps)) return false;
        if (function_exists('sige_user_can_any_secure')) return (bool)sige_user_can_any_secure($permissions, $legacy_caps);
        if (function_exists('sige_can')) {
            foreach ($permissions as $permission) {
                if ($permission !== '' && sige_can($permission, ['surface' => (string)($rule['id'] ?? '')])) return true;
            }
        }
        // Fallback legado directo nao e chamado aqui para evitar reabrir
        // autorizacao fora da matriz. Em runtime normal, sige_user_can_any_secure()
        // ja cobre as legacy caps com a regra anti-bypass do SIGE.
        return false;
    }
}

if (!function_exists('sige_security_kernel_request_int')) {
    function sige_security_kernel_request_int(string $field, string $source = 'request'): int {
        $value = sige_security_kernel_request_value($field, $source);
        return (int)$value;
    }
}

if (!function_exists('sige_security_kernel_require_object_guards')) {
    function sige_security_kernel_require_object_guards(array $rule): bool {
        $guards = isset($rule['object_guards']) && is_array($rule['object_guards']) ? $rule['object_guards'] : [];
        if (empty($guards)) return true;
        if (!function_exists('sige_get_escola_id')) return false;
        $school_id = (int)sige_get_escola_id();
        if ($school_id <= 0) return false;
        global $wpdb;
        foreach ($guards as $guard) {
            if (!is_array($guard)) return false;
            $field = (string)($guard['field'] ?? 'id');
            $source = (string)($guard['source'] ?? 'request');
            $id = sige_security_kernel_request_int($field, $source);
            if ($id <= 0) {
                if (!empty($guard['required'])) return false;
                continue;
            }
            $table_key = (string)($guard['table'] ?? '');
            if ($table_key === '') return false;
            $table = preg_match('/^sige_/', $table_key) ? $wpdb->prefix . $table_key : $table_key;
            $primary = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($guard['primary_key'] ?? 'id'));
            $tenant_col = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($guard['tenant_column'] ?? 'escola_id'));
            if ($primary === '' || $tenant_col === '') return false;
            $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE {$primary} = %d AND {$tenant_col} = %d LIMIT 1", $id, $school_id));
            if ($exists <= 0) return false;
        }
        return true;
    }
}


if (!function_exists('sige_security_kernel_rate_limit')) {
    function sige_security_kernel_rate_limit(array $rule): bool {
        if (empty($rule['rate_limit']) || !is_array($rule['rate_limit'])) return true;
        $rl = $rule['rate_limit'];
        $fallback = 'security_kernel_' . md5((string)($rule['id'] ?? 'unknown'));
        $key_raw = (string)($rl['key'] ?? $fallback);
        $key = function_exists('sanitize_key') ? sanitize_key($key_raw) : preg_replace('/[^a-z0-9_]+/i', '_', $key_raw);
        $max = max(1, (int)($rl['max'] ?? 30));
        $window = max(30, (int)($rl['window'] ?? 300));
        if (function_exists('sige_rate_limit_action')) return (bool)sige_rate_limit_action($key, $max, $window, false);
        if (function_exists('sige_sec_rate_limit')) return (bool)sige_sec_rate_limit($key, $max, $window);
        return true;
    }
}

if (!function_exists('sige_security_kernel_safe_context')) {
    function sige_security_kernel_safe_context(array $context): array {
        $blocked = ['_wpnonce', 'nonce', 'token', 'key', 'secret', 'password', 'senha', 'api_key', 'api_secret', 'public_key'];
        $safe = [];
        foreach ($context as $k => $v) {
            $key = preg_replace('/[^a-zA-Z0-9_.:-]/', '', (string)$k);
            if ($key === '') continue;
            $lower = strtolower($key);
            foreach ($blocked as $frag) {
                if (strpos($lower, $frag) !== false) { $safe[$key] = '[redacted]'; continue 2; }
            }
            $safe[$key] = is_scalar($v) || $v === null ? $v : gettype($v);
        }
        if (function_exists('get_current_user_id')) $safe['user_id'] = (int)get_current_user_id();
        if (function_exists('sige_get_escola_id')) $safe['school_id'] = (int)sige_get_escola_id();
        if (isset($_SERVER['REQUEST_URI'])) $safe['path'] = (string)parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH);
        return $safe;
    }
}

if (!function_exists('sige_security_kernel_audit')) {
    function sige_security_kernel_audit(string $event, array $context = []): void {
        $safe = sige_security_kernel_safe_context($context);
        $pairs = [];
        foreach ($safe as $k => $v) {
            if (is_scalar($v) || $v === null) $pairs[] = $k . '=' . (string)$v;
        }
        $line = implode('; ', array_slice($pairs, 0, 14));
        if (function_exists('sige_security_log')) {
            sige_security_log($event, $line);
            return;
        }
        if (function_exists('error_log')) error_log('[SIGE SECURITY KERNEL] ' . $event . ($line !== '' ? ' - ' . $line : ''));
    }
}

if (!function_exists('sige_security_kernel_block')) {
    function sige_security_kernel_block(string $surface_id, string $reason, array $context = []) {
        $message = 'Acesso bloqueado pelo nucleo de seguranca do SIGE.';
        $transport = (string)($context['transport'] ?? 'web');
        if ($transport === 'rest' && class_exists('WP_Error')) {
            return new WP_Error('sige_security_kernel_denied', $message, ['status' => 403, 'surface_id' => $surface_id, 'reason' => $reason]);
        }
        if ($transport === 'ajax' && function_exists('wp_send_json_error')) {
            wp_send_json_error(['message' => $message, 'reason' => $reason], 403);
        }
        if (function_exists('wp_die')) {
            wp_die(function_exists('esc_html') ? esc_html($message) : $message, 'SIGE - Security Kernel', ['response' => 403]);
        }
        if (!headers_sent()) http_response_code(403);
        exit($message);
    }
}

if (!function_exists('sige_security_kernel_dispatch')) {
    function sige_security_kernel_dispatch(string $surface_id, array $context = []) {
        static $processed = [];
        $rule = sige_security_kernel_get_rule($surface_id);
        if (!is_array($rule)) {
            sige_security_kernel_audit('security_kernel.unknown_surface', ['surface_id' => $surface_id] + $context);
            return true;
        }
        $mode = (string)($rule['mode'] ?? 'observe');
        if ($mode === 'retired') {
            sige_security_kernel_audit('security_kernel.retired_surface_blocked', ['surface_id' => $surface_id, 'mode' => $mode] + $context);
            return sige_security_kernel_block($surface_id, 'retired_surface', $context);
        }
        if (!in_array($mode, ['enforce','delegated'], true)) {
            if (defined('SIGE_SECURITY_KERNEL_OBSERVE_LOG') && SIGE_SECURITY_KERNEL_OBSERVE_LOG) {
                sige_security_kernel_audit('security_kernel.observed', ['surface_id' => $surface_id, 'mode' => $mode] + $context);
            }
            return true;
        }
        if (isset($processed[$surface_id])) return true;
        $processed[$surface_id] = true;

        $failure = '';
        if (empty($rule['public']) && function_exists('is_user_logged_in') && !is_user_logged_in()) $failure = 'auth_required';
        if ($failure === '' && !sige_security_kernel_rate_limit($rule)) $failure = 'rate_limited';
        if ($failure === '' && !sige_security_kernel_verify_intent($rule, $context)) $failure = 'invalid_intent';
        if ($failure === '' && !sige_security_kernel_require_tenant($rule)) $failure = 'tenant_missing';
        if ($failure === '' && !sige_security_kernel_require_permissions($rule)) $failure = 'permission_denied';
        if ($failure === '' && !sige_security_kernel_require_object_guards($rule)) $failure = 'object_scope_denied';

        if ($failure !== '') {
            sige_security_kernel_audit('security_kernel.denied', ['surface_id' => $surface_id, 'mode' => $mode, 'reason' => $failure] + $context);
            return sige_security_kernel_block($surface_id, $failure, $context);
        }
        if (!empty($rule['audit'])) {
            sige_security_kernel_audit('security_kernel.allowed', ['surface_id' => $surface_id, 'mode' => $mode, 'risk' => (string)($rule['risk'] ?? 'medium')] + $context);
        }
        return true;
    }
}

if (!function_exists('sige_security_kernel_enforce')) {
    function sige_security_kernel_enforce(string $surface_id, array $context = []) {
        return sige_security_kernel_dispatch($surface_id, $context);
    }
}

if (!function_exists('sige_security_kernel_dispatch_query_handlers')) {
    function sige_security_kernel_dispatch_query_handlers(string $hook = ''): void {
        foreach (sige_security_kernel_all_rules() as $surface_id => $rule) {
            if (($rule['type'] ?? '') !== 'query_handler') continue;
            $name = (string)($rule['name'] ?? '');
            if ($name !== '' && (isset($_GET[$name]) || isset($_REQUEST[$name]))) {
                sige_security_kernel_dispatch($surface_id, [
                    'transport' => 'query_handler',
                    'runtime_hook' => $hook !== '' ? $hook : (function_exists('current_filter') ? (string) current_filter() : ''),
                    'query_key' => $name,
                ]);
            }
        }
    }
}

if (!function_exists('sige_security_kernel_dispatch_query_handlers_on_hook')) {
    function sige_security_kernel_dispatch_query_handlers_on_hook(string $hook): void {
        sige_security_kernel_dispatch_query_handlers($hook);
    }
}

if (!function_exists('sige_security_kernel_rest_pre_dispatch')) {
    function sige_security_kernel_rest_pre_dispatch($result, $server, $request) {
        if (!is_object($request) || !method_exists($request, 'get_route')) return $result;
        $route = (string)$request->get_route();
        $surface_id = sige_security_kernel_rest_surface_id_for_route($route);
        if ($surface_id !== '') {
            $decision = sige_security_kernel_dispatch($surface_id, ['transport' => 'rest', 'return_error' => true, 'rest_route' => $route, 'rest_request' => $request]);
            if (class_exists('WP_Error') && $decision instanceof WP_Error) return $decision;
        }
        return $result;
    }
}


if (!function_exists('sige_security_kernel_shortcode_pre')) {
    function sige_security_kernel_shortcode_pre($return, $tag, $attr = [], $m = []) {
        $tag = is_scalar($tag) ? (string)$tag : '';
        if ($tag === '') return $return;
        $surface_id = 'shortcode:' . $tag;
        if (sige_security_kernel_get_rule($surface_id)) {
            $decision = sige_security_kernel_dispatch($surface_id, ['transport' => 'shortcode', 'shortcode' => $tag]);
            if (class_exists('WP_Error') && $decision instanceof WP_Error) return '';
        }
        return $return;
    }
}

if (!function_exists('sige_security_kernel_dispatch_wp_hook')) {
    function sige_security_kernel_dispatch_wp_hook(string $hook): bool {
        $surface_id = 'wp_hook:' . $hook;
        if (!sige_security_kernel_get_rule($surface_id)) return true;
        sige_security_kernel_dispatch($surface_id, ['transport' => 'wp_hook', 'wp_hook' => $hook]);
        return true;
    }
}

if (!function_exists('sige_security_kernel_view_action_matches')) {
    function sige_security_kernel_view_action_matches(array $rule): bool {
        if (($rule['type'] ?? '') !== 'view_action') return false;
        $page = isset($_REQUEST['page']) && !is_array($_REQUEST['page']) ? sanitize_key((string)wp_unslash($_REQUEST['page'])) : '';
        $view = isset($_REQUEST['view']) && !is_array($_REQUEST['view']) ? sanitize_key((string)wp_unslash($_REQUEST['view'])) : '';
        if ($page === '') $page = 'sige-app';
        if ((string)($rule['page'] ?? 'sige-app') !== '' && $page !== (string)($rule['page'] ?? 'sige-app')) return false;
        if ((string)($rule['view'] ?? '') !== '' && $view !== sanitize_key((string)$rule['view'])) return false;
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $expected_method = strtoupper((string)($rule['method'] ?? 'POST'));
        if ($expected_method !== '' && $method !== $expected_method) return false;
        $disc = isset($rule['discriminator']) && is_array($rule['discriminator']) ? $rule['discriminator'] : [];
        $field = (string)($disc['field'] ?? '');
        if ($field === '') return true;
        $source = (string)($disc['source'] ?? ($expected_method === 'GET' ? 'get' : 'post'));
        $exists = $source === 'get' ? isset($_GET[$field]) : ($source === 'request' ? isset($_REQUEST[$field]) : isset($_POST[$field]));
        if (!$exists) return false;
        if (array_key_exists('value', $disc) && $disc['value'] !== null) {
            return sige_security_kernel_request_value($field, $source) === (string)$disc['value'];
        }
        return true;
    }
}

if (!function_exists('sige_security_kernel_matching_view_actions')) {
    function sige_security_kernel_matching_view_actions(): array {
        $matches = [];
        foreach (sige_security_kernel_all_rules() as $surface_id => $rule) {
            if (($rule['type'] ?? '') === 'view_action' && sige_security_kernel_view_action_matches($rule)) {
                $matches[$surface_id] = $rule;
            }
        }
        return $matches;
    }
}

if (!function_exists('sige_security_kernel_dispatch_view_actions')) {
    function sige_security_kernel_dispatch_view_actions(string $hook = ''): void {
        foreach (sige_security_kernel_matching_view_actions() as $surface_id => $rule) {
            sige_security_kernel_dispatch($surface_id, [
                'transport' => 'view_action',
                'runtime_hook' => $hook !== '' ? $hook : (function_exists('current_filter') ? (string) current_filter() : ''),
                'view' => (string)($rule['view'] ?? ''),
                'method' => (string)($rule['method'] ?? ''),
            ]);
        }
    }
}


if (!function_exists('sige_security_kernel_register')) {
    function sige_security_kernel_register(): void {
        if (!function_exists('add_action')) return;
        $early = (int) SIGE_SECURITY_KERNEL_EARLY_PRIORITY;
        foreach (sige_security_kernel_all_rules() as $surface_id => $rule) {
            $type = (string)($rule['type'] ?? '');
            $name = (string)($rule['name'] ?? '');
            if ($name === '') continue;
            if ($type === 'wp_ajax') {
                add_action('wp_ajax_' . $name, static function () use ($surface_id): void { sige_security_kernel_dispatch($surface_id, ['transport' => 'ajax']); }, 0);
            } elseif ($type === 'wp_ajax_nopriv') {
                add_action('wp_ajax_nopriv_' . $name, static function () use ($surface_id): void { sige_security_kernel_dispatch($surface_id, ['transport' => 'ajax']); }, 0);
            } elseif ($type === 'admin_post') {
                add_action('admin_post_' . $name, static function () use ($surface_id): void { sige_security_kernel_dispatch($surface_id, ['transport' => 'admin_post']); }, 0);
            } elseif ($type === 'admin_post_nopriv') {
                add_action('admin_post_nopriv_' . $name, static function () use ($surface_id): void { sige_security_kernel_dispatch($surface_id, ['transport' => 'admin_post']); }, 0);
            } elseif ($type === 'cron_hook') {
                add_action($name, static function () use ($surface_id): void { sige_security_kernel_dispatch($surface_id, ['transport' => 'cron']); }, 0);
            } elseif ($type === 'wp_hook') {
                add_action($name, static function () use ($name): void { sige_security_kernel_dispatch_wp_hook($name); }, $early);
            }
        }
        // Query handlers can execute from admin_init (document prints),
        // parse_request or template_redirect. Register all three before
        // functional handlers at priority 0 to avoid false runtime coverage.
        add_action('admin_init', static function (): void { sige_security_kernel_dispatch_view_actions('admin_init'); }, $early - 1);
        add_action('admin_init', static function (): void { sige_security_kernel_dispatch_query_handlers_on_hook('admin_init'); }, $early);
        add_action('parse_request', static function (): void { sige_security_kernel_dispatch_query_handlers_on_hook('parse_request'); }, $early);
        add_action('template_redirect', static function (): void { sige_security_kernel_dispatch_query_handlers_on_hook('template_redirect'); }, $early);
        if (function_exists('add_filter')) {
            add_filter('rest_pre_dispatch', 'sige_security_kernel_rest_pre_dispatch', $early, 3);
            add_filter('pre_do_shortcode_tag', 'sige_security_kernel_shortcode_pre', $early, 4);
        }
    }
}

if (!function_exists('sige_security_kernel_bootstrap')) {
    function sige_security_kernel_bootstrap(): void {
        static $booted = false;
        if ($booted) return;
        $booted = true;
        sige_security_kernel_register();
    }
}

sige_security_kernel_bootstrap();
