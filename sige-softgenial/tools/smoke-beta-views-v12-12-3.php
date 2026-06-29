<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$fails = [];
$oks = 0;
$check = static function (bool $cond, string $label) use (&$fails, &$oks): void {
    if ($cond) { $oks++; echo "OK   {$label}\n"; }
    else { $fails[] = $label; echo "FAIL {$label}\n"; }
};
$read = static function (string $rel) use ($root): string {
    $path = $root . '/' . $rel;
    return is_file($path) ? (string) file_get_contents($path) : '';
};

$ui = $read('includes/ui-components.php');
$shell = $read('includes/admin-shell.php');
$css = $read('assets/style.css');
$views = [
    'financeiro-mpesa',
    'whatsapp_circulares',
    'comunicacoes_central',
    'presencas',
];

foreach ($views as $view) {
    $check(strpos($ui, "'{$view}' =>") !== false, "Catalogo UI contem o view beta {$view}");
    $pos = strpos($ui, "'{$view}' =>");
    $chunk = $pos !== false ? substr($ui, $pos, 520) : '';
    $check(strpos($chunk, "'status' => 'beta'") !== false, "View {$view} marcado como beta no catalogo");
    $check(strpos($chunk, "'beta_note'") !== false, "View {$view} tem nota beta declarada");
}

$check(strpos($ui, 'sg-product-status-badge sg-product-status-beta') !== false, 'Renderizador imprime badge BETA');
$check(strpos($ui, 'sg-product-beta-note') !== false, 'Renderizador imprime nota beta');
$check(strpos($ui, 'Nota beta:') !== false, 'Nota beta tem rotulo claro');
$check(strpos($css, '.sg-product-page-title-row') !== false, 'CSS da linha titulo mais badge presente');
$check(strpos($css, '.sg-product-status-beta') !== false, 'CSS do badge beta presente');
$check(strpos($css, '.sg-product-beta-note') !== false, 'CSS da nota beta presente');

if (preg_match('/\$sige_views_with_own_header\s*=\s*\[(.*?)\];/s', $shell, $m)) {
    $own = $m[1];
    foreach ($views as $view) {
        $check(strpos($own, "'{$view}'") === false, "View {$view} usa cabecalho central para renderizar nota beta");
    }
} else {
    $check(false, 'Lista de views com cabecalho proprio encontrada');
}

$routes = [
    "'financeiro-mpesa' => 'admin/finance/mpesa-view.php'",
    "'whatsapp_circulares' => 'admin/whatsapp_circulares-view.php'",
    "'comunicacoes_central' => 'admin/comunicacoes_central-view.php'",
    "'presencas'        => 'admin/academic/presencas-view.php'",
];
foreach ($routes as $route) {
    $check(strpos($shell, $route) !== false, "Rota preservada: {$route}");
}

if ($fails) {
    fwrite(STDERR, 'BETA VIEWS SMOKE FALHOU: ' . count($fails) . " falha(s)\n");
    foreach ($fails as $f) { fwrite(STDERR, " - {$f}\n"); }
    exit(1);
}

echo "BETA VIEWS SMOKE OK - {$oks} verificacoes passaram.\n";
exit(0);
