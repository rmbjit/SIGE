<?php
/**
 * SIGE SoftGenial - Observability Layer v12.5.3
 *
 * Observabilidade leve e segura para diagnóstico técnico.
 * - Não altera schema da BD.
 * - Não altera UI.
 * - Não altera cálculos.
 * - Não grava opções automaticamente.
 * - Só escreve no error_log quando activado por constante, opção ou WP_DEBUG.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_observability_enabled')) {
    function sige_observability_enabled(): bool {
        if (defined('SIGE_OBSERVABILITY') && SIGE_OBSERVABILITY) {
            return true;
        }

        $opt = get_option('sige_observability_enabled', null);
        if ($opt !== null && $opt !== false && $opt !== '') {
            return in_array(strtolower((string)$opt), ['1', 'true', 'yes', 'on'], true);
        }

        return (defined('WP_DEBUG') && WP_DEBUG);
    }
}

if (!function_exists('sige_safe_log_context')) {
    function sige_safe_log_context($context): array {
        if (!is_array($context)) {
            return [];
        }

        $blocked = ['password', 'senha', 'pass', 'token', 'secret', 'authorization', 'cookie', 'nonce'];
        $safe = [];

        foreach ($context as $key => $value) {
            $k = sanitize_key((string)$key);
            if ($k === '') continue;

            foreach ($blocked as $needle) {
                if (strpos($k, $needle) !== false) {
                    $safe[$k] = '[redacted]';
                    continue 2;
                }
            }

            if (is_scalar($value) || $value === null) {
                $safe[$k] = $value;
            } elseif (is_array($value)) {
                $safe[$k] = '[array:' . count($value) . ']';
            } else {
                $safe[$k] = '[' . gettype($value) . ']';
            }
        }

        return $safe;
    }
}

if (!function_exists('sige_log_event')) {
    function sige_log_event(string $channel, string $event, array $context = [], string $level = 'info'): void {
        if (!sige_observability_enabled()) {
            return;
        }

        $channel = sanitize_key($channel ?: 'core');
        $event = sanitize_key($event ?: 'event');
        $level = sanitize_key($level ?: 'info');
        $payload = [
            'level'   => $level,
            'channel' => $channel,
            'event'   => $event,
            'version' => defined('SIGE_VERSION') ? SIGE_VERSION : null,
            'user_id' => function_exists('get_current_user_id') ? (int)get_current_user_id() : 0,
            'context' => sige_safe_log_context($context),
        ];

        error_log('[SIGE] ' . wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('sige_guard_bool')) {
    function sige_guard_bool($value, bool $default = false): bool {
        if (is_bool($value)) return $value;
        if (is_int($value)) return $value === 1;
        if (is_float($value)) return (int)$value === 1;
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['1', 'true', 'yes', 'sim', 'on'], true)) return true;
            if (in_array($v, ['0', 'false', 'no', 'nao', 'não', 'off', ''], true)) return false;
        }
        return $default;
    }
}
