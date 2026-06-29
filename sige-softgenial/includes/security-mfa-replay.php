<?php
/**
 * SIGE SoftGenial - MFA de Operacao: reposicao automatica apos confirmacao
 *
 * Fase 4, incremento 3 (v12.12.12). Quando o step-up bloqueia uma das 6
 * operacoes de servico, captura-se um descritor de uso unico (contexto +
 * argumentos da chamada). Depois de o utilizador confirmar a identidade (TOTP
 * ou email), o handler de confirmacao consome o descritor de forma atomica
 * (apaga antes de despachar) e re-executa a operacao exactamente uma vez, com
 * tenant e permissoes re-validados por passar pelo mesmo metodo de servico.
 *
 * So abrange as 6 operacoes de servico (SIGE_FinanceActionService). A
 * configuracao de pagamentos (e-Mola, M-Pesa) e a caixa (reabrir, fechar)
 * mantem a repeticao manual, por desenho.
 *
 * Desligado por defeito (opcao opt-in sige_mfa_autoreplay; kill-switch
 * SIGE_MFA_AUTOREPLAY_OFF). A repeticao manual funciona sempre.
 */

if (!defined('ABSPATH') && !defined('SIGE_MFA_REPLAY_TEST_MODE')) exit;

if (!defined('SIGE_MFA_REPLAY_TTL')) define('SIGE_MFA_REPLAY_TTL', 600); // descritor vive 10 min (igual a validade do desafio)

if (!function_exists('sige_mfa_replay_enabled')) {
    /** A reposicao automatica esta ligada? (opt-in; desligavel de emergencia). */
    function sige_mfa_replay_enabled(): bool {
        if (defined('SIGE_MFA_AUTOREPLAY_OFF') && SIGE_MFA_AUTOREPLAY_OFF) return false;
        if (!function_exists('get_option')) return false;
        return get_option('sige_mfa_autoreplay', 'off') === 'on';
    }
}

if (!function_exists('sige_mfa_replay_registry')) {
    /** Mapa contexto -> [classe, metodo] das 6 operacoes de servico re-executaveis. */
    function sige_mfa_replay_registry(): array {
        return [
            'fin_cancelLancamento'    => ['SIGE_FinanceActionService', 'cancelLancamento'],
            'fin_isentarLancamento'   => ['SIGE_FinanceActionService', 'isentarLancamento'],
            'fin_reactivarLancamento' => ['SIGE_FinanceActionService', 'reactivarLancamento'],
            'fin_bloquearMes'         => ['SIGE_FinanceActionService', 'bloquearMes'],
            'fin_desbloquearMes'      => ['SIGE_FinanceActionService', 'desbloquearMes'],
            'fin_estornarPagamento'   => ['SIGE_FinanceActionService', 'estornarPagamento'],
        ];
    }
}

if (!function_exists('sige_mfa_replay_key')) {
    function sige_mfa_replay_key(int $user_id): string { return 'sige_mfa_replay_' . $user_id; }
}

if (!function_exists('sige_mfa_replay_capture')) {
    /**
     * Captura um descritor de reposicao de uso unico para o utilizador actual.
     * Chamado pelas operacoes de servico no ramo em que o step-up bloqueia.
     * So captura as 6 operacoes conhecidas e so se a reposicao estiver ligada.
     */
    function sige_mfa_replay_capture(string $ctx, array $args): void {
        if (!sige_mfa_replay_enabled()) return;
        if (!isset(sige_mfa_replay_registry()[$ctx])) return; // nunca config/caixa nem desconhecidos
        $uid = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        if ($uid <= 0 || !function_exists('set_transient')) return;
        set_transient(sige_mfa_replay_key($uid), [
            'ctx'     => $ctx,
            'args'    => array_values($args),
            'created' => time(),
        ], SIGE_MFA_REPLAY_TTL);
        if (function_exists('sige_security_log')) sige_security_log('mfa_replay', "capture ctx={$ctx} user_id={$uid}");
    }
}

if (!function_exists('sige_mfa_replay_pending')) {
    /** Ha um descritor de reposicao pendente para o utilizador? */
    function sige_mfa_replay_pending(int $user_id): bool {
        if ($user_id <= 0 || !function_exists('get_transient')) return false;
        $d = get_transient(sige_mfa_replay_key($user_id));
        return is_array($d) && !empty($d['ctx']);
    }
}

if (!function_exists('sige_mfa_replay_dispatch')) {
    /** Re-invoca o metodo de servico do contexto com os argumentos capturados. */
    function sige_mfa_replay_dispatch(string $ctx, array $args) {
        $reg = sige_mfa_replay_registry();
        if (!isset($reg[$ctx])) return null;
        [$class, $method] = $reg[$ctx];
        if (!is_callable([$class, $method])) return null;
        return call_user_func_array([$class, $method], array_values($args));
    }
}

if (!function_exists('sige_mfa_replay_consume_and_run')) {
    /**
     * Consome o descritor (apaga ANTES de despachar, uso unico atomico) e
     * re-executa a operacao uma vez. Devolve o resultado do metodo (array) ou
     * null se nao havia descritor, a reposicao esta desligada ou o contexto e
     * desconhecido.
     */
    function sige_mfa_replay_consume_and_run(int $user_id) {
        if (!sige_mfa_replay_enabled() || $user_id <= 0 || !function_exists('get_transient')) return null;
        $desc = get_transient(sige_mfa_replay_key($user_id));
        if (!is_array($desc) || empty($desc['ctx'])) return null;
        // Consumo atomico: apagar antes de despachar evita execucao dupla por confirmacoes concorrentes.
        if (function_exists('delete_transient')) delete_transient(sige_mfa_replay_key($user_id));
        $ctx  = (string) $desc['ctx'];
        $args = isset($desc['args']) && is_array($desc['args']) ? $desc['args'] : [];
        if (function_exists('sige_security_log')) sige_security_log('mfa_replay', "run ctx={$ctx} user_id={$user_id}");
        $res = sige_mfa_replay_dispatch($ctx, $args);
        return is_array($res) ? $res : null;
    }
}
