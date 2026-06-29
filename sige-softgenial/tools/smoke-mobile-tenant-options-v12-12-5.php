<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
if (!defined('ABSPATH')) define('ABSPATH', $root . '/');
$GLOBALS['sige_mobile_test_options'] = [];
if (!function_exists('get_option')) {
    function get_option($name, $default = false) { return array_key_exists($name, $GLOBALS['sige_mobile_test_options']) ? $GLOBALS['sige_mobile_test_options'][$name] : $default; }
}
if (!function_exists('update_option')) {
    function update_option($name, $value, $autoload = null) { $GLOBALS['sige_mobile_test_options'][$name] = $value; return true; }
}
if (!function_exists('is_user_logged_in')) { function is_user_logged_in() { return true; } }
if (!function_exists('sige_get_escola_id')) { function sige_get_escola_id() { return 7; } }
if (!function_exists('sige_multitenancy_strict_enabled')) { function sige_multitenancy_strict_enabled() { return true; } }
if (!function_exists('sige_multitenancy_active_school_count')) { function sige_multitenancy_active_school_count() { return 2; } }

require_once $root . '/includes/payments/mobile-tenant-options.php';
$fails = [];
$oks = 0;
$check = static function (bool $cond, string $label) use (&$fails, &$oks): void {
    if ($cond) { $oks++; echo "OK   {$label}\n"; }
    else { $fails[] = $label; echo "FAIL {$label}\n"; }
};

$_REQUEST['escola_id'] = 9;
$check(sige_mobile_payment_current_school_id() === 7, 'admin logado usa escola do utilizador, nao escola_id injectado no request');
unset($_REQUEST['escola_id']);
$check(sige_mobile_payment_scoped_option_name('mpesa', 'api_key', 7) === 'sige_mpesa_escola_7_api_key', 'nome scoped M-Pesa correcto');
$check(sige_mobile_payment_scoped_option_name('emola', 'api_key', 7) === 'sige_emola_escola_7_api_key', 'nome scoped e-Mola correcto');
sige_mobile_payment_update_option('mpesa', 'api_key', 'MPESA-K', false, 7);
sige_mobile_payment_update_option('emola', 'api_key', 'EMOLA-K', false, 7);
$check(($GLOBALS['sige_mobile_test_options']['sige_mpesa_escola_7_api_key'] ?? '') === 'MPESA-K', 'M-Pesa escreve option scoped por escola');
$check(($GLOBALS['sige_mobile_test_options']['sige_emola_escola_7_api_key'] ?? '') === 'EMOLA-K', 'e-Mola escreve option scoped por escola');
$check(!array_key_exists('sige_mpesa_api_key', $GLOBALS['sige_mobile_test_options']), 'M-Pesa nao sobrescreve global em multi-escola estrito');
$check(!array_key_exists('sige_emola_api_key', $GLOBALS['sige_mobile_test_options']), 'e-Mola nao sobrescreve global em multi-escola estrito');
$check(sige_mobile_payment_get_option('mpesa', 'api_key', '', 7) === 'MPESA-K', 'M-Pesa le option scoped');
$check(sige_mobile_payment_get_option('emola', 'api_key', '', 7) === 'EMOLA-K', 'e-Mola le option scoped');
sige_mobile_payment_update_option('mpesa', 'webhook_token', 'tok-mpesa', false, 7);
sige_mobile_payment_update_option('emola', 'webhook_token', 'tok-emola', false, 7);
$check(sige_mobile_payment_find_school_by_token('mpesa', 'tok-mpesa', 7) === 7, 'M-Pesa resolve escola por token scoped');
$check(sige_mobile_payment_find_school_by_token('emola', 'tok-emola', 7) === 7, 'e-Mola resolve escola por token scoped');

if ($fails) {
    fwrite(STDERR, 'SMOKE MOBILE TENANT OPTIONS FALHOU: ' . implode('; ', $fails) . "\n");
    exit(1);
}
echo "SMOKE MOBILE TENANT OPTIONS OK - {$oks} verificacoes passaram.\n";
