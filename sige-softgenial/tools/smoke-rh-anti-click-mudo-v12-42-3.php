<?php
/**
 * Smoke v12.42.3 - Correcção definitiva do "click mudo" em view=equipe.
 *
 * CAUSA-RAIZ: toda a interacção da view (data-sige-act + data-sige-json + submit)
 * dependia do despachante delegado que vive no RODAPÉ (assets/sige-ui.js). Nesta
 * view enorme (e após location.reload() ao gravar) havia uma janela — ou uma
 * falha em ligação lenta — em que os botões ficavam "mudos" até o rodapé chegar.
 *
 * FIX: a view passa a ter um despachante INLINE (corre a meio do body, antes do
 * rodapé), de-duplicado por evento (ev.__sigeAct) com o global para nunca haver
 * duplo disparo. Este smoke verifica as duas metades do contrato + colocação.
 *
 * Uso: php tools/smoke-rh-anti-click-mudo-v12-42-3.php
 */

$root  = dirname(__DIR__);
$fails = [];
function _p(&$a, $c, $m) { $a[] = [(bool)$c, $m]; }

$ui     = (string) @file_get_contents($root . '/assets/sige-ui.js');
$equipe = (string) @file_get_contents($root . '/admin/hr/equipe-view.php');
$boot   = (string) @file_get_contents($root . '/sige-softgenial.php');
$build  = json_decode((string) @file_get_contents($root . '/BUILD.json'), true);

// ── (1) De-dup no despachante GLOBAL (rodapé) ─────────────────────────────────
_p($fails, strpos($ui, 'if (ev.__sigeAct) return;') !== false, 'sige-ui.js: guarda anti-duplo-despacho (lê ev.__sigeAct)');
_p($fails, strpos($ui, 'ev.__sigeAct = true;') !== false, 'sige-ui.js: marca ev.__sigeAct ao despachar');

// ── (2) Despachante INLINE na view (autossuficiente) ──────────────────────────
_p($fails, strpos($equipe, 'window.__sigeEquipeDispatch') !== false, 'view: guarda de idempotência do despachante inline');
_p($fails, substr_count($equipe, "addEventListener('click', function (ev) {") >= 1, 'view: despachante delegado inline de clique');
_p($fails, strpos($equipe, 'if (ev.__sigeAct) return;') !== false, 'view: inline respeita a mesma marca de de-dup');
_p($fails, strpos($equipe, 'ev.__sigeAct = true;') !== false, 'view: inline marca o evento (o global depois ignora)');
_p($fails, strpos($equipe, 'data-sige-args') !== false && strpos($equipe, 'data-sige-noargs') !== false && strpos($equipe, 'data-sige-self') !== false, 'view: inline replica a semântica de argumentos');
_p($fails, strpos($equipe, "if (typeof window.sigeExecutarJsonData !== 'function')") !== false, 'view: fallback inline do executor data-sige-json (recibo/mapa)');
_p($fails, strpos($equipe, "getElementById('form-staff')") !== false && strpos($equipe, "setAttribute('data-sige-bound-submit', '1')") !== false, 'view: liga o submit do formulário e impede religação dupla');
_p($fails, strpos($equipe, 'window.guardarStaff(ev)') !== false, 'view: submit inline chama guardarStaff');

// ── (3) Colocação e conformidade ──────────────────────────────────────────────
// O bloco inline tem de vir DEPOIS do endif da secção só-gestão (para servir
// também quem só tem permissão de VER), e ser nonce'd (CSP).
$pos_endif_final = strrpos($equipe, '<?php endif; ?>');
$pos_dispatch    = strpos($equipe, 'window.__sigeEquipeDispatch');
_p($fails, $pos_endif_final !== false && $pos_dispatch !== false && $pos_dispatch > $pos_endif_final, 'view: despachante inline renderiza para todos os utilizadores (fora do endif de gestão)');
_p($fails, strpos($equipe, '<script <?php echo sige_csp_script_attr(); ?>>') !== false, 'view: blocos inline usam nonce CSP');

preg_match('/Version:\s*([0-9.]+)/', $boot, $vh);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $boot, $vc);
$vb = is_array($build) ? (string)($build['version'] ?? '') : '';
_p($fails, !empty($vh[1]) && ($vh[1] === ($vc[1] ?? '')) && (($vc[1] ?? '') === $vb), 'Versões sincronizadas -> ' . ($vh[1] ?? '?'));

$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) { echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n"; }
echo "\n";
if ($erros) { echo 'SMOKE RH-ANTI-CLICK-MUDO FALHOU: ' . count($erros) . " verificacao(oes).\n"; exit(1); }
echo "SMOKE RH-ANTI-CLICK-MUDO OK.\n";
exit(0);
