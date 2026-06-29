<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$build = json_decode((string) file_get_contents($root . '/BUILD.json'), true);
if (!is_array($build)) {
    $errors[] = 'BUILD.json invalido.';
} else {
    if (version_compare((string)($build['version'] ?? '0'), '12.17.1', '<')) $errors[] = 'BUILD version deve ser 12.17.1 ou superior.';
    if (version_compare((string)($build['source_package']['version'] ?? '0'), '12.17.0', '<')) $errors[] = 'source_package.version deve ser 12.17.0 ou superior.';
    if (($build['alunos_mobile_header_hotfix']['status'] ?? '') !== 'enabled') $errors[] = 'hotfix mobile header nao esta enabled.';
    foreach (['main_view','css_contract','mobile_scope','year_chip_mobile','avatar_column'] as $key) {
        if (empty($build['alunos_mobile_header_hotfix'][$key])) $errors[] = 'contrato mobile incompleto: ' . $key;
    }
}

$requiredDocs = [
    'docs/governance/PHASE_CHARTER-v12.17.1-alunos-mobile-header-hotfix.md',
    'docs/qa/QA-v12.17.1-alunos-mobile-header-hotfix.md',
    'docs/traceability/MATRIZ_RASTREABILIDADE-v12.17.1-alunos-mobile-header-hotfix.md',
    'docs/migration/MIGRACAO_ROLLBACK-v12.17.1-alunos-mobile-header-hotfix.md',
    'docs/changelog/CHANGELOG-v12-17-1.txt',
];
foreach ($requiredDocs as $rel) {
    if (!is_file($root . '/' . $rel)) $errors[] = 'documento obrigatorio em falta: ' . $rel;
}

$changelog = (string) file_get_contents($root . '/CHANGELOG.md');
if (strpos($changelog, '## v12.17.1 - Alunos Mobile Header Hotfix') === false) {
    $errors[] = 'CHANGELOG.md sem entrada v12.17.1.';
}

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "ERRO: {$error}\n");
    exit(1);
}

echo "v12.17.1 PACKAGE MANIFEST OK - manifesto, docs e changelog alinhados.\n";
