<?php
/**
 * SIGE SoftGenial - M-Pesa: webhook de callbacks
 *
 * Rota: POST /wp-json/sige/v1/mpesa/callback?token=...
 *
 * Segurança:
 *   - token obrigatório (gerado por escola; ver sige_mpesa_webhook_token);
 *   - idempotência por (escola_id, referencia_mpesa): a Vodacom re-entrega
 *     callbacks; a mesma transacção nunca entra duas vezes;
 *   - payload completo guardado em payload_json para auditoria/diagnóstico;
 *   - responde sempre com output_ResponseCode INS-0 quando aceitou
 *     (mesmo que a conciliação fique pendente: receber != conciliar).
 */
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
    register_rest_route('sige/v1', '/mpesa/callback', [
        'methods' => 'POST',
        'permission_callback' => function (WP_REST_Request $req): bool {
            if (!function_exists('sige_mpesa_webhook_token')) return false;
            $token = (string) $req->get_param('token');
            if ($token === '') $token = (string) $req->get_header('x-sige-token');
            $hint = (int)($req->get_param('escola_id') ?: $req->get_param('school_id'));
            $school_id = function_exists('sige_mobile_payment_find_school_by_token')
                ? sige_mobile_payment_find_school_by_token('mpesa', $token, $hint)
                : 0;
            if ($school_id > 0) {
                $esperado = function_exists('sige_mpesa_opt') ? sige_mpesa_opt('webhook_token', '', $school_id) : '';
                if ($esperado !== '' && hash_equals($esperado, $token)) {
                    $req->set_param('_sige_escola_id', $school_id);
                    return true;
                }
            }
            return false;
        },
        'callback' => function (WP_REST_Request $req) {
            global $wpdb;
            $tX = $wpdb->prefix . 'sige_mpesa_transacoes';

            $payload = $req->get_json_params();
            if (!is_array($payload) || empty($payload)) {
                $payload = $req->get_body_params();
            }
            if (!is_array($payload) || empty($payload)) {
                return new WP_REST_Response(['output_ResponseCode' => 'INS-2051', 'output_ResponseDesc' => 'Payload vazio.'], 400);
            }

            $m = sige_mpesa_mapear_payload($payload);
            if ($m['referencia'] === '' || $m['valor'] <= 0) {
                if (function_exists('sige_security_log')) {
                    sige_security_log('mpesa_callback_invalido', 'campos essenciais em falta');
                }
                return new WP_REST_Response(['output_ResponseCode' => 'INS-2051', 'output_ResponseDesc' => 'Campos essenciais em falta.'], 400);
            }

            $escola_id = (int)$req->get_param('_sige_escola_id');
            if ($escola_id <= 0) {
                $escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
            }
            if ($escola_id <= 0) {
                return new WP_REST_Response(['output_ResponseCode' => 'INS-2051', 'output_ResponseDesc' => 'Escola não identificada.'], 400);
            }

            // Idempotência: a mesma referência nunca entra duas vezes.
            $ja = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tX} WHERE escola_id = %d AND referencia_mpesa = %s",
                $escola_id, $m['referencia']
            ));
            if ($ja > 0) {
                return new WP_REST_Response([
                    'output_ResponseCode' => 'INS-0',
                    'output_ResponseDesc' => 'Transacção já registada.',
                    'output_TransactionID' => $m['referencia'],
                ], 200);
            }

            $ok = $wpdb->insert($tX, [
                'escola_id' => $escola_id,
                'provider' => 'mpesa',
                'referencia_mpesa' => $m['referencia'],
                'referencia_cliente' => $m['referencia_cliente'],
                'msisdn' => $m['msisdn'],
                'valor' => $m['valor'],
                'moeda' => $m['moeda'],
                'estado' => 'recebida',
                'payload_json' => wp_json_encode($payload),
                'criado_em' => current_time('mysql'),
            ], ['%d','%s','%s','%s','%s','%f','%s','%s','%s','%s']);

            if (!$ok) {
                // Corrida com outra entrega simultânea: o UNIQUE protege; aceitar.
                return new WP_REST_Response(['output_ResponseCode' => 'INS-0', 'output_ResponseDesc' => 'Aceite.'], 200);
            }
            $tx_id = (int) $wpdb->insert_id;
            if (function_exists('sige_security_log')) {
                sige_security_log('mpesa_callback_recebido', "tx={$tx_id} ref={$m['referencia']} valor={$m['valor']}");
            }

            // Conciliação imediata (best effort; falhas ficam para o cron diário)
            if (function_exists('sige_mpesa_conciliar')) {
                sige_mpesa_conciliar($tx_id);
            }

            return new WP_REST_Response([
                'output_ResponseCode' => 'INS-0',
                'output_ResponseDesc' => 'Aceite.',
                'output_TransactionID' => $m['referencia'],
            ], 201);
        },
    ]);
});
