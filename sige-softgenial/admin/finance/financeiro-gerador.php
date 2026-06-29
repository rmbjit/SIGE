<?php
if (!defined('ABSPATH')) exit;
/**
 * Ficheiro: admin/financeiro-gerador.php
 * Módulo: Lançar Mensalidades (Lote) + Pacote Mensal Inteligente
 *
 * REFATORIZADO: Abril 2026 - Design System v1.0
 * - Removido CSS inline
 * - Classes sg-* do Design System
 * - Lógica PHP 100% preservada
 *
 * Objectivo:
 * - Lançar mensalidades do mês
 * - Se "Pacote Mensal" estiver activo (ou serviço = Mensalidade), gerar também:
 * - Transporte (por rota do aluno)
 * - Extras/Alimentação (a partir das flags do aluno)
 *
 * Regras:
 * - Extras: preço SEMPRE de wpq1_sige_fin_servicos
 * - Transporte: preço SEMPRE de wpq1_sige_transporte_rotas.preco_mensal
 * - Config: apenas regras (multa, descontos, tolerância, etc.)
 */

// [FIX R-02] Guard de acesso - Tesouraria (Gerador)
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['financeiro.lancar_mensalidades'],
    ['sige_director','sige_secretario']
)) return;

if (!defined('ABSPATH')) exit;

global $wpdb;

$sg_generator_success_modal_items = [];
$sg_generator_notice_messages = [];

// [v12.5.0] Config Layer: leitura não intrusiva do regime de mensalidade.
// Nesta versão, esta variável NÃO altera cálculo nem selecção de serviço.
$sige_regime_mensalidade_config = function_exists('sige_financeiro_config') ? sige_financeiro_config('regime_mensalidade', 'normal') : 'normal';

// Permissões (controlo fino para acção de lançar)
// [12.9.6] Verificação fina via matriz; WP cap legacy fallback.
if (!sige_page_guard_allows(
    ['financeiro.lancar_mensalidades'],
    ['sige_fin_lancar','sige_secretario']
)) {
 echo '<div class="sg-alert sg-alert-error sg-m-4"><span class="sg-alert-icon"></span><div class="sg-alert-content">Sem permissão.</div></div>';
 return;
}

// Dependência: Finance Core
if (!function_exists('sige_fin_get_config')) {
 echo '<div class="sg-alert sg-alert-error sg-m-4"><span class="sg-alert-icon"></span><div class="sg-alert-content">Não foi possível preparar o módulo financeiro. Actualize a página e tente novamente. Se persistir, contacte o suporte.</div></div>';
 return;
}

$ano_letivo = sige_fin_get_ano_letivo_master();
$config_fin = sige_fin_get_config($ano_letivo);

// Tabelas
// ── escola_id via helper com cache estático ─────────────────────────────
$escola_id = function_exists('sige_get_escola_id') ? sige_get_escola_id() : 0;

$tServ = $wpdb->prefix . 'sige_fin_servicos';
$tTur = $wpdb->prefix . 'sige_turmas';
$tAlu = $wpdb->prefix . 'sige_alunos';
$tMat = $wpdb->prefix . 'sige_matriculas';
$tLan = $wpdb->prefix . 'sige_fin_lancamentos';
$tRot = $wpdb->prefix . 'sige_transporte_rotas';

// [v12.11.9.88.1] Condição canónica do aluno activo.
// O estado principal do aluno é a fonte operacional para lançamentos.
// A matrícula continua obrigatória pelo JOIN do ano lectivo/turma, mas não pode
// manter um aluno reactivado bloqueado por um status_matricula legado.
$__sige_a_activo_gerador = function_exists('sige_aluno_activo_sql')
    ? sige_aluno_activo_sql('a')
    : "(a.status IS NULL OR TRIM(LOWER(a.status)) IN ('activo','ativo','activa','ativa'))";
// a_activo_gerador_status_sync_12119881

// Dropdowns
$servicos = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tServ WHERE escola_id = %d AND ativo = 1 ORDER BY nome ASC", $escola_id));
$turmas = $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM $tTur WHERE escola_id = %d AND ano_lectivo = %d ORDER BY classe ASC, nome ASC",
 $escola_id,
 $ano_letivo
));

// Lista de alunos activos matriculados no ano lectivo (para selecção individual)
$alunos_lista = $wpdb->get_results($wpdb->prepare(
 "SELECT a.id, a.nome_completo, t.classe, t.nome AS turma_nome
 FROM $tAlu a
 JOIN $tMat m ON m.aluno_id = a.id AND m.escola_id = a.escola_id AND m.ano_lectivo = %d
 LEFT JOIN $tTur t ON t.id = m.turma_id AND t.escola_id = a.escola_id
 WHERE {$__sige_a_activo_gerador} AND a.escola_id = %d
 ORDER BY CASE WHEN (m.status_matricula IS NULL OR TRIM(LOWER(m.status_matricula)) IN ('activa','ativa','activo','ativo')) THEN 0 ELSE 1 END,
          t.classe ASC, a.nome_completo ASC",
 $ano_letivo, $escola_id
));

/**
 * =========================================================
 * Helpers
 * =========================================================
 */
if (!function_exists('sige_fin_data_vencimento')) {
 function sige_fin_data_vencimento($mes_ref, $dia_venc) {
 $dia = max(1, min(28, (int)$dia_venc));
 $ts = strtotime($mes_ref . '-01');
 return wp_date('Y-m', $ts) . '-' . str_pad((string)$dia, 2, '0', STR_PAD_LEFT);
 }
}

/**
 * Retorna o servico de transporte do catalogo.
 * Se nao existir, cria automaticamente (preco=0, classe=todas).
 * O preco real vem sempre da rota do aluno.
 */
// [DRY-S12] sige_fin_get_ou_criar_servico_transporte() - canónica em finance-core.php

// [DRY-S12] sige_fin_servico_eh_mensalidade() - canónica em finance-core.php

/**
 * Creche: encontrar servico dedicado por regime.
 */
// [DRY-S12] sige_fin_get_servico_creche() - canónica em finance-core.php

/**
 * Transporte: rota do aluno + preço mensal
 */
if (!function_exists('sige_transporte_get_rota_aluno')) {
 function sige_transporte_get_rota_aluno($aluno_id) {
 global $wpdb;
 $tA = $wpdb->prefix . 'sige_alunos';
 $tR = $wpdb->prefix . 'sige_transporte_rotas';
 // [v14.0.2 MT-01] Defesa multi-tenant em helper público reutilizável
 $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
 $rota_id = (int)$wpdb->get_var($wpdb->prepare(
 "SELECT rota_transporte_id FROM $tA WHERE id=%d AND escola_id=%d LIMIT 1",
 (int)$aluno_id, $eid
 ));
 if ($rota_id <= 0) return null;
 return $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM $tR WHERE id=%d AND (ativo=1 OR ativo IS NULL) LIMIT 1",
 $rota_id
 ));
 }
}

// [DRY-S12] sige_transporte_get_preco_mensal() - canónica em finance-core.php

/**
 * Procura serviço por palavras-chave no nome (fallback).
 */
if (!function_exists('sige_fin_encontrar_servico_por_keywords')) {
 function sige_fin_encontrar_servico_por_keywords($servicos, $keywords) {
 $keywords = (array)$keywords;
 foreach ((array)$servicos as $s) {
 $nome = strtolower(trim((string)($s->nome ?? '')));
 if ($nome === '') continue;
 $ok = true;
 foreach ($keywords as $kw) {
 $kw = strtolower(trim((string)$kw));
 if ($kw === '') continue;
 if (strpos($nome, $kw) === false) { $ok = false; break; }
 }
 if ($ok) return $s;
 }
 return null;
 }
}

/**
 * =========================================================
 * MAPA DE EXTRAS (flags do aluno)
 * =========================================================
 */
$MAPA_EXTRAS = [
 'tem_estudos' => ['estudos'],
 'tem_ingles' => ['ingles'],
 'tem_desporto' => ['desporto'],
 'tem_almoco' => ['almoco', 'almoço'],
 'tem_pequeno_almoco' => ['pequeno', 'almoco', 'almoço']
];

$TIPO_EXTRAS = [
 'tem_estudos' => 'estudos',
 'tem_ingles' => 'ingles',
 'tem_desporto' => 'desporto',
 'tem_almoco' => 'almoco',
 'tem_pequeno_almoco' => 'pequeno_almoco',
];

$EXTRA_TIPO_TO_FLAG = [];
foreach ($TIPO_EXTRAS as $flag_col => $tipo_servico) {
 $EXTRA_TIPO_TO_FLAG[strtolower($tipo_servico)] = $flag_col;
}

$EXTRA_KEYWORDS_TO_FLAG = [
 'estudos' => 'tem_estudos',
 'ingles' => 'tem_ingles',
 'inglês' => 'tem_ingles',
 'desporto' => 'tem_desporto',
 'pequeno almoco' => 'tem_pequeno_almoco',
 'pequeno almoço' => 'tem_pequeno_almoco',
 'almoco' => 'tem_almoco',
 'almoço' => 'tem_almoco',
];

/**
 * =========================================================
 * PROCESSAR GERACAO EM MASSA
 * =========================================================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_gerar_lote'])) {
 // [AUTH-11] Capability check inline (defesa em profundidade)
 if (!((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director') || current_user_can('sige_secretario'))) {
 echo '<div class="sg-alert sg-alert-error sg-m-4"><span class="sg-alert-icon"></span><div class="sg-alert-content">Não tem permissão para gerar lançamentos.</div></div>';
 } elseif (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'sige_fin_gerar_lote')) {
 echo '<div class="sg-alert sg-alert-error sg-m-4"><span class="sg-alert-icon"></span><div class="sg-alert-content">A sessão expirou. Actualize a página e tente novamente.</div></div>';
 } else {
 $servico_id = sige_fin_post_int('servico_id');
 $mes_ref = sige_fin_post_param('mes_ref');
 $turma_alvo = sige_fin_post_int('turma_alvo');
 $aluno_alvo = sige_fin_post_int('aluno_alvo');
 $gerar_ano_todo = sige_fin_post_int('gerar_ano_todo') === 1;
 $modo_pacote = sige_fin_post_int('modo_pacote_mensal') === 1;
    // [FIX BUG-ABRIL-2026] Extras opcionais so para alunos com flag. Checkbox sobrepoe.
    $forcar_todos_subscricao = !empty($_POST['forcar_todos_subscricao']);
    // v12.11.9.32 - autonomia da escola: lançar com ou sem mensagens aos encarregados.
    // Padrão seguro: NÃO enviar. Só envia quando a secretaria marca explicitamente
    // WhatsApp e/ou E-mail no formulário desta operação. Esta leitura directa evita
    // o padrão legado de helpers antigos que assumiam envio activo quando o campo não existia.
    $sg_lanc_bool_post = static function (string $campo): bool {
        if (!array_key_exists($campo, $_POST)) return false;
        $valor = $_POST[$campo];
        if (is_array($valor)) { $valor = end($valor); }
        $valor = strtolower(trim((string)$valor));
        return in_array($valor, ['1', 'sim', 'yes', 'on', 'true'], true);
    };
    $notificar_whatsapp_lote = $sg_lanc_bool_post('notificar_whatsapp');
    $notificar_email_lote    = $sg_lanc_bool_post('notificar_email');
    // Se algum canal estiver globalmente indisponível/desligado, respeitamos essa configuração.
    if ($notificar_whatsapp_lote && function_exists('sige_fin_notificacao_canal_ativo') && !sige_fin_notificacao_canal_ativo('whatsapp')) {
        $notificar_whatsapp_lote = false;
    }
    if ($notificar_email_lote && function_exists('sige_fin_notificacao_canal_ativo') && !sige_fin_notificacao_canal_ativo('email')) {
        $notificar_email_lote = false;
    }

 if (!$servico_id || (!$gerar_ano_todo && !preg_match('/^\d{4}\-\d{2}$/', $mes_ref))) {
 echo '<div class="sg-alert sg-alert-error sg-m-4"><span class="sg-alert-icon"></span><div class="sg-alert-content">Seleccione um serviço e um mês válido (YYYY-MM), ou active "Gerar ano todo".</div></div>';
 } else {
 // Se gerar_ano_todo, construir lista dos 12 meses do ano lectivo
 if ($gerar_ano_todo) {
 $meses_a_gerar = [];
 for ($m = 1; $m <= 12; $m++) {
 $meses_a_gerar[] = sprintf('%04d-%02d', $ano_letivo, $m);
 }
 } else {
 $meses_a_gerar = [$mes_ref];
 }

 $servico_base = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM $tServ WHERE id=%d AND ativo=1 AND escola_id=%d",
 $servico_id, $escola_id
 ));

 if (!$servico_base) {
 echo '<div class="sg-alert sg-alert-error sg-m-4"><span class="sg-alert-icon"></span><div class="sg-alert-content">Serviço inválido/inactivo.</div></div>';
 } elseif ((int)($servico_base->centro_id ?? 0) === 0) {
 // [v13.4.2] Gate do centro: bloquear geração de lançamentos com serviço não classificado.
 // Sem isto, os lançamentos iam para centro=1 por default e todo o filtro do Bloco 3
 // ficaria incoerente. Melhor forçar o Director a classificar primeiro.
 echo '<div class="sg-alert sg-alert-error sg-m-4" style="background:#fef2f2;border-left:4px solid #dc2626;padding:16px;">
         <div class="sg-alert-content">
           <strong style="color:#991b1b;font-size:1.1em;">⚠ Serviço sem centro de custo atribuído</strong>
           <p style="margin:6px 0;color:#7f1d1d;">
             O serviço <strong>' . esc_html($servico_base->nome) . '</strong> não tem centro de custo definido. 
             Lançamentos criados agora iriam para o centro errado e contaminariam os relatórios por centro.
           </p>
           <p class="sige-u-m0">
             <a href="?page=sige-app&view=financeiro-config&edit=' . (int)$servico_base->id . '" 
                class="sg-btn sg-btn-primary" style="margin-top:8px;">
               Atribuir centro agora →
             </a>
           </p>
         </div>
       </div>';
 } else {
 // Se for mensalidade, activa pacote automaticamente
 if (sige_fin_servico_eh_mensalidade($servico_base)) {
 $modo_pacote = true;
 }

 // Buscar alunos matriculados no ano lectivo master
 // Condição já definida no topo da view e reutilizada aqui.
 $sql_alunos = "
 SELECT 
 a.id,
 a.nome_completo,
 a.status,
 a.whatsapp_notificacoes,
 a.telemovel_pai,
 a.telemovel_mae,
 a.rota_transporte_id,
 a.mensalidade_base,
 a.regime_creche,
 a.regime_mensalidade,
 a.tem_estudos,
 a.tem_ingles,
 a.tem_desporto,
 a.tem_almoco,
 a.tem_pequeno_almoco,
 t.classe
 FROM $tAlu a
 JOIN $tMat m ON (m.aluno_id = a.id AND m.escola_id = a.escola_id AND m.ano_lectivo = %d)
 LEFT JOIN {$wpdb->prefix}sige_turmas t ON t.id = m.turma_id AND t.escola_id = a.escola_id
 WHERE {$__sige_a_activo_gerador} AND a.escola_id = %d
 ";

 $params = [$ano_letivo, $escola_id]; // escola_id para a WHERE dinâmica
 if ($aluno_alvo > 0) {
 $sql_alunos .= " AND a.id = %d";
 $params[] = $aluno_alvo;
 } elseif ($turma_alvo > 0) {
 $sql_alunos .= " AND m.turma_id = %d";
 $params[] = $turma_alvo;
 }

 $alunos = $wpdb->get_results($wpdb->prepare($sql_alunos, $params));
 foreach ((array)$alunos as $__a_fix) {
  if (empty($__a_fix->classe) && function_exists('sige_fin_obter_classe_actual_aluno_gerador')) {
   $__a_fix->classe = sige_fin_obter_classe_actual_aluno_gerador((int)$__a_fix->id, (int)$ano_letivo, (int)$escola_id);
  } else {
   $__a_fix->classe = trim((string)preg_replace('/[ªº]/u', '', (string)($__a_fix->classe ?? '')));
  }
 }

 $sige_lancamento_total_alunos = count((array)$alunos);
 $sige_lancamento_notificacoes_pedidas = (!empty($notificar_whatsapp_lote) || !empty($notificar_email_lote));

 if ($sige_lancamento_notificacoes_pedidas) {
     if (function_exists('sige_notify_lancamento_manual_optin') && !sige_notify_lancamento_manual_optin()) {
         $notificar_whatsapp_lote = false;
         $notificar_email_lote = false;
         $sg_generator_notice_messages[] = 'As mensagens do lançamento não foram activadas porque a validação de segurança do pedido falhou. Os lançamentos foram preparados sem envio automático.';
     }
 }

 // Segurança anti-envio agressivo: gerar ano inteiro pode criar centenas/milhares
 // de mensagens e aumentar risco de restrição do número WhatsApp. O lançamento anual
 // continua permitido, mas sem mensagens automáticas.
 if ($gerar_ano_todo && (!empty($notificar_whatsapp_lote) || !empty($notificar_email_lote))) {
     $notificar_whatsapp_lote = false;
     $notificar_email_lote = false;
     $sg_generator_notice_messages[] = 'As mensagens foram desligadas para “Gerar ano todo”. Para proteger o número da escola, envie avisos por mês, turma ou aluno específico.';
 }

 // Limite conservador para WhatsApp no lançamento de mensalidades. E-mail pode seguir
 // quando escolhido, mas WhatsApp em lote grande deve ser feito com cuidado.
 $sige_lancamento_wpp_max_lote = (int) apply_filters('sige_fin_lancamento_whatsapp_max_lote', 80, (int)$escola_id);
 if (!empty($notificar_whatsapp_lote) && $sige_lancamento_wpp_max_lote > 0 && $sige_lancamento_total_alunos > $sige_lancamento_wpp_max_lote) {
     $notificar_whatsapp_lote = false;
     $sg_generator_notice_messages[] = 'O WhatsApp foi desligado para este lote porque inclui ' . (int)$sige_lancamento_total_alunos . ' alunos. O limite seguro configurado é ' . (int)$sige_lancamento_wpp_max_lote . '. Use uma turma menor ou a Central de Cobranças.';
 }

 $notificacao_evento_lote = (!empty($notificar_whatsapp_lote) || !empty($notificar_email_lote))
     && (!function_exists('sige_notify_event_allowed') || sige_notify_event_allowed('fatura'));
 if ((!empty($notificar_whatsapp_lote) || !empty($notificar_email_lote)) && !$notificacao_evento_lote) {
     $notificar_whatsapp_lote = false;
     $notificar_email_lote = false;
     $sg_generator_notice_messages[] = 'A política de notificações da escola bloqueou o envio de avisos de lançamento. Os valores foram lançados sem mensagens automáticas.';
 }
 $servicos_ativos = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tServ WHERE escola_id=%d AND ativo=1", $escola_id));
 $servico_transporte = sige_fin_get_ou_criar_servico_transporte();

 $stats = [
 'alunos_processados' => 0,
 'mensalidade_criados' => 0,
 'mensalidade_actualizados' => 0,
 'mensalidade_ignorados' => 0,
 'transporte_criados' => 0,
 'transporte_actualizados' => 0,
 'transporte_ignorados' => 0,
 'transporte_sem_rota' => 0,
 'extras_criados' => 0,
 'extras_actualizados' => 0,
 'extras_ignorados' => 0,
 'extras_sem_servico' => 0,
 ];

 $wpp_stats = ['tentativas'=>0,'enviadas'=>0,'falhas'=>0,'sem_numero'=>0];
 $wpp_falhas = [];
 $email_stats = ['tentativas'=>0,'enviadas'=>0,'falhas'=>0];
 $email_falhas = [];
 $sige_notif_batch_total = max(1, count((array)$alunos) * count((array)$meses_a_gerar));
 $sige_notif_batch_index = 0;

 // ── LOOP DE MESES ──────────────────────────────────────
 foreach ($meses_a_gerar as $mes_ref_loop) {

 // Reset stats por mês
 $stats = [
 'alunos_processados' => 0,
 'mensalidade_criados' => 0,
 'mensalidade_actualizados' => 0,
 'mensalidade_ignorados' => 0,
 'transporte_criados' => 0,
 'transporte_actualizados' => 0,
 'transporte_ignorados' => 0,
 'transporte_sem_rota' => 0,
 'extras_criados' => 0,
 'extras_actualizados' => 0,
 'extras_ignorados' => 0,
 'extras_sem_servico' => 0,
 ];

 $dia_venc = (int)($servico_base->dia_vencimento ?? 5);
 $data_vencimento = sige_fin_data_vencimento($mes_ref_loop, $dia_venc);

 foreach ($alunos as $a) {
 $stats['alunos_processados']++;

 /**
 * ==========================================
 * MODO 1: Serviço único (comportamento antigo)
 * ==========================================
 */
 if (!$modo_pacote) {
 // [FIX F-08] Verificar classe do serviço ORIGINAL antes de qualquer substituição.
 // Se o serviço seleccionado é "Mensalidade 1ª Classe", alunos de outras classes
 // (incluindo creche) não devem receber lançamento - nem o da sua própria classe.
 if (function_exists('sige_fin_servico_permitido_para_classe') && !sige_fin_servico_permitido_para_classe($servico_base, ($a->classe ?? ''))) {
 $stats['mensalidade_ignorados']++;
 continue;
 }

 $srv_ativo = $servico_base;
 if (!empty($a->regime_creche) && sige_fin_servico_eh_mensalidade($servico_base)) {
 $srv_creche = sige_fin_get_servico_creche($a->regime_creche, $servicos_ativos, ($a->classe ?? ''));
 if ($srv_creche) $srv_ativo = $srv_creche;
 }
 // [v13] Detecção de regime_mensalidade (tempo_inteiro / meio_dia)
 // Só substitui se regime_creche NÃO foi encontrado (creche tem precedência
 // pois aplica-se a perfis jardim/primário); regime_mensalidade para escolas
 // como Casa Colorida que não usam lógica de creche.
 if ($srv_ativo === $servico_base && !empty($a->regime_mensalidade)
     && sige_fin_servico_eh_mensalidade($servico_base)
     && function_exists('sige_fin_get_servico_regime_mensalidade')) {
     $srv_regime = sige_fin_get_servico_regime_mensalidade($a->regime_mensalidade, $servicos_ativos);
     if ($srv_regime) $srv_ativo = $srv_regime;
 }

 $valor_original = (float)($srv_ativo->valor ?? 0);
 if (sige_fin_servico_eh_mensalidade($srv_ativo) && isset($a->mensalidade_base) && (float)$a->mensalidade_base > 0) {
 $valor_original = (float)$a->mensalidade_base;
 }

 $descricao = (string)($srv_ativo->nome . ' (' . wp_date('m/Y', strtotime($mes_ref_loop . '-01')) . ')');

 $tipo_servico_sel = strtolower((string)($servico_base->tipo ?? ''));

 $flag_col = null;
 if (!empty($tipo_servico_sel) && isset($EXTRA_TIPO_TO_FLAG[$tipo_servico_sel])) {
 $flag_col = $EXTRA_TIPO_TO_FLAG[$tipo_servico_sel];
 }

 if (!$flag_col) {
 $nome_servico_sel = strtolower((string)($servico_base->nome ?? $servico_base->nome_servico ?? ''));
 foreach ($EXTRA_KEYWORDS_TO_FLAG as $kw => $col) {
 if ($nome_servico_sel && strpos($nome_servico_sel, $kw) !== false) {
 $flag_col = $col;
 break;
 }
 }
 }

            // [FIX BUG-ABRIL-2026] Verificacao de flag restaurada.
            // Servicos opcionais so sao lancados a alunos com flag activa,
            // salvo se a secretaria marcou 'Forcar para todos' no formulario.
            if ($flag_col && !$forcar_todos_subscricao) {
                if (!isset($a->$flag_col) || (int)$a->$flag_col !== 1) {
                    $stats['mensalidade_ignorados']++;
                    continue;
                }
            }

 // [FIX F-02] Validação de classe - movida para ANTES da substituição creche (FIX F-08 acima)

 // Verificar se o mês está bloqueado para este aluno
 // Bloqueio = lançamento com status='cancelado', servico_id=0, valor_original=0
 $_bloqueio_mes = (int)$wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(1) FROM $tLan
 WHERE aluno_id=%d AND escola_id=%d AND mes_referencia=%s
 AND status='cancelado' AND servico_id=0 AND valor_original=0",
 (int)$a->id, $escola_id, $mes_ref_loop
 ));
 if ($_bloqueio_mes > 0) {
 // Mês bloqueado para este aluno - não gerar mensalidade
 continue;
 }

 $res = sige_fin_upsert_lancamento((int)$a->id, (int)$srv_ativo->id, $mes_ref_loop, $descricao, $valor_original, $data_vencimento, $config_fin, $srv_ativo);

 if ($res['acao'] === 'criado') $stats['mensalidade_criados']++;
 elseif ($res['acao'] === 'actualizado') $stats['mensalidade_actualizados']++;
 else $stats['mensalidade_ignorados']++;

 // [FIX NOTIF-01] Só enviar notificação se lançamento foi criado/actualizado
 if (!in_array($res['acao'], ['criado', 'actualizado'], true)) {
 continue;
 }

 // [FIX BLOQ-02] Se mês está bloqueado para este aluno, não enviar notificação
 if (function_exists('sige_fin_mes_bloqueado') && sige_fin_mes_bloqueado((int)$a->id, (string)$mes_ref_loop)) {
 continue;
 }

 // WhatsApp via FILA
 $tel_raw = $a->whatsapp_notificacoes ?: ($a->telemovel_pai ?: $a->telemovel_mae);
 $tel_num = preg_replace('/[^0-9]/', '', (string)$tel_raw);
 $tel_num = sige_telefone_normalizar($tel_num);

 $total_mes = (float)$wpdb->get_var($wpdb->prepare("
 SELECT SUM(GREATEST(
 COALESCE(valor_original,0)
 + COALESCE(valor_transporte,0)
 + COALESCE(valor_extras,0)
 + COALESCE(NULLIF(valor_multa_cobrada,0), valor_multa, 0)
 - COALESCE(valor_desconto,0)
 - COALESCE(valor_desconto_especial,0)
 - COALESCE(valor_pago,0)
 , 0))
 FROM {$tLan} WHERE aluno_id=%d AND mes_referencia=%s AND status IN ('pendente','parcial','em_plano')
 ", (int)$a->id, (string)$mes_ref_loop));

 // [FIX NOTIF-02] Se total pendente é 0 ou negativo, skip notificação
 if ($total_mes <= 0) { continue; }

 $servico_nome = (string)($servico_base->nome ?? 'Serviço');
 $aluno_nome = (string)($a->nome_completo ?? '');

 // [FIX SALDO-01] Buscar campos para calcular saldo devedor
 $_lancs_fatura = $wpdb->get_results($wpdb->prepare("
 SELECT descricao, valor_original,
 IFNULL(valor_multa, 0) AS valor_multa,
 IFNULL(valor_desconto, 0) AS valor_desconto,
 IFNULL(valor_desconto_especial, 0) AS valor_desconto_especial,
 IFNULL(valor_pago, 0) AS valor_pago,
 IFNULL(valor_transporte, 0) AS valor_transporte,
 IFNULL(valor_extras, 0) AS valor_extras,
 valor_multa_cobrada
 FROM {$tLan} 
 WHERE aluno_id = %d AND mes_referencia = %s AND status IN ('pendente','parcial','em_plano')
 ", (int)$a->id, (string)$mes_ref_loop));

 $_det_items = [];
 foreach ((array)$_lancs_fatura as $_lf) {
 $_saldo = sige_fin_saldo_lancamento($_lf); // função canónica
 if ($_saldo > 0) {
 $_label = ($_lf->descricao ?: 'Serviço');
 if ((float)$_lf->valor_pago > 0) { $_label .= ' (saldo)'; }
 $_det_items[] = "• " . $_label . " - " . number_format($_saldo, 2, ',', '.') . " " . sige_moeda();
 }
 }
 $_det_str = !empty($_det_items) ? implode("\n", $_det_items) : "• {$servico_nome} - " . number_format((float)$valor_original, 2, ',', '.') . " " . sige_moeda();

 if (!empty($notificar_whatsapp_lote) && !$tel_num) {
 $wpp_stats['sem_numero']++;
 } elseif (!empty($notificar_whatsapp_lote) && !function_exists('sige_fin_queue_whatsapp')) {
 $wpp_stats['falhas']++;
 $wpp_falhas[] = "Aluno #{$a->id}: serviço de mensagens indisponível.";
 } else {
 $total_mes = (float)$wpdb->get_var($wpdb->prepare("
 SELECT SUM(GREATEST(
 COALESCE(valor_original,0)
 + COALESCE(valor_transporte,0)
 + COALESCE(valor_extras,0)
 + COALESCE(NULLIF(valor_multa_cobrada,0), valor_multa, 0)
 - COALESCE(valor_desconto,0)
 - COALESCE(valor_desconto_especial,0)
 - COALESCE(valor_pago,0)
 , 0))
 FROM {$tLan}
 WHERE aluno_id = %d
 AND mes_referencia = %s
 AND status IN ('pendente','parcial','em_plano')
 ", (int)$a->id, (string)$mes_ref_loop));

 $_skip_wpp = ($total_mes <= 0);

 $servico_nome = (string)($servico_base->nome ?? 'Serviço');
 $aluno_nome = (string)($a->nome_completo ?? '');

 // [FIX W-01 + SALDO-01] Mensagem detalhada com saldo devedor
 $_lancs_fatura = $wpdb->get_results($wpdb->prepare("
 SELECT descricao, valor_original,
 IFNULL(valor_multa, 0) AS valor_multa,
 IFNULL(valor_desconto, 0) AS valor_desconto,
 IFNULL(valor_desconto_especial, 0) AS valor_desconto_especial,
 IFNULL(valor_pago, 0) AS valor_pago,
 IFNULL(valor_transporte, 0) AS valor_transporte,
 IFNULL(valor_extras, 0) AS valor_extras,
 valor_multa_cobrada
 FROM {$tLan} 
 WHERE aluno_id = %d AND mes_referencia = %s AND status IN ('pendente','parcial','em_plano')
 ", (int)$a->id, (string)$mes_ref_loop));

 $_det_items = [];
 foreach ((array)$_lancs_fatura as $_lf) {
 $_saldo = sige_fin_saldo_lancamento($_lf); // função canónica
 if ($_saldo > 0) {
 $_label = ($_lf->descricao ?: 'Serviço');
 if ((float)$_lf->valor_pago > 0) { $_label .= ' (saldo)'; }
 $_det_items[] = "• " . $_label . " - " . number_format($_saldo, 2, ',', '.') . " " . sige_moeda();
 }
 }
 $_det_str = !empty($_det_items) ? implode("\n", $_det_items) : "• {$servico_nome}";

 if (function_exists('sige_wpp_render_finance_template')) {
 $_cfg_f = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1", sige_get_escola_id()));
                                    $msg = sige_wpp_render_finance_template('fatura', $_cfg_f, $a, [
 'valor' => (float)max(0, $total_mes),
 'vencimento' => $data_vencimento,
 'detalhes' => $_det_str,
 'servico' => $_det_str,
 'mes' => wp_date('m/Y', strtotime($mes_ref_loop . '-01')),
 'id' => (string)$a->id,
 ]);
 } else {
 $escola_nome = function_exists('sige_get_escola_perfil') ? (sige_get_escola_perfil()->nome_escola ?? '') : '';
                                    $msg = "Informação de mensalidade" . ($escola_nome ? " - {$escola_nome}" : "") . "\n\n"
 . "Estimado Encarregado de Educação,\nA secretaria partilha a informação financeira dos serviços escolares de {$aluno_nome}, com vencimento a " . wp_date('d/m/Y', strtotime($data_vencimento)) . ":\n\n"
 . "Serviços:\n" . $_det_str . "\n\n"
 . "Total: " . number_format(max(0, $total_mes), 2, ',', '.') . " " . sige_moeda() . "\n\n"
                                         . "Agradecemos a sua atenção. Se precisar de esclarecer algum detalhe ou combinar uma data, pode responder por aqui.";
 }

 // v12.11.9.32 - lançamento só envia WhatsApp quando o operador marca a opção.
 // O envio fica em fila e passa pelas guardas de segurança do WhatsApp.
 if (!empty($notificacao_evento_lote) && !empty($notificar_whatsapp_lote) && !$_skip_wpp) {
                                    // [v13.5.1] Dual-send: factura via WhatsApp para pai E mãe
                                    if (!empty($notificar_whatsapp_lote) && function_exists('sige_fin_wpp_notificar_encarregados')) {
                                        $res_w = sige_fin_wpp_notificar_encarregados((int)$a->id, 'fatura', [
                                            'valor'      => (float)max(0, $total_mes),
                                            'vencimento' => $data_vencimento,
                                            'detalhes'   => $_det_str,
                                            'servico'    => $_det_str,
                                            'mes'        => wp_date('m/Y', strtotime($mes_ref_loop . '-01')),
                                            'id'         => (string)$a->id,
                                        ]);
                                        $wpp_stats['tentativas']++;
                                        if (($res_w['enviados'] ?? 0) > 0) {
                                            $wpp_stats['enviadas']++;
                                            if (function_exists('sige_notify_apply_batch_delay')) {
                                                sige_notify_apply_batch_delay((int)$a->id, 'fatura', (int)$sige_notif_batch_index, (int)$sige_notif_batch_total);
                                            }
                                            $sige_notif_batch_index++;
                                        } else {
                                            $wpp_stats['falhas']++;
                                            $wpp_falhas[] = "Aluno #{$a->id}: nenhum destinatário recebeu (pai/mãe sem números válidos?).";
                                        }
                                    } else {
                                        // Fallback legacy single-send
 if (!empty($notificar_whatsapp_lote)) { $ok = sige_fin_queue_whatsapp((int)$a->id, $tel_num, 'fatura', $msg); } else { $ok = false; }
 $wpp_stats['tentativas']++;
 if ($ok) {
 $wpp_stats['enviadas']++;
 if (function_exists('sige_notify_apply_batch_delay')) {
     sige_notify_apply_batch_delay((int)$a->id, 'fatura', (int)$sige_notif_batch_index, (int)$sige_notif_batch_total);
 }
 $sige_notif_batch_index++;
 } else {
 $wpp_stats['falhas']++;
 $wpp_falhas[] = "Aluno #{$a->id}: não foi possível agendar a mensagem.";
 }
                                    }
 }
 }

 // [FIX E-01] Enviar fatura por email ao encarregado
 // v12.11.9.32 - lançamento só envia e-mail quando o operador marca a opção.
 if (!empty($notificacao_evento_lote) && !empty($notificar_email_lote)
     && (function_exists('sige_fin_email_notificar_encarregados') || function_exists('sige_enviar_email_financeiro'))) {
 $email_stats['tentativas']++;
 if (function_exists('sige_fin_email_notificar_encarregados')) {
     $_res_email = sige_fin_email_notificar_encarregados((int)$a->id, 'fatura', $_det_str, (float)max(0, $total_mes), $data_vencimento);
     if ((int)($_res_email['enviados'] ?? 0) > 0) { $email_stats['enviadas']++; } else { $email_stats['falhas']++; $email_falhas[] = "Aluno #{$a->id}: nenhum e-mail válido recebeu a notificação."; }
 } elseif (function_exists('sige_enviar_email_financeiro')) {
     $ok_email = sige_enviar_email_financeiro((int)$a->id, 'fatura', $_det_str, (float)max(0, $total_mes), $data_vencimento);
     if ($ok_email === false) { $email_stats['falhas']++; $email_falhas[] = "Aluno #{$a->id}: não foi possível enviar e-mail."; } else { $email_stats['enviadas']++; }
 }
 }

 continue;
 }

 /**
 * ==========================================
 * MODO 2: Pacote Mensal (Mensalidade + Extras)
 * ==========================================
 */
 // (A) Mensalidade
 $srv_mens_ativo = $servico_base;
 if (!empty($a->regime_creche)) {
 $srv_creche_m = sige_fin_get_servico_creche($a->regime_creche, $servicos_ativos, ($a->classe ?? ''));
 if ($srv_creche_m) $srv_mens_ativo = $srv_creche_m;
 }
 // [v13] Detecção de regime_mensalidade (tempo_inteiro / meio_dia)
 if ($srv_mens_ativo === $servico_base && !empty($a->regime_mensalidade)
     && function_exists('sige_fin_get_servico_regime_mensalidade')) {
     $srv_regime_m = sige_fin_get_servico_regime_mensalidade($a->regime_mensalidade, $servicos_ativos);
     if ($srv_regime_m) $srv_mens_ativo = $srv_regime_m;
 }

 $valor_mensalidade = (float)($srv_mens_ativo->valor ?? 0);
 if (isset($a->mensalidade_base) && (float)$a->mensalidade_base > 0) {
 $valor_mensalidade = (float)$a->mensalidade_base;
 }

 $desc_mensalidade = (string)($srv_mens_ativo->nome . ' (' . wp_date('m/Y', strtotime($mes_ref_loop . '-01')) . ')');

 // [FIX F-02 + v15.2.1 FIX BUG #3] Validação de classe - só pula a MENSALIDADE,
 // NÃO o aluno todo. Antes: continue saltava (B) Transporte, (C) Extras flag-based
 // E (D) Atividades Extras dinâmicas (Judo/Piano/etc.). Resultado: aluno em turma
 // que não casa com $servico_base perdia também os extras tagados via
 // sige_aluno_atividades_extras. Agora a mensalidade é skipada mas o resto corre.
 $mensalidade_skipped = false;
 if (function_exists('sige_fin_servico_permitido_para_classe') && !sige_fin_servico_permitido_para_classe($srv_mens_ativo, ($a->classe ?? ''))) {
 $stats['mensalidade_ignorados']++;
 $mensalidade_skipped = true;
 }

 if (!$mensalidade_skipped) {
 $resM = sige_fin_upsert_lancamento((int)$a->id, (int)$srv_mens_ativo->id, $mes_ref_loop, $desc_mensalidade, $valor_mensalidade, $data_vencimento, $config_fin, $srv_mens_ativo);

 if ($resM['acao'] === 'criado') $stats['mensalidade_criados']++;
 elseif ($resM['acao'] === 'actualizado') $stats['mensalidade_actualizados']++;
 else $stats['mensalidade_ignorados']++;
 }

 // (B) Transporte
 $preco_rota = sige_transporte_get_preco_mensal((int)$a->id);
 if ($preco_rota === null || $preco_rota <= 0) {
 $stats['transporte_sem_rota']++;
 } else {
 if ($servico_transporte && !empty($servico_transporte->id)) {
 $rota = sige_transporte_get_rota_aluno((int)$a->id);
 $rota_nome = ($rota && !empty($rota->nome_rota)) ? (' | Rota: ' . $rota->nome_rota) : '';
 $desc_transp = (string)($servico_transporte->nome . ' (' . wp_date('m/Y', strtotime($mes_ref_loop . '-01')) . ')' . $rota_nome);
 $resT = sige_fin_upsert_lancamento((int)$a->id, (int)$servico_transporte->id, $mes_ref_loop, $desc_transp, (float)$preco_rota, $data_vencimento, $config_fin, $servico_transporte);
 if ($resT['acao'] === 'criado') $stats['transporte_criados']++;
 elseif ($resT['acao'] === 'actualizado') $stats['transporte_actualizados']++;
 else $stats['transporte_ignorados']++;
 } else {
 $stats['transporte_ignorados']++;
 }
 }

 // (C) Extras / Alimentação
 foreach ($MAPA_EXTRAS as $flag => $keywords) {
 if (!isset($a->$flag) || (int)$a->$flag !== 1) continue;
 $sExtra = null;
 $tipo_procurar = $TIPO_EXTRAS[$flag] ?? '';
 if ($tipo_procurar) {
 $sExtra = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM $tServ WHERE ativo=1 AND escola_id=%d AND LOWER(tipo)=%s LIMIT 1",
 $escola_id, strtolower($tipo_procurar)
 ));
 }
 if (!$sExtra) {
 $sExtra = sige_fin_encontrar_servico_por_keywords($servicos_ativos, $keywords);
 }
 if (!$sExtra || empty($sExtra->id)) {
 $stats['extras_sem_servico']++;
 continue;
 }
 $valor_extra = (float)($sExtra->valor ?? 0);
 $desc_extra = (string)($sExtra->nome . ' (' . wp_date('m/Y', strtotime($mes_ref_loop . '-01')) . ')');
 $resE = sige_fin_upsert_lancamento((int)$a->id, (int)$sExtra->id, $mes_ref_loop, $desc_extra, $valor_extra, $data_vencimento, $config_fin, $sExtra);
 if ($resE['acao'] === 'criado') $stats['extras_criados']++;
 elseif ($resE['acao'] === 'actualizado') $stats['extras_actualizados']++;
 else $stats['extras_ignorados']++;
 }

 // [v13] (D) Actividades Extras DINÂMICAS - Judo, Piano, Natação, etc.
 // Lê da tabela sige_aluno_atividades_extras (vínculo N:M flexível).
 // Só gera para serviços com tipo='atividade_extra' e recorrente=1.
 if (function_exists('sige_fin_get_atividades_extras_aluno')) {
 $atividades_aluno = sige_fin_get_atividades_extras_aluno((int)$a->id, $escola_id);
 foreach ($atividades_aluno as $sAtv) {
 if (empty($sAtv->id)) continue;
 // Só mensais; serviços avulsos (recorrente=0) são lançados manualmente
 if ((int)($sAtv->recorrente ?? 1) !== 1) continue;
 // Se tem tipo atividade_extra ou livros recorrente, prossegue
 $tipo_sAtv = strtolower((string)($sAtv->tipo ?? ''));
 if (!in_array($tipo_sAtv, ['atividade_extra', 'livros'], true)) continue;

 $valor_atv = (float)($sAtv->valor ?? 0);
 // Se valor = 0 (ex: Livros Cambridge sem valor definido), skip silencioso
 if ($valor_atv <= 0) continue;

 $desc_atv = (string)($sAtv->nome . ' (' . wp_date('m/Y', strtotime($mes_ref_loop . '-01')) . ')');
 $resA = sige_fin_upsert_lancamento((int)$a->id, (int)$sAtv->id, $mes_ref_loop, $desc_atv, $valor_atv, $data_vencimento, $config_fin, $sAtv);
 if ($resA['acao'] === 'criado') $stats['extras_criados']++;
 elseif ($resA['acao'] === 'actualizado') $stats['extras_actualizados']++;
 else $stats['extras_ignorados']++;
 }
 }

 // [FIX BLOQ-03] Se mês está bloqueado, não enviar notificação
 if (function_exists('sige_fin_mes_bloqueado') && sige_fin_mes_bloqueado((int)$a->id, (string)$mes_ref_loop)) {
 continue;
 }

 // WhatsApp via FILA - Modo 2
 $tel_raw = $a->whatsapp_notificacoes ?: ($a->telemovel_pai ?: $a->telemovel_mae);
 $tel_num = preg_replace('/[^0-9]/', '', (string)$tel_raw);
 $tel_num = sige_telefone_normalizar($tel_num);

 $total_mes = (float)$wpdb->get_var($wpdb->prepare("
 SELECT SUM(GREATEST(
 COALESCE(valor_original,0)
 + COALESCE(valor_transporte,0)
 + COALESCE(valor_extras,0)
 + COALESCE(NULLIF(valor_multa_cobrada,0), valor_multa, 0)
 - COALESCE(valor_desconto,0)
 - COALESCE(valor_desconto_especial,0)
 - COALESCE(valor_pago,0)
 , 0))
 FROM {$tLan} WHERE aluno_id=%d AND mes_referencia=%s AND status IN ('pendente','parcial','em_plano')
 ", (int)$a->id, (string)$mes_ref_loop));

 // [FIX NOTIF-03] Modo 2: Se total pendente é 0 ou negativo, skip
 if ($total_mes <= 0) {
 continue;
 }

 $total_fmt = number_format(max(0, $total_mes), 2, ',', '.');
 $aluno_nome = (string)($a->nome_completo ?? '');

 // [FIX SALDO-01] Saldo devedor por serviço
 $_lancs_fat2 = $wpdb->get_results($wpdb->prepare("
 SELECT descricao, valor_original,
 IFNULL(valor_multa, 0) AS valor_multa,
 IFNULL(valor_desconto, 0) AS valor_desconto,
 IFNULL(valor_desconto_especial, 0) AS valor_desconto_especial,
 IFNULL(valor_pago, 0) AS valor_pago,
 IFNULL(valor_transporte, 0) AS valor_transporte,
 IFNULL(valor_extras, 0) AS valor_extras,
 valor_multa_cobrada
 FROM {$tLan} 
 WHERE aluno_id = %d AND mes_referencia = %s AND status IN ('pendente','parcial','em_plano')
 ", (int)$a->id, (string)$mes_ref_loop));

 $_det2 = [];
 foreach ((array)$_lancs_fat2 as $_lf2) {
 $_saldo2 = sige_fin_saldo_lancamento($_lf2); // [FIX CRECHE-03] função canónica
 if ($_saldo2 > 0) {
 $_label2 = ($_lf2->descricao ?: 'Serviço');
 if ((float)$_lf2->valor_pago > 0) { $_label2 .= ' (saldo)'; }
 $_det2[] = "• " . $_label2 . " - " . number_format($_saldo2, 2, ',', '.') . " " . sige_moeda();
 }
 }
 $_det2_str = !empty($_det2) ? implode("\n", $_det2) : "• Mensalidades do mês - " . $total_fmt . " " . sige_moeda();

 if (!empty($notificar_whatsapp_lote) && !$tel_num) {
 $wpp_stats['sem_numero']++;
 } elseif (!empty($notificar_whatsapp_lote) && !function_exists('sige_fin_queue_whatsapp')) {
 $wpp_stats['falhas']++;
 $wpp_falhas[] = "Aluno #{$a->id}: serviço de mensagens indisponível.";
 } else {
 $total_mes = (float)$wpdb->get_var($wpdb->prepare("
 SELECT SUM(GREATEST(
 COALESCE(valor_original,0)
 + COALESCE(valor_transporte,0)
 + COALESCE(valor_extras,0)
 + COALESCE(NULLIF(valor_multa_cobrada,0), valor_multa, 0)
 - COALESCE(valor_desconto,0)
 - COALESCE(valor_desconto_especial,0)
 - COALESCE(valor_pago,0)
 , 0))
 FROM {$tLan}
 WHERE aluno_id = %d
 AND mes_referencia = %s
 AND status IN ('pendente','parcial','em_plano')
 ", (int)$a->id, (string)$mes_ref_loop));

 $total_fmt = number_format(max(0, $total_mes), 2, ',', '.');
 $aluno_nome = (string)($a->nome_completo ?? '');

 // [FIX W-01 + SALDO-01] Mensagem detalhada
 $_lancs_fat2 = $wpdb->get_results($wpdb->prepare("
 SELECT descricao, valor_original,
 IFNULL(valor_multa, 0) AS valor_multa,
 IFNULL(valor_desconto, 0) AS valor_desconto,
 IFNULL(valor_desconto_especial, 0) AS valor_desconto_especial,
 IFNULL(valor_pago, 0) AS valor_pago,
 IFNULL(valor_transporte, 0) AS valor_transporte,
 IFNULL(valor_extras, 0) AS valor_extras,
 valor_multa_cobrada
 FROM {$tLan} 
 WHERE aluno_id = %d AND mes_referencia = %s AND status IN ('pendente','parcial','em_plano')
 ", (int)$a->id, (string)$mes_ref_loop));

 $_det2 = [];
 foreach ((array)$_lancs_fat2 as $_lf2) {
 $_saldo2 = sige_fin_saldo_lancamento($_lf2); // [FIX CRECHE-03] função canónica
 if ($_saldo2 > 0) {
 $_label2 = ($_lf2->descricao ?: 'Serviço');
 if ((float)$_lf2->valor_pago > 0) { $_label2 .= ' (saldo)'; }
 $_det2[] = "• " . $_label2 . " - " . number_format($_saldo2, 2, ',', '.') . " " . sige_moeda();
 }
 }
 $_det2_str = !empty($_det2) ? implode("\n", $_det2) : "• Mensalidades do mês";

 if (function_exists('sige_wpp_render_finance_template')) {
 $_cfg_f2 = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1", sige_get_escola_id()));
                                $msg = sige_wpp_render_finance_template('fatura', $_cfg_f2, $a, [
 'valor' => (float)max(0, $total_mes),
 'vencimento' => $data_vencimento,
 'detalhes' => $_det2_str,
 'servico' => $_det2_str,
 'mes' => wp_date('m/Y', strtotime($mes_ref_loop . '-01')),
 'id' => (string)$a->id,
 ]);
 } else {
 $escola_nome = function_exists('sige_get_escola_perfil') ? (sige_get_escola_perfil()->nome_escola ?? '') : '';
                                $msg = "Informação de mensalidade" . ($escola_nome ? " - {$escola_nome}" : "") . "\n\n"
 . "Estimado Encarregado de Educação,\nA secretaria partilha a informação financeira dos serviços escolares de {$aluno_nome}, relativa a " . wp_date('m/Y', strtotime($mes_ref_loop . '-01')) . ", com vencimento a " . wp_date('d/m/Y', strtotime($data_vencimento)) . ":\n\n"
 . "Serviços:\n" . $_det2_str . "\n\n"
 . "Total: {$total_fmt} " . sige_moeda() . "\n\n"
                                     . "Agradecemos a sua atenção. Se precisar de esclarecer algum detalhe ou combinar uma data, pode responder por aqui.";
 }

 // v12.11.9.32 - lançamento só envia WhatsApp quando há opt-in e política validada.
 if (!empty($notificacao_evento_lote) && !empty($notificar_whatsapp_lote)) {
                                // [v13.5.1] Dual-send: factura via WhatsApp para pai E mãe
                                if (!empty($notificar_whatsapp_lote) && function_exists('sige_fin_wpp_notificar_encarregados')) {
                                    $res_w = sige_fin_wpp_notificar_encarregados((int)$a->id, 'fatura', [
                                        'valor'      => (float)max(0, $total_mes),
                                        'vencimento' => $data_vencimento,
                                        'detalhes'   => $_det2_str,
                                        'servico'    => $_det2_str,
                                        'mes'        => wp_date('m/Y', strtotime($mes_ref_loop . '-01')),
                                        'id'         => (string)$a->id,
                                    ]);
                                    $wpp_stats['tentativas']++;
                                    if (($res_w['enviados'] ?? 0) > 0) {
                                        $wpp_stats['enviadas']++;
                                        if (function_exists('sige_notify_apply_batch_delay')) {
                                            sige_notify_apply_batch_delay((int)$a->id, 'fatura', (int)$sige_notif_batch_index, (int)$sige_notif_batch_total);
                                        }
                                        $sige_notif_batch_index++;
                                    } else {
                                        $wpp_stats['falhas']++;
                                        $wpp_falhas[] = "Aluno #{$a->id}: nenhum destinatário recebeu (pai/mãe sem números válidos?).";
                                    }
                                } else {
                                    // Fallback legacy single-send
 if (!empty($notificar_whatsapp_lote)) { $ok = sige_fin_queue_whatsapp((int)$a->id, $tel_num, 'fatura', $msg); } else { $ok = false; }
 $wpp_stats['tentativas']++;
 if ($ok) {
 $wpp_stats['enviadas']++;
 if (function_exists('sige_notify_apply_batch_delay')) {
     sige_notify_apply_batch_delay((int)$a->id, 'fatura', (int)$sige_notif_batch_index, (int)$sige_notif_batch_total);
 }
 $sige_notif_batch_index++;
 } else {
 $wpp_stats['falhas']++;
 $wpp_falhas[] = "Aluno #{$a->id}: não foi possível agendar a mensagem.";
 }
                                }
 } // fecha policy guard whatsapp v12.11.9.32
 }

 // [FIX E-01] Enviar fatura por email
 // v12.11.9.32 - e-mail no lançamento é opcional e desligado por padrão.
 if (!empty($notificacao_evento_lote) && !empty($notificar_email_lote)
     && (function_exists('sige_fin_email_notificar_encarregados') || function_exists('sige_enviar_email_financeiro'))) {
 $email_stats['tentativas']++;
 if (function_exists('sige_fin_email_notificar_encarregados')) {
     $_res_email2 = sige_fin_email_notificar_encarregados((int)$a->id, 'fatura', $_det2_str, (float)max(0, $total_mes), $data_vencimento);
     if ((int)($_res_email2['enviados'] ?? 0) > 0) { $email_stats['enviadas']++; } else { $email_stats['falhas']++; $email_falhas[] = "Aluno #{$a->id}: nenhum e-mail válido recebeu a notificação."; }
 } elseif (function_exists('sige_enviar_email_financeiro')) {
     $ok_email2 = sige_enviar_email_financeiro((int)$a->id, 'fatura', $_det2_str, (float)max(0, $total_mes), $data_vencimento);
     if ($ok_email2 === false) { $email_stats['falhas']++; $email_falhas[] = "Aluno #{$a->id}: não foi possível enviar e-mail."; } else { $email_stats['enviadas']++; }
 }
 }
 }

 if (function_exists('sige_fin_log')) {
 sige_fin_log('geracao_lote_pacote_mensal', [
 'servico_base_id' => $servico_id,
 'mes_ref' => $mes_ref_loop,
 'turma_id' => $turma_alvo,
 'aluno_id' => $aluno_alvo ?: null,
 'modo_pacote' => (int)$modo_pacote,
 'stats' => $stats
 ]);
 }

 $label_mes = wp_date('M/Y', strtotime($mes_ref_loop . '-01'));

 // Sucesso apresentado em pop-up real no final da página.
 $sg_generator_success_modal_items[] = [
 'label_mes' => $label_mes,
 'stats' => $stats,
 'wpp_stats' => $wpp_stats,
 'email_stats' => $email_stats,
 'notificacoes' => [
     'whatsapp' => (bool)$notificar_whatsapp_lote,
     'email'    => (bool)$notificar_email_lote,
     'label'    => function_exists('sige_fin_notificacao_canal_label') ? sige_fin_notificacao_canal_label((bool)$notificar_whatsapp_lote, (bool)$notificar_email_lote) : '',
 ],
 ];

 } // ── FIM LOOP DE MESES ──────────────────────────────

 // Detalhes de falhas WhatsApp
 if (!empty($wpp_stats['falhas']) && !empty($wpp_falhas)) {
 echo '<div class="sg-alert sg-alert-warning sg-mb-4">
 <span class="sg-alert-icon"></span>
 <div class="sg-alert-content">
 <strong>WhatsApp: algumas mensagens falharam</strong>
 <details class="sg-mt-2">
 <summary class="sg-cursor-pointer sg-font-semibold">Ver detalhes (máx. 30)</summary>
 <ol class="sg-mt-2 sg-text-sm" style="margin-left: 18px;">';
 $lim = 0;
 foreach ($wpp_falhas as $linha) {
 $lim++;
 if ($lim > 30) break;
 echo '<li><span class="sg-text-xs">' . esc_html($linha) . '</span></li>';
 }
 echo '</ol>
</details>
</div>
</div>';
 }
 }
 }
 }
}
?>

<?php
$__sem_centro = function_exists('sige_fin_count_servicos_sem_centro') ? (int) sige_fin_count_servicos_sem_centro() : 0;
$__servicos_activos = is_array($servicos) ? count($servicos) : 0;
$__turmas_activas = is_array($turmas) ? count($turmas) : 0;
$__alunos_activos = is_array($alunos_lista) ? count($alunos_lista) : 0;
$__mes_actual_label = wp_date('m/Y');
?>

<div class="sg-finpro-wrap sg-generator-wrap">

  <section class="sg-finpro-hero sg-generator-hero" aria-label="Lançar mensalidades">
    <div class="sg-finpro-hero-copy">
      <span class="sg-finpro-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('rocket') : ''; ?> Tesouraria</span>
      <h1>Lançar Mensalidades</h1>
      <p>Crie mensalidades e outros serviços recorrentes de forma controlada, escolhendo serviço, período, turma ou aluno específico antes de confirmar.</p>
      <div class="sg-finpro-hero-actions">
        <a href="?page=sige-app&view=financeiro-lancamentos" class="sg-finpro-btn sg-finpro-btn-primary">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/></svg>
          Ver lançamentos
        </a>
        <a href="?page=sige-app&view=financeiro-config" class="sg-finpro-btn sg-finpro-btn-light">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06A2 2 0 1 1 7.1 4.29l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06A2 2 0 1 1 19.71 7.1l-.06.06A1.65 1.65 0 0 0 19.4 9c.14.31.45.51.79.51H21a2 2 0 1 1 0 4h-.81a1.65 1.65 0 0 0-.79 1.49z"/></svg>
          Preços e serviços
        </a>
      </div>
    </div>
    <div class="sg-finpro-hero-panel sg-generator-hero-panel">
      <span class="sg-finpro-mini-label">Ano lectivo activo</span>
      <strong><?php echo (int)$ano_letivo; ?></strong>
      <small>Mês de referência: <?php echo esc_html($__mes_actual_label); ?></small>
      <div class="sg-generator-hero-meta">
        <span><?php echo (int)$__servicos_activos; ?> serviços</span>
        <span><?php echo (int)$__turmas_activas; ?> turmas</span>
        <span><?php echo (int)$__alunos_activos; ?> alunos</span>
      </div>
    </div>
  </section>

  <?php if ($__sem_centro > 0): ?>
  <div class="sg-generator-notice sg-generator-notice-danger">
    <div class="sg-generator-notice-icon">!</div>
    <div>
      <strong>Há <?php echo (int)$__sem_centro; ?> serviço(s) sem centro de custo.</strong>
      <p>Antes de lançar mensalidades, classifique esses serviços para manter os relatórios financeiros organizados.</p>
    </div>
    <a href="?page=sige-app&view=financeiro-config" class="sg-finpro-btn sg-finpro-btn-primary">Configurar agora</a>
  </div>
  <?php endif; ?>

  <?php if (!empty($sg_generator_notice_messages)): ?>
    <?php foreach ((array)$sg_generator_notice_messages as $__sg_notice): ?>
      <div class="sg-generator-notice sg-generator-notice-warning">
        <div class="sg-generator-notice-icon">!</div>
        <div>
          <strong>Aviso sobre mensagens</strong>
          <p><?php echo esc_html((string)$__sg_notice); ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <section class="sg-finpro-kpi-grid sg-generator-kpis" aria-label="Resumo do lançamento">
    <article class="sg-finpro-kpi sg-finpro-tone-blue">
      <div class="sg-finpro-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div>
      <div><span>Serviços activos</span><strong><?php echo (int)$__servicos_activos; ?></strong><small>Disponíveis para lançamento</small></div>
    </article>
    <article class="sg-finpro-kpi sg-finpro-tone-green">
      <div class="sg-finpro-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
      <div><span>Alunos activos</span><strong><?php echo (int)$__alunos_activos; ?></strong><small>Matriculados no ano lectivo</small></div>
    </article>
    <article class="sg-finpro-kpi sg-finpro-tone-amber">
      <div class="sg-finpro-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg></div>
      <div><span>Turmas</span><strong><?php echo (int)$__turmas_activas; ?></strong><small>Com ano lectivo activo</small></div>
    </article>
    <article class="sg-finpro-kpi sg-finpro-tone-coral">
      <div class="sg-finpro-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg></div>
      <div><span>Atenções</span><strong><?php echo (int)$__sem_centro; ?></strong><small>Serviços por classificar</small></div>
    </article>
  </section>

  <div class="sg-finpro-grid sg-generator-grid">
    <section class="sg-finpro-card sg-generator-form-card">
      <div class="sg-finpro-card-head">
        <div>
          <span class="sg-finpro-section-icon sg-finpro-soft-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg></span>
          <div>
            <h2>Novo lançamento em lote</h2>
            <p>Escolha exactamente o que pretende lançar antes de confirmar.</p>
          </div>
        </div>
      </div>

      <form method="post" id="sg-generator-form" class="sg-generator-form">
        <?php wp_nonce_field('sige_fin_gerar_lote'); ?>
        <input type="hidden" name="sige_gerar_lote" value="1">
        <input type="hidden" name="notificar_whatsapp" value="0">
        <input type="hidden" name="notificar_email" value="0">

        <div class="sg-generator-alert sg-generator-alert-info">
          <strong>Mensagens desactivadas por padrão</strong>
          <span>O lançamento cria os valores para controlo financeiro. Só envia WhatsApp ou E-mail se marcar essa opção nesta operação.</span>
        </div>

        <div class="sg-generator-form-grid">
          <div class="sg-generator-field sg-generator-span-2">
            <label for="sg_servico_id">Serviço base</label>
            <select name="servico_id" id="sg_servico_id" required class="sg-input">
              <option value="">Seleccione o serviço</option>
              <?php foreach ($servicos as $s): ?>
              <option value="<?php echo (int)$s->id; ?>"><?php echo esc_html($s->nome); ?> - <?php echo number_format((float)$s->valor, 2, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></option>
              <?php endforeach; ?>
            </select>
            <small>Quando for mensalidade, o sistema considera automaticamente transporte e extras quando aplicável.</small>
          </div>

          <div class="sg-generator-field">
            <label for="campo_mes_ref">Período</label>
            <input type="month" name="mes_ref" id="campo_mes_ref" value="<?php echo esc_attr(wp_date('Y-m')); ?>" class="sg-input">
            <small>Use para lançar apenas um mês.</small>
          </div>

          <label class="sg-generator-check sg-generator-check-card" for="chk_ano_todo">
            <input type="checkbox" name="gerar_ano_todo" value="1" id="chk_ano_todo">
            <span><strong>Gerar ano todo</strong><small>Criar lançamentos para os 12 meses de <?php echo (int)$ano_letivo; ?>.</small></span>
          </label>

          <div class="sg-generator-field sg-generator-span-2">
            <label for="campo_turma_alvo">Turma alvo</label>
            <select name="turma_alvo" id="campo_turma_alvo" class="sg-input">
              <option value="0">Toda a escola</option>
              <?php foreach ($turmas as $t): ?>
              <option value="<?php echo (int)$t->id; ?>"><?php echo esc_html($t->classe . ' - ' . $t->nome); ?></option>
              <?php endforeach; ?>
            </select>
            <small id="turma_nota">Será ignorada se seleccionar um aluno específico.</small>
          </div>

          <div class="sg-generator-field sg-generator-span-2">
            <label for="campo_aluno_alvo">Aluno específico</label>
            <select name="aluno_alvo" id="campo_aluno_alvo" class="sg-input">
              <option value="0">Todos os alunos da turma seleccionada</option>
              <?php
              $turma_grupo_atual = null;
              foreach ($alunos_lista as $al):
                $grupo = trim($al->classe . ' - ' . $al->turma_nome);
                if ($grupo !== $turma_grupo_atual):
                  if ($turma_grupo_atual !== null) echo '</optgroup>';
                  echo '<optgroup label="' . esc_attr($grupo) . '">';
                  $turma_grupo_atual = $grupo;
                endif;
              ?>
              <option value="<?php echo (int)$al->id; ?>"><?php echo esc_html($al->nome_completo); ?></option>
              <?php endforeach; ?>
              <?php if ($turma_grupo_atual !== null) echo '</optgroup>'; ?>
            </select>
            <small>Use para corrigir ou lançar mensalidades de apenas um aluno.</small>
          </div>
        </div>

        <div class="sg-generator-options">
          <label class="sg-generator-check" for="sg_modo_pacote_mensal">
            <input type="checkbox" name="modo_pacote_mensal" value="1" id="sg_modo_pacote_mensal">
            <span><strong>Pacote mensal</strong><small>Inclui mensalidade, transporte e actividades/extras de acordo com a ficha do aluno.</small></span>
          </label>

          <label class="sg-generator-check sg-generator-check-warning" for="sg_forcar_todos_subscricao">
            <input type="checkbox" name="forcar_todos_subscricao" value="1" id="sg_forcar_todos_subscricao">
            <span><strong>Aplicar a todos os alunos seleccionados</strong><small>Use apenas quando a cobrança deve ser lançada para todos, mesmo sem subscrição marcada na ficha do aluno.</small></span>
          </label>
        </div>

        <div class="sg-generator-options sg-generator-notification-options" aria-label="Opções de comunicação do lançamento">
          <label class="sg-generator-check" for="sg_notificar_whatsapp">
            <input type="checkbox" name="notificar_whatsapp" value="1" id="sg_notificar_whatsapp">
            <span><strong>Enviar WhatsApp aos encarregados</strong><small>As mensagens entram na fila e são espaçadas pelo sistema; não são disparadas todas no clique.</small></span>
          </label>

          <label class="sg-generator-check" for="sg_notificar_email">
            <input type="checkbox" name="notificar_email" value="1" id="sg_notificar_email">
            <span><strong>Enviar E-mail aos encarregados</strong><small>Use quando a escola quer avisar formalmente sobre o lançamento/serviços do período.</small></span>
          </label>
        </div>

        <div class="sg-generator-alert sg-generator-alert-warning" id="sg_generator_notify_warning" style="display:none;">
          <strong>Atenção ao envio em massa</strong>
          <span>Para reduzir risco de restrição no WhatsApp, use esta opção apenas para comunicações necessárias e para contactos que aceitaram receber mensagens da escola. Em lotes grandes, o sistema agenda os envios com espaçamento.</span>
        </div>

        <div class="sg-generator-alert sg-generator-alert-soft">
          <strong>Regras financeiras preservadas</strong>
          <span>Descontos, multas, transporte, extras e mensalidade base continuam a ser calculados pelas regras já configuradas pela escola.</span>
        </div>

        <div class="sg-generator-actions">
          <button type="submit" class="sg-finpro-btn sg-finpro-btn-primary sg-generator-submit">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            Gerar lançamentos
          </button>
        </div>
      </form>
    </section>

    <aside class="sg-finpro-card sg-generator-guide-card">
      <div class="sg-finpro-card-head">
        <div>
          <span class="sg-finpro-section-icon sg-finpro-soft-amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
          <div>
            <h2>Antes de lançar</h2>
            <p>Pequenas verificações evitam cobranças erradas.</p>
          </div>
        </div>
      </div>
      <div class="sg-generator-guide-list">
        <div><strong>1</strong><span>Confirme se o serviço escolhido tem o preço correcto.</span></div>
        <div><strong>2</strong><span>Verifique se está a lançar para o mês, turma ou aluno certo.</span></div>
        <div><strong>3</strong><span>Use “Aplicar a todos” apenas para cobranças excepcionais.</span></div>
        <div><strong>4</strong><span>Depois de gerar, acompanhe os valores em Lançamentos e no Painel Financeiro.</span></div>
      </div>
    </aside>
  </div>
</div>

<div id="sg-generator-confirm-modal" class="sg-modal-backdrop sg-generator-modal" aria-hidden="true">
  <div class="sige-lanc-modal sg-generator-modal-card" role="dialog" aria-modal="true" aria-labelledby="sg-generator-confirm-title">
    <div class="sige-lanc-modal-header">
      <h3 id="sg-generator-confirm-title" class="sige-lanc-modal-title">Confirmar lançamento</h3>
    </div>
    <div class="sige-lanc-modal-body">
      <p class="sg-generator-modal-text">Antes de continuar, confirme se o serviço, o período e o grupo seleccionado estão correctos.</p>
      <div class="sg-expense-confirm-box sg-generator-confirm-box">
        <span>Serviço</span><strong id="sg-generator-confirm-service">-</strong>
        <span>Período</span><strong id="sg-generator-confirm-period">-</strong>
        <span>Turma</span><strong id="sg-generator-confirm-class">-</strong>
        <span>Aluno</span><strong id="sg-generator-confirm-student">-</strong>
        <span>Opções</span><strong id="sg-generator-confirm-options">-</strong>
        <span>Mensagens</span><strong id="sg-generator-confirm-notifications">Sem envio de mensagens</strong>
      </div>
      <div class="sg-generator-alert sg-generator-alert-warning sg-generator-modal-warning">
        <strong>Atenção</strong>
        <span>Esta acção cria ou actualiza lançamentos financeiros. Se activar WhatsApp ou E-mail, as mensagens serão geradas de acordo com os canais seleccionados e com as guardas de segurança do sistema.</span>
      </div>
    </div>
    <div class="sige-lanc-modal-footer">
      <button type="button" class="sg-finpro-btn sg-finpro-btn-light" data-sg-generator-close>Voltar e corrigir</button>
      <button type="button" class="sg-finpro-btn sg-finpro-btn-primary" id="sg-generator-confirm-submit">Confirmar e gerar</button>
    </div>
  </div>
</div>

<?php if (!empty($sg_generator_success_modal_items)): ?>
<div id="sg-generator-success-modal" class="sg-modal-backdrop sg-generator-modal sg-generator-success-modal" aria-hidden="true">
  <div class="sige-lanc-modal sg-generator-modal-card sg-generator-success-card" role="dialog" aria-modal="true" aria-labelledby="sg-generator-success-title">
    <div class="sige-lanc-modal-header sg-generator-success-header">
      <span class="sg-generator-success-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
      </span>
      <div>
        <h3 id="sg-generator-success-title" class="sige-lanc-modal-title">Lançamentos gerados com sucesso</h3>
        <p class="sg-generator-modal-text">A operação foi concluída. Confira o resumo abaixo e acompanhe os valores em Lançamentos e no Painel Financeiro.</p>
      </div>
    </div>
    <div class="sige-lanc-modal-body">
      <div class="sg-generator-success-list">
        <?php foreach ($sg_generator_success_modal_items as $item):
          $stats_item = (array)($item['stats'] ?? []);
          $wpp_item = (array)($item['wpp_stats'] ?? []);
          $email_item = (array)($item['email_stats'] ?? []);
          $notif_item = (array)($item['notificacoes'] ?? []);
          $notif_label = (string)($notif_item['label'] ?? 'Sem notificação');
          $total_criados = (int)($stats_item['mensalidade_criados'] ?? 0) + (int)($stats_item['transporte_criados'] ?? 0) + (int)($stats_item['extras_criados'] ?? 0);
          $total_actualizados = (int)($stats_item['mensalidade_actualizados'] ?? 0) + (int)($stats_item['transporte_actualizados'] ?? 0) + (int)($stats_item['extras_actualizados'] ?? 0);
          $total_ignorados = (int)($stats_item['mensalidade_ignorados'] ?? 0) + (int)($stats_item['transporte_ignorados'] ?? 0) + (int)($stats_item['extras_ignorados'] ?? 0);
        ?>
        <article class="sg-generator-success-month">
          <div class="sg-generator-success-month-head">
            <strong><?php echo esc_html($item['label_mes'] ?? 'Período'); ?></strong>
            <span>Lote concluído</span>
          </div>
          <div class="sg-generator-success-grid">
            <div><span>Alunos processados</span><strong><?php echo (int)($stats_item['alunos_processados'] ?? 0); ?></strong></div>
            <div><span>Criados</span><strong><?php echo (int)$total_criados; ?></strong></div>
            <div><span>Actualizados</span><strong><?php echo (int)$total_actualizados; ?></strong></div>
            <div><span>Ignorados</span><strong><?php echo (int)$total_ignorados; ?></strong></div>
          </div>
          <div class="sg-generator-success-details">
            <span>Mensalidade: <?php echo (int)($stats_item['mensalidade_criados'] ?? 0); ?> criados · <?php echo (int)($stats_item['mensalidade_actualizados'] ?? 0); ?> actualizados</span>
            <span>Transporte: <?php echo (int)($stats_item['transporte_criados'] ?? 0); ?> criados · <?php echo (int)($stats_item['transporte_actualizados'] ?? 0); ?> actualizados · <?php echo (int)($stats_item['transporte_sem_rota'] ?? 0); ?> sem rota</span>
            <span>Extras: <?php echo (int)($stats_item['extras_criados'] ?? 0); ?> criados · <?php echo (int)($stats_item['extras_actualizados'] ?? 0); ?> actualizados · <?php echo (int)($stats_item['extras_sem_servico'] ?? 0); ?> sem serviço</span>
            <span>Notificações: <?php echo esc_html($notif_label); ?></span>
            <span>WhatsApp: <?php echo (int)($wpp_item['tentativas'] ?? 0); ?> tentativas · <?php echo (int)($wpp_item['enviadas'] ?? 0); ?> agendadas · <?php echo (int)($wpp_item['falhas'] ?? 0); ?> falhas · <?php echo (int)($wpp_item['sem_numero'] ?? 0); ?> sem número</span>
            <span>E-mail: <?php echo (int)($email_item['tentativas'] ?? 0); ?> tentativas · <?php echo (int)($email_item['enviadas'] ?? 0); ?> enviados · <?php echo (int)($email_item['falhas'] ?? 0); ?> falhas</span>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="sige-lanc-modal-footer">
      <button type="button" class="sg-finpro-btn sg-finpro-btn-primary" data-sg-generator-success-close>Entendi</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script <?php echo sige_csp_script_attr(); ?>>
(function(){
  const form = document.getElementById('sg-generator-form');
  const modal = document.getElementById('sg-generator-confirm-modal');
  if (!form || !modal) return;

  const monthInput = document.getElementById('campo_mes_ref');
  const allYear = document.getElementById('chk_ano_todo');
  const classSelect = document.getElementById('campo_turma_alvo');
  const studentSelect = document.getElementById('campo_aluno_alvo');
  const turmaNote = document.getElementById('turma_nota');
  const notifyWhatsapp = document.getElementById('sg_notificar_whatsapp');
  const notifyEmail = document.getElementById('sg_notificar_email');
  const notifyWarning = document.getElementById('sg_generator_notify_warning');

  const textOf = (select) => {
    if (!select || !select.options || select.selectedIndex < 0) return '-';
    return (select.options[select.selectedIndex].text || '').trim() || '-';
  };
  const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value || '-'; };

  const syncInputs = () => {
    if (monthInput && allYear) {
      monthInput.disabled = allYear.checked;
      monthInput.style.opacity = allYear.checked ? '0.48' : '1';
    }
    if (studentSelect && classSelect) {
      const hasStudent = studentSelect.value !== '0';
      classSelect.disabled = hasStudent;
      classSelect.style.opacity = hasStudent ? '0.48' : '1';
      if (turmaNote) {
        turmaNote.textContent = hasStudent ? 'Turma ignorada - aluno específico seleccionado.' : 'Será ignorada se seleccionar um aluno específico.';
      }
    }
    if (notifyWarning) {
      const hasNotify = (notifyWhatsapp && notifyWhatsapp.checked) || (notifyEmail && notifyEmail.checked);
      notifyWarning.style.display = hasNotify ? '' : 'none';
    }
  };
  syncInputs();
  if (allYear) allYear.addEventListener('change', syncInputs);
  if (studentSelect) studentSelect.addEventListener('change', syncInputs);
  if (notifyWhatsapp) notifyWhatsapp.addEventListener('change', syncInputs);
  if (notifyEmail) notifyEmail.addEventListener('change', syncInputs);

  const openModal = () => {
    const serviceSelect = document.getElementById('sg_servico_id');
    const packageMode = document.getElementById('sg_modo_pacote_mensal');
    const forceAll = document.getElementById('sg_forcar_todos_subscricao');
    const options = [];
    options.push(packageMode && packageMode.checked ? 'Pacote mensal activo' : 'Serviço seleccionado apenas');
    if (forceAll && forceAll.checked) options.push('Aplicar a todos os alunos seleccionados');
    const canais = [];
    if (notifyWhatsapp && notifyWhatsapp.checked) canais.push('WhatsApp');
    if (notifyEmail && notifyEmail.checked) canais.push('E-mail');
    if (allYear && allYear.checked && canais.length) options.push('Mensagens serão desligadas no modo ano todo');

    setText('sg-generator-confirm-service', textOf(serviceSelect));
    setText('sg-generator-confirm-period', allYear && allYear.checked ? 'Ano todo' : (monthInput && monthInput.value ? monthInput.value : 'Mês actual'));
    setText('sg-generator-confirm-class', studentSelect && studentSelect.value !== '0' ? 'Ignorada por aluno específico' : textOf(classSelect));
    setText('sg-generator-confirm-student', studentSelect && studentSelect.value !== '0' ? textOf(studentSelect) : 'Todos os alunos seleccionados');
    setText('sg-generator-confirm-options', options.join(' · '));
    setText('sg-generator-confirm-notifications', canais.length ? canais.join(' + ') : 'Sem envio de mensagens');

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('sg-modal-open');
  };
  const closeModal = () => {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('sg-modal-open');
  };

  form.addEventListener('submit', function(e){
    if (form.dataset.confirmed === '1') return;
    e.preventDefault();
    openModal();
  });
  modal.querySelectorAll('[data-sg-generator-close]').forEach(btn => btn.addEventListener('click', closeModal));
  modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal(); });

  const confirmBtn = document.getElementById('sg-generator-confirm-submit');
  if (confirmBtn) {
    confirmBtn.addEventListener('click', function(){
      form.dataset.confirmed = '1';
      closeModal();
      if (typeof HTMLFormElement !== 'undefined' && HTMLFormElement.prototype.submit) {
        HTMLFormElement.prototype.submit.call(form);
      } else {
        form.submit();
      }
    });
  }
})();

(function(){
  const successModal = document.getElementById('sg-generator-success-modal');
  if (!successModal) return;

  const openSuccessModal = () => {
    successModal.classList.add('is-open');
    successModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('sg-modal-open');
  };
  const closeSuccessModal = () => {
    successModal.classList.remove('is-open');
    successModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('sg-modal-open');
  };

  successModal.querySelectorAll('[data-sg-generator-success-close]').forEach(btn => btn.addEventListener('click', closeSuccessModal));
  successModal.addEventListener('click', function(e){ if (e.target === successModal) closeSuccessModal(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && successModal.classList.contains('is-open')) closeSuccessModal(); });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', openSuccessModal, { once: true });
  } else {
    openSuccessModal();
  }
})();
</script>
