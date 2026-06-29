<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
/**
 * Gate Fase 4 Incr 1 - self-host das bibliotecas + infra de enqueue.
 * Verifica (estatico): registador presente e completo, catalogo local, zero
 * literais de CDN, ficheiros vendor presentes, SRI completo, carregamento no
 * bootstrap. Nao altera nada; so confirma a postura.
 */
$root = getenv('SIGE_ROOT') ?: dirname(__DIR__);
$fails = [];
$read = static function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return is_file($p) ? (string) file_get_contents($p) : '';
};

// 1. Registador presente e completo.
$reg = $read('includes/assets-registry.php');
if ($reg === '') {
    $fails[] = 'includes/assets-registry.php em falta';
} else {
    foreach (['sige_assets_libs', 'sige_assets_base_url', 'sige_assets_registar', 'sige_enqueue_lib', 'sige_assets_sri_para_handle', 'sige_assets_tag_sri'] as $fn) {
        if (strpos($reg, "function $fn(") === false) $fails[] = "registador sem a funcao $fn";
    }
    if (strpos($reg, "add_action('admin_enqueue_scripts', 'sige_assets_registar'") === false) {
        $fails[] = 'registador nao regista no admin_enqueue_scripts';
    }
    if (strpos($reg, "add_filter('script_loader_tag', 'sige_assets_tag_sri'") === false) {
        $fails[] = 'registador nao injecta SRI via script_loader_tag';
    }
    if (strpos($reg, "if (!defined('ABSPATH')) exit;") === false) {
        $fails[] = 'registador sem guarda ABSPATH';
    }
}

// 2. Catalogo aponta local e sem literais de CDN.
$cat = $read('includes/cdn-scripts.php');
if (strpos($cat, "assets/vendor/") === false) $fails[] = 'catalogo nao aponta para assets/vendor/';
if (preg_match('#https?://(cdnjs\.cloudflare\.com|unpkg\.com)#i', $cat)) {
    $fails[] = 'catalogo ainda tem literal de CDN (cdnjs/unpkg)';
}

// 3. Zero literais de CDN em todo o codigo de runtime.
$cdn_hits = [];
$scan = static function (string $dir) use ($root, &$cdn_hits) {
    if (!is_dir($root . '/' . $dir)) return;
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $f) {
        $path = str_replace('\\', '/', $f->getPathname());
        if (strpos($path, '/assets/vendor/') !== false) continue; // vendorizado e first-party
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['php', 'js', 'css'], true)) continue;
        $src = (string) file_get_contents($path);
        if (preg_match('#https?://(cdnjs\.cloudflare\.com|unpkg\.com)#i', $src)) {
            $cdn_hits[] = substr($path, strlen($root) + 1);
        }
    }
};
$scan('admin');
$scan('includes');
if (preg_match('#https?://(cdnjs\.cloudflare\.com|unpkg\.com)#i', $read('sige-softgenial.php'))) {
    $cdn_hits[] = 'sige-softgenial.php';
}
if ($cdn_hits) $fails[] = 'literais de CDN remanescentes em: ' . implode(', ', $cdn_hits);

// 4. Ficheiros vendor presentes e com tamanho.
$libs = [
    'chartjs/chart.umd.js', 'xlsx/xlsx.full.min.js', 'exceljs/exceljs.min.js',
    'sortable/Sortable.min.js', 'qrious/qrious.min.js', 'filesaver/FileSaver.min.js',
    'html5qrcode/html5-qrcode.min.js',
];
foreach ($libs as $rel) {
    $p = $root . '/assets/vendor/' . $rel;
    if (!is_file($p)) { $fails[] = "vendor em falta: $rel"; continue; }
    if (filesize($p) < 1000) $fails[] = "vendor suspeito (tamanho): $rel";
}

// 5. SRI completo.
$sri = $read('includes/sri-hashes.php');
foreach (['chartjs-4.4.1', 'xlsx-0.18.5', 'exceljs-4.3.0', 'sortable-1.15.2', 'qrious-4.0.2', 'filesaver-2.0.5', 'html5qrcode-2.3.8'] as $k) {
    if (strpos($sri, "'$k'") === false) $fails[] = "SRI em falta para $k";
}

// 6. Registador carregado no bootstrap.
$boot = $read('sige-softgenial.php');
if (strpos($boot, "includes/assets-registry.php") === false) {
    $fails[] = 'bootstrap nao carrega o registador de assets';
}

if ($fails) {
    echo "CHECK FASE4 ASSETS FALHOU:\n";
    foreach ($fails as $f) echo "  - $f\n";
    exit(1);
}
echo "CHECK FASE4 ASSETS OK - registador completo, catalogo local, zero CDN, vendor presente, SRI completo, carregado no bootstrap.\n";
