<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
if (!defined('ABSPATH')) define('ABSPATH', dirname(__DIR__) . '/');

/**
 * Gate: MFA de Operacao (TOTP) - Fase 4, incremento 2 (v12.12.11).
 * Garante que o modulo existe, as primitivas estao definidas, o nucleo bate os
 * vectores RFC 6238, o QR gera matriz, a integracao no step-up esta no sitio
 * (inscrito nao gera email, aceita TOTP), e o endpoint esta no Security Kernel
 * e no manifesto.
 */

$root = dirname(__DIR__);
$fails = [];
$read = static function (string $rel) use ($root): string {
    $abs = $root . '/' . $rel;
    return is_file($abs) ? (string) file_get_contents($abs) : '';
};

// 1. Modulo e primitivas -------------------------------------------------------
$modulo = $root . '/includes/security-mfa-totp.php';
if (!file_exists($modulo)) { fwrite(STDERR, "modulo includes/security-mfa-totp.php ausente\n"); exit(1); }
if (!defined('SIGE_MFA_TOTP_TEST_MODE')) define('SIGE_MFA_TOTP_TEST_MODE', true);
require_once $modulo;

$primitivas = [
    'sige_mfa_totp_base32_encode', 'sige_mfa_totp_base32_decode', 'sige_mfa_totp_hotp',
    'sige_mfa_totp_code', 'sige_mfa_totp_verify', 'sige_mfa_totp_generate_secret', 'sige_mfa_totp_uri',
    'sige_mfa_totp_store_secret', 'sige_mfa_totp_get_secret', 'sige_mfa_totp_enrolled',
    'sige_mfa_totp_confirm_enrollment', 'sige_mfa_totp_disable', 'sige_mfa_totp_verify_user',
    'sige_mfa_qr_matrix', 'sige_mfa_totp_qr_svg', 'sige_mfa_totp_render_page', 'sige_mfa_totp_handle_enroll',
];
foreach ($primitivas as $fn) {
    if (!function_exists($fn)) $fails[] = "primitiva ausente: {$fn}()";
}

// 2. Nucleo RFC 6238 -----------------------------------------------------------
if (function_exists('sige_mfa_totp_code')) {
    $b32 = sige_mfa_totp_base32_encode('12345678901234567890');
    if ($b32 !== 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ') $fails[] = 'base32 da semente RFC incorrecto';
    $vec = [[59,'287082'],[1111111109,'081804'],[1111111111,'050471'],[1234567890,'005924'],[2000000000,'279037'],[20000000000,'353130']];
    foreach ($vec as $v) {
        if (sige_mfa_totp_code($b32, $v[0]) !== $v[1]) $fails[] = "vector RFC 6238 falhou em t={$v[0]}";
    }
    if (!sige_mfa_totp_verify($b32, sige_mfa_totp_code($b32, 1111111111 - 30), 1111111111)) $fails[] = 'verify nao tolera desvio de relogio';
    if (sige_mfa_totp_verify($b32, '000000', 1111111111)) $fails[] = 'verify aceitou codigo errado';
}

// 3. QR ------------------------------------------------------------------------
if (function_exists('sige_mfa_qr_matrix') && function_exists('sige_mfa_totp_uri')) {
    $uri = sige_mfa_totp_uri('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'director@escola.mz', 'SIGE SoftGenial');
    $m = sige_mfa_qr_matrix($uri);
    if (!is_array($m)) $fails[] = 'matriz QR nao gerada';
    elseif (count($m) !== (sige_mfa_qr_pick_version(strlen($uri)) * 4 + 17)) $fails[] = 'dimensao da matriz QR incorrecta';
    if (strpos(sige_mfa_totp_qr_svg($uri), '<svg') !== 0) $fails[] = 'SVG do QR mal formado';
}

// 4. Integracao no step-up -----------------------------------------------------
$stepup = $read('includes/security-mfa-stepup.php');
if ($stepup === '') {
    $fails[] = 'modulo security-mfa-stepup.php ausente';
} else {
    if (strpos($stepup, 'sige_mfa_totp_enrolled') === false) $fails[] = 'step-up nao consulta sige_mfa_totp_enrolled';
    if (strpos($stepup, 'challenge_totp_expected') === false) $fails[] = 'issue_challenge sem ramo TOTP (sem email)';
    if (strpos($stepup, 'verify_ok_totp') === false) $fails[] = 'verify_challenge sem ramo TOTP';
    if (strpos($stepup, 'sige_mfa_totp_verify_user') === false) $fails[] = 'verify_challenge nao verifica TOTP do utilizador';
    if (strpos($stepup, 'sige_mfa_totp_pending_') === false) $fails[] = 'pending nao considera o desafio TOTP';
    if (strpos($stepup, 'aplicacao autenticadora') === false) $fails[] = 'formulario sem mensagem TOTP';
}

// 5. Carregamento e governanca -------------------------------------------------
if (strpos($read('sige-softgenial.php'), 'security-mfa-totp.php') === false) $fails[] = 'modulo TOTP nao carregado no bootstrap';
$rules = $read('includes/security-kernel-rules.php');
if (strpos($rules, 'admin_post:sige_mfa_totp_enroll') === false) $fails[] = 'regra de Kernel do endpoint ausente';
if (strpos($rules, "'action' => 'sige_mfa_totp_enroll'") === false) $fails[] = 'regra sem intent nonce sige_mfa_totp_enroll';
$rj = json_decode($read('docs/security/SECURITY_KERNEL_RULES-v12.12.11.json'), true);
if (!is_array($rj) || !in_array('admin_post:sige_mfa_totp_enroll', array_column($rj['items'] ?? [], 'id'), true)) $fails[] = 'endpoint ausente do JSON de regras v12.12.11';
$mf = json_decode($read('docs/security/ACTION_SURFACE_MANIFEST-v12.12.11.json'), true);
$mfItems = is_array($mf) ? ($mf['items'] ?? []) : [];
if (count($mfItems) !== 195) $fails[] = 'manifesto v12.12.11 nao tem 195 itens (' . count($mfItems) . ')';
if (!in_array('admin_post:sige_mfa_totp_enroll', array_column($mfItems, 'id'), true)) $fails[] = 'endpoint ausente do manifesto v12.12.11';

// 6. Crypto reutilizada para o segredo -----------------------------------------
if (strpos($read('includes/db-handler.php'), 'function sige_encrypt_token') === false) $fails[] = 'ajudante sige_encrypt_token ausente (cifra do segredo)';

if ($fails) {
    fwrite(STDERR, "MFA TOTP GATE FALHOU\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo "MFA TOTP OK - nucleo RFC 6238, QR, integracao step-up e governanca verificados.\n";
exit(0);
