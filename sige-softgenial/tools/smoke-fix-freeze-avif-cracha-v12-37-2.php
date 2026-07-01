<?php
/**
 * Smoke v12.37.2 - congelamento (causa-raiz), AVIF e cargo real no crachá.
 *
 * (1) FREEZE: a visibilidade dos modais depende SÓ de .active (sem aria-hidden),
 *     eliminando o overlay invisível que capturava cliques.
 * (2) AVIF: aceite no validador da equipa + filtro upload_mimes.
 * (3) CRACHÁ: usa o cargo real (sige_hr_role_label) em vez de DOCENTE/STAFF.
 *
 * Uso: php tools/smoke-fix-freeze-avif-cracha-v12-37-2.php
 */

$root  = dirname(__DIR__);
$fails = [];
function _p(&$a, $c, $m) { $a[] = [(bool)$c, $m]; }

$equipe  = (string) @file_get_contents($root . '/admin/hr/equipe-view.php');
$ajax    = (string) @file_get_contents($root . '/includes/ajax-handlers.php');
$uploads = (string) @file_get_contents($root . '/includes/security-uploads.php');
$boot    = (string) @file_get_contents($root . '/sige-softgenial.php');
$build   = json_decode((string) @file_get_contents($root . '/BUILD.json'), true);

// ── (1) FREEZE: visibilidade só por .active ─────────────────────────────────────
_p($fails, strpos($equipe, '#box-ficha.sige-modal.active{') !== false, 'Regra SHOW usa .active');
_p($fails, strpos($equipe, '#box-ficha.sige-modal:not(.active){') !== false, 'Regra HIDE usa :not(.active)');
// Já não deve depender de aria-hidden na visibilidade (causa da dessincronia).
_p($fails, strpos($equipe, 'sige-modal[aria-hidden="false"]') === false, 'SHOW já não depende de aria-hidden=false');
_p($fails, strpos($equipe, 'sige-modal:not(.active)[aria-hidden="true"]') === false, 'HIDE já não depende de aria-hidden=true');
_p($fails, strpos($equipe, 'depender SÓ de .active') !== false, 'Comentário/invariante documentado');

// ── (2) AVIF ────────────────────────────────────────────────────────────────────
_p($fails, strpos($ajax, "'webp', 'svg', 'gif', 'avif'") !== false, 'Validador da equipa aceita avif');
_p($fails, strpos($uploads, "upload_mimes") !== false && strpos($uploads, "'image/avif'") !== false, 'Filtro upload_mimes permite image/avif');
_p($fails, strpos($uploads, "empty(\$mimes['avif'])") !== false, 'Filtro é idempotente (não duplica avif)');

// ── (3) CRACHÁ: cargo real ────────────────────────────────────────────────────────
_p($fails, strpos($equipe, "'cargo' => sige_hr_role_label(\$role_slug)") !== false, 'Crachá usa o cargo real (sige_hr_role_label)');
_p($fails, strpos($equipe, "? 'DOCENTE' : 'STAFF'") === false, 'Removido o cargo genérico DOCENTE/STAFF');
// A função de rótulo existe (fonte única dos cargos).
_p($fails, strpos($equipe, 'function sige_hr_role_label(') !== false, 'sige_hr_role_label disponível');

// ── Versões sincronizadas ──────────────────────────────────────────────────────
preg_match('/Version:\s*([0-9.]+)/', $boot, $vh);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $boot, $vc);
$vb = is_array($build) ? (string)($build['version'] ?? '') : '';
_p($fails, !empty($vh[1]) && ($vh[1] === ($vc[1] ?? '')) && (($vc[1] ?? '') === $vb), 'Versões sincronizadas (header=const=build) -> ' . ($vh[1] ?? '?'));

$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) { echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n"; }
echo "\n";
if ($erros) { echo 'SMOKE FIX-FREEZE-AVIF-CRACHA FALHOU: ' . count($erros) . " verificacao(oes).\n"; exit(1); }
echo "SMOKE FIX-FREEZE-AVIF-CRACHA OK.\n";
exit(0);
