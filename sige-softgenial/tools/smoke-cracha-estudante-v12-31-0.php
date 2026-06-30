<?php
/**
 * Smoke v12.31.0 - Cracha do estudante: construtor unico + robustez de impressao.
 *
 * Salvaguarda de codigo-fonte (a logica e JS dentro do view; fixamos os
 * invariantes da correccao para nao regredir):
 *  - printSingleCard e printBatchCards delegam no MESMO construtor (sem duplicacao).
 *  - Todos os campos dinamicos sao escapados (sigeAlunoEscapeHtml).
 *  - QR cai para o numero de processo legivel quando nao pode ser gerado.
 *  - A impressao espera o carregamento das imagens e trata popup bloqueado.
 *  - O payload 'cards' do servidor continua a trazer turma (classe/turma_nome).
 *
 * Uso: php tools/smoke-cracha-estudante-v12-31-0.php
 */

$root  = dirname(__DIR__);
$fails = [];
function _p(&$a, $c, $m) { $a[] = [(bool)$c, $m]; }

$get  = fn($rel) => (string) @file_get_contents($root . '/' . $rel);
$view = $get('admin/academic/alunos_lista.php');
$ajax = $get('includes/aluno-fetch-ajax.php');
$main = $get('sige-softgenial.php');
$build = json_decode($get('BUILD.json'), true);

// --- Construtor unico (cura da duplicacao) ---
_p($fails, substr_count($view, 'function sigeCardMarkup(') === 1, 'Existe um unico construtor de cartao (sigeCardMarkup)');
_p($fails, substr_count($view, 'function sigeCardStyles(') === 1, 'Existe um unico bloco de estilos (sigeCardStyles)');
_p($fails, substr_count($view, 'function sigeCardsDocument(') === 1, 'Existe um unico montador de documento (sigeCardsDocument)');
_p($fails, strpos($view, 'sigeCardsDocument(visibleStudents, sigeCardCtx(), true)') !== false, 'printBatchCards delega no construtor unico (lote)');
_p($fails, strpos($view, 'sigeCardsDocument([a || {}], sigeCardCtx(), false)') !== false, 'printSingleCard delega no construtor unico (individual)');
// O CSS do cartao deixou de estar duplicado: 'card-photo-wrapper' aparece so na definicao de estilos.
_p($fails, substr_count($view, '.card-photo-wrapper { width: 90px;') === 1, 'CSS do cartao nao esta duplicado (uma so definicao)');

// --- Escape de todos os campos ---
_p($fails, strpos($view, 'var esc = sigeAlunoEscapeHtml;') !== false, 'Construtor usa o escapador');
_p($fails, strpos($view, 'esc((a && a.nome_completo)') !== false, 'Nome do aluno e escapado');
_p($fails, strpos($view, 'esc(ctx.escolaNome)') !== false, 'Nome da escola e escapado');
_p($fails, strpos($view, 'esc(foto)') !== false, 'URL da foto e escapada');

// --- QR com fallback de texto ---
_p($fails, strpos($view, "return '';") !== false && strpos($view, 'R0lGODlhAQABAAAA') === false, 'sigeQrDataUri devolve vazio em falha (sem pixel transparente)');
_p($fails, strpos($view, 'class="qr-fallback"') !== false, 'Fallback de texto do QR presente');
_p($fails, strpos($view, 'var qrCell = qr ?') !== false, 'Cartao escolhe QR ou numero de processo legivel');

// --- Impressao robusta ---
_p($fails, strpos($view, 'function sigePrintCardsWindow(') !== false, 'Existe helper de impressao robusta');
_p($fails, strpos($view, "addEventListener('load'") !== false, 'Impressao espera o carregamento das imagens');
_p($fails, strpos($view, 'Pop-up bloqueado') !== false, 'Popup bloqueado e tratado com aviso');
_p($fails, strpos($view, 'setTimeout(go, 6000)') !== false, 'Salvaguarda de tempo para nao prender a impressao');
_p($fails, strpos($view, 'setTimeout(function() { w.focus(); w.print(); }, 1500)') === false && strpos($view, 'setTimeout(function(){ w.focus(); w.print(); }, 1500)') === false, 'Removidos os setTimeout(1500) fixos antigos');

// --- Nao-regressao: o payload 'cards' do servidor traz turma ---
_p($fails, strpos($ajax, "'classe' => (string)(\$a->classe ?? '')") !== false, "Payload 'cards' inclui classe");
_p($fails, strpos($ajax, "'turma_nome' => (string)(\$a->turma_nome ?? '')") !== false, "Payload 'cards' inclui turma_nome");

// --- Versao ---
_p($fails, strpos($main, 'Version: 12.31.0') !== false, 'Header em 12.31.0');
_p($fails, strpos($main, "define('SIGE_VERSION', '12.31.0')") !== false, 'SIGE_VERSION em 12.31.0');
_p($fails, is_array($build) && ($build['version'] ?? '') === '12.31.0', 'BUILD.json em 12.31.0');

$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) { echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n"; }
echo "\n";
if ($erros) { echo 'SMOKE CRACHA-ESTUDANTE FALHOU: ' . count($erros) . " verificacao(oes).\n"; exit(1); }
echo "SMOKE CRACHA-ESTUDANTE OK - construtor unico, escape, fallback de QR e impressao robusta.\n";
exit(0);
