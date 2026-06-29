<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke da reconciliacao (Fase 7 incremento 1).
 *
 * Testa sige_reconciliacao_divergencias() de forma isolada, com um $wpdb
 * simulado que devolve dados conhecidos, e confirma a classificacao das tres
 * classes de divergencia e os totais, mais o comportamento fail-closed.
 */

define('SIGE_RECON_TEST_MODE', true);

$pass = 0; $fail = 0;
$check = function (string $nome, bool $ok) use (&$pass, &$fail) {
    if ($ok) { $pass++; echo "OK   {$nome}\n"; }
    else { $fail++; echo "FALHA {$nome}\n"; }
};

// ---- $wpdb simulado -------------------------------------------------------
class FakeWpdbRecon {
    public $prefix = 'wp_';
    public function prepare($sql, ...$args) { return $sql; }
    public function get_results($sql) {
        if (strpos($sql, "pagamento_id IS NULL") !== false && strpos($sql, "estado IN ('recebida'") !== false) {
            return [
                (object) ['id' => 1, 'provider' => 'mpesa', 'referencia_mpesa' => 'AAA1', 'referencia_cliente' => null, 'msisdn' => '84xx', 'valor' => 500.00, 'estado' => 'recebida', 'criado_em' => '2026-06-20 10:00:00'],
                (object) ['id' => 2, 'provider' => 'emola', 'referencia_mpesa' => 'BBB2', 'referencia_cliente' => null, 'msisdn' => '86xx', 'valor' => 300.00, 'estado' => 'pendente_manual', 'criado_em' => '2026-06-20 11:00:00'],
            ];
        }
        if (strpos($sql, "ABS(t.valor - p.valor_pago)") !== false) {
            return [
                (object) ['id' => 9, 'provider' => 'mpesa', 'referencia_mpesa' => 'CCC3', 'valor_gateway' => 1000.00, 'pagamento_id' => 77, 'valor_pagamento' => 900.00, 'conciliado_em' => '2026-06-19 09:00:00'],
            ];
        }
        if (strpos($sql, "NOT EXISTS") !== false) {
            return [
                (object) ['id' => 55, 'valor_pago' => 250.00, 'metodo_pagamento' => 'mpesa', 'referencia_externa' => 'manual-1', 'data_pagamento' => '2026-06-18'],
            ];
        }
        return [];
    }
    public function get_var($sql) {
        if (strpos($sql, "SUM(valor_pago)") !== false) return '1250.00';
        if (strpos($sql, "pagamento_id IS NULL") !== false) return '800.00';      // em limbo
        if (strpos($sql, "estado = 'conciliada'") !== false) return '1000.00';    // conciliado
        if (strpos($sql, "estado IN ('recebida', 'pendente', 'pendente_manual', 'conciliada')") !== false) return '1800.00'; // recebido
        return '0';
    }
}
$GLOBALS['wpdb'] = new FakeWpdbRecon();

require __DIR__ . '/../includes/payments/reconciliacao-divergencias.php';

$check('funcao existe', function_exists('sige_reconciliacao_divergencias'));

$d = sige_reconciliacao_divergencias(14);

// Classes
$check('recebido por aplicar: 2 transacoes', count($d['nao_conciliadas']) === 2);
$check('divergencia de montante: 1 transacao', count($d['divergencias_montante']) === 1);
$check('pagamento sem gateway: 1 pagamento', count($d['pagamentos_sem_gateway']) === 1);

// Conteudo de uma divergencia de montante
$dm = $d['divergencias_montante'][0];
$check('divergencia de montante: gateway 1000 vs pagamento 900', (float)$dm->valor_gateway === 1000.00 && (float)$dm->valor_pagamento === 900.00);

// Totais
$t = $d['totais'];
$check('total recebido pelo gateway = 1800', (float)$t['recebido_gateway'] === 1800.00);
$check('total conciliado = 1000', (float)$t['conciliado'] === 1000.00);
$check('total em limbo = 800', (float)$t['em_limbo'] === 800.00);
$check('total pagamentos moveis = 1250', (float)$t['pagamentos_moveis'] === 1250.00);
$check('diferenca conciliado vs moveis = -250', (float)$t['diferenca_conciliado_vs_moveis'] === -250.00);
$check('contadores coerentes', $t['n_nao_conciliadas'] === 2 && $t['n_divergencias_montante'] === 1 && $t['n_pagamentos_sem_gateway'] === 1);

// Fail-closed
$vazio = sige_reconciliacao_divergencias(0);
$check('fail-closed: escola invalida devolve vazio', $vazio['nao_conciliadas'] === [] && $vazio['divergencias_montante'] === [] && $vazio['pagamentos_sem_gateway'] === [] && $vazio['totais'] === []);

echo "\n";
if ($fail > 0) {
    echo "SMOKE RECONCILIACAO FALHOU - {$fail} de " . ($pass + $fail) . " verificacoes falharam.\n";
    exit(1);
}
echo "SMOKE RECONCILIACAO OK - {$pass} verificacoes passaram (tres classes de divergencia, totais e fail-closed).\n";
