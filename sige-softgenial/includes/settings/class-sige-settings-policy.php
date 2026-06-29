<?php
/**
 * SIGE SoftGenial - Settings Policy
 *
 * Decide quem pode ver e quem pode editar cada chave do Registry,
 * baseado em capabilities do plugin e no modo técnico governado.
 *
 * Princípios:
 *  - "Tech-only" só fica visível/editável quando o modo técnico está activo.
 *  - Capabilities específicas (configuracoes.ver / configuracoes.editar) são
 *    consultadas via sige_page_guard_allows; admin WP continua a passar pelo
 *    bypass natural do super admin.
 *  - Permissões falham fechadas: se a capability não existir, recusa edição.
 *
 * @since v12.10.0
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Settings_Policy')) {

    final class SIGE_Settings_Policy {

        public static function can_view(string $key): bool {
            $meta = SIGE_Settings_Registry::get($key);
            if (!$meta) return false;

            if (!self::has_cap((string)($meta['caps_view'] ?? 'configuracoes.ver'), ['configuracoes.editar'])) {
                return false;
            }

            if (!empty($meta['tech_only']) && !self::is_technical_mode_active()) {
                return false;
            }

            return true;
        }

        public static function can_edit(string $key): bool {
            $meta = SIGE_Settings_Registry::get($key);
            if (!$meta) return false;

            if (!empty($meta['readonly'])) return false;

            if (!self::has_cap((string)($meta['caps_edit'] ?? 'configuracoes.editar'), [])) {
                return false;
            }

            if (!empty($meta['tech_only']) && !self::is_technical_mode_active()) {
                return false;
            }

            return true;
        }

        /**
         * Filtra um array de chaves para apenas as graváveis pelo utilizador actual.
         *
         * @param string[] $keys
         * @return string[]
         */
        public static function filter_writable(array $keys): array {
            return array_values(array_filter($keys, [__CLASS__, 'can_edit']));
        }

        /**
         * Filtra um array de chaves para apenas as visíveis ao utilizador actual.
         */
        public static function filter_viewable(array $keys): array {
            return array_values(array_filter($keys, [__CLASS__, 'can_view']));
        }

        public static function is_technical_mode_active(): bool {
            if (class_exists('SIGE_Settings_Technical_Mode')) {
                return SIGE_Settings_Technical_Mode::is_active();
            }
            return false;
        }

        public static function can_enter_technical_mode(): bool {
            if (class_exists('SIGE_Settings_Technical_Mode')) {
                return SIGE_Settings_Technical_Mode::can_govern();
            }
            if (class_exists('SIGE_Core') && method_exists('SIGE_Core', 'can_access_core_admin')) {
                return SIGE_Core::can_access_core_admin();
            }
            return (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'));
        }

        // ─── Helpers ──────────────────────────────────────────────────────────

        private static function has_cap(string $cap, array $fallback): bool {
            if (function_exists('sige_page_guard_allows')) {
                $list = array_merge([$cap], $fallback);
                return (bool) sige_page_guard_allows(array_values(array_unique($list)), []);
            }
            return (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'));
        }
    }
}
