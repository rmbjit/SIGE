<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

/**
 * Gate: Livro-razao financeiro - v12.12.15 (Fase 6, incremento 1).
 * Garante que o ledger existe e usa HMAC com chave dos salts, que e append-only
 * (sem UPDATE/DELETE sobre a tabela no codigo), que as 6 operacoes criticas o
 * alimentam, que a tabela esta na migracao, que o ecra de integridade e so para
 * super admin, e que nao ha endpoint novo.
 */
$root = dirname(__DIR__);
$fails = [];
$read = static function (string $rel) use ($root): string {
    $abs = $root . '/' . $rel;
    return is_file($abs) ? (string) file_get_contents($abs) : '';
};

// Stubs minimos para carregar as funcoes puras.
define('SIGE_LEDGER_TEST_MODE', true);
define('ABSPATH', '/tmp/');
$GLOBALS['salt'] = 'S1';
if (!function_exists('wp_salt'))        { function wp_salt($s=''){ return $GLOBALS['salt'] . '_' . $s; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($d,$f=0){ return json_encode($d,$f); } }

$modulo = $root . '/includes/finance-ledger.php';
if (!file_exists($modulo)) { fwrite(STDERR, "modulo includes/finance-ledger.php ausente\n"); exit(1); }
require_once $modulo;
$mod = $read('includes/finance-ledger.php');
$svc = $read('includes/fin-action-service.php');
$mig = $read('includes/class-sige-migration.php');
$core = $read('includes/finance-core.php');

// 1. Primitivas ----------------------------------------------------------------
foreach (['sige_ledger_hmac_key','sige_ledger_canonical','sige_ledger_compute_hash','sige_ledger_append','sige_ledger_verify','sige_ledger_render_integrity_page'] as $fn) {
    if (!function_exists($fn)) $fails[] = "primitiva ausente: {$fn}()";
}

// 2. HMAC com chave dos salts --------------------------------------------------
if (strpos($mod, 'hash_hmac') === false) $fails[] = 'o ledger nao usa hash_hmac';
if (strpos($mod, 'wp_salt') === false) $fails[] = 'a chave do ledger nao deriva dos salts do WordPress';

// 3. Runtime: invariantes do HMAC ----------------------------------------------
if (function_exists('sige_ledger_compute_hash')) {
    $f = ['escola_id'=>1,'seq'=>1,'event_type'=>'e','entidade'=>'x','entidade_id'=>5,'montante'=>'10.00','actor_user_id'=>2,'ocorrido_em'=>'2026-01-01 00:00:00','payload'=>'[]'];
    $h1 = sige_ledger_compute_hash($f, str_repeat('0', 64));
    $h1b = sige_ledger_compute_hash($f, str_repeat('0', 64));
    if ($h1 !== $h1b) $fails[] = 'hash nao deterministico para a mesma entrada';
    if (strlen($h1) !== 64 || !ctype_xdigit($h1)) $fails[] = 'hash nao e 64 hex (sha256)';
    $GLOBALS['salt'] = 'S2';
    $h2 = sige_ledger_compute_hash($f, str_repeat('0', 64));
    $GLOBALS['salt'] = 'S1';
    if ($h1 === $h2) $fails[] = 'hash nao depende da chave (HMAC sem chave efectiva)';
    // mudar um campo muda o hash
    $f2 = $f; $f2['montante'] = '99.00';
    if (sige_ledger_compute_hash($f2, str_repeat('0', 64)) === $h1) $fails[] = 'alteracao de conteudo nao muda o hash';
    if (sige_ledger_hmac_key() === '' || strlen(sige_ledger_hmac_key()) !== 32) $fails[] = 'chave HMAC invalida';
}

// 4. Disciplina append-only: sem UPDATE/DELETE sobre a tabela no codigo --------
$ledger_dml = [];
foreach (['includes/finance-ledger.php','includes/fin-action-service.php'] as $rel) {
    $src = $read($rel);
    if (preg_match('/->\s*update\s*\([^;]*sige_fin_ledger/s', $src)) $ledger_dml[] = "{$rel}: update sobre o ledger";
    if (preg_match('/->\s*delete\s*\([^;]*sige_fin_ledger/s', $src)) $ledger_dml[] = "{$rel}: delete sobre o ledger";
    if (preg_match('/\bUPDATE\b[^;]*sige_fin_ledger/s', $src)) $ledger_dml[] = "{$rel}: UPDATE SQL sobre o ledger";
    if (preg_match('/\bDELETE\b[^;]*sige_fin_ledger/s', $src)) $ledger_dml[] = "{$rel}: DELETE SQL sobre o ledger";
}
if ($ledger_dml) $fails[] = 'ledger nao e append-only: ' . implode('; ', $ledger_dml);

// 5. As 6 operacoes criticas alimentam o ledger --------------------------------
if (substr_count($svc, 'sige_ledger_append(') < 6) $fails[] = 'menos de 6 operacoes criticas instrumentadas';
foreach (['fin_cancelar_lancamento','fin_isentar_lancamento','fin_reactivar_lancamento','fin_bloquear_mes','fin_desbloquear_mes','fin_estornar_pagamento'] as $ev) {
    if (strpos($svc, "'{$ev}'") === false) $fails[] = "evento {$ev} nao registado no ledger";
}

// 5.1 Pagamentos instrumentados (Fase 6 incr 2) -------------------------------
if (strpos($core, "sige_ledger_append('fin_registar_pagamento'") === false) $fails[] = 'registo de pagamento nao instrumentado no ledger';

// 5.1.1 Lancamentos instrumentados (Fase 6 incr 3): criacao e alteracao de valor
if (strpos($core, "sige_ledger_record_charge('fin_criar_lancamento'") === false) $fails[] = 'criacao de lancamento nao instrumentada no ledger';
if (strpos($core, "sige_ledger_record_charge('fin_actualizar_lancamento'") === false) $fails[] = 'alteracao de valor de lancamento nao instrumentada no ledger';
foreach (['sige_ledger_append_many','sige_ledger_record_charge','sige_ledger_flush_charges'] as $fn) {
    if (!function_exists($fn)) $fails[] = "primitiva de escrita em bloco ausente: {$fn}()";
}
if (strpos($mod, "add_action('shutdown', 'sige_ledger_flush_charges'") === false) $fails[] = 'flush diferido nao ligado ao shutdown';
if (strpos($mod, 'GET_LOCK') === false || substr_count($mod, 'sige_ledger_anchor_write(') < 2) $fails[] = 'escrita em bloco nao usa um unico bloqueio e uma unica ancora';

// 5.1.2 Despesas, creditos e fechos instrumentados (Fase 6 incr 4) ------------
$incr4 = [
    'fin_criar_despesa'     => $read('admin/finance/financeiro-despesas-view.php'),
    'fin_despesa_transitar' => $read('includes/despesa-state-machine.php'),
    'fin_criar_credito'     => $core,
    'fin_fechar_turno'      => $read('includes/fin-fecho-turno.php'),
    'fin_reabrir_turno'     => $read('includes/fin-fecho-turno.php'),
];
foreach ($incr4 as $ev => $src) {
    if (strpos($src, "'{$ev}'") === false) $fails[] = "evento {$ev} nao instrumentado no ledger";
}

// 5.2 Ancora externa (Fase 6 incr 2): fora da base de dados --------------------
foreach (['sige_ledger_anchor_dir','sige_ledger_anchor_write','sige_ledger_anchor_read','sige_ledger_anchor_path'] as $fn) {
    if (!function_exists($fn)) $fails[] = "primitiva de ancora ausente: {$fn}()";
}
if (strpos($mod, 'wp_upload_dir') === false) $fails[] = 'a ancora nao usa um local fora da base de dados (wp_upload_dir)';
if (strpos($mod, 'rename(') === false) $fails[] = 'a ancora nao e escrita de forma atomica (temp + rename)';
if (strpos($mod, 'sige_ledger_anchor_write(') === false || strpos($mod, 'sige_ledger_anchor_read(') === false) $fails[] = 'ancora nao integrada no escritor/verificador';
if (strpos($mod, 'truncagem da cauda') === false) $fails[] = 'verificador nao deteta truncagem da cauda via ancora';

// 6. Tabela na migracao + gate de schema subido (senao a migracao nao a cria) -
if (strpos($mig, 'sige_fin_ledger') === false || stripos($mig, 'CREATE TABLE') === false) $fails[] = 'tabela sige_fin_ledger ausente da migracao';
if (strpos($mig, 'UNIQUE KEY uniq_escola_seq') === false) $fails[] = 'falta a chave unica (escola_id, seq) na tabela do ledger';
if (preg_match("/const\s+SCHEMA_VERSION\s*=\s*'20260611\.1'/", $mig)) $fails[] = 'SCHEMA_VERSION nao foi subida ao acrescentar a tabela (maybe_upgrade nao a criaria nas instalacoes existentes)';

// 6.1 Resiliencia: o ledger degrada sem erro quando a tabela nao existe --------
if (strpos($mod, 'sige_ledger_table_exists') === false) $fails[] = 'falta o guard de existencia da tabela (sige_ledger_table_exists)';
if (substr_count($mod, 'sige_ledger_table_exists(') < 4) $fails[] = 'guard de existencia da tabela nao aplicado ao escritor, verificador, lista e ecra';

// 7. Ecra de integridade so para super admin -----------------------------------
if (strpos($mod, 'sige_is_real_wp_admin_user') === false) $fails[] = 'ecra de integridade nao restrito ao super admin';

// 8. Carregamento e governanca -------------------------------------------------
if (strpos($read('sige-softgenial.php'), 'finance-ledger.php') === false) $fails[] = 'ledger nao carregado no bootstrap';
$mf = json_decode($read('docs/security/ACTION_SURFACE_MANIFEST-v12.12.15.json'), true);
if (!is_array($mf) || count($mf['items'] ?? []) !== 196) $fails[] = 'manifesto v12.12.15 nao tem 196 itens (nao deve haver endpoint novo)';

if ($fails) {
    fwrite(STDERR, "LEDGER GATE FALHOU\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo "LEDGER OK - livro-razao append-only encadeado por HMAC, cobertura financeira completa (6 operacoes criticas, pagamentos, lancamentos, despesas, creditos e fechos), escrita em bloco diferida, ancora externa contra truncagem da cauda, ecra so super admin, manifesto 196 inalterado.\n";
exit(0);
