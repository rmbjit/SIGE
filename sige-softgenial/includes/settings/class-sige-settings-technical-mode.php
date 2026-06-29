<?php
/**
 * SIGE SoftGenial - Technical Mode Governor v12.9.103
 *
 * Substitui o acesso técnico por parâmetro manual no URL por um modo técnico
 * governado, temporário, auditado e restrito à equipa SoftGenial autorizada.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Settings_Technical_Mode')) {
    final class SIGE_Settings_Technical_Mode {
        private const META_UNTIL = '_sige_settings_tech_mode_until';
        private const META_STARTED = '_sige_settings_tech_mode_started_at';
        private const META_NONCE = '_sige_settings_tech_mode_nonce_hint';
        private const TTL_SECONDS = 1800; // 30 minutos

        public static function init(): void {
            add_action('wp_ajax_sige_settings_enter_technical_mode', [__CLASS__, 'ajax_enter']);
            add_action('wp_ajax_sige_settings_exit_technical_mode', [__CLASS__, 'ajax_exit']);
        }

        public static function is_core_tech(): bool {
            if (class_exists('SIGE_Core') && method_exists('SIGE_Core', 'can_access_core_admin')) {
                return (bool) SIGE_Core::can_access_core_admin();
            }
            return (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'));
        }

        public static function can_govern(): bool {
            if (!is_user_logged_in() || !self::is_core_tech()) return false;
            if (function_exists('sige_page_guard_allows')) {
                return (bool) sige_page_guard_allows(['configuracoes.editar','sistema.estado_ver'], []);
            }
            return (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'));
        }

        public static function now(): int {
            return (int) current_time('timestamp');
        }

        public static function until(): int {
            $uid = get_current_user_id();
            if (!$uid) return 0;
            return (int) get_user_meta($uid, self::META_UNTIL, true);
        }

        public static function started_at(): int {
            $uid = get_current_user_id();
            if (!$uid) return 0;
            return (int) get_user_meta($uid, self::META_STARTED, true);
        }

        public static function is_active(): bool {
            if (!self::can_govern()) return false;
            $until = self::until();
            if ($until <= self::now()) {
                self::clear(false);
                return false;
            }
            return true;
        }

        public static function remaining_seconds(): int {
            if (!self::is_active()) return 0;
            return max(0, self::until() - self::now());
        }

        public static function remaining_label(): string {
            $seconds = self::remaining_seconds();
            if ($seconds <= 0) return 'expirado';
            $minutes = (int) ceil($seconds / 60);
            return $minutes . ' min';
        }

        public static function enter(): bool {
            if (!self::can_govern()) return false;
            $uid = get_current_user_id();
            $now = self::now();
            update_user_meta($uid, self::META_STARTED, $now);
            update_user_meta($uid, self::META_UNTIL, $now + self::TTL_SECONDS);
            update_user_meta($uid, self::META_NONCE, wp_generate_password(24, false, false));
            self::audit('modo_tecnico_activado', [
                'ttl_seconds' => self::TTL_SECONDS,
                'expires_at' => date_i18n('Y-m-d H:i:s', $now + self::TTL_SECONDS),
            ]);
            return true;
        }

        public static function exit(): bool {
            if (!self::can_govern()) return false;
            self::clear(true);
            return true;
        }

        private static function clear(bool $audit): void {
            $uid = get_current_user_id();
            if (!$uid) return;
            delete_user_meta($uid, self::META_STARTED);
            delete_user_meta($uid, self::META_UNTIL);
            delete_user_meta($uid, self::META_NONCE);
            if ($audit) {
                self::audit('modo_tecnico_desactivado', ['reason' => 'user_action']);
            }
        }

        public static function ajax_enter(): void {
            if (!check_ajax_referer('sige_settings_technical_mode', 'nonce', false)) {
                wp_send_json_error(['message' => 'Pedido inválido. Actualize a página e tente novamente.'], 403);
            }
            if (!self::can_govern()) {
                wp_send_json_error(['message' => 'Sem permissão para activar o modo técnico SoftGenial.'], 403);
            }
            if (!self::enter()) {
                wp_send_json_error(['message' => 'Não foi possível activar o modo técnico.'], 500);
            }
            wp_send_json_success([
                'message' => 'Modo técnico SoftGenial activado por ' . self::remaining_label() . '.',
                'expires_in' => self::remaining_seconds(),
            ]);
        }

        public static function ajax_exit(): void {
            if (!check_ajax_referer('sige_settings_technical_mode', 'nonce', false)) {
                wp_send_json_error(['message' => 'Pedido inválido. Actualize a página e tente novamente.'], 403);
            }
            if (!self::can_govern()) {
                wp_send_json_error(['message' => 'Sem permissão para desactivar o modo técnico SoftGenial.'], 403);
            }
            self::exit();
            wp_send_json_success(['message' => 'Modo técnico SoftGenial desactivado.']);
        }

        public static function audit(string $action, array $payload = []): void {
            $payload = array_merge([
                'scope' => 'settings_technical_mode',
                'user_id' => get_current_user_id(),
                'environment' => function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production',
            ], $payload);
            if (function_exists('sige_audit_log')) {
                sige_audit_log($action, $payload, 'sistema');
                return;
            }
            global $wpdb;
            if (!isset($wpdb) || !is_object($wpdb)) return;
            $table = $wpdb->prefix . 'sige_logs_auditoria';
            if (!class_exists('SIGE_Settings_Repository') || !SIGE_Settings_Repository::table_exists($table)) return;
            $uid = get_current_user_id();
            $u = $uid ? get_userdata($uid) : null;
            $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
            if ($eid <= 0) { return; }
            $wpdb->insert($table, [
                'escola_id'    => $eid,
                'user_id'      => $uid,
                'user_display' => $u ? ($u->display_name ?: $u->user_login) : 'Sistema',
                'modulo'       => 'sistema',
                'acao'         => $action,
                'detalhes'     => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
                'ip_address'   => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
                'data_hora'    => current_time('mysql'),
            ]);
        }
    }
}

SIGE_Settings_Technical_Mode::init();
