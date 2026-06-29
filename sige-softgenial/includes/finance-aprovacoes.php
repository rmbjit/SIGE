<?php
/**
 * Regra de quatro-olhos (dupla aprovacao) - Fase 7 incremento 2.
 *
 * Estorno de pagamento e reabertura de caixa deixam de executar com a decisao de
 * uma so pessoa. Cada um passa a ser pedido por um utilizador autorizado e
 * aprovado por um utilizador DIFERENTE antes de produzir efeito. Tudo registado
 * (quem pediu, quem decidiu, quando, porque), no Ledger e no log financeiro.
 *
 * Separacao de funcoes: o aprovador tem de ser utilizador diferente do
 * solicitante e deter a mesma permissao da accao. Auto-aprovacao nunca e
 * possivel. A execucao real do estorno reutiliza estornarPagamento (intacto); a
 * da reabertura usa sige_fin_reabertura_caixa_executar (extraida do handler).
 */

if (!defined('ABSPATH') && !defined('SIGE_APROVACOES_TEST_MODE')) { exit; }

if (!function_exists('sige_fin_aprovacao_tipos')) {
    /** Tipos suportados e a permissao exigida para cada um. */
    function sige_fin_aprovacao_tipos(): array {
        return [
            'estorno_pagamento' => [
                'rotulo'     => 'Estorno de pagamento',
                'permissao'  => 'financeiro.estornar',
            ],
            'reabertura_caixa'  => [
                'rotulo'     => 'Reabertura de caixa',
                'permissao'  => 'financeiro.caixa_reabrir',
            ],
            'recon_conciliar'   => [
                'rotulo'     => 'Conciliar transacao (reconciliacao, quatro-olhos)',
                'permissao'  => 'financeiro.mobile_payments_gerir',
            ],
            'recon_rejeitar'    => [
                'rotulo'     => 'Rejeitar transacao (reconciliacao, quatro-olhos)',
                'permissao'  => 'financeiro.mobile_payments_gerir',
            ],
        ];
    }
}

if (!function_exists('sige_fin_aprovacao_permissao_do_tipo')) {
    function sige_fin_aprovacao_permissao_do_tipo(string $tipo): string {
        $t = sige_fin_aprovacao_tipos();
        return $t[$tipo]['permissao'] ?? '';
    }
}

if (!function_exists('sige_fin_aprovacao_tipo_rotulo')) {
    function sige_fin_aprovacao_tipo_rotulo(string $tipo): string {
        $t = sige_fin_aprovacao_tipos();
        return $t[$tipo]['rotulo'] ?? $tipo;
    }
}

if (!function_exists('sige_fin_aprovacao_tem_permissao')) {
    /** Detem a permissao de uma accao (ou e administrador real). */
    function sige_fin_aprovacao_tem_permissao(string $tipo): bool {
        $perm = sige_fin_aprovacao_permissao_do_tipo($tipo);
        if ($perm === '') return false;
        if (function_exists('sige_can') && sige_can($perm)) return true;
        if (function_exists('sige_is_real_wp_admin_user') && sige_is_real_wp_admin_user()) return true;
        return false;
    }
}

if (!function_exists('sige_fin_aprovacao_pode_aceder')) {
    /** Pode aceder ao ecra de aprovacoes (detem pelo menos uma das permissoes). */
    function sige_fin_aprovacao_pode_aceder(): bool {
        foreach (array_keys(sige_fin_aprovacao_tipos()) as $tipo) {
            if (sige_fin_aprovacao_tem_permissao($tipo)) return true;
        }
        return false;
    }
}

if (!function_exists('sige_fin_aprovacao_solicitar')) {
    /**
     * Cria um pedido de aprovacao pendente. Nao executa nada.
     *
     * @return array ['ok'=>bool, 'id'=>int] ou ['ok'=>false, 'error'=>string]
     */
    function sige_fin_aprovacao_solicitar(string $tipo, int $alvo_id, array $parametros, int $escola_id, string $alvo_ref = ''): array {
        global $wpdb;

        if ($escola_id <= 0) return ['ok' => false, 'error' => 'Contexto de escola invalido.'];
        if (function_exists('sige_tenant_write_guard') && !sige_tenant_write_guard($escola_id, 'aprovacao_solicitar')) {
            return ['ok' => false, 'error' => 'Contexto de escola invalido.'];
        }
        if (!isset(sige_fin_aprovacao_tipos()[$tipo])) {
            return ['ok' => false, 'error' => 'Tipo de operacao desconhecido.'];
        }
        if (!sige_fin_aprovacao_tem_permissao($tipo)) {
            return ['ok' => false, 'error' => 'Sem permissao para solicitar esta operacao.'];
        }

        $tA = $wpdb->prefix . 'sige_fin_aprovacoes';

        // Evitar pedidos pendentes duplicados para o mesmo alvo.
        $ja = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tA} WHERE escola_id = %d AND tipo = %s AND alvo_id = %d AND estado = 'pendente' LIMIT 1",
            $escola_id, $tipo, $alvo_id
        ));
        if ($ja) {
            return ['ok' => false, 'error' => 'Ja existe um pedido pendente para esta operacao. Aguarde a decisao de outro utilizador autorizado.'];
        }

        $user = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
        $uid  = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $nome = $user && isset($user->display_name) ? (string) $user->display_name : '';

        $ok = $wpdb->insert($tA, [
            'escola_id'           => $escola_id,
            'tipo'                => $tipo,
            'alvo_id'             => $alvo_id,
            'alvo_ref'            => $alvo_ref !== '' ? $alvo_ref : null,
            'parametros'          => wp_json_encode($parametros),
            'estado'              => 'pendente',
            'solicitante_user_id' => $uid,
            'solicitante_nome'    => $nome,
            'solicitado_em'       => current_time('mysql'),
        ]);

        if (!$ok) {
            return ['ok' => false, 'error' => 'Nao foi possivel registar o pedido.'];
        }
        $id = (int) $wpdb->insert_id;

        if (function_exists('sige_fin_log')) {
            sige_fin_log('aprovacao_solicitada', ['id' => $id, 'tipo' => $tipo, 'alvo_id' => $alvo_id, 'user' => $uid]);
        }
        if (function_exists('sige_ledger_append')) {
            sige_ledger_append('fin_aprovacao_solicitada', 'aprovacao', $id, null, [
                'tipo' => $tipo, 'alvo_id' => $alvo_id, 'solicitante' => $uid,
            ], $escola_id);
        }

        return ['ok' => true, 'id' => $id];
    }
}

if (!function_exists('sige_fin_aprovacao_get')) {
    function sige_fin_aprovacao_get(int $aprovacao_id, int $escola_id) {
        global $wpdb;
        if ($aprovacao_id <= 0 || $escola_id <= 0) return null;
        $tA = $wpdb->prefix . 'sige_fin_aprovacoes';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tA} WHERE id = %d AND escola_id = %d", $aprovacao_id, $escola_id
        ));
    }
}

if (!function_exists('sige_fin_aprovacoes_listar')) {
    function sige_fin_aprovacoes_listar(int $escola_id, string $estado = 'pendente', int $limite = 100): array {
        global $wpdb;
        if ($escola_id <= 0) return [];
        $tA = $wpdb->prefix . 'sige_fin_aprovacoes';
        $limite = max(1, min(500, $limite));
        if ($estado === 'todos') {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$tA} WHERE escola_id = %d ORDER BY id DESC LIMIT %d", $escola_id, $limite
            )) ?: [];
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tA} WHERE escola_id = %d AND estado = %s ORDER BY id DESC LIMIT %d",
            $escola_id, $estado, $limite
        )) ?: [];
    }
}

if (!function_exists('sige_fin_aprovacao_executar')) {
    /**
     * Executa a accao subjacente de um pedido aprovado.
     * Reutiliza estornarPagamento (estorno) e sige_fin_reabertura_caixa_executar
     * (reabertura). Devolve o resultado da accao (que pode pedir MFA).
     */
    function sige_fin_aprovacao_executar($registo, int $escola_id): array {
        $tipo = (string) $registo->tipo;
        $params = json_decode((string) $registo->parametros, true);
        if (!is_array($params)) $params = [];

        if ($tipo === 'estorno_pagamento') {
            if (!class_exists('SIGE_FinanceActionService') || !method_exists('SIGE_FinanceActionService', 'estornarPagamento')) {
                return ['ok' => false, 'error' => 'Servico de estorno indisponivel.'];
            }
            return SIGE_FinanceActionService::estornarPagamento(
                (int) $registo->alvo_id,
                (string) ($params['motivo'] ?? ''),
                $escola_id,
                (float) ($params['valor'] ?? 0.0)
            );
        }

        if ($tipo === 'reabertura_caixa') {
            if (!function_exists('sige_fin_reabertura_caixa_executar')) {
                return ['ok' => false, 'error' => 'Servico de reabertura indisponivel.'];
            }
            return sige_fin_reabertura_caixa_executar(
                (string) ($params['data_caixa'] ?? ''),
                (string) ($params['motivo'] ?? ''),
                $escola_id
            );
        }

        if ($tipo === 'recon_conciliar') {
            if (!function_exists('sige_recon_executar_conciliar')) {
                return ['ok' => false, 'error' => 'Servico de reconciliacao indisponivel.'];
            }
            return sige_recon_executar_conciliar((int) $registo->alvo_id, (int) ($params['lancamento_id'] ?? 0), $escola_id);
        }

        if ($tipo === 'recon_rejeitar') {
            if (!function_exists('sige_recon_executar_rejeitar')) {
                return ['ok' => false, 'error' => 'Servico de reconciliacao indisponivel.'];
            }
            return sige_recon_executar_rejeitar((int) $registo->alvo_id, (string) ($params['motivo'] ?? ''), $escola_id);
        }

        return ['ok' => false, 'error' => 'Tipo de operacao desconhecido.'];
    }
}

if (!function_exists('sige_fin_aprovacao_decidir')) {
    /**
     * Decide (aprova ou rejeita) um pedido pendente.
     *
     * Regras: o decisor tem de deter a permissao da accao E ser utilizador
     * diferente do solicitante (sem auto-aprovacao). Na aprovacao, executa a
     * accao subjacente; se esta pedir MFA, o pedido fica pendente e devolve-se
     * mfa_required para o ecra apresentar o desafio.
     *
     * @return array ['ok'=>bool, ...] ou ['ok'=>false, 'mfa_required'=>true, ...]
     */
    function sige_fin_aprovacao_decidir(int $aprovacao_id, bool $aprovar, string $motivo, int $escola_id): array {
        global $wpdb;

        if ($escola_id <= 0) return ['ok' => false, 'error' => 'Contexto de escola invalido.'];
        if (function_exists('sige_tenant_write_guard') && !sige_tenant_write_guard($escola_id, 'aprovacao_decidir')) {
            return ['ok' => false, 'error' => 'Contexto de escola invalido.'];
        }

        $registo = sige_fin_aprovacao_get($aprovacao_id, $escola_id);
        if (!$registo) return ['ok' => false, 'error' => 'Pedido inexistente nesta escola.'];
        if ($registo->estado !== 'pendente') {
            return ['ok' => false, 'error' => 'Este pedido ja foi decidido.'];
        }

        $tipo = (string) $registo->tipo;
        if (!sige_fin_aprovacao_tem_permissao($tipo)) {
            return ['ok' => false, 'error' => 'Sem permissao para decidir esta operacao.'];
        }

        $uid = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;

        // Separacao de funcoes: o decisor nao pode ser o solicitante.
        if ($uid > 0 && (int) $registo->solicitante_user_id === $uid) {
            return ['ok' => false, 'error' => 'Nao pode aprovar nem rejeitar o seu proprio pedido. E necessario um segundo utilizador autorizado.'];
        }

        $tA = $wpdb->prefix . 'sige_fin_aprovacoes';
        $user = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
        $nome = $user && isset($user->display_name) ? (string) $user->display_name : '';
        $agora = current_time('mysql');

        // Rejeicao.
        if (!$aprovar) {
            $wpdb->update($tA, [
                'estado'            => 'rejeitada',
                'aprovador_user_id' => $uid,
                'aprovador_nome'    => $nome,
                'decidido_em'       => $agora,
                'decisao_motivo'    => $motivo,
            ], ['id' => $aprovacao_id, 'escola_id' => $escola_id]);

            if (function_exists('sige_fin_log')) sige_fin_log('aprovacao_rejeitada', ['id' => $aprovacao_id, 'tipo' => $tipo, 'por' => $uid]);
            if (function_exists('sige_ledger_append')) {
                sige_ledger_append('fin_aprovacao_rejeitada', 'aprovacao', $aprovacao_id, null, [
                    'tipo' => $tipo, 'alvo_id' => (int) $registo->alvo_id, 'aprovador' => $uid, 'motivo' => $motivo,
                ], $escola_id);
            }
            return ['ok' => true, 'estado' => 'rejeitada'];
        }

        // Aprovacao: executar a accao subjacente.
        $res = sige_fin_aprovacao_executar($registo, $escola_id);

        // Se a accao pede MFA, o pedido fica pendente para nova tentativa.
        if (!empty($res['mfa_required'])) {
            return ['ok' => false, 'mfa_required' => true, 'error' => $res['error'] ?? 'Confirmacao de identidade necessaria.'];
        }

        if (empty($res['ok'])) {
            // Falha de execucao: manter pendente e devolver o erro (pode retentar ou rejeitar).
            return ['ok' => false, 'error' => $res['error'] ?? 'Falha ao executar a operacao aprovada.'];
        }

        // Sucesso: marcar executada e registar.
        $wpdb->update($tA, [
            'estado'            => 'executada',
            'aprovador_user_id' => $uid,
            'aprovador_nome'    => $nome,
            'decidido_em'       => $agora,
            'decisao_motivo'    => $motivo,
            'resultado'         => wp_json_encode($res),
            'executado_em'      => $agora,
        ], ['id' => $aprovacao_id, 'escola_id' => $escola_id]);

        if (function_exists('sige_fin_log')) sige_fin_log('aprovacao_aprovada', ['id' => $aprovacao_id, 'tipo' => $tipo, 'por' => $uid]);
        if (function_exists('sige_ledger_append')) {
            sige_ledger_append('fin_aprovacao_aprovada', 'aprovacao', $aprovacao_id, null, [
                'tipo' => $tipo, 'alvo_id' => (int) $registo->alvo_id, 'aprovador' => $uid, 'solicitante' => (int) $registo->solicitante_user_id,
            ], $escola_id);
        }

        return ['ok' => true, 'estado' => 'executada', 'resultado' => $res];
    }
}
