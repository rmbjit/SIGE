<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Smoke de runtime: armazenamento privado de documentos.
 * Carrega o modulo com stubs do WordPress e de um directorio de uploads temporario
 * e prova: a deteccao de valores privados, a escrita de negacao total, o movimento
 * seguro de um documento (e da sua miniatura) para o directorio privado, a remocao do
 * original e a degradacao sem perda quando o ficheiro nao existe.
 */
if (!defined('ABSPATH')) define('ABSPATH', dirname(__DIR__) . '/');

$BASE = sys_get_temp_dir() . '/sige_priv_' . uniqid();
$UPLOADS = $BASE . '/uploads';
$BASEURL = 'https://example.test/wp-content/uploads';
@mkdir($UPLOADS . '/2026/06', 0777, true);

// Stubs.
$GLOBALS['__sige_hooks'] = [];
if (!function_exists('add_action')) { function add_action($h, $c, $p = 10, $a = 1) { $GLOBALS['__sige_hooks'][] = $h; } }
if (!function_exists('add_filter')) { function add_filter($h, $c, $p = 10, $a = 1) {} }
if (!function_exists('sige_security_log')) { function sige_security_log($a, $b = '', $c = 0) {} }
if (!function_exists('wp_get_upload_dir')) { function wp_get_upload_dir() { return ['basedir' => $GLOBALS['__UPLOADS'], 'baseurl' => $GLOBALS['__BASEURL']]; } }
if (!function_exists('wp_mkdir_p')) { function wp_mkdir_p($d) { return is_dir($d) || mkdir($d, 0777, true); } }
if (!function_exists('wp_basename')) { function wp_basename($p) { return basename($p); } }
if (!function_exists('wp_unique_filename')) { function wp_unique_filename($dir, $name) { $t = $dir . '/' . $name; if (!file_exists($t)) return $name; $i = 1; $ext = pathinfo($name, PATHINFO_EXTENSION); $b = pathinfo($name, PATHINFO_FILENAME); while (file_exists($dir . '/' . $b . '-' . $i . '.' . $ext)) $i++; return $b . '-' . $i . '.' . $ext; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($d, $o = 0) { return json_encode($d, $o); } }
if (!function_exists('sige_get_escola_id')) { function sige_get_escola_id() { return 7; } }
// Resolver: mapeia a URL publica de teste para o caminho local temporario.
if (!function_exists('sige_secure_document_resolve_local_path')) {
    function sige_secure_document_resolve_local_path($url) {
        $base = $GLOBALS['__BASEURL'];
        if (strpos($url, $base . '/') !== 0) return '';
        $rel = ltrim(substr($url, strlen($base)), '/');
        $p = $GLOBALS['__UPLOADS'] . '/' . $rel;
        return is_file($p) ? $p : '';
    }
}
$GLOBALS['__UPLOADS'] = $UPLOADS;
$GLOBALS['__BASEURL'] = $BASEURL;

require_once dirname(__DIR__) . '/includes/security-uploads-private.php';

$fails = []; $ok = 0;
$check = static function (bool $cond, string $label) use (&$fails, &$ok): void {
    if ($cond) { $ok++; echo "OK   {$label}\n"; } else { $fails[] = $label; echo "FAIL {$label}\n"; }
};

// 1. Hook do dreno registado.
$check(in_array('admin_init', $GLOBALS['__sige_hooks'], true), 'dreno ligado a admin_init');

// 2. Deteccao de valor privado.
$check(sige_uploads_value_is_private($BASEURL . '/sige-private/docs/7/bi.jpg') === true, 'URL privada detectada');
$check(sige_uploads_value_is_private($BASEURL . '/2026/06/bi.jpg') === false, 'URL publica nao e privada');

// 3. Garantir directorio privado e negacao total.
$dir = sige_uploads_ensure_private_dir(7);
$check($dir !== '' && is_dir($dir), 'directorio privado da escola criado');
$root = $UPLOADS . '/sige-private/docs';
$ht = (string)@file_get_contents($root . '/.htaccess');
$check(is_file($root . '/.htaccess') && strpos($ht, 'Require all denied') !== false, '.htaccess de negacao total escrito');
$check(is_file($root . '/web.config') && is_file($root . '/index.php'), 'web.config e index.php do directorio privado presentes');
$check(strpos($ht, 'SIGE-PRIVATE-DOCS') !== false, 'marcador do directorio privado presente');

// 4. Movimento de um documento (com miniatura) para privado.
file_put_contents($UPLOADS . '/2026/06/bi.jpg', str_repeat('A', 2048));
file_put_contents($UPLOADS . '/2026/06/bi-150x150.jpg', str_repeat('B', 256)); // miniatura
$pub_url = $BASEURL . '/2026/06/bi.jpg';
$priv = sige_uploads_move_to_private($pub_url, 7);
$check($priv !== '' && strpos($priv, '/sige-private/docs/7/') !== false, 'documento movido devolve URL privada');
$check(!is_file($UPLOADS . '/2026/06/bi.jpg'), 'original removido do directorio publico');
$check(!is_file($UPLOADS . '/2026/06/bi-150x150.jpg'), 'miniatura removida do directorio publico');
$priv_rel = ltrim(substr($priv, strlen($BASEURL)), '/');
$check(is_file($UPLOADS . '/' . $priv_rel), 'documento presente no directorio privado');

// 5. Choke-point: vazio, ja privado, e degradacao sem perda.
$check(sige_uploads_privatize_doc_value('') === '', 'valor vazio devolve vazio');
$already = $BASEURL . '/sige-private/docs/7/x.pdf';
$check(sige_uploads_privatize_doc_value($already, 7) === $already, 'valor ja privado fica inalterado');
$ghost = $BASEURL . '/2026/06/nao-existe.pdf';
$check(sige_uploads_privatize_doc_value($ghost, 7) === $ghost, 'URL sem ficheiro degrada sem alterar (sem regressao)');

// 6. Choke-point move um ficheiro real e devolve URL privada.
file_put_contents($UPLOADS . '/2026/06/cv.pdf', str_repeat('C', 1024));
$priv2 = sige_uploads_privatize_doc_value($BASEURL . '/2026/06/cv.pdf', 7);
$check(strpos($priv2, '/sige-private/docs/7/') !== false && !is_file($UPLOADS . '/2026/06/cv.pdf'), 'choke-point move documento publico para privado');

// Limpeza.
$rrm = static function ($d) use (&$rrm) { foreach (glob($d . '/*') ?: [] as $p) { is_dir($p) ? $rrm($p) : @unlink($p); } @rmdir($d); };
$rrm($BASE);

echo str_repeat('-', 56) . "\n";
if ($fails) {
    fwrite(STDERR, 'SMOKE ARMAZENAMENTO PRIVADO FALHOU: ' . count($fails) . " falha(s)\n");
    foreach ($fails as $f) fwrite(STDERR, " - {$f}\n");
    exit(1);
}
echo "SMOKE ARMAZENAMENTO PRIVADO OK - {$ok} verificacoes passaram.\n";
