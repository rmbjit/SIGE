<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Gate da reconciliacao (Fase 7 incremento 1).
 *
 * Verifica que o relatorio de divergencias e so de leitura, esta separado da
 * apresentacao, tem guarda de acesso e nao introduz endpoint de escrita.
 */

$root = dirname(__DIR__);
$read = function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return file_exists($p) ? (string) file_get_contents($p) : '';
};

$fails = [];

$lib  = $read('includes/payments/reconciliacao-divergencias.php');
$view = $read('admin/finance/reconciliacao-view.php');
$shell = $read('includes/admin-shell.php');
$boot = $read('sige-softgenial.php');

// 1. Helper existe e define a funcao de dominio.
if ($lib === '') {
    $fails[] = 'includes/payments/reconciliacao-divergencias.php em falta';
} else {
    if (strpos($lib, 'function sige_reconciliacao_divergencias') === false) {
        $fails[] = 'funcao sige_reconciliacao_divergencias em falta';
    }
    // 2. Helper e so de leitura.
    foreach (['->insert(', '->update(', '->delete(', 'INSERT ', 'UPDATE ', 'DELETE ', 'DROP ', 'ALTER '] as $w) {
        if (strpos($lib, $w) !== false) { $fails[] = "helper de reconciliacao tem escrita proibida ({$w})"; }
    }
    // 3. As tres classes de divergencia + totais.
    foreach (['nao_conciliadas', 'divergencias_montante', 'pagamentos_sem_gateway', 'totais'] as $k) {
        if (strpos($lib, $k) === false) { $fails[] = "classe '{$k}' em falta no helper"; }
    }
    // 4. Fail-closed por escola.
    if (strpos($lib, '$escola_id <= 0') === false) {
        $fails[] = 'helper de reconciliacao nao e fail-closed por escola';
    }
    // 5. Idempotencia partilhada: usa a tabela unica de transacoes.
    if (strpos($lib, 'sige_mpesa_transacoes') === false) {
        $fails[] = 'helper nao consulta sige_mpesa_transacoes';
    }
}

// 6. View existe, tem guarda de acesso e e so de leitura.
if ($view === '') {
    $fails[] = 'admin/finance/reconciliacao-view.php em falta';
} else {
    if (strpos($view, 'sige_mpesa_pode_gerir') === false) {
        $fails[] = 'view de reconciliacao sem guarda de acesso (sige_mpesa_pode_gerir)';
    }
    foreach (['$_POST', '<form', 'admin-post', 'wp_ajax', '->insert(', '->update(', '->delete('] as $w) {
        if (strpos($view, $w) !== false) { $fails[] = "view de reconciliacao nao deve ter escrita/POST ({$w})"; }
    }
    if (strpos($view, 'sige_reconciliacao_divergencias') === false) {
        $fails[] = 'view nao usa o helper de dominio';
    }
}

// 7. Rota e link de navegacao registados.
if (strpos($shell, "'financeiro-reconciliacao' => 'admin/finance/reconciliacao-view.php'") === false) {
    $fails[] = 'rota financeiro-reconciliacao nao registada no mapa';
}
if (strpos($shell, 'view=financeiro-reconciliacao') === false) {
    $fails[] = 'link de navegacao para a reconciliacao em falta';
}
// 7b. [v12.12.22] A rota no mapa nao basta: o slug TEM de constar da allowlist anti-LFI E da
// matriz de permissoes, senao a guarda reescreve o pedido para o painel inicial (rota morta).
if (preg_match('/\$_views_ok\s*=\s*\[(.*?)\];/s', $shell, $mAlR)) {
    if (strpos($mAlR[1], "'financeiro-reconciliacao'") === false) {
        $fails[] = 'financeiro-reconciliacao ausente da allowlist $_views_ok (rota cai no painel inicial)';
    }
} else {
    $fails[] = 'allowlist $_views_ok nao encontrada no admin-shell';
}
if (preg_match('/\$sige_view_permission_map\s*=\s*\[(.*?)\n\s*\];/s', $shell, $mPmR)) {
    if (strpos($mPmR[1], '"financeiro-reconciliacao"') === false) {
        $fails[] = 'financeiro-reconciliacao ausente da matriz de permissoes';
    }
} else {
    $fails[] = 'matriz de permissoes nao encontrada no admin-shell';
}

// 8. Helper carregado no arranque.
if (strpos($boot, 'reconciliacao-divergencias.php') === false) {
    $fails[] = 'helper de reconciliacao nao carregado em sige-softgenial.php';
}

if ($fails) {
    echo "RECONCILIACAO FALHOU:\n";
    foreach ($fails as $f) { echo " - {$f}\n"; }
    exit(1);
}

echo "RECONCILIACAO OK - relatorio de divergencias so de leitura (recebido por aplicar, divergencia de montante, pagamento sem gateway), dominio separado da apresentacao, guarda de acesso, fail-closed por escola, idempotencia partilhada pela tabela unica, sem endpoint novo.\n";
