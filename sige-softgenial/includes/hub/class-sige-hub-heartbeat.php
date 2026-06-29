<?php
/**
 * SIGE_Hub_Heartbeat - Envio periódico de telemetria para o Hub Central.
 *
 * Recolhe métricas locais e envia para /telemetria/heartbeat.
 *
 * Princípios:
 *   - Falha silenciosamente. Nunca interrompe o admin.
 *   - Só envia se o Hub Client estiver minimamente configurado.
 *   - Idempotente - pode tentar múltiplas vezes sem efeitos colaterais.
 *   - Rate-limited do lado do servidor (5 min) - esta classe respeita o cron diário.
 *
 * @since 12.3.0 (Fase 1B)
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Hub_Heartbeat')) {
    final class SIGE_Hub_Heartbeat {

        public static function boot(): void {
            add_filter('cron_schedules', [__CLASS__, 'add_cron_schedule']);
            add_action('sige_hub_heartbeat_send', [__CLASS__, 'send']);

            // v12.9.51: heartbeat operacional deve acompanhar o cron real do VPS.
            // Se versões anteriores tinham agendado como "daily", removemos e recriamos.
            $ts = wp_next_scheduled('sige_hub_heartbeat_send');
            $current_schedule = $ts ? wp_get_schedule('sige_hub_heartbeat_send') : '';
            if (!$ts || $current_schedule !== 'sige_every_5_minutes') {
                self::clear_scheduled_events();
                wp_schedule_event(time() + 60, 'sige_every_5_minutes', 'sige_hub_heartbeat_send');
            }
        }

        public static function add_cron_schedule(array $schedules): array {
            if (!isset($schedules['sige_every_5_minutes'])) {
                $schedules['sige_every_5_minutes'] = [
                    'interval' => 5 * MINUTE_IN_SECONDS,
                    'display'  => 'A cada 5 minutos (SIGE Hub heartbeat)',
                ];
            }
            return $schedules;
        }

        private static function clear_scheduled_events(): void {
            while ($ts = wp_next_scheduled('sige_hub_heartbeat_send')) {
                wp_unschedule_event($ts, 'sige_hub_heartbeat_send');
            }
        }

        public static function deactivate(): void {
            self::clear_scheduled_events();
        }

        // ====================================================================
        // ENVIO
        // ====================================================================

        public static function send(): array {
            $endpoint = SIGE_Hub_Client::resolve_heartbeat_endpoint();
            $api_key = sanitize_text_field((string) get_option('sige_license_key', ''));
            $domain = function_exists('sige_hub_get_domain') ? sige_hub_get_domain() : '';

            if ($endpoint === '' || $api_key === '' || $domain === '') {
                return ['ok' => false, 'reason' => 'Hub não configurado.'];
            }

            $payload = self::collect_payload($api_key, $domain);
            $body_json = wp_json_encode($payload);
            $headers = [
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept' => 'application/json',
            ];
            if (function_exists('sige_sec_hub_headers')) {
                $headers = array_merge($headers, sige_sec_hub_headers($api_key, (string)$body_json, $domain));
            }

            $response = wp_remote_post($endpoint, [
                'timeout' => 10,
                'headers' => $headers,
                'body' => $body_json,
                'sslverify' => true,
            ]);

            if (is_wp_error($response)) {
                update_option('sige_hub_last_heartbeat_error', sanitize_text_field($response->get_error_message()), false);
                return ['ok' => false, 'reason' => 'Hub offline: ' . $response->get_error_message()];
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $body = json_decode((string) wp_remote_retrieve_body($response), true);

            update_option('sige_hub_last_heartbeat_at', time(), false);
            update_option('sige_hub_last_heartbeat_code', $code, false);
            update_option('sige_hub_last_heartbeat_body', is_array($body) ? wp_json_encode($body) : '', false);

            if (is_array($body) && !empty($body['commands']) && is_array($body['commands']) && class_exists('SIGE_Hub_Commands')) {
                SIGE_Hub_Commands::process($body['commands']);
            }

            return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'body' => $body];
        }

        // ====================================================================
        // RECOLHA DE PAYLOAD
        // ====================================================================

        private static function collect_payload(string $api_key, string $domain): array {
            global $wpdb;

            $alunos = self::count_alunos();
            $pagamentos_30d = self::count_pagamentos_30d();
            $errors_24h = self::count_errors_24h();

            return [
                // Auth
                'dominio' => $domain,
                'api_key' => $api_key,

                // Versões
                'plugin_version' => defined('SIGE_VERSION') ? SIGE_VERSION : '',
                'hub_client_version' => defined('SIGE_HUB_VERSION') ? SIGE_HUB_VERSION : '1.0.0',
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'mysql_version' => self::mysql_version(),

                // Métricas
                'alunos_count' => $alunos,
                'pagamentos_30d' => $pagamentos_30d,
                'errors_24h' => $errors_24h,

                // Saúde operacional (painel por tenant no Hub)
                'wpp_queue_pendentes' => self::count_queue($wpdb->prefix . 'sige_whatsapp_queue', 'pendente'),
                'wpp_queue_falhadas_24h' => self::count_queue_falhas_24h($wpdb->prefix . 'sige_whatsapp_queue'),
                'email_queue_pendentes' => self::count_queue($wpdb->prefix . 'sige_email_queue', 'pendente'),
                'cron_wpp_proximo' => self::next_cron('sige_processar_whatsapp_queue'),
                'cron_diario_proximo' => self::next_cron('sige_evento_diario'),
                'login_locks_24h' => self::count_login_locks_24h(),

                // Registry local de funcionalidades/permissões (12.8.2)
                'feature_registry_keys' => function_exists('sige_feature_registry_keys') ? sige_feature_registry_keys() : [],
                'feature_registry_manifest' => function_exists('sige_feature_registry_manifest') ? sige_feature_registry_manifest() : [],

                // Contexto
                'site_url' => home_url(),
                'admin_email' => get_bloginfo('admin_email'),
                'language' => get_bloginfo('language'),
                'timezone' => function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC',
                'sent_at' => current_time('mysql'),
                'sent_at_utc' => gmdate('Y-m-d H:i:s'),
            ];
        }

        // ====================================================================
        // RECOLHA DE MÉTRICAS (defensiva - tolera ausência de tabelas)
        // ====================================================================

        private static function count_alunos(): ?int {
            global $wpdb;
            $table = $wpdb->prefix . 'sige_alunos';
            if (!self::table_exists($table)) return null;
            $result = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            return is_numeric($result) ? (int)$result : null;
        }

        private static function count_pagamentos_30d(): ?int {
            global $wpdb;
            $table = $wpdb->prefix . 'sige_pagamentos';
            if (!self::table_exists($table)) return null;
            $cutoff = gmdate('Y-m-d H:i:s', time() - (30 * DAY_IN_SECONDS));
            // Tenta com coluna 'data_pagamento' (convenção SIGE), depois 'created_at'
            foreach (['data_pagamento', 'data', 'created_at'] as $col) {
                if (self::column_exists($table, $col)) {
                    $result = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table} WHERE {$col} >= %s",
                        $cutoff
                    ));
                    return is_numeric($result) ? (int)$result : null;
                }
            }
            return null;
        }

        private static function count_errors_24h(): ?int {
            global $wpdb;
            // Se SIGE_Logger guardar em tabela
            $table = $wpdb->prefix . 'sige_logs';
            if (self::table_exists($table)) {
                $cutoff = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
                if (self::column_exists($table, 'level') && self::column_exists($table, 'created_at')) {
                    $result = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table} WHERE level IN ('error','critical') AND created_at >= %s",
                        $cutoff
                    ));
                    return is_numeric($result) ? (int)$result : null;
                }
            }
            return 0; // não há logger ou não há tabela: assumir zero
        }

        private static function mysql_version(): string {
            global $wpdb;
            return (string) $wpdb->get_var('SELECT VERSION()');
        }

        /** Conta itens de uma fila por status. Devolve null se a tabela não existir. */
        private static function count_queue(string $table, string $status): ?int {
            global $wpdb;
            if (!self::table_exists($table)) return null;
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status
            ));
            return is_numeric($result) ? (int)$result : null;
        }

        /** Falhas de envio WhatsApp nas últimas 24h (status falhado/erro). */
        private static function count_queue_falhas_24h(string $table): ?int {
            global $wpdb;
            if (!self::table_exists($table) || !self::column_exists($table, 'criado_em')) return null;
            $cutoff = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status IN ('falhado','erro','falhada') AND criado_em >= %s",
                $cutoff
            ));
            return is_numeric($result) ? (int)$result : null;
        }

        /** Próxima execução agendada de um hook de cron (UTC) ou null. */
        private static function next_cron(string $hook): ?string {
            $ts = wp_next_scheduled($hook);
            return $ts ? gmdate('Y-m-d H:i:s', (int)$ts) : null;
        }

        /** Bloqueios do escudo de login nas últimas 24h, via auditoria. */
        private static function count_login_locks_24h(): ?int {
            global $wpdb;
            $table = $wpdb->prefix . 'sige_auditoria';
            if (!self::table_exists($table)) return null;
            $col_data = self::column_exists($table, 'criado_em') ? 'criado_em'
                      : (self::column_exists($table, 'data_hora') ? 'data_hora' : '');
            $col_det = self::column_exists($table, 'detalhes') ? 'detalhes'
                     : (self::column_exists($table, 'contexto') ? 'contexto' : '');
            if ($col_data === '' || $col_det === '') return null;
            $cutoff = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE {$col_det} LIKE %s AND {$col_data} >= %s",
                '%login_shield%lock%', $cutoff
            ));
            return is_numeric($result) ? (int)$result : null;
        }

        private static function table_exists(string $table): bool {
            global $wpdb;
            return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
        }

        private static function column_exists(string $table, string $column): bool {
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare(
                "SHOW COLUMNS FROM `{$table}` LIKE %s",
                $column
            ));
            return $row !== null;
        }
    }
}
