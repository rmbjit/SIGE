<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
/**
 * Smoke v12.12.64 - Financeiro: histórico de classe preservado.
 *
 * Objectivo: impedir regressão onde meses pagos com serviço da classe anterior
 * voltam a aparecer como dívida após mudança de classe/turma.
 */
$root = dirname(__DIR__);
$core = file_get_contents($root . '/includes/finance-core.php');
$pag  = file_get_contents($root . '/admin/finance/financeiro-pagamentos.php');
$errors = [];
$assert = function(bool $cond, string $msg) use (&$errors) {
    if (!$cond) $errors[] = $msg;
};

$assert(strpos($core, 'function sige_fin_tipo_servico_canonico') !== false, 'core sem normalização canónica de tipo de serviço');
$assert(strpos($core, "return ['mensalidade', 'mensal'];") !== false, 'core não trata mensalidade/mensal como equivalentes');
$assert(strpos($core, 'sige_fin_lancamento_recorrente_existente_por_tipo_mes') !== false, 'core sem lookup histórico por aluno+mês+tipo');
$assert(strpos($core, 'Idempotência financeira antes da elegibilidade de classe') !== false, 'upsert não documenta/aplica idempotência antes da validação de classe');
$assert(strpos($core, "['pago', 'isento', 'em_plano']") !== false, 'upsert não protege lançamentos históricos pagos/isentos/em_plano');
$assert(strpos($core, "LOWER(TRIM(COALESCE(s.tipo") !== false, 'duplicata recorrente não usa tipo normalizado');
$assert(strpos($core, 'IN ({$tipo_placeholders})') !== false, 'duplicata recorrente não usa tipos equivalentes em SQL');

$assert(strpos($pag, '$mapa_tipo_stats') !== false, 'pagamentos sem mapa por tipo canónico');
$assert(strpos($pag, 'servico_tipo') !== false, 'pagamentos não carrega tipo do serviço no mapa de existentes');
$assert(strpos($pag, '$tem_mensalidade_no_mes') !== false, 'pagamentos não verifica mensalidade histórica por tipo');
$assert(strpos($pag, '$mapa_tipo_saldo') !== false, 'pagamentos não usa saldo real por tipo histórico');
$assert(strpos($pag, 'servico_id actual') !== false, 'pagamentos sem comentário de prevenção da regressão por servico_id actual');
$assert(strpos($pag, 'isset($mapa_status[$m][(int)$mensalidade_srv->id])') !== false, 'fallback por servico_id actual removido indevidamente');
$assert(strpos($pag, '!empty($mapa_tipo_stats[$m][\'mensalidade\'][\'count\'])') !== false, 'mensalidade histórica não é reconhecida por tipo');

if ($errors) {
    fwrite(STDERR, "FALHOU smoke-financeiro-historico-classe-v12-12-64:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "OK smoke-financeiro-historico-classe-v12-12-64\n";
