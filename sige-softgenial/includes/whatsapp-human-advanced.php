<?php
/**
 * SIGE SoftGenial - WhatsApp Modo Humano Avançado
 * Versão: 12.9.39
 *
 * Objectivo:
 * - Desbloquear gradualmente mensagens bloqueadas pela protecção anti-restrição.
 * - Evitar libertação em massa.
 * - Variar o ritmo por execução do cron central.
 * - Manter o Guardian/Recovery como autoridade final antes de cada envio.
 */

if (!defined('ABSPATH')) exit;

if (!defined('SIGE_WPP_HUMAN_ADVANCED_VERSION')) {
    define('SIGE_WPP_HUMAN_ADVANCED_VERSION', '12.9.39');
}

if (!function_exists('sige_wpp_human_mz_ts')) {
    function sige_wpp_human_mz_ts(): int {
        return current_time('timestamp');
    }
}

if (!function_exists('sige_wpp_human_mysql_at')) {
    function sige_wpp_human_mysql_at(int $ts): string {
        return wp_date('Y-m-d H:i:s', $ts, new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo'));
    }
}

if (!function_exists('sige_wpp_human_table_has_col')) {
    function sige_wpp_human_table_has_col(string $table, string $col): bool {
        global $wpdb;
        static $cache = [];
        if (!isset($cache[$table])) {
            $cache[$table] = $wpdb->get_col("SHOW COLUMNS FROM {$table}") ?: [];
        }
        return in_array($col, $cache[$table], true);
    }
}


// ============================================================================
// v12.9.39 - WhatsApp Guardrails Invisíveis
// ----------------------------------------------------------------------------
// Camada invisível de protecção. Não cria painel para a escola e não permite
// que status manuais como "forcar_envio" ignorem janela, limite diário,
// scheduled_at, cooldown por contacto ou pausa por falhas.
// ============================================================================
if (!defined('SIGE_WPP_GUARDRAILS_VERSION')) {
    define('SIGE_WPP_GUARDRAILS_VERSION', '12.9.39');
}

if (!function_exists('sige_wpp_guardrails_policy')) {
    function sige_wpp_guardrails_policy(int $escola_id = 1): array {
        /**
         * Valores deliberadamente conservadores. Podem ser ajustados depois por
         * filtros/Hub sem expor botões perigosos à escola.
         */
        $policy = [
            'enabled' => true,
            'safe_windows' => [
                ['start' => 730,  'end' => 1930],
            ],
            'min_seconds_same_phone' => 30,
            'max_per_hour' => 120,
            'max_force_per_run' => 2,
            'honour_future_schedule_for_forced' => true,
        ];
        return apply_filters('sige_wpp_invisible_guardrails_policy', $policy, $escola_id);
    }
}

if (!function_exists('sige_wpp_guardrails_in_window')) {
    function sige_wpp_guardrails_in_window(int $escola_id = 1): bool {
        $policy = sige_wpp_guardrails_policy($escola_id);
        $hm = (int) wp_date('Hi', sige_wpp_human_mz_ts(), new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo'));
        foreach (($policy['safe_windows'] ?? []) as $w) {
            if ($hm >= (int)$w['start'] && $hm <= (int)$w['end']) return true;
        }
        return false;
    }
}

if (!function_exists('sige_wpp_guardrails_recent_sent_hour')) {
    function sige_wpp_guardrails_recent_sent_hour(int $escola_id = 1): int {
        global $wpdb;
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tQ}'") !== $tQ) return 0;
        $since = sige_wpp_human_mysql_at(sige_wpp_human_mz_ts() - HOUR_IN_SECONDS);
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tQ} WHERE escola_id=%d AND status='enviado' AND enviado_em >= %s",
            $escola_id,
            $since
        ));
    }
}

if (!function_exists('sige_wpp_guardrails_same_phone_recent')) {
    function sige_wpp_guardrails_same_phone_recent(int $escola_id, string $telefone): bool {
        global $wpdb;
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tQ}'") !== $tQ) return false;
        $policy = sige_wpp_guardrails_policy($escola_id);
        $seconds = max(30, (int)($policy['min_seconds_same_phone'] ?? 30));
        $since = sige_wpp_human_mysql_at(sige_wpp_human_mz_ts() - $seconds);
        $count = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tQ} WHERE escola_id=%d AND telefone=%s AND status='enviado' AND enviado_em >= %s",
            $escola_id,
            $telefone,
            $since
        ));
        return $count > 0;
    }
}

if (!function_exists('sige_wpp_guardrails_next_safe_schedule')) {
    function sige_wpp_guardrails_next_safe_schedule(int $escola_id = 1): string {
        // Reagenda para a próxima janela humana com jitter natural.
        return sige_wpp_human_mysql_at(sige_wpp_human_next_window_ts() + rand(180, 2400));
    }
}

if (!function_exists('sige_wpp_guardrails_can_attempt')) {
    function sige_wpp_guardrails_can_attempt($q, array $args = []): array {
        $eid = (int)($q->escola_id ?? 0);
        $policy = sige_wpp_guardrails_policy($eid);
        if (empty($policy['enabled'])) return ['ok' => true, 'reason' => 'guardrails_disabled'];

        $status = (string)($q->status ?? '');
        $isForced = ($status === 'forcar_envio') || !empty($args['force']);
        $profile = function_exists('sige_wpp_human_throughput_profile') ? sige_wpp_human_throughput_profile($eid) : [];

        // v12.9.75 - Health Mode PRO é uma trava final: estado do número,
        // limite dinâmico, pausa por revisão e intervalo global entre mensagens.
        if (function_exists('sige_wpp_health_can_attempt')) {
            $health = sige_wpp_health_can_attempt($q, $args);
            if (empty($health['ok'])) {
                return ['ok' => false, 'reason' => (string)($health['reason'] ?? 'Health Mode activo')];
            }
        }

        if (!sige_wpp_guardrails_in_window($eid)) {
            return ['ok' => false, 'reason' => 'Guardrail invisível: fora da janela humana segura.'];
        }

        if ((int)($profile['remaining'] ?? 1) <= 0) {
            return ['ok' => false, 'reason' => 'Guardrail invisível: limite diário anti-restrição atingido.'];
        }

        if ((int)($profile['failures_6h'] ?? 0) >= 3) {
            return ['ok' => false, 'reason' => 'Guardrail invisível: pausa por falhas recentes.'];
        }

        $sentHour = sige_wpp_guardrails_recent_sent_hour($eid);
        $maxHour = max(3, (int)($policy['max_per_hour'] ?? 18));
        if ($sentHour >= $maxHour) {
            return ['ok' => false, 'reason' => 'Guardrail invisível: limite horário seguro atingido.'];
        }

        $tel = (string)($q->telefone ?? '');
        if ($tel !== '' && sige_wpp_guardrails_same_phone_recent($eid, $tel)) {
            return ['ok' => false, 'reason' => 'Guardrail invisível: intervalo mínimo por contacto ainda não cumprido.'];
        }

        // "forcar_envio" é prioridade operacional, mas não deve furar agendamento futuro.
        if ($isForced && !empty($policy['honour_future_schedule_for_forced']) && !empty($q->scheduled_at)) {
            $scheduledTs = strtotime((string)$q->scheduled_at);
            if ($scheduledTs && $scheduledTs > sige_wpp_human_mz_ts()) {
                return ['ok' => false, 'reason' => 'Guardrail invisível: envio forçado ainda respeita scheduled_at.'];
            }
        }

        return ['ok' => true, 'reason' => 'guardrails_ok'];
    }
}

if (!function_exists('sige_wpp_guardrails_mark_deferred')) {
    function sige_wpp_guardrails_mark_deferred($q, string $reason = ''): bool {
        global $wpdb;
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tQ}'") !== $tQ) return false;

        $hasScheduled = sige_wpp_human_table_has_col($tQ, 'scheduled_at');
        $hasNextRetry = sige_wpp_human_table_has_col($tQ, 'next_retry_at');
        $hasRisk      = sige_wpp_human_table_has_col($tQ, 'risk_flags');
        $hasErroTxt   = sige_wpp_human_table_has_col($tQ, 'ultimo_erro');
        $hasErroShort = sige_wpp_human_table_has_col($tQ, 'erro');
        $hasGuardian  = sige_wpp_human_table_has_col($tQ, 'guardian_meta');

        $eid = (int)($q->escola_id ?? 0);
        $tz = new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo');
        if (stripos($reason, 'limite diário') !== false && function_exists('sige_notify_daily_profile')) {
            $profile = sige_notify_daily_profile($eid);
            $next_ts = (int)($profile['resume_ts'] ?? 0);
            if ($next_ts <= 0) $next_ts = sige_wpp_human_mz_ts() + DAY_IN_SECONDS;
            $next = wp_date('Y-m-d H:i:s', $next_ts + rand(0, 600), $tz);
        } elseif (stripos($reason, 'janela') !== false && function_exists('sige_notify_next_window_ts')) {
            $next = wp_date('Y-m-d H:i:s', sige_notify_next_window_ts($eid) + rand(0, 300), $tz);
        } elseif (stripos($reason, 'contacto') !== false) {
            $next = wp_date('Y-m-d H:i:s', sige_wpp_human_mz_ts() + 30 + rand(0, 30), $tz);
        } elseif (stripos($reason, 'intervalo global') !== false && function_exists('sige_wpp_health_next_global_slot_ts')) {
            $next = wp_date('Y-m-d H:i:s', sige_wpp_health_next_global_slot_ts($eid, $q) + rand(0, 10), $tz);
        } else {
            $next = sige_wpp_guardrails_next_safe_schedule($eid);
        }
        $data = ['status' => 'pendente'];
        if ($hasScheduled) $data['scheduled_at'] = $next;
        if ($hasNextRetry) $data['next_retry_at'] = $next;
        if ($hasErroTxt) $data['ultimo_erro'] = substr($reason, 0, 250);
        if ($hasErroShort) $data['erro'] = 'guardrail_invisivel';
        if ($hasRisk) {
            $old = (string)($q->risk_flags ?? '');
            $flag = 'guardrail_invisivel_' . wp_date('Ymd_His', sige_wpp_human_mz_ts());
            $data['risk_flags'] = trim($old ? ($old . ',' . $flag) : $flag, ',');
        }
        if ($hasGuardian) {
            $data['guardian_meta'] = wp_json_encode([
                'version' => '12.9.39',
                'guardrail' => 'invisible',
                'reason' => $reason,
                'next_retry_at' => $next,
            ], JSON_UNESCAPED_UNICODE);
        }

        return (bool)$wpdb->update($tQ, $data, ['id' => (int)$q->id]);
    }
}

if (!function_exists('sige_wpp_human_in_send_window')) {
    function sige_wpp_human_in_send_window(): bool {
        // Janela conservadora. Evita início muito cedo e noite.
        $hm = (int) wp_date('Hi', sige_wpp_human_mz_ts(), new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo'));
        return ($hm >= 730 && $hm <= 1930);
    }
}

if (!function_exists('sige_wpp_human_next_window_ts')) {
    function sige_wpp_human_next_window_ts(): int {
        $tz = new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo');
        $now = new DateTimeImmutable('now', $tz);
        $start = $now->setTime(7, 30, 0);
        $end = $now->setTime(19, 30, 0);
        if ($now <= $start) return $start->getTimestamp() + rand(0, 600);
        if ($now <= $end) return $now->getTimestamp() + rand(30, 120);
        return $start->modify('+1 day')->getTimestamp() + rand(0, 900);
    }
}

if (!function_exists('sige_wpp_human_sent_today')) {
    function sige_wpp_human_sent_today(int $escola_id = 1): int {
        global $wpdb;
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tQ}'") !== $tQ) return 0;
        $start = wp_date('Y-m-d 00:00:00', sige_wpp_human_mz_ts(), new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo'));
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tQ} WHERE escola_id=%d AND status='enviado' AND enviado_em >= %s",
            $escola_id,
            $start
        ));
    }
}

if (!function_exists('sige_wpp_human_daily_limit')) {
    function sige_wpp_human_daily_limit(int $escola_id = 1): int {
        // v12.9.68 - a camada humana já não corta silenciosamente para 80.
        if (function_exists('sige_notify_daily_limit')) {
            return (int) sige_notify_daily_limit($escola_id);
        }
        if (function_exists('sige_wpp_get_state') && function_exists('sige_wpp_daily_limit_for_state')) {
            $state = sige_wpp_get_state($escola_id);
            return (int) sige_wpp_daily_limit_for_state($state);
        }
        return 250;
    }
}

if (!function_exists('sige_wpp_human_recent_failures')) {
    function sige_wpp_human_recent_failures(int $escola_id = 1, int $hours = 6): int {
        global $wpdb;
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tQ}'") !== $tQ) return 0;
        $hasUltTent = sige_wpp_human_table_has_col($tQ, 'ultima_tentativa_em');
        $hasErroTxt = sige_wpp_human_table_has_col($tQ, 'ultimo_erro');
        $cut = sige_wpp_human_mysql_at(sige_wpp_human_mz_ts() - max(1, $hours) * HOUR_IN_SECONDS);
        $whereTime = $hasUltTent ? " AND ultima_tentativa_em >= %s" : " AND criado_em >= %s";
        $whereErr  = $hasErroTxt ? " OR (ultimo_erro IS NOT NULL AND ultimo_erro <> '')" : "";
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tQ} WHERE escola_id=%d AND (status IN ('falhou','erro') {$whereErr}) {$whereTime}",
            $escola_id,
            $cut
        ));
    }
}

if (!function_exists('sige_wpp_human_throughput_profile')) {
    function sige_wpp_human_throughput_profile(int $escola_id = 1): array {
        $dailyLimit = sige_wpp_human_daily_limit($escola_id);
        $sentToday  = sige_wpp_human_sent_today($escola_id);
        $remaining  = max(0, $dailyLimit - $sentToday);
        $failures   = sige_wpp_human_recent_failures($escola_id, 6);
        $hm = (int) wp_date('Hi', sige_wpp_human_mz_ts(), new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo'));

        $window = 'off';
        $maxBatch = 0;
        if ($hm >= 730 && $hm < 930) {
            $window = 'warmup_manha';
            $maxBatch = 2;
        } elseif ($hm >= 930 && $hm < 1200) {
            $window = 'manha_estavel';
            $maxBatch = 4;
        } elseif ($hm >= 1400 && $hm < 1700) {
            $window = 'tarde_estavel';
            $maxBatch = 4;
        } elseif ($hm >= 1700 && $hm <= 1930) {
            $window = 'fim_tarde';
            $maxBatch = 2;
        }

        if ($remaining <= 0) $maxBatch = 0;
        if ($failures >= 5) $maxBatch = 0;         // pausa real por falhas recentes
        elseif ($failures >= 2) $maxBatch = min($maxBatch, 1); // modo cautela

        $usage = $dailyLimit > 0 ? ($sentToday / max(1, $dailyLimit)) : 1;
        if ($usage >= 0.80) $maxBatch = min($maxBatch, 1);
        elseif ($usage >= 0.55) $maxBatch = min($maxBatch, 2);

        $suggested = 0;
        if ($maxBatch > 0) {
            $min = ($maxBatch >= 3 && $usage < 0.45 && $failures === 0) ? 2 : 1;
            $suggested = rand($min, $maxBatch);
            $suggested = min($suggested, $remaining);
        }

        return [
            'daily_limit' => $dailyLimit,
            'sent_today' => $sentToday,
            'remaining' => $remaining,
            'failures_6h' => $failures,
            'window' => $window,
            'max_batch' => $maxBatch,
            'suggested_batch' => max(0, (int)$suggested),
        ];
    }
}

if (!function_exists('sige_wpp_human_batch_limit')) {
    function sige_wpp_human_batch_limit(int $requested = 5, int $escola_id = 1): int {
        // v12.9.36 - Throughput Seguro Adaptativo: varia por janela, limite diário, uso e falhas.
        $requested = max(1, (int)$requested);
        $profile = sige_wpp_human_throughput_profile($escola_id);
        $suggested = (int)($profile['suggested_batch'] ?? 0);
        if ($suggested <= 0) return 0;
        return max(1, min($requested, $suggested));
    }
}

if (!function_exists('sige_wpp_adaptive_unblock_args')) {
    function sige_wpp_adaptive_unblock_args(int $escola_id = 1): array {
        $profile = sige_wpp_human_throughput_profile($escola_id);
        $remaining = (int)($profile['remaining'] ?? 0);
        $failures = (int)($profile['failures_6h'] ?? 0);
        $window = (string)($profile['window'] ?? 'off');

        if ($window === 'off' || $remaining <= 0 || $failures >= 2) {
            return ['max_per_run' => 0, 'max_daily_release' => 0];
        }

        $perRun = min(max(1, (int)($profile['suggested_batch'] ?? 1)), rand(1, 3));
        $dailyRelease = min(24, max(6, (int)floor($remaining * 0.45)));
        return ['max_per_run' => $perRun, 'max_daily_release' => $dailyRelease];
    }
}

add_filter('sige_wpp_queue_batch_limit', function ($limit) {
    return sige_wpp_human_batch_limit((int)$limit);
}, 20);

add_filter('sige_wpp_manual_default_limit', function ($limit) {
    return sige_wpp_human_batch_limit((int)$limit);
}, 20);

if (!function_exists('sige_wpp_smart_unblock_step')) {
    function sige_wpp_smart_unblock_step(array $args = []): array {
        global $wpdb;

        $defaults = [
            'max_per_run' => rand(1, 3),
            'max_daily_release' => 18,
        ];
        $args = array_merge($defaults, $args);

        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tQ}'") !== $tQ) {
            return ['ok' => false, 'reason' => 'Tabela de fila não existe.', 'released' => 0];
        }

        $hasScheduled = sige_wpp_human_table_has_col($tQ, 'scheduled_at');
        $hasNextRetry = sige_wpp_human_table_has_col($tQ, 'next_retry_at');
        $hasRisk      = sige_wpp_human_table_has_col($tQ, 'risk_flags');
        $hasErroTxt   = sige_wpp_human_table_has_col($tQ, 'ultimo_erro');
        $hasErroShort = sige_wpp_human_table_has_col($tQ, 'erro');

        if (!sige_wpp_human_in_send_window()) {
            return ['ok' => true, 'released' => 0, 'reason' => 'Fora da janela humana de libertação.'];
        }

        $escolas = $wpdb->get_col("SELECT DISTINCT escola_id FROM {$tQ} WHERE status='bloqueado' ORDER BY escola_id ASC") ?: [];
        if (empty($escolas)) return ['ok' => true, 'released' => 0, 'reason' => 'Sem bloqueados.'];

        $released = 0;
        $details = [];
        $dayStart = wp_date('Y-m-d 00:00:00', sige_wpp_human_mz_ts(), new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo'));

        foreach ($escolas as $eidRaw) {
            $eid = max(1, (int)$eidRaw);
            $sentToday = sige_wpp_human_sent_today($eid);
            $dailyLimit = sige_wpp_human_daily_limit($eid);

            // Só liberta se ainda existir margem real no limite diário.
            if ($sentToday >= $dailyLimit) {
                $details[] = ['escola_id' => $eid, 'released' => 0, 'reason' => 'limite_diario_sem_margem'];
                continue;
            }

            $releasedToday = 0;
            if ($hasRisk) {
                $releasedToday = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tQ} WHERE escola_id=%d AND risk_flags LIKE %s AND criado_em >= %s",
                    $eid,
                    '%smart_unblock%',
                    $dayStart
                ));
            }
            if ($releasedToday >= (int)$args['max_daily_release']) {
                $details[] = ['escola_id' => $eid, 'released' => 0, 'reason' => 'limite_diario_de_desbloqueio'];
                continue;
            }

            $capacity = min(
                (int)$args['max_per_run'],
                max(0, $dailyLimit - $sentToday),
                max(0, (int)$args['max_daily_release'] - $releasedToday)
            );
            if ($capacity <= 0) continue;

            $ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$tQ} WHERE escola_id=%d AND status='bloqueado' ORDER BY id ASC LIMIT %d",
                $eid,
                $capacity
            )) ?: [];
            if (empty($ids)) continue;

            $i = 0;
            foreach ($ids as $idRaw) {
                $id = (int)$idRaw;
                $i++;
                $data = ['status' => 'pendente'];

                // Distribui libertações dentro de uma janela humana, evitando que todas fiquem elegíveis ao mesmo tempo.
                $delay = rand(8, 45) * MINUTE_IN_SECONDS + ($i * rand(120, 480));
                $ts = sige_wpp_human_mz_ts() + $delay;
                if (!sige_wpp_human_in_send_window()) $ts = sige_wpp_human_next_window_ts();
                if ($hasScheduled) $data['scheduled_at'] = sige_wpp_human_mysql_at($ts);
                if ($hasNextRetry) $data['next_retry_at'] = sige_wpp_human_mysql_at($ts);
                if ($hasErroTxt) $data['ultimo_erro'] = 'Desbloqueado gradualmente pelo modo humano avançado.';
                if ($hasErroShort) $data['erro'] = 'smart_unblock';
                if ($hasRisk) {
                    $old = (string) $wpdb->get_var($wpdb->prepare("SELECT risk_flags FROM {$tQ} WHERE id=%d", $id));
                    $flag = 'smart_unblock_' . wp_date('Ymd_His', sige_wpp_human_mz_ts());
                    $data['risk_flags'] = trim($old ? ($old . ',' . $flag) : $flag, ',');
                }

                $wpdb->update($tQ, $data, ['id' => $id]);
                $released++;
            }

            $details[] = ['escola_id' => $eid, 'released' => count($ids), 'daily_limit' => $dailyLimit, 'sent_today' => $sentToday];
        }

        return ['ok' => true, 'released' => $released, 'details' => $details];
    }
}
