<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/governance-lib.php';
$v = sige_gov_version();
$manifest = json_decode(sige_gov_read("docs/security/ACTION_SURFACE_MANIFEST-v{$v}.json"), true);
if (!is_array($manifest) || empty($manifest['items']) || !is_array($manifest['items'])) {
    fwrite(STDERR, "Manifesto ausente ou invalido.\n"); exit(1);
}
$current = sige_gov_extract_action_surface();
$independent = sige_gov_extract_action_surface_independent();
$currentIds = array_map(static function ($it) { return $it['id']; }, $current);
$independentIds = array_map(static function ($it) { return $it['id']; }, $independent);
$manifestIds = array_map(static function ($it) { return $it['id']; }, $manifest['items']);
$expectedIds = array_values(array_unique(array_merge($currentIds, $independentIds)));
sort($expectedIds); sort($manifestIds);
$missing = array_values(array_diff($expectedIds, $manifestIds));
$stale = array_values(array_diff($manifestIds, $expectedIds));
$bad = [];
foreach ($manifest['items'] as $it) {
    foreach (['id','type','name','module','risk','tenant_required','csrf_or_hmac_required','audit_required','rate_limit_expected','permission_expected','status','registrations'] as $field) {
        if (!array_key_exists($field, $it)) $bad[] = ($it['id'] ?? 'item') . " sem $field";
    }
}
$must = [
    'wp_ajax:sige_settings_save',
    'query_handler:sige_desp_print',
    'query_handler:sige_recibo',
    'query_handler:sige_portaria_camera',
    'query_handler:sige_print',
    'wp_hook:template_redirect',
];
foreach ($must as $id) {
    if (!in_array($id, $manifestIds, true)) $bad[] = "manifesto sem superficie obrigatoria {$id}";
}
if ($missing || $stale || $bad) {
    if ($missing) fwrite(STDERR, 'Itens de codigo fora do manifesto: ' . implode(', ', $missing) . "\n");
    if ($stale) fwrite(STDERR, 'Itens obsoletos no manifesto: ' . implode(', ', $stale) . "\n");
    if ($bad) fwrite(STDERR, 'Itens incompletos: ' . implode('; ', array_slice($bad, 0, 30)) . "\n");
    exit(1);
}
echo 'ACTION SURFACE MANIFEST OK - ' . count($manifestIds) . ' superficies declaradas, extractor primario e independente alinhados.' . "\n";
