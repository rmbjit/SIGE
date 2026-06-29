<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Smoke de runtime: cofre de segredos - mascaramento e redaccao
 * (Fase 2, incremento 2). Prova o mascaramento, a redaccao de segredos em texto
 * (logs e URLs), a cobertura do registo e a ausencia de regressao no selar/revelar.
 * Sem WordPress vivo.
 */
define('SIGE_VAULT_TEST_MODE', true);
// Cifra simulada fiel (mesma do gate de cofre): prefixo selado + base64.
if (!function_exists('sige_encrypt_token')) { function sige_encrypt_token($p){ $p=(string)$p; return $p===''?'':'sige2:'.base64_encode($p); } }
if (!function_exists('sige_decrypt_token')) { function sige_decrypt_token($e){ $e=(string)$e; if($e==='')return ''; if(strncmp($e,'sige2:',6)===0){$r=base64_decode(substr($e,6),true); return $r===false?'':$r;} return ''; } }

require_once dirname(__DIR__) . '/includes/security-vault.php';

$fails = []; $ok = 0;
$check = static function (bool $cond, string $label) use (&$fails, &$ok): void {
    if ($cond) { $ok++; echo "OK   {$label}\n"; } else { $fails[] = $label; echo "FAIL {$label}\n"; }
};

// 1. Mascaramento.
$m = sige_secret_mask('verylongsecretvalue', 4);
$check(substr($m, -4) === 'alue' && strpos($m, '*') !== false && strpos($m, 'verylong') === false, 'mascara revela so os ultimos 4 e esconde o resto');
$check(sige_secret_mask('abcd', 4) === '****', 'valor curto fica totalmente mascarado');
$check(sige_secret_mask('', 4) === '', 'valor vazio devolve vazio');

// 2. Redaccao em texto (logs e URLs).
$check(sige_secret_scrub('password=hunter2 e token: ab.cd&x=1') === 'password=[SEGREDO] e token: [SEGREDO]&x=1', 'redige pares chave=valor de segredos');
$check(sige_secret_scrub('valor sige2:QUJDREVGR0g= fim') === 'valor [SEGREDO] fim', 'redige tokens selados do cofre');
$check(sige_secret_scrub('aluno_id=7 field=doc_bi ip=1.2.3.4') === 'aluno_id=7 field=doc_bi ip=1.2.3.4', 'texto normal passa intacto (sem sobre-redaccao)');
$check(sige_secret_scrub('https://x/y?webhook_token=ABC123&ambiente=prod') === 'https://x/y?webhook_token=[SEGREDO]&ambiente=prod', 'redige segredo em URL e mantem o resto');
$check(sige_secret_scrub('') === '', 'redaccao de vazio devolve vazio');

// 3. Cobertura do registo de segredos.
$check(sige_vault_is_secret_key('license', 'license_key') === true, 'registo cobre a chave de licenca');
$check(sige_vault_is_secret_key('smtp', 'password') === true, 'registo cobre a password SMTP');
$check(sige_vault_is_secret_key('mpesa', 'ambiente') === false, 'valor nao-segredo nao e classificado como segredo');

// 4. Sem regressao: selar/revelar continua a funcionar.
$sealed = sige_vault_seal('CRED-XYZ-9');
$check(sige_vault_is_sealed($sealed) && $sealed !== 'CRED-XYZ-9', 'selar continua a produzir formato selado');
$check(sige_vault_reveal($sealed) === 'CRED-XYZ-9', 'revelar continua a reconstruir o original');
$check(sige_vault_reveal('EM-CLARO') === 'EM-CLARO', 'revelar continua a passar texto em claro');

echo str_repeat('-', 56) . "\n";
if ($fails) {
    fwrite(STDERR, 'SMOKE COFRE COBERTURA FALHOU: ' . count($fails) . " falha(s)\n");
    foreach ($fails as $f) fwrite(STDERR, " - {$f}\n");
    exit(1);
}
echo "SMOKE COFRE COBERTURA OK - {$ok} verificacoes passaram.\n";
