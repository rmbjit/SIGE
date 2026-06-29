<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Smoke de runtime: resolucao de divergencias com escrita
 * (quatro-olhos) (Fase 3, incremento 3). Testa a execucao (conciliar delega no
 * canonico e marca conciliada; rejeitar e idempotente e nunca rejeita conciliada;
 * fail-closed), as propostas (criam pedido via solicitar), e o despacho de
 * aprovacoes (encaminha para a execucao). Tudo com simulacoes; sem WordPress vivo.
 */
define('ABSPATH', '/tmp/');
define('SIGE_RECON_ACOES_TEST_MODE', true);

$fails = []; $ok = 0;
$check = static function (bool $cond, string $label) use (&$fails, &$ok): void {
    if ($cond) { $ok++; echo "OK   {$label}\n"; } else { $fails[] = $label; echo "FAIL {$label}\n"; }
};

// ---- Stubs de ambiente -----------------------------------------------------
function current_time($t) { return '2026-06-22 21:00:00'; }
function get_current_user_id() { return 7; }
function is_wp_error($x) { return $x instanceof WP_Error_Stub; }
class WP_Error_Stub { public $m; function __construct($m) { $this->m = $m; } function get_error_message() { return $this->m; } }
function sige_provider_rotulo($p) { return strtoupper((string)$p); }
function sige_provider_metodo($p) { return (string)$p; }
$GLOBALS['__log'] = []; function sige_security_log($e, $c = '') { $GLOBALS['__log'][] = $e; }
$GLOBALS['__solic'] = null;
function sige_fin_aprovacao_solicitar($tipo, $alvo, $params, $escola, $ref) { $GLOBALS['__solic'] = ['tipo' => $tipo, 'alvo' => $alvo, 'params' => $params]; return ['ok' => true, 'id' => 99]; }
$GLOBALS['__pago'] = null;
function sige_fin_registar_pagamento($lanc, $valor, $metodo, $ref) { $GLOBALS['__pago'] = ['lanc' => $lanc, 'valor' => $valor, 'metodo' => $metodo]; return 5000; }

class FakeWpdbRes {
    public $prefix = 'wp_'; public $updates = [];
    public function prepare($s, ...$a) { foreach ($a as $x) { $s = preg_replace('/%[ds]/', is_int($x) ? (string)$x : "'" . $x . "'", $s, 1); } return $s; }
    public function get_row($s) {
        if (strpos($s, 'WHERE id = 10') !== false) return (object) ['id' => 10, 'estado' => 'pendente_manual', 'escola_id' => 3, 'provider' => 'mpesa', 'referencia_mpesa' => 'R10', 'referencia_cliente' => 'PROC1', 'valor' => 500.0, 'aluno_id' => 0];
        if (strpos($s, 'WHERE id = 20') !== false) return (object) ['estado' => 'conciliada'];
        if (strpos($s, 'WHERE id = 21') !== false) return (object) ['estado' => 'recebida'];
        if (strpos($s, 'WHERE id = 22') !== false) return (object) ['estado' => 'rejeitada'];
        if (strpos($s, 'FROM wp_sige_fin_lancamentos') !== false) return (object) ['id' => 900, 'aluno_id' => 44, 'escola_id' => 3];
        return null;
    }
    public function get_var($s) { return 0; }
    public function update($t, $f, $w) { $this->updates[] = $f; return 1; }
}
$GLOBALS['wpdb'] = new FakeWpdbRes();

require_once dirname(__DIR__) . '/includes/payments/reconciliacao-accoes.php';

// ---- Execucao: conciliar (manual, delega no canonico) ----------------------
$GLOBALS['wpdb']->updates = []; $GLOBALS['__pago'] = null;
$r = sige_recon_executar_conciliar(10, 900, 3);
$check(!empty($r['ok']) && ($r['lancamento_id'] ?? 0) === 900 && ($r['pagamento_id'] ?? 0) === 5000, 'conciliar regista pelo caminho canonico e devolve lancamento e pagamento');
$check($GLOBALS['__pago'] && $GLOBALS['__pago']['lanc'] === 900 && $GLOBALS['__pago']['valor'] === 500.0, 'conciliar chama sige_fin_registar_pagamento com o lancamento e valor certos');
$check(!empty($GLOBALS['wpdb']->updates) && $GLOBALS['wpdb']->updates[0]['estado'] === 'conciliada' && $GLOBALS['wpdb']->updates[0]['conciliado_por'] === 'aprovacao:user:7', 'conciliar marca a transacao conciliada e regista o aprovador');

// ---- Execucao: rejeitar ----------------------------------------------------
$r2 = sige_recon_executar_rejeitar(21, 'duplicado', 3);
$check(!empty($r2['ok']) && ($r2['estado'] ?? '') === 'rejeitada', 'rejeitar uma transacao recebida marca rejeitada');
$r3 = sige_recon_executar_rejeitar(20, '', 3);
$check(empty($r3['ok']), 'rejeitar uma transacao ja conciliada falha (nunca rejeita conciliada)');
$r4 = sige_recon_executar_rejeitar(22, '', 3);
$check(!empty($r4['ok']) && ($r4['estado'] ?? '') === 'rejeitada', 'rejeitar uma transacao ja rejeitada e idempotente');

// ---- Fail-closed -----------------------------------------------------------
$check(empty(sige_recon_executar_conciliar(10, 900, 0)['ok']), 'conciliar e fail-closed para escola invalida');
$check(empty(sige_recon_executar_rejeitar(21, '', 0)['ok']), 'rejeitar e fail-closed para escola invalida');

// ---- Propostas (maker): criam pedido, nao executam -------------------------
$GLOBALS['__solic'] = null; sige_recon_propor_conciliacao(10, 900, 3);
$check($GLOBALS['__solic'] && $GLOBALS['__solic']['tipo'] === 'recon_conciliar' && (int)$GLOBALS['__solic']['params']['lancamento_id'] === 900, 'propor conciliacao cria pedido recon_conciliar com o lancamento');
$GLOBALS['__solic'] = null; sige_recon_propor_rejeicao(10, 'fraude', 3);
$check($GLOBALS['__solic'] && $GLOBALS['__solic']['tipo'] === 'recon_rejeitar' && $GLOBALS['__solic']['params']['motivo'] === 'fraude', 'propor rejeicao cria pedido recon_rejeitar com o motivo');

// ---- Despacho de aprovacoes encaminha para a execucao ----------------------
require_once dirname(__DIR__) . '/includes/finance-aprovacoes.php';
$tipos = sige_fin_aprovacao_tipos();
$check(isset($tipos['recon_conciliar']) && $tipos['recon_conciliar']['permissao'] === 'financeiro.mobile_payments_gerir', 'tipo recon_conciliar registado com a permissao reutilizada');
$check(isset($tipos['recon_rejeitar']) && $tipos['recon_rejeitar']['permissao'] === 'financeiro.mobile_payments_gerir', 'tipo recon_rejeitar registado com a permissao reutilizada');
$GLOBALS['wpdb']->updates = [];
$d1 = sige_fin_aprovacao_executar((object) ['tipo' => 'recon_rejeitar', 'alvo_id' => 21, 'parametros' => json_encode(['motivo' => 'x'])], 3);
$check(!empty($d1['ok']) && ($d1['estado'] ?? '') === 'rejeitada', 'o despacho de aprovacoes executa a rejeicao da reconciliacao');
$d2 = sige_fin_aprovacao_executar((object) ['tipo' => 'recon_conciliar', 'alvo_id' => 10, 'parametros' => json_encode(['lancamento_id' => 900])], 3);
$check(!empty($d2['ok']) && ($d2['lancamento_id'] ?? 0) === 900, 'o despacho de aprovacoes executa a conciliacao da reconciliacao');

echo str_repeat('-', 56) . "\n";
if ($fails) {
    fwrite(STDERR, 'SMOKE RESOLUCAO QUATRO-OLHOS FALHOU: ' . count($fails) . " falha(s)\n");
    foreach ($fails as $f) fwrite(STDERR, " - {$f}\n");
    exit(1);
}
echo "SMOKE RESOLUCAO QUATRO-OLHOS OK - {$ok} verificacoes passaram.\n";
