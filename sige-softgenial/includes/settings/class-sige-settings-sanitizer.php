<?php
/**
 * SIGE SoftGenial - Settings Sanitizer
 *
 * Validação e sanitização declarativa por tipo, lendo meta do Registry.
 * Tipos suportados:
 *   string, email, url, integer, float, money, boolean, color, date,
 *   secret, textarea, select, csv, json_list, json, smtp
 *
 * @since v12.10.0
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Settings_Sanitizer')) {

    final class SIGE_Settings_Sanitizer {

        /**
         * Sanitiza um valor segundo o tipo declarado no meta.
         *
         * @param mixed $raw
         * @param array $meta
         * @return mixed Valor sanitizado pronto para validação.
         */
        public static function sanitize($raw, array $meta) {
            $type = (string)($meta['type'] ?? 'string');

            // Callbacks de normalização externos têm prioridade.
            $cb = (string)($meta['normalize_callback'] ?? '');
            if ($cb !== '' && function_exists($cb)) {
                return call_user_func($cb, $raw);
            }

            switch ($type) {
                case 'email':
                    return sanitize_email(trim((string)$raw));
                case 'url':
                    return esc_url_raw(trim((string)$raw));
                case 'integer':
                    return ($raw === '' || $raw === null) ? null : (int)$raw;
                case 'float':
                    return ($raw === '' || $raw === null) ? null : (float)$raw;
                case 'money':
                    return number_format((float)$raw, 2, '.', '');
                case 'boolean':
                    return (!empty($raw) && $raw !== '0' && $raw !== 'false') ? 1 : 0;
                case 'color':
                    $c = sanitize_hex_color(trim((string)$raw));
                    return $c ?: '';
                case 'date':
                    $s = trim((string)$raw);
                    return $s === '' ? '' : preg_replace('/[^0-9\-]/', '', $s);
                case 'secret':
                    return (string)$raw; // Encryption tratada pelo Repository.
                case 'textarea':
                    return wp_kses_post((string)$raw);
                case 'select':
                    $val = sanitize_key((string)$raw);
                    $choices = (array)($meta['choices'] ?? []);
                    $cb_choices = (string)($meta['choices_callback'] ?? '');
                    if ($cb_choices !== '' && function_exists($cb_choices)) {
                        $choices = (array) call_user_func($cb_choices);
                    }
                    if (!empty($choices) && !array_key_exists($val, $choices) && !in_array($val, array_values($choices), true)) {
                        $default = (string)($meta['default'] ?? '');
                        return $default;
                    }
                    return $val !== '' ? $val : (string)($meta['default'] ?? '');
                case 'csv':
                    $arr = is_array($raw) ? $raw : explode(',', (string)$raw);
                    $arr = array_map(function ($v) { return sanitize_text_field(trim((string)$v)); }, $arr);
                    $arr = array_filter($arr, function ($v) { return $v !== ''; });
                    return implode(', ', array_unique($arr));
                case 'json_list':
                    $arr = is_array($raw) ? $raw : (array) json_decode((string)$raw, true);
                    $arr = array_filter(array_map('sanitize_text_field', $arr), function ($v) { return $v !== ''; });
                    if (!empty($meta['always_on']) && is_array($meta['always_on'])) {
                        foreach ($meta['always_on'] as $must) {
                            if (!in_array($must, $arr, true)) $arr[] = $must;
                        }
                    }
                    if (!empty($meta['choices']) && is_array($meta['choices'])) {
                        $valid = array_keys($meta['choices']);
                        $arr = array_values(array_intersect($valid, $arr));
                    } else {
                        $arr = array_values(array_unique($arr));
                    }
                    return wp_json_encode($arr, JSON_UNESCAPED_UNICODE);
                case 'json':
                    if (is_array($raw) || is_object($raw)) return wp_json_encode($raw, JSON_UNESCAPED_UNICODE);
                    $decoded = json_decode((string)$raw, true);
                    return (json_last_error() === JSON_ERROR_NONE) ? wp_json_encode($decoded, JSON_UNESCAPED_UNICODE) : '';
                case 'smtp':
                    return self::sanitize_smtp(is_array($raw) ? $raw : []);
                case 'string':
                default:
                    return sanitize_text_field((string)$raw);
            }
        }

        /**
         * Valida um valor sanitizado. Devolve [ok, mensagem].
         *
         * @return array{0:bool,1:string}
         */
        public static function validate($value, array $meta): array {
            $label = (string)($meta['label'] ?? '');

            if (!empty($meta['required'])) {
                $empty = ($value === '' || $value === null || (is_array($value) && empty($value)));
                if ($empty) return [false, sprintf('O campo "%s" é obrigatório.', $label)];
            }

            $type = (string)($meta['type'] ?? 'string');

            if ($type === 'email' && $value !== '' && !is_email((string)$value)) {
                return [false, sprintf('Email inválido em "%s".', $label)];
            }
            if ($type === 'url' && $value !== '') {
                if (!filter_var((string)$value, FILTER_VALIDATE_URL)) {
                    return [false, sprintf('URL inválida em "%s".', $label)];
                }
            }
            if ($type === 'integer' && $value !== null) {
                $min = $meta['ui_min']; $max = $meta['ui_max'];
                if ($min !== null && (int)$value < (int)$min) return [false, sprintf('"%s" abaixo do mínimo (%d).', $label, (int)$min)];
                if ($max !== null && (int)$value > (int)$max) return [false, sprintf('"%s" acima do máximo (%d).', $label, (int)$max)];
            }
            if ($type === 'smtp' && !empty($value['enabled']) && (string)$value['enabled'] === '1') {
                $smtp_from = !empty($value['from_email']) ? (string)$value['from_email'] : (string)($value['username'] ?? '');
                if (empty($value['host']) || empty($smtp_from) || !is_email($smtp_from)) {
                    return [false, 'Para activar SMTP é preciso host e email remetente válidos.'];
                }
                if (!empty($value['auth']) && (string)$value['auth'] === '1' && empty($value['username'])) {
                    return [false, 'Para activar SMTP com autenticação é preciso informar o utilizador SMTP.'];
                }
            }

            return [true, ''];
        }

        public static function sanitize_smtp(array $raw): array {
            $enc = in_array(($raw['encryption'] ?? 'ssl'), ['ssl', 'tls', 'none'], true) ? $raw['encryption'] : 'ssl';
            $username = sanitize_text_field((string)($raw['username'] ?? ''));
            $from_email = sanitize_email((string)($raw['from_email'] ?? ''));
            if ($from_email === '' && is_email($username)) {
                $from_email = sanitize_email($username);
            }
            return [
                'enabled'    => !empty($raw['enabled']) ? '1' : '0',
                'host'       => sanitize_text_field((string)($raw['host'] ?? 'smtp.zoho.com')),
                'port'       => max(1, min(65535, (int)($raw['port'] ?? 465))),
                'encryption' => $enc,
                'auth'       => !empty($raw['auth']) ? '1' : '0',
                'username'   => $username,
                'password'   => isset($raw['password']) ? (string)$raw['password'] : '', // Preservação/encriptação tratada pelo Repository.
                'from_email' => $from_email,
                'from_name'  => sanitize_text_field((string)($raw['from_name'] ?? 'SoftGenial')),
                'reply_to'   => sanitize_email((string)($raw['reply_to'] ?? '')),
            ];
        }

        /** Apresenta valor mascarado quando sensível. */
        public static function mask($value, bool $sensitive = false): string {
            if (is_array($value) || is_object($value)) $value = wp_json_encode($value);
            $value = (string)$value;
            if ($value === '') return '-';
            if (!$sensitive) return strlen($value) > 80 ? substr($value, 0, 80) . '…' : $value;
            $len = strlen($value);
            if ($len <= 6) return '••••••';
            return substr($value, 0, 3) . str_repeat('•', min(16, max(6, $len - 6))) . substr($value, -3);
        }
    }
}
