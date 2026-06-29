<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
if (!defined('SIGE_SECURITY_KERNEL_TEST_MODE')) define('SIGE_SECURITY_KERNEL_TEST_MODE', true);
if (!defined('ABSPATH')) define('ABSPATH', $root . '/');

$fails = [];
$oks = 0;
$check = static function (bool $cond, string $label) use (&$fails, &$oks): void {
    if ($cond) { $oks++; echo "OK   {$label}\n"; }
    else { $fails[] = $label; echo "FAIL {$label}\n"; }
};

if (!function_exists('sige_get_escola_id')) { function sige_get_escola_id() { return 0; } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($v) { return trim(strip_tags((string)$v)); } }
if (!function_exists('sanitize_email')) { function sanitize_email($v) { return trim((string)$v); } }
if (!function_exists('sanitize_key')) { function sanitize_key($v) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string)$v)); } }
if (!function_exists('sanitize_hex_color')) { function sanitize_hex_color($v) { return preg_match('/^#[0-9a-fA-F]{6}$/', (string)$v) ? (string)$v : ''; } }
if (!function_exists('esc_url_raw')) { function esc_url_raw($v) { return trim((string)$v); } }
if (!function_exists('wp_kses_post')) { function wp_kses_post($v) { return (string)$v; } }
if (!function_exists('is_email')) { function is_email($v) { return strpos((string)$v, '@') !== false; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); } }
if (!function_exists('get_bloginfo')) { function get_bloginfo($show = '') { return 'SoftGenial'; } }
if (!function_exists('current_time')) { function current_time($type = 'mysql') { return '2026-06-17 00:00:00'; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id() { return 202; } }
if (!function_exists('do_action')) { function do_action($hook, ...$args) { return null; } }
if (!function_exists('get_option')) { function get_option($name, $default = false) { return $default; } }
if (!function_exists('update_option')) { function update_option($name, $value, $autoload = null) { return true; } }

class SIGE_Settings_Tenant_Guard_Fake_WPDB {
    public $prefix = 'wp_';
    public $last_error = '';
    public $queries = [];
    public function prepare($query, ...$args) { $this->queries[] = ['prepare', $query, $args]; return $query; }
    public function get_var($query) {
        $this->queries[] = ['get_var', $query];
        if (strpos($query, 'SHOW TABLES LIKE') !== false) return 'wp_sige_config';
        if (strpos($query, 'SHOW COLUMNS FROM') !== false) return 'nome_escola';
        return null;
    }
    public function update($table, $data, $where) { $this->queries[] = ['update', $table, $data, $where]; return 1; }
    public function insert($table, $data) { $this->queries[] = ['insert', $table, $data]; return 1; }
    public function get_row($query) { return null; }
}
$GLOBALS['wpdb'] = new SIGE_Settings_Tenant_Guard_Fake_WPDB();

require_once $root . '/includes/settings/class-sige-settings-registry.php';
require_once $root . '/includes/settings/class-sige-settings-sanitizer.php';
require_once $root . '/includes/settings/class-sige-settings-repository.php';
require_once $root . '/includes/security-kernel-rules.php';

[$ok, $err] = SIGE_Settings_Repository::set('escola.nome', 'Escola Teste', ['actor' => 'unit']);
$check($ok === false, 'Repository bloqueia escrita sige_config sem escola resolvida');
$check(strpos($err, 'Escola não resolvida') !== false, 'Repository devolve erro fail-closed de tenant');
$mutating = array_filter($GLOBALS['wpdb']->queries, static function ($q) { return in_array($q[0], ['update','insert'], true); });
$check(count($mutating) === 0, 'Repository nao executa update/insert quando tenant falta');

$rules = sige_security_kernel_rules();
$settings = null;
foreach ($rules as $rule) {
    if (($rule['id'] ?? '') === 'wp_ajax:sige_settings_save') { $settings = $rule; break; }
}
$check(is_array($settings), 'Regra settings_save existe');
$check(!empty($settings['tenant_required']), 'Regra settings_save exige tenant_required=true');
$check(($settings['tenant_scope'] ?? '') === 'required_for_sige_config_writes', 'Regra settings_save declara tenant_scope de escrita sige_config');

if ($fails) {
    fwrite(STDERR, 'SMOKE SETTINGS TENANT GUARD 12.12.6 FALHOU: ' . implode('; ', $fails) . "\n");
    exit(1);
}
echo "SMOKE SETTINGS TENANT GUARD 12.12.6 OK - {$oks} verificacoes passaram.\n";
