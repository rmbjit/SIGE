<?php
/**
 * SIGE SoftGenial - Central de Cobranças (Devedores)
 *
 * v2.3 - Maio 2026
 * - v12.10.13: harmonia visual com o Painel Principal, sem alterar lógica financeira
 * - v12.9.30: UI refinada da Central de Cobranças sem alterar lógica financeira
 * - v12.9.30.7: remove definitivamente ícones KPI/decorativos que apareciam no header por colisão visual
 * - v12.9.30.2: hotfix visual definitivo do header: remove ícones decorativos e compacta o topo
 * - v12.9.30.1: hotfix visual do header para alinhar/remover ícones soltos
 * - v12.9.29: régua de cobrança operacional por caso
 * - v12.9.28: inteligência temporal, comportamento de pagamento e filtro por comportamento
 * - v12.9.27: inteligência de cobrança, sugestão de acção e lista operacional imprimível
 * - v12.9.26: acção inteligente por prioridade, resumo dinâmico da selecção e pré-validação operacional
 * - v12.9.25: prioridade de cobrança sem alterar fórmulas financeiras
 * - CSS: Indigo→Navy, Tailwind→Material palette
 * - @import removido, sigeFadeInUp→fadeInUp
 * - 6× date() → wp_date()
 * - escola_id normalizado
 */
if (!defined('ABSPATH')) exit;
global $wpdb;
// ── escola_id centralizado ────────────────────────────────────────────────
$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
// Permissões
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['financeiro.cobrancas_ver','financeiro.cobrancas_gerir'],
    ['sige_financeiro','sige_secretario','sige_director']
)) return;
// 1. PROCESSAR ENVIO DE NOTIFICAÇÃO (INDIVIDUAL OU EM MASSA)
if (isset($_POST['sige_cobrar_nonce']) && wp_verify_nonce($_POST['sige_cobrar_nonce'], 'enviar_cobranca')) {
 $ids_para_cobrar = isset($_POST['aluno_ids']) ? array_map('intval', $_POST['aluno_ids']) : [];
 $enviados = 0;
 $notificar_whatsapp_cobranca = function_exists('sige_fin_notificacao_canal_ativo') ? sige_fin_notificacao_canal_ativo('whatsapp') : true;
 $notificar_email_cobranca    = function_exists('sige_fin_notificacao_canal_ativo') ? sige_fin_notificacao_canal_ativo('email') : true;
 // [v12.9.57] Batch tracking para delay progressivo entre destinatários
 $_batch_total = count($ids_para_cobrar);
 $_batch_index = 0;
 foreach ($ids_para_cobrar as $aid) {
 // Buscar dados frescos do aluno e da dívida
 $aluno = $wpdb->get_row($wpdb->prepare("SELECT nome_completo, whatsapp_notificacoes, telemovel_pai, telemovel_mae, contacto_encarregado, nome_pai, nome_mae, email_encarregado, email_pai, email_mae FROM {$wpdb->prefix}sige_alunos WHERE id=%d AND escola_id=%d", $aid, $escola_id));
 
 // Calcular total em dívida - FIX: escola_id adicionado
 $dividas = $wpdb->get_results($wpdb->prepare("
 SELECT * FROM {$wpdb->prefix}sige_fin_lancamentos 
 WHERE aluno_id=%d AND escola_id=%d AND status IN ('pendente','parcial','em_plano')
 ", $aid, $escola_id));
 $total_divida = 0;
 $detalhes_msg = [];
 $min_venc = null;
 foreach($dividas as $d) {
 // Força recálculo para garantir multa atualizada
 sige_fin_recalcular_e_sync((int)$d->id);
 $d_fresh = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_fin_lancamentos WHERE id=%d AND escola_id=%d", (int)$d->id, $escola_id));
 
 // [FIX FORMULA-05] Usar função canónica em vez de fórmula inline
 // ANTES: usava (float)$d_fresh->valor_multa sem valor_multa_cobrada
 $subtotal = sige_fin_saldo_lancamento($d_fresh);
 
 if($subtotal > 0) {
 if (!empty($d_fresh->data_vencimento)) {
 $dv = (string)$d_fresh->data_vencimento;
 if ($min_venc === null || $dv < $min_venc) $min_venc = $dv;
 }
 $total_divida += $subtotal;
 $detalhes_msg[] = $d_fresh->descricao . " (" . number_format($subtotal,2) . " " . sige_moeda() . ")";
 }
 }
 
 if ($total_divida > 0 && $aluno) {
 $detalhes_cob_str = !empty($detalhes_msg) ? implode("\n", array_map(function($d) { return "• " . $d; }, $detalhes_msg)) : 'Valores pendentes';

                // [v13.5.1] Dual-send: cobrança via WhatsApp para pai E mãe
                if (!empty($notificar_whatsapp_cobranca) && function_exists('sige_fin_wpp_notificar_encarregados')) {
                    $res_wpp = sige_fin_wpp_notificar_encarregados((int)$aid, 'cobranca', [
                        'valor'      => (float)$total_divida,
                        'vencimento' => $min_venc ?: wp_date('Y-m-d'),
                        'detalhes'   => $detalhes_cob_str,
                        'servico'    => $detalhes_cob_str,
                        'mes'        => wp_date('m/Y'),
                        'id'         => (string)$aid,
                    ]);
                    if (($res_wpp['enviados'] ?? 0) > 0) $enviados++;
                    // [v12.9.57] Aplicar delay progressivo entre destinatários do lote
                    if (function_exists('sige_notify_apply_batch_delay')) {
                        sige_notify_apply_batch_delay((int)$aid, 'cobranca', $_batch_index, $_batch_total);
                    }
                } else {
                    // ── Fallback legacy (single-send) ────────────────────
 $tel_raw = $aluno->whatsapp_notificacoes ?: ($aluno->telemovel_pai ?: ($aluno->telemovel_mae ?? ''));
 $tel_num = preg_replace('/[^0-9]/', '', (string)$tel_raw);
 $tel_num = sige_telefone_normalizar($tel_num);
 if (!empty($notificar_whatsapp_cobranca) && $tel_num && function_exists('sige_fin_queue_whatsapp')) {
 $min_venc_fmt = $min_venc ? wp_date('d/m/Y', strtotime($min_venc)) : wp_date('d/m/Y');
 if (function_exists('sige_wpp_render_finance_template')) {
 $cfg_cob = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1", $escola_id));
 $msg_cob = sige_wpp_render_finance_template('cobranca', $cfg_cob, $aluno, [
 'valor' => (float)$total_divida,
 'vencimento' => $min_venc ?: wp_date('Y-m-d'),
 'detalhes' => $detalhes_cob_str,
 'servico' => $detalhes_cob_str,
 'mes' => wp_date('m/Y'),
 'id' => (string)$aid,
 ]);
 } else {
 // v12.9.54 - fallback usa renderer v2 conversacional quando disponível
 $cfg_cob_fb = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1", $escola_id));
 if (function_exists('sige_wpp_tpl_v2_render')) {
     $msg_cob = sige_wpp_tpl_v2_render('cobranca', $cfg_cob_fb, $aluno, [
         'valor'      => (float)$total_divida,
         'vencimento' => $min_venc ?: wp_date('Y-m-d'),
         'detalhes'   => $detalhes_cob_str,
         'mes'        => wp_date('m/Y'),
         'id'         => (string)$aid,
     ]);
 } else {
     $escola_nome = function_exists('sige_get_escola_perfil') ? (sige_get_escola_perfil()->nome_escola ?? '') : '';
     $msg_cob = "Olá,\n\nA secretaria" . ($escola_nome ? " da {$escola_nome}" : '') . " gostaria de confirmar consigo a situação financeira dos serviços escolares de {$aluno->nome_completo}.\n\n"
              . "{$detalhes_cob_str}\n\n"
              . "Total em aberto: " . number_format($total_divida, 2, ',', '.') . " " . sige_moeda() . ".\n\n"
              . "Se já regularizou, peço desculpa pelo incómodo - pode responder por aqui que actualizamos. Se ainda não foi possível, fale connosco com calma.";
 }
 }
 $ok = sige_fin_queue_whatsapp((int)$aid, $tel_num, 'cobranca', $msg_cob);
 if ($ok) $enviados++;
 // [v12.9.57] Delay progressivo no fallback legacy também
 if ($ok && function_exists('sige_notify_apply_batch_delay')) {
 sige_notify_apply_batch_delay((int)$aid, 'cobranca', $_batch_index, $_batch_total);
 }
 } elseif (!empty($notificar_whatsapp_cobranca) && function_exists('sige_enviar_whatsapp')) {
 $enviado_ok = sige_enviar_whatsapp($aid, 'cobranca', [
 'valor' => (float)$total_divida,
 'vencimento' => $min_venc ?: wp_date('Y-m-d'),
 'id' => (string)$aid,
 'servico' => $detalhes_cob_str,
 'detalhes' => $detalhes_cob_str,
 'mes' => wp_date('m/Y'),
 ]);
 if ($enviado_ok) $enviados++;
 }
                }

                // [v13.5.1] sige_enviar_email_financeiro já faz dual-send internamente (refactor v13.5.1)
 if (!empty($notificar_email_cobranca) && function_exists('sige_enviar_email_financeiro')) {
 sige_enviar_email_financeiro((int)$aid, 'cobranca', $detalhes_cob_str, (float)$total_divida, $min_venc ?: wp_date('Y-m-d'));
 }
 }
 // [v12.9.57] Próxima iteração receberá delay extra proporcional ao índice no lote
 $_batch_index++;
 }
 if ($enviados > 0) {
 echo '<div class="notice notice-success is-dismissible" style="border-radius:8px;"><p>' . $enviados . ' notificações enviadas para a fila de WhatsApp!</p></div>';
 } else {
 echo '<div class="notice notice-warning is-dismissible" style="border-radius:8px;"><p>Nenhuma notificação enviada (verifique se os alunos têm telefone ou dívidas).</p></div>';
 }
}
// 2. FILTROS
$turma_filtro = sige_fin_get_int('turma_id');
$mes_filtro = sige_fin_get_param('mes');
$situacao_aluno_filtro = sanitize_key((string) sige_fin_get_param('situacao_aluno', 'activos'));
if (!in_array($situacao_aluno_filtro, ['activos','transferidos','todos'], true)) {
 $situacao_aluno_filtro = 'activos';
}
$prioridade_filtro = sanitize_key((string) sige_fin_get_param('prioridade_cobranca'));
$comportamento_filtro = sanitize_key((string) sige_fin_get_param('comportamento_cobranca'));
$regua_filtro = sanitize_key((string) sige_fin_get_param('regua_cobranca'));
$prioridades_validas = ['critica', 'alta', 'media', 'sem_contacto'];
if (!in_array($prioridade_filtro, $prioridades_validas, true)) {
 $prioridade_filtro = '';
}
$comportamentos_validos = ['bom_pagador', 'irregular', 'cronico'];
if (!in_array($comportamento_filtro, $comportamentos_validos, true)) {
 $comportamento_filtro = '';
}
$reguas_validas = ['lembrete_cordial', 'cobranca_formal', 'aviso_prioritario', 'atualizar_contacto'];
if (!in_array($regua_filtro, $reguas_validas, true)) {
 $regua_filtro = '';
}
// [v13.4.0 BLOCO 3] Filtro universal por centro
$centro_id_filtro = function_exists('sige_fin_centro_ativo') ? sige_fin_centro_ativo() : 0;
// 3. QUERY PRINCIPAL (BUSCAR DEVEDORES)
$where_base = "l.status IN ('pendente', 'parcial')";
$where_extra = "";
$where_mes = "";
$params = [];
$params_mes = [];
if ($turma_filtro > 0) {
 $where_extra .= " AND m.turma_id = %d AND m.ano_lectivo = %d";
 $params[] = $turma_filtro;
 $params[] = sige_fin_get_ano_letivo_master();
}
if ($mes_filtro && preg_match('/^\d{4}-\d{2}$/', $mes_filtro)) {
 $where_mes .= " AND LEFT(l.mes_referencia, 7) = %s";
 $params_mes[] = $mes_filtro;
}
if ($centro_id_filtro > 0) {
 $where_extra .= " AND l.centro_id = %d";
 $params[] = $centro_id_filtro;
}
$__sige_a_activo_cobranca = function_exists('sige_aluno_activo_sql')
 ? sige_aluno_activo_sql('a')
 : "(a.status IS NULL OR LOWER(a.status) IN ('activo','ativo','activa','ativa'))";
$__sige_m_activa_cobranca = function_exists('sige_matricula_activa_sql')
 ? sige_matricula_activa_sql('m')
 : "(m.status_matricula IS NULL OR LOWER(m.status_matricula) IN ('activa','ativa','activo','ativo'))";
$__sige_a_inactivo_cobranca = function_exists('sige_aluno_inactivo_operacional_sql')
 ? sige_aluno_inactivo_operacional_sql('a')
 : "LOWER(COALESCE(a.status,'')) IN ('transferido','transferida','desistente','desistiu','cancelado','cancelada','inactivo','inativo')";
$__sige_m_inactiva_cobranca = function_exists('sige_matricula_inactiva_operacional_sql')
 ? sige_matricula_inactiva_operacional_sql('m')
 : "TRIM(LOWER(COALESCE(m.status_matricula,''))) IN ('transferido','transferida','desistente','desistiu','cancelado','cancelada','inactiva','inativa','inactivo','inativo')";
if ($situacao_aluno_filtro === 'activos') {
 $where_extra .= " AND {$__sige_a_activo_cobranca} AND {$__sige_m_activa_cobranca}";
} elseif ($situacao_aluno_filtro === 'transferidos') {
 $where_extra .= " AND ({$__sige_a_inactivo_cobranca} OR {$__sige_m_inactiva_cobranca})";
}
// FIX: divida_base_aprox inclui agora transporte + extras + multa_cobrada (ou multa) - descontos
// FIX: escola_id adicionado à query principal
// [FIX FORMULA-06] Usar sige_fin_saldo_sql() canónica
// ANTES: COALESCE(multa_cobrada, multa, 0) sem NULLIF - zero era tratado como válido
$_saldo_sql = sige_fin_saldo_sql('l');
// [SQLI-04] $escola_id agora usa %d em prepare() - nunca interpolar
$sql_devedores = "
 SELECT l.aluno_id, a.nome_completo, a.numero_processo, a.telemovel_pai, a.whatsapp_notificacoes, a.foto, a.genero, a.status AS aluno_status,
 t.nome as turma_nome, t.classe, t.nivel_ensino,
 COUNT(l.id) as qtd_mensalidades,
 SUM($_saldo_sql) as divida_base_aprox
 FROM {$wpdb->prefix}sige_fin_lancamentos l
 JOIN {$wpdb->prefix}sige_alunos a ON l.aluno_id = a.id AND a.escola_id = %d
 LEFT JOIN {$wpdb->prefix}sige_matriculas m ON (a.id = m.aluno_id AND m.escola_id = %d AND m.ano_lectivo = " . (int)sige_fin_get_ano_letivo_master() . ")
 LEFT JOIN {$wpdb->prefix}sige_turmas t ON m.turma_id = t.id AND t.escola_id = %d
 WHERE l.escola_id = %d AND {$where_base}{$where_extra}{$where_mes}
 GROUP BY l.aluno_id
 ORDER BY divida_base_aprox DESC
";
$base_params = [$escola_id, $escola_id, $escola_id, $escola_id];
$devedores_raw = $wpdb->get_results($wpdb->prepare($sql_devedores, array_merge($base_params, $params, $params_mes)));

// ── Calendário de devedores por mês (pedido de cliente, 12 Jun 2026) ────────
// Reutiliza as MESMAS condições da lista (sem o mês) e a saldo canónica.
$sgdc_dados = [];
if (function_exists('sige_fin_devedores_por_mes')) {
 $sgdc_ano = (int)sige_fin_get_ano_letivo_master();
 $sgdc_linhas = sige_fin_devedores_por_mes($wpdb, (int)$escola_id, $sgdc_ano, $where_base, $where_extra, $params);
 $sgdc_dados = sige_fin_calendario_devedores_montar($sgdc_linhas, $sgdc_ano, (string)wp_date('Y-m'), $mes_filtro ?: null);
}
// 4. PREPARAR DADOS REAIS (RECALCULAR NA HORA)
$lista_final = [];
$total_geral_divida = 0;
$stats_prioridade = ['critica' => 0, 'alta' => 0, 'media' => 0, 'sem_contacto' => 0];
$stats_comportamento = ['bom_pagador' => 0, 'irregular' => 0, 'cronico' => 0];
$stats_regua = ['lembrete_cordial' => 0, 'cobranca_formal' => 0, 'aviso_prioritario' => 0, 'atualizar_contacto' => 0];
foreach ($devedores_raw as $d) {
 // [FIX DEV-02] Usar a função canónica sige_fin_saldo_lancamento() - a mesma que pagamentos usa.
 // REMOVIDO: sige_fin_recalcular_lancamento() causava side-effects (escrevia na BD durante display,
 // zerava valor_transporte/extras, recalculava multas). Visualização nunca deve modificar dados.
 // [v13.4.0] Filtro por centro propagado - quando activo, considera apenas a dívida
 // daquele centro para manter coerência com a totalização já filtrada acima.
 $_centro_sql_dev = $centro_id_filtro > 0 ? ' AND centro_id = ' . (int)$centro_id_filtro : '';
 $lancamentos = $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$wpdb->prefix}sige_fin_lancamentos WHERE aluno_id=%d AND escola_id=%d AND status IN ('pendente','parcial'){$_centro_sql_dev}",
 $d->aluno_id, $escola_id
 ));
 
 $divida_real = 0;
 $meses_devidos = [];
 $menor_vencimento = null;
 $qtd_pendencias_reais = 0;
 $meses_referencia_abertos = [];
 
 foreach ($lancamentos as $lanc) {
 $a_pagar = function_exists('sige_fin_saldo_lancamento')
 ? sige_fin_saldo_lancamento($lanc)
 : max(0, (float)($lanc->valor_original ?? 0) - (float)($lanc->valor_pago ?? 0));
 
 if ($a_pagar > 0) {
 $divida_real += $a_pagar;
 $qtd_pendencias_reais++;
 if (!empty($lanc->data_vencimento)) {
 $dv = (string) $lanc->data_vencimento;
 if ($menor_vencimento === null || $dv < $menor_vencimento) {
 $menor_vencimento = $dv;
 }
 }
 $mes_ref_raw = (string)($lanc->mes_referencia ?? '');
 if ($mes_ref_raw !== '') {
  $meses_referencia_abertos[] = substr($mes_ref_raw, 0, 7);
 }
 $mes_parts = explode('-', $mes_ref_raw);
 if(count($mes_parts)>=2) {
 $dateObj = DateTime::createFromFormat('!m', $mes_parts[1]);
 $meses_devidos[] = $dateObj ? $dateObj->format('M') : $mes_ref_raw;
 } else {
 $meses_devidos[] = $lanc->descricao ?? $mes_ref_raw;
 }
 }
 }
 
 // Em plano negociado (contabilizar separadamente para transparência)
 $em_plano_rows = $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$wpdb->prefix}sige_fin_lancamentos WHERE aluno_id=%d AND escola_id=%d AND status = 'em_plano'{$_centro_sql_dev}",
 $d->aluno_id, $escola_id
 ));
 $divida_plano = 0;
 foreach ($em_plano_rows as $lp) {
 $divida_plano += function_exists('sige_fin_saldo_lancamento')
 ? sige_fin_saldo_lancamento($lp) : max(0, (float)($lp->valor_original ?? 0) - (float)($lp->valor_pago ?? 0));
 }
 
 if ($divida_real > 0 || $divida_plano > 0) {
 $tel_prioridade = $d->whatsapp_notificacoes ?: $d->telemovel_pai;
 $tem_contacto = preg_replace('/[^0-9]/', '', (string) $tel_prioridade) !== '';
 $dias_atraso = 0;
 if ($menor_vencimento) {
 $venc_ts = strtotime($menor_vencimento);
 $hoje_ts = function_exists('current_time') ? current_time('timestamp') : time();
 if ($venc_ts && $hoje_ts > $venc_ts) {
 $dias_atraso = (int) floor(($hoje_ts - $venc_ts) / DAY_IN_SECONDS);
 }
 }

 $meses_referencia_abertos = array_values(array_unique(array_filter($meses_referencia_abertos)));
 sort($meses_referencia_abertos);
 $total_meses_atraso = count($meses_referencia_abertos);
 $meses_consecutivos_atraso = 0;
 if (!empty($meses_referencia_abertos)) {
  $timestamps_meses = [];
  foreach ($meses_referencia_abertos as $mr) {
   $ts_mr = strtotime($mr . '-01');
   if ($ts_mr) $timestamps_meses[] = $ts_mr;
  }
  rsort($timestamps_meses);
  $prev = null;
  foreach ($timestamps_meses as $ts_mr) {
   if ($prev === null) {
    $meses_consecutivos_atraso = 1;
    $prev = $ts_mr;
    continue;
   }
   $esperado = strtotime('-1 month', $prev);
   if (wp_date('Y-m', $esperado) === wp_date('Y-m', $ts_mr)) {
    $meses_consecutivos_atraso++;
    $prev = $ts_mr;
   } else {
    break;
   }
  }
 }

 $comportamento_slug = 'bom_pagador';
 $comportamento_label = 'Bom pagador';
 $comportamento_hint = 'Pendência recente; acompanhar com cobrança leve.';
 if ($dias_atraso >= 60 || $total_meses_atraso >= 3 || $meses_consecutivos_atraso >= 3) {
  $comportamento_slug = 'cronico';
  $comportamento_label = 'Crónico';
  $comportamento_hint = 'Reincidência de atraso; exige seguimento próximo.';
 } elseif ($dias_atraso >= 30 || $total_meses_atraso >= 2 || $meses_consecutivos_atraso >= 2) {
  $comportamento_slug = 'irregular';
  $comportamento_label = 'Irregular';
  $comportamento_hint = 'Falhas recorrentes ou atraso moderado.';
 }

 $prioridade_slug = 'media';
 $prioridade_label = 'Média';
 $prioridade_score = 40;
 $prioridade_hint = 'Acompanhar no ciclo normal de cobrança.';

 if (!$tem_contacto) {
 $prioridade_slug = 'sem_contacto';
 $prioridade_label = 'Sem contacto';
 $prioridade_score = 100;
 $prioridade_hint = 'Actualizar contacto antes de enviar cobrança.';
 } elseif ($dias_atraso >= 60 || $qtd_pendencias_reais >= 3 || $total_meses_atraso >= 3 || $meses_consecutivos_atraso >= 3) {
 $prioridade_slug = 'critica';
 $prioridade_label = 'Crítica';
 $prioridade_score = 90;
 $prioridade_hint = 'Priorizar contacto humano e seguimento pela secretaria.';
 } elseif ($dias_atraso >= 30 || $qtd_pendencias_reais >= 2 || $total_meses_atraso >= 2 || $meses_consecutivos_atraso >= 2) {
 $prioridade_slug = 'alta';
 $prioridade_label = 'Alta';
 $prioridade_score = 70;
 $prioridade_hint = 'Cobrança recomendada nesta ronda.';
 }

 $regua_slug = 'lembrete_cordial';
 $regua_label = 'Lembrete cordial';
 $regua_hint = 'Mensagem leve, educativa e sem tom de pressão.';
 $regua_modelo = 'Olá, lembramos com cordialidade que existe uma pendência financeira por regularizar. Agradecemos a atenção.';
 if (!$tem_contacto) {
  $regua_slug = 'atualizar_contacto';
  $regua_label = 'Actualizar contacto';
  $regua_hint = 'Antes de cobrar, regularizar telefone/WhatsApp/e-mail do encarregado.';
  $regua_modelo = 'Prioridade operacional: actualizar contacto do encarregado antes de qualquer tentativa de cobrança.';
 } elseif ($prioridade_slug === 'critica' || $comportamento_slug === 'cronico') {
  $regua_slug = 'aviso_prioritario';
  $regua_label = 'Aviso prioritário';
  $regua_hint = 'Cobrança com seguimento humano próximo pela secretaria/direcção.';
  $regua_modelo = 'Olá, existe uma pendência financeira em estado prioritário. Pedimos contacto com a secretaria para regularização.';
 } elseif ($prioridade_slug === 'alta' || $comportamento_slug === 'irregular') {
  $regua_slug = 'cobranca_formal';
  $regua_label = 'Cobrança formal';
  $regua_hint = 'Mensagem objectiva, com valor e necessidade de regularização.';
  $regua_modelo = 'Olá, informamos que existe uma pendência financeira. Solicitamos a regularização dentro do prazo possível.';
 }

 if ($prioridade_filtro && $prioridade_filtro !== $prioridade_slug) {
 continue;
 }
 if ($comportamento_filtro && $comportamento_filtro !== $comportamento_slug) {
 continue;
 }
 if ($regua_filtro && $regua_filtro !== $regua_slug) {
 continue;
 }

 $d->divida_total = $divida_real;
 $d->divida_plano = $divida_plano;
 $d->meses_desc = array_unique($meses_devidos);
 $d->dias_atraso = $dias_atraso;
 $d->vencimento_mais_antigo = $menor_vencimento;
 $d->qtd_pendencias_reais = $qtd_pendencias_reais;
 $d->cobranca_prioridade_slug = $prioridade_slug;
 $d->cobranca_prioridade_label = $prioridade_label;
 $d->cobranca_prioridade_score = $prioridade_score;
 $d->cobranca_prioridade_hint = $prioridade_hint;
 $d->cobranca_tem_contacto = $tem_contacto;
 $d->total_meses_atraso = $total_meses_atraso;
 $d->meses_consecutivos_atraso = $meses_consecutivos_atraso;
 $d->comportamento_cobranca_slug = $comportamento_slug;
 $d->comportamento_cobranca_label = $comportamento_label;
 $d->comportamento_cobranca_hint = $comportamento_hint;
 $d->regua_cobranca_slug = $regua_slug;
 $d->regua_cobranca_label = $regua_label;
 $d->regua_cobranca_hint = $regua_hint;
 $d->regua_cobranca_modelo = $regua_modelo;

 if (isset($stats_prioridade[$prioridade_slug])) {
 $stats_prioridade[$prioridade_slug]++;
 }
 if (isset($stats_comportamento[$comportamento_slug])) {
 $stats_comportamento[$comportamento_slug]++;
 }
 if (isset($stats_regua[$regua_slug])) {
 $stats_regua[$regua_slug]++;
 }
 $lista_final[] = $d;
 $total_geral_divida += $divida_real;
 }
}

usort($lista_final, function($a, $b) {
 $score_a = (int)($a->cobranca_prioridade_score ?? 0);
 $score_b = (int)($b->cobranca_prioridade_score ?? 0);
 if ($score_a !== $score_b) return $score_b <=> $score_a;
 $meses_a = (int)($a->meses_consecutivos_atraso ?? 0);
 $meses_b = (int)($b->meses_consecutivos_atraso ?? 0);
 if ($meses_a !== $meses_b) return $meses_b <=> $meses_a;
 return (float)($b->divida_total ?? 0) <=> (float)($a->divida_total ?? 0);
});

// Configs para selects
// FIX: escola_id adicionado à query de turmas
$turmas = $wpdb->get_results($wpdb->prepare("SELECT id, nome, classe FROM {$wpdb->prefix}sige_turmas WHERE escola_id=%d ORDER BY classe ASC", $escola_id));
?>
<style>
/* ========================================
 SIGE CENTRAL DE COBRANÇAS - UI v12.9.30
 Apenas camada visual/UX. Não altera cálculo, saldo ou regras financeiras.
 ======================================== */
:root {
 --sige-font-display: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
 --sige-font-body: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
 --sige-page-bg: var(--color-slate-50);
 --sige-surface: var(--color-white);
 --sige-surface-soft: var(--color-slate-50);
 --sige-line: var(--color-ink-100);
 --sige-line-strong: var(--color-ink-200);
 --sige-text: var(--color-black);
 --sige-muted: var(--color-slate-500);
 --sige-muted-2: var(--color-slate-400);
 --sige-primary: var(--color-info-500);
 --sige-primary-dark: var(--color-info-700);
 --sige-primary-soft: var(--color-info-50);
 --sige-success: var(--color-success-500);
 --sige-success-soft: var(--color-success-50);
 --sige-warning: var(--color-warning-500);
 --sige-warning-soft: var(--color-warning-50);
 --sige-danger: var(--color-danger-600);
 --sige-danger-soft: var(--color-danger-50);
 --sige-orange-soft: var(--color-warning-50);
 --sige-radius: 14px;
 --sige-radius-lg: 20px;
 --sige-shadow-sm: 0 1px 2px rgba(15, 23, 42, .04);
 --sige-shadow: 0 8px 26px rgba(15, 23, 42, .08);
 --sige-shadow-lg: 0 18px 44px rgba(15, 23, 42, .12);
}
.sige-page { font-family: var(--sige-font-body); color: var(--sige-text); background: var(--sige-page-bg); min-height: 100vh; padding: 22px 24px 34px; box-sizing: border-box; }
.sige-page * { box-sizing: border-box; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
.sige-hero { margin-bottom: 18px; padding: 20px 22px; border-radius:var(--radius-xl); background: linear-gradient(135deg, var(--color-black) 0%, var(--color-ink-900) 52%, var(--color-slate-800) 100%); color: var(--color-white); box-shadow:var(--shadow-xs); display: flex; align-items: center; justify-content: space-between; gap:var(--space-5); animation: fadeInUp .28s ease-out; }
.sige-hero-main { display:flex; align-items:center; gap:14px; min-width:0; }
.sige-hero-icon { width:46px; height:46px; border-radius:var(--radius-lg); display:flex; align-items:center; justify-content:center; background: rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18); flex-shrink:0; }
.sige-hero h1 { font-family: var(--sige-font-display); font-size: 1.42rem; line-height:1.1; font-weight:700; margin: 0; color: var(--color-white); letter-spacing:-.02em; }
.sige-hero p { margin: 5px 0 0; font-size: .88rem; color: rgba(255,255,255,.78); }
.sige-hero-badges { display:flex; align-items:center; gap:var(--space-2); flex-wrap:wrap; justify-content:flex-end; }
.sige-hero-badge { display:inline-flex; align-items:center; gap:7px; padding:8px 11px; border-radius:var(--radius-pill); background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18); color:var(--color-white); font-size:.76rem; font-weight:700; white-space:nowrap; }
/* Hotfix UI v12.9.30.1 - Header limpo: remove a sensação de ícones soltos/empilhados */
.sige-hero-clean { margin-bottom:18px; padding:18px 24px; border-radius:var(--radius-xl); background:linear-gradient(135deg,var(--color-black) 0%,var(--color-ink-900) 62%,var(--color-slate-800) 100%); color:var(--color-white); box-shadow:var(--shadow-xs); display:grid; grid-template-columns:minmax(0,1fr) auto; gap:18px; align-items:center; animation:fadeInUp .28s ease-out; }
.sige-hero-clean-left { display:block; min-width:0; }
.sige-hero-clean-icon, .sige-hero-icon { display:none !important; }
.sige-hero-clean h1 { font-family:var(--sige-font-display); font-size:1.38rem; line-height:1.1; font-weight:700; margin:0; color:var(--color-white); letter-spacing:-.02em; }
.sige-hero-clean p { margin:var(--space-2) 0 0; font-size:.84rem; color:rgba(255,255,255,.76); max-width:820px; }
.sige-hero-clean-badges { display:flex; align-items:center; justify-content:flex-end; gap:var(--space-2); flex-wrap:wrap; }
.sige-hero-clean-badge { display:inline-flex; align-items:center; gap:7px; min-height:32px; padding:7px 11px; border-radius:var(--radius-pill); background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18); color:var(--color-white); font-size:.72rem; font-weight:700; white-space:nowrap; }
.sige-hero-clean-badge::before { content:''; width:6px; height:6px; border-radius:var(--radius-pill); background:var(--color-info-200); box-shadow:var(--shadow-xs); }

.sige-stats-grid { display:grid; grid-template-columns: minmax(260px, 1.35fr) repeat(4, minmax(170px, .75fr)); gap:14px; margin-bottom:16px; animation: fadeInUp .32s ease-out .04s both; }
.sige-stat-card { background: var(--sige-surface); border-radius:var(--radius-lg); padding: 18px 18px; border:1px solid var(--sige-line); box-shadow:var(--shadow-xs); display:flex; align-items:center; gap:14px; min-height:94px; position:relative; overflow:hidden; }
.sige-stat-card:first-child { border-color:var(--color-danger-200); background: linear-gradient(180deg,var(--color-white) 0%,var(--color-white) 100%); }
.sige-stat-card:hover { transform: translateY(-1px); box-shadow:var(--shadow-xs); transition: all .18s ease; }
.sige-stat-icon { width:46px; height:46px; border-radius:var(--radius-lg); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.sige-stat-icon.error { background: var(--sige-danger-soft); color: var(--sige-danger); }
.sige-stat-icon.warning { background: var(--sige-orange-soft); color: var(--color-danger-500); }
.sige-stat-label { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--color-slate-600); margin-bottom:6px; }
.sige-stat-value { font-family: var(--sige-font-display); font-size:1.7rem; font-weight:700; color:var(--sige-text); margin:0; letter-spacing:-.04em; }
.sige-stat-card:first-child .sige-stat-value { font-size:2.05rem; color:var(--color-black); }
.sige-card { background: var(--sige-surface); border-radius:var(--radius-xl); border:1px solid var(--sige-line); box-shadow:var(--shadow-xs); overflow:hidden; animation: fadeInUp .34s ease-out .08s both; }
.sige-toolbar { padding:18px 20px; border-bottom:1px solid var(--sige-line); display:grid; grid-template-columns: repeat(6, minmax(150px, 1fr)) auto auto; gap:var(--space-3); align-items:end; background: linear-gradient(180deg,var(--color-white) 0%, var(--color-slate-50) 100%); }
.sige-form-group { display:flex; flex-direction:column; gap:6px; min-width:0; }
.sige-label { font-size:.67rem; font-weight:700; color:var(--color-slate-700); text-transform:uppercase; letter-spacing:.07em; }
.sige-input, .sige-select { width:100%; height:42px; padding:0 var(--space-3); border:1px solid var(--sige-line-strong); border-radius:var(--radius-md); font-family:var(--sige-font-body); font-size:.86rem; color:var(--sige-text); background:var(--color-white); outline:none; transition:border-color .16s ease, box-shadow .16s ease; }
.sige-input:focus, .sige-select:focus { border-color:var(--sige-primary); box-shadow:var(--shadow-xs); }
.sige-btn { display:inline-flex; align-items:center; justify-content:center; gap:var(--space-2); min-height:38px; padding:0 15px; border-radius:var(--radius-md); font-weight:700; font-size:.8rem; cursor:pointer; transition:all .18s ease; border:none; text-decoration:none; font-family:var(--sige-font-body); white-space:nowrap; }
.sige-btn-primary { background:var(--sige-primary); color:var(--color-white); box-shadow:var(--shadow-sm); }
.sige-btn-primary:hover { background:var(--sige-primary-dark); color:var(--color-white); transform:translateY(-1px); }
.sige-btn-ghost { background:var(--color-white); color:var(--color-slate-800); border:1px solid var(--sige-line-strong); box-shadow:var(--shadow-xs); }
.sige-btn-ghost:hover { background:var(--color-slate-50); border-color:var(--color-slate-400); color:var(--color-black); }
.sige-btn-success { background:var(--sige-success); color:var(--color-white); box-shadow:var(--shadow-sm); }
.sige-btn-success:hover { background:var(--color-success-800); color:var(--color-white); transform:translateY(-1px); }
.sige-btn-mini { min-height:32px; padding:0 11px; font-size:.73rem; border-radius:var(--radius-sm); }
.sige-smart-actions { padding:14px 18px; border-bottom:1px solid var(--sige-line); background:var(--color-white); display:grid; grid-template-columns: minmax(300px, 1fr) auto; gap:var(--space-3); align-items:center; }
.sige-smart-actions-left, .sige-smart-actions-right { display:flex; flex-wrap:wrap; align-items:center; gap:var(--space-2); }
.sige-smart-actions-left strong { color:var(--color-slate-800) !important; font-size:.78rem !important; margin-right:4px; }
.sige-selection-summary { font-size:.8rem; color:var(--color-slate-700); justify-content:flex-end; }
.sige-selection-pill { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:var(--radius-pill); background:var(--color-ink-50); color:var(--color-slate-800); font-weight:700; border:1px solid var(--sige-line); }
.sige-selection-pill.warn { background:var(--sige-orange-soft); color:var(--color-warning-800); border-color:var(--color-warning-200); }
.sige-selection-pill.danger { background:var(--sige-danger-soft); color:var(--color-danger-700); border-color:var(--color-danger-200); }
.sige-selection-note { grid-column:1 / -1; padding:10px 12px; border-radius:var(--radius-md); background:var(--sige-orange-soft); color:var(--color-warning-800); border:1px solid var(--color-warning-200); font-size:.78rem; font-weight:700; display:none; }
.sige-operational-panel { grid-column:1 / -1; display:none; border:1px solid var(--sg-theme-soft,var(--color-brand-50)); border-left:4px solid var(--color-info-500); background:var(--color-slate-50); border-radius:var(--radius-md); padding:12px 14px; }
.sige-operational-panel.active { display:block; }
.sige-operational-panel strong { color:var(--sg-theme-primary-800,var(--color-ink-700)); display:block; margin-bottom:4px; }
.sige-table-wrap { overflow:auto; max-height: calc(100vh - 260px); background:var(--color-white); }
.sige-table { width:100%; min-width:1480px; border-collapse:separate; border-spacing:0; text-align:left; }
.sige-table th { position:sticky; top:0; z-index:2; background:var(--color-white); padding:14px 14px; font-size:.68rem; font-weight:700; color:var(--color-slate-500); text-transform:uppercase; letter-spacing:.08em; border-bottom:1px solid var(--sige-line); white-space:nowrap; }
.sige-table td { padding:14px; border-bottom:1px solid var(--color-slate-100); vertical-align:middle; background:var(--color-white); transition:background .18s ease; }
.sige-table tbody tr { cursor:pointer; transition:all .16s ease; }
.sige-table tbody tr:hover td { background:var(--color-slate-50); }
.sige-table tbody tr:hover td:first-child { box-shadow: inset 4px 0 0 var(--sige-primary); }
.sige-row-selected td { background:var(--color-info-50) !important; }
.sige-row-selected td:first-child { box-shadow: inset 4px 0 0 var(--sige-primary); }
.sige-row-sem-contacto td { background:var(--color-white); }
.sige-row-critical td { background:var(--color-warning-50); }
.sige-row-critical:hover td { background:var(--color-danger-50) !important; }
.sige-row-cronico td { background: linear-gradient(90deg, rgba(254,242,242,.75), var(--color-white) 55%); }
.sige-user-block { display:flex; align-items:center; gap:var(--space-3); min-width:260px; }
.sige-avatar { width:42px; height:42px; border-radius:50%; background:linear-gradient(135deg,var(--color-ink-100),var(--color-ink-200)); display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0; border:2px solid var(--color-white); box-shadow:var(--shadow-xs); }
.sige-avatar img { width:100%; height:100%; object-fit:cover; }
.sige-avatar-text { font-size:.85rem; font-weight:700; color:var(--color-slate-700); }
.sige-user-name { font-weight:700; color:var(--color-black); text-decoration:none; font-size:.94rem; line-height:1.25; }
.sige-user-name:hover { color:var(--sige-primary); text-decoration:underline; }
.sige-user-meta { font-size:.72rem; color:var(--color-slate-500); margin-top:4px; }
.sige-badge { display:inline-flex; align-items:center; gap:5px; padding:5px 8px; border-radius:var(--radius-sm); font-size:.68rem; font-weight:700; white-space:nowrap; line-height:1.1; }
.sige-badge-info { background:var(--color-ink-50); color:var(--color-slate-800); }
.sige-badge-error { background:var(--color-danger-100); color:var(--color-danger-700); }
.sige-badge-list { display:flex; flex-wrap:wrap; gap:var(--space-1); max-width:150px; }
.sige-badge-priority { border:1px solid transparent; }
.sige-badge-priority.critica { background:var(--color-danger-100); color:var(--color-danger-700); border-color:var(--color-danger-200); }
.sige-badge-priority.alta { background:var(--color-warning-50); color:var(--color-warning-800); border-color:var(--color-warning-200); }
.sige-badge-priority.media { background:var(--sg-theme-soft,var(--color-brand-50)); color:var(--color-info-600); border-color:var(--sg-theme-soft,var(--color-brand-50)); }
.sige-badge-priority.sem_contacto { background:var(--color-slate-50); color:var(--color-slate-800); border-color:var(--color-ink-200); }
.sige-priority-note, .sige-temporal-note, .sige-regua-modelo, .sige-action-sub { display:block; margin-top:5px; font-size:.68rem; color:var(--color-slate-500); line-height:1.3; max-width:210px; }
.sige-badge-behavior { border:1px solid transparent; }
.sige-badge-behavior.bom_pagador { background:var(--color-success-50); color:var(--color-success-800); border-color:var(--color-success-200); }
.sige-badge-behavior.irregular { background:var(--color-warning-50); color:var(--color-warning-800); border-color:var(--color-warning-300); }
.sige-badge-behavior.cronico { background:var(--color-danger-100); color:var(--color-danger-700); border-color:var(--color-danger-200); }
.sige-badge-regua.lembrete_cordial { background:var(--sg-theme-soft,var(--color-brand-50)); color:var(--sg-theme-primary,var(--color-brand-500)); }
.sige-badge-regua.cobranca_formal { background:var(--color-warning-100); color:var(--color-warning-800); }
.sige-badge-regua.aviso_prioritario { background:var(--color-danger-100); color:var(--color-danger-700); }
.sige-badge-regua.atualizar_contacto { background:var(--color-slate-50); color:var(--color-slate-800); border:1px dashed var(--color-slate-400); }
.sige-action-hint { display:flex; flex-direction:column; gap:var(--space-1); min-width:145px; }
.sige-action-chip { display:inline-flex; align-items:center; width:max-content; max-width:170px; padding:6px 10px; border-radius:var(--radius-pill); font-size:.68rem; font-weight:700; border:1px solid transparent; }
.sige-action-chip.urgente { background:var(--color-danger-100); color:var(--color-danger-700); border-color:var(--color-danger-200); }
.sige-action-chip.contactar { background:var(--color-success-100); color:var(--color-success-900); border-color:var(--color-success-200); }
.sige-action-chip.aguardar { background:var(--sg-theme-soft,var(--color-brand-50)); color:var(--color-info-600); border-color:var(--sg-theme-soft,var(--color-brand-50)); }
.sige-action-chip.sem_contacto { background:var(--color-warning-50); color:var(--color-warning-800); border-color:var(--color-warning-200); }
.sige-debt-amount { font-family:var(--sige-font-display); font-size:1rem; font-weight:700; color:var(--color-danger-700); white-space:nowrap; }
.sige-text-right { text-align:right; }
.sige-table-footer { padding:16px 18px; background:var(--color-white); display:grid; grid-template-columns:1fr auto; gap:var(--space-3); align-items:center; border-top:1px solid var(--sige-line); }
.sige-table-footer > div { grid-column:1 / -1; }
.sige-table-footer .sige-btn-success { justify-self:end; }
@media (max-width: 1500px) { .sige-stats-grid { grid-template-columns: repeat(3, minmax(220px,1fr)); } .sige-toolbar { grid-template-columns: repeat(3, minmax(190px,1fr)); } }
@media (max-width: 980px) { .sige-page { padding:14px; } .sige-hero { align-items:flex-start; flex-direction:column; } .sige-hero-badges { justify-content:flex-start; } .sige-hero-clean { grid-template-columns:1fr; align-items:flex-start; } .sige-hero-clean-badges { justify-content:flex-start; } .sige-stats-grid, .sige-toolbar, .sige-smart-actions, .sige-table-footer { grid-template-columns:1fr; } .sige-smart-actions-right { justify-content:flex-start; } }

/* v12.9.30.2: Header definitivo sem grelha de ícones decorativos */
.sige-hero .sige-hero-icon, .sige-hero-clean .sige-hero-clean-icon { display:none !important; }
.sige-hero, .sige-hero-clean { min-height:unset !important; }
.sige-hero-main, .sige-hero-clean-left { display:block !important; }


/* v12.9.30.5: Header definitivo sem ícones decorativos no hero.
   A Central de Cobranças mantém apenas título, subtítulo e badges.
   Este bloqueio remove qualquer resíduo antigo de ícones verticais/horizontais no header. */
.sige-hero-clean .sige-hero-clean-icon,
.sige-hero-clean .sige-hero-icon,
.sige-hero-clean-icons-row,
.sige-hero-clean-icons-vertical,
.sige-hero-icons,
.sige-hero-icons-vertical {
  display: none !important;
  visibility: hidden !important;
  width: 0 !important;
  height: 0 !important;
  overflow: hidden !important;
}
.sige-hero-clean {
  min-height: 118px !important;
  padding: 22px 28px !important;
}
.sige-hero-clean-left {
  display: block !important;
}


/* v12.9.30.6 - Header isolado da Central de Cobranças.
   Usa classes novas para não herdar CSS/cache antigo que criava ícones verticais. */
.sgcc-hero-v306 {
  margin-bottom:18px !important;
  padding:28px 34px !important;
  min-height:132px !important;
  border-radius:var(--radius-xl)!important;
  background:linear-gradient(135deg,var(--color-black) 0%,var(--color-ink-900) 62%,var(--color-slate-800) 100%) !important;
  color:var(--color-white) !important;
  box-shadow:var(--shadow-sm);
  display:grid !important;
  grid-template-columns:minmax(0,1fr) auto !important;
  gap:18px !important;
  align-items:center !important;
}
.sgcc-hero-v306 h1 {
  margin:0 !important;
  color:var(--color-white) !important;
  font-family:var(--sige-font-display, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif) !important;
  font-size:1.55rem !important;
  line-height:1.12 !important;
  font-weight:700 !important;
  letter-spacing:-.03em !important;
}
.sgcc-hero-v306 p {
  margin:10px 0 0 !important;
  max-width:760px !important;
  color:rgba(255,255,255,.78) !important;
  font-size:.9rem !important;
  line-height:1.45 !important;
}
.sgcc-hero-v306-badges {
  display:flex !important;
  align-items:center !important;
  justify-content:flex-end !important;
  gap:9px !important;
  flex-wrap:wrap !important;
}
.sgcc-hero-v306-badges span {
  display:inline-flex !important;
  align-items:center !important;
  gap:7px !important;
  min-height:32px !important;
  padding:7px 12px !important;
  border-radius:var(--radius-pill)!important;
  background:rgba(255,255,255,.13) !important;
  border:1px solid rgba(255,255,255,.2) !important;
  color:var(--color-white) !important;
  font-size:.74rem !important;
  font-weight:700 !important;
  white-space:nowrap !important;
}
.sgcc-hero-v306-badges span::before {
  content:'' !important;
  width:6px !important;
  height:6px !important;
  border-radius:var(--radius-pill)!important;
  background:var(--color-info-200) !important;
  box-shadow:var(--shadow-xs);
}
/* Bloqueio final: qualquer resíduo antigo de ícones decorativos dentro do hero antigo fica invisível. */
.sige-hero-clean > svg,
.sige-hero-clean-left > svg,
.sige-hero-clean-icon,
.sige-hero-icon,
.sige-hero-icons,
.sige-hero-icons-vertical,
.sige-hero-clean-icons-row,
.sige-hero-clean-icons-vertical {
  display:none !important;
  visibility:hidden !important;
  width:0 !important;
  height:0 !important;
  margin:0 !important;
  padding:0 !important;
  overflow:hidden !important;
}
@media (max-width:980px) {
  .sgcc-hero-v306 { grid-template-columns:1fr !important; padding:22px 24px !important; }
  .sgcc-hero-v306-badges { justify-content:flex-start !important; }
}


/* v12.9.30.7 - Correção definitiva: os ícones que persistiam não vinham de CSS externo;
   eram blocos .sige-stat-icon da própria Central de Cobranças.
   Esta página passa a usar KPIs textuais, sem ícones decorativos, para evitar colisão visual no header. */
.sige-page .sige-stat-icon,
.sige-page .sige-hero-icon,
.sige-page .sige-hero-clean-icon,
.sige-page .sige-hero-clean-icons-row,
.sige-page .sige-hero-clean-icons-vertical,
.sige-page .sige-hero-icons,
.sige-page .sige-hero-icons-vertical {
  display:none !important;
  visibility:hidden !important;
  width:0 !important;
  height:0 !important;
  min-width:0 !important;
  min-height:0 !important;
  margin:0 !important;
  padding:0 !important;
  overflow:hidden !important;
}
.sige-page .sige-stat-card {
  align-items:flex-start !important;
}


/* ==========================================================================
   SIGE SoftGenial v12.10.61 - Central de Cobranças: Compliance Visual Integral
   Referência mandatória: Painel Principal / Dashboard V2 MJS-grade.
   Escopo: camada visual apenas. Não altera fórmulas, saldos, cobrança,
   WhatsApp, filtros, queries, centro de custo ou regras financeiras.
   ========================================================================== */
body.sige-view-financeiro-devedores .sg-product-page-head{display:none!important;}
body.sige-view-financeiro-devedores .sg-app-page{padding-top:0!important;}
body.sige-view-financeiro-devedores .sige-page.sgcc-pro{
  --sgcc-blue:var(--sg-theme-primary,var(--color-brand-500));
  --sgcc-blue-dark:var(--sg-theme-primary-800,var(--color-ink-700));
  --sgcc-purple:var(--color-brand-500);
  --sgcc-purple-soft:var(--color-brand-50);
  --sgcc-ink:var(--color-black);
  --sgcc-muted:var(--color-slate-700);
  --sgcc-line:var(--color-ink-100);
  --sgcc-green:var(--color-success-500);
  --sgcc-red:var(--color-danger-500);
  --sgcc-amber:var(--color-warning-500);
  width:100%!important;
  max-width:none!important;
  min-height:auto!important;
  margin:0!important;
  padding:0 0 28px!important;
  background:transparent!important;
  color:var(--sgcc-ink)!important;
  font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif)!important;
}
body.sige-view-financeiro-devedores .sgcc-pro *{box-sizing:border-box!important;}
body.sige-view-financeiro-devedores .sgcc-pro svg{
  stroke:currentColor!important;
  color:currentColor!important;
  fill:none!important;
  opacity:1!important;
}

/* HERO - espelho do Painel Principal */
body.sige-view-financeiro-devedores .sgcc-pro-hero{
  position:relative!important;
  overflow:hidden!important;
  min-height:178px!important;
  border-radius:var(--radius-xl)!important;
  background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-info-50) 100%)!important;
  border:1px solid rgba(92,64,187,.12)!important;
  box-shadow:var(--shadow-lg);
  padding:32px 34px!important;
  margin:0 0 18px!important;
  display:grid!important;
  grid-template-columns:minmax(0,1.04fr) minmax(340px,.96fr)!important;
  gap:22px!important;
  align-items:center!important;
  color:var(--sgcc-ink)!important;
}
body.sige-view-financeiro-devedores .sgcc-pro-hero:before{
  content:""!important;
  position:absolute!important;
  inset:auto -80px -130px auto!important;
  width:420px!important;
  height:300px!important;
  border-radius:var(--radius-pill)!important;
  background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%)!important;
  pointer-events:none!important;
}
body.sige-view-financeiro-devedores .sgcc-pro-hero:after{display:none!important;content:none!important;}
body.sige-view-financeiro-devedores .sgcc-pro-hero-text,
body.sige-view-financeiro-devedores .sgcc-pro-flow{
  position:relative!important;
  z-index:1!important;
}
body.sige-view-financeiro-devedores .sgcc-pro-kicker{
  display:inline-flex!important;
  align-items:center!important;
  gap:var(--space-2)!important;
  margin:0 0 10px!important;
  padding:0!important;
  border:0!important;
  border-radius:0!important;
  background:transparent!important;
  color:var(--sgcc-blue)!important;
  font-size:12px!important;
  line-height:1.2!important;
  font-weight:700!important;
  letter-spacing:.11em!important;
  text-transform:uppercase!important;
  box-shadow:none!important;
}
body.sige-view-financeiro-devedores .sgcc-pro-kicker:before,
body.sige-view-financeiro-devedores .sgcc-pro-kicker:after{display:none!important;content:none!important;}
body.sige-view-financeiro-devedores .sgcc-pro-kicker svg{
  width:18px!important;
  height:18px!important;
}
body.sige-view-financeiro-devedores .sgcc-pro-hero h1{
  margin:0!important;
  max-width:650px!important;
  color:var(--color-black)!important;
  font-size:31px!important;
  line-height:1.08!important;
  font-weight:700!important;
  letter-spacing:-.04em!important;
  font-family:inherit!important;
}
body.sige-view-financeiro-devedores .sgcc-pro-hero p{
  max-width:650px!important;
  margin:var(--space-3) 0 0!important;
  color:var(--color-slate-700)!important;
  font-size:15px!important;
  line-height:1.65!important;
  font-weight:500!important;
}
body.sige-view-financeiro-devedores .sgcc-pro-actions{
  display:flex!important;
  flex-wrap:wrap!important;
  gap:var(--space-3)!important;
  margin-top:24px!important;
}
body.sige-view-financeiro-devedores .sgcc-pro-btn{
  min-height:46px!important;
  display:inline-flex!important;
  align-items:center!important;
  justify-content:center!important;
  gap:10px!important;
  border-radius:var(--radius-md)!important;
  padding:0 22px!important;
  font-size:var(--fs-base)!important;
  font-weight:700!important;
  text-decoration:none!important;
  border:1px solid transparent!important;
  transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease!important;
}
body.sige-view-financeiro-devedores .sgcc-pro-btn svg{
  width:18px!important;
  height:18px!important;
}
body.sige-view-financeiro-devedores .sgcc-pro-btn-primary{
  background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-800,var(--color-ink-700)))!important;
  color:var(--color-white)!important;
  box-shadow:var(--shadow-md);
}
body.sige-view-financeiro-devedores .sgcc-pro-btn-light{
  background:var(--color-white)!important;
  color:var(--color-ink-900)!important;
  border-color:var(--color-ink-100)!important;
  box-shadow:var(--shadow-sm);
}
body.sige-view-financeiro-devedores .sgcc-pro-btn:hover{
  transform:translateY(-1px)!important;
  box-shadow:var(--shadow-md);
}

/* Flow no lugar do painel lateral - sem pontinhos verdes/decorativos */
body.sige-view-financeiro-devedores .sgcc-pro-flow{
  min-height:148px!important;
  border-radius:var(--radius-xl)!important;
  background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.18))!important;
  padding:22px!important;
  overflow:hidden!important;
  display:flex!important;
  flex-direction:column!important;
  justify-content:center!important;
  align-items:stretch!important;
  gap:10px!important;
  border:1px solid rgba(92,64,187,.08)!important;
  box-shadow:none!important;
}
body.sige-view-financeiro-devedores .sgcc-pro-flow:before{
  content:""!important;
  position:absolute!important;
  right:22px!important;
  bottom:16px!important;
  width:112px!important;
  height:92px!important;
  border-radius:22px 22px 12px 12px!important;
  background:rgba(109,93,252,.16)!important;
  box-shadow:inset 0 0 0 2px rgba(109,93,252,.12)!important;
}
body.sige-view-financeiro-devedores .sgcc-pro-flow span{
  position:relative!important;
  z-index:1!important;
  display:flex!important;
  align-items:center!important;
  gap:9px!important;
  min-height:38px!important;
  padding:var(--space-2) var(--space-3)!important;
  border-radius:var(--radius-md)!important;
  background:rgba(255,255,255,.72)!important;
  border:1px solid rgba(255,255,255,.74)!important;
  color:var(--color-slate-700)!important;
  font-size:var(--fs-sm)!important;
  font-weight:700!important;
  white-space:normal!important;
  box-shadow:var(--shadow-sm);
}
body.sige-view-financeiro-devedores .sgcc-pro-flow span:before{
  display:none!important;
  content:none!important;
}

/* KPIs - modelo dos cartões do Painel Principal */
body.sige-view-financeiro-devedores .sige-stats-grid{
  display:grid!important;
  grid-template-columns:repeat(5,minmax(0,1fr))!important;
  gap:var(--space-4)!important;
  margin:0 0 18px!important;
  animation:none!important;
}
body.sige-view-financeiro-devedores .sige-stat-card{
  position:relative!important;
  overflow:hidden!important;
  display:grid!important;
  grid-template-columns:auto minmax(0,1fr)!important;
  align-items:center!important;
  gap:var(--space-4)!important;
  min-height:104px!important;
  padding:18px 20px!important;
  border-radius:var(--radius-xl)!important;
  background:var(--color-white)!important;
  border:1px solid rgba(28,32,54,.08)!important;
  box-shadow:var(--shadow-md);
}
body.sige-view-financeiro-devedores .sige-stat-card:hover{
  transform:translateY(-1px)!important;
  box-shadow:var(--shadow-lg);
}
body.sige-view-financeiro-devedores .sige-stat-card:after{
  content:""!important;
  position:absolute!important;
  right:-28px!important;
  top:-34px!important;
  width:92px!important;
  height:92px!important;
  border-radius:50%!important;
  background:var(--kpi-soft,var(--color-brand-50))!important;
}
body.sige-view-financeiro-devedores .sgcc-kpi-icon{
  width:52px!important;
  height:52px!important;
  border-radius:var(--radius-lg)!important;
  display:flex!important;
  align-items:center!important;
  justify-content:center!important;
  background:var(--kpi-soft,var(--color-brand-50))!important;
  color:var(--kpi-color,var(--color-brand-500))!important;
  position:relative!important;
  z-index:1!important;
  flex:0 0 auto!important;
}
body.sige-view-financeiro-devedores .sgcc-kpi-icon svg{
  width:24px!important;
  height:24px!important;
}
body.sige-view-financeiro-devedores .sige-stat-card > div{
  position:relative!important;
  z-index:1!important;
}
body.sige-view-financeiro-devedores .sige-stat-label{
  margin:0 0 6px!important;
  color:var(--color-slate-600)!important;
  font-size:var(--fs-sm)!important;
  font-weight:600!important;
  text-transform:none!important;
  letter-spacing:0!important;
}
body.sige-view-financeiro-devedores .sige-stat-value{
  margin:0!important;
  color:var(--color-black)!important;
  font-size:27px!important;
  line-height:1!important;
  font-weight:700!important;
  letter-spacing:-.03em!important;
  font-family:inherit!important;
}
body.sige-view-financeiro-devedores .sige-stat-value span{
  font-size:var(--fs-sm)!important;
  color:var(--color-ink-400)!important;
  font-weight:700!important;
}
body.sige-view-financeiro-devedores .sgcc-kpi-debt{--kpi-color:var(--color-danger-500)!important;--kpi-soft:var(--color-danger-50)!important;}
body.sige-view-financeiro-devedores .sgcc-kpi-students{--kpi-color:var(--color-brand-500)!important;--kpi-soft:var(--color-brand-50)!important;}
body.sige-view-financeiro-devedores .sgcc-kpi-critical{--kpi-color:var(--color-warning-500)!important;--kpi-soft:var(--color-warning-50)!important;}
body.sige-view-financeiro-devedores .sgcc-kpi-recurring{--kpi-color:var(--color-success-500)!important;--kpi-soft:var(--color-success-100)!important;}
body.sige-view-financeiro-devedores .sgcc-kpi-contact{--kpi-color:var(--sg-theme-primary,var(--color-brand-500))!important;--kpi-soft:var(--color-info-50)!important;}

/* Cartões, filtros e tabela */
body.sige-view-financeiro-devedores .sige-card{
  background:var(--color-white)!important;
  border:1px solid rgba(30,34,60,.08)!important;
  border-radius:var(--radius-xl)!important;
  box-shadow:var(--shadow-md);
  overflow:hidden!important;
}
body.sige-view-financeiro-devedores .sgcc-card-head{
  display:flex!important;
  align-items:center!important;
  justify-content:space-between!important;
  gap:var(--space-4)!important;
  padding:20px 22px 14px!important;
}
body.sige-view-financeiro-devedores .sgcc-card-head > div{
  display:flex!important;
  align-items:center!important;
  gap:var(--space-3)!important;
  min-width:0!important;
}
body.sige-view-financeiro-devedores .sgcc-card-icon{
  width:40px!important;
  height:40px!important;
  border-radius:var(--radius-md)!important;
  display:flex!important;
  align-items:center!important;
  justify-content:center!important;
  flex:0 0 auto!important;
  background:var(--color-brand-50)!important;
  color:var(--color-brand-500)!important;
}
body.sige-view-financeiro-devedores .sgcc-card-icon svg{
  width:20px!important;
  height:20px!important;
}
body.sige-view-financeiro-devedores .sgcc-card-head h2{
  margin:0!important;
  color:var(--color-ink-500)!important;
  font-size:17px!important;
  line-height:1.1!important;
  font-weight:700!important;
  letter-spacing:-.03em!important;
}
body.sige-view-financeiro-devedores .sgcc-card-head p{
  margin:5px 0 0!important;
  color:var(--color-ink-400)!important;
  font-size:12px!important;
  font-weight:600!important;
}
body.sige-view-financeiro-devedores .sgcc-toolbar{
  display:grid!important;
  grid-template-columns:repeat(4,minmax(180px,1fr)) auto auto!important;
  gap:var(--space-3)!important;
  align-items:end!important;
  padding:0 22px 20px!important;
  margin:0!important;
  background:var(--color-white)!important;
}
body.sige-view-financeiro-devedores .sige-form-group{
  display:flex!important;
  flex-direction:column!important;
  gap:7px!important;
  margin:0!important;
}
body.sige-view-financeiro-devedores .sige-label{
  color:var(--color-slate-600)!important;
  font-size:var(--fs-xs)!important;
  font-weight:700!important;
  letter-spacing:.07em!important;
  text-transform:uppercase!important;
}
body.sige-view-financeiro-devedores .sige-input,
body.sige-view-financeiro-devedores .sige-select{
  width:100%!important;
  min-height:44px!important;
  border:1px solid var(--color-ink-100)!important;
  background:var(--color-white)!important;
  border-radius:var(--radius-md)!important;
  padding:0 13px!important;
  color:var(--color-ink-500)!important;
  font-size:var(--fs-sm)!important;
  font-weight:600!important;
  box-shadow:var(--shadow-sm);
  outline:none!important;
}
body.sige-view-financeiro-devedores .sige-input:focus,
body.sige-view-financeiro-devedores .sige-select:focus{
  border-color:rgba(90,63,214,.55)!important;
  box-shadow:var(--shadow-xs);
}
body.sige-view-financeiro-devedores .sige-btn{
  display:inline-flex!important;
  align-items:center!important;
  justify-content:center!important;
  gap:var(--space-2)!important;
  min-height:40px!important;
  padding:0 15px!important;
  border-radius:var(--radius-md)!important;
  font-size:12.5px!important;
  font-weight:700!important;
  text-decoration:none!important;
  cursor:pointer!important;
  border:1px solid transparent!important;
}
body.sige-view-financeiro-devedores .sige-btn-primary,
body.sige-view-financeiro-devedores .sige-btn-success{
  background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-800,var(--color-ink-700)))!important;
  color:var(--color-white)!important;
  box-shadow:var(--shadow-sm);
}
body.sige-view-financeiro-devedores .sige-btn-ghost{
  background:var(--color-white)!important;
  color:var(--color-ink-900)!important;
  border-color:var(--color-ink-100)!important;
  box-shadow:var(--shadow-sm);
}
body.sige-view-financeiro-devedores .sige-smart-actions{
  margin:0!important;
  padding:16px 22px!important;
  background:var(--color-white)!important;
  border-top:1px solid var(--color-slate-100)!important;
  border-bottom:1px solid var(--color-slate-100)!important;
  display:grid!important;
  grid-template-columns:1fr!important;
  gap:var(--space-3)!important;
}
body.sige-view-financeiro-devedores .sige-table-wrap{
  margin:0!important;
  border-radius:0 0 22px 22px!important;
  overflow:auto!important;
  background:var(--color-white)!important;
}
body.sige-view-financeiro-devedores .sige-table{
  width:100%!important;
  min-width:1180px!important;
  border-collapse:separate!important;
  border-spacing:0!important;
  background:var(--color-white)!important;
}
body.sige-view-financeiro-devedores .sige-table th{
  background:var(--color-slate-50)!important;
  color:var(--color-slate-800)!important;
  border-bottom:1px solid var(--color-slate-100)!important;
  text-transform:uppercase!important;
  letter-spacing:.04em!important;
  font-size:12px!important;
  font-weight:700!important;
}
body.sige-view-financeiro-devedores .sige-table td{
  border-bottom:1px solid var(--color-info-50)!important;
}
body.sige-view-financeiro-devedores .sige-table tbody tr:hover td{
  background:var(--color-white)!important;
}
body.sige-view-financeiro-devedores .sige-table-footer{
  background:var(--color-white)!important;
  border-top:1px solid var(--color-slate-100)!important;
  padding:16px 22px!important;
}

@media(max-width:1320px){
  body.sige-view-financeiro-devedores .sige-stats-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important;}
  body.sige-view-financeiro-devedores .sgcc-toolbar{grid-template-columns:repeat(3,minmax(180px,1fr))!important;}
}
@media(max-width:980px){
  body.sige-view-financeiro-devedores .sgcc-pro-hero{grid-template-columns:1fr!important;padding:26px 24px!important;}
  body.sige-view-financeiro-devedores .sige-stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;}
  body.sige-view-financeiro-devedores .sgcc-toolbar{grid-template-columns:1fr 1fr!important;}
}
@media(max-width:720px){
  body.sige-view-financeiro-devedores .sgcc-pro-hero h1{font-size:var(--fs-xl)!important;}
  body.sige-view-financeiro-devedores .sgcc-pro-actions{display:grid!important;grid-template-columns:1fr!important;}
  body.sige-view-financeiro-devedores .sgcc-pro-btn{width:100%!important;}
  body.sige-view-financeiro-devedores .sige-stats-grid{grid-template-columns:1fr!important;}
  body.sige-view-financeiro-devedores .sgcc-toolbar{grid-template-columns:1fr!important;}
  body.sige-view-financeiro-devedores .sgcc-toolbar .sige-btn{width:100%!important;}
}

</style>
<div class="wrap sige-page sgcc-pro">

<?php if (!empty($sgdc_dados['celulas'])): $sgdc_url_base = remove_query_arg('mes'); ?>
<div class="sgdc-faixa">
    <div class="sgdc-topo">
        <span class="sgdc-titulo">Devedores por mês · <?php echo (int)$sgdc_dados['ano']; ?></span>
        <span class="sgdc-legenda">Clique num mês para filtrar a lista. Em aberto no ano: <strong><?php echo esc_html(number_format((float)$sgdc_dados['total_aberto'], 2, ',', '.')); ?> MT</strong></span>
    </div>
    <div class="sgdc-grelha">
        <?php foreach ($sgdc_dados['celulas'] as $sgdc_c): ?>
            <a class="sgdc-tile sgdc-n<?php echo (int)$sgdc_c['nivel']; ?><?php echo $sgdc_c['corrente'] ? ' sgdc-corrente' : ''; ?><?php echo $sgdc_c['activo'] ? ' sgdc-activo' : ''; ?>"
               href="<?php echo esc_url(add_query_arg('mes', $sgdc_c['ym'], $sgdc_url_base)); ?>"
               title="<?php echo esc_attr($sgdc_c['rotulo'] . ': ' . (int)$sgdc_c['devedores'] . ' devedor(es) · ' . number_format((float)$sgdc_c['aberto'], 2, ',', '.') . ' MT em aberto'); ?>">
                <span class="sgdc-rotulo"><?php echo esc_html($sgdc_c['rotulo']); ?></span>
                <span class="sgdc-num"><?php echo (int)$sgdc_c['devedores']; ?></span>
                <span class="sgdc-aberto"><?php echo esc_html(number_format((float)$sgdc_c['aberto'], 0, ',', '.')); ?> MT</span>
            </a>
        <?php endforeach; ?>
        <?php if (!empty($sgdc_dados['tem_filtro_mes'])): ?>
            <a class="sgdc-tile sgdc-reset" href="<?php echo esc_url($sgdc_url_base); ?>" title="Remover o filtro de mês e ver o ano inteiro">
                <span class="sgdc-rotulo">Filtro</span>
                <span class="sgdc-num">Ano inteiro</span>
            </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
 <section class="sgcc-hero-v306 sgcc-pro-hero" aria-label="Central de Cobranças">
  <div class="sgcc-hero-v306-text sgcc-pro-hero-text">
    <div class="sgcc-pro-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('trending') : ''; ?> Gestão financeira</div>
    <h1>Central de Cobranças</h1>
    <p>Acompanhe valores em aberto, seleccione os alunos a contactar e envie cobranças com mais clareza e segurança.</p>
    <div class="sgcc-pro-actions">
      <a class="sgcc-pro-btn sgcc-pro-btn-primary" href="?page=sige-app&view=financeiro-pagamentos"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('money') : ''; ?> Registar Pagamento</a>
      <button type="button" class="sgcc-pro-btn sgcc-pro-btn-light" id="sgcc-scroll-lista"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('clipboard') : ''; ?> Ver Lista de Cobrança</button>
      <a class="sgcc-pro-btn sgcc-pro-btn-light" target="_blank" rel="noopener" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['sige_dev_print' => 'lista'], home_url('/')), 'sige_dev_print')); ?>"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('file') : ''; ?> Imprimir lista (PDF)</a>
    </div>
  </div>
  <div class="sgcc-hero-v306-badges sgcc-pro-flow" aria-label="Fluxo da cobrança">
    <span>1. Filtrar devedores</span>
    <span>2. Seleccionar alunos</span>
    <span>3. Confirmar envio</span>
  </div>
</section>
 <div class="sige-stats-grid">
 <div class="sige-stat-card sgcc-kpi-debt">
 <span class="sgcc-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('wallet') : ''; ?></span><div>
 <div class="sige-stat-label">Dívida total acumulada</div>
 <div class="sige-stat-value"><?php echo number_format($total_geral_divida, 2, ',', '.'); ?> <span style="font-size:1rem;color:var(--sige-slate-400);">MT</span></div>
</div>
</div>
 <div class="sige-stat-card sgcc-kpi-students">
 <span class="sgcc-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('users') : ''; ?></span><div>
 <div class="sige-stat-label">Alunos com dívida</div>
 <div class="sige-stat-value"><?php echo count($lista_final); ?></div>
</div>
</div>
 <div class="sige-stat-card sgcc-kpi-critical">
 <span class="sgcc-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('alert') : ''; ?></span><div>
 <div class="sige-stat-label">Casos críticos</div>
 <div class="sige-stat-value"><?php echo (int)($stats_prioridade['critica'] ?? 0); ?></div>
</div>
</div>
 <div class="sige-stat-card sgcc-kpi-recurring">
 <span class="sgcc-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('activity') : ''; ?></span><div>
 <div class="sige-stat-label">Atraso recorrente</div>
 <div class="sige-stat-value"><?php echo (int)($stats_comportamento['cronico'] ?? 0); ?></div>
</div>
</div>
 <div class="sige-stat-card sgcc-kpi-contact">
 <span class="sgcc-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('message') : ''; ?></span><div>
 <div class="sige-stat-label">Sem contacto</div>
 <div class="sige-stat-value"><?php echo (int)($stats_prioridade['sem_contacto'] ?? 0); ?></div>
</div>
</div>
</div>
 <div class="sige-card sgcc-filter-card">
 <div class="sgcc-card-head"><div><span class="sgcc-card-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('grid') : ''; ?></span><div><h2>Filtros da cobrança</h2><p>Refine a lista por turma, mês, centro e prioridade.</p></div></div></div>
 <form method="get" class="sige-toolbar sgcc-toolbar">
 <input type="hidden" name="page" value="sige-app">
 <input type="hidden" name="view" value="financeiro-devedores">
 
 <div class="sige-form-group">
 <label class="sige-label">Turma</label>
 <select name="turma_id" class="sige-select" style="min-width: 220px;">
 <option value="">-- Todas as Turmas --</option>
 <?php foreach($turmas as $t): ?>
 <option value="<?php echo esc_attr($t->id); ?>" <?php selected($turma_filtro, $t->id); ?>>
 <?php echo esc_html($t->classe . ' - ' . $t->nome); ?>
</option>
 <?php endforeach; ?>
</select>
</div>
 <div class="sige-form-group">
 <label class="sige-label">Mês Referência</label>
 <input type="month" name="mes" value="<?php echo esc_attr($mes_filtro); ?>" class="sige-input">
</div>
 <?php
   // [v13.4.0 BLOCO 3] Filtro de centro (silencioso em tenants single-center)
   if (function_exists('sige_fin_render_filtro_centro')) {
     $__sel = sige_fin_render_filtro_centro([
       'selected'      => $centro_id_filtro,
       'include_label' => false,
       'class'         => 'sige-select',
       'style'         => 'min-width: 200px;',
     ]);
     if ($__sel !== '') {
       echo '<div class="sige-form-group"><label class="sige-label">Centro</label>' . $__sel . '</div>';
     }
   }
 ?>
  <div class="sige-form-group">
 <label class="sige-label">Situação do aluno</label>
 <select name="situacao_aluno" class="sige-select" style="min-width: 210px;">
 <option value="activos" <?php selected($situacao_aluno_filtro, 'activos'); ?>>Activos</option>
 <option value="transferidos" <?php selected($situacao_aluno_filtro, 'transferidos'); ?>>Transferidos/inactivos com saldo</option>
 <option value="todos" <?php selected($situacao_aluno_filtro, 'todos'); ?>>Todos</option>
</select>
</div>
 <div class="sige-form-group">
 <label class="sige-label">Prioridade</label>
 <select name="prioridade_cobranca" class="sige-select" style="min-width: 190px;">
 <option value="" <?php selected($prioridade_filtro, ''); ?>>-- Todas --</option>
 <option value="critica" <?php selected($prioridade_filtro, 'critica'); ?>>Crítica</option>
 <option value="alta" <?php selected($prioridade_filtro, 'alta'); ?>>Alta</option>
 <option value="media" <?php selected($prioridade_filtro, 'media'); ?>>Média</option>
 <option value="sem_contacto" <?php selected($prioridade_filtro, 'sem_contacto'); ?>>Sem contacto</option>
</select>
</div>
 <div class="sige-form-group">
 <label class="sige-label">Comportamento</label>
 <select name="comportamento_cobranca" class="sige-select" style="min-width: 190px;">
 <option value="" <?php selected($comportamento_filtro, ''); ?>>-- Todos --</option>
 <option value="bom_pagador" <?php selected($comportamento_filtro, 'bom_pagador'); ?>>Bom pagador</option>
 <option value="irregular" <?php selected($comportamento_filtro, 'irregular'); ?>>Irregular</option>
 <option value="cronico" <?php selected($comportamento_filtro, 'cronico'); ?>>Crónico</option>
</select>
</div>
 <div class="sige-form-group">
 <label class="sige-label">Régua</label>
 <select name="regua_cobranca" class="sige-select" style="min-width: 210px;">
 <option value="" <?php selected($regua_filtro, ''); ?>>-- Todas --</option>
 <option value="lembrete_cordial" <?php selected($regua_filtro, 'lembrete_cordial'); ?>>Lembrete cordial</option>
 <option value="cobranca_formal" <?php selected($regua_filtro, 'cobranca_formal'); ?>>Cobrança formal</option>
 <option value="aviso_prioritario" <?php selected($regua_filtro, 'aviso_prioritario'); ?>>Aviso prioritário</option>
 <option value="atualizar_contacto" <?php selected($regua_filtro, 'atualizar_contacto'); ?>>Actualizar contacto</option>
</select>
</div>
 <button type="submit" class="sige-btn sige-btn-primary">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> Filtrar
</button>
 <?php if($turma_filtro || $mes_filtro || $centro_id_filtro || $prioridade_filtro || $comportamento_filtro || $regua_filtro): ?>
 <a href="?page=sige-app&view=financeiro-devedores" class="sige-btn sige-btn-ghost">Limpar</a>
 <?php endif; ?>
</form>
 <form method="post" id="sgcc-cobranca-form">
 <?php wp_nonce_field('enviar_cobranca', 'sige_cobrar_nonce'); ?>

 <div class="sige-smart-actions">
 <div class="sige-smart-actions-left">
 <strong style="font-size:0.82rem;color:var(--sige-slate-700);margin-right:4px;">Seleccionar rapidamente:</strong>
 <button type="button" class="sige-btn sige-btn-ghost sige-btn-mini" data-select-priority="critica">Críticos</button>
 <button type="button" class="sige-btn sige-btn-ghost sige-btn-mini" data-select-priority="alta">Alta</button>
 <button type="button" class="sige-btn sige-btn-ghost sige-btn-mini" data-select-priority="sem_contacto">Sem contacto</button>
 <button type="button" class="sige-btn sige-btn-ghost sige-btn-mini" data-select-behavior="cronico">Crónicos</button>
 <button type="button" class="sige-btn sige-btn-ghost sige-btn-mini" data-select-regua="aviso_prioritario">Aviso prioritário</button>
 <button type="button" class="sige-btn sige-btn-ghost sige-btn-mini" data-select-regua="cobranca_formal">Cobrança formal</button>
 <button type="button" class="sige-btn sige-btn-ghost sige-btn-mini" id="cb-clear-selection">Limpar selecção</button>
</div>
 <div class="sige-smart-actions-right sige-selection-summary" aria-live="polite">
 <span class="sige-selection-pill"><span id="cb-selected-count">0</span> seleccionado(s)</span>
 <span class="sige-selection-pill danger"><span id="cb-selected-debt">0,00</span> <?php echo esc_html(sige_moeda()); ?></span>
 <span class="sige-selection-pill warn"><span id="cb-selected-no-contact">0</span> sem contacto</span>
 <span class="sige-selection-pill danger"><span id="cb-selected-critical">0</span> crítico(s)</span>
 <button type="button" class="sige-btn sige-btn-ghost sige-btn-mini" id="cb-print-lista" title="Gerar lista limpa para impressão">Lista de cobrança</button>
</div>
 <div class="sige-selection-note" id="cb-selection-note">Atenção: há aluno(s) sem contacto seleccionado(s). Actualize o contacto antes de tentar cobrança por WhatsApp/e-mail.</div>
 <div class="sige-operational-panel" id="cb-operational-panel" aria-live="polite">
 <strong>Resumo operacional da cobrança seleccionada</strong>
 <span id="cb-operational-text">Seleccione alunos para ver a recomendação operacional.</span>
</div>
</div>
 <div class="sige-table-wrap">
 <table class="sige-table">
 <thead>
 <tr>
 <th style="width: 40px; text-align:center;"><input type="checkbox" id="cb-select-all-1" style="accent-color:var(--sige-primary); cursor:pointer;"></th>
 <th>Aluno</th>
 <th>Turma</th>
 <th>Prioridade</th>
 <th>Comportamento</th>
 <th>Meses em Atraso</th>
 <th>Contacto</th>
 <th>Régua</th>
 <th>Sugestão</th>
 <th class="sige-u-tar">Dívida</th>
 <th class="sige-u-tar">Acções</th>
</tr>
</thead>
 <tbody>
 <?php if(empty($lista_final)): ?>
 <tr><td colspan="11" style="padding:40px; text-align:center; color:var(--sige-slate-500);">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:40px;height:40px;margin-bottom:12px;opacity:0.5;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><br>
 Nenhuma dívida encontrada com estes filtros. ✓
</td></tr>
 <?php else: ?>
 <?php foreach($lista_final as $aluno): 
 $foto_url = !empty($aluno->foto) ? esc_url($aluno->foto) : '';
 $nomes = explode(' ', trim($aluno->nome_completo));
 $iniciais = (count($nomes) >= 2) ? strtoupper(substr($nomes[0], 0, 1) . substr(end($nomes), 0, 1)) : strtoupper(substr($aluno->nome_completo, 0, 2));
 
 $classe_display = $aluno->classe ?: $aluno->nivel_ensino ?: '';
 $turma_display = $aluno->turma_nome ?: 'Sem Turma';
 $tel = $aluno->whatsapp_notificacoes ?: $aluno->telemovel_pai;
 $prioridade_slug_row = $aluno->cobranca_prioridade_slug ?? 'media';
 $sugestao_slug = 'aguardar';
 $sugestao_label = 'Aguardar';
 $sugestao_sub = 'Atraso leve; acompanhar no ciclo normal.';
 if (empty($aluno->cobranca_tem_contacto)) {
  $sugestao_slug = 'sem_contacto';
  $sugestao_label = 'Actualizar contacto';
  $sugestao_sub = 'Não enviar cobrança até confirmar telefone/e-mail.';
 } elseif ($prioridade_slug_row === 'critica') {
  $sugestao_slug = 'urgente';
  $sugestao_label = 'Cobrança urgente';
  $sugestao_sub = 'Contactar por chamada/secretaria antes de nova ronda.';
 } elseif ($prioridade_slug_row === 'alta') {
  $sugestao_slug = 'contactar';
  $sugestao_label = 'Contactar';
  $sugestao_sub = 'Pronto para cobrança nesta ronda.';
 }
 $row_classes = [];
 if (empty($aluno->cobranca_tem_contacto)) $row_classes[] = 'sige-row-sem-contacto';
 if ($prioridade_slug_row === 'critica') $row_classes[] = 'sige-row-critical';
 if (($aluno->comportamento_cobranca_slug ?? '') === 'cronico') $row_classes[] = 'sige-row-cronico';
 ?>
 <tr class="<?php echo esc_attr(implode(' ', $row_classes)); ?>">
 <td class="sige-u-tac">
 <input type="checkbox" name="aluno_ids[]" value="<?php echo esc_attr($aluno->aluno_id); ?>" data-priority="<?php echo esc_attr($aluno->cobranca_prioridade_slug ?? 'media'); ?>" data-debt="<?php echo esc_attr(number_format((float)$aluno->divida_total, 2, '.', '')); ?>" data-has-contact="<?php echo !empty($aluno->cobranca_tem_contacto) ? '1' : '0'; ?>" data-student-name="<?php echo esc_attr($aluno->nome_completo); ?>" data-class="<?php echo esc_attr(trim($classe_display . ' - ' . $turma_display, ' -')); ?>" data-contact="<?php echo esc_attr($tel ?: 'Não definido'); ?>" data-action="<?php echo esc_attr($sugestao_label); ?>" data-behavior="<?php echo esc_attr($aluno->comportamento_cobranca_slug ?? 'bom_pagador'); ?>" data-regua="<?php echo esc_attr($aluno->regua_cobranca_slug ?? 'lembrete_cordial'); ?>" data-regua-label="<?php echo esc_attr($aluno->regua_cobranca_label ?? 'Lembrete cordial'); ?>" data-regua-modelo="<?php echo esc_attr($aluno->regua_cobranca_modelo ?? ''); ?>" data-consecutive-months="<?php echo esc_attr((int)($aluno->meses_consecutivos_atraso ?? 0)); ?>" data-process="<?php echo esc_attr($aluno->numero_processo); ?>" style="accent-color:var(--sige-primary); cursor:pointer;">
</td>
 <td>
 <div class="sige-user-block">
 <div class="sige-avatar">
 <?php if($foto_url): ?><img src="<?php echo $foto_url; ?>" alt=""><?php else: ?><span class="sige-avatar-text"><?php echo esc_html($iniciais); ?></span><?php endif; ?>
</div>
 <div>
 <a href="?page=sige-app&view=alunos_lista&edit=<?php echo esc_attr($aluno->aluno_id); ?>" class="sige-user-name"><?php echo esc_html($aluno->nome_completo); ?></a>
 <div class="sige-user-meta">Proc: #<?php echo esc_html($aluno->numero_processo); ?></div>
</div>
</div>
</td>
 <td>
 <span class="sige-badge sige-badge-info">
 <?php echo esc_html($classe_display . ' - ' . $turma_display); ?>
</span>
</td>
 <td>
 <span class="sige-badge sige-badge-priority <?php echo esc_attr($aluno->cobranca_prioridade_slug ?? 'media'); ?>">
 <?php echo esc_html($aluno->cobranca_prioridade_label ?? 'Média'); ?>
</span>
 <span class="sige-priority-note">
 <?php
 $dias_info = (int)($aluno->dias_atraso ?? 0);
 $pend_info = (int)($aluno->qtd_pendencias_reais ?? 0);
 echo esc_html(($dias_info > 0 ? $dias_info . ' dia(s) em atraso · ' : '') . $pend_info . ' pendência(s)');
 ?>
 </span>
</td>
 <td>
 <span class="sige-badge sige-badge-behavior <?php echo esc_attr($aluno->comportamento_cobranca_slug ?? 'bom_pagador'); ?>">
 <?php echo esc_html($aluno->comportamento_cobranca_label ?? 'Bom pagador'); ?>
</span>
 <span class="sige-temporal-note"><?php echo esc_html($aluno->comportamento_cobranca_hint ?? ''); ?></span>
</td>
 <td>
 <div class="sige-badge-list">
 <?php foreach((array)$aluno->meses_desc as $mes): ?>
 <span class="sige-badge sige-badge-error"><?php echo esc_html($mes); ?></span>
 <?php endforeach; ?>
</div>
 <span class="sige-temporal-note">
 <?php echo esc_html((int)($aluno->meses_consecutivos_atraso ?? 0) . ' mês(es) consecutivo(s) · ' . (int)($aluno->total_meses_atraso ?? 0) . ' no total'); ?>
</span>
</td>
 <td>
 <div style="display:flex;align-items:center;gap:6px;font-size:0.85rem;color:var(--sige-slate-600);">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
 <?php echo $tel ? esc_html($tel) : '<span class="sige-badge sige-badge-priority sem_contacto">Não definido</span>'; ?>
</div>
</td>
 <td>
 <span class="sige-badge sige-badge-regua <?php echo esc_attr($aluno->regua_cobranca_slug ?? 'lembrete_cordial'); ?>">
 <?php echo esc_html($aluno->regua_cobranca_label ?? 'Lembrete cordial'); ?>
</span>
 <span class="sige-regua-modelo"><?php echo esc_html($aluno->regua_cobranca_hint ?? ''); ?></span>
</td>
 <td>
 <div class="sige-action-hint">
 <span class="sige-action-chip <?php echo esc_attr($sugestao_slug); ?>"><?php echo esc_html($sugestao_label); ?></span>
 <span class="sige-action-sub"><?php echo esc_html($sugestao_sub); ?></span>
</div>
</td>
 <td class="sige-text-right sige-debt-amount">
 <?php echo number_format($aluno->divida_total, 2, ',', '.'); ?>
</td>
 <td class="sige-u-tar">
 <div style="display:flex; gap:6px; justify-content:flex-end;">
 <a href="?page=sige-app&view=financeiro-pagamentos&aluno_id=<?php echo esc_attr($aluno->aluno_id); ?>" class="sige-btn sige-btn-primary" style="height:32px; padding:0 12px; font-size:0.75rem;" title="Pagar">
 Pagar
</a>
 <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=sige-app&sige_print=factura&id=' . (int)$aluno->aluno_id), 'sige_print_factura_' . (int)$aluno->aluno_id, '_sige_doc_nonce')); ?>" data-sige-act="sigeAbrirJanela" data-sige-prevent data-sige-window-name="ExtractoDividaSIGE" data-sige-window-features="width=900,height=800,scrollbars=yes" class="sige-btn sige-btn-ghost" style="height:32px; padding:0 10px; font-size:0.75rem;" title="Imprimir Extracto de Dívida">Extracto dívida</a>
 <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=sige-app&sige_print=extracto&id=' . (int)$aluno->aluno_id), 'sige_print_extracto_' . (int)$aluno->aluno_id, '_sige_doc_nonce')); ?>" data-sige-act="sigeAbrirJanela" data-sige-prevent data-sige-window-name="HistoricoFinanceiroSIGE" data-sige-window-features="width=900,height=800,scrollbars=yes" class="sige-btn sige-btn-ghost" style="height:32px; padding:0 10px; font-size:0.75rem;" title="Imprimir Histórico Financeiro">Histórico</a>
</div>
</td>
</tr>
 <?php endforeach; ?>
 <?php endif; ?>
</tbody>
</table>
</div>
 <?php if(!empty($lista_final)): ?>
 <div class="sige-table-footer">
 <span style="font-size:0.85rem; color:var(--sige-slate-500); display:flex; align-items:center; gap:6px;">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><path d="M12 19V5M5 12l7-7 7 7"/></svg> Escolha manualmente os alunos ou use uma selecção rápida antes de enviar a cobrança.
</span>
 <div style="width:100%;background:var(--color-slate-50);border:1px solid var(--sg-theme-soft,var(--color-brand-50));border-left:4px solid var(--color-info-500);border-radius:12px;padding:12px 14px;margin:0 0 12px;">
 <strong style="color:var(--sg-theme-primary-800,var(--color-ink-700));display:block;margin-bottom:6px;">Canais desta cobrança</strong>
 <input type="hidden" name="notificar_whatsapp" value="0">
 <input type="hidden" name="notificar_email" value="0">
 <label style="display:inline-flex;align-items:center;gap:8px;margin-right:18px;font-weight:700;color:var(--color-black);"><input type="checkbox" name="notificar_whatsapp" value="1" checked> WhatsApp</label>
 <label style="display:inline-flex;align-items:center;gap:8px;font-weight:700;color:var(--color-black);"><input type="checkbox" name="notificar_email" value="1" checked> E-mail</label>
</div>
 <button type="submit" class="sige-btn sige-btn-success" style="height:44px; padding:0 24px;" id="cb-submit-cobranca">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg> 
 Enviar Cobrança pelos canais escolhidos
</button>
</div>
 <?php endif; ?>
</form>
</div>
</div>
<div id="sgcc-modal-confirmar-cobranca" class="sgcc-confirm-modal" aria-hidden="true">
 <div class="sgcc-confirm-card" role="dialog" aria-modal="true" aria-labelledby="sgcc-confirm-title">
  <div class="sgcc-confirm-head">
   <div>
    <span class="sgcc-confirm-kicker">Confirmar cobrança</span>
    <h3 id="sgcc-confirm-title">Confirme antes de enviar</h3>
   </div>
   <button type="button" class="sgcc-confirm-close" data-sgcc-modal-close aria-label="Fechar">×</button>
  </div>
  <div class="sgcc-confirm-body">
   <p>Antes de enviar, confirme se a selecção e os canais estão correctos. Esta verificação evita cobranças enviadas por engano.</p>
   <div class="sgcc-confirm-grid">
    <div><span>Alunos seleccionados</span><strong id="sgcc-confirm-count">0</strong></div>
    <div><span>Total em aberto</span><strong id="sgcc-confirm-total">0,00 MT</strong></div>
    <div><span>Canais</span><strong id="sgcc-confirm-channels">WhatsApp e E-mail</strong></div>
    <div><span>Sem contacto</span><strong id="sgcc-confirm-no-contact">0</strong></div>
   </div>
   <div class="sgcc-confirm-warning" id="sgcc-confirm-warning" style="display:none;">Há alunos seleccionados sem contacto registado. Actualize os dados antes de enviar, se necessário.</div>
   <div class="sgcc-confirm-list" id="sgcc-confirm-list"></div>
  </div>
  <div class="sgcc-confirm-footer">
   <button type="button" class="sgcc-pro-btn sgcc-pro-btn-light" data-sgcc-modal-close>Voltar e corrigir</button>
   <button type="button" class="sgcc-pro-btn sgcc-pro-btn-primary" id="sgcc-confirm-submit">Confirmar envio</button>
  </div>
 </div>
</div>

<script <?php echo sige_csp_script_attr(); ?>>
(function(){
 'use strict';

 var form = document.getElementById('sgcc-cobranca-form');
 var checkAll = document.getElementById('cb-select-all-1');
 var clearBtn = document.getElementById('cb-clear-selection');
 var submitBtn = document.getElementById('cb-submit-cobranca');
 var countEl = document.getElementById('cb-selected-count');
 var debtEl = document.getElementById('cb-selected-debt');
 var noContactEl = document.getElementById('cb-selected-no-contact');
 var criticalEl = document.getElementById('cb-selected-critical');
 var printBtn = document.getElementById('cb-print-lista');
 var operationalPanel = document.getElementById('cb-operational-panel');
 var operationalText = document.getElementById('cb-operational-text');
 var noteEl = document.getElementById('cb-selection-note');
 var table = document.querySelector('.sige-table');
 var checkboxes = Array.prototype.slice.call(document.querySelectorAll('input[name="aluno_ids[]"]'));
 var scrollBtn = document.getElementById('sgcc-scroll-lista');
 var confirmModal = document.getElementById('sgcc-modal-confirmar-cobranca');
 var confirmSubmitBtn = document.getElementById('sgcc-confirm-submit');
 var submitAllowed = false;

 function formatMzn(value) {
  var n = Number(value || 0);
  if (!isFinite(n)) n = 0;
  try {
   return n.toLocaleString('pt-MZ', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  } catch(e) {
   return (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2).replace('.', ',');
  }
 }

 function updateSummary() {
  var selected = 0;
  var totalDebt = 0;
  var noContact = 0;
  var critical = 0;
  var avisoPrioritario = 0;
  var cobrancaFormal = 0;

  checkboxes.forEach(function(cb){
   var row = cb.closest ? cb.closest('tr') : null;
   if (cb.checked) {
    selected++;
    totalDebt += parseFloat(cb.getAttribute('data-debt') || '0') || 0;
    if (cb.getAttribute('data-has-contact') !== '1') noContact++;
    if (cb.getAttribute('data-priority') === 'critica') critical++;
    if (cb.getAttribute('data-regua') === 'aviso_prioritario') avisoPrioritario++;
    if (cb.getAttribute('data-regua') === 'cobranca_formal') cobrancaFormal++;
    if (row) row.classList.add('sige-row-selected');
   } else if (row) {
    row.classList.remove('sige-row-selected');
   }
  });

  if (countEl) countEl.textContent = selected;
  if (debtEl) debtEl.textContent = formatMzn(totalDebt);
  if (noContactEl) noContactEl.textContent = noContact;
  if (criticalEl) criticalEl.textContent = critical;
  if (operationalPanel && operationalText) {
   if (selected > 0) {
    var partes = [];
    if (avisoPrioritario > 0) partes.push(avisoPrioritario + ' caso(s) na régua de aviso prioritário.');
    if (cobrancaFormal > 0) partes.push(cobrancaFormal + ' caso(s) para cobrança formal.');
    if (critical > 0) partes.push(critical + ' caso(s) crítico(s) exigem seguimento humano mais próximo.');
    if (noContact > 0) partes.push(noContact + ' aluno(s) precisam de contacto actualizado antes da cobrança.');
    if (critical === 0 && noContact === 0) partes.push('Selecção pronta para envio pelos canais escolhidos.');
    operationalText.textContent = selected + ' aluno(s), dívida seleccionada de ' + formatMzn(totalDebt) + ' ' + '<?php echo esc_js(sige_moeda()); ?>' + '. ' + partes.join(' ');
    operationalPanel.classList.add('active');
   } else {
    operationalText.textContent = 'Seleccione alunos para ver a recomendação operacional.';
    operationalPanel.classList.remove('active');
   }
  }
  if (noteEl) noteEl.style.display = noContact > 0 ? 'block' : 'none';
  if (checkAll) checkAll.checked = checkboxes.length > 0 && selected === checkboxes.length;
 }

 function setSelectionByPriority(priority) {
  checkboxes.forEach(function(cb){
   cb.checked = cb.getAttribute('data-priority') === priority;
  });
  updateSummary();
 }

 function setSelectionByBehavior(behavior) {
  checkboxes.forEach(function(cb){
   cb.checked = cb.getAttribute('data-behavior') === behavior;
  });
  updateSummary();
 }

 function setSelectionByRegua(regua) {
  checkboxes.forEach(function(cb){
   cb.checked = cb.getAttribute('data-regua') === regua;
  });
  updateSummary();
 }

 if (checkAll) {
  checkAll.addEventListener('change', function(e) {
   checkboxes.forEach(function(cb){ cb.checked = !!e.target.checked; });
   updateSummary();
  });
 }

 checkboxes.forEach(function(cb){
  cb.addEventListener('change', updateSummary);
  cb.addEventListener('click', function(e){ e.stopPropagation(); });
 });

 if (table) {
  table.addEventListener('click', function(e){
   var target = e.target;
   if (!target) return;
   if (target.closest && target.closest('a,button,input,select,textarea,label')) return;
   var row = target.closest ? target.closest('tbody tr') : null;
   if (!row) return;
   var cb = row.querySelector('input[name="aluno_ids[]"]');
   if (!cb) return;
   cb.checked = !cb.checked;
   updateSummary();
  });
 }

 document.querySelectorAll('[data-select-priority]').forEach(function(btn){
  btn.addEventListener('click', function(){
   setSelectionByPriority(btn.getAttribute('data-select-priority'));
  });
 });
 document.querySelectorAll('[data-select-behavior]').forEach(function(btn){
  btn.addEventListener('click', function(){
   setSelectionByBehavior(btn.getAttribute('data-select-behavior'));
  });
 });
 document.querySelectorAll('[data-select-regua]').forEach(function(btn){
  btn.addEventListener('click', function(){
   setSelectionByRegua(btn.getAttribute('data-select-regua'));
  });
 });

 if (clearBtn) {
  clearBtn.addEventListener('click', function(){
   checkboxes.forEach(function(cb){ cb.checked = false; });
   if (checkAll) checkAll.checked = false;
   updateSummary();
  });
 }

 function getSelectedRowsForPrint() {
  var selected = checkboxes.filter(function(cb){ return cb.checked; });
  return selected.length ? selected : checkboxes;
 }

 function gerarListaCobranca() {
  var rows = getSelectedRowsForPrint();
  if (!rows.length) {
   sigeUi.toast('Não há alunos na lista actual para gerar cobrança.', 'aviso');
   return;
  }
  var total = 0;
  var linhas = rows.map(function(cb, idx){
   var debt = parseFloat(cb.getAttribute('data-debt') || '0') || 0;
   total += debt;
   return '<tr>' +
    '<td>' + (idx + 1) + '</td>' +
    '<td>' + escapeHtml(cb.getAttribute('data-student-name') || '') + '<br><small>Proc: ' + escapeHtml(cb.getAttribute('data-process') || '') + '</small></td>' +
    '<td>' + escapeHtml(cb.getAttribute('data-class') || '') + '</td>' +
    '<td>' + escapeHtml(cb.getAttribute('data-contact') || '') + '</td>' +
    '<td>' + escapeHtml(cb.getAttribute('data-priority') || '') + '</td>' +
    '<td>' + escapeHtml(cb.getAttribute('data-behavior') || '') + '</td>' +
    '<td>' + escapeHtml(cb.getAttribute('data-regua-label') || '') + '</td>' +
    '<td>' + escapeHtml(cb.getAttribute('data-action') || '') + '<br><small>' + escapeHtml(cb.getAttribute('data-regua-modelo') || '') + '</small></td>' +
    '<td class="sige-u-tar sige-u-fw7">' + formatMzn(debt) + '</td>' +
   '</tr>';
  }).join('');
  var html = '<!doctype html><html><head><meta charset="utf-8"><title>Lista de Cobrança</title>' +
   '<style><?php echo sige_utilities_inline_css(); ?>body{font-family:Arial,sans-serif;color:#0f172a;padding:24px;}h1{font-size:22px;margin:0 0 4px;}p{margin:0 0 14px;color:#475569;}table{width:100%;border-collapse:collapse;font-size:12px;}th,td{border:1px solid #cbd5e1;padding:8px;vertical-align:top;}th{background:#f1f5f9;text-align:left;}tfoot td{font-weight:800;background:#f8fafc;}small{color:#64748b;}@media print{button{display:none;}}</style>' +
   '</head><body><button data-sige-act="sigeImprimirPagina" data-sige-noargs class="sgk-btn sgk-btn-sec sgk-btn-sm" style="float:right;">Imprimir</button><h1>Lista de Cobrança</h1>' +
   '<p>Gerada pela Central de Cobranças - ' + new Date().toLocaleString('pt-MZ') + '</p>' +
   '<table><thead><tr><th>#</th><th>Aluno</th><th>Turma</th><th>Contacto</th><th>Prioridade</th><th>Comportamento</th><th>Régua</th>' +
   '<th>Sugestão</th><th class="sige-u-tar">Dívida</th></tr></thead><tbody>' + linhas +
   '</tbody><tfoot><tr><td colspan="8">Total</td><td class="sige-u-tar">' + formatMzn(total) + ' ' + '<?php echo esc_js(sige_moeda()); ?>' + '</td></tr></tfoot></table>' +
   '</body></html>';
  var w = window.open('', 'ListaCobrancaSIGE', 'width=1100,height=800,scrollbars=yes');
  if (!w) { sigeUi.toast('O navegador bloqueou a janela de impressão. Permita popups para este site e tente novamente.', 'aviso'); return; }
  w.document.open();
  w.document.write(html);
  w.document.close();
  w.focus();
 }

 function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, function(ch){
   return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch];
  });
 }

 if (printBtn) {
  printBtn.addEventListener('click', gerarListaCobranca);
 }

 if (scrollBtn) {
  scrollBtn.addEventListener('click', function(){
   var target = document.querySelector('.sige-table-wrap') || document.querySelector('.sgcc-filter-card');
   if (target && target.scrollIntoView) target.scrollIntoView({behavior:'smooth', block:'start'});
  });
 }

 function getSelectedCheckboxes() {
  return checkboxes.filter(function(cb){ return cb.checked; });
 }

 function getChannelSummary() {
  var w = form ? form.querySelector('input[name="notificar_whatsapp"][type="checkbox"]') : null;
  var e = form ? form.querySelector('input[name="notificar_email"][type="checkbox"]') : null;
  var parts = [];
  if (!w || w.checked) parts.push('WhatsApp');
  if (!e || e.checked) parts.push('E-mail');
  return parts.length ? parts.join(' e ') : 'Sem canal seleccionado';
 }

 function openConfirmModal(selected) {
  if (!confirmModal) return;
  var total = 0;
  var noContact = 0;
  selected.forEach(function(cb){
   total += parseFloat(cb.getAttribute('data-debt') || '0') || 0;
   if (cb.getAttribute('data-has-contact') !== '1') noContact++;
  });
  var countTarget = document.getElementById('sgcc-confirm-count');
  var totalTarget = document.getElementById('sgcc-confirm-total');
  var channelsTarget = document.getElementById('sgcc-confirm-channels');
  var noContactTarget = document.getElementById('sgcc-confirm-no-contact');
  var warningTarget = document.getElementById('sgcc-confirm-warning');
  var listTarget = document.getElementById('sgcc-confirm-list');
  if (countTarget) countTarget.textContent = selected.length;
  if (totalTarget) totalTarget.textContent = formatMzn(total) + ' ' + '<?php echo esc_js(sige_moeda()); ?>';
  if (channelsTarget) channelsTarget.textContent = getChannelSummary();
  if (noContactTarget) noContactTarget.textContent = noContact;
  if (warningTarget) warningTarget.style.display = noContact > 0 ? 'block' : 'none';
  if (listTarget) {
   var preview = selected.slice(0, 5).map(function(cb){
    return '<span>' + escapeHtml(cb.getAttribute('data-student-name') || 'Aluno') + '</span>';
   }).join('');
   if (selected.length > 5) preview += '<em>+' + (selected.length - 5) + ' outro(s)</em>';
   listTarget.innerHTML = preview;
  }
  confirmModal.setAttribute('aria-hidden','false');
  confirmModal.style.display = 'flex';
  document.documentElement.classList.add('sgcc-modal-open');
  document.body.classList.add('sgcc-modal-open');
 }

 function closeConfirmModal() {
  if (!confirmModal) return;
  confirmModal.setAttribute('aria-hidden','true');
  confirmModal.style.display = 'none';
  document.documentElement.classList.remove('sgcc-modal-open');
  document.body.classList.remove('sgcc-modal-open');
 }

 document.querySelectorAll('[data-sgcc-modal-close]').forEach(function(btn){
  btn.addEventListener('click', closeConfirmModal);
 });

 if (confirmModal) {
  confirmModal.addEventListener('click', function(e){
   if (e.target === confirmModal) closeConfirmModal();
  });
 }

 if (confirmSubmitBtn) {
  confirmSubmitBtn.addEventListener('click', function(){
   if (!form) return;
   submitAllowed = true;
   closeConfirmModal();
   form.submit();
  });
 }

 if (form) {
  form.addEventListener('submit', function(e){
   if (submitAllowed) return true;
   var selected = getSelectedCheckboxes();
   if (selected.length === 0) {
    e.preventDefault();
    sigeUi.toast('Seleccione pelo menos um aluno antes de enviar a cobrança.', 'aviso');
    return false;
   }
   e.preventDefault();
   openConfirmModal(selected);
   return false;
  });
 }

 updateSummary();
})();
</script>
