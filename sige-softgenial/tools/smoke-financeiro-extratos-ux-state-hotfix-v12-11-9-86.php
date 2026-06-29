<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test - SIGE SoftGenial v12.11.9.86
 * Financeiro > Extractos/Caixa UX State Hotfix.
 *
 * Valida o hotfix do warning $sg_period_range e o estado correcto quando
 * o utilizador abre modo=aluno sem aluno_id seleccionado.
 */
$root = dirname(__DIR__);
$files = [
    'main'   => $root . '/sige-softgenial.php',
    'build'  => $root . '/BUILD.json',
    'module' => $root . '/admin/finance/financeiro-extratos.php',
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

$posPeriodDefine = strpos($module, '$sg_period_range = ($data_inicio_relatorio === $data_fim_relatorio)');
$posModeToggle = strpos($module, '$modo_get = sige_fin_get_param');
$posTableUse = strpos($module, '$rel_periodo_label . \' · \' . $rel_ciclo_label . \' · \' . $sg_period_range');
$periodAssigns = substr_count($module, '$sg_period_range = ($data_inicio_relatorio === $data_fim_relatorio)');
$snippetKpi = "if (!\$sg_em_modo_aluno && function_exists('sige_kpi_caixa_dia'))";
$snippetReopen = "if (!\$sg_em_modo_aluno && \$_SERVER['REQUEST_METHOD'] === 'POST' && isset(\$_POST['sige_reabrir_caixa']))";
$snippetDespesas = "if (!\$sg_em_modo_aluno && \$wpdb->get_var(\"SHOW TABLES LIKE '\$tDesp'\") === \$tDesp)";
$snippetTableTitle = "echo \$sg_em_modo_aluno ? 'Histórico individual' : 'Movimentos financeiros'";

$checks = [
    'Header Version 12.11.9.86' => strpos($main, 'Version: 12.11.9.86') !== false,
    'SIGE_VERSION 12.11.9.86' => strpos($main, "define('SIGE_VERSION', '12.11.9.86');") !== false,
    'BUILD version 12.11.9.86' => strpos($build, '"version": "12.11.9.86"') !== false,
    'Changelog hotfix registado' => strpos($main, 'Financeiro Extractos/Caixa UX State Hotfix') !== false,
    'Intervalo inicializado antes do modo' => $posPeriodDefine !== false && $posModeToggle !== false && $posPeriodDefine < $posModeToggle,
    'Intervalo inicializado uma só vez' => $periodAssigns === 1,
    'Intervalo usado depois da inicialização' => $posPeriodDefine !== false && $posTableUse !== false && $posPeriodDefine < $posTableUse,
    'Defaults seguros para extrato' => strpos($module, '$extrato = [];') !== false && strpos($module, '$aluno_dados = null;') !== false,
    'Modo aluno pretendido bloqueia query diária' => strpos($module, '} elseif (!$sg_em_modo_aluno) {') !== false,
    'Fecho do caixa só no modo diário pretendido' => strpos($module, 'if (!$sg_em_modo_aluno) {' . "\n\n" . ' $fecho_row') !== false,
    'KPI diário só no modo diário pretendido' => strpos($module, $snippetKpi) !== false,
    'Reabrir caixa só no modo diário pretendido' => strpos($module, $snippetReopen) !== false,
    'Despesas do dia só no modo diário pretendido' => strpos($module, $snippetDespesas) !== false,
    'Tabela usa modo aluno pretendido' => strpos($module, $snippetTableTitle) !== false,
    'Estado vazio de aluno sem aluno_id' => strpos($module, 'if ($sg_em_modo_aluno && !$aluno_id):') !== false,
    'Export Excel oculto no modo aluno pretendido' => strpos($module, 'if (!$sg_em_modo_aluno && !empty($extrato)):') !== false,
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
echo "SMOKE OK - financeiro-extratos UX State Hotfix v12.11.9.86 validado." . PHP_EOL;
