<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke funcional - Escudo de Login (rate limit + OTP)
 * Testa o NÚCLEO em isolamento, com armazenamento em memória e relógio
 * controlado (SIGE_SHIELD_TEST_MODE). Sem WordPress.
 *
 * Executar: php tools/smoke-login-shield.php
 */
define('SIGE_SHIELD_TEST_MODE', true);
$GLOBALS['sige_shield_test_store'] = [];
$GLOBALS['sige_shield_test_now'] = 1000000;

require __DIR__ . '/../includes/security-login-shield.php';

$fails = []; $oks = 0;
$check = function (bool $c, string $l) use (&$fails, &$oks) {
    if ($c) { $oks++; echo "OK   {$l}\n"; } else { $fails[] = $l; echo "FAIL {$l}\n"; }
};
$avancar = function (int $seg) { $GLOBALS['sige_shield_test_now'] += $seg; };
$reset = function () { $GLOBALS['sige_shield_test_store'] = []; };

// ── 1. Lockout por utilizador+IP: 4 falhas livre, 5ª tranca ───────────────
$reset();
$u = 'director@escola'; $ip = '197.218.10.5';
for ($i = 1; $i <= 4; $i++) sige_shield_register_failure($u, $ip);
$check(sige_shield_is_locked($u, $ip) === 0, '4 falhas: ainda livre');
sige_shield_register_failure($u, $ip);
$r = sige_shield_is_locked($u, $ip);
$check($r > 0 && $r <= SIGE_SHIELD_USER_LOCK, "5ª falha tranca por {$r}s (<= " . SIGE_SHIELD_USER_LOCK . ')');

// ── 2. Bloqueio expira sozinho ─────────────────────────────────────────────
$avancar(SIGE_SHIELD_USER_LOCK + 1);
$check(sige_shield_is_locked($u, $ip) === 0, 'Bloqueio de utilizador expira após a janela');

// ── 3. Janela desliza: falhas antigas não contam ───────────────────────────
$reset();
for ($i = 1; $i <= 3; $i++) sige_shield_register_failure($u, $ip);
$avancar(max(SIGE_SHIELD_USER_WIN, SIGE_SHIELD_USER_GLOBAL_WIN) + 1);
for ($i = 1; $i <= 4; $i++) sige_shield_register_failure($u, $ip);
$check(sige_shield_is_locked($u, $ip) === 0, 'Falhas fora da janela não acumulam (3 antigas + 4 novas = livre)');

// ── 4. Sucesso limpa o balde do utilizador ─────────────────────────────────
$reset();
for ($i = 1; $i <= 4; $i++) sige_shield_register_failure($u, $ip);
sige_shield_register_success($u, $ip);
sige_shield_register_failure($u, $ip);
$check(sige_shield_is_locked($u, $ip) === 0, 'Sucesso faz reset ao contador do utilizador');

// ── 5. Anti-spray por IP: falhas com utilizadores diferentes trancam o IP ─
$reset();
for ($i = 1; $i <= SIGE_SHIELD_IP_MAX; $i++) {
    sige_shield_register_failure('user' . $i, $ip);
}
$r = sige_shield_is_locked('utilizador-novo', $ip);
$check($r > 0, "Spray de " . SIGE_SHIELD_IP_MAX . " utilizadores tranca o IP ({$r}s)");
$check(sige_shield_is_locked('utilizador-novo', '41.220.30.9') === 0, 'Outro IP continua livre');

// ── 6. Utilizadores diferentes no mesmo IP não partilham o balde de utilizador ─
$reset();
for ($i = 1; $i <= 4; $i++) sige_shield_register_failure('secretaria', $ip);
$check(sige_shield_is_locked('professor', $ip) === 0, 'Falhas de A não trancam B (abaixo do tecto de IP)');


// ── 6b. Anti-spray distribuido por username: IPs diferentes trancam a mesma conta ─
$reset();
for ($i = 1; $i <= SIGE_SHIELD_USER_GLOBAL_MAX; $i++) {
    sige_shield_register_failure('alvo-critico', '197.218.20.' . $i);
}
$r = sige_shield_is_locked('alvo-critico', '41.220.30.200');
$check($r > 0, 'Falhas distribuidas por username trancam a conta alvo (' . $r . 's)');
$check(sige_shield_is_locked('outro-utilizador', '41.220.30.200') === 0, 'Bloqueio por username nao tranca outro utilizador noutro IP');

// ── 7. OTP: emitir, errar, acertar ─────────────────────────────────────────
$reset();
$code = sige_otp_issue(77);
$check(strlen($code) === 6 && ctype_digit($code), 'OTP tem 6 dígitos');
$check(sige_otp_pending(77) === true, 'OTP fica pendente após emissão');
$check(sige_otp_verify(77, '000000') === 'errado', 'Código errado devolve "errado"');
$check(sige_otp_verify(77, $code) === 'ok', 'Código certo devolve "ok"');
$check(sige_otp_pending(77) === false, 'OTP consumido deixa de estar pendente');
$check(sige_otp_verify(77, $code) === 'ausente', 'Reutilizar o código devolve "ausente"');

// ── 8. OTP expira ──────────────────────────────────────────────────────────
$code = sige_otp_issue(78);
$avancar(SIGE_OTP_TTL + 1);
$check(sige_otp_verify(78, $code) === 'expirado', 'OTP expira após ' . SIGE_OTP_TTL . 's');

// ── 9. OTP esgota tentativas ───────────────────────────────────────────────
$code = sige_otp_issue(79);
for ($i = 1; $i <= SIGE_OTP_MAX_TENTATIVAS; $i++) sige_otp_verify(79, 'xxxxxx');
$check(sige_otp_verify(79, $code) === 'esgotado', 'Código certo após esgotar tentativas é recusado');

echo str_repeat('-', 60) . "\n";
if ($fails) {
    fwrite(STDERR, 'SMOKE LOGIN SHIELD FALHOU: ' . count($fails) . " falha(s)\n");
    foreach ($fails as $f) fwrite(STDERR, " - {$f}\n");
    exit(1);
}
echo "SMOKE LOGIN SHIELD OK - {$oks} verificações passaram.\n";
