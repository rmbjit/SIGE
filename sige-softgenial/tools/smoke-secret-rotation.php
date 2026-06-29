<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Smoke de runtime: cofre de segredos - rotacao e segredos por
 * escola (Fase 2, incremento 3). Prova o isolamento por escola (cifrado), a passagem
 * de texto em claro, o rastreio e a decisao de rotacao, a geracao de segredos fortes,
 * a rotacao (gera, guarda, revela igual, marca) e a re-selagem segura. Opcoes em
 * memoria; cifra simulada fiel. Sem WordPress vivo.
 */
define('SIGE_VAULT_TEST_MODE', true);

$GLOBALS['__opts'] = [];
if (!function_exists('get_option')) { function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['__opts']) ? $GLOBALS['__opts'][$k] : $d; } }
if (!function_exists('update_option')) { function update_option($k, $v, $a = false) { $GLOBALS['__opts'][$k] = $v; return true; } }
if (!function_exists('delete_option')) { function delete_option($k) { if (array_key_exists($k, $GLOBALS['__opts'])) { unset($GLOBALS['__opts'][$k]); return true; } return false; } }
if (!function_exists('sige_encrypt_token')) { function sige_encrypt_token($p){ $p=(string)$p; return $p===''?'':'sige2:'.base64_encode($p); } }
if (!function_exists('sige_decrypt_token')) { function sige_decrypt_token($e){ $e=(string)$e; if($e==='')return ''; if(strncmp($e,'sige2:',6)===0){$r=base64_decode(substr($e,6),true); return $r===false?'':$r;} return ''; } }
if (!function_exists('sige_security_log')) { function sige_security_log($a, $b = '', $c = 0) {} }

require_once dirname(__DIR__) . '/includes/security-vault.php';

$fails = []; $ok = 0;
$check = static function (bool $cond, string $label) use (&$fails, &$ok): void {
    if ($cond) { $ok++; echo "OK   {$label}\n"; } else { $fails[] = $label; echo "FAIL {$label}\n"; }
};

// 1. Segredos por escola: isolamento e cifra.
$check(sige_secret_set_for_school('zoho_app_pw', 'SENHA-ESCOLA-5', 5), 'guardar segredo da escola 5');
$check(sige_secret_set_for_school('zoho_app_pw', 'SENHA-ESCOLA-7', 7), 'guardar segredo da escola 7');
$check(sige_secret_get_for_school('zoho_app_pw', 5) === 'SENHA-ESCOLA-5', 'escola 5 le o seu segredo');
$check(sige_secret_get_for_school('zoho_app_pw', 7) === 'SENHA-ESCOLA-7', 'escola 7 le o seu segredo (isolamento)');
$check(strncmp((string)get_option('sige_secret_zoho_app_pw_esc5'), 'sige2:', 6) === 0, 'segredo guardado cifrado, nao em claro');
$check(sige_secret_get_for_school('zoho_app_pw', 99, 'NADA') === 'NADA', 'escola sem segredo devolve o valor por omissao');

// 2. Passagem de texto em claro (instalacao pre-cofre).
$GLOBALS['__opts']['sige_secret_legado_esc3'] = 'EM-CLARO-LEGADO';
$check(sige_secret_get_for_school('legado', 3) === 'EM-CLARO-LEGADO', 'segredo legado em claro passa intacto');

// 3. Rastreio e decisao de rotacao.
$check(sige_secret_rotation_due('inexistente', 9) === true, 'segredo nunca rodado precisa de rotacao');
$check(sige_secret_rotation_due('zoho_app_pw', 5) === false, 'segredo acabado de gravar nao precisa de rotacao');
$GLOBALS['__opts']['sige_secret_zoho_app_pw_esc5_rotacao'] = time() - 200 * 86400;
$check(sige_secret_rotation_due('zoho_app_pw', 5) === true, 'segredo antigo (200 dias) precisa de rotacao');
$check(sige_secret_rotation_due('zoho_app_pw', 5, 365) === false, 'idade maxima ajustavel respeitada (365 dias)');
$check(sige_secret_rotated_at('zoho_app_pw', 7) > 0, 'instante da ultima rotacao registado ao gravar');

// 4. Geracao de segredos fortes.
$t1 = sige_secret_generate_token(); $t2 = sige_secret_generate_token();
$check(strlen($t1) >= 32 && preg_match('/^[A-Za-z0-9_-]+$/', $t1) === 1, 'token gerado e forte e url-safe');
$check($t1 !== $t2, 'dois tokens gerados sao distintos');

// 5. Rotacao: gera, guarda, revela igual, marca.
$new = sige_secret_rotate_for_school('api_tok', 12);
$check($new !== '' && sige_secret_get_for_school('api_tok', 12) === $new, 'rotacao gera e guarda um novo segredo legivel');
$check(sige_secret_rotated_at('api_tok', 12) > 0, 'rotacao marca o instante');

// 6. Apagar remove segredo e marca.
$check(sige_secret_delete_for_school('api_tok', 12), 'apagar o segredo por escola');
$check(!array_key_exists('sige_secret_api_tok_esc12', $GLOBALS['__opts']) && !array_key_exists('sige_secret_api_tok_esc12_rotacao', $GLOBALS['__opts']), 'apagar remove segredo e marca de rotacao');

// 7. Re-selagem (higiene), sem perder valor nao decifravel.
$check(strncmp(sige_vault_reseal('EM-CLARO'), 'sige2:', 6) === 0, 're-selar texto em claro produz formato selado');
$sealed = sige_vault_seal('VALOR-9');
$check(sige_vault_reveal(sige_vault_reseal($sealed)) === 'VALOR-9', 're-selar um selado mantem o valor');
$check(sige_vault_reseal('sige2:@@@nao-decifravel@@@') === 'sige2:@@@nao-decifravel@@@', 're-selar um valor nao decifravel deixa-o intacto');

echo str_repeat('-', 56) . "\n";
if ($fails) {
    fwrite(STDERR, 'SMOKE ROTACAO E SEGREDOS POR ESCOLA FALHOU: ' . count($fails) . " falha(s)\n");
    foreach ($fails as $f) fwrite(STDERR, " - {$f}\n");
    exit(1);
}
echo "SMOKE ROTACAO E SEGREDOS POR ESCOLA OK - {$ok} verificacoes passaram.\n";
