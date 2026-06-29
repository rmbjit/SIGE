<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$manifestFile = $root . '/docs/design-system/FINANCEIRO_CORE_PHP_BASELINE-v12.15.12.json';
if (!is_file($manifestFile)) {
    fwrite(STDERR, "check-financeiro-formula-integrity-v12-15-12: FALHOU - baseline ausente.\n");
    exit(1);
}
$manifest = json_decode(file_get_contents($manifestFile), true);
$errors = [];
foreach (($manifest['files'] ?? []) as $file => $expected) {
    $path = $root . '/' . $file;
    if (!is_file($path)) {
        $errors[] = "Ficheiro protegido ausente: {$file}";
        continue;
    }
    $actual = hash_file('sha256', $path);
    if (!hash_equals((string)$expected, (string)$actual)) {
        $errors[] = "Ficheiro financeiro protegido alterado: {$file}";
    }
}
if (count($manifest['files'] ?? []) < 20) $errors[] = 'Baseline financeira tem cobertura insuficiente.';
if ($errors) {
    fwrite(STDERR, "check-financeiro-formula-integrity-v12-15-12: FALHOU\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
echo "check-financeiro-formula-integrity-v12-15-12: OK - PHP financeiro/Finance Score/formulas protegidos por hash.\n";
