<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$errors = [];
$ui = file_get_contents($root . '/includes/ui-kit.php');
$css = file_get_contents($root . '/assets/views/financeiro-core-design-pro.css');
$js = file_get_contents($root . '/assets/views/financeiro-core-design-pro.js');

$requiredViews = [
    'financeiro-dashboard','financeiro-pagamentos','financeiro-devedores','financeiro-extratos',
    'financeiro-lancamentos','financeiro-relatorio-mensal','financeiro-centros','financeiro-config',
    'financeiro-planos','financeiro-despesas','financeiro-auditoria','financeiro-inscricoes',
    'financeiro-gerador','pagamentos-turma','mpesa','reconciliacao','aprovacoes'
];
foreach ($requiredViews as $view) {
    if (strpos($ui, "'{$view}'") === false) $errors[] = "View financeira ausente da whitelist do ui-kit: {$view}";
    $bodyClass = '.sige-view-' . $view;
    if (strpos($css, $bodyClass) === false) $errors[] = "CSS financeiro sem escopo para {$bodyClass}";
}
if (strpos($ui, 'sige_design_financeiro_core_v121512_enabled') === false) $errors[] = 'Feature flag financeira ausente no ui-kit.';
if (strpos($ui, 'financeiro-core-design-pro.css') === false || strpos($ui, 'financeiro-core-design-pro.js') === false) $errors[] = 'Assets financeiros não enfileirados.';
if (strpos($ui, 'in_array($sige_view, $financeiro_core_views, true)') === false) $errors[] = 'Assets financeiros não estão limitados por whitelist de view.';
$financeiroArraySegment = '';
if (preg_match('/\$financeiro_core_views\s*=\s*\[([\s\S]*?)\];/m', $ui, $m)) {
    $financeiroArraySegment = $m[1];
} else {
    $errors[] = 'Array $financeiro_core_views não encontrado no ui-kit.';
}
foreach (['alunos_lista','portaria','portaria_camera','sige_portaria_camera'] as $forbiddenView) {
    if ($financeiroArraySegment !== '' && preg_match('/[\"\']' . preg_quote($forbiddenView, '/') . '[\"\']/', $financeiroArraySegment)) {
        $errors[] = "View fora de escopo incluída no Financeiro Core: {$forbiddenView}";
    }
}
foreach (['MutationObserver', 'appendChild', 'innerHTML', 'document.write', 'eval(', 'new Function', 'addEventListener(\'submit', 'addEventListener("submit', 'addEventListener(\'click', 'addEventListener("click'] as $forbidden) {
    if (strpos($js, $forbidden) !== false) $errors[] = "JS financeiro contém padrão proibido: {$forbidden}";
}
if (substr_count($css, 'body.sige-admin-app:is(') < 6) $errors[] = 'CSS financeiro parece pouco escopado ao body/view.';
if (strpos($css, 'overflow: hidden') !== false || strpos($css, 'overflow:hidden') !== false) $errors[] = 'CSS financeiro não pode introduzir overflow hidden.';
if (strpos($css, 'position: fixed') !== false || strpos($css, 'position:fixed') !== false) $errors[] = 'CSS financeiro não pode introduzir position fixed.';
if ($errors) {
    fwrite(STDERR, "check-financeiro-core-design-scope-v12-15-12: FALHOU\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
echo "check-financeiro-core-design-scope-v12-15-12: OK - Financeiro Core escopado, reversivel e sem JS funcional perigoso.\n";
