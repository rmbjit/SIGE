<?php
/**
 * Smoke v12.39.0 - Assiduidade / Ponto (Fase 1).
 *
 * (1) PURA: dias úteis do mês; rótulos/estados.
 * (2) DADOS (com $wpdb stub): grelha marca ausências; guardar_lote não sobrepõe
 *     dias cobertos por ausência e faz insert/delete conforme o estado.
 * (3) LIGAÇÃO: bootstrap, migração, AJAX, aba/painel/JS/CSS, versões.
 *
 * Uso: php tools/smoke-rh-assiduidade-v12-39-0.php
 */

$root  = dirname(__DIR__);
$fails = [];
function _p(&$a, $c, $m) { $a[] = [(bool)$c, $m]; }

if (!defined('ABSPATH')) define('ABSPATH', __DIR__);
if (!function_exists('add_action')) { function add_action(...$a) {} }
if (!function_exists('add_filter')) { function add_filter(...$a) {} }
if (!function_exists('current_time')) { function current_time($t) { return '2026-07-06 09:00:00'; } }
if (!function_exists('update_option')) { function update_option(...$a) { return true; } }

// Conjunto canonico de staff (mesma fonte da aba Equipa): utilizadores 5 (Ana) e
// 99 (Coberto), ligados as fichas de RH com o mesmo id via sige_professor_id.
if (!function_exists('get_users')) {
    function get_users($args = []) {
        $ana = (object) ['ID' => 5,  'user_email' => 'ana@escola.mz',     'display_name' => 'Ana'];
        $cob = (object) ['ID' => 99, 'user_email' => 'coberto@escola.mz', 'display_name' => 'Coberto'];
        if (isset($args['include'])) {
            $inc = array_map('intval', (array) $args['include']); $r = [];
            if (in_array(5, $inc, true))  $r[] = $ana;
            if (in_array(99, $inc, true)) $r[] = $cob;
            return $r;
        }
        if (isset($args['meta_key'])) return [];
        if (isset($args['fields']) && is_array($args['fields'])) return [$ana, $cob];
        return [];
    }
}
if (!function_exists('sige_staff_active_profile_user_ids')) { function sige_staff_active_profile_user_ids($e) { return [5, 99]; } }
if (!function_exists('get_user_meta')) { function get_user_meta($uid, $key, $single = false) { return $key === 'sige_professor_id' ? (int) $uid : ''; } }
if (!function_exists('sige_is_real_wp_admin_user')) { function sige_is_real_wp_admin_user($uid = null) { return false; } }

require_once $root . '/includes/rh-assiduidade.php';

// ── (1) PURA ─────────────────────────────────────────────────────────────────
_p($fails, sige_rh_dias_uteis_mes(2026, 7) === 23, 'Dias úteis Jul/2026 = 23 -> ' . sige_rh_dias_uteis_mes(2026, 7));
_p($fails, sige_rh_dias_uteis_mes(2026, 2) === 20, 'Dias úteis Fev/2026 = 20 -> ' . sige_rh_dias_uteis_mes(2026, 2));
_p($fails, sige_rh_dias_uteis_mes(2026, 13) === 0, 'Mês inválido = 0');
_p($fails, count(sige_rh_assiduidade_estados()) === 7, '7 estados disponíveis');
_p($fails, sige_rh_assiduidade_estado_label('falta_injustificada') === 'Falta injustificada', 'Rótulo de estado');

// ── (2) DADOS com $wpdb stub ──────────────────────────────────────────────────
class _SmokeWpdbAssi {
    public $prefix = 'wp_'; public $inserts = 0; public $updates = 0; public $deletes = 0;
    public function prepare($q, ...$a) { return $q; }
    public function get_charset_collate() { return ''; }
    public function get_var($q) {
        if (strpos($q, 'SHOW TABLES') !== false) return $this->prefix . 'sige_rh_assiduidade'; // tabela "existe"
        if (stripos($q, 'COUNT(1)') !== false) return 1;   // professor válido
        if (stripos($q, 'SELECT id FROM') !== false) return 0; // sem registo -> insert
        return 0;
    }
    public function get_results($q) {
        if (strpos($q, 'sige_rh_ausencias') !== false) return [ (object) ['professor_id' => 99, 'tipo' => 'ferias'] ];
        if (strpos($q, 'sige_professores') !== false) return [
            (object) ['id' => 5,  'nome_completo' => 'Ana',     'email' => 'ana@escola.mz',     'nuit' => '123', 'salario_base' => 30000, 'subsidio' => 0, 'status_ativo' => 1],
            (object) ['id' => 99, 'nome_completo' => 'Coberto', 'email' => 'coberto@escola.mz', 'nuit' => '999', 'salario_base' => 20000, 'subsidio' => 0, 'status_ativo' => 1],
        ];
        return []; // registos do dia / resumo
    }
    public function insert($t, $d) { $this->inserts++; return 1; }
    public function update($t, $d, $w) { $this->updates++; return 1; }
    public function delete($t, $w) { $this->deletes++; return 1; }
}
global $wpdb; $wpdb = new _SmokeWpdbAssi();

// Grelha: 2 colaboradores; o 99 aparece "em ausência".
$grid = sige_rh_assiduidade_grelha(1, '2026-07-06');
_p($fails, count($grid) === 2, 'Grelha lista 2 colaboradores -> ' . count($grid));
$byid = [];
foreach ($grid as $g) { $byid[$g['professor_id']] = $g; }
_p($fails, isset($byid[99]) && is_array($byid[99]['ausencia']) && $byid[99]['ausencia']['tipo'] === 'ferias', 'Colaborador coberto marcado "em ausência"');
_p($fails, isset($byid[5]) && $byid[5]['ausencia'] === null, 'Colaborador livre sem ausência');

// Guardar em lote: 5 presente (insert), 99 coberto (skip), 7 atraso (insert), 8 vazio (delete).
$res = sige_rh_assiduidade_guardar_lote(1, '2026-07-06', [
    ['professor_id' => 5,  'estado' => 'presente'],
    ['professor_id' => 99, 'estado' => 'falta_injustificada'],
    ['professor_id' => 7,  'estado' => 'atraso', 'minutos_atraso' => 15],
    ['professor_id' => 8,  'estado' => ''],
], 10);
_p($fails, $res['ok'] === true, 'guardar_lote ok');
_p($fails, $res['guardados'] === 3, 'guardar_lote conta 3 (99 coberto ignorado) -> ' . $res['guardados']);
_p($fails, $wpdb->inserts === 2, '2 inserts (5 e 7) -> ' . $wpdb->inserts);
_p($fails, $wpdb->deletes === 1, '1 delete (8 vazio) -> ' . $wpdb->deletes);

$rbad = sige_rh_assiduidade_guardar_lote(1, 'xx', [], 10);
_p($fails, $rbad['ok'] === false, 'guardar_lote recusa data inválida');

$resumo = sige_rh_assiduidade_resumo_mes(1, 5, 2026, 7);
_p($fails, $resumo['dias_uteis'] === 23 && is_array($resumo['por_estado']), 'resumo_mes: dias úteis + por_estado');

// ── (3) LIGAÇÃO ──────────────────────────────────────────────────────────────
$boot   = (string) @file_get_contents($root . '/sige-softgenial.php');
$incl   = (string) @file_get_contents($root . '/includes/rh-assiduidade.php');
$equipe = (string) @file_get_contents($root . '/admin/hr/equipe-view.php');
$build  = json_decode((string) @file_get_contents($root . '/BUILD.json'), true);

_p($fails, strpos($boot, "require_once SIGE_PATH . 'includes/rh-assiduidade.php'") !== false, 'Bootstrap carrega includes/rh-assiduidade.php');
_p($fails, strpos($incl, "add_action('admin_init', 'sige_rh_assiduidade_migrar'") !== false, 'Migração idempotente registada');
_p($fails, strpos($incl, 'wp_ajax_sige_rh_assiduidade_grelha') !== false && strpos($incl, 'wp_ajax_sige_rh_assiduidade_guardar') !== false, 'AJAX grelha/guardar registados');
_p($fails, strpos($incl, 'não sobrepor ausência') !== false, 'Integração: não sobrepõe dias de ausência');
_p($fails, strpos($equipe, 'id="sg-rh-tabbtn-assiduidade"') !== false, 'Aba Assiduidade presente');
_p($fails, strpos($equipe, 'id="sg-rh-panel-assiduidade"') !== false, 'Painel Assiduidade presente');
_p($fails, strpos($equipe, 'function sgAssiGuardar()') !== false && strpos($equipe, 'function sgAssiCarregar()') !== false, 'Funções JS presentes');
_p($fails, strpos($equipe, "action: 'sige_rh_assiduidade_grelha'") !== false, 'JS chama a grelha');
_p($fails, strpos($equipe, '.sg-assi-sel{') !== false, 'CSS da assiduidade presente');

preg_match('/Version:\s*([0-9.]+)/', $boot, $vh);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $boot, $vc);
$vb = is_array($build) ? (string)($build['version'] ?? '') : '';
_p($fails, !empty($vh[1]) && ($vh[1] === ($vc[1] ?? '')) && (($vc[1] ?? '') === $vb), 'Versões sincronizadas -> ' . ($vh[1] ?? '?'));

$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) { echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n"; }
echo "\n";
if ($erros) { echo 'SMOKE RH-ASSIDUIDADE FALHOU: ' . count($erros) . " verificacao(oes).\n"; exit(1); }
echo "SMOKE RH-ASSIDUIDADE OK - Fase 1 completa e ligada.\n";
exit(0);
