<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Deep Smoke Gate - SIGE SoftGenial v12.11.9.87.1
 * Financeiro > Extractos/Caixa.
 *
 * Gate estático pós-render harness: verifica os hardenings descobertos no smoke
 * profundo antes da Fase 2, sem carregar WordPress nem executar SQL.
 */
$root = dirname(__DIR__);
$main   = $root . '/sige-softgenial.php';
$build  = $root . '/BUILD.json';
$module = $root . '/admin/finance/financeiro-extratos.php';
$style  = $root . '/assets/style.css';
$errors = [];
foreach ([$main, $build, $module, $style] as $file) {
    if (!is_file($file)) $errors[] = 'Ficheiro em falta: ' . $file;
}
if ($errors) {
    fwrite(STDERR, "DEEP SMOKE FAILED\n" . implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}
$mainTxt = file_get_contents($main);
$buildTxt = file_get_contents($build);
$moduleTxt = file_get_contents($module);
$styleTxt = file_get_contents($style);
$checks = [
    'Versão principal 12.11.9.87.1' => strpos($mainTxt, 'Version: 12.11.9.87.1') !== false && strpos($mainTxt, "define('SIGE_VERSION', '12.11.9.87.1');") !== false,
    'BUILD 12.11.9.87.1' => strpos($buildTxt, '"version": "12.11.9.87.1"') !== false,
    'Registo Deep Smoke no changelog inline' => strpos($mainTxt, 'Financeiro Extractos Deep Smoke Gate Hotfix') !== false,
    'Helper safe lower existe' => strpos($moduleTxt, 'function sige_extracto_safe_lower') !== false,
    'Helper safe lower usa fallback sem mbstring' => strpos($moduleTxt, "function_exists('mb_strtolower') ? mb_strtolower") !== false && strpos($moduleTxt, 'strtolower($value)') !== false,
    'Ciclo não chama mb_strtolower directamente' => strpos($moduleTxt, '$classe_txt = mb_strtolower') === false && strpos($moduleTxt, '$servico_ciclo_txt = mb_strtolower') === false,
    'Regex de ciclo case-insensitive Unicode' => strpos($moduleTxt, '/iu') !== false,
    'Despesa sem categoria protegida' => strpos($moduleTxt, '$dd_categoria = trim((string)($dd->categoria ?? \'\'))') !== false,
    'Descrição de despesa com fallback' => strpos($moduleTxt, "\$dd->descricao ?? 'Despesa'") !== false,
    'Recibo sem recibo_numero protegido' => substr_count($moduleTxt, '$e->recibo_numero ??') >= 2,
    'Densidade preservada' => strpos($moduleTxt, 'sige_fin_extratos_density') !== false && strpos($moduleTxt, 'sg-density-toggle') !== false,
    'Barra mobile preservada' => strpos($moduleTxt, 'sg-mobile-actionbar') !== false,
    'Submit lock preservado' => strpos($moduleTxt, 'data-sg-lock-submit') !== false && strpos($moduleTxt, 'dataset.sgSubmitted') !== false,
    'Style global da Fase 1 preservado' => strpos($styleTxt, 'SoftGenial v12.11.9.87 - Financeiro Extractos PRO UX Hardening') !== false,
];
foreach ($checks as $name => $ok) {
    if (!$ok) $errors[] = 'Falhou: ' . $name;
}
foreach ([$main, $module] as $file) {
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    if ($code !== 0) $errors[] = 'PHP lint falhou em ' . basename($file) . ': ' . implode(' ', $out);
}
if ($errors) {
    fwrite(STDERR, "DEEP SMOKE FAILED\n" . implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}
echo "DEEP SMOKE OK - financeiro-extratos v12.11.9.87.1 pronto para gate da Fase 2." . PHP_EOL;
