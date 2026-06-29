<?php
/**
 * SIGE SoftGenial - Notification Policy
 * Ficheiro: includes/notification-policy.php
 *
 * Política central de envio de notificações (WhatsApp + e-mail).
 *
 * Resolve 3 problemas relatados pelas escolas (Maio 2026):
 *
 *   1. LANÇAMENTOS de mensalidade: NÃO enviar nada aos encarregados.
 *      Os lançamentos são apenas para controlo interno da escola sobre quem
 *      deve. Envios em massa no acto de lançar mensalidades eram a causa
 *      principal das restrições de Z-API e dos bloqueios de WhatsApp.
 *
 *   2. CONFIRMAÇÃO DE PAGAMENTO: enfileirar com prioridade alta, mas sem
 *      envio no clique; o cron envia depois do intervalo seguro mínimo.
 *
 *   3. COBRANÇAS de pendências em LOTE (selecção múltipla na Central de
 *      Cobranças): aplicar delay progressivo entre destinatários para
 *      simular comportamento humano e evitar restrições.
 *
 * E também:
 *
 *   4. Saudação: usar SEMPRE o primeiro nome do ALUNO (não do encarregado).
 *      Razão: nem todos os alunos têm encarregado registado e os clientes
 *      preferem uniformidade.
 *
 * Princípios:
 *   - NÃO toca em fórmulas financeiras nem académicas.
 *   - NÃO toca no motor WhatsApp (engine, recovery-mode, guardian).
 *   - NÃO altera schema da BD.
 *   - Aplica-se via wrappers chamados pelas views financeiras.
 *
 * @since 12.9.57
 */

if (!defined('ABSPATH')) exit;


// ============================================================================
// 0) OPT-IN EXPLÍCITO PARA LANÇAMENTO DE MENSALIDADES
// ----------------------------------------------------------------------------
// Por padrão, lançamentos/faturas continuam sem envio automático. A view de
// lançamento só pode activar notificação se o operador marcar WhatsApp e/ou
// E-mail no formulário e se o nonce da operação for válido. Esta abertura é
// intencionalmente estreita para evitar reactivar envios em massa por acidente.
// ============================================================================
if (!function_exists('sige_notify_lancamento_manual_optin')) {
    function sige_notify_lancamento_manual_optin(string $evento = 'fatura', ?int $escola_id = null): bool {
        $evento = strtolower(trim($evento));
        if (!in_array($evento, ['lancamento', 'fatura', 'mensalidade', 'nova_mensalidade'], true)) {
            return false;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return false;
        if (empty($_POST['sige_gerar_lote'])) return false;

        $campo_ativo = static function (string $campo): bool {
            if (!array_key_exists($campo, $_POST)) return false;
            $valor = $_POST[$campo];
            if (is_array($valor)) { $valor = end($valor); }
            return in_array(strtolower(trim((string)$valor)), ['1', 'sim', 'yes', 'on', 'true'], true);
        };

        if (!$campo_ativo('notificar_whatsapp') && !$campo_ativo('notificar_email')) return false;

        if (!function_exists('wp_verify_nonce')) return false;
        $nonce = isset($_POST['_wpnonce']) ? (string)$_POST['_wpnonce'] : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'sige_fin_gerar_lote')) return false;

        return true;
    }
}

// ============================================================================
// 1) KILL-SWITCH POR EVENTO
// ----------------------------------------------------------------------------
//   lancamento / fatura / mensalidade  → BLOQUEADO  (causava restrições Z-API)
//   pagamento  / recibo                → PERMITIDO  (agendado prioritário)
//   cobranca   / lembrete              → PERMITIDO  (com throttle em lote)
//   alerta_*                           → PERMITIDO  (mensagens académicas)
//   conta_aluno                        → PERMITIDO  (criação de conta)
//
// Override por escola: add_filter('sige_notify_event_allowed', ...);
// ============================================================================
if (!function_exists('sige_notify_event_allowed')) {
    function sige_notify_event_allowed(string $evento): bool {
        $evento = strtolower(trim($evento));

        $defaults = [
            'lancamento'      => false,
            'fatura'          => false,
            'mensalidade'     => false,
            'nova_mensalidade'=> false,

            'pagamento'       => true,
            'recibo'          => true,
            'recibo_diferido' => true,
            'pagamento_confirmado' => true,

            'cobranca'        => true,
            'cobranca_dia'    => true,
            'cobranca_atraso' => true,
            'lembrete'        => true,

            'conta_aluno'     => true,
            'alerta_falta'    => true,
            'alerta_nota'     => true,
        ];

        $allowed = array_key_exists($evento, $defaults) ? $defaults[$evento] : true;

        // Lançamentos/faturas continuam bloqueados por padrão, mas podem ser
        // liberados apenas quando a secretaria fez opt-in explícito no
        // formulário validado de geração de mensalidades.
        if (!$allowed
            && in_array($evento, ['lancamento', 'fatura', 'mensalidade', 'nova_mensalidade'], true)
            && function_exists('sige_notify_lancamento_manual_optin')
            && sige_notify_lancamento_manual_optin()) {
            $allowed = true;
        }

        $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
        $allowed = (bool) apply_filters('sige_notify_event_allowed', $allowed, $evento, $eid);

        return $allowed;
    }
}

// ============================================================================
// 2) DELAY PROGRESSIVO PÓS-ENFILEIRAMENTO (cobranças em lote)
// ----------------------------------------------------------------------------
// Após uma chamada bem-sucedida a sige_fin_queue_whatsapp() vinda de um loop
// de cobranças, este helper FAZ UPDATE da última linha enfileirada para esse
// aluno+tipo, atrasando o scheduled_at de forma progressiva.
//
// Curva de delay v12.9.68:
//   selecção única → agendada para depois do intervalo seguro mínimo;
//   lote           → cada aluno separado por janela dinâmica conservadora;
//   pai/mãe        → segundo contacto do mesmo aluno respeita intervalo mínimo.
// ============================================================================
if (!function_exists('sige_notify_apply_batch_delay')) {
    function sige_notify_apply_batch_delay(int $aluno_id, string $tipo, int $batch_index, int $batch_total): bool {
        // v12.10.136 - o clique nunca envia: cobrança individual também fica agendada.
        // Lote: espaçamento dinâmico conservador, evitando padrão robótico.
        if ($batch_index < 0) $batch_index = 0;

        global $wpdb;
        $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
        if ($eid <= 0) { return false; }
        $tQ  = $wpdb->prefix . 'sige_whatsapp_queue';

        $has_scheduled = function_exists('sige_wpp_queue_has_col')
            ? sige_wpp_queue_has_col('scheduled_at')
            : true;
        if (!$has_scheduled) return false;

        // Mantido por compatibilidade para fallback. A regra PRO abaixo usa 4-10 min.
        $base_extra = ($batch_total > 1) ? ($batch_index * 300) : 180;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, scheduled_at, criado_em
               FROM {$tQ}
              WHERE escola_id = %d
                AND aluno_id  = %d
                AND tipo      = %s
                AND status    = 'pendente'
                AND criado_em >= DATE_SUB(%s, INTERVAL 10 MINUTE)
              ORDER BY id ASC
              LIMIT 4",
            $eid, $aluno_id, $tipo, current_time('mysql')
        ));

        if (empty($rows)) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT id, scheduled_at, criado_em
                   FROM {$tQ}
                  WHERE escola_id = %d
                    AND aluno_id  = %d
                    AND tipo      = %s
                    AND status    = 'pendente'
                  ORDER BY id DESC
                  LIMIT 1",
                $eid, $aluno_id, $tipo
            ));
            $rows = $row ? [$row] : [];
        }
        if (empty($rows)) return false;

        $base_ts = function_exists('sige_notify_next_window_ts')
            ? sige_notify_next_window_ts($eid)
            : current_time('timestamp');

        $ok_all = true;
        $i = 0;
        foreach ($rows as $row) {
            if (function_exists('sige_wpp_guardrails_pro_batch_schedule_ts')) {
                $new_ts = sige_wpp_guardrails_pro_batch_schedule_ts($eid, $tipo, $batch_index, $batch_total, $i);
            } else {
                $new_ts = $base_ts + $base_extra + ($i * 120);
                if (function_exists('sige_notify_fit_in_operational_window')) {
                    $new_ts = sige_notify_fit_in_operational_window($new_ts, $eid);
                }
            }
            $new_scheduled = wp_date('Y-m-d H:i:s', $new_ts, new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo'));

            $ok = $wpdb->update(
                $tQ,
                ['scheduled_at' => $new_scheduled],
                ['id' => (int)$row->id, 'escola_id' => $eid],
                ['%s'],
                ['%d', '%d']
            );
            if ($ok === false) $ok_all = false;
            $i++;
        }

        if ($ok_all && function_exists('sige_fin_log')) {
            sige_fin_log('notify_batch_delay_guardrails_pro', [
                'aluno_id'    => $aluno_id,
                'tipo'        => $tipo,
                'batch_index' => $batch_index,
                'batch_total' => $batch_total,
                'base_extra'  => $base_extra,
                'items'       => count($rows),
            ]);
        }

        // v12.10.136 - nunca processa a fila no próprio clique. O cron central envia depois.
        return $ok_all;
    }
}

// ============================================================================
// 3) PRIORIDADE ALTA PÓS-ENFILEIRAMENTO (confirmação de pagamento)
// ----------------------------------------------------------------------------
// v12.10.136: após enfileirar recibo/pagamento, define priority=1 e agenda
// para o primeiro slot seguro. Não dispara o engine no mesmo pedido HTTP.
// ============================================================================
if (!function_exists('sige_notify_force_immediate')) {
    function sige_notify_force_immediate(int $aluno_id, string $tipo): bool {
        global $wpdb;
        $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
        if ($eid <= 0) { return false; }
        $tQ  = $wpdb->prefix . 'sige_whatsapp_queue';

        $has_scheduled = function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('scheduled_at');
        $has_priority  = function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('priority');
        if (!$has_scheduled && !$has_priority) return true;

        // Confirmações de pagamento têm prioridade alta, mas respeitam mínimo de 2 minutos.
        // Se a secretaria lançar vários pagamentos em sequência, os recibos são espaçados.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, telefone, scheduled_at, criado_em
               FROM {$tQ}
              WHERE escola_id = %d
                AND aluno_id = %d
                AND tipo = %s
                AND status = 'pendente'
                AND criado_em >= DATE_SUB(%s, INTERVAL 10 MINUTE)
              ORDER BY id ASC
              LIMIT 6",
            $eid, $aluno_id, $tipo, current_time('mysql')
        ));

        if (empty($rows)) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, telefone, scheduled_at, criado_em
                   FROM {$tQ}
                  WHERE escola_id = %d
                    AND aluno_id = %d
                    AND tipo = %s
                    AND status = 'pendente'
                  ORDER BY id DESC
                  LIMIT 2",
                $eid, $aluno_id, $tipo
            ));
            $rows = array_reverse((array)$rows);
        }

        $slot_ts = function_exists('sige_notify_next_receipt_slot_ts')
            ? sige_notify_next_receipt_slot_ts($eid)
            : current_time('timestamp');

        $ok_all = true;
        $i = 0;
        foreach ((array)$rows as $row) {
            if (function_exists('sige_wpp_guardrails_pro_batch_schedule_ts')) {
                $ts = sige_wpp_guardrails_pro_batch_schedule_ts($eid, $tipo, $i, max(1, count((array)$rows)), $i, (string)($row->telefone ?? ''));
            } else {
                $ts = $slot_ts + ($i * 120);
                if (function_exists('sige_notify_fit_in_operational_window')) {
                    $ts = sige_notify_fit_in_operational_window($ts, $eid);
                }
            }
            $data = [];
            if ($has_scheduled) {
                $data['scheduled_at'] = wp_date('Y-m-d H:i:s', $ts, new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo'));
            }
            if ($has_priority) $data['priority'] = 1;
            if (!empty($data)) {
                $ok = $wpdb->update($tQ, $data, ['id' => (int)$row->id, 'escola_id' => $eid]);
                if ($ok === false) $ok_all = false;
            }
            $i++;
        }

        // v12.10.136 - sem envio imediato; o cron central processa no horário seguro.
        if (function_exists('sige_fin_log')) {
            sige_fin_log('notify_receipt_priority_guardrails_pro', [
                'aluno_id' => $aluno_id,
                'tipo'     => $tipo,
                'items'    => count((array)$rows),
            ]);
        }
        return $ok_all;
    }
}

// ============================================================================
// 4) NORMALIZAÇÃO DA SAUDAÇÃO - usar nome do ENCARREGADO
// ----------------------------------------------------------------------------
// [v12.9.58] Faz pós-processamento da mensagem JÁ renderizada e substitui
// saudações genéricas ("Encarregado(a)") pelo nome do encarregado registado
// no formulário do aluno. Se não houver encarregado registado, usa o
// fallback fixo "Encarregado de Educação" (pedido das escolas, Maio 2026).
//
// Ordem de resolução:
//   1. nome_pai
//   2. nome_mae
//   3. contacto_encarregado
//   4. fallback "Encarregado de Educação"
//
// Apanha apenas templates LEGACY guardados em BD pela escola (msg_recibo_pago,
// msg_nova_fatura, msg_cobranca). Os templates do v2_render conversacional já
// resolvem isto em tempo de composição.
// ============================================================================
if (!function_exists('sige_notify_resolve_encarregado_nome')) {
    function sige_notify_resolve_encarregado_nome(int $aluno_id): string {
        if ($aluno_id <= 0) return 'Encarregado de Educação';

        static $cache = [];
        if (isset($cache[$aluno_id])) return $cache[$aluno_id];

        global $wpdb;
        $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT nome_pai, nome_mae, contacto_encarregado
               FROM {$wpdb->prefix}sige_alunos
              WHERE id = %d AND escola_id = %d LIMIT 1",
            $aluno_id, $eid
        ));

        $nome = '';
        if ($row) {
            $pai = !empty($row->nome_pai) ? trim((string) $row->nome_pai) : '';
            $mae = !empty($row->nome_mae) ? trim((string) $row->nome_mae) : '';
            // Sem contexto do telefone, não assumir o pai quando há pai e mãe.
            // Isto evita tratar a mãe como se fosse o pai em templates legados.
            if ($pai !== '' && $mae === '') $nome = $pai;
            elseif ($mae !== '' && $pai === '') $nome = $mae;
            elseif (!empty($row->contacto_encarregado) && !preg_match('/^[+\d\s().-]+$/', (string)$row->contacto_encarregado)) $nome = trim((string) $row->contacto_encarregado);
        }

        if ($nome === '') $nome = 'Encarregado de Educação';

        $cache[$aluno_id] = $nome;
        return $nome;
    }
}

if (!function_exists('sige_notify_normalize_saudacao_aluno')) {
    /**
     * Compat name kept from v12.9.57. A função foi semanticamente actualizada
     * em v12.9.58 para usar nome do ENCARREGADO (não do aluno). Mantém o nome
     * antigo para evitar quebrar callers existentes.
     */
    function sige_notify_normalize_saudacao_aluno(string $mensagem, int $aluno_id): string {
        return sige_notify_normalize_saudacao_encarregado($mensagem, $aluno_id);
    }
}

if (!function_exists('sige_notify_normalize_saudacao_encarregado')) {
    function sige_notify_normalize_saudacao_encarregado(string $mensagem, int $aluno_id): string {
        if ($mensagem === '' || $aluno_id <= 0) return $mensagem;

        $nome_completo = sige_notify_resolve_encarregado_nome($aluno_id);

        // Para fallback fixo, usa string completa "Encarregado de Educação".
        // Para nomes reais, usa primeiro nome (mais conversacional).
        if ($nome_completo === 'Encarregado de Educação') {
            $saudacao = 'Encarregado de Educação';
        } else {
            $partes   = preg_split('/\s+/', $nome_completo) ?: [];
            $saudacao = $partes[0] ?? $nome_completo;
        }

        $patterns = [
            '/Exmo\(a\)\s+Encarregado(?:\(a\))?/iu'      => 'Exmo(a) ' . $saudacao,
            '/Prezado\(a\)\s+Encarregado(?:\(a\))?/iu'   => 'Prezado(a) ' . $saudacao,
            '/Caro\(a\)\s+Encarregado(?:\(a\))?/iu'      => 'Caro(a) ' . $saudacao,
            '/Caro\s+Encarregado(?:\(a\))?/iu'           => 'Caro(a) ' . $saudacao,
            '/Cara\s+Encarregada(?:\(a\))?/iu'           => 'Cara ' . $saudacao,
            '/Olá,?\s+Encarregado(?:\(a\))?/iu'          => 'Olá ' . $saudacao,
            '/Olá\s+Encarregada/iu'                      => 'Olá ' . $saudacao,
            '/\bEncarregado\(a\)\b/u'                    => $saudacao,
            '/\bEncarregada\(o\)\b/u'                    => $saudacao,
        ];

        $resultado = $mensagem;
        foreach ($patterns as $regex => $repl) {
            $resultado = preg_replace($regex, $repl, $resultado) ?? $resultado;
        }

        return $resultado;
    }
}

// ============================================================================
// 5) HELPER PARA UI - etiqueta informativa
// ============================================================================
if (!function_exists('sige_notify_lancamento_label')) {
    function sige_notify_lancamento_label(): string {
        return '🔕 Notificações para lançamentos foram desactivadas (política contra envios em massa). '
             . 'As mensagens só são enviadas no momento do pagamento ou via Central de Cobranças.';
    }
}

// ============================================================================
// [v12.10.136] LIMITE DIÁRIO CONSERVADOR
// ----------------------------------------------------------------------------
// Tecto operacional por escola/instância: 180 mensagens/dia.
// A segurança vem da combinação de: janela 07:30-19:30, batch=1 por tick,
// intervalo mínimo global de 2 minutos e cobranças em lote espaçadas dinamicamente.
//
// Override técnico, se necessário:
//   add_filter('sige_wpp_operational_daily_limit', fn() => 180);
// ============================================================================
add_filter('sige_wpp_normal_daily_limit', function ($limit) {
    return 180;
}, 99);

// Alinha a camada invisível com a regra operacional v12.10.136.
add_filter('sige_wpp_invisible_guardrails_policy', function ($p, $eid) {
    // Janela única e limite/hora compatível com a quota diária conservadora.
    // Mantém intervalo mínimo por contacto em pelo menos 2 minutos.
    if (is_array($p)) {
        $p['safe_windows'] = [ ['start' => 730, 'end' => 1930] ];
        $p['min_seconds_same_phone'] = 120;
        $p['max_per_hour'] = 25;
    }
    return $p;
}, 10, 2);

// ============================================================================
// [v12.10.136] BATCH HUMANO - 1 mensagem por ciclo, sem envio no clique
// ----------------------------------------------------------------------------
// O engine canónico (cron-tasks.php) tem usleep(0.5-1.6s) entre mensagens
// dentro do mesmo batch. Isto faz aparecerem 4 mensagens no mesmo minuto,
// o que é padrão de bot e arrisca ban WhatsApp.
//
// SOLUÇÃO: forçar batch_limit=1 para cobranças (cada tick processa 1 só →
// natural entre cobranças). Recibos ficam com priority=1, mas também
// respeitam o intervalo mínimo e saem apenas pelo cron.
//
// Esta abordagem evita tocar no canónico cron-tasks.php e cria espaçamento
// humano real entre mensagens em massa.
//
// Mesmo com muitos recibos em fila, mantemos batch=1; a prioridade ordena,
// mas o intervalo mínimo protege o número WhatsApp.
// ============================================================================
add_filter('sige_wpp_queue_batch_limit', function ($limit) {
    return sige_notify_dynamic_batch_limit((int)$limit);
}, 30); // prioridade 30 > 20 do whatsapp-human-advanced

add_filter('sige_wpp_manual_default_limit', function ($limit) {
    return sige_notify_dynamic_batch_limit((int)$limit);
}, 30);

if (!function_exists('sige_notify_dynamic_batch_limit')) {
    function sige_notify_dynamic_batch_limit(int $requested): int {
        global $wpdb;
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$tQ}'") !== $tQ) return 1;

        // Conta quantos recibos com priority=1 estão prontos, apenas para diagnóstico interno
        $now_mysql = current_time('mysql');
        $has_scheduled = (function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('scheduled_at'));
        $has_priority  = (function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('priority'));

        if (!$has_priority) {
            // Sem coluna priority, força batch=1 (espaçamento humano universal)
            return 1;
        }

        $sched_clause = $has_scheduled ? "AND (scheduled_at IS NULL OR scheduled_at <= %s)" : "";
        $params = [$eid];
        if ($has_scheduled) $params[] = $now_mysql;

        $recibos_imediatos = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tQ}
              WHERE escola_id = %d
                AND status IN ('pendente','forcar_envio')
                AND priority = 1
                AND tipo = 'recibo'
                {$sched_clause}",
            $params
        ));

        if ($recibos_imediatos >= 1) {
            // Há recibos prioritários, mas o processamento continua em batch=1.
            return 1;
        }

        // Sem recibos urgentes → cobranças/lembretes em batch=1.
        // Resultado: 1 mensagem por tick do cron; o Health Mode garante o intervalo mínimo.
        return 1;
    }
}

// ============================================================================
// [v12.9.62] SPREAD-LOAD RESCHEDULE
// ----------------------------------------------------------------------------
// Quando uma mensagem precisa ser reagendada (excedeu limite/hora, esteve fora
// de janela, etc.), em vez de empilhar tudo na próxima abertura (07:30 do dia
// seguinte com jitter 3-40min), DISTRIBUI ao longo das janelas humanas
// disponíveis do dia corrente. Só passa para o dia seguinte se o dia actual
// já estiver saturado.
//
// Janelas humanas alvo (Africa/Maputo):
//   - manhã:    07:30 → 12:00
//   - tarde:    12:00 → 15:30
//   - fim:      15:30 → 19:30
//
// Algoritmo:
//   1. Calcula remaining_today = limite_diário - enviadas_hoje
//   2. Se remaining_today > 0 e estamos antes das 19:30 → escolhe slot livre hoje
//   3. Senão → manhã de amanhã com jitter
//
// v12.10.136: recibos/pagamentos também são reagendados para o slot seguro;
// priority=1 apenas ordena a fila, não permite envio imediato.
// ============================================================================
if (!function_exists('sige_notify_spread_reschedule')) {
    function sige_notify_spread_reschedule(int $escola_id = 1, $current_q = null): string {
        $tz_name = defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo';
        $tz = new DateTimeZone($tz_name);
        $now = new DateTimeImmutable('now', $tz);

        // Mensagem de pagamento/recibo também respeita slot seguro.
        // Priority=1 ordena a fila, mas não fura o intervalo mínimo.
        if (is_object($current_q) && isset($current_q->tipo)) {
            $tipo = strtolower((string)$current_q->tipo);
            $priority = (int)($current_q->priority ?? 5);
            $is_pagamento = (strpos($tipo, 'recibo') !== false || $tipo === 'pagamento');
            $is_forced = ($priority === 1);

            if ($is_pagamento || $is_forced) {
                if (function_exists('sige_wpp_guardrails_pro_batch_schedule_ts')) {
                    $ts = sige_wpp_guardrails_pro_batch_schedule_ts($escola_id, $tipo, 0, 1, 0);
                    return wp_date('Y-m-d H:i:s', $ts, $tz);
                }
                $seguro = $now->getTimestamp() + 120;
                return wp_date('Y-m-d H:i:s', $seguro, $tz);
            }
        }

        $hm = (int) $now->format('Hi');
        $today_end = $now->setTime(19, 30, 0);

        // Janelas do dia corrente (em formato HHMM)
        $windows_today = [
            ['start' => 730,  'end' => 1200, 'label' => 'manha'],
            ['start' => 1200, 'end' => 1530, 'label' => 'tarde'],
            ['start' => 1530, 'end' => 1930, 'label' => 'fim'],
        ];

        // Calcula quanto cabe ainda hoje (limite diário - já enviadas)
        $remaining_today = function_exists('sige_wpp_human_daily_limit')
            ? max(0, sige_wpp_human_daily_limit($escola_id) - (function_exists('sige_wpp_human_sent_today') ? sige_wpp_human_sent_today($escola_id) : 0))
            : 100;

        // Conta quantas estão JÁ agendadas para hoje (para saber se há espaço livre)
        $already_scheduled_today = sige_notify_count_scheduled_today($escola_id);
        $space_left_today = max(0, $remaining_today - $already_scheduled_today);

        // Se há espaço hoje E ainda não passou das 18:30 → distribui hoje
        if ($space_left_today > 0 && $now < $today_end) {
            // Escolhe uma janela ainda activa ou futura no dia corrente
            $candidates = [];
            foreach ($windows_today as $w) {
                if ($hm < $w['end']) {
                    // Janela ainda aberta ou futura
                    $start_hm = max($hm + 5, $w['start']); // não menos de 5min no futuro
                    $end_hm = $w['end'];
                    if ($start_hm < $end_hm) {
                        $candidates[] = ['start' => $start_hm, 'end' => $end_hm];
                    }
                }
            }

            if (!empty($candidates)) {
                // Escolhe uma janela aleatoriamente para distribuir naturalmente
                $chosen = $candidates[array_rand($candidates)];
                // Hora aleatória dentro da janela
                $hours_range_start = (int) floor($chosen['start'] / 100);
                $mins_range_start  = $chosen['start'] % 100;
                $hours_range_end   = (int) floor($chosen['end'] / 100);
                $mins_range_end    = $chosen['end'] % 100;

                $start_minutes = $hours_range_start * 60 + $mins_range_start;
                $end_minutes   = $hours_range_end * 60 + $mins_range_end;
                $picked_minute = rand($start_minutes, $end_minutes);

                $picked = $now->setTime((int)floor($picked_minute / 60), $picked_minute % 60, rand(0, 59));
                return $picked->format('Y-m-d H:i:s');
            }
        }

        // Senão: amanhã de manhã com jitter
        $tomorrow_morning = $now->modify('+1 day')->setTime(7, 30, 0);
        $jitter = rand(0, 1800); // 0-30 min de jitter para espalhar
        return wp_date('Y-m-d H:i:s', $tomorrow_morning->getTimestamp() + $jitter, $tz);
    }
}

if (!function_exists('sige_notify_count_scheduled_today')) {
    function sige_notify_count_scheduled_today(int $escola_id): int {
        global $wpdb;
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tQ}'") !== $tQ) return 0;

        $tz_name = defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo';
        $start_today = wp_date('Y-m-d 00:00:00', null, new DateTimeZone($tz_name));
        $end_today   = wp_date('Y-m-d 23:59:59', null, new DateTimeZone($tz_name));

        $has_scheduled = (function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('scheduled_at'));
        if (!$has_scheduled) return 0;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tQ}
              WHERE escola_id=%d
                AND status IN ('pendente','forcar_envio')
                AND scheduled_at BETWEEN %s AND %s",
            $escola_id, $start_today, $end_today
        ));
    }
}

// ============================================================================
// HOOK no reschedule existente
// ----------------------------------------------------------------------------
// O ficheiro canónico whatsapp-human-advanced.php tem
// sige_wpp_guardrails_next_safe_schedule() que é chamado pelo cron quando uma
// mensagem foi adiada. Define-se com `if (!function_exists())`, portanto se
// nós a definirmos PRIMEIRO (e o nosso ficheiro carrega antes - ver ordem em
// sige-softgenial.php) a nossa versão ganha. A canónica não substitui.
// ============================================================================
if (!function_exists('sige_wpp_guardrails_next_safe_schedule')) {
    function sige_wpp_guardrails_next_safe_schedule(int $escola_id = 1): string {
        // [v12.9.62] Substitui a versão canónica que empilhava tudo na próxima
        // abertura. Agora distribui pelas janelas humanas do dia corrente,
        // só passa para amanhã se hoje estiver saturado.
        return sige_notify_spread_reschedule($escola_id, null);
    }
}

if (!function_exists('sige_notify_better_reschedule')) {
    function sige_notify_better_reschedule(int $escola_id, $current_q = null): string {
        return sige_notify_spread_reschedule($escola_id, $current_q);
    }
}

// ============================================================================
// [v12.9.62] PULL-FORWARD: trazer mensagens reagendadas para o futuro de volta
// para hoje, respeitando spread-load. Usado pelo botão "Activar distribuição
// inteligente" na Central de Mensagens.
// ----------------------------------------------------------------------------
// Apanha todas as pendentes com scheduled_at no futuro, recalcula um novo
// scheduled_at usando spread-load, e actualiza em lote. Idempotente.
// ============================================================================
if (!function_exists('sige_notify_pull_forward_pending')) {
    function sige_notify_pull_forward_pending(int $escola_id): array {
        if (!sige_tenant_write_guard((int) $escola_id, 'sige_notify_pull_forward_pending')) { return ['ok' => false, 'rescued' => 0, 'reason' => 'Contexto de escola invalido.']; }
        global $wpdb;
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tQ}'") !== $tQ) {
            return ['scanned' => 0, 'pulled_today' => 0, 'kept_future' => 0, 'reason' => 'tabela inexistente'];
        }
        if (!function_exists('sige_wpp_queue_has_col') || !sige_wpp_queue_has_col('scheduled_at')) {
            return ['scanned' => 0, 'pulled_today' => 0, 'kept_future' => 0, 'reason' => 'sem coluna scheduled_at'];
        }

        $tz_name = defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo';
        $now_mysql = current_time('mysql');

        // Selecciona pendentes com scheduled_at depois de agora
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, tipo, priority, scheduled_at FROM {$tQ}
              WHERE escola_id = %d
                AND status IN ('pendente','forcar_envio')
                AND scheduled_at > %s
              ORDER BY scheduled_at ASC",
            $escola_id, $now_mysql
        ));

        $stats = ['scanned' => count($rows), 'pulled_today' => 0, 'kept_future' => 0];
        $today_end = wp_date('Y-m-d 19:30:00', null, new DateTimeZone($tz_name));
        $today_end_ts = strtotime($today_end);

        foreach ($rows as $r) {
            $new_at = sige_notify_spread_reschedule($escola_id, $r);
            $new_ts = strtotime($new_at);
            if ($new_ts <= $today_end_ts) {
                $stats['pulled_today']++;
            } else {
                $stats['kept_future']++;
            }
            $wpdb->update($tQ, ['scheduled_at' => $new_at], ['id' => (int)$r->id]);
        }

        if (function_exists('sige_fin_log')) {
            sige_fin_log('notify_pull_forward', $stats);
        }
        return $stats;
    }
}


// ============================================================================
// [v12.10.136] REGRAS OPERACIONAIS WHATSAPP - 07:30-19:30, 180/dia,
// mínimo 2min global, cobranças em lote com espaçamento dinâmico conservador.
// ============================================================================
if (!defined('SIGE_WPP_OPERATIONAL_DAILY_LIMIT')) {
    define('SIGE_WPP_OPERATIONAL_DAILY_LIMIT', 180);
}

add_filter('sige_wpp_normal_daily_limit', function ($limit) {
    return SIGE_WPP_OPERATIONAL_DAILY_LIMIT;
}, 999);

add_filter('sige_wpp_normal_daily_link_limit', function ($limit) {
    // Links só sob pedido/manual; mantém tecto próprio para não transformar link em massa.
    return min(80, SIGE_WPP_OPERATIONAL_DAILY_LIMIT);
}, 999);

if (!function_exists('sige_notify_tz')) {
    function sige_notify_tz(): DateTimeZone {
        return new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo');
    }
}

if (!function_exists('sige_notify_now_ts')) {
    function sige_notify_now_ts(): int {
        return (new DateTimeImmutable('now', sige_notify_tz()))->getTimestamp();
    }
}

if (!function_exists('sige_notify_daily_limit')) {
    function sige_notify_daily_limit(int $escola_id = 1): int {
        return (int) apply_filters('sige_wpp_operational_daily_limit', SIGE_WPP_OPERATIONAL_DAILY_LIMIT, $escola_id);
    }
}

if (!function_exists('sige_notify_is_in_operational_window')) {
    function sige_notify_is_in_operational_window(?int $ts = null): bool {
        $ts = $ts ?: sige_notify_now_ts();
        $hm = (int) wp_date('Hi', $ts, sige_notify_tz());
        return ($hm >= 730 && $hm <= 1930);
    }
}

if (!function_exists('sige_notify_next_window_ts')) {
    function sige_notify_next_window_ts(int $escola_id = 1, ?int $from_ts = null): int {
        $tz = sige_notify_tz();
        $from = (new DateTimeImmutable('@' . (int)($from_ts ?: sige_notify_now_ts())))->setTimezone($tz);
        $start = $from->setTime(7, 30, 0);
        $end   = $from->setTime(19, 30, 0);
        if ($from < $start) return $start->getTimestamp();
        if ($from <= $end) return $from->getTimestamp();
        return $start->modify('+1 day')->getTimestamp();
    }
}

if (!function_exists('sige_notify_fit_in_operational_window')) {
    function sige_notify_fit_in_operational_window(int $ts, int $escola_id = 1): int {
        $tz = sige_notify_tz();
        $dt = (new DateTimeImmutable('@' . $ts))->setTimezone($tz);
        $end = $dt->setTime(19, 30, 0);
        if ($dt <= $end && sige_notify_is_in_operational_window($ts)) return $ts;
        return $dt->setTime(7, 30, 0)->modify('+1 day')->getTimestamp();
    }
}

if (!function_exists('sige_notify_count_sent_today')) {
    function sige_notify_count_sent_today(int $escola_id = 1): int {
        global $wpdb;
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tQ}'") !== $tQ) return 0;
        $start = wp_date('Y-m-d 00:00:00', sige_notify_now_ts(), sige_notify_tz());
        $end   = wp_date('Y-m-d 23:59:59', sige_notify_now_ts(), sige_notify_tz());
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tQ}
              WHERE escola_id=%d AND status='enviado' AND enviado_em BETWEEN %s AND %s",
            $escola_id, $start, $end
        ));
    }
}

if (!function_exists('sige_notify_daily_profile')) {
    function sige_notify_daily_profile(int $escola_id = 1): array {
        $limit = sige_notify_daily_limit($escola_id);
        $sent  = sige_notify_count_sent_today($escola_id);
        $remaining = max(0, $limit - $sent);
        $pct = $limit > 0 ? min(100, round(($sent / $limit) * 100, 1)) : 100;
        $now = sige_notify_now_ts();
        $in_window = sige_notify_is_in_operational_window($now);
        $resume_ts = null;
        $reason = 'ok';
        if ($remaining <= 0) {
            $resume_ts = (new DateTimeImmutable('now', sige_notify_tz()))->setTime(7,30,0)->modify('+1 day')->getTimestamp();
            $reason = 'daily_limit_reached';
        } elseif (!$in_window) {
            $resume_ts = sige_notify_next_window_ts($escola_id, $now);
            $reason = 'outside_window';
        }
        return [
            'limit' => $limit,
            'sent' => $sent,
            'remaining' => $remaining,
            'percent' => $pct,
            'in_window' => $in_window,
            'resume_ts' => $resume_ts,
            'resume_at' => $resume_ts ? wp_date('d/m/Y H:i', $resume_ts, sige_notify_tz()) : '',
            'reason' => $reason,
        ];
    }
}

if (!function_exists('sige_notify_is_receipt_type')) {
    function sige_notify_is_receipt_type(string $tipo): bool {
        $t = sanitize_key($tipo);
        return (strpos($t, 'recibo') !== false || strpos($t, 'pagamento') !== false || strpos($t, 'receipt') !== false);
    }
}

if (!function_exists('sige_notify_next_receipt_slot_ts')) {
    function sige_notify_next_receipt_slot_ts(int $escola_id = 1): int {
        global $wpdb;
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        $base = sige_notify_next_window_ts($escola_id);
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tQ}'") !== $tQ) return $base;

        $hasScheduled = function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('scheduled_at');
        $last_ts = 0;
        if ($hasScheduled) {
            $last_sched = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(scheduled_at) FROM {$tQ}
                  WHERE escola_id=%d
                    AND status IN ('pendente','forcar_envio')
                    AND (tipo LIKE %s OR tipo LIKE %s)
                    AND scheduled_at >= %s",
                $escola_id, '%recibo%', '%pagamento%', wp_date('Y-m-d 00:00:00', $base, sige_notify_tz())
            ));
            if ($last_sched) $last_ts = max($last_ts, (int)strtotime((string)$last_sched));
        }
        $last_sent = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(enviado_em) FROM {$tQ}
              WHERE escola_id=%d
                AND status='enviado'
                AND (tipo LIKE %s OR tipo LIKE %s)
                AND enviado_em >= %s",
            $escola_id, '%recibo%', '%pagamento%', wp_date('Y-m-d 00:00:00', $base, sige_notify_tz())
        ));
        if ($last_sent) $last_ts = max($last_ts, (int)strtotime((string)$last_sent));

        $next = max($base, $last_ts ? ($last_ts + 120) : ($base + 120));
        return sige_notify_fit_in_operational_window($next, $escola_id);
    }
}

if (!function_exists('sige_notify_rescue_stuck_receipts')) {
    function sige_notify_rescue_stuck_receipts(array $args = []): array {
        global $wpdb;
        $args = array_merge(['limit' => 100, 'older_than_minutes' => 10], $args);
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tQ}'") !== $tQ) {
            return ['ok' => false, 'rescued' => 0, 'reason' => 'Tabela WhatsApp inexistente'];
        }
        if (!function_exists('sige_wpp_queue_has_col') || !sige_wpp_queue_has_col('scheduled_at')) {
            return ['ok' => false, 'rescued' => 0, 'reason' => 'Coluna scheduled_at ausente'];
        }

        $cut = wp_date('Y-m-d H:i:s', sige_notify_now_ts() - ((int)$args['older_than_minutes'] * MINUTE_IN_SECONDS), sige_notify_tz());
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, escola_id, tipo
               FROM {$tQ}
              WHERE status='pendente'
                AND (tipo LIKE %s OR tipo LIKE %s)
                AND (scheduled_at IS NULL OR scheduled_at <= %s)
                AND criado_em >= DATE_SUB(%s, INTERVAL 7 DAY)
              ORDER BY escola_id ASC, id ASC
              LIMIT %d",
            '%recibo%', '%pagamento%', $cut, current_time('mysql'), max(1, min(300, (int)$args['limit']))
        ));
        if (empty($rows)) return ['ok' => true, 'rescued' => 0, 'reason' => 'Sem recibos presos'];

        $hasPriority = function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('priority');
        $hasErro = function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('erro');
        $hasUlt  = function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('ultimo_erro');
        $last_by_school = [];
        $rescued = 0;
        foreach ($rows as $r) {
            $eid = max(1, (int)$r->escola_id);
            if (!isset($last_by_school[$eid])) {
                $last_by_school[$eid] = sige_notify_next_receipt_slot_ts($eid);
            } else {
                $last_by_school[$eid] = sige_notify_fit_in_operational_window($last_by_school[$eid] + 120, $eid);
            }
            $data = ['scheduled_at' => wp_date('Y-m-d H:i:s', $last_by_school[$eid], sige_notify_tz())];
            if ($hasPriority) $data['priority'] = 1;
            if ($hasErro) $data['erro'] = null;
            if ($hasUlt) $data['ultimo_erro'] = 'Recibo pendente reprogramado automaticamente pela política v12.10.136.';
            $ok = $wpdb->update($tQ, $data, ['id' => (int)$r->id]);
            if ($ok !== false) $rescued++;
        }
        if ($rescued && function_exists('sige_fin_log')) {
            sige_fin_log('wpp_rescue_stuck_receipts_v1210136', ['rescued' => $rescued]);
        }
        return ['ok' => true, 'rescued' => $rescued, 'reason' => 'Recibos reprogramados com intervalo mínimo de 2 minutos'];
    }
}


// ============================================================================
// [v12.9.75] WHATSAPP HEALTH MODE PRO - reputação, recuperação e travas finais
// ----------------------------------------------------------------------------
// Camada defensiva adicional sobre a fila WhatsApp. Não altera finanças nem
// regras académicas. Usa a tabela já existente sige_whatsapp_instance_state.
// ============================================================================
if (!defined('SIGE_WPP_HEALTH_MODE_PRO_VERSION')) {
    define('SIGE_WPP_HEALTH_MODE_PRO_VERSION', '12.9.76');
}

if (!function_exists('sige_wpp_health_normalize_mode')) {
    function sige_wpp_health_normalize_mode($mode): string {
        $m = sanitize_key((string)$mode);
        $aliases = [
            'healthy' => 'normal', 'saudavel' => 'normal', 'saudável' => 'normal', 'auto' => 'normal',
            'aquecimento' => 'warmup', 'warming' => 'warmup',
            'recuperacao' => 'recovery', 'recuperação' => 'recovery',
            'revisao' => 'review', 'revisão' => 'review', 'em_revisao' => 'review', 'em-revisao' => 'review',
            'pausado' => 'paused', 'pause' => 'paused',
        ];
        if (isset($aliases[$m])) $m = $aliases[$m];
        return in_array($m, ['normal','warmup','recovery','review','paused'], true) ? $m : 'normal';
    }
}

if (!function_exists('sige_wpp_health_state_label')) {
    function sige_wpp_health_state_label(string $mode): string {
        $mode = sige_wpp_health_normalize_mode($mode);
        $labels = [
            'normal'   => 'Saudável / Automático',
            'warmup'   => 'Aquecimento',
            'recovery' => 'Recuperação',
            'review'   => 'Em revisão',
            'paused'   => 'Pausado',
        ];
        return $labels[$mode] ?? $mode;
    }
}


// ============================================================================
// [v12.11.6] WhatsApp Health Recovery Hotfix PRO
// Marca temporal de intervenção manual para evitar que falhas antigas
// continuem a forçar pausa/revisão depois de o administrador colocar o número
// em Aquecimento/Recuperação/Saudável.
// ============================================================================
if (!function_exists('sige_wpp_health_manual_since_ts')) {
    function sige_wpp_health_manual_since_ts(int $escola_id = 1): int {
        $ts = (int) get_option('sige_wpp_health_manual_since_e' . max(1, $escola_id), 0);
        return max(0, $ts);
    }
}

if (!function_exists('sige_wpp_health_mark_manual_since')) {
    function sige_wpp_health_mark_manual_since(int $escola_id = 1, ?int $ts = null): int {
        $ts = $ts ?: sige_notify_now_ts();
        update_option('sige_wpp_health_manual_since_e' . max(1, $escola_id), (int)$ts, false);
        return (int)$ts;
    }
}

if (!function_exists('sige_wpp_health_effective_cut_mysql')) {
    function sige_wpp_health_effective_cut_mysql(int $escola_id, int $hours): string {
        $window_ts = sige_notify_now_ts() - max(1, $hours) * HOUR_IN_SECONDS;
        $manual_ts = sige_wpp_health_manual_since_ts($escola_id);
        $cut_ts = max($window_ts, $manual_ts);
        return wp_date('Y-m-d H:i:s', $cut_ts, sige_notify_tz());
    }
}

if (!function_exists('sige_wpp_health_clear_pause_locks')) {
    function sige_wpp_health_clear_pause_locks(int $escola_id = 1): void {
        $escola_id = max(1, $escola_id);
        delete_transient('sige_wpp_guardian_pause_e' . $escola_id);
        delete_transient('sige_wpp_health_pause_e' . $escola_id);
        delete_transient('sige_wpp_pause_e' . $escola_id);
    }
}

if (!function_exists('sige_wpp_health_error_is_critical')) {
    function sige_wpp_health_error_is_critical($error): bool {
        $e = strtolower(remove_accents((string)$error));
        if ($e === '') return false;
        $needles = [
            'revisao', 'review', 'conta em revisao', 'account review',
            'restri', 'restricted', 'restriction', 'temporarily restricted',
            'bloque', 'blocked', 'ban', 'banned', 'suspended', 'suspenso',
            'not connected', 'disconnected', 'desconect', 'session closed',
            'account unavailable', 'device disconnected'
        ];
        foreach ($needles as $n) {
            if (strpos($e, $n) !== false) return true;
        }
        return false;
    }
}

if (!function_exists('sige_wpp_health_recent_critical_errors')) {
    function sige_wpp_health_recent_critical_errors(int $escola_id = 1, int $hours = 72): int {
        global $wpdb;
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tQ}'") !== $tQ) return 0;
        $hasUlt = function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('ultimo_erro');
        $hasGuardian = function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('guardian_meta');
        $cut = function_exists('sige_wpp_health_effective_cut_mysql')
            ? sige_wpp_health_effective_cut_mysql($escola_id, $hours)
            : wp_date('Y-m-d H:i:s', sige_notify_now_ts() - max(1, $hours) * HOUR_IN_SECONDS, sige_notify_tz());
        $cols = ['erro'];
        if ($hasUlt) $cols[] = 'ultimo_erro';
        if ($hasGuardian) $cols[] = 'guardian_meta';
        $patterns = ['%revis%', '%review%', '%restri%', '%restrict%', '%bloque%', '%blocked%', '%ban%', '%suspend%', '%disconnect%', '%desconect%', '%unavailable%'];
        $parts = [];
        $params = [$escola_id, $cut];
        foreach ($cols as $c) {
            foreach ($patterns as $pat) {
                $parts[] = "{$c} LIKE %s";
                $params[] = $pat;
            }
        }
        $sql = "SELECT COUNT(*) FROM {$tQ} WHERE escola_id=%d AND criado_em >= %s AND (" . implode(' OR ', $parts) . ")";
        return (int)$wpdb->get_var($wpdb->prepare($sql, $params));
    }
}

if (!function_exists('sige_wpp_health_recent_failures')) {
    function sige_wpp_health_recent_failures(int $escola_id = 1, int $hours = 6): int {
        global $wpdb;
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tQ}'") !== $tQ) return 0;
        $cut = function_exists('sige_wpp_health_effective_cut_mysql')
            ? sige_wpp_health_effective_cut_mysql($escola_id, $hours)
            : wp_date('Y-m-d H:i:s', sige_notify_now_ts() - max(1, $hours) * HOUR_IN_SECONDS, sige_notify_tz());
        $hasUltTent = function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('ultima_tentativa_em');
        $timeCol = $hasUltTent ? 'ultima_tentativa_em' : 'criado_em';
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tQ}
              WHERE escola_id=%d
                AND status IN ('falhou','erro')
                AND {$timeCol} >= %s",
            $escola_id, $cut
        ));
    }
}

if (!function_exists('sige_wpp_health_day_index')) {
    function sige_wpp_health_day_index($state): int {
        $start = '';
        if ($state) {
            $start = (string)($state->recovery_started_at ?? '');
            if ($start === '') $start = (string)($state->updated_at ?? '');
        }
        $ts = $start ? strtotime($start) : false;
        if (!$ts) return 1;
        return max(1, (int)floor((sige_notify_now_ts() - $ts) / DAY_IN_SECONDS) + 1);
    }
}

if (!function_exists('sige_wpp_health_limit_for_mode')) {
    function sige_wpp_health_limit_for_mode(string $mode, int $day, int $base_limit = 180): int {
        $mode = sige_wpp_health_normalize_mode($mode);
        $base_limit = max(0, min(180, (int)$base_limit));
        if ($mode === 'paused' || $mode === 'review') return 0;
        $warmup   = [1=>20, 2=>30, 3=>50, 4=>80, 5=>120, 6=>150, 7=>180];
        $recovery = [1=>10, 2=>20, 3=>30, 4=>50, 5=>80, 6=>120, 7=>150, 8=>180];
        if ($mode === 'warmup') {
            $v = $warmup[min(max(1,$day), 7)] ?? 180;
            return min($base_limit, $v);
        }
        if ($mode === 'recovery') {
            $v = $recovery[min(max(1,$day), 8)] ?? 180;
            return min($base_limit, $v);
        }
        return $base_limit;
    }
}

if (!function_exists('sige_wpp_health_effective_state')) {
    function sige_wpp_health_effective_state(int $escola_id = 1): array {
        $state = function_exists('sige_wpp_get_state') ? sige_wpp_get_state($escola_id) : null;
        $mode  = $state ? sige_wpp_health_normalize_mode($state->mode ?? 'normal') : 'normal';
        $reason = $state && !empty($state->last_risk_reason) ? (string)$state->last_risk_reason : 'Sem sinal crítico recente.';
        $forced = false;

        if ($mode === 'paused') {
            $until = $state && !empty($state->paused_until) ? strtotime((string)$state->paused_until) : 0;
            // v12.11.6 - pausa sem data final ou já expirada não pode prender o WhatsApp indefinidamente.
            if ((!$until || $until <= sige_notify_now_ts()) && function_exists('sige_wpp_set_state')) {
                if (function_exists('sige_wpp_health_clear_pause_locks')) {
                    sige_wpp_health_clear_pause_locks($escola_id);
                }
                sige_wpp_set_state($escola_id, [
                    'mode' => 'recovery',
                    'paused_until' => null,
                    'recovery_started_at' => sige_wpp_now(),
                    'daily_failed' => 0,
                    'last_risk_reason' => 'Pausa concluída ou inválida; recuperação automática iniciada.',
                ]);
                if (function_exists('sige_wpp_health_mark_manual_since')) {
                    sige_wpp_health_mark_manual_since($escola_id);
                }
                $state = function_exists('sige_wpp_get_state') ? sige_wpp_get_state($escola_id) : $state;
                $mode = 'recovery';
                $reason = 'Pausa concluída ou inválida; recuperação automática iniciada.';
            }
        }

        $critical = sige_wpp_health_recent_critical_errors($escola_id, 72);
        if ($critical > 0) {
            $mode = 'review';
            $reason = 'Sinais críticos recentes da API/Z-API/WhatsApp. Envios pausados até validação humana.';
            $forced = true;
            if ($state && (string)($state->mode ?? '') !== 'review' && function_exists('sige_wpp_set_state')) {
                sige_wpp_set_state($escola_id, ['mode' => 'review', 'last_risk_reason' => $reason]);
            }
        } else {
            $failures = sige_wpp_health_recent_failures($escola_id, 6);
            if ($failures >= 5 && $mode !== 'paused') {
                $mode = 'paused';
                $reason = 'Pausa cautelar: 5 ou mais falhas novas nas últimas 6 horas.';
                $forced = true;
                if (function_exists('sige_wpp_set_state')) {
                    sige_wpp_set_state($escola_id, [
                        'mode' => 'paused',
                        'paused_until' => wp_date('Y-m-d H:i:s', sige_notify_now_ts() + 2 * HOUR_IN_SECONDS, sige_notify_tz()),
                        'last_risk_reason' => $reason,
                    ]);
                    $state = function_exists('sige_wpp_get_state') ? sige_wpp_get_state($escola_id) : $state;
                }
            } elseif ($failures >= 3 && $mode === 'normal') {
                $mode = 'recovery';
                $reason = 'Recuperação cautelar: falhas recentes de envio.';
                $forced = true;
            }
        }

        $day = sige_wpp_health_day_index($state);
        return [
            'state' => $state,
            'mode' => $mode,
            'label' => sige_wpp_health_state_label($mode),
            'day' => $day,
            'reason' => $reason,
            'forced' => $forced,
            'paused_until' => $state ? (string)($state->paused_until ?? '') : '',
        ];
    }
}

if (!function_exists('sige_wpp_health_effective_daily_limit')) {
    function sige_wpp_health_effective_daily_limit(int $escola_id = 1, int $base_limit = 180): int {
        $st = sige_wpp_health_effective_state($escola_id);
        return sige_wpp_health_limit_for_mode((string)$st['mode'], (int)$st['day'], $base_limit);
    }
}

if (!function_exists('sige_wpp_health_profile')) {
    function sige_wpp_health_profile(int $escola_id = 1): array {
        $st = sige_wpp_health_effective_state($escola_id);
        $limit = sige_wpp_health_effective_daily_limit($escola_id, SIGE_WPP_OPERATIONAL_DAILY_LIMIT);
        $sent = function_exists('sige_notify_count_sent_today') ? sige_notify_count_sent_today($escola_id) : 0;
        $remaining = max(0, $limit - $sent);
        $failures = sige_wpp_health_recent_failures($escola_id, 6);
        $critical = sige_wpp_health_recent_critical_errors($escola_id, 72);
        $resume_ts = null;
        if ($limit <= 0) {
            if ((string)$st['mode'] === 'paused' && !empty($st['paused_until'])) $resume_ts = strtotime((string)$st['paused_until']);
            if (!$resume_ts && (string)$st['mode'] !== 'review') $resume_ts = sige_notify_next_window_ts($escola_id);
        } elseif (!sige_notify_is_in_operational_window()) {
            $resume_ts = sige_notify_next_window_ts($escola_id);
        }
        return [
            'mode' => (string)$st['mode'],
            'label' => (string)$st['label'],
            'day' => (int)$st['day'],
            'limit' => (int)$limit,
            'sent' => (int)$sent,
            'remaining' => (int)$remaining,
            'percent' => $limit > 0 ? min(100, round(($sent / max(1,$limit)) * 100, 1)) : 100,
            'failures_6h' => (int)$failures,
            'critical_72h' => (int)$critical,
            'reason' => (string)$st['reason'],
            'forced' => !empty($st['forced']),
            'resume_ts' => $resume_ts ?: null,
            'resume_at' => $resume_ts ? wp_date('d/m/Y H:i', $resume_ts, sige_notify_tz()) : '',
        ];
    }
}

// A quota diária deixa de ser agressiva: 180 é o tecto conservador padrão.
add_filter('sige_wpp_operational_daily_limit', function ($limit, $escola_id = 1) {
    return sige_wpp_health_effective_daily_limit((int)$escola_id, min(180, max(0, (int)$limit)));
}, 10000, 2);

add_filter('sige_wpp_normal_daily_limit', function ($limit) {
    $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    return sige_wpp_health_effective_daily_limit($eid, min(180, max(0, (int)$limit)));
}, 10000);

add_filter('sige_wpp_normal_daily_link_limit', function ($limit) {
    $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $profile = sige_wpp_health_profile($eid);
    $mode = (string)$profile['mode'];
    if ($mode === 'review' || $mode === 'paused') return 0;
    if ($mode === 'recovery') return min((int)$limit, 5);
    if ($mode === 'warmup') return min((int)$limit, 10);
    return min((int)$limit, 60);
}, 10000);

if (!function_exists('sige_wpp_health_gap_seconds')) {
    function sige_wpp_health_gap_seconds($q): int {
        $tipo = strtolower((string)($q->tipo ?? ''));
        $msg  = (string)($q->mensagem ?? '');
        if (function_exists('sige_wpp_message_has_link') && sige_wpp_message_has_link($msg)) return 300;
        if (strpos($tipo, 'cobr') !== false || strpos($tipo, 'lembrete') !== false || strpos($tipo, 'pend') !== false || strpos($tipo, 'divida') !== false || strpos($tipo, 'dívida') !== false) return 300;
        if (strpos($tipo, 'recibo') !== false || strpos($tipo, 'pagamento') !== false || strpos($tipo, 'receipt') !== false) return 120;
        return 120;
    }
}

if (!function_exists('sige_wpp_health_last_sent_ts')) {
    function sige_wpp_health_last_sent_ts(int $escola_id = 1): int {
        global $wpdb;
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tQ}'") !== $tQ) return 0;
        $last = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(enviado_em) FROM {$tQ} WHERE escola_id=%d AND status='enviado'",
            $escola_id
        ));
        return $last ? (int)strtotime((string)$last) : 0;
    }
}

if (!function_exists('sige_wpp_health_next_global_slot_ts')) {
    function sige_wpp_health_next_global_slot_ts(int $escola_id = 1, $q = null): int {
        $now = sige_notify_now_ts();
        $base = sige_notify_next_window_ts($escola_id, $now);
        $last = sige_wpp_health_last_sent_ts($escola_id);
        $gap  = $q ? sige_wpp_health_gap_seconds($q) : 60;
        $next = max($base, $last ? ($last + $gap) : $base);
        return sige_notify_fit_in_operational_window($next, $escola_id);
    }
}

if (!function_exists('sige_wpp_health_can_attempt')) {
    function sige_wpp_health_can_attempt($q, array $args = []): array {
        $eid = (int)($q->escola_id ?? 0);
        $profile = sige_wpp_health_profile($eid);
        if ((int)$profile['limit'] <= 0) {
            return ['ok' => false, 'reason' => 'Health Mode: ' . $profile['label'] . ' - ' . $profile['reason']];
        }
        if ((int)$profile['remaining'] <= 0) {
            return ['ok' => false, 'reason' => 'Health Mode: limite diário actual atingido (' . (int)$profile['limit'] . '/dia).'];
        }
        $last = sige_wpp_health_last_sent_ts($eid);
        $gap = sige_wpp_health_gap_seconds($q);
        $now = sige_notify_now_ts();
        if ($last > 0 && ($now - $last) < $gap) {
            $faltam = max(1, $gap - ($now - $last));
            return ['ok' => false, 'reason' => 'Health Mode: intervalo global ainda não cumprido; aguardar ' . $faltam . 's.'];
        }
        return ['ok' => true, 'reason' => 'health_ok'];
    }
}

if (!function_exists('sige_wpp_health_set_mode')) {
    function sige_wpp_health_set_mode(int $escola_id, string $mode, string $reason = 'manual'): bool {
        if (!function_exists('sige_wpp_set_state')) return false;
        $mode = sige_wpp_health_normalize_mode($mode);
        $fields = [
            'mode' => $mode,
            'last_risk_reason' => substr($reason ?: 'Ajuste manual pelo administrador.', 0, 255),
            'day_key' => wp_date('Y-m-d', sige_notify_now_ts(), sige_notify_tz()),
        ];

        // v12.11.6 - mudança manual para modo operacional é um novo ponto de partida.
        // Limpa pausa anterior, contador antigo de falhas e transientes do Guardian para
        // evitar que histórico já tratado volte a bloquear o número.
        if (in_array($mode, ['normal','warmup','recovery'], true)) {
            if (function_exists('sige_wpp_health_clear_pause_locks')) {
                sige_wpp_health_clear_pause_locks($escola_id);
            }
            if (function_exists('sige_wpp_health_mark_manual_since')) {
                sige_wpp_health_mark_manual_since($escola_id);
            }
            $fields['paused_until'] = null;
            $fields['daily_failed'] = 0;
            $fields['daily_links'] = 0;
            $fields['recovery_started_at'] = ($mode === 'normal') ? null : sige_wpp_now();
        } elseif ($mode === 'paused') {
            $fields['paused_until'] = wp_date('Y-m-d H:i:s', sige_notify_now_ts() + 2 * HOUR_IN_SECONDS, sige_notify_tz());
        } else {
            $fields['paused_until'] = null;
        }
        return sige_wpp_set_state($escola_id, $fields);
    }
}

if (!function_exists('sige_wpp_health_admin_post')) {
    function sige_wpp_health_admin_post() {
        if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_admin')) {
            wp_die('Sem permissão para alterar o estado WhatsApp.', 403);
        }
        check_admin_referer('sige_wpp_health_set');
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        $mode = isset($_POST['mode']) ? sanitize_key((string)$_POST['mode']) : 'normal';
        $reason = isset($_POST['reason']) ? sanitize_text_field((string)$_POST['reason']) : 'Ajuste manual pelo administrador.';
        sige_wpp_health_set_mode($eid, $mode, $reason);
        $url = wp_get_referer() ?: admin_url('admin.php?page=sige-app&view=whatsapp_central');
        wp_safe_redirect(add_query_arg('wpp_health_updated', '1', $url));
        exit;
    }
}
add_action('admin_post_sige_wpp_health_set', 'sige_wpp_health_admin_post');
