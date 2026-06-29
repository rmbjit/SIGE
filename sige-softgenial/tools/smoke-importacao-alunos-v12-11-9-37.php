<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$base = dirname(__DIR__);
$view = file_get_contents($base . '/admin/academic/alunos_lista.php');
$ajax = file_get_contents($base . '/includes/aluno-fetch-ajax.php');
$main = file_get_contents($base . '/sige-softgenial.php');
$build = json_decode(file_get_contents($base . '/BUILD.json'), true);
$checks = [
    ['main version header', strpos($main, 'Version: 12.11.9.37') !== false],
    ['main SIGE_VERSION', strpos($main, "define('SIGE_VERSION', '12.11.9.37');") !== false],
    ['build version', isset($build['version']) && $build['version'] === '12.11.9.37'],
    ['hero import button', strpos($view, 'abrirImportarAlunos()') !== false && strpos($view, 'Importar Lista') !== false],
    ['import modal', strpos($view, 'modal-import-alunos') !== false],
    ['csv template download', strpos($view, 'baixarModeloImportacaoAlunos') !== false && strpos($view, 'modelo-importacao-alunos-softgenial.csv') !== false],
    ['ajax submit action', strpos($view, 'sige_importar_alunos_csv') !== false],
    ['result renderer', strpos($view, 'renderImportacaoAlunosResultado') !== false],
    ['ajax endpoint registered', strpos($ajax, "add_action('wp_ajax_sige_importar_alunos_csv'") !== false],
    ['manage capability check', strpos($ajax, 'sige_alunos_user_can_manage') !== false],
    ['parent contacts validation', strpos($ajax, 'nome_pai em falta') !== false && strpos($ajax, 'telemovel_mae inválido') !== false],
    ['pending contacts mode', strpos($ajax, 'permitir_pendentes') !== false && strpos($ajax, 'pendentes de confirmação') !== false],
    ['phone normalization', strpos($ajax, 'sige_import_alunos_normalize_phone') !== false && strpos($ajax, '/^(82|83|84|85|86|87)') !== false],
    ['duplicate detection', strpos($ajax, 'número de processo já existe') !== false && strpos($ajax, 'mesmo nome e data de nascimento') !== false],
    ['matricula insert', strpos($ajax, '$tbl_matriculas') !== false && strpos($ajax, '$wpdb->insert($tbl_matriculas') !== false],
];
$failed = [];
foreach ($checks as $c) {
    if (!$c[1]) $failed[] = $c[0];
}
if ($failed) {
    fwrite(STDERR, "FAILED: " . implode(', ', $failed) . "\n");
    exit(1);
}
echo "OK: smoke importacao alunos v12.11.9.37\n";
