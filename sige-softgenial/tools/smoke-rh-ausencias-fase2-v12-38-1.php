<?php
/**
 * Smoke v12.38.1 - Férias & Ausências (Fase 2): resumo na Ficha e nos Relatórios.
 *
 * (1) FUNCIONAL: sige_rh_ausencia_resumo_professor agrega por tipo e detecta
 *     ausência actual (com um $wpdb stub).
 * (2) LIGAÇÃO: o endpoint da Ficha inclui 'ausencias'; a Ficha (JS) rende a
 *     secção; os Relatórios têm o cartão de Ausências.
 *
 * Uso: php tools/smoke-rh-ausencias-fase2-v12-38-1.php
 */

$root  = dirname(__DIR__);
$fails = [];
function _p(&$a, $c, $m) { $a[] = [(bool)$c, $m]; }

if (!defined('ABSPATH')) define('ABSPATH', __DIR__);
if (!function_exists('add_action')) { function add_action(...$a) {} }
if (!function_exists('add_filter')) { function add_filter(...$a) {} }

require_once $root . '/includes/rh-ausencias.php';

// ── (1) FUNCIONAL com $wpdb stub ────────────────────────────────────────────────
class _SmokeWpdbAus {
    public $prefix = 'wp_';
    private $results; private $row;
    public function __construct($results, $row) { $this->results = $results; $this->row = $row; }
    public function prepare($q, ...$a) { return $q; }
    public function get_var($q) { return $this->prefix . 'sige_rh_ausencias'; } // tabela "existe"
    public function get_results($q) { return $this->results; }
    public function get_row($q) { return $this->row; }
}

global $wpdb;
$wpdb = new _SmokeWpdbAus(
    [ (object) ['tipo' => 'ferias', 'dias' => 10.0], (object) ['tipo' => 'doenca', 'dias' => 2.0] ],
    (object) ['tipo' => 'ferias', 'data_fim' => '2026-07-17']
);
$rp = sige_rh_ausencia_resumo_professor(1, 5, 2026, '2026-07-10');
_p($fails, abs($rp['total_dias'] - 12.0) < 0.001, 'Resumo colaborador: total = 12 dias -> ' . $rp['total_dias']);
_p($fails, ($rp['por_tipo']['ferias'] ?? 0) == 10.0 && ($rp['por_tipo']['doenca'] ?? 0) == 2.0, 'Resumo colaborador: por tipo correcto');
_p($fails, is_array($rp['ausente_hoje']) && $rp['ausente_hoje']['tipo'] === 'ferias', 'Resumo colaborador: detecta ausência actual');

$wpdb = new _SmokeWpdbAus([], null); // sem dados
$re = sige_rh_ausencia_resumo_escola(1, 2026, '2026-07-10');
_p($fails, $re['total_dias'] === 0.0 && $re['por_tipo'] === [] && $re['ausentes_hoje'] === [], 'Resumo escola vazio: shape correcto, sem erro');

// ── (2) LIGAÇÃO ──────────────────────────────────────────────────────────────
$ajax   = (string) @file_get_contents($root . '/includes/ajax-handlers.php');
$equipe = (string) @file_get_contents($root . '/admin/hr/equipe-view.php');
$boot   = (string) @file_get_contents($root . '/sige-softgenial.php');
$build  = json_decode((string) @file_get_contents($root . '/BUILD.json'), true);

_p($fails, strpos($ajax, "'ausencias'       => \$aus_fmt") !== false, 'Endpoint da Ficha inclui o resumo de ausências');
_p($fails, strpos($ajax, 'sige_rh_ausencia_resumo_professor(') !== false, 'Endpoint usa o resumo por colaborador');
_p($fails, strpos($equipe, 'Ausências em ' . "' + ausAno") !== false, 'Ficha (JS) rende a secção de ausências');
_p($fails, strpos($equipe, 'd.ausencias') !== false, 'Ficha lê d.ausencias');
_p($fails, strpos($equipe, 'sige_rh_ausencia_resumo_escola(') !== false, 'Relatórios calculam o resumo da escola');
_p($fails, strpos($equipe, 'Ausentes hoje') !== false, 'Cartão de Relatórios mostra "Ausentes hoje"');
_p($fails, strpos($equipe, '.sg-aus-hoje{') !== false, 'CSS do cartão de ausências presente');

preg_match('/Version:\s*([0-9.]+)/', $boot, $vh);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $boot, $vc);
$vb = is_array($build) ? (string)($build['version'] ?? '') : '';
_p($fails, !empty($vh[1]) && ($vh[1] === ($vc[1] ?? '')) && (($vc[1] ?? '') === $vb), 'Versões sincronizadas -> ' . ($vh[1] ?? '?'));

$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) { echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n"; }
echo "\n";
if ($erros) { echo 'SMOKE RH-AUSENCIAS-FASE2 FALHOU: ' . count($erros) . " verificacao(oes).\n"; exit(1); }
echo "SMOKE RH-AUSENCIAS-FASE2 OK.\n";
exit(0);
