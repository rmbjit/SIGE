<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.11.9.10 - Smoke estático RH / Arquivo de Removidos
 * Executar fora do WordPress: php tools/smoke-rh-equipe-v12-11-9-9.php
 */
$root = dirname(__DIR__);
$files = [
    'ajax' => $root . '/includes/ajax-handlers.php',
    'perm' => $root . '/includes/permissions-layer.php',
    'view' => $root . '/admin/hr/equipe-view.php',
    'main' => $root . '/sige-softgenial.php',
    'build'=> $root . '/BUILD.json',
];
foreach ($files as $k => $f) { if (!is_file($f)) fail("Ficheiro em falta: {$k} {$f}"); }
$ajax = file_get_contents($files['ajax']);
$perm = file_get_contents($files['perm']);
$view = file_get_contents($files['view']);
$main = file_get_contents($files['main']);
$build = json_decode(file_get_contents($files['build']), true);

assert_contains($main, 'Version: 12.11.9.10', 'Header do plugin em 12.11.9.10');
assert_contains($main, "define('SIGE_VERSION', '12.11.9.10');", 'SIGE_VERSION em 12.11.9.10');
if (($build['version'] ?? '') !== '12.11.9.10') fail('BUILD.json não está em 12.11.9.10');

assert_contains($view, 'Arquivo de colaboradores removidos', 'Arquivo RH visível no módulo');
assert_contains($view, '$removed_staff = get_users', 'Consulta de removidos existe');
assert_contains($view, 'sige_staff_removed_escola_id', 'Arquivo é filtrado por escola');
assert_contains($view, 'sige_staff_removed_at', 'Arquivo depende do marcador de soft delete');
assert_contains($view, 'mostrarArquivoRh', 'Botão/handler de scroll para arquivo existe');
assert_contains($view, 'este arquivo é apenas consultivo', 'Arquivo não propõe reactivação operacional');
assert_contains($view, 'Acesso revogado', 'Estado operacional do arquivo é claro');
assert_not_contains_region($view, 'id="rh-arquivo-removidos"', '</section>', 'salario_base', 'Arquivo não deve expor salário');
assert_not_contains_region($view, 'id="rh-arquivo-removidos"', '</section>', 'nib', 'Arquivo não deve expor dados bancários');
assert_not_contains_region($view, 'id="rh-arquivo-removidos"', '</section>', 'doc_bi', 'Arquivo não deve expor documentos pessoais');
assert_not_contains_region($ajax, 'REMOVER FUNCIONÁRIO', 'RESET SENHA FUNCIONÁRIO', 'wp_delete_user', 'Remoção continua sem apagar wp_users');
assert_contains($ajax, 'staff_removido_soft_delete', 'Auditoria soft delete continua activa');
assert_contains($perm, 'sige_staff_removed_at', 'Permissões continuam bloqueando removidos');

echo "OK - smoke RH v12.11.9.10 concluído sem falhas.\n";
exit(0);

function assert_contains(string $haystack, string $needle, string $msg): void { if (strpos($haystack, $needle) === false) fail($msg . " - não encontrado: " . $needle); }
function assert_not_contains_region(string $haystack, string $start, string $end, string $needle, string $msg): void {
    $a = strpos($haystack, $start); $b = strpos($haystack, $end, $a === false ? 0 : $a);
    if ($a === false || $b === false || $b <= $a) fail('Região não encontrada: ' . $start . ' → ' . $end);
    $region = substr($haystack, $a, $b - $a);
    if (strpos($region, $needle) !== false) fail($msg . " - encontrado indevidamente: " . $needle);
}
function fail(string $msg): void { fwrite(STDERR, "FALHOU: {$msg}\n"); exit(1); }
