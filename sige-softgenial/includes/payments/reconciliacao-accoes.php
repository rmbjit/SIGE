<?php
/**
 * SIGE SoftGenial - Resolucao de divergencias com escrita (quatro-olhos).
 * Fase 3, incremento 3.
 *
 * Permite resolver transacoes de gateway atraves da regra de quatro-olhos ja
 * existente (sige_fin_aprovacao_*): um utilizador propoe (conciliar ou rejeitar) e
 * um SEGUNDO utilizador autorizado aprova; so entao a accao e executada. A escrita
 * financeira passa SEMPRE pelo caminho canonico (sige_fin_registar_pagamento); este
 * ficheiro nunca calcula valores nem altera regras financeiras.
 *
 * Tres pecas:
 *  - Execucao (sige_recon_executar_conciliar, sige_recon_executar_rejeitar):
 *    chamadas pelo despacho de aprovacoes quando um pedido e aprovado. Re-validam o
 *    estado da transacao no momento da execucao (a transacao pode ter mudado entre
 *    a proposta e a aprovacao), sao idempotentes e respeitam o tenant.
 *  - Proposta (sige_recon_propor_conciliacao, sige_recon_propor_rejeicao): criam o
 *    pedido pendente (maker) via sige_fin_aprovacao_solicitar; nao executam nada.
 *  - Os tipos de aprovacao e o despacho vivem em includes/finance-aprovacoes.php.
 *
 * Permissao reutilizada: financeiro.mobile_payments_gerir (sem nova permissao).
 */

if (!defined('ABSPATH') && !defined('SIGE_RECON_ACOES_TEST_MODE')) exit;

// ============================================================================
// EXECUCAO (chamada pelo despacho de aprovacoes apos a aprovacao do 2.o utilizador)
// ============================================================================

if (!function_exists('sige_recon_executar_conciliar')) {
    /**
     * Concilia uma transacao confirmada, registando o pagamento pelo caminho
     * canonico. Se $lanc_id > 0 usa esse lancamento (escolha manual); caso
     * contrario tenta a correspondencia automatica (mesma logica do funil canonico).
     * Re-valida o estado da transacao; nao age se ja nao estiver pendente.
     */
    function sige_recon_executar_conciliar(int $tx_id, int $lanc_id, int $escola_id): array {
        global $wpdb;
        if ($escola_id <= 0) return ['ok' => false, 'error' => 'Contexto de escola invalido.'];
        if (function_exists('sige_tenant_write_guard') && !sige_tenant_write_guard($escola_id, 'recon_conciliar')) {
            return ['ok' => false, 'error' => 'Contexto de escola invalido.'];
        }

        $tX = $wpdb->prefix . 'sige_mpesa_transacoes';
        $tL = $wpdb->prefix . 'sige_fin_lancamentos';
        $tx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tX} WHERE id = %d AND escola_id = %d", $tx_id, $escola_id));
        if (!$tx) return ['ok' => false, 'error' => 'Transacao inexistente nesta escola.'];
        if (!in_array($tx->estado, ['recebida', 'pendente_manual'], true)) {
            return ['ok' => false, 'error' => 'A transacao ja nao esta pendente (estado: ' . (string) $tx->estado . ').'];
        }
        if (!function_exists('sige_fin_registar_pagamento')) {
            return ['ok' => false, 'error' => 'Motor financeiro indisponivel.'];
        }

        if ($lanc_id > 0) {
            $lanc = $wpdb->get_row($wpdb->prepare(
                "SELECT id, aluno_id, escola_id FROM {$tL} WHERE id = %d AND escola_id = %d LIMIT 1", $lanc_id, $escola_id
            ));
            if (!$lanc) return ['ok' => false, 'error' => 'Lancamento nao pertence a esta escola.'];
            if (!empty($tx->aluno_id) && (int) $tx->aluno_id > 0 && (int) $tx->aluno_id !== (int) $lanc->aluno_id) {
                return ['ok' => false, 'error' => 'Lancamento nao pertence ao aluno da transacao.'];
            }
            $alvo_lanc = $lanc_id; $alvo_aluno = (int) $lanc->aluno_id;
        } else {
            if (!function_exists('sige_mpesa_extrair_processo') || !function_exists('sige_mpesa_lancamentos_abertos') || !function_exists('sige_mpesa_escolher_lancamento')) {
                return ['ok' => false, 'error' => 'Conciliacao automatica indisponivel; escolha um lancamento.'];
            }
            $processo = sige_mpesa_extrair_processo((string) $tx->referencia_cliente);
            if ($processo === '') return ['ok' => false, 'error' => 'Referencia sem numero de processo; escolha um lancamento.'];
            $tA = $wpdb->prefix . 'sige_alunos';
            $aluno_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tA} WHERE escola_id = %d AND numero_processo = %s LIMIT 1", $escola_id, $processo
            ));
            if ($aluno_id <= 0) return ['ok' => false, 'error' => 'Processo ' . $processo . ' nao encontrado; escolha um lancamento.'];
            $abertos = sige_mpesa_lancamentos_abertos($escola_id, $aluno_id);
            $decisao = sige_mpesa_escolher_lancamento($abertos, (float) $tx->valor);
            if (($decisao['accao'] ?? '') !== 'registar') {
                return ['ok' => false, 'error' => (string) ($decisao['motivo'] ?? 'Sem lancamento aberto compativel; escolha um lancamento.')];
            }
            $alvo_lanc = (int) $decisao['lancamento_id']; $alvo_aluno = $aluno_id;
        }

        $rotulo = function_exists('sige_provider_rotulo') ? sige_provider_rotulo((string) $tx->provider) : (string) $tx->provider;
        $metodo = function_exists('sige_provider_metodo') ? sige_provider_metodo((string) $tx->provider) : (string) $tx->provider;
        $ref_ext = $rotulo . ' ' . (string) $tx->referencia_mpesa . ' (quatro-olhos)';

        $resultado = sige_fin_registar_pagamento($alvo_lanc, (float) $tx->valor, $metodo, $ref_ext);
        if (function_exists('is_wp_error') && is_wp_error($resultado)) {
            return ['ok' => false, 'error' => $resultado->get_error_message()];
        }

        $uid = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $wpdb->update($tX, [
            'estado'         => 'conciliada',
            'lancamento_id'  => $alvo_lanc,
            'pagamento_id'   => (int) $resultado,
            'aluno_id'       => $alvo_aluno,
            'erro'           => null,
            'conciliado_em'  => current_time('mysql'),
            'conciliado_por' => 'aprovacao:user:' . $uid,
        ], ['id' => $tx_id, 'escola_id' => $escola_id]);

        if (function_exists('sige_security_log')) {
            sige_security_log('recon_conciliada_quatro_olhos', "tx={$tx_id} lanc={$alvo_lanc} aprovador={$uid}");
        }
        return ['ok' => true, 'estado' => 'conciliada', 'lancamento_id' => $alvo_lanc, 'pagamento_id' => (int) $resultado];
    }
}

if (!function_exists('sige_recon_executar_rejeitar')) {
    /**
     * Rejeita uma transacao. Idempotente (ja rejeitada -> ok). Nunca rejeita uma
     * transacao ja conciliada. So toca no estado da transacao; nao mexe em pagamentos.
     */
    function sige_recon_executar_rejeitar(int $tx_id, string $motivo, int $escola_id): array {
        global $wpdb;
        if ($escola_id <= 0) return ['ok' => false, 'error' => 'Contexto de escola invalido.'];
        if (function_exists('sige_tenant_write_guard') && !sige_tenant_write_guard($escola_id, 'recon_rejeitar')) {
            return ['ok' => false, 'error' => 'Contexto de escola invalido.'];
        }

        $tX = $wpdb->prefix . 'sige_mpesa_transacoes';
        $tx = $wpdb->get_row($wpdb->prepare("SELECT estado FROM {$tX} WHERE id = %d AND escola_id = %d", $tx_id, $escola_id));
        if (!$tx) return ['ok' => false, 'error' => 'Transacao inexistente nesta escola.'];
        if ($tx->estado === 'conciliada') return ['ok' => false, 'error' => 'A transacao ja foi conciliada; nao pode ser rejeitada.'];
        if ($tx->estado === 'rejeitada') return ['ok' => true, 'estado' => 'rejeitada'];

        $uid = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $wpdb->update($tX, [
            'estado'         => 'rejeitada',
            'erro'           => $motivo !== '' ? $motivo : 'Rejeitada por reconciliacao (quatro-olhos).',
            'conciliado_em'  => current_time('mysql'),
            'conciliado_por' => 'aprovacao:user:' . $uid,
        ], ['id' => $tx_id, 'escola_id' => $escola_id]);

        if (function_exists('sige_security_log')) {
            sige_security_log('recon_rejeitada_quatro_olhos', "tx={$tx_id} aprovador={$uid}");
        }
        return ['ok' => true, 'estado' => 'rejeitada'];
    }
}

// ============================================================================
// PROPOSTA (maker): cria o pedido pendente, nao executa nada
// ============================================================================

if (!function_exists('sige_recon_propor_conciliacao')) {
    /** Propoe conciliar uma transacao (maker). $lanc_id = 0 deixa a correspondencia automatica. */
    function sige_recon_propor_conciliacao(int $tx_id, int $lanc_id, int $escola_id): array {
        if (!function_exists('sige_fin_aprovacao_solicitar')) return ['ok' => false, 'error' => 'Modulo de aprovacoes indisponivel.'];
        if ($tx_id <= 0) return ['ok' => false, 'error' => 'Transacao invalida.'];
        return sige_fin_aprovacao_solicitar('recon_conciliar', $tx_id, ['lancamento_id' => max(0, $lanc_id)], $escola_id, 'tx ' . $tx_id);
    }
}

if (!function_exists('sige_recon_propor_rejeicao')) {
    /** Propoe rejeitar uma transacao (maker). */
    function sige_recon_propor_rejeicao(int $tx_id, string $motivo, int $escola_id): array {
        if (!function_exists('sige_fin_aprovacao_solicitar')) return ['ok' => false, 'error' => 'Modulo de aprovacoes indisponivel.'];
        if ($tx_id <= 0) return ['ok' => false, 'error' => 'Transacao invalida.'];
        return sige_fin_aprovacao_solicitar('recon_rejeitar', $tx_id, ['motivo' => $motivo], $escola_id, 'tx ' . $tx_id);
    }
}
