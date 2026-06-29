<?php
/**
 * SIGE SoftGenial - Planos de Pagamento Negociados
 * Ficheiro: admin/financeiro-planos-view.php
 * Rota: ?page=sige-app&view=financeiro-planos
 *
 * Permite ao director/tesoureiro criar acordos de pagamento faseado
 * com os encarregados que não conseguem pagar a dívida de uma vez.
 * Cada plano tem N prestações com datas de vencimento definidas.
 * O sistema gera lançamentos para cada prestação e controla o estado.
 */

if (!defined('ABSPATH')) exit;

// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['financeiro.planos_ver','financeiro.planos_gerir'],
    ['sige_director','sige_financeiro','sige_secretario']
)) return;

global $wpdb;
$p = $wpdb->prefix;
$escola_id = sige_require_escola_id('planos');

$tPl = $p . 'sige_fin_planos_pagamento';
$tPr = $p . 'sige_fin_planos_prestacoes';
$tL = $p . 'sige_fin_lancamentos';
$tA = $p . 'sige_alunos';
$tS = $p . 'sige_fin_servicos';
$tP = $p . 'sige_fin_pagamentos';

// ── Verificar se tabelas existem (migração pode ainda não ter corrido) ─────
$tabelas_ok = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tPl)) === $tPl)
 && ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tPr)) === $tPr);

$ano_letivo = function_exists('sige_fin_get_ano_letivo_master') ? sige_fin_get_ano_letivo_master() : (int)wp_date('Y');
$hoje = current_time('Y-m-d');

// ══════════════════════════════════════════════════════════════════════════════
// POST: CRIAR PLANO
// ══════════════════════════════════════════════════════════════════════════════
if ($tabelas_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_criar_plano_submit'])) {

 // [AUTH-06] Capability check inline (defesa em profundidade)
 if (!((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director') || current_user_can('sige_financeiro') || current_user_can('sige_secretario'))) {
 echo '<div class="notice notice-error" style="border-radius:8px;padding:12px;"><p>Sem permissao para esta operacao.</p></div>';
 } else if (!wp_verify_nonce($_POST['_wpnonce_plano'] ?? '', 'sige_criar_plano')) {
 echo '<div class="notice notice-error" style="border-radius:8px;padding:12px;"><p> Pedido inválido.</p></div>';
 } else {
 $pl_aluno_id = sige_fin_post_int('pl_aluno_id');
 $pl_descricao = sige_fin_post_param('pl_descricao');
 $pl_valor = sige_fin_post_float('pl_valor_total');
 $pl_n = max(1, min(24, sige_fin_post_int('pl_n_prestacoes', 1)));
 $pl_notas = sanitize_textarea_field($_POST['pl_notas'] ?? '');
 $pl_data_ini = sige_fin_post_param('pl_data_inicio') ?: $hoje;

 // Lançamentos seleccionados para o plano
 $pl_lancamentos = isset($_POST['pl_lancamentos']) && is_array($_POST['pl_lancamentos'])
 ? array_map('intval', $_POST['pl_lancamentos']) : [];

 if (!$pl_aluno_id || $pl_valor <= 0 || !$pl_descricao) {
 echo '<div class="notice notice-warning" style="border-radius:8px;padding:12px;"><p>️ Preencha aluno, valor e descrição.</p></div>';
 } elseif (empty($pl_lancamentos)) {
 echo '<div class="notice notice-warning" style="border-radius:8px;padding:12px;"><p>️ Seleccione os lançamentos que este plano cobre.</p></div>';
 } else {
 // Validar que os lançamentos pertencem ao aluno e à escola
 $in_ids = implode(',', $pl_lancamentos);
 $lancs_validos = $wpdb->get_results($wpdb->prepare(
 "SELECT id, valor_original, valor_multa, valor_desconto,
 COALESCE(valor_desconto_especial,0) AS vde,
 valor_pago
 FROM $tL
 WHERE id IN ($in_ids) AND aluno_id=%d AND escola_id=%d
 AND status IN ('pendente','parcial')",
 $pl_aluno_id, $escola_id
 ));

 if (count($lancs_validos) !== count($pl_lancamentos)) {
 echo '<div class="notice notice-error" style="border-radius:8px;padding:12px;"><p> Um ou mais lançamentos inválidos ou já processados.</p></div>';
 } else {
 // Calcular prestações
 $valor_prest = round($pl_valor / $pl_n, 2);
 $valor_ultima = round($pl_valor - ($valor_prest * ($pl_n - 1)), 2);

 $wpdb->query('START TRANSACTION');

 // Inserir plano
 $ok_pl = $wpdb->insert($tPl, [
 'escola_id' => $escola_id,
 'aluno_id' => $pl_aluno_id,
 'criado_por' => get_current_user_id(),
 'valor_total' => $pl_valor,
 'n_prestacoes' => $pl_n,
 'descricao' => $pl_descricao,
 'notas' => $pl_notas ?: null,
 'status' => 'activo',
 'criado_em' => current_time('mysql'),
 ]);

 if (!$ok_pl) {
 $wpdb->query('ROLLBACK');
 echo '<div class="notice notice-error" style="border-radius:8px;padding:12px;"><p> Erro ao criar plano: ' . esc_html($wpdb->last_error) . '</p></div>';
 } else {
 $plano_id = (int)$wpdb->insert_id;
 $erros_pr = [];

 // Inserir prestações
 $data_venc = $pl_data_ini;
 for ($i = 1; $i <= $pl_n; $i++) {
 $val_i = ($i === $pl_n) ? $valor_ultima : $valor_prest;
 $ok_pr = $wpdb->insert($tPr, [
 'escola_id' => $escola_id,
 'plano_id' => $plano_id,
 'numero' => $i,
 'valor' => $val_i,
 'data_vencimento' => $data_venc,
 'status' => 'pendente',
 ]);
 if (!$ok_pr) $erros_pr[] = "Prestação $i: " . $wpdb->last_error;

 // Avançar 1 mês para a próxima prestação
 $data_venc = wp_date('Y-m-d', strtotime('+1 month', strtotime($data_venc)));
 }

 if (!empty($erros_pr)) {
 $wpdb->query('ROLLBACK');
 echo '<div class="notice notice-error" style="border-radius:8px;padding:12px;"><p> Erros nas prestações:<br>' . implode('<br>', array_map('esc_html', $erros_pr)) . '</p></div>';
 } else {
 // CONGELAR os lançamentos seleccionados (em_plano)
 // Estes lançamentos saem da vista normal de dívidas
 $wpdb->query($wpdb->prepare(
 "UPDATE $tL SET status='em_plano', plano_id=%d WHERE id IN ($in_ids) AND aluno_id=%d AND escola_id=%d",
 $plano_id, $pl_aluno_id, $escola_id
 ));

 $wpdb->query('COMMIT');
 if (function_exists('sige_audit_log')) {
 sige_audit_log('criar_plano_pagamento', [
 'plano_id' => $plano_id,
 'aluno_id' => $pl_aluno_id,
 'valor' => $pl_valor,
 'prestacoes' => $pl_n,
 'lancamentos' => $pl_lancamentos,
 ], 'financeiro');
 }
 echo '<div class="notice notice-success is-dismissible" style="border-radius:8px;padding:12px;">
 <p>✓ Plano criado com ' . $pl_n . ' prestação' . ($pl_n > 1 ? 'ões' : '') . ' de ' . number_format($valor_prest, 2, ',', '.') . ' MT cada.</p>
 <p style="font-size:0.85rem;color:#475569;">' . count($lancs_validos) . ' lançamento' . (count($lancs_validos) > 1 ? 's' : '') . ' congelados - visíveis em Planos de Pagamento.</p>
 </div>';
 }
 } // fecha if (!$ok_pl) else
 } // fecha if (count mismatch) else
 } // fecha condições principais
} // fecha nonce else
} // fecha if ($tabelas_ok && ... sige_criar_plano_submit)

// ══════════════════════════════════════════════════════════════════════════════
// POST: REGISTAR PAGAMENTO DE PRESTAÇÃO
// ══════════════════════════════════════════════════════════════════════════════
if ($tabelas_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_pagar_prestacao_submit'])) {

 // [AUTH-06] Capability check inline (defesa em profundidade)
 if (!((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director') || current_user_can('sige_financeiro') || current_user_can('sige_secretario'))) {
 echo '<div class="notice notice-error" style="border-radius:8px;padding:12px;"><p>Sem permissao para esta operacao.</p></div>';
 } else if (!wp_verify_nonce($_POST['_wpnonce_prest'] ?? '', 'sige_pagar_prestacao')) {
 echo '<div class="notice notice-error" style="border-radius:8px;padding:12px;"><p> Pedido inválido.</p></div>';
 } else {
 $pr_id = sige_fin_post_int('prestacao_id');
 $pr_metodo = sige_fin_post_param('pr_metodo', 'numerario');
 $pr_ref = sige_fin_post_param('pr_referencia');

 $prestacao = $pr_id ? $wpdb->get_row($wpdb->prepare(
 "SELECT pr.*, pl.aluno_id, pl.descricao AS plano_desc
 FROM $tPr pr JOIN $tPl pl ON pl.id = pr.plano_id
 WHERE pr.id = %d AND pr.escola_id = %d AND pr.status = 'pendente'",
 $pr_id, $escola_id
 )) : null;

 if (!$prestacao) {
 echo '<div class="notice notice-warning" style="border-radius:8px;padding:12px;"><p>️ Prestação não encontrada ou já paga.</p></div>';
 } else {
 // ── Pagamento FIFO sobre os lançamentos reais do plano ────────────────
 // Em vez de criar um novo lançamento (abordagem anterior, desintegrada),
 // distribuímos o valor da prestação pelos lançamentos em_plano do aluno,
 // da mais antiga para a mais recente - exactamente como o encarregado
 // negociou com a escola.
 $recibo = function_exists('sige_fin_gerar_recibo_numero')
 ? sige_fin_gerar_recibo_numero('PLN')
 : 'PLN-' . wp_date('Ymd-His');

 // Buscar lançamentos em_plano deste plano, FIFO
 $lancs_em_plano = $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM $tL
 WHERE plano_id = %d AND aluno_id = %d AND escola_id = %d AND status = 'em_plano'
 ORDER BY data_vencimento ASC",
 (int)$prestacao->plano_id, (int)$prestacao->aluno_id, $escola_id
 ));

 $saldo_prestacao = (float)$prestacao->valor;
 $pagos_ids = [];
 $erros_pag = [];

 foreach ($lancs_em_plano as $lp) {
 if ($saldo_prestacao <= 0.005) break;

 $total_lp = max(0.0, (float)$lp->valor_original
 + (float)($lp->valor_multa ?? 0)
 - (float)($lp->valor_desconto ?? 0)
 - (float)($lp->valor_desconto_especial ?? 0));
 $falta_lp = max(0.0, $total_lp - (float)($lp->valor_pago ?? 0));
 if ($falta_lp <= 0) continue;

 $aplicar = min($saldo_prestacao, $falta_lp);
 $res_pag = function_exists('sige_fin_registar_pagamento')
 ? sige_fin_registar_pagamento((int)$lp->id, $aplicar, $pr_metodo, $pr_ref ?: null, false, null, $recibo)
 : false;

 if ($res_pag && !is_wp_error($res_pag)) {
 $saldo_prestacao -= $aplicar;
 $pagos_ids[] = $lp->id;
 } else {
 $erros_pag[] = is_wp_error($res_pag) ? $res_pag->get_error_message() : 'Erro no lancamento #' . $lp->id;
 break;
 }
 }

 if (!empty($pagos_ids) && empty($erros_pag)) {
 // Marcar prestação como paga
 $wpdb->update($tPr, [
 'status' => 'pago',
 'pago_em' => current_time('mysql'),
 'notas' => 'Recibo: ' . $recibo,
 ], ['id' => $pr_id]);

 // Verificar se plano está cumprido (todas as prestações pagas)
 $pr_pendentes = (int)$wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM $tPr WHERE plano_id=%d AND status IN ('pendente','atrasado')",
 $prestacao->plano_id
 ));
 // Verificar se ficaram lançamentos em_plano (prestação cobriu só parte)
 $lancs_restantes = (int)$wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM $tL WHERE plano_id=%d AND status='em_plano'",
 (int)$prestacao->plano_id
 ));

 if ($pr_pendentes === 0 && $lancs_restantes === 0) {
 $wpdb->update($tPl, ['status' => 'cumprido'], ['id' => (int)$prestacao->plano_id]);
 }

 // Marcar prestações vencidas como atrasado
 $wpdb->query($wpdb->prepare(
 "UPDATE $tPr SET status='atrasado' WHERE plano_id=%d AND status='pendente' AND data_vencimento < %s",
 $prestacao->plano_id, $hoje
 ));

 echo '<div class="notice notice-success is-dismissible" style="border-radius:8px;padding:12px;">
 <p>✓ Prestação ' . (int)$prestacao->numero . ' paga - Recibo <strong>' . esc_html($recibo) . '</strong></p>
 <p style="font-size:0.85rem;color:#475569;">' . count($pagos_ids) . ' lançamento' . (count($pagos_ids)>1?'s':'') . ' actualizados.</p>
 </div>';
 } else {
 $msg = !empty($erros_pag) ? implode(' | ', $erros_pag) : 'Nenhum lançamento disponível neste plano.';
 echo '<div class="notice notice-error" style="border-radius:8px;padding:12px;"><p> ' . esc_html($msg) . '</p></div>';
 }
 }
 }
}

// ══════════════════════════════════════════════════════════════════════════════
// POST: CANCELAR PLANO
// ══════════════════════════════════════════════════════════════════════════════
if ($tabelas_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_cancelar_plano_submit'])) {
 if (!wp_verify_nonce($_POST['_wpnonce_cancel_pl'] ?? '', 'sige_cancelar_plano')) {
 echo '<div class="notice notice-error" style="border-radius:8px;padding:12px;"><p> Pedido inválido.</p></div>';
 } elseif (!((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director'))) {
 echo '<div class="notice notice-error" style="border-radius:8px;padding:12px;"><p> Apenas o Director pode cancelar planos.</p></div>';
 } else {
 $cancel_id = sige_fin_post_int('plano_id_cancel');
 if ($cancel_id > 0) {
 // Reverter lançamentos em_plano → pendente (descongelar dívida)
 $wpdb->query($wpdb->prepare(
 "UPDATE $tL SET status='pendente', plano_id=NULL WHERE plano_id=%d AND escola_id=%d AND status='em_plano'",
 $cancel_id, $escola_id
 ));
 $wpdb->update($tPl, ['status' => 'cancelado'], ['id' => $cancel_id, 'escola_id' => $escola_id]);
 $wpdb->query($wpdb->prepare("UPDATE $tPr SET status='cancelado' WHERE plano_id=%d AND status IN ('pendente','atrasado')", $cancel_id));
 if (function_exists('sige_audit_log')) sige_audit_log('cancelar_plano', [
 'plano_id' => $cancel_id, 'lancamentos_revertidos' => true
 ], 'financeiro');
 echo '<div class="notice notice-success" style="border-radius:8px;padding:12px;"><p>✓ Plano cancelado. Lançamentos revertidos para pendente.</p></div>';
 }
 }
}

// ══════════════════════════════════════════════════════════════════════════════
// POST: EDITAR PLANO (notas/descrição)
// ══════════════════════════════════════════════════════════════════════════════
if ($tabelas_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_editar_plano_submit'])) {
 if (!wp_verify_nonce($_POST['_wpnonce_edit_pl'] ?? '', 'sige_editar_plano')) {
 echo '<div class="notice notice-error" style="border-radius:8px;padding:12px;"><p>Pedido inválido.</p></div>';
 } else {
 $edit_id = sige_fin_post_int('edit_plano_id');
 $edit_desc = sige_fin_post_param('edit_descricao');
 $edit_notas = sanitize_textarea_field(wp_unslash($_POST['edit_notas'] ?? ''));
 if ($edit_id > 0) {
 $wpdb->update($tPl, [
 'descricao' => $edit_desc,
 'notas' => $edit_notas,
 ], ['id' => $edit_id, 'escola_id' => $escola_id]);
 sige_fin_log('editar_plano', ['plano_id' => $edit_id, 'descricao' => $edit_desc]);
 echo '<div class="notice notice-success" style="border-radius:8px;padding:12px;"><p>Plano #' . $edit_id . ' actualizado.</p></div>';
 }
 }
}

// ══════════════════════════════════════════════════════════════════════════════
// GET: Carregar dados
// ══════════════════════════════════════════════════════════════════════════════
$filtro_status = sige_fin_get_param('st', 'activo');
$filtro_aluno = sige_fin_get_int('aluno_id');
$aluno_busca = sige_fin_get_param('q');
// [v13.4.0 BLOCO 3] Filtro universal por centro - aplicado via JOIN em sige_alunos
// (tabela planos_pagamento não tem coluna centro_id; o centro do plano é o centro
// do aluno, por design, já que um plano é sempre de um aluno específico).
$centro_id_filtro = function_exists('sige_fin_centro_ativo') ? sige_fin_centro_ativo() : 0;

// Planos com prestações e dados do aluno
$planos = [];
$prestacoes_map = []; // plano_id → array de prestações

if ($tabelas_ok) {
 // Auto-marcar atrasados (prestações vencidas ainda pendentes)
 $wpdb->query($wpdb->prepare(
 "UPDATE $tPr pr
 JOIN $tPl pl ON pl.id = pr.plano_id
 SET pr.status = 'atrasado'
 WHERE pr.status = 'pendente'
 AND pr.data_vencimento < %s
 AND pl.escola_id = %s",
 $hoje, $escola_id
 ));
 // Marcar planos como 'quebrado' se têm prestações atrasadas
 $wpdb->query($wpdb->prepare(
 "UPDATE $tPl pl SET pl.status = 'quebrado'
 WHERE pl.escola_id = %d AND pl.status = 'activo'
 AND EXISTS (
 SELECT 1 FROM $tPr pr
 WHERE pr.plano_id = pl.id AND pr.status = 'atrasado'
 )",
 $escola_id
 ));

 $where_pl = "pl.escola_id = %d";
 $params_pl = [$escola_id];
 if (in_array($filtro_status, ['activo','cumprido','quebrado','cancelado'], true)) {
 $where_pl .= " AND pl.status = %s";
 $params_pl[] = $filtro_status;
 }
 if ($filtro_aluno > 0) {
 $where_pl .= " AND pl.aluno_id = %d";
 $params_pl[] = $filtro_aluno;
 }
 // [v13.4.0] Filtro por centro via coluna a.centro_id (JOIN já existe)
 if ($centro_id_filtro > 0) {
 $where_pl .= " AND a.centro_id = %d";
 $params_pl[] = $centro_id_filtro;
 }

 $planos = $wpdb->get_results($wpdb->prepare(
 "SELECT pl.*,
 a.nome_completo, a.numero_processo,
 u.display_name AS criado_por_nome,
 (SELECT COUNT(*) FROM $tPr pr WHERE pr.plano_id=pl.id AND pr.status='pendente') AS n_pendentes,
 (SELECT COUNT(*) FROM $tPr pr WHERE pr.plano_id=pl.id AND pr.status='pago') AS n_pagos,
 (SELECT COUNT(*) FROM $tPr pr WHERE pr.plano_id=pl.id AND pr.status='atrasado') AS n_atrasados,
 (SELECT COALESCE(SUM(pr.valor),0) FROM $tPr pr WHERE pr.plano_id=pl.id AND pr.status='pendente') AS valor_pendente,
 (SELECT MIN(pr.data_vencimento) FROM $tPr pr WHERE pr.plano_id=pl.id AND pr.status='pendente') AS prox_vencimento
 FROM $tPl pl
 JOIN $tA a ON a.id = pl.aluno_id
 LEFT JOIN {$wpdb->users} u ON u.ID = pl.criado_por
 WHERE $where_pl
 ORDER BY pl.criado_em DESC
 LIMIT 100",
 ...$params_pl
 ));

 // Carregar todas as prestações dos planos carregados
 if (!empty($planos)) {
 $pids = implode(',', array_map(fn($pl) => (int)$pl->id, $planos));
 $prestacoes_all = $wpdb->get_results(
 "SELECT * FROM $tPr WHERE plano_id IN ($pids) ORDER BY plano_id ASC, numero ASC"
 );
 foreach ($prestacoes_all as $pr) {
 $prestacoes_map[(int)$pr->plano_id][] = $pr;
 }
 }

 // Resumo global para os cards KPI
 // [v13.4.0] Quando filtro de centro está activo, KPI respeita via JOIN em alunos
 if ($centro_id_filtro > 0) {
     $kpi = $wpdb->get_row($wpdb->prepare(
     "SELECT
     COUNT(CASE WHEN pl.status='activo' THEN 1 END) AS n_activos,
     COUNT(CASE WHEN pl.status='cumprido' THEN 1 END) AS n_cumpridos,
     COUNT(CASE WHEN pl.status='quebrado' THEN 1 END) AS n_quebrados,
     COUNT(CASE WHEN pl.status='cancelado' THEN 1 END) AS n_cancelados,
     COALESCE(SUM(CASE WHEN pl.status IN ('activo','quebrado')
     THEN pl.valor_total END), 0) AS valor_em_curso,
     COALESCE(SUM(CASE WHEN pl.status='cumprido'
     THEN pl.valor_total END), 0) AS valor_cumprido
     FROM $tPl pl
     JOIN $tA a ON a.id = pl.aluno_id
     WHERE pl.escola_id=%d AND a.centro_id = %d",
     $escola_id, $centro_id_filtro
     ));
 } else {
     $kpi = $wpdb->get_row($wpdb->prepare(
     "SELECT
     COUNT(CASE WHEN status='activo' THEN 1 END) AS n_activos,
     COUNT(CASE WHEN status='cumprido' THEN 1 END) AS n_cumpridos,
     COUNT(CASE WHEN status='quebrado' THEN 1 END) AS n_quebrados,
     COUNT(CASE WHEN status='cancelado' THEN 1 END) AS n_cancelados,
     COALESCE(SUM(CASE WHEN status IN ('activo','quebrado')
     THEN valor_total END), 0) AS valor_em_curso,
     COALESCE(SUM(CASE WHEN status='cumprido'
     THEN valor_total END), 0) AS valor_cumprido
     FROM $tPl WHERE escola_id=%d",
     $escola_id
     ));
 }
}

// Pesquisa de aluno para o formulário de novo plano
$alunos_disponiveis = $wpdb->get_results($wpdb->prepare(
 "SELECT id, nome_completo, numero_processo FROM $tA
 WHERE escola_id=%d AND status='activo'
 ORDER BY nome_completo ASC LIMIT 300",
 $escola_id
));

// Labels e cores por status
$status_cfg = [
 'activo' => ['label' => 'Activo', 'bg' => '#dcfce7', 'txt' => '#166534', 'icon' => '●'],
 'cumprido' => ['label' => 'Cumprido', 'bg' => '#dbeafe', 'txt' => '#1e40af', 'icon' => '✓'],
 'quebrado' => ['label' => 'Quebrado', 'bg' => '#fee2e2', 'txt' => '#b91c1c', 'icon' => '️'],
 'cancelado' => ['label' => 'Cancelado', 'bg' => '#f1f5f9', 'txt' => '#64748b', 'icon' => ''],
 'pendente' => ['label' => 'Pendente', 'bg' => '#fef9c3', 'txt' => '#854d0e', 'icon' => '⏳'],
 'pago' => ['label' => 'Pago', 'bg' => '#dcfce7', 'txt' => '#166534', 'icon' => '✓'],
 'atrasado' => ['label' => 'Atrasado', 'bg' => '#fee2e2', 'txt' => '#b91c1c', 'icon' => '🔴'],
];
?>


<div class="pl-wrap sg-plans-wrap">

 <section class="sg-finpro-hero sg-plans-hero" aria-label="Planos de Pagamento">
  <div class="sg-finpro-hero-copy">
   <div class="sg-finpro-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('clipboard') : ''; ?> Tesouraria</div>
   <h1>Planos de Pagamento</h1>
   <p>Organize acordos de pagamento faseado, acompanhe prestações, vencimentos e cumprimento dos compromissos assumidos com os encarregados.</p>
   <div class="sg-finpro-hero-actions">
    <button type="button" data-sige-act="plToggleNovoPlano" data-sige-noargs class="sg-finpro-btn sg-finpro-btn-primary">
     <?php echo function_exists('sige_ui_icon') ? sige_ui_icon('plus') : ''; ?>
     Novo Plano
    </button>
    <a class="sg-finpro-btn sg-finpro-btn-light" href="<?php echo esc_url(add_query_arg(['page'=>'sige-app','view'=>'financeiro-lancamentos'], admin_url('admin.php'))); ?>">
     <?php echo function_exists('sige_ui_icon') ? sige_ui_icon('pin') : ''; ?>
     Ver Lançamentos
    </a>
   </div>
  </div>
  <div class="sg-finpro-hero-panel">
   <div class="sg-finpro-mini-label">Valor em curso</div>
   <strong><?php echo number_format((float)($kpi->valor_em_curso ?? 0), 0, ',', '.'); ?> MT</strong>
   <span><?php echo (int)($kpi->n_activos ?? 0); ?> plano<?php echo (int)($kpi->n_activos ?? 0) === 1 ? '' : 's'; ?> activo<?php echo (int)($kpi->n_activos ?? 0) === 1 ? '' : 's'; ?></span>
   <div class="sg-finpro-progress"><i style="width:<?php echo max(2, min(100, (int)($kpi->n_activos ?? 0) * 10)); ?>%"></i></div>
   <small>Os planos ajudam a controlar dívidas negociadas sem alterar as regras financeiras da escola.</small>
  </div>
 </section>

<?php if (!$tabelas_ok): ?>
 <div class="pl-card">
 <div class="pl-card-b" style="text-align:center;padding:40px;">
 <div style="font-size:2rem;margin-bottom:12px;">⚙️</div>
 <h3 style="color:#7c3aed;margin:0 0 8px;">Área ainda não preparada</h3>
 <p style="color:#64748b;font-size:0.9rem;">Esta área precisa de preparação inicial antes de ser usada pela escola.</p>
 <p style="color:#64748b;font-size:0.9rem;">Peça ao responsável técnico da SoftGenial para concluir a activação desta funcionalidade.</p>
 </div>
 </div>
<?php else: ?>

 <!-- Indicadores -->
 <?php if ($kpi): ?>
 <div class="sg-finpro-kpi-grid sg-plans-kpis">
  <div class="sg-finpro-kpi sg-finpro-tone-green">
   <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('check') : ''; ?></div>
   <div><span>Planos activos</span><strong><?php echo (int)($kpi->n_activos ?? 0); ?></strong><small>Acordos em acompanhamento</small></div>
  </div>
  <div class="sg-finpro-kpi sg-finpro-tone-coral">
   <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('trending') : ''; ?></div>
   <div><span>Planos em atraso</span><strong><?php echo (int)($kpi->n_quebrados ?? 0); ?></strong><small>Precisam de atenção</small></div>
  </div>
  <div class="sg-finpro-kpi sg-finpro-tone-blue">
   <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('award') : ''; ?></div>
   <div><span>Planos cumpridos</span><strong><?php echo (int)($kpi->n_cumpridos ?? 0); ?></strong><small>Acordos concluídos</small></div>
  </div>
  <div class="sg-finpro-kpi sg-finpro-tone-amber">
   <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('money') : ''; ?></div>
   <div><span>Valor cumprido</span><strong><?php echo number_format((float)($kpi->valor_cumprido ?? 0), 0, ',', '.'); ?> MT</strong><small>Total já regularizado</small></div>
  </div>
 </div>
 <?php endif; ?>

 <!-- Formulário Novo Plano -->
 <div id="pl-form-novo" class="pl-card sg-plans-form-card" style="display:none;">
 <div class="pl-card-h sg-plans-card-head">
 <h3>Novo Plano de Pagamento</h3>
 <button type="button" data-sige-act="plToggleNovoPlano" data-sige-args='[false]' class="sg-plans-close" aria-label="Fechar">&times;</button>
 </div>
 <div class="pl-card-b">
 <form method="post" class="pl-create-form">
 <?php wp_nonce_field('sige_criar_plano','_wpnonce_plano'); ?>
 <input type="hidden" name="sige_criar_plano_submit" value="1">

 <div class="pl-form-row">
 <div class="pl-form-col" style="flex:2;">
 <label class="pl-label">Aluno *</label>
 <select name="pl_aluno_id" class="pl-input" required
 onchange="plCarregarLancamentos(this.value)">
 <option value="">- Seleccione o aluno -</option>
 <?php foreach ($alunos_disponiveis as $al): ?>
 <option value="<?php echo (int)$al->id; ?>">
 <?php echo esc_html($al->nome_completo . ' (Proc: ' . ($al->numero_processo ?? '-') . ')'); ?>
 </option>
 <?php endforeach; ?>
 </select>
 </div>
 </div>

 <div class="pl-form-row">
 <div class="pl-form-col" style="flex:3;">
 <label class="pl-label">Descrição do Acordo *</label>
 <input type="text" name="pl_descricao" class="pl-input" required
 placeholder="Ex: Acordo Maio 2026 - dívida de Jan-Abr">
 </div>
 </div>

 <!-- Lançamentos pendentes (carregados via AJAX ao seleccionar aluno) -->
 <div id="pl-lancs-wrap" style="display:none; margin-bottom:14px;">
 <label class="pl-label">Lançamentos que este Plano Cobre *</label>
 <div id="pl-lancs-lista" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; max-height:250px; overflow-y:auto;"></div>
 <div id="pl-lancs-total" style="margin-top:8px; font-size:0.85rem; color:#7c3aed; font-weight:700;"></div>
 <div style="margin-top:6px; display:flex; gap:8px;">
 <button type="button" data-sige-act="plSelTodosLancs" data-sige-args='[true]'
 class="sgk-btn sgk-btn-sec sgk-btn-sm" style="border-color:#7c3aed; color:#7c3aed;">
 ☑ Seleccionar Tudo
 </button>
 <button type="button" data-sige-act="plSelTodosLancs" data-sige-args='[false]'
 class="sgk-btn sgk-btn-sec sgk-btn-sm">
 ☐ Limpar
 </button>
 <span style="font-size:0.75rem; color:#94a3b8; align-self:center;">
 Seleccione os lançamentos que o encarregado quer regularizar com este acordo.
 </span>
 </div>
 </div>

 <div class="pl-form-row">
 <div class="pl-form-col">
 <label class="pl-label">Valor Total Acordado (MT) *</label>
 <input type="number" name="pl_valor_total" class="pl-input" required
 step="0.01" min="1" placeholder="Ex: 30000" id="pl-valor-total"
 oninput="plPreview()">
 </div>
 <div class="pl-form-col">
 <label class="pl-label">Nº de Prestações (1-24)</label>
 <input type="number" name="pl_n_prestacoes" class="pl-input"
 value="3" min="1" max="24" id="pl-n-prest" oninput="plPreview()">
 </div>
 <div class="pl-form-col">
 <label class="pl-label">1ª Prestação - Data de Vencimento</label>
 <input type="date" name="pl_data_inicio" class="pl-input"
 value="<?php echo esc_attr(wp_date('Y-m-d', strtotime('+1 month'))); ?>">
 </div>
 </div>

 <!-- Preview automático das prestações -->
 <div id="pl-preview" style="display:none; background:#f5f3ff; border:1px solid #ddd6fe; border-radius:10px; padding:14px; margin-bottom:14px;">
 <div style="font-size:0.78rem;font-weight:700;color:var(--pl-purple);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">Preview das Prestações</div>
 <div id="pl-preview-txt" style="font-size:0.88rem;color:#4c1d95;font-weight:600;"></div>
 </div>

 <div class="pl-form-row">
 <div class="pl-form-col" style="flex:3;">
 <label class="pl-label">Notas do Acordo (opcional)</label>
 <textarea name="pl_notas" rows="2" class="pl-input" style="height:auto;padding:8px 12px;resize:vertical;"
 placeholder="Ex: Pai comprometeu-se a pagar até ao dia 5 de cada mês. Contacto: 84 123 4567"></textarea>
 </div>
 </div>

 <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:4px;">
 <button type="button" data-sige-act="plToggleNovoPlano" data-sige-args='[false]'
 class="pl-btn pl-btn-ghost">Cancelar</button>
 <button type="submit" class="pl-btn pl-btn-pri">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:15px;height:15px;"><path d="M20 6L9 17l-5-5"/></svg>
 Criar Plano
 </button>
 </div>
 </form>
 </div>
 </div>

 <!-- Filtros -->
 <div class="pl-card sg-plans-filter-card">
 <div class="pl-card-b sg-plans-filter-body">
 <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
 <?php
   // [v13.4.0] Pills preservam centro_id activo (adicionado via add_query_arg)
   foreach ([''=>'Todos']+array_combine(['activo','cumprido','quebrado','cancelado'],['Activos','Cumpridos','Quebrados','Cancelados']) as $v=>$l):
     $__args_pill = ['page'=>'sige-app','view'=>'financeiro-planos','st'=>$v ?: null, 'centro_id'=>$centro_id_filtro ?: null];
     $__href_pill = esc_url(add_query_arg($__args_pill, admin_url('admin.php')));
 ?>
 <a href="<?php echo $__href_pill; ?>"
 style="padding:6px 14px;border-radius:8px;font-size:0.8rem;font-weight:700;text-decoration:none;
 <?php echo ($filtro_status===$v || ($v===''&&!in_array($filtro_status,['activo','cumprido','quebrado','cancelado'],true)))
 ? 'background:var(--pl-purple);color:#fff;'
 : 'background:#f1f5f9;color:#475569;'; ?>">
 <?php echo $l; ?>
 <?php if ($v && $kpi): echo ' (' . (int)($kpi->{'n_'.$v.'s'} ?? $kpi->{'n_'.$v} ?? 0) . ')'; endif; ?>
 </a>
 <?php endforeach; ?>
 </div>

 <?php
   // [v13.4.0 BLOCO 3] Filtro por centro (form GET separado; auto_submit para
   // dispensar botão extra, ficando coerente com a UX de pills acima)
   if (function_exists('sige_fin_render_filtro_centro')) {
     $__sel = sige_fin_render_filtro_centro([
       'selected'      => $centro_id_filtro,
       'include_label' => true,
       'label'         => 'Centro:',
       'auto_submit'   => true,
     ]);
     if ($__sel !== ''):
 ?>
 <form method="get" style="display:flex;align-items:center;gap:8px;">
   <input type="hidden" name="page" value="sige-app">
   <input type="hidden" name="view" value="financeiro-planos">
   <?php if ($filtro_status !== ''): ?>
   <input type="hidden" name="st" value="<?php echo esc_attr($filtro_status); ?>">
   <?php endif; ?>
   <?php echo $__sel; ?>
 </form>
 <?php
     endif;
   }
 ?>
 </div>
 </div>

 <!-- Lista de Planos -->
 <?php if (empty($planos)): ?>
 <div class="pl-card sg-plans-empty">
 <div class="sg-plans-empty-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('clipboard') : ''; ?></div>
 <strong>Nenhum plano encontrado</strong>
 <p>Crie o primeiro plano para acompanhar pagamentos faseados com mais clareza.</p>
 </div>
 <?php else: ?>

 <?php foreach ($planos as $pl):
 $prs = $prestacoes_map[(int)$pl->id] ?? [];
 $pct_pago = $pl->n_prestacoes > 0 ? round((int)$pl->n_pagos / $pl->n_prestacoes * 100) : 0;
 $st_cfg = $status_cfg[$pl->status] ?? $status_cfg['activo'];
 $pr_id_key = 'pl-prest-' . $pl->id;
 ?>
 <div class="pl-card sg-plans-plan-card">
 <div class="pl-card-h sg-plans-plan-head">
 <!-- Lado esquerdo: aluno + status -->
 <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
 <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--pl-purple),#a855f7);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:1rem;flex-shrink:0;">
 <?php echo esc_html(mb_strtoupper(mb_substr($pl->nome_completo, 0, 1))); ?>
 </div>
 <div>
 <div style="font-weight:800;color:#1e293b;font-size:0.95rem;"><?php echo esc_html($pl->nome_completo); ?></div>
 <div style="font-size:0.78rem;color:#64748b;display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:2px;">
 <span>Proc: <?php echo esc_html($pl->numero_processo ?? '-'); ?></span>
 <span class="pl-badge" style="background:<?php echo $st_cfg['bg']; ?>;color:<?php echo $st_cfg['txt']; ?>;">
 <?php echo $st_cfg['icon'] . ' ' . $st_cfg['label']; ?>
 </span>
 <span style="color:#94a3b8;">Criado em <?php echo wp_date('d/m/Y', strtotime($pl->criado_em)); ?> por <?php echo esc_html($pl->criado_por_nome ?? '-'); ?></span>
 </div>
 </div>
 </div>
 <!-- Lado direito: totais + botões -->
 <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
 <div class="sige-u-tar">
 <div style="font-weight:900;color:#1e293b;font-size:1rem;"><?php echo number_format($pl->valor_total, 2, ',', '.'); ?> MT</div>
 <div style="font-size:0.75rem;color:#64748b;">
 <?php echo (int)$pl->n_pagos; ?>/<?php echo (int)$pl->n_prestacoes; ?> pagas
 <?php if ((int)$pl->n_atrasados > 0): ?>
 · <span style="color:#b91c1c;font-weight:700;"><?php echo (int)$pl->n_atrasados; ?> atrasada<?php echo $pl->n_atrasados > 1 ? 's' : ''; ?></span>
 <?php endif; ?>
 </div>
 </div>
 <button data-sige-act="sigeAlternarDisplay" data-target="<?php echo esc_attr($pr_id_key); ?>" data-display="table-row-group"
 class="pl-btn pl-btn-ghost" style="font-size:0.78rem;">
 Prestações
 </button>
 <?php if ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director')): ?>
 <?php if ($pl->status !== 'cancelado' && $pl->status !== 'cumprido'): ?>
 <button data-sige-act="sigeAlternarDisplay" data-target="edit-pl-<?php echo (int)$pl->id; ?>" data-display="block"
 class="pl-btn pl-btn-ghost" style="font-size:0.78rem;">Editar</button>
 <form method="post" style="display:inline;" class="pl-cancel-form" data-plano-nome="<?php echo esc_attr($pl->nome_completo); ?>" data-plano-valor="<?php echo esc_attr(number_format((float)$pl->valor_total, 2, ',', '.')); ?> MT">
 <?php wp_nonce_field('sige_cancelar_plano','_wpnonce_cancel_pl'); ?>
 <input type="hidden" name="sige_cancelar_plano_submit" value="1">
 <input type="hidden" name="plano_id_cancel" value="<?php echo (int)$pl->id; ?>">
 <button type="submit" class="pl-btn pl-btn-danger" style="font-size:0.78rem;">Cancelar</button>
 </form>
 <?php endif; ?>
 <?php endif; ?>
 </div>
 </div>

 <!-- [B10] Formulário de edição inline -->
 <div id="edit-pl-<?php echo (int)$pl->id; ?>" style="display:none;padding:12px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;">
 <form method="post" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
 <?php wp_nonce_field('sige_editar_plano','_wpnonce_edit_pl'); ?>
 <input type="hidden" name="sige_editar_plano_submit" value="1">
 <input type="hidden" name="edit_plano_id" value="<?php echo (int)$pl->id; ?>">
 <div style="flex:2;min-width:200px;">
 <label style="font-size:0.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:2px;">Descrição</label>
 <input type="text" name="edit_descricao" value="<?php echo esc_attr($pl->descricao); ?>"
 style="width:100%;padding:5px 8px;border:1px solid #cbd5e1;border-radius:6px;font-size:0.82rem;">
 </div>
 <div style="flex:3;min-width:200px;">
 <label style="font-size:0.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:2px;">Notas</label>
 <input type="text" name="edit_notas" value="<?php echo esc_attr($pl->notas ?? ''); ?>"
 style="width:100%;padding:5px 8px;border:1px solid #cbd5e1;border-radius:6px;font-size:0.82rem;">
 </div>
 <button type="submit" class="pl-btn pl-btn-pri" style="font-size:0.78rem;padding:6px 16px;">Guardar</button>
 </form>
 </div>

 <!-- Barra de progresso -->
 <div style="padding:0 20px 4px;">
 <div style="font-size:0.72rem;color:#64748b;margin-bottom:4px;"><?php echo esc_html($pl->descricao); ?></div>
 <div class="pl-progress">
 <div class="pl-progress-bar" style="width:<?php echo $pct_pago; ?>%;"></div>
 </div>
 <div style="display:flex;justify-content:space-between;font-size:0.7rem;color:#94a3b8;margin-top:2px;">
 <span><?php echo $pct_pago; ?>% pago</span>
 <?php if ($pl->prox_vencimento): ?>
 <span>Próx: <?php echo wp_date('d/m/Y', strtotime($pl->prox_vencimento));
 if ($pl->prox_vencimento < $hoje) echo ' <span style="color:#b91c1c;"> vencida</span>';
 ?></span>
 <?php endif; ?>
 </div>
 </div>

 <!-- Tabela de prestações (collapsível) -->
 <table class="pl-table">
 <tbody id="<?php echo $pr_id_key; ?>" style="display:none;">
 <?php foreach ($prs as $pr):
 $pr_cfg = $status_cfg[$pr->status] ?? $status_cfg['pendente'];
 $pr_late = ($pr->status === 'pendente' || $pr->status === 'atrasado') && $pr->data_vencimento < $hoje;
 ?>
 <tr class="pl-prest-row">
 <td style="width:32px;color:#94a3b8;font-size:0.8rem;padding-left:20px;">P<?php echo (int)$pr->numero; ?></td>
 <td>
 <span class="pl-badge" style="background:<?php echo $pr_cfg['bg']; ?>;color:<?php echo $pr_cfg['txt']; ?>;">
 <?php echo $pr_cfg['icon'] . ' ' . $pr_cfg['label']; ?>
 </span>
 </td>
 <td class="sige-u-fw7"><?php echo number_format((float)$pr->valor, 2, ',', '.'); ?> MT</td>
 <td style="<?php echo $pr_late ? 'color:#b91c1c;font-weight:700;' : 'color:#64748b;'; ?>">
 <?php echo wp_date('d/m/Y', strtotime($pr->data_vencimento)); ?>
 <?php if ($pr_late) echo ' '; ?>
 </td>
 <td>
 <?php if ($pr->status === 'pendente' || $pr->status === 'atrasado'): ?>
 <button data-sige-act="plAbrirPagar" data-sige-args="<?php echo esc_attr(wp_json_encode([(int)$pr->id, $pl->nome_completo, (float)$pr->valor, (int)$pr->numero])); ?>"
 class="pl-btn pl-btn-pay" style="font-size:0.76rem;padding:5px 12px;">
 Pagar
 </button>
 <?php elseif ($pr->status === 'pago'): ?>
 <span style="font-size:0.78rem;color:#16a34a;font-weight:700;">✓ Pago em <?php echo $pr->pago_em ? wp_date('d/m/Y', strtotime($pr->pago_em)) : '-'; ?></span>
 <?php endif; ?>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 <?php endforeach; ?>
 <?php endif; ?>

<?php endif; // tabelas_ok ?>

</div><!-- .pl-wrap -->

<!-- Modal: Pagar Prestação -->
<div id="pl-modal-pagar" class="sg-plans-modal" style="display:none;">
 <div class="sg-plans-modal-card">
 <div class="sg-plans-modal-head">
 <div><h3>Pagar Prestação</h3><p>Confirme o valor, o método e a referência antes de registar.</p></div>
 <button data-sige-act="plFecharPagar" data-sige-noargs class="sg-plans-close" aria-label="Fechar">&times;</button>
 </div>
 <div id="pl-pagar-info" class="sg-plans-modal-info"></div>
 <form method="post">
 <?php wp_nonce_field('sige_pagar_prestacao','_wpnonce_prest'); ?>
 <input type="hidden" name="sige_pagar_prestacao_submit" value="1">
 <input type="hidden" name="prestacao_id" id="pl-pagar-id" value="">
 <div style="margin-bottom:14px;">
 <label class="pl-label">Método de Pagamento</label>
 <select name="pr_metodo" class="pl-input">
 <?php echo function_exists('sige_fin_metodos_pagamento_options_html')
     ? sige_fin_metodos_pagamento_options_html('', ['context' => 'plano'])
     : '<option value="numerario">Numerário</option><option value="mpesa">M-Pesa</option><option value="emola">E-Mola</option><option value="emola_comerciante">E-Mola Comerciante</option><option value="bim">Millennium BIM</option><option value="bci">BCI</option><option value="pagafacil">Paga Fácil</option><option value="pos_bci">POS BCI</option><option value="pos_bim">POS BIM</option><option value="pos_stbank">POS STBANK</option><option value="pos_moza">POS MOZA</option><option value="pos_nedbank">POS NEDBANK</option><option value="pos_fnb">POS FNB</option><option value="nib">Transferência (NIB)</option><option value="transferencia">Transferência Bancária</option>'; ?>
 </select>
 </div>
 <div style="margin-bottom:20px;">
 <label class="pl-label">Referência (opcional)</label>
 <input type="text" name="pr_referencia" class="pl-input" placeholder="Nº do talão POS, M-Pesa, NIB, etc.">
 </div>
 <div class="sg-plans-modal-footer">
 <button type="button" data-sige-act="plFecharPagar" data-sige-noargs class="pl-btn pl-btn-ghost">Cancelar</button>
 <button type="submit" class="pl-btn pl-btn-pay">Confirmar Pagamento</button>
 </div>
 </form>
 </div>
</div>

<!-- Modal: Confirmação Geral -->
<div id="pl-modal-confirm" class="sg-plans-modal" style="display:none;">
 <div class="sg-plans-modal-card sg-plans-confirm-card">
  <div class="sg-plans-modal-head">
   <div><h3 id="pl-confirm-title">Confirmar acção</h3><p id="pl-confirm-subtitle">Reveja os dados antes de continuar.</p></div>
   <button type="button" data-sige-act="plFecharConfirmacao" data-sige-noargs class="sg-plans-close" aria-label="Fechar">&times;</button>
  </div>
  <div class="sg-plans-modal-body">
   <div id="pl-confirm-body" class="sg-plans-confirm-body"></div>
  </div>
  <div class="sg-plans-modal-footer">
   <button type="button" data-sige-act="plFecharConfirmacao" data-sige-noargs class="pl-btn pl-btn-ghost">Voltar</button>
   <button type="button" id="pl-confirm-action" class="pl-btn pl-btn-pri">Confirmar</button>
  </div>
 </div>
</div>

<!-- Modal: Mensagem -->
<div id="pl-modal-feedback" class="sg-plans-modal" style="display:none;">
 <div class="sg-plans-modal-card sg-plans-confirm-card">
  <div class="sg-plans-modal-head">
   <div><h3 id="pl-feedback-title">Mensagem</h3><p id="pl-feedback-subtitle">Informação da operação.</p></div>
   <button type="button" data-sige-act="plFecharFeedback" data-sige-noargs class="sg-plans-close" aria-label="Fechar">&times;</button>
  </div>
  <div class="sg-plans-modal-body">
   <div id="pl-feedback-body" class="sg-plans-confirm-body"></div>
  </div>
  <div class="sg-plans-modal-footer">
   <button type="button" data-sige-act="plFecharFeedback" data-sige-noargs class="pl-btn pl-btn-pri">Entendi</button>
  </div>
 </div>
</div>

<script <?php echo sige_csp_script_attr(); ?>>
// Nonce para AJAX
var plNonce = '<?php echo wp_create_nonce('sige_planos_nonce'); ?>';
var plAjaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
var plPendingForm = null;

function plToggleNovoPlano(force) {
 var el = document.getElementById('pl-form-novo');
 if (!el) return;
 var show = (typeof force === 'boolean') ? force : (el.style.display === 'none' || el.style.display === '');
 el.style.display = show ? 'block' : 'none';
 if (show) { el.scrollIntoView({behavior:'smooth', block:'start'}); }
}

function plAbrirConfirmacao(titulo, subtitulo, corpo, form) {
 plPendingForm = form || null;
 document.getElementById('pl-confirm-title').textContent = titulo || 'Confirmar acção';
 document.getElementById('pl-confirm-subtitle').textContent = subtitulo || 'Reveja os dados antes de continuar.';
 document.getElementById('pl-confirm-body').innerHTML = corpo || '';
 var modal = document.getElementById('pl-modal-confirm');
 modal.style.display = 'flex';
 document.body.style.overflow = 'hidden';
}
function plFecharConfirmacao() {
 document.getElementById('pl-modal-confirm').style.display = 'none';
 document.body.style.overflow = '';
 plPendingForm = null;
}
function plAbrirFeedback(tipo, titulo, corpo) {
 document.getElementById('pl-feedback-title').textContent = titulo || 'Mensagem';
 document.getElementById('pl-feedback-subtitle').textContent = tipo === 'error' ? 'Verifique a informação apresentada.' : 'Operação concluída.';
 document.getElementById('pl-feedback-body').innerHTML = corpo || '';
 var modal = document.getElementById('pl-modal-feedback');
 modal.classList.toggle('is-error', tipo === 'error');
 modal.style.display = 'flex';
 document.body.style.overflow = 'hidden';
}
function plFecharFeedback() {
 document.getElementById('pl-modal-feedback').style.display = 'none';
 document.body.style.overflow = '';
}
function plEsc(v){return String(v || '').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}


// Carregar lançamentos pendentes do aluno via AJAX
function plCarregarLancamentos(alunoId) {
 var wrap = document.getElementById('pl-lancs-wrap');
 var lista = document.getElementById('pl-lancs-lista');
 var total = document.getElementById('pl-lancs-total');
 if (!alunoId) { wrap.style.display = 'none'; return; }

 lista.innerHTML = '<div style="color:#64748b;font-size:0.85rem;padding:8px;">A carregar...</div>';
 wrap.style.display = 'block';

 var fd = new FormData();
 fd.append('action', 'sige_planos_lancs_aluno');
 fd.append('_nonce', plNonce);
 fd.append('aluno_id', alunoId);

 fetch(plAjaxUrl, {method:'POST', body:fd})
 .then(function(r){return r.json();})
 .then(function(res){
 if (!res.success || !res.data.lancamentos.length) {
 lista.innerHTML = '<div style="color:#94a3b8;font-size:0.85rem;padding:8px;">Nenhum lançamento pendente para este aluno.</div>';
 total.textContent = '';
 return;
 }
 var html = '';
 res.data.lancamentos.forEach(function(l) {
 var rest = parseFloat(l.restante) || 0;
 html += '<label style="display:flex;align-items:center;gap:10px;padding:8px;border-bottom:1px solid #f1f5f9;cursor:pointer;">'
 + '<input type="checkbox" name="pl_lancamentos[]" value="' + l.id + '" '
 + 'class="pl-lanc-check" data-valor="' + rest + '" '
 + 'style="width:15px;height:15px;" onchange="plSincronizarValor()">'
 + '<div class="sige-u-flex-1">'
 + '<span style="font-size:0.85rem;font-weight:600;color:#1e293b;">' + (l.servico_nome || l.descricao) + '</span>'
 + '<span style="font-size:0.75rem;color:#64748b;margin-left:8px;">' + (l.mes_referencia || '') + '</span>'
 + '</div>'
 + '<span style="font-weight:800;color:#dc2626;font-size:0.88rem;white-space:nowrap;">'
 + rest.toLocaleString('pt-MZ',{minimumFractionDigits:2}) + ' MT</span>'
 + '</label>';
 });
 lista.innerHTML = html;
 plSincronizarValor();
 })
 .catch(function(){ lista.innerHTML = '<div style="color:#b91c1c;font-size:0.85rem;padding:8px;">Erro ao carregar lançamentos.</div>'; });
}

// Sincronizar total seleccionado com campo de valor do plano
function plSincronizarValor() {
 var checks = document.querySelectorAll('.pl-lanc-check:checked');
 var total = 0;
 checks.forEach(function(cb){ total += parseFloat(cb.getAttribute('data-valor')) || 0; });
 var elTotal = document.getElementById('pl-lancs-total');
 if (elTotal) {
 elTotal.textContent = checks.length + ' lançamento' + (checks.length!==1?'s':'')
 + ' - Total: ' + total.toLocaleString('pt-MZ',{minimumFractionDigits:2}) + ' MT';
 }
 // Preencher automaticamente o campo de valor total
 var elValor = document.getElementById('pl-valor-total');
 if (elValor && total > 0) { elValor.value = total.toFixed(2); }
 plPreview();
}

function plSelTodosLancs(checked) {
 document.querySelectorAll('.pl-lanc-check').forEach(function(cb){ cb.checked = checked; });
 plSincronizarValor();
}

// Preview prestações ao criar plano
function plPreview() {
 var val = parseFloat(document.getElementById('pl-valor-total').value) || 0;
 var n = Math.max(1, Math.min(24, parseInt(document.getElementById('pl-n-prest').value) || 1));
 var prev = document.getElementById('pl-preview');
 var txt = document.getElementById('pl-preview-txt');
 if (!val || val <= 0) { prev.style.display = 'none'; return; }
 var prest = Math.round((val / n) * 100) / 100;
 var ultima = Math.round((val - prest * (n - 1)) * 100) / 100;
 var msg = n + ' prestação' + (n > 1 ? 'ões' : '') + ' de '
 + prest.toLocaleString('pt-MZ', {minimumFractionDigits:2}) + ' MT';
 if (n > 1 && Math.abs(ultima - prest) > 0.01) {
 msg += ' (última: ' + ultima.toLocaleString('pt-MZ', {minimumFractionDigits:2}) + ' MT)';
 }
 txt.textContent = msg;
 prev.style.display = 'block';
}

// Modal pagar prestação
function plAbrirPagar(prId, nomeAluno, valor, numero) {
 document.getElementById('pl-pagar-id').value = prId;
 document.getElementById('pl-pagar-info').textContent =
 nomeAluno + ' - Prestação ' + numero + ' - '
 + valor.toLocaleString('pt-MZ', {minimumFractionDigits:2}) + ' MT';
 var modal = document.getElementById('pl-modal-pagar');
 modal.style.display = 'flex';
 document.body.style.overflow = 'hidden';
}

function plFecharPagar() {
 document.getElementById('pl-modal-pagar').style.display = 'none';
 document.body.style.overflow = '';
}

document.getElementById('pl-modal-pagar').addEventListener('click', function(e) {
 if (e.target === this) plFecharPagar();
});
document.addEventListener('keydown', function(e) {
 if (e.key === 'Escape') plFecharPagar();
});

var plConfirmAction = document.getElementById('pl-confirm-action');
if (plConfirmAction) {
 plConfirmAction.addEventListener('click', function(){
  if (plPendingForm) {
   var f = plPendingForm;
   plPendingForm = null;
   f.dataset.plConfirmed = '1';
   f.submit();
  }
 });
}

document.querySelectorAll('.pl-create-form').forEach(function(form){
 form.addEventListener('submit', function(e){
  if (form.dataset.plConfirmed === '1') return;
  e.preventDefault();
  var alunoSelect = form.querySelector('[name="pl_aluno_id"]');
  var aluno = alunoSelect && alunoSelect.options[alunoSelect.selectedIndex] ? alunoSelect.options[alunoSelect.selectedIndex].text : '-';
  var valor = form.querySelector('[name="pl_valor_total"]') ? form.querySelector('[name="pl_valor_total"]').value : '';
  var prest = form.querySelector('[name="pl_n_prestacoes"]') ? form.querySelector('[name="pl_n_prestacoes"]').value : '';
  var checks = form.querySelectorAll('.pl-lanc-check:checked').length;
  var corpo = '<div class="sg-plans-confirm-grid">'
   + '<span>Aluno</span><strong>'+plEsc(aluno)+'</strong>'
   + '<span>Valor acordado</span><strong>'+plEsc(valor)+' MT</strong>'
   + '<span>Prestações</span><strong>'+plEsc(prest)+'</strong>'
   + '<span>Lançamentos seleccionados</span><strong>'+checks+'</strong>'
   + '</div>';
  plAbrirConfirmacao('Criar plano de pagamento?', 'Confirme os dados antes de gravar o acordo.', corpo, form);
 });
});

document.querySelectorAll('.pl-cancel-form').forEach(function(form){
 form.addEventListener('submit', function(e){
  if (form.dataset.plConfirmed === '1') return;
  e.preventDefault();
  var corpo = '<p class="sg-plans-modal-text">Esta acção cancela o plano e devolve os lançamentos ainda em plano para o estado pendente.</p>'
   + '<div class="sg-plans-confirm-grid">'
   + '<span>Aluno</span><strong>'+plEsc(form.getAttribute('data-plano-nome'))+'</strong>'
   + '<span>Valor do plano</span><strong>'+plEsc(form.getAttribute('data-plano-valor'))+'</strong>'
   + '</div>';
  plAbrirConfirmacao('Cancelar plano de pagamento?', 'Esta acção deve ser feita apenas quando o acordo deixou de ser válido.', corpo, form);
 });
});

(function(){
 var notice = document.querySelector('body.sige-view-financeiro-planos .notice.notice-success, body.sige-view-financeiro-planos .notice.notice-error, body.sige-view-financeiro-planos .notice.notice-warning');
 if (!notice) return;
 var isError = notice.classList.contains('notice-error') || notice.classList.contains('notice-warning');
 var title = isError ? 'Atenção' : 'Operação concluída';
 var html = notice.innerHTML;
 notice.style.display = 'none';
 setTimeout(function(){ plAbrirFeedback(isError ? 'error' : 'success', title, html); }, 250);
})();

['pl-modal-confirm','pl-modal-feedback'].forEach(function(id){
 var m = document.getElementById(id);
 if (m) m.addEventListener('click', function(e){ if (e.target === m) { if (id === 'pl-modal-confirm') plFecharConfirmacao(); else plFecharFeedback(); } });
});

</script>
