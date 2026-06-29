<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$checks = [];
$fail = [];
$ok = function(string $label, bool $cond) use (&$checks, &$fail): void {
    $checks[] = $label;
    if (!$cond) { $fail[] = $label; echo "FAIL  {$label}\n"; }
    else { echo "OK    {$label}\n"; }
};

$plugin = file_get_contents($root . '/sige-softgenial.php');
$uiKit  = file_get_contents($root . '/includes/ui-kit.php');
$pag    = file_get_contents($root . '/admin/finance/financeiro-pagamentos.php');
$run    = file_get_contents($root . '/tools/run-gates.php');

$ok('plugin header declara versao 12.15.x estavel', preg_match('/Version:\s*([0-9.]+)/', $plugin, $m) && version_compare($m[1], '12.15.3', '>='));
$ok('SIGE_VERSION declara versao 12.15.x estavel', preg_match('/define\(\'SIGE_VERSION\',\s*\'([0-9.]+)\'\);/', $plugin, $m2) && version_compare($m2[1], '12.15.3', '>='));
$ok('ui-kit nao enfileira CSS global sige-design-system-pro', strpos($uiKit, "sige-design-system-pro.css") === false && strpos($uiKit, "wp_enqueue_style('sige-design-system-pro'") === false);
$ok('ui-kit nao enfileira JS global sige-design-system-pro', strpos($uiKit, "sige-design-system-pro.js") === false && strpos($uiKit, "wp_enqueue_script('sige-design-system-pro'") === false);
$ok('asset CSS global PRO ausente do pacote', !is_file($root . '/assets/sige-design-system-pro.css'));
$ok('asset JS global PRO ausente do pacote', !is_file($root . '/assets/sige-design-system-pro.js'));
$ok('confirmacao de pagamento valida seleccao antes de abrir modal', strpos($pag, 'sigePagamentoPodeAbrirConfirmacao') !== false && strpos($pag, 'Seleccione pelo menos uma dívida') !== false);
$ok('confirmacao de pagamento valida metodo antes de abrir modal', strpos($pag, 'Seleccione o método de pagamento') !== false);
$ok('confirmacao de pagamento valida total positivo', strpos($pag, 'O total a pagar está a zero') !== false);
$ok('run-gates inclui smoke de estabilidade v12.15.3', strpos($run, 'smoke-design-system-stability-v12-15-3.php') !== false);

if ($fail) {
    fwrite(STDERR, "SMOKE DESIGN SYSTEM STABILITY v12.15.3 FALHOU: " . implode('; ', $fail) . "\n");
    exit(1);
}
echo str_repeat('-', 56) . "\n";
echo 'SMOKE DESIGN SYSTEM STABILITY v12.15.3 OK - ' . count($checks) . " verificacoes passaram.\n";
exit(0);
