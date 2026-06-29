<?php
/**
 * SoftGenial Core v1.0 Foundation - Logger seguro.
 * Não altera fluxos existentes: apenas disponibiliza uma camada de registo técnico.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Logger')) {
    final class SIGE_Logger {
        public static function log(string $level, string $message, array $context = []): void {
            $level = strtolower($level);
            if (!in_array($level, ['debug','info','warning','error','critical'], true)) {
                $level = 'info';
            }

            $entry = [
                'time'    => function_exists('wp_date') ? wp_date('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
                'level'   => $level,
                'message' => sanitize_text_field($message),
                'context' => self::sanitize_context($context),
            ];

            $logs = get_option('sige_core_logs', []);
            if (!is_array($logs)) $logs = [];
            $logs[] = $entry;

            // Guardar apenas os últimos 200 registos para evitar crescimento descontrolado.
            if (count($logs) > 200) {
                $logs = array_slice($logs, -200);
            }

            update_option('sige_core_logs', $logs, false);
        }

        public static function recent(int $limit = 30): array {
            $logs = get_option('sige_core_logs', []);
            if (!is_array($logs)) return [];
            $limit = max(1, min(200, $limit));
            return array_reverse(array_slice($logs, -$limit));
        }

        private static function sanitize_context(array $context): array {
            $safe = [];
            foreach ($context as $key => $value) {
                $k = sanitize_key((string)$key);
                if ($k === '') continue;
                if (is_scalar($value) || $value === null) {
                    $safe[$k] = sanitize_text_field((string)$value);
                } else {
                    $safe[$k] = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            return $safe;
        }
    }
}
