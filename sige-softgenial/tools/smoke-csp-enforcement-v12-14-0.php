<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
/**
 * Smoke v12.14.x - CSP Enforcement / Zero Inline.
 */
$root = getenv('SIGE_ROOT') ?: dirname(__DIR__);
$read = static function (string $rel) use ($root): string {
    $path = $root . '/' . $rel;
    return is_file($path) ? (string) file_get_contents($path) : '';
};
$fails = [];
$has = static function (string $src, string $needle, string $label) use (&$fails): void { if (strpos($src, $needle) === false) $fails[] = $label; };
$not = static function (string $src, string $needle, string $label) use (&$fails): void { if (strpos($src, $needle) !== false) $fails[] = $label; };

$guard = $read('includes/csp-zero-inline.php');
$has($guard, 'function sige_csp_zero_inline_policy', 'sem política CSP zero-inline');
$has($guard, "script-src-attr 'none'", 'sem script-src-attr none');
$has($guard, "style-src-attr 'none'", 'sem style-src-attr none');
$has($guard, "style-src 'self'", 'sem style-src self');
$has($guard, "script-src 'self'", 'sem script-src self');
$has($guard, 'sige_csp_zero_inline_sanitise_html', 'sem sanitizador zero-inline');
$has($guard, "header('Content-Security-Policy: ' . sige_csp_zero_inline_policy()", 'não envia CSP enforcement zero-inline');
$not($guard, 'unsafe-inline', 'guard contém unsafe-inline');

$ui = $read('assets/sige-ui.js');
foreach (['sigeAbrirJanela','sigeExecutarJsonData','sigeConfirmacaoCaixaSubmit','sigeRemoverPai','CSP Zero-Inline Hydrator','MutationObserver','resolverAcao'] as $fn) {
    $has($ui, $fn, "assets/sige-ui.js sem $fn");
}
foreach (['eval(', 'new Function', 'Function('] as $bad) $not($ui, $bad, "JS usa $bad");

foreach (['includes/login-page.php','includes/portal-logic.php'] as $rel) {
    $src = $read($rel);
    $not($src, 'fonts.googleapis', "$rel ainda referencia fonts.googleapis");
    $not($src, 'fonts.gstatic', "$rel ainda referencia fonts.gstatic");
}

foreach (['admin','includes','assets'] as $dir) {
    $base = $root . '/' . $dir;
    if (!is_dir($base)) continue;
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $f) {
        if (!$f->isFile()) continue;
        $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['php','js','css'], true)) continue;
        $src = (string) file_get_contents($f->getPathname());
        if (strpos($src, 'unsafe-inline') !== false) {
            $fails[] = 'unsafe-inline em produção: ' . str_replace($root . '/', '', $f->getPathname());
        }
    }
}

if ($fails) {
    echo "SMOKE CSP ZERO-INLINE FALHOU:\n";
    foreach ($fails as $f) echo "  - $f\n";
    exit(1);
}
echo "SMOKE CSP ZERO-INLINE OK - sem unsafe-inline, attrs inline bloqueados e compatibilidade hidratada por JS externo.\n";
