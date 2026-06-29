<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.11.9.8 - Smoke estático RH / Equipa e Professores
 * Executar fora do WordPress: php tools/smoke-rh-equipe-v12-11-9-8.php
 */
$root = dirname(__DIR__);
$files = [
    'ajax' => $root . '/includes/ajax-handlers.php',
    'perm' => $root . '/includes/permissions-layer.php',
    'view' => $root . '/admin/hr/equipe-view.php',
    'main' => $root . '/sige-softgenial.php',
    'build'=> $root . '/BUILD.json',
];
foreach ($files as $k => $f) {
    if (!is_file($f)) fail("Ficheiro em falta: {$k} {$f}");
}
$ajax = file_get_contents($files['ajax']);
$perm = file_get_contents($files['perm']);
$view = file_get_contents($files['view']);
$main = file_get_contents($files['main']);
$build = json_decode(file_get_contents($files['build']), true);

assert_contains($main, 'Version: 12.11.9.8', 'Header do plugin em 12.11.9.8');
assert_contains($main, "define('SIGE_VERSION', '12.11.9.8');", 'SIGE_VERSION em 12.11.9.8');
if (($build['version'] ?? '') !== '12.11.9.8') fail('BUILD.json não está em 12.11.9.8');

assert_contains($ajax, 'function sige_ajax_equipe_soft_revoke_access', 'Helper de soft delete existe');
assert_contains($ajax, 'remove_all_caps', 'Remoção revoga roles/capabilities');
assert_contains($ajax, 'sige_staff_removed_at', 'Meta de remoção segura existe');
assert_contains($ajax, 'staff_removido_soft_delete', 'Auditoria soft delete existe');
assert_contains($ajax, 'Acesso removido sem apagar wp_users', 'Não anuncia remoção física');
assert_not_contains_region($ajax, 'REMOVER FUNCIONÁRIO', 'RESET SENHA FUNCIONÁRIO', 'wp_delete_user', 'Endpoint de remover não pode apagar wp_users');
assert_contains($perm, 'sige_staff_removed_at', 'Permissões bloqueiam utilizador removido');
assert_contains($view, 'ocultar utilizadores removidos', 'Listagem oculta removidos');
assert_contains($view, "'nuit' => ''", 'Crachá não embute NUIT no DOM');
assert_not_contains($view, '<div class="info-label">NUIT</div>', 'Crachá não imprime linha NUIT');
assert_contains($ajax, 'Nome, email e perfil de acesso são obrigatórios', 'Cargo obrigatório no servidor');
assert_contains($ajax, 'NUIT inválido. Use exactamente 9 dígitos.', 'Validação NUIT no servidor');
assert_contains($ajax, 'sige_ajax_equipe_sanitize_image_url', 'Sanitização de fotografia existe');
assert_contains($ajax, "'/wp-content/uploads/'", 'Documentos limitados a uploads/biblioteca');

echo "OK - smoke RH v12.11.9.8 concluído sem falhas.\n";
exit(0);

function assert_contains(string $haystack, string $needle, string $msg): void {
    if (strpos($haystack, $needle) === false) fail($msg . " - não encontrado: " . $needle);
}
function assert_not_contains(string $haystack, string $needle, string $msg): void {
    if (strpos($haystack, $needle) !== false) fail($msg . " - encontrado indevidamente: " . $needle);
}
function assert_not_contains_region(string $haystack, string $start, string $end, string $needle, string $msg): void {
    $a = strpos($haystack, $start);
    $b = strpos($haystack, $end, $a === false ? 0 : $a);
    if ($a === false || $b === false || $b <= $a) fail('Região não encontrada para validação: ' . $start . ' → ' . $end);
    $region = substr($haystack, $a, $b - $a);
    assert_not_contains($region, $needle, $msg);
}
function fail(string $msg): void {
    fwrite(STDERR, "FALHOU: {$msg}\n");
    exit(1);
}
