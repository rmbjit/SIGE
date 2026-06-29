<?php
/**
 * SIGE SoftGenial - State Machine para Despesas
 *
 * [v13.6.0] Introduzida no Bloco 5 - Hardening.
 *
 * Problema resolvido (BUG-MOD-03 da AUDITORIA_v13.1.0_SIGE.md §2):
 *   As mudanças de status em sige_fin_despesas eram feitas com
 *   $wpdb->update() directo, sem validação de transições. Código futuro
 *   (imports, bulk edits) poderia introduzir valores fora dos três estados
 *   suportados pela UI ('registado' | 'aprovado' | 'anulado').
 *
 * Solução: gatekeeper único inspirado em sige_fin_transitar_status() para
 * lançamentos (finance-core.php:2356). Mesmo padrão, matriz específica.
 *
 * Matriz mínima alinhada com o fluxo REAL observado na Casa Colorida:
 *
 *     registado  ──► aprovado  ──► anulado
 *          │                         ▲
 *          └─────────────────────────┘
 *     anulado → terminal
 *
 * NOTA SOBRE O HANDOFF:
 *   O HANDOFF_v13.6.0_PLAN.md §5.2 propunha um modelo rico de 6 estados
 *   ('rascunho', 'pendente', 'aprovada', 'paga', 'estornada', 'anulada'),
 *   mas os call sites reais em admin/finance/financeiro-despesas-view.php
 *   (linhas 79, 103, 124 na v13.5.1) usam apenas três valores. Introduzir
 *   estados que a UI não expõe seria dívida futura, não hardening.
 *
 *   Se um workflow de aprovação formal for adicionado em v13.7+, basta
 *   estender a matriz aqui - a assinatura pública fica estável.
 *
 * @package SIGE_SoftGenial
 * @since   13.6.0
 */

if (!defined('ABSPATH')) exit;

// ─────────────────────────────────────────────────────────────────────────────
// Matriz de transições permitidas
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('sige_fin_despesa_transicoes_permitidas')) {
    /**
     * Devolve a matriz completa de transições válidas.
     *
     * Exposta como função (em vez de constante) para permitir que testes
     * e inspectores de debug leiam sem depender de reflection.
     *
     * @return array<string, array<int, string>>
     */
    function sige_fin_despesa_transicoes_permitidas(): array {
        return [
            'registado' => ['aprovado', 'anulado'],
            'aprovado'  => ['anulado'],
            'anulado'   => [], // terminal
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Validador puro (sem side-effects)
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('sige_fin_despesa_transicao_permitida')) {
    /**
     * Pergunta se uma transição (de → para) é permitida.
     *
     * Útil para a UI decidir se mostra ou não um botão (ex: esconder
     * "Aprovar" numa despesa já anulada).
     *
     * @param string $de   Estado actual.
     * @param string $para Estado alvo.
     * @return bool
     */
    function sige_fin_despesa_transicao_permitida(string $de, string $para): bool {
        $matriz = sige_fin_despesa_transicoes_permitidas();
        return isset($matriz[$de]) && in_array($para, $matriz[$de], true);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Gatekeeper com side-effect (UPDATE + audit)
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('sige_fin_despesa_transitar_status')) {
    /**
     * Transita o status de uma despesa, validando contra a matriz.
     *
     * Retorna true se a transição foi efectuada, false se foi bloqueada
     * (seja por ID inválido, seja por transição proibida). Em ambos os
     * casos, regista em sige_logs_auditoria para ser auditável.
     *
     * NÃO altera outras colunas - se o call site precisa de gravar
     * `aprovado_por` ao mesmo tempo (caso da aprovação), deve fazê-lo
     * em UPDATE separado após confirmação deste helper, ou passar via
     * $extra_data.
     *
     * Multi-tenant: o escola_id é extraído da despesa antes da transição
     * para prevenir cross-tenant. O call site é responsável por garantir
     * que só invoca em despesas visíveis ao utilizador (continua a ser
     * necessário o `escola_id = X` no WHERE do SELECT upstream).
     *
     * @param int    $despesa_id   ID da despesa.
     * @param string $novo_status  'registado' | 'aprovado' | 'anulado'.
     * @param string $motivo       Texto livre para audit (ex: "aprovação manual").
     * @param array  $extra_data   Colunas adicionais a actualizar em conjunto
     *                             com o status (ex: ['aprovado_por' => 5]).
     *                             Não pode conter a chave 'status'.
     * @return bool  true se UPDATE efectuado, false se bloqueado.
     */
    function sige_fin_despesa_transitar_status(
        int $despesa_id,
        string $novo_status,
        string $motivo = '',
        array $extra_data = []
    ): bool {
        global $wpdb;

        $tD = $wpdb->prefix . 'sige_fin_despesas';

        // 1. Ler estado actual (SELECT mínimo - só o que é preciso)
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, escola_id, status FROM {$tD} WHERE id = %d",
            $despesa_id
        ));

        if (!$row) {
            if (function_exists('sige_fin_log')) {
                sige_fin_log('despesa_transicao_bloqueada', [
                    'despesa_id' => $despesa_id,
                    'razao'      => 'despesa_inexistente',
                    'para'       => $novo_status,
                    'motivo'     => $motivo,
                ]);
            }
            return false;
        }

        $actual = (string)$row->status;

        // 2. Validar contra matriz
        if (!sige_fin_despesa_transicao_permitida($actual, $novo_status)) {
            if (function_exists('sige_fin_log')) {
                sige_fin_log('despesa_transicao_bloqueada', [
                    'despesa_id' => $despesa_id,
                    'de'         => $actual,
                    'para'       => $novo_status,
                    'motivo'     => $motivo,
                    'razao'      => 'transicao_proibida',
                ]);
            }
            return false;
        }

        // 3. Filtrar $extra_data - nunca permitir override de 'status'
        // (se o caller passou status, usamos o $novo_status do parâmetro,
        // que é a única fonte de verdade desta função).
        unset($extra_data['status']);

        $update_data = array_merge($extra_data, ['status' => $novo_status]);

        // 4. UPDATE com WHERE scoped (id + escola_id herdado)
        $ok = $wpdb->update(
            $tD,
            $update_data,
            ['id' => $despesa_id, 'escola_id' => (int)$row->escola_id]
        );

        if ($ok === false) {
            if (function_exists('sige_fin_log')) {
                sige_fin_log('despesa_transicao_falha_sql', [
                    'despesa_id' => $despesa_id,
                    'de'         => $actual,
                    'para'       => $novo_status,
                    'erro'       => $wpdb->last_error,
                ]);
            }
            return false;
        }

        // 5. Audit da transição bem-sucedida
        if (function_exists('sige_fin_log')) {
            sige_fin_log('despesa_transicao', [
                'despesa_id' => $despesa_id,
                'de'         => $actual,
                'para'       => $novo_status,
                'motivo'     => $motivo,
                'extra'      => $extra_data ?: null,
                'user_id'    => get_current_user_id(),
            ]);
        }

        if (function_exists('sige_ledger_append')) {
            sige_ledger_append('fin_despesa_transitar', 'despesa', $despesa_id, null, ['de' => $actual, 'para' => $novo_status, 'motivo' => $motivo], (int)$row->escola_id);
        }
        return true;
    }
}
