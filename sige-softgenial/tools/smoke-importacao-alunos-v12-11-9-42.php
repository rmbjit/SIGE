<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test - SIGE SoftGenial v12.11.9.42
 * Confirma que o hotfix definitivo do scroll do modal de importação está no bloco visual principal.
 */
$root = dirname(__DIR__);
$file = $root . '/admin/academic/alunos_lista.php';
if (!is_file($file)) {
    fwrite(STDERR, "Ficheiro alunos_lista.php não encontrado.\n");
    exit(1);
}
$src = file_get_contents($file);
$required = [
    'v12.11.9.42 - Hotfix definitivo: scroll real do modal de importação de estudantes',
    '#modal-import-alunos.sige-import-modal-pro .sige-modal-content',
    'height:calc(100dvh - 24px)!important',
    '#modal-import-alunos.sige-import-modal-pro #form-import-alunos',
    '#modal-import-alunos.sige-import-modal-pro .sige-modal-body',
    'overscroll-behavior:contain!important',
    '#modal-import-alunos.sige-import-modal-pro .sige-import-actions',
    'box-shadow:0 -16px 34px rgba(15,23,42,.08)!important',
    'sige-aluno-modal-open',
];
foreach ($required as $needle) {
    if (strpos($src, $needle) === false) {
        fwrite(STDERR, "Selector/trecho obrigatório ausente: {$needle}\n");
        exit(1);
    }
}
$lineHotfix = substr_count(substr($src, 0, strpos($src, 'v12.11.9.42 - Hotfix definitivo')), "\n") + 1;
$lineMainWrap = substr_count(substr($src, 0, strpos($src, '<div class="wrap sige-alunos-page">')), "\n") + 1;
if ($lineHotfix < 500 || $lineHotfix > $lineMainWrap) {
    fwrite(STDERR, "Hotfix v12.11.9.42 não parece estar no bloco visual principal antes do HTML do módulo. Linha: {$lineHotfix}\n");
    exit(1);
}
echo "Smoke v12.11.9.42 OK - scroll definitivo do modal de importação activo no bloco principal.\n";
