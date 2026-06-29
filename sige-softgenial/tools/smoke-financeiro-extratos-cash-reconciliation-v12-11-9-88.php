<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test - SIGE SoftGenial v12.11.9.88
 * Financeiro Extractos - Cash Reconciliation PRO.
 */
$root = dirname(__DIR__);
$mainFile = $root . '/sige-softgenial.php';
$buildFile = $root . '/BUILD.json';
$viewFile = $root . '/admin/finance/financeiro-extratos.php';
$styleFile = $root . '/assets/style.css';

foreach ([$mainFile, $buildFile, $viewFile, $styleFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Ficheiro obrigatório em falta: {$file}\n");
        exit(1);
    }
}

$main = file_get_contents($mainFile);
$build = file_get_contents($buildFile);
$view = file_get_contents($viewFile);
$style = file_get_contents($styleFile);

$checks = [
    'Header Version 12.11.9.88.x' => (bool)preg_match('/Version:\s*12\.11\.9\.88(?:\.1)?/', $main),
    'SIGE_VERSION 12.11.9.88.x' => (bool)preg_match("/define\('SIGE_VERSION', '12\.11\.9\.88(?:\.1)?'\);/", $main),
    'BUILD version 12.11.9.88.x' => (bool)preg_match('/"version"\s*:\s*"12\.11\.9\.88(?:\.1)?"/', $build),
    'Build label phase 2 or hotfix' => strpos($build, 'Financeiro Cash Reconciliation PRO') !== false || strpos($build, 'Financeiro Status Sync Hotfix') !== false,
    'Helper money parser' => strpos($view, 'function sige_extracto_money_from_raw') !== false,
    'Helper schema-safe column filter' => strpos($view, 'function sige_extracto_filter_existing_columns') !== false,
    'Recon marker encode' => strpos($view, '[SIGE_RECON_V1]') !== false,
    'Recon marker parse' => strpos($view, 'function sige_extracto_recon_parse') !== false,
    'Recon form present' => strpos($view, 'data-sg-recon-form') !== false,
    'Counted values by method' => strpos($view, 'name="sg_contado[') !== false,
    'Divergence note field' => strpos($view, 'name="motivo_divergencia"') !== false,
    'Recon confirmation checkbox' => strpos($view, 'name="sg_recon_confirmado"') !== false,
    'Server-side divergence validation' => strpos($view, 'Existe divergência entre o sistema e o valor contado') !== false,
    'Server-side checklist validation' => strpos($view, 'Confirme a checklist de reconciliação') !== false,
    'Audit log reconciled close' => strpos($view, 'caixa_fechado_reconciliado') !== false,
    'Print close term function' => strpos($view, 'sigeImprimirTermoFecho') !== false,
    'Closed summary recon grid' => strpos($view, 'sg-close-recon-grid') !== false,
    'Close history section' => strpos($view, 'sg-close-history') !== false,
    'Previous observations preserved' => strpos($view, 'HISTÓRICO ANTERIOR') !== false,
    'Reopen preserves observations' => strpos($view, 'obs_anterior') !== false && strpos($view, 'obs_reabertura') !== false,
    'No schema migration added in view' => strpos($view, 'ALTER TABLE') === false,
    'Phase 1 density preserved' => strpos($view, 'sige_fin_extratos_density') !== false && strpos($view, 'sg-density-toggle') !== false,
    'Phase 1 mobile actionbar preserved' => strpos($view, 'sg-mobile-actionbar') !== false,
    'Phase 1 submit lock preserved' => strpos($view, 'data-sg-lock-submit') !== false && strpos($view, 'dataset.sgSubmitted') !== false,
    'Deep smoke safe lower preserved' => strpos($view, 'function sige_extracto_safe_lower') !== false,
    'No direct mb_strtolower cycle regression' => strpos($view, '$classe_txt = mb_strtolower') === false && strpos($view, '$servico_ciclo_txt = mb_strtolower') === false,
    'Style global PRO UX preserved' => strpos($style, 'Financeiro Extractos PRO UX Hardening') !== false,
];

$failed = [];
foreach ($checks as $label => $ok) {
    if (!$ok) $failed[] = $label;
}

// Basic CSS brace balance inside the inline style region.
if (preg_match('/<style>(.*?)<\/style>/s', $view, $m)) {
    $css = preg_replace('/\/\*.*?\*\//s', '', $m[1]);
    $checks['CSS brace balance'] = substr_count($css, '{') === substr_count($css, '}');
    if (!$checks['CSS brace balance']) $failed[] = 'CSS brace balance';
} else {
    $failed[] = 'Inline style block found';
}

// Basic JavaScript presence checks. Full syntax is validated separately by extracting rendered-like JS.
foreach (['sgReconDifference', 'sgValidateReconForm', 'sgInitReconForms', 'sgNumber', 'sgMoney'] as $fn) {
    if (strpos($view, $fn) === false) $failed[] = "JS function {$fn}";
}

if ($failed) {
    fwrite(STDERR, "SMOKE FAILED - Financeiro Cash Reconciliation PRO\n");
    foreach ($failed as $f) fwrite(STDERR, " - {$f}\n");
    exit(1);
}

echo "SMOKE OK - financeiro-extratos Cash Reconciliation PRO v12.11.9.88.x validado." . PHP_EOL;
