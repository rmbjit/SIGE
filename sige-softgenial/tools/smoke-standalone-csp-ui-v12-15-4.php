<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
/**
 * Smoke v12.15.4 - Standalone UI/CSP Recovery
 * Garante que páginas autónomas (Portaria/QR, documentos e popups de impressão)
 * não voltam a aparecer como HTML cru sob CSP enforcement.
 */
$root = dirname(__DIR__);
$fail = [];
$read = static function(string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return is_file($p) ? (string) file_get_contents($p) : '';
};
$has = static function(string $src, string $needle): bool { return strpos($src, $needle) !== false; };
$check = static function(bool $ok, string $msg) use (&$fail): void { if (!$ok) $fail[] = $msg; };

$core = $read('includes/core-helpers.php');
$csp  = $read('includes/csp-zero-inline.php');
$ui   = $read('assets/sige-ui.js');
$docs = $read('includes/documents-engine.php');
$port = $read('includes/portaria-camera-safe-page.php');
$jard = $read('admin/jardim/jardim_boletim-view.php');
$bol  = $read('admin/boletim-pdf-template.php');
$paut = $read('admin/pauta-pdf-template.php');

$check($has($core, 'function sige_csp_style_attr'), 'falta helper sige_csp_style_attr para <style nonce>');
$check($has($csp, 'sige_csp_zero_inline_is_standalone_request'), 'falta detector de páginas autónomas CSP');
$check($has($csp, "'sige_portaria_camera'"), 'Portaria/QR não coberta pelo guard autónomo');
$check($has($csp, "'sige_print'"), 'documentos sige_print não cobertos pelo guard autónomo');
$check($has($csp, "'sige_dev_print'"), 'mapa/lista de cobrança não coberto pelo guard autónomo');
$check($has($csp, "'sige_desp_print'"), 'documentos de despesas não cobertos pelo guard autónomo');
$check($has($csp, "add_action('template_redirect', 'sige_csp_zero_inline_boot', -100)"), 'guard CSP não inicia antes de páginas front-end autónomas');
$check($has($csp, 'sige_csp_zero_inline_buffer_active'), 'guard não consegue rearmar buffer após ob_end_clean');
$check($has($csp, 'sige_csp_zero_inline_inject_standalone_hydrator'), 'falta injecção do hidratador externo em páginas autónomas');
$check($has($csp, 'assets/sige-ui.js'), 'hidratador sige-ui.js não é injectado em páginas autónomas quando necessário');

$check($has($ui, 'CSP Popup Guard v12.15.4'), 'falta popup guard CSP no JS externo');
$check($has($ui, 'window.open = function'), 'window.open não é protegido para popups de impressão');
$check($has($ui, 'doc.write = function'), 'document.write dos popups não recebe nonce CSP');
$check($has($ui, 'cspNonceHtml'), 'popup guard não normaliza <style>/<script> com nonce');
$check(strpos($ui, 'eval(') === false && strpos($ui, 'new Function') === false && strpos($ui, 'Function(') === false, 'popup/hidratador usa API proibida eval/Function');

foreach ([
    'includes/documents-engine.php' => $docs,
    'includes/portaria-camera-safe-page.php' => $port,
    'admin/boletim-pdf-template.php' => $bol,
    'admin/pauta-pdf-template.php' => $paut,
    'admin/jardim/jardim_boletim-view.php' => $jard,
] as $rel => $src) {
    $check($has($src, 'sige_csp_style_attr'), "$rel sem nonce helper em <style>");
}
$check($has($docs, '$sige_doc_is_binary') && $has($docs, '!$sige_doc_is_binary') && $has($docs, 'sige_csp_zero_inline_boot'), 'documents-engine não rearma CSP apenas para HTML');
$check(!$has($port, 'style="position:absolute;left:-9999px'), 'Portaria ainda tem style= crítico no file-reader');
$check($has($port, 'qr-file-reader') && $has($port, 'manual-card'), 'Portaria não substituiu style= por classes');
$check($has($jard, 'sigePrintStyleOpen()'), 'Boletim Jardim não coloca nonce em styles de popup');

foreach (['includes/core-helpers.php'=>$core,'includes/csp-zero-inline.php'=>$csp,'assets/sige-ui.js'=>$ui] as $rel=>$src) {
    $check(strpos($src, 'unsafe-inline') === false, "$rel contém unsafe-inline literal");
}

// Varre páginas que enviam CSP e alerta para <style> sem nonce/helper. Exclui XML <styleSheet> e regex interno do guard.
foreach (['includes','admin'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION)) !== 'php') continue;
        $rel = str_replace($root . '/', '', $f->getPathname());
        $src = (string) file_get_contents($f->getPathname());
        if (strpos($src, 'Content-Security-Policy') === false) continue;
        if (!preg_match_all('/<style\b[^>]*>/i', $src, $m)) continue;
        foreach ($m[0] as $tag) {
            if (stripos($tag, '<styleSheet') === 0) continue;
            if (strpos($tag, 'sige_csp_style_attr') !== false || stripos($tag, 'nonce=') !== false) continue;
            if ($rel === 'includes/csp-zero-inline.php') continue;
            if ($rel === 'admin/jardim/jardim_boletim-view.php' && strpos($src, 'sigePrintStyleOpen()') !== false && $tag === '<style>') continue;
            $fail[] = "$rel tem <style> sem nonce/helper sob CSP: " . substr($tag, 0, 80);
        }
    }
}

if ($fail) {
    echo "SMOKE STANDALONE CSP/UI v12.15.4 FALHOU:\n";
    foreach ($fail as $f) echo " - {$f}\n";
    exit(1);
}
echo "SMOKE STANDALONE CSP/UI v12.15.4 OK - Portaria/QR, documentos autónomos e popups de impressão protegidos contra HTML cru sob CSP.\n";
