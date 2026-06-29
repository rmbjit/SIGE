<?php
// Acesso restrito: smoke corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SMOKE - Tenant Read Isolation & Resolver Hardening (v12.12.9)
 * Asserts estaticos sobre a remocao do fallback cego para escola 1, o helper de
 * escola unica activa, a resolucao fail-closed e a integridade dos baselines.
 * Nao requer bootstrap do WordPress.
 */
$root = dirname(__DIR__);
$fail = [];
$ok = 0;
$check = static function (string $label, bool $cond) use (&$fail, &$ok) {
    if ($cond) { $ok++; echo "OK   {$label}\n"; }
    else { $fail[] = $label; echo "FAIL {$label}\n"; }
};
$read = static function (string $rel) use ($root): string {
    $abs = $root . '/' . $rel;
    return is_file($abs) ? (string) file_get_contents($abs) : '';
};
$grepAllPhp = static function (string $regex) use ($root): int {
    $count = 0;
    foreach (['includes', 'admin'] as $dir) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
                $count += preg_match_all($regex, (string) file_get_contents($f->getPathname()));
            }
        }
    }
    return $count;
};

$mt = $read('includes/multitenancy.php');

// 1. Helper de escola unica activa existe.
$check('helper sige_multitenancy_single_active_school_id existe', strpos($mt, 'function sige_multitenancy_single_active_school_id') !== false);

// 2. Resolvedor nao devolve a constante cega.
$check('resolvedor sem return SIGE_ESCOLA_MALISA', preg_match('/return\s+SIGE_ESCOLA_MALISA\s*;/', $mt) === 0);

// 3. Resolvedor passo 5 usa o helper de escola unica.
$check('resolvedor usa sige_multitenancy_single_active_school_id', strpos($mt, 'sige_multitenancy_single_active_school_id()') !== false);

// 4. Constante marcada como obsoleta.
$check('SIGE_ESCOLA_MALISA marcada OBSOLETO', strpos($mt, 'OBSOLETO') !== false);

// 5. Zero ternarios cegos : 1 (com cast).
$check('zero ternario cego : 1 (cast)', $grepAllPhp("/function_exists\('sige_get_escola_id'\)\s*\?\s*\(int\)\s*sige_get_escola_id\(\)\s*:\s*1\b/") === 0);

// 6. Zero ternarios cegos : 1 (sem cast).
$check('zero ternario cego : 1 (sem cast)', $grepAllPhp("/function_exists\('sige_get_escola_id'\)\s*\?\s*sige_get_escola_id\(\)\s*:\s*1\b/") === 0);

// 7. Zero fallbacks fixos = 1.
$check('zero fallback fixo = 1', $grepAllPhp('/\$(escola_id|escola_id_contexto|eid)\s*=\s*1\s*;/') === 0);

// 8. Zero defaults de linha escola_id ?? 1.
$check('zero default de linha escola_id ?? 1', $grepAllPhp('/escola_id\s*\?\?\s*1\b/') === 0);

// 9. Baseline de fallbacks v12.12.9 = 0.
$b = json_decode($read('docs/security/TENANT_FALLBACK_BASELINE-v12.12.9.json'), true);
$check('baseline de fallbacks v12.12.9 = 0', is_array($b) && count($b['items'] ?? []) === 0);

// 10. Baselines congelados intactos (138/139).
$b81 = json_decode($read('docs/security/TENANT_FALLBACK_BASELINE-v12.12.8.1.json'), true);
$b80 = json_decode($read('docs/security/TENANT_FALLBACK_BASELINE-v12.12.8.json'), true);
$check('baseline congelado v12.12.8.1 = 138', is_array($b81) && count($b81['items'] ?? []) === 138);
$check('baseline congelado v12.12.8 = 139', is_array($b80) && count($b80['items'] ?? []) === 139);

// 11. Funcoes de calculo presentes e intactas (assinaturas canonicas).
$fc = $read('includes/finance-core.php');
$check('calc sige_fin_saldo_lancamento presente', strpos($fc, 'function sige_fin_saldo_lancamento') !== false);
$check('calc sige_fin_saldo_sql presente', strpos($fc, 'function sige_fin_saldo_sql') !== false);
$check('calc sige_fin_total_bruto_sql presente', strpos($fc, 'function sige_fin_total_bruto_sql') !== false);

// 12. Novo gate existe e esta ligado ao corredor.
$check('gate check-tenant-read-resolver existe', is_file($root . '/tools/check-tenant-read-resolver.php'));
$check('gate ligado ao corredor', strpos($read('tools/run-gates.php'), 'check-tenant-read-resolver.php') !== false);

echo "\n";
if ($fail) {
    fwrite(STDERR, "SMOKE TENANT READ ISOLATION FALHOU (" . count($fail) . "): " . implode('; ', $fail) . "\n");
    exit(1);
}
echo "SMOKE TENANT READ ISOLATION OK - {$ok} verificacoes.\n";
