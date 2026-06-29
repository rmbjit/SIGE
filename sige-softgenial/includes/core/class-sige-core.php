<?php
/**
 * SoftGenial Core v1.0 Foundation.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Core')) {
    final class SIGE_Core {
        public const CORE_VERSION = '1.0.0-foundation';
        public const PLUGIN_BUILD = '12.2.43-v93-app-shell-redirect-fix';

        public static function boot(): void {
            add_action('admin_init', [__CLASS__, 'maybe_initialize_options']);
            add_action('admin_post_sige_core_save_license_settings', [__CLASS__, 'handle_save_license_settings']);
            add_action('admin_post_sige_core_force_license_check', [__CLASS__, 'handle_force_license_check']);
            add_action('admin_notices', [__CLASS__, 'admin_license_notice']);
            if (class_exists('SIGE_License')) { add_action('admin_init', ['SIGE_License', 'maybe_auto_validate'], 20); }
            add_action('admin_menu', [__CLASS__, 'register_system_status_submenu'], 30);
        }

        /**
         * Acesso técnico restrito para módulos sensíveis do Core.
         * Por defeito, apenas administradores WordPress reais (manage_options).
         */
        public static function can_access_core_admin(): bool {
            $allowed = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'));
            return (bool) apply_filters('sige_core_can_access_admin', $allowed, get_current_user_id());
        }

        public static function deny_core_access(): void {
            if (defined('DOING_AJAX') && DOING_AJAX) {
                wp_send_json_error(['message' => 'Sem permissão para aceder ao Core SoftGenial.'], 403);
            }
            wp_die(
                esc_html__('Sem permissão para aceder à Saúde do Sistema.', 'sige-softgenial'),
                esc_html__('Acesso restrito', 'sige-softgenial'),
                ['response' => 403]
            );
        }

        /**
         * URL canónica da Saúde do Sistema dentro do App Shell do SIGE.
         * Evita regressão de navegação para o dashboard real do WordPress.
         */
        public static function system_status_app_url(array $args = []): string {
            $base = [
                'page' => 'sige-app',
                'view' => 'sige_core_status',
            ];
            return add_query_arg(array_merge($base, $args), admin_url('admin.php'));
        }

        public static function register_system_status_submenu(): void {
            if (!self::can_access_core_admin()) return;
            add_submenu_page(
                'sige-app',
                'Saúde do Sistema',
                'Saúde do Sistema',
                'manage_options',
                'sige_estado_sistema',
                [__CLASS__, 'render_system_status_submenu']
            );
        }

        public static function render_system_status_submenu(): void {
            if (!self::can_access_core_admin()) self::deny_core_access();
            // V93: mesmo quando alguém acede ao submenu técnico antigo, regressa ao App Shell.
            wp_safe_redirect(self::system_status_app_url());
            exit;
        }

        public static function maybe_initialize_options(): void {
            if (get_option('sige_core_version') !== self::CORE_VERSION) {
                update_option('sige_core_version', self::CORE_VERSION, false);
            }
            if (!get_option('sige_license_client_id')) {
                update_option('sige_license_client_id', self::default_client_id(), false);
            }
            if (!get_option('sige_license_server_url')) {
                update_option('sige_license_server_url', 'https://softgenial.edu.mz/wp-json/softgenial/v1/licenca/validar', false);
            }
            if (get_option('sige_license_grace_days', null) === null) {
                update_option('sige_license_grace_days', 7, false);
            }
        }

        public static function default_client_id(): string {
            $host = wp_parse_url(home_url(), PHP_URL_HOST);
            $path = trim((string) wp_parse_url(home_url(), PHP_URL_PATH), '/');
            $base = $host ?: 'softgenial-client';
            if ($path !== '') $base .= '-' . str_replace('/', '-', $path);
            return sanitize_title($base);
        }

        public static function handle_save_license_settings(): void {
            if (!self::can_access_core_admin()) self::deny_core_access();
            check_admin_referer('sige_core_save_license_settings');

            $client_id = isset($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : '';
            $license_key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
            $clear_license_key = !empty($_POST['clear_license_key']);
            $server_url = isset($_POST['server_url']) ? esc_url_raw(wp_unslash($_POST['server_url'])) : '';
            $grace_days = isset($_POST['grace_days']) ? absint($_POST['grace_days']) : 7;

            if ($client_id !== '') update_option('sige_license_client_id', $client_id, false);
            if ($clear_license_key) {
                delete_option('sige_license_key');
            } elseif ($license_key !== '') {
                update_option('sige_license_key', $license_key, false);
            }
            if ($server_url !== '') update_option('sige_license_server_url', $server_url, false);
            update_option('sige_license_grace_days', max(1, min(30, $grace_days)), false);

            if (class_exists('SIGE_Logger')) {
                SIGE_Logger::log('info', 'Configuração de licença actualizada.', ['client_id' => $client_id]);
            }

            wp_safe_redirect(self::system_status_app_url(['saved'=>'1']));
            exit;
        }

        public static function handle_force_license_check(): void {
            if (!self::can_access_core_admin()) self::deny_core_access();
            check_admin_referer('sige_core_force_license_check');

            $result = class_exists('SIGE_License') ? SIGE_License::validate_now(true) : ['ok'=>false, 'message'=>'Classe de licença indisponível.'];
            $status = !empty($result['ok']) ? 'checked' : 'check_failed';

            wp_safe_redirect(self::system_status_app_url(['license'=>$status]));
            exit;
        }

        public static function admin_license_notice(): void {
            if (!self::can_access_core_admin()) return;
            if (!class_exists('SIGE_License')) return;
            if (!isset($_GET['page'])) return;
            $page = sanitize_key(wp_unslash($_GET['page']));
            if (strpos($page, 'sige') === false) return;

            $state = SIGE_License::get_state();
            if (($state['status'] ?? '') === 'active' && !SIGE_License::should_show_expiry_warning($state)) return;

            $message = $state['message'] ?? 'Licença SoftGenial ainda não validada.';
            if (SIGE_License::should_show_expiry_warning($state)) {
                $days = (int)($state['days_to_expiry'] ?? 0);
                $message = 'A licença SoftGenial expira em ' . $days . ' dia(s). Recomenda-se regularizar antes do fim do prazo.';
            }
            echo '<div class="notice notice-warning"><p><strong>SoftGenial:</strong> ' . esc_html($message) . ' <a href="' . esc_url(self::system_status_app_url()) . '">Ver estado do sistema</a>.</p></div>';
        }
    }
}
