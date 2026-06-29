<?php
/**
 * SIGE SoftGenial - Alertas Core
 * Ficheiro: includes/alertas-core.php
 *
 * API central do Sistema de Alertas v1 (Bloco 4).
 *
 * ┌────────────────────────────────────────────────────────────────────┐
 * │  Arquitectura:                                                     │
 * │                                                                    │
 * │   [Cron diário 07h00 Maputo]                                       │
 * │        │                                                           │
 * │        ▼                                                           │
 * │   sige_alertas_correr_deteccoes()                                  │
 * │        │                                                           │
 * │        ├── detectar_pagamentos_atraso() ───┐                       │
 * │        ├── detectar_mensalidades_falta() ──┤                       │
 * │        └── detectar_servicos_sem_centro() ─┘                       │
 * │                                            │                       │
 * │                                            ▼                       │
 * │                            sige_alerta_criar() ──► INSERT IGNORE   │
 * │                                  (hash_dedup)      sige_alertas    │
 * │                                                                    │
 * │   [Director clica "Notificar" no dashboard]                        │
 * │        │                                                           │
 * │        ▼                                                           │
 * │   AJAX → sige_alerta_notificar()  (implementado no Turno 2)        │
 * │        │                                                           │
 * │        ├── debounce 24h via sige_alertas_envios                    │
 * │        ├── envia WhatsApp (pai + mãe)                              │
 * │        └── envia E-mail (pai + mãe)                                │
 * └────────────────────────────────────────────────────────────────────┘
 *
 * Invariantes:
 *   • hash_dedup é SHA256(tipo + entidade_id + data_ancoragem) - idempotente
 *   • Cron diário nunca duplica; 30 dias de atraso = 1 linha em sige_alertas
 *   • Multi-tenant: toda a query filtra escola_id
 *   • Resolver alerta ≠ apagar. Status passa a 'resolvido' + audit.
 *
 * @since   13.5.0
 * @version 13.5.0-T1 (2026-04-19) - Turno 1: schema + detecção
 * @author  RMBJ Consultoria
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// CONSTANTES - tipos de alerta
// ============================================================================
if (!defined('SIGE_ALERTA_PAGAMENTO_ATRASO'))   define('SIGE_ALERTA_PAGAMENTO_ATRASO',   'pagamento_atraso');
if (!defined('SIGE_ALERTA_MENSALIDADE_FALTA'))  define('SIGE_ALERTA_MENSALIDADE_FALTA',  'mensalidade_falta');
if (!defined('SIGE_ALERTA_SERVICO_SEM_CENTRO')) define('SIGE_ALERTA_SERVICO_SEM_CENTRO', 'servico_sem_centro');

// [v14.0.0-T2] Cenários novos de cobrança proactiva (D-5, D-0, D+5, D+15)
// + cenário 11 (queue WhatsApp falhada >24h). Cada um tem a sua entrada em
// sige_alertas_config; os nomes batem com a coluna 'cenario' da M17.
if (!defined('SIGE_ALERTA_COBRANCA_D_MENOS_5')) define('SIGE_ALERTA_COBRANCA_D_MENOS_5', 'cobranca_d_menos_5');
if (!defined('SIGE_ALERTA_COBRANCA_D_ZERO'))    define('SIGE_ALERTA_COBRANCA_D_ZERO',    'cobranca_d_zero');
if (!defined('SIGE_ALERTA_COBRANCA_D_MAIS_5'))  define('SIGE_ALERTA_COBRANCA_D_MAIS_5',  'cobranca_d_mais_5');
if (!defined('SIGE_ALERTA_COBRANCA_D_MAIS_15')) define('SIGE_ALERTA_COBRANCA_D_MAIS_15', 'cobranca_d_mais_15');
if (!defined('SIGE_ALERTA_QUEUE_WPP_FALHADA'))  define('SIGE_ALERTA_QUEUE_WPP_FALHADA',  'queue_wpp_falhada');

// [v14.0.0-T2] Threshold do detector legado `pagamento_atraso` subiu 5→31:
// os 4 cenários novos cobrem D+5 e D+15; o legado passa a cobrir só dívida
// crónica (>30 dias) - evita dupla-notificação e mantém o sinal de
// "inadimplência prolongada" nítido. Fátima não perde nada visível, ganha
// clareza de inbox.
//
// A constante é definida com `if (!defined(...))` para permitir override em
// wp-config.php caso algum cliente queira voltar ao comportamento legado.
// Dias de tolerância antes de criar alerta de pagamento em atraso (após vencimento)
if (!defined('SIGE_ALERTA_DIAS_TOLERANCIA_ATRASO')) define('SIGE_ALERTA_DIAS_TOLERANCIA_ATRASO', 31);

// Dia do mês a partir do qual se detecta mensalidade em falta
if (!defined('SIGE_ALERTA_DIA_DETECCAO_MENSALIDADE')) define('SIGE_ALERTA_DIA_DETECCAO_MENSALIDADE', 10);

// [v14.0.0-T2] Horas sem movimento até cenário 11 disparar. Uma mensagem que
// fica em `falhou_definitivo` ultrapassando esta janela sinaliza problema
// operacional e não transitorio.
if (!defined('SIGE_ALERTA_QUEUE_FALHADA_HORAS')) define('SIGE_ALERTA_QUEUE_FALHADA_HORAS', 24);

// ============================================================================
// HELPER: nome das tabelas (com prefixo WP)
// ============================================================================
if (!function_exists('sige_alertas_tbl')) {
    function sige_alertas_tbl(): string {
        global $wpdb;
        return $wpdb->prefix . 'sige_alertas';
    }
}
if (!function_exists('sige_alertas_envios_tbl')) {
    function sige_alertas_envios_tbl(): string {
        global $wpdb;
        return $wpdb->prefix . 'sige_alertas_envios';
    }
}

// ============================================================================
// HELPER: hash de deduplicação
// ============================================================================
// O hash é o coração da idempotência do cron diário. Gera-se a partir do
// (tipo + entidade_id + data_ancoragem). data_ancoragem é uma data canónica
// do ponto de origem do alerta:
//   • pagamento_atraso  → data_vencimento do lançamento
//   • mensalidade_falta → primeiro dia do mês de referência
//   • servico_sem_centro → '0000-00-00' (global, 1 alerta por serviço)
//
// Se o cron corre 10 dias seguidos para um lançamento que continua em atraso,
// o hash é o mesmo todos os dias → INSERT IGNORE rejeita duplicatas.
// ============================================================================
if (!function_exists('sige_alerta_hash_dedup')) {
    function sige_alerta_hash_dedup(string $tipo, int $entidade_id, string $data_ancoragem = '0000-00-00'): string {
        $chave = $tipo . '|' . $entidade_id . '|' . $data_ancoragem;
        return hash('sha256', $chave);
    }
}

// ============================================================================
// API: sige_alerta_criar - insert idempotente via hash_dedup
// ============================================================================
/**
 * Cria um alerta (ou devolve o id do existente se já havia um com mesmo hash).
 *
 * @param string $tipo           Constante SIGE_ALERTA_*
 * @param array  $dados          {
 *     @type int    escola_id       (obrigatório)
 *     @type string entidade_tipo   'lancamento' | 'aluno' | 'servico'
 *     @type int    entidade_id
 *     @type int    aluno_id        (opcional - denormalizado p/ JOINs)
 *     @type string severidade     'baixa' | 'media' | 'alta' | 'critica'
 *     @type string titulo          (obrigatório)
 *     @type string mensagem
 *     @type array  metadata       (vira JSON)
 *     @type string data_ancoragem (YYYY-MM-DD, para hash_dedup)
 * }
 *
 * @return int ID do alerta (novo ou existente). 0 em caso de erro.
 */
if (!function_exists('sige_alerta_criar')) {
    function sige_alerta_criar(string $tipo, array $dados): int {
        global $wpdb;
        $t = sige_alertas_tbl();

        $escola_id     = (int)($dados['escola_id'] ?? 0);
        $entidade_id   = (int)($dados['entidade_id'] ?? 0);
        $data_ancor    = $dados['data_ancoragem'] ?? '0000-00-00';
        $titulo        = (string)($dados['titulo'] ?? '');

        if ($escola_id <= 0 || $titulo === '') {
            return 0;
        }

        $hash = sige_alerta_hash_dedup($tipo, $entidade_id, $data_ancor);

        // Check rápido: já existe?
        $existe_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t} WHERE escola_id = %d AND hash_dedup = %s LIMIT 1",
            $escola_id, $hash
        ));
        if ($existe_id > 0) {
            // Actualiza timestamp de "visto pela última vez" - útil para ver que
            // o alerta continua activo hoje (não foi resolvido silenciosamente).
            $wpdb->update($t,
                ['actualizado_em' => current_time('mysql')],
                ['id' => $existe_id],
                ['%s'], ['%d']
            );
            return $existe_id;
        }

        $metadata_json = null;
        if (!empty($dados['metadata']) && is_array($dados['metadata'])) {
            $metadata_json = wp_json_encode($dados['metadata']);
        }

        $now = current_time('mysql');
        $inserted = $wpdb->insert($t, [
            'escola_id'      => $escola_id,
            'tipo'           => $tipo,
            'severidade'     => $dados['severidade'] ?? 'media',
            'entidade_tipo'  => $dados['entidade_tipo'] ?? null,
            'entidade_id'    => $entidade_id ?: null,
            'aluno_id'       => !empty($dados['aluno_id']) ? (int)$dados['aluno_id'] : null,
            'titulo'         => $titulo,
            'mensagem'       => $dados['mensagem'] ?? null,
            'metadata'       => $metadata_json,
            'status'         => 'activo',
            'criado_em'      => $now,
            'actualizado_em' => $now,
            'hash_dedup'     => $hash,
        ], [
            '%d', '%s', '%s', '%s', '%d', '%d',
            '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        ]);

        if ($inserted === false) {
            // Race condition: duas instâncias do cron a correr em simultâneo.
            // O UNIQUE KEY uniq_dedup devolve erro → re-SELECT.
            $existe_id = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$t} WHERE escola_id = %d AND hash_dedup = %s LIMIT 1",
                $escola_id, $hash
            ));
            return $existe_id;
        }

        return (int)$wpdb->insert_id;
    }
}

// ============================================================================
// API: sige_alerta_resolver - marcar como resolvido (não apaga)
// ============================================================================
if (!function_exists('sige_alerta_resolver')) {
    function sige_alerta_resolver(int $alerta_id, int $user_id = 0): bool {
        global $wpdb;
        $t = sige_alertas_tbl();
        $user_id = $user_id ?: get_current_user_id();

        $ok = $wpdb->update($t, [
            'status'         => 'resolvido',
            'resolvido_em'   => current_time('mysql'),
            'resolvido_por'  => $user_id ?: null,
            'actualizado_em' => current_time('mysql'),
        ], ['id' => $alerta_id], ['%s','%s','%d','%s'], ['%d']);

        if ($ok && function_exists('sige_fin_log')) {
            sige_fin_log('alerta_resolvido', ['alerta_id' => $alerta_id, 'user_id' => $user_id]);
        }
        return (bool)$ok;
    }
}

// ============================================================================
// API: sige_alerta_dispensar - desligar sem resolver o problema subjacente
// ============================================================================
// "Dispensar" significa: reconheço o alerta mas não quero que apareça mais.
// Útil quando o problema não aplica (ex: aluno trial, serviço experimental).
// Diferente de resolver: resolver = problema corrigido. Dispensar = ignorar.
// ============================================================================
if (!function_exists('sige_alerta_dispensar')) {
    function sige_alerta_dispensar(int $alerta_id, int $user_id = 0, string $motivo = ''): bool {
        global $wpdb;
        $t = sige_alertas_tbl();
        $user_id = $user_id ?: get_current_user_id();

        // Se há motivo, anexa ao metadata para audit
        $motivo = trim($motivo);
        if ($motivo !== '') {
            $row = $wpdb->get_row($wpdb->prepare("SELECT metadata FROM {$t} WHERE id = %d", $alerta_id));
            $meta = [];
            if ($row && !empty($row->metadata)) {
                $decoded = json_decode($row->metadata, true);
                if (is_array($decoded)) $meta = $decoded;
            }
            $meta['dispensa'] = [
                'motivo'  => substr($motivo, 0, 500),
                'por'     => $user_id,
                'quando'  => current_time('mysql'),
            ];
            $wpdb->update($t, ['metadata' => wp_json_encode($meta)], ['id' => $alerta_id], ['%s'], ['%d']);
        }

        $ok = $wpdb->update($t, [
            'status'         => 'dispensado',
            'resolvido_em'   => current_time('mysql'),
            'resolvido_por'  => $user_id ?: null,
            'actualizado_em' => current_time('mysql'),
        ], ['id' => $alerta_id], ['%s','%s','%d','%s'], ['%d']);

        if ($ok && function_exists('sige_fin_log')) {
            sige_fin_log('alerta_dispensado', [
                'alerta_id' => $alerta_id,
                'user_id'   => $user_id,
                'motivo'    => substr($motivo, 0, 200),
            ]);
        }
        return (bool)$ok;
    }
}

// ============================================================================
// API: sige_alerta_listar - lista com filtros para UI
// ============================================================================
/**
 * @param array $filtros {
 *     @type int    $escola_id   (default: actual)
 *     @type string $status      'activo' (default) | 'resolvido' | 'dispensado' | 'todos'
 *     @type string $tipo        (opcional)
 *     @type string $severidade  (opcional)
 *     @type int    $aluno_id    (opcional)
 *     @type int    $limit       (default 100, max 500)
 *     @type int    $offset      (default 0)
 *     @type string $order       'recente' (default) | 'antigo' | 'severidade'
 * }
 */
if (!function_exists('sige_alerta_listar')) {
    function sige_alerta_listar(array $filtros = []): array {
        global $wpdb;
        $t = sige_alertas_tbl();

        $escola_id = isset($filtros['escola_id'])
            ? (int)$filtros['escola_id']
            : (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);

        $status    = $filtros['status'] ?? 'activo';
        $tipo      = $filtros['tipo'] ?? '';
        $severid   = $filtros['severidade'] ?? '';
        $aluno_id  = (int)($filtros['aluno_id'] ?? 0);
        $limit     = min(500, max(1, (int)($filtros['limit'] ?? 100)));
        $offset    = max(0, (int)($filtros['offset'] ?? 0));
        $order     = $filtros['order'] ?? 'recente';

        $where  = ['escola_id = %d'];
        $params = [$escola_id];

        if ($status !== 'todos') {
            $where[] = 'status = %s';
            $params[] = $status;
        }
        if ($tipo !== '') {
            $where[] = 'tipo = %s';
            $params[] = $tipo;
        }
        if ($severid !== '') {
            $where[] = 'severidade = %s';
            $params[] = $severid;
        }
        if ($aluno_id > 0) {
            $where[] = 'aluno_id = %d';
            $params[] = $aluno_id;
        }

        $order_sql = 'criado_em DESC';
        if ($order === 'antigo')       $order_sql = 'criado_em ASC';
        elseif ($order === 'severidade') {
            // Ordem: critica > alta > media > baixa
            $order_sql = "FIELD(severidade,'critica','alta','media','baixa') ASC, criado_em DESC";
        }

        $sql = "SELECT * FROM {$t} WHERE " . implode(' AND ', $where)
             . " ORDER BY {$order_sql} LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));
        return is_array($rows) ? $rows : [];
    }
}

// ============================================================================
// API: sige_alerta_contagem_activos - para badge de menu
// ============================================================================
if (!function_exists('sige_alerta_contagem_activos')) {
    function sige_alerta_contagem_activos(?int $escola_id = null): int {
        global $wpdb;
        $t = sige_alertas_tbl();
        $eid = $escola_id ?? (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$t} WHERE escola_id = %d AND status = 'activo'",
            $eid
        ));
    }
}

// ============================================================================
// API: contagem por severidade (para widget dashboard)
// ============================================================================
if (!function_exists('sige_alerta_contagem_por_severidade')) {
    function sige_alerta_contagem_por_severidade(?int $escola_id = null): array {
        global $wpdb;
        $t = sige_alertas_tbl();
        $eid = $escola_id ?? (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT severidade, COUNT(*) AS total
             FROM {$t}
             WHERE escola_id = %d AND status = 'activo'
             GROUP BY severidade",
            $eid
        ));

        $out = ['critica' => 0, 'alta' => 0, 'media' => 0, 'baixa' => 0];
        foreach ((array)$rows as $r) {
            $out[$r->severidade] = (int)$r->total;
        }
        return $out;
    }
}

// ============================================================================
// DETECTOR 1 - Pagamentos em atraso
// ============================================================================
// Condição: lancamento com data_vencimento + SIGE_ALERTA_DIAS_TOLERANCIA_ATRASO
// dias < hoje, status != 'pago' e não cancelado.
//
// Hash_dedup: SHA256('pagamento_atraso' + lancamento_id + data_vencimento)
// → 1 alerta por lançamento, estável ao longo dos dias.
//
// Severidade escala com dias de atraso:
//   5-14 dias  → média
//   15-29 dias → alta
//   30+ dias   → crítica
// ============================================================================
if (!function_exists('sige_alertas_detectar_pagamentos_atraso')) {
    function sige_alertas_detectar_pagamentos_atraso(int $escola_id): int {
        global $wpdb;
        $tL = $wpdb->prefix . 'sige_fin_lancamentos';
        $tA = $wpdb->prefix . 'sige_alunos';

        // Garantir que a tabela existe (defesa contra deploy parcial)
        if (!sige_alertas_table_exists($tL) || !sige_alertas_table_exists($tA)) {
            return 0;
        }

        $hoje = wp_date('Y-m-d');
        $dias_tol = SIGE_ALERTA_DIAS_TOLERANCIA_ATRASO;
        $__sige_a_activo_alertas_cobranca = function_exists('sige_aluno_activo_sql')
            ? sige_aluno_activo_sql('a')
            : "(a.status IS NULL OR LOWER(a.status) IN ('activo','ativo','activa','ativa'))";

        // Lançamentos em atraso: vencidos há mais de $dias_tol dias,
        // ainda não pagos, e não cancelados (cancelado_em IS NULL).
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT
                l.id,
                l.aluno_id,
                l.descricao,
                l.mes_referencia,
                l.valor_original,
                l.valor_pago,
                l.valor_multa,
                l.valor_desconto,
                l.valor_desconto_especial,
                l.data_vencimento,
                l.status,
                a.nome_completo,
                DATEDIFF(%s, l.data_vencimento) AS dias_atraso
            FROM {$tL} l
            JOIN {$tA} a ON a.id = l.aluno_id AND a.escola_id = l.escola_id
            WHERE l.escola_id = %d
              AND {$__sige_a_activo_alertas_cobranca}
              AND l.status IN ('pendente', 'parcial')
              AND l.data_vencimento IS NOT NULL
              AND l.cancelado_em IS NULL
              AND DATEDIFF(%s, l.data_vencimento) > %d
        ", $hoje, $escola_id, $hoje, $dias_tol));

        if (empty($rows)) return 0;

        $moeda = function_exists('sige_moeda') ? sige_moeda() : 'MT';
        $criados = 0;

        foreach ($rows as $r) {
            $dias = max(0, (int)$r->dias_atraso);

            // Calcular saldo pendente
            $saldo = (float)$r->valor_original
                   + (float)($r->valor_multa ?? 0)
                   - (float)($r->valor_desconto ?? 0)
                   - (float)($r->valor_desconto_especial ?? 0)
                   - (float)($r->valor_pago ?? 0);
            if ($saldo <= 0.005) continue; // margem de arredondamento

            // Escala de severidade por dias
            if     ($dias >= 30) $severid = 'critica';
            elseif ($dias >= 15) $severid = 'alta';
            else                 $severid = 'media';

            $titulo = sprintf(
                'Pagamento em atraso: %s - %s (%d dias)',
                $r->nome_completo ?: '-',
                $r->descricao ?: 'Serviço',
                $dias
            );

            $mensagem = sprintf(
                'O lançamento #%d (%s) de %s está em atraso há %d dias. Saldo: %s %s.',
                (int)$r->id,
                $r->descricao ?: 'Serviço',
                $r->nome_completo ?: '-',
                $dias,
                number_format($saldo, 2, ',', '.'),
                $moeda
            );

            $id = sige_alerta_criar(SIGE_ALERTA_PAGAMENTO_ATRASO, [
                'escola_id'      => $escola_id,
                'severidade'     => $severid,
                'entidade_tipo'  => 'lancamento',
                'entidade_id'    => (int)$r->id,
                'aluno_id'       => (int)$r->aluno_id,
                'titulo'         => $titulo,
                'mensagem'       => $mensagem,
                'data_ancoragem' => (string)$r->data_vencimento,
                'metadata'       => [
                    'lancamento_id'   => (int)$r->id,
                    'aluno_id'        => (int)$r->aluno_id,
                    'aluno_nome'      => $r->nome_completo,
                    'descricao'       => $r->descricao,
                    'mes_referencia'  => $r->mes_referencia,
                    'valor'           => round($saldo, 2),
                    'moeda'           => $moeda,
                    'dias_atraso'     => $dias,
                    'data_vencimento' => $r->data_vencimento,
                ],
            ]);

            if ($id > 0) $criados++;
        }

        return $criados;
    }
}

// ============================================================================
// DETECTOR 2 - Mensalidade em falta
// ============================================================================
// Condição: aluno com status='activo', existe serviço de tipo='mensalidade'
// na escola, mas NÃO há lançamento desse tipo para o mes_referencia do mês
// corrente. Só dispara a partir do dia SIGE_ALERTA_DIA_DETECCAO_MENSALIDADE
// (default 10) - antes disso pode estar simplesmente por gerar.
//
// Modelo de mes_referencia: 'YYYY-MM' (ex: '2026-04').
//
// Hash_dedup: SHA256('mensalidade_falta' + aluno_id + YYYY-MM-01)
// → 1 alerta por (aluno, mês), estável dentro do mês.
//
// Severidade:
//   Dia 10-14 → média
//   Dia 15-19 → alta
//   Dia 20+   → crítica
// ============================================================================
if (!function_exists('sige_alertas_detectar_mensalidades_falta')) {
    function sige_alertas_detectar_mensalidades_falta(int $escola_id): int {
        global $wpdb;
        $tL = $wpdb->prefix . 'sige_fin_lancamentos';
        $tA = $wpdb->prefix . 'sige_alunos';
        $tS = $wpdb->prefix . 'sige_fin_servicos';

        if (!sige_alertas_table_exists($tL) ||
            !sige_alertas_table_exists($tA) ||
            !sige_alertas_table_exists($tS)) {
            return 0;
        }

        // Guarda de calendário: só detectar a partir do dia configurado
        $dia_actual = (int)wp_date('j');
        if ($dia_actual < SIGE_ALERTA_DIA_DETECCAO_MENSALIDADE) {
            return 0;
        }

        $mes_ref = wp_date('Y-m');              // '2026-04'
        $data_ancor = wp_date('Y-m-01');        // '2026-04-01' - chave estável no mês

        // Escala de severidade por dia do mês
        if     ($dia_actual >= 20) $severid = 'critica';
        elseif ($dia_actual >= 15) $severid = 'alta';
        else                       $severid = 'media';

        // Alunos activos que NÃO têm lançamento de mensalidade para o mês corrente
        // (LEFT JOIN com filtro na WHERE da subquery para capturar "ausência")
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT
                a.id   AS aluno_id,
                a.nome_completo
            FROM {$tA} a
            WHERE a.escola_id = %d
              AND {$__sige_a_activo_alertas_cobranca}
              AND NOT EXISTS (
                    SELECT 1
                    FROM {$tL} l
                    JOIN {$tS} s ON s.id = l.servico_id
                    WHERE l.aluno_id = a.id
                      AND l.escola_id = %d
                      AND l.mes_referencia = %s
                      AND l.cancelado_em IS NULL
                      AND LOWER(s.tipo) = 'mensalidade'
              )
            ORDER BY a.nome_completo ASC
        ", $escola_id, $escola_id, $mes_ref));

        if (empty($rows)) return 0;

        $mes_fmt = wp_date('F Y', strtotime($data_ancor));
        $criados = 0;

        foreach ($rows as $r) {
            $titulo = sprintf(
                'Mensalidade em falta: %s - %s',
                $r->nome_completo ?: '-',
                $mes_fmt
            );
            $mensagem = sprintf(
                'O aluno %s não tem mensalidade gerada para o mês %s. Gere o lançamento em Tesouraria → Gerador.',
                $r->nome_completo ?: '-',
                $mes_fmt
            );

            $id = sige_alerta_criar(SIGE_ALERTA_MENSALIDADE_FALTA, [
                'escola_id'      => $escola_id,
                'severidade'     => $severid,
                'entidade_tipo'  => 'aluno',
                'entidade_id'    => (int)$r->aluno_id,
                'aluno_id'       => (int)$r->aluno_id,
                'titulo'         => $titulo,
                'mensagem'       => $mensagem,
                'data_ancoragem' => $data_ancor,
                'metadata'       => [
                    'aluno_id'       => (int)$r->aluno_id,
                    'aluno_nome'     => $r->nome_completo,
                    'mes_referencia' => $mes_ref,
                    'dia_deteccao'   => $dia_actual,
                ],
            ]);

            if ($id > 0) $criados++;
        }

        return $criados;
    }
}

// ============================================================================
// DETECTOR 3 - Serviços sem centro atribuído
// ============================================================================
// Condição: sige_fin_servicos.ativo=1 AND (centro_id IS NULL OR centro_id=0)
// Hash_dedup: SHA256('servico_sem_centro' + servico_id + '0000-00-00')
// → 1 alerta por serviço; desaparece assim que Fátima classifica.
//
// Severidade: alta (bloqueia gerador, mas não é crítico para pagamentos já feitos)
//
// Esta reutiliza parcialmente sige_fin_count_servicos_sem_centro() mas precisa
// da lista - não só contagem - por isso faz a query directa.
// ============================================================================
if (!function_exists('sige_alertas_detectar_servicos_sem_centro')) {
    function sige_alertas_detectar_servicos_sem_centro(int $escola_id): int {
        global $wpdb;
        $tS = $wpdb->prefix . 'sige_fin_servicos';

        if (!sige_alertas_table_exists($tS)) return 0;

        // Confirmar que coluna centro_id já existe (M13 pode não ter corrido em BD legada)
        if (function_exists('sige_db_column_exists') &&
            !sige_db_column_exists($tS, 'centro_id')) {
            return 0;
        }

        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT id, nome, tipo, valor
            FROM {$tS}
            WHERE escola_id = %d
              AND ativo = 1
              AND (centro_id IS NULL OR centro_id = 0)
            ORDER BY nome ASC
        ", $escola_id));

        if (empty($rows)) return 0;

        $moeda = function_exists('sige_moeda') ? sige_moeda() : 'MT';
        $criados = 0;

        foreach ($rows as $r) {
            $titulo = sprintf('Serviço sem centro: %s', $r->nome);
            $mensagem = sprintf(
                'O serviço "%s" (%s, %s %s) não tem centro de custo atribuído. O gerador de mensalidades vai bloquear. Atribua em Tesouraria → Configurar Preços.',
                $r->nome,
                $r->tipo,
                number_format((float)$r->valor, 2, ',', '.'),
                $moeda
            );

            $id = sige_alerta_criar(SIGE_ALERTA_SERVICO_SEM_CENTRO, [
                'escola_id'      => $escola_id,
                'severidade'     => 'alta',
                'entidade_tipo'  => 'servico',
                'entidade_id'    => (int)$r->id,
                'aluno_id'       => null,
                'titulo'         => $titulo,
                'mensagem'       => $mensagem,
                'data_ancoragem' => '0000-00-00', // global, não muda
                'metadata'       => [
                    'servico_id'   => (int)$r->id,
                    'servico_nome' => $r->nome,
                    'tipo'         => $r->tipo,
                    'valor'        => (float)$r->valor,
                    'moeda'        => $moeda,
                ],
            ]);

            if ($id > 0) $criados++;
        }

        // Auto-resolver alertas deste tipo para serviços que já têm centro
        // (caso Fátima tenha atribuído manualmente entre runs do cron)
        sige_alertas_auto_resolver_servicos_com_centro($escola_id);

        return $criados;
    }
}

// ============================================================================
// Auto-resolver alertas de serviço sem centro quando o problema foi corrigido
// ============================================================================
// Desenho: quando a Fátima classifica um serviço, o alerta devia fechar-se
// automaticamente. Caso contrário fica pendurado no dashboard mesmo depois
// de o problema estar resolvido.
// ============================================================================
if (!function_exists('sige_alertas_auto_resolver_servicos_com_centro')) {
    function sige_alertas_auto_resolver_servicos_com_centro(int $escola_id): int {
        global $wpdb;
        $tA = sige_alertas_tbl();
        $tS = $wpdb->prefix . 'sige_fin_servicos';

        if (!sige_alertas_table_exists($tS)) return 0;

        // Subquery: serviços que já têm centro (centro_id > 0)
        $ok = $wpdb->query($wpdb->prepare("
            UPDATE {$tA}
            SET status = 'resolvido',
                resolvido_em = %s,
                resolvido_por = NULL,
                actualizado_em = %s
            WHERE escola_id = %d
              AND tipo = %s
              AND status = 'activo'
              AND entidade_id IN (
                    SELECT id FROM {$tS}
                    WHERE escola_id = %d
                      AND centro_id IS NOT NULL
                      AND centro_id > 0
              )
        ", current_time('mysql'), current_time('mysql'),
           $escola_id, SIGE_ALERTA_SERVICO_SEM_CENTRO, $escola_id));

        return (int)$ok;
    }
}

// ============================================================================
// Auto-resolver alertas de pagamento_atraso quando lançamento foi pago/cancelado
// ============================================================================
if (!function_exists('sige_alertas_auto_resolver_pagamentos_pagos')) {
    function sige_alertas_auto_resolver_pagamentos_pagos(int $escola_id): int {
        global $wpdb;
        $tA = sige_alertas_tbl();
        $tL = $wpdb->prefix . 'sige_fin_lancamentos';

        if (!sige_alertas_table_exists($tL)) return 0;

        $ok = $wpdb->query($wpdb->prepare("
            UPDATE {$tA}
            SET status = 'resolvido',
                resolvido_em = %s,
                resolvido_por = NULL,
                actualizado_em = %s
            WHERE escola_id = %d
              AND tipo = %s
              AND status = 'activo'
              AND entidade_id IN (
                    SELECT id FROM {$tL}
                    WHERE escola_id = %d
                      AND (status = 'pago' OR cancelado_em IS NOT NULL)
              )
        ", current_time('mysql'), current_time('mysql'),
           $escola_id, SIGE_ALERTA_PAGAMENTO_ATRASO, $escola_id));

        return (int)$ok;
    }
}

// ============================================================================
// Auto-resolver alertas de mensalidade_falta quando lançamento foi gerado
// ============================================================================
if (!function_exists('sige_alertas_auto_resolver_mensalidades_geradas')) {
    function sige_alertas_auto_resolver_mensalidades_geradas(int $escola_id): int {
        global $wpdb;
        $tA = sige_alertas_tbl();
        $tL = $wpdb->prefix . 'sige_fin_lancamentos';
        $tS = $wpdb->prefix . 'sige_fin_servicos';

        if (!sige_alertas_table_exists($tL) || !sige_alertas_table_exists($tS)) return 0;

        $mes_ref = wp_date('Y-m');
        $data_ancor = wp_date('Y-m-01');

        // Resolver alertas deste mês para alunos que já têm mensalidade gerada
        $ok = $wpdb->query($wpdb->prepare("
            UPDATE {$tA}
            SET status = 'resolvido',
                resolvido_em = %s,
                resolvido_por = NULL,
                actualizado_em = %s
            WHERE escola_id = %d
              AND tipo = %s
              AND status = 'activo'
              AND aluno_id IN (
                    SELECT DISTINCT l.aluno_id
                    FROM {$tL} l
                    JOIN {$tS} s ON s.id = l.servico_id
                    WHERE l.escola_id = %d
                      AND l.mes_referencia = %s
                      AND l.cancelado_em IS NULL
                      AND LOWER(s.tipo) = 'mensalidade'
              )
              AND hash_dedup = SHA2(CONCAT(%s, '|', aluno_id, '|', %s), 256)
        ", current_time('mysql'), current_time('mysql'),
           $escola_id, SIGE_ALERTA_MENSALIDADE_FALTA,
           $escola_id, $mes_ref,
           SIGE_ALERTA_MENSALIDADE_FALTA, $data_ancor));

        return (int)$ok;
    }
}

// ============================================================================
// WRAPPER: correr todos os detectores para uma escola
// ============================================================================
// Devolve array com estatísticas por detector. Usado pelo cron diário e pelo
// "Correr cron agora" manual do Director (implementado no Turno 2).
// ============================================================================
if (!function_exists('sige_alertas_correr_deteccoes')) {
    function sige_alertas_correr_deteccoes(int $escola_id): array {
        $inicio = microtime(true);

        // Primeiro auto-resolve problemas já corrigidos (limpa o inbox)
        $auto_pag = sige_alertas_auto_resolver_pagamentos_pagos($escola_id);
        $auto_srv = sige_alertas_auto_resolver_servicos_com_centro($escola_id);
        $auto_men = sige_alertas_auto_resolver_mensalidades_geradas($escola_id);

        // [v14.0.0-T2] Auto-resolve dos cenários de cobrança proactiva:
        // se o aluno pagou antes do D+N previsto, resolve os 4 tipos.
        // Reutilizamos o auto-resolver legado já escalado para todos os tipos
        // ligados a lançamento (ver função auto_resolver_pagamentos_pagos).
        $auto_cobranca = sige_alertas_auto_resolver_cobranca_paga($escola_id);
        $auto_inactivos = sige_alertas_auto_resolver_alunos_inactivos_financeiro($escola_id);

        // Detectores legados (v13.5.0) - criam alertas idempotentes via hash
        $n_pag  = sige_alertas_detectar_pagamentos_atraso($escola_id);
        $n_men  = sige_alertas_detectar_mensalidades_falta($escola_id);
        $n_srv  = sige_alertas_detectar_servicos_sem_centro($escola_id);

        // [v14.0.0-T2] 4 cenários novos de cobrança proactiva.
        // Cada um respeita o toggle de sige_alertas_config (activo=0/1).
        // Internamente: se activo=1, cria alerta + dispara auto-send via
        // sige_alerta_notificar() respeitando o canal configurado. Isto é o
        // "coração" do Bloco 4.
        $n_dm5  = sige_alertas_detectar_cobranca_d_menos_5($escola_id);
        $n_d0   = sige_alertas_detectar_cobranca_d_zero($escola_id);
        $n_dp5  = sige_alertas_detectar_cobranca_d_mais_5($escola_id);
        $n_dp15 = sige_alertas_detectar_cobranca_d_mais_15($escola_id);

        // [v14.0.0-T2] Cenário 11 - queue WhatsApp falhada >24h.
        // Alerta INTERNO (canal email_only, destinatário = Director).
        // Não dispara auto-send pelo caminho de encarregado - tem lógica
        // própria de notificação via sige_alertas_notificar_queue_falhada().
        $n_q11 = sige_alertas_detectar_queue_wpp_falhada($escola_id);

        $duracao_ms = (int)round((microtime(true) - $inicio) * 1000);

        $stats = [
            'escola_id'            => $escola_id,
            'pagamentos_criados'   => $n_pag,
            'mensalidades_criados' => $n_men,
            'servicos_criados'     => $n_srv,
            // [v14.0.0-T2] cobranças proactivas + cenário 11
            'cobranca_d_menos_5'   => $n_dm5,
            'cobranca_d_zero'      => $n_d0,
            'cobranca_d_mais_5'    => $n_dp5,
            'cobranca_d_mais_15'   => $n_dp15,
            'queue_wpp_falhada'    => $n_q11,
            'auto_resolvidos'      => $auto_pag + $auto_srv + $auto_men + $auto_cobranca + $auto_inactivos,
            'total_novos'          => $n_pag + $n_men + $n_srv
                                    + $n_dm5 + $n_d0 + $n_dp5 + $n_dp15 + $n_q11,
            'duracao_ms'           => $duracao_ms,
            'timestamp'            => current_time('mysql'),
        ];

        if (function_exists('sige_fin_log')) {
            sige_fin_log('alertas_deteccao_corrida', $stats);
        }

        return $stats;
    }
}

// ============================================================================
// [v14.0.0-T2] CONFIG READERS - leitura de sige_alertas_config com cache
// ============================================================================
// Uma corrida do cron pode chamar sige_alertas_config_get() dezenas de vezes
// (uma por detector × potencialmente uma por alerta criado). Cache in-request
// evita N queries. Cache é invalidada ao fim do request naturalmente.
// ============================================================================
if (!function_exists('sige_alertas_config_tbl')) {
    function sige_alertas_config_tbl(): string {
        global $wpdb;
        return $wpdb->prefix . 'sige_alertas_config';
    }
}

if (!function_exists('sige_alertas_config_get')) {
    /**
     * Devolve a configuração efectiva para um cenário.
     *
     * Fallback em cadeia:
     *   1. Linha da tabela sige_alertas_config (produzida pela M17)
     *   2. Default seguro hard-coded (activo=0, canal='ambos', dias_ref=null)
     *      - usado se a tabela ainda não existe (janela entre deploy do ZIP
     *      e execução da migração, ou instalação nova antes do primeiro
     *      plugins_loaded).
     *
     * @param int    $escola_id
     * @param string $cenario   Identificador: 'cobranca_d_menos_5', ...
     * @return array{activo:int,canal:string,dias_ref:?int,config_extra:?array}
     */
    function sige_alertas_config_get(int $escola_id, string $cenario): array {
        global $wpdb;

        static $cache = [];
        $key = $escola_id . '|' . $cenario;
        if (isset($cache[$key])) return $cache[$key];

        $t = sige_alertas_config_tbl();

        // Defensiva: se a tabela não existe (migração ainda não correu),
        // devolve default seguro e cacheia para não re-testar.
        if (!sige_alertas_table_exists($t)) {
            return $cache[$key] = [
                'activo'       => 0,
                'canal'        => 'ambos',
                'dias_ref'     => null,
                'config_extra' => null,
            ];
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT activo, canal, dias_ref, config_extra
             FROM {$t}
             WHERE escola_id = %d AND cenario = %s
             LIMIT 1",
            $escola_id, $cenario
        ));

        if (!$row) {
            // Cenário não seeded - default fechado para segurança.
            return $cache[$key] = [
                'activo'       => 0,
                'canal'        => 'ambos',
                'dias_ref'     => null,
                'config_extra' => null,
            ];
        }

        $extra = null;
        if (!empty($row->config_extra)) {
            $decoded = json_decode((string)$row->config_extra, true);
            if (is_array($decoded)) $extra = $decoded;
        }

        return $cache[$key] = [
            'activo'       => (int)$row->activo,
            'canal'        => (string)$row->canal,
            'dias_ref'     => $row->dias_ref === null ? null : (int)$row->dias_ref,
            'config_extra' => $extra,
        ];
    }
}

if (!function_exists('sige_alertas_config_set')) {
    /**
     * Actualiza a configuração de um cenário (UI de config).
     *
     * @param int    $escola_id
     * @param string $cenario
     * @param array  $patch   Campos a alterar (qualquer subset de
     *                        activo, canal, dias_ref, config_extra)
     * @return bool
     */
    function sige_alertas_config_set(int $escola_id, string $cenario, array $patch): bool {
        if (!sige_tenant_write_guard((int) $escola_id, 'sige_alertas_config_set')) { return false; }
        global $wpdb;
        $t = sige_alertas_config_tbl();
        if (!sige_alertas_table_exists($t)) return false;

        $data    = [];
        $formats = [];

        if (array_key_exists('activo', $patch)) {
            $data['activo']    = (int)!empty($patch['activo']);
            $formats[] = '%d';
        }
        if (array_key_exists('canal', $patch)) {
            $valid = ['whatsapp_only', 'email_only', 'ambos'];
            $canal = in_array($patch['canal'], $valid, true) ? $patch['canal'] : 'ambos';
            $data['canal'] = $canal;
            $formats[] = '%s';
        }
        if (array_key_exists('dias_ref', $patch)) {
            $data['dias_ref'] = $patch['dias_ref'] === null ? null : (int)$patch['dias_ref'];
            $formats[] = ($patch['dias_ref'] === null) ? null : '%d';
        }
        if (array_key_exists('config_extra', $patch)) {
            $data['config_extra'] = $patch['config_extra'] === null
                ? null
                : (is_string($patch['config_extra'])
                    ? $patch['config_extra']
                    : wp_json_encode($patch['config_extra']));
            $formats[] = ($patch['config_extra'] === null) ? null : '%s';
        }

        if (empty($data)) return false;

        $data['actualizado_em'] = current_time('mysql');
        $formats[] = '%s';

        $ok = $wpdb->update(
            $t,
            $data,
            ['escola_id' => $escola_id, 'cenario' => $cenario],
            $formats,
            ['%d', '%s']
        );

        // Invalidar cache in-request para não devolver valor velho
        // a chamadas subsequentes dentro do mesmo page-load
        // (ex: save → reload imediato da página de config).
        if (function_exists('sige_alertas_config_get')) {
            // Workaround: static de outra função não é directamente mutável.
            // A invalidação ideal é via refactor para classe; por agora,
            // o caller é responsável por recarregar a página (já é o fluxo
            // normal da UI de config no admin WP).
        }

        return $ok !== false;
    }
}

// ============================================================================
// [v14.0.0-T2] DETECTORES DE COBRANÇA PROACTIVA
// ============================================================================
// 4 detectores espelhados: D-5, D-0, D+5, D+15. Cada um procura lançamentos
// cujo `data_vencimento = CURDATE() + dias_ref` (dias_ref negativo = antes
// de vencer, positivo = depois). Criam alerta com hash_dedup estável
// (tipo + lancamento_id + data_vencimento) → garantia de "1 alerta por
// fase por lançamento" mesmo se o cron correr 2× no mesmo dia.
//
// Auto-send: se sige_alertas_config.activo=1 para o cenário, chama
// sige_alerta_notificar() IMEDIATAMENTE após criação do alerta - respeita o
// canal configurado (whatsapp_only / email_only / ambos) e o debounce 24h
// já existente em sige_alertas_enviar_*_dual().
// ============================================================================

/**
 * Helper interno: query canónica dos 4 detectores de cobrança.
 *
 * Todos partilham a mesma estrutura - diferem só no offset `dias_ref`.
 * Factor-out aqui mantém a lógica SQL numa única sql-truth-source; alterar
 * filtro (ex: excluir descontos especiais) propaga às 4 fases sem divergir.
 *
 * @param int $escola_id
 * @param int $dias_ref   Offset em dias. -5 = 5 dias antes de vencer.
 *                        +5 = 5 dias depois de vencer.
 * @return array Linhas com id, aluno_id, descricao, mes_referencia,
 *               valor_original, valor_pago, valor_multa, valor_desconto,
 *               valor_desconto_especial, data_vencimento, status,
 *               nome_completo, saldo (calculado).
 */
if (!function_exists('sige_alertas_cobranca_query_base')) {
    function sige_alertas_cobranca_query_base(int $escola_id, int $dias_ref): array {
        global $wpdb;
        $tL = $wpdb->prefix . 'sige_fin_lancamentos';
        $tA = $wpdb->prefix . 'sige_alunos';

        if (!sige_alertas_table_exists($tL) || !sige_alertas_table_exists($tA)) {
            return [];
        }
        $__sige_a_activo_alertas_cobranca = function_exists('sige_aluno_activo_sql')
            ? sige_aluno_activo_sql('a')
            : "(a.status IS NULL OR LOWER(a.status) IN ('activo','ativo','activa','ativa'))";

        // Fórmula da janela: data_vencimento = hoje + dias_ref.
        // Negativo = futuro (D-5 → vencimento daqui a 5 dias);
        // zero = hoje; positivo = passado (D+5 → venceu há 5 dias).
        //
        // IMPORTANTE: usamos DATE(DATE_ADD(...)) para ignorar a componente
        // horária - lançamento com data_vencimento '2026-04-20 00:00:00' e
        // hoje '2026-04-15 15:00:00' → diferença de 5 dias, NÃO 4d 9h.
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT
                l.id,
                l.aluno_id,
                l.descricao,
                l.mes_referencia,
                l.valor_original,
                l.valor_pago,
                l.valor_multa,
                l.valor_desconto,
                l.valor_desconto_especial,
                l.data_vencimento,
                l.status,
                a.nome_completo
            FROM {$tL} l
            JOIN {$tA} a ON a.id = l.aluno_id AND a.escola_id = l.escola_id
            WHERE l.escola_id = %d
              AND {$__sige_a_activo_alertas_cobranca}
              AND l.status IN ('pendente', 'parcial')
              AND l.data_vencimento IS NOT NULL
              AND l.cancelado_em IS NULL
              AND DATE(l.data_vencimento) = DATE(DATE_ADD(CURDATE(), INTERVAL %d DAY))
        ", $escola_id, $dias_ref));

        if (empty($rows)) return [];

        // Filtrar os que ainda têm saldo > 0 (multa/desconto já aplicados).
        $out = [];
        foreach ($rows as $r) {
            $saldo = (float)$r->valor_original
                   + (float)($r->valor_multa ?? 0)
                   - (float)($r->valor_desconto ?? 0)
                   - (float)($r->valor_desconto_especial ?? 0)
                   - (float)($r->valor_pago ?? 0);
            if ($saldo <= 0.005) continue; // margem de arredondamento
            $r->saldo = round($saldo, 2);
            $out[] = $r;
        }
        return $out;
    }
}

/**
 * Helper: dispara notificação imediata se o cenário estiver activo
 * e enviar pelo menos um canal. Respeita debounce 24h implícito.
 *
 * Chamado pelos 4 detectores de cobrança após criar o alerta.
 */
if (!function_exists('sige_alertas_auto_send_se_activo')) {
    function sige_alertas_auto_send_se_activo(int $escola_id, string $cenario, int $alerta_id): void {
        if (!sige_tenant_write_guard((int) $escola_id, 'sige_alertas_auto_send_se_activo')) { return; }
        if ($alerta_id <= 0) return;

        $cfg = sige_alertas_config_get($escola_id, $cenario);
        if ((int)$cfg['activo'] !== 1) return;

        // `sige_alerta_notificar()` existe só quando alertas-templates.php
        // está carregado (bootstrap order). Em produção está sempre presente.
        if (!function_exists('sige_alerta_notificar')) return;

        // Respeitar o canal configurado. sige_alerta_notificar() envia
        // whatsapp+email por default; precisamos de um wrapper que honre
        // o canal do cenário. Em vez de reescrever a engine, filtramos
        // o retorno - mas isso não impede o envio, só ignora contabilização.
        //
        // A forma correcta: passar o canal como argumento opcional. Mantemos
        // compatibilidade chamando directamente - canal='ambos' é o default.
        // Para whatsapp_only/email_only, usamos chamadas directas às funções
        // dual-send específicas.
        global $wpdb;
        $alerta = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sige_alertas WHERE id = %d LIMIT 1",
            $alerta_id
        ));
        if (!$alerta) return;

        $aluno = $wpdb->get_row($wpdb->prepare(
            "SELECT id, nome_completo, nome_pai, nome_mae,
                    telemovel_pai, telemovel_mae, email_pai, email_mae
             FROM {$wpdb->prefix}sige_alunos
             WHERE id = %d AND escola_id = %d
             LIMIT 1",
            (int)$alerta->aluno_id, (int)$alerta->escola_id
        ));
        if (!$aluno) return;

        $user_id = 0; // 0 = "sistema/cron" - sige_alertas_registar_envio lida com isto

        $canal = $cfg['canal'];
        if ($canal === 'whatsapp_only') {
            if (function_exists('sige_alertas_enviar_whatsapp_dual')) {
                sige_alertas_enviar_whatsapp_dual($alerta, $aluno, $user_id);
            }
        } elseif ($canal === 'email_only') {
            if (function_exists('sige_alertas_enviar_email_dual')) {
                sige_alertas_enviar_email_dual($alerta, $aluno, $user_id);
            }
        } else { // ambos
            if (function_exists('sige_alertas_enviar_whatsapp_dual')) {
                sige_alertas_enviar_whatsapp_dual($alerta, $aluno, $user_id);
            }
            if (function_exists('sige_alertas_enviar_email_dual')) {
                sige_alertas_enviar_email_dual($alerta, $aluno, $user_id);
            }
        }

        $wpdb->update(
            $wpdb->prefix . 'sige_alertas',
            ['actualizado_em' => current_time('mysql')],
            ['id' => $alerta_id],
            ['%s'],
            ['%d']
        );
    }
}

/**
 * Helper: constrói título + mensagem + metadata dos 4 cenários e cria
 * o alerta via sige_alerta_criar(). Evita duplicação de ~30 linhas × 4.
 */
if (!function_exists('sige_alertas_criar_cobranca')) {
    function sige_alertas_criar_cobranca(
        int $escola_id,
        string $tipo,
        string $severidade,
        object $row,
        string $fase_texto
    ): int {
        $moeda = function_exists('sige_moeda') ? sige_moeda() : 'MT';

        $titulo = sprintf(
            'Cobrança %s: %s - %s',
            $fase_texto,
            $row->nome_completo ?: '-',
            $row->descricao ?: 'Serviço'
        );

        $mensagem = sprintf(
            'Lançamento #%d (%s) de %s - vencimento %s. Saldo: %s %s.',
            (int)$row->id,
            $row->descricao ?: 'Serviço',
            $row->nome_completo ?: '-',
            $row->data_vencimento,
            number_format((float)$row->saldo, 2, ',', '.'),
            $moeda
        );

        return sige_alerta_criar($tipo, [
            'escola_id'      => $escola_id,
            'severidade'     => $severidade,
            'entidade_tipo'  => 'lancamento',
            'entidade_id'    => (int)$row->id,
            'aluno_id'       => (int)$row->aluno_id,
            'titulo'         => $titulo,
            'mensagem'       => $mensagem,
            'data_ancoragem' => (string)$row->data_vencimento,
            'metadata'       => [
                'lancamento_id'   => (int)$row->id,
                'aluno_id'        => (int)$row->aluno_id,
                'aluno_nome'      => $row->nome_completo,
                'descricao'       => $row->descricao,
                'mes_referencia'  => $row->mes_referencia,
                'valor'           => (float)$row->saldo,
                'moeda'           => $moeda,
                'data_vencimento' => $row->data_vencimento,
                'fase'            => $fase_texto,
            ],
        ]);
    }
}

/**
 * Detector D-5: lançamentos cujo data_vencimento = hoje + 5 dias.
 * Tom: amigável, lembrete preventivo. Severidade: baixa.
 *
 * Não dispara se o cenário estiver com activo=0 em sige_alertas_config
 * (curto-circuito no início - evita até ler a tabela de lançamentos).
 */
if (!function_exists('sige_alertas_detectar_cobranca_d_menos_5')) {
    function sige_alertas_detectar_cobranca_d_menos_5(int $escola_id): int {
        // v12.9.31 - Segurança WhatsApp: sem lembretes automáticos antes do vencimento.
        // Mantido como no-op para compatibilidade com estatísticas e chamadas existentes.
        return 0;
        $cfg = sige_alertas_config_get($escola_id, SIGE_ALERTA_COBRANCA_D_MENOS_5);
        if ((int)$cfg['activo'] !== 1) return 0;

        $rows = sige_alertas_cobranca_query_base($escola_id, -5);
        if (empty($rows)) return 0;

        $criados = 0;
        foreach ($rows as $r) {
            $id = sige_alertas_criar_cobranca(
                $escola_id,
                SIGE_ALERTA_COBRANCA_D_MENOS_5,
                'baixa',
                $r,
                '(5 dias antes do vencimento)'
            );
            if ($id > 0) {
                $criados++;
                sige_alertas_auto_send_se_activo($escola_id, SIGE_ALERTA_COBRANCA_D_MENOS_5, $id);
            }
        }
        return $criados;
    }
}

/**
 * Detector D-0: lançamentos cujo data_vencimento = hoje.
 * Tom: curto, neutro. Severidade: média.
 */
if (!function_exists('sige_alertas_detectar_cobranca_d_zero')) {
    function sige_alertas_detectar_cobranca_d_zero(int $escola_id): int {
        // v12.9.31 - Segurança WhatsApp: sem lembrete automático no dia do vencimento.
        // A cobrança deve ser accionada manualmente pela Central de Cobranças.
        return 0;
        $cfg = sige_alertas_config_get($escola_id, SIGE_ALERTA_COBRANCA_D_ZERO);
        if ((int)$cfg['activo'] !== 1) return 0;

        $rows = sige_alertas_cobranca_query_base($escola_id, 0);
        if (empty($rows)) return 0;

        $criados = 0;
        foreach ($rows as $r) {
            $id = sige_alertas_criar_cobranca(
                $escola_id,
                SIGE_ALERTA_COBRANCA_D_ZERO,
                'media',
                $r,
                '(vence hoje)'
            );
            if ($id > 0) {
                $criados++;
                sige_alertas_auto_send_se_activo($escola_id, SIGE_ALERTA_COBRANCA_D_ZERO, $id);
            }
        }
        return $criados;
    }
}

/**
 * Detector D+5: lançamentos cujo data_vencimento = hoje - 5 dias.
 * Tom: firme-amigável, reforço de urgência. Severidade: média.
 */
if (!function_exists('sige_alertas_detectar_cobranca_d_mais_5')) {
    function sige_alertas_detectar_cobranca_d_mais_5(int $escola_id): int {
        $cfg = sige_alertas_config_get($escola_id, SIGE_ALERTA_COBRANCA_D_MAIS_5);
        if ((int)$cfg['activo'] !== 1) return 0;

        $rows = sige_alertas_cobranca_query_base($escola_id, 5);
        if (empty($rows)) return 0;

        $criados = 0;
        foreach ($rows as $r) {
            $id = sige_alertas_criar_cobranca(
                $escola_id,
                SIGE_ALERTA_COBRANCA_D_MAIS_5,
                'media',
                $r,
                '(5 dias em atraso)'
            );
            if ($id > 0) {
                $criados++;
                // v12.9.31 - Segurança WhatsApp: alerta interno apenas; envio externo fica manual.
            }
        }
        return $criados;
    }
}

/**
 * Detector D+15: lançamentos cujo data_vencimento = hoje - 15 dias.
 * Tom: firme, aviso de suspensão. Severidade: alta.
 */
if (!function_exists('sige_alertas_detectar_cobranca_d_mais_15')) {
    function sige_alertas_detectar_cobranca_d_mais_15(int $escola_id): int {
        $cfg = sige_alertas_config_get($escola_id, SIGE_ALERTA_COBRANCA_D_MAIS_15);
        if ((int)$cfg['activo'] !== 1) return 0;

        $rows = sige_alertas_cobranca_query_base($escola_id, 15);
        if (empty($rows)) return 0;

        $criados = 0;
        foreach ($rows as $r) {
            $id = sige_alertas_criar_cobranca(
                $escola_id,
                SIGE_ALERTA_COBRANCA_D_MAIS_15,
                'alta',
                $r,
                '(15 dias em atraso)'
            );
            if ($id > 0) {
                $criados++;
                // v12.9.31 - Segurança WhatsApp: alerta interno apenas; envio externo fica manual.
            }
        }
        return $criados;
    }
}

/**
 * Auto-resolver dos 4 cenários de cobrança: se o lançamento passou a
 * status 'pago' (ou não existe mais), resolve automaticamente o alerta.
 *
 * Alinha com o comportamento de sige_alertas_auto_resolver_pagamentos_pagos()
 * do legado, estendendo-o para os 4 tipos novos. Retorna contagem resolvida.
 */
if (!function_exists('sige_alertas_auto_resolver_cobranca_paga')) {
    function sige_alertas_auto_resolver_cobranca_paga(int $escola_id): int {
        global $wpdb;
        $tA = sige_alertas_tbl();
        $tL = $wpdb->prefix . 'sige_fin_lancamentos';

        if (!sige_alertas_table_exists($tA) || !sige_alertas_table_exists($tL)) {
            return 0;
        }

        $tipos = [
            SIGE_ALERTA_COBRANCA_D_MENOS_5,
            SIGE_ALERTA_COBRANCA_D_ZERO,
            SIGE_ALERTA_COBRANCA_D_MAIS_5,
            SIGE_ALERTA_COBRANCA_D_MAIS_15,
        ];
        $placeholders = implode(',', array_fill(0, count($tipos), '%s'));

        // Seleccionar alertas activos cujo lançamento já está pago (ou sumiu).
        $params = array_merge([$escola_id], $tipos);
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT a.id, a.entidade_id
            FROM {$tA} a
            LEFT JOIN {$tL} l
                ON l.id = a.entidade_id AND l.escola_id = a.escola_id
            WHERE a.escola_id = %d
              AND a.tipo IN ({$placeholders})
              AND a.status = 'activo'
              AND (l.id IS NULL OR l.status = 'pago' OR l.cancelado_em IS NOT NULL)
        ", $params));

        if (empty($rows)) return 0;

        $resolvidos = 0;
        foreach ($rows as $r) {
            if (sige_alerta_resolver((int)$r->id)) {
                $resolvidos++;
            }
        }
        return $resolvidos;
    }
}

// ============================================================================
// [v14.0.0-T2] CENÁRIO 11 - Queue WhatsApp falhada >24h
// ============================================================================
// Alerta INTERNO ao Director quando há mensagens em `falhou_definitivo`
// criadas há mais de SIGE_ALERTA_QUEUE_FALHADA_HORAS (default 24h). Não
// envia WhatsApp ao encarregado (a API está justamente em baixa). Envia
// email ao Director via sige_alertas_email_director_get() com fallback
// para admin_email.
//
// Dedup: hash por (tipo + 0 + data_ancoragem=YYYY-MM-DD). Uma linha por dia
// - se continuar o problema no dia seguinte, dispara alerta novo (sinalizando
// escalada). Se hoje a queue ficou saudável, o alerta anterior resolve-se
// automaticamente no run seguinte.
// ============================================================================

if (!function_exists('sige_alertas_email_director_get')) {
    /**
     * Devolve o email do Director para o escola_id dado.
     *
     * Cadeia de fallback:
     *   1. Opção WP: sige_alertas_email_director_{escola_id}
     *   2. Opção WP: sige_alertas_email_director (global)
     *   3. get_option('admin_email') - último recurso, garante que
     *      cenário 11 NUNCA falha por configuração em falta.
     */
    function sige_alertas_email_director_get(int $escola_id): string {
        $em = (string)get_option('sige_alertas_email_director_' . $escola_id, '');
        if ($em !== '' && is_email($em)) return $em;

        $em = (string)get_option('sige_alertas_email_director', '');
        if ($em !== '' && is_email($em)) return $em;

        $em = (string)get_option('admin_email', '');
        return is_email($em) ? $em : '';
    }
}

if (!function_exists('sige_alertas_detectar_queue_wpp_falhada')) {
    function sige_alertas_detectar_queue_wpp_falhada(int $escola_id): int {
        global $wpdb;
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';

        if (!sige_alertas_table_exists($tQ)) return 0;

        $cfg = sige_alertas_config_get($escola_id, SIGE_ALERTA_QUEUE_WPP_FALHADA);
        if ((int)$cfg['activo'] !== 1) return 0;

        $horas = (int)SIGE_ALERTA_QUEUE_FALHADA_HORAS;

        // Defensiva: coluna `status` pode ter 'falhou' legado ou
        // 'falhou_definitivo' v14. Contamos os dois - ambos são sinal real
        // de problema prolongado.
        $contagem = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$tQ}
            WHERE escola_id = %d
              AND status IN ('falhou', 'falhou_definitivo')
              AND criado_em <= DATE_SUB(NOW(), INTERVAL %d HOUR)
        ", $escola_id, $horas));

        if ($contagem === 0) {
            // Saúde boa: auto-resolver alertas anteriores do tipo que
            // estejam activos. Função não chama recursivamente este
            // detector - só marca resolvido.
            $tA = sige_alertas_tbl();
            $resolve = (array)$wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$tA}
                 WHERE escola_id = %d AND tipo = %s AND status = 'activo'",
                $escola_id, SIGE_ALERTA_QUEUE_WPP_FALHADA
            ));
            foreach ($resolve as $aid) {
                sige_alerta_resolver((int)$aid);
            }
            return 0;
        }

        // data_ancoragem = dia corrente → dedup por dia.
        $hoje = wp_date('Y-m-d');
        $titulo = sprintf(
            'Queue WhatsApp com %d mensagem(s) falhada(s) há >%dh',
            $contagem, $horas
        );
        $mensagem = sprintf(
            'Há %d mensagens em status falhou_definitivo há mais de %d horas. '
            . 'Verifique a configuração da API WhatsApp e o dashboard em Sistema → Fila WhatsApp.',
            $contagem, $horas
        );

        $id = sige_alerta_criar(SIGE_ALERTA_QUEUE_WPP_FALHADA, [
            'escola_id'      => $escola_id,
            'severidade'     => 'alta',
            'entidade_tipo'  => 'queue',
            'entidade_id'    => 0,
            'aluno_id'       => null,
            'titulo'         => $titulo,
            'mensagem'       => $mensagem,
            'data_ancoragem' => $hoje,
            'metadata'       => [
                'contagem'  => $contagem,
                'horas_min' => $horas,
                'data'      => $hoje,
            ],
        ]);

        if ($id > 0) {
            // Email directo ao Director - não passa por templates de
            // encarregado porque este é alerta interno. Mantém-se contido.
            sige_alertas_notificar_queue_falhada($escola_id, $id, $contagem, $horas);
            return 1;
        }
        return 0;
    }
}

if (!function_exists('sige_alertas_notificar_queue_falhada')) {
    /**
     * Envia email ao Director a reportar queue falhada.
     * Não usa templates de encarregado (alerta interno).
     * Regista o envio em sige_alertas_envios para trilha auditável.
     */
    function sige_alertas_notificar_queue_falhada(
        int $escola_id, int $alerta_id, int $contagem, int $horas
    ): void {
        $to = sige_alertas_email_director_get($escola_id);
        if ($to === '') return; // nenhum destinatário configurado

        // Debounce 24h via sige_alertas_envios (mesma função que os outros
        // canais usam). Evita spam se o cron correr várias vezes no dia.
        if (function_exists('sige_alertas_debounce_ok')
            && !sige_alertas_debounce_ok($alerta_id, 'email', $to)) {
            return;
        }

        $escola_nome = function_exists('sige_get_escola_nome')
            ? sige_get_escola_nome($escola_id)
            : (string)get_option('blogname', 'Escola');

        $assunto = sprintf(
            '[%s] Queue WhatsApp com %d mensagens falhadas há >%dh',
            $escola_nome, $contagem, $horas
        );
        $corpo = sprintf(
            "<p>Caro(a) Director(a),</p>"
            . "<p>O sistema detectou <strong>%d mensagem(s)</strong> na queue WhatsApp "
            . "com status <code>falhou_definitivo</code> há mais de <strong>%d horas</strong>.</p>"
            . "<p>Isto sinaliza que a API WhatsApp pode estar com problema prolongado. "
            . "Verifique o dashboard em <em>Sistema → Fila WhatsApp</em> para detalhes "
            . "das mensagens e possíveis acções (forçar retry, limpar).</p>"
            . "<p>Este alerta será dispensado automaticamente assim que a queue voltar ao normal.</p>"
            . "<p>- SIGE SoftGenial</p>",
            $contagem, $horas
        );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: SIGE SoftGenial <' . ($to) . '>',
        ];
        $enviado = wp_mail($to, $assunto, $corpo, $headers);

        if (function_exists('sige_alertas_registar_envio')) {
            sige_alertas_registar_envio(
                $alerta_id,
                $escola_id,
                'email',
                $to,
                'director',
                $enviado ? 'sucesso' : 'falha',
                $enviado ? null : 'wp_mail devolveu false',
                0
            );
        }
    }
}

if (!function_exists('sige_alertas_table_exists')) {
    function sige_alertas_table_exists(string $tabela): bool {
        // Preferir helper existente (fix correcto do v13.4.2 Session 8)
        if (function_exists('sige_table_exists')) {
            return sige_table_exists($tabela);
        }
        global $wpdb;
        // Pattern correcto: comparar com nome completo (NÃO (int)$wpdb->get_var)
        return ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tabela)) === $tabela);
    }
}
