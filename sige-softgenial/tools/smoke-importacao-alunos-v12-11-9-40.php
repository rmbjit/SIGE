<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$view = file_get_contents($root . '/admin/academic/alunos_lista.php');
$ajax = file_get_contents($root . '/includes/aluno-fetch-ajax.php');
$mig  = file_get_contents($root . '/includes/class-sige-migration.php');
$main = file_get_contents($root . '/sige-softgenial.php');
$build = file_get_contents($root . '/BUILD.json');

$checks = [
    ['version bumped', strpos($main, 'Version: 12.11.9.40') !== false && strpos($main, "SIGE_VERSION', '12.11.9.40") !== false],
    ['build json', strpos($build, '12.11.9.40') !== false && strpos($build, 'Histórico de Lotes') !== false],
    ['schema table', strpos($mig, 'sige_alunos_importacao_lotes') !== false && strpos($mig, "SCHEMA_VERSION = '20260605.1'") !== false],
    ['history ajax endpoint', strpos($ajax, "wp_ajax_sige_listar_lotes_importacao_alunos") !== false && strpos($ajax, 'sige_ajax_listar_lotes_importacao_alunos') !== false],
    ['history persistence', strpos($ajax, 'sige_import_alunos_save_batch_history') !== false && strpos($ajax, 'sige_import_alunos_mark_batch_annulled') !== false],
    ['history fallback', strpos($ajax, 'sige_import_alunos_list_legacy_batches') !== false && strpos($ajax, "origem' => 'reconstruido'") !== false],
    ['history UI panel', strpos($view, 'sige-import-history-panel') !== false && strpos($view, 'Histórico de lotes importados') !== false],
    ['history JS loader', strpos($view, 'carregarHistoricoLotesImportacao') !== false && strpos($view, "action: 'sige_listar_lotes_importacao_alunos'") !== false],
    ['anular from history', strpos($view, 'seleccionarLoteImportacao') !== false && strpos($view, 'anularLoteImportacaoAlunos') !== false],
];

$fail = 0;
foreach ($checks as [$label, $ok]) {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $fail++;
}
exit($fail ? 1 : 0);
