<?php
/**
 * Smoke v12.36.1 - Ficha: ESC + design padrão + reconciliação de baseline.
 *
 * (1) ESC/click-fora fecham o modal da ficha.
 * (2) O modal #box-ficha partilha o chrome dos modais padrão (failsafe de
 *     exibição do App Shell + cabeçalho/overlay/close).
 * (3) A dívida de baseline foi reconciliada (hashes actuais no gate).
 *
 * Uso: php tools/smoke-ficha-esc-design-v12-36-1.php
 */

$root  = dirname(__DIR__);
$fails = [];
function _p(&$a, $c, $m) { $a[] = [(bool)$c, $m]; }

$equipe = (string) @file_get_contents($root . '/admin/hr/equipe-view.php');
$gate   = (string) @file_get_contents($root . '/tools/check-v12-16-2-baseline-preservation.php');
$boot   = (string) @file_get_contents($root . '/sige-softgenial.php');
$build  = json_decode((string) @file_get_contents($root . '/BUILD.json'), true);

// ── (1) ESC / click-fora ────────────────────────────────────────────────────────
$kd = strpos($equipe, "if (e.key !== 'Escape') return;");
_p($fails, $kd !== false, 'Handler de teclado actualizado (early-return no Escape)');
if ($kd !== false) {
    $region = substr($equipe, $kd, 320);
    _p($fails, strpos($region, 'fecharFicha()') !== false, 'ESC fecha a ficha (fecharFicha no handler)');
    _p($fails, strpos($region, "getElementById('box-ficha')") !== false, 'ESC dá prioridade à ficha quando aberta');
}
_p($fails, strpos($equipe, "ficha.addEventListener('click'") !== false, 'Ficha fecha ao clicar fora (overlay)');

// ── (2) Design padrão partilhado ────────────────────────────────────────────────
_p($fails, strpos($equipe, '#box-ficha.sige-modal.active') !== false, 'Failsafe de exibição inclui #box-ficha');
_p($fails, strpos($equipe, "#box-equipa.sige-modal,\n#box-ficha.sige-modal{") !== false, 'Overlay (blur/z-index) partilhado com o padrão');
_p($fails, strpos($equipe, '#box-ficha .modal-header{') !== false, 'Cabeçalho padrão aplicado ao #box-ficha');
_p($fails, strpos($equipe, '#box-ficha .modal-close{') !== false, 'Botão de fecho padrão aplicado ao #box-ficha');
_p($fails, strpos($equipe, '#box-ficha .sg-rh-modal-title-wrap{') !== false, 'Título do cabeçalho padrão aplicado ao #box-ficha');
_p($fails, strpos($equipe, '#box-ficha .sg-rh-modal-icon{') !== false, 'Ícone do cabeçalho padrão aplicado ao #box-ficha');
// O título do cabeçalho ficou genérico (não sobrepõe o nome via JS).
_p($fails, strpos($equipe, "t.textContent = 'Ficha de '") === false, 'Título do cabeçalho genérico (nome só na identity head)');
// A identity head deixou de ter gradiente próprio.
_p($fails, strpos($equipe, '.sg-ficha-head{display:flex;align-items:center;gap:var(--space-4);padding:var(--space-5) var(--space-6);background:var(--color-white)') !== false, 'Identity head com fundo neutro (sem gradiente duplo)');

// ── (3) Baseline reconciliada ─────────────────────────────────────────────────────
_p($fails, strpos($gate, '05b757784760f5ebd7ad195bb3c6e792afdc9cf5d2a1646a4a57eb08bed1fc6a') !== false, 'Hash de dashboard-view reconciliado no gate');
_p($fails, strpos($gate, '0b518a7b868e39134b43fc8897f458dfb42160e8a5e8960091f9dcd332f00ee1') !== false, 'Hash de admin-shell reconciliado no gate');
_p($fails, strpos($gate, '480999e77c77f842b002707fca2665ed39221e9a264326662710c20b737f138c') === false, 'Hash antigo de dashboard-view removido');
// Confirma que os ficheiros no disco batem certo com os hashes novos.
$h_dash = @hash_file('sha256', $root . '/admin/system/dashboard-view.php');
$h_shell = @hash_file('sha256', $root . '/includes/admin-shell.php');
_p($fails, $h_dash === '05b757784760f5ebd7ad195bb3c6e792afdc9cf5d2a1646a4a57eb08bed1fc6a', 'dashboard-view no disco corresponde ao hash novo');
_p($fails, $h_shell === '0b518a7b868e39134b43fc8897f458dfb42160e8a5e8960091f9dcd332f00ee1', 'admin-shell no disco corresponde ao hash novo');

// ── Versões sincronizadas ──────────────────────────────────────────────────────
preg_match('/Version:\s*([0-9.]+)/', $boot, $vh);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $boot, $vc);
$vb = is_array($build) ? (string)($build['version'] ?? '') : '';
_p($fails, !empty($vh[1]) && ($vh[1] === ($vc[1] ?? '')) && (($vc[1] ?? '') === $vb), 'Versões sincronizadas (header=const=build) -> ' . ($vh[1] ?? '?'));

$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) { echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n"; }
echo "\n";
if ($erros) { echo 'SMOKE FICHA-ESC-DESIGN FALHOU: ' . count($erros) . " verificacao(oes).\n"; exit(1); }
echo "SMOKE FICHA-ESC-DESIGN OK - ESC, design padrao e baseline reconciliada.\n";
exit(0);
