<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$errors = [];
$version = file_get_contents($root . '/sige-softgenial.php');
$build = json_decode(file_get_contents($root . '/BUILD.json'), true);
$changelog = file_get_contents($root . '/CHANGELOG.md');
$cssPath = $root . '/assets/views/financeiro-core-design-pro.css';
$jsPath = $root . '/assets/views/financeiro-core-design-pro.js';
$css = is_file($cssPath) ? file_get_contents($cssPath) : '';
$js = is_file($jsPath) ? file_get_contents($jsPath) : '';

// v12.15.24: gate generalizado. Deixa de exigir que a versao corrente seja
// 12.15.12 (esse hardcode tornava-o vermelho em qualquer release posterior).
// Passa a verificar que a funcionalidade Financeiro Core Design PRO, entregue
// na 12.15.12, continua PRESENTE e intacta: entrada historica no CHANGELOG,
// assets, documentos e marcadores no codigo. O conteudo financeiro nao muda.
if (strpos($changelog, 'v12.15.12 - Design System PRO: Financeiro Core') === false) $errors[] = 'CHANGELOG principal sem a entrada historica v12.15.12.';
foreach ([$cssPath, $jsPath] as $path) if (!is_file($path)) $errors[] = 'Asset ausente: ' . basename($path);
$docs = [
    'docs/governance/PHASE_CHARTER-v12.15.12-financeiro-core-design-pro.md',
    'docs/design-system/FINANCEIRO_CORE_IMPLEMENTATION-v12.15.12.md',
    'docs/design-system/FINANCEIRO_CORE_PHP_BASELINE-v12.15.12.json',
    'docs/traceability/MATRIZ_RASTREABILIDADE-v12.15.12-financeiro-core-design-pro.md',
    'docs/qa/QA-v12.15.12-financeiro-core-design-pro.md',
    'docs/rediagnostico/REDIAGNOSTICO-v12.15.12-financeiro-core-design-pro.md',
    'docs/migration/MIGRACAO_ROLLBACK-v12.15.12-financeiro-core-design-pro.md',
    'docs/changelog/CHANGELOG-v12-15-12-financeiro-core-design-pro.txt',
];
foreach ($docs as $doc) if (!is_file($root . '/' . $doc)) $errors[] = 'Documento obrigatório ausente: ' . $doc;
foreach (['sige_design_financeiro_core_v121512_enabled','Financeiro Core','nao altera PHP financeiro','overflow-x: auto','max-height: calc(100vh - 24px)'] as $needle) {
    if (strpos($css . file_get_contents($root . '/includes/ui-kit.php'), $needle) === false) $errors[] = 'Marcador obrigatório ausente: ' . $needle;
}
if (strpos($js, "data-sige-financeiro-core-design', '12.15.12'") === false) $errors[] = 'JS financeiro não marca versão no body.';
if ($errors) {
    fwrite(STDERR, "smoke-financeiro-core-design-pro-v12-15-12: FALHOU\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
echo "smoke-financeiro-core-design-pro-v12-15-12: OK - Financeiro Core visual, escopado e documentado.\n";
