<?php
/**
 * Smoke v12.36.0 - Ficha do Colaborador (perfil 360, só leitura).
 *
 * A ficha é renderizada no cliente a partir do endpoint autorizado já existente
 * (sige_get_staff_secure). Este smoke valida a LIGAÇÃO e a fronteira de segurança:
 *  - o endpoint passa a devolver, de forma aditiva, os campos não sensíveis da
 *    ficha (data_admissao/nivel_carreira/regime_trabalho);
 *  - a view tem o botão, o modal, as funções e o CSS, e reutiliza o mesmo
 *    endpoint seguro (sem novo endpoint, sem dados sensíveis no DOM da lista).
 *
 * Uso: php tools/smoke-rh-ficha-v12-36-0.php
 */

$root  = dirname(__DIR__);
$fails = [];
function _p(&$a, $c, $m) { $a[] = [(bool)$c, $m]; }

$ajax   = (string) @file_get_contents($root . '/includes/ajax-handlers.php');
$equipe = (string) @file_get_contents($root . '/admin/hr/equipe-view.php');
$boot   = (string) @file_get_contents($root . '/sige-softgenial.php');
$build  = json_decode((string) @file_get_contents($root . '/BUILD.json'), true);

// ── Endpoint seguro: payload aditivo (campos não sensíveis da ficha) ────────────
$secure_region = '';
$pos = strpos($ajax, "wp_ajax_sige_get_staff_secure");
if ($pos !== false) { $secure_region = substr($ajax, $pos, 3000); }
_p($fails, $secure_region !== '', 'Handler sige_get_staff_secure encontrado');
_p($fails, strpos($secure_region, "'data_admissao'") !== false, 'Endpoint devolve data_admissao');
_p($fails, strpos($secure_region, "'nivel_carreira'") !== false, 'Endpoint devolve nivel_carreira');
_p($fails, strpos($secure_region, "'regime_trabalho'") !== false, 'Endpoint devolve regime_trabalho');
// Continua a exigir nonce + permissão de gestão (fronteira intacta).
_p($fails, strpos($secure_region, 'sige_ajax_equipe_can_manage()') !== false, 'Endpoint mantém gating por gestão');
_p($fails, strpos($secure_region, "wp_verify_nonce") !== false, 'Endpoint mantém verificação de nonce');

// ── View: botão, modal, funções, CSS e reutilização do endpoint ─────────────────
_p($fails, strpos($equipe, 'data-sige-json-fn="verFichaColaborador"') !== false, 'Botão "Ver ficha" presente (sigeExecutarJsonData)');
_p($fails, strpos($equipe, 'class="btn-action btn-ficha"') !== false, 'Botão usa estilo btn-ficha');
_p($fails, strpos($equipe, 'id="box-ficha"') !== false, 'Modal #box-ficha presente');
_p($fails, strpos($equipe, 'function verFichaColaborador(') !== false, 'Função verFichaColaborador presente');
_p($fails, strpos($equipe, 'function fecharFicha(') !== false, 'Função fecharFicha presente');
_p($fails, strpos($equipe, 'function sgFichaRender(') !== false, 'Função de render sgFichaRender presente');
_p($fails, substr_count($equipe, "verFichaColaborador") >= 2, 'verFichaColaborador registado e usado');
_p($fails, strpos($equipe, "action: 'sige_get_staff_secure'") !== false, 'Ficha reutiliza o endpoint seguro (sem novo endpoint)');
_p($fails, strpos($equipe, '.sg-ficha-sec{') !== false, 'CSS da ficha presente (design system)');
// Minimização: o blob da linha continua mínimo (sem salário/banco/NUIT no DOM).
_p($fails, strpos($equipe, "'nuit'=>''") !== false || strpos($equipe, "não são renderizados no DOM") !== false, 'Dados sensíveis continuam fora do DOM da lista');

// Banner de estado do contrato (limiares alinhados: <=30 crítico, <=90 aviso).
_p($fails, strpos($equipe, 'function sgFichaContrato(') !== false, 'Helper de estado de contrato presente');
_p($fails, strpos($equipe, 'dias <= 30') !== false && strpos($equipe, 'dias <= 90') !== false, 'Limiares do contrato alinhados (30/90)');

// ── Versões sincronizadas ───────────────────────────────────────────────────────
preg_match('/Version:\s*([0-9.]+)/', $boot, $vh);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $boot, $vc);
$vb = is_array($build) ? (string)($build['version'] ?? '') : '';
_p($fails, !empty($vh[1]) && ($vh[1] === ($vc[1] ?? '')) && (($vc[1] ?? '') === $vb), 'Versões sincronizadas (header=const=build) -> ' . ($vh[1] ?? '?'));

$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) { echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n"; }
echo "\n";
if ($erros) { echo 'SMOKE RH-FICHA FALHOU: ' . count($erros) . " verificacao(oes).\n"; exit(1); }
echo "SMOKE RH-FICHA OK - ligacao completa e fronteira de seguranca intacta.\n";
exit(0);
