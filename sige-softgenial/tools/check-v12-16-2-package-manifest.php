<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$read = static function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return is_file($p) ? (string) file_get_contents($p) : '';
};
$build = json_decode($read('BUILD.json'), true);
$main = $read('sige-softgenial.php');
preg_match('/^\s*\*\s*Version:\s*([0-9.]+)/mi', $main, $mH);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $main, $mC);
$vH = $mH[1] ?? '';
$v = $mC[1] ?? '';
$vB = is_array($build) ? (string)($build['version'] ?? '') : '';
if ($vH === '' || $vH !== $v || $v !== $vB || version_compare($v, '12.16.2', '<')) {
    $errors[] = 'versao nao sincronizada ou inferior ao baseline 12.16.2.';
}
if (is_array($build)) {
    if (!in_array(($build['channel'] ?? ''), ['stable-readiness','stable-performance','stable-hotfix','stable-ux-hardening','stable-dashboard-intelligence','stable-dashboard-copy-hotfix'], true)) $errors[] = 'channel deve pertencer a linha stable aprovada.';
    foreach ([
        ['baseline_preservation','regression_matrix'],
        ['baseline_preservation','post_install_checklist'],
        ['baseline_preservation','protected_hashes_gate'],
        ['qa_summary','browser_staging'],
    ] as $path) {
        $cursor = $build;
        foreach ($path as $key) $cursor = is_array($cursor) && array_key_exists($key, $cursor) ? $cursor[$key] : null;
        if ($cursor === null || $cursor === '') $errors[] = 'BUILD.json sem campo: ' . implode('.', $path);
    }
}
$required_files = [
    'docs/changelog/CHANGELOG-v12-16-2.txt' => ['Baseline Preservation','Checklist obrigatorio','Nao alterado'],
    'docs/qa/QA_RESULTS-v12.16.2-baseline-preservation-operational-readiness.md' => ['PHP lint','Release gate','Run gates','ZIP integrity'],
    'docs/traceability/MATRIZ_RASTREABILIDADE-v12.16.2-baseline-preservation-operational-readiness.md' => ['BP-16-02-01','ST-16-02-08'],
];
foreach ($required_files as $rel => $needles) {
    $content = $read($rel);
    if ($content === '') { $errors[] = 'ficheiro de manifesto em falta: ' . $rel; continue; }
    foreach ($needles as $needle) if (strpos($content, $needle) === false) $errors[] = $rel . ' sem marcador: ' . $needle;
}
if (strpos($read('CHANGELOG.md'), '## v12.16.2') === false) $errors[] = 'CHANGELOG.md sem entrada v12.16.2.';
if (strpos($read('tools/run-gates.php'), 'v12.16.2 Baseline Preservation Contract') === false) $errors[] = 'run-gates sem gate v12.16.2.';

if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR, "ERRO: {$error}\n"); }
    exit(1);
}

echo "v12.16.2 PACKAGE MANIFEST OK - versao, manifesto, changelog e run-gates sincronizados.\n";
