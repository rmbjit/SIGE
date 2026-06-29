<?php
// Acesso restrito: smoke corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SMOKE - Livro-razao financeiro v12.12.15 (Fase 6, incremento 1)
 * Prova: append constroi cadeia HMAC valida (genesis + encadeamento); o
 * verificador deteta adulteracao de conteudo, remocao (salto de sequencia) e
 * mudanca de chave (rotacao de salts). Usa um $wpdb em memoria; a cifra/HMAC e
 * real (hash_hmac), so os salts sao simulados.
 */
$root = dirname(__DIR__);
$fail = [];
$ok = 0;
$check = static function (string $label, bool $cond) use (&$fail, &$ok) {
    if ($cond) { $ok++; echo "OK   {$label}\n"; }
    else { $fail[] = $label; echo "FAIL {$label}\n"; }
};

define('SIGE_LEDGER_TEST_MODE', true);
define('ABSPATH', '/tmp/');
$GLOBALS['salt'] = 'SALT-ORIGINAL';
if (!function_exists('wp_salt'))            { function wp_salt($s=''){ return $GLOBALS['salt'] . '_' . $s; } }
if (!function_exists('get_current_user_id')){ function get_current_user_id(){ return 7; } }
if (!function_exists('get_userdata'))       { function get_userdata($id){ return (object)['ID'=>$id,'display_name'=>'Tester','user_login'=>'tester']; } }
if (!function_exists('current_time'))       { function current_time($t){ return '2026-06-20 05:00:00'; } }
if (!function_exists('wp_json_encode'))     { function wp_json_encode($d,$f=0){ return json_encode($d, $f); } }
$GLOBALS['ledger_anchor_base'] = sys_get_temp_dir() . '/sige_ledger_smoke_' . getmypid();
if (!function_exists('wp_upload_dir'))      { function wp_upload_dir(){ return ['basedir' => $GLOBALS['ledger_anchor_base']]; } }
if (!function_exists('wp_mkdir_p'))         { function wp_mkdir_p($d){ return is_dir($d) || @mkdir($d, 0755, true); } }

class FakeWpdb {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $last_error = '';
    public $rows = [];
    public $table_present = true;
    private $auto = 0;
    public function prepare($q, ...$args) {
        if (count($args) === 1 && is_array($args[0])) $args = $args[0];
        foreach ($args as $a) {
            $rep = is_int($a) ? (string)(int)$a : "'" . str_replace("'", "''", (string)$a) . "'";
            $q = preg_replace('/%d|%s/', $rep, $q, 1);
        }
        return $q;
    }
    public function get_var($q) {
        if (stripos($q, 'SHOW TABLES') !== false) {
            if (!$this->table_present) return null;
            if (preg_match("/'([^']+)'/", $q, $m)) return $m[1];
            return null;
        }
        if (stripos($q, 'GET_LOCK') !== false || stripos($q, 'RELEASE_LOCK') !== false) return 1;
        return null;
    }
    public function get_row($q) {
        if (preg_match('/escola_id=(\d+)/', $q, $m)) {
            $eid = (int)$m[1];
            $sub = array_values(array_filter($this->rows, fn($r) => (int)$r->escola_id === $eid));
            if (!$sub) return null;
            usort($sub, fn($a,$b) => $b->seq <=> $a->seq);
            return $sub[0];
        }
        return null;
    }
    public function get_results($q) {
        if (preg_match('/escola_id=(\d+)/', $q, $m)) {
            $eid = (int)$m[1];
            $sub = array_values(array_filter($this->rows, fn($r) => (int)$r->escola_id === $eid));
            usort($sub, fn($a,$b) => $a->seq <=> $b->seq);
            return $sub;
        }
        return [];
    }
    public function get_col($q) {
        $ids = array_values(array_unique(array_map(fn($r) => (int)$r->escola_id, $this->rows)));
        sort($ids);
        return $ids;
    }
    public function insert($table, $row) {
        $this->auto++;
        $obj = (object)$row;
        $obj->id = $this->auto;
        $this->rows[] = $obj;
        $this->insert_id = $this->auto;
        return 1;
    }
}
$GLOBALS['wpdb'] = new FakeWpdb();
global $wpdb; $wpdb = $GLOBALS['wpdb'];

require $root . '/includes/finance-ledger.php';

// ---- 0. Resiliencia: sem tabela, degrada sem erro ---------------------------
$wpdb->table_present = false;
$r0 = sige_ledger_append('fin_cancelar_lancamento', 'lancamento', 1, 10.00, [], 5);
$v0 = sige_ledger_verify(5);
$s0 = sige_ledger_schools();
$check('sem tabela: append nao regista (devolve false)', $r0 === false);
$check('sem tabela: verify devolve vazio sem erro', $v0['ok'] === true && $v0['total'] === 0);
$check('sem tabela: lista de escolas vazia', $s0 === []);
$wpdb->table_present = true; // a partir daqui a tabela existe

// ---- 1. Cadeia valida (escola 5) --------------------------------------------
$a = sige_ledger_append('fin_cancelar_lancamento', 'lancamento', 101, 1500.00, ['motivo' => 'erro de registo'], 5);
$b = sige_ledger_append('fin_estornar_pagamento', 'pagamento', 202, 750.50, ['motivo' => 'duplicado'], 5);
$c = sige_ledger_append('fin_isentar_lancamento', 'lancamento', 303, 0, ['motivo' => 'bolseiro'], 5);
$check('append devolve ok e seq crescente', $a && $b && $c && $a['seq'] === 1 && $b['seq'] === 2 && $c['seq'] === 3);

$rows = $wpdb->get_results("SELECT * FROM wp_sige_fin_ledger WHERE escola_id=5 ORDER BY seq ASC");
$check('primeira entrada encadeia ao genesis', $rows[0]->prev_hash === SIGE_LEDGER_GENESIS);
$check('segunda entrada encadeia a primeira', $rows[1]->prev_hash === $rows[0]->hash);
$check('terceira entrada encadeia a segunda', $rows[2]->prev_hash === $rows[1]->hash);

$v = sige_ledger_verify(5);
$check('verificador: cadeia integra', $v['ok'] === true && $v['total'] === 3 && $v['broken_seq'] === null);

// ---- 2. Adulteracao de conteudo (muda o montante de uma entrada) -------------
$rows[1]->montante = '999999.99'; // adultera a entrada seq 2 em memoria
$v2 = sige_ledger_verify(5);
$check('verificador deteta adulteracao de conteudo', $v2['ok'] === false && $v2['broken_seq'] === 2 && strpos($v2['reason'], 'hash') !== false);
$rows[1]->montante = '750.50'; // repor para os testes seguintes

// ---- 3. Remocao de uma entrada (salto de sequencia) - escola 6 ---------------
sige_ledger_append('fin_cancelar_lancamento', 'lancamento', 1, 100.00, [], 6);
sige_ledger_append('fin_cancelar_lancamento', 'lancamento', 2, 200.00, [], 6);
sige_ledger_append('fin_cancelar_lancamento', 'lancamento', 3, 300.00, [], 6);
// apagar a entrada do meio (seq 2) da escola 6
$wpdb->rows = array_values(array_filter($wpdb->rows, fn($r) => !((int)$r->escola_id === 6 && (int)$r->seq === 2)));
$v3 = sige_ledger_verify(6);
$check('verificador deteta remocao (salto de sequencia)', $v3['ok'] === false && $v3['broken_seq'] === 3 && strpos($v3['reason'], 'sequencia') !== false);

// ---- 4. Mudanca de chave (rotacao de salts) - escola 7 ----------------------
sige_ledger_append('fin_isentar_lancamento', 'lancamento', 9, 50.00, [], 7);
sige_ledger_append('fin_isentar_lancamento', 'lancamento', 10, 60.00, [], 7);
$vbefore = sige_ledger_verify(7);
$GLOBALS['salt'] = 'SALT-NOVO-APOS-ROTACAO'; // simula rotacao de salts
$vafter = sige_ledger_verify(7);
$GLOBALS['salt'] = 'SALT-ORIGINAL';
$check('com a chave certa a escola 7 estava integra', $vbefore['ok'] === true);
$check('apos rotacao de salts a verificacao assinala (chave necessaria)', $vafter['ok'] === false && $vafter['broken_seq'] === 1);

// ---- 5. Escolas com entradas -------------------------------------------------
$schools = sige_ledger_schools();
$check('lista de escolas inclui 5, 6 e 7', in_array(5, $schools, true) && in_array(6, $schools, true) && in_array(7, $schools, true));

// ---- 6. HMAC depende mesmo da chave -----------------------------------------
$f = ['escola_id'=>1,'seq'=>1,'event_type'=>'x','entidade'=>'y','entidade_id'=>0,'montante'=>null,'actor_user_id'=>0,'ocorrido_em'=>'2026-01-01 00:00:00','payload'=>'[]'];
$h1 = sige_ledger_compute_hash($f, SIGE_LEDGER_GENESIS);
$GLOBALS['salt'] = 'OUTRA';
$h2 = sige_ledger_compute_hash($f, SIGE_LEDGER_GENESIS);
$GLOBALS['salt'] = 'SALT-ORIGINAL';
$check('hash muda com a chave (HMAC com chave dos salts)', $h1 !== $h2 && strlen($h1) === 64);

// ---- 7. Ancora externa: escrita e truncagem da cauda (escola 8) --------------
sige_ledger_append('fin_registar_pagamento', 'pagamento', 11, 1000.00, ['metodo' => 'mpesa'], 8);
sige_ledger_append('fin_registar_pagamento', 'pagamento', 12, 2000.00, ['metodo' => 'numerario'], 8);
sige_ledger_append('fin_registar_pagamento', 'pagamento', 13, 3000.00, ['metodo' => 'emola'], 8);
$anchor8 = sige_ledger_anchor_read(8);
$check('ancora escrita apos append (cabeca na seq 3)', $anchor8 !== null && $anchor8['seq'] === 3);
$v8 = sige_ledger_verify(8);
$check('com ancora: cadeia integra e ancora confirmada', $v8['ok'] === true && ($v8['anchor'] ?? '') === 'confirmada');
// truncar a cauda: apagar a ultima entrada (seq 3) SO na base de dados
$wpdb->rows = array_values(array_filter($wpdb->rows, fn($r) => !((int)$r->escola_id === 8 && (int)$r->seq === 3)));
$v8t = sige_ledger_verify(8);
$check('ancora deteta truncagem da cauda', $v8t['ok'] === false && strpos($v8t['reason'], 'truncagem') !== false && $v8t['broken_seq'] === 3);

// ---- 8. Ancora divergente da cabeca (escola 9) ------------------------------
sige_ledger_append('fin_registar_pagamento', 'pagamento', 21, 500.00, [], 9);
sige_ledger_append('fin_registar_pagamento', 'pagamento', 22, 600.00, [], 9);
sige_ledger_anchor_write(9, 2, str_repeat('a', 64), 2); // ancora com hash falso na cabeca
$v9 = sige_ledger_verify(9);
$check('ancora divergente da cabeca e detectada', $v9['ok'] === false && ($v9['anchor'] ?? '') === 'divergente');

// ---- 9. Ancora ausente (escola 10) ------------------------------------------
sige_ledger_append('fin_registar_pagamento', 'pagamento', 31, 700.00, [], 10);
@unlink(sige_ledger_anchor_path(10));
$v10 = sige_ledger_verify(10);
$check('ancora ausente assinalada sem falso positivo', $v10['ok'] === true && ($v10['anchor'] ?? '') === 'ausente');

// ---- 10. Escrita em bloco (append_many) - escola 11 -------------------------
$n = sige_ledger_append_many(11, [
    ['event_type' => 'fin_criar_lancamento', 'entidade' => 'lancamento', 'entidade_id' => 101, 'montante' => 500.00, 'payload' => ['mes' => '2026-01']],
    ['event_type' => 'fin_criar_lancamento', 'entidade' => 'lancamento', 'entidade_id' => 102, 'montante' => 500.00, 'payload' => ['mes' => '2026-01']],
    ['event_type' => 'fin_criar_lancamento', 'entidade' => 'lancamento', 'entidade_id' => 103, 'montante' => 750.00, 'payload' => ['mes' => '2026-01']],
]);
$check('escrita em bloco grava todos os eventos', $n === 3);
$v11 = sige_ledger_verify(11);
$check('cadeia do bloco integra e ancora confirmada', $v11['ok'] === true && $v11['total'] === 3 && ($v11['anchor'] ?? '') === 'confirmada');
$rows11 = $wpdb->get_results("SELECT * FROM wp_sige_fin_ledger WHERE escola_id=11 ORDER BY seq ASC");
$check('bloco encadeia ao genesis e entre si', $rows11[0]->prev_hash === SIGE_LEDGER_GENESIS && $rows11[1]->prev_hash === $rows11[0]->hash && $rows11[2]->prev_hash === $rows11[1]->hash);

// ---- 11. Buffer diferido de cobrancas + flush - escola 12 -------------------
sige_ledger_record_charge('fin_criar_lancamento', 'lancamento', 201, 300.00, ['mes' => '2026-02'], 12);
sige_ledger_record_charge('fin_criar_lancamento', 'lancamento', 202, 300.00, ['mes' => '2026-02'], 12);
sige_ledger_record_charge('fin_actualizar_lancamento', 'lancamento', 201, 350.00, ['mes' => '2026-02', 'tipo' => 'valor'], 12);
$antes = $wpdb->get_results("SELECT * FROM wp_sige_fin_ledger WHERE escola_id=12 ORDER BY seq ASC");
$check('buffer acumula sem gravar ja', count($antes) === 0);
sige_ledger_flush_charges();
$v12 = sige_ledger_verify(12);
$check('apos flush: 3 eventos e cadeia integra', $v12['ok'] === true && $v12['total'] === 3 && ($v12['anchor'] ?? '') === 'confirmada');

// ---- 12. Misto: imediato e diferido na mesma cadeia - escola 13 ------------
sige_ledger_append('fin_estornar_pagamento', 'pagamento', 9, 100.00, [], 13);
sige_ledger_record_charge('fin_criar_lancamento', 'lancamento', 301, 200.00, [], 13);
sige_ledger_flush_charges();
$v13 = sige_ledger_verify(13);
$check('imediato e diferido na mesma cadeia integra', $v13['ok'] === true && $v13['total'] === 2);

// ---- 13. Despesas, creditos e fechos (incr 4) - escola 14 ------------------
sige_ledger_append('fin_criar_despesa', 'despesa', 1, 1200.00, ['categoria' => 'material'], 14);
sige_ledger_append('fin_despesa_transitar', 'despesa', 1, null, ['de' => 'registado', 'para' => 'pago'], 14);
sige_ledger_append('fin_criar_credito', 'credito', 5, 80.00, ['aluno_id' => 9], 14);
sige_ledger_append('fin_fechar_turno', 'fecho', 3, 9500.00, ['total_bruto' => 10000.00], 14);
sige_ledger_append('fin_reabrir_turno', 'fecho', 3, null, ['motivo' => 'correccao'], 14);
$v14 = sige_ledger_verify(14);
$check('despesas, creditos e fechos: 5 eventos e cadeia integra', $v14['ok'] === true && $v14['total'] === 5 && ($v14['anchor'] ?? '') === 'confirmada');
$rows14 = $wpdb->get_results("SELECT event_type FROM wp_sige_fin_ledger WHERE escola_id=14 ORDER BY seq ASC");
$tipos14 = array_map(fn($r) => $r->event_type, $rows14);
$check('os 5 tipos de evento estao registados', $tipos14 === ['fin_criar_despesa','fin_despesa_transitar','fin_criar_credito','fin_fechar_turno','fin_reabrir_turno']);

// limpeza do temp da ancora
$base = $GLOBALS['ledger_anchor_base'];
if (is_dir($base)) { foreach (glob($base . '/sige-private/ledger/*') as $f) @unlink($f); }

echo "\n";
if ($fail) {
    fwrite(STDERR, 'SMOKE LEDGER FALHOU: ' . count($fail) . " falha(s)\n - " . implode("\n - ", $fail) . "\n");
    exit(1);
}
echo "SMOKE LEDGER OK - {$ok} verificacoes passaram (cadeia HMAC, deteccao de adulteracao/remocao/chave).\n";
exit(0);
