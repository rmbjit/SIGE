<?php
/**
 * SIGE SoftGenial - e-Mola (Movitel): cliente HTTP (ESQUELETO)
 *
 * Estado: à espera da documentação oficial da API e-Mola. Esta classe
 * existe para que, no dia em que a doc chegar, a integração seja questão
 * de preencher dois métodos, não de redesenhar o módulo: a superfície é
 * idêntica ao SIGE_MPesa_Client e tudo o que é variável (host, caminhos,
 * cabeçalhos de autenticação) vem de options afináveis sem novo release.
 *
 * Options de afinação (além das de emola-config.php):
 *   sige_emola_caminho_push    caminho do endpoint de cobrança push
 *   sige_emola_caminho_query   caminho do endpoint de consulta
 *   sige_emola_porta           porta (defeito 443)
 *   sige_emola_auth_header     nome do header de autenticação (defeito Authorization)
 *   sige_emola_auth_prefixo    prefixo do valor (defeito 'Bearer ')
 *
 * Até a doc chegar, qualquer chamada devolve uma resposta honesta de
 * "por activar"; nada na interface dispara este cliente ainda (o e-Mola
 * v1 é webhook-first, exactamente como o M-Pesa começou).
 */
if (!defined('ABSPATH') && !defined('SIGE_MPESA_TEST_MODE')) exit;

if (!defined('SIGE_MPESA_TEST_MODE')) {

    if (!class_exists('SIGE_EMola_Client')) {
        class SIGE_EMola_Client {

            private static function pronto(): bool {
                return function_exists('sige_emola_configurado')
                    && sige_emola_configurado()
                    && sige_emola_opt('host') !== ''
                    && sige_emola_opt('caminho_push') !== '';
            }

            private static function post(string $caminho, array $corpo): array {
                if (!self::pronto()) {
                    return [
                        'ok' => false,
                        'erro' => 'Cliente e-Mola por activar: falta a documentação oficial da Movitel '
                                . '(host e caminhos dos endpoints). O canal de RECEPÇÃO via webhook já funciona.',
                    ];
                }
                $porta = (int) sige_emola_opt('porta', '443');
                $url = 'https://' . sige_emola_opt('host') . ($porta !== 443 ? ':' . $porta : '') . $caminho;
                $auth_header = sige_emola_opt('auth_header', 'Authorization');
                $auth_prefixo = sige_emola_opt('auth_prefixo', 'Bearer ');
                $resp = wp_remote_post($url, [
                    'timeout' => 60,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        $auth_header => $auth_prefixo . sige_emola_opt('api_key'),
                    ],
                    'body' => wp_json_encode($corpo),
                    'sslverify' => true,
                ]);
                if (is_wp_error($resp)) {
                    return ['ok' => false, 'erro' => $resp->get_error_message()];
                }
                $codigo = (int) wp_remote_retrieve_response_code($resp);
                $json = json_decode((string) wp_remote_retrieve_body($resp), true);
                return [
                    'ok' => $codigo >= 200 && $codigo < 300,
                    'http' => $codigo,
                    'dados' => is_array($json) ? $json : [],
                ];
            }

            /** Cobrança push (a afinar com a doc: nomes de campos por confirmar). */
            public static function cobrar_push(string $msisdn, float $valor, string $referencia): array {
                return self::post((string) sige_emola_opt('caminho_push'), [
                    'msisdn' => function_exists('sige_mpesa_normalizar_msisdn') ? sige_mpesa_normalizar_msisdn($msisdn) : $msisdn,
                    'amount' => number_format($valor, 2, '.', ''),
                    'reference' => substr(preg_replace('/[^A-Za-z0-9]/', '', $referencia), 0, 20),
                    'merchant' => sige_emola_opt('merchant_code'),
                ]);
            }

            /** Consulta de estado (a afinar com a doc). */
            public static function query_status(string $referencia): array {
                return self::post((string) sige_emola_opt('caminho_query'), [
                    'reference' => $referencia,
                    'merchant' => sige_emola_opt('merchant_code'),
                ]);
            }
        }
    }
}
