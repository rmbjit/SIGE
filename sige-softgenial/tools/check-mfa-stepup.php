<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
if (!defined('ABSPATH')) define('ABSPATH', dirname(__DIR__) . '/');

/**
 * Gate: MFA de Operacao (Step-up) - Fase 4, incremento 1.
 * Garante que o modulo existe, as primitivas estao definidas, os 10 pontos de
 * operacao critica invocam o guard sige_mfa_require_step_up e a accao do
 * endpoint de confirmacao esta no Security Kernel.
 */

$root = dirname(__DIR__);
$fails = [];

// 1. Modulo e primitivas -------------------------------------------------------
$modulo = $root . '/includes/security-mfa-stepup.php';
if (!file_exists($modulo)) {
    fwrite(STDERR, "modulo includes/security-mfa-stepup.php ausente\n");
    exit(1);
}
if (!defined('SIGE_MFA_TEST_MODE')) define('SIGE_MFA_TEST_MODE', true);
require_once $modulo;

$primitivas = [
    'sige_mfa_stepup_enabled', 'sige_mfa_stepup_strict', 'sige_mfa_stepup_roles', 'sige_mfa_applies_to_user',
    'sige_mfa_window_key', 'sige_mfa_recently_verified', 'sige_mfa_mark_verified',
    'sige_mfa_pending', 'sige_mfa_send_challenge_email', 'sige_mfa_issue_challenge',
    'sige_mfa_verify_challenge', 'sige_mfa_require_step_up', 'sige_mfa_render_challenge_form',
];
foreach ($primitivas as $fn) {
    if (!function_exists($fn)) $fails[] = "primitiva ausente: {$fn}()";
}

// 2. Pontos de integracao (o guard tem de estar presente) ----------------------
$pontos = [
    'includes/fin-action-service.php' => [
        'min' => 6,
        'ctxs' => ['fin_cancelLancamento','fin_isentarLancamento','fin_reactivarLancamento','fin_bloquearMes','fin_desbloquearMes','fin_estornarPagamento'],
    ],
    'includes/payments/emola-config.php' => ['min' => 1, 'ctxs' => ['cfg_emola']],
    'includes/payments/mpesa-config.php' => ['min' => 1, 'ctxs' => ['cfg_mpesa']],
    'admin/finance/financeiro-extratos.php' => ['min' => 2, 'ctxs' => ['caixa_reabrir','caixa_fechar']],
];
$total_pontos = 0;
foreach ($pontos as $rel => $spec) {
    $src = @file_get_contents($root . '/' . $rel);
    if ($src === false) { $fails[] = "ficheiro de integracao ausente: {$rel}"; continue; }
    $n = substr_count($src, 'sige_mfa_require_step_up');
    if ($n < $spec['min']) $fails[] = "{$rel}: esperados >= {$spec['min']} guards MFA, encontrados {$n}";
    foreach ($spec['ctxs'] as $ctx) {
        if (strpos($src, "sige_mfa_require_step_up('{$ctx}')") === false) {
            $fails[] = "{$rel}: contexto MFA em falta: {$ctx}";
        } else {
            $total_pontos++;
        }
    }
}
if ($total_pontos !== 10) $fails[] = "esperados 10 contextos de operacao critica protegidos, encontrados {$total_pontos}";

// 3. Regra no Security Kernel (observe; auto-protegido por login/nonce) ---------
if (!defined('SIGE_SECURITY_KERNEL_TEST_MODE')) define('SIGE_SECURITY_KERNEL_TEST_MODE', true);
require_once $root . '/includes/security-kernel-rules.php';
$regras = sige_security_kernel_rules();
$byId = [];
foreach ($regras as $r) $byId[(string)($r['id'] ?? '')] = $r;
if (!isset($byId['admin_post:sige_mfa_confirm'])) {
    $fails[] = 'regra de Kernel admin_post:sige_mfa_confirm ausente';
} else {
    $r = $byId['admin_post:sige_mfa_confirm'];
    if (($r['mode'] ?? '') !== 'observe') $fails[] = 'admin_post:sige_mfa_confirm deveria estar em modo observe';
    if (($r['intent']['type'] ?? '') !== 'nonce') $fails[] = 'admin_post:sige_mfa_confirm deveria exigir nonce';
}

// 4. Sem regressao de calculo (as funcoes financeiras nao sao tocadas) ----------
$fin = @file_get_contents($root . '/includes/finance-core.php');
if ($fin !== false) {
    foreach (['sige_fin_saldo_lancamento','sige_fin_saldo_sql','sige_fin_total_bruto_sql'] as $calc) {
        if (strpos($fin, "function {$calc}") === false) $fails[] = "funcao de calculo ausente: {$calc}";
    }
}

// Resultado --------------------------------------------------------------------
if ($fails) {
    fwrite(STDERR, "MFA STEP-UP FALHOU\n");
    foreach ($fails as $f) fwrite(STDERR, " - {$f}\n");
    exit(1);
}
echo "MFA STEP-UP OK - 13 primitivas, 10 operacoes criticas protegidas, regra de Kernel presente.\n";
exit(0);
