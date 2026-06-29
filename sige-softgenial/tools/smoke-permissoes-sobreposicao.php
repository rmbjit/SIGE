<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke da sobreposicao reversivel do papel WordPress (Fase 9 incremento 3).
 *
 * Valida que o papel WordPress original e preservado antes de ser substituido,
 * que a preservacao e idempotente, que a reposicao devolve o original e limpa a
 * copia, os casos de borda (estado legado sige_*, sem copia, multiplos papeis), e
 * que a sincronizacao no init repoe o original quando o perfil sai mas o
 * utilizador ainda esta num papel sige_*.
 */

$root = dirname(__DIR__);
if (!defined('ABSPATH')) { define('ABSPATH', '/tmp/'); }

if (!function_exists('sanitize_key')) { function sanitize_key($s) { return preg_replace('/[^a-z0-9_]/', '', strtolower((string) $s)); } }
if (!function_exists('apply_filters')) { function apply_filters($t, $v, ...$a) { return $v; } }
if (!function_exists('add_action')) { function add_action(...$a) {} }
if (!function_exists('add_filter')) { function add_filter(...$a) {} }
if (!function_exists('get_option')) { function get_option($k, $d = false) { return $k === 'default_role' ? 'subscriber' : $d; } }
if (!function_exists('current_time')) { function current_time($t = 'mysql') { return gmdate('Y-m-d H:i:s'); } }
if (!function_exists('is_user_logged_in')) { function is_user_logged_in() { return true; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id() { return $GLOBALS['__current'] ?? 0; } }
if (!function_exists('user_can')) { function user_can($u, $c) { return false; } }

$GLOBALS['__meta'] = [];
if (!function_exists('get_user_meta')) { function get_user_meta($id, $k, $s = false) { return $GLOBALS['__meta'][$id][$k] ?? ''; } }
if (!function_exists('update_user_meta')) { function update_user_meta($id, $k, $v) { $GLOBALS['__meta'][$id][$k] = $v; return true; } }
if (!function_exists('delete_user_meta')) { function delete_user_meta($id, $k) { unset($GLOBALS['__meta'][$id][$k]); return true; } }

if (!class_exists('WP_User')) {
    class WP_User {
        public $ID; public $roles = [];
        public function __construct($id, $roles) { $this->ID = $id; $this->roles = $roles; }
        public function set_role($r) { $this->roles = [$r]; }
        public function add_role($r) { if (!in_array($r, $this->roles, true)) { $this->roles[] = $r; } }
    }
}
$GLOBALS['__users'] = [
    10 => new WP_User(10, ['professor_legado']),
    11 => new WP_User(11, ['sige_professor']),
    13 => new WP_User(13, ['editor', 'author']),
    20 => new WP_User(20, ['sige_admin_ti']),
];
if (!function_exists('get_user_by')) { function get_user_by($f, $id) { return $GLOBALS['__users'][$id] ?? false; } }

// Stubs para a sincronizacao no init.
$GLOBALS['__active_role'] = []; // user_id => slug ou null
if (!function_exists('sige_permissions_is_super_admin')) { function sige_permissions_is_super_admin($id) { return false; } }
if (!function_exists('sige_rh_user_is_active_for_school')) { function sige_rh_user_is_active_for_school($id) { return true; } }
if (!function_exists('sige_permissions_get_active_role')) {
    function sige_permissions_get_active_role($id) { $s = $GLOBALS['__active_role'][$id] ?? null; return $s ? (object) ['slug' => $s] : null; }
}

$GLOBALS['wpdb'] = new class {
    public $prefix = 'wp_';
    public function prepare($q, ...$a) { return $q; }
    public function get_var($q) { return null; }
    public function get_row($q) { return null; }
    public function get_results($q) { return []; }
    public function update(...$a) { return 1; }
    public function insert(...$a) { return 1; }
};

require_once $root . '/includes/permissions-layer.php';

$falhas = [];
$ok = function (bool $cond, string $msg) use (&$falhas) { if (!$cond) { $falhas[] = $msg; } };

// S1: preservar o original antes de substituir.
sige_permissions_backup_wp_roles(10);
$ok(($GLOBALS['__meta'][10]['_sige_wp_roles_backup'] ?? null) === ['professor_legado'], 'S1 copia deve guardar o papel original');
$GLOBALS['__users'][10]->set_role('sige_professor');
// S2: idempotencia - segunda chamada nao sobrescreve.
$GLOBALS['__users'][10]->roles = ['sige_admin_ti'];
sige_permissions_backup_wp_roles(10);
$ok(($GLOBALS['__meta'][10]['_sige_wp_roles_backup'] ?? null) === ['professor_legado'], 'S2 copia nao deve ser sobrescrita');
// S3: repor devolve o original e limpa a copia.
sige_permissions_restore_wp_roles(10);
$ok($GLOBALS['__users'][10]->roles === ['professor_legado'], 'S3 reposicao deve devolver o original');
$ok(empty($GLOBALS['__meta'][10]['_sige_wp_roles_backup']), 'S3 copia deve ser limpa apos repor');

// S4: estado legado (so sige_*) - copia assume o papel por omissao.
sige_permissions_backup_wp_roles(11);
$ok(($GLOBALS['__meta'][11]['_sige_wp_roles_backup'] ?? null) === ['subscriber'], 'S4 estado legado deve assumir o papel por omissao');

// S5: repor sem copia nenhuma - papel por omissao.
$GLOBALS['__users'][12] = new WP_User(12, ['sige_guarda']);
sige_permissions_restore_wp_roles(12);
$ok($GLOBALS['__users'][12]->roles === ['subscriber'], 'S5 reposicao sem copia deve usar o papel por omissao');

// S6: multiplos papeis originais - primeiro substitui, restantes acrescentam.
sige_permissions_backup_wp_roles(13);
$ok(($GLOBALS['__meta'][13]['_sige_wp_roles_backup'] ?? null) === ['editor', 'author'], 'S6 copia deve guardar os varios papeis');
$GLOBALS['__users'][13]->set_role('sige_secretario');
sige_permissions_restore_wp_roles(13);
$ok($GLOBALS['__users'][13]->roles === ['editor', 'author'], 'S6 reposicao deve devolver os varios papeis');

// S7: sincronizacao no init repoe quando o perfil sai mas ainda ha papel sige_*.
$GLOBALS['__current'] = 20;
$GLOBALS['__active_role'][20] = null; // sem perfil activo
$GLOBALS['__meta'][20]['_sige_wp_roles_backup'] = ['vendedor_legado'];
sige_permissions_sync_current_user_legacy_role();
$ok($GLOBALS['__users'][20]->roles === ['vendedor_legado'], 'S7 init deve repor o original quando o perfil sai');
$ok(empty($GLOBALS['__meta'][20]['_sige_wp_roles_backup']), 'S7 init deve limpar a copia ao repor');

// S8: sob a sobreposicao aditiva, o init limpa sempre um papel sige_* de staff
// legado, mesmo havendo perfil activo (o modulo nao mantem papeis sige_*; as
// capacidades vem do filtro user_has_cap). Sem copia, repoe o papel por omissao.
$GLOBALS['__users'][21] = new WP_User(21, ['sige_professor']);
$GLOBALS['__current'] = 21;
$GLOBALS['__active_role'][21] = 'professor';
sige_permissions_sync_current_user_legacy_role();
$ok($GLOBALS['__users'][21]->roles === ['subscriber'], 'S8 init limpa o papel sige_* de staff legado (capacidades vem do filtro)');

if (!empty($falhas)) {
    echo "SMOKE PERMISSOES-SOBREPOSICAO FALHOU:\n";
    foreach ($falhas as $f) echo " - {$f}\n";
    exit(1);
}
echo "SMOKE PERMISSOES-SOBREPOSICAO OK - original preservado e idempotente, reposicao devolve o original e limpa a copia, estado legado e sem copia usam o papel por omissao, multiplos papeis repostos, init repoe a partir da copia e limpa sempre papel sige_* de staff legado (sobreposicao aditiva).\n";
exit(0);
