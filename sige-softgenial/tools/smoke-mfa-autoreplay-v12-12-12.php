<?php
// Acesso restrito: smoke corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SMOKE - MFA de Operacao: reposicao automatica (v12.12.12)
 * Estatico (modulo, carregamento, captura nas 6 operacoes, handler de
 * confirmacao, aviso) e runtime com servico-stub que conta chamadas: captura,
 * consumo de uso unico (sem execucao dupla), passagem de argumentos e o gating
 * por opcao. Nao requer bootstrap do WordPress.
 */
$root = dirname(__DIR__);
$fail = [];
$ok = 0;
$check = static function (string $label, bool $cond) use (&$fail, &$ok) {
    if ($cond) { $ok++; echo "OK   {$label}\n"; }
    else { $fail[] = $label; echo "FAIL {$label}\n"; }
};
$read = static function (string $rel) use ($root): string {
    $abs = $root . '/' . $rel;
    return is_file($abs) ? (string) file_get_contents($abs) : '';
};

// ---- Stubs em memoria + servico-stub que conta chamadas ----------------------
define('SIGE_MFA_REPLAY_TEST_MODE', true);
$GLOBALS['t_trans'] = [];
$GLOBALS['opt'] = [];
$GLOBALS['cur_uid'] = 7001;
if (!function_exists('get_transient'))      { function get_transient($k){ return $GLOBALS['t_trans'][$k] ?? false; } }
if (!function_exists('set_transient'))      { function set_transient($k,$v,$ttl=0){ $GLOBALS['t_trans'][$k]=$v; return true; } }
if (!function_exists('delete_transient'))   { function delete_transient($k){ unset($GLOBALS['t_trans'][$k]); return true; } }
if (!function_exists('get_option'))         { function get_option($k,$d=false){ return $GLOBALS['opt'][$k] ?? $d; } }
if (!function_exists('get_current_user_id')){ function get_current_user_id(){ return $GLOBALS['cur_uid']; } }
if (!function_exists('sige_security_log'))  { function sige_security_log($a,$b){ /* noop */ } }

if (!class_exists('SIGE_FinanceActionService')) {
    class SIGE_FinanceActionService {
        public static $calls = [];
        public static function cancelLancamento($id, $motivo, $escola) { self::$calls[] = ['cancelLancamento', $id, $motivo, $escola]; return ['ok' => true, 'op' => 'cancelLancamento', 'id' => $id]; }
        public static function isentarLancamento($id, $motivo, $escola) { self::$calls[] = ['isentarLancamento', $id, $motivo, $escola]; return ['ok' => true, 'op' => 'isentarLancamento']; }
        public static function reactivarLancamento($id, $escola) { self::$calls[] = ['reactivarLancamento', $id, $escola]; return ['ok' => true, 'op' => 'reactivarLancamento']; }
        public static function bloquearMes($aluno, $mes, $motivo, $ano, $escola) { self::$calls[] = ['bloquearMes', $aluno, $mes, $motivo, $ano, $escola]; return ['ok' => true, 'op' => 'bloquearMes']; }
        public static function desbloquearMes($bid, $escola) { self::$calls[] = ['desbloquearMes', $bid, $escola]; return ['ok' => true, 'op' => 'desbloquearMes']; }
        public static function estornarPagamento($pid, $motivo, $escola, $valor = 0.0) { self::$calls[] = ['estornarPagamento', $pid, $motivo, $escola, $valor]; return ['ok' => true, 'op' => 'estornarPagamento', 'valor' => $valor]; }
    }
}

require $root . '/includes/security-mfa-replay.php';

// ---- 1. Estatico -------------------------------------------------------------
$check('modulo security-mfa-replay.php existe', is_file($root . '/includes/security-mfa-replay.php'));
$check('modulo carregado no bootstrap', strpos($read('sige-softgenial.php'), 'security-mfa-replay.php') !== false);
$svc = $read('includes/fin-action-service.php');
$check('captura presente nas 6 operacoes de servico', substr_count($svc, 'sige_mfa_replay_capture(') === 6);
foreach (['fin_cancelLancamento','fin_isentarLancamento','fin_reactivarLancamento','fin_bloquearMes','fin_desbloquearMes','fin_estornarPagamento'] as $ctx) {
    $check("captura no contexto {$ctx}", strpos($svc, "sige_mfa_replay_capture('{$ctx}', func_get_args())") !== false);
}
$stepup = $read('includes/security-mfa-stepup.php');
$check('handler de confirmacao dispara a reposicao', strpos($stepup, 'sige_mfa_replay_consume_and_run') !== false);
$check('aviso de reposicao concluida presente', strpos($stepup, 'concluida automaticamente') !== false);
$check('gating por opcao sige_mfa_autoreplay', strpos($read('includes/security-mfa-replay.php'), "get_option('sige_mfa_autoreplay'") !== false);
$check('kill-switch SIGE_MFA_AUTOREPLAY_OFF presente', strpos($read('includes/security-mfa-replay.php'), 'SIGE_MFA_AUTOREPLAY_OFF') !== false);
$check('registo cobre exactamente as 6 operacoes', count(sige_mfa_replay_registry()) === 6);
$mf = json_decode($read('docs/security/ACTION_SURFACE_MANIFEST-v12.12.12.json'), true);
$check('manifesto mantem-se em 195 (sem novo endpoint)', is_array($mf) && count($mf['items'] ?? []) === 195);

// ---- 2. Gating ---------------------------------------------------------------
$GLOBALS['opt']['sige_mfa_autoreplay'] = 'off';
$check('reposicao desligada por defeito (off)', !sige_mfa_replay_enabled());
$GLOBALS['opt']['sige_mfa_autoreplay'] = 'on';
$check('reposicao ligada com a opcao on', sige_mfa_replay_enabled());

// ---- 3. Captura --------------------------------------------------------------
$uid = $GLOBALS['cur_uid'];
SIGE_FinanceActionService::$calls = [];
$GLOBALS['t_trans'] = [];
sige_mfa_replay_capture('fin_estornarPagamento', [42, 'duplicado', 1, 150.0]);
$check('descritor capturado para operacao conhecida', sige_mfa_replay_pending($uid));
// contexto desconhecido (ex.: config) nao e capturado
sige_mfa_replay_capture('cfg_emola', [1, 2, 3]);
$d = get_transient('sige_mfa_replay_' . $uid);
$check('contexto fora das 6 nao sobrepoe o descritor', is_array($d) && $d['ctx'] === 'fin_estornarPagamento');

// ---- 4. Consumo de uso unico + sem execucao dupla ---------------------------
$res = sige_mfa_replay_consume_and_run($uid);
$check('consumo devolve resultado ok da operacao', is_array($res) && !empty($res['ok']) && ($res['op'] ?? '') === 'estornarPagamento');
$check('operacao executada exactamente uma vez', count(SIGE_FinanceActionService::$calls) === 1);
$check('argumentos passados intactos (incl. valor)', SIGE_FinanceActionService::$calls[0] === ['estornarPagamento', 42, 'duplicado', 1, 150.0]);
$check('descritor consumido (ja nao ha pendente)', !sige_mfa_replay_pending($uid));
$res2 = sige_mfa_replay_consume_and_run($uid);
$check('segundo consumo nao dispara (uso unico)', $res2 === null && count(SIGE_FinanceActionService::$calls) === 1);

// ---- 5. Despacho posicional correcto para cada operacao ----------------------
$casos = [
    ['fin_cancelLancamento',    [5, 'motivo', 1],          'cancelLancamento'],
    ['fin_isentarLancamento',   [6, 'bolsa', 1],           'isentarLancamento'],
    ['fin_reactivarLancamento', [7, 1],                    'reactivarLancamento'],
    ['fin_bloquearMes',         [8, 3, 'incumprimento', 2026, 1], 'bloquearMes'],
    ['fin_desbloquearMes',      [9, 1],                    'desbloquearMes'],
];
$todos = true;
foreach ($casos as $c) {
    SIGE_FinanceActionService::$calls = [];
    $GLOBALS['t_trans'] = [];
    sige_mfa_replay_capture($c[0], $c[1]);
    $r = sige_mfa_replay_consume_and_run($uid);
    $callOk = count(SIGE_FinanceActionService::$calls) === 1
        && SIGE_FinanceActionService::$calls[0] === array_merge([$c[2]], $c[1]);
    if (!is_array($r) || empty($r['ok']) || !$callOk) $todos = false;
}
$check('despacho posicional correcto nas restantes operacoes', $todos);

// ---- 6. Off bloqueia o consumo ----------------------------------------------
$GLOBALS['t_trans'] = [];
SIGE_FinanceActionService::$calls = [];
sige_mfa_replay_capture('fin_cancelLancamento', [1, 'x', 1]); // capturado com on
$GLOBALS['opt']['sige_mfa_autoreplay'] = 'off';
$r = sige_mfa_replay_consume_and_run($uid);
$check('com reposicao off o consumo nao executa nada', $r === null && count(SIGE_FinanceActionService::$calls) === 0);
$GLOBALS['opt']['sige_mfa_autoreplay'] = 'on';

// ---- Resultado ---------------------------------------------------------------
echo "\n";
if ($fail) {
    fwrite(STDERR, 'SMOKE REPOSICAO FALHOU: ' . count($fail) . " falha(s)\n - " . implode("\n - ", $fail) . "\n");
    exit(1);
}
echo "SMOKE REPOSICAO OK - {$ok} verificacoes passaram.\n";
exit(0);
