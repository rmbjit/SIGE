<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test - SIGE SoftGenial v12.11.9.85
 * Financeiro > Extractos/Caixa UX/UI Mobile-Tablet PRO.
 *
 * Este teste é estático: valida assinatura da build, presença dos blocos UX,
 * tabela responsiva por data-label, acessibilidade básica dos modais e remoção
 * do banner antigo duplicado de caixa fechado.
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
    'Header Version 12.11.9.85' => strpos($main, 'Version: 12.11.9.85') !== false,
    'SIGE_VERSION 12.11.9.85' => strpos($main, "define('SIGE_VERSION', '12.11.9.85');") !== false,
    'BUILD version 12.11.9.85' => strpos($build, '"version": "12.11.9.85"') !== false,
    'CSS marker inline UX-first' => strpos($module, 'v12.11.9.85 - Extractos UX/UI customization-first') !== false,
    'CSS global override v12.11.9.85' => strpos($style, 'SoftGenial v12.11.9.85 - Financeiro > Extractos/Caixa UX-first') !== false,
    'Filtro redesenhado' => strpos($module, 'sg-extracts-filter-card') !== false,
    'Resumo operacional separado' => strpos($module, 'sg-extracts-balance-card') !== false,
    'Acções críticas de caixa isoladas' => strpos($module, 'sg-extracts-cash-card') !== false,
    'Resumo de fecho moderno' => strpos($module, 'sg-extracts-close-summary') !== false,
    'Pesquisa de aluno táctil' => strpos($module, 'sg-student-search-card') !== false,
    'Perfil do aluno redesenhado' => strpos($module, 'sg-student-profile-card') !== false,
    'Tabela mobile com data-label Data/Hora' => strpos($module, 'data-label="<?php echo $modo_aluno ? \'Data\' : ($rel_periodo === \'diario\' ? \'Hora\' : \'Data/Hora\'); ?>"') !== false,
    'Tabela mobile com data-label Valor' => strpos($module, 'data-label="Valor"') !== false,
    'Bulk bar acessível' => strpos($module, 'id="sige-bulk-bar" aria-live="polite" aria-hidden="true"') !== false,
    'Modais com aria-modal' => strpos($module, "setAttribute('aria-modal', 'true')") !== false,
    'Body lock nos modais' => strpos($module, 'sg-extracts-modal-open') !== false,
    'Estado vazio accionável' => strpos($module, 'sg-empty-actions') !== false,
    'Banner antigo duplicado removido' => strpos($module, '<!-- CLOSED BANNER -->') === false,
];
foreach ($checks as $name => $ok) {
    if (!$ok) {
        $errors[] = "Falhou: {$name}";
    }
}
$lintTargets = [$files['main'], $files['module'], $files['module']];
foreach (array_unique($lintTargets) as $path) {
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
echo "SMOKE OK - financeiro-extratos UX/UI v12.11.9.85 validado." . PHP_EOL;
