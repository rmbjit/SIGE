<?php
/**
 * Smoke v12.40.0 - Processamento de Salário (Fase 1).
 *
 * (1) PURA: INSS, IRPS por escalões (com parcela a abater + isenção), desconto
 *     de faltas e cálculo completo; normalização da config.
 * (2) LIGAÇÃO: bootstrap, migração, AJAX, aba/painel/modal/JS/CSS, versões.
 *
 * Uso: php tools/smoke-rh-salarios-v12-40-0.php
 */

$root  = dirname(__DIR__);
$fails = [];
function _p(&$a, $c, $m) { $a[] = [(bool)$c, $m]; }
function _eq($x, $y) { return abs((float)$x - (float)$y) < 0.01; }

if (!defined('ABSPATH')) define('ABSPATH', __DIR__);
if (!function_exists('add_action')) { function add_action(...$a) {} }
if (!function_exists('get_option')) { function get_option($k, $d = null) { return $d; } }
if (!function_exists('update_option')) { function update_option(...$a) { return true; } }

require_once $root . '/includes/rh-salarios.php';

$cfg = sige_rh_salario_config_defaults();

// ── (1) PURA ─────────────────────────────────────────────────────────────────
_p($fails, count($cfg['irps_escaloes']) === 6 && (float) $cfg['inss_trabalhador'] === 3.0, 'Config padrão: INSS 3% e 6 escalões');
_p($fails, _eq(sige_rh_salario_inss(30000, 3), 900), 'INSS 3% de 30000 = 900');

$esc = $cfg['irps_escaloes'];
_p($fails, _eq(sige_rh_salario_irps(14550, $esc), 0), 'IRPS isento (14550) = 0');
_p($fails, _eq(sige_rh_salario_irps(29100, $esc), 2614.5), 'IRPS 29100 (32%) = 2614,50 -> ' . sige_rh_salario_irps(29100, $esc));
_p($fails, _eq(sige_rh_salario_irps(20800, $esc), 57.5), 'IRPS 20800 (15%) = 57,50 -> ' . sige_rh_salario_irps(20800, $esc));
_p($fails, _eq(sige_rh_salario_desconto_faltas(30000, 23, 2), 2608.70), 'Desconto 2 faltas (30000/23*2) = 2608,70');
_p($fails, sige_rh_salario_desconto_faltas(30000, 0, 2) === 0.0, 'Sem dias úteis -> sem desconto de faltas');

$c = sige_rh_salario_calcular(['salario_base' => 28000, 'subsidio' => 2000, 'dias_uteis' => 23], $cfg);
_p($fails, _eq($c['bruto'], 30000) && _eq($c['inss'], 900) && _eq($c['colectavel'], 29100) && _eq($c['irps'], 2614.5) && _eq($c['liquido'], 26485.5), 'Cálculo completo (base bruto-INSS): líquido 26485,50');

$cfg2 = $cfg; $cfg2['irps_base'] = 'bruto';
$c2 = sige_rh_salario_calcular(['salario_base' => 30000, 'subsidio' => 0, 'dias_uteis' => 23], $cfg2);
_p($fails, _eq($c2['irps'], 2902.5), 'IRPS com base=bruto (30000) = 2902,50 -> ' . $c2['irps']);

$c3 = sige_rh_salario_calcular(['salario_base' => 30000, 'subsidio' => 0, 'faltas_injustificadas' => 2, 'dias_uteis' => 23, 'outros' => 500], $cfg);
_p($fails, _eq($c3['desconto_faltas'], 2608.70) && _eq($c3['outros'], 500) && _eq($c3['liquido'], 30000 - 900 - $c3['irps'] - 2608.70 - 500), 'Cálculo com faltas + outros coerente');

$norm = sige_rh_salario_config_normalizar(['inss_trabalhador' => 999, 'irps_base' => 'xx', 'irps_escaloes' => 'nao-array']);
_p($fails, $norm['inss_trabalhador'] === 100.0 && $norm['irps_base'] === 'bruto_menos_inss' && count($norm['irps_escaloes']) === 6, 'Normalização: clamp + defaults seguros');

// ── (2) LIGAÇÃO ──────────────────────────────────────────────────────────────
$boot   = (string) @file_get_contents($root . '/sige-softgenial.php');
$incl   = (string) @file_get_contents($root . '/includes/rh-salarios.php');
$equipe = (string) @file_get_contents($root . '/admin/hr/equipe-view.php');
$build  = json_decode((string) @file_get_contents($root . '/BUILD.json'), true);

_p($fails, strpos($boot, "require_once SIGE_PATH . 'includes/rh-salarios.php'") !== false, 'Bootstrap carrega includes/rh-salarios.php');
_p($fails, strpos($incl, "add_action('admin_init', 'sige_rh_salarios_migrar'") !== false, 'Migração idempotente registada');
foreach (['preview', 'processar', 'config_guardar'] as $act) {
    _p($fails, strpos($incl, "wp_ajax_sige_rh_salario_{$act}") !== false, "AJAX registado: sige_rh_salario_{$act}");
}
_p($fails, strpos($incl, 'sige_ajax_equipe_can_manage') !== false, 'AJAX gated por gestão');
_p($fails, strpos($incl, 'RECALCULA') !== false || strpos($incl, 'não confia') !== false, 'Processar recalcula no servidor (não confia no cliente)');
_p($fails, strpos($equipe, 'id="sg-rh-tabbtn-salarios"') !== false, 'Aba Salários presente');
_p($fails, strpos($equipe, 'id="sg-rh-panel-salarios"') !== false, 'Painel Salários presente');
_p($fails, strpos($equipe, 'id="box-salario-cfg"') !== false, 'Modal de configuração presente');
_p($fails, strpos($equipe, 'function sgSalRecibo(') !== false && strpos($equipe, 'function sgSalProcessar(') !== false, 'Funções JS (recibo/processar) presentes');
_p($fails, strpos($equipe, "action: 'sige_rh_salario_preview'") !== false, 'JS chama o preview');
_p($fails, strpos($equipe, '.sg-sal-table{') !== false, 'CSS dos salários presente');
_p($fails, strpos($equipe, 'sgFichaTokenVars()') !== false, 'Recibo usa tokens da página (sem cores mágicas)');
// O construtor do recibo não deve introduzir hex.
$ri = strpos($equipe, 'function sgSalRecibo('); $rf = strpos($equipe, 'var w = window.open', $ri);
$rbody = ($ri !== false && $rf !== false) ? substr($equipe, $ri, $rf - $ri) : '';
_p($fails, $rbody !== '' && !preg_match('/#[0-9a-fA-F]{3,8}\b/', $rbody), 'Recibo sem cores mágicas (só var(--token))');

preg_match('/Version:\s*([0-9.]+)/', $boot, $vh);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $boot, $vc);
$vb = is_array($build) ? (string)($build['version'] ?? '') : '';
_p($fails, !empty($vh[1]) && ($vh[1] === ($vc[1] ?? '')) && (($vc[1] ?? '') === $vb), 'Versões sincronizadas -> ' . ($vh[1] ?? '?'));

$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) { echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n"; }
echo "\n";
if ($erros) { echo 'SMOKE RH-SALARIOS FALHOU: ' . count($erros) . " verificacao(oes).\n"; exit(1); }
echo "SMOKE RH-SALARIOS OK - calculo MZ correcto e ligacao completa.\n";
exit(0);
