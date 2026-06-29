<?php
/**
 * SoftGenial Core v1.0 Foundation - Licenciamento central com cache local,
 * avisos progressivos e preparação de enforcement leve (sem bloqueios agressivos).
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_License')) {
    final class SIGE_License {
        public static function get_state(): array {
            $cache = get_option('sige_license_cache', []);
            if (!is_array($cache)) $cache = [];

            $grace_days = (int) get_option('sige_license_grace_days', 7);
            $checked_at = isset($cache['checked_at']) ? (int)$cache['checked_at'] : 0;
            $status = sanitize_key($cache['status'] ?? 'unknown');
            $now = time();
            $within_grace = $checked_at > 0 && ($now - $checked_at) <= ($grace_days * DAY_IN_SECONDS);
            $meta = self::build_meta($cache, $status, $checked_at, $grace_days);

            if ($status === 'active') {
                return array_merge([
                    'status' => 'active',
                    'message' => 'Licença activa.',
                    'cached' => true,
                    'checked_at' => $checked_at,
                    'plan' => sanitize_text_field($cache['plan'] ?? 'Completo'),
                    'raw' => $cache,
                ], $meta);
            }

            if ($within_grace) {
                return array_merge([
                    'status' => 'grace',
                    'message' => 'Licença em tolerância local. O sistema continua operacional enquanto aguarda nova validação central.',
                    'cached' => true,
                    'checked_at' => $checked_at,
                    'plan' => sanitize_text_field($cache['plan'] ?? ''),
                    'raw' => $cache,
                ], $meta);
            }

            return array_merge([
                'status' => $status ?: 'unknown',
                'message' => sanitize_text_field($cache['message'] ?? 'Licença ainda não validada no servidor central.'),
                'cached' => !empty($cache),
                'checked_at' => $checked_at,
                'plan' => sanitize_text_field($cache['plan'] ?? ''),
                'raw' => $cache,
            ], $meta);
        }

        public static function validate_now(bool $forced = false): array {
            $server_url = esc_url_raw(get_option('sige_license_server_url', ''));
            $client_id = sanitize_text_field(get_option('sige_license_client_id', ''));
            $license_key = sanitize_text_field(get_option('sige_license_key', ''));

            if ($server_url === '' || $client_id === '' || $license_key === '') {
                return self::store_cache('unknown', 'Servidor central, domínio/ID do cliente ou chave da licença não configurados.', []);
            }

            $payload = [
                'dominio' => home_url(),
                'api_key' => $license_key, // compatibilidade; também segue por Authorization/HMAC.
                'client_id' => $client_id,
                'plugin_version' => defined('SIGE_VERSION') ? SIGE_VERSION : '',
                'core_version' => class_exists('SIGE_Core') ? SIGE_Core::CORE_VERSION : '',
                'forced' => $forced ? 1 : 0,
            ];
            $body_json = wp_json_encode($payload);
            $headers = [
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept' => 'application/json',
            ];
            if (function_exists('sige_sec_hub_headers')) {
                $headers = array_merge($headers, sige_sec_hub_headers($license_key, (string)$body_json, $client_id));
            }

            $response = wp_remote_post($server_url, [
                'timeout' => 12,
                'headers' => $headers,
                'body' => $body_json,
            ]);

            if (is_wp_error($response)) {
                $state = self::get_state();
                if (($state['status'] ?? '') === 'active' || ($state['status'] ?? '') === 'grace') {
                    self::remember_last_attempt(false, $response->get_error_message());
                    return ['ok'=>true, 'message'=>'Servidor central indisponível. A cache local mantém o sistema operacional.', 'state'=>$state];
                }
                self::remember_last_attempt(false, $response->get_error_message());
                return self::store_cache('offline', 'Não foi possível contactar o servidor central: ' . $response->get_error_message(), ['last_error'=>$response->get_error_message()]);
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $body = json_decode((string) wp_remote_retrieve_body($response), true);
            if (!is_array($body)) $body = [];

            if ($code >= 200 && $code < 300 && !empty($body['ok'])) {
                $remote_status = sanitize_key($body['status'] ?? 'activo');
                $local_status = 'active';
                if (in_array($remote_status, ['em_tolerancia', 'grace', 'tolerancia'], true)) {
                    $local_status = 'grace';
                } elseif (in_array($remote_status, ['expirado', 'suspenso', 'cancelado', 'invalid', 'unauthorized'], true)) {
                    $local_status = 'invalid';
                }

                self::remember_last_attempt(true, 'OK');
                return self::store_cache($local_status, sanitize_text_field($body['message'] ?? 'Licença validada com sucesso.'), [
                    'plan' => sanitize_text_field($body['plano'] ?? $body['plan'] ?? 'completo'),
                    'expires_at' => sanitize_text_field($body['expira_em'] ?? $body['expires_at'] ?? ''),
                    'started_at' => sanitize_text_field($body['inicio_em'] ?? $body['started_at'] ?? ''),
                    'modules' => isset($body['features']) && is_array($body['features']) ? array_map('sanitize_key', $body['features']) : self::default_modules_for_plan((string)($body['plano'] ?? $body['plan'] ?? 'completo')),
                    'remote_status' => $remote_status,
                    'license_domain' => sanitize_text_field($body['dominio'] ?? ''),
                    'school_name' => sanitize_text_field($body['escola_nome'] ?? $body['school_name'] ?? ''),
                    'student_limit' => isset($body['limite_alunos']) ? absint($body['limite_alunos']) : 0,
                    'grace_days_remote' => isset($body['dias_tolerancia']) ? absint($body['dias_tolerancia']) : 0,
                ]);
            }

            self::remember_last_attempt(false, 'HTTP ' . $code);
            return self::store_cache('invalid', sanitize_text_field($body['message'] ?? 'Licença não validada pelo servidor central.'), [
                'http_code' => $code,
                'remote_status' => sanitize_key($body['status'] ?? ''),
            ]);
        }

        public static function maybe_auto_validate(): void {
            if (!is_admin()) return;
            if (!get_option('sige_license_key')) return;
            $cache = get_option('sige_license_cache', []);
            $checked_at = is_array($cache) && isset($cache['checked_at']) ? (int)$cache['checked_at'] : 0;
            $last_attempt = (int) get_option('sige_license_last_attempt_at', 0);
            $now = time();

            // Validação silenciosa no admin, limitada para não sobrecarregar o servidor central.
            if (($now - max($checked_at, $last_attempt)) < 6 * HOUR_IN_SECONDS) return;
            self::validate_now(false);
        }

        public static function default_modules_for_plan(string $plan): array {
            $p = sanitize_key(remove_accents($plan));
            if (strpos($p, 'tesour') !== false) {
                return ['financeiro','cobrancas','transporte','portal','auditoria','comunicacao'];
            }
            if (strpos($p, 'institucional') !== false) {
                return ['financeiro','cobrancas','transporte','portal','auditoria','comunicacao','academico','jardim','documentos','portaria','qrcode','website','email_corporativo','customizacao'];
            }
            return ['financeiro','cobrancas','transporte','portal','auditoria','comunicacao','academico','jardim','documentos','portaria','qrcode'];
        }

        public static function should_show_expiry_warning(array $state = null): bool {
            $state = $state ?: self::get_state();
            $days = $state['days_to_expiry'] ?? null;
            return is_int($days) && $days >= 0 && $days <= 7;
        }

        public static function enforcement_preview(array $state = null): array {
            $state = $state ?: self::get_state();
            $status = $state['status'] ?? 'unknown';
            $days = $state['days_to_expiry'] ?? null;
            $plan = $state['plan'] ?? '';

            $level = 'none';
            $title = 'Sem restrições';
            $message = 'A licença está regular. Nenhum bloqueio será aplicado.';
            $actions = [];

            if ($status === 'grace') {
                $level = 'notice';
                $title = 'Tolerância local';
                $message = 'Se o servidor central continuar indisponível ou a licença expirar, o sistema poderá entrar em modo restrito no futuro.';
                $actions = ['Mostrar aviso interno para administradores'];
            } elseif (in_array($status, ['invalid','expired'], true)) {
                $level = 'soft_lock_ready';
                $title = 'Modo restrito preparado';
                $message = 'A licença não está regular. Nesta versão, apenas mostramos avisos; bloqueios reais ainda não estão activos.';
                $actions = ['Preparado para limitar relatórios avançados', 'Preparado para limitar novos envios em massa', 'Nunca apagar ou ocultar dados da escola'];
            } elseif (is_int($days) && $days <= 7 && $days >= 0) {
                $level = 'warning';
                $title = 'Licença próxima do fim';
                $message = 'A licença expira em breve. O sistema deve avisar a administração com antecedência.';
                $actions = ['Mostrar aviso preventivo', 'Registar evento técnico'];
            }

            return compact('level','title','message','actions','plan');
        }

        private static function build_meta(array $cache, string $status, int $checked_at, int $grace_days): array {
            $expires_at = sanitize_text_field($cache['expires_at'] ?? '');
            $days_to_expiry = null;
            $expiry_ts = self::parse_date_to_timestamp($expires_at);
            if ($expiry_ts) {
                $today = strtotime(wp_date('Y-m-d 00:00:00'));
                $days_to_expiry = (int) floor(($expiry_ts - $today) / DAY_IN_SECONDS);
            }

            $cache_age_hours = $checked_at > 0 ? round((time() - $checked_at) / HOUR_IN_SECONDS, 1) : null;
            $modules = isset($cache['modules']) && is_array($cache['modules']) ? array_map('sanitize_key', $cache['modules']) : self::default_modules_for_plan((string)($cache['plan'] ?? 'completo'));

            return [
                'expires_at' => $expires_at,
                'started_at' => sanitize_text_field($cache['started_at'] ?? ''),
                'days_to_expiry' => $days_to_expiry,
                'cache_age_hours' => $cache_age_hours,
                'grace_days' => $grace_days,
                'modules' => $modules,
                'student_limit' => isset($cache['student_limit']) ? absint($cache['student_limit']) : 0,
                'remote_status' => sanitize_key($cache['remote_status'] ?? ''),
                'school_name' => sanitize_text_field($cache['school_name'] ?? ''),
                'license_domain' => sanitize_text_field($cache['license_domain'] ?? ''),
                'last_attempt_at' => (int) get_option('sige_license_last_attempt_at', 0),
                'last_attempt_ok' => (bool) get_option('sige_license_last_attempt_ok', false),
                'last_attempt_message' => sanitize_text_field((string)get_option('sige_license_last_attempt_message', '')),
            ];
        }

        private static function parse_date_to_timestamp(string $date): int {
            if ($date === '') return 0;
            try {
                $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('Africa/Maputo');
                $dt = new DateTime($date . ' 23:59:59', $tz);
                return $dt->getTimestamp();
            } catch (Exception $e) {
                return 0;
            }
        }

        private static function remember_last_attempt(bool $ok, string $message): void {
            update_option('sige_license_last_attempt_at', time(), false);
            update_option('sige_license_last_attempt_ok', $ok ? 1 : 0, false);
            update_option('sige_license_last_attempt_message', sanitize_text_field($message), false);
        }

        private static function store_cache(string $status, string $message, array $extra): array {
            $cache = array_merge([
                'status' => sanitize_key($status),
                'message' => sanitize_text_field($message),
                'checked_at' => time(),
                'domain' => home_url(),
            ], $extra);
            update_option('sige_license_cache', $cache, false);

            if (class_exists('SIGE_Logger')) {
                $level = $status === 'active' ? 'info' : ($status === 'grace' ? 'warning' : 'warning');
                SIGE_Logger::log($level, 'Validação de licença: ' . $message, ['status'=>$status]);
            }

            return [
                'ok' => in_array($status, ['active','grace'], true),
                'message' => $message,
                'state' => self::get_state(),
            ];
        }
    }
}
