<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.15.19 - Gate do contrato dos separadores do modal de aluno.
 *
 * Contexto: o refactor de CSP trocou os onclick por data-sige-act. O dispatcher
 * declarativo (assets/sige-ui.js) chama uma accao sem args como fn(elemento). O
 * override de switchTab tinha ficado com a assinatura antiga (event, tabId) e lia
 * event.currentTarget/target num elemento (undefined), caindo sempre no primeiro
 * separador: clicar 'Encarregados'/'Saude' voltava a 'Dados gerais'. Nenhum gate
 * cobria isto. Este gate fixa o contrato para a regressao nao voltar:
 *  (a) os separadores chamam a accao pelo dispatcher (data-sige-act="switchTab");
 *  (b) o override de switchTab resolve o separador a partir do elemento recebido
 *      (trata nodeType === 1 e le o data-tab via closest('.sige-tab'));
 *  (c) a linha exacta do bug antigo nao existe;
 *  (d) activateTab valida o id contra a lista de separadores conhecida.
 */
$root = dirname(__DIR__);
$errors = [];

$viewPath = $root . '/admin/academic/alunos_lista.php';
$uiPath   = $root . '/assets/sige-ui.js';

foreach ([$viewPath => 'admin/academic/alunos_lista.php', $uiPath => 'assets/sige-ui.js'] as $path => $label) {
    if (!is_file($path)) {
        fwrite(STDERR, "check-alunos-modal-tabs-v12-15-19: FALHOU\n- Ficheiro em falta: {$label}\n");
        exit(1);
    }
}

$view = (string) file_get_contents($viewPath);
$ui   = (string) file_get_contents($uiPath);

// (a) Os separadores chamam switchTab pelo dispatcher declarativo.
if (substr_count($view, 'data-sige-act="switchTab"') < 1) {
    $errors[] = 'Os separadores do modal nao usam data-sige-act="switchTab".';
}

// O dispatcher chama uma accao sem args/noargs como fn(elemento): garante que a
// convencao que o gate assume continua a ser a verdadeira em sige-ui.js.
if (strpos($ui, "fn(alvo)") === false) {
    $errors[] = 'O dispatcher declarativo deixou de chamar fn(elemento) para accoes sem args (contrato mudou).';
}

// (b) O override de switchTab resolve o separador a partir do elemento recebido.
if (strpos($view, 'arg.nodeType === 1') === false) {
    $errors[] = 'switchTab nao trata o caso de receber o elemento do separador (nodeType === 1).';
}
if (strpos($view, "closest('.sige-tab').attr('data-tab')") === false) {
    $errors[] = 'switchTab nao resolve o data-tab do separador clicado via closest(.sige-tab).';
}

// (c) A linha exacta do bug antigo nao pode reaparecer.
if (strpos($view, 'var source = event && (event.currentTarget || event.target)') !== false) {
    $errors[] = 'Regressao: switchTab voltou a ler event.currentTarget/target de um elemento (cai sempre no primeiro separador).';
}

// (d) activateTab valida o id contra a lista conhecida de separadores.
if (strpos($view, 'if (tabs.indexOf(tabId) < 0) tabId = tabs[0];') === false) {
    $errors[] = 'activateTab nao valida o separador alvo contra a lista conhecida.';
}

if ($errors) {
    fwrite(STDERR, "check-alunos-modal-tabs-v12-15-19: FALHOU\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
printf("check-alunos-modal-tabs-v12-15-19: OK - separadores do modal via dispatcher, switchTab resolve o separador clicado a partir do elemento, sem a regressao da assinatura antiga.\n");
