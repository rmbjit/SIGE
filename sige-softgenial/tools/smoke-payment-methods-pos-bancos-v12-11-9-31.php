<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$checks = [];
$ok = function($cond, $msg) use (&$checks) { $checks[] = [$cond, $msg]; };
$read = function($rel) use ($root) { return file_get_contents($root . '/' . $rel); };
$core = $read('includes/finance-core.php');
$pag  = $read('admin/finance/financeiro-pagamentos.php');
$plan = $read('admin/finance/financeiro-planos-view.php');
$ext  = $read('admin/finance/financeiro-extratos.php');
$rel  = $read('admin/finance/financeiro-relatorio-mensal-view.php');
$dash = $read('admin/finance/financeiro-dashboard.php');
$desp = $read('admin/finance/financeiro-despesas-view.php');
$portal = $read('includes/portal-logic.php');

foreach (['pos_bci'=>'POS BCI','pos_bim'=>'POS BIM','pos_stbank'=>'POS STBANK','pos_moza'=>'POS MOZA','pos_nedbank'=>'POS NEDBANK','pos_fnb'=>'POS FNB'] as $key => $label) {
    $ok(strpos($core, "'$key'") !== false && strpos($core, $label) !== false, "Core contém $key/$label");
    $ok(strpos($pag, $key) !== false, "Pagamento normal contém $key");
    $ok(strpos($plan, $key) !== false, "Planos contêm $key");
    $ok(strpos($ext, $key) !== false, "Extractos contêm $key");
    $ok(strpos($rel, $key) !== false, "Relatório mensal contém $key");
    $ok(strpos($desp, $key) !== false, "Despesas contêm $key");
}
$ok(strpos($core, "'pos'               => 'POS (Banco não especificado)'") !== false, "Valor legado pos é preservado como POS (Banco não especificado)");
$ok(preg_match("/'bim'\\s*=>\\s*'Millennium BIM'/", $core) === 1, "Valor legado bim preservado como Millennium BIM");
$ok(preg_match("/'bci'\\s*=>\\s*'BCI'/", $core) === 1, "Valor legado bci preservado como BCI");
$ok(strpos($pag, '<option value="bim">Millennium BIM</option>') !== false, "Pagamento normal preserva Millennium BIM isolado");
$ok(strpos($pag, '<option value="bci">BCI</option>') !== false, "Pagamento normal preserva BCI isolado");
$ok(strpos($pag, 'POS (Outros)') === false && strpos($pag, 'POS (Outro banco)') === false && strpos($pag, 'POS (Banco não especificado)') === false && strpos($pag, 'POS/TPA') === false, "Pagamento normal não mostra POS genérico");
$ok(strpos($pag, "sige_fin_metodos_pagamento_options_html('', ['context' => 'pagamento'])") !== false, "Pagamento normal usa helper canónico");
$ok(strpos($pag, "sige_fin_metodos_pagamento_options_html('', ['context' => 'familia'])") !== false, "Pagamento familiar usa helper canónico");
$ok(strpos($plan, "sige_fin_metodos_pagamento_options_html('', ['context' => 'plano'])") !== false, "Planos usam helper canónico");
$ok(strpos($desp, "sige_fin_metodos_pagamento_options_html('', ['context' => 'despesa'])") !== false, "Despesas usam helper canónico");
$ok(strpos($dash, 'sige_fin_metodo_pagamento_label($key)') !== false, "Dashboard usa label canónica");
$ok(strpos($portal, 'sige_fin_metodo_pagamento_label($pg->metodo_pagamento') !== false || strpos($portal, 'sige_fin_metodo_pagamento_label($pag->metodo_pagamento') !== false, "Portal usa label canónica");
$ok(strpos($core, 'POS/TPA') === false, "Texto antigo POS/TPA removido do core");
$ok(strpos($pag, 'POS/TPA') === false, "Texto antigo POS/TPA removido dos pagamentos");

$fail = 0;
foreach ($checks as [$cond, $msg]) {
    echo ($cond ? 'OK  ' : 'FAIL') . " - $msg\n";
    if (!$cond) $fail++;
}
if ($fail) {
    fwrite(STDERR, "$fail falha(s) no smoke de POS por banco.\n");
    exit(1);
}
echo "Smoke POS por banco concluído: " . count($checks) . " OK / 0 FAIL\n";
