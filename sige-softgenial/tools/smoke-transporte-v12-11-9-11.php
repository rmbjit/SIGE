<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.11.9.11 - Smoke estático Transportes / Harmonia Visual
 * Executar fora do WordPress: php tools/smoke-transporte-v12-11-9-11.php
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

assert_contains($main, 'Version: 12.11.9.11', 'Header do plugin em 12.11.9.11');
assert_contains($main, "define('SIGE_VERSION', '12.11.9.11');", 'SIGE_VERSION em 12.11.9.11');
if (($build['version'] ?? '') !== '12.11.9.11') fail('BUILD.json não está em 12.11.9.11');

// Lógica funcional da v12.11.9.10 preservada.
assert_contains($view, 'edit_rota', 'Parâmetro de edição de rota existe');
assert_contains($view, 'rota_id', 'Campo oculto de rota editável existe');
assert_contains($view, '$wpdb->update', 'Actualização de rota existe');
assert_contains($view, "['id' => \$rota_id, 'escola_id' => \$sige_transport_escola_id]", 'Update protegido por id + escola_id');
assert_contains($view, 'sige_transport_rota_post_data', 'Sanitização centralizada do formulário existe');
assert_contains($view, 'sige_transport_money_to_float', 'Normalização segura de valor monetário existe');
assert_contains($view, 'wp_verify_nonce', 'Nonce continua obrigatório');
assert_contains($view, 'sige_transport_can_manage_routes', 'Permissão granular de gestão de rotas continua activa');
assert_contains($view, 'rota_transporte_editada', 'Auditoria de edição existe');

// Harmonia visual Produto PRO.
assert_contains($view, 'sg-transport-v2', 'Wrapper visual Produto PRO existe');
assert_contains($view, 'sg-transport-hero', 'Hero moderno existe');
assert_contains($view, 'sg-transport-kpi-grid', 'Grid de KPIs existe');
assert_contains($view, 'sg-route-card', 'Cartões de rota existem');
assert_contains($view, 'sg-progress-mini', 'Barra visual de lotação existe');
assert_contains($view, 'body.sige-view-transporte .sg-product-page-head', 'Cabeçalho genérico oculto quando há hero próprio');
assert_contains($view, 'sige_transport_icon', 'Helper de ícones do módulo existe');
assert_contains($view, 'Potencial mensal', 'Indicador visual de potencial mensal existe');
assert_contains($view, 'Não altera cobranças, pagamentos ou fórmulas', 'Comentário de escopo visual preservado');
assert_not_contains($view, 'sige_fin_', 'Módulo financeiro não foi acoplado ao transporte');

// Segurança contra regressões visuais antigas.
assert_not_contains($view, 'wp-list-table widefat fixed striped', 'Tabela WordPress antiga removida');
assert_not_contains($view, 'border-bottom:2px solid #e65100', 'Cabeçalho antigo laranja removido');

echo "OK - smoke Transportes v12.11.9.11 concluído sem falhas.\n";
exit(0);

function assert_contains(string $haystack, string $needle, string $msg): void { if (strpos($haystack, $needle) === false) fail($msg . " - não encontrado: " . $needle); }
function assert_not_contains(string $haystack, string $needle, string $msg): void { if (strpos($haystack, $needle) !== false) fail($msg . " - encontrado indevidamente: " . $needle); }
function fail(string $msg): void { fwrite(STDERR, "FALHOU: {$msg}\n"); exit(1); }
