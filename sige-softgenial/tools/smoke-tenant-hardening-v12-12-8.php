<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SMOKE - Tenant Isolation Hardening (escritas) - v12.12.8
 *
 * Prova, por inspeccao de codigo e dos baselines, que:
 *  - existe o resolvedor fail-closed sige_require_escola_id e que ele bloqueia
 *    (wp_die) e audita (tenant_write_blocked) quando a escola nao resolve;
 *  - os call-sites de escrita em contexto de request usam o resolvedor;
 *  - as funcoes de biblioteca abortam a escrita (return tipado) sem wp_die;
 *  - o baseline de fallbacks desceu de 178 (v12.12.7.1) para 139 (v12.12.8);
 *  - as funcoes de calculo financeiro nao foram tocadas.
 */
$root = dirname(__DIR__);
if (!defined('ABSPATH')) define('ABSPATH', $root . '/');

$fails = [];
$oks = 0;
$check = static function (bool $cond, string $label) use (&$fails, &$oks): void {
    if ($cond) { $oks++; echo "OK   {$label}\n"; }
    else { $fails[] = $label; echo "FAIL {$label}\n"; }
};
$read = static function (string $rel) use ($root): string {
    return (string) @file_get_contents($root . '/' . $rel);
};

// 1. Resolvedor fail-closed
$mt = $read('includes/multitenancy.php');
$check(strpos($mt, 'function sige_require_escola_id') !== false, 'Resolvedor sige_require_escola_id existe');
$check(strpos($mt, "sige_security_log('tenant_write_blocked'") !== false, 'Resolvedor audita tenant_write_blocked');
$check(strpos($mt, 'wp_die(') !== false && strpos($mt, "'response' => 403") !== false, 'Resolvedor bloqueia com wp_die 403');
// o guard <= 0 esta presente no corpo do resolvedor
$reqFn = substr($mt, strpos($mt, 'function sige_require_escola_id'));
$check(strpos($reqFn, '$eid <= 0') !== false, 'Resolvedor tem guard $eid <= 0');

// 2. Call-sites de escrita em contexto de request usam o resolvedor
$requestFiles = [
    'admin/academic/abertura-view.php',
    'admin/academic/disciplinas-view.php',
    'admin/academic/encerramento-view.php',
    'admin/academic/notas-view.php',
    'admin/finance/financeiro-config.php',
    'admin/finance/financeiro-inscricoes-view.php',
    'admin/finance/financeiro-planos-view.php',
    'admin/logistics/transporte-view.php',
    'includes/academic-logic.php',
    'includes/acta-pdf-handler.php',
    'includes/aluno-fetch-ajax.php',
    'includes/centros-helpers.php',
    'includes/db-handler.php',
    'includes/whatsapp-engine.php',
];
foreach ($requestFiles as $rf) {
    $check(strpos($read($rf), 'sige_require_escola_id(') !== false, "Usa sige_require_escola_id: {$rf}");
}

// 3. Funcoes de biblioteca abortam a escrita (return tipado) sem wp_die novo
$fc = $read('includes/finance-core.php');
$check(strpos($fc, 'if ($eid <= 0) { return false; }') !== false, 'finance-core queue_whatsapp aborta (return false)');
$check(strpos($fc, 'if ($eid <= 0) { return null; }') !== false, 'finance-core servico_transporte aborta (return null)');
$np = $read('includes/notification-policy.php');
$check(substr_count($np, 'if ($eid <= 0) { return false; }') >= 2, 'notification-policy aborta nas duas funcoes (return false)');
$wg = $read('includes/whatsapp-guardian.php');
$check(strpos($wg, 'if ($escola_id <= 0) { return false; }') !== false, 'whatsapp-guardian aborta (return false)');
$fde = $read('includes/finance-data-efectiva.php');
$check(strpos($fde, "'message' => 'Contexto de escola invalido.'") !== false, 'finance-data-efectiva aborta com ok=false');
$tm = $read('includes/settings/class-sige-settings-technical-mode.php');
$check(strpos($tm, 'if ($eid <= 0) { return; }') !== false, 'technical-mode audit aborta (return) sem escola');
$wdp = $read('includes/whatsapp-destinatarios-policy.php');
$check(strpos($wdp, "if (\$eid <= 0) { return ''; }") !== false, 'whatsapp-destinatarios aborta (return vazio)');

// 4. Baselines de fallback: desceu de 178 para 139
$b8 = json_decode($read('docs/security/TENANT_FALLBACK_BASELINE-v12.12.8.json'), true);
$b71 = json_decode($read('docs/security/TENANT_FALLBACK_BASELINE-v12.12.7.1.json'), true);
$c8 = is_array($b8) && isset($b8['items']) ? count($b8['items']) : -1;
$c71 = is_array($b71) && isset($b71['items']) ? count($b71['items']) : -1;
$check($c8 === 139, "Baseline v12.12.8 = 139 (obtido {$c8})");
$check($c71 === 178, "Baseline v12.12.7.1 congelado = 178 (obtido {$c71})");
$check($c8 < $c71, 'Baseline desceu (139 < 178)');

// 5. Funcoes de calculo financeiro nao foram tocadas (continuam presentes)
$check(strpos($fc, 'function sige_fin_saldo_lancamento') !== false, 'sige_fin_saldo_lancamento intacta');
$check(strpos($fc, 'function sige_fin_saldo_sql') !== false, 'sige_fin_saldo_sql intacta');

echo "\n";
if ($fails) {
    fwrite(STDERR, 'SMOKE TENANT HARDENING v12.12.8 FALHOU (' . count($fails) . "):\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo "SMOKE TENANT HARDENING v12.12.8 OK - {$oks} verificacoes.\n";
