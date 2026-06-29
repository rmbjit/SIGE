<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
if (!defined('SIGE_SECURITY_KERNEL_TEST_MODE')) define('SIGE_SECURITY_KERNEL_TEST_MODE', true);
require_once $root . '/includes/security-kernel-rules.php';
$fails = [];
$oks = 0;
$check = static function (bool $cond, string $label) use (&$fails, &$oks): void {
    if ($cond) { $oks++; echo "OK   {$label}\n"; }
    else { $fails[] = $label; echo "FAIL {$label}\n"; }
};
$rules_raw = function_exists('sige_security_kernel_rules') ? sige_security_kernel_rules() : [];
$rules = [];
foreach ((array)$rules_raw as $key => $rule) {
    if (!is_array($rule)) continue;
    $id = isset($rule['id']) ? (string)$rule['id'] : (is_string($key) ? $key : '');
    if ($id === '') continue;
    $rules[$id] = $rule;
}
$kernel = is_file($root . '/includes/security-kernel.php') ? (string)file_get_contents($root . '/includes/security-kernel.php') : '';
$check(count($rules) >= 174, 'Contrato do kernel cobre superficies inventariadas');
$enforce = array_filter($rules, static fn($r) => ($r['mode'] ?? '') === 'enforce');
$observe = array_filter($rules, static fn($r) => ($r['mode'] ?? '') === 'observe');
$criticalObserve = array_filter($rules, static fn($r) => ($r['risk'] ?? '') === 'critical' && ($r['mode'] ?? '') === 'observe');
$check(count($enforce) >= 4, 'enforcement mínimo do kernel activo');
$check(count($criticalObserve) === 0, 'zero acções críticas em observe');
$pilotExpect = [
    'admin_post:sige_mpesa_guardar_config' => ['action' => 'sige_mpesa_config', 'perm' => 'financeiro.mobile_payments_gerir', 'tenant' => true],
    'admin_post:sige_emola_guardar_config' => ['action' => 'sige_emola_config', 'perm' => 'financeiro.mobile_payments_gerir', 'tenant' => true],
    'query_handler:sige_desp_print' => ['action' => 'sige_desp_print', 'perm' => 'financeiro.despesas_ver', 'tenant' => true],
    'wp_ajax:sige_settings_save' => ['action' => 'sige_settings_save_v1', 'perm' => 'settings.policy.can_edit', 'tenant' => true],
];
foreach ($pilotExpect as $id => $expected) {
    $rule = $rules[$id] ?? null;
    $check(is_array($rule), "{$id} existe");
    if (!is_array($rule)) continue;
    $check(($rule['mode'] ?? '') === 'enforce', "{$id} em enforce");
    $check(($rule['intent']['type'] ?? '') === 'nonce', "{$id} protegido por nonce");
    $check(($rule['intent']['action'] ?? '') === $expected['action'], "{$id} nonce action correcto");
    $check(in_array($expected['perm'], (array)($rule['permissions'] ?? []), true), "{$id} com permissao esperada");
    $check((bool)($rule['tenant_required'] ?? false) === (bool)$expected['tenant'], "{$id} tenant_required correcto");
    $check(!empty($rule['rate_limit']) && is_array($rule['rate_limit']), "{$id} com rate limit");
    $check(!empty($rule['audit']), "{$id} com auditoria");
}
$settings = $rules['wp_ajax:sige_settings_save'] ?? [];
$check((string)($settings['authorization_mode'] ?? '') === 'delegated', 'settings save preserva autorizacao fina delegada');
$check((string)($settings['delegated_policy'] ?? $settings['delegated_to'] ?? '') === 'SIGE_Settings_Policy::can_edit', 'settings save aponta para SIGE_Settings_Policy::can_edit');
foreach (['security_kernel.allowed', 'security_kernel.denied', 'auth_required', 'invalid_intent', 'permission_denied', 'tenant_missing', 'rate_limited'] as $needle) {
    $check(strpos($kernel, $needle) !== false, "kernel contem marcador {$needle}");
}
$check(strpos($kernel, 'sige_security_kernel_safe_context') !== false, 'kernel redige contexto de auditoria');
$check(strpos($kernel, 'SIGE_SECURITY_KERNEL_EARLY_PRIORITY') !== false, 'kernel tem prioridade antecipada formal');
foreach (['admin_init','parse_request','template_redirect'] as $qh) {
    $check(strpos($kernel, "add_action('{$qh}'") !== false, "query handlers registados em {$qh}");
}
$check(strpos($kernel, 'sige_security_kernel_dispatch_query_handlers_on_hook') !== false, 'query handlers usam dispatcher multi-hook antecipado');
if ($fails) {
    fwrite(STDERR, 'SMOKE SECURITY KERNEL FALHOU: ' . implode('; ', $fails) . "\n");
    exit(1);
}
echo "SMOKE SECURITY KERNEL OK - {$oks} verificacoes passaram.\n";
