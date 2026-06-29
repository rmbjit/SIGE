<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/governance-lib.php';
$v = sige_gov_version();
$baseline = json_decode(sige_gov_read("docs/security/EXTERNAL_DEPENDENCIES_BASELINE-v{$v}.json"), true);
if (!is_array($baseline) || !isset($baseline['items'])) { fwrite(STDERR, "Registo de dependencias ausente.\n"); exit(1); }
$registered = [];
foreach ($baseline['items'] as $it) { $registered[$it['host']] = true; }
$missing = [];
foreach (sige_gov_external_hosts() as $it) {
    if (!isset($registered[$it['host']])) $missing[] = $it['host'];
}
if ($missing) { fwrite(STDERR, 'Hosts externos nao registados: ' . implode(', ', $missing) . "\n"); exit(1); }
echo 'EXTERNAL DEPENDENCIES REGISTER OK - hosts classificados.' . "\n";
