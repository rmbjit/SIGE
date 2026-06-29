<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$errors = [];
$versionFile = file_get_contents($root . '/sige-softgenial.php');
$build = json_decode(file_get_contents($root . '/BUILD.json'), true);
$changelog = file_get_contents($root . '/CHANGELOG.md');
$css = file_get_contents($root . '/assets/views/alunos-design-pro.css');
$js = file_get_contents($root . '/assets/views/alunos-design-pro.js');

$pluginVersion = '';
$constVersion = '';
if (preg_match('/Version:\s*([0-9.]+)/', $versionFile, $m)) $pluginVersion = $m[1];
if (preg_match('/define\(\'SIGE_VERSION\',\s*\'([0-9.]+)\'\)/', $versionFile, $m)) $constVersion = $m[1];
if ($pluginVersion === '' || $constVersion === '' || version_compare($pluginVersion, '12.15.11', '<') || version_compare($constVersion, '12.15.11', '<')) {
    $errors[] = 'Versao 12.15.11 ou superior nao sincronizada em sige-softgenial.php.';
}
if (version_compare((string)($build['version'] ?? ''), '12.15.11', '<')) $errors[] = 'BUILD.json nao aponta para 12.15.11 ou superior.';
if (strpos($changelog, 'v12.15.11 - Alunos Stability Recovery') === false) $errors[] = 'CHANGELOG principal sem entrada v12.15.11.';
$docs = [
    'docs/design-system/ALUNOS_STABILITY_RECOVERY-v12.15.11.md',
    'docs/changelog/CHANGELOG-v12-15-11-alunos-stability-recovery.txt',
    'docs/qa/QA-v12.15.11-alunos-stability-recovery.md',
    'docs/traceability/MATRIZ_RASTREABILIDADE-v12.15.11-alunos-stability-recovery.md',
    'docs/rediagnostico/REDIAGNOSTICO-v12.15.11-alunos-stability-recovery.md',
    'docs/migration/MIGRACAO_ROLLBACK-v12.15.11-alunos-stability-recovery.md',
    'docs/governance/PHASE_CHARTER-v12.15.11-alunos-stability-recovery.md'
];
foreach ($docs as $doc) if (!is_file($root . '/' . $doc)) $errors[] = 'Documento obrigatório ausente: ' . $doc;
$jsRequired = [
    'function syncMenu' => 'gestao do menu dos tres pontinhos',
    'sige-card-actions-open' => 'classe de card com menu aberto',
    'sige-alunos-actions-open' => 'classe no body quando menu aberto',
    "document.addEventListener('click'" => 'fecho por clique fora',
    "document.addEventListener('keydown'" => 'fecho por Escape'
];
foreach ($jsRequired as $needle => $label) if (strpos($js, $needle) === false) $errors[] = 'JS sem ' . $label . '.';
$cssRequired = [
    'sige-actions-menu' => 'menu de accoes',
    'grid-template-columns: 1fr !important' => 'menu vertical',
    '.sige-card-actions-open' => 'layer de card aberto',
    'sige-aluno-modal-open .sg-product-pro-shell .sg-app-content' => 'fix de modal/sidebar'
];
foreach ($cssRequired as $needle => $label) if (strpos($css, $needle) === false) $errors[] = 'CSS sem ' . $label . '.';
if ($errors) {
    fwrite(STDERR, "smoke-alunos-stability-recovery-v12-15-11: FALHOU\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
echo "smoke-alunos-stability-recovery-v12-15-11: OK - Alunos recuperado para base estavel, modal layering por CSS scoped.\n";
