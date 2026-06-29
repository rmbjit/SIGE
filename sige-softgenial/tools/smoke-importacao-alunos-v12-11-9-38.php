<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$base = dirname(__DIR__);
$view = file_get_contents($base . '/admin/academic/alunos_lista.php');
$ajax = file_get_contents($base . '/includes/aluno-fetch-ajax.php');
$main = file_get_contents($base . '/sige-softgenial.php');
$build = json_decode(file_get_contents($base . '/BUILD.json'), true);
$checks = [
    ['main version header', strpos($main, 'Version: 12.11.9.38') !== false],
    ['main SIGE_VERSION', strpos($main, "define('SIGE_VERSION', '12.11.9.38');") !== false],
    ['build version', isset($build['version']) && $build['version'] === '12.11.9.38'],
    ['hero import button preserved', strpos($view, 'abrirImportarAlunos()') !== false && strpos($view, 'Importar Lista') !== false],
    ['two-step modal copy', strpos($view, 'pré-validação') !== false && strpos($view, 'confirmação final') !== false],
    ['hidden mode fields', strpos($view, 'sige_import_mode') !== false && strpos($view, 'sige_preview_hash') !== false],
    ['preview submit label', strpos($view, 'Pré-validar lista') !== false],
    ['confirm function', strpos($view, 'confirmarImportacaoAlunos') !== false && strpos($view, 'Confirmar e gravar') !== false],
    ['preview result renderer', strpos($view, 'Etapa 1 concluída') !== false && strpos($view, 'Etapa 2 concluída') !== false],
    ['ajax endpoint registered', strpos($ajax, "add_action('wp_ajax_sige_importar_alunos_csv'") !== false],
    ['mode validation', strpos($ajax, "['preview', 'confirm']") !== false && strpos($ajax, '$modo_importacao') !== false],
    ['secure preview hash', strpos($ajax, 'sige_import_alunos_preview_hash') !== false && strpos($ajax, 'hash_hmac') !== false && strpos($ajax, 'hash_equals') !== false],
    ['batch id', strpos($ajax, 'sige_import_alunos_generate_batch_id') !== false && strpos($ajax, 'SGIMP-') !== false],
    ['audit lote', strpos($ajax, 'alunos_importacao_csv_confirmada') !== false && strpos($ajax, 'lote_id') !== false],
    ['no direct save on preview', strpos($ajax, "\$modo_importacao === 'preview'") !== false && strpos($ajax, 'pronto para gravar') !== false],
    ['parent contacts validation preserved', strpos($ajax, 'nome_pai em falta') !== false && strpos($ajax, 'telemovel_mae inválido') !== false],
    ['duplicate detection preserved', strpos($ajax, 'número de processo já existe') !== false && strpos($ajax, 'mesmo nome e data de nascimento') !== false],
    ['matricula insert preserved', strpos($ajax, '$tbl_matriculas') !== false && strpos($ajax, '$wpdb->insert($tbl_matriculas') !== false],
];
$failed = [];
foreach ($checks as $c) {
    if (!$c[1]) $failed[] = $c[0];
}
if ($failed) {
    fwrite(STDERR, "FAILED: " . implode(', ', $failed) . "\n");
    exit(1);
}
echo "OK: smoke importacao alunos duas etapas v12.11.9.38\n";
