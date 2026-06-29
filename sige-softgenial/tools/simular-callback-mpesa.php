<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Simulador de callback M-Pesa
 *
 * Envia ao webhook da escola um callback idêntico ao da Vodacom, para
 * validar o circuito completo (recepção -> idempotência -> conciliação ->
 * recibo) ANTES de ter credenciais reais, e para repetir testes depois.
 *
 * Uso:
 *   php tools/simular-callback-mpesa.php "URL_DO_WEBHOOK_COM_TOKEN" PROCESSO VALOR [REFERENCIA]
 *
 * Exemplos:
 *   php tools/simular-callback-mpesa.php "https://demo.softgenial.edu.mz/wp-json/sige/v1/mpesa/callback?token=XYZ" 4521 1500
 *   php tools/simular-callback-mpesa.php "$URL" 4521 1500 TESTE002   (referência própria p/ testar idempotência)
 *
 * Sem extensão curl: usa streams nativos do PHP.
 */

$url = $argv[1] ?? '';
$processo = $argv[2] ?? '';
$valor = $argv[3] ?? '';
$ref = $argv[4] ?? ('SIM' . date('YmdHis'));

if ($url === '' || $processo === '' || $valor === '' || !preg_match('#^https?://#', $url)) {
    fwrite(STDERR, "Uso: php tools/simular-callback-mpesa.php URL_DO_WEBHOOK PROCESSO VALOR [REFERENCIA]\n");
    fwrite(STDERR, "A URL é a do ecrã Pagamentos M-Pesa (botão Copiar), já com o token.\n");
    exit(1);
}

$payload = [
    'input_TransactionID' => $ref,
    'input_ThirdPartyReference' => $processo,
    'input_CustomerMSISDN' => '258841234567',
    'input_Amount' => number_format((float)str_replace(',', '.', $valor), 2, '.', ''),
    'input_Currency' => 'MZN',
];

echo "A enviar callback simulado...\n";
echo "  URL........: " . preg_replace('/token=[^&]+/', 'token=***', $url) . "\n";
echo "  Referência.: {$ref}\n";
echo "  Processo...: {$processo}\n";
echo "  Valor......: {$payload['input_Amount']} MZN\n";
echo str_repeat('-', 60) . "\n";

$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
        'content' => json_encode($payload),
        'ignore_errors' => true,
        'timeout' => 30,
    ],
    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
]);

$body = @file_get_contents($url, false, $ctx);
$status = 0;
foreach ((array)($http_response_header ?? []) as $h) {
    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) { $status = (int)$m[1]; }
}

if ($body === false) {
    fwrite(STDERR, "FALHA: sem resposta do servidor (rede/SSL/firewall?).\n");
    exit(1);
}

echo "HTTP {$status}\n{$body}\n";
echo str_repeat('-', 60) . "\n";

$json = json_decode($body, true);
$code = is_array($json) ? (string)($json['output_ResponseCode'] ?? '') : '';
if ($status >= 200 && $status < 300 && $code === 'INS-0') {
    echo "OK: callback aceite. Verificar no ecrã Pagamentos M-Pesa o estado\n";
    echo "(conciliada se o processo tem dívida em aberto; pendente_manual caso contrário).\n";
    echo "Repetir o MESMO comando com a MESMA referência deve devolver 'já registada' sem duplicar.\n";
    exit(0);
}
if ($status === 401 || $status === 403) {
    fwrite(STDERR, "RECUSADO: token inválido. Copiar de novo a URL no ecrã M-Pesa.\n");
    exit(1);
}
fwrite(STDERR, "Resposta inesperada (código {$code}). Ver auditoria/logs do site.\n");
exit(1);
