<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
if (!defined('SIGE_SECURITY_KERNEL_TEST_MODE')) define('SIGE_SECURITY_KERNEL_TEST_MODE', true);
if (!defined('SIGE_SECURITY_KERNEL_OBSERVE_LOG')) define('SIGE_SECURITY_KERNEL_OBSERVE_LOG', true);
if (!defined('ABSPATH')) define('ABSPATH', $root . '/');

$GLOBALS['sige_sk_test_actions'] = [];
$GLOBALS['sige_sk_test_filters'] = [];
$GLOBALS['sige_sk_test_logs'] = [];
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['sige_sk_test_actions'][] = [$hook, (int)$priority, (int)$accepted_args, is_callable($callback) ? $callback : null];
        return true;
    }
}
if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['sige_sk_test_filters'][] = [$hook, (int)$priority, (int)$accepted_args, is_callable($callback) ? $callback : null];
        return true;
    }
}
if (!function_exists('is_user_logged_in')) { function is_user_logged_in() { return true; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id() { return 202; } }
if (!function_exists('sige_get_escola_id')) { function sige_get_escola_id() { return 7; } }
if (!function_exists('sige_user_can_any_secure')) { function sige_user_can_any_secure($permissions, $legacy = []) { return true; } }
if (!function_exists('sige_rate_limit_action')) { function sige_rate_limit_action($key, $max, $window, $die = false) { return true; } }
if (!function_exists('wp_verify_nonce')) { function wp_verify_nonce($nonce, $action) { return $nonce === ('ok-' . $action); } }
if (!function_exists('sanitize_key')) { function sanitize_key($key) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string)$key)); } }
if (!function_exists('wp_unslash')) { function wp_unslash($value) { return $value; } }
if (!function_exists('current_filter')) { function current_filter() { return 'unit_test'; } }
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

foreach (['admin_init','parse_request','template_redirect'] as $hook) {
    $check(in_array($hook . '@-1000', $actions, true), "query handlers interceptados em {$hook}@-1000");
}
$check(in_array('send_headers@-1000', $actions, true), 'wp_hook send_headers interceptado antes de headers funcionais');
$check(in_array('rest_pre_dispatch@-1000/3', $filters, true), 'REST pre_dispatch usa prioridade antecipada');
$check(in_array('pre_do_shortcode_tag@-1000/4', $filters, true), 'shortcode pre_do usa prioridade antecipada');

$_GET = ['sige_print' => 'factura'];
$_REQUEST = $_GET;
sige_security_kernel_dispatch_query_handlers_on_hook('admin_init');
$observedPrint = false;
foreach ($GLOBALS['sige_sk_test_logs'] as $row) {
    if ($row[0] === 'security_kernel.observed' && strpos($row[1], 'surface_id=query_handler:sige_print') !== false && strpos($row[1], 'runtime_hook=admin_init') !== false) {
        $observedPrint = true;
    }
}
$check($observedPrint, 'sige_print passa pelo kernel em admin_init antes de handlers de impressao');

$_GET = ['sige_portaria_camera' => '1'];
$_REQUEST = $_GET;
sige_security_kernel_dispatch_query_handlers_on_hook('template_redirect');
$observedPortaria = false;
foreach ($GLOBALS['sige_sk_test_logs'] as $row) {
    if ($row[0] === 'security_kernel.observed' && strpos($row[1], 'surface_id=query_handler:sige_portaria_camera') !== false && strpos($row[1], 'runtime_hook=template_redirect') !== false) {
        $observedPortaria = true;
    }
}
$check($observedPortaria, 'sige_portaria_camera passa pelo kernel em template_redirect antes do render standalone');

$_GET = ['sige_desp_print' => '123', '_wpnonce' => 'ok-sige_desp_print'];
$_REQUEST = $_GET;
$check(sige_security_kernel_dispatch_query_handlers_on_hook('template_redirect') === null, 'sige_desp_print valido passa pelo dispatcher antecipado');

if ($fails) {
    fwrite(STDERR, 'SMOKE SECURITY KERNEL 12.12.6 FALHOU: ' . implode('; ', $fails) . "\n");
    exit(1);
}
echo "SMOKE SECURITY KERNEL 12.12.6 OK - {$oks} verificacoes passaram.\n";
