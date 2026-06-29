<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.11.9.10 - Smoke estático Transportes / Edição de Rotas
 * Executar fora do WordPress: php tools/smoke-transporte-v12-11-9-10.php
 */
$root = dirname(__DIR__);
$files = [
    'view'  => $root . '/admin/logistics/transporte-view.php',
    'main'  => $root . '/sige-softgenial.php',
    'build' => $root . '/BUILD.json',
];
foreach ($files as $k => $f) { if (!is_file($f)) fail("Ficheiro em falta: {$k} {$f}"); }

$view  = file_get_contents($files['view']);
$main  = file_get_contents($files['main']);
$build = json_decode(file_get_contents($files['build']), true);

assert_contains($main, 'Version: 12.11.9.10', 'Header do plugin em 12.11.9.10');
assert_contains($main, "define('SIGE_VERSION', '12.11.9.10');", 'SIGE_VERSION em 12.11.9.10');
if (($build['version'] ?? '') !== '12.11.9.10') fail('BUILD.json não está em 12.11.9.10');

assert_contains($view, 'edit_rota', 'Parâmetro de edição de rota existe');
assert_contains($view, 'rota_id', 'Campo oculto de rota editável existe');
assert_contains($view, '$wpdb->update', 'Actualização de rota existe');
assert_contains($view, "['id' => \$rota_id, 'escola_id' => \$sige_transport_escola_id]", 'Update protegido por id + escola_id');
assert_contains($view, 'sige_transport_rota_post_data', 'Sanitização centralizada do formulário existe');
assert_contains($view, 'sige_transport_money_to_float', 'Normalização segura de valor monetário existe');
assert_contains($view, 'wp_verify_nonce', 'Nonce continua obrigatório');
assert_contains($view, 'sige_transport_can_manage_routes', 'Permissão granular de gestão de rotas continua activa');
assert_contains($view, 'Editar Rota', 'Modo visual de edição existe');
assert_contains($view, 'Cancelar edição', 'Cancelamento de edição existe');
assert_contains($view, 'rota_transporte_editada', 'Auditoria de edição existe');
assert_not_contains($view, 'sige_fin_', 'Módulo financeiro não foi acoplado ao transporte');

echo "OK - smoke Transportes v12.11.9.10 concluído sem falhas.\n";
exit(0);

function assert_contains(string $haystack, string $needle, string $msg): void { if (strpos($haystack, $needle) === false) fail($msg . " - não encontrado: " . $needle); }
function assert_not_contains(string $haystack, string $needle, string $msg): void { if (strpos($haystack, $needle) !== false) fail($msg . " - encontrado indevidamente: " . $needle); }
function fail(string $msg): void { fwrite(STDERR, "FALHOU: {$msg}\n"); exit(1); }
