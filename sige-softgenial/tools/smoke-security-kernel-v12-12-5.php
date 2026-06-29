<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
if (!defined('SIGE_SECURITY_KERNEL_TEST_MODE')) define('SIGE_SECURITY_KERNEL_TEST_MODE', true);
if (!defined('ABSPATH')) define('ABSPATH', $root . '/');

$GLOBALS['sige_sk_test_actions'] = [];
$GLOBALS['sige_sk_test_filters'] = [];
$GLOBALS['sige_sk_test_logs'] = [];

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['sige_sk_test_actions'][] = [$hook, $priority, $accepted_args];
        return true;
    }
}
if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['sige_sk_test_filters'][] = [$hook, $priority, $accepted_args];
        return true;
    }
}
if (!function_exists('is_user_logged_in')) { function is_user_logged_in() { return true; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id() { return 101; } }
if (!function_exists('sige_get_escola_id')) { function sige_get_escola_id() { return 7; } }
if (!function_exists('sige_user_can_any_secure')) { function sige_user_can_any_secure($permissions, $legacy = []) { return true; } }
if (!function_exists('sige_rate_limit_action')) { function sige_rate_limit_action($key, $max, $window, $die = false) { return true; } }
if (!function_exists('wp_verify_nonce')) { function wp_verify_nonce($nonce, $action) { return $nonce === ('ok-' . $action); } }
if (!function_exists('sanitize_key')) { function sanitize_key($key) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string)$key)); } }
if (!function_exists('wp_unslash')) { function wp_unslash($value) { return $value; } }
if (!function_exists('sige_security_log')) { function sige_security_log($event, $line = '') { $GLOBALS['sige_sk_test_logs'][] = [$event, $line]; } }

require_once $root . '/includes/security-kernel.php';

$fails = [];
$oks = 0;
$check = static function (bool $cond, string $label) use (&$fails, &$oks): void {
    if ($cond) { $oks++; echo "OK   {$label}\n"; }
    else { $fails[] = $label; echo "FAIL {$label}\n"; }
};
$actions = array_map(static function ($row) { return $row[0] . '@' . $row[1]; }, $GLOBALS['sige_sk_test_actions']);
$filters = array_map(static function ($row) { return $row[0] . '@' . $row[1] . '/' . $row[2]; }, $GLOBALS['sige_sk_test_filters']);

foreach ([
    '/sige/v1/mpesa/callback' => 'rest_route:sige/v1:/mpesa/callback',
    '/sige/v1/emola/callback' => 'rest_route:sige/v1:/emola/callback',
    '/sige/v1/process-queue' => 'rest_route:sige/v1:/process-queue',
    '/sige/v1/whatsapp-webhook' => 'rest_route:sige/v1:/whatsapp-webhook',
    '/sige/v1/hub/instant-refresh' => 'rest_route:sige/v1:/hub/instant-refresh',
] as $route => $id) {
    $check(function_exists('sige_security_kernel_rest_surface_id_for_route') && sige_security_kernel_rest_surface_id_for_route($route) === $id, "REST {$route} mapeia para {$id}");
}

foreach (['admin_post_sige_mpesa_guardar_config','admin_post_sige_emola_guardar_config','wp_ajax_sige_settings_save'] as $hook) {
    $check(in_array($hook . '@0', $actions, true), "hook runtime registado {$hook}@0");
}
foreach (['template_redirect','admin_init','parse_request','send_headers'] as $hook) {
    $check(in_array($hook . '@-1000', $actions, true), "hook runtime antecipado registado {$hook}@-1000");
}
$check(function_exists('sige_security_kernel_dispatch_query_handlers_on_hook'), 'dispatcher multi-hook de query handlers existe');
$check(in_array('rest_pre_dispatch@-1000/3', $filters, true), 'filter rest_pre_dispatch antecipado com 3 argumentos');
$check(in_array('pre_do_shortcode_tag@-1000/4', $filters, true), 'filter pre_do_shortcode_tag antecipado com 4 argumentos');

$check(function_exists('sige_security_kernel_shortcode_pre') && sige_security_kernel_shortcode_pre(false, 'sige_portal', [], []) === false, 'shortcode:sige_portal despachavel sem substituir output em observe');
$check(function_exists('sige_security_kernel_dispatch_wp_hook') && sige_security_kernel_dispatch_wp_hook('template_redirect') === true, 'wp_hook:template_redirect despachavel');

$_POST['nonce'] = 'ok-sige_settings_save_v1';
$check(sige_security_kernel_dispatch('wp_ajax:sige_settings_save', ['transport' => 'ajax']) === true, 'dispatch enforce settings_save permite request valida');

if ($fails) {
    fwrite(STDERR, 'SMOKE SECURITY KERNEL 12.12.5 FALHOU: ' . implode('; ', $fails) . "\n");
    exit(1);
}
echo "SMOKE SECURITY KERNEL 12.12.5 OK - {$oks} verificacoes passaram.\n";
