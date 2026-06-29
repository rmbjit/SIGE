<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/governance-lib.php';
$v = sige_gov_version();
$manifest = json_decode(sige_gov_read("docs/security/ACTION_SURFACE_MANIFEST-v{$v}.json"), true);
$policy = sige_gov_read("docs/security/PUBLIC_ENDPOINTS_POLICY-v{$v}.md");
if (!is_array($manifest) || $policy === '') { fwrite(STDERR, "Manifesto ou politica ausente.\n"); exit(1); }
$missing = [];
foreach ($manifest['items'] as $it) {
    if (!empty($it['public']) && strpos($policy, (string)$it['id']) === false) $missing[] = (string)$it['id'];
}
if ($missing) {
    fwrite(STDERR, 'Endpoints publicos sem politica: ' . implode(', ', $missing) . "\n");
    exit(1);
}
echo 'PUBLIC ENDPOINTS POLICY OK - endpoints publicos cobertos.' . "\n";
