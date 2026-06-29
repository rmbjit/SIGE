<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Gate estatico: ciclo de vida dos documentos (incremento 3).
 * Tranca: links com expiracao assinados, registo de download reforcado, limpeza
 * de orfaos segura e QR gerado localmente (sem servico externo).
 */
$root = dirname(__DIR__);
$fails = [];
$read = static function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return is_file($p) ? (string)file_get_contents($p) : '';
};

// 1. Links com expiracao + registo reforcado.
$ep = $read('includes/secure-document-download.php');
if ($ep === '') {
    $fails[] = 'includes/secure-document-download.php ausente';
} else {
    foreach (['sige_secure_document_link_ttl', 'sige_secure_document_link_secret', 'sige_secure_document_link_sig', 'sige_secure_document_link_valid', 'sige_secure_document_client_ip'] as $fn) {
        if (strpos($ep, "function {$fn}(") === false) $fails[] = "endpoint sem funcao {$fn}";
    }
    if (substr_count($ep, "'exp'") < 2 || substr_count($ep, "'sig'") < 2) {
        $fails[] = "construtores de URL sem exp/sig nos dois fluxos (aluno e equipa)";
    }
    if (substr_count($ep, 'sige_secure_document_link_valid(') < 2) {
        $fails[] = "handlers nao verificam a expiracao nos dois fluxos";
    }
    if (substr_count($ep, "'ip' => sige_secure_document_client_ip()") < 2) {
        $fails[] = "registo de download nao reforcado com IP nos dois fluxos";
    }
    if (strpos($ep, 'hash_equals(') === false) {
        $fails[] = "verificacao da assinatura sem comparacao em tempo constante";
    }
}

// 2. Limpeza de orfaos segura.
$pv = $read('includes/security-uploads-private.php');
if ($pv === '') {
    $fails[] = 'includes/security-uploads-private.php ausente';
} else {
    foreach (['sige_uploads_private_ref_key', 'sige_uploads_private_referenced_keys', 'sige_uploads_private_orphan_grace', 'sige_uploads_private_orphan_sweep'] as $fn) {
        if (strpos($pv, "function {$fn}(") === false) $fails[] = "modulo privado sem funcao {$fn}";
    }
    if (strpos($pv, 'sige_uploads_private_orphan_sweep(') === false || strpos($pv, 'maybe_run_private_migration') === false) {
        $fails[] = "varrimento de orfaos nao e chamado pela rotina do admin_init";
    }
    if (strpos($pv, 'if ($referenced === null) return 0;') === false) {
        $fails[] = "varrimento nao aborta quando as referencias nao podem ser lidas";
    }
    if (strpos($pv, "'.htaccess' => true") === false || strpos($pv, "'web.config' => true") === false) {
        $fails[] = "varrimento nao protege os ficheiros de proteccao";
    }
}

// 3. QR local: sem servico externo, com a biblioteca local.
$scan_dirs = ['admin', 'includes', 'assets'];
$ext_qr = [];
foreach ($scan_dirs as $d) {
    $dir = $root . '/' . $d;
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $fi) {
        if (!$fi->isFile()) continue;
        $ext = strtolower($fi->getExtension());
        if (!in_array($ext, ['php', 'js', 'css'], true)) continue;
        if (strpos((string)@file_get_contents($fi->getPathname()), 'api.qrserver.com') !== false) {
            $ext_qr[] = str_replace($root . '/', '', $fi->getPathname());
        }
    }
}
if ($ext_qr) {
    $fails[] = 'QR ainda gerado por servico externo (api.qrserver.com) em: ' . implode(', ', $ext_qr);
}
$al = $read('admin/academic/alunos_lista.php');
if ($al !== '') {
    if (strpos($al, 'sige_cdn_script("qrious")') === false && strpos($al, "sige_cdn_script('qrious')") === false) {
        $fails[] = 'biblioteca de QR local (qrious) nao carregada no alunos_lista';
    }
    if (strpos($al, 'function sigeQrDataUri(') === false) {
        $fails[] = 'auxiliar de QR local (sigeQrDataUri) ausente';
    }
}

if ($fails) {
    fwrite(STDERR, "CICLO DE VIDA DOS DOCUMENTOS FALHOU\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo 'CICLO DE VIDA DOS DOCUMENTOS OK - links com expiracao, registo reforcado, limpeza de orfaos segura e QR local.' . "\n";
