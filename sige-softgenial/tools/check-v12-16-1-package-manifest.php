<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$main = is_file($root . '/sige-softgenial.php') ? (string) file_get_contents($root . '/sige-softgenial.php') : '';
$build_raw = is_file($root . '/BUILD.json') ? (string) file_get_contents($root . '/BUILD.json') : '';
$build = $build_raw !== '' ? json_decode($build_raw, true) : null;

preg_match('/^\s*\*\s*Version:\s*([0-9.]+)/mi', $main, $mH);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $main, $mC);
$vH = $mH[1] ?? '';
$vC = $mC[1] ?? '';
$vB = is_array($build) ? (string)($build['version'] ?? '') : '';
if ($vH === '' || $vH !== $vC || $vC !== $vB || version_compare($vC, '12.16.1', '<')) {
    $errors[] = 'versao nao sincronizada ou inferior a 12.16.1: header=' . $vH . ' const=' . $vC . ' build=' . $vB;
}
if (!is_array($build)) {
    $errors[] = 'BUILD.json invalido.';
} else {
    $required = [
        ['source_package','version'],
        ['source_package','sha256'],
        ['runtime_evidence','browser_staging'],
        ['runtime_evidence','manual_staging_required'],
        ['qa_summary','php_lint'],
        ['qa_summary','gates'],
        ['qa_summary','release_gate'],
        ['qa_summary','zip_integrity'],
    ];
    foreach ($required as $path) {
        $cursor = $build;
        foreach ($path as $key) { $cursor = is_array($cursor) && array_key_exists($key, $cursor) ? $cursor[$key] : null; }
        if ($cursor === null || $cursor === '') $errors[] = 'BUILD.json sem campo: ' . implode('.', $path);
    }
    if (!in_array(($build['channel'] ?? ''), ['stable-hardening','stable-readiness','stable-performance','stable-hotfix','stable-ux-hardening','stable-dashboard-intelligence','stable-dashboard-copy-hotfix'], true)) $errors[] = 'BUILD channel inesperado.';
    if (($build['runtime_evidence']['browser_staging'] ?? '') !== 'prepared_not_executed_here') $errors[] = 'browser staging deve ser declarado como preparado, nao executado aqui.';
}

$required_files = [
    'docs/qa/QA_RESULTS-v12.16.1-runtime-evidence-technical-debt-closure.md' => ['PHP lint', 'Release gate', 'Run gates', 'ZIP integrity'],
    'docs/changelog/CHANGELOG-v12-16-1.txt' => ['Runtime Evidence', 'Checklist obrigatório'],
];
foreach ($required_files as $rel => $needles) {
    $content = is_file($root . '/' . $rel) ? (string) file_get_contents($root . '/' . $rel) : '';
    if ($content === '') { $errors[] = 'ficheiro de manifesto em falta: ' . $rel; continue; }
    foreach ($needles as $needle) if (strpos($content, $needle) === false) $errors[] = $rel . ' sem marcador: ' . $needle;
}

if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR, "ERRO: {$error}
"); }
    exit(1);
}

echo "v12.16.1 PACKAGE MANIFEST OK - versao, origem, QA, staging honesty e changelog sincronizados.
";
