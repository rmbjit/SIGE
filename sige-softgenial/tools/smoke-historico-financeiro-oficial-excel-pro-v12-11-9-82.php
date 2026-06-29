<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$checks = [];
function add_check(&$checks, $label, $ok, $detail = '') { $checks[] = [$label, (bool)$ok, $detail]; }
function f($root, $path) { return file_get_contents($root . '/' . $path); }
$main = f($root, 'sige-softgenial.php');
$build = f($root, 'BUILD.json');
$hist = f($root, 'includes/financeiro-historico-aluno-pro.php');
$alunos = f($root, 'admin/academic/alunos_lista.php');
$fetch = f($root, 'includes/aluno-fetch-ajax.php');
$extr = f($root, 'admin/finance/financeiro-extratos.php');
$docs = f($root, 'includes/documents-engine.php');

add_check($checks, 'Plugin header actualizado', strpos($main, 'Version: 12.11.9.82') !== false);
add_check($checks, 'SIGE_VERSION actualizado', strpos($main, "SIGE_VERSION', '12.11.9.82'") !== false);
add_check($checks, 'BUILD alinhado', strpos($build, '12.11.9.82') !== false && strpos($build, 'historico-financeiro-oficial-excel-pro') !== false);
add_check($checks, 'Ambiente explícito no HTML', strpos($hist, 'Ambiente Histórico Financeiro do Aluno') !== false);
add_check($checks, 'PDF administrativo oficial', strpos($hist, 'Documento administrativo oficial') !== false && strpos($hist, 'Guardar PDF / Imprimir') !== false);
add_check($checks, 'Excel .xlsx implementado', strpos($hist, 'sige_fin_hist_aluno_output_xlsx') !== false && strpos($hist, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') !== false);
add_check($checks, 'ZIP XLSX sem depender de ZipArchive', strpos($hist, 'sige_fin_hist_aluno_zip_binary') !== false && strpos($hist, '0x04034b50') !== false);
add_check($checks, 'Folhas Excel 360', strpos($hist, 'Resumo 360') !== false && strpos($hist, 'Pagamentos') !== false && strpos($hist, 'Obrigacoes') !== false && strpos($hist, 'Metodos 360') !== false);
add_check($checks, 'CSV legado roteado para Excel', strpos($hist, 'Compatibilidade com URLs antigas') !== false && strpos($hist, "['xlsx','excel','csv']") !== false);
add_check($checks, 'Alunos mostra ambiente histórico', strpos($alunos, 'Abrir ambiente Histórico Financeiro do aluno') !== false && strpos($alunos, 'Histórico financeiro') !== false);
add_check($checks, 'Ficha 360 tem Excel', strpos($alunos, 'historico_xlsx_url') !== false && strpos($alunos, 'Baixar Excel') !== false && strpos($alunos, 'sigeAlunoFinanceHistoryExcelUrl') !== false);
add_check($checks, 'Ficha 360 remove CSV da UI', strpos($alunos, 'Baixar CSV') === false);
add_check($checks, 'AJAX fornece XLSX', strpos($fetch, 'historico_xlsx_url') !== false && strpos($fetch, 'sige_fin_hist_aluno_build_url((int)') !== false && strpos($fetch, "'xlsx'") !== false);
add_check($checks, 'Extractos/Caixa usa Excel', strpos($extr, 'sg_hist_pro_xlsx_url') !== false && strpos($extr, 'Baixar Excel') !== false && strpos($extr, 'Histórico financeiro oficial do aluno') !== false);
add_check($checks, 'Extractos/Caixa remove CSV da UI', strpos($extr, 'Baixar CSV') === false && strpos($extr, 'sg_hist_pro_csv_url') === false);
add_check($checks, 'Documents engine reconhece XLSX', strpos($docs, 'sige_fin_hist_aluno_output_xlsx') !== false && strpos($docs, "['xlsx','excel','csv']") !== false);
add_check($checks, 'Assinaturas oficiais', strpos($hist, 'Tesouraria') !== false && strpos($hist, 'Secretaria') !== false && strpos($hist, 'Direcção / Carimbo') !== false);
add_check($checks, 'Permissão Guarda preservada', strpos($hist, 'sige_guarda') !== false && strpos($hist, "slug === 'guarda'") !== false);
add_check($checks, 'Cálculo canónico preservado', strpos($hist, 'sige_fin_saldo_sql') !== false && strpos($hist, 'sige_fin_total_bruto_sql') !== false);
add_check($checks, 'Não altera base de dados', strpos($hist, 'CREATE TABLE') === false && strpos($hist, 'ALTER TABLE') === false && strpos($hist, 'DROP TABLE') === false);

$fail = 0;
foreach ($checks as [$label, $ok, $detail]) {
    echo ($ok ? 'OK  ' : 'FAIL') . ' - ' . $label . ($detail ? ' :: ' . $detail : '') . PHP_EOL;
    if (!$ok) $fail++;
}
echo PHP_EOL . 'TOTAL: ' . (count($checks)-$fail) . '/' . count($checks) . ' checks OK' . PHP_EOL;
exit($fail ? 1 : 0);
