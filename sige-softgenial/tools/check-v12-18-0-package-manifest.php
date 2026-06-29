<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$build = json_decode((string) file_get_contents($root . '/BUILD.json'), true);
if (!is_array($build)) {
    $errors[] = 'BUILD.json invalido.';
} else {
    if (version_compare((string)($build['version'] ?? '0'), '12.18.0', '<')) $errors[] = 'BUILD version deve ser 12.18.0 ou superior.';
    if (($build['version'] ?? '') === '12.18.0') {
        if (version_compare((string)($build['source_package']['version'] ?? '0'), '12.17.1', '<')) $errors[] = 'source_package.version deve ser 12.17.1 ou superior.';
        if (($build['source_package']['validated_in_staging_by_user'] ?? null) !== true) $errors[] = 'source_package deve reflectir baseline aprovado.';
    }
    $ow = $build['operational_workflow_hardening'] ?? [];
    if (!is_array($ow) || ($ow['status'] ?? '') !== 'enabled') $errors[] = 'operational_workflow_hardening nao esta enabled.';
    foreach (['include','css','js','render_strategy','storage','catalog_views','contracts'] as $key) {
        if (empty($ow[$key])) $errors[] = 'manifesto operacional incompleto: ' . $key;
    }
    if (isset($ow['catalog_views']) && is_array($ow['catalog_views']) && count($ow['catalog_views']) < 12) {
        $errors[] = 'catalog_views deve cobrir pelo menos 12 views.';
    }
    foreach (['no_db_write','no_schema_change','no_permission_change','no_finance_formula_change','no_academic_formula_change','admin_shell_preserved'] as $key) {
        if (empty($ow['contracts'][$key])) $errors[] = 'contrato operacional falso/ausente: ' . $key;
    }
}

$requiredDocs = [
    'docs/governance/PHASE_CHARTER-v12.18.0-operational-ux-workflow-hardening.md',
    'docs/qa/QA-v12.18.0-operational-ux-workflow-hardening.md',
    'docs/traceability/MATRIZ_RASTREABILIDADE-v12.18.0-operational-ux-workflow-hardening.md',
    'docs/migration/MIGRACAO_ROLLBACK-v12.18.0-operational-ux-workflow-hardening.md',
    'docs/changelog/CHANGELOG-v12-18-0.txt',
];
foreach ($requiredDocs as $rel) {
    if (!is_file($root . '/' . $rel)) $errors[] = 'documento obrigatorio em falta: ' . $rel;
}

$changelog = (string) file_get_contents($root . '/CHANGELOG.md');
if (strpos($changelog, '## v12.18.0 - Operational UX & Workflow Hardening') === false) {
    $errors[] = 'CHANGELOG.md sem entrada v12.18.0.';
}

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "ERRO: {$error}\n");
    exit(1);
}

echo "v12.18.0 PACKAGE MANIFEST OK - manifesto, docs e changelog alinhados.\n";
