<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$protected = [
    'includes/finance-core.php' => 'bcb51dcfaf93f8c45f378b825d734df2a3dea8dfe23e26b5364fc12fb5ad7266',
    'admin/finance/financeiro-pagamentos.php' => '3afab797c29cd261d25c53410ac482f267d043232b98f31c02c323caebd9a823',
    'admin/finance/financeiro-extratos.php' => 'e251917d82564372c5a779152ef6a9640754a394e95298396ac48bf53eb293d1',
    'admin/system/dashboard-view.php' => '480999e77c77f842b002707fca2665ed39221e9a264326662710c20b737f138c',
    'includes/permissions-layer.php' => '3661ef0d427790f413b2148092bd2f340c4c064a9913d8eb1264301cd5728c47',
    'includes/security-kernel-rules.php' => '774436262ffab70665dcb61cca1f3c14494e7adde846d6b269665cae028d6869',
    'includes/admin-shell.php' => 'df0f6d8e97681bc95ece2e33a08fed73d980937d7cdc5724e4eb48ee74d446cb',
];
foreach ($protected as $rel => $expected) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) { $errors[] = 'ficheiro protegido em falta: ' . $rel; continue; }
    $actual = hash_file('sha256', $path);
    if ($actual !== $expected) $errors[] = 'hash protegido alterado em ' . $rel . ': ' . $actual;
}

$main = (string) file_get_contents($root . '/sige-softgenial.php');
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $main, $mV);
$currentVersion = $mV[1] ?? '0';
if (version_compare($currentVersion, '12.17.1', '<')) $errors[] = 'SIGE_VERSION inferior a 12.17.1.';
$build = json_decode((string) file_get_contents($root . '/BUILD.json'), true);
if (!is_array($build) || version_compare((string)($build['version'] ?? '0'), '12.17.1', '<')) $errors[] = 'BUILD.json inferior a 12.17.1.';

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "ERRO: {$error}\n");
    exit(1);
}

echo "v12.17.1 NO SENSITIVE REGRESSION OK - ficheiros sensiveis preservados.\n";
