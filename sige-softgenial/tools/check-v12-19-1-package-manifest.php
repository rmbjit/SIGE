<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$build = json_decode((string) file_get_contents($root . '/BUILD.json'), true);
if (!is_array($build)) {
    $errors[] = 'BUILD.json inválido.';
} else {
    if (($build['version'] ?? '') !== '12.19.1') $errors[] = 'BUILD version deve ser 12.19.1.';
    if (($build['source_package']['version'] ?? '') !== '12.19.0') $errors[] = 'source_package.version deve ser 12.19.0.';
    $hotfix = $build['dashboard_profile_intelligence_copy_hotfix'] ?? [];
    if (!is_array($hotfix) || ($hotfix['status'] ?? '') !== 'enabled') $errors[] = 'hotfix de copy não está enabled no manifesto.';
    foreach (['issue','decision','scope','contracts'] as $key) {
        if (empty($hotfix[$key])) $errors[] = 'manifesto do hotfix incompleto: ' . $key;
    }
    foreach (['no_db_write','no_schema_change','no_permission_change','no_finance_formula_change','no_academic_formula_change','dashboard_core_preserved','copy_only'] as $key) {
        if (empty($hotfix['contracts'][$key])) $errors[] = 'contrato do hotfix falso/ausente: ' . $key;
    }
}
foreach ([
    'docs/changelog/CHANGELOG-v12-19-1.txt',
    'docs/qa/QA-v12.19.1-dashboard-copy-portugues-final-hotfix.md',
    'docs/traceability/MATRIZ_RASTREABILIDADE-v12.19.1-dashboard-copy-portugues-final-hotfix.md',
    'docs/migration/MIGRACAO_ROLLBACK-v12.19.1-dashboard-copy-portugues-final-hotfix.md',
] as $rel) {
    if (!is_file($root . '/' . $rel)) $errors[] = 'documento obrigatório em falta: ' . $rel;
}
$changelog = (string) file_get_contents($root . '/CHANGELOG.md');
if (strpos($changelog, '## v12.19.1 - Dashboard Copy & Português Final Hotfix') === false) {
    $errors[] = 'CHANGELOG.md sem entrada v12.19.1.';
}

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "ERRO: {$error}
");
    exit(1);
}

echo "v12.19.1 PACKAGE MANIFEST OK - manifesto, docs e changelog alinhados.
";
