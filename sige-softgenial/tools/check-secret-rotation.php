<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Gate estatico: cofre de segredos - rotacao e segredos por escola
 * (Fase 2, incremento 3).
 * Tranca: camada de segredos isolados por escola (cifrados), rastreio de antiguidade
 * e rotacao, geracao de segredos fortes, re-selagem de higiene, e rotacao concreta do
 * webhook_token de pagamento por escola. Tudo aditivo, sem nova superficie.
 */
$root = dirname(__DIR__);
$fails = [];
$read = static function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return is_file($p) ? (string)file_get_contents($p) : '';
};

// 1. Funcoes da camada no cofre.
$vault = $read('includes/security-vault.php');
if ($vault === '') {
    $fails[] = 'includes/security-vault.php ausente';
} else {
    $need = [
        'sige_secret_school_option', 'sige_secret_rotation_meta_option', 'sige_secret_rotation_days',
        'sige_secret_mark_rotated', 'sige_secret_rotated_at', 'sige_secret_rotation_due',
        'sige_secret_set_for_school', 'sige_secret_get_for_school', 'sige_secret_delete_for_school',
        'sige_secret_generate_token', 'sige_secret_rotate_for_school', 'sige_vault_reseal',
    ];
    foreach ($need as $fn) {
        if (strpos($vault, "function {$fn}(") === false) $fails[] = "cofre sem funcao {$fn}";
    }
    if (strpos($vault, "'_esc'") === false && strpos($vault, "_esc'") === false) {
        $fails[] = 'opcao por escola sem isolamento por escola (_esc)';
    }
    if (strpos($vault, 'SIGE_SECRET_ROTATION_DAYS') === false) {
        $fails[] = 'idade de rotacao nao ajustavel por constante';
    }
    if (strpos($vault, 'random_bytes(') === false) {
        $fails[] = 'geracao de segredos nao usa aleatoriedade forte (random_bytes)';
    }
    // set sela; get revela.
    if (!preg_match('/function sige_secret_set_for_school\(.*?sige_vault_seal\(/s', $vault)) {
        $fails[] = 'set_for_school nao sela o segredo';
    }
    if (!preg_match('/function sige_secret_get_for_school\(.*?sige_vault_reveal\(/s', $vault)) {
        $fails[] = 'get_for_school nao revela o segredo';
    }
    // rotacao audita e devolve o novo segredo.
    if (!preg_match("/function sige_secret_rotate_for_school\(.*?'segredo_rodado'/s", $vault)) {
        $fails[] = 'rotate_for_school nao audita a rotacao';
    }
    // reseal nao perde valor nao decifravel.
    if (!preg_match('/function sige_vault_reseal\(.*?if \(\$plain === \'\'\) return \$stored;/s', $vault)) {
        $fails[] = 're-selagem pode perder um valor nao decifravel';
    }
}

// 2. Rotacao concreta do webhook_token de pagamento por escola.
$pay = $read('includes/payments/mobile-tenant-options.php');
if (strpos($pay, 'function sige_mobile_payment_rotate_webhook_token(') === false) {
    $fails[] = 'falta a rotacao do webhook_token de pagamento por escola';
} else {
    if (strpos($pay, 'sige_secret_generate_token(') === false && strpos($pay, 'random_bytes(') === false) {
        $fails[] = 'rotacao do webhook_token nao gera token forte';
    }
    if (strpos($pay, 'sige_mobile_payment_update_option(') === false) {
        $fails[] = 'rotacao do webhook_token nao guarda por escola (update_option)';
    }
    if (strpos($pay, "'segredo_rodado'") === false) {
        $fails[] = 'rotacao do webhook_token nao audita a rotacao';
    }
}

if ($fails) {
    fwrite(STDERR, "COFRE - ROTACAO E SEGREDOS POR ESCOLA FALHOU\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo 'COFRE - ROTACAO E SEGREDOS POR ESCOLA OK - segredos por escola cifrados, rastreio e rotacao, geracao forte, re-selagem e rotacao do webhook por escola.' . "\n";
