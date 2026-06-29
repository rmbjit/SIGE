<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/governance-lib.php';
$v = sige_gov_version();
$baseline = json_decode(sige_gov_read("docs/security/SECRETS_OPTIONS_BASELINE-v{$v}.json"), true);
if (!is_array($baseline) || !isset($baseline['items'])) { fwrite(STDERR, "Registo de opcoes ausente.\n"); exit(1); }
$registered = [];
foreach ($baseline['items'] as $it) { $registered[$it['name']] = true; }
$missing = [];
foreach (sige_gov_option_names() as $it) {
    if (!empty($it['sensitive_candidate']) && !isset($registered[$it['name']])) $missing[] = $it['name'];
}
if ($missing) { fwrite(STDERR, 'Opcoes sensiveis nao registadas: ' . implode(', ', $missing) . "\n"); exit(1); }
echo 'SECRETS/OPTIONS REGISTER OK - candidatos sensiveis registados.' . "\n";
