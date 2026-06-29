<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

function sige_sk_gate_root(): string { return dirname(__DIR__); }
function sige_sk_gate_read(string $rel): string {
    $path = sige_sk_gate_root() . '/' . ltrim($rel, '/');
    return is_file($path) ? (string)file_get_contents($path) : '';
}
function sige_sk_gate_manifest_items(string $version): array {
    $json = json_decode(sige_sk_gate_read("docs/security/ACTION_SURFACE_MANIFEST-v{$version}.json"), true);
    return is_array($json) && isset($json['items']) && is_array($json['items']) ? $json['items'] : [];
}
function sige_sk_gate_load_rules(): array {
    if (!defined('SIGE_SECURITY_KERNEL_TEST_MODE')) define('SIGE_SECURITY_KERNEL_TEST_MODE', true);
    $rules_file = sige_sk_gate_root() . '/includes/security-kernel-rules.php';
    if (!is_file($rules_file)) return [];
    require_once $rules_file;
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
function sige_sk_gate_validate_rules(array $rules, array $manifest_items, bool $strict = true): array {
    $fails = [];
    $manifest_ids = [];
    foreach ($manifest_items as $it) {
        if (is_array($it) && isset($it['id'])) $manifest_ids[] = (string)$it['id'];
    }
    sort($manifest_ids);
    $rule_ids = array_keys($rules);
    sort($rule_ids);
    $missing = array_values(array_diff($manifest_ids, $rule_ids));
    $stale = array_values(array_diff($rule_ids, $manifest_ids));
    if ($missing) $fails[] = 'rules_missing_manifest_surfaces=' . implode(',', array_slice($missing, 0, 20));
    if ($stale) $fails[] = 'rules_with_stale_surfaces=' . implode(',', array_slice($stale, 0, 20));

    $pilots = [
        'admin_post:sige_mpesa_guardar_config',
        'admin_post:sige_emola_guardar_config',
        'query_handler:sige_desp_print',
        'wp_ajax:sige_settings_save',
    ];
    $valid_modes = ['enforce', 'observe', 'delegated', 'retired'];
    foreach ($rules as $id => $rule) {
        foreach (['id','type','name','mode','risk','module','public','tenant_required','intent','permissions','audit','registrations'] as $field) {
            if (!array_key_exists($field, $rule)) $fails[] = $id . ':missing_' . $field;
        }
        if (!array_key_exists('authorization_mode', $rule) && !array_key_exists('permission_mode', $rule)) $fails[] = $id . ':missing_authorization_mode';
        if (!array_key_exists('status', $rule) && !array_key_exists('source_manifest_status', $rule)) $fails[] = $id . ':missing_status';
        if (!in_array((string)($rule['mode'] ?? ''), $valid_modes, true)) $fails[] = $id . ':invalid_mode';
        if (($rule['type'] ?? '') === 'rest_route') {
            if (empty($rule['route']) || strpos((string)$rule['route'], '/') !== 0) $fails[] = $id . ':rest_route_missing_route';
            if (($rule['runtime_match'] ?? '') !== 'namespace_route') $fails[] = $id . ':rest_route_missing_runtime_match';
        }
        if (($rule['type'] ?? '') === 'query_handler') {
            $hooks = isset($rule['runtime_hooks']) && is_array($rule['runtime_hooks']) ? $rule['runtime_hooks'] : [];
            foreach (['admin_init','parse_request','template_redirect'] as $qh) {
                if (!in_array($qh, $hooks, true)) $fails[] = $id . ':query_handler_missing_' . $qh;
            }
            if ((int)($rule['runtime_priority'] ?? 0) > -1) $fails[] = $id . ':query_handler_not_early';
        }
        if (($rule['type'] ?? '') === 'wp_hook' && (int)($rule['runtime_priority'] ?? 0) > -1) {
            $fails[] = $id . ':wp_hook_not_early';
        }
        if (in_array($id, ['admin_post:sige_mpesa_guardar_config','admin_post:sige_emola_guardar_config'], true) && (($rule['tenant_storage'] ?? '') !== 'tenant_scoped_wp_option')) {
            $fails[] = $id . ':mobile_payment_not_tenant_scoped';
        }
        if (($rule['type'] ?? '') === 'view_action') {
            foreach (['page','view','method','discriminator'] as $vf) if (!array_key_exists($vf, $rule)) $fails[] = $id . ':view_action_missing_' . $vf;
        }
        if (($rule['mode'] ?? '') === 'enforce') {
            $intent = isset($rule['intent']) && is_array($rule['intent']) ? $rule['intent'] : [];
            $intent_type = (string)($intent['type'] ?? '');
            if (!in_array($intent_type, ['nonce','token','hmac','hmac_or_token'], true)) $fails[] = $id . ':enforce_without_nonce_or_token';
            if ($intent_type === 'nonce' && (empty($intent['field']) || empty($intent['action']))) $fails[] = $id . ':enforce_nonce_incomplete';
            if (in_array($intent_type, ['token','hmac','hmac_or_token'], true) && empty($intent['validator']) && empty($intent['expected_option'])) $fails[] = $id . ':enforce_token_without_validator';
            if (empty($rule['rate_limit']) || !is_array($rule['rate_limit'])) $fails[] = $id . ':enforce_without_rate_limit';
            if (empty($rule['audit'])) $fails[] = $id . ':enforce_without_audit';
            $auth_mode = (string)($rule['authorization_mode'] ?? ($rule['permission_mode'] ?? 'permissions'));
            if (!in_array($auth_mode, ['delegated','public_token','public_hmac'], true) && empty($rule['permissions'])) $fails[] = $id . ':enforce_without_permissions';
        }
        if (($rule['risk'] ?? '') === 'critical' && ($rule['mode'] ?? '') === 'observe') $fails[] = $id . ':critical_still_observe';
    }
    foreach ($pilots as $pilot) {
        if (!isset($rules[$pilot])) $fails[] = 'pilot_missing=' . $pilot;
        elseif (($rules[$pilot]['mode'] ?? '') !== 'enforce') $fails[] = 'pilot_not_enforced=' . $pilot;
    }
    if (isset($rules['wp_ajax:sige_settings_save'])) {
        if (empty($rules['wp_ajax:sige_settings_save']['tenant_required'])) $fails[] = 'settings_save_not_tenant_required';
        if (($rules['wp_ajax:sige_settings_save']['tenant_scope'] ?? '') !== 'required_for_sige_config_writes') $fails[] = 'settings_save_missing_tenant_scope';
    }
    $enforce_count = 0;
    foreach ($rules as $rule) if (($rule['mode'] ?? '') === 'enforce') $enforce_count++;
    if ($strict && $enforce_count < 4) $fails[] = 'unexpected_enforce_count=' . $enforce_count;
    return $fails;
}
