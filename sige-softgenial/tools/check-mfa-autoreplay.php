<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

/**
 * Gate: MFA de Operacao (reposicao automatica) - Fase 4, incremento 3 (v12.12.12).
 * Garante que o modulo existe, as primitivas estao definidas, as 6 operacoes de
 * servico capturam o descritor, o handler de confirmacao dispara a reposicao, o
 * gating (opcao + kill-switch) esta no sitio, e o consumo e de uso unico (sem
 * execucao dupla), com manifesto inalterado (sem novo endpoint).
 */

$root = dirname(__DIR__);
$fails = [];
$read = static function (string $rel) use ($root): string {
    $abs = $root . '/' . $rel;
    return is_file($abs) ? (string) file_get_contents($abs) : '';
};

// Stubs minimos + servico-stub que conta chamadas (para o teste runtime).
if (!defined('SIGE_MFA_REPLAY_TEST_MODE')) define('SIGE_MFA_REPLAY_TEST_MODE', true);
$GLOBALS['t_trans'] = [];
$GLOBALS['opt'] = ['sige_mfa_autoreplay' => 'on'];
if (!function_exists('get_transient'))      { function get_transient($k){ return $GLOBALS['t_trans'][$k] ?? false; } }
if (!function_exists('set_transient'))      { function set_transient($k,$v,$ttl=0){ $GLOBALS['t_trans'][$k]=$v; return true; } }
if (!function_exists('delete_transient'))   { function delete_transient($k){ unset($GLOBALS['t_trans'][$k]); return true; } }
if (!function_exists('get_option'))         { function get_option($k,$d=false){ return $GLOBALS['opt'][$k] ?? $d; } }
if (!function_exists('get_current_user_id')){ function get_current_user_id(){ return 9001; } }
if (!function_exists('sige_security_log'))  { function sige_security_log($a,$b){} }
if (!class_exists('SIGE_FinanceActionService')) {
    class SIGE_FinanceActionService {
        public static $n = 0;
        public static function cancelLancamento($id, $motivo, $escola) { self::$n++; return ['ok' => true, 'op' => 'cancelLancamento', 'id' => $id]; }
        public static function isentarLancamento($id, $motivo, $escola) { self::$n++; return ['ok' => true]; }
        public static function reactivarLancamento($id, $escola) { self::$n++; return ['ok' => true]; }
        public static function bloquearMes($a, $m, $mo, $an, $e) { self::$n++; return ['ok' => true]; }
        public static function desbloquearMes($b, $e) { self::$n++; return ['ok' => true]; }
        public static function estornarPagamento($p, $mo, $e, $v = 0.0) { self::$n++; return ['ok' => true]; }
    }
}

$modulo = $root . '/includes/security-mfa-replay.php';
if (!file_exists($modulo)) { fwrite(STDERR, "modulo includes/security-mfa-replay.php ausente\n"); exit(1); }
require_once $modulo;

// 1. Primitivas ----------------------------------------------------------------
foreach (['sige_mfa_replay_enabled','sige_mfa_replay_registry','sige_mfa_replay_capture','sige_mfa_replay_pending','sige_mfa_replay_dispatch','sige_mfa_replay_consume_and_run'] as $fn) {
    if (!function_exists($fn)) $fails[] = "primitiva ausente: {$fn}()";
}

// 2. Registo das 6 operacoes ---------------------------------------------------
$reg = function_exists('sige_mfa_replay_registry') ? sige_mfa_replay_registry() : [];
$esperadas = ['fin_cancelLancamento','fin_isentarLancamento','fin_reactivarLancamento','fin_bloquearMes','fin_desbloquearMes','fin_estornarPagamento'];
if (count($reg) !== 6) $fails[] = 'registo nao tem exactamente 6 operacoes (' . count($reg) . ')';
foreach ($esperadas as $ctx) {
    if (!isset($reg[$ctx])) $fails[] = "registo sem o contexto {$ctx}";
    elseif (($reg[$ctx][0] ?? '') !== 'SIGE_FinanceActionService') $fails[] = "registo de {$ctx} nao aponta para SIGE_FinanceActionService";
}

// 3. Captura nas operacoes de servico ------------------------------------------
$svc = $read('includes/fin-action-service.php');
if (substr_count($svc, 'sige_mfa_replay_capture(') !== 6) $fails[] = 'captura nao esta nas 6 operacoes de servico';
foreach ($esperadas as $ctx) {
    if (strpos($svc, "sige_mfa_replay_capture('{$ctx}', func_get_args())") === false) $fails[] = "captura em falta no contexto {$ctx}";
}
// config e caixa NAO capturam
foreach (['cfg_emola','cfg_mpesa'] as $cfgctx) {
    if (strpos($read('includes/payments/emola-config.php') . $read('includes/payments/mpesa-config.php'), "sige_mfa_replay_capture('{$cfgctx}'") !== false) $fails[] = "captura indevida em {$cfgctx}";
}

// 4. Handler de confirmacao e aviso --------------------------------------------
$stepup = $read('includes/security-mfa-stepup.php');
if (strpos($stepup, 'sige_mfa_replay_consume_and_run') === false) $fails[] = 'handler de confirmacao nao dispara a reposicao';
if (strpos($stepup, 'sige_mfa_replay_result_') === false) $fails[] = 'handler nao guarda o resultado da reposicao';
if (strpos($stepup, 'concluida automaticamente') === false) $fails[] = 'aviso de reposicao concluida ausente';

// 5. Gating --------------------------------------------------------------------
$mod = $read('includes/security-mfa-replay.php');
if (strpos($mod, "get_option('sige_mfa_autoreplay'") === false) $fails[] = 'gating por opcao sige_mfa_autoreplay ausente';
if (strpos($mod, 'SIGE_MFA_AUTOREPLAY_OFF') === false) $fails[] = 'kill-switch SIGE_MFA_AUTOREPLAY_OFF ausente';

// 6. Runtime: uso unico, sem execucao dupla ------------------------------------
if (function_exists('sige_mfa_replay_capture')) {
    SIGE_FinanceActionService::$n = 0;
    $GLOBALS['t_trans'] = [];
    sige_mfa_replay_capture('fin_cancelLancamento', [11, 'motivo', 1]);
    if (!sige_mfa_replay_pending(9001)) $fails[] = 'descritor nao capturado';
    $r1 = sige_mfa_replay_consume_and_run(9001);
    if (!is_array($r1) || empty($r1['ok'])) $fails[] = 'consumo nao devolveu resultado ok';
    if (SIGE_FinanceActionService::$n !== 1) $fails[] = 'operacao nao executada exactamente uma vez';
    $r2 = sige_mfa_replay_consume_and_run(9001);
    if ($r2 !== null || SIGE_FinanceActionService::$n !== 1) $fails[] = 'execucao dupla: segundo consumo disparou';
    // off bloqueia
    $GLOBALS['opt']['sige_mfa_autoreplay'] = 'off';
    SIGE_FinanceActionService::$n = 0; $GLOBALS['t_trans'] = [];
    sige_mfa_replay_capture('fin_cancelLancamento', [1, 'x', 1]);
    if (sige_mfa_replay_consume_and_run(9001) !== null || SIGE_FinanceActionService::$n !== 0) $fails[] = 'reposicao off nao bloqueou o consumo';
    $GLOBALS['opt']['sige_mfa_autoreplay'] = 'on';
}

// 7. Carregamento e manifesto --------------------------------------------------
if (strpos($read('sige-softgenial.php'), 'security-mfa-replay.php') === false) $fails[] = 'modulo nao carregado no bootstrap';
$mf = json_decode($read('docs/security/ACTION_SURFACE_MANIFEST-v12.12.12.json'), true);
if (!is_array($mf) || count($mf['items'] ?? []) !== 195) $fails[] = 'manifesto v12.12.12 nao tem 195 itens (reposicao nao deve adicionar endpoint)';

if ($fails) {
    fwrite(STDERR, "MFA AUTOREPLAY GATE FALHOU\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo "MFA AUTOREPLAY OK - captura nas 6 operacoes, consumo de uso unico sem execucao dupla, gating e manifesto verificados.\n";
exit(0);
