<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
/**
 * Smoke Fase 4 Incr 1 - self-host + infra de enqueue (runtime, com stubs).
 * Confirma que o catalogo produz URLs locais, que sige_cdn_script emite src local
 * com integrity, que o registador regista os sete handles locais, que o SRI e
 * injectado nas tags dos nossos handles e nao nos outros, e que os ficheiros vendor
 * existem.
 */
$root = getenv('SIGE_ROOT') ?: dirname(__DIR__);
if (!defined('ABSPATH')) define('ABSPATH', '/tmp/');
if (!defined('SIGE_URL')) define('SIGE_URL', 'https://escola.example/wp-content/plugins/sige-softgenial/');
if (!defined('SIGE_PATH')) define('SIGE_PATH', $root . '/');
if (!function_exists('esc_url')) { function esc_url($u) { return $u; } }
if (!function_exists('esc_attr')) { function esc_attr($s) { return $s; } }
if (!function_exists('add_action')) { function add_action(...$a) {} }
if (!function_exists('add_filter')) { function add_filter(...$a) {} }
$GLOBALS['__reg'] = [];
$GLOBALS['__enq'] = [];
if (!function_exists('wp_register_script')) { function wp_register_script($h, $src, $d, $v, $f) { $GLOBALS['__reg'][$h] = $src; } }
if (!function_exists('wp_enqueue_script')) { function wp_enqueue_script($h) { $GLOBALS['__enq'][] = $h; } }

require $root . '/includes/sri-hashes.php';
require $root . '/includes/cdn-scripts.php';
require $root . '/includes/assets-registry.php';

$pass = 0; $fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail) {
    if ($ok) { $pass++; }
    else { $fail++; echo "  FALHOU: $label\n"; }
};

// Catalogo local.
$cat = sige_cdn_catalog();
$check(strpos($cat['chartjs'], 'assets/vendor/chartjs/chart.umd.js') !== false, 'catalogo chartjs aponta local');
$check(strpos($cat['html5qrcode'], 'assets/vendor/html5qrcode/') !== false, 'catalogo html5qrcode aponta local');
$check(strpos(implode('', $cat), 'cdnjs') === false && strpos(implode('', $cat), 'unpkg') === false, 'catalogo sem CDN');

// sige_cdn_script emite src local com integrity.
$tag = sige_cdn_script('chartjs');
$check(strpos($tag, 'assets/vendor/chartjs/chart.umd.js') !== false, 'tag chartjs src local');
$check(strpos($tag, 'integrity="sha384-') !== false, 'tag chartjs com integrity SRI');

// Registador regista os sete handles locais.
sige_assets_registar();
$esperados = ['sige-chartjs', 'sige-xlsx', 'sige-exceljs', 'sige-sortable', 'sige-qrious', 'sige-filesaver', 'sige-html5qrcode'];
$check(count($GLOBALS['__reg']) === 7, 'sete handles registados');
foreach ($esperados as $h) {
    $check(isset($GLOBALS['__reg'][$h]) && strpos($GLOBALS['__reg'][$h], 'assets/vendor/') !== false, "handle $h registado local");
}

// Enqueue helper.
sige_enqueue_lib('sige-xlsx');
$check(in_array('sige-xlsx', $GLOBALS['__enq'], true), 'sige_enqueue_lib enfileira o handle');

// SRI por handle.
$check(strpos(sige_assets_sri_para_handle('sige-exceljs'), 'sha384-') === 0, 'SRI obtido para sige-exceljs');
$check(sige_assets_sri_para_handle('handle-inexistente') === '', 'SRI vazio para handle desconhecido');

// Injeccao de SRI na tag.
$t = sige_assets_tag_sri('<script src="/x/Sortable.min.js" id="sige-sortable-js"></script>', 'sige-sortable');
$check(strpos($t, 'integrity="sha384-') !== false && strpos($t, 'crossorigin="anonymous"') !== false, 'SRI injectado na tag sige-*');
$t2 = sige_assets_tag_sri('<script src="/x/other.js"></script>', 'jquery-core');
$check($t2 === '<script src="/x/other.js"></script>', 'tag nao-sige fica inalterada');
$t3 = sige_assets_tag_sri('<script integrity="sha384-ja" src="/x/Sortable.min.js"></script>', 'sige-sortable');
$check(substr_count($t3, 'integrity=') === 1, 'nao duplica integrity se ja existir');

// Ficheiros vendor existem e tem tamanho.
foreach (sige_assets_libs() as $h => $info) {
    $p = $root . '/assets/vendor/' . $info[0];
    $check(is_file($p) && filesize($p) > 1000, "ficheiro vendor presente: " . $info[0]);
}

echo "--------------------------------------------------------\n";
if ($fail > 0) { echo "SMOKE FASE4 ASSETS: $fail falha(s), $pass ok.\n"; exit(1); }
echo "SMOKE FASE4 ASSETS OK - $pass verificacoes passaram.\n";
