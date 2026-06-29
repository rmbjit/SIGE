<?php
/**
 * SIGE SoftGenial - M-Pesa: cliente da OpenAPI (Vodacom Moçambique)
 *
 * Implementa o essencial do canal:
 *   - bearer(): cifra a API Key com a Public Key (RSA/PKCS1) e codifica em
 *     base64, como a OpenAPI exige em cada chamada;
 *   - c2b_push(): cobrança iniciada pela escola (USSD push no telemóvel do
 *     encarregado) via c2bPayment/singleStage;
 *   - query_status(): consulta do estado de uma transacção.
 *
 * Os pagamentos ESPONTÂNEOS do encarregado (ele paga para o código da
 * escola sem push) chegam pelo webhook (mpesa-webhook.php); este cliente
 * serve o botão "Cobrar agora" e as reconsultas.
 *
 * Portas por operação (defeito da OpenAPI; substituíveis por option se a
 * Vodacom as alterar): C2B 18352, query 18353.
 *
 * O núcleo de cifra é puro para ser testável em CLI com um par de chaves
 * gerado no momento: ver tools/smoke-mpesa.php.
 */
if (!defined('ABSPATH') && !defined('SIGE_MPESA_TEST_MODE')) exit;

// ============================================================================
// NÚCLEO PURO (testável em isolamento)
// ============================================================================

if (!function_exists('sige_mpesa_normalizar_public_key')) {
    /** Aceita PEM completo ou base64 nu (como o portal entrega) e devolve PEM. */
    function sige_mpesa_normalizar_public_key(string $raw): string {
        $raw = trim($raw);
        if ($raw === '') return '';
        if (strpos($raw, 'BEGIN PUBLIC KEY') !== false) return $raw;
        $limpa = preg_replace('/\s+/', '', $raw);
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split($limpa, 64, "\n") . "-----END PUBLIC KEY-----\n";
    }
}

if (!function_exists('sige_mpesa_gerar_bearer')) {
    /**
     * Cifra a API Key com a Public Key (RSA PKCS1) e devolve base64,
     * ou string vazia em falha (chave inválida, openssl ausente).
     */
    function sige_mpesa_gerar_bearer(string $api_key, string $public_key_raw): string {
        if ($api_key === '' || !function_exists('openssl_public_encrypt')) return '';
        $pem = sige_mpesa_normalizar_public_key($public_key_raw);
        if ($pem === '') return '';
        $pub = @openssl_pkey_get_public($pem);
        if ($pub === false) return '';
        $cifrado = '';
        $ok = @openssl_public_encrypt($api_key, $cifrado, $pub, OPENSSL_PKCS1_PADDING);
        return $ok ? base64_encode($cifrado) : '';
    }
}

if (!function_exists('sige_mpesa_normalizar_msisdn')) {
    /** Normaliza um número moçambicano para o formato 258XXXXXXXXX. */
    function sige_mpesa_normalizar_msisdn(string $raw): string {
        $d = preg_replace('/\D+/', '', $raw);
        if ($d === '') return '';
        if (strpos($d, '258') === 0 && strlen($d) === 12) return $d;
        if (strlen($d) === 9 && $d[0] === '8') return '258' . $d;
        if (strpos($d, '00258') === 0) return substr($d, 2);
        return $d;
    }
}

// ============================================================================
// CLIENTE HTTP (só fora do modo de teste)
// ============================================================================
if (!defined('SIGE_MPESA_TEST_MODE')) {

    if (!class_exists('SIGE_MPesa_Client')) {
        class SIGE_MPesa_Client {

            private static function porta(string $operacao): int {
                $map = ['c2b' => 18352, 'query' => 18353];
                $opt = (int) get_option('sige_mpesa_porta_' . $operacao, 0);
                return $opt > 0 ? $opt : ($map[$operacao] ?? 18352);
            }

            private static function headers(): array {
                $bearer = sige_mpesa_gerar_bearer(sige_mpesa_opt('api_key'), sige_mpesa_opt('public_key'));
                return [
                    'Content-Type' => 'application/json',
                    'Origin' => 'developer.mpesa.vm.co.mz',
                    'Authorization' => 'Bearer ' . $bearer,
                ];
            }

            private static function post(string $operacao, string $caminho, array $corpo): array {
                if (!sige_mpesa_configurado()) {
                    return ['ok' => false, 'erro' => 'M-Pesa não configurado.'];
                }
                $url = 'https://' . sige_mpesa_host() . ':' . self::porta($operacao) . $caminho;
                $resp = wp_remote_post($url, [
                    'timeout' => 60,
                    'headers' => self::headers(),
                    'body' => wp_json_encode($corpo),
                    'sslverify' => true,
                ]);
                if (is_wp_error($resp)) {
                    return ['ok' => false, 'erro' => $resp->get_error_message()];
                }
                $codigo = (int) wp_remote_retrieve_response_code($resp);
                $json = json_decode((string) wp_remote_retrieve_body($resp), true);
                $resp_code = is_array($json) ? (string)($json['output_ResponseCode'] ?? '') : '';
                return [
                    'ok' => $codigo >= 200 && $codigo < 300 && $resp_code === 'INS-0',
                    'http' => $codigo,
                    'response_code' => $resp_code,
                    'dados' => is_array($json) ? $json : [],
                ];
            }

            /**
             * Cobrança push: o encarregado recebe o pedido no telemóvel e
             * confirma com o PIN. $referencia = numero_processo do aluno.
             */
            public static function c2b_push(string $msisdn, float $valor, string $referencia, string $terceira_ref): array {
                return self::post('c2b', '/ipg/v1x/c2bPayment/singleStage/', [
                    'input_TransactionReference' => substr(preg_replace('/[^A-Za-z0-9]/', '', $referencia), 0, 20),
                    'input_CustomerMSISDN' => sige_mpesa_normalizar_msisdn($msisdn),
                    'input_Amount' => number_format($valor, 2, '.', ''),
                    'input_ThirdPartyReference' => substr(preg_replace('/[^A-Za-z0-9]/', '', $terceira_ref), 0, 20),
                    'input_ServiceProviderCode' => sige_mpesa_opt('provider_code'),
                ]);
            }

            /** Consulta do estado de uma transacção pela referência de consulta. */
            public static function query_status(string $query_ref, string $terceira_ref): array {
                return self::post('query', '/ipg/v1x/queryTransactionStatus/', [
                    'input_QueryReference' => $query_ref,
                    'input_ThirdPartyReference' => substr(preg_replace('/[^A-Za-z0-9]/', '', $terceira_ref), 0, 20),
                    'input_ServiceProviderCode' => sige_mpesa_opt('provider_code'),
                ]);
            }
        }
    }
}
