<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Gate estatico: cofre de segredos - cobertura, mascaramento e
 * nao-vazamento (Fase 2, incremento 2).
 * Tranca: utilitarios de mascaramento e de redaccao; o canal de seguranca redige
 * segredos antes de registar; a alteracao de segredos e auditada (sem o valor); o
 * registo de segredos cobre a licenca; nenhum segredo e colocado num URL.
 */
$root = dirname(__DIR__);
$fails = [];
$read = static function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return is_file($p) ? (string)file_get_contents($p) : '';
};

// 1. Utilitarios no cofre.
$vault = $read('includes/security-vault.php');
if ($vault === '') {
    $fails[] = 'includes/security-vault.php ausente';
} else {
    foreach (['sige_secret_mask', 'sige_secret_names', 'sige_secret_scrub'] as $fn) {
        if (strpos($vault, "function {$fn}(") === false) $fails[] = "cofre sem funcao {$fn}";
    }
    if (strpos($vault, "'license'") === false) $fails[] = 'registo de segredos nao cobre a licenca';
    if (strpos($vault, 'sige2') === false || strpos($vault, 'gcm1') === false) {
        $fails[] = 'redaccao nao cobre os formatos selados do cofre';
    }
}

// 2. O canal de seguranca redige segredos antes de registar.
$hard = $read('includes/security-hardening.php');
if ($hard === '') {
    $fails[] = 'includes/security-hardening.php ausente';
} elseif (!preg_match('/function sige_security_log\([^)]*\)\s*:\s*void\s*\{.*?sige_secret_scrub\(/s', $hard)) {
    $fails[] = 'sige_security_log nao redige segredos (sige_secret_scrub)';
}

// 3. Auditoria de alteracao de segredo (sem o valor) nos pontos de gravacao.
$repo = $read('includes/settings/class-sige-settings-repository.php');
$pay = $read('includes/payments/mobile-tenant-options.php');
if (strpos($repo, "sige_security_log('segredo_alterado'") === false) {
    $fails[] = 'repositorio de definicoes nao audita a alteracao de segredos';
}
if (strpos($pay, "sige_security_log('segredo_alterado'") === false) {
    $fails[] = 'gravacao de segredos de pagamento nao audita a alteracao';
}

// 4. Proibicao de segredos em URL: nenhuma chave-credencial passa por add_query_arg.
$risky = '(?:webhook_token|api_secret|client_secret|api_key|password|senha|passwd)';
$dir = $root . '/includes';
$offending = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $fi) {
    if (!$fi->isFile() || strtolower($fi->getExtension()) !== 'php') continue;
    $s = (string)@file_get_contents($fi->getPathname());
    $off = 0;
    while (($pos = strpos($s, 'add_query_arg', $off)) !== false) {
        $window = substr($s, $pos, 400);
        if (preg_match('/' . $risky . '\s*=>/i', $window)) {
            $offending[] = str_replace($root . '/', '', $fi->getPathname());
            break;
        }
        $off = $pos + 13;
    }
}
if ($offending) {
    $fails[] = 'segredo colocado em URL (add_query_arg) em: ' . implode(', ', array_unique($offending));
}

if ($fails) {
    fwrite(STDERR, "COFRE - COBERTURA E NAO-VAZAMENTO FALHOU\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo 'COFRE - COBERTURA E NAO-VAZAMENTO OK - mascaramento, redaccao em registos, auditoria de alteracao e sem segredos em URL.' . "\n";
