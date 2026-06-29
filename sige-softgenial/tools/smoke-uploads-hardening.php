<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Smoke de runtime: blindagem de uploads e ficheiros.
 * Carrega o modulo com stubs do WordPress e prova o comportamento real:
 * deteccao de extensoes perigosas, de inicio executavel/script, de SVG malicioso,
 * o veredicto do avaliador, a validacao da importacao e a escrita dos ficheiros
 * de proteccao. Nao precisa de WordPress vivo.
 */
if (!defined('ABSPATH')) define('ABSPATH', dirname(__DIR__) . '/');

// Stubs minimos.
$GLOBALS['__sige_hooks'] = [];
if (!function_exists('add_action')) { function add_action($h, $c, $p = 10, $a = 1) { $GLOBALS['__sige_hooks']['action'][] = $h; } }
if (!function_exists('add_filter')) { function add_filter($h, $c, $p = 10, $a = 1) { $GLOBALS['__sige_hooks']['filter'][] = $h; } }
if (!function_exists('sige_security_log')) { function sige_security_log($a, $b = '', $c = 0) {} }

require_once dirname(__DIR__) . '/includes/security-uploads.php';

$fails = [];
$ok = 0;
$check = static function (bool $cond, string $label) use (&$fails, &$ok): void {
    if ($cond) { $ok++; echo "OK   {$label}\n"; }
    else { $fails[] = $label; echo "FAIL {$label}\n"; }
};
$tmpfile = static function (string $bytes): string {
    $p = tempnam(sys_get_temp_dir(), 'sige_up_');
    file_put_contents($p, $bytes);
    return $p;
};

// 1. Hooks registados pelos stubs.
$check(in_array('wp_handle_upload_prefilter', $GLOBALS['__sige_hooks']['filter'] ?? [], true), 'prefilter ligado a wp_handle_upload_prefilter');
$check(in_array('admin_init', $GLOBALS['__sige_hooks']['action'] ?? [], true), 'blindagem ligada a admin_init');

// 2. Extensoes perigosas (incluindo dupla extensao).
$check(sige_uploads_filename_has_dangerous_ext('shell.php') === true, 'extensao .php detectada');
$check(sige_uploads_filename_has_dangerous_ext('factura.php.jpg') === true, 'dupla extensao .php.jpg detectada');
$check(sige_uploads_filename_has_dangerous_ext('foto.jpg') === false, 'foto.jpg nao e perigosa');
$check(sige_uploads_filename_has_dangerous_ext('lista.xlsx') === false, 'lista.xlsx nao e perigosa');
$check(sige_uploads_filename_has_dangerous_ext('.htaccess') === true || sige_uploads_filename_has_dangerous_ext('a.htaccess') === true, 'htaccess tratado como perigoso');

// 3. Inicio de ficheiro executavel/script.
$php = $tmpfile("<?php echo 'x'; ?>");
$csv = $tmpfile("nome,idade\nJoao,7\nMaria,8\n");
$mz  = $tmpfile("MZ\x90\x00\x03binario");
$xml = $tmpfile("<?xml version=\"1.0\"?><root/>");
$check(sige_uploads_head_is_executable_or_script($php) === true, 'inicio <?php detectado');
$check(sige_uploads_head_is_executable_or_script($mz) === true, 'cabecalho MZ (executavel) detectado');
$check(sige_uploads_head_is_executable_or_script($csv) === false, 'CSV de texto nao e executavel');
$check(sige_uploads_head_is_executable_or_script($xml) === false, 'XML legitimo nao e confundido com PHP curto');

// 4. SVG malicioso vs limpo.
$check(sige_uploads_svg_is_malicious('<svg xmlns="..."><script>alert(1)</script></svg>') === true, 'SVG com script recusado');
$check(sige_uploads_svg_is_malicious('<svg xmlns="..."><image onload="x()"/></svg>') === true, 'SVG com evento recusado');
$check(sige_uploads_svg_is_malicious('<svg xmlns="..."><rect width="1" height="1"/></svg>') === false, 'SVG limpo aceite');

// 5. Avaliador central.
$php_as_jpg = $tmpfile('<?php system($_GET["c"]); ?>');
$txt_as_jpg = $tmpfile("isto e apenas texto, nao uma imagem");
$v1 = sige_uploads_assess_file($php_as_jpg, 'inocente.jpg');
$v2 = sige_uploads_assess_file($txt_as_jpg, 'inocente.jpg');
$v3 = sige_uploads_assess_file($csv, 'alunos.csv');
$check(!empty($v1['dangerous']), 'PHP renomeado para .jpg recusado (' . ($v1['reason'] ?? '') . ')');
$check(!empty($v2['dangerous']) && $v2['reason'] === 'imagem_invalida', 'texto renomeado para .jpg recusado como imagem invalida');
$check(empty($v3['dangerous']), 'CSV legitimo aceite pelo avaliador');

// 6. Validacao da importacao.
$xlsx_real = $tmpfile("PK\x03\x04" . str_repeat("\x00", 40));
$xlsx_fake = $tmpfile("<?php echo 'nao sou xlsx'; ?>");
$r1 = sige_uploads_validate_import_file($csv, 'alunos.csv', ['xlsx', 'csv', 'txt']);
$r2 = sige_uploads_validate_import_file($xlsx_real, 'alunos.xlsx', ['xlsx', 'csv', 'txt']);
$r3 = sige_uploads_validate_import_file($xlsx_fake, 'alunos.xlsx', ['xlsx', 'csv', 'txt']);
$r4 = sige_uploads_validate_import_file($php, 'alunos.csv', ['xlsx', 'csv', 'txt']);
$check(!empty($r1['ok']), 'importacao aceita CSV de texto');
$check(!empty($r2['ok']), 'importacao aceita .xlsx com cabecalho ZIP');
$check(empty($r3['ok']), 'importacao recusa PHP disfarcado de .xlsx');
$check(empty($r4['ok']), 'importacao recusa PHP disfarcado de .csv');

// 7. Escrita e verificacao dos ficheiros de proteccao.
$dir = sys_get_temp_dir() . '/sige_up_dir_' . uniqid();
@mkdir($dir, 0775, true);
$wrote = sige_uploads_write_protection_files($dir);
$check($wrote === true, 'ficheiros de proteccao escritos no directorio');
$check(is_file($dir . '/.htaccess') && is_file($dir . '/web.config') && is_file($dir . '/index.php'), '.htaccess, web.config e index.php presentes');
$check(sige_uploads_protection_present($dir) === true, 'proteccao reconhecida pelo verificador');
$ht = (string)@file_get_contents($dir . '/.htaccess');
$check(strpos($ht, 'Require all denied') !== false && strpos(strtolower($ht), 'php') !== false, '.htaccess nega execucao de PHP');
// Idempotencia: segunda escrita nao altera (marcador presente).
$mt1 = filemtime($dir . '/.htaccess');
clearstatcache();
sige_uploads_write_protection_files($dir);
$check(filemtime($dir . '/.htaccess') === $mt1, 'segunda escrita e idempotente (sem reescrita)');

// Limpeza.
foreach ([$php, $csv, $mz, $xml, $php_as_jpg, $txt_as_jpg, $xlsx_real, $xlsx_fake] as $f) { @unlink($f); }
@unlink($dir . '/.htaccess'); @unlink($dir . '/web.config'); @unlink($dir . '/index.php'); @rmdir($dir);

echo str_repeat('-', 56) . "\n";
if ($fails) {
    fwrite(STDERR, 'SMOKE BLINDAGEM DE UPLOADS FALHOU: ' . count($fails) . " falha(s)\n");
    foreach ($fails as $f) fwrite(STDERR, " - {$f}\n");
    exit(1);
}
echo "SMOKE BLINDAGEM DE UPLOADS OK - {$ok} verificacoes passaram.\n";
