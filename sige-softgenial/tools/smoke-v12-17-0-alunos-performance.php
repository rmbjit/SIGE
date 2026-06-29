<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$helper = (string)file_get_contents($root . '/includes/alunos-performance-contract.php');
$view = (string)file_get_contents($root . '/admin/academic/alunos_lista.php');
$ajax = (string)file_get_contents($root . '/includes/aluno-fetch-ajax.php');

$requiredListFields = [
    'nome_completo','numero_processo','data_nascimento','genero','nome_pai','telemovel_pai',
    'nome_mae','telemovel_mae','whatsapp_notificacoes','doc_bi_url','doc_cert_url','bairro',
    'nacionalidade','contacto_emergencia_1','contacto_emergencia_2','rota_transporte_id'
];
foreach ($requiredListFields as $field) {
    if (strpos($helper, "'{$field}' =>") === false) $errors[] = 'campo essencial ausente do SELECT leve: ' . $field;
}

foreach (['alergias','condicoes_medicas','doc_bi_url','contacto_emergencia_1','contacto_emergencia_2'] as $sensitive) {
    $excelBlockPos = strpos($helper, 'function sige_alunos_perf_excel_fields');
    $cardsBlockPos = strpos($helper, 'function sige_alunos_perf_cards_fields');
    if ($excelBlockPos !== false && $cardsBlockPos !== false) {
        $excelBlock = substr($helper, $excelBlockPos, $cardsBlockPos - $excelBlockPos);
        if (strpos($excelBlock, "'{$sensitive}'") !== false) $errors[] = 'Excel export inclui campo sensivel desnecessario: ' . $sensitive;
    }
}

$payloadPos = strpos($helper, 'function sige_alunos_perf_card_document_payload');
if ($payloadPos !== false) {
    $payloadBlock = substr($helper, $payloadPos);
    foreach (['alergias','condicoes_medicas','contacto_emergencia_1','contacto_emergencia_2','doc_bi_url'] as $notAllowed) {
        if (strpos($payloadBlock, "'{$notAllowed}'") !== false) $errors[] = 'payload inline do card inclui campo sensivel: ' . $notAllowed;
    }
} else {
    $errors[] = 'funcao de payload minimo ausente.';
}

if (strpos($view, '_sigeTodosAlunosCache') === false || strpos($view, 'sigeGetTodosAlunos') === false) {
    $errors[] = 'cache on-demand de exportacao ausente.';
}
if (strpos($ajax, 'SELECT {$select_aluno}') === false) {
    $errors[] = 'exportacao nao usa variavel de SELECT controlado.';
}
if (strpos($ajax, 'ORDER BY t.classe ASC, t.nome ASC, a.nome_completo ASC') === false) {
    $errors[] = 'ordenacao operacional da lista/exportacao foi alterada.';
}

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "ERRO: {$error}\n");
    exit(1);
}

echo "v12.17.0 ALUNOS PERFORMANCE SMOKE OK - campos, cache e payloads preservados.\n";
