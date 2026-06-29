<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

/**
 * Gate: Painel de controlo de seguranca (MFA) - v12.12.13.
 * Garante que o ecra existe, usa o gate canonico de super admin, o handler tem
 * nonce + gate + sanitizacao + auditoria (incluindo o evento de desligar o
 * step-up), o endpoint esta governado (manifesto 196 e regra) e, em runtime, que
 * o Admin IT e recusado e o administrador real acede.
 */
$root = dirname(__DIR__);
$fails = [];
$read = static function (string $rel) use ($root): string {
    $abs = $root . '/' . $rel;
    return is_file($abs) ? (string) file_get_contents($abs) : '';
};

// Stubs minimos + gate real para o teste runtime.
define('SIGE_MFA_SETTINGS_TEST_MODE', true);
define('ABSPATH', '/tmp/');
$GLOBALS['opt'] = [];
$GLOBALS['cur_uid'] = 0;
$GLOBALS['users'] = [];
if (!function_exists('add_filter'))          { function add_filter(...$a){ return true; } }
if (!function_exists('add_action'))          { function add_action(...$a){ return true; } }
if (!function_exists('get_option'))          { function get_option($k,$d=false){ return $GLOBALS['opt'][$k] ?? $d; } }
if (!function_exists('update_option'))       { function update_option($k,$v){ $GLOBALS['opt'][$k]=$v; return true; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id(){ return (int)$GLOBALS['cur_uid']; } }
if (!function_exists('is_multisite'))        { function is_multisite(){ return false; } }
if (!function_exists('get_users'))           { function get_users($a=[]){ return []; } }
if (!function_exists('sige_security_log'))   { function sige_security_log($e,$c=''){} }
if (!function_exists('get_userdata'))        {
    function get_userdata($id){ $id=(int)$id; if ($id<=0 || !isset($GLOBALS['users'][$id])) return false; return (object)['ID'=>$id,'roles'=>$GLOBALS['users'][$id],'user_login'=>'u'.$id]; }
}

$modulo = $root . '/includes/security-mfa-settings.php';
if (!file_exists($modulo)) { fwrite(STDERR, "modulo includes/security-mfa-settings.php ausente\n"); exit(1); }
require_once $root . '/includes/security-hardening.php';
require_once $modulo;

$mod = $read('includes/security-mfa-settings.php');

// 1. Primitivas ----------------------------------------------------------------
foreach (['sige_mfa_settings_can_manage','sige_mfa_settings_compute','sige_mfa_settings_role_choices','sige_mfa_settings_handle_save','sige_mfa_settings_render_page'] as $fn) {
    if (!function_exists($fn)) $fails[] = "primitiva ausente: {$fn}()";
}

// 2. Gate canonico de super admin ----------------------------------------------
if (strpos($mod, 'sige_is_real_wp_admin_user') === false) $fails[] = 'o painel nao usa o gate canonico sige_is_real_wp_admin_user';
if (strpos($mod, 'manage_options') !== false && strpos($mod, "get_option('sige_mfa_stepup')") !== false) { /* manage_options so como capability do menu, aceitavel */ }

// 3. Handler: nonce + gate + sanitizacao + auditoria ---------------------------
if (strpos($mod, "check_admin_referer('sige_mfa_settings')") === false) $fails[] = 'handler sem verificacao de nonce';
if (strpos($mod, 'sige_mfa_settings_can_manage()') === false) $fails[] = 'handler/render sem o gate de acesso';
if (strpos($mod, 'wp_die(') === false) $fails[] = 'gate nao recusa com wp_die';
foreach (['sige_mfa_stepup','sige_mfa_autoreplay','sige_mfa_stepup_strict','sige_mfa_stepup_roles'] as $opt) {
    if (strpos($mod, "update_option('{$opt}'") === false) $fails[] = "handler nao grava a opcao {$opt}";
}
if (strpos($mod, "sige_security_log('mfa_settings_change'") === false) $fails[] = 'handler nao audita alteracoes';
if (strpos($mod, "sige_security_log('mfa_stepup_disabled'") === false) $fails[] = 'handler nao audita o desligar do step-up';

// 4. Sem $_GET prefixado sige_ (evita superficie nova) -------------------------
if (preg_match("/\\\$_(GET|REQUEST)\\s*\\[\\s*['\\\"]sige_/", $mod)) $fails[] = 'acesso GET/REQUEST prefixado sige_ cria superficie nao governada';

// 5. Carregamento e governanca -------------------------------------------------
if (strpos($read('sige-softgenial.php'), 'security-mfa-settings.php') === false) $fails[] = 'modulo nao carregado no bootstrap';
$mf = json_decode($read('docs/security/ACTION_SURFACE_MANIFEST-v12.12.13.json'), true);
if (!is_array($mf) || count($mf['items'] ?? []) !== 196) $fails[] = 'manifesto v12.12.13 nao tem 196 itens';
$ids = is_array($mf) ? array_column($mf['items'], 'id') : [];
if (!in_array('admin_post:sige_mfa_settings_save', $ids, true)) $fails[] = 'endpoint sige_mfa_settings_save ausente do manifesto';

// 6. Runtime: Admin IT recusado, administrador real acede ----------------------
if (function_exists('sige_mfa_settings_can_manage')) {
    $GLOBALS['cur_uid'] = 11; $GLOBALS['users'][11] = ['administrator'];
    if (sige_mfa_settings_can_manage() !== true) $fails[] = 'administrador real recusado pelo gate';
    $GLOBALS['cur_uid'] = 12; $GLOBALS['users'][12] = ['sige_admin_ti'];
    if (sige_mfa_settings_can_manage() !== false) $fails[] = 'Admin IT (sige_admin_ti) aceite pelo gate (deveria ser recusado)';
    $GLOBALS['cur_uid'] = 13; $GLOBALS['users'][13] = ['sige_director'];
    if (sige_mfa_settings_can_manage() !== false) $fails[] = 'sige_director aceite pelo gate (deveria ser recusado)';
}

if ($fails) {
    fwrite(STDERR, "MFA SETTINGS GATE FALHOU\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo "MFA SETTINGS OK - painel restrito ao super admin (Admin IT recusado em runtime), handler com nonce/gate/auditoria, endpoint governado (196).\n";
exit(0);
