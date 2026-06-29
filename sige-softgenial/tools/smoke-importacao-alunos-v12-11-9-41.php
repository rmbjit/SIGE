<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test - SIGE SoftGenial v12.11.9.41
 * Confirma a presença dos selectores críticos do hotfix de scroll do modal de importação.
 */
$root = dirname(__DIR__);
$file = $root . '/admin/academic/alunos_lista.php';
if (!is_file($file)) {
    fwrite(STDERR, "Ficheiro alunos_lista.php não encontrado.
");
    exit(1);
}
$src = file_get_contents($file);
$required = [
    'v12.11.9.41 - Hotfix scroll modal de importação de estudantes',
    '.sige-import-modal-pro #form-import-alunos',
    '.sige-import-modal-pro .sige-modal-body',
    'overflow-y:auto!important',
    '.sige-import-modal-pro .sige-import-actions',
    'sige-aluno-modal-open',
];
foreach ($required as $needle) {
    if (strpos($src, $needle) === false) {
        fwrite(STDERR, "Selector/trecho obrigatório ausente: {$needle}
");
        exit(1);
    }
}
echo "Smoke v12.11.9.41 OK - scroll interno do modal de importação protegido.
";
