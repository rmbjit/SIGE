<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke da regra de quatro-olhos (Fase 7 incremento 2).
 *
 * Exercita sige_fin_aprovacao_solicitar / _decidir de forma isolada, com um
 * $wpdb simulado (tabela em memoria) e stubs das execucoes subjacentes.
 * Confirma: criacao de pedido, anti-duplicado, proibicao de auto-aprovacao,
 * aprovacao por utilizador diferente (executa), rejeicao, fail-closed e o
 * caminho de MFA (pedido fica pendente).
 */

define('SIGE_APROVACOES_TEST_MODE', true);

$pass = 0; $fail = 0;
$check = function (string $nome, bool $ok) use (&$pass, &$fail) {
    if ($ok) { $pass++; echo "OK   {$nome}\n"; }
    else { $fail++; echo "FALHA {$nome}\n"; }
};

// ---- Estado de teste controlavel -----------------------------------------
$GLOBALS['TEST_UID'] = 10;
$GLOBALS['TEST_PERMS'] = ['financeiro.estornar', 'financeiro.caixa_reabrir'];
$GLOBALS['TEST_ESTORNO_RESULT'] = ['ok' => true, 'pagamento_original_id' => 77, 'valor_estornado' => 500.0];
$GLOBALS['TEST_REABERTURA_RESULT'] = ['ok' => true, 'data_caixa' => '2026-06-20'];

// ---- Stubs WordPress / SIGE ----------------------------------------------
function get_current_user_id() { return (int) ($GLOBALS['TEST_UID'] ?? 0); }
function wp_get_current_user() { return (object) ['display_name' => 'User ' . (int) ($GLOBALS['TEST_UID'] ?? 0)]; }
function current_time($t = 'mysql') { return '2026-06-21 12:00:00'; }
function wp_json_encode($v) { return json_encode($v); }
function sige_can($perm, $ctx = null, $uid = null) { return in_array($perm, $GLOBALS['TEST_PERMS'], true); }
function sige_is_real_wp_admin_user() { return false; }
function sige_tenant_write_guard($escola_id, $ctx = '') { return (int) $escola_id > 0; }
function sige_fin_log($acao, $det = null) {}
function sige_ledger_append($t, $e, $eid, $m, $p = [], $esc = 0) { $GLOBALS['LEDGER'][] = $t; }
function sige_fin_reabertura_caixa_executar($data_caixa, $motivo, $escola_id) { return $GLOBALS['TEST_REABERTURA_RESULT']; }
class SIGE_FinanceActionService {
    public static function estornarPagamento($id, $motivo, $escola_id, $valor = 0.0) { return $GLOBALS['TEST_ESTORNO_RESULT']; }
}

// ---- $wpdb simulado (tabela em memoria) ----------------------------------
class FakeWpdbAprov {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $rows = [];
    private $auto = 0;
    public function prepare($sql, ...$args) {
        if (count($args) === 1 && is_array($args[0])) $args = $args[0];
        $i = 0;
        return preg_replace_callback('/%[dsf]/', function ($m) use (&$i, $args) {
            $a = $args[$i++] ?? '';
            if ($m[0] === '%d') return (string) (int) $a;
            if ($m[0] === '%f') return (string) (float) $a;
            return "'" . addslashes((string) $a) . "'";
        }, $sql);
    }
    public function insert($table, $data) {
        $this->auto++;
        $row = (object) $data;
        $row->id = $this->auto;
        $this->rows[$this->auto] = $row;
        $this->insert_id = $this->auto;
        return 1;
    }
    public function update($table, $data, $where) {
        $id = (int) ($where['id'] ?? 0);
        if (isset($this->rows[$id])) {
            foreach ($data as $k => $v) $this->rows[$id]->$k = $v;
            return 1;
        }
        return 0;
    }
    public function get_var($sql) {
        if (preg_match("/alvo_id = (\d+) AND estado = 'pendente'/", $sql, $m)) {
            $alvo = (int) $m[1];
            preg_match("/tipo = '([^']+)'/", $sql, $mt);
            preg_match("/escola_id = (\d+)/", $sql, $me);
            $tipo = $mt[1] ?? '';
            $esc = (int) ($me[1] ?? 0);
            foreach ($this->rows as $r) {
                if ((int) $r->alvo_id === $alvo && $r->tipo === $tipo && (int) $r->escola_id === $esc && $r->estado === 'pendente') return (string) $r->id;
            }
            return null;
        }
        return null;
    }
    public function get_row($sql) {
        if (preg_match('/id = (\d+) AND escola_id = (\d+)/', $sql, $m)) {
            $id = (int) $m[1]; $esc = (int) $m[2];
            if (isset($this->rows[$id]) && (int) $this->rows[$id]->escola_id === $esc) return $this->rows[$id];
        }
        return null;
    }
    public function get_results($sql) {
        preg_match('/escola_id = (\d+)/', $sql, $me);
        $esc = (int) ($me[1] ?? 0);
        $res = [];
        if (preg_match("/estado = '([^']+)'/", $sql, $m)) {
            foreach ($this->rows as $r) if ((int) $r->escola_id === $esc && $r->estado === $m[1]) $res[] = $r;
        } else {
            foreach ($this->rows as $r) if ((int) $r->escola_id === $esc) $res[] = $r;
        }
        return $res;
    }
}
$GLOBALS['wpdb'] = new FakeWpdbAprov();
$GLOBALS['LEDGER'] = [];

require __DIR__ . '/../includes/finance-aprovacoes.php';

$check('modulo carrega', function_exists('sige_fin_aprovacao_solicitar') && function_exists('sige_fin_aprovacao_decidir'));

// 1. Criar um pedido de estorno (utilizador 10).
$GLOBALS['TEST_UID'] = 10;
$r1 = sige_fin_aprovacao_solicitar('estorno_pagamento', 77, ['motivo' => 'duplicado', 'valor' => 500.0], 14, 'pagamento #77');
$check('pedido de estorno criado', !empty($r1['ok']) && (int) $r1['id'] === 1);
$check('ledger registou a solicitacao', in_array('fin_aprovacao_solicitada', $GLOBALS['LEDGER'], true));

// 2. Anti-duplicado: segundo pedido para o mesmo alvo e recusado.
$r1b = sige_fin_aprovacao_solicitar('estorno_pagamento', 77, ['motivo' => 'outra vez', 'valor' => 500.0], 14, 'pagamento #77');
$check('pedido duplicado recusado', empty($r1b['ok']) && strpos((string) ($r1b['error'] ?? ''), 'pendente') !== false);

// 3. Auto-aprovacao proibida (mesmo utilizador que solicitou).
$GLOBALS['TEST_UID'] = 10;
$rauto = sige_fin_aprovacao_decidir(1, true, '', 14);
$check('auto-aprovacao bloqueada', empty($rauto['ok']) && strpos((string) ($rauto['error'] ?? ''), 'proprio') !== false);
$check('pedido continua pendente apos auto-aprovacao', $GLOBALS['wpdb']->rows[1]->estado === 'pendente');

// 4. Aprovacao por utilizador DIFERENTE executa o estorno.
$GLOBALS['TEST_UID'] = 20;
$rap = sige_fin_aprovacao_decidir(1, true, 'confirmado', 14);
$check('aprovacao por outro utilizador executa', !empty($rap['ok']) && ($rap['estado'] ?? '') === 'executada');
$check('estado persistido = executada', $GLOBALS['wpdb']->rows[1]->estado === 'executada');
$check('aprovador registado = 20', (int) $GLOBALS['wpdb']->rows[1]->aprovador_user_id === 20);
$check('ledger registou a aprovacao', in_array('fin_aprovacao_aprovada', $GLOBALS['LEDGER'], true));

// 5. Decidir um pedido ja decidido e recusado.
$rre = sige_fin_aprovacao_decidir(1, true, '', 14);
$check('nao se decide um pedido ja decidido', empty($rre['ok']));

// 6. Rejeicao por utilizador diferente.
$GLOBALS['TEST_UID'] = 10;
$r2 = sige_fin_aprovacao_solicitar('estorno_pagamento', 88, ['motivo' => 'engano', 'valor' => 200.0], 14, 'pagamento #88');
$GLOBALS['TEST_UID'] = 30;
$rrej = sige_fin_aprovacao_decidir((int) $r2['id'], false, 'sem fundamento', 14);
$check('rejeicao aceite', !empty($rrej['ok']) && ($rrej['estado'] ?? '') === 'rejeitada');
$check('estado persistido = rejeitada', $GLOBALS['wpdb']->rows[(int) $r2['id']]->estado === 'rejeitada');
$check('ledger registou a rejeicao', in_array('fin_aprovacao_rejeitada', $GLOBALS['LEDGER'], true));

// 7. Fail-closed por escola.
$rfc = sige_fin_aprovacao_solicitar('estorno_pagamento', 99, ['motivo' => 'x', 'valor' => 1.0], 0, 'p');
$check('fail-closed por escola (<=0)', empty($rfc['ok']));

// 8. Reabertura: pedido + aprovacao por outro executa via stub.
$GLOBALS['TEST_UID'] = 10;
$r3 = sige_fin_aprovacao_solicitar('reabertura_caixa', 5, ['data_caixa' => '2026-06-20', 'motivo' => 'correccao'], 14, 'caixa 2026-06-20');
$check('pedido de reabertura criado', !empty($r3['ok']));
$GLOBALS['TEST_UID'] = 20;
$r3d = sige_fin_aprovacao_decidir((int) $r3['id'], true, 'ok', 14);
$check('reabertura aprovada executa', !empty($r3d['ok']) && ($r3d['estado'] ?? '') === 'executada');

// 9. Caminho de MFA: execucao pede MFA, pedido fica pendente.
$GLOBALS['TEST_ESTORNO_RESULT'] = ['ok' => false, 'mfa_required' => true, 'error' => 'Confirme a identidade.'];
$GLOBALS['TEST_UID'] = 10;
$r4 = sige_fin_aprovacao_solicitar('estorno_pagamento', 111, ['motivo' => 'rever', 'valor' => 50.0], 14, 'pagamento #111');
$GLOBALS['TEST_UID'] = 20;
$r4d = sige_fin_aprovacao_decidir((int) $r4['id'], true, 'ok', 14);
$check('MFA pendente devolvido na aprovacao', empty($r4d['ok']) && !empty($r4d['mfa_required']));
$check('pedido continua pendente apos MFA', $GLOBALS['wpdb']->rows[(int) $r4['id']]->estado === 'pendente');

// ---- Resultado ------------------------------------------------------------
echo "\n{$pass} OK / {$fail} FALHA\n";
exit($fail === 0 ? 0 : 1);
