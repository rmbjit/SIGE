<?php
/**
 * SIGE SoftGenial - Settings Audit
 *
 * Auditoria unificada de mudanças de configuração e de tentativas negadas.
 * Faz pivot para a função sige_log() do plugin (que existe em audit-hooks).
 *
 * Não duplica registos: o ficheiro includes/audit-hooks.php continua a logar
 * eventos de alto nível (ex: config_avancada_alterada). Esta classe acrescenta
 * granularidade por chave, sem alterar o resto.
 *
 * @since v12.10.0
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Settings_Audit')) {

    final class SIGE_Settings_Audit {

        /** Regista uma mudança de valor de uma chave. */
        public static function log_change(string $key, array $meta, $old, $new, string $actor = 'system'): void {
            $payload = [
                'chave'      => $key,
                'rotulo'     => (string)($meta['label'] ?? $key),
                'dominio'    => (string)($meta['domain'] ?? ''),
                'fonte'      => (string)($meta['source'] ?? ''),
                'tipo'       => (string)($meta['type'] ?? ''),
                'sensivel'   => !empty($meta['sensitive']),
                'antes'      => self::shadow_value($old, $meta),
                'depois'     => self::shadow_value($new, $meta),
                'actor'      => $actor,
                'versao'     => defined('SIGE_VERSION') ? SIGE_VERSION : 'N/D',
            ];

            if (function_exists('sige_log')) {
                sige_log('settings_change', 'configuracoes', $payload);
            }

            do_action('sige_settings_changed_logged', $key, $payload);
        }

        /** Regista uma tentativa de acesso/escrita negada por Policy. */
        public static function log_access_denied(string $key, string $reason, string $actor = 'unknown'): void {
            $meta = SIGE_Settings_Registry::get($key) ?: ['label' => $key];
            $payload = [
                'chave'  => $key,
                'rotulo' => (string)($meta['label'] ?? $key),
                'motivo' => $reason,
                'actor'  => $actor,
                'versao' => defined('SIGE_VERSION') ? SIGE_VERSION : 'N/D',
            ];
            if (function_exists('sige_log')) {
                sige_log('settings_access_denied', 'configuracoes', $payload);
            }
        }

        /**
         * Substitui valores sensíveis por marcador (não loga segredos em claro).
         */
        private static function shadow_value($value, array $meta): string {
            $sensitive = !empty($meta['sensitive']) || !empty($meta['masked']);
            if ($sensitive) {
                $len = is_string($value) ? strlen($value) : (is_array($value) ? count($value) : 0);
                return '[sensível • len=' . $len . ']';
            }
            if (is_array($value) || is_object($value)) {
                $j = wp_json_encode($value);
                return is_string($j) ? (strlen($j) > 200 ? substr($j, 0, 200) . '…' : $j) : '';
            }
            $s = (string)$value;
            return strlen($s) > 200 ? substr($s, 0, 200) . '…' : $s;
        }
    }
}
