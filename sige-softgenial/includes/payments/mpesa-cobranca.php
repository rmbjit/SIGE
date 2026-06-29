<?php
/**
 * SIGE SoftGenial - M-Pesa: cobrança push ("Cobrar agora")
 *
 * A secretaria introduz o número de processo, o telefone do encarregado e
 * o valor; o encarregado recebe o pedido no telemóvel e confirma com o
 * PIN. Em caso de sucesso, a transacção entra no MESMO funil do webhook
 * (sige_mpesa_transacoes -> sige_mpesa_conciliar) e o pagamento é
 * registado pela função financeira canónica, com recibo WhatsApp normal.
 *
 * Nada aqui escreve nas tabelas financeiras.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_mpesa_traduzir_codigo')) {
    /** Traduz os códigos INS-* mais comuns para mensagens humanas. */
    function sige_mpesa_traduzir_codigo(string $code, string $desc = ''): string {
        $mapa = [
            'INS-0' => 'Pagamento confirmado pelo encarregado.',
            'INS-1' => 'Erro interno da Vodacom. Tentar novamente dentro de momentos.',
            'INS-6' => 'A transacção falhou do lado da Vodacom.',
            'INS-9' => 'O encarregado não confirmou a tempo (pedido expirou no telemóvel).',
            'INS-10' => 'Transacção duplicada: este pedido já foi processado.',
            'INS-13' => 'Service Provider Code inválido. Rever a configuração.',
            'INS-15' => 'Valor inválido para esta operação.',
            'INS-996' => 'A conta M-Pesa do cliente não está activa.',
            'INS-2001' => 'O encarregado recusou ou errou o PIN.',
            'INS-2006' => 'Saldo insuficiente na conta M-Pesa do encarregado.',
            'INS-2051' => 'Número de telemóvel (MSISDN) inválido.',
        ];
        if (isset($mapa[$code])) return $mapa[$code];
        $extra = $desc !== '' ? ' (' . $desc . ')' : '';
        return $code !== '' ? 'Resposta da Vodacom: ' . $code . $extra : 'Sem resposta da Vodacom.';
    }
}

// ── AJAX: disparar a cobrança push ───────────────────────────────────────────
add_action('wp_ajax_sige_mpesa_cobrar', function () {
    if (!function_exists('sige_mpesa_pode_gerir') || !sige_mpesa_pode_gerir()) {
        wp_send_json_error('Sem permissão.');
    }
    check_ajax_referer('sige_mpesa', '_wpnonce');

    if (!function_exists('sige_mpesa_configurado') || !sige_mpesa_configurado()) {
        wp_send_json_error('O canal M-Pesa não está configurado. Preencha as credenciais primeiro.');
    }

    global $wpdb;
    $escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
    $processo = sige_mpesa_extrair_processo(sanitize_text_field((string)($_POST['processo'] ?? '')));
    $telefone = sige_mpesa_normalizar_msisdn(sanitize_text_field((string)($_POST['telefone'] ?? '')));
    $valor = (float) str_replace(',', '.', sanitize_text_field((string)($_POST['valor'] ?? '0')));

    if ($processo === '') wp_send_json_error('Indique o número de processo do aluno.');
    if (strlen($telefone) !== 12) wp_send_json_error('Telefone inválido. Use o formato 84/85/86/87 XXX XXXX.');
    if ($valor < 1) wp_send_json_error('Indique um valor válido (mínimo 1 MT).');

    // Confirmar que o processo existe nesta escola antes de incomodar o telemóvel
    $tA = $wpdb->prefix . 'sige_alunos';
    $aluno = $wpdb->get_row($wpdb->prepare(
        "SELECT id, nome_completo FROM {$tA} WHERE escola_id = %d AND numero_processo = %s LIMIT 1",
        $escola_id, $processo
    ));
    if (!$aluno) wp_send_json_error('Processo ' . $processo . ' não encontrado nesta escola.');

    $terceira_ref = 'SGE' . $escola_id . 'T' . time() . wp_rand(10, 99);
    $resp = SIGE_MPesa_Client::c2b_push($telefone, $valor, $processo, $terceira_ref);

    $code = (string)($resp['response_code'] ?? '');
    $desc = (string)($resp['dados']['output_ResponseDesc'] ?? ($resp['erro'] ?? ''));

    if (empty($resp['ok'])) {
        if (function_exists('sige_security_log')) {
            sige_security_log('mpesa_push_falhou', "processo={$processo} valor={$valor} code={$code}");
        }
        wp_send_json_error(sige_mpesa_traduzir_codigo($code, $desc));
    }

    // Sucesso: entra no funil normal (idempotente pelo UNIQUE da referência)
    $tX = $wpdb->prefix . 'sige_mpesa_transacoes';
    $ref = (string)($resp['dados']['output_TransactionID'] ?? '');
    if ($ref === '') $ref = $terceira_ref;
    $inseriu = $wpdb->insert($tX, [
        'escola_id' => $escola_id,
        'provider' => 'mpesa',
        'referencia_mpesa' => $ref,
        'referencia_cliente' => $processo,
        'msisdn' => $telefone,
        'valor' => $valor,
        'moeda' => 'MZN',
        'estado' => 'recebida',
        'payload_json' => wp_json_encode($resp['dados']),
        'criado_em' => current_time('mysql'),
    ], ['%d','%s','%s','%s','%s','%f','%s','%s','%s','%s']);

    $estado_final = 'recebida';
    if ($inseriu && function_exists('sige_mpesa_conciliar')) {
        sige_mpesa_conciliar((int)$wpdb->insert_id);
        $estado_final = (string)$wpdb->get_var($wpdb->prepare(
            "SELECT estado FROM {$tX} WHERE id = %d", (int)$wpdb->insert_id
        ));
    }
    if (function_exists('sige_security_log')) {
        sige_security_log('mpesa_push_ok', "processo={$processo} valor={$valor} ref={$ref} estado={$estado_final}");
    }

    $msg = 'Pagamento confirmado por ' . $aluno->nome_completo . '.';
    $msg .= $estado_final === 'conciliada'
        ? ' Conciliado automaticamente; o recibo segue por WhatsApp.'
        : ' Aguarda conciliação (ver tabela abaixo).';
    wp_send_json_success(['mensagem' => $msg, 'estado' => $estado_final, 'referencia' => $ref]);
});
