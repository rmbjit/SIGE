<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/governance-lib.php';
$v = sige_gov_version();
$baseline = json_decode(sige_gov_read("docs/security/AUTHORIZATION_DEBT_BASELINE-v{$v}.json"), true);
if (!is_array($baseline)) { fwrite(STDERR, "Baseline de autorizacao ausente.\n"); exit(1); }
$current = sige_gov_auth_baseline();
if ($current['current_user_can_total'] > (int)$baseline['current_user_can_total']) {
    fwrite(STDERR, 'current_user_can aumentou: ' . $current['current_user_can_total'] . ' > ' . $baseline['current_user_can_total'] . "\n");
    exit(1);
}
if ($current['sige_can_total'] < (int)$baseline['sige_can_total']) {
    fwrite(STDERR, 'sige_can baixou inesperadamente: ' . $current['sige_can_total'] . ' < ' . $baseline['sige_can_total'] . "\n");
    exit(1);
}
echo 'AUTHORIZATION BASELINE OK - divida congelada sem aumento de current_user_can.' . "\n";
