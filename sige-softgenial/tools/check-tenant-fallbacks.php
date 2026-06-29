<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/governance-lib.php';
$v = sige_gov_version();
$baseline = json_decode(sige_gov_read("docs/security/TENANT_FALLBACK_BASELINE-v{$v}.json"), true);
if (!is_array($baseline) || !isset($baseline['items'])) { fwrite(STDERR, "Baseline tenant ausente.\n"); exit(1); }
$base = [];
foreach ($baseline['items'] as $it) { $base[$it['file'] . ':' . $it['line'] . ':' . $it['code']] = true; }
$new = [];
foreach (sige_gov_tenant_fallbacks() as $it) {
    $key = $it['file'] . ':' . $it['line'] . ':' . $it['code'];
    if (!isset($base[$key])) $new[] = $key;
}
if ($new) { fwrite(STDERR, 'Novos fallbacks tenant nao registados: ' . implode('; ', array_slice($new, 0, 20)) . "\n"); exit(1); }

// Ratchet monotonico: face a versao anterior registada, o numero de fallbacks
// so pode descer ou manter, nunca subir. O isolamento de tenant so pode melhorar.
$dir = dirname(__DIR__) . '/docs/security';
$prevVer = null; $prevCount = null;
foreach (glob($dir . '/TENANT_FALLBACK_BASELINE-v*.json') as $file) {
    if (!preg_match('/TENANT_FALLBACK_BASELINE-v(.+)\.json$/', $file, $m)) continue;
    $ver = $m[1];
    if (version_compare($ver, $v, '>=')) continue; // apenas versoes anteriores a actual
    if ($prevVer === null || version_compare($ver, $prevVer, '>')) {
        $d = json_decode((string) file_get_contents($file), true);
        if (is_array($d) && isset($d['items'])) { $prevVer = $ver; $prevCount = count($d['items']); }
    }
}
$curCount = count($baseline['items']);
if ($prevVer !== null && $prevCount !== null && $curCount > $prevCount) {
    fwrite(STDERR, "Baseline de fallbacks subiu: v{$v}={$curCount} > v{$prevVer}={$prevCount}. O isolamento de tenant so pode melhorar.\n");
    exit(1);
}
$ratchet = ($prevVer !== null) ? " Ratchet: v{$v}={$curCount} <= v{$prevVer}={$prevCount}." : '';
echo 'TENANT FALLBACKS OK - nenhum fallback novo fora do registo.' . $ratchet . "\n";
