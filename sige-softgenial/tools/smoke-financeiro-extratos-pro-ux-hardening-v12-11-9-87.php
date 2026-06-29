<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test - SIGE SoftGenial v12.11.9.87.1
 * Financeiro > Extractos/Caixa PRO UX Hardening - Fase 1 + Deep Smoke Gate Hotfix.
 *
 * Valida camada de UX PRO incremental sem tocar em regras financeiras.
 */
$root = dirname(__DIR__);
$files = [
    'main'   => $root . '/sige-softgenial.php',
    'build'  => $root . '/BUILD.json',
    'module' => $root . '/admin/finance/financeiro-extratos.php',
    'style'  => $root . '/assets/style.css',
];
$errors = [];
foreach ($files as $label => $path) {
    if (!is_file($path)) {
        $errors[] = "Ficheiro em falta: {$label} ({$path})";
    }
}
if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}
$main = file_get_contents($files['main']);
$build = file_get_contents($files['build']);
$module = file_get_contents($files['module']);
$style = file_get_contents($files['style']);

$checks = [
    'Header Version 12.11.9.87.1' => strpos($main, 'Version: 12.11.9.87.1') !== false,
    'SIGE_VERSION 12.11.9.87.1' => strpos($main, "define('SIGE_VERSION', '12.11.9.87.1');") !== false,
    'BUILD version 12.11.9.87.1' => strpos($build, '"version": "12.11.9.87.1"') !== false,
    'Changelog PRO UX Hardening registado' => strpos($main, 'Financeiro Extractos PRO UX Hardening') !== false,
    'Deep Smoke Gate registado' => strpos($main, 'Financeiro Extractos Deep Smoke Gate Hotfix') !== false,
    'CSS marker inline v12.11.9.87' => strpos($module, 'v12.11.9.87 - Financeiro Extractos PRO UX Hardening') !== false,
    'CSS global marker v12.11.9.87' => strpos($style, 'SoftGenial v12.11.9.87 - Financeiro Extractos PRO UX Hardening') !== false,
    'Preferência de densidade via user_meta' => strpos($module, "sige_fin_extratos_density") !== false && strpos($module, '$sg_density_class') !== false,
    'Densidade visual na raiz' => strpos($module, 'data-sg-density="<?php echo esc_attr($sg_density); ?>"') !== false,
    'Toggle de densidade' => strpos($module, 'sg-density-toggle') !== false && strpos($module, 'Confortável') !== false,
    'Presets rápidos' => strpos($module, 'sg-filter-presets') !== false && strpos($module, 'Mês anterior') !== false && strpos($module, '$sg_filter_presets') !== false,
    'Filtros preservam densidade' => strpos($module, 'name="sg_density" value="<?php echo esc_attr($sg_density); ?>"') !== false,
    'Barra móvel de acções' => strpos($module, 'sg-mobile-actionbar') !== false && strpos($module, 'data-sg-submit-bulk') !== false,
    'Scroll assistido' => strpos($module, 'data-sg-scroll="#sg-filter-card"') !== false && strpos($module, 'sg-scroll-highlight') !== false,
    'Checklist crítica do caixa' => strpos($module, 'sg-critical-checklist') !== false && strpos($module, 'Conferir valores por método de pagamento') !== false,
    'Submit idempotente' => strpos($module, 'data-sg-lock-submit') !== false && strpos($module, 'dataset.sgSubmitted') !== false,
    'Modal focus trap' => strpos($module, 'function sgFocusable') !== false && strpos($module, "e.key !== 'Tab'") !== false,
    'Modais com role e aria' => strpos($module, 'role="dialog" aria-modal="true" aria-labelledby="sg-estorno-title"') !== false && strpos($module, 'aria-labelledby="sg-caixa-confirm-title"') !== false,
    'Export mantém feedback visual' => strpos($module, 'data-sg-export') !== false && strpos($module, 'sgSetBusy') !== false,
    'Estorno continua delegado ao service' => strpos($module, 'SIGE_FinanceActionService::estornarPagamento') !== false,
    'Fallback sem mbstring no ciclo' => strpos($module, 'function sige_extracto_safe_lower') !== false && strpos($module, 'sige_extracto_safe_lower($classe)') !== false,
    'Despesa sem categoria protegida' => strpos($module, "\$dd_categoria = trim((string)(\$dd->categoria ?? ''))") !== false,
    'Recibo sem recibo_numero protegido' => strpos($module, "\$e->recibo_numero ?? ''") !== false,
    'Query diária continua bloqueada no modo aluno pretendido' => strpos($module, '} elseif (!$sg_em_modo_aluno) {') !== false,
];
foreach ($checks as $name => $ok) {
    if (!$ok) {
        $errors[] = "Falhou: {$name}";
    }
}
foreach ([$files['main'], $files['module']] as $path) {
    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    exec($cmd, $out, $code);
    if ($code !== 0) {
        $errors[] = 'PHP lint falhou em ' . basename($path) . ': ' . implode(' ', $out);
    }
}
if ($errors) {
    fwrite(STDERR, "SMOKE FAILED" . PHP_EOL . implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}
echo "SMOKE OK - financeiro-extratos PRO UX Hardening v12.11.9.87.1 validado." . PHP_EOL;
