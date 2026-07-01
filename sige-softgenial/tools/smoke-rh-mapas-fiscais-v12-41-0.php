<?php
/**
 * Smoke v12.41.0 - Relatórios fiscais (Mapa INSS/IRPS mensal).
 *
 * (1) DADOS (com $wpdb stub): sige_rh_salario_mapa_mes agrega os recibos
 *     processados e calcula os totais (INSS 3% + 4% = 7%).
 * (2) LIGAÇÃO: AJAX do mapa; preview inclui NUIT; botões e JS na aba Salários;
 *     recibo mostra NUIT; versões.
 *
 * Uso: php tools/smoke-rh-mapas-fiscais-v12-41-0.php
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

// ── (1) DADOS com $wpdb stub ──────────────────────────────────────────────────
class _SmokeWpdbMapa {
    public $prefix = 'wp_';
    public function prepare($q, ...$a) { return $q; }
    public function get_charset_collate() { return ''; }
    public function get_var($q) { if (strpos($q, 'SHOW TABLES') !== false) return $this->prefix . 'sige_rh_salarios'; return 0; }
    public function get_results($q) {
        if (strpos($q, 'sige_rh_salarios') !== false) {
            return [
                (object) ['professor_id' => 5, 'nome' => 'Ana', 'nuit' => '123', 'bruto' => 30000, 'inss' => 900, 'inss_empregador' => 1200, 'irps' => 2614.5, 'liquido' => 25285.5],
                (object) ['professor_id' => 7, 'nome' => 'Bo',  'nuit' => '456', 'bruto' => 18000, 'inss' => 540, 'inss_empregador' => 720,  'irps' => 0,      'liquido' => 16740],
            ];
        }
        return [];
    }
}
global $wpdb; $wpdb = new _SmokeWpdbMapa();

$m = sige_rh_salario_mapa_mes(1, 2026, 7);
_p($fails, count($m['itens']) === 2, 'Mapa lista 2 recibos processados -> ' . count($m['itens']));
_p($fails, _eq($m['itens'][0]['inss_total'], 2100), 'Item 1: INSS total 7% = 900+1200 = 2100');
_p($fails, _eq($m['totais']['inss'], 1440), 'Total INSS 3% = 1440');
_p($fails, _eq($m['totais']['inss_empregador'], 1920), 'Total INSS 4% = 1920');
_p($fails, _eq($m['totais']['inss_total'], 3360), 'Total INSS 7% = 3360');
_p($fails, _eq($m['totais']['irps'], 2614.5), 'Total IRPS = 2614,50');
_p($fails, _eq($m['totais']['bruto'], 48000) && _eq($m['totais']['liquido'], 42025.5), 'Totais bruto/líquido correctos');
_p($fails, $m['itens'][0]['nuit'] === '123', 'NUIT presente nas linhas do mapa');

// ── (2) LIGAÇÃO ──────────────────────────────────────────────────────────────
$incl   = (string) @file_get_contents($root . '/includes/rh-salarios.php');
$equipe = (string) @file_get_contents($root . '/admin/hr/equipe-view.php');
$boot   = (string) @file_get_contents($root . '/sige-softgenial.php');
$build  = json_decode((string) @file_get_contents($root . '/BUILD.json'), true);

_p($fails, strpos($incl, "wp_ajax_sige_rh_salario_mapa") !== false, 'AJAX do mapa registado');
_p($fails, strpos($incl, "SELECT id, nome_completo, nuit, salario_base, subsidio") !== false, 'Preview inclui NUIT');
_p($fails, strpos($equipe, 'data-sige-act="sgSalMapa" data-sige-arg="inss"') !== false, 'Botão Mapa INSS presente');
_p($fails, strpos($equipe, 'data-sige-act="sgSalMapa" data-sige-arg="irps"') !== false, 'Botão Mapa IRPS presente');
_p($fails, strpos($equipe, 'function sgSalMapa(') !== false && strpos($equipe, 'function sgSalMapaDoc(') !== false, 'Funções do mapa presentes');
_p($fails, strpos($equipe, "action: 'sige_rh_salario_mapa'") !== false, 'JS chama o endpoint do mapa');
_p($fails, strpos($equipe, 'Mapa de Contribuições — INSS') !== false, 'Documento do Mapa INSS presente');
_p($fails, strpos($equipe, 'Mapa de Retenção na Fonte — IRPS') !== false, 'Documento do Mapa IRPS presente');
_p($fails, strpos($equipe, 'NUIT ' . "' + sgFichaEsc(a.nuit)") !== false, 'Recibo mostra o NUIT');
_p($fails, strpos($equipe, 'sgSalMapa,') !== false, 'sgSalMapa registado no window');
// Documento do mapa sem cores mágicas.
$mi = strpos($equipe, 'function sgSalMapaDoc('); $mf = strpos($equipe, 'function sgSalMapa(', $mi);
$mbody = ($mi !== false && $mf !== false) ? substr($equipe, $mi, $mf - $mi) : '';
_p($fails, $mbody !== '' && !preg_match('/#[0-9a-fA-F]{3,8}\b/', $mbody), 'Documento do mapa sem cores mágicas');

preg_match('/Version:\s*([0-9.]+)/', $boot, $vh);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $boot, $vc);
$vb = is_array($build) ? (string)($build['version'] ?? '') : '';
_p($fails, !empty($vh[1]) && ($vh[1] === ($vc[1] ?? '')) && (($vc[1] ?? '') === $vb), 'Versões sincronizadas -> ' . ($vh[1] ?? '?'));

$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) { echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n"; }
echo "\n";
if ($erros) { echo 'SMOKE RH-MAPAS-FISCAIS FALHOU: ' . count($erros) . " verificacao(oes).\n"; exit(1); }
echo "SMOKE RH-MAPAS-FISCAIS OK.\n";
exit(0);
