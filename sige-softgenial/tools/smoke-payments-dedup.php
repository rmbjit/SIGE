<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Smoke de runtime: reconciliacao de pagamentos digitais -
 * deteccao de duplicados (Fase 3, incremento 1). Testa o nucleo puro (as tres
 * classes, a janela de tempo que evita falsos positivos em mensalidades, vazio e
 * transacao unica) e o involucro por escola com um $wpdb simulado (grupos, totais
 * e fail-closed). Sem WordPress vivo.
 */
define('SIGE_RECON_TEST_MODE', true);
require_once dirname(__DIR__) . '/includes/payments/reconciliacao-divergencias.php';

$fails = []; $ok = 0;
$check = static function (bool $cond, string $label) use (&$fails, &$ok): void {
    if ($cond) { $ok++; echo "OK   {$label}\n"; } else { $fails[] = $label; echo "FAIL {$label}\n"; }
};
$has_motivo = static function (array $grupos, string $motivo): bool {
    foreach ($grupos as $g) if (($g['motivo'] ?? '') === $motivo) return true;
    return false;
};
$grupo_com_tx = static function (array $grupos, string $motivo, int $tx): bool {
    foreach ($grupos as $g) if (($g['motivo'] ?? '') === $motivo && in_array($tx, (array)($g['transacoes'] ?? []), true)) return true;
    return false;
};

// ---- Nucleo puro -----------------------------------------------------------
$txs = [
    ['id'=>1,'provider'=>'mpesa','referencia_mpesa'=>'REP1','msisdn'=>'841','valor'=>500,'estado'=>'recebida','pagamento_id'=>0,'criado_em'=>'2026-06-20 10:00:00'],
    ['id'=>2,'provider'=>'mpesa','referencia_mpesa'=>'REP1','msisdn'=>'841','valor'=>500,'estado'=>'conciliada','pagamento_id'=>10,'criado_em'=>'2026-06-20 10:05:00'],
    ['id'=>3,'provider'=>'mpesa','referencia_mpesa'=>'AAA','msisdn'=>'842','valor'=>300,'estado'=>'conciliada','pagamento_id'=>77,'criado_em'=>'2026-06-19 09:00:00'],
    ['id'=>4,'provider'=>'emola','referencia_mpesa'=>'BBB','msisdn'=>'843','valor'=>300,'estado'=>'conciliada','pagamento_id'=>77,'criado_em'=>'2026-06-19 09:30:00'],
    ['id'=>5,'provider'=>'mpesa','referencia_mpesa'=>'CCC','msisdn'=>'8499','valor'=>1500,'estado'=>'recebida','pagamento_id'=>0,'criado_em'=>'2026-06-18 08:00:00'],
    ['id'=>6,'provider'=>'mpesa','referencia_mpesa'=>'DDD','msisdn'=>'8499','valor'=>1500,'estado'=>'recebida','pagamento_id'=>0,'criado_em'=>'2026-06-18 08:10:00'],
    // mesmo pagador e valor mas com um mes de intervalo (mensalidade legitima)
    ['id'=>7,'provider'=>'mpesa','referencia_mpesa'=>'EEE','msisdn'=>'8500','valor'=>2000,'estado'=>'recebida','pagamento_id'=>0,'criado_em'=>'2026-05-01 08:00:00'],
    ['id'=>8,'provider'=>'mpesa','referencia_mpesa'=>'FFF','msisdn'=>'8500','valor'=>2000,'estado'=>'recebida','pagamento_id'=>0,'criado_em'=>'2026-06-01 08:00:00'],
];
$g = sige_pagamentos_detectar_duplicados($txs, 24);
$check($grupo_com_tx($g, 'referencia_repetida', 1) && $grupo_com_tx($g, 'referencia_repetida', 2), 'deteta referencia repetida (mesma provider+referencia)');
$check($grupo_com_tx($g, 'pagamento_duplo', 3) && $grupo_com_tx($g, 'pagamento_duplo', 4), 'deteta o mesmo pagamento conciliado mais de uma vez');
$check($grupo_com_tx($g, 'mesmo_pagador_valor', 5) && $grupo_com_tx($g, 'mesmo_pagador_valor', 6), 'deteta mesmo pagador e valor proximos no tempo');
$em_mensal = $grupo_com_tx($g, 'mesmo_pagador_valor', 7) || $grupo_com_tx($g, 'mesmo_pagador_valor', 8);
$check(!$em_mensal, 'mensalidades distantes no tempo nao sao marcadas (sem falsos positivos)');

$check(count(sige_pagamentos_detectar_duplicados([])) === 0, 'lista vazia nao produz grupos');
$unica = sige_pagamentos_detectar_duplicados([
    ['id'=>1,'provider'=>'mpesa','referencia_mpesa'=>'X','msisdn'=>'84','valor'=>100,'estado'=>'recebida','pagamento_id'=>0,'criado_em'=>'2026-06-20 10:00:00'],
]);
$check(count($unica) === 0, 'transacao unica nao e marcada como duplicado');

// janela curta separa pagamentos afastados
$g2 = sige_pagamentos_detectar_duplicados([
    ['id'=>1,'provider'=>'mpesa','referencia_mpesa'=>'P','msisdn'=>'84','valor'=>700,'estado'=>'recebida','pagamento_id'=>0,'criado_em'=>'2026-06-20 08:00:00'],
    ['id'=>2,'provider'=>'mpesa','referencia_mpesa'=>'Q','msisdn'=>'84','valor'=>700,'estado'=>'recebida','pagamento_id'=>0,'criado_em'=>'2026-06-20 12:00:00'],
], 1); // janela 1h, 4h de intervalo
$check(!$has_motivo($g2, 'mesmo_pagador_valor'), 'janela curta separa pagamentos afastados no tempo');

// ---- Involucro por escola com $wpdb simulado -------------------------------
class FakeWpdbDedup {
    public $prefix = 'wp_';
    public function prepare($sql, ...$a) { return $sql; }
    public function get_results($sql) {
        return [
            (object) ['id'=>1,'provider'=>'mpesa','referencia_mpesa'=>'DUP','msisdn'=>'84','valor'=>900.0,'estado'=>'recebida','pagamento_id'=>0,'criado_em'=>'2026-06-20 10:00:00'],
            (object) ['id'=>2,'provider'=>'mpesa','referencia_mpesa'=>'DUP','msisdn'=>'84','valor'=>900.0,'estado'=>'conciliada','pagamento_id'=>5,'criado_em'=>'2026-06-20 10:02:00'],
        ];
    }
}
$GLOBALS['wpdb'] = new FakeWpdbDedup();
$res = sige_reconciliacao_duplicados(7);
$check(($res['totais']['n_grupos'] ?? 0) >= 1 && ($res['totais']['n_transacoes'] ?? 0) === 2, 'involucro agrupa e conta as transacoes envolvidas');
$vazio = sige_reconciliacao_duplicados(0);
$check(($vazio['totais']['n_grupos'] ?? -1) === 0 && empty($vazio['grupos']), 'involucro e fail-closed para escola invalida');

echo str_repeat('-', 56) . "\n";
if ($fails) {
    fwrite(STDERR, 'SMOKE DETECCAO DE DUPLICADOS FALHOU: ' . count($fails) . " falha(s)\n");
    foreach ($fails as $f) fwrite(STDERR, " - {$f}\n");
    exit(1);
}
echo "SMOKE DETECCAO DE DUPLICADOS OK - {$ok} verificacoes passaram.\n";
