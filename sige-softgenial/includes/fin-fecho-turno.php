<?php
/**
 * SIGE SoftGenial - Fecho de Caixa por Turno
 *
 * Resolve a fragilidade F5: na v15.1.0 o fecho de caixa é por
 * (escola_id, data_caixa) - fecha o dia todo para todos os utilizadores.
 *
 * Numa escola com secretário da manhã e secretária da tarde, isto é rigoroso
 * demais: uma sessão fecha às 12h e a outra não consegue receber à tarde.
 * Pior - um estorno de Sexta à tarde não pode ser feito na Segunda de manhã
 * sem reabrir o caixa de Sexta, o que distorce os totais.
 *
 * Solução: fecho escopado por (escola_id, data_caixa, user_id_turno).
 * Um utilizador pode fechar o seu turno; outro continua a receber.
 *
 * A coluna user_id_turno foi adicionada pela migration M20. Registos
 * pré-existentes ficam com NULL - interpretado como "fecho geral do dia"
 * (retrocompat: todos ficam fechados).
 *
 * @since v15.2.0
 */

if (!defined('ABSPATH')) exit;

final class SIGE_FinanceFechoTurnoService {
    /**
     * Verifica existência de coluna sem disparar erro SQL em instalações antigas.
     */
    private static function columnExists(string $table, string $column): bool {
        global $wpdb;
        static $cache = [];
        $key = $table . '::' . $column;
        if (array_key_exists($key, $cache)) {
            return (bool)$cache[$key];
        }
        $found = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
        $cache[$key] = !empty($found);
        return (bool)$cache[$key];
    }

    /**
     * Remove campos inexistentes antes de INSERT/UPDATE.
     */
    private static function filterExistingColumns(string $table, array $data): array {
        foreach (array_keys($data) as $col) {
            if (!self::columnExists($table, (string)$col)) {
                unset($data[$col]);
            }
        }
        return $data;
    }

    /**
     * Verifica se a caixa está fechada para um utilizador específico numa data.
     *
     * Semântica:
     *   • Se existe fecho com user_id_turno = $user_id AND status='fechado' → fechado
     *   • Se existe fecho com user_id_turno IS NULL AND status='fechado'   → fechado geral
     *   • Caso contrário → aberto
     *
     * @param int    $escola_id
     * @param string $data       Formato 'Y-m-d'
     * @param int    $user_id    Utilizador que tenta operar
     * @return bool              true se fechado para este user
     */
    public static function caixaFechadaParaUser(int $escola_id, string $data, int $user_id): bool {
        global $wpdb;
        $tF = $wpdb->prefix . 'sige_fin_fechos_caixa';

        // Hotfix 12.5.4: compatibilidade com instâncias cuja tabela de fechos
        // ainda não tem a coluna user_id_turno. Nesses casos, usa-se a regra
        // legada de fecho diário, sem consultar coluna inexistente.
        if (!self::columnExists($tF, 'user_id_turno')) {
            $fechado = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(1) FROM {$tF}
                  WHERE escola_id = %d
                    AND data_caixa = %s
                    AND status = 'fechado'
                  LIMIT 1",
                $escola_id, $data
            ));
            return $fechado > 0;
        }

        $fechado = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$tF}
              WHERE escola_id = %d
                AND data_caixa = %s
                AND status = 'fechado'
                AND (user_id_turno = %d OR user_id_turno IS NULL)
              LIMIT 1",
            $escola_id, $data, $user_id
        ));

        return $fechado > 0;
    }
    /**
     * Verifica se existe QUALQUER fecho no dia (retrocompat para relatórios
     * que querem saber "o dia foi fechado?" independentemente de quem).
     */
    public static function caixaFechadaNoDia(int $escola_id, string $data): bool {
        global $wpdb;
        $tF = $wpdb->prefix . 'sige_fin_fechos_caixa';

        $fechado = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$tF}
              WHERE escola_id = %d AND data_caixa = %s AND status = 'fechado'
              LIMIT 1",
            $escola_id, $data
        ));

        return $fechado > 0;
    }

    /**
     * Fecha o turno do utilizador actual.
     *
     * Calcula o total recebido por este utilizador nesta data (pagamentos
     * válidos, estornos já subtraídos via valor_pago negativo). Grava snapshot
     * em sige_fin_fechos_caixa com user_id_turno preenchido.
     *
     * @param int    $escola_id
     * @param string $data              Data a fechar (default: hoje)
     * @param string $observacoes       Notas opcionais do utilizador
     * @return array{ok: bool, error?: string, fecho_id?: int, total_liquido?: float}
     */
    public static function fecharTurno(int $escola_id, string $data = '', string $observacoes = ''): array {
        if (!sige_tenant_write_guard((int) $escola_id, 'fecharTurno')) { return ['ok' => false, 'error' => 'Contexto de escola invalido.']; }
        global $wpdb;

        if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
            && !current_user_can('sige_director')
            && !current_user_can('sige_financeiro')
            && !current_user_can('sige_secretario')) {
            return ['ok' => false, 'error' => 'Sem permissão para fechar turno.'];
        }

        $data = $data ?: current_time('Y-m-d');
        $user_id = get_current_user_id();

        if ($user_id <= 0) {
            return ['ok' => false, 'error' => 'Utilizador não identificado.'];
        }

        $tP = $wpdb->prefix . 'sige_fin_pagamentos';
        $tF = $wpdb->prefix . 'sige_fin_fechos_caixa';

        // Já existe fecho neste dia?
        // Com user_id_turno: fecho por turno. Sem user_id_turno: fecho diário legado.
        if (self::columnExists($tF, 'user_id_turno')) {
            $ja_fechado = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tF}
                  WHERE escola_id = %d AND data_caixa = %s AND user_id_turno = %d AND status = 'fechado'",
                $escola_id, $data, $user_id
            ));
        } else {
            $ja_fechado = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tF}
                  WHERE escola_id = %d AND data_caixa = %s AND status = 'fechado'",
                $escola_id, $data
            ));
        }
        if ($ja_fechado > 0) {
            return ['ok' => false, 'error' => 'O seu turno já foi fechado neste dia.'];
        }

        // Calcular totais deste user neste dia (só pagamentos positivos + estornos)
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT metodo_pagamento, SUM(valor_pago) AS total
               FROM {$tP}
              WHERE escola_id = %d
                AND DATE(data_pagamento) = %s
                AND recebido_por = %d
              GROUP BY metodo_pagamento",
            $escola_id, $data, $user_id
        ));

        $total_bruto = 0.0;
        $total_estornos = 0.0;
        $resumo_metodos = [];

        foreach ($rows as $r) {
            $valor = (float)$r->total;
            $metodo = (string)$r->metodo_pagamento;

            if ($metodo === 'estorno') {
                $total_estornos += abs($valor);
            } else {
                $total_bruto += $valor;
                $resumo_metodos[$metodo] = $valor;
            }
        }

        $total_liquido = $total_bruto - $total_estornos;

        // Gravar fecho com filtro de colunas para compatibilidade entre schemas.
        $payload = self::filterExistingColumns($tF, [
            'escola_id'      => $escola_id,
            'user_id_turno'  => $user_id,
            'data_caixa'     => $data,
            'total_bruto'    => $total_bruto,
            'total_estornos' => $total_estornos,
            'total_liquido'  => $total_liquido,
            'total_por_metodo' => wp_json_encode($resumo_metodos),
            'resumo_metodos' => implode(', ', array_map(
                fn($k, $v) => (function_exists('sige_fin_metodo_pagamento_label') ? sige_fin_metodo_pagamento_label($k) : $k) . ': ' . number_format($v, 2, ',', '.'),
                array_keys($resumo_metodos),
                array_values($resumo_metodos)
            )),
            'fechado_por'    => $user_id,
            'data_fecho'     => current_time('mysql'),
            'fechado_em'     => current_time('mysql'),
            'status'         => 'fechado',
            'observacoes'    => $observacoes ?: null,
        ]);
        $ok = $wpdb->insert($tF, $payload);
        if (!$ok) {
            return ['ok' => false, 'error' => 'Erro ao fechar turno: ' . $wpdb->last_error];
        }

        if (function_exists('sige_audit_log')) {
            sige_audit_log('fechar_turno', [
                'data'          => $data,
                'user_id'       => $user_id,
                'total_bruto'   => $total_bruto,
                'total_estornos'=> $total_estornos,
                'total_liquido' => $total_liquido,
            ], 'financeiro');
        }

        $sige_fecho_id = (int)$wpdb->insert_id;
        if (function_exists('sige_ledger_append')) {
            sige_ledger_append('fin_fechar_turno', 'fecho', $sige_fecho_id, (float)$total_liquido, ['total_bruto' => $total_bruto, 'total_estornos' => $total_estornos], (int)$escola_id);
        }
        return [
            'ok'            => true,
            'fecho_id'      => $sige_fecho_id,
            'total_liquido' => $total_liquido,
        ];
    }

    /**
     * Reabre um turno fechado. Apenas Director ou Admin.
     */
    public static function reabrirTurno(int $fecho_id, string $motivo, int $escola_id): array {
        if (!sige_tenant_write_guard((int) $escola_id, 'reabrirTurno')) { return ['ok' => false, 'error' => 'Contexto de escola invalido.']; }
        global $wpdb;

        if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director')) {
            return ['ok' => false, 'error' => 'Apenas o Director pode reabrir um turno.'];
        }

        if ($fecho_id <= 0) return ['ok' => false, 'error' => 'ID inválido.'];
        $motivo = trim($motivo);
        if ($motivo === '') return ['ok' => false, 'error' => 'Motivo obrigatório para reabertura.'];

        $tF = $wpdb->prefix . 'sige_fin_fechos_caixa';

        $fecho = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tF} WHERE id=%d AND escola_id=%d AND status='fechado'",
            $fecho_id, $escola_id
        ));

        if (!$fecho) {
            return ['ok' => false, 'error' => 'Fecho não encontrado ou já aberto.'];
        }

        $ok = $wpdb->update($tF, [
            'status'      => 'aberto',
            'observacoes' => ($fecho->observacoes ? $fecho->observacoes . "\n\n" : '')
                           . '[REABERTO por user #' . get_current_user_id() . ' em ' . current_time('mysql') . '] ' . $motivo,
        ], ['id' => $fecho_id]);

        if ($ok === false) {
            return ['ok' => false, 'error' => 'Erro ao reabrir: ' . $wpdb->last_error];
        }

        if (function_exists('sige_audit_log')) {
            sige_audit_log('reabrir_turno', [
                'fecho_id'     => $fecho_id,
                'user_turno'   => isset($fecho->user_id_turno) ? (int)$fecho->user_id_turno : 0,
                'data'         => $fecho->data_caixa,
                'motivo'       => $motivo,
            ], 'financeiro');
        }

        if (function_exists('sige_ledger_append')) {
            sige_ledger_append('fin_reabrir_turno', 'fecho', $fecho_id, null, ['motivo' => $motivo, 'data' => $fecho->data_caixa], (int)$escola_id);
        }
        return ['ok' => true];
    }

    /**
     * Lista fechos de um dia para apresentação na UI de Extratos.
     * Agrupa por user_id_turno.
     */
    public static function listarFechosDoDia(int $escola_id, string $data): array {
        global $wpdb;
        $tF = $wpdb->prefix . 'sige_fin_fechos_caixa';

        if (self::columnExists($tF, 'user_id_turno')) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT f.*, u.display_name AS user_nome
                   FROM {$tF} f
                   LEFT JOIN {$wpdb->users} u ON u.ID = f.user_id_turno
                  WHERE f.escola_id = %d AND f.data_caixa = %s
                  ORDER BY f.data_fecho DESC",
                $escola_id, $data
            ));
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT f.*, u.display_name AS user_nome
                   FROM {$tF} f
                   LEFT JOIN {$wpdb->users} u ON u.ID = f.fechado_por
                  WHERE f.escola_id = %d AND f.data_caixa = %s
                  ORDER BY f.data_fecho DESC",
                $escola_id, $data
            ));
        }

        return $rows ?: [];
    }
}

/**
 * Execucao da reabertura de caixa (Fase 7 incr 2).
 *
 * Extraida do handler inline de financeiro-extratos.php para ser chamada apenas
 * na aprovacao (regra de quatro-olhos). Exige MFA do aprovador. Reabre o fecho
 * fechado da data indicada, registando quem aprovou.
 *
 * @return array ['ok'=>true, 'data_caixa'=>string] ou ['ok'=>false, 'error'=>string]
 *               ou ['ok'=>false, 'mfa_required'=>true, 'error'=>string]
 */
if (!function_exists('sige_fin_reabertura_caixa_executar')) {
    function sige_fin_reabertura_caixa_executar(string $data_caixa, string $motivo, int $escola_id): array {
        global $wpdb;

        if ($escola_id <= 0) return ['ok' => false, 'error' => 'Contexto de escola invalido.'];
        if (function_exists('sige_tenant_write_guard') && !sige_tenant_write_guard($escola_id, 'reabertura_caixa_executar')) {
            return ['ok' => false, 'error' => 'Contexto de escola invalido.'];
        }
        if (function_exists('sige_mfa_require_step_up') && !sige_mfa_require_step_up('caixa_reabrir')) {
            if (function_exists('sige_mfa_replay_capture')) sige_mfa_replay_capture('caixa_reabrir', func_get_args());
            return ['ok' => false, 'mfa_required' => true, 'error' => 'Confirmacao de identidade necessaria para reabrir o caixa.'];
        }
        if (trim($motivo) === '') return ['ok' => false, 'error' => 'O motivo da reabertura e obrigatorio.'];
        if (trim($data_caixa) === '') return ['ok' => false, 'error' => 'Data de caixa em falta.'];

        $tF = $wpdb->prefix . 'sige_fin_fechos_caixa';
        $uid = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;

        $fecho = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tF} WHERE escola_id = %d AND data_caixa = %s AND status = 'fechado' ORDER BY id DESC LIMIT 1",
            $escola_id, $data_caixa
        ));
        if (!$fecho) {
            return ['ok' => false, 'error' => 'Nao existe um caixa fechado nesta data para reabrir.'];
        }

        $obs_anterior = (string) ($fecho->observacoes ?? '');
        $obs_reabertura = trim($obs_anterior . "\n\n" . 'REABERTURA (aprovada): ' . $motivo . ' | aprovador user #' . $uid . ' em ' . current_time('mysql'));

        $wpdb->update($tF,
            ['status' => 'reaberto', 'observacoes' => $obs_reabertura],
            ['id' => (int) $fecho->id, 'escola_id' => $escola_id]
        );

        if (function_exists('sige_fin_log')) {
            sige_fin_log('caixa_reaberto', ['data_caixa' => $data_caixa, 'motivo' => $motivo, 'aprovador' => $uid]);
        }
        if (function_exists('sige_ledger_append')) {
            sige_ledger_append('fin_reabrir_caixa', 'fecho', (int) $fecho->id, null, [
                'data_caixa' => $data_caixa, 'motivo' => $motivo, 'aprovador' => $uid,
            ], $escola_id);
        }

        return ['ok' => true, 'data_caixa' => $data_caixa];
    }
}
