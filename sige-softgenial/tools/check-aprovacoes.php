<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Gate da regra de quatro-olhos (Fase 7 incremento 2).
 *
 * Verifica que o estorno e a reabertura passam por pedido + aprovacao de um
 * utilizador DIFERENTE (separacao de funcoes, sem auto-aprovacao), que a tabela
 * e o ecra existem, e que o endpoint de decisao esta governado no Kernel.
 */

$root = dirname(__DIR__);
$read = function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return file_exists($p) ? (string) file_get_contents($p) : '';
};

$fails = [];

$lib    = $read('includes/finance-aprovacoes.php');
$fecho  = $read('includes/fin-fecho-turno.php');
$view   = $read('admin/finance/aprovacoes-view.php');
$extr   = $read('admin/finance/financeiro-extratos.php');
$shell  = $read('includes/admin-shell.php');
$boot   = $read('sige-softgenial.php');
$mig    = $read('includes/class-sige-migration.php');
$gov    = $read('tools/governance-lib.php');
$css    = $read('assets/views/aprovacoes.css');
$uikit  = $read('includes/ui-kit.php');

// 1. Modulo de dominio existe e define as funcoes nucleares.
if ($lib === '') {
    $fails[] = 'includes/finance-aprovacoes.php em falta';
} else {
    foreach ([
        'function sige_fin_aprovacao_tipos',
        'function sige_fin_aprovacao_solicitar',
        'function sige_fin_aprovacao_decidir',
        'function sige_fin_aprovacao_executar',
        'function sige_fin_aprovacoes_listar',
        'function sige_fin_aprovacao_pode_aceder',
        'function sige_fin_aprovacao_tem_permissao',
    ] as $fn) {
        if (strpos($lib, $fn) === false) { $fails[] = "funcao em falta no modulo: {$fn}"; }
    }
    // 2. Separacao de funcoes: o decisor nao pode ser o solicitante.
    if (strpos($lib, 'solicitante_user_id === $uid') === false) {
        $fails[] = 'separacao de funcoes ausente (decisor != solicitante)';
    }
    // 3. Os dois tipos cobertos e a permissao de cada um.
    foreach (['estorno_pagamento', 'reabertura_caixa', 'financeiro.estornar', 'financeiro.caixa_reabrir'] as $k) {
        if (strpos($lib, $k) === false) { $fails[] = "chave '{$k}' em falta no modulo"; }
    }
    // 4. Fail-closed por escola.
    if (strpos($lib, '$escola_id <= 0') === false) {
        $fails[] = 'modulo de aprovacoes nao e fail-closed por escola';
    }
    // 5. Dedup de pedidos pendentes.
    if (strpos($lib, "estado = 'pendente'") === false || strpos($lib, 'Ja existe um pedido pendente') === false) {
        $fails[] = 'sem guarda anti-duplicado de pedidos pendentes';
    }
    // 6. Execucao reutiliza estornarPagamento e a reabertura extraida.
    if (strpos($lib, 'estornarPagamento') === false) { $fails[] = 'execucao nao reutiliza estornarPagamento'; }
    if (strpos($lib, 'sige_fin_reabertura_caixa_executar') === false) { $fails[] = 'execucao nao usa sige_fin_reabertura_caixa_executar'; }
    // 7. Ledger nos tres momentos.
    foreach (['fin_aprovacao_solicitada', 'fin_aprovacao_aprovada', 'fin_aprovacao_rejeitada'] as $ev) {
        if (strpos($lib, $ev) === false) { $fails[] = "evento de ledger em falta: {$ev}"; }
    }
}

// 8. Funcao de reabertura extraida, com MFA do aprovador.
if (strpos($fecho, 'function sige_fin_reabertura_caixa_executar') === false) {
    $fails[] = 'sige_fin_reabertura_caixa_executar em falta em fin-fecho-turno.php';
} elseif (strpos($fecho, "sige_mfa_require_step_up('caixa_reabrir')") === false) {
    $fails[] = 'reabertura executar sem MFA do aprovador';
}

// 9. Os dois handlers em extratos criam pedidos (nao executam directamente).
$nsolic = substr_count($extr, 'sige_fin_aprovacao_solicitar(');
if ($nsolic < 2) { $fails[] = "extratos deve criar pedido nos dois handlers (encontrados {$nsolic})"; }
if (strpos($extr, "sige_fin_aprovacao_solicitar('estorno_pagamento'") === false) { $fails[] = 'handler de estorno nao cria pedido'; }
if (strpos($extr, "sige_fin_aprovacao_solicitar('reabertura_caixa'") === false) { $fails[] = 'handler de reabertura nao cria pedido'; }
// O estorno deixou de executar inline (estornarPagamento ja nao e chamado em extratos).
if (strpos($extr, 'SIGE_FinanceActionService::estornarPagamento(') !== false) {
    $fails[] = 'estorno ainda executa inline em extratos (devia ir por aprovacao)';
}

// 10. View: guarda de acesso + handler de decisao + nonce, sem estilo inline.
if ($view === '') {
    $fails[] = 'admin/finance/aprovacoes-view.php em falta';
} else {
    if (strpos($view, 'sige_fin_aprovacao_pode_aceder') === false) { $fails[] = 'view sem guarda de acesso'; }
    if (strpos($view, "isset(\$_POST['sige_fin_aprovacao_decidir'])") === false) { $fails[] = 'view sem handler de decisao'; }
    if (strpos($view, "wp_verify_nonce") === false || strpos($view, 'sige_fin_aprovacao_decidir') === false) { $fails[] = 'view sem nonce na decisao'; }
    if (strpos($view, 'sige_fin_aprovacao_decidir(') === false) { $fails[] = 'view nao invoca a decisao do dominio'; }
    if (preg_match('/\bstyle\s*=\s*["\']/', $view)) { $fails[] = 'view tem estilo inline (deve usar tokens)'; }
}

// 11. Rota + nav registadas.
if (strpos($shell, "'financeiro-aprovacoes' => 'admin/finance/aprovacoes-view.php'") === false) {
    $fails[] = 'rota financeiro-aprovacoes em falta no admin-shell';
}
if (strpos($shell, 'view=financeiro-aprovacoes') === false || strpos($shell, 'sige_fin_aprovacao_pode_aceder') === false) {
    $fails[] = 'link de navegacao para Aprovacoes em falta';
}
// 11b. [v12.12.22] A rota no mapa nao basta: o slug TEM de constar da allowlist anti-LFI E da
// matriz de permissoes, senao a guarda reescreve o pedido para o painel inicial e a view fica
// morta na UI. Esta foi a regressao da Fase 7 que o gate nao apanhava.
if (preg_match('/\$_views_ok\s*=\s*\[(.*?)\];/s', $shell, $mAl)) {
    if (strpos($mAl[1], "'financeiro-aprovacoes'") === false) {
        $fails[] = 'financeiro-aprovacoes ausente da allowlist $_views_ok (rota cai no painel inicial)';
    }
} else {
    $fails[] = 'allowlist $_views_ok nao encontrada no admin-shell';
}
if (preg_match('/\$sige_view_permission_map\s*=\s*\[(.*?)\n\s*\];/s', $shell, $mPm)) {
    if (strpos($mPm[1], '"financeiro-aprovacoes"') === false) {
        $fails[] = 'financeiro-aprovacoes ausente da matriz de permissoes';
    }
} else {
    $fails[] = 'matriz de permissoes nao encontrada no admin-shell';
}

// 12. Modulo carregado no arranque.
if (strpos($boot, "'finance-aprovacoes.php'") === false) {
    $fails[] = 'finance-aprovacoes.php nao e carregado no arranque';
}

// 13. Tabela e SCHEMA_VERSION.
if (strpos($mig, 'sige_fin_aprovacoes') === false) { $fails[] = 'tabela sige_fin_aprovacoes em falta na migracao'; }
if (strpos($mig, "SCHEMA_VERSION = '20260621.1'") === false) { $fails[] = 'SCHEMA_VERSION nao foi subido para 20260621.1'; }

// 14. Endpoint de decisao governado (lista do manifesto).
if (strpos($gov, 'view_action:financeiro-aprovacoes:sige_fin_aprovacao_decidir') === false) {
    $fails[] = 'endpoint de decisao nao registado na lista de view-actions';
}

// 15. CSS tokenizado existe e e enfileirado.
if ($css === '') { $fails[] = 'assets/views/aprovacoes.css em falta'; }
if (strpos($uikit, 'aprovacoes.css') === false) { $fails[] = 'aprovacoes.css nao e enfileirado no ui-kit'; }

// Resultado.
if (!empty($fails)) {
    echo "GATE APROVACOES FALHOU:\n";
    foreach ($fails as $f) echo " - {$f}\n";
    exit(1);
}
echo "GATE APROVACOES OK - regra de quatro-olhos: pedido + aprovacao por utilizador diferente, sem auto-aprovacao, com MFA do aprovador, ledger completo e endpoint governado.\n";
exit(0);
