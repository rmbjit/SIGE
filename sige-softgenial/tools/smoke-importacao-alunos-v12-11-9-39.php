<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$ajax = file_get_contents($root . '/includes/aluno-fetch-ajax.php');
$view = file_get_contents($root . '/admin/academic/alunos_lista.php');
$mig  = file_get_contents($root . '/includes/class-sige-migration.php');
$main = file_get_contents($root . '/sige-softgenial.php');
$build = file_get_contents($root . '/BUILD.json');

$checks = [
    ['version main', strpos($main, "Version: 12.11.9.39") !== false && strpos($main, "SIGE_VERSION', '12.11.9.39'") !== false],
    ['build id', strpos($build, 'sige-12.11.9.39-anulacao-lote-importacao-alunos-pro') !== false],
    ['ajax annul endpoint', strpos($ajax, "add_action('wp_ajax_sige_anular_lote_importacao_alunos'") !== false],
    ['annul function', strpos($ajax, 'function sige_ajax_anular_lote_importacao_alunos') !== false],
    ['batch metadata insert', strpos($ajax, "importacao_lote_id") !== false && strpos($ajax, "importado_por") !== false],
    ['dependency blockers', strpos($ajax, 'sige_import_alunos_batch_blockers') !== false && strpos($ajax, 'sige_fin_lancamentos') !== false && strpos($ajax, 'sige_notas') !== false && strpos($ajax, 'sige_acessos') !== false],
    ['transactional delete', strpos($ajax, "START TRANSACTION") !== false && strpos($ajax, "ROLLBACK") !== false && strpos($ajax, "COMMIT") !== false],
    ['audit blocked/anulado', strpos($ajax, 'alunos_importacao_lote_anulado') !== false && strpos($ajax, 'alunos_importacao_lote_anulacao_bloqueada') !== false],
    ['view annul panel', strpos($view, 'Anulação segura de lote') !== false && strpos($view, 'sige_lote_anular_id') !== false],
    ['view contextual button', strpos($view, 'Anular este lote') !== false && strpos($view, 'anularLoteImportacaoAlunos') !== false],
    ['migration columns', strpos($mig, 'importacao_lote_id VARCHAR(100)') !== false && strpos($mig, 'idx_importacao_lote') !== false],
];

$fail = 0;
foreach ($checks as $c) {
    echo ($c[1] ? "OK   " : "FAIL ") . $c[0] . PHP_EOL;
    if (!$c[1]) $fail++;
}
exit($fail ? 1 : 0);
