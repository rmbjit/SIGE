<?php
/**
 * SIGE SoftGenial - M-Pesa: configuração por escola
 *
 * Tudo OFF por defeito. O módulo só age quando as credenciais da Vodacom
 * estão preenchidas E o ambiente está definido. Sandbox primeiro, sempre.
 *
 * Options (todas com prefixo sige_mpesa_):
 *   ambiente        'off' | 'sandbox' | 'producao'   (defeito: off)
 *   api_key         API Key do portal developer.mpesa.vm.co.mz
 *   public_key      Public Key (PEM ou base64) do mesmo portal
 *   provider_code   Service Provider Code (código de carteira da escola)
 *   webhook_token   gerado automaticamente; valida os callbacks
 */
if (!defined('ABSPATH') && !defined('SIGE_MPESA_TEST_MODE')) exit;

if (!defined('SIGE_MPESA_TEST_MODE')) {

    if (!function_exists('sige_mpesa_opt')) {
        function sige_mpesa_opt(string $chave, string $defeito = '', int $escola_id = 0): string {
            if (function_exists('sige_mobile_payment_get_option')) {
                return sige_mobile_payment_get_option('mpesa', $chave, $defeito, $escola_id);
            }
            return trim((string) get_option('sige_mpesa_' . $chave, $defeito));
        }
    }

    if (!function_exists('sige_mpesa_update_opt')) {
        function sige_mpesa_update_opt(string $chave, string $valor, bool $autoload = false, int $escola_id = 0): bool {
            if (function_exists('sige_mobile_payment_update_option')) {
                return sige_mobile_payment_update_option('mpesa', $chave, $valor, $autoload, $escola_id);
            }
            return (bool) update_option('sige_mpesa_' . $chave, $valor, $autoload);
        }
    }

    if (!function_exists('sige_mpesa_ambiente')) {
        function sige_mpesa_ambiente(): string {
            $a = sige_mpesa_opt('ambiente', 'off');
            return in_array($a, ['off', 'sandbox', 'producao'], true) ? $a : 'off';
        }
    }

    if (!function_exists('sige_mpesa_configurado')) {
        function sige_mpesa_configurado(): bool {
            return sige_mpesa_ambiente() !== 'off'
                && sige_mpesa_opt('api_key') !== ''
                && sige_mpesa_opt('public_key') !== ''
                && sige_mpesa_opt('provider_code') !== '';
        }
    }

    if (!function_exists('sige_mpesa_webhook_token')) {
        /** Token do webhook; gera-se sozinho na primeira utilização. */
        function sige_mpesa_webhook_token(int $escola_id = 0): string {
            if ($escola_id <= 0 && function_exists('sige_mobile_payment_current_school_id')) {
                $escola_id = sige_mobile_payment_current_school_id();
            }
            $t = sige_mpesa_opt('webhook_token', '', $escola_id);
            if ($t === '') {
                $t = wp_generate_password(40, false, false);
                sige_mpesa_update_opt('webhook_token', $t, false, $escola_id);
            }
            return $t;
        }
    }

    if (!function_exists('sige_mpesa_webhook_url')) {
        function sige_mpesa_webhook_url(): string {
            $escola_id = function_exists('sige_mobile_payment_current_school_id') ? sige_mobile_payment_current_school_id() : 0;
            $args = ['token' => sige_mpesa_webhook_token($escola_id)];
            if ($escola_id > 0) $args['escola_id'] = $escola_id;
            return add_query_arg($args, rest_url('sige/v1/mpesa/callback'));
        }
    }

    if (!function_exists('sige_mpesa_host')) {
        /** Host da API conforme o ambiente (substituível por option se a Vodacom mudar). */
        function sige_mpesa_host(): string {
            $h = sige_mpesa_opt('host');
            if ($h !== '') return $h;
            return sige_mpesa_ambiente() === 'producao' ? 'api.vm.co.mz' : 'api.sandbox.vm.co.mz';
        }
    }

    if (!function_exists('sige_mpesa_pode_gerir')) {
        function sige_mpesa_pode_gerir(): bool {
            if (function_exists('sige_can') && sige_can('financeiro.mobile_payments_gerir')) return true;
            if (function_exists('sige_page_guard_is_real_admin') && sige_page_guard_is_real_admin()) return true;
            return current_user_can('sige_director') || current_user_can('sige_secretaria_geral');
        }
    }

    // ── Gravar credenciais (POST do ecrã M-Pesa) ───────────────────────────
    add_action('admin_post_sige_mpesa_guardar_config', function () {
        if (!sige_mpesa_pode_gerir()) wp_die('Sem permissão para configurar o M-Pesa.');
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'sige_mpesa_config')) {
            wp_die('A sessão expirou por segurança. Volte atrás, recarregue a página e tente novamente.');
        }
        $ambiente = sanitize_key($_POST['ambiente'] ?? 'off');
        if (!in_array($ambiente, ['off', 'sandbox', 'producao'], true)) $ambiente = 'off';
        $escola_id = function_exists('sige_mobile_payment_current_school_id') ? sige_mobile_payment_current_school_id() : (function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0);
        if ($escola_id <= 0) wp_die('Contexto de escola inválido para configurar o M-Pesa.');
        if (function_exists('sige_mfa_require_step_up') && !sige_mfa_require_step_up('cfg_mpesa')) {
            wp_safe_redirect(wp_get_referer() ?: admin_url()); exit;
        }
        sige_mpesa_update_opt('ambiente', $ambiente, false, $escola_id);
        sige_mpesa_update_opt('provider_code', sanitize_text_field(wp_unslash((string)($_POST['provider_code'] ?? ''))), false, $escola_id);
        // Campos sensíveis: só substituem quando preenchidos (vazio = manter)
        $api_key = trim((string) wp_unslash($_POST['api_key'] ?? ''));
        if ($api_key !== '') sige_mpesa_update_opt('api_key', sanitize_text_field($api_key), false, $escola_id);
        $public_key = trim((string) wp_unslash($_POST['public_key'] ?? ''));
        if ($public_key !== '') sige_mpesa_update_opt('public_key', $public_key, false, $escola_id);

        if (function_exists('sige_security_log')) {
            sige_security_log('mpesa_config_alterada', 'ambiente=' . $ambiente . ' escola=' . $escola_id . ' user=' . get_current_user_id());
        }
        wp_safe_redirect(add_query_arg(['page' => 'sige-app', 'view' => 'financeiro-mpesa', 'ok' => 'config'], admin_url('admin.php')));
        exit;
    });
}
