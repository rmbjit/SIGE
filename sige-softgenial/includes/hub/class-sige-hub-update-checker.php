<?php
/**
 * SIGE_Hub_Update_Checker - Update checker usando endpoint dinâmico do Hub v3.
 *
 * Substitui o info.json estático por chamada ao endpoint /updates/info
 * que devolve target version por escola (com pinning, channel, frozen).
 *
 * IMPORTANTE: convive com o SIGE_Update_Checker existente. Se o endpoint v3 falhar,
 * cai automaticamente para o checker antigo (info.json estático). Zero quebras.
 *
 * Mecanismo de prioridade:
 *   - SIGE_Update_Checker antigo: prioridade 10 (default)
 *   - SIGE_Hub_Update_Checker novo: prioridade 5 (corre primeiro)
 *
 * Quando o novo regista uma update no transient, o antigo vê e respeita.
 * Se o novo NÃO responder, o antigo segue o seu fluxo normal.
 *
 * @since 12.3.0 (Fase 1B)
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Hub_Update_Checker')) {
    final class SIGE_Hub_Update_Checker {

        const TRANSIENT = 'sige_hub_update_check';
        const CACHE_TTL = 6 * HOUR_IN_SECONDS;

        public static function boot(): void {
            if (!is_admin()) return;
            // Prioridade 99: corre DEPOIS do SIGE_Update_Checker antigo (prio 10).
            // Se Hub respondeu, sobrescrevemos a entrada; se não, o antigo prevalece.
            add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'check_for_update'], 99);
            add_filter('plugins_api', [__CLASS__, 'plugin_info'], 99, 3);
        }

        // ====================================================================
        // CHECK FOR UPDATE
        // ====================================================================

        public static function check_for_update($transient) {
            if (empty($transient->checked)) return $transient;

            $info = self::get_remote_info();
            if (!$info || empty($info->version)) return $transient;

            $current = defined('SIGE_VERSION') ? SIGE_VERSION : '0.0';
            $plugin_slug = self::plugin_slug();

            if (!empty($info->frozen)) {
                // Canal frozen: marcar como up-to-date independentemente da versão
                $transient->no_update[$plugin_slug] = (object) [
                    'slug' => dirname($plugin_slug),
                    'plugin' => $plugin_slug,
                    'new_version' => $current,
                    'url' => 'https://softgenial.edu.mz',
                ];
                return $transient;
            }

            if (version_compare($current, $info->version, '<') && !empty($info->download_url)) {
                $transient->response[$plugin_slug] = (object) [
                    'slug' => dirname($plugin_slug),
                    'plugin' => $plugin_slug,
                    'new_version' => $info->version,
                    'url' => $info->homepage ?? 'https://softgenial.edu.mz',
                    'package' => $info->download_url,
                    'tested' => $info->tested ?? '',
                    'requires' => $info->requires ?? '6.0',
                    'requires_php' => $info->requires_php ?? '7.4',
                ];
            } else {
                $transient->no_update[$plugin_slug] = (object) [
                    'slug' => dirname($plugin_slug),
                    'plugin' => $plugin_slug,
                    'new_version' => $current,
                    'url' => 'https://softgenial.edu.mz',
                ];
            }

            return $transient;
        }

        // ====================================================================
        // PLUGIN DETAILS MODAL
        // ====================================================================

        public static function plugin_info($result, $action, $args) {
            if ($action !== 'plugin_information') return $result;
            if (!isset($args->slug) || $args->slug !== dirname(self::plugin_slug())) return $result;

            $info = self::get_remote_info();
            if (!$info) return $result;

            return (object) [
                'name' => 'SIGE SoftGenial',
                'slug' => dirname(self::plugin_slug()),
                'version' => $info->version ?? '',
                'author' => '<a href="https://rmbjconsulting.com">RMBJ Consultoria</a>',
                'homepage' => $info->homepage ?? 'https://softgenial.edu.mz',
                'download_link' => $info->download_url ?? '',
                'requires' => $info->requires ?? '6.0',
                'requires_php' => $info->requires_php ?? '7.4',
                'tested' => $info->tested ?? '',
                'last_updated' => $info->last_updated ?? '',
                'sections' => [
                    'description' => 'Software de Gestão Integrado para Escolas - Moçambique. Versão dirigida pelo Hub Central por canal: '
                        . esc_html($info->channel ?? 'stable')
                        . (!empty($info->pinned) ? ' (versão bloqueada para esta escola)' : ''),
                    'changelog' => $info->changelog ?? 'Sem notas de versão.',
                ],
            ];
        }

        // ====================================================================
        // CHAMADA AO HUB
        // ====================================================================

        private static function get_remote_info(): ?object {
            $cached = get_transient(self::TRANSIENT);
            if ($cached instanceof \stdClass) return $cached;
            if ($cached === 'none') return null;

            $endpoint = SIGE_Hub_Client::resolve_updates_endpoint();
            $api_key = sanitize_text_field((string) get_option('sige_license_key', ''));
            $client_id = sanitize_text_field((string) get_option('sige_license_client_id', ''));

            if ($endpoint === '' || $api_key === '' || $client_id === '') {
                set_transient(self::TRANSIENT, 'none', HOUR_IN_SECONDS);
                return null;
            }

            // v12.10.140 - não expor api_key em query string. A autenticação vai por
            // Authorization + HMAC em headers; domain/dominio continuam como identificadores.
            $url = add_query_arg([
                'domain' => $client_id,
                'dominio' => $client_id, // alias
            ], $endpoint);
            $body_for_sig = 'domain=' . $client_id;
            $headers = ['Accept' => 'application/json'];
            if (function_exists('sige_sec_hub_headers')) {
                $headers = array_merge($headers, sige_sec_hub_headers($api_key, $body_for_sig, $client_id));
            } else {
                $headers['Authorization'] = 'Bearer ' . $api_key;
            }

            $response = wp_remote_get($url, [
                'timeout' => 10,
                'sslverify' => true,
                'headers' => $headers,
            ]);

            if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
                set_transient(self::TRANSIENT, 'none', HOUR_IN_SECONDS);
                return null;
            }

            $body = json_decode(wp_remote_retrieve_body($response));
            if (!$body || empty($body->version)) {
                set_transient(self::TRANSIENT, 'none', HOUR_IN_SECONDS);
                return null;
            }

            set_transient(self::TRANSIENT, $body, self::CACHE_TTL);
            return $body;
        }

        private static function plugin_slug(): string {
            // Caminho relativo do entry point (ex: sige-softgenial-12-3-0/sige-softgenial.php)
            return plugin_basename(dirname(__FILE__, 3) . '/sige-softgenial.php');
        }
    }
}
