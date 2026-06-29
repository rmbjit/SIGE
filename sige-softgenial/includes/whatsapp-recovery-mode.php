<?php
/**
 * SIGE SoftGenial - WhatsApp Recovery Mode / Guardian Pro
 * Ficheiro: includes/whatsapp-recovery-mode.php
 *
 * Camada conservadora para reduzir risco de restrição em envios via Z-API:
 * - aquecimento/recovery por escola/instância;
 * - opt-in/opt-out por resposta dos encarregados;
 * - priorização de contactos quentes;
 * - controlo rigoroso de links;
 * - auto-pausa por sinais de falha;
 * - webhook REST para respostas e status (desligado por defeito desde v12.10.141).
 *
 * Esta camada é ADITIVA e defensiva: se alguma tabela/coluna ainda não existir,
 * cria/migra sem quebrar a lógica financeira validada.
 */
if (!defined('ABSPATH')) exit;

if (!defined('SIGE_WPP_RECOVERY_VERSION')) {
    define('SIGE_WPP_RECOVERY_VERSION', '12.11.9.30');
}

// -----------------------------------------------------------------------------
// INSTALAÇÃO / MIGRAÇÃO LEVE
// -----------------------------------------------------------------------------
add_action('init', 'sige_wpp_recovery_install', 20);
if (!function_exists('sige_wpp_recovery_install')) {
    function sige_wpp_recovery_install() {
        $installed = (string) get_option('sige_wpp_recovery_version', '');
        if ($installed === SIGE_WPP_RECOVERY_VERSION) return;

        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $cc = $wpdb->get_charset_collate();
        $p  = $wpdb->prefix;

        dbDelta("CREATE TABLE {$p}sige_whatsapp_contacts (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            telefone VARCHAR(50) NOT NULL,
            consent_status VARCHAR(20) NOT NULL DEFAULT 'unknown',
            heat_score INT(11) NOT NULL DEFAULT 0,
            replies_count INT(11) NOT NULL DEFAULT 0,
            sent_count INT(11) NOT NULL DEFAULT 0,
            failed_count INT(11) NOT NULL DEFAULT 0,
            last_reply_at DATETIME DEFAULT NULL,
            last_sent_at DATETIME DEFAULT NULL,
            blocked_until DATETIME DEFAULT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_escola_phone (escola_id, telefone),
            KEY idx_consent (consent_status),
            KEY idx_heat (heat_score)
        ) {$cc};");

        dbDelta("CREATE TABLE {$p}sige_whatsapp_instance_state (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            mode VARCHAR(30) NOT NULL DEFAULT 'normal',
            recovery_started_at DATETIME DEFAULT NULL,
            paused_until DATETIME DEFAULT NULL,
            daily_sent INT(11) NOT NULL DEFAULT 0,
            daily_failed INT(11) NOT NULL DEFAULT 0,
            daily_links INT(11) NOT NULL DEFAULT 0,
            day_key VARCHAR(10) DEFAULT NULL,
            last_risk_reason VARCHAR(255) DEFAULT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_escola (escola_id),
            KEY idx_mode (mode),
            KEY idx_paused (paused_until)
        ) {$cc};");

        $q = $p . 'sige_whatsapp_queue';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $q)) === $q) {
            $cols = $wpdb->get_col("SHOW COLUMNS FROM {$q}", 0);
            $add = function($col, $sql) use ($wpdb, $q, $cols) {
                if (!in_array($col, $cols, true)) {
                    $wpdb->query("ALTER TABLE {$q} ADD COLUMN {$sql}");
                }
            };
            $add('scheduled_at', "scheduled_at DATETIME DEFAULT NULL AFTER criado_em");
            $add('priority', "priority TINYINT(3) UNSIGNED NOT NULL DEFAULT 5 AFTER tipo");
            $add('risk_flags', "risk_flags VARCHAR(255) DEFAULT NULL AFTER erro");
            $add('guardian_meta', "guardian_meta TEXT DEFAULT NULL AFTER risk_flags");

            // v12.9.76 - migração idempotente: evita erro SQL "Duplicate key name"
            // quando a escola já tem o índice criado por uma tentativa anterior.
            $idx_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW INDEX FROM `{$q}` WHERE Key_name = %s",
                'idx_wpp_guardian'
            ));
            if (!$idx_exists) {
                $wpdb->query("ALTER TABLE `{$q}` ADD INDEX `idx_wpp_guardian` (`status`, `scheduled_at`, `priority`, `criado_em`)");
            }
        }

        update_option('sige_wpp_recovery_version', SIGE_WPP_RECOVERY_VERSION, false);
    }
}

// -----------------------------------------------------------------------------
// UTILITÁRIOS
// -----------------------------------------------------------------------------
if (!function_exists('sige_wpp_now')) {
    function sige_wpp_now() { return current_time('mysql'); }
}

if (!function_exists('sige_wpp_has_table')) {
    function sige_wpp_has_table($table) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}

if (!function_exists('sige_wpp_queue_has_col')) {
    function sige_wpp_queue_has_col($col) {
        global $wpdb;
        $t = $wpdb->prefix . 'sige_whatsapp_queue';
        return (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$t} LIKE %s", $col));
    }
}

if (!function_exists('sige_wpp_normalize_inbound_phone')) {
    function sige_wpp_normalize_inbound_phone($raw) {
        if (function_exists('sige_telefone_normalizar')) return sige_telefone_normalizar($raw);
        return preg_replace('/[^0-9]/', '', (string)$raw);
    }
}

if (!function_exists('sige_wpp_get_contact')) {
    function sige_wpp_get_contact($escola_id, $telefone) {
        if (!sige_tenant_write_guard((int) $escola_id, 'sige_wpp_get_contact')) { return null; }
        global $wpdb;
        $telefone = sige_wpp_normalize_inbound_phone($telefone);
        if (!$telefone) return null;
        $t = $wpdb->prefix . 'sige_whatsapp_contacts';
        if (!sige_wpp_has_table($t)) return null;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE escola_id=%d AND telefone=%s LIMIT 1", (int)$escola_id, $telefone));
        if ($row) return $row;
        $wpdb->insert($t, [
            'escola_id' => (int)$escola_id,
            'telefone' => $telefone,
            'consent_status' => 'unknown',
            'heat_score' => 0,
            'updated_at' => sige_wpp_now(),
        ]);
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE escola_id=%d AND telefone=%s LIMIT 1", (int)$escola_id, $telefone));
    }
}

if (!function_exists('sige_wpp_contact_bump')) {
    function sige_wpp_contact_bump($escola_id, $telefone, array $fields) {
        if (!sige_tenant_write_guard((int) $escola_id, 'sige_wpp_contact_bump')) { return false; }
        global $wpdb;
        $telefone = sige_wpp_normalize_inbound_phone($telefone);
        if (!$telefone) return false;
        sige_wpp_get_contact($escola_id, $telefone);
        $t = $wpdb->prefix . 'sige_whatsapp_contacts';
        $fields['updated_at'] = sige_wpp_now();
        return false !== $wpdb->update($t, $fields, ['escola_id'=>(int)$escola_id, 'telefone'=>$telefone]);
    }
}

if (!function_exists('sige_wpp_get_state')) {
    function sige_wpp_get_state($escola_id) {
        if (!sige_tenant_write_guard((int) $escola_id, 'sige_wpp_get_state')) { return null; }
        global $wpdb;
        $t = $wpdb->prefix . 'sige_whatsapp_instance_state';
        if (!sige_wpp_has_table($t)) return null;
        $today = wp_date('Y-m-d');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE escola_id=%d LIMIT 1", (int)$escola_id));
        if (!$row) {
            $wpdb->insert($t, [
                'escola_id' => (int)$escola_id,
                'mode' => 'normal',
                'day_key' => $today,
                'updated_at' => sige_wpp_now(),
            ]);
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE escola_id=%d LIMIT 1", (int)$escola_id));
        }
        if ($row && (string)$row->day_key !== $today) {
            $wpdb->update($t, [
                'daily_sent' => 0,
                'daily_failed' => 0,
                'daily_links' => 0,
                'day_key' => $today,
                'updated_at' => sige_wpp_now(),
            ], ['escola_id'=>(int)$escola_id]);
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE escola_id=%d LIMIT 1", (int)$escola_id));
        }
        return $row;
    }
}

if (!function_exists('sige_wpp_set_state')) {
    function sige_wpp_set_state($escola_id, array $fields) {
        if (!sige_tenant_write_guard((int) $escola_id, 'sige_wpp_set_state')) { return false; }
        global $wpdb;
        sige_wpp_get_state($escola_id);
        $t = $wpdb->prefix . 'sige_whatsapp_instance_state';
        $fields['updated_at'] = sige_wpp_now();
        return false !== $wpdb->update($t, $fields, ['escola_id'=>(int)$escola_id]);
    }
}

if (!function_exists('sige_wpp_activate_recovery')) {
    function sige_wpp_activate_recovery($escola_id, $reason = 'manual/risco') {
        $state = sige_wpp_get_state($escola_id);
        $is_manual = (stripos((string)$reason, 'manual') !== false || stripos((string)$reason, 'activação') !== false || stripos((string)$reason, 'ativação') !== false);
        $fields = [
            'mode' => 'recovery',
            'paused_until' => null,
            'last_risk_reason' => substr((string)$reason, 0, 255),
        ];
        if (!$state || empty($state->recovery_started_at) || $is_manual) {
            $fields['recovery_started_at'] = sige_wpp_now();
        }
        if ($is_manual) {
            $fields['daily_failed'] = 0;
            $fields['daily_links'] = 0;
            $fields['day_key'] = wp_date('Y-m-d', current_time('timestamp'), new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo'));
            if (function_exists('sige_wpp_health_clear_pause_locks')) sige_wpp_health_clear_pause_locks((int)$escola_id);
            if (function_exists('sige_wpp_health_mark_manual_since')) sige_wpp_health_mark_manual_since((int)$escola_id);
        }
        return sige_wpp_set_state($escola_id, $fields);
    }
}

if (!function_exists('sige_wpp_pause_instance')) {
    function sige_wpp_pause_instance($escola_id, $hours = 48, $reason = 'auto-stop') {
        $until = gmdate('Y-m-d H:i:s', current_time('timestamp', true) + ((int)$hours * HOUR_IN_SECONDS));
        return sige_wpp_set_state($escola_id, [
            'mode' => 'paused',
            'paused_until' => get_date_from_gmt($until, 'Y-m-d H:i:s'),
            'last_risk_reason' => substr((string)$reason, 0, 255),
        ]);
    }
}

if (!function_exists('sige_wpp_recovery_day')) {
    function sige_wpp_recovery_day($state) {
        if (!$state || empty($state->recovery_started_at)) return 0;
        $start = strtotime((string)$state->recovery_started_at);
        if (!$start) return 1;
        $days = floor((current_time('timestamp') - $start) / DAY_IN_SECONDS) + 1;
        return max(1, (int)$days);
    }
}

if (!function_exists('sige_wpp_daily_limit_for_state')) {
    function sige_wpp_daily_limit_for_state($state) {
        // v12.9.75 - limite diário dinâmico por saúde/reputação do número.
        // 250 passa a ser o tecto saudável, não um valor aplicado cegamente.
        $eid = (int)($state->escola_id ?? 0);
        if (function_exists('sige_wpp_health_effective_daily_limit')) {
            return (int) sige_wpp_health_effective_daily_limit($eid, 250);
        }
        if ($state && in_array((string)$state->mode, ['paused','review'], true)) return 0;
        if ($state && (string)$state->mode === 'recovery' && function_exists('sige_wpp_recovery_day')) {
            $d = sige_wpp_recovery_day($state);
            if ($d <= 1) return 10;
            if ($d === 2) return 20;
            if ($d === 3) return 30;
            if ($d <= 5) return 50;
            return 80;
        }
        return 250;
    }
}

if (!function_exists('sige_wpp_link_limit_for_state')) {
    function sige_wpp_link_limit_for_state($state) {
        if (!$state) return 20;
        if ($state->mode === 'recovery') {
            $d = sige_wpp_recovery_day($state);
            if ($d <= 1) return 0;
            if ($d === 2) return 3;
            if ($d === 3) return 8;
            return 15;
        }
        return (int) apply_filters('sige_wpp_normal_daily_link_limit', 40);
    }
}

if (!function_exists('sige_wpp_message_has_link')) {
    function sige_wpp_message_has_link($message) {
        return (bool) preg_match('~https?://|www\.~i', (string)$message);
    }
}

if (!function_exists('sige_wpp_extract_links')) {
    function sige_wpp_extract_links($message) {
        preg_match_all('~https?://[^\s]+~i', (string)$message, $m);
        return array_values(array_unique($m[0] ?? []));
    }
}

if (!function_exists('sige_wpp_remove_links')) {
    function sige_wpp_remove_links($message) {
        $out = preg_replace('~https?://[^\s]+~i', '', (string)$message);
        return trim(preg_replace("/\n{3,}/", "\n\n", $out));
    }
}

if (!function_exists('sige_wpp_consent_footer')) {
    function sige_wpp_consent_footer() {
        return "\n\nSe esta mensagem não devia ter sido enviada para este número, diga-nos por aqui que a secretaria corrige o contacto.";
    }
}

// ============================================================================
// v12.9.32 - WhatsApp Conversacional Humano
// - cobrança automática continua desactivada;
// - confirmação de pagamento NÃO envia link de recibo automaticamente;
// - recibo só é enviado se o encarregado responder SIM/Quero/Pode enviar;
// - mensagens de mensalidade, cobrança e pagamento recebem variação humana;
// - cada mensagem termina com pergunta/convite à conversa.
// ============================================================================
if (!function_exists('sige_wpp_pick')) {
    function sige_wpp_pick(array $items) {
        if (empty($items)) return '';
        return (string)$items[array_rand($items)];
    }
}

if (!function_exists('sige_wpp_is_receipt_type')) {
    function sige_wpp_is_receipt_type($tipo): bool {
        $t = sanitize_key((string)$tipo);
        return (strpos($t, 'recibo') !== false || strpos($t, 'pagamento') !== false || strpos($t, 'receipt') !== false);
    }
}

if (!function_exists('sige_wpp_is_billing_type')) {
    function sige_wpp_is_billing_type($tipo): bool {
        $t = sanitize_key((string)$tipo);
        return (strpos($t, 'fatura') !== false || strpos($t, 'factura') !== false || strpos($t, 'mensal') !== false || strpos($t, 'lanc') !== false);
    }
}

if (!function_exists('sige_wpp_is_collection_type')) {
    function sige_wpp_is_collection_type($tipo): bool {
        $t = sanitize_key((string)$tipo);
        return (strpos($t, 'cobr') !== false || strpos($t, 'divida') !== false || strpos($t, 'pend') !== false || strpos($t, 'lembrete') !== false);
    }
}

if (!function_exists('sige_wpp_conversational_questions')) {
    function sige_wpp_conversational_questions($tipo): array {
        if (sige_wpp_is_receipt_type($tipo)) {
            return [
                'Gostaria que enviássemos o link do recibo por aqui?',
                'Quer que a secretaria envie também o link do recibo?',
                'Podemos enviar o link do recibo por esta conversa?',
                'Pretende receber o recibo em link por WhatsApp?',
                'Fica bem para si receber o link do recibo por aqui?',
                'Quer que partilhemos o recibo por este número?',
                'Podemos seguir com o envio do link do recibo?',
                'Deseja que enviemos o comprovativo em formato de link?',
                'Está tudo claro ou quer que enviemos o recibo por aqui?',
                'Se quiser, podemos mandar o link do recibo; pretende receber?',
            ];
        }
        if (sige_wpp_is_billing_type($tipo)) {
            return [
                'Conseguiu verificar esta informação?',
                'Está tudo claro para si?',
                'Há algum detalhe que gostaria que a secretaria confirmasse?',
                'Quer que a escola esclareça algum ponto?',
                'Podemos ajudar com alguma informação adicional?',
                'Ficou alguma dúvida sobre este lançamento?',
                'Se precisar, podemos confirmar os detalhes consigo. Quer que verifiquemos algo?',
                'Está de acordo com a informação apresentada?',
                'Podemos apoiar em alguma questão sobre esta mensalidade?',
                'Quer conversar com a secretaria sobre algum detalhe?',
            ];
        }
        return [
            'Podemos ajudar com alguma informação?',
            'Quer que a secretaria confirme algum detalhe consigo?',
            'Se houver alguma diferença, pode responder por aqui?',
            'Está tudo claro ou prefere que a escola verifique consigo?',
            'Precisa de algum apoio da secretaria?',
            'Podemos conversar sobre esta situação?',
            'Quer que alguém da escola confirme melhor consigo?',
            'Há algum ponto que gostaria de esclarecer?',
            'Se já tratou disso, pode responder para actualizarmos a informação?',
            'Como prefere que a secretaria lhe apoie nesta situação?',
        ];
    }
}

if (!function_exists('sige_wpp_conversational_openers')) {
    function sige_wpp_conversational_openers($tipo): array {
        $s = function_exists('sige_wpp_tpl_saudacao_temporal') ? sige_wpp_tpl_saudacao_temporal() : 'Bom dia';
        if (sige_wpp_is_receipt_type($tipo)) {
            return [
                $s . '. Confirmamos que o pagamento foi recebido e registado.',
                $s . '. A secretaria confirma que o pagamento ficou registado.',
                $s . '. O pagamento consta como recebido na secretaria.',
                $s . '. Fica confirmada a recepção do pagamento.',
                $s . '. Partilhamos a confirmação do pagamento registado.',
            ];
        }
        if (sige_wpp_is_billing_type($tipo)) {
            return [
                $s . '. Partilhamos consigo uma informação financeira da escola.',
                $s . '. A secretaria tem uma informação financeira para confirmar consigo.',
                $s . '. Deixamos consigo uma actualização financeira.',
                $s . '. Partilhamos a actualização da mensalidade.',
                $s . '. Escrevemos para manter a informação financeira alinhada consigo.',
            ];
        }
        return [
            $s . '. A secretaria gostaria de confirmar uma situação consigo.',
            $s . '. Passamos para verificar uma informação com calma.',
            $s . '. A escola gostaria de alinhar uma informação consigo.',
            $s . '. Há uma informação que gostaríamos de confirmar consigo.',
            $s . '. A secretaria está disponível para esclarecer esta situação.',
        ];
    }
}

if (!function_exists('sige_wpp_strip_old_footers')) {
    function sige_wpp_strip_old_footers($message) {
        $message = (string)$message;
        $patterns = [
            '/\n*Se preferir não receber avisos por WhatsApp, responda PARAR\.?/iu',
            '/\n*Para continuar a receber avisos da escola por WhatsApp, responda por aqui\. Se preferir parar, responda NÃO ou PARAR\.?/iu',
            '/\n*Obrigado!?\s*$/iu',
            '/\n*Obrigado pela compreensão\.?\s*$/iu',
        ];
        foreach ($patterns as $pat) $message = preg_replace($pat, '', $message);
        return trim($message);
    }
}

if (!function_exists('sige_wpp_humanize_message')) {
    function sige_wpp_humanize_message($message, $tipo = '') {
        $message = trim(wp_strip_all_tags((string)$message));
        $message = preg_replace("/\r\n|\r/", "\n", $message);
        $message = preg_replace('/[ \t]+/', ' ', $message);
        $message = preg_replace("/\n{3,}/", "\n\n", $message);
        $message = sige_wpp_strip_old_footers($message);
        $message = str_replace([
            'Solicitamos a regularização o mais breve possível.',
            'Efectue o pagamento atempadamente para evitar encargos adicionais.',
            'Por favor efectue o pagamento até à data indicada.',
            'Regularize imediatamente.',
            'Evite penalizações.',
        ], [
            'Quando puder, por favor confirme a situação com a secretaria.',
            'Quando puder, confirme com a secretaria se a informação está correcta.',
            'Agradecemos que confirme com a secretaria se a informação está correcta.',
            'A secretaria está disponível para confirmar consigo.',
            'Se houver alguma dúvida, responda por aqui para a escola ajudar.',
        ], $message);

        $openers = sige_wpp_conversational_openers($tipo);
        $questions = sige_wpp_conversational_questions($tipo);
        $prefix = sige_wpp_pick($openers);
        $question = sige_wpp_pick($questions);

        if (!preg_match('/^\s*(Olá|Bom dia|Boa tarde|Boa noite|Saudações)\b/iu', $message)) {
            $message = $prefix . "\n\n" . ltrim($message);
        }

        if (sige_wpp_is_collection_type($tipo)) {
            if (stripos($message, 'Se já regularizou') === false && stripos($message, 'Se já tratou') === false) {
                $message .= "\n\nSe já tratou desta situação, responda por aqui para a secretaria actualizar a informação.";
            }
        }

        // Toda mensagem financeira deve terminar como conversa, não como ordem.
        $trim = rtrim($message);
        if (!preg_match('/\?\s*$/u', $trim)) {
            $message = $trim . "\n\n" . $question;
        }
        return trim($message);
    }
}

if (!function_exists('sige_wpp_pending_receipt_key')) {
    function sige_wpp_pending_receipt_key($escola_id, $telefone): string {
        $phone = function_exists('sige_wpp_normalize_inbound_phone') ? sige_wpp_normalize_inbound_phone($telefone) : preg_replace('/[^0-9]/', '', (string)$telefone);
        return 'sige_wpp_pending_receipt_' . (int)$escola_id . '_' . md5($phone);
    }
}

if (!function_exists('sige_wpp_store_pending_receipt')) {
    function sige_wpp_store_pending_receipt($escola_id, $telefone, $aluno_id, array $links, $base_message = ''): void {
        $payload = [
            'escola_id' => (int)$escola_id,
            'telefone' => function_exists('sige_wpp_normalize_inbound_phone') ? sige_wpp_normalize_inbound_phone($telefone) : preg_replace('/[^0-9]/', '', (string)$telefone),
            'aluno_id' => (int)$aluno_id,
            'links' => array_values(array_unique(array_filter(array_map('esc_url_raw', $links)))),
            'base_message' => wp_strip_all_tags((string)$base_message),
            'created_at' => current_time('mysql'),
        ];
        set_transient(sige_wpp_pending_receipt_key($escola_id, $telefone), $payload, 48 * HOUR_IN_SECONDS);
    }
}

if (!function_exists('sige_wpp_get_pending_receipt')) {
    function sige_wpp_get_pending_receipt($escola_id, $telefone) {
        $data = get_transient(sige_wpp_pending_receipt_key($escola_id, $telefone));
        return is_array($data) ? $data : null;
    }
}

if (!function_exists('sige_wpp_clear_pending_receipt')) {
    function sige_wpp_clear_pending_receipt($escola_id, $telefone): void {
        delete_transient(sige_wpp_pending_receipt_key($escola_id, $telefone));
    }
}

if (!function_exists('sige_wpp_receipt_request_message')) {
    function sige_wpp_receipt_request_message($base, $tipo): string {
        $base = sige_wpp_remove_links((string)$base);
        $base = sige_wpp_strip_old_footers($base);
        $base = trim($base);
        // Não deixar textos longos e cheios de detalhes; confirmação deve ser leve.
        if (mb_strlen($base) > 700) {
            $base = mb_substr($base, 0, 680) . '...';
        }
        $open = sige_wpp_pick(sige_wpp_conversational_openers('recibo'));
        $q = sige_wpp_pick(sige_wpp_conversational_questions('recibo'));
        if ($base === '') {
            return trim($open . "\n\n" . $q);
        }
        if (!preg_match('/^\s*(Olá|Bom dia|Boa tarde|Boa noite|Saudações)\b/iu', $base)) {
            $base = $open . "\n\n" . $base;
        }
        if (!preg_match('/\?\s*$/u', $base)) $base .= "\n\n" . $q;
        return trim($base);
    }
}

if (!function_exists('sige_wpp_receipt_link_message')) {
    function sige_wpp_receipt_link_message(array $links): string {
        $opens = [
            'Claro. Segue o link do recibo:',
            'Combinado. Enviamos abaixo o link do recibo:',
            'Segue o link para descarregar o recibo:',
            'A secretaria partilha abaixo o link do recibo:',
            'Obrigado pela confirmação. Segue o link do recibo:',
            'Certo. A secretaria envia abaixo o link do recibo:',
            'Pode descarregar o recibo por aqui:',
            'Como pediu, segue o link do recibo:',
            'Segue o comprovativo em formato digital:',
            'Confirmado. Aqui está o link do recibo:',
        ];
        $qs = [
            'Conseguiu abrir correctamente?',
            'Se tiver alguma dificuldade em abrir, pode responder por aqui?',
            'Está a conseguir aceder ao recibo?',
            'Quer que a secretaria confirme mais alguma coisa?',
            'Ficou tudo certo para si?',
        ];
        return trim(sige_wpp_pick($opens) . "\n" . implode("\n", $links) . "\n\n" . sige_wpp_pick($qs));
    }
}

if (!function_exists('sige_wpp_queue_receipt_link_after_yes')) {
    function sige_wpp_queue_receipt_link_after_yes($escola_id, $telefone, array $pending): bool {
        if (!sige_tenant_write_guard((int) $escola_id, 'sige_wpp_queue_receipt_link_after_yes')) { return false; }
        global $wpdb;
        $links = $pending['links'] ?? [];
        if (empty($links)) return false;
        $t = $wpdb->prefix . 'sige_whatsapp_queue';
        $msg = sige_wpp_receipt_link_message($links);
        $delay = rand(0, 30); // v12.9.68: link manual sob pedido sai quase imediato, sem massa.
        $data = [
            'escola_id' => (int)$escola_id,
            'telefone' => function_exists('sige_wpp_normalize_inbound_phone') ? sige_wpp_normalize_inbound_phone($telefone) : preg_replace('/[^0-9]/', '', (string)$telefone),
            'mensagem' => $msg,
            'tipo' => 'recibo_link_solicitado',
            'aluno_id' => (int)($pending['aluno_id'] ?? 0),
            'status' => 'pendente',
            'tentativas' => 0,
            'criado_em' => current_time('mysql'),
            'enviado_em' => null,
            'erro' => null,
        ];
        if (function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('scheduled_at')) $data['scheduled_at'] = wp_date('Y-m-d H:i:s', current_time('timestamp') + $delay);
        if (function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('priority')) $data['priority'] = 1;
        if (function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('risk_flags')) $data['risk_flags'] = 'link_sob_pedido';
        if (function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('guardian_meta')) $data['guardian_meta'] = wp_json_encode(['version'=>'12.9.68','motivo'=>'recibo_link_manual_sob_pedido','delay'=>$delay]);
        $ok = (bool)$wpdb->insert($t, $data);
        if ($ok) sige_wpp_clear_pending_receipt($escola_id, $telefone);
        if ($ok && function_exists('sige_fin_log')) sige_fin_log('wpp_recibo_link_sob_pedido_enfileirado', ['telefone'=>$data['telefone'], 'aluno_id'=>$data['aluno_id']]);
        return $ok;
    }
}

if (!function_exists('sige_wpp_prepare_queue_messages')) {
    function sige_wpp_prepare_queue_messages($escola_id, $telefone, $tipo, $mensagem, $aluno_id = 0) {
        $state = sige_wpp_get_state($escola_id);
        $tipo_key = sanitize_key((string)$tipo);
        $msg = sige_wpp_humanize_message($mensagem, $tipo_key);
        $items = [];
        $links = sige_wpp_extract_links($msg);

        // Pagamento/recibo: confirmar pagamento e perguntar antes de enviar qualquer link.
        if (!empty($links) && sige_wpp_is_receipt_type($tipo_key)) {
            sige_wpp_store_pending_receipt((int)$escola_id, $telefone, (int)$aluno_id, $links, $msg);
            $items[] = [
                'message' => sige_wpp_receipt_request_message($msg, $tipo_key),
                'delay' => 0,
                'priority' => 1,
                'flags' => 'recibo_link_aguarda_pedido_manual',
            ];
            return $items;
        }

        // Outros links continuam separados, mas com atraso conservador.
        if (!empty($links)) {
            $base = sige_wpp_remove_links($msg);
            $items[] = [
                'message' => $base ?: sige_wpp_humanize_message('Confirmamos uma actualização da escola.', $tipo_key),
                'delay' => rand(60, 240),
                'priority' => 3,
                'flags' => 'sem_link_primeiro',
            ];
            $delay = rand(900, 3600); // 15 a 60 minutos
            if ($state && $state->mode === 'recovery') {
                $d = sige_wpp_recovery_day($state);
                if ($d <= 1) $delay = DAY_IN_SECONDS + rand(3600, 7200);
                elseif ($d === 2) $delay = rand(3600, 10800);
            }
            $items[] = [
                'message' => "Como combinado, segue a informação em link:\n" . implode("\n", $links) . "\n\nConseguiu abrir correctamente?",
                'delay' => $delay,
                'priority' => 7,
                'flags' => 'link_atrasado',
            ];
            return $items;
        }

        $is_receipt = sige_wpp_is_receipt_type($tipo_key);
        $items[] = [
            'message' => $msg,
            'delay' => $is_receipt ? 0 : rand(60, 300),
            'priority' => $is_receipt ? 1 : 5,
            'flags' => $is_receipt ? 'recibo_imediato_sem_link' : 'conversacional',
        ];
        return $items;
    }
}

// Ritmo global mais conservador para escolas grandes: um item por ciclo da queue.
add_filter('sige_wpp_queue_batch_limit', function($limit) { return 1; }, 20);
add_filter('sige_wpp_normal_daily_limit', function($limit) { return function_exists('sige_wpp_health_effective_daily_limit') ? sige_wpp_health_effective_daily_limit(function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0, 250) : 250; }, 999);
add_filter('sige_wpp_normal_daily_link_limit', function($limit) { return min((int)$limit, 80); }, 999);

if (!function_exists('sige_wpp_contact_is_warm')) {
    function sige_wpp_contact_is_warm($contact) {
        if (!$contact) return false;
        if (in_array((string)$contact->consent_status, ['yes','optin'], true)) return true;
        if ((int)$contact->replies_count > 0) return true;
        return (int)$contact->heat_score >= 2;
    }
}

if (!function_exists('sige_wpp_can_send_queue_item')) {
    function sige_wpp_can_send_queue_item($q) {
        global $wpdb;
        $eid = (int)($q->escola_id ?? 0);
        $state = sige_wpp_get_state($eid);
        $nowTs = current_time('timestamp');

        // v12.9.75 - Health Mode PRO: estado do número, limite dinâmico e intervalo global.
        if (function_exists('sige_wpp_health_can_attempt')) {
            $health = sige_wpp_health_can_attempt($q, []);
            if (empty($health['ok'])) {
                return ['ok' => false, 'reason' => (string)($health['reason'] ?? 'Health Mode activo')];
            }
        }

        if ($state && $state->mode === 'paused') {
            if (!empty($state->paused_until) && strtotime($state->paused_until) > $nowTs) {
                return ['ok'=>false, 'reason'=>'Instância em pausa automática até ' . $state->paused_until];
            }
            sige_wpp_set_state($eid, ['mode'=>'recovery', 'paused_until'=>null, 'recovery_started_at'=>sige_wpp_now()]);
            $state = sige_wpp_get_state($eid);
        }

        // v12.9.68 - janela operacional oficial: 07:30-19:30 Africa/Maputo.
        $tz = new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo');
        $hm = (int) wp_date('Hi', null, $tz);
        if ($hm < 730 || $hm > 1930) {
            return ['ok'=>false, 'reason'=>'Fora da janela segura de envio (07:30-19:30 Maputo)'];
        }

        $limit = sige_wpp_daily_limit_for_state($state);
        $sent_today = function_exists('sige_notify_count_sent_today') ? (int)sige_notify_count_sent_today($eid) : (int)($state->daily_sent ?? 0);
        if ($sent_today >= $limit) {
            return ['ok'=>false, 'reason'=>'Limite diário actual atingido (' . (int)$limit . ' mensagens/dia)'];
        }

        $telefone = (string)($q->telefone ?? '');
        $contact = sige_wpp_get_contact($eid, $telefone);
        if ($contact) {
            if (in_array((string)$contact->consent_status, ['no','optout','blocked'], true)) {
                return ['ok'=>false, 'reason'=>'Contacto em opt-out/bloqueado'];
            }
            if (!empty($contact->blocked_until) && strtotime($contact->blocked_until) > $nowTs) {
                return ['ok'=>false, 'reason'=>'Contacto temporariamente em repouso'];
            }
            if (!empty($contact->last_sent_at) && (strtotime($contact->last_sent_at) > ($nowTs - 30))) {
                return ['ok'=>false, 'reason'=>'Intervalo mínimo por contacto ainda não cumprido'];
            }
        }

        if ($state && $state->mode === 'recovery' && !sige_wpp_contact_is_warm($contact)) {
            $tipo = strtolower((string)($q->tipo ?? ''));
            $msg  = (string)($q->mensagem ?? '');
            $is_light = (strpos($tipo, 'recibo') !== false || strpos($tipo, 'pagamento') !== false || strpos($msg, 'responda por aqui') !== false);
            if (!$is_light) {
                return ['ok'=>false, 'reason'=>'Recovery Mode: contacto frio adiado'];
            }
        }

        if (sige_wpp_message_has_link((string)$q->mensagem)) {
            $linkLimit = sige_wpp_link_limit_for_state($state);
            if ($state && (int)$state->daily_links >= $linkLimit) {
                return ['ok'=>false, 'reason'=>'Limite diário de links atingido'];
            }
        }

        return ['ok'=>true, 'reason'=>'ok'];
    }
}

if (!function_exists('sige_wpp_guardian_mark_sent')) {
    function sige_wpp_guardian_mark_sent($q) {
        $eid = (int)($q->escola_id ?? 0);
        $state = sige_wpp_get_state($eid);
        sige_wpp_contact_bump($eid, (string)$q->telefone, [
            'last_sent_at' => sige_wpp_now(),
            'sent_count' => (int)($state->daily_sent ?? 0) + 1,
            'heat_score' => 1,
        ]);
        sige_wpp_set_state($eid, [
            'daily_sent' => (int)($state->daily_sent ?? 0) + 1,
            'daily_links' => (int)($state->daily_links ?? 0) + (sige_wpp_message_has_link((string)$q->mensagem) ? 1 : 0),
        ]);
    }
}

if (!function_exists('sige_wpp_guardian_mark_failed')) {
    function sige_wpp_guardian_mark_failed($q, $reason = '') {
        $eid = (int)($q->escola_id ?? 0);
        $state = sige_wpp_get_state($eid);
        $failed = (int)($state->daily_failed ?? 0) + 1;
        if (function_exists('sige_wpp_health_error_is_critical') && sige_wpp_health_error_is_critical($reason)) {
            sige_wpp_set_state($eid, [
                'mode' => 'review',
                'last_risk_reason' => substr('Sinal crítico WhatsApp/Z-API: ' . (string)$reason, 0, 255),
            ]);
            // Crítico real deve ficar em revisão; não rebaixar logo para pausa por contador acumulado.
            return;
        }
        sige_wpp_contact_bump($eid, (string)$q->telefone, [
            'failed_count' => $failed,
            'heat_score' => -2,
        ]);
        sige_wpp_set_state($eid, [
            'daily_failed' => $failed,
            'last_risk_reason' => substr((string)$reason, 0, 255),
        ]);
        $sent = max(1, (int)($state->daily_sent ?? 0));
        if ($failed >= 5 && ($failed / $sent) >= 0.35) {
            if (function_exists('sige_wpp_health_set_mode')) {
                sige_wpp_health_set_mode($eid, 'paused', 'Falhas elevadas no envio WhatsApp');
            } else {
                sige_wpp_pause_instance($eid, 2, 'Falhas elevadas no envio WhatsApp');
            }
        } elseif ($failed >= 3) {
            sige_wpp_activate_recovery($eid, 'Falhas recentes no envio WhatsApp');
        }
    }
}

// -----------------------------------------------------------------------------
// WEBHOOK REST - respostas dos encarregados e status Z-API
// -----------------------------------------------------------------------------
add_action('rest_api_init', function () {
    if (function_exists('sige_sec_whatsapp_webhook_enabled') && !sige_sec_whatsapp_webhook_enabled()) {
        return;
    }
    register_rest_route('sige/v1', '/whatsapp-webhook', [
        'methods' => ['POST'],
        'callback' => 'sige_wpp_webhook_handler',
        'permission_callback' => function (WP_REST_Request $request) {
            return function_exists('sige_sec_validate_rest_secret_or_hmac')
                ? sige_sec_validate_rest_secret_or_hmac($request, 'whatsapp_webhook')
                : new WP_Error('sige_security_unavailable', 'Camada de segurança indisponível.', ['status' => 503]);
        },
    ]);
});

if (!function_exists('sige_wpp_webhook_handler')) {
    function sige_wpp_webhook_handler(WP_REST_Request $request) {
        $payload = $request->get_json_params();
        if (!is_array($payload) || empty($payload)) $payload = $request->get_params();
        $raw = $request->get_body();

        $phone = $payload['phone'] ?? $payload['from'] ?? $payload['sender'] ?? $payload['participantPhone'] ?? '';
        if (!$phone && isset($payload['message']['phone'])) $phone = $payload['message']['phone'];
        if (!$phone && isset($payload['data']['phone'])) $phone = $payload['data']['phone'];

        $text = $payload['text'] ?? $payload['body'] ?? $payload['message'] ?? '';
        if (is_array($text)) $text = $text['text'] ?? $text['body'] ?? '';
        if (!$text && isset($payload['data']['text'])) $text = $payload['data']['text'];
        if (!$text && isset($payload['message']['text'])) $text = $payload['message']['text'];

        $status = strtolower((string)($payload['status'] ?? $payload['event'] ?? $payload['type'] ?? ''));

        // Sem mapeamento explícito de instância, registamos na escola actual/default.
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        $phone = sige_wpp_normalize_inbound_phone($phone);

        if ($phone) {
            $lower = trim(mb_strtolower((string)$text));
            $yes = preg_match('/\b(sim|aceito|autorizo|ok|pode|quero|continuar|recibo|pago)\b/u', $lower);
            $no  = preg_match('/\b(n[aã]o|nao|parar|stop|sair|cancelar|bloquear|remover)\b/u', $lower);

            if ($no) {
                sige_wpp_contact_bump($eid, $phone, [
                    'consent_status' => 'no',
                    'last_reply_at' => sige_wpp_now(),
                    'replies_count' => 1,
                    'heat_score' => -10,
                ]);
                if (function_exists('sige_wpp_clear_pending_receipt')) {
                    sige_wpp_clear_pending_receipt($eid, $phone);
                }
            } elseif ($yes || $text !== '') {
                sige_wpp_contact_bump($eid, $phone, [
                    'consent_status' => $yes ? 'yes' : 'unknown',
                    'last_reply_at' => sige_wpp_now(),
                    'replies_count' => 1,
                    'heat_score' => $yes ? 8 : 3,
                ]);
                // v12.9.68 - resposta positiva aquece o contacto, mas NÃO envia o link automaticamente.
                // O link do recibo fica sob controlo manual da escola na Central WhatsApp.
                if ($yes && function_exists('sige_wpp_get_pending_receipt')) {
                    $pending = sige_wpp_get_pending_receipt($eid, $phone);
                    if ($pending && function_exists('sige_fin_log')) {
                        sige_fin_log('wpp_recibo_link_pedido_aguarda_envio_manual', ['telefone'=>$phone, 'aluno_id'=>(int)($pending['aluno_id'] ?? 0)]);
                    }
                }
            }
        }

        if (strpos($status, 'disconnect') !== false || strpos($status, 'ban') !== false || strpos($status, 'block') !== false || strpos($status, 'restricted') !== false) {
            sige_wpp_pause_instance($eid, 48, 'Webhook reportou risco/restrição: ' . $status);
        }

        return new WP_REST_Response(['ok'=>true, 'version'=>SIGE_WPP_RECOVERY_VERSION], 200);
    }
}

// -----------------------------------------------------------------------------
// AJAX ADMIN - activar recuperação manualmente se uma escola sofreu restrição.
// -----------------------------------------------------------------------------
add_action('wp_ajax_sige_wpp_activar_recovery', function () {
    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director') && !current_user_can('sige_admin_ti')) {
        wp_send_json_error('Sem permissão.');
    }
    $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    sige_wpp_activate_recovery($eid, 'activação manual');
    wp_send_json_success('Recovery Mode activado para esta escola/instância.');
});
