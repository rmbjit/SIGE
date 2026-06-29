<?php
/**
 * SIGE SoftGenial - Cron Tasks
 * Ficheiro: includes/cron-tasks.php
 *
 * Tarefas agendadas: WhatsApp queue, alertas RH, multas, lembretes.
 * Multi-tenant: cada tarefa processa todas as escolas activas.
 *
 * @since 11.0
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// SCHEDULE PERSONALIZADO (1 minuto para WhatsApp + tarefas semanais)
// ============================================================================
add_filter('cron_schedules', function ($s) {
 if (!isset($s['sige_1min'])) {
 $s['sige_1min'] = ['interval' => 60, 'display' => 'SIGE 1min'];
 }
 if (!isset($s['sige_5min'])) {
 $s['sige_5min'] = ['interval' => 300, 'display' => 'SIGE 5min'];
 }
 if (!isset($s['sige_semanal'])) {
 $s['sige_semanal'] = ['interval' => 604800, 'display' => 'SIGE Semanal'];
 }
 return $s;
});

// ============================================================================
// AGENDAR EVENTOS
// ============================================================================
add_action('init', function () {
 // v12.9.68 - WhatsApp precisa de recuperação mais rápida: 1 mensagem por ciclo,
 // com o próprio motor a respeitar 07:30-19:30, limite conservador e intervalos de segurança.
 $wpp_schedule = wp_get_schedule('sige_processar_whatsapp_queue');
 if ($wpp_schedule !== 'sige_1min') {
     wp_clear_scheduled_hook('sige_processar_whatsapp_queue');
     wp_schedule_event(time() + 60, 'sige_1min', 'sige_processar_whatsapp_queue');
 }
 if (!wp_next_scheduled('sige_evento_diario')) {
 wp_schedule_event(time(), 'daily', 'sige_evento_diario');
 }
 if (!wp_next_scheduled('sige_conciliacao_semanal')) {
 wp_schedule_event(time(), 'sige_semanal', 'sige_conciliacao_semanal');
 }
});

// ============================================================================
// HELPER: Obter config WhatsApp por escola
// ============================================================================
if (!function_exists('sige_cron_get_wpp_config')) {
 function sige_cron_get_wpp_config(int $escola_id): ?object {
 global $wpdb;
 return $wpdb->get_row($wpdb->prepare(
 "SELECT whatsapp_url, whatsapp_token FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1",
 $escola_id
 ));
 }
}

// ============================================================================
// HELPER: Listar escolas activas (para loop de cron)
// ============================================================================
if (!function_exists('sige_cron_get_escolas')) {
 function sige_cron_get_escolas(): array {
 global $wpdb;
 $t = $wpdb->prefix . 'sige_escolas';
 if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'") !== $t) {
 return [(object)['id' => 1]];
 }
 $rows = $wpdb->get_results("SELECT id FROM {$t} WHERE activo = 1");
 return !empty($rows) ? $rows : [(object)['id' => 1]];
 }
}

// ============================================================================
// HELPERS DE PERFORMANCE/SEGURANÇA PARA CRON
// ============================================================================
if (!function_exists('sige_cron_lock_acquire')) {
 function sige_cron_lock_acquire(string $key, int $ttl = 900): bool {
 $key = 'sige_cron_lock_' . sanitize_key($key);
 if (get_transient($key)) return false;
 set_transient($key, time(), max(60, $ttl));
 return true;
 }
}

if (!function_exists('sige_cron_lock_release')) {
 function sige_cron_lock_release(string $key): void {
 delete_transient('sige_cron_lock_' . sanitize_key($key));
 }
}


// ============================================================================
// WhatsApp Queue - Token seguro para CRON central (v12.10.140)
// ----------------------------------------------------------------------------
// A chave já não é hardcoded. Cada instalação gera/guarda a sua própria chave
// em wp_options. Isto reduz o risco de todas as escolas partilharem o mesmo
// segredo operacional.
// ============================================================================
if (!function_exists('sige_wpp_queue_validate_cron_key')) {
    function sige_wpp_queue_validate_cron_key($provided): bool {
        $provided = trim((string)$provided);
        if ($provided === '') {
            $provided = trim((string)($_SERVER['HTTP_X_SIGE_CRON_KEY'] ?? ''));
        }
        if ($provided === '' && !empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Bearer\s+(.+)/i', (string)$_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $provided = trim((string)$m[1]);
        }
        if ($provided === '') return false;

        $configured = function_exists('sige_sec_get_cron_key')
            ? sige_sec_get_cron_key()
            : (string)get_option('sige_wpp_queue_cron_key', '');

        // Compatibilidade controlada: aceita constante externa definida pelo wp-config.php,
        // mas nunca aceita a chave antiga hardcoded como segredo válido.
        if (defined('SIGE_WPP_QUEUE_CRON_KEY') && SIGE_WPP_QUEUE_CRON_KEY && SIGE_WPP_QUEUE_CRON_KEY !== 'SGQ-2026-MZ-7f42c9e6d1b54a8f') {
            if (hash_equals((string)SIGE_WPP_QUEUE_CRON_KEY, $provided)) return true;
        }

        $legacy_key = (string)get_option('sige_cron_key', '');
        if ($legacy_key !== '' && $legacy_key !== 'SGQ-2026-MZ-7f42c9e6d1b54a8f' && hash_equals($legacy_key, $provided)) return true;

        return $configured !== '' && hash_equals($configured, $provided);
    }
}

// ============================================================================
// PROCESSAR WHATSAPP QUEUE - Motor Profissional v12.9.33
// ----------------------------------------------------------------------------
// Objectivo:
// - Processar a fila real sige_whatsapp_queue;
// - Respeitar scheduled_at para envios normais;
// - Aceitar status forcar_envio para recuperação operacional;
// - Actualizar status/enviado_em/tentativas/erro;
// - Expor endpoint seguro para disparo manual/controlado;
// - Manter ritmo conservador para reduzir risco de restrição do WhatsApp.
// ============================================================================
if (!function_exists('sige_mz_current_mysql')) {
    function sige_mz_current_mysql(): string {
        try {
            $tz = new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo');
            return (new DateTime('now', $tz))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return current_time('mysql');
        }
    }
}

if (!function_exists('sige_wpp_queue_table_has_col')) {
    function sige_wpp_queue_table_has_col(string $col): bool {
        global $wpdb;
        static $cache = [];
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        if (!isset($cache[$tQ])) {
            $cache[$tQ] = $wpdb->get_col("SHOW COLUMNS FROM {$tQ}") ?: [];
        }
        return in_array($col, $cache[$tQ], true);
    }
}

if (!function_exists('sige_wpp_process_queue_engine')) {
    function sige_wpp_process_queue_engine(array $args = []): array {
        global $wpdb;

        $defaults = [
            'manual' => false,
            'limit'  => 5,
            'force'  => false,
            'rescue' => true,
        ];
        $args = array_merge($defaults, $args);

        if (!function_exists('sige_wpp_post_json')) {
            return ['ok' => false, 'reason' => 'Função sige_wpp_post_json indisponível.', 'enviadas' => 0, 'falhas' => 0, 'adiadas' => 0, 'lidas' => 0];
        }

        $lock_key = 'sige_wpp_queue_lock_v12933';
        if (get_transient($lock_key)) {
            return ['ok' => true, 'locked' => true, 'reason' => 'Processamento já em curso.', 'enviadas' => 0, 'falhas' => 0, 'adiadas' => 0, 'lidas' => 0];
        }
        set_transient($lock_key, 1, 240);

        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tQ}'") !== $tQ) {
            delete_transient($lock_key);
            return ['ok' => false, 'reason' => 'Tabela de fila WhatsApp não existe.', 'enviadas' => 0, 'falhas' => 0, 'adiadas' => 0, 'lidas' => 0];
        }

        // v12.9.68 - antes da selecção, reprograma recibos/pagamentos presos
        // há muito tempo para voltarem a sair pela cadência de 30 segundos.
        $rescue_receipts = ['ok' => true, 'rescued' => 0, 'reason' => 'não executado'];
        if (!empty($args['rescue']) && function_exists('sige_notify_rescue_stuck_receipts')) {
            $rescue_receipts = sige_notify_rescue_stuck_receipts(['limit' => 120, 'older_than_minutes' => 10]);
        }

        // v12.9.35 - antes de escolher mensagens elegíveis, liberta gradualmente pequenos blocos bloqueados.
        $smart_unblock = ['ok' => true, 'released' => 0];
        if (function_exists('sige_wpp_smart_unblock_step')) {
            $unblock_args = function_exists('sige_wpp_adaptive_unblock_args')
                ? sige_wpp_adaptive_unblock_args(1)
                : ['max_per_run' => rand(1, 3), 'max_daily_release' => 18];
            $smart_unblock = ((int)($unblock_args['max_per_run'] ?? 0) > 0)
                ? sige_wpp_smart_unblock_step($unblock_args)
                : ['ok' => true, 'released' => 0, 'reason' => 'Throughput adaptativo sem margem segura para libertação.'];
        }

        $limite = max(1, min(8, (int)$args['limit']));
        if (function_exists('sige_wpp_human_batch_limit')) {
            $limite = sige_wpp_human_batch_limit($limite, 1);
        }
        // Em modo manual/cron-token, mantemos limite pequeno e variável por segurança; o operador pode chamar novamente.
        if (!empty($args['manual'])) {
            $limite = max(0, min(5, $limite));
            if (function_exists('sige_wpp_human_batch_limit')) {
                $limite = sige_wpp_human_batch_limit(max(1, $limite), 1);
            }
        }
        if ($limite <= 0) {
            delete_transient($lock_key);
            return [
                'ok' => true,
                'enviadas' => 0,
                'falhas' => 0,
                'adiadas' => 0,
                'lidas' => 0,
                'now_maputo' => sige_mz_current_mysql(),
                'smart_unblock' => $smart_unblock ?? null,
                'rescue_receipts' => $rescue_receipts ?? null,
                'throughput' => function_exists('sige_wpp_human_throughput_profile') ? sige_wpp_human_throughput_profile(1) : null,
                'message' => 'Throughput adaptativo sem margem segura para envio neste momento.'
            ];
        }

        $hasScheduled = sige_wpp_queue_table_has_col('scheduled_at');
        $hasPriority  = sige_wpp_queue_table_has_col('priority');
        $hasNextRetry = sige_wpp_queue_table_has_col('next_retry_at');
        $hasUltTent   = sige_wpp_queue_table_has_col('ultima_tentativa_em');
        $hasRisk      = sige_wpp_queue_table_has_col('risk_flags');
        $hasGuardian  = sige_wpp_queue_table_has_col('guardian_meta');
        $hasErroTxt   = sige_wpp_queue_table_has_col('ultimo_erro');
        $hasErroShort = sige_wpp_queue_table_has_col('erro');
        $hasAlunoId   = sige_wpp_queue_table_has_col('aluno_id');

        $now = sige_mz_current_mysql();
        $order = $hasPriority ? "priority ASC, id ASC" : "id ASC";

        // Status aceites:
        // - pendente: respeita scheduled_at/next_retry_at;
        // - forcar_envio: recuperação manual, ignora scheduled_at mas continua em lote pequeno.
        $extra_cols = [];
        if ($hasScheduled) $extra_cols[] = 'scheduled_at';
        if ($hasNextRetry) $extra_cols[] = 'next_retry_at';
        if ($hasRisk) $extra_cols[] = 'risk_flags';
        if ($hasGuardian) $extra_cols[] = 'guardian_meta';
        if ($hasAlunoId)  $extra_cols[] = 'aluno_id';
        $cols = "id, escola_id, telefone, mensagem, tipo, tentativas, status" . (!empty($extra_cols) ? ', ' . implode(', ', $extra_cols) : '');
        $whereRetry = $hasNextRetry ? " AND (next_retry_at IS NULL OR next_retry_at <= %s)" : "";
        if ($hasScheduled && $hasNextRetry) {
            $sql = "SELECT {$cols} FROM {$tQ}
                    WHERE tentativas < 3
                      AND (
                        status = 'forcar_envio'
                        OR (status = 'pendente' AND (scheduled_at IS NULL OR scheduled_at <= %s) {$whereRetry})
                      )
                    ORDER BY {$order}
                    LIMIT %d";
            $pendentes = $wpdb->get_results($wpdb->prepare($sql, $now, $now, $limite));
        } elseif ($hasScheduled) {
            $sql = "SELECT {$cols} FROM {$tQ}
                    WHERE tentativas < 3
                      AND (status = 'forcar_envio' OR (status = 'pendente' AND (scheduled_at IS NULL OR scheduled_at <= %s)))
                    ORDER BY {$order}
                    LIMIT %d";
            $pendentes = $wpdb->get_results($wpdb->prepare($sql, $now, $limite));
        } else {
            $sql = "SELECT {$cols} FROM {$tQ}
                    WHERE tentativas < 3 AND status IN ('pendente','forcar_envio')
                    ORDER BY {$order}
                    LIMIT %d";
            $pendentes = $wpdb->get_results($wpdb->prepare($sql, $limite));
        }

        if (empty($pendentes)) {
            delete_transient($lock_key);
            return ['ok' => true, 'enviadas' => 0, 'falhas' => 0, 'adiadas' => 0, 'lidas' => 0, 'now' => $now, 'smart_unblock' => $smart_unblock ?? null, 'rescue_receipts' => $rescue_receipts ?? null, 'throughput' => function_exists('sige_wpp_human_throughput_profile') ? sige_wpp_human_throughput_profile(1) : null, 'message' => 'Sem mensagens elegíveis para envio neste momento.'];
        }

        $cfg_cache = [];
        $enviadas = 0;
        $falhas = 0;
        $adiadas = 0;
        $detalhes = [];

        foreach ($pendentes as $q) {
            $eid = (int)($q->escola_id ?? 0);
            $is_forced = ((string)($q->status ?? '') === 'forcar_envio') || !empty($args['force']);

            // v12.9.39 - Guardrails Invisíveis: "forcar_envio" é prioridade, nunca bypass.
            if (function_exists('sige_wpp_guardrails_can_attempt')) {
                $guard = sige_wpp_guardrails_can_attempt($q, $args);
                if (empty($guard['ok'])) {
                    $adiadas++;
                    $reason = substr((string)($guard['reason'] ?? 'Guardrail invisível activo'), 0, 240);
                    if (function_exists('sige_wpp_guardrails_mark_deferred')) {
                        sige_wpp_guardrails_mark_deferred($q, $reason);
                    } else {
                        $data = [];
                        if ($hasErroShort) $data['erro'] = 'guardrail_invisivel';
                        if ($hasErroTxt) $data['ultimo_erro'] = $reason;
                        if ($hasScheduled) {
                            $data['scheduled_at'] = wp_date('Y-m-d H:i:s', current_time('timestamp') + rand(1800, 7200), new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo'));
                        }
                        $data['status'] = 'pendente';
                        $wpdb->update($tQ, $data, ['id' => (int)$q->id]);
                    }
                    $detalhes[] = ['id' => (int)$q->id, 'status' => 'adiado', 'reason' => $reason, 'guardrail' => 'invisivel'];
                    continue;
                }
            }

            // Guardian Pro continua activo para segurança, mas o envio forçado não deve ficar preso por scheduled_at.
            if (function_exists('sige_wpp_can_send_queue_item')) {
                $permit = sige_wpp_can_send_queue_item($q);
                if (empty($permit['ok'])) {
                    $adiadas++;
                    $reason = substr((string)($permit['reason'] ?? 'adiado pelo Guardian'), 0, 240);
                    $data = [];
                    if ($hasErroShort) $data['erro'] = $reason;
                    if ($hasErroTxt)   $data['ultimo_erro'] = $reason;
                    if ($hasScheduled) {
                        $tz = new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo');
                        if (stripos($reason, 'janela') !== false && function_exists('sige_notify_next_window_ts')) {
                            $next_ts = sige_notify_next_window_ts($eid) + rand(0, 300);
                        } elseif (stripos($reason, 'diário') !== false && function_exists('sige_notify_daily_profile')) {
                            $profile = sige_notify_daily_profile($eid);
                            $next_ts = (int)($profile['resume_ts'] ?? 0);
                            if ($next_ts <= 0) $next_ts = current_time('timestamp') + DAY_IN_SECONDS;
                            $next_ts += rand(0, 600);
                        } elseif (stripos($reason, 'links') !== false) {
                            $next_ts = current_time('timestamp') + DAY_IN_SECONDS + rand(600, 2400);
                        } else {
                            $next_ts = current_time('timestamp') + rand(1200, 5400);
                        }
                        $data['scheduled_at'] = wp_date('Y-m-d H:i:s', $next_ts, $tz);
                    }
                    // Se era forçado e foi adiado por segurança, volta a pendente para não ficar preso num estado especial.
                    if ($is_forced) $data['status'] = 'pendente';
                    if ($data) $wpdb->update($tQ, $data, ['id' => (int)$q->id]);
                    $detalhes[] = ['id' => (int)$q->id, 'status' => 'adiado', 'reason' => $reason];
                    continue;
                }
            }

            if (!isset($cfg_cache[$eid])) {
                $cfg_cache[$eid] = sige_cron_get_wpp_config($eid);
            }
            $cfg = $cfg_cache[$eid];
            if (!$cfg || empty($cfg->whatsapp_url)) {
                $falhas++;
                $erro = 'Configuração Z-API ausente para escola_id=' . $eid;
                $update = ['tentativas' => ((int)$q->tentativas + 1), 'status' => 'pendente'];
                if ($hasErroShort) $update['erro'] = $erro;
                if ($hasErroTxt) $update['ultimo_erro'] = $erro;
                if ($hasUltTent) $update['ultima_tentativa_em'] = $now;
                if ($hasNextRetry) $update['next_retry_at'] = wp_date('Y-m-d H:i:s', current_time('timestamp') + HOUR_IN_SECONDS, new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo'));
                $wpdb->update($tQ, $update, ['id' => (int)$q->id]);
                $detalhes[] = ['id' => (int)$q->id, 'status' => 'falha', 'reason' => $erro];
                continue;
            }

            $token = function_exists('sige_decrypt_token') ? sige_decrypt_token($cfg->whatsapp_token) : $cfg->whatsapp_token;
            $telefone = function_exists('sige_wpp_normalizar_numero') ? sige_wpp_normalizar_numero($q->telefone) : preg_replace('/[^0-9]/', '', (string)$q->telefone);

            // v12.9.54 - Guard final no processador: bloqueia mensagens com
            // padrão de template antigo. Linha de defesa última, mesmo que
            // a migração não tenha apanhado este item.
            if (function_exists('sige_wpp_tpl_tem_padrao_legado')
                && sige_wpp_tpl_tem_padrao_legado((string)$q->mensagem)) {
                $upd_kill = [
                    'status' => 'cancelado',
                ];
                if ($hasErroShort) $upd_kill['erro'] = 'Cancelada por segurança: modelo de mensagem desactualizado detectado no processador.';
                if ($hasErroTxt)   $upd_kill['ultimo_erro'] = 'v12.9.54: cancelada - padrão legado detectado pré-envio';
                if ($hasGuardian) {
                    $upd_kill['guardian_meta'] = wp_json_encode([
                        'cancelled_at' => $now,
                        'tpl_version'  => defined('SIGE_WPP_TPL_VERSION') ? SIGE_WPP_TPL_VERSION : 'unknown',
                        'origem'       => 'cron_guard_v54',
                        'motivo'       => 'padrão legado pré-envio',
                    ], JSON_UNESCAPED_UNICODE);
                }
                $wpdb->update($tQ, $upd_kill, ['id' => (int)$q->id]);
                if (function_exists('sige_fin_log')) {
                    sige_fin_log('wpp_envio_legado_cancelado', [
                        'queue_id'  => (int)$q->id,
                        'aluno_id'  => (int)$q->aluno_id,
                        'tipo'      => (string)$q->tipo,
                        'telefone'  => $telefone,
                    ]);
                }
                $detalhes[] = ['id' => (int)$q->id, 'status' => 'cancelado', 'reason' => 'padrão legado'];
                continue;
            }

            $res = sige_wpp_post_json($cfg->whatsapp_url, [
                'phone' => $telefone,
                'message' => (string)$q->mensagem,
            ], $token);

            if (!empty($res['ok'])) {
                $update = [
                    'status' => 'enviado',
                    'enviado_em' => $now,
                ];
                if ($hasErroShort) $update['erro'] = null;
                if ($hasErroTxt) $update['ultimo_erro'] = null;
                if ($hasUltTent) $update['ultima_tentativa_em'] = $now;
                if ($hasGuardian) {
                    $meta = ['version' => defined('SIGE_WPP_GUARDRAILS_PRO_VERSION') ? SIGE_WPP_GUARDRAILS_PRO_VERSION : '12.9.39', 'engine' => 'professional_queue_guardrails_pro', 'sent_at' => $now, 'manual' => !empty($args['manual'])];
                    $update['guardian_meta'] = wp_json_encode($meta, JSON_UNESCAPED_UNICODE);
                }
                $wpdb->update($tQ, $update, ['id' => (int)$q->id]);
                $enviadas++;
                if (function_exists('sige_wpp_guardian_mark_sent')) sige_wpp_guardian_mark_sent($q);
                $detalhes[] = ['id' => (int)$q->id, 'status' => 'enviado'];

                // Pausa curta intra-lote. O espaçamento principal continua em scheduled_at.
                usleep(rand(500000, 1600000));
            } else {
                $falhas++;
                $novas_tent = (int)$q->tentativas + 1;
                $novo_status = $novas_tent >= 3 ? 'falhou' : 'pendente';
                $erro_body = is_string($res['body'] ?? null) ? substr($res['body'], 0, 220) : '';
                $erro = substr('HTTP ' . ($res['http_code'] ?? 0) . ': ' . (($res['error'] ?? '') ?: $erro_body), 0, 250);

                $update = [
                    'tentativas' => $novas_tent,
                    'status' => $novo_status,
                ];
                if ($hasErroShort) $update['erro'] = $erro;
                if ($hasErroTxt) $update['ultimo_erro'] = $erro;
                if ($hasUltTent) $update['ultima_tentativa_em'] = $now;
                if ($hasScheduled && $novo_status === 'pendente') {
                    $update['scheduled_at'] = wp_date('Y-m-d H:i:s', current_time('timestamp') + (45 * MINUTE_IN_SECONDS) + rand(300, 1800), new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo'));
                }
                if ($hasNextRetry && $novo_status === 'pendente') {
                    $update['next_retry_at'] = $update['scheduled_at'] ?? wp_date('Y-m-d H:i:s', current_time('timestamp') + HOUR_IN_SECONDS, new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo'));
                }
                if ($hasRisk) {
                    $existing = (string)($q->risk_flags ?? '');
                    $update['risk_flags'] = trim($existing . ' wpp_engine_retry');
                }
                $wpdb->update($tQ, $update, ['id' => (int)$q->id]);
                if (function_exists('sige_wpp_guardrails_pro_after_send_failure')) {
                    sige_wpp_guardrails_pro_after_send_failure($eid, $erro, is_array($res) ? $res : []);
                }
                if (function_exists('sige_wpp_guardian_mark_failed')) sige_wpp_guardian_mark_failed($q, $erro);
                $detalhes[] = ['id' => (int)$q->id, 'status' => $novo_status, 'reason' => $erro];
            }
        }

        if (function_exists('sige_fin_log') && ($enviadas + $falhas + $adiadas) > 0) {
            sige_fin_log('wpp_engine_v12939_guardrails_processado', [
                'enviadas' => $enviadas,
                'falhas' => $falhas,
                'adiadas' => $adiadas,
                'lidas' => count($pendentes),
                'manual' => !empty($args['manual']),
            ]);
        }

        delete_transient($lock_key);
        return [
            'ok' => true,
            'enviadas' => $enviadas,
            'falhas' => $falhas,
            'adiadas' => $adiadas,
            'lidas' => count($pendentes),
            'now_maputo' => $now,
            'smart_unblock' => $smart_unblock ?? null,
            'rescue_receipts' => $rescue_receipts ?? null,
            'throughput' => function_exists('sige_wpp_human_throughput_profile') ? sige_wpp_human_throughput_profile(1) : null,
            'detalhes' => $detalhes,
        ];
    }
}

add_action('sige_processar_whatsapp_queue', function () {
    sige_wpp_process_queue_engine(['manual' => false, 'limit' => (int) apply_filters('sige_wpp_queue_batch_limit', 5)]);
});

// Endpoint seguro para teste/processamento manual pelo administrador.
// Exemplo: /wp-json/sige/v1/process-queue com header X-SIGE-Cron-Key
add_action('rest_api_init', function () {
    register_rest_route('sige/v1', '/process-queue', [
        'methods' => ['GET', 'POST'],
        'permission_callback' => function (WP_REST_Request $request) {
            if (function_exists('sige_sec_rate_limit') && !sige_sec_rate_limit('process_queue_endpoint', 30, 300)) {
                return new WP_Error('sige_rate_limited', 'Demasiadas tentativas. Tente mais tarde.', ['status' => 429]);
            }
            $key = function_exists('sige_sec_cron_key_from_request') ? sige_sec_cron_key_from_request($request) : (string)($request->get_param('key') ?: '');
            if (function_exists('sige_wpp_queue_validate_cron_key') && sige_wpp_queue_validate_cron_key($key)) {
                return true;
            }
            $is_real_admin = function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'));
            $manual_ok = function_exists('sige_user_can_any_secure')
                ? sige_user_can_any_secure(['comunicacao.enviar'], ['sige_admin_ti','sige_admin','sige_financeiro','sige_secretario'])
                : (current_user_can('sige_admin') || current_user_can('sige_financeiro') || current_user_can('sige_secretario'));
            if (!$is_real_admin && !$manual_ok && function_exists('sige_security_log')) {
                sige_security_log('queue_rest_permission_denied', 'Tentativa sem chave cron válida.');
            }
            return $is_real_admin || $manual_ok;
        },
        'callback' => function (WP_REST_Request $request) {
            $limit = (int)($request->get_param('limit') ?: apply_filters('sige_wpp_manual_default_limit', 5));
            $force = (int)($request->get_param('force') ?: 0) === 1;
            $result = sige_wpp_process_queue_engine(['manual' => true, 'limit' => $limit, 'force' => $force, 'rescue' => true]);
            return rest_ensure_response($result);
        },
    ]);
});

// Fallback admin-post para ambientes onde REST com cookie/nonce não passa.
// Exemplo com sessão: /wp-admin/admin-post.php?action=sige_process_queue_now
// Exemplo cron central: /wp-admin/admin-post.php?action=sige_process_queue_now com header X-SIGE-Cron-Key
if (!function_exists('sige_wpp_handle_process_queue_now')) {
    function sige_wpp_handle_process_queue_now() {
        if (function_exists('sige_sec_rate_limit') && !sige_sec_rate_limit('process_queue_admin_post', 30, 300)) {
            wp_send_json(['ok' => false, 'reason' => 'Demasiadas tentativas. Tente mais tarde.', 'status' => 429], 429);
        }
        $key = function_exists('sige_sec_cron_key_from_request') ? sige_sec_cron_key_from_request(null) : (isset($_GET['key']) ? sanitize_text_field((string)$_GET['key']) : '');
        $has_valid_key = function_exists('sige_wpp_queue_validate_cron_key') && sige_wpp_queue_validate_cron_key($key);

        // Sem sessão WordPress, só uma chave válida pode processar a fila.
        // Com sessão, mantém permissões antigas para teste manual por administradores/secretaria/financeiro.
        $is_real_admin = function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'));
        $manual_ok = function_exists('sige_user_can_any_secure')
            ? sige_user_can_any_secure(['comunicacao.enviar'], ['sige_admin_ti','sige_admin','sige_financeiro','sige_secretario'])
            : (current_user_can('sige_admin') || current_user_can('sige_financeiro') || current_user_can('sige_secretario'));
        if (!$has_valid_key && !$is_real_admin && !$manual_ok) {
            if (function_exists('sige_security_log')) sige_security_log('queue_admin_post_permission_denied', 'Tentativa sem sessão autorizada/chave cron válida.');
            wp_send_json(['ok' => false, 'reason' => 'Sem permissão ou chave cron inválida.', 'status' => 403], 403);
        }

        $limit = isset($_GET['limit']) ? max(1, min(5, (int)$_GET['limit'])) : (int) apply_filters('sige_wpp_manual_default_limit', 5);
        $result = sige_wpp_process_queue_engine(['manual' => true, 'limit' => $limit, 'rescue' => true]);
        wp_send_json($result);
    }
}
add_action('admin_post_sige_process_queue_now', 'sige_wpp_handle_process_queue_now');
add_action('admin_post_nopriv_sige_process_queue_now', 'sige_wpp_handle_process_queue_now');

// ============================================================================
// ALERTA RH: VENCIMENTOS DE CONTRATO (por escola)
// ============================================================================
add_action('sige_evento_diario', 'sige_verificar_vencimentos_contratos');
if (!function_exists('sige_verificar_vencimentos_contratos')) {
 function sige_verificar_vencimentos_contratos() {
 if (!sige_cron_lock_acquire('vencimentos_contratos', 900)) return;
 global $wpdb;

 $data_alvo = wp_date('Y-m-d', strtotime('+30 days'));
 $tabela = $wpdb->prefix . 'sige_professores';

 if ($wpdb->get_var("SHOW TABLES LIKE '$tabela'") != $tabela) { sige_cron_lock_release('vencimentos_contratos'); return; }

 foreach (sige_cron_get_escolas() as $escola) {
 $eid = (int)$escola->id;

 $funcionarios = $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM $tabela WHERE fim_contrato = %s AND status_ativo = 1 AND escola_id = %d",
 $data_alvo, $eid
 ));

 if (empty($funcionarios)) continue;

 $cfg = $wpdb->get_row($wpdb->prepare(
 "SELECT email_institucional FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1",
 $eid
 ));
 $email_destino = (!empty($cfg->email_institucional)) ? $cfg->email_institucional : get_option('admin_email');

 foreach ($funcionarios as $f) {
                $assunto = "⚠️ ALERTA RH: Fim de Contrato - " . $f->nome_completo;
                $mensagem = "O contrato do funcionário " . $f->nome_completo
 . " vence a " . wp_date('d/m/Y', strtotime($f->fim_contrato));
                wp_mail($email_destino, $assunto, $mensagem);
 }
 }
 sige_cron_lock_release('vencimentos_contratos');
 }
}

// ============================================================================
// RECALCULAR MULTAS DIARIAMENTE (por escola)
// ============================================================================
add_action('sige_evento_diario', 'sige_recalcular_multas_diario');
if (!function_exists('sige_recalcular_multas_diario')) {
 function sige_recalcular_multas_diario() {
 if (!function_exists('sige_fin_recalcular_lancamento')) return;
 if (!sige_cron_lock_acquire('recalcular_multas', 1800)) return;

 global $wpdb;
 $tL = $wpdb->prefix . 'sige_fin_lancamentos';

 foreach (sige_cron_get_escolas() as $escola) {
 $ids = $wpdb->get_col($wpdb->prepare(
 "SELECT id FROM $tL WHERE status IN ('pendente','parcial') AND escola_id = %d ORDER BY id ASC LIMIT 500",
 (int)$escola->id
 ));

 if (empty($ids)) continue;

 foreach ($ids as $lid) {
 sige_fin_recalcular_e_sync((int)$lid);
 }
 }
 sige_cron_lock_release('recalcular_multas');
 }
}

// ============================================================================
// LEMBRETES PRÉ-VENCIMENTO (por escola)
// ============================================================================
// v12.9.31 - Segurança WhatsApp: lembretes automáticos pré-vencimento desactivados.
// A cobrança deve partir da Central de Cobranças, com selecção humana e ritmo controlado.
// add_action('sige_evento_diario', 'sige_enviar_lembretes_vencimento');
if (!function_exists('sige_enviar_lembretes_vencimento')) {
 function sige_enviar_lembretes_vencimento() {
 // v12.9.31 - Segurança WhatsApp: função mantida por compatibilidade,
 // mas não gera nem envia lembretes automáticos antes do vencimento.
 if (function_exists('sige_fin_log')) {
     sige_fin_log('wpp_lembretes_pre_vencimento_desactivados', [
         'motivo' => 'Envios automáticos pré-vencimento removidos para reduzir risco operacional WhatsApp',
         'timestamp' => current_time('mysql'),
     ]);
 }
 return;
 if (!function_exists('sige_fin_queue_whatsapp')) return;
 if (!function_exists('sige_fin_get_config')) return;
 if (!sige_cron_lock_acquire('lembretes_vencimento', 1800)) return;

 global $wpdb;
 $tL = $wpdb->prefix . 'sige_fin_lancamentos';
 $tA = $wpdb->prefix . 'sige_alunos';

 foreach (sige_cron_get_escolas() as $escola) {
 $eid = (int)$escola->id;

 $ano = function_exists('sige_fin_get_ano_letivo_master')
 ? sige_fin_get_ano_letivo_master()
 : (int)wp_date('Y');
 $config = sige_fin_get_config($ano);

 if (empty($config) || (int)($config->enviar_lembrete_vencimento ?? 0) !== 1) continue;

 $canal_lembrete_auto = 'both';
 if (!in_array($canal_lembrete_auto, ['both','whatsapp','email'], true)) {
     $canal_lembrete_auto = 'both';
 }

 $dias_antecedencia = max(1, (int)($config->dias_antecedencia_lembrete ?? 3));
 $data_alvo = wp_date('Y-m-d', strtotime("+{$dias_antecedencia} days"));

 $lancamentos = $wpdb->get_results($wpdb->prepare("
 SELECT l.*, a.nome_completo, a.whatsapp_notificacoes, a.telemovel_pai, a.telemovel_mae,
 a.contacto_encarregado, a.nome_pai, a.nome_mae
 FROM $tL l
 JOIN $tA a ON a.id = l.aluno_id
 WHERE l.status IN ('pendente','parcial')
 AND l.data_vencimento = %s
 AND l.escola_id = %d
 ORDER BY l.aluno_id ASC
 ", $data_alvo, $eid));

 if (empty($lancamentos)) continue;

 $por_aluno = [];
 foreach ($lancamentos as $l) {
 $por_aluno[(int)$l->aluno_id][] = $l;
 }

 $cfg_wpp = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1",
 $eid
 ));

 $moeda = function_exists('sige_moeda') ? sige_moeda() : 'MT';

 foreach ($por_aluno as $aluno_id => $lancs) {
 $primeiro = $lancs[0];
 $tel_raw = $primeiro->whatsapp_notificacoes ?: ($primeiro->telemovel_pai ?: ($primeiro->telemovel_mae ?? ''));
 $tel_num = function_exists('sige_telefone_normalizar')
 ? sige_telefone_normalizar($tel_raw)
 : preg_replace('/[^0-9]/', '', (string)$tel_raw);

 $detalhes = [];
 $total = 0.0;
 foreach ($lancs as $ll) {
 $restante = max(0, (float)$ll->valor_original + (float)($ll->valor_multa ?? 0)
 - (float)($ll->valor_desconto ?? 0) - (float)($ll->valor_desconto_especial ?? 0)
 - (float)($ll->valor_pago ?? 0));
 if ($restante <= 0) continue;
 $detalhes[] = "• " . ($ll->descricao ?: 'Serviço') . " - " . number_format($restante, 2, ',', '.') . " " . $moeda;
 $total += $restante;
 }
 if (empty($detalhes)) continue;

 $detalhes_str = implode("\n", $detalhes);

 if (function_exists('sige_wpp_render_finance_template') && $cfg_wpp) {
                    $msg = sige_wpp_render_finance_template('cobranca', $cfg_wpp, $primeiro, [
 'valor' => $total,
 'vencimento' => $data_alvo,
 'detalhes' => $detalhes_str,
 'servico' => $detalhes_str,
 'mes' => wp_date('m/Y', strtotime($data_alvo)),
 'id' => (string)$aluno_id,
 ]);
 } else {
 $escola_nome = ($cfg_wpp->nome_escola ?? '');
 $primeiro_nome = explode(' ', trim((string)$primeiro->nome_completo))[0];
 $venc_fmt = wp_date('d/m/Y', strtotime($data_alvo));
                    $msg = "🔔 *Lembrete de Pagamento*" . ($escola_nome ? " - {$escola_nome}" : "") . "\n\n"
 . "Estimado Encarregado de Educação,\nOs seguintes serviços escolares de {$primeiro_nome} vencem em {$venc_fmt}:\n\n"
 . "Serviços:\n" . $detalhes_str . "\n\n"
 . " *Total:* " . number_format($total, 2, ',', '.') . " " . $moeda . "\n\n"
                         . "Efectue o pagamento atempadamente para evitar encargos adicionais.\nObrigado! ";
 }

 if (($canal_lembrete_auto === 'both' || $canal_lembrete_auto === 'whatsapp') && $tel_num) {
 sige_fin_queue_whatsapp($aluno_id, $tel_num, 'lembrete', $msg);
 }

 if (($canal_lembrete_auto === 'both' || $canal_lembrete_auto === 'email') && function_exists('sige_enviar_email_financeiro')) {
 sige_enviar_email_financeiro((int)$aluno_id, 'lembrete', $detalhes_str, (float)$total, $data_alvo);
 }
 }

 if (function_exists('sige_fin_log')) {
 sige_fin_log('lembretes_enviados', [
 'escola_id' => $eid,
 'data_alvo' => $data_alvo,
 'dias_antecedencia'=> $dias_antecedencia,
 'alunos' => count($por_aluno),
 ]);
 }
 }
 sige_cron_lock_release('lembretes_vencimento');
 }
}

// ============================================================================
// REPARADOR DB - (v11.0) Migrado para class-sige-migration.php
// Hook mantido como no-op para compatibilidade.
// ============================================================================
add_action('sige_db_upgrade_extra', function () { return; });

// ============================================================================
// [B13] CONCILIAÇÃO SEMANAL AUTOMATIZADA
// ============================================================================
// Verifica divergências entre valor_pago nos lançamentos e soma real dos
// pagamentos. Gera alerta por email ao admin e regista log se divergência > 0.
// A VIEW {prefix}sige_v_reconciliacao já existe na BD.
// ============================================================================
add_action('sige_conciliacao_semanal', 'sige_cron_conciliacao_semanal');

function sige_cron_conciliacao_semanal(): void {
    if (!sige_cron_lock_acquire('conciliacao_semanal', 1800)) return;
    global $wpdb;

    $escolas = sige_cron_get_escolas();
    $tV = $wpdb->prefix . 'sige_v_reconciliacao';

    // Verificar se a view existe
    if ($wpdb->get_var("SHOW TABLES LIKE '$tV'") !== $tV) { sige_cron_lock_release('conciliacao_semanal'); return; }

    foreach ($escolas as $escola) {
        $eid = (int) $escola->id;

        // Usar a VIEW de conciliação com filtro escola_id
        $divergencias = $wpdb->get_results($wpdb->prepare(
            "SELECT lancamento_id, aluno_id, descricao, vp_lancamento, vp_pagamentos, divergencia, mes_referencia
             FROM `$tV`
             WHERE escola_id = %d
             ORDER BY divergencia DESC
             LIMIT 50",
            $eid
        ));

        $total_diverg = count($divergencias);

        if ($total_diverg === 0) {
            // Tudo OK - log silencioso
            sige_fin_log('conciliacao_ok', ['escola_id' => $eid, 'data' => wp_date('Y-m-d')]);
            continue;
        }

        // Há divergências - registar log com detalhes
        $soma_divergencia = 0.0;
        $ids_afectados = [];
        foreach ($divergencias as $d) {
            $soma_divergencia += (float) $d->divergencia;
            $ids_afectados[] = (int) $d->lancamento_id;
        }

        sige_fin_log('conciliacao_divergencia', [
            'escola_id'       => $eid,
            'data'            => wp_date('Y-m-d'),
            'total_registos'  => $total_diverg,
            'soma_divergencia'=> round($soma_divergencia, 2),
            'lancamentos'     => array_slice($ids_afectados, 0, 20),
        ]);

        // Enviar email de alerta ao admin
        $admin_email = get_option('admin_email');
        if ($admin_email) {
            $moeda = function_exists('sige_moeda') ? sige_moeda() : 'MT';
            $assunto = 'SIGE Alerta: ' . $total_diverg . ' divergência(s) na conciliação financeira';
            $corpo = "Conciliação semanal automática - " . wp_date('d/m/Y') . "\n\n"
                   . "Foram detectadas {$total_diverg} divergência(s) entre valor_pago dos lançamentos e a soma real dos pagamentos.\n"
                   . "Divergência total: " . number_format($soma_divergencia, 2, ',', '.') . " {$moeda}\n\n"
                   . "Primeiros registos:\n";
            foreach (array_slice($divergencias, 0, 10) as $d) {
                $corpo .= "  - Lançamento #{$d->lancamento_id}: "
                        . number_format((float)$d->vp_lancamento, 2, ',', '.') . " vs "
                        . number_format((float)$d->vp_pagamentos, 2, ',', '.') . " ("
                        . number_format((float)$d->divergencia, 2, ',', '.') . " {$moeda})\n";
            }
            $corpo .= "\nVerifique no painel Tesouraria > Dashboard.\n";
            wp_mail($admin_email, $assunto, $corpo);
        }

        // Auto-correcção: actualizar valor_pago nos lançamentos divergentes
        foreach ($divergencias as $d) {
            $wpdb->update(
                $wpdb->prefix . 'sige_fin_lancamentos',
                ['valor_pago' => (float) $d->vp_pagamentos],
                ['id' => (int) $d->lancamento_id]
            );
            // Recalcular status após correcção
            if (function_exists('sige_fin_recalcular_e_sync')) {
                sige_fin_recalcular_e_sync((int) $d->lancamento_id);
            }
        }

        sige_fin_log('conciliacao_autocorrecao', [
            'escola_id'       => $eid,
            'registos_corrigidos' => $total_diverg,
            'soma_corrigida'  => round($soma_divergencia, 2),
        ]);
    }
    sige_cron_lock_release('conciliacao_semanal');
}
