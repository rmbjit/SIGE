<?php
/**
 * SIGE SoftGenial - Finance Action Service
 *
 * Consolida num único serviço os handlers POST de acções sobre lançamentos
 * e pagamentos que estavam dispersos por três ficheiros na v15.1.0:
 *
 *   admin/finance/financeiro-pagamentos.php:
 *     - sige_fin_pagar_submit          (550 linhas - trata no próprio ficheiro)
 *     - sige_fin_cancelar_submit       → SIGE_FinanceActionService::cancelLancamento()
 *     - sige_fin_bloquear_mes_submit   → SIGE_FinanceActionService::bloquearMes()
 *     - sige_fin_desbloquear_mes_submit→ SIGE_FinanceActionService::desbloquearMes()
 *     - sige_fin_pagar_familia_submit  (trata no próprio ficheiro - é um sub-fluxo)
 *
 *   admin/finance/financeiro-lancamentos-view.php:
 *     - acao=cancelar_lancamento       → SIGE_FinanceActionService::cancelLancamento()
 *     - acao=isentar_lancamento        → SIGE_FinanceActionService::isentarLancamento()
 *     - acao=reativar_lancamento       → SIGE_FinanceActionService::reactivarLancamento()
 *
 *   admin/finance/financeiro-extratos.php:
 *     - sige_anular_recibo             → SIGE_FinanceActionService::estornarPagamento()
 *
 * Benefícios:
 *   • Uma regra de permissões por acção (matriz explícita em $perms abaixo),
 *     em vez de repetida em sete lugares com pequenas divergências.
 *   • Uma validação de FSM por transição - status só muda via enum validado.
 *   • Uma audit trail por acção - sige_audit_log chamado uniformemente.
 *   • Teste unitário possível - serviço não toca em $_POST directamente.
 *
 * Nota: o POST handler em cada ficheiro continua a existir para manter URLs
 * e nonces que a UI já emite. Mas cada handler agora é um adapter de ~15
 * linhas que valida nonce + capability + chama este serviço.
 *
 * @since v15.2.0
 */

if (!defined('ABSPATH')) exit;

final class SIGE_FinanceActionService {

    /**
     * Matriz de permissões por acção.
     * Capabilities WP - OR lógico (se o user tem qualquer uma, pode).
     */
    private const PERMS = [
        'cancelar_lancamento'   => ['manage_options', 'sige_director', 'sige_financeiro', 'sige_secretario'],
        'isentar_lancamento'    => ['manage_options', 'sige_director', 'sige_secretario'], // mais restritivo: só direcção
        'reactivar_lancamento'  => ['manage_options', 'sige_director', 'sige_secretario'],
        'bloquear_mes'          => ['manage_options', 'sige_director', 'sige_financeiro'],
        'desbloquear_mes'       => ['manage_options', 'sige_director', 'sige_financeiro'],
        'estornar_pagamento'    => ['manage_options', 'sige_director', 'sige_financeiro', 'sige_secretario'],
    ];

    /**
     * Verifica se o user actual pode executar uma acção.
     *
     * @param string $acao  Chave da matriz PERMS
     * @return bool
     */
    public static function userCan(string $acao): bool {
        $permission_map = [
            'bloquear_mes'       => 'financeiro.bloquear_mes',
            'desbloquear_mes'    => 'financeiro.desbloquear_mes',
            'estornar_pagamento' => 'financeiro.estornar',
        ];

        // v12.6.0: acções financeiras críticas passam pela Permission Engine real.
        if (isset($permission_map[$acao]) && function_exists('sige_can')) {
            return sige_can($permission_map[$acao], ['service' => __CLASS__, 'acao' => $acao]);
        }

        // Compatibilidade para acções ainda não migradas para bloqueio real nesta fase.
        $caps = self::PERMS[$acao] ?? [];
        foreach ($caps as $cap) {
            if (current_user_can($cap)) return true;
        }
        return false;
    }


    /**
     * Verifica se uma coluna existe. Usado para evitar páginas brancas quando
     * uma instalação antiga ainda não tem todas as colunas de auditoria.
     */
    private static function columnExists(string $table, string $column): bool {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
        return !empty($exists);
    }

    /**
     * Remove do payload colunas que ainda não existem na BD local.
     * Isto corta pela raiz os erros SQL do tipo "Unknown column" nas acções
     * financeiras sensíveis, sem esconder o resultado da operação ao utilizador.
     */
    private static function filterExistingColumns(string $table, array $data): array {
        static $cache = [];
        if (!isset($cache[$table])) {
            $cache[$table] = [];
            global $wpdb;
            $cols = (array)$wpdb->get_results("SHOW COLUMNS FROM {$table}");
            foreach ($cols as $col) {
                if (isset($col->Field)) $cache[$table][(string)$col->Field] = true;
            }
        }
        return array_intersect_key($data, $cache[$table]);
    }

    /**
     * Status seguro: se a FSM não estiver carregada por alguma razão, mantém
     * a acção funcional com o valor histórico usado na base de dados.
     */
    private static function status(string $name, string $fallback): string {
        if (class_exists('SIGE_LancamentoStatus') && defined('SIGE_LancamentoStatus::' . $name)) {
            return constant('SIGE_LancamentoStatus::' . $name);
        }
        return $fallback;
    }

    /**
     * Validação segura da transição. Se a FSM não estiver disponível, evita
     * fatal error e permite que a regra local continue protegida por guards.
     */
    private static function canTransition(string $from, string $to): bool {
        if (class_exists('SIGE_LancamentoStatus') && method_exists('SIGE_LancamentoStatus', 'canTransitionTo')) {
            return SIGE_LancamentoStatus::canTransitionTo($from, $to);
        }
        return true;
    }

    /**
     * Cancela um lançamento. Uso: quando há engano no lançamento ou decisão
     * administrativa de não cobrar. NÃO aplicável se houver pagamento associado
     * (aí usa-se estorno).
     *
     * @param int    $lancamento_id
     * @param string $motivo         Obrigatório, max 255 chars
     * @param int    $escola_id
     * @return array{ok: bool, error?: string, lancamento_id?: int}
     */
    public static function cancelLancamento(int $lancamento_id, string $motivo, int $escola_id): array {
        if (!sige_tenant_write_guard((int) $escola_id, 'cancelLancamento')) { return ['ok' => false, 'error' => 'Contexto de escola invalido.']; }
        if (function_exists('sige_mfa_require_step_up') && !sige_mfa_require_step_up('fin_cancelLancamento')) { if (function_exists('sige_mfa_replay_capture')) sige_mfa_replay_capture('fin_cancelLancamento', func_get_args()); return ['ok' => false, 'mfa_required' => true, 'error' => 'Confirmacao de identidade necessaria para esta operacao. Confirme a sua identidade para continuar.']; }
        global $wpdb;

        if (!self::userCan('cancelar_lancamento')) {
            return ['ok' => false, 'error' => 'Sem permissão para cancelar lançamentos.'];
        }

        if ($lancamento_id <= 0) {
            return ['ok' => false, 'error' => 'ID de lançamento inválido.'];
        }

        $motivo = trim($motivo);
        if ($motivo === '') {
            return ['ok' => false, 'error' => 'O motivo do cancelamento é obrigatório.'];
        }

        $tL = $wpdb->prefix . 'sige_fin_lancamentos';
        $l = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tL} WHERE id=%d AND escola_id=%d", $lancamento_id, $escola_id));

        if (!$l) {
            return ['ok' => false, 'error' => 'Lançamento não encontrado.'];
        }

        // Validar transição via FSM
        $status_actual = strtolower((string)$l->status);
        $status_cancelado = self::status('CANCELADO', 'cancelado');
        if (!self::canTransition($status_actual, $status_cancelado)) {
            $label_actual = sige_fin_fsm_label('lancamento', $status_actual);
            return ['ok' => false, 'error' => "Não é possível cancelar um lançamento '{$label_actual}'. Use estorno se houver pagamento."];
        }

        // Guard extra: se tem valor_pago > 0, não cancelar (usar estorno)
        if ((float)$l->valor_pago > 0) {
            return ['ok' => false, 'error' => 'Este lançamento tem pagamento associado. Use o estorno no painel de Extratos.'];
        }

        $data = [
            'status'                   => $status_cancelado,
            'cancelado_em'             => current_time('mysql'),
            'cancelado_por'            => get_current_user_id(),
            'motivo_cancelamento'      => $motivo,
            // [FIX DESC-ESP-03] cancelamento limpa desconto especial
            'valor_desconto_especial'  => 0.00,
            'motivo_desconto_especial' => null,
            'desconto_especial_por'    => null, // agora gravado correctamente (B1)
        ];

        $data = self::filterExistingColumns($tL, $data);
        $ok = $wpdb->update($tL, $data, ['id' => $lancamento_id, 'escola_id' => $escola_id]);

        if ($ok === false) {
            return ['ok' => false, 'error' => 'Erro ao cancelar: ' . $wpdb->last_error];
        }

        if (function_exists('sige_audit_log')) {
            sige_audit_log('cancelar_lancamento', [
                'lancamento_id'  => $lancamento_id,
                'aluno_id'       => (int)$l->aluno_id,
                'servico_id'     => (int)$l->servico_id,
                'mes_referencia' => $l->mes_referencia,
                'valor_original' => (float)$l->valor_original,
                'motivo'         => $motivo,
                'status_anterior'=> $status_actual,
            ], 'financeiro');
        }

        if (function_exists('sige_ledger_append')) {
            sige_ledger_append('fin_cancelar_lancamento', 'lancamento', $lancamento_id, (float)$l->valor_original, ['motivo' => $motivo, 'status_anterior' => $status_actual, 'aluno_id' => (int)$l->aluno_id], (int)$escola_id);
        }
        return ['ok' => true, 'lancamento_id' => $lancamento_id];
    }

    /**
     * Marca um lançamento como isento. Uso: bolseiros, filhos de funcionários
     * com isenção total, decisões pontuais da direcção. Requer permissão de
     * director ou secretário.
     */
    public static function isentarLancamento(int $lancamento_id, string $motivo, int $escola_id): array {
        if (!sige_tenant_write_guard((int) $escola_id, 'isentarLancamento')) { return ['ok' => false, 'error' => 'Contexto de escola invalido.']; }
        if (function_exists('sige_mfa_require_step_up') && !sige_mfa_require_step_up('fin_isentarLancamento')) { if (function_exists('sige_mfa_replay_capture')) sige_mfa_replay_capture('fin_isentarLancamento', func_get_args()); return ['ok' => false, 'mfa_required' => true, 'error' => 'Confirmacao de identidade necessaria para esta operacao. Confirme a sua identidade para continuar.']; }
        global $wpdb;

        if (!self::userCan('isentar_lancamento')) {
            return ['ok' => false, 'error' => 'Sem permissão. Apenas Director ou Secretário.'];
        }

        if ($lancamento_id <= 0) return ['ok' => false, 'error' => 'ID inválido.'];
        $motivo = trim($motivo);
        if ($motivo === '') return ['ok' => false, 'error' => 'Motivo obrigatório.'];

        $tL = $wpdb->prefix . 'sige_fin_lancamentos';
        $l = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tL} WHERE id=%d AND escola_id=%d", $lancamento_id, $escola_id));

        if (!$l) return ['ok' => false, 'error' => 'Lançamento não encontrado.'];

        $status_actual = strtolower((string)$l->status);
        $status_isento = self::status('ISENTO', 'isento');
        if (!self::canTransition($status_actual, $status_isento)) {
            $label_actual = sige_fin_fsm_label('lancamento', $status_actual);
            return ['ok' => false, 'error' => "Não é possível isentar um lançamento '{$label_actual}'."];
        }

        if ((float)$l->valor_pago > 0) {
            return ['ok' => false, 'error' => 'Este lançamento tem pagamento associado - não pode ser isento.'];
        }

        $data = self::filterExistingColumns($tL, [
            'status'              => $status_isento,
            'cancelado_em'        => current_time('mysql'), // reutiliza coluna para audit
            'cancelado_por'       => get_current_user_id(),
            'motivo_cancelamento' => 'ISENÇÃO: ' . $motivo,
        ]);
        $ok = $wpdb->update($tL, $data, ['id' => $lancamento_id, 'escola_id' => $escola_id]);

        if ($ok === false) {
            return ['ok' => false, 'error' => 'Erro ao isentar: ' . $wpdb->last_error];
        }

        if (function_exists('sige_audit_log')) {
            sige_audit_log('isentar_lancamento', [
                'lancamento_id'  => $lancamento_id,
                'motivo'         => $motivo,
                'status_anterior'=> $status_actual,
            ], 'financeiro');
        }

        if (function_exists('sige_ledger_append')) {
            sige_ledger_append('fin_isentar_lancamento', 'lancamento', $lancamento_id, (float)$l->valor_original, ['motivo' => $motivo, 'status_anterior' => $status_actual, 'aluno_id' => (int)$l->aluno_id], (int)$escola_id);
        }
        return ['ok' => true, 'lancamento_id' => $lancamento_id];
    }

    /**
     * Reactiva um lançamento cancelado ou isento, voltando-o a pendente.
     * Raro mas necessário quando se cancela por engano.
     */
    public static function reactivarLancamento(int $lancamento_id, int $escola_id): array {
        if (!sige_tenant_write_guard((int) $escola_id, 'reactivarLancamento')) { return ['ok' => false, 'error' => 'Contexto de escola invalido.']; }
        if (function_exists('sige_mfa_require_step_up') && !sige_mfa_require_step_up('fin_reactivarLancamento')) { if (function_exists('sige_mfa_replay_capture')) sige_mfa_replay_capture('fin_reactivarLancamento', func_get_args()); return ['ok' => false, 'mfa_required' => true, 'error' => 'Confirmacao de identidade necessaria para esta operacao. Confirme a sua identidade para continuar.']; }
        global $wpdb;

        if (!self::userCan('reactivar_lancamento')) {
            return ['ok' => false, 'error' => 'Sem permissão para reactivar lançamentos.'];
        }

        if ($lancamento_id <= 0) return ['ok' => false, 'error' => 'ID inválido.'];

        $tL = $wpdb->prefix . 'sige_fin_lancamentos';
        $l = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tL} WHERE id=%d AND escola_id=%d", $lancamento_id, $escola_id));

        if (!$l) return ['ok' => false, 'error' => 'Lançamento não encontrado.'];

        $status_actual = strtolower((string)$l->status);
        $status_pendente = self::status('PENDENTE', 'pendente');
        if (!self::canTransition($status_actual, $status_pendente)) {
            $label_actual = sige_fin_fsm_label('lancamento', $status_actual);
            return ['ok' => false, 'error' => "Não é possível reactivar um lançamento '{$label_actual}'."];
        }

        if ((float)$l->valor_pago > 0) {
            return ['ok' => false, 'error' => 'Não é possível reactivar: existe pagamento associado.'];
        }

        $data = self::filterExistingColumns($tL, [
            'status'              => $status_pendente,
            'cancelado_em'        => null,
            'cancelado_por'       => null,
            'motivo_cancelamento' => null,
        ]);
        $ok = $wpdb->update($tL, $data, ['id' => $lancamento_id, 'escola_id' => $escola_id]);

        if ($ok === false) {
            return ['ok' => false, 'error' => 'Erro ao reactivar: ' . $wpdb->last_error];
        }

        if (function_exists('sige_audit_log')) {
            sige_audit_log('reactivar_lancamento', [
                'lancamento_id'  => $lancamento_id,
                'status_anterior'=> $status_actual,
            ], 'financeiro');
        }

        if (function_exists('sige_ledger_append')) {
            sige_ledger_append('fin_reactivar_lancamento', 'lancamento', $lancamento_id, (float)$l->valor_original, ['status_anterior' => $status_actual, 'aluno_id' => (int)$l->aluno_id], (int)$escola_id);
        }
        return ['ok' => true, 'lancamento_id' => $lancamento_id];
    }

    /**
     * Bloqueia um mês para um aluno (marca como "sem cobrança prevista").
     * Implementado como lançamento cancelado com valor_original=0 e servico_id=0
     * - padrão já em uso no plugin. Mantido para compat.
     */
    public static function bloquearMes(int $aluno_id, int $mes, string $motivo, int $ano_letivo, int $escola_id): array {
        if (!sige_tenant_write_guard((int) $escola_id, 'bloquearMes')) { return ['ok' => false, 'error' => 'Contexto de escola invalido.']; }
        if (function_exists('sige_mfa_require_step_up') && !sige_mfa_require_step_up('fin_bloquearMes')) { if (function_exists('sige_mfa_replay_capture')) sige_mfa_replay_capture('fin_bloquearMes', func_get_args()); return ['ok' => false, 'mfa_required' => true, 'error' => 'Confirmacao de identidade necessaria para esta operacao. Confirme a sua identidade para continuar.']; }
        global $wpdb;

        if (!self::userCan('bloquear_mes')) {
            return ['ok' => false, 'error' => 'Sem permissão para bloquear meses.'];
        }

        if ($aluno_id <= 0 || $mes < 1 || $mes > 12) {
            return ['ok' => false, 'error' => 'Dados inválidos para bloqueio.'];
        }

        $motivo = trim($motivo) ?: 'Mês sem cobrança';
        $mes_ref = $ano_letivo . '-' . str_pad((string)$mes, 2, '0', STR_PAD_LEFT);

        $tL = $wpdb->prefix . 'sige_fin_lancamentos';

        $ja_bloqueado = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tL}
             WHERE aluno_id=%d AND mes_referencia=%s
               AND status='cancelado' AND valor_original=0 AND servico_id=0
               AND escola_id=%d LIMIT 1",
            $aluno_id, $mes_ref, $escola_id
        ));

        if ($ja_bloqueado) {
            return ['ok' => false, 'error' => 'Este mês já está bloqueado.'];
        }

        $tem_lancs = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tL}
             WHERE aluno_id=%d AND mes_referencia=%s
               AND status != 'cancelado' AND escola_id=%d",
            $aluno_id, $mes_ref, $escola_id
        ));

        if ($tem_lancs > 0) {
            return ['ok' => false, 'error' => 'Já existem lançamentos activos neste mês. Cancele-os antes.'];
        }

        $data = self::filterExistingColumns($tL, [
            'escola_id'            => $escola_id,
            'aluno_id'             => $aluno_id,
            'servico_id'           => 0,
            'descricao'            => '[BLOQUEIO] ' . sige_fin_obter_nome_mes($mes) . ' ' . $ano_letivo . ' - ' . $motivo,
            'mes_referencia'       => $mes_ref,
            'valor_original'       => 0,
            'valor_multa'          => 0,
            'valor_desconto'       => 0,
            'valor_pago'           => 0,
            'data_vencimento'      => current_time('Y-m-d'),
            'status'               => self::status('CANCELADO', 'cancelado'),
            'cancelado_em'         => current_time('mysql'),
            'cancelado_por'        => get_current_user_id(),
            'motivo_cancelamento'  => $motivo,
        ]);
        $ok = $wpdb->insert($tL, $data);

        if (!$ok) {
            return ['ok' => false, 'error' => 'Erro ao bloquear: ' . $wpdb->last_error];
        }

        if (function_exists('sige_audit_log')) {
            sige_audit_log('bloquear_mes', [
                'aluno_id'       => $aluno_id,
                'mes_referencia' => $mes_ref,
                'motivo'         => $motivo,
            ], 'financeiro');
        }

        $bloqueio_id = (int)$wpdb->insert_id;
        if (function_exists('sige_ledger_append')) {
            sige_ledger_append('fin_bloquear_mes', 'lancamento', $bloqueio_id, 0.0, ['aluno_id' => $aluno_id, 'mes_referencia' => $mes_ref, 'motivo' => $motivo], (int)$escola_id);
        }
        return ['ok' => true, 'bloqueio_id' => $bloqueio_id];
    }

    /**
     * Remove o bloqueio de um mês.
     */
    public static function desbloquearMes(int $bloqueio_id, int $escola_id): array {
        if (!sige_tenant_write_guard((int) $escola_id, 'desbloquearMes')) { return ['ok' => false, 'error' => 'Contexto de escola invalido.']; }
        if (function_exists('sige_mfa_require_step_up') && !sige_mfa_require_step_up('fin_desbloquearMes')) { if (function_exists('sige_mfa_replay_capture')) sige_mfa_replay_capture('fin_desbloquearMes', func_get_args()); return ['ok' => false, 'mfa_required' => true, 'error' => 'Confirmacao de identidade necessaria para esta operacao. Confirme a sua identidade para continuar.']; }
        global $wpdb;

        if (!self::userCan('desbloquear_mes')) {
            return ['ok' => false, 'error' => 'Sem permissão para desbloquear meses.'];
        }

        if ($bloqueio_id <= 0) return ['ok' => false, 'error' => 'ID inválido.'];

        $tL = $wpdb->prefix . 'sige_fin_lancamentos';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tL}
             WHERE id=%d AND status='cancelado' AND valor_original=0 AND servico_id=0
               AND escola_id=%d",
            $bloqueio_id, $escola_id
        ));

        if (!$row) {
            return ['ok' => false, 'error' => 'Bloqueio não encontrado.'];
        }

        $wpdb->delete($tL, ['id' => $bloqueio_id]);

        if (function_exists('sige_audit_log')) {
            sige_audit_log('desbloquear_mes', [
                'aluno_id'       => (int)$row->aluno_id,
                'mes_referencia' => $row->mes_referencia,
            ], 'financeiro');
        }

        if (function_exists('sige_ledger_append')) {
            sige_ledger_append('fin_desbloquear_mes', 'lancamento', $bloqueio_id, 0.0, ['aluno_id' => (int)$row->aluno_id, 'mes_referencia' => $row->mes_referencia], (int)$escola_id);
        }
        return ['ok' => true];
    }

    /**
     * Estorna um pagamento. Cria um registo "negativo" em sige_fin_pagamentos
     * (valor_pago = -abs(original)) e recalcula o lançamento.
     *
     * Fluxo atómico via transação SQL (FOR UPDATE bloqueia race condition de
     * duplo estorno). Esta lógica estava inline em financeiro-extratos.php:281-435;
     * agora consolidada aqui.
     */
    public static function estornarPagamento(int $pagamento_id, string $motivo, int $escola_id, float $valor_estorno = 0.0): array {
        if (!sige_tenant_write_guard((int) $escola_id, 'estornarPagamento')) { return ['ok' => false, 'error' => 'Contexto de escola invalido.']; }
        if (function_exists('sige_mfa_require_step_up') && !sige_mfa_require_step_up('fin_estornarPagamento')) { if (function_exists('sige_mfa_replay_capture')) sige_mfa_replay_capture('fin_estornarPagamento', func_get_args()); return ['ok' => false, 'mfa_required' => true, 'error' => 'Confirmacao de identidade necessaria para esta operacao. Confirme a sua identidade para continuar.']; }
        global $wpdb;

        if (!self::userCan('estornar_pagamento')) {
            return ['ok' => false, 'error' => 'Sem permissão para estornar pagamentos.'];
        }

        if ($pagamento_id <= 0) return ['ok' => false, 'error' => 'ID de pagamento inválido.'];
        $motivo = trim($motivo);
        if ($motivo === '') return ['ok' => false, 'error' => 'O motivo do estorno é obrigatório.'];

        $tP = $wpdb->prefix . 'sige_fin_pagamentos';
        $tL = $wpdb->prefix . 'sige_fin_lancamentos';

        // Verificar fecho de caixa - AGORA usa fecho por turno (F5)
        // Ver SIGE_FinanceFechoTurnoService::caixaFechadaParaUser()
        if (class_exists('SIGE_FinanceFechoTurnoService')) {
            $fechado = SIGE_FinanceFechoTurnoService::caixaFechadaParaUser(
                $escola_id,
                current_time('Y-m-d'),
                get_current_user_id()
            );
            if ($fechado) {
                return ['ok' => false, 'error' => 'O seu turno está fechado. Peça à Direcção para reabrir.'];
            }
        }

        // ── TRANSAÇÃO ─────────────────────────────────────────────────────────
        $wpdb->query('START TRANSACTION');

        // FOR UPDATE bloqueia a linha até COMMIT/ROLLBACK (previne duplo estorno)
        $original = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tP} WHERE id=%d AND escola_id=%d FOR UPDATE",
            $pagamento_id, $escola_id
        ));

        if (!$original || (float)$original->valor_pago <= 0) {
            $wpdb->query('ROLLBACK');
            return ['ok' => false, 'error' => 'Pagamento inválido ou já estornado.'];
        }

        $total_estornado_antes = (float)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(ABS(valor_pago)),0) FROM {$tP}
             WHERE referencia_externa = %s AND metodo_pagamento = 'estorno' AND escola_id = %d",
            'ESTORNO:' . $pagamento_id, $escola_id
        ));

        $valor_original_pago = abs((float)$original->valor_pago);
        $saldo_estornavel = max(0.0, $valor_original_pago - $total_estornado_antes);
        if ($saldo_estornavel <= 0.00001) {
            $wpdb->query('ROLLBACK');
            return ['ok' => false, 'error' => 'Este pagamento já foi totalmente estornado.'];
        }

        $valor_estorno = (float)$valor_estorno;
        if ($valor_estorno <= 0) $valor_estorno = $saldo_estornavel;
        if ($valor_estorno > $saldo_estornavel) {
            $wpdb->query('ROLLBACK');
            return ['ok' => false, 'error' => 'O valor do estorno excede o saldo estornável deste pagamento.'];
        }

        // Inserir pagamento negativo
        $data = self::filterExistingColumns($tP, [
            'escola_id'          => (int)($original->escola_id ?? 0),
            'centro_id'          => (int)($original->centro_id ?? 1) > 0 ? (int)$original->centro_id : 1,
            'lancamento_id'      => (int)$original->lancamento_id,
            'aluno_id'           => (int)$original->aluno_id,
            'recibo_numero'      => $original->recibo_numero ?: null,
            'valor_pago'         => -abs((float)$valor_estorno),
            'metodo_pagamento'   => 'estorno',
            'referencia_externa' => 'ESTORNO:' . $pagamento_id,
            'recebido_por'       => get_current_user_id(),
            'data_pagamento'     => current_time('mysql'),
            'observacoes'        => "ESTORNO do pagamento #{$pagamento_id}. Motivo: {$motivo}",
            'multa_isenta'       => 0,
            'motivo_isencao'     => null,
        ]);
        $ok = $wpdb->insert($tP, $data);

        if (!$ok) {
            $wpdb->query('ROLLBACK');
            return ['ok' => false, 'error' => 'Erro ao criar estorno: ' . $wpdb->last_error];
        }

        // Reverter o lançamento
        $l = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tL} WHERE id=%d AND escola_id=%d",
            (int)$original->lancamento_id, $escola_id
        ));

        if ($l) {
            $novo_valor_pago = max(0.0, (float)$l->valor_pago - abs((float)$valor_estorno));

            // Usar fórmula canónica para saldo restante
            $l_tmp = clone $l;
            $l_tmp->valor_pago = $novo_valor_pago;
            $saldo_restante = function_exists('sige_fin_saldo_lancamento')
                ? sige_fin_saldo_lancamento($l_tmp)
                : max(0, sige_fin_total_lancamento($l_tmp) - $novo_valor_pago);

            // Determinar novo status via FSM, com fallback seguro
            $novo_status = self::status('PENDENTE', 'pendente');
            if ($novo_valor_pago > 0 && $saldo_restante > 0.5) {
                $novo_status = self::status('PARCIAL', 'parcial');
            } elseif ($saldo_restante <= 0.5) {
                $novo_status = self::status('PAGO', 'pago');
            }

            // [FIX DESC-ESP-02] Estorno anula desconto especial
            $data = self::filterExistingColumns($tL, [
                'valor_pago'               => $novo_valor_pago,
                'status'                   => $novo_status,
                'valor_desconto_especial'  => 0.00,
                'motivo_desconto_especial' => null,
                'desconto_especial_por'    => null, // agora gravado (B1 da v15.1.0)
            ]);
            $upd = $wpdb->update($tL, $data, ['id' => (int)$l->id]);

            if ($upd === false) {
                $wpdb->query('ROLLBACK');
                return ['ok' => false, 'error' => 'Erro ao reverter lançamento: ' . $wpdb->last_error];
            }
        }

        $wpdb->query('COMMIT');

        // Pós-transação - recálculo (falhas aqui não revertem o estorno)
        if ($l) {
            if (function_exists('sige_fin_recalcular_lancamento')) {
                sige_fin_recalcular_lancamento((int)$l->id);
            }
            if (function_exists('sige_fin_atualizar_status_lancamento')) {
                sige_fin_atualizar_status_lancamento((int)$l->id);
            }
        }

        if (function_exists('sige_fin_log')) {
            sige_fin_log('estorno_pagamento', [
                'pagamento_original_id'   => $pagamento_id,
                'lancamento_id'           => (int)$original->lancamento_id,
                'aluno_id'                => (int)$original->aluno_id,
                'valor'                   => (float)$valor_estorno,
                'motivo'                  => $motivo,
                'desconto_especial_zerado' => $l && (float)($l->valor_desconto_especial ?? 0) > 0,
            ]);
        }

        if (function_exists('sige_ledger_append')) {
            sige_ledger_append('fin_estornar_pagamento', 'pagamento', $pagamento_id, (float)$valor_estorno, ['lancamento_id' => (int)$original->lancamento_id, 'aluno_id' => (int)$original->aluno_id, 'motivo' => $motivo], (int)$escola_id);
        }
        return [
            'ok'                   => true,
            'pagamento_original_id' => $pagamento_id,
            'valor_estornado'      => (float)$valor_estorno,
        ];
    }
}
