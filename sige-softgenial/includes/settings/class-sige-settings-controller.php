<?php
/**
 * SIGE SoftGenial - Settings Controller
 *
 * Endpoint AJAX único para todas as escritas de configuração.
 * Recebe um array { settings: { 'chave.canonica' => 'valor', ... } } e:
 *   1. Verifica nonce.
 *   2. Filtra para chaves graváveis pelo utilizador (Policy enforce).
 *   3. Sanitiza + valida cada valor (Sanitizer).
 *   4. Grava através do Repository (com cache invalidation e audit).
 *   5. Devolve resumo JSON com changed/errors/count.
 *
 * Para retrocompatibilidade durante a v12.10.0, o ficheiro
 * class-sige-settings-legacy.php intercepta as actions antigas e converte-as
 * para chamadas a este controller. Isto permite que a refundação fique 100%
 * dentro de /includes/settings/ sem tocar em db-handler, email-engine ou
 * whatsapp-engine.
 *
 * @since v12.10.0
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Settings_Controller')) {

    final class SIGE_Settings_Controller {

        const ACTION_SAVE   = 'sige_settings_save';
        const NONCE_ACTION  = 'sige_settings_save_v1';

        public static function init(): void {
            add_action('wp_ajax_' . self::ACTION_SAVE, [__CLASS__, 'handle_save']);
        }

        public static function handle_save(): void {
            if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
                wp_send_json_error(['message' => 'Pedido inválido. Recarregue a página e tente novamente.'], 403);
            }

            $posted = isset($_POST['settings']) && is_array($_POST['settings'])
                ? wp_unslash($_POST['settings'])
                : [];

            $result = self::save_array($posted, ['actor' => 'config_center']);

            if (!$result['ok']) {
                wp_send_json_error([
                    'message' => implode(' ', $result['errors']),
                    'changed' => $result['changed'],
                    'count'   => $result['count'],
                ], 400);
            }

            wp_send_json_success([
                'message' => empty($result['changed']) ? 'Nenhuma alteração detectada.' : 'Configurações guardadas com segurança.',
                'changed' => $result['changed'],
                'count'   => $result['count'],
            ]);
        }

        /**
         * Núcleo reutilizável de gravação. Aplicado por handle_save e pelo
         * interceptor de actions legacy.
         *
         * @param array $kv     Mapa chave_canonica => valor cru.
         * @param array $context ['actor' => 'config_center' | 'legacy_form' | ...]
         * @return array{ok:bool, errors:string[], changed:array, count:int, denied:string[]}
         */
        public static function save_array(array $kv, array $context = []): array {
            $kv = self::normalizar_aparencia_escola($kv);
            $denied = [];
            $allowed_kv = [];

            // Filtragem de Policy: só passa o que o utilizador pode editar.
            foreach ($kv as $key => $value) {
                if (!is_string($key) || $key === '') continue;
                if (SIGE_Settings_Policy::can_edit($key)) {
                    $allowed_kv[$key] = $value;
                } else {
                    $denied[] = $key;
                    if (class_exists('SIGE_Settings_Audit')) {
                        SIGE_Settings_Audit::log_access_denied($key, 'policy.can_edit=false', (string)($context['actor'] ?? 'unknown'));
                    }
                }
            }

            if (empty($allowed_kv)) {
                return [
                    'ok'      => true,
                    'errors'  => [],
                    'changed' => [],
                    'count'   => 0,
                    'denied'  => $denied,
                ];
            }

            $res = SIGE_Settings_Repository::set_many($allowed_kv, $context);
            $res['denied'] = $denied;
            return $res;
        }

        /**
         * Quando a escola altera uma cor da aplicação, o tema da escola deve ser
         * activado automaticamente. Isto evita a experiência confusa de guardar
         * cores personalizadas e continuar a ver o padrão SoftGenial.
         */
        private static function normalizar_aparencia_escola(array $kv): array {
            $color_keys = ['aparencia.primaria', 'aparencia.secundaria', 'aparencia.destaque'];
            $has_color  = false;
            foreach ($color_keys as $key) {
                if (array_key_exists($key, $kv)) { $has_color = true; break; }
            }
            if (!$has_color) return $kv;

            $defaults = [
                'aparencia.primaria'   => '#5a3fd6',
                'aparencia.secundaria' => '#3f2c9f',
                'aparencia.destaque'   => '#34a853',
            ];
            $has_custom = false;
            foreach ($defaults as $key => $default) {
                if (!array_key_exists($key, $kv)) continue;
                $value = self::normalizar_cor_hex($kv[$key], $default);
                $kv[$key] = $value;
                if (strtolower($value) !== strtolower($default)) $has_custom = true;
            }

            if ($has_custom && (string)($kv['aparencia.tema'] ?? '') !== 'escola') {
                $kv['aparencia.tema'] = 'escola';
            }

            // A cor principal da Aparência também alimenta os recibos e documentos.
            // Mantém Marca e documentos como área de logotipos/cabeçalhos/rodapé,
            // mas evita que a escola altere o tema e veja recibos com cor antiga.
            if (array_key_exists('aparencia.primaria', $kv)) {
                $kv['documentos.cor_primaria'] = self::normalizar_cor_hex($kv['aparencia.primaria'], '#5a3fd6');
            }
            return $kv;
        }

        private static function normalizar_cor_hex($value, string $fallback): string {
            $value = is_string($value) ? trim($value) : '';
            if (function_exists('sanitize_hex_color')) {
                $hex = sanitize_hex_color($value);
                if ($hex) return strtolower($hex);
            }
            return strtolower($fallback);
        }

        public static function nonce_field(): string {
            return wp_nonce_field(self::NONCE_ACTION, 'nonce', true, false);
        }

        public static function nonce_value(): string {
            return wp_create_nonce(self::NONCE_ACTION);
        }
    }
}

SIGE_Settings_Controller::init();
