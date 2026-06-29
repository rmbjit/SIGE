<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Smoke v12.12.2
 * Valida o extracto de divida detalhado para encarregados por inspecao estatica.
 */

$root = dirname(__DIR__);
$doc = (string)file_get_contents($root . '/includes/documents-engine.php');
$dev = (string)file_get_contents($root . '/admin/finance/financeiro-devedores-view.php');
$fails = [];
$oks = 0;
$check = static function (bool $cond, string $msg) use (&$fails, &$oks): void {
    if ($cond) { $oks++; echo "OK   {$msg}\n"; }
    else { $fails[] = $msg; echo "FALHOU  {$msg}\n"; }
};
$between = static function (string $src, string $from, string $to): string {
    $a = strpos($src, $from);
    if ($a === false) return '';
    $b = strpos($src, $to, $a + strlen($from));
    if ($b === false) return substr($src, $a);
    return substr($src, $a, $b - $a);
};

$factura = $between($doc, 'function sige_gerar_html_factura($aluno_id)', '// ==========================================' . "\n" . '// 3.E) MOTOR DE EXTRACTO');
$helper = $between($doc, 'function sige_doc_fin_lancamento_breakdown', 'function sige_gerar_html_factura($aluno_id)');

$check(strpos($doc, 'function sige_doc_fin_lancamento_breakdown') !== false, 'Helper documental de decomposicao existe');
$check(strpos($helper, 'sige_fin_total_lancamento') !== false, 'Helper usa total canonico quando disponivel');
$check(strpos($helper, 'sige_fin_saldo_lancamento') !== false, 'Helper usa saldo canonico quando disponivel');
$check(strpos($factura, "status IN ('pendente','parcial','em_plano')") !== false, 'Extracto inclui pendente, parcial e em_plano');
foreach (['valor_original','valor_transporte','valor_extras','valor_multa_cobrada','valor_desconto','valor_desconto_especial','valor_pago'] as $campo) {
    $check(strpos($factura . $helper, $campo) !== false, "Campo financeiro {$campo} considerado");
}
$check(strpos($factura, 'sige_fin_pagamentos') !== false && strpos($factura, 'GROUP BY lancamento_id') !== false, 'Pagamentos abatidos por lancamento consultados');
$check(strpos($factura, 'lancamento_id IN') !== false, 'Consulta de pagamentos limita lancamentos do aluno');
$check(strpos($factura, 'WHERE l.aluno_id=%d AND l.escola_id=%d') !== false, 'Query de dividas usa aluno_id e escola_id');
$check(strpos($factura, 'WHERE escola_id = %d') !== false && strpos($factura, 'AND aluno_id = %d') !== false, 'Query de pagamentos usa escola_id e aluno_id');
$check(strpos($factura, 'sige_fin_recalcular_lancamento') === false, 'Documento nao chama recalculador com side effects');
$check(strpos($factura, 'EXTRATO DE DÍVIDA DETALHADO') !== false, 'Titulo do documento detalhado presente');
$check(strpos($factura, 'Total em aberto') !== false && strpos($factura, 'Total vencido') !== false && strpos($factura, 'Já pago nos itens') !== false, 'Resumo geral da divida presente');
$check(strpos($factura, 'Base/Propina:') !== false && strpos($factura, 'Transporte:') !== false && strpos($factura, 'Extras:') !== false, 'Decomposicao visivel por item presente');
$check(strpos($factura, 'Multa:') !== false && strpos($factura, 'Desconto especial:') !== false && strpos($factura, 'Já pago') !== false, 'Multa, desconto especial e pago visiveis');
$check(strpos($factura, 'Recibos abatidos:') !== false && strpos($factura, 'Último pagamento:') !== false, 'Recibos e ultimo pagamento aparecem quando existirem');
$check(strpos($factura, 'Pagamentos feitos depois da emissão podem alterar o saldo') !== false, 'Nota de leitura financeira presente');
$check(strpos($dev, 'wp_nonce_url') !== false && strpos($dev, 'sige_print_factura_') !== false && strpos($dev, 'sige_print_extracto_') !== false, 'Links de impressao geram nonce documental');
$check(strpos($dev, 'Extracto dívida') !== false && strpos($dev, 'Histórico') !== false, 'Labels da Central de Devedores ficaram claros');
$check(strpos($dev, '>Factura</a>') === false, 'Label antigo Factura removido do fluxo de devedores');

if ($fails) {
    fwrite(STDERR, 'SMOKE EXTRACTO DETALHADO FALHOU: ' . count($fails) . " falha(s)\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}

echo str_repeat('-', 60) . "\n";
echo "SMOKE EXTRACTO DETALHADO OK - {$oks} verificacoes passaram.\n";
exit(0);
