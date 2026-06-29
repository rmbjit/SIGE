<?php
/**
 * SIGE SoftGenial - e-Mola (Movitel): configuração por escola
 *
 * ESQUELETO SOBRE O CONTRATO INTERNO DO M-PESA: o e-Mola entra pela mesma
 * tabela (sige_mpesa_transacoes, coluna provider='emola') e pelo mesmo
 * funil de conciliação provider-aware. Falta apenas afinar o cliente HTTP
 * e o mapeador quando a documentação oficial da Movitel chegar; o resto
 * do circuito (idempotência, conciliação canónica, recibo, ecrã) já é o
 * mesmo músculo provado do M-Pesa.
 *
 * Tudo OFF por defeito. Options (prefixo sige_emola_):
 *   ambiente        'off' | 'sandbox' | 'producao'   (defeito: off)
 *   host            endereço da API (sem https://), a confirmar com a doc
 *   merchant_code   código de carteira/comerciante da escola
 *   api_key         credencial principal
 *   api_secret      credencial secundária (se a Movitel usar par)
 *   webhook_token   gerado automaticamente; valida os callbacks
 */
if (!defined('ABSPATH') && !defined('SIGE_MPESA_TEST_MODE')) exit;

if (!defined('SIGE_MPESA_TEST_MODE')) {

    if (!function_exists('sige_emola_opt')) {
        function sige_emola_opt(string $chave, string $defeito = '', int $escola_id = 0): string {
            if (function_exists('sige_mobile_payment_get_option')) {
                return sige_mobile_payment_get_option('emola', $chave, $defeito, $escola_id);
            }
            return trim((string) get_option('sige_emola_' . $chave, $defeito));
        }
    }

    if (!function_exists('sige_emola_update_opt')) {
        function sige_emola_update_opt(string $chave, string $valor, bool $autoload = false, int $escola_id = 0): bool {
            if (function_exists('sige_mobile_payment_update_option')) {
                return sige_mobile_payment_update_option('emola', $chave, $valor, $autoload, $escola_id);
            }
            return (bool) update_option('sige_emola_' . $chave, $valor, $autoload);
        }
    }

    if (!function_exists('sige_emola_ambiente')) {
        function sige_emola_ambiente(): string {
            $a = sige_emola_opt('ambiente', 'off');
            return in_array($a, ['off', 'sandbox', 'producao'], true) ? $a : 'off';
        }
    }

    if (!function_exists('sige_emola_configurado')) {
        function sige_emola_configurado(): bool {
            return sige_emola_ambiente() !== 'off'
                && sige_emola_opt('merchant_code') !== ''
                && sige_emola_opt('api_key') !== '';
        }
    }

    if (!function_exists('sige_emola_webhook_token')) {
        function sige_emola_webhook_token(int $escola_id = 0): string {
            if ($escola_id <= 0 && function_exists('sige_mobile_payment_current_school_id')) {
                $escola_id = sige_mobile_payment_current_school_id();
            }
            $t = sige_emola_opt('webhook_token', '', $escola_id);
            if ($t === '') {
                $t = wp_generate_password(40, false, false);
                sige_emola_update_opt('webhook_token', $t, false, $escola_id);
            }
            return $t;
        }
    }

    if (!function_exists('sige_emola_webhook_url')) {
        function sige_emola_webhook_url(): string {
            $escola_id = function_exists('sige_mobile_payment_current_school_id') ? sige_mobile_payment_current_school_id() : 0;
            $args = ['token' => sige_emola_webhook_token($escola_id)];
            if ($escola_id > 0) $args['escola_id'] = $escola_id;
            return add_query_arg($args, rest_url('sige/v1/emola/callback'));
        }
    }

    // A gestão usa o mesmo gate do canal móvel (Direcção/Secretaria Geral).

    // ── Gravar credenciais (POST do ecrã Pagamentos Móveis) ────────────────
    add_action('admin_post_sige_emola_guardar_config', function () {
        if (!function_exists('sige_mpesa_pode_gerir') || !sige_mpesa_pode_gerir()) {
            wp_die('Sem permissão para configurar o e-Mola.');
        }
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'sige_emola_config')) {
            wp_die('A sessão expirou por segurança. Volte atrás, recarregue a página e tente novamente.');
        }
        $ambiente = sanitize_key($_POST['ambiente'] ?? 'off');
        if (!in_array($ambiente, ['off', 'sandbox', 'producao'], true)) $ambiente = 'off';
        $escola_id = function_exists('sige_mobile_payment_current_school_id') ? sige_mobile_payment_current_school_id() : (function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0);
        if ($escola_id <= 0) wp_die('Contexto de escola inválido para configurar o e-Mola.');
        if (function_exists('sige_mfa_require_step_up') && !sige_mfa_require_step_up('cfg_emola')) {
            wp_safe_redirect(wp_get_referer() ?: admin_url()); exit;
        }
        sige_emola_update_opt('ambiente', $ambiente, false, $escola_id);
        sige_emola_update_opt('host', sanitize_text_field(wp_unslash((string)($_POST['host'] ?? ''))), false, $escola_id);
        sige_emola_update_opt('merchant_code', sanitize_text_field(wp_unslash((string)($_POST['merchant_code'] ?? ''))), false, $escola_id);
        // Sensíveis: vazio = manter
        $api_key = trim((string) wp_unslash($_POST['api_key'] ?? ''));
        if ($api_key !== '') sige_emola_update_opt('api_key', sanitize_text_field($api_key), false, $escola_id);
        $api_secret = trim((string) wp_unslash($_POST['api_secret'] ?? ''));
        if ($api_secret !== '') sige_emola_update_opt('api_secret', sanitize_text_field($api_secret), false, $escola_id);

        if (function_exists('sige_security_log')) {
            sige_security_log('emola_config_alterada', 'ambiente=' . $ambiente . ' escola=' . $escola_id . ' user=' . get_current_user_id());
        }
        wp_safe_redirect(add_query_arg(['page' => 'sige-app', 'view' => 'financeiro-mpesa', 'ok' => 'emola'], admin_url('admin.php')));
        exit;
    });
}
