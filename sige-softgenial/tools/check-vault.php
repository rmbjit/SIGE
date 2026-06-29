<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

/**
 * Gate: Secret Vault - v12.12.14 (Fase 5, incremento 1).
 * Garante que o cofre existe e usa a cifra forte existente, que o helper de
 * pagamentos sela as chaves-segredo na gravacao e as revela na leitura (com
 * passagem de texto em claro), que o caminho publico do webhook revela, que os
 * ecras nao pre-preenchem o segredo, e que nada disto introduz endpoint novo.
 */
$root = dirname(__DIR__);
$fails = [];
$read = static function (string $rel) use ($root): string {
    $abs = $root . '/' . $rel;
    return is_file($abs) ? (string) file_get_contents($abs) : '';
};

// Stubs + cifra simulada fiel para o teste runtime.
define('SIGE_VAULT_TEST_MODE', true);
if (!function_exists('sige_encrypt_token')) { function sige_encrypt_token($p){ $p=(string)$p; return $p===''?'':'sige2:'.base64_encode($p); } }
if (!function_exists('sige_decrypt_token')) { function sige_decrypt_token($e){ $e=(string)$e; if($e==='')return ''; if(strncmp($e,'sige2:',6)===0){$r=base64_decode(substr($e,6),true); return $r===false?'':$r;} return ''; } }

$modulo = $root . '/includes/security-vault.php';
if (!file_exists($modulo)) { fwrite(STDERR, "modulo includes/security-vault.php ausente\n"); exit(1); }
require_once $modulo;
$mod = $read('includes/security-vault.php');
$pay = $read('includes/payments/mobile-tenant-options.php');

// 1. Primitivas do cofre --------------------------------------------------------
foreach (['sige_vault_seal','sige_vault_reveal','sige_vault_is_sealed','sige_vault_secret_registry','sige_vault_is_secret_key'] as $fn) {
    if (!function_exists($fn)) $fails[] = "primitiva ausente: {$fn}()";
}

// 2. Usa a cifra forte existente -----------------------------------------------
if (strpos($mod, 'sige_encrypt_token') === false) $fails[] = 'o cofre nao usa sige_encrypt_token';
if (strpos($mod, 'sige_decrypt_token') === false) $fails[] = 'o cofre nao usa sige_decrypt_token';

// 3. Runtime: selar/revelar e passagem de texto em claro -----------------------
if (function_exists('sige_vault_seal')) {
    $s = sige_vault_seal('CRED-XYZ-1');
    if (!sige_vault_is_sealed($s)) $fails[] = 'selar nao produz formato selado';
    if ($s === 'CRED-XYZ-1') $fails[] = 'selar devolve o texto em claro';
    if (sige_vault_reveal($s) !== 'CRED-XYZ-1') $fails[] = 'revelar nao reconstroi o original';
    if (sige_vault_reveal('EM-CLARO') !== 'EM-CLARO') $fails[] = 'revelar nao passa texto em claro';
    if (sige_vault_seal($s) !== $s) $fails[] = 'selar nao e idempotente';
    if (sige_vault_seal('') !== '' || sige_vault_reveal('') !== '') $fails[] = 'vazio nao tratado';
    if (!sige_vault_is_secret_key('mpesa','api_key') || !sige_vault_is_secret_key('emola','api_secret') || !sige_vault_is_secret_key('mpesa','webhook_token')) $fails[] = 'registo de segredos incompleto';
    if (sige_vault_is_secret_key('mpesa','ambiente')) $fails[] = 'valor nao-segredo classificado como segredo';
}

// 4. Integracao com pagamentos: sela na gravacao, revela na leitura ------------
if (strpos($pay, 'sige_vault_is_secret_key') === false || strpos($pay, 'sige_vault_seal') === false) $fails[] = 'helper de pagamentos nao sela as chaves-segredo na gravacao';
if (strpos($pay, 'sige_vault_reveal') === false) $fails[] = 'helper de pagamentos nao revela as chaves-segredo na leitura';
// auto-reparacao guardada por is_admin (nunca no webhook publico)
if (strpos($pay, 'is_admin()') === false) $fails[] = 'auto-reparacao nao guardada por is_admin';
// caminho do webhook revela antes do hash_equals
if (!preg_match('/sige_vault_reveal\([^)]*\)[^;]*;\s*\n\s*\$expected/s', $pay) && substr_count($pay, 'sige_vault_reveal') < 3) {
    $fails[] = 'caminho do webhook_token nao revela antes da comparacao';
}

// 5. Redaccao: os ecras nao pre-preenchem o segredo ----------------------------
$view = $read('admin/finance/mpesa-view.php');
if ($view !== '') {
    // os campos de segredo devem ser inputs de password sem value pre-preenchido
    if (preg_match('/name="api_key"[^>]*value=/s', $view)) $fails[] = 'campo api_key pre-preenche o segredo';
    if (preg_match('/name="api_secret"[^>]*value=/s', $view)) $fails[] = 'campo api_secret pre-preenche o segredo';
    if (strpos($view, 'name="api_key"') !== false && strpos($view, 'type="password"') === false) $fails[] = 'campo de segredo nao e do tipo password';
}

// 6. Carregamento e governanca -------------------------------------------------
if (strpos($read('sige-softgenial.php'), 'security-vault.php') === false) $fails[] = 'cofre nao carregado no bootstrap';
$mf = json_decode($read('docs/security/ACTION_SURFACE_MANIFEST-v12.12.14.json'), true);
if (!is_array($mf) || count($mf['items'] ?? []) !== 196) $fails[] = 'manifesto v12.12.14 nao tem 196 itens (nao deve haver endpoint novo)';

if ($fails) {
    fwrite(STDERR, "VAULT GATE FALHOU\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo "VAULT OK - segredos cifrados em repouso (gateways), cofre sobre a cifra forte, webhook revela, redaccao mantida, manifesto 196 inalterado.\n";
exit(0);
