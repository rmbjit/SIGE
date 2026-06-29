<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$build = json_decode((string)file_get_contents($root . '/BUILD.json'), true);
if (!is_array($build)) {
    $errors[] = 'BUILD.json invalido.';
} else {
    if (version_compare((string)($build['version'] ?? '0'), '12.17.0', '<')) $errors[] = 'BUILD version deve ser 12.17.0 ou superior.';
    if (($build['baseline_preservation']['baseline_version'] ?? '') !== '12.16.2') $errors[] = 'baseline_preservation.baseline_version deve ser 12.16.2.';
    if (($build['alunos_performance_contract']['status'] ?? '') !== 'enabled') $errors[] = 'alunos_performance_contract.status deve ser enabled.';
    foreach (['list_select','export_select','card_payload','deferred_vendor_scripts'] as $key) {
        if (empty($build['alunos_performance_contract'][$key])) $errors[] = 'contrato de alunos incompleto: ' . $key;
    }
}

$requiredDocs = [
    'docs/governance/PHASE_CHARTER-v12.17.0-alunos-performance-modularization-contract.md',
    'docs/qa/QA-v12.17.0-alunos-performance-modularization-contract.md',
    'docs/traceability/MATRIZ_RASTREABILIDADE-v12.17.0-alunos-performance-modularization-contract.md',
    'docs/migration/MIGRACAO_ROLLBACK-v12.17.0-alunos-performance-modularization-contract.md',
    'docs/changelog/CHANGELOG-v12-17-0.txt',
];
foreach ($requiredDocs as $rel) {
    if (!is_file($root . '/' . $rel)) $errors[] = 'documento obrigatorio em falta: ' . $rel;
}

$changelog = (string)file_get_contents($root . '/CHANGELOG.md');
if (strpos($changelog, '## v12.17.0 - Alunos Performance & Modularization Contract') === false) {
    $errors[] = 'CHANGELOG.md sem entrada v12.17.0.';
}

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "ERRO: {$error}\n");
    exit(1);
}

echo "v12.17.0 PACKAGE MANIFEST OK - manifesto, docs e changelog alinhados.\n";
