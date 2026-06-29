<?php
// Acesso restrito: smoke corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SMOKE - Painel de controlo de seguranca (MFA) v12.12.13
 * Usa o gate REAL sige_is_real_wp_admin_user (carregado de security-hardening.php)
 * para provar que so o administrador WordPress real acede, e que o Admin IT
 * (sige_admin_ti) e os outros perfis nativos do SIGE sao recusados. Testa ainda
 * o calculo dos valores e os perfis abrangidos. Nao requer bootstrap do WordPress.
 */
$root = dirname(__DIR__);
$fail = [];
$ok = 0;
$check = static function (string $label, bool $cond) use (&$fail, &$ok) {
    if ($cond) { $ok++; echo "OK   {$label}\n"; }
    else { $fail[] = $label; echo "FAIL {$label}\n"; }
};

// ---- Stubs em memoria -------------------------------------------------------
define('SIGE_MFA_SETTINGS_TEST_MODE', true);
define('ABSPATH', '/tmp/');
$GLOBALS['opt'] = [];
$GLOBALS['cur_uid'] = 0;
$GLOBALS['users'] = []; // uid => roles[]
$GLOBALS['logs'] = [];
if (!function_exists('add_filter'))          { function add_filter(...$a){ return true; } }
if (!function_exists('add_action'))          { function add_action(...$a){ return true; } }
if (!function_exists('get_option'))          { function get_option($k,$d=false){ return $GLOBALS['opt'][$k] ?? $d; } }
if (!function_exists('update_option'))       { function update_option($k,$v){ $GLOBALS['opt'][$k]=$v; return true; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id(){ return (int)$GLOBALS['cur_uid']; } }
if (!function_exists('is_multisite'))        { function is_multisite(){ return false; } }
if (!function_exists('get_users'))           { function get_users($a=[]){ return []; } }
if (!function_exists('sige_security_log'))   { function sige_security_log($e,$c=''){ $GLOBALS['logs'][]=$e.'|'.$c; } }
if (!function_exists('get_userdata'))        {
    function get_userdata($id){
        $id=(int)$id;
        if ($id<=0 || !isset($GLOBALS['users'][$id])) return false;
        return (object)['ID'=>$id,'roles'=>$GLOBALS['users'][$id],'user_login'=>'u'.$id];
    }
}
if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user(){
        $id=(int)$GLOBALS['cur_uid'];
        return (object)['ID'=>$id,'roles'=>$GLOBALS['users'][$id] ?? []];
    }
}
$set_user = static function (int $uid, array $roles) {
    $GLOBALS['cur_uid'] = $uid;
    if ($uid > 0) $GLOBALS['users'][$uid] = $roles;
};

// Carrega o gate REAL (security-hardening.php) e depois o modulo do painel.
require $root . '/includes/security-hardening.php';
require $root . '/includes/security-mfa-settings.php';

$check('gate real sige_is_real_wp_admin_user carregado', function_exists('sige_is_real_wp_admin_user'));

// ---- 1. Acesso: so super admin real -----------------------------------------
$set_user(1, ['administrator']);
$check('ADMIN Super (administrator) acede', sige_mfa_settings_can_manage() === true);

$set_user(2, ['sige_admin_ti']);
$check('Admin IT (sige_admin_ti) e RECUSADO', sige_mfa_settings_can_manage() === false);

$set_user(3, ['sige_director']);
$check('sige_director e recusado', sige_mfa_settings_can_manage() === false);

$set_user(4, ['sige_financeiro', 'sige_secretario']);
$check('outros nativos do SIGE sao recusados', sige_mfa_settings_can_manage() === false);

$set_user(5, ['administrator', 'sige_director']);
$check('dono com administrator + perfil SIGE acede (administrator vence)', sige_mfa_settings_can_manage() === true);

$GLOBALS['cur_uid'] = 0; // ninguem
$check('sem sessao e recusado', sige_mfa_settings_can_manage() === false);

// ---- 2. Calculo dos valores (puro) ------------------------------------------
$r = sige_mfa_settings_compute([
    'sige_mfa_stepup' => 'on',
    'sige_mfa_autoreplay' => 'on',
    // strict ausente
    'sige_mfa_stepup_roles' => ['sige_director', 'sige_admin_ti', 'perfil_invalido', 'administrator'],
]);
$check('checkbox presente vale on', $r['sige_mfa_stepup'] === 'on' && $r['sige_mfa_autoreplay'] === 'on');
$check('checkbox ausente vale off', $r['sige_mfa_stepup_strict'] === 'off');
$check('perfis filtrados ao conjunto permitido', $r['sige_mfa_stepup_roles'] === ['sige_director', 'sige_admin_ti']);

$r2 = sige_mfa_settings_compute([]);
$check('post vazio: tudo off e perfis vazios', $r2['sige_mfa_stepup'] === 'off' && $r2['sige_mfa_autoreplay'] === 'off' && $r2['sige_mfa_stepup_strict'] === 'off' && $r2['sige_mfa_stepup_roles'] === []);

// ---- 3. Perfis abrangidos (alvos) -------------------------------------------
$choices = sige_mfa_settings_role_choices();
$check('Admin IT e um alvo possivel do step-up', isset($choices['sige_admin_ti']));
$check('Director e um alvo possivel do step-up', isset($choices['sige_director']));
$check('ha varios perfis alvo disponiveis', count($choices) >= 6);

// ---- Resultado ---------------------------------------------------------------
echo "\n";
if ($fail) {
    fwrite(STDERR, 'SMOKE PAINEL MFA FALHOU: ' . count($fail) . " falha(s)\n - " . implode("\n - ", $fail) . "\n");
    exit(1);
}
echo "SMOKE PAINEL MFA OK - {$ok} verificacoes passaram (gate de acesso real).\n";
exit(0);
