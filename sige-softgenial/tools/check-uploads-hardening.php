<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Gate estatico: blindagem de uploads e ficheiros.
 * Verifica que o modulo existe, define o contrato, regista os hooks defensivos,
 * esta ligado ao plugin e protege o ponto de importacao. Sem WordPress vivo.
 */
$root = dirname(__DIR__);
$fails = [];
$read = static function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return is_file($p) ? (string)file_get_contents($p) : '';
};

$mod = $read('includes/security-uploads.php');
if ($mod === '') {
    fwrite(STDERR, "BLINDAGEM DE UPLOADS FALHOU\n - includes/security-uploads.php ausente\n");
    exit(1);
}

// 1. Contrato de funcoes presente.
$funcs = [
    'sige_uploads_dangerous_extensions',
    'sige_uploads_dangerous_mimes',
    'sige_uploads_filename_has_dangerous_ext',
    'sige_uploads_head_is_executable_or_script',
    'sige_uploads_svg_is_malicious',
    'sige_uploads_detect_real_mime',
    'sige_uploads_assess_file',
    'sige_uploads_prefilter',
    'sige_uploads_validate_import_file',
    'sige_uploads_write_protection_files',
    'sige_uploads_protection_present',
    'sige_uploads_harden_dir',
];
foreach ($funcs as $fn) {
    if (strpos($mod, "function {$fn}(") === false) $fails[] = "modulo sem funcao {$fn}";
}

// 2. Hooks defensivos registados.
if (strpos($mod, "add_filter('wp_handle_upload_prefilter', 'sige_uploads_prefilter')") === false) {
    $fails[] = "prefilter wp_handle_upload_prefilter nao registado";
}
if (strpos($mod, "add_action('admin_init', 'sige_uploads_harden_dir')") === false) {
    $fails[] = "blindagem do directorio nao ligada a admin_init";
}

// 3. Lista de extensoes perigosas cobre os tipos criticos.
foreach (['php', 'phtml', 'phar', 'exe', 'sh', 'jsp', 'cgi'] as $ext) {
    if (!preg_match("/'" . preg_quote($ext, '/') . "'/", $mod)) {
        $fails[] = "lista de extensoes perigosas sem {$ext}";
    }
}

// 4. .htaccess de uploads nega execucao de PHP (FilesMatch com php e Require all denied).
if (strpos($mod, 'FilesMatch') === false || strpos($mod, 'Require all denied') === false) {
    $fails[] = "regra .htaccess de negacao ausente no construtor";
}
if (strpos($mod, 'php_admin_flag engine off') === false) {
    $fails[] = "desactivacao do motor PHP ausente (mod_php)";
}

// 5. Marcador de versao das regras presente.
if (strpos($mod, 'SIGE-UPLOADS-HARDENING') === false) {
    $fails[] = "marcador de versao das regras ausente";
}

// 6. Modulo ligado ao plugin principal.
$main = $read('sige-softgenial.php');
if (strpos($main, "includes/security-uploads.php") === false) {
    $fails[] = "modulo nao carregado em sige-softgenial.php";
}

// 7. Ponto de importacao de alunos protegido pela validacao real.
$imp = $read('includes/aluno-fetch-ajax.php');
if (strpos($imp, 'sige_uploads_validate_import_file') === false) {
    $fails[] = "importacao de alunos sem validacao de conteudo real";
}

if ($fails) {
    fwrite(STDERR, "BLINDAGEM DE UPLOADS FALHOU\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo 'BLINDAGEM DE UPLOADS OK - modulo, hooks, regras e pontos de importacao verificados.' . "\n";
