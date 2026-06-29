<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke dos niveis de perfil editaveis (Fase 9 incremento 4).
 *
 * Valida a fusao do mapa base com os desvios guardados, a gravacao que so guarda
 * desvios (um valor igual ao base remove o desvio), a validacao 0..100, e que o
 * avaliador passa a respeitar imediatamente os niveis editados.
 */

$root = dirname(__DIR__);
if (!defined('ABSPATH')) { define('ABSPATH', '/tmp/'); }

if (!function_exists('sanitize_key')) { function sanitize_key($s) { return preg_replace('/[^a-z0-9_]/', '', strtolower((string) $s)); } }
if (!function_exists('apply_filters')) { function apply_filters($t, $v, ...$a) { return $v; } }
if (!function_exists('add_action')) { function add_action(...$a) {} }
if (!function_exists('add_filter')) { function add_filter(...$a) {} }
if (!function_exists('get_current_user_id')) { function get_current_user_id() { return $GLOBALS['__actor'] ?? 0; } }
if (!function_exists('user_can')) { function user_can($u, $c) { return false; } }

$GLOBALS['__opt'] = [];
if (!function_exists('get_option')) { function get_option($k, $d = false) { return $GLOBALS['__opt'][$k] ?? $d; } }
if (!function_exists('update_option')) { function update_option($k, $v, $a = null) { $GLOBALS['__opt'][$k] = $v; return true; } }

$GLOBALS['__protegidos'] = [1 => true];
if (!function_exists('sige_is_real_wp_admin_user')) {
    function sige_is_real_wp_admin_user($id = null) { $id = $id ?: ($GLOBALS['__actor'] ?? 0); return !empty($GLOBALS['__protegidos'][(int) $id]); }
}

// Mock minimo para o avaliador (perfis activos por utilizador).
class SigeNivMock {
    public $prefix = 'wp_';
    public $slug_by_id = [90 => 'direccao_geral', 80 => 'admin_ti', 70 => 'director', 30 => 'professor'];
    public $user_role = [5 => 80, 7 => 30, 8 => 70];
    public function prepare($q, ...$a) {
        if (count($a) === 1 && is_array($a[0])) { $a = $a[0]; }
        $i = 0;
        return preg_replace_callback('/%[ds]/', function ($m) use (&$i, $a) { $v = $a[$i++] ?? ''; return $m[0] === '%d' ? (string) (int) $v : "'" . addslashes((string) $v) . "'"; }, $q);
    }
    public function get_var($q) {
        if (strpos($q, 'SELECT slug FROM') !== false && preg_match('/id = (\d+)/', $q, $m)) { return $this->slug_by_id[(int) $m[1]] ?? null; }
        if (strpos($q, 'SELECT id FROM') !== false && preg_match("/slug = '([^']+)'/", $q, $m)) { $k = array_search($m[1], $this->slug_by_id, true); return $k === false ? 0 : $k; }
        if (strpos($q, 'SELECT ur.role_id FROM') !== false && preg_match('/ur\.user_id = (\d+)/', $q, $m)) { return $this->user_role[(int) $m[1]] ?? null; }
        if (strpos($q, 'SELECT allowed FROM') !== false) { return null; }
        return null;
    }
    public function get_row($q) { if (preg_match('/ur\.user_id = (\d+)/', $q, $m) && isset($this->user_role[(int) $m[1]])) { $r = $this->user_role[(int) $m[1]]; return (object) ['id' => $r, 'slug' => $this->slug_by_id[$r] ?? '']; } return null; }
    public function get_results($q) { if (strpos($q, 'ur.escola_id') !== false) { return [(object) ['user_id' => 5, 'role_id' => 80]]; } return []; }
    public function query($q) { return true; }
}

global $wpdb;
$wpdb = new SigeNivMock();
require_once $root . '/includes/permissions-layer.php';

$falhas = [];
$ok = function (bool $cond, string $msg) use (&$falhas) { if (!$cond) { $falhas[] = $msg; } };

// E1: sem desvios, vale o mapa base.
$ok(sige_permissions_role_nivel('admin_ti') === 80, 'E1 sem desvio admin_ti deve ser 80 (base)');
$ok(sige_permissions_role_nivel('professor') === 30, 'E1 sem desvio professor deve ser 30 (base)');

// E2: gravar desvios guarda so o que difere do base.
$r = sige_permissions_guardar_niveis(['admin_ti' => 85, 'professor' => 30, 'director' => 75]);
$ok(($r['desvios'] ?? -1) === 2, 'E2 deve guardar 2 desvios (professor=base nao conta)');
$ok(sige_permissions_role_nivel('admin_ti') === 85, 'E2 admin_ti editado deve ser 85');
$ok(sige_permissions_role_nivel('professor') === 30, 'E2 professor sem desvio deve manter 30');
$ok(sige_permissions_role_nivel('director') === 75, 'E2 director editado deve ser 75');

// E3: o avaliador respeita imediatamente os niveis editados.
// admin_ti (5) agora a 85; director (8) agora a 75. admin_ti atribui director (75) a professor (7) -> 75 < 85 -> permitido.
$GLOBALS['__actor'] = 5;
$res = sige_permissions_avaliar_operacao(5, 7, 'assign_user_role', 70, 3);
$ok($res['permitido'], 'E3 com niveis editados admin_ti(85) deve poder atribuir director(75)');

// E4: voltar um valor ao base remove o desvio.
$r = sige_permissions_guardar_niveis(['admin_ti' => 80]);
$over = $GLOBALS['__opt']['sige_permissions_niveis_overrides'] ?? [];
$ok(!isset($over['admin_ti']), 'E4 repor ao base deve remover o desvio de admin_ti');
$ok(isset($over['director']), 'E4 outros desvios devem manter-se');
$ok(sige_permissions_role_nivel('admin_ti') === 80, 'E4 admin_ti deve voltar a 80 (base)');

// E5: validacao 0..100 (clamp).
sige_permissions_guardar_niveis(['professor' => 250, 'guarda' => -10]);
$ok(sige_permissions_role_nivel('professor') === 100, 'E5 professor=250 deve ser limitado a 100');
$ok(sige_permissions_role_nivel('guarda') === 0, 'E5 guarda=-10 deve ser limitado a 0');

// E6: entradas invalidas sao ignoradas (nao numericas, slug vazio).
$antes = $GLOBALS['__opt']['sige_permissions_niveis_overrides'] ?? [];
sige_permissions_guardar_niveis(['' => 50, 'professor' => 'abc']);
$depois = $GLOBALS['__opt']['sige_permissions_niveis_overrides'] ?? [];
$ok($antes === $depois, 'E6 entradas invalidas (slug vazio, nao numerico) nao devem alterar nada');

if (!empty($falhas)) {
    echo "SMOKE PERMISSOES-NIVEIS-EDITAVEIS FALHOU:\n";
    foreach ($falhas as $f) echo " - {$f}\n";
    exit(1);
}
echo "SMOKE PERMISSOES-NIVEIS-EDITAVEIS OK - base mais desvios, gravacao guarda so desvios, repor ao base remove o desvio, validacao 0..100, entradas invalidas ignoradas, avaliador respeita os niveis editados.\n";
exit(0);
