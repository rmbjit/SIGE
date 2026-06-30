<?php
/**
 * Smoke v12.32.0 - Modelos de crachá por escola.
 *
 * (1) FUNCIONAL: carrega a camada de configuracao real e valida a normalizacao
 *     (allowlist de modelo, sanitizacao de cor hex, limite das redes, booleano).
 * (2) PARIDADE: os ids de modelo do PHP coincidem EXACTAMENTE com os do registo
 *     JS (assets/cracha/sige-cracha-templates.js) - se divergirem, a escola
 *     escolhe um modelo que nao existe ou vice-versa.
 * (3) LIGACAO: bootstrap carrega a camada, ui-kit enfileira o asset, a pagina
 *     Alunos tem o botao/modal e a impressao delega no registo unico.
 *
 * Uso: php tools/smoke-cracha-modelos-v12-32-0.php
 */

$root  = dirname(__DIR__);
$fails = [];
function _p(&$a, $c, $m) { $a[] = [(bool)$c, $m]; }

// ── Stubs mínimos de WP para carregar a camada sem base de dados ───────────────
if (!defined('ABSPATH')) define('ABSPATH', __DIR__);
if (!function_exists('sanitize_key')) { function sanitize_key($k) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string)$k)); } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($s) { return trim(preg_replace('/\s+/', ' ', strip_tags((string)$s))); } }
if (!function_exists('add_action')) { function add_action() { return true; } }

require_once $root . '/includes/cracha-config.php';

// ── (1) FUNCIONAL ──────────────────────────────────────────────────────────────
_p($fails, function_exists('sige_cracha_normalize_config'), 'Camada de config carregada');
_p($fails, sige_cracha_template_ids() === ['aurora', 'classic', 'vivid', 'minimal'], 'Lista canónica de modelos correcta');

$n1 = sige_cracha_normalize_config(['template' => 'vivid']);
_p($fails, $n1['template'] === 'vivid', 'Modelo válido é aceite (vivid)');

$n2 = sige_cracha_normalize_config(['template' => 'hacker']);
_p($fails, $n2['template'] === 'aurora', 'Modelo inválido cai para o default (aurora)');

$n3 = sige_cracha_normalize_config(['accent' => 'ABCDEF']);
_p($fails, $n3['accent'] === '#abcdef', 'Cor hex sem # é normalizada para #abcdef');
$n4 = sige_cracha_normalize_config(['accent' => 'nope']);
_p($fails, $n4['accent'] === '#7c3aed', 'Cor inválida cai para o default');

$n5 = sige_cracha_normalize_config(['show_social' => '1']);
_p($fails, $n5['show_social'] === true, 'show_social "1" -> true');
$n6 = sige_cracha_normalize_config(['show_social' => '0']);
_p($fails, $n6['show_social'] === false, 'show_social "0" -> false');

$long = str_repeat('x', 200);
$n7 = sige_cracha_normalize_config(['social' => ['instagram' => $long, 'facebook' => '<b>fb</b>', 'website' => 'site.co.mz']]);
_p($fails, strlen($n7['social']['instagram']) <= 80, 'Rede social é limitada a 80 caracteres');
_p($fails, strpos($n7['social']['facebook'], '<') === false, 'Rede social é sanitizada (sem HTML)');
_p($fails, isset($n7['social']['website']) && $n7['social']['website'] === 'site.co.mz', 'Website preservado');

// ── (2) PARIDADE PHP <-> JS ──────────────────────────────────────────────────
$js = (string) @file_get_contents($root . '/assets/cracha/sige-cracha-templates.js');
preg_match_all('/\bT\.([a-z0-9_]+)\s*=\s*\{/', $js, $jm);
$js_ids = array_values(array_unique($jm[1]));
sort($js_ids);
$php_ids = sige_cracha_template_ids();
sort($php_ids);
_p($fails, $js_ids === $php_ids, 'Ids de modelo do JS coincidem com os do PHP (' . implode(',', $js_ids) . ' vs ' . implode(',', $php_ids) . ')');
_p($fails, strpos($js, 'window.SigeCrachaTemplates') !== false, 'Registo JS expõe SigeCrachaTemplates');
_p($fails, strpos($js, 'function buildDocument') !== false || strpos($js, 'buildDocument:') !== false, 'Registo JS tem buildDocument');
// Coberturas herdadas da v12.31.0 (agora no registo, fonte única do desenho):
_p($fails, strpos($js, 'function esc(') !== false && substr_count($js, 'esc(') > 5, 'Registo JS escapa os campos dinâmicos');
_p($fails, strpos($js, 'qr-fallback') !== false && strpos($js, 'sigeQrDataUri') !== false, 'QR cai para o nº de processo legível quando indisponível');
_p($fails, strpos($js, 'nonce="') !== false, 'Estilos levam nonce de CSP (pré-visualização em iframe)');
_p($fails, strpos($js, "ctx.showSocial") !== false && strpos($js, 'function socialRow') !== false, 'Redes sociais são renderizadas quando activas');

// ── (3) LIGACAO ──────────────────────────────────────────────────────────────
$boot  = (string) @file_get_contents($root . '/sige-softgenial.php');
$uikit = (string) @file_get_contents($root . '/includes/ui-kit.php');
$view  = (string) @file_get_contents($root . '/admin/academic/alunos_lista.php');
$build = json_decode((string) @file_get_contents($root . '/BUILD.json'), true);

_p($fails, strpos($boot, "require_once SIGE_PATH . 'includes/cracha-config.php'") !== false, 'Bootstrap carrega a camada de crachá');
_p($fails, strpos($uikit, "'sige-cracha-templates'") !== false, 'ui-kit enfileira o registo de modelos no ecrã Alunos');
_p($fails, strpos($view, 'data-sige-act="abrirModeloCracha"') !== false, 'Botão "Modelo de Crachá" presente');
_p($fails, strpos($view, 'id="sige-cracha-modal"') !== false, 'Modal do seletor presente');
_p($fails, strpos($view, '$sige_can_editar_cracha') !== false, 'Botão/modal são gated por permissão');
_p($fails, strpos($view, 'window.SigeCrachaTemplates.buildDocument') !== false, 'Impressão delega no registo único (com fallback)');
_p($fails, strpos($view, 'function sigeCardsDocumentFallback') !== false, 'Existe fallback de impressão degradado');
_p($fails, strpos($view, 'sigeGlobal.cracha.config = r.data.config') !== false, 'Gravar actualiza o modelo usado na impressão');
_p($fails, strpos($view, 'sigePrintCardsWindow') !== false && strpos($view, "addEventListener('load'") !== false, 'Impressão robusta (espera imagens) preservada da v12.31.0');

// ── Versões sincronizadas (sem fixar número; o literal é validado pelo gate de baseline) ──
preg_match('/Version:\s*([0-9.]+)/', $boot, $vh);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $boot, $vc);
$vb = is_array($build) ? (string)($build['version'] ?? '') : '';
_p($fails, !empty($vh[1]) && ($vh[1] === ($vc[1] ?? '')) && (($vc[1] ?? '') === $vb), 'Versões sincronizadas (header=const=build)');

$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) { echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n"; }
echo "\n";
if ($erros) { echo 'SMOKE CRACHA-MODELOS FALHOU: ' . count($erros) . " verificacao(oes).\n"; exit(1); }
echo "SMOKE CRACHA-MODELOS OK - config validada, paridade PHP<->JS e ligacao completas.\n";
exit(0);
