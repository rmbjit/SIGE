<?php
/**
 * SIGE SoftGenial - WhatsApp Guardrails PRO
 * Ficheiro: includes/whatsapp-guardrails-pro.php
 *
 * v12.10.136 - camada conservadora anti-ban:
 * - o clique apenas agenda; o cron envia depois;
 * - mínimo global de 2 minutos entre envios;
 * - espaçamento maior para cobranças e links;
 * - limite horário/dia mais prudente;
 * - bloqueio silencioso de duplicados/cobranças repetidas;
 * - pausa automática perante sinais críticos da API/Z-API/WhatsApp.
 *
 * Não altera fórmulas financeiras, pagamentos, multas, notas, Pauta, DEC, ACTA,
 * boletins, Jardim, Portal ou regras de negócio validadas.
 */
if (!defined('ABSPATH')) exit;

if (!defined('SIGE_WPP_GUARDRAILS_PRO_VERSION')) {
    define('SIGE_WPP_GUARDRAILS_PRO_VERSION', '12.10.136');
}
if (!defined('SIGE_WPP_PRO_DAILY_LIMIT')) {
    define('SIGE_WPP_PRO_DAILY_LIMIT', 180);
}
if (!defined('SIGE_WPP_PRO_HOURLY_LIMIT')) {
    define('SIGE_WPP_PRO_HOURLY_LIMIT', 25);
}
if (!defined('SIGE_WPP_PRO_MIN_GLOBAL_SECONDS')) {
    define('SIGE_WPP_PRO_MIN_GLOBAL_SECONDS', 120);
}

if (!function_exists('sige_wpp_pro_tz')) {
    function sige_wpp_pro_tz(): DateTimeZone {
        return new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo');
    }
}

if (!function_exists('sige_wpp_pro_now_ts')) {
    function sige_wpp_pro_now_ts(): int {
        return (new DateTimeImmutable('now', sige_wpp_pro_tz()))->getTimestamp();
    }
}

if (!function_exists('sige_wpp_pro_mysql_at')) {
    function sige_wpp_pro_mysql_at(int $ts): string {
        return wp_date('Y-m-d H:i:s', $ts, sige_wpp_pro_tz());
    }
}

if (!function_exists('sige_wpp_pro_type_key')) {
    function sige_wpp_pro_type_key(string $tipo): string {
        return sanitize_key(remove_accents(strtolower(trim($tipo))));
    }
}

if (!function_exists('sige_wpp_pro_is_receipt_type')) {
    function sige_wpp_pro_is_receipt_type(string $tipo): bool {
        if (function_exists('sige_notify_is_receipt_type')) return sige_notify_is_receipt_type($tipo);
        $t = sige_wpp_pro_type_key($tipo);
        return (strpos($t, 'recibo') !== false || strpos($t, 'pagamento') !== false || strpos($t, 'receipt') !== false);
    }
}

if (!function_exists('sige_wpp_pro_is_debt_type')) {
    function sige_wpp_pro_is_debt_type(string $tipo): bool {
        $t = sige_wpp_pro_type_key($tipo);
        return (strpos($t, 'cobr') !== false || strpos($t, 'lembrete') !== false || strpos($t, 'pend') !== false || strpos($t, 'divida') !== false || strpos($t, 'devedor') !== false);
    }
}

if (!function_exists('sige_wpp_pro_has_link_type')) {
    function sige_wpp_pro_has_link_type(string $tipo, string $mensagem = ''): bool {
        $t = sige_wpp_pro_type_key($tipo);
        if (strpos($t, 'link') !== false) return true;
        return (bool) preg_match('~https?://\S+~i', $mensagem);
    }
}

if (!function_exists('sige_wpp_pro_fit_window_ts')) {
    function sige_wpp_pro_fit_window_ts(int $ts, int $escola_id = 1): int {
        if (function_exists('sige_notify_fit_in_operational_window')) {
            return sige_notify_fit_in_operational_window($ts, $escola_id);
        }
        $tz = sige_wpp_pro_tz();
        $dt = (new DateTimeImmutable('@' . $ts))->setTimezone($tz);
        $start = $dt->setTime(7, 30, 0);
        $end   = $dt->setTime(19, 30, 0);
        if ($dt < $start) return $start->getTimestamp() + random_int(0, 180);
        if ($dt <= $end) return $ts;
        return $start->modify('+1 day')->getTimestamp() + random_int(0, 300);
    }
}

if (!function_exists('sige_wpp_pro_base_window_ts')) {
    function sige_wpp_pro_base_window_ts(int $escola_id = 1): int {
        if (function_exists('sige_notify_next_window_ts')) {
            return sige_notify_next_window_ts($escola_id, sige_wpp_pro_now_ts());
        }
        return sige_wpp_pro_fit_window_ts(sige_wpp_pro_now_ts(), $escola_id);
    }
}

if (!function_exists('sige_wpp_guardrails_pro_gap_seconds')) {
    function sige_wpp_guardrails_pro_gap_seconds(string $tipo, string $mensagem = ''): int {
        if (sige_wpp_pro_has_link_type($tipo, $mensagem)) return random_int(300, 900);      // links: 5-15 min
        if (sige_wpp_pro_is_debt_type($tipo)) return random_int(240, 600);                // cobranças: 4-10 min
        if (sige_wpp_pro_is_receipt_type($tipo)) return random_int(120, 240);             // recibos: 2-4 min
        return random_int(120, 300);                                                     // outros: 2-5 min
    }
}

if (!function_exists('sige_wpp_guardrails_pro_last_activity_ts')) {
    function sige_wpp_guardrails_pro_last_activity_ts(int $escola_id = 1, string $telefone = ''): int {
        global $wpdb;
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tQ)) !== $tQ) return 0;

        $where_phone = '';
        $params_sent = [$escola_id];
        $params_sched = [$escola_id, sige_wpp_pro_mysql_at(sige_wpp_pro_now_ts() - DAY_IN_SECONDS)];
        if ($telefone !== '') {
            $where_phone = ' AND telefone = %s';
            $params_sent[] = $telefone;
            $params_sched[] = $telefone;
        }

        $sent_sql = "SELECT MAX(enviado_em) FROM {$tQ} WHERE escola_id=%d AND status='enviado'" . $where_phone;
        $last_sent = $wpdb->get_var($wpdb->prepare($sent_sql, $params_sent));
        $last = $last_sent ? (int) strtotime((string)$last_sent) : 0;

        $has_scheduled = function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('scheduled_at');
        if ($has_scheduled) {
            $sched_sql = "SELECT MAX(scheduled_at) FROM {$tQ} WHERE escola_id=%d AND status IN ('pendente','forcar_envio') AND scheduled_at >= %s" . $where_phone;
            $last_sched = $wpdb->get_var($wpdb->prepare($sched_sql, $params_sched));
            if ($last_sched) $last = max($last, (int) strtotime((string)$last_sched));
        }
        return max(0, $last);
    }
}

if (!function_exists('sige_wpp_guardrails_pro_initial_schedule_ts')) {
    function sige_wpp_guardrails_pro_initial_schedule_ts(int $escola_id, string $telefone, string $tipo, int $priority = 5, int $delay_seconds = 0, int $aluno_id = 0, string $mensagem = ''): int {
        $now = sige_wpp_pro_now_ts();
        $gap = max(SIGE_WPP_PRO_MIN_GLOBAL_SECONDS, sige_wpp_guardrails_pro_gap_seconds($tipo, $mensagem));
        $base = max(
            sige_wpp_pro_base_window_ts($escola_id),
            $now + max(SIGE_WPP_PRO_MIN_GLOBAL_SECONDS, $delay_seconds),
            $now + $gap
        );

        $last_global = sige_wpp_guardrails_pro_last_activity_ts($escola_id, '');
        if ($last_global > 0) $base = max($base, $last_global + SIGE_WPP_PRO_MIN_GLOBAL_SECONDS);

        $last_phone = sige_wpp_guardrails_pro_last_activity_ts($escola_id, $telefone);
        if ($last_phone > 0) $base = max($base, $last_phone + max(SIGE_WPP_PRO_MIN_GLOBAL_SECONDS, $gap));

        return sige_wpp_pro_fit_window_ts($base, $escola_id);
    }
}

if (!function_exists('sige_wpp_guardrails_pro_batch_schedule_ts')) {
    function sige_wpp_guardrails_pro_batch_schedule_ts(int $escola_id, string $tipo, int $batch_index, int $batch_total, int $contact_index = 0, string $telefone = '', string $mensagem = ''): int {
        $batch_index = max(0, $batch_index);
        $contact_index = max(0, $contact_index);
        $base = sige_wpp_pro_base_window_ts($escola_id);
        if ($batch_total > 1) {
            $ts = $base + ($batch_index * random_int(300, 600)) + random_int(0, 120) + ($contact_index * SIGE_WPP_PRO_MIN_GLOBAL_SECONDS);
        } elseif (sige_wpp_pro_is_debt_type($tipo)) {
            $ts = $base + random_int(180, 360) + ($contact_index * SIGE_WPP_PRO_MIN_GLOBAL_SECONDS);
        } else {
            $ts = $base + sige_wpp_guardrails_pro_gap_seconds($tipo, $mensagem) + ($contact_index * SIGE_WPP_PRO_MIN_GLOBAL_SECONDS);
        }
        return sige_wpp_pro_fit_window_ts($ts, $escola_id);
    }
}

if (!function_exists('sige_wpp_guardrails_pro_can_queue')) {
    function sige_wpp_guardrails_pro_can_queue(int $escola_id, int $aluno_id, string $telefone, string $tipo, string $mensagem): array {
        global $wpdb;
        $telefone = function_exists('sige_telefone_normalizar') ? (string)sige_telefone_normalizar($telefone) : preg_replace('/\D+/', '', $telefone);
        if ($telefone === '') return ['ok' => false, 'reason' => 'numero_invalido', 'silent' => false];

        if (function_exists('sige_wpp_guardian_consent_status') && sige_wpp_guardian_consent_status($telefone, $escola_id) === 'recusado') {
            return ['ok' => false, 'reason' => 'optout', 'silent' => false];
        }

        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tQ)) !== $tQ) return ['ok' => true, 'reason' => 'sem_tabela'];

        $since_exact = sige_wpp_pro_mysql_at(sige_wpp_pro_now_ts() - 2 * HOUR_IN_SECONDS);
        $exact = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tQ}
              WHERE escola_id=%d
                AND telefone=%s
                AND tipo=%s
                AND mensagem=%s
                AND status IN ('pendente','forcar_envio','enviado')
                AND criado_em >= %s",
            $escola_id, $telefone, sanitize_text_field($tipo), trim($mensagem), $since_exact
        ));
        if ($exact > 0) return ['ok' => false, 'reason' => 'duplicado_exact_2h', 'silent' => true];

        if (sige_wpp_pro_is_debt_type($tipo)) {
            $since_debt = sige_wpp_pro_mysql_at(sige_wpp_pro_now_ts() - DAY_IN_SECONDS);
            $dup_debt = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tQ}
                  WHERE escola_id=%d
                    AND telefone=%s
                    AND aluno_id=%d
                    AND tipo=%s
                    AND status IN ('pendente','forcar_envio','enviado')
                    AND criado_em >= %s",
                $escola_id, $telefone, $aluno_id, sanitize_text_field($tipo), $since_debt
            ));
            if ($dup_debt > 0) return ['ok' => false, 'reason' => 'cooldown_cobranca_24h', 'silent' => true];
        }

        return ['ok' => true, 'reason' => 'ok'];
    }
}

if (!function_exists('sige_wpp_guardrails_pro_after_send_failure')) {
    function sige_wpp_guardrails_pro_after_send_failure(int $escola_id, string $erro = '', array $res = []): void {
        $http = (int)($res['http_code'] ?? 0);
        $critical = false;
        if (function_exists('sige_wpp_health_error_is_critical')) {
            $critical = sige_wpp_health_error_is_critical($erro);
        }
        if (in_array($http, [401, 403, 423, 429], true)) $critical = true;

        if ($critical && function_exists('sige_wpp_health_set_mode')) {
            // 401/403/423 tendem a indicar autenticação/restrição/sessão: revisão humana.
            // 429 indica excesso/rate limit: pausa curta.
            $mode = in_array($http, [401, 403, 423], true) ? 'review' : 'paused';
            sige_wpp_health_set_mode($escola_id, $mode, 'Pausa automática por erro crítico WhatsApp/Z-API: ' . substr($erro, 0, 180));
        } elseif ($http >= 500 && function_exists('sige_wpp_health_set_mode')) {
            // v12.11.6 - instabilidade 5xx isolada não deve prender o número em pausa.
            // Entra em recuperação; a pausa só ocorre se houver repetição contabilizada pelo Guardian.
            sige_wpp_health_set_mode($escola_id, 'recovery', 'Recuperação cautelar por instabilidade temporária da API/Z-API: HTTP ' . $http);
        }
    }
}

// Reforça limites já existentes sem expor botões perigosos no UI.
add_filter('sige_wpp_invisible_guardrails_policy', function ($p, $eid) {
    if (is_array($p)) {
        $p['safe_windows'] = [['start' => 730, 'end' => 1930]];
        $p['min_seconds_same_phone'] = max(SIGE_WPP_PRO_MIN_GLOBAL_SECONDS, (int)($p['min_seconds_same_phone'] ?? 0));
        $p['max_per_hour'] = min(SIGE_WPP_PRO_HOURLY_LIMIT, max(1, (int)($p['max_per_hour'] ?? SIGE_WPP_PRO_HOURLY_LIMIT)));
        $p['max_force_per_run'] = 1;
        $p['honour_future_schedule_for_forced'] = true;
    }
    return $p;
}, 12000, 2);

add_filter('sige_wpp_guardian_config', function ($cfg, $eid) {
    if (is_array($cfg)) {
        $cfg['safe_start'] = '07:30';
        $cfg['safe_end'] = '19:30';
        $cfg['batch_limit'] = 1;
        $cfg['min_seconds_global'] = SIGE_WPP_PRO_MIN_GLOBAL_SECONDS;
        $cfg['min_seconds_same_phone'] = max(SIGE_WPP_PRO_MIN_GLOBAL_SECONDS, (int)($cfg['min_seconds_same_phone'] ?? 0));
        $cfg['hourly_limit'] = min(SIGE_WPP_PRO_HOURLY_LIMIT, max(1, (int)($cfg['hourly_limit'] ?? SIGE_WPP_PRO_HOURLY_LIMIT)));
        $cfg['daily_max_limit'] = min(SIGE_WPP_PRO_DAILY_LIMIT, max(1, (int)($cfg['daily_max_limit'] ?? SIGE_WPP_PRO_DAILY_LIMIT)));
        $cfg['duplicate_hours'] = max(24, (int)($cfg['duplicate_hours'] ?? 24));
    }
    return $cfg;
}, 12000, 2);

add_filter('sige_wpp_operational_daily_limit', function ($limit, $escola_id = 1) {
    $base = min(SIGE_WPP_PRO_DAILY_LIMIT, max(0, (int)$limit));
    return function_exists('sige_wpp_health_effective_daily_limit')
        ? sige_wpp_health_effective_daily_limit((int)$escola_id, $base)
        : $base;
}, 12000, 2);

add_filter('sige_wpp_normal_daily_limit', function ($limit) {
    $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $base = min(SIGE_WPP_PRO_DAILY_LIMIT, max(0, (int)$limit));
    return function_exists('sige_wpp_health_effective_daily_limit')
        ? sige_wpp_health_effective_daily_limit($eid, $base)
        : $base;
}, 12000);

add_filter('sige_wpp_normal_daily_link_limit', function ($limit) {
    return min(30, max(0, (int)$limit));
}, 12000);

add_filter('sige_wpp_queue_batch_limit', function ($limit) {
    return ((int)$limit <= 0) ? 0 : 1;
}, 12000);

add_filter('sige_wpp_manual_default_limit', function ($limit) {
    return ((int)$limit <= 0) ? 0 : 1;
}, 12000);
