<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/governance-lib.php';
require_once __DIR__ . '/security-kernel-gate-lib.php';
$v = sige_gov_version();
$rules = sige_sk_gate_load_rules();
$manifest = sige_sk_gate_manifest_items($v);
$fails = [];
$baseline_errors = sige_sk_gate_validate_rules($rules, $manifest, true);
if ($baseline_errors) { fwrite(STDERR, 'SECURITY KERNEL NEGATIVE TESTS baseline invalida: ' . implode('; ', array_slice($baseline_errors, 0, 20)) . "\n"); exit(1); }
$expect_fail = static function (array $mutated, string $label) use ($manifest, &$fails): void {
    $errors = sige_sk_gate_validate_rules($mutated, $manifest, true);
    if (!$errors) $fails[] = $label;
};
$mut = $rules;
unset($mut['wp_ajax:sige_settings_save']);
$expect_fail($mut, 'remocao de superficie enforce nao falhou');
$mut = $rules;
$mut['admin_post:sige_mpesa_guardar_config']['intent'] = ['type' => 'none'];
$expect_fail($mut, 'enforce sem nonce nao falhou');
$mut = $rules;
$mut['admin_post:sige_emola_guardar_config']['audit'] = false;
$expect_fail($mut, 'enforce sem auditoria nao falhou');
$mut = $rules;
$mut['query_handler:sige_desp_print']['rate_limit'] = [];
$expect_fail($mut, 'enforce sem rate limit nao falhou');
$mut = $rules;
$mut['wp_ajax:sige_settings_save']['mode'] = 'shadow';
$expect_fail($mut, 'mode invalido nao falhou');
$mut = $rules;
unset($mut['query_handler:sige_print']['runtime_hooks']);
$expect_fail($mut, 'query_handler sem runtime_hooks nao falhou');
$mut = $rules;
$mut['query_handler:sige_portaria_camera']['runtime_priority'] = 0;
$expect_fail($mut, 'query_handler sem prioridade antecipada nao falhou');
$mut = $rules;
$mut['wp_ajax:sige_settings_save']['tenant_required'] = false;
$expect_fail($mut, 'settings_save sem tenant_required nao falhou');
$mut = $rules;
unset($mut['wp_ajax:sige_settings_save']['tenant_scope']);
$expect_fail($mut, 'settings_save sem tenant_scope nao falhou');

if ($fails) { fwrite(STDERR, "SECURITY KERNEL NEGATIVE TESTS FALHOU\n - " . implode("\n - ", $fails) . "\n"); exit(1); }
echo 'SECURITY KERNEL NEGATIVE TESTS OK - gates falham quando regras obrigatorias sao removidas ou enfraquecidas.' . "\n";
