<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Smoke de runtime: ciclo de vida dos documentos (incremento 3).
 * Prova: a validade dos links assinados com expiracao (fresco aceite; expirado,
 * adulterado e vazio recusados) e o varrimento de orfaos seguro (remove o orfao
 * antigo, mantem o referenciado, o recente e os ficheiros de proteccao, e aborta
 * quando as referencias nao podem ser lidas). Sem WordPress vivo.
 */
if (!defined('ABSPATH')) define('ABSPATH', dirname(__DIR__) . '/');

// Stubs comuns.
if (!function_exists('add_action')) { function add_action($h, $c, $p = 10, $a = 1) {} }
if (!function_exists('add_filter')) { function add_filter($h, $c, $p = 10, $a = 1) {} }
if (!function_exists('wp_salt')) { function wp_salt($s = '') { return 'smoke-fixed-salt-9876543210'; } }
if (!function_exists('sige_security_log')) { function sige_security_log($a, $b = '', $c = 0) {} }

$BASE = sys_get_temp_dir() . '/sige_life_' . uniqid();
$UPLOADS = $BASE . '/uploads';
@mkdir($UPLOADS, 0777, true);
$GLOBALS['__UPLOADS'] = $UPLOADS;
if (!function_exists('wp_get_upload_dir')) { function wp_get_upload_dir() { return ['basedir' => $GLOBALS['__UPLOADS'], 'baseurl' => 'https://example.test/wp-content/uploads']; } }

// Stub configuravel das referencias (para testar tambem o caminho de aborto).
$GLOBALS['__ref_set'] = [];
$GLOBALS['__ref_null'] = false;
if (!function_exists('sige_uploads_private_referenced_keys')) {
    function sige_uploads_private_referenced_keys(): ?array {
        if (!empty($GLOBALS['__ref_null'])) return null;
        return $GLOBALS['__ref_set'];
    }
}

require_once dirname(__DIR__) . '/includes/secure-document-download.php';
require_once dirname(__DIR__) . '/includes/security-uploads-private.php';

$fails = []; $ok = 0;
$check = static function (bool $cond, string $label) use (&$fails, &$ok): void {
    if ($cond) { $ok++; echo "OK   {$label}\n"; } else { $fails[] = $label; echo "FAIL {$label}\n"; }
};

// 1. Links com expiracao.
$exp = time() + 3600;
$sig = sige_secure_document_link_sig('aluno', 7, 'doc_bi_url', $exp);
$check(sige_secure_document_link_valid('aluno', 7, 'doc_bi_url', $exp, $sig) === true, 'link fresco e valido');
$past = time() - 10;
$check(sige_secure_document_link_valid('aluno', 7, 'doc_bi_url', $past, sige_secure_document_link_sig('aluno', 7, 'doc_bi_url', $past)) === false, 'link expirado recusado');
$check(sige_secure_document_link_valid('aluno', 7, 'doc_cert_url', $exp, $sig) === false, 'campo adulterado recusado');
$check(sige_secure_document_link_valid('staff', 7, 'doc_bi_url', $exp, $sig) === false, 'escopo adulterado recusado');
$check(sige_secure_document_link_valid('aluno', 7, 'doc_bi_url', $exp, '') === false, 'assinatura vazia recusada');
$check(sige_secure_document_link_valid('aluno', 7, 'doc_bi_url', $exp, $sig . 'x') === false, 'assinatura alterada recusada');

// 2. Varrimento de orfaos.
$eid_dir = $UPLOADS . '/sige-private/docs/7';
@mkdir($eid_dir, 0777, true);
// Ficheiros de proteccao (nunca devem ser apagados).
file_put_contents($UPLOADS . '/sige-private/docs/.htaccess', "Require all denied\n");
file_put_contents($eid_dir . '/index.php', "<?php\n");
// Documento referenciado.
file_put_contents($eid_dir . '/bi-referenciado.pdf', 'X');
// Orfao antigo (mtime para alem do periodo de graca).
file_put_contents($eid_dir . '/bi-orfao-antigo.pdf', 'Y');
@touch($eid_dir . '/bi-orfao-antigo.pdf', time() - 90000); // > 24h
// Orfao recente (dentro do periodo de graca).
file_put_contents($eid_dir . '/bi-orfao-recente.pdf', 'Z');

// Referencias: so o referenciado (chave a partir de sige-private/).
$GLOBALS['__ref_set'] = ['sige-private/docs/7/bi-referenciado.pdf' => true];
$GLOBALS['__ref_null'] = false;
$removed = sige_uploads_private_orphan_sweep(100);
$check($removed === 1, 'varrimento removeu exactamente 1 orfao antigo');
$check(!is_file($eid_dir . '/bi-orfao-antigo.pdf'), 'orfao antigo removido');
$check(is_file($eid_dir . '/bi-referenciado.pdf'), 'documento referenciado mantido');
$check(is_file($eid_dir . '/bi-orfao-recente.pdf'), 'orfao recente mantido (periodo de graca)');
$check(is_file($UPLOADS . '/sige-private/docs/.htaccess') && is_file($eid_dir . '/index.php'), 'ficheiros de proteccao mantidos');

// 3. Aborto seguro quando as referencias nao podem ser lidas.
file_put_contents($eid_dir . '/outro-orfao-antigo.pdf', 'W');
@touch($eid_dir . '/outro-orfao-antigo.pdf', time() - 90000);
$GLOBALS['__ref_null'] = true;
$removed2 = sige_uploads_private_orphan_sweep(100);
$check($removed2 === 0 && is_file($eid_dir . '/outro-orfao-antigo.pdf'), 'varrimento aborta sem apagar quando as referencias falham');

// Limpeza.
$rrm = static function ($d) use (&$rrm) { foreach (glob($d . '/*') ?: [] as $p) { is_dir($p) ? $rrm($p) : @unlink($p); } @rmdir($d); };
$rrm($BASE);

echo str_repeat('-', 56) . "\n";
if ($fails) {
    fwrite(STDERR, 'SMOKE CICLO DE VIDA DOS DOCUMENTOS FALHOU: ' . count($fails) . " falha(s)\n");
    foreach ($fails as $f) fwrite(STDERR, " - {$f}\n");
    exit(1);
}
echo "SMOKE CICLO DE VIDA DOS DOCUMENTOS OK - {$ok} verificacoes passaram.\n";
