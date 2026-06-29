<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$build = json_decode((string) file_get_contents($root . '/BUILD.json'), true);
if (!is_array($build)) {
    $errors[] = 'BUILD.json invalido.';
} else {
    if (version_compare((string)($build['version'] ?? '0'), '12.19.0', '<')) $errors[] = 'BUILD version deve ser >= 12.19.0.';
    if (($build['version'] ?? '') === '12.19.0') {
        if (($build['source_package']['version'] ?? '') !== '12.18.0') $errors[] = 'source_package.version deve ser 12.18.0.';
        if (($build['source_package']['validated_in_staging_by_user'] ?? null) !== true) $errors[] = 'source_package deve reflectir baseline aprovado.';
    }
    $di = $build['dashboard_profile_intelligence'] ?? [];
    if (!is_array($di) || ($di['status'] ?? '') !== 'enabled') $errors[] = 'dashboard_profile_intelligence nao esta enabled.';
    foreach (['include','css','js','render_strategy','storage','source_of_truth','profiles','contracts'] as $key) {
        if (empty($di[$key])) $errors[] = 'manifesto dashboard intelligence incompleto: ' . $key;
    }
    if (isset($di['profiles']) && is_array($di['profiles']) && count($di['profiles']) < 6) {
        $errors[] = 'profiles deve cobrir pelo menos 6 perfis operacionais.';
    }
    foreach (['no_db_write','no_schema_change','no_permission_change','no_finance_formula_change','no_academic_formula_change','dashboard_core_preserved','admin_shell_preserved','uses_filtered_actions_only'] as $key) {
        if (empty($di['contracts'][$key])) $errors[] = 'contrato dashboard intelligence falso/ausente: ' . $key;
    }
}

$requiredDocs = [
    'docs/governance/PHASE_CHARTER-v12.19.0-dashboard-executivo-inteligencia-operacional-por-perfil.md',
    'docs/qa/QA-v12.19.0-dashboard-executivo-inteligencia-operacional-por-perfil.md',
    'docs/traceability/MATRIZ_RASTREABILIDADE-v12.19.0-dashboard-executivo-inteligencia-operacional-por-perfil.md',
    'docs/migration/MIGRACAO_ROLLBACK-v12.19.0-dashboard-executivo-inteligencia-operacional-por-perfil.md',
    'docs/changelog/CHANGELOG-v12-19-0.txt',
];
foreach ($requiredDocs as $rel) {
    if (!is_file($root . '/' . $rel)) $errors[] = 'documento obrigatorio em falta: ' . $rel;
}

$changelog = (string) file_get_contents($root . '/CHANGELOG.md');
if (strpos($changelog, '## v12.19.0 - Dashboard Executivo & Inteligencia Operacional por Perfil') === false) {
    $errors[] = 'CHANGELOG.md sem entrada v12.19.0.';
}

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "ERRO: {$error}\n");
    exit(1);
}

echo "v12.19.0 PACKAGE MANIFEST OK - manifesto, docs e changelog alinhados.\n";
