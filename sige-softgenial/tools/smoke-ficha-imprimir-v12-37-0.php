<?php
/**
 * Smoke v12.37.0 - Imprimir/Exportar (PDF) a Ficha do Colaborador.
 *
 * (1) LIGAÇÃO: botão + funções + guarda do último registo + reutilização do
 *     utilitário de impressão e do nonce vivo + nome da escola exposto.
 * (2) FRONTEIRA DE TOKENS: o documento autónomo é construído com VALORES de
 *     tokens lidos da página (getComputedStyle) e var(--token); o construtor
 *     não introduz cores mágicas (hex) no .php.
 *
 * Uso: php tools/smoke-ficha-imprimir-v12-37-0.php
 */

$root  = dirname(__DIR__);
$fails = [];
function _p(&$a, $c, $m) { $a[] = [(bool)$c, $m]; }

$equipe = (string) @file_get_contents($root . '/admin/hr/equipe-view.php');
$boot   = (string) @file_get_contents($root . '/sige-softgenial.php');
$build  = json_decode((string) @file_get_contents($root . '/BUILD.json'), true);

// ── (1) LIGAÇÃO ──────────────────────────────────────────────────────────────
_p($fails, strpos($equipe, 'data-sige-act="imprimirFicha"') !== false, 'Botão Imprimir/Exportar presente no rodapé da ficha');
_p($fails, strpos($equipe, 'function imprimirFicha()') !== false, 'Função imprimirFicha presente');
_p($fails, strpos($equipe, 'function sgFichaBuildPrintDoc(') !== false, 'Construtor do documento presente');
_p($fails, strpos($equipe, 'function sgFichaTokenVars(') !== false, 'Helper de tokens (getComputedStyle) presente');
_p($fails, strpos($equipe, 'sgFichaUltima = { d: res.data') !== false, 'Guarda o último registo seguro ao carregar a ficha');
_p($fails, strpos($equipe, 'sgRhPrintWindow(w, sgFichaBuildPrintDoc(') !== false, 'Reutiliza sgRhPrintWindow para imprimir');
_p($fails, strpos($equipe, 'sgRhLiveNonce()') !== false, 'Usa o nonce de CSP vivo no documento');
_p($fails, substr_count($equipe, 'imprimirFicha') >= 3, 'imprimirFicha registado (Object.assign) e usado');
_p($fails, strpos($equipe, "escola: '<?php echo isset(\$escola->nome_escola)") !== false, 'Nome da escola exposto a JS (cabeçalho do documento)');

// ── (2) FRONTEIRA DE TOKENS (sem cores mágicas no construtor) ────────────────────
$ini = strpos($equipe, 'function sgFichaBuildPrintDoc(');
$fim = strpos($equipe, 'function imprimirFicha()');
$corpo = ($ini !== false && $fim !== false && $fim > $ini) ? substr($equipe, $ini, $fim - $ini) : '';
_p($fails, $corpo !== '', 'Corpo do construtor isolado para análise');
_p($fails, $corpo !== '' && !preg_match('/#[0-9a-fA-F]{3,8}\b/', $corpo), 'Construtor SEM cores mágicas (só var(--token))');
_p($fails, strpos($corpo, 'var(--color-') !== false || strpos($corpo, 'var(--sg-theme') !== false, 'Construtor usa var(--token)');
_p($fails, strpos($corpo, "':root{' + sgFichaTokenVars()") !== false, 'Injecta :root com valores de tokens da página viva');
// Helper de tokens não fixa valores (lê da página); só nomes de tokens.
$ini2 = strpos($equipe, 'function sgFichaTokenVars(');
$fim2 = strpos($equipe, 'function sgFichaPRow(');
$corpo2 = ($ini2 !== false && $fim2 !== false && $fim2 > $ini2) ? substr($equipe, $ini2, $fim2 - $ini2) : '';
_p($fails, $corpo2 !== '' && !preg_match('/#[0-9a-fA-F]{3,8}\b/', $corpo2), 'Helper de tokens SEM cores mágicas');
_p($fails, strpos($corpo2, 'getComputedStyle(document.documentElement)') !== false, 'Helper lê tokens computados da página');

// ── Versões sincronizadas ──────────────────────────────────────────────────────
preg_match('/Version:\s*([0-9.]+)/', $boot, $vh);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $boot, $vc);
$vb = is_array($build) ? (string)($build['version'] ?? '') : '';
_p($fails, !empty($vh[1]) && ($vh[1] === ($vc[1] ?? '')) && (($vc[1] ?? '') === $vb), 'Versões sincronizadas (header=const=build) -> ' . ($vh[1] ?? '?'));

$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) { echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n"; }
echo "\n";
if ($erros) { echo 'SMOKE FICHA-IMPRIMIR FALHOU: ' . count($erros) . " verificacao(oes).\n"; exit(1); }
echo "SMOKE FICHA-IMPRIMIR OK - ligacao completa e documento sem cores magicas.\n";
exit(0);
