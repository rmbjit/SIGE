<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke da hierarquia de perfis por nivel (Fase 9 incremento 2).
 *
 * Executa o avaliador contra um $wpdb e helpers WP simulados, validando que um
 * actor nao protegido so atribui perfis abaixo do seu nivel e so mexe em
 * utilizadores abaixo do seu nivel; que a regra anti-escalada do Incr 1 mantem
 * precedencia; e que o administrador WordPress real mantem autoridade plena.
 */

$root = dirname(__DIR__);
if (!defined('ABSPATH')) { define('ABSPATH', '/tmp/'); }

if (!function_exists('get_current_user_id')) { function get_current_user_id() { return $GLOBALS['__actor'] ?? 0; } }
if (!function_exists('sanitize_key')) { function sanitize_key($s) { return preg_replace('/[^a-z0-9_]/', '', strtolower((string) $s)); } }
if (!function_exists('user_can')) { function user_can($u, $c) { return false; } }
if (!function_exists('update_option')) { function update_option($k, $v, $a = null) { return true; } }
if (!function_exists('get_option')) { function get_option($k, $d = false) { return $d; } }
if (!function_exists('add_action')) { function add_action(...$a) {} }
if (!function_exists('add_filter')) { function add_filter(...$a) {} }
if (!function_exists('apply_filters')) { function apply_filters($t, $v, ...$a) { return $v; } }

$GLOBALS['__protegidos'] = [1 => true];
if (!function_exists('sige_is_real_wp_admin_user')) {
    function sige_is_real_wp_admin_user($id = null) { $id = $id ?: ($GLOBALS['__actor'] ?? 0); return !empty($GLOBALS['__protegidos'][(int) $id]); }
}

/**
 * Perfis (id usado tambem como nivel-base no mock, por conveniencia):
 *   90=direccao_geral (gestor), 90b? usamos 91=admin_escola (gestor),
 *   80=admin_ti (gestor), 70=director, 70b=gestor_rh, 50=secretaria, 30=professor.
 * Perfil activo por utilizador (get_var ur.role_id e get_row):
 *   2=direccao_geral, 5=admin_ti, 6=director, 7=professor, 8=secretaria,
 *   9=admin_ti (segundo admin_ti, para teste de pares).
 */
class SigeHierMock {
    public $prefix = 'wp_';
    public $slug_by_id = [90 => 'direccao_geral', 91 => 'admin_escola', 80 => 'admin_ti', 70 => 'director', 71 => 'gestor_rh', 50 => 'secretaria', 30 => 'professor'];
    public $user_role = [2 => 90, 5 => 80, 6 => 70, 7 => 30, 8 => 50, 9 => 80];
    public $gestores = [90, 91, 80]; // ids cujo slug confere gestao
    public $assignments = [['user_id' => 2, 'role_id' => 90], ['user_id' => 5, 'role_id' => 80], ['user_id' => 9, 'role_id' => 80]];

    public function prepare($q, ...$a) {
        if (count($a) === 1 && is_array($a[0])) { $a = $a[0]; }
        $i = 0;
        return preg_replace_callback('/%[ds]/', function ($m) use (&$i, $a) {
            $v = $a[$i++] ?? '';
            return $m[0] === '%d' ? (string) (int) $v : "'" . addslashes((string) $v) . "'";
        }, $q);
    }
    public function get_var($q) {
        if (strpos($q, 'SELECT slug FROM') !== false && preg_match('/id = (\d+)/', $q, $m)) {
            return $this->slug_by_id[(int) $m[1]] ?? null;
        }
        if (strpos($q, 'SELECT id FROM') !== false && preg_match("/slug = '([^']+)'/", $q, $m)) {
            $k = array_search($m[1], $this->slug_by_id, true);
            return $k === false ? 0 : $k;
        }
        if (strpos($q, 'SELECT ur.role_id FROM') !== false && preg_match('/ur\.user_id = (\d+)/', $q, $m)) {
            return $this->user_role[(int) $m[1]] ?? null;
        }
        if (strpos($q, 'SELECT allowed FROM') !== false) { return null; }
        return null;
    }
    public function get_row($q) {
        if (preg_match('/ur\.user_id = (\d+)/', $q, $m) && isset($this->user_role[(int) $m[1]])) {
            $rid = $this->user_role[(int) $m[1]];
            return (object) ['id' => $rid, 'slug' => $this->slug_by_id[$rid] ?? ''];
        }
        return null;
    }
    public function get_results($q) {
        if (strpos($q, 'ur.escola_id') !== false) {
            return array_map(function ($a) { return (object) $a; }, $this->assignments);
        }
        return [];
    }
    public function query($q) { return true; }
}

global $wpdb;
$wpdb = new SigeHierMock();
require_once $root . '/includes/permissions-layer.php';

// Forcar a deteccao de gestao por id, coerente com o mock (slugs gestores).
add_filter('sige_permissions_role_niveis', function ($n) {
    // Garante os niveis usados nos cenarios mesmo que o mapa real mude.
    $n['direccao_geral'] = 90; $n['admin_escola'] = 90; $n['admin_ti'] = 80;
    $n['director'] = 70; $n['gestor_rh'] = 70; $n['secretaria'] = 50; $n['professor'] = 30;
    return $n;
});

$falhas = [];
$ok = function (bool $cond, string $msg) use (&$falhas) { if (!$cond) { $falhas[] = $msg; } };

// Sanidade dos niveis.
$ok(sige_permissions_role_nivel('admin_ti') === 80, 'nivel admin_ti deve ser 80');
$ok(sige_permissions_role_nivel('professor') === 30, 'nivel professor deve ser 30');
$ok(sige_permissions_role_nivel('xpto_desconhecido') === 0, 'perfil desconhecido deve ser nivel 0');

// H1: admin_ti (5, nivel 80) atribui director (70) a professor (7, nivel 30) -> permitido.
$GLOBALS['__actor'] = 5;
$r = sige_permissions_avaliar_operacao(5, 7, 'assign_user_role', 70, 3);
$ok($r['permitido'], 'H1 admin_ti deve poder atribuir director a um professor');

// H2: admin_ti (80) atribui secretaria (50) a uma direccao_geral (2, nivel 90) -> nivel_insuficiente (alvo acima).
$r = sige_permissions_avaliar_operacao(5, 2, 'assign_user_role', 50, 3);
$ok(!$r['permitido'] && $r['codigo'] === 'nivel_insuficiente', 'H2 nao deve mexer em alvo de nivel superior');

// H3: director-actor (6, nivel 70) atribui gestor_rh (70) -> nivel_insuficiente (perfil ao mesmo nivel).
$GLOBALS['__actor'] = 6;
$r = sige_permissions_avaliar_operacao(6, 7, 'assign_user_role', 71, 3);
$ok(!$r['permitido'] && $r['codigo'] === 'nivel_insuficiente', 'H3 nao deve atribuir perfil de nivel igual ao seu');

// H4: admin_ti (80) remove uma direccao_geral (2, nivel 90) -> nivel_insuficiente (alvo acima).
$GLOBALS['__actor'] = 5;
$r = sige_permissions_avaliar_operacao(5, 2, 'unassign_user_role', 0, 3);
$ok(!$r['permitido'] && $r['codigo'] === 'nivel_insuficiente', 'H4 nao deve remover alvo de nivel superior');

// H5: admin_ti (80) atribui professor (30) a outro admin_ti (9, nivel 80) -> nivel_insuficiente (par, nao estritamente abaixo).
$r = sige_permissions_avaliar_operacao(5, 9, 'assign_user_role', 30, 3);
$ok(!$r['permitido'] && $r['codigo'] === 'nivel_insuficiente', 'H5 nao deve mexer num par do mesmo nivel');

// H6: admin_ti (80) tenta atribuir admin_escola (91, gestor) -> escalada_gestao tem precedencia sobre o nivel.
$r = sige_permissions_avaliar_operacao(5, 7, 'assign_user_role', 91, 3);
$ok(!$r['permitido'] && $r['codigo'] === 'escalada_gestao', 'H6 anti-escalada deve manter precedencia');

// H7: direccao_geral (2, nivel 90) atribui director (70) a um admin_ti (5, nivel 80) -> permitido (alvo abaixo, perfil nao-gestor abaixo).
$GLOBALS['__actor'] = 2;
$r = sige_permissions_avaliar_operacao(2, 5, 'assign_user_role', 70, 3);
$ok($r['permitido'], 'H7 a direccao geral deve poder gerir um admin_ti com um perfil nao-gestor abaixo');

// H8: actor protegido (1) atribui o que quiser, incluindo gestor, a qualquer nao protegido.
$GLOBALS['__actor'] = 1;
$r = sige_permissions_avaliar_operacao(1, 2, 'assign_user_role', 91, 3);
$ok($r['permitido'], 'H8 administrador WordPress mantem autoridade plena');

if (!empty($falhas)) {
    echo "SMOKE PERMISSOES-HIERARQUIA FALHOU:\n";
    foreach ($falhas as $f) echo " - {$f}\n";
    exit(1);
}
echo "SMOKE PERMISSOES-HIERARQUIA OK - so atribui e mexe estritamente abaixo do seu nivel, anti-escalada com precedencia, administrador WordPress com autoridade plena.\n";
exit(0);
