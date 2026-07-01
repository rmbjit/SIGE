<?php
/**
 * Smoke v12.37.1 - correcções: foto de perfil + botões congelados.
 *
 * (1) FOTO: o validador de URL de imagem deixa de exigir host idêntico ao do
 *     site e passa a aceitar anexos da biblioteca / URLs do directório de
 *     uploads (tolerante a CDN/www/proxy), mantendo a recusa de externos.
 * (2) BOTÕES: o despachante data-sige-act corre em try/catch; a view tem a
 *     auto-recuperação de estado de modal preso (fase de captura).
 *
 * Uso: php tools/smoke-fix-foto-botoes-v12-37-1.php
 */

$root  = dirname(__DIR__);
$fails = [];
function _p(&$a, $c, $m) { $a[] = [(bool)$c, $m]; }

$ajax   = (string) @file_get_contents($root . '/includes/ajax-handlers.php');
$ui     = (string) @file_get_contents($root . '/assets/sige-ui.js');
$equipe = (string) @file_get_contents($root . '/admin/hr/equipe-view.php');
$boot   = (string) @file_get_contents($root . '/sige-softgenial.php');
$build  = json_decode((string) @file_get_contents($root . '/BUILD.json'), true);

// ── (1) FOTO: validador robusto ──────────────────────────────────────────────
$ini = strpos($ajax, 'function sige_ajax_equipe_sanitize_image_url');
$fim = $ini !== false ? strpos($ajax, "\n    }", $ini) : false;
$fn  = ($ini !== false && $fim !== false) ? substr($ajax, $ini, $fim - $ini) : '';
_p($fails, $fn !== '', 'Função sanitize_image_url localizada');
_p($fails, strpos($fn, 'attachment_url_to_postid') !== false, 'Aceita anexos reais da biblioteca (attachment_url_to_postid)');
_p($fails, strpos($fn, "wp_get_upload_dir") !== false && strpos($fn, "['baseurl']") !== false, 'Aceita URLs do directório de uploads (por caminho)');
_p($fails, strpos($fn, "strtolower(\$url_host) === strtolower(\$site_host)") !== false, 'Mesmo host é aceite (recurso), não rejeitado');
_p($fails, strpos($fn, "!== strtolower(\$site_host)") === false, 'Removida a rejeição por host diferente (causa do bug)');
_p($fails, preg_match('/in_array\(\$ext, \[[^\]]*\x27png\x27/', $fn) === 1, 'Mantém a exigência de extensão de imagem');

// ── (2) BOTÕES: try/catch no despachante ────────────────────────────────────────
$d = strpos($ui, "data-sige-act");
_p($fails, strpos($ui, 'erro na acção') !== false, 'Despachante regista erros de handler (try/catch)');
// O invocador fn.apply está dentro de um try.
$posApply = strpos($ui, 'fn.apply(null, args);');
$posTry   = $posApply !== false ? strrpos(substr($ui, 0, $posApply), 'try {') : false;
$posCatch = $posApply !== false ? strpos($ui, 'catch (err)', $posApply) : false;
_p($fails, $posTry !== false && $posCatch !== false && $posCatch > $posApply, 'fn.apply envolvido por try/catch');

// ── (2b) Auto-recuperação de estado preso na view ────────────────────────────────
_p($fails, strpos($equipe, 'Auto-recuperação anti-"congelamento"') !== false, 'Auto-recuperação presente');
_p($fails, strpos($equipe, "document.querySelector('.sige-modal.active, .is-open')") !== false, 'Só actua quando NENHUM modal está aberto (.active/.is-open)');
_p($fails, strpos($equipe, "'.sige-modal[aria-hidden=\"false\"]'") !== false, 'Fecha overlays órfãos (aria-hidden=false sem estado)');
_p($fails, strpos($equipe, "classList.remove('sige-rh-modal-open')") !== false, 'Liberta o body (sige-rh-modal-open)');
_p($fails, strpos($equipe, "}, true);") !== false, 'Regista o recuperador em fase de captura');

// ── Versões sincronizadas ──────────────────────────────────────────────────────
preg_match('/Version:\s*([0-9.]+)/', $boot, $vh);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $boot, $vc);
$vb = is_array($build) ? (string)($build['version'] ?? '') : '';
_p($fails, !empty($vh[1]) && ($vh[1] === ($vc[1] ?? '')) && (($vc[1] ?? '') === $vb), 'Versões sincronizadas (header=const=build) -> ' . ($vh[1] ?? '?'));

$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) { echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n"; }
echo "\n";
if ($erros) { echo 'SMOKE FIX-FOTO-BOTOES FALHOU: ' . count($erros) . " verificacao(oes).\n"; exit(1); }
echo "SMOKE FIX-FOTO-BOTOES OK - foto valida por anexo/uploads e UI auto-recupera.\n";
exit(0);
