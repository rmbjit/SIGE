<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$checks = [];
$fail = [];
function add_check(&$checks, &$fail, $label, $condition) {
    $checks[] = $label;
    if (!$condition) $fail[] = $label;
}
function file_has($rel, $needle) {
    global $root;
    $path = $root . '/' . $rel;
    return is_file($path) && strpos(file_get_contents($path), $needle) !== false;
}
function file_has_all($rel, array $needles) {
    foreach ($needles as $n) if (!file_has($rel, $n)) return false;
    return true;
}

add_check($checks,$fail,'Version header 12.11.9.81', file_has('sige-softgenial.php', 'Version: 12.11.9.81'));
add_check($checks,$fail,'SIGE_VERSION 12.11.9.81', file_has('sige-softgenial.php', "SIGE_VERSION', '12.11.9.81"));
add_check($checks,$fail,'Build JSON v12.11.9.81', file_has('BUILD.json', '12.11.9.81') && file_has('BUILD.json', 'historico-financeiro-aluno-pro'));
add_check($checks,$fail,'Require financeiro-historico-aluno-pro', file_has('sige-softgenial.php', 'financeiro-historico-aluno-pro.php'));

add_check($checks,$fail,'Documento PRO file exists', is_file($root . '/includes/financeiro-historico-aluno-pro.php'));
add_check($checks,$fail,'Documento PRO helpers principais', file_has_all('includes/financeiro-historico-aluno-pro.php', [
    'sige_fin_hist_aluno_load',
    'sige_fin_hist_aluno_render_html',
    'sige_fin_hist_aluno_output_csv',
    'sige_fin_hist_aluno_can_download',
    'sige_fin_hist_aluno_build_url',
    'sige_fin_hist_aluno_maybe_handle_print'
]));
add_check($checks,$fail,'Documento PRO copy essencial', file_has_all('includes/financeiro-historico-aluno-pro.php', [
    'Histórico financeiro do aluno',
    'Visão financeira 360º',
    'Resumo executivo',
    'Pagamentos registados',
    'Lançamentos e obrigações',
    'Guardar PDF / Imprimir',
    'Baixar CSV'
]));
add_check($checks,$fail,'Documento PRO métricas 360º', file_has_all('includes/financeiro-historico-aluno-pro.php', [
    'total_lancado',
    'total_entradas',
    'total_estornos',
    'total_liquido',
    'credito_disponivel',
    'regularidade',
    'estado_financeiro'
]));
add_check($checks,$fail,'Documento PRO não altera cálculo canónico', file_has_all('includes/financeiro-historico-aluno-pro.php', ['sige_fin_saldo_sql', 'sige_fin_total_bruto_sql']));
add_check($checks,$fail,'Documento PRO CSV separado por ;', file_has('includes/financeiro-historico-aluno-pro.php', "implode(';',"));
add_check($checks,$fail,'Documento PRO route admin_init priority 0', file_has('includes/financeiro-historico-aluno-pro.php', "add_action('admin_init', 'sige_fin_hist_aluno_maybe_handle_print', 0)"));

add_check($checks,$fail,'Documents engine reconhece historico_financeiro_aluno', file_has('includes/documents-engine.php', 'historico_financeiro_aluno'));
add_check($checks,$fail,'Scope guard reconhece historico_financeiro_aluno', file_has('includes/security-scope-guard.php', "'historico_financeiro_aluno'"));
add_check($checks,$fail,'Guarda bloqueado no histórico financeiro', file_has('includes/financeiro-historico-aluno-pro.php', "current_user_can('sige_guarda')"));

add_check($checks,$fail,'Alunos card tem Histórico financeiro', file_has_all('admin/academic/alunos_lista.php', [
    'btn-fin-historico',
    'sige-mobile-fin-action',
    'Histórico financeiro',
    'sige_fin_hist_aluno_build_url'
]));
add_check($checks,$fail,'Ficha 360 tem CTA histórico financeiro', file_has_all('admin/academic/alunos_lista.php', [
    'baixarHistoricoFinanceiroAluno',
    'sigeAlunoFinanceHistoryUrl',
    'sigeAluno360FinanceCurrentUrl',
    'sige-360-fin-actions',
    'Baixar histórico financeiro',
    'Baixar CSV',
    'Abrir extracto/caixa'
]));
add_check($checks,$fail,'Ficha 360 expõe URLs financeiro sob permissão', file_has_all('includes/aluno-fetch-ajax.php', [
    'historico_url',
    'historico_csv_url',
    'extracto_url',
    'status_operacional',
    'saldo_vencido',
    'total_estornado',
    'ultimo_pagamento',
    'proximo_vencimento'
]));
add_check($checks,$fail,'Guarda não recebe URLs financeiros', file_has_all('includes/aluno-fetch-ajax.php', [
    "'historico_url' => ''",
    "'historico_csv_url' => ''",
    "'extracto_url' => ''",
    "'oculto_por_permissao' => true"
]));

add_check($checks,$fail,'Extractos pesquisa por processo', file_has('admin/finance/financeiro-extratos.php', 'numero_processo LIKE'));
add_check($checks,$fail,'Extractos placeholder processo', file_has('admin/finance/financeiro-extratos.php', 'nome ou número de processo'));
add_check($checks,$fail,'Extractos CTA PDF/CSV PRO', file_has_all('admin/finance/financeiro-extratos.php', [
    'sg-hist-pro-download',
    'Guardar PDF / Imprimir',
    'Baixar CSV',
    'Histórico financeiro 360º pronto para baixar'
]));
add_check($checks,$fail,'Excel aluno usa título histórico', file_has_all('admin/finance/financeiro-extratos.php', [
    'HISTÓRICO FINANCEIRO DO ALUNO',
    'alunoNome',
    'alunoProcesso',
    'Historico_Financeiro_Aluno_'
]));

add_check($checks,$fail,'Portaria state consistency preservada', file_has('includes/db-handler.php', 'acesso_permitido') && file_has('includes/portaria-camera-safe-page.php', 'BLOQUEADO'));
add_check($checks,$fail,'Role Guarda preservado', file_has('includes/security-roles.php', 'sige_guarda'));

$total = count($checks);
$ok = $total - count($fail);
echo "Smoke Histórico Financeiro Aluno v12.11.9.81: {$ok}/{$total} checks OK\n";
if ($fail) {
    echo "Falhas:\n";
    foreach ($fail as $f) echo " - {$f}\n";
    exit(1);
}
