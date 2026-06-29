<?php
/**
 * SIGE SoftGenial - e-Mola (Movitel): webhook de callbacks
 *
 * Rota: POST /wp-json/sige/v1/emola/callback?token=...
 *
 * Mesmo contrato interno do M-Pesa: token próprio validado por
 * hash_equals, idempotência pelo UNIQUE (escola_id, referencia), payload
 * integral guardado, e a transacção entra com provider='emola' no MESMO
 * funil de conciliação (que regista pelo método 'emola' via
 * sige_provider_metodo). O mapeador é deliberadamente tolerante a
 * variações de nomes; afina-se ao formato exacto quando a documentação
 * oficial da Movitel estiver disponível.
 */
if (!defined('ABSPATH') && !defined('SIGE_MPESA_TEST_MODE')) exit;

// ============================================================================
// NÚCLEO PURO (testável em isolamento)
// ============================================================================

if (!function_exists('sige_emola_mapear_payload')) {
    /**
     * Extrai os campos essenciais de um callback e-Mola, tolerando os
     * nomes mais prováveis (case-insensitive). Devolve o mesmo contrato
     * do mapeador M-Pesa: ['referencia','referencia_cliente','msisdn','valor','moeda'].
     */
    function sige_emola_mapear_payload(array $p): array {
        $pega = static function (array $chaves) use ($p): string {
            $lower = array_change_key_case($p, CASE_LOWER);
            foreach ($chaves as $k) {
                $lk = strtolower($k);
                if (isset($lower[$lk]) && $lower[$lk] !== '') return (string)$lower[$lk];
            }
            return '';
        };
        $valor_raw = $pega(['amount', 'transamount', 'valor', 'value', 'montante']);
        return [
            'referencia' => $pega(['transid', 'transactionid', 'transaction_id', 'txnid', 'txn_id', 'emolaref', 'id']),
            'referencia_cliente' => $pega(['clientreference', 'client_reference', 'reference', 'ref', 'referencia', 'partnerref', 'thirdpartyreference']),
            'msisdn' => $pega(['msisdn', 'phone', 'phonenumber', 'customermsisdn', 'telefone']),
            'valor' => sige_pagamentos_parse_valor($valor_raw),
            'moeda' => $pega(['currency', 'moeda']) ?: 'MZN',
        ];
    }
}

// ============================================================================
// INTEGRAÇÃO WORDPRESS
// ============================================================================
if (!defined('SIGE_MPESA_TEST_MODE')) {

    add_action('rest_api_init', function () {
        register_rest_route('sige/v1', '/emola/callback', [
            'methods' => 'POST',
            'permission_callback' => function (WP_REST_Request $req): bool {
                if (!function_exists('sige_emola_webhook_token')) return false;
                $token = (string) $req->get_param('token');
                if ($token === '') $token = (string) $req->get_header('x-sige-token');
                $hint = (int)($req->get_param('escola_id') ?: $req->get_param('school_id'));
                $school_id = function_exists('sige_mobile_payment_find_school_by_token')
                    ? sige_mobile_payment_find_school_by_token('emola', $token, $hint)
                    : 0;
                if ($school_id > 0) {
                    $esperado = function_exists('sige_emola_opt') ? sige_emola_opt('webhook_token', '', $school_id) : '';
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
                    return new WP_REST_Response(['status' => 'erro', 'mensagem' => 'Payload vazio.'], 400);
                }

                $m = sige_emola_mapear_payload($payload);
                if ($m['referencia'] === '' || $m['valor'] <= 0) {
                    if (function_exists('sige_security_log')) {
                        sige_security_log('emola_callback_invalido', 'campos essenciais em falta');
                    }
                    return new WP_REST_Response(['status' => 'erro', 'mensagem' => 'Campos essenciais em falta.'], 400);
                }

                $escola_id = (int)$req->get_param('_sige_escola_id');
                if ($escola_id <= 0) {
                    $escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
                }
                if ($escola_id <= 0) {
                    return new WP_REST_Response(['status' => 'erro', 'mensagem' => 'Escola não identificada.'], 400);
                }

                // Idempotência: a mesma referência nunca entra duas vezes.
                $ja = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$tX} WHERE escola_id = %d AND referencia_mpesa = %s",
                    $escola_id, $m['referencia']
                ));
                if ($ja > 0) {
                    return new WP_REST_Response(['status' => 'ok', 'mensagem' => 'Transacção já registada.', 'referencia' => $m['referencia']], 200);
                }

                $ok = $wpdb->insert($tX, [
                    'escola_id' => $escola_id,
                    'provider' => 'emola',
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
                    return new WP_REST_Response(['status' => 'ok', 'mensagem' => 'Aceite.'], 200);
                }
                $tx_id = (int) $wpdb->insert_id;
                if (function_exists('sige_security_log')) {
                    sige_security_log('emola_callback_recebido', "tx={$tx_id} ref={$m['referencia']} valor={$m['valor']}");
                }
                if (function_exists('sige_mpesa_conciliar')) {
                    sige_mpesa_conciliar($tx_id);
                }
                return new WP_REST_Response(['status' => 'ok', 'mensagem' => 'Aceite.', 'referencia' => $m['referencia']], 201);
            },
        ]);
    });
}
