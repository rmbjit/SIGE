<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$checks = [];
$fail = [];
function chk($label, $cond) { global $checks, $fail; $checks[] = [$label, (bool)$cond]; if (!$cond) $fail[] = $label; }
function rd($rel) { global $root; $p = $root . '/' . $rel; return is_file($p) ? file_get_contents($p) : ''; }
function has($rel, $needle) { return strpos(rd($rel), $needle) !== false; }
function re($rel, $pattern) { return (bool)preg_match($pattern, rd($rel)); }

$main = rd('sige-softgenial.php');
$build = json_decode(rd('BUILD.json'), true);
$new = rd('includes/financeiro-historico-aluno-pro.php');
$ajax = rd('includes/aluno-fetch-ajax.php');
$alunos = rd('admin/academic/alunos_lista.php');
$extratos = rd('admin/finance/financeiro-extratos.php');
$docs = rd('includes/documents-engine.php');
$scope = rd('includes/security-scope-guard.php');
$portaria = rd('includes/portaria-camera-safe-page.php');

chk('Header do plugin em 12.11.9.81', strpos($main, 'Version: 12.11.9.81') !== false);
chk('SIGE_VERSION em 12.11.9.81', strpos($main, "define('SIGE_VERSION', '12.11.9.81')") !== false);
chk('BUILD.json em 12.11.9.81', is_array($build) && ($build['version'] ?? '') === '12.11.9.81');
chk('Build id identifica historico financeiro aluno pro', is_array($build) && strpos((string)($build['build_id'] ?? ''), 'historico-financeiro-aluno-pro') !== false);
chk('Novo include registado no bootstrap', strpos($main, "includes/financeiro-historico-aluno-pro.php") !== false);
chk('Novo ficheiro existe', $new !== '');

foreach (['sige_fin_hist_aluno_table_exists','sige_fin_hist_aluno_column_exists','sige_fin_hist_aluno_money','sige_fin_hist_aluno_date','sige_fin_hist_aluno_can_download','sige_fin_hist_aluno_build_url','sige_fin_hist_aluno_load','sige_fin_hist_aluno_output_csv','sige_fin_hist_aluno_render_html','sige_fin_hist_aluno_maybe_handle_print'] as $fn) {
    chk("Função {$fn} presente", strpos($new, 'function ' . $fn) !== false);
}
chk('Hook admin_init priority 0 para capturar documento cedo', strpos($new, "add_action('admin_init', 'sige_fin_hist_aluno_maybe_handle_print', 0)") !== false);
chk('Endpoint sige_print historico_financeiro_aluno', strpos($new, "'historico_financeiro_aluno'") !== false);
chk('Formato CSV suportado', strpos($new, "formato']") !== false && strpos($new, "'csv'") !== false);
chk('CSV com BOM UTF-8 para Excel', strpos($new, '"\\xEF\\xBB\\xBF"') !== false || strpos($new, "\xEF\xBB\xBF") !== false);
chk('HTML pronto para print/guardar PDF', strpos($new, 'window.print') !== false && strpos($new, 'Guardar PDF / Imprimir') !== false);
chk('Documento inclui resumo executivo', strpos($new, 'Resumo executivo') !== false);
chk('Documento inclui KPIs 360', strpos($new, 'Indicadores 360') !== false || strpos($new, 'Indicadores 360º') !== false);
chk('Documento inclui pagamentos registados', strpos($new, 'Pagamentos registados') !== false);
chk('Documento inclui lançamentos/obrigações', strpos($new, 'Lançamentos e obrigações') !== false);
chk('Documento inclui próximos acompanhamentos', strpos($new, 'Próximos acompanhamentos') !== false);
chk('Documento inclui entradas por método', strpos($new, 'Entradas por método') !== false);
chk('Documento é responsivo/print-aware', strpos($new, '@media(max-width:900px)') !== false && strpos($new, '@media(max-width:520px)') !== false && strpos($new, '@media print') !== false);

chk('Guarda bloqueado no histórico financeiro', strpos($new, "current_user_can('sige_guarda')") !== false && strpos($new, "slug === 'guarda'") !== false);
chk('Permissão financeiro.extractos_ver reconhecida', strpos($new, 'financeiro.extractos_ver') !== false);
chk('Permissão financeiro.cobrancas_ver reconhecida', strpos($new, 'financeiro.cobrancas_ver') !== false);
chk('Permissão documentos.emitir reconhecida', strpos($new, 'documentos.emitir') !== false);
chk('Aluno portal limitado ao próprio aluno', strpos($new, 'sige_get_aluno_id_do_utilizador') !== false && strpos($new, '$meu_aluno === $aluno_id') !== false);
chk('Scope por escola aplicado em aluno', strpos($new, 'WHERE id=%d AND escola_id=%d') !== false);
chk('Scope por escola aplicado em lançamentos', strpos($new, 'l.escola_id=%d AND l.aluno_id=%d') !== false);
chk('Scope por escola aplicado em pagamentos', strpos($new, 'p.escola_id=%d AND p.aluno_id=%d') !== false);
chk('Join turma preserva escola_id', strpos($new, 't.escola_id = m.escola_id') !== false);
chk('Finance-core saldo SQL usado quando disponível', strpos($new, 'sige_fin_saldo_sql') !== false);
chk('Finance-core total bruto SQL usado quando disponível', strpos($new, 'sige_fin_total_bruto_sql') !== false);
chk('Sem dbDelta no novo ficheiro', strpos($new, 'dbDelta') === false);
chk('Sem ALTER TABLE no novo ficheiro', stripos($new, 'ALTER TABLE') === false);
chk('Sem DELETE/UPDATE destrutivo no novo ficheiro', !preg_match('/\b(DELETE|UPDATE|INSERT)\b/i', $new));

chk('AJAX Ficha 360 expõe historico_url', strpos($ajax, "'historico_url'") !== false);
chk('AJAX Ficha 360 expõe historico_csv_url', strpos($ajax, "'historico_csv_url'") !== false);
chk('AJAX Ficha 360 expõe extracto_url', strpos($ajax, "'extracto_url'") !== false);
chk('AJAX Ficha 360 expõe total_pago', strpos($ajax, "'total_pago'") !== false);
chk('AJAX Ficha 360 expõe total_estornado', strpos($ajax, "'total_estornado'") !== false);
chk('AJAX Ficha 360 expõe ultimo_pagamento', strpos($ajax, "'ultimo_pagamento'") !== false);
chk('AJAX Ficha 360 expõe proximo_vencimento', strpos($ajax, "'proximo_vencimento'") !== false);
chk('AJAX minimiza payload financeiro para perfis sem escopo', substr_count($ajax, "'historico_url' => ''") >= 1 && (strpos($ajax, "'financeiro' => [") !== false || strpos($ajax, '$payload[\'financeiro\'] = [') !== false));
chk('AJAX usa builder oficial de URL quando disponível', strpos($ajax, 'sige_fin_hist_aluno_build_url') !== false);
chk('AJAX calcula pagamentos por tabela financeira', strpos($ajax, 'sige_fin_pagamentos') !== false || strpos($ajax, '$tPag') !== false);

chk('Alunos card tem acção Histórico mobile', strpos($alunos, 'sige-mobile-fin-action') !== false && strpos($alunos, 'Histórico') !== false);
chk('Alunos menu tem Histórico financeiro', strpos($alunos, 'btn-fin-historico') !== false && strpos($alunos, 'Histórico financeiro') !== false);
chk('Alunos Ficha 360 tem botão Baixar histórico financeiro', strpos($alunos, 'Baixar histórico financeiro') !== false);
chk('Alunos Ficha 360 tem botão Baixar CSV', strpos($alunos, 'Baixar CSV') !== false);
chk('Alunos Ficha 360 tem nota de documento 360º', strpos($alunos, 'Documento 360º pronto') !== false);
chk('Alunos Ficha 360 recebe URL do backend antes do fallback', strpos($alunos, 'financeiro.historico_url') !== false && strpos($alunos, 'sigeAlunoFinanceHistoryUrl') !== false);
chk('Alunos Ficha 360 mantém extracto/caixa', strpos($alunos, 'Abrir extracto/caixa') !== false);
chk('Acção financeira escondida por permissão', strpos($alunos, 'if ($sige_alunos_can_finance_view)') !== false);
chk('Base URL global para histórico existe', strpos($alunos, 'sigeAlunosFinanceHistoryBaseUrl') !== false);
chk('Base URL global para extractos existe', strpos($alunos, 'sigeAlunosFinanceExtractsBaseUrl') !== false);
chk('Modal footer preserva botão histórico financeiro', strpos($alunos, 'baixarHistoricoFinanceiroAluno') !== false);

chk('Extractos/Caixa tem CTA PRO', strpos($extratos, 'sg-hist-pro-download') !== false);
chk('Extractos/Caixa permite Guardar PDF / Imprimir', strpos($extratos, 'Guardar PDF / Imprimir') !== false);
chk('Extractos/Caixa permite Baixar CSV', strpos($extratos, 'Baixar CSV') !== false);
chk('Extractos/Caixa usa builder oficial', strpos($extratos, 'sige_fin_hist_aluno_build_url') !== false);
chk('Extractos/Caixa CTA só em modo aluno', strpos($extratos, '$modo_aluno && !empty($aluno_dados)') !== false);

chk('documents-engine reconhece novo tipo', strpos($docs, "case 'historico_financeiro_aluno'") !== false);
chk('documents-engine fallback chama load', strpos($docs, 'sige_fin_hist_aluno_load') !== false);
chk('documents-engine fallback chama render HTML', strpos($docs, 'sige_fin_hist_aluno_render_html') !== false);
chk('documents-engine fallback chama CSV', strpos($docs, 'sige_fin_hist_aluno_output_csv') !== false);
chk('documents-engine inclui permissões financeiras granulares', strpos($docs, 'financeiro.extractos_ver') !== false && strpos($docs, 'financeiro.cobrancas_ver') !== false);
chk('security-scope-guard mapeia historico financeiro', strpos($scope, "'historico_financeiro_aluno'") !== false);

chk('Portaria clean room mantida', strpos($portaria, 'sige_portaria_camera') !== false || strpos($portaria, 'Ler próximo crachá') !== false);
chk('Portaria estado consistente mantido', strpos($portaria, 'BLOQUEADO') !== false && strpos($portaria, 'AUTORIZADO') !== false);

if ($fail) {
    echo "Smoke Histórico Financeiro Aluno PRO v12.11.9.81 falhou: " . count($fail) . " falha(s).\n";
    foreach ($fail as $f) echo "FAIL: {$f}\n";
    exit(1);
}
echo "Smoke Histórico Financeiro Aluno PRO v12.11.9.81 OK: " . count($checks) . "/" . count($checks) . " checks OK\n";
