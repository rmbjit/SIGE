<?php
/**
 * Smoke v12.42.x - (1) as sub-abas de RH (Ausencias/Assiduidade/Salarios/mapas)
 * listam EXACTAMENTE os mesmos colaboradores da aba Equipa (conjunto canonico),
 * nao a tabela sige_professores em bruto; (2) correccao rigorosa do congelamento.
 *
 * (1) DADOS: sige_rh_colaboradores_escola() parte do conjunto CANONICO de staff
 *     (perfil SIGE activo + meta de escola), liga cada um a sua ficha de RH e
 *     EXCLUI o admin WP real e as linhas orfas de sige_professores que nao sao
 *     staff. sige_rh_salario_mapa_mes() so conta recibos desse conjunto.
 * (2) LIGACAO: grelha/preview/mapa/dropdown usam a lista canonica; o
 *     reconciliador unico do shell existe e esta ligado (open/close, cracha,
 *     pointerdown de captura); versoes sincronizadas.
 *
 * Uso: php tools/smoke-rh-superadmin-anti-congelamento-v12-42-0.php
 */

$root  = dirname(__DIR__);
$fails = [];
function _p(&$a, $c, $m) { $a[] = [(bool)$c, $m]; }
function _eq($x, $y) { return abs((float)$x - (float)$y) < 0.01; }

if (!defined('ABSPATH')) define('ABSPATH', __DIR__);
if (!function_exists('add_action')) { function add_action(...$a) {} }
if (!function_exists('add_filter')) { function add_filter(...$a) {} }
if (!function_exists('get_option')) { function get_option($k, $d = null) { return $d; } }
if (!function_exists('update_option')) { function update_option(...$a) { return true; } }

/* ── Stubs do WordPress que modelam o cenario ──────────────────────────────────
 * Fichas de RH (sige_professores) na escola 1:
 *   id=1 admin@sistema.mz  (super admin de manutencao) -> deve SAIR
 *   id=5 ana@escola.mz     (staff real)                -> deve FICAR
 *   id=9 orfao@escola.mz   (linha antiga, sem staff WP) -> deve SAIR
 * Utilizadores WP staff canonicos: 100 (Ana), 101 (Admin).
 */
if (!function_exists('get_users')) {
    function get_users($args = []) {
        $ana = (object) ['ID' => 100, 'user_email' => 'ana@escola.mz',   'display_name' => 'Ana'];
        $adm = (object) ['ID' => 101, 'user_email' => 'admin@sistema.mz', 'display_name' => 'Admin'];
        if (isset($args['include'])) {
            $inc = array_map('intval', (array) $args['include']); $r = [];
            if (in_array(100, $inc, true)) $r[] = $ana;
            if (in_array(101, $inc, true)) $r[] = $adm;
            return $r;
        }
        if (isset($args['meta_key'])) return [100]; // fields=ID (staff scoped por escola)
        if (isset($args['fields']) && is_array($args['fields'])) return [$ana, $adm]; // fallback
        return [];
    }
}
if (!function_exists('sige_staff_active_profile_user_ids')) {
    function sige_staff_active_profile_user_ids($escola_id) { return [100, 101]; }
}
if (!function_exists('get_user_meta')) {
    function get_user_meta($uid, $key, $single = false) {
        $map = [100 => 5, 101 => 1];
        if ($key === 'sige_professor_id') return $map[$uid] ?? 0;
        return ''; // sem sige_staff_removed_at
    }
}
if (!function_exists('sige_is_real_wp_admin_user')) {
    function sige_is_real_wp_admin_user($uid = null) { return ((int) $uid) === 101; }
}

class _SmokeWpdbCanon {
    public $prefix = 'wp_';
    public function prepare($q, ...$a) { return $q; }
    public function get_charset_collate() { return ''; }
    public function get_var($q) { if (strpos($q, 'SHOW TABLES') !== false) return $this->prefix . 'sige_rh_salarios'; return 0; }
    public function query($q) { return true; }
    public function get_results($q) {
        if (strpos($q, 'sige_professores') !== false && strpos($q, 'sige_rh_salarios') === false) {
            return [
                (object) ['id' => 1, 'nome_completo' => 'Admin', 'nuit' => '000', 'email' => 'admin@sistema.mz', 'salario_base' => 99999, 'subsidio' => 0, 'status_ativo' => 1],
                (object) ['id' => 5, 'nome_completo' => 'Ana',   'nuit' => '123', 'email' => 'ana@escola.mz',   'salario_base' => 30000, 'subsidio' => 0, 'status_ativo' => 1],
                (object) ['id' => 9, 'nome_completo' => 'Orfao', 'nuit' => '999', 'email' => 'orfao@escola.mz', 'salario_base' => 12345, 'subsidio' => 0, 'status_ativo' => 1],
            ];
        }
        if (strpos($q, 'sige_rh_salarios') !== false) {
            return [
                (object) ['professor_id' => 1, 'nome' => 'Admin', 'nuit' => '000', 'email' => 'admin@sistema.mz', 'bruto' => 99999, 'inss' => 1, 'inss_empregador' => 1, 'irps' => 1, 'liquido' => 99997],
                (object) ['professor_id' => 5, 'nome' => 'Ana',   'nuit' => '123', 'email' => 'ana@escola.mz',   'bruto' => 30000, 'inss' => 900, 'inss_empregador' => 1200, 'irps' => 2614.5, 'liquido' => 25285.5],
                (object) ['professor_id' => 9, 'nome' => 'Orfao', 'nuit' => '999', 'email' => 'orfao@escola.mz', 'bruto' => 12345, 'inss' => 370.35, 'inss_empregador' => 493.8, 'irps' => 0, 'liquido' => 11974.65],
            ];
        }
        return [];
    }
}
global $wpdb; $wpdb = new _SmokeWpdbCanon();

require_once $root . '/includes/rh-assiduidade.php';
require_once $root . '/includes/rh-salarios.php';

// ── (1) LISTA CANONICA ────────────────────────────────────────────────────────
$colabs = sige_rh_colaboradores_escola(1);
$ids = array_map(fn($c) => (int) $c['professor_id'], $colabs);
_p($fails, count($colabs) === 1, 'Lista canonica tem 1 colaborador (Ana) -> ' . count($colabs));
_p($fails, in_array(5, $ids, true), 'Inclui o staff real (id 5, Ana)');
_p($fails, !in_array(1, $ids, true), 'EXCLUI o admin WP real (id 1)');
_p($fails, !in_array(9, $ids, true), 'EXCLUI a linha orfa que nao e staff (id 9)');
_p($fails, !empty($colabs) && (string) $colabs[0]['nuit'] === '123', 'Traz o NUIT da ficha de RH');

// ── (2) MAPA so conta o conjunto canonico ─────────────────────────────────────
$m = sige_rh_salario_mapa_mes(1, 2026, 7);
_p($fails, count($m['itens']) === 1, 'Mapa conta so o staff canonico -> ' . count($m['itens']) . ' (esperado 1)');
_p($fails, !empty($m['itens']) && $m['itens'][0]['professor_id'] === 5, 'A unica linha do mapa e a do colaborador real (Ana)');
_p($fails, _eq($m['totais']['bruto'], 30000), 'Totais NAO incluem admin nem orfao (bruto 30000)');

// ── (3) LIGACAO no codigo ─────────────────────────────────────────────────────
$assi   = (string) @file_get_contents($root . '/includes/rh-assiduidade.php');
$sal    = (string) @file_get_contents($root . '/includes/rh-salarios.php');
$equipe = (string) @file_get_contents($root . '/admin/hr/equipe-view.php');
$boot   = (string) @file_get_contents($root . '/sige-softgenial.php');
$build  = json_decode((string) @file_get_contents($root . '/BUILD.json'), true);

_p($fails, strpos($assi, 'function sige_rh_colaboradores_escola') !== false, 'Resolver canonico definido');
_p($fails, strpos($assi, 'sige_staff_active_profile_user_ids') !== false, 'Resolver usa o criterio canonico da Equipa');
_p($fails, strpos($assi, '$colabs = sige_rh_colaboradores_escola($escola_id)') !== false, 'Grelha usa a lista canonica');
_p($fails, strpos($sal, 'sige_rh_colaboradores_escola($escola_id)') !== false, 'Preview usa a lista canonica');
_p($fails, strpos($sal, 'sige_rh_colaboradores_ids_escola') !== false, 'Mapa filtra pelo conjunto canonico');
_p($fails, strpos($equipe, 'sige_rh_colaboradores_escola((int) $escola_id)') !== false, 'Dropdown de Ausencias usa a lista canonica');
_p($fails, strpos($assi, 'function sige_rh_professor_e_admin_sistema') === false, 'Helper antigo (por role/email) removido');

// Anti-congelamento: fonte de verdade unica + ligacao.
_p($fails, strpos($equipe, 'function sigeRhReconciliarShell(') !== false, 'Reconciliador unico do shell presente');
_p($fails, substr_count($equipe, 'sigeRhReconciliarShell()') >= 4, 'Reconciliador chamado em open/close/cracha (>=4)');
_p($fails, strpos($equipe, "addEventListener('pointerdown', function () {") !== false && strpos($equipe, '}, true);') !== false, 'Rede de seguranca em pointerdown de captura');
_p($fails, strpos($equipe, '#box-salario-cfg.sige-modal:not(.active)') !== false && strpos($equipe, 'pointer-events:none!important;') !== false, 'Invariante CSS (fechado nao captura) mantido');

preg_match('/Version:\s*([0-9.]+)/', $boot, $vh);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $boot, $vc);
$vb = is_array($build) ? (string)($build['version'] ?? '') : '';
_p($fails, !empty($vh[1]) && ($vh[1] === ($vc[1] ?? '')) && (($vc[1] ?? '') === $vb), 'Versoes sincronizadas -> ' . ($vh[1] ?? '?'));

$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) { echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n"; }
echo "\n";
if ($erros) { echo 'SMOKE RH-COLABORADORES/ANTI-CONGELAMENTO FALHOU: ' . count($erros) . " verificacao(oes).\n"; exit(1); }
echo "SMOKE RH-COLABORADORES/ANTI-CONGELAMENTO OK.\n";
exit(0);
