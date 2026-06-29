<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Gate estatico: armazenamento privado de documentos sensiveis.
 * Verifica o modulo, o contrato, o dreno, a ligacao ao plugin, os pontos de
 * gravacao privatizados e a exclusao explicita da foto. Sem WordPress vivo.
 */
$root = dirname(__DIR__);
$fails = [];
$read = static function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return is_file($p) ? (string)file_get_contents($p) : '';
};

$mod = $read('includes/security-uploads-private.php');
if ($mod === '') {
    fwrite(STDERR, "ARMAZENAMENTO PRIVADO FALHOU\n - includes/security-uploads-private.php ausente\n");
    exit(1);
}

// 1. Contrato de funcoes.
$funcs = [
    'sige_uploads_private_marker', 'sige_uploads_write_deny_all', 'sige_uploads_private_root',
    'sige_uploads_ensure_private_dir', 'sige_uploads_value_is_private',
    'sige_uploads_resolve_public_local_path', 'sige_uploads_move_siblings_to_private',
    'sige_uploads_move_to_private', 'sige_uploads_privatize_doc_value',
    'sige_uploads_private_migration_drain', 'sige_uploads_maybe_run_private_migration',
];
foreach ($funcs as $fn) {
    if (strpos($mod, "function {$fn}(") === false) $fails[] = "modulo sem funcao {$fn}";
}

// 2. Dreno ligado ao admin_init.
if (strpos($mod, "add_action('admin_init', 'sige_uploads_maybe_run_private_migration')") === false) {
    $fails[] = "dreno de migracao nao ligado a admin_init";
}

// 3. Negacao total no directorio privado.
if (strpos($mod, 'Require all denied') === false || strpos($mod, 'sige-private') === false) {
    $fails[] = "regra de negacao total do directorio privado ausente";
}
if (strpos($mod, 'SIGE-PRIVATE-DOCS') === false) {
    $fails[] = "marcador do directorio privado ausente";
}

// 4. Movimento consumado so apos remover o original (seguranca anti-perda).
if (strpos($mod, 'if (!@unlink($src))') === false) {
    $fails[] = "movimento nao protege contra perda (consumar antes de remover original)";
}

// 5. Modulo ligado ao plugin.
$main = $read('sige-softgenial.php');
if (strpos($main, 'includes/security-uploads-private.php') === false) {
    $fails[] = "modulo nao carregado em sige-softgenial.php";
}

// 6. Pontos de gravacao do aluno privatizados.
$db = $read('includes/db-handler.php');
foreach (['doc_bi_url', 'doc_cert_url', 'doc_vacina_url'] as $field) {
    if (!preg_match('/' . preg_quote($field, '/') . "'\\s*=>\\s*function_exists\\('sige_uploads_privatize_doc_value'\\)/", $db)) {
        $fails[] = "campo do aluno {$field} nao privatizado na gravacao";
    }
}

// 7. Foto explicitamente NAO privatizada (mostrada em linha como imagem).
if (strpos($db, "sige_uploads_privatize_doc_value(esc_url_raw(\$_POST['foto_url']") !== false) {
    $fails[] = "a foto nao deve ser privatizada (e mostrada em linha)";
}
if (strpos($db, "'foto'                   => esc_url_raw(\$_POST['foto_url'] ?? ''),") === false) {
    $fails[] = "linha original da foto alterada inesperadamente";
}

// 8. Caminho AJAX da equipa privatizado.
$ax = $read('includes/ajax-handlers.php');
if (strpos($ax, 'sige_uploads_privatize_doc_value') === false) {
    $fails[] = "documentos da equipa nao privatizados no caminho AJAX";
}

if ($fails) {
    fwrite(STDERR, "ARMAZENAMENTO PRIVADO FALHOU\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo 'ARMAZENAMENTO PRIVADO OK - modulo, dreno, negacao total, gravacao privatizada e foto preservada.' . "\n";
