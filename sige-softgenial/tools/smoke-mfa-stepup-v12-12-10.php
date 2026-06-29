<?php
// Acesso restrito: smoke corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SMOKE - MFA de Operacao (Step-up) (v12.12.10)
 * Asserts estaticos (modulo, primitivas, 10 pontos de integracao, regra de
 * Kernel, calculo intacto, baselines congelados) e runtime da logica fail-closed
 * com stubs. Nao requer bootstrap do WordPress.
 */
$root = dirname(__DIR__);
$fail = [];
$ok = 0;
$check = static function (string $label, bool $cond) use (&$fail, &$ok) {
    if ($cond) { $ok++; echo "OK   {$label}\n"; }
    else { $fail[] = $label; echo "FAIL {$label}\n"; }
};
$read = static function (string $rel) use ($root): string {
    $abs = $root . '/' . $rel;
    return is_file($abs) ? (string) file_get_contents($abs) : '';
};

// ---- 1. Estatico: modulo e integracao ---------------------------------------
$check('modulo security-mfa-stepup.php existe', is_file($root . '/includes/security-mfa-stepup.php'));
$check('modulo carregado apos o escudo de login', strpos($read('sige-softgenial.php'), "security-mfa-stepup.php") !== false);

$svc = $read('includes/fin-action-service.php');
$check('servico: 6 guards MFA', substr_count($svc, 'sige_mfa_require_step_up') >= 6);
foreach (['fin_cancelLancamento','fin_isentarLancamento','fin_reactivarLancamento','fin_bloquearMes','fin_desbloquearMes','fin_estornarPagamento'] as $c) {
    $check("servico: contexto {$c}", strpos($svc, "sige_mfa_require_step_up('{$c}')") !== false);
}
$check('credenciais e-Mola: guard cfg_emola', strpos($read('includes/payments/emola-config.php'), "sige_mfa_require_step_up('cfg_emola')") !== false);
$check('credenciais M-Pesa: guard cfg_mpesa', strpos($read('includes/payments/mpesa-config.php'), "sige_mfa_require_step_up('cfg_mpesa')") !== false);
$ext = $read('admin/finance/financeiro-extratos.php');
$check('caixa: guard reabrir', strpos($ext, "sige_mfa_require_step_up('caixa_reabrir')") !== false);
$check('caixa: guard fechar', strpos($ext, "sige_mfa_require_step_up('caixa_fechar')") !== false);

// ---- 2. Estatico: calculo intacto e baselines congelados --------------------
$fin = $read('includes/finance-core.php');
foreach (['sige_fin_saldo_lancamento','sige_fin_saldo_sql','sige_fin_total_bruto_sql'] as $calc) {
    $check("calculo intacto: {$calc}", strpos($fin, "function {$calc}") !== false);
}
$b81 = json_decode($read('docs/security/TENANT_FALLBACK_BASELINE-v12.12.8.1.json'), true);
$b80 = json_decode($read('docs/security/TENANT_FALLBACK_BASELINE-v12.12.8.json'), true);
$check('baseline tenant 12.12.8.1 congelado = 138', is_array($b81) && count($b81['items'] ?? []) === 138);
$check('baseline tenant 12.12.8 congelado = 139', is_array($b80) && count($b80['items'] ?? []) === 139);

// ---- 3. Estatico: regra de Kernel -------------------------------------------
$krules = $read('docs/security/SECURITY_KERNEL_RULES-v12.12.10.json');
$check('Kernel JSON v12.12.10 inclui admin_post:sige_mfa_confirm', strpos($krules, 'admin_post:sige_mfa_confirm') !== false);
$check('gate check-mfa-stepup ligado ao corredor', strpos($read('tools/run-gates.php'), 'check-mfa-stepup.php') !== false);

// ---- 4. Runtime: logica fail-closed com stubs -------------------------------
define('SIGE_MFA_TEST_MODE', true);
$GLOBALS['_opt'] = ['sige_mfa_stepup' => 'off', 'sige_mfa_stepup_roles' => ['sige_director']];
$GLOBALS['_trans'] = [];
$GLOBALS['_otp_pending'] = false;
$GLOBALS['_mail_ok'] = true;
$GLOBALS['_cur_uid'] = 77;
$GLOBALS['_roles'] = ['sige_director'];
if (!function_exists('get_option')) { function get_option($k,$d=false){ return $GLOBALS['_opt'][$k] ?? $d; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id(){ return $GLOBALS['_cur_uid']; } }
if (!function_exists('get_userdata')) { function get_userdata($id){ $o=new stdClass(); $o->roles=$GLOBALS['_roles']; $o->user_email='d@x.mz'; $o->display_name='Dir'; return $o; } }
if (!function_exists('get_transient')) { function get_transient($k){ return $GLOBALS['_trans'][$k] ?? false; } }
if (!function_exists('set_transient')) { function set_transient($k,$v,$t){ $GLOBALS['_trans'][$k]=$v; return true; } }
if (!function_exists('delete_transient')) { function delete_transient($k){ unset($GLOBALS['_trans'][$k]); return true; } }
if (!function_exists('wp_mail')) { function wp_mail($to,$s,$b){ return $GLOBALS['_mail_ok']; } }
if (!function_exists('sige_otp_issue')) { function sige_otp_issue($uid){ $GLOBALS['_otp_pending']=true; return '654321'; } }
if (!function_exists('sige_otp_verify')) { function sige_otp_verify($uid,$c){ if($c==='654321'){ $GLOBALS['_otp_pending']=false; return 'ok'; } return 'errado'; } }
if (!function_exists('sige_otp_pending')) { function sige_otp_pending($uid){ return $GLOBALS['_otp_pending']; } }
$GLOBALS['_log'] = [];
if (!function_exists('sige_security_log')) { function sige_security_log($e,$l){ $GLOBALS['_log'][] = "$e:$l"; } }

require_once $root . '/includes/security-mfa-stepup.php';

$primitivas = ['sige_mfa_stepup_enabled','sige_mfa_stepup_strict','sige_mfa_stepup_roles','sige_mfa_applies_to_user','sige_mfa_window_key','sige_mfa_recently_verified','sige_mfa_mark_verified','sige_mfa_pending','sige_mfa_send_challenge_email','sige_mfa_issue_challenge','sige_mfa_verify_challenge','sige_mfa_require_step_up','sige_mfa_render_challenge_form'];
$todas = true;
foreach ($primitivas as $fn) { if (!function_exists($fn)) $todas = false; }
$check('13 primitivas definidas', $todas);

$GLOBALS['_opt']['sige_mfa_stepup']='off';
$check('desligado: guard permite', sige_mfa_require_step_up('s') === true);

$GLOBALS['_opt']['sige_mfa_stepup']='on'; $GLOBALS['_roles']=['sige_secretaria'];
$check('fora do perfil: permite', sige_mfa_require_step_up('s') === true);

$GLOBALS['_roles']=['sige_director']; $GLOBALS['_trans']=[]; $GLOBALS['_otp_pending']=false; $GLOBALS['_mail_ok']=true;
$check('no perfil, nao verificado: BLOQUEIA (fail-closed)', sige_mfa_require_step_up('s') === false);
$check('desafio emitido (pendente)', sige_mfa_pending(77) === true);

sige_mfa_mark_verified(77);
$check('verificacao recente: permite', sige_mfa_require_step_up('s') === true);

$GLOBALS['_trans']=[]; $GLOBALS['_otp_pending']=false; $GLOBALS['_mail_ok']=false;
$check('email falha (anti-lockout): permite', sige_mfa_require_step_up('s') === true);

$GLOBALS['_trans']=[]; $GLOBALS['_otp_pending']=true;
$check('verify codigo certo -> ok + janela aberta', sige_mfa_verify_challenge(77,'654321') === 'ok' && sige_mfa_recently_verified(77) === true);
$GLOBALS['_otp_pending']=true;
$check('verify codigo errado -> errado', sige_mfa_verify_challenge(77,'000000') === 'errado');

// A5: passagem pela janela e auditada
$GLOBALS['_trans']=[]; sige_mfa_mark_verified(77); $GLOBALS['_log']=[];
$rA5 = sige_mfa_require_step_up('fin_estornarPagamento');
$check('A5: operacao pela janela auditada (satisfied_window)', $rA5 === true && count(array_filter($GLOBALS['_log'], fn($x)=>strpos($x,'satisfied_window')!==false))>0);

// A2: modo estrito + email falha BLOQUEIA; defeito permite (anti-lockout)
$GLOBALS['_opt']['sige_mfa_stepup_strict']='on'; $GLOBALS['_trans']=[]; $GLOBALS['_otp_pending']=false; $GLOBALS['_mail_ok']=false;
$check('A2: estrito + email falha -> BLOQUEIA', sige_mfa_require_step_up('fin_estornarPagamento') === false);
$GLOBALS['_opt']['sige_mfa_stepup_strict']='off'; $GLOBALS['_trans']=[]; $GLOBALS['_otp_pending']=false; $GLOBALS['_mail_ok']=false;
$check('A2: defeito + email falha -> permite (anti-lockout)', sige_mfa_require_step_up('fin_estornarPagamento') === true);

// A1: os 3 ficheiros de chamadores renderizam o formulario inline
$check('A1: lancamentos-view renderiza formulario', strpos($read('admin/finance/financeiro-lancamentos-view.php'), 'sige_mfa_render_challenge_form') !== false);
$check('A1: pagamentos renderiza formulario', substr_count($read('admin/finance/financeiro-pagamentos.php'), 'sige_mfa_render_challenge_form') >= 4);
$check('A1: extratos (anular_recibo) renderiza formulario', substr_count($read('admin/finance/financeiro-extratos.php'), 'sige_mfa_render_challenge_form') >= 3);

echo "\n";
if ($fail) {
    fwrite(STDERR, "SMOKE MFA STEP-UP FALHOU (" . count($fail) . "): " . implode('; ', $fail) . "\n");
    exit(1);
}
echo "SMOKE MFA STEP-UP OK - {$ok} verificacoes.\n";
