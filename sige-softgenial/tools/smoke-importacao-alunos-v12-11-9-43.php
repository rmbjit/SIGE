<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$view  = file_get_contents($root . '/admin/academic/alunos_lista.php');
$ajax  = file_get_contents($root . '/includes/aluno-fetch-ajax.php');
$main  = file_get_contents($root . '/sige-softgenial.php');
$build = file_get_contents($root . '/BUILD.json');
$changelog = file_get_contents($root . '/CHANGELOG-v12-11-9-43-modelo-excel-controlado-importacao-alunos-pro.txt');

$checks = [
    ['version bumped', strpos($main, 'Version: 12.11.9.43') !== false && strpos($main, "SIGE_VERSION', '12.11.9.43") !== false],
    ['build json', strpos($build, '12.11.9.43') !== false && strpos($build, 'Modelo Excel Controlado') !== false],
    ['changelog present', strpos($changelog, 'Modelo Excel Controlado') !== false && strpos($changelog, 'listas controladas') !== false],
    ['download endpoint', strpos($ajax, "admin_post_sige_download_modelo_importacao_alunos_xlsx") !== false && strpos($ajax, 'sige_admin_post_download_modelo_importacao_alunos_xlsx') !== false],
    ['xlsx builder', strpos($ajax, 'sige_import_alunos_build_xlsx_template') !== false && strpos($ajax, 'sige_import_alunos_zip_store') !== false],
    ['xlsx parser', strpos($ajax, 'sige_import_alunos_parse_xlsx') !== false && strpos($ajax, 'sige_import_alunos_xlsx_rows_from_xml') !== false],
    ['accept xlsx upload', strpos($ajax, "['xlsx','csv','txt']") !== false && strpos($view, 'accept=".xlsx,.csv,.txt') !== false],
    ['controlled lists', strpos($ajax, 'SIGE_GENEROS') !== false && strpos($ajax, 'SIGE_CLASSES') !== false && strpos($ajax, 'SIGE_TURMAS') !== false],
    ['view xlsx button', strpos($view, 'Baixar modelo Excel (.xlsx)') !== false && strpos($view, 'sige_download_modelo_importacao_alunos_xlsx') !== false],
    ['csv fallback kept', strpos($view, 'Baixar modelo CSV') !== false && strpos($view, 'baixarModeloImportacaoAlunos') !== false],
    ['two-step preserved', strpos($view, 'Pré-validar lista') !== false && strpos($view, 'Confirmar e gravar') !== false && strpos($ajax, 'sige_preview_hash') !== false],
];

$fail = 0;
foreach ($checks as [$label, $ok]) {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $fail++;
}
exit($fail ? 1 : 0);
