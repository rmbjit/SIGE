<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke funcional - M-Pesa (núcleo puro)
 * Testa: geração do bearer (prova por decifra com a chave privada),
 * normalização de public key e MSISDN, mapeador de payload e algoritmo
 * de escolha de lançamento. Sem WordPress, sem rede.
 *
 * Executar: php tools/smoke-mpesa.php
 */
define('SIGE_MPESA_TEST_MODE', true);
require __DIR__ . '/../includes/payments/mpesa-client.php';
require __DIR__ . '/../includes/payments/mpesa-conciliacao.php';
require __DIR__ . '/../includes/payments/emola-webhook.php';

$fails = []; $oks = 0;
$check = function (bool $c, string $l) use (&$fails, &$oks) {
    if ($c) { $oks++; echo "OK   {$l}\n"; } else { $fails[] = $l; echo "FAIL {$l}\n"; }
};

// ── 1. Bearer: cifrar com a pública, decifrar com a privada, comparar ──────
if (function_exists('openssl_pkey_new')) {
    $par = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $detalhes = openssl_pkey_get_details($par);
    $pub_pem = (string)$detalhes['key'];
    $api_key_teste = 'chaveapi-de-teste-1234567890';

    $bearer = sige_mpesa_gerar_bearer($api_key_teste, $pub_pem);
    $check($bearer !== '' && base64_decode($bearer, true) !== false, 'Bearer gerado em base64 válido');
    $decifrado = '';
    openssl_private_decrypt((string)base64_decode($bearer), $decifrado, $par, OPENSSL_PKCS1_PADDING);
    $check($decifrado === $api_key_teste, 'Decifrar o bearer com a chave privada devolve a API key exacta');

    // Public key em base64 nu (como o portal entrega): normalização deve funcionar
    $pub_nu = trim(str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n", "\r"], '', $pub_pem));
    $bearer2 = sige_mpesa_gerar_bearer($api_key_teste, $pub_nu);
    $decifrado2 = '';
    openssl_private_decrypt((string)base64_decode($bearer2), $decifrado2, $par, OPENSSL_PKCS1_PADDING);
    $check($decifrado2 === $api_key_teste, 'Public key em base64 nu é normalizada para PEM e cifra igual');
} else {
    $check(false, 'openssl indisponível neste ambiente');
}
$check(sige_mpesa_gerar_bearer('x', 'lixo-que-nao-e-chave') === '', 'Chave pública inválida devolve bearer vazio (sem fatal)');

// ── 2. MSISDN ────────────────────────────────────────────────────────────────
$check(sige_mpesa_normalizar_msisdn('84 123 4567') === '258841234567', '9 dígitos começados em 8 ganham 258');
$check(sige_mpesa_normalizar_msisdn('+258 84 123 4567') === '258841234567', '+258 com espaços normaliza');
$check(sige_mpesa_normalizar_msisdn('258841234567') === '258841234567', 'Já normalizado mantém-se');
$check(sige_mpesa_normalizar_msisdn('00258841234567') === '258841234567', 'Prefixo 00 internacional removido');

// ── 3. Mapeador de payload (formatos input_/output_/minúsculas) ────────────
$p1 = sige_mpesa_mapear_payload([
    'input_TransactionID' => 'ABC123XYZ',
    'input_ThirdPartyReference' => 'PROC4521',
    'input_CustomerMSISDN' => '258841234567',
    'input_Amount' => '1500.00',
]);
$check($p1['referencia'] === 'ABC123XYZ' && $p1['valor'] === 1500.0 && $p1['referencia_cliente'] === 'PROC4521', 'Formato input_* mapeado');
$p2 = sige_mpesa_mapear_payload([
    'output_TransactionID' => 'OUT777',
    'output_ThirdPartyReference' => '88912',
    'output_Amount' => '2.350,50',
]);
$check($p2['referencia'] === 'OUT777', 'Formato output_* mapeado');
$p3 = sige_mpesa_mapear_payload(['TRANSACTION_ID' => 'CASE1', 'AMOUNT' => '100']);
$check($p3['referencia'] === 'CASE1' && $p3['valor'] === 100.0, 'Chaves em maiúsculas resolvidas case-insensitive');
$check($p3['moeda'] === 'MZN', 'Moeda por defeito MZN');

// ── 4. Extracção do número de processo ──────────────────────────────────────
$check(sige_mpesa_extrair_processo('PROC-4521') === '4521', 'Só os dígitos da referência contam');
$check(sige_mpesa_extrair_processo('aluno 00789 mensalidade') === '00789', 'Dígitos no meio de texto extraídos');
$check(sige_mpesa_extrair_processo('sem numeros') === '', 'Sem dígitos devolve vazio');

// ── 5. Algoritmo de escolha de lançamento ───────────────────────────────────
$abertos = [
    ['id' => 30, 'saldo' => 0.0,    'data_vencimento' => '2026-03-10'], // liquidado
    ['id' => 31, 'saldo' => 1500.0, 'data_vencimento' => '2026-04-10'],
    ['id' => 32, 'saldo' => 1500.0, 'data_vencimento' => '2026-05-10'],
];
$d = sige_mpesa_escolher_lancamento($abertos, 1500.0);
$check($d['accao'] === 'registar' && $d['lancamento_id'] === 31, 'Valor exacto vai ao mais antigo com saldo (31)');
$d = sige_mpesa_escolher_lancamento($abertos, 800.0);
$check($d['accao'] === 'registar' && $d['lancamento_id'] === 31, 'Pagamento parcial vai ao mais antigo (31)');
$d = sige_mpesa_escolher_lancamento($abertos, 1500.4);
$check($d['accao'] === 'registar' && $d['lancamento_id'] === 31, 'Tolerância de 0,50 MT aceita arredondamento');
$d = sige_mpesa_escolher_lancamento($abertos, 3000.0);
$check($d['accao'] === 'pendente_manual', 'Valor que excede o mais antigo vai a pendente_manual (sem inventar divisões)');
$d = sige_mpesa_escolher_lancamento([['id' => 9, 'saldo' => 0.0, 'data_vencimento' => '2026-01-01']], 500.0);
$check($d['accao'] === 'pendente_manual', 'Aluno sem dívida em aberto vai a pendente_manual');
$desordenado = [
    ['id' => 52, 'saldo' => 700.0, 'data_vencimento' => '2026-06-10'],
    ['id' => 51, 'saldo' => 700.0, 'data_vencimento' => '2026-02-10'],
];
$d = sige_mpesa_escolher_lancamento($desordenado, 700.0);
$check($d['lancamento_id'] === 51, 'Ordenação por vencimento é feita internamente (51 vence)');

// ── 5b. Parser de valores monetários (anglo e europeu) ──────────────────────
$check(sige_pagamentos_parse_valor('1500.00') === 1500.0, "Parser: '1500.00' = 1500");
$check(sige_pagamentos_parse_valor('2.350,50') === 2350.5, "Parser: '2.350,50' europeu = 2350.5");
$check(sige_pagamentos_parse_valor('2,350.50') === 2350.5, "Parser: '2,350.50' anglo = 2350.5");
$check(sige_pagamentos_parse_valor('1.500') === 1500.0, "Parser: '1.500' = milhares = 1500");
$check(sige_pagamentos_parse_valor('1,50') === 1.5, "Parser: '1,50' = decimal = 1.5");
$check(sige_pagamentos_parse_valor('1500 MZN') === 1500.0, "Parser: sufixo de moeda ignorado");

// ── 6. Provider-aware: método e rótulo (M-Pesa + e-Mola no mesmo funil) ─────
$check(sige_provider_metodo('mpesa') === 'mpesa', "Provider mpesa regista com método 'mpesa'");
$check(sige_provider_metodo('emola') === 'emola', "Provider emola regista com método 'emola'");
$check(sige_provider_metodo('EMOLA') === 'emola', 'Provider case-insensitive');
$check(sige_provider_metodo('desconhecido') === 'mpesa', 'Provider desconhecido cai no método mpesa (defeito seguro)');
$check(sige_provider_rotulo('emola') === 'e-Mola', 'Rótulo humano do e-Mola correcto');
$check(sige_provider_rotulo('mpesa') === 'M-Pesa', 'Rótulo humano do M-Pesa correcto');

// ── 7. Mapeador e-Mola tolerante (até a doc oficial fixar os nomes) ─────────
$e1 = sige_emola_mapear_payload(['transid' => 'EM12345', 'amount' => '1500.00', 'msisdn' => '258861234567', 'clientreference' => '4521']);
$check($e1['referencia'] === 'EM12345' && $e1['valor'] === 1500.0 && $e1['referencia_cliente'] === '4521', 'e-Mola formato transid/amount mapeado');
$e2 = sige_emola_mapear_payload(['TransactionID' => 'TX99', 'Value' => '2.350,50', 'Phone' => '861234567', 'Reference' => 'PROC789']);
$check($e2['referencia'] === 'TX99' && abs($e2['valor'] - 2350.5) < 0.001, 'e-Mola formato alternativo + vírgula decimal');
$check($e2['moeda'] === 'MZN', 'e-Mola moeda por defeito MZN');
$e3 = sige_emola_mapear_payload(['foo' => 'bar']);
$check($e3['referencia'] === '' && $e3['valor'] === 0.0, 'e-Mola payload irreconhecível devolve vazio (webhook recusa com 400)');

echo str_repeat('-', 60) . "\n";
if ($fails) {
    fwrite(STDERR, 'SMOKE M-PESA FALHOU: ' . count($fails) . " falha(s)\n");
    foreach ($fails as $f) fwrite(STDERR, " - {$f}\n");
    exit(1);
}
echo "SMOKE M-PESA OK - {$oks} verificações passaram.\n";
