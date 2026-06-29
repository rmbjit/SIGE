<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/governance-lib.php';
$v = sige_gov_version();
$manifest = json_decode(sige_gov_read("docs/security/ACTION_SURFACE_MANIFEST-v{$v}.json"), true);
if (!is_array($manifest) || empty($manifest['items'])) { fwrite(STDERR, "Manifesto ausente.\n"); exit(1); }
$ids = [];
foreach ($manifest['items'] as $it) $ids[(string)$it['id']] = $it;
$required = [
    'query_handler:sige_desp_print' => 'print de despesas autenticado',
    'query_handler:sige_recibo' => 'recibo publico tokenizado',
    'query_handler:sige_portaria_camera' => 'leitor seguro da portaria',
    'query_handler:sige_print' => 'impressao universal autenticada',
    'query_handler:sige_billing_bypass' => 'bypass tecnico de licenca restrito',
    'wp_ajax:sige_settings_save' => 'endpoint AJAX dinamico de configuracoes',
];
$missing = [];
foreach ($required as $id => $reason) {
    if (!isset($ids[$id])) $missing[] = "$id ($reason)";
}
if ($missing) { fwrite(STDERR, 'Superficies obrigatorias ausentes: ' . implode('; ', $missing) . "\n"); exit(1); }
if (empty($ids['query_handler:sige_recibo']['public'])) {
    fwrite(STDERR, "sige_recibo deve permanecer classificado como publico tokenizado.\n"); exit(1);
}
if (!empty($ids['query_handler:sige_desp_print']['public'])) {
    fwrite(STDERR, "sige_desp_print nao deve ser publico; exige login, nonce e permissao.\n"); exit(1);
}
echo 'QUERY HANDLER SURFACE OK - query handlers criticos cobertos no manifesto.' . "\n";
