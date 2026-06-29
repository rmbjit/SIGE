<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$view = file_get_contents(__DIR__ . '/../admin/academic/alunos_lista.php');
$core = file_get_contents(__DIR__ . '/../includes/aluno-fetch-ajax.php');
$main = file_get_contents(__DIR__ . '/../sige-softgenial.php');
$tests = [
    ['version bumped', strpos($main, '12.11.9.45') !== false],
    ['direct final confirmation', strpos($view, 'o botão "Confirmar e gravar" já é a confirmação final') !== false && strpos($view, "jQuery('#form-import-alunos').trigger('submit');") !== false],
    ['second hidden confirmation removed from import confirm', strpos($view, "Confirma que deseja gravar os alunos importáveis desta lista?") === false],
    ['visible stage 2 feedback', strpos($view, 'sige-import-processing-note') !== false && strpos($view, 'Etapa 2 em curso') !== false],
    ['popup above import modal', strpos($view, 'z-index:170000!important') !== false],
    ['two-step backend preserved', strpos($core, 'sige_import_alunos_preview_hash') !== false && strpos($core, "['preview', 'confirm']") !== false],
];
$failed = 0;
foreach ($tests as [$name, $ok]) {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$ok) $failed++;
}
if ($failed) exit(1);
