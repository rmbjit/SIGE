<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.11.9.14 - Smoke estático Página do Aluno / Pendências financeiras reais
 */
$root = dirname(__DIR__);
$failures = [];
$read = function($rel) use ($root) {
    $path = $root . '/' . $rel;
    return file_exists($path) ? file_get_contents($path) : '';
};
$ok = function($msg) { echo "OK - {$msg}\n"; };
$fail = function($msg) use (&$failures) { $failures[] = $msg; echo "FAIL - {$msg}\n"; };
$contains = function($haystack, $needle, $msg) use ($ok, $fail) {
    strpos($haystack, $needle) !== false ? $ok($msg) : $fail($msg);
};
$notContains = function($haystack, $needle, $msg) use ($ok, $fail) {
    strpos($haystack, $needle) === false ? $ok($msg) : $fail($msg);
};

$main     = $read('sige-softgenial.php');
$buildRaw = $read('BUILD.json');
$build    = json_decode($buildRaw, true);
$portal   = $read('admin/academic/aluno-portal-view.php');
$legacy   = $read('includes/portal-logic.php');
$logic    = $read('includes/academic-logic.php');
$mapH     = $read('includes/map-pdf-handler.php');
$rh       = $read('admin/hr/equipe-view.php');
$transp   = $read('admin/logistics/transporte-view.php');

$contains($main, 'Version: 12.11.9.14', 'Header do plugin em 12.11.9.14');
$contains($main, "define('SIGE_VERSION', '12.11.9.14');", 'SIGE_VERSION em 12.11.9.14');
(($build['version'] ?? '') === '12.11.9.14') ? $ok('BUILD.json em 12.11.9.14') : $fail('BUILD.json não está em 12.11.9.14');

$contains($portal, 'function sige_aluno_portal_saldo_sql', 'Página do Aluno tem helper financeiro canónico');
$contains($portal, "LOWER(COALESCE(l.status,'')) IN ('pendente','parcial')", 'Página do Aluno restringe dívida a pendente/parcial');
$contains($portal, '{$ap_saldo_expr} > 0.009', 'Página do Aluno ignora saldo técnico residual/zero');
$contains($portal, "LOWER(COALESCE(l.status,'')) = 'em_plano'", 'Página do Aluno separa plano negociado da dívida livre');
$contains($portal, 'sige_fin_total_lancamento', 'Tabela financeira usa total canónico quando disponível');
$notContains($portal, "status NOT IN ('pago','cancelado','anulado') THEN GREATEST((valor_original", 'Página do Aluno não usa fórmula antiga de falso positivo');

$contains($legacy, 'function sige_portal_fin_saldo_sql', 'Portal legado tem helper financeiro canónico');
$contains($legacy, "LOWER(COALESCE(l.status,'')) IN ('pendente','parcial')", 'Portal legado restringe dívida a pendente/parcial');
$contains($legacy, '{$sp_saldo_expr} > 0.009', 'Portal legado ignora saldo técnico residual/zero');
$contains($legacy, 'sige_fin_saldo_lancamento($l)', 'Lista de propinas em dívida usa saldo canónico');
$contains($legacy, 'valor_desconto_especial', 'Portal legado considera desconto especial na fórmula fallback');

$contains($logic, 'function sige_classe_usa_af($classe_num)', 'Regra canónica AF preservada');
$contains($logic, 'return (int) $classe_num === 3;', 'AF continua exclusiva da 3.ª classe');
$contains($mapH, 'function sige_map_turma_pdf_handler()', 'MAP por turma preservado');
$contains($rh, 'rh-arquivo-removidos', 'RH validado preservado');
$contains($transp, 'Transporte Escolar', 'Transportes harmonizado preservado');

if ($failures) {
    fwrite(STDERR, "\nFalhas:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "OK - smoke Página do Aluno Pendências v12.11.9.14 concluído sem falhas.\n";
