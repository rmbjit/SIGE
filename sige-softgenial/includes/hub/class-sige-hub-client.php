<?php
/**
 * SIGE_Hub_Client - Camada Hub Client v3.
 *
 * Corre em paralelo ao SIGE_License existente. Não modifica SIGE_License.
 *
 * Responsabilidades:
 *   1. Chamar /licenca/validar do Hub v3 com payload mínimo
 *   2. Captar campos novos da resposta (features_effective, version_target, rollout_channel)
 *   3. Guardar em wp_option 'sige_hub_state' (separado de sige_license_cache)
 *   4. Cron diário de actualização
 *   5. Tolerância de 7 dias se Hub offline (igual ao SIGE_License)
 *
 * @since 12.3.0 (Fase 1B)
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Hub_Client')) {
    final class SIGE_Hub_Client {

        const STATE_OPTION = 'sige_hub_state';
        const GRACE_DAYS = 7;
        const REFRESH_INTERVAL = 5 * MINUTE_IN_SECONDS;

        public static function boot(): void {
            // Cron - primeira execução em 2 minutos (não 1 hora) para feedback rápido em setup
            add_action('sige_hub_client_refresh', [__CLASS__, 'refresh']);
            add_action('rest_api_init', [__CLASS__, 'routes']);
            if (!wp_next_scheduled('sige_hub_client_refresh')) {
                wp_schedule_event(time() + 2 * MINUTE_IN_SECONDS, 'twicedaily', 'sige_hub_client_refresh');
            }

            // Verificação leve no admin (não mais frequente que REFRESH_INTERVAL)
            add_action('admin_init', [__CLASS__, 'maybe_refresh_in_admin']);
            add_action('admin_init', [__CLASS__, 'enforce_remote_status'], 1);
        }

        public static function deactivate(): void {
            $ts = wp_next_scheduled('sige_hub_client_refresh');
            if ($ts) wp_unschedule_event($ts, 'sige_hub_client_refresh');
        }



        public static function routes(): void {
            register_rest_route('sige/v1', '/hub/instant-refresh', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'instant_refresh'],
                'permission_callback' => function (WP_REST_Request $request) {
                    return function_exists('sige_sec_validate_rest_secret_or_hmac')
                        ? sige_sec_validate_rest_secret_or_hmac($request, 'hub_instant_refresh')
                        : new WP_Error('sige_security_unavailable', 'Camada de segurança indisponível.', ['status' => 503]);
                },
            ]);
        }

        public static function instant_refresh(WP_REST_Request $request) {
            $payload = $request->get_json_params();
            if (!is_array($payload)) $payload = $request->get_params();
            $source = sanitize_key((string)($payload['source'] ?? ''));
            $command_id = absint($payload['command_id'] ?? 0);

            // Endpoint intencionalmente leve: não executa payload externo.
            // Apenas força o cliente a consultar o Hub autenticado com a sua própria chave local.
            update_option('sige_hub_last_instant_refresh_request', [
                'source' => $source,
                'command_id' => $command_id,
                'ip' => sanitize_text_field((string)($_SERVER['REMOTE_ADDR'] ?? '')),
                'received_at' => current_time('mysql'),
            ], false);

            $result = self::refresh();
            $commands_result = get_option('sige_hub_last_commands_result', []);
            return new WP_REST_Response([
                'ok' => empty($result['last_error']),
                'message' => empty($result['last_error']) ? 'Refresh instantâneo executado.' : (string)$result['last_error'],
                'command_id' => $command_id,
                'remote_status' => sanitize_key((string)($result['remote_status'] ?? '')),
                'commands_result' => is_array($commands_result) ? $commands_result : [],
                'server_time' => current_time('mysql'),
            ], empty($result['last_error']) ? 200 : 503);
        }


        // ====================================================================
        // ESTADO
        // ====================================================================

        public static function get_state(): array {
            $state = get_option(self::STATE_OPTION, []);
            if (!is_array($state)) $state = [];

            $now = time();
            $checked_at = (int)($state['checked_at'] ?? 0);
            $age = $checked_at > 0 ? ($now - $checked_at) : null;
            $within_grace = $checked_at > 0 && $age <= (self::GRACE_DAYS * DAY_IN_SECONDS);

            $defaults = [
                'features_effective' => [],
                'features_plan' => [],
                'version_target' => '',
                'version_pinned' => '',
                'rollout_channel' => 'stable',
                'update_available' => false,
                'update_url' => '',
                'schema_version' => '',
                'checked_at' => 0,
                'last_error' => '',
                'within_grace' => $within_grace,
                'age_seconds' => $age,
            ];
            return array_merge($defaults, $state, [
                'within_grace' => $within_grace,
                'age_seconds' => $age,
            ]);
        }

        public static function maybe_refresh_in_admin(): void {
            if (!is_admin()) return;
            if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) return;

            $last = (int) get_option('sige_hub_last_refresh_attempt', 0);
            if ((time() - $last) < self::REFRESH_INTERVAL) return;

            self::refresh();
        }



        public static function enforce_remote_status(): void {
            if (!is_admin()) return;
            $page = isset($_GET['page']) ? sanitize_key((string)$_GET['page']) : '';
            if ($page !== 'sige-app') return;
            $cache = get_option('sige_license_cache', []);
            if (!is_array($cache)) return;
            $remote = sanitize_key((string)($cache['remote_status'] ?? ''));
            if (!in_array($remote, ['suspenso','cancelado'], true)) return;
            $msg = $remote === 'suspenso'
                ? 'Esta escola encontra-se temporariamente suspensa pelo SoftGenial Hub. Contacte a administração da SoftGenial para regularização.'
                : 'Esta licença foi cancelada pelo SoftGenial Hub. Contacte a administração da SoftGenial.';
            wp_die(
                '<div style="max-width:720px;margin:30px auto;font-family:system-ui,-apple-system,Segoe UI,sans-serif;line-height:1.6">'
                . '<h1 style="color:#991b1b;margin-bottom:10px">Acesso temporariamente indisponível</h1>'
                . '<p style="font-size:16px;color:#334155">' . esc_html($msg) . '</p>'
                . '<p style="color:#64748b">Os dados da escola permanecem preservados. Esta acção apenas restringe o acesso operacional ao SIGE.</p>'
                . '</div>',
                'SIGE SoftGenial - licença ' . esc_html($remote),
                ['response' => 403]
            );
        }

        // ====================================================================
        // CHAMADA AO HUB
        // ====================================================================

        public static function refresh(): array {
            update_option('sige_hub_last_refresh_attempt', time(), false);

            $server_url = self::resolve_validate_endpoint();
            $api_key = sanitize_text_field((string) get_option('sige_license_key', ''));
            $domain = function_exists('sige_hub_get_domain') ? sige_hub_get_domain() : '';

            if ($server_url === '' || $api_key === '' || $domain === '') {
                return self::store_state_partial([
                    'last_error' => 'Hub não configurado (URL, API key ou domínio em falta).',
                ]);
            }

            $payload = [
                'dominio' => $domain,
                'api_key' => $api_key, // compatibilidade; o segredo principal também vai em Authorization/HMAC.
                'plugin_version' => defined('SIGE_VERSION') ? SIGE_VERSION : '',
                'hub_client_version' => defined('SIGE_HUB_VERSION') ? SIGE_HUB_VERSION : '1.0.0',
            ];
            $body_json = wp_json_encode($payload);
            $headers = [
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept' => 'application/json',
            ];
            if (function_exists('sige_sec_hub_headers')) {
                $headers = array_merge($headers, sige_sec_hub_headers($api_key, (string)$body_json, $domain));
            }

            $response = wp_remote_post($server_url, [
                'timeout' => 12,
                'headers' => $headers,
                'body' => $body_json,
                'sslverify' => true,
            ]);

            if (is_wp_error($response)) {
                return self::store_state_partial([
                    'last_error' => 'Hub indisponível: ' . $response->get_error_message(),
                ]);
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $body = json_decode((string) wp_remote_retrieve_body($response), true);
            if (!is_array($body)) $body = [];

            if ($code < 200 || $code >= 300 || empty($body['ok'])) {
                return self::store_state_partial([
                    'last_error' => 'Hub respondeu HTTP ' . $code . ': ' . sanitize_text_field((string)($body['message'] ?? '')),
                ]);
            }

            // Sucesso - extrair campos novos v3 (com fallback para campos v2)
            $features_effective = isset($body['features_effective']) && is_array($body['features_effective'])
                ? array_map('sanitize_key', $body['features_effective'])
                : (isset($body['features']) && is_array($body['features']) ? array_map('sanitize_key', $body['features']) : []);

            $features_plan = isset($body['features_plan']) && is_array($body['features_plan'])
                ? array_map('sanitize_key', $body['features_plan'])
                : $features_effective;

            $new_state = [
                'features_effective' => $features_effective,
                'features_plan' => $features_plan,
                'version_target' => sanitize_text_field((string)($body['version_target'] ?? '')),
                'version_pinned' => sanitize_text_field((string)($body['version_pinned'] ?? '')),
                'rollout_channel' => sanitize_key((string)($body['rollout_channel'] ?? 'stable')),
                'update_available' => !empty($body['update_available']),
                'update_url' => esc_url_raw((string)($body['update_url'] ?? '')),
                'schema_version' => sanitize_text_field((string)($body['schema_version'] ?? '')),
                'plan' => sanitize_text_field((string)($body['plano'] ?? 'completo')),
                'remote_status' => sanitize_key((string)($body['status'] ?? 'activo')),
                'message' => sanitize_text_field((string)($body['message'] ?? '')),
                'checked_at' => time(),
                'last_error' => '',
            ];

            // Espelha o estado da licença no cache legado para que suspensão/cancelamento
            // decididos no Hub tenham efeito prático no cliente.
            $remote_status = $new_state['remote_status'];
            $legacy_status = in_array($remote_status, ['suspenso','cancelado','expirado','invalid','unauthorized'], true) ? 'invalid' : ($remote_status === 'em_tolerancia' ? 'grace' : 'active');
            $legacy_cache = [
                'status' => $legacy_status,
                'message' => $new_state['message'] ?: 'Licença sincronizada com o Hub.',
                'plan' => $new_state['plan'],
                'remote_status' => $remote_status,
                'school_name' => sanitize_text_field((string)($body['escola_nome'] ?? '')),
                'license_domain' => sanitize_text_field((string)($body['dominio'] ?? '')),
                'modules' => $features_effective,
                'checked_at' => time(),
            ];
            update_option('sige_license_cache', $legacy_cache, false);

            update_option(self::STATE_OPTION, $new_state, false);
            update_option('sige_hub_last_refresh_ok', time(), false);

            if (!empty($body['commands']) && is_array($body['commands']) && class_exists('SIGE_Hub_Commands')) {
                SIGE_Hub_Commands::process($body['commands']);
            }

            return $new_state;
        }

        // ====================================================================
        // RESOLUÇÃO DE ENDPOINT
        // ====================================================================

        /**
         * Devolve URL do endpoint de validação. Por defeito derivado do server_url
         * configurado para SIGE_License - assim não há configuração duplicada.
         */
        public static function resolve_validate_endpoint(): string {
            // Configuração explícita do Hub (preferencial)
            $hub_url = (string) get_option('sige_hub_validate_url', '');
            if ($hub_url !== '') return $hub_url;

            // Fallback: usar a URL de validação do SIGE_License (já configurada)
            $legacy = (string) get_option('sige_license_server_url', '');
            if ($legacy !== '') return $legacy;

            return '';
        }

        public static function resolve_heartbeat_endpoint(): string {
            $hub_url = (string) get_option('sige_hub_heartbeat_url', '');
            if ($hub_url !== '') return $hub_url;

            // Derivar do endpoint de validação substituindo o último segmento
            $validate = self::resolve_validate_endpoint();
            if ($validate !== '') {
                return preg_replace('#/licenca/validar/?$#', '/telemetria/heartbeat', $validate);
            }
            return '';
        }

        public static function resolve_updates_endpoint(): string {
            $hub_url = (string) get_option('sige_hub_updates_url', '');
            if ($hub_url !== '') return $hub_url;

            $validate = self::resolve_validate_endpoint();
            if ($validate !== '') {
                return preg_replace('#/licenca/validar/?$#', '/updates/info', $validate);
            }
            return '';
        }

        // ====================================================================
        // HELPERS PRIVADOS
        // ====================================================================

        private static function store_state_partial(array $partial): array {
            $current = get_option(self::STATE_OPTION, []);
            if (!is_array($current)) $current = [];
            $merged = array_merge($current, $partial);
            update_option(self::STATE_OPTION, $merged, false);
            return $merged;
        }
    }
}
