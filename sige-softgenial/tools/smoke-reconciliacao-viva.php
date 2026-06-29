<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Smoke de runtime: reconciliacao viva (Fase 3, incremento 2).
 * Testa o nucleo puro (classificacao em confirmadas, divergentes e inconclusivas),
 * o normalizador conservador (codigo desconhecido nunca vira falhada), o adaptador
 * defensivo (cliente ausente devolve erro) e a orquestracao por escola (com $wpdb
 * simulado, classificacao e fail-closed). Sem WordPress vivo nem gateway real.
 */
define('SIGE_MPESA_TEST_MODE', true);
if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, $provider = '') {
        if ($hook === 'sige_mpesa_codigos_falha') {
            return $provider === 'emola' ? ['INS-700'] : ['INS-994', 'INS-995'];
        }
        if ($hook === 'sige_mpesa_estados_falha' && $provider === 'emola') {
            $value[] = 'recusada_movitel';
        }
        return $value;
    }
}
if (!function_exists('sige_security_log')) { $GLOBALS['__logs'] = []; function sige_security_log($e, $c = '', $o = 0) { $GLOBALS['__logs'][] = $e . '|' . $c; } }

require_once dirname(__DIR__) . '/includes/payments/reconciliacao-viva.php';

$fails = []; $ok = 0;
$check = static function (bool $cond, string $label) use (&$fails, &$ok): void {
    if ($cond) { $ok++; echo "OK   {$label}\n"; } else { $fails[] = $label; echo "FAIL {$label}\n"; }
};
$ids = static function (array $lista): array { return array_map(static function ($x) { return (int) ($x['id'] ?? 0); }, $lista); };
$tem = static function (array $lista, int $id, string $motivo): bool {
    foreach ($lista as $x) if ((int)($x['id'] ?? 0) === $id && ($x['motivo'] ?? '') === $motivo) return true;
    return false;
};

// ---- Nucleo puro -----------------------------------------------------------
$consultar = static function ($prov, $ref, $refc) {
    $map = [
        'OK500' => ['estado' => 'confirmada', 'valor' => 500],
        'OKDIV' => ['estado' => 'confirmada', 'valor' => 999],
        'FAIL'  => ['estado' => 'falhada', 'valor' => null],
        'NF'    => ['estado' => 'nao_encontrada', 'valor' => null],
        'DESC'  => ['estado' => 'desconhecida', 'valor' => null],
        'ERR'   => ['estado' => 'erro', 'valor' => null],
    ];
    return $map[$ref] ?? ['estado' => 'erro', 'valor' => null];
};
$locais = [
    ['id' => 1, 'provider' => 'mpesa', 'referencia_mpesa' => 'OK500', 'valor' => 500],
    ['id' => 2, 'provider' => 'mpesa', 'referencia_mpesa' => 'OKDIV', 'valor' => 500],
    ['id' => 3, 'provider' => 'mpesa', 'referencia_mpesa' => 'FAIL', 'valor' => 300],
    ['id' => 4, 'provider' => 'mpesa', 'referencia_mpesa' => 'NF', 'valor' => 300],
    ['id' => 5, 'provider' => 'mpesa', 'referencia_mpesa' => 'DESC', 'valor' => 300],
    ['id' => 6, 'provider' => 'mpesa', 'referencia_mpesa' => 'ERR', 'valor' => 300],
    ['id' => 7, 'provider' => 'mpesa', 'referencia_mpesa' => '', 'valor' => 300],
];
$r = sige_pagamentos_reconciliar_estado($locais, $consultar);
$check(in_array(1, $ids($r['confirmadas']), true), 'transacao confirmada pelo gateway com valor igual entra em confirmadas');
$check($tem($r['divergentes'], 2, 'valor_divergente'), 'valor diferente do gateway e divergencia');
$check($tem($r['divergentes'], 3, 'rejeitada_gateway') && $tem($r['divergentes'], 4, 'rejeitada_gateway'), 'falhada e nao encontrada sao rejeitadas pelo gateway');
$check($tem($r['inconclusivas'], 5, 'desconhecida') && $tem($r['inconclusivas'], 6, 'erro'), 'desconhecida e erro ficam inconclusivas');
$check($tem($r['inconclusivas'], 7, 'sem_referencia'), 'transacao sem referencia fica inconclusiva');
$check($r['totais']['n_confirmadas'] === 1 && $r['totais']['n_divergentes'] === 3 && $r['totais']['n_inconclusivas'] === 3, 'totais coerentes');

// ---- Normalizador conservador ---------------------------------------------
$n_ok = sige_pagamentos_normalizar_estado_gateway(['ok' => true, 'http' => 200, 'dados' => ['output_TransactionAmount' => '500.00']]);
$check($n_ok['estado'] === 'confirmada' && abs(((float)$n_ok['valor']) - 500.0) < 0.01, 'sucesso claro vira confirmada e extrai o valor');
$check(sige_pagamentos_normalizar_estado_gateway(['erro' => 'nao configurado'])['estado'] === 'erro', 'erro do cliente vira erro');
$check(sige_pagamentos_normalizar_estado_gateway(['ok' => false, 'http' => 200, 'response_code' => 'INS-994'])['estado'] === 'falhada', 'codigo de falha conhecido vira falhada');
$check(sige_pagamentos_normalizar_estado_gateway(['ok' => false, 'http' => 200, 'response_code' => 'INS-9'])['estado'] === 'desconhecida', 'codigo desconhecido fica desconhecida (conservador, nunca acusa)');
$check(sige_pagamentos_normalizar_estado_gateway(['ok' => false])['estado'] === 'erro', 'sem resposta http vira erro');

// ---- Estado da transacao tem prioridade (afinacao dos codigos do gateway) ---
$check(sige_pagamentos_normalizar_estado_gateway(['ok' => true, 'http' => 200, 'response_code' => 'INS-0', 'dados' => ['output_ResponseTransactionStatus' => 'Completed', 'output_TransactionAmount' => '500']])['estado'] === 'confirmada', 'INS-0 com estado Completed e confirmada');
$check(sige_pagamentos_normalizar_estado_gateway(['ok' => true, 'http' => 200, 'response_code' => 'INS-0', 'dados' => ['output_ResponseTransactionStatus' => 'Failed']])['estado'] === 'falhada', 'INS-0 mas estado Failed e falhada (a consulta ok nao basta)');
$check(sige_pagamentos_normalizar_estado_gateway(['ok' => true, 'http' => 200, 'dados' => ['output_ResponseTransactionStatus' => 'Pending']])['estado'] === 'desconhecida', 'estado nao mapeado (Pending) fica desconhecida (conservador)');
$check(sige_pagamentos_normalizar_estado_gateway(['ok' => true, 'dados' => ['status' => 'Rejeitada']])['estado'] === 'falhada', 'estado em portugues (Rejeitada) e falhada');

// ---- Afinacao POR OPERADORA (mapa e filtros com o provider como contexto) ---
$mapa_mp = sige_pagamentos_mapa_estados_gateway('mpesa');
$mapa_em = sige_pagamentos_mapa_estados_gateway('emola');
$check(in_array('INS-994', $mapa_mp['codigos'], true) && !in_array('INS-994', $mapa_em['codigos'], true), 'os codigos de falha sao por operadora (M-Pesa difere do e-Mola)');
$check(in_array('recusada_movitel', $mapa_em['falha'], true) && !in_array('recusada_movitel', $mapa_mp['falha'], true), 'um estado de falha so do e-Mola nao contamina o M-Pesa');
$check(sige_pagamentos_normalizar_estado_gateway(['ok' => false, 'http' => 200, 'response_code' => 'INS-700'], 'emola')['estado'] === 'falhada', 'codigo de falha do e-Mola e falhada no e-Mola');
$check(sige_pagamentos_normalizar_estado_gateway(['ok' => false, 'http' => 200, 'response_code' => 'INS-700'], 'mpesa')['estado'] === 'desconhecida', 'o mesmo codigo nao e falha no M-Pesa (isolamento por operadora)');
$check(sige_pagamentos_normalizar_estado_gateway(['ok' => true, 'dados' => ['status' => 'recusada_movitel']], 'emola')['estado'] === 'falhada', 'estado especifico do e-Mola e falhada no e-Mola');
$check(sige_pagamentos_normalizar_estado_gateway(['ok' => true, 'dados' => ['status' => 'recusada_movitel']], 'mpesa')['estado'] === 'desconhecida', 'o mesmo estado fica desconhecida no M-Pesa (isolamento por operadora)');

// ---- Adaptador defensivo ---------------------------------------------------
$check(sige_pagamentos_consultar_gateway('mpesa', 'XYZ', 'ABC')['estado'] === 'erro', 'cliente M-Pesa ausente (modo teste) devolve erro');
$check(sige_pagamentos_consultar_gateway('emola', 'XYZ')['estado'] === 'erro', 'cliente e-Mola ausente devolve erro');
$check(sige_pagamentos_consultar_gateway('mpesa', '')['estado'] === 'erro', 'referencia vazia devolve erro');

// ---- Orquestracao por escola (com $wpdb simulado e consulta injectada) -----
class FakeWpdbViva {
    public $prefix = 'wp_';
    public function prepare($sql, ...$a) { return $sql; }
    public function get_results($sql) {
        return [
            (object) ['id' => 10, 'provider' => 'mpesa', 'referencia_mpesa' => 'OK500', 'referencia_cliente' => 'P1', 'valor' => 500.0, 'estado' => 'recebida'],
            (object) ['id' => 11, 'provider' => 'mpesa', 'referencia_mpesa' => 'FAIL', 'referencia_cliente' => 'P2', 'valor' => 300.0, 'estado' => 'pendente_manual'],
        ];
    }
}
$GLOBALS['wpdb'] = new FakeWpdbViva();
$GLOBALS['__logs'] = [];
$res = sige_reconciliacao_viva(7, 50, $consultar);
$check(($res['totais']['n_confirmadas'] ?? 0) === 1 && ($res['totais']['n_divergentes'] ?? 0) === 1, 'orquestracao classifica as transacoes da escola');
$logou = false; foreach ($GLOBALS['__logs'] as $l) if (strpos($l, 'reconciliacao_viva_divergencia') === 0) $logou = true;
$check($logou, 'orquestracao regista a divergencia no log de seguranca');
$vazio = sige_reconciliacao_viva(0, 50, $consultar);
$check(($vazio['totais']['n_confirmadas'] ?? null) === null || empty($vazio['confirmadas']), 'orquestracao e fail-closed para escola invalida');

echo str_repeat('-', 56) . "\n";
if ($fails) {
    fwrite(STDERR, 'SMOKE RECONCILIACAO VIVA FALHOU: ' . count($fails) . " falha(s)\n");
    foreach ($fails as $f) fwrite(STDERR, " - {$f}\n");
    exit(1);
}
echo "SMOKE RECONCILIACAO VIVA OK - {$ok} verificacoes passaram.\n";
