<?php
/**
 * SIGE SoftGenial - WhatsApp Guardian
 * Ficheiro: includes/whatsapp-guardian.php
 *
 * Camada anti-restrição para Z-API/WhatsApp:
 * - consentimento/opt-out automático (PARAR/NÃO/STOP)
 * - humanização e variação segura das mensagens
 * - separação inteligente de links/recibos
 * - aquecimento progressivo por escola
 * - limites diários, por hora e por contacto
 * - pausa automática se houver muitas falhas
 * - webhook REST para respostas dos encarregados (desligado por defeito desde v12.10.141)
 *
 * @since 12.9.18
 */

if (!defined('ABSPATH')) exit;

if (!defined('SIGE_WPP_GUARDIAN_VERSION')) define('SIGE_WPP_GUARDIAN_VERSION', '12.9.18');

// -----------------------------------------------------------------------------
// Instalação leve e segura: cria tabela de consentimento e acrescenta colunas
// opcionais na fila, sem depender de reinstalação do plugin.
// -----------------------------------------------------------------------------
add_action('init', 'sige_wpp_guardian_bootstrap', 5);
if (!function_exists('sige_wpp_guardian_bootstrap')) {
    function sige_wpp_guardian_bootstrap(): void {
        if (get_option('sige_wpp_guardian_db_version') === SIGE_WPP_GUARDIAN_VERSION) return;
        sige_wpp_guardian_install();
        update_option('sige_wpp_guardian_db_version', SIGE_WPP_GUARDIAN_VERSION, false);
    }
}

if (!function_exists('sige_wpp_guardian_install')) {
    function sige_wpp_guardian_install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $tC = $wpdb->prefix . 'sige_whatsapp_consentimentos';
        dbDelta("CREATE TABLE {$tC} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            telefone VARCHAR(32) NOT NULL,
            aluno_id BIGINT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pendente',
            origem VARCHAR(60) NULL,
            ultima_resposta TEXT NULL,
            criado_em DATETIME NOT NULL,
            actualizado_em DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY escola_telefone (escola_id, telefone),
            KEY aluno_id (aluno_id),
            KEY status (status)
        ) {$charset};");

        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tQ}'") === $tQ) {
            $cols = $wpdb->get_col("SHOW COLUMNS FROM {$tQ}", 0);
            $adds = [
                'not_before' => "ALTER TABLE {$tQ} ADD COLUMN not_before DATETIME NULL AFTER criado_em",
                'prioridade'  => "ALTER TABLE {$tQ} ADD COLUMN prioridade TINYINT NOT NULL DEFAULT 5 AFTER tipo",
                'risco_score' => "ALTER TABLE {$tQ} ADD COLUMN risco_score TINYINT NOT NULL DEFAULT 0 AFTER prioridade",
                'hash_msg'    => "ALTER TABLE {$tQ} ADD COLUMN hash_msg CHAR(40) NULL AFTER mensagem",
                'guardian_meta' => "ALTER TABLE {$tQ} ADD COLUMN guardian_meta TEXT NULL AFTER erro",
            ];
            foreach ($adds as $col => $sql) {
                if (!in_array($col, $cols, true)) { $wpdb->query($sql); }
            }
        }
    }
}

// -----------------------------------------------------------------------------
// Configuração conservadora por defeito. Pode ser ajustada por filtros.
// -----------------------------------------------------------------------------
if (!function_exists('sige_wpp_guardian_cfg')) {
    function sige_wpp_guardian_cfg(int $escola_id = 1): array {
        $cfg = [
            'enabled' => true,
            'safe_start' => '07:00',
            'safe_end' => '19:00',
            'batch_limit' => 2,
            'min_seconds_same_phone' => 1800,
            'min_seconds_global' => 90,
            'hourly_limit' => 18,
            'daily_base_limit' => 12,
            'daily_max_limit' => 80,
            'warmup_days_to_max' => 21,
            'failure_pause_threshold' => 4,
            'failure_window_minutes' => 30,
            'pause_minutes_on_risk' => 180,
            'duplicate_hours' => 24,
            'link_delay_min' => 20,
            'link_delay_max' => 60,
            'require_consent_for_marketing' => true,
        ];
        return apply_filters('sige_wpp_guardian_config', $cfg, $escola_id);
    }
}

if (!function_exists('sige_wpp_guardian_now')) {
    function sige_wpp_guardian_now(): string { return current_time('mysql'); }
}

if (!function_exists('sige_wpp_guardian_in_safe_window')) {
    function sige_wpp_guardian_in_safe_window(?int $ts = null, int $escola_id = 1): bool {
        $c = sige_wpp_guardian_cfg($escola_id);
        $ts = $ts ?: current_time('timestamp');
        $hm = wp_date('H:i', $ts);
        return ($hm >= $c['safe_start'] && $hm <= $c['safe_end']);
    }
}

if (!function_exists('sige_wpp_guardian_next_safe_time')) {
    function sige_wpp_guardian_next_safe_time(int $delay_minutes = 0, int $escola_id = 1): string {
        $c = sige_wpp_guardian_cfg($escola_id);
        $ts = current_time('timestamp') + ($delay_minutes * 60);
        if (sige_wpp_guardian_in_safe_window($ts, $escola_id)) return wp_date('Y-m-d H:i:s', $ts);
        $today_start = strtotime(wp_date('Y-m-d ', current_time('timestamp')) . $c['safe_start']);
        if ($ts < $today_start) return wp_date('Y-m-d H:i:s', $today_start + ($delay_minutes * 60));
        return wp_date('Y-m-d H:i:s', strtotime('+1 day', $today_start) + ($delay_minutes * 60));
    }
}

// -----------------------------------------------------------------------------
// Consentimento e respostas humanas.
// -----------------------------------------------------------------------------
if (!function_exists('sige_wpp_guardian_normalize_phone')) {
    function sige_wpp_guardian_normalize_phone($phone): string {
        return function_exists('sige_telefone_normalizar') ? (string) sige_telefone_normalizar($phone) : preg_replace('/\D+/', '', (string)$phone);
    }
}

if (!function_exists('sige_wpp_guardian_consent_status')) {
    function sige_wpp_guardian_consent_status(string $telefone, int $escola_id = 1): string {
        global $wpdb;
        $telefone = sige_wpp_guardian_normalize_phone($telefone);
        if (!$telefone) return 'invalido';
        $t = $wpdb->prefix . 'sige_whatsapp_consentimentos';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'") !== $t) return 'pendente';
        $st = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$t} WHERE escola_id=%d AND telefone=%s LIMIT 1", $escola_id, $telefone));
        return $st ?: 'pendente';
    }
}

if (!function_exists('sige_wpp_guardian_set_consent')) {
    function sige_wpp_guardian_set_consent(string $telefone, string $status, int $escola_id = 1, int $aluno_id = 0, string $origem = 'sistema', string $resposta = ''): bool {
        if (!sige_tenant_write_guard((int) $escola_id, 'sige_wpp_guardian_set_consent')) { return false; }
        global $wpdb;
        $telefone = sige_wpp_guardian_normalize_phone($telefone);
        if (!$telefone) return false;
        $status = in_array($status, ['aceito','recusado','pendente'], true) ? $status : 'pendente';
        $t = $wpdb->prefix . 'sige_whatsapp_consentimentos';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'") !== $t) sige_wpp_guardian_install();
        $now = sige_wpp_guardian_now();
        $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE escola_id=%d AND telefone=%s LIMIT 1", $escola_id, $telefone));
        $data = [
            'aluno_id' => $aluno_id ?: null,
            'status' => $status,
            'origem' => sanitize_text_field($origem),
            'ultima_resposta' => sanitize_textarea_field($resposta),
            'actualizado_em' => $now,
        ];
        if ($exists) return $wpdb->update($t, $data, ['id'=>$exists]) !== false;
        $data['escola_id'] = $escola_id;
        $data['telefone'] = $telefone;
        $data['criado_em'] = $now;
        return (bool)$wpdb->insert($t, $data);
    }
}

if (!function_exists('sige_wpp_guardian_interpret_reply')) {
    function sige_wpp_guardian_interpret_reply(string $texto): string {
        $t = function_exists('remove_accents') ? remove_accents($texto) : $texto;
        $t = strtolower(trim(preg_replace('/\s+/', ' ', $t)));
        $neg = ['nao','não','n','stop','parar','sair','cancelar','nao quero','não quero','remover','bloquear'];
        $pos = ['sim','s','ok','aceito','autorizo','pode enviar','quero','confirmo'];
        $rec = ['recibo','comprovativo','comprovante','link','pdf'];
        foreach ($neg as $w) if ($t === $w || strpos($t, $w) !== false) return 'recusado';
        foreach ($rec as $w) if ($t === $w || strpos($t, $w) !== false) return 'pedido_recibo';
        foreach ($pos as $w) if ($t === $w || strpos($t, $w) !== false) return 'aceito';
        return 'neutro';
    }
}

add_action('rest_api_init', function () {
    if (function_exists('sige_sec_whatsapp_webhook_enabled') && !sige_sec_whatsapp_webhook_enabled()) {
        return;
    }
    register_rest_route('sige/v1', '/whatsapp-webhook', [
        'methods' => 'POST',
        'permission_callback' => function (WP_REST_Request $request) {
            return function_exists('sige_sec_validate_rest_secret_or_hmac')
                ? sige_sec_validate_rest_secret_or_hmac($request, 'whatsapp_webhook')
                : new WP_Error('sige_security_unavailable', 'Camada de segurança indisponível.', ['status' => 503]);
        },
        'callback' => 'sige_wpp_guardian_webhook',
    ]);
});

if (!function_exists('sige_wpp_guardian_webhook')) {
    function sige_wpp_guardian_webhook(WP_REST_Request $request) {
        $data = $request->get_json_params();
        if (!is_array($data)) $data = $request->get_params();
        $phone = $data['phone'] ?? $data['from'] ?? $data['sender'] ?? $data['participantPhone'] ?? '';
        $msg = $data['message'] ?? $data['text'] ?? ($data['body']['text'] ?? '') ?? '';
        if (is_array($msg)) $msg = wp_json_encode($msg, JSON_UNESCAPED_UNICODE);
        $phone = sige_wpp_guardian_normalize_phone($phone);
        $msg = (string)$msg;
        if (!$phone || !$msg) return new WP_REST_Response(['ok'=>false, 'error'=>'payload incompleto'], 400);
        $escola_id = (int) apply_filters('sige_wpp_guardian_webhook_escola_id', 1, $data);
        $intent = sige_wpp_guardian_interpret_reply($msg);
        if ($intent === 'recusado') sige_wpp_guardian_set_consent($phone, 'recusado', $escola_id, 0, 'webhook', $msg);
        elseif ($intent === 'aceito') sige_wpp_guardian_set_consent($phone, 'aceito', $escola_id, 0, 'webhook', $msg);
        if (function_exists('sige_fin_log')) sige_fin_log('wpp_resposta_recebida', ['telefone'=>$phone, 'intent'=>$intent, 'texto'=>substr($msg,0,160)]);
        return ['ok'=>true, 'intent'=>$intent];
    }
}

// -----------------------------------------------------------------------------
// Humanização: variações, consentimento leve e opt-out claro.
// -----------------------------------------------------------------------------
if (!function_exists('sige_wpp_guardian_has_url')) {
    function sige_wpp_guardian_has_url(string $msg): bool { return (bool)preg_match('~https?://\S+~i', $msg); }
}
if (!function_exists('sige_wpp_guardian_extract_urls')) {
    function sige_wpp_guardian_extract_urls(string $msg): array {
        preg_match_all('~https?://\S+~i', $msg, $m);
        return array_values(array_unique($m[0] ?? []));
    }
}
if (!function_exists('sige_wpp_guardian_remove_urls')) {
    function sige_wpp_guardian_remove_urls(string $msg): string {
        $msg = preg_replace('~\s*https?://\S+~i', '', $msg);
        return trim(preg_replace("/\n{3,}/", "\n\n", (string)$msg));
    }
}

if (!function_exists('sige_wpp_guardian_humanize')) {
    function sige_wpp_guardian_humanize(string $mensagem, string $tipo, int $aluno_id, string $telefone): string {
        $tipo = sanitize_key($tipo);
        $seed = crc32($telefone . '|' . $tipo . '|' . wp_date('Y-m-d'));
        $saudacoes = [
            'Bom dia. Esperamos que esteja bem.',
            'Olá. Esperamos que esteja tudo bem consigo.',
            'Saudações. A secretaria da escola partilha esta informação.',
            'Caro(a) encarregado(a), esperamos que esteja bem.',
        ];
        $prefix = $saudacoes[$seed % count($saudacoes)];

        $mensagem = wp_strip_all_tags((string)$mensagem);
        $mensagem = preg_replace("/\r\n|\r/", "\n", $mensagem);
        $mensagem = preg_replace('/[ \t]+/', ' ', $mensagem);
        $mensagem = preg_replace("/\n{3,}/", "\n\n", $mensagem);

        $duros = [
            'URGENTE!!!', 'Urgente!!!', 'PAGUE JÁ', 'Pague já',
            'Solicitamos a regularização o mais breve possível.',
            'Efectue o pagamento atempadamente para evitar encargos adicionais.',
            'Por favor efectue o pagamento até à data indicada.',
        ];
        $suaves = [
            'Importante', 'Importante', 'Por favor, confirme com a secretaria', 'Por favor, confirme com a secretaria',
            'Quando puder, por favor confirme a situação com a secretaria.',
            'Quando puder, confirme com a secretaria se a informação está correcta.',
            'Agradecemos que confirme com a secretaria se a informação está correcta.',
        ];
        $mensagem = str_replace($duros, $suaves, $mensagem);

        if (stripos($mensagem, 'Bom dia') === false && stripos($mensagem, 'Olá') === false && stripos($mensagem, 'Caro') === false && stripos($mensagem, 'Saudações') === false) {
            $mensagem = $prefix . "\n\n" . ltrim($mensagem);
        }

        if (stripos($tipo, 'cobr') !== false || stripos($tipo, 'pend') !== false || stripos($tipo, 'fatura') !== false || stripos($tipo, 'lembrete') !== false) {
            if (stripos($mensagem, 'Se já regularizou') === false) {
                $mensagem .= "\n\nSe já regularizou ou se houver alguma diferença, responda por aqui para a secretaria confirmar.";
            }
        }

        if (!preg_match('/\b(PARAR|NÃO|NAO|STOP)\b/i', $mensagem)) {
            $mensagem .= "\n\nSe esta mensagem não devia ter sido enviada para este número, diga-nos por aqui que a secretaria corrige o contacto.";
        }
        return trim($mensagem);
    }
}

if (!function_exists('sige_wpp_guardian_link_message')) {
    function sige_wpp_guardian_link_message(array $urls, string $tipo): string {
        $u = reset($urls);
        if (!$u) return '';
        if (sanitize_key($tipo) === 'recibo') {
            return "Como combinado, segue o comprovativo digital do recibo:\n" . $u . "\n\nSe houver alguma dúvida, pode responder por aqui.";
        }
        return "Segue a informação digital relacionada com a escola:\n" . $u . "\n\nSe houver alguma dúvida, pode responder por aqui.";
    }
}

// -----------------------------------------------------------------------------
// Decisão de enfileiramento e throttling.
// -----------------------------------------------------------------------------
if (!function_exists('sige_wpp_guardian_daily_limit')) {
    function sige_wpp_guardian_daily_limit(int $escola_id): int {
        $c = sige_wpp_guardian_cfg($escola_id);
        $start_key = 'sige_wpp_guardian_start_e' . $escola_id;
        $start = (string)get_option($start_key, '');
        if (!$start) { $start = wp_date('Y-m-d'); update_option($start_key, $start, false); }
        $days = max(1, (int)floor((current_time('timestamp') - strtotime($start . ' 00:00:00')) / DAY_IN_SECONDS) + 1);
        $step = ($c['daily_max_limit'] - $c['daily_base_limit']) / max(1, (int)$c['warmup_days_to_max']);
        return (int)min($c['daily_max_limit'], round($c['daily_base_limit'] + ($days - 1) * $step));
    }
}

if (!function_exists('sige_wpp_guardian_should_pause')) {
    function sige_wpp_guardian_should_pause(int $escola_id): bool {
        $paused_until = (int)get_transient('sige_wpp_guardian_pause_e' . $escola_id);
        return $paused_until && $paused_until > current_time('timestamp');
    }
}

if (!function_exists('sige_wpp_guardian_record_failure_risk')) {
    function sige_wpp_guardian_record_failure_risk(int $escola_id): void {
        global $wpdb;
        $c = sige_wpp_guardian_cfg($escola_id);
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        $since = wp_date('Y-m-d H:i:s', current_time('timestamp') - ((int)$c['failure_window_minutes'] * 60));
        $fails = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tQ} WHERE escola_id=%d AND status='falhou' AND criado_em >= %s", $escola_id, $since));
        if ($fails >= (int)$c['failure_pause_threshold']) {
            set_transient('sige_wpp_guardian_pause_e' . $escola_id, current_time('timestamp') + ((int)$c['pause_minutes_on_risk'] * 60), (int)$c['pause_minutes_on_risk'] * 60);
        }
    }
}

if (!function_exists('sige_wpp_guardian_can_queue')) {
    function sige_wpp_guardian_can_queue(int $escola_id, string $telefone, string $tipo, string $mensagem): array {
        global $wpdb;
        $c = sige_wpp_guardian_cfg($escola_id);
        if (empty($c['enabled'])) return ['ok'=>true, 'reason'=>'disabled'];
        $telefone = sige_wpp_guardian_normalize_phone($telefone);
        if (!$telefone) return ['ok'=>false, 'reason'=>'numero_invalido'];
        if (sige_wpp_guardian_consent_status($telefone, $escola_id) === 'recusado') return ['ok'=>false, 'reason'=>'optout'];
        if (sige_wpp_guardian_should_pause($escola_id)) return ['ok'=>false, 'reason'=>'pausa_guardian'];

        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        $hash = sha1($escola_id . '|' . $telefone . '|' . sanitize_key($tipo) . '|' . trim(preg_replace('/\s+/', ' ', $mensagem)));
        $sinceDup = wp_date('Y-m-d H:i:s', current_time('timestamp') - ((int)$c['duplicate_hours'] * HOUR_IN_SECONDS));
        $dup = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tQ} WHERE escola_id=%d AND telefone=%s AND hash_msg=%s AND criado_em >= %s", $escola_id, $telefone, $hash, $sinceDup));
        if ($dup > 0) return ['ok'=>false, 'reason'=>'duplicado_24h'];

        $dayStart = wp_date('Y-m-d 00:00:00');
        $sentToday = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tQ} WHERE escola_id=%d AND status='enviado' AND enviado_em >= %s", $escola_id, $dayStart));
        if ($sentToday >= sige_wpp_guardian_daily_limit($escola_id)) return ['ok'=>false, 'reason'=>'limite_diario'];

        return ['ok'=>true, 'reason'=>'ok', 'hash'=>$hash];
    }
}

if (!function_exists('sige_wpp_guardian_insert_queue')) {
    function sige_wpp_guardian_insert_queue(int $aluno_id, string $telefone, string $tipo, string $mensagem, int $prioridade = 5, int $delay_minutes = 0, array $meta = []): bool {
        global $wpdb;
        $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        if ($escola_id <= 0) { return false; }
        $telefone = sige_wpp_guardian_normalize_phone($telefone);
        $decision = sige_wpp_guardian_can_queue($escola_id, $telefone, $tipo, $mensagem);
        if (empty($decision['ok'])) {
            if (function_exists('sige_fin_log')) sige_fin_log('wpp_guardian_bloqueado', ['telefone'=>$telefone, 'tipo'=>$tipo, 'motivo'=>$decision['reason'] ?? 'desconhecido']);
            return false;
        }
        $t = $wpdb->prefix . 'sige_whatsapp_queue';
        $not_before = sige_wpp_guardian_next_safe_time($delay_minutes, $escola_id);
        $ok = $wpdb->insert($t, [
            'escola_id' => $escola_id,
            'telefone' => $telefone,
            'mensagem' => $mensagem,
            'hash_msg' => $decision['hash'] ?? sha1($mensagem),
            'tipo' => sanitize_text_field($tipo),
            'prioridade' => max(1, min(9, $prioridade)),
            'risco_score' => 0,
            'aluno_id' => $aluno_id,
            'status' => 'pendente',
            'tentativas' => 0,
            'criado_em' => sige_wpp_guardian_now(),
            'not_before' => $not_before,
            'guardian_meta' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);
        if ($ok && function_exists('sige_fin_log')) sige_fin_log('wpp_guardian_enfileirado', ['aluno_id'=>$aluno_id, 'tipo'=>$tipo, 'telefone'=>$telefone, 'not_before'=>$not_before]);
        return (bool)$ok;
    }
}

/**
 * Função central chamada a partir de sige_fin_queue_whatsapp().
 * Se a mensagem tiver link, separa texto e link para reduzir o padrão de spam.
 */
if (!function_exists('sige_wpp_guardian_queue')) {
    function sige_wpp_guardian_queue(int $aluno_id, string $telefone, string $tipo, string $mensagem): bool {
        $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        $telefone = sige_wpp_guardian_normalize_phone($telefone);
        $tipo_key = sanitize_key($tipo);
        $prioridade = ($tipo_key === 'recibo' || $tipo_key === 'conta_aluno') ? 2 : (($tipo_key === 'cobranca' || $tipo_key === 'lembrete') ? 6 : 5);
        $urls = sige_wpp_guardian_extract_urls($mensagem);
        $base = sige_wpp_guardian_humanize(sige_wpp_guardian_remove_urls($mensagem), $tipo, $aluno_id, $telefone);
        $ok = sige_wpp_guardian_insert_queue($aluno_id, $telefone, $tipo, $base, $prioridade, 0, ['guardian'=>'base']);
        if (!empty($urls)) {
            $c = sige_wpp_guardian_cfg($escola_id);
            $delay = rand((int)$c['link_delay_min'], (int)$c['link_delay_max']);
            $linkMsg = sige_wpp_guardian_link_message($urls, $tipo);
            // Links de recibo saem depois, em mensagem própria, menos agressiva.
            $ok2 = sige_wpp_guardian_insert_queue($aluno_id, $telefone, $tipo . '_link', $linkMsg, max(1, $prioridade + 1), $delay, ['guardian'=>'link_delay','delay_min'=>$delay]);
            return $ok || $ok2;
        }
        return $ok;
    }
}

// -----------------------------------------------------------------------------
// Cron Guardian: substitui de forma segura o volume de processamento por filtros
// e fornece helper para o cron existente respeitar limites.
// -----------------------------------------------------------------------------
add_filter('sige_wpp_queue_batch_limit', function ($limit) {
    $cfg = sige_wpp_guardian_cfg(1);
    return min((int)$limit, (int)$cfg['batch_limit']);
}, 20);

if (!function_exists('sige_wpp_guardian_queue_where_sql')) {
    function sige_wpp_guardian_queue_where_sql(string $table_alias = ''): string {
        $p = $table_alias ? rtrim($table_alias, '.') . '.' : '';
        return " AND ({$p}not_before IS NULL OR {$p}not_before <= '" . esc_sql(sige_wpp_guardian_now()) . "')";
    }
}

if (!function_exists('sige_wpp_guardian_can_send_now')) {
    function sige_wpp_guardian_can_send_now(int $escola_id, string $telefone): array {
        global $wpdb;
        $c = sige_wpp_guardian_cfg($escola_id);
        if (!sige_wpp_guardian_in_safe_window(null, $escola_id)) return ['ok'=>false, 'reason'=>'fora_janela'];
        if (sige_wpp_guardian_should_pause($escola_id)) return ['ok'=>false, 'reason'=>'pausa_guardian'];
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        $sincePhone = wp_date('Y-m-d H:i:s', current_time('timestamp') - ((int)$c['min_seconds_same_phone']));
        $recentPhone = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tQ} WHERE escola_id=%d AND telefone=%s AND status='enviado' AND enviado_em >= %s", $escola_id, $telefone, $sincePhone));
        if ($recentPhone > 0) return ['ok'=>false, 'reason'=>'intervalo_contacto'];
        $sinceGlobal = wp_date('Y-m-d H:i:s', current_time('timestamp') - ((int)$c['min_seconds_global']));
        $recentGlobal = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tQ} WHERE escola_id=%d AND status='enviado' AND enviado_em >= %s", $escola_id, $sinceGlobal));
        if ($recentGlobal > 0) return ['ok'=>false, 'reason'=>'intervalo_global'];
        $hourStart = wp_date('Y-m-d H:00:00');
        $sentHour = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tQ} WHERE escola_id=%d AND status='enviado' AND enviado_em >= %s", $escola_id, $hourStart));
        if ($sentHour >= (int)$c['hourly_limit']) return ['ok'=>false, 'reason'=>'limite_hora'];
        return ['ok'=>true, 'reason'=>'ok'];
    }
}
