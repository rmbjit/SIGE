<?php
// Acesso restrito: smoke corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SMOKE - Secret Vault v12.12.14 (Fase 5, incremento 1)
 * Prova: selar/revelar e idempotencia; passagem de texto em claro; registo de
 * segredos; e a integracao com o helper de pagamentos (gravacao cifra, leitura
 * revela, instalacao pre-cofre passa intacta, auto-reparacao so no admin, e o
 * caminho publico do webhook revela). Cifra simulada de forma fiel (prefixo
 * sige2:) para correr sem WordPress; a cifra real e validada noutro sitio.
 */
$root = dirname(__DIR__);
$fail = [];
$ok = 0;
$check = static function (string $label, bool $cond) use (&$fail, &$ok) {
    if ($cond) { $ok++; echo "OK   {$label}\n"; }
    else { $fail[] = $label; echo "FAIL {$label}\n"; }
};

define('SIGE_VAULT_TEST_MODE', true);
define('SIGE_MPESA_TEST_MODE', true);
define('ABSPATH', '/tmp/');
$GLOBALS['opt'] = [];
$GLOBALS['is_admin'] = false;
$GLOBALS['escola'] = 0;

// Cifra simulada fiel: prefixo sige2:, e em texto nao-prefixado devolve vazio (como a real).
if (!function_exists('sige_encrypt_token')) { function sige_encrypt_token($p){ $p=(string)$p; return $p===''?'':'sige2:'.base64_encode($p); } }
if (!function_exists('sige_decrypt_token')) { function sige_decrypt_token($e){ $e=(string)$e; if($e==='')return ''; if(strncmp($e,'sige2:',6)===0){$r=base64_decode(substr($e,6),true); return $r===false?'':$r;} return ''; } }
if (!function_exists('get_option'))        { function get_option($k,$d=false){ return array_key_exists($k,$GLOBALS['opt'])?$GLOBALS['opt'][$k]:$d; } }
if (!function_exists('update_option'))     { function update_option($k,$v,$a=false){ $GLOBALS['opt'][$k]=$v; return true; } }
if (!function_exists('is_admin'))          { function is_admin(){ return (bool)$GLOBALS['is_admin']; } }
if (!function_exists('is_user_logged_in')) { function is_user_logged_in(){ return true; } }
if (!function_exists('sige_get_escola_id')){ function sige_get_escola_id(){ return (int)$GLOBALS['escola']; } }

require $root . '/includes/security-vault.php';
require $root . '/includes/payments/mobile-tenant-options.php';

// ---- 1. Selar / revelar -----------------------------------------------------
$sealed = sige_vault_seal('SEGREDO-abc-123');
$check('selar produz formato selado (sige2:/gcm1:)', sige_vault_is_sealed($sealed));
$check('selar nao devolve o texto em claro', $sealed !== 'SEGREDO-abc-123');
$check('revelar devolve o original', sige_vault_reveal($sealed) === 'SEGREDO-abc-123');
$check('selar e idempotente', sige_vault_seal($sealed) === $sealed);
$check('selar vazio devolve vazio', sige_vault_seal('') === '');
$check('revelar vazio devolve vazio', sige_vault_reveal('') === '');
$check('revelar texto em claro passa intacto', sige_vault_reveal('TEXTO-EM-CLARO') === 'TEXTO-EM-CLARO');
$check('is_sealed falso para texto em claro', sige_vault_is_sealed('TEXTO-EM-CLARO') === false);

// ---- 2. Registo de segredos -------------------------------------------------
$check('mpesa api_key e segredo', sige_vault_is_secret_key('mpesa', 'api_key') === true);
$check('mpesa public_key e segredo', sige_vault_is_secret_key('mpesa', 'public_key') === true);
$check('emola api_key e segredo', sige_vault_is_secret_key('emola', 'api_key') === true);
$check('emola api_secret e segredo', sige_vault_is_secret_key('emola', 'api_secret') === true);
$check('webhook_token e segredo (qualquer fornecedor)', sige_vault_is_secret_key('mpesa', 'webhook_token') && sige_vault_is_secret_key('emola', 'webhook_token'));
$check('mpesa ambiente NAO e segredo', sige_vault_is_secret_key('mpesa', 'ambiente') === false);
$check('emola merchant_code NAO e segredo', sige_vault_is_secret_key('emola', 'merchant_code') === false);
$reg = sige_vault_secret_registry();
$check('registo cataloga SMTP e WhatsApp (ja cifrados)', isset($reg['smtp']) && isset($reg['whatsapp']));

// ---- 3. Integracao com pagamentos: cifra em repouso, le em claro -------------
sige_mobile_payment_update_option('mpesa', 'api_key', 'KEY-MPESA-9999', false, 5);
$raw = $GLOBALS['opt']['sige_mpesa_escola_5_api_key'] ?? '';
$check('credencial guardada CIFRADA em repouso', $raw !== '' && sige_vault_is_sealed($raw) && $raw !== 'KEY-MPESA-9999');
$check('cliente recebe a credencial em CLARO', sige_mobile_payment_get_option('mpesa', 'api_key', '', 5) === 'KEY-MPESA-9999');

sige_mobile_payment_update_option('mpesa', 'ambiente', 'sandbox', false, 5);
$rawamb = $GLOBALS['opt']['sige_mpesa_escola_5_ambiente'] ?? '';
$check('valor NAO-segredo fica em claro', $rawamb === 'sandbox');
$check('valor NAO-segredo le-se igual', sige_mobile_payment_get_option('mpesa', 'ambiente', '', 5) === 'sandbox');

// ---- 4. Instalacao pre-cofre: texto em claro passa, sem reparacao fora do admin
$GLOBALS['is_admin'] = false;
$GLOBALS['opt']['sige_emola_escola_5_api_key'] = 'CLARO-EMOLA-OLD'; // como numa instalacao antiga
$check('pre-cofre: leitura devolve o valor em claro', sige_mobile_payment_get_option('emola', 'api_key', '', 5) === 'CLARO-EMOLA-OLD');
$check('pre-cofre fora do admin: NAO regrava (continua em claro)', ($GLOBALS['opt']['sige_emola_escola_5_api_key'] ?? '') === 'CLARO-EMOLA-OLD');

// ---- 5. Auto-reparacao no admin --------------------------------------------
$GLOBALS['is_admin'] = true;
$GLOBALS['opt']['sige_emola_escola_5_api_secret'] = 'CLARO-EMOLA-SECRET'; // antigo em claro
$lido = sige_mobile_payment_get_option('emola', 'api_secret', '', 5);
$rawrep = $GLOBALS['opt']['sige_emola_escola_5_api_secret'] ?? '';
$check('auto-reparacao: leitura devolve o valor correcto', $lido === 'CLARO-EMOLA-SECRET');
$check('auto-reparacao no admin: passa a estar CIFRADO', sige_vault_is_sealed($rawrep) && sige_vault_reveal($rawrep) === 'CLARO-EMOLA-SECRET');
$GLOBALS['is_admin'] = false;

// ---- 6. webhook_token no caminho publico (revelar) --------------------------
$GLOBALS['opt']['sige_mpesa_escola_5_webhook_token'] = sige_vault_seal('TOKEN-WH-7777');
$found = sige_mobile_payment_find_school_by_token('mpesa', 'TOKEN-WH-7777', 5);
$check('webhook: token cifrado e revelado e a escola e encontrada', $found === 5);
$found_bad = sige_mobile_payment_find_school_by_token('mpesa', 'TOKEN-ERRADO', 5);
$check('webhook: token errado nao encontra escola', $found_bad === 0);

// Ramo global do webhook (sem hint de escola): tambem revela.
$GLOBALS['opt']['sige_mpesa_webhook_token'] = sige_vault_seal('TOKEN-GLOBAL-1');
$found_g = sige_mobile_payment_find_school_by_token('mpesa', 'TOKEN-GLOBAL-1', 0);
$check('webhook (ramo global): token cifrado revelado encontra escola', $found_g >= 1);

echo "\n";
if ($fail) {
    fwrite(STDERR, 'SMOKE VAULT FALHOU: ' . count($fail) . " falha(s)\n - " . implode("\n - ", $fail) . "\n");
    exit(1);
}
echo "SMOKE VAULT OK - {$ok} verificacoes passaram (cifra em repouso + integracao pagamentos).\n";
exit(0);
