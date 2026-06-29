<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke da sobreposicao puramente aditiva por capacidades (Fase 9 incremento 5).
 *
 * Valida que o filtro user_has_cap concede as capacidades do papel sige_* mapeado
 * ao perfil SIGE activo sem tocar no papel guardado; que ignora administradores
 * reais e utilizadores sem perfil; que caps_for_user devolve o conjunto certo
 * (incluindo a capacidade com o nome do papel e as em cascata); e que a guarda
 * anti-recursao se mantem.
 */

$root = dirname(__DIR__);
if (!defined('ABSPATH')) { define('ABSPATH', '/tmp/'); }

if (!class_exists('WP_User')) {
    class WP_User { public $ID = 0; public $roles = []; public function __construct($id = 0, $roles = []) { $this->ID = (int) $id; $this->roles = $roles; } }
}
if (!class_exists('WP_Role')) {
    class WP_Role { public $capabilities = []; public function __construct($c = []) { $this->capabilities = $c; } }
}
if (!function_exists('sanitize_key')) { function sanitize_key($s) { return preg_replace('/[^a-z0-9_]/', '', strtolower((string) $s)); } }
if (!function_exists('apply_filters')) { function apply_filters($t, $v, ...$a) { return $v; } }
if (!function_exists('add_action')) { function add_action(...$a) {} }
if (!function_exists('add_filter')) { function add_filter(...$a) {} }
if (!function_exists('get_option')) { function get_option($k, $d = false) { return $d; } }

$GLOBALS['__admin'] = [1 => true];
if (!function_exists('sige_is_real_wp_admin_user')) {
    function sige_is_real_wp_admin_user($id = null) { return !empty($GLOBALS['__admin'][(int) $id]); }
}

// Papeis WP mapeados, cada um com a capacidade do proprio nome e as em cascata.
$GLOBALS['__roles'] = [
    'sige_admin_ti'  => new WP_Role(['read' => true, 'upload_files' => true, 'sige_admin_ti' => true, 'sige_director' => true, 'sige_professor' => true, 'sige_financeiro' => true]),
    'sige_professor' => new WP_Role(['read' => true, 'sige_professor' => true]),
];
if (!function_exists('get_role')) { function get_role($s) { return $GLOBALS['__roles'][$s] ?? null; } }

// Perfil SIGE activo por utilizador (slug SIGE). 5=admin_ti, 7=professor, 9=sem perfil.
$GLOBALS['__active'] = [5 => 'admin_ti', 7 => 'professor'];
if (!function_exists('sige_permissions_tables')) { function sige_permissions_tables() { return ['user_roles' => 'ur', 'roles' => 'r']; } }
if (!function_exists('sige_permissions_get_active_role')) {
    function sige_permissions_get_active_role($uid = null, $eid = null) { $s = $GLOBALS['__active'][(int) $uid] ?? null; return $s ? (object) ['slug' => $s] : null; }
}
global $wpdb;
$wpdb = new class {
    public $prefix = 'wp_';
    public function prepare($q, ...$a) { if (count($a) === 1 && is_array($a[0])) { $a = $a[0]; } $i = 0; return preg_replace_callback('/%[ds]/', function ($m) use (&$i, $a) { $v = $a[$i++] ?? ''; return $m[0] === '%d' ? (string) (int) $v : "'" . addslashes((string) $v) . "'"; }, $q); }
    public function get_row($q) { if (preg_match('/user_id = (\d+)/', $q, $m)) { $u = (int) $m[1]; $s = $GLOBALS['__active'][$u] ?? null; return $s ? (object) ['slug' => $s] : null; } return null; }
    public function get_var($q) { return null; }
};

require_once $root . '/includes/permissions-layer.php';

$falhas = [];
$ok = function (bool $cond, string $msg) use (&$falhas) { if (!$cond) { $falhas[] = $msg; } };

// A1: caps_for_user devolve o conjunto completo do papel mapeado.
$c5 = sige_permissions_caps_for_user(5);
$ok(in_array('sige_admin_ti', $c5, true), 'A1 caps_for_user(admin_ti) deve incluir a capacidade do proprio nome');
$ok(in_array('sige_director', $c5, true) && in_array('sige_financeiro', $c5, true), 'A1 caps_for_user deve incluir as capacidades em cascata');

// A2: o filtro concede as capacidades mapeadas e preserva o papel guardado.
$u7 = new WP_User(7, ['subscriber']);
$all = sige_permissions_grant_caps_filter(['subscriber' => true], ['sige_professor'], ['sige_professor', 7], $u7);
$ok(!empty($all['sige_professor']), 'A2 filtro deve conceder sige_professor ao utilizador com perfil activo');
$ok(!empty($all['subscriber']), 'A2 filtro deve preservar o papel WordPress guardado (subscriber)');
$ok($u7->roles === ['subscriber'], 'A2 filtro nao deve alterar o papel guardado do utilizador');

// A3: administrador real nao e afectado.
$u1 = new WP_User(1, ['administrator']);
$all1 = sige_permissions_grant_caps_filter(['administrator' => true], ['x'], ['x', 1], $u1);
$ok($all1 === ['administrator' => true], 'A3 filtro nao deve mexer num administrador WordPress real');

// A4: utilizador sem perfil activo nao ganha nada.
$u9 = new WP_User(9, ['subscriber']);
$all9 = sige_permissions_grant_caps_filter(['subscriber' => true], ['x'], ['x', 9], $u9);
$ok($all9 === ['subscriber' => true], 'A4 filtro nao deve conceder nada a utilizador sem perfil activo');

// A5: guarda anti-recursao - uma chamada reentrante devolve $allcaps sem alteracao.
// Simula reentrada: um helper que volta a chamar o filtro nao deve provocar recursao infinita.
$reentrou = false;
$GLOBALS['__reentrada'] = function () use (&$reentrou) {
    $reentrou = true;
    $u = new WP_User(7, ['subscriber']);
    return sige_permissions_grant_caps_filter(['subscriber' => true], ['x'], ['x', 7], $u);
};
// Chamada normal seguida de reentrada manual (a guarda e por-chamada, reposta no finally).
$res1 = sige_permissions_grant_caps_filter(['subscriber' => true], ['sige_professor'], ['sige_professor', 7], new WP_User(7, ['subscriber']));
$res2 = ($GLOBALS['__reentrada'])();
$ok($reentrou && !empty($res2['sige_professor']), 'A5 apos uma chamada terminar, a guarda e reposta e a seguinte volta a conceder');

// A6: a identificacao de papel sige_* de staff distingue staff de portal.
$ok(sige_permissions_is_staff_wp_role('sige_professor') === true, 'A6 sige_professor e papel de staff');
$ok(sige_permissions_is_staff_wp_role('sige_aluno') === false, 'A6 sige_aluno NAO e papel de staff (portal)');
$ok(sige_permissions_is_staff_wp_role('sige_encarregado') === false, 'A6 sige_encarregado NAO e papel de staff (portal)');

if (!empty($falhas)) {
    echo "SMOKE PERMISSOES-ADITIVA FALHOU:\n";
    foreach ($falhas as $f) echo " - {$f}\n";
    exit(1);
}
echo "SMOKE PERMISSOES-ADITIVA OK - filtro concede as capacidades do papel mapeado e preserva o papel guardado, ignora administrador real e utilizador sem perfil, caps_for_user devolve o conjunto completo, guarda anti-recursao reposta por chamada, e staff distingue-se de portal.\n";
exit(0);
