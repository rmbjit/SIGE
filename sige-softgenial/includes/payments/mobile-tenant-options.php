<?php
/**
 * SIGE SoftGenial v12.12.5 - tenant-scoped storage for mobile payment options.
 *
 * This helper is deliberately small and schema-free: it keeps legacy global
 * options for mono-school compatibility, but writes scoped options whenever a
 * school context exists. Secret Vault will later replace this storage layer.
 */
if (!defined('ABSPATH') && !defined('SIGE_MPESA_TEST_MODE')) exit;

if (!function_exists('sige_mobile_payment_normalize_provider')) {
    function sige_mobile_payment_normalize_provider(string $provider): string {
        $provider = strtolower(trim($provider));
        return in_array($provider, ['mpesa', 'emola'], true) ? $provider : '';
    }
}

if (!function_exists('sige_mobile_payment_current_school_id')) {
    function sige_mobile_payment_current_school_id(): int {
        if (function_exists('is_user_logged_in') && is_user_logged_in() && function_exists('sige_get_escola_id')) {
            $id = (int)sige_get_escola_id();
            if ($id > 0) return $id;
        }
        foreach (['sige_escola_id', 'escola_id', 'school_id'] as $key) {
            if (isset($_REQUEST[$key]) && !is_array($_REQUEST[$key])) {
                $id = (int)$_REQUEST[$key];
                if ($id > 0) return $id;
            }
        }
        if (function_exists('sige_get_escola_id')) {
            $id = (int)sige_get_escola_id();
            if ($id > 0) return $id;
        }
        if (defined('SIGE_CURRENT_ESCOLA')) {
            $id = (int)SIGE_CURRENT_ESCOLA;
            if ($id > 0) return $id;
        }
        return 0;
    }
}

if (!function_exists('sige_mobile_payment_global_option_name')) {
    function sige_mobile_payment_global_option_name(string $provider, string $key): string {
        $provider = sige_mobile_payment_normalize_provider($provider);
        $key = preg_replace('/[^a-z0-9_]/i', '', strtolower($key));
        return $provider !== '' && $key !== '' ? 'sige_' . $provider . '_' . $key : '';
    }
}

if (!function_exists('sige_mobile_payment_scoped_option_name')) {
    function sige_mobile_payment_scoped_option_name(string $provider, string $key, int $school_id): string {
        $provider = sige_mobile_payment_normalize_provider($provider);
        $key = preg_replace('/[^a-z0-9_]/i', '', strtolower($key));
        return $provider !== '' && $key !== '' && $school_id > 0 ? 'sige_' . $provider . '_escola_' . $school_id . '_' . $key : '';
    }
}

if (!function_exists('sige_mobile_payment_allow_global_fallback')) {
    function sige_mobile_payment_allow_global_fallback(): bool {
        if (defined('SIGE_MOBILE_PAYMENTS_DISABLE_GLOBAL_FALLBACK') && SIGE_MOBILE_PAYMENTS_DISABLE_GLOBAL_FALLBACK) return false;
        if (function_exists('sige_multitenancy_strict_enabled') && sige_multitenancy_strict_enabled()) return false;
        if (function_exists('sige_multitenancy_active_school_count') && sige_multitenancy_active_school_count() > 1) return false;
        return true;
    }
}

if (!function_exists('sige_mobile_payment_finalize_secret')) {
    function sige_mobile_payment_finalize_secret(string $option_name, string $raw, bool $is_secret): string {
        if (!$is_secret) return trim($raw);
        // Revela; texto em claro (instalacoes pre-cofre) passa intacto.
        $plain = function_exists('sige_vault_reveal') ? sige_vault_reveal($raw) : $raw;
        // Auto-reparacao (uma vez, so no admin): se estava em claro, regrava cifrado. Nunca no webhook publico.
        if ($raw !== '' && function_exists('sige_vault_is_sealed') && !sige_vault_is_sealed($raw)
            && function_exists('is_admin') && is_admin()
            && function_exists('sige_vault_seal')) {
            $sealed = sige_vault_seal($raw);
            if (sige_vault_is_sealed($sealed)) update_option($option_name, $sealed, false);
        }
        return trim((string)$plain);
    }
}

if (!function_exists('sige_mobile_payment_get_option')) {
    function sige_mobile_payment_get_option(string $provider, string $key, string $default = '', int $school_id = 0): string {
        $provider = sige_mobile_payment_normalize_provider($provider);
        if ($provider === '') return $default;
        $school_id = $school_id > 0 ? $school_id : sige_mobile_payment_current_school_id();
        $sentinel = '__SIGE_MOBILE_OPTION_MISSING__';
        $is_secret = function_exists('sige_vault_is_secret_key') && sige_vault_is_secret_key($provider, $key);
        if ($school_id > 0) {
            $scoped = sige_mobile_payment_scoped_option_name($provider, $key, $school_id);
            if ($scoped !== '') {
                $value = get_option($scoped, $sentinel);
                if ($value !== $sentinel) return sige_mobile_payment_finalize_secret($scoped, (string)$value, $is_secret);
            }
        }
        $global = sige_mobile_payment_global_option_name($provider, $key);
        if ($global === '') return $default;
        if ($school_id > 0 && !sige_mobile_payment_allow_global_fallback()) return $default;
        $gval = get_option($global, $sentinel);
        if ($gval === $sentinel) return $default;
        return sige_mobile_payment_finalize_secret($global, (string)$gval, $is_secret);
    }
}

if (!function_exists('sige_mobile_payment_update_option')) {
    function sige_mobile_payment_update_option(string $provider, string $key, string $value, bool $autoload = false, int $school_id = 0): bool {
        $provider = sige_mobile_payment_normalize_provider($provider);
        if ($provider === '') return false;
        $school_id = $school_id > 0 ? $school_id : sige_mobile_payment_current_school_id();
        if ($school_id <= 0) return false;
        $scoped = sige_mobile_payment_scoped_option_name($provider, $key, $school_id);
        if ($scoped === '') return false;
        // Secret Vault: chaves-segredo (credenciais dos gateways, webhook_token) sao cifradas em repouso.
        $store = (function_exists('sige_vault_is_secret_key') && sige_vault_is_secret_key($provider, $key) && function_exists('sige_vault_seal'))
            ? sige_vault_seal($value)
            : $value;
        $ok = (bool)update_option($scoped, $store, $autoload);
        // Auditoria de alteracao de segredo (apenas fornecedor e chave, nunca o valor).
        if ($ok && function_exists('sige_vault_is_secret_key') && sige_vault_is_secret_key($provider, $key) && function_exists('sige_security_log')) {
            sige_security_log('segredo_alterado', 'fornecedor=' . $provider . ';chave=' . $key);
        }
        if (sige_mobile_payment_allow_global_fallback()) {
            $global = sige_mobile_payment_global_option_name($provider, $key);
            if ($global !== '') update_option($global, $store, $autoload);
        }
        return $ok;
    }
}

if (!function_exists('sige_mobile_payment_find_school_by_token')) {
    function sige_mobile_payment_find_school_by_token(string $provider, string $token, int $school_hint = 0): int {
        $provider = sige_mobile_payment_normalize_provider($provider);
        $token = trim($token);
        if ($provider === '' || $token === '') return 0;
        $check_school = static function (int $school_id) use ($provider, $token): bool {
            if ($school_id <= 0) return false;
            $opt = sige_mobile_payment_scoped_option_name($provider, 'webhook_token', $school_id);
            $raw = $opt !== '' ? (string)get_option($opt, '') : '';
            $expected = ($raw !== '' && function_exists('sige_vault_reveal')) ? sige_vault_reveal($raw) : $raw;
            return $expected !== '' && hash_equals($expected, $token);
        };
        if ($school_hint > 0 && $check_school($school_hint)) return $school_hint;
        global $wpdb;
        if (isset($wpdb) && is_object($wpdb)) {
            $table = $wpdb->prefix . 'sige_escolas';
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
            if ($exists === $table) {
                $ids = $wpdb->get_col("SELECT id FROM {$table} WHERE activo = 1 ORDER BY id ASC LIMIT 1000");
                foreach ((array)$ids as $id) {
                    $id = (int)$id;
                    if ($id > 0 && $check_school($id)) return $id;
                }
            }
        }
        if (sige_mobile_payment_allow_global_fallback()) {
            $global = sige_mobile_payment_global_option_name($provider, 'webhook_token');
            $raw = $global !== '' ? (string)get_option($global, '') : '';
            $expected = ($raw !== '' && function_exists('sige_vault_reveal')) ? sige_vault_reveal($raw) : $raw;
            if ($expected !== '' && hash_equals($expected, $token)) {
                return $school_hint > 0 ? $school_hint : max(1, sige_mobile_payment_current_school_id());
            }
        }
        return 0;
    }
}

if (!function_exists('sige_mobile_payment_rotate_webhook_token')) {
    /**
     * Roda o webhook_token de um fornecedor de pagamento por escola: gera um novo
     * token forte, guarda-o cifrado no escopo da escola (via update_option, que sela
     * e audita), marca a rotacao e devolve o novo token em claro para configurar no
     * fornecedor. Devolve '' se falhar.
     */
    function sige_mobile_payment_rotate_webhook_token(string $provider, int $school_id = 0): string {
        $provider = sige_mobile_payment_normalize_provider($provider);
        if ($provider === '') return '';
        $school_id = $school_id > 0 ? $school_id : sige_mobile_payment_current_school_id();
        if ($school_id <= 0) return '';
        $token = function_exists('sige_secret_generate_token') ? sige_secret_generate_token(32) : bin2hex(random_bytes(16));
        if (!sige_mobile_payment_update_option($provider, 'webhook_token', $token, false, $school_id)) return '';
        if (function_exists('sige_secret_mark_rotated')) sige_secret_mark_rotated('pay_' . $provider . '_webhook_token', $school_id);
        if (function_exists('sige_security_log')) sige_security_log('segredo_rodado', 'fornecedor=' . $provider . ';chave=webhook_token;escola=' . (int) $school_id);
        return $token;
    }
}
