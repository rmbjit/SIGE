<?php
// Acesso restrito: smoke corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SMOKE - MFA de Operacao: TOTP (v12.12.11)
 * Estatico (modulo, carregamento, regra de Kernel, manifesto, baselines) e
 * runtime com stubs: nucleo RFC 6238, QR, ciclo de inscricao e integracao no
 * step-up (inscrito nao gera email, aceita TOTP, tecto de tentativas). Nao
 * requer bootstrap do WordPress.
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

// ---- Stubs em memoria (sem WordPress) ---------------------------------------
define('SIGE_MFA_TOTP_TEST_MODE', true);
define('SIGE_MFA_TEST_MODE', true);
$GLOBALS['t_meta'] = [];
$GLOBALS['t_trans'] = [];
$GLOBALS['t_otp_issue_called'] = false;
if (!function_exists('get_user_meta'))    { function get_user_meta($uid,$key,$single=true){ return $GLOBALS['t_meta'][$uid][$key] ?? ''; } }
if (!function_exists('update_user_meta')) { function update_user_meta($uid,$key,$val){ $GLOBALS['t_meta'][$uid][$key]=$val; return true; } }
if (!function_exists('delete_user_meta')) { function delete_user_meta($uid,$key){ unset($GLOBALS['t_meta'][$uid][$key]); return true; } }
if (!function_exists('get_transient'))    { function get_transient($k){ return $GLOBALS['t_trans'][$k] ?? false; } }
if (!function_exists('set_transient'))    { function set_transient($k,$v,$ttl=0){ $GLOBALS['t_trans'][$k]=$v; return true; } }
if (!function_exists('delete_transient')) { function delete_transient($k){ unset($GLOBALS['t_trans'][$k]); return true; } }
if (!function_exists('sige_encrypt_token')){ function sige_encrypt_token($p){ return $p===''?'':'enc:'.base64_encode((string)$p); } }
if (!function_exists('sige_decrypt_token')){ function sige_decrypt_token($e){ return strpos((string)$e,'enc:')===0?(string)base64_decode(substr($e,4)):''; } }
if (!function_exists('sige_security_log')) { function sige_security_log($a,$b){ /* noop */ } }
if (!function_exists('get_userdata'))      { function get_userdata($uid){ $o=new stdClass(); $o->user_login='u'.$uid; $o->user_email='u'.$uid.'@x.mz'; return $o; } }
if (!function_exists('sige_otp_issue'))    { function sige_otp_issue($uid){ $GLOBALS['t_otp_issue_called']=true; return '000000'; } }

require $root . '/includes/security-mfa-totp.php';
require $root . '/includes/security-mfa-stepup.php';

// ---- 1. Estatico -------------------------------------------------------------
$check('modulo security-mfa-totp.php existe', is_file($root . '/includes/security-mfa-totp.php'));
$check('modulo carregado no bootstrap', strpos($read('sige-softgenial.php'), 'security-mfa-totp.php') !== false);
$rules = $read('includes/security-kernel-rules.php');
$check('regra de Kernel do endpoint presente', strpos($rules, "admin_post:sige_mfa_totp_enroll") !== false);
$check('regra com intent nonce sige_mfa_totp_enroll', strpos($rules, "'action' => 'sige_mfa_totp_enroll'") !== false);
$rulesJson = json_decode($read('docs/security/SECURITY_KERNEL_RULES-v12.12.11.json'), true);
$jsonIds = is_array($rulesJson) ? array_column($rulesJson['items'] ?? [], 'id') : [];
$check('endpoint no JSON de regras', in_array('admin_post:sige_mfa_totp_enroll', $jsonIds, true));
$manifest = json_decode($read('docs/security/ACTION_SURFACE_MANIFEST-v12.12.11.json'), true);
$mItems = is_array($manifest) ? ($manifest['items'] ?? []) : [];
$check('manifesto com 195 itens', count($mItems) === 195);
$check('endpoint no manifesto', in_array('admin_post:sige_mfa_totp_enroll', array_column($mItems, 'id'), true));
$check('Phase Charter v12.12.11 presente', is_file($root . '/docs/governance/PHASE_CHARTER-v12.12.11.md'));
$check('Adversarial Review v12.12.11 presente', is_file($root . '/docs/governance/ADVERSARIAL_REVIEW-v12.12.11.md'));

// ---- 2. Nucleo RFC 6238 ------------------------------------------------------
$seedB32 = sige_mfa_totp_base32_encode('12345678901234567890');
$check('base32 da semente RFC = GEZD...QOJQ', $seedB32 === 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
$vec = [[59,'287082'],[1111111109,'081804'],[1111111111,'050471'],[1234567890,'005924'],[2000000000,'279037'],[20000000000,'353130']];
$allVec = true;
foreach ($vec as $v) { if (sige_mfa_totp_code($seedB32, $v[0]) !== $v[1]) $allVec = false; }
$check('codigos TOTP batem os 6 vectores RFC 6238', $allVec);
$check('verify aceita o codigo actual', sige_mfa_totp_verify($seedB32, '050471', 1111111111));
$check('verify aceita o passo anterior (desvio relogio)', sige_mfa_totp_verify($seedB32, sige_mfa_totp_code($seedB32, 1111111111 - 30), 1111111111));
$check('verify rejeita codigo errado', !sige_mfa_totp_verify($seedB32, '000000', 1111111111));
$check('base32 round-trip', sige_mfa_totp_base32_decode($seedB32) === '12345678901234567890');

// ---- 3. QR -------------------------------------------------------------------
$uri = sige_mfa_totp_uri('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'director@escola.mz', 'SIGE SoftGenial');
$check('URI otpauth bem formado', strpos($uri, 'otpauth://totp/') === 0 && strpos($uri, 'algorithm=SHA1') !== false && strpos($uri, 'period=30') !== false);
$mat = sige_mfa_qr_matrix($uri);
$check('matriz QR gerada (nao nula)', is_array($mat));
if (is_array($mat)) {
    $v = sige_mfa_qr_pick_version(strlen($uri));
    $check('dimensao da matriz = 4*versao+17', count($mat) === ($v * 4 + 17));
    $check('matriz quadrada', count($mat) === count($mat[0]));
}
$svg = sige_mfa_totp_qr_svg($uri);
$check('SVG do QR comeca com <svg', strpos($svg, '<svg') === 0);

// ---- 4. Ciclo de inscricao ---------------------------------------------------
$uid = 4001;
$secret = sige_mfa_totp_generate_secret();
$check('segredo gerado com 32 caracteres base32 (160 bits)', strlen($secret) === 32);
$check('store_secret guarda cifrado', sige_mfa_totp_store_secret($uid, $secret));
$check('segredo guardado esta cifrado em meta (prefixo enc:)', strpos((string)($GLOBALS['t_meta'][$uid]['_sige_mfa_totp_secret'] ?? ''), 'enc:') === 0);
$check('ainda nao inscrito apos guardar', !sige_mfa_totp_enrolled($uid));
$check('tem segredo pendente', sige_mfa_totp_has_pending_secret($uid));
$check('confirm com codigo errado falha', !sige_mfa_totp_confirm_enrollment($uid, '000000'));
$check('continua nao inscrito apos codigo errado', !sige_mfa_totp_enrolled($uid));
$codeNow = sige_mfa_totp_code($secret);
$check('confirm com codigo certo activa', sige_mfa_totp_confirm_enrollment($uid, $codeNow));
$check('inscrito apos confirmar', sige_mfa_totp_enrolled($uid));
$check('ja nao ha segredo pendente', !sige_mfa_totp_has_pending_secret($uid));
$check('verify_user aceita codigo certo', sige_mfa_totp_verify_user($uid, sige_mfa_totp_code($secret)));
$check('verify_user rejeita codigo errado', !sige_mfa_totp_verify_user($uid, '111111'));
sige_mfa_totp_disable($uid);
$check('desactivar remove a inscricao', !sige_mfa_totp_enrolled($uid));
$check('desactivar remove o segredo', sige_mfa_totp_get_secret($uid) === '');

// ---- 5. Integracao no step-up ------------------------------------------------
$uid2 = 4002;
$secret2 = sige_mfa_totp_generate_secret();
sige_mfa_totp_store_secret($uid2, $secret2);
sige_mfa_totp_confirm_enrollment($uid2, sige_mfa_totp_code($secret2));
$GLOBALS['t_otp_issue_called'] = false;
$emit = sige_mfa_issue_challenge($uid2, 'fin_estornarPagamento');
$check('issue_challenge devolve true para inscrito', $emit === true);
$check('inscrito NAO aciona o caminho de email (A2 fechado)', $GLOBALS['t_otp_issue_called'] === false);
$check('marca de desafio TOTP pendente colocada', get_transient('sige_mfa_totp_pending_' . $uid2) !== false);
$check('pending verdadeiro para inscrito com desafio', sige_mfa_pending($uid2));
$check('verify_challenge com codigo errado devolve errado', sige_mfa_verify_challenge($uid2, '000000') === 'errado');
$check('pending continua verdadeiro apos erro', sige_mfa_pending($uid2));
$check('verify_challenge com codigo certo devolve ok', sige_mfa_verify_challenge($uid2, sige_mfa_totp_code($secret2)) === 'ok');
$check('janela de step-up aberta apos ok', sige_mfa_recently_verified($uid2));
$check('marca de desafio TOTP limpa apos ok', get_transient('sige_mfa_totp_pending_' . $uid2) === false);

// tecto de tentativas (utilizador fresco)
$uid3 = 4003;
$secret3 = sige_mfa_totp_generate_secret();
sige_mfa_totp_store_secret($uid3, $secret3);
sige_mfa_totp_confirm_enrollment($uid3, sige_mfa_totp_code($secret3));
$last = '';
for ($i = 0; $i < 11; $i++) { $last = sige_mfa_verify_challenge($uid3, '000000'); }
$check('tecto de tentativas TOTP trava com esgotado', $last === 'esgotado');

// ---- Resultado ---------------------------------------------------------------
echo "\n";
if ($fail) {
    fwrite(STDERR, 'SMOKE TOTP FALHOU: ' . count($fail) . " falha(s)\n - " . implode("\n - ", $fail) . "\n");
    exit(1);
}
echo "SMOKE TOTP OK - {$ok} verificacoes passaram.\n";
exit(0);
