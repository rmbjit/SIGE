<?php
/**
 * SIGE SoftGenial - Dashboard Financeiro Executivo
 *
 * v2.1 - Abril 2026
 * - ABSPATH guard adicionado
 * - CSS Design System (Navy/Amber) com hero, cards, animations
 * - date() → wp_date(), funções guardadas
 * - $escola_id normalizado
 *
 * v14.1.0 - 19 Abril 2026
 * - Duplicação de docblock e ABSPATH removida (dívida cosmética)
 * - 2º capability check removido (unificado com guard inicial L13)
 * - $ano propagado para sige_kpi_top_devedores (respeita filtro da UI)
 *
 * Objetivo:
 * - KPIs executivos (Previsto / Recebido / Pendente / Em atraso / Taxa de cobrança)
 * - Gráficos (Previsto vs Recebido por mês; Mix por serviço; Métodos de pagamento)
 * - Top devedores e Últimas transações
 *
 * Notas:
 * - Usa $wpdb->prefix para suportar qualquer prefixo (ex: wpq1_)
 * - Não altera dados, apenas consulta
 */
if (!defined('ABSPATH')) exit;
// Guard de acesso - Tesouraria
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['financeiro.dashboard_ver','financeiro.ver'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_financeiro']
)) return;
global $wpdb;
// ----------------------------
// 1) Configurações / Filtros
// ----------------------------
// ── escola_id via helper (cache estático em finance-core) ─────────────────
$escola_id = function_exists('sige_get_escola_id') ? sige_get_escola_id() : 0;
$ano_letivo = function_exists('sige_fin_get_ano_letivo_master') ? (int)sige_fin_get_ano_letivo_master() : (int)wp_date('Y');
$ano = sige_fin_get_int('ano', $ano_letivo);
// [BUG-CRIT-06 FIX v13.2.1] Defesa em profundidade: se ano_letivo mesmo assim
// vier fora do range válido (raro mas possível via URL manipulado), cair para
// ano civil actual em vez de ficar preso em 0.
if ($ano < 2000 || $ano > 2100) $ano = (int)wp_date('Y');

// [12.9.77] Dashboard financeiro operacional: filtros por período, serviço e aluno.
// Mantém os KPIs executivos existentes, mas adiciona uma leitura prática dos
// valores recebidos no dia/mês/ano seleccionado, com cortes por serviço e aluno.
if (!function_exists('sige_findash_date_ymd')) {
function sige_findash_date_ymd($value, $fallback = '') {
    $value = trim((string)$value);
    $fallback = $fallback !== '' ? (string)$fallback : (function_exists('sige_mz_date') ? sige_mz_date('Y-m-d') : wp_date('Y-m-d'));
    if ($value === '') return $fallback;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
    $ts = strtotime($value);
    return $ts ? gmdate('Y-m-d', $ts) : $fallback;
}
}
if (!function_exists('sige_findash_month_ym')) {
function sige_findash_month_ym($value, $fallback = '') {
    $value = trim((string)$value);
    $fallback = preg_match('/^\d{4}-\d{2}$/', $fallback) ? $fallback : (function_exists('sige_mz_date') ? sige_mz_date('Y-m') : wp_date('Y-m'));
    return preg_match('/^\d{4}-\d{2}$/', $value) ? $value : $fallback;
}
}
if (!function_exists('sige_findash_period_bounds')) {
function sige_findash_period_bounds($periodo, $dia_ref, $mes_ref, $ano_ref) {
    $periodo = in_array($periodo, ['diario','mensal','anual'], true) ? $periodo : 'mensal';
    $dia_ref = sige_findash_date_ymd($dia_ref);
    $mes_ref = sige_findash_month_ym($mes_ref, substr($dia_ref, 0, 7));
    $ano_ref = (int)$ano_ref;
    if ($ano_ref < 2000 || $ano_ref > 2100) $ano_ref = (int)substr($dia_ref, 0, 4);
    if ($periodo === 'diario') {
        return [$dia_ref, $dia_ref, 'Diário · ' . wp_date('d/m/Y', strtotime($dia_ref))];
    }
    if ($periodo === 'anual') {
        return [sprintf('%04d-01-01', $ano_ref), sprintf('%04d-12-31', $ano_ref), 'Anual · ' . $ano_ref];
    }
    $inicio = $mes_ref . '-01';
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $inicio);
    if (!$dt) {
        $mes_ref = function_exists('sige_mz_date') ? sige_mz_date('Y-m') : wp_date('Y-m');
        $inicio = $mes_ref . '-01';
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $inicio);
    }
    return [$inicio, $dt->modify('last day of this month')->format('Y-m-d'), 'Mensal · ' . wp_date('F Y', strtotime($inicio))];
}
}

$dash_periodo = sanitize_key((string)(sige_fin_get_param('dash_periodo', 'mensal')));
if (!in_array($dash_periodo, ['diario','mensal','anual'], true)) $dash_periodo = 'mensal';
$dash_dia = sige_findash_date_ymd(sige_fin_get_param('dash_dia'), function_exists('sige_mz_date') ? sige_mz_date('Y-m-d') : wp_date('Y-m-d'));
$dash_mes = sige_findash_month_ym(sige_fin_get_param('dash_mes'), substr($dash_dia, 0, 7));
$dash_ano = sige_fin_get_int('dash_ano', $ano);
if ($dash_ano < 2000 || $dash_ano > 2100) $dash_ano = $ano;
$dash_servico_id = sige_fin_get_int('dash_servico_id', 0);
$dash_aluno_q = trim((string)sige_fin_get_param('dash_aluno_q', ''));
[$dash_inicio, $dash_fim, $dash_periodo_label] = sige_findash_period_bounds($dash_periodo, $dash_dia, $dash_mes, $dash_ano);

// [v13.4.0 BLOCO 3] Filtro universal por Centro. sige_fin_centro_ativo() valida
// contra a lista de centros activos da escola actual (defesa multi-tenant).
// 0 = todos os centros (não filtrar); N = apenas centro N.
$centro_id_filtro = function_exists('sige_fin_centro_ativo') ? sige_fin_centro_ativo() : 0;

// [12.9.8.7 PERFORMANCE] Cache curto do HTML do dashboard financeiro.
if (function_exists('get_transient') && function_exists('set_transient') && empty($_POST)) {
    $__sige_fin_dash_cache_key = 'sige_fin_dash_html_' . md5((string)get_current_user_id() . '|' . (string)$escola_id . '|' . (string)$ano . '|' . (string)$centro_id_filtro . '|' . (string)$dash_periodo . '|' . (string)$dash_dia . '|' . (string)$dash_mes . '|' . (string)$dash_ano . '|' . (string)$dash_servico_id . '|' . (string)$dash_aluno_q . '|' . (defined('SIGE_VERSION') ? SIGE_VERSION : ''));
    $__sige_fin_dash_cached = get_transient($__sige_fin_dash_cache_key);
    if (is_string($__sige_fin_dash_cached) && $__sige_fin_dash_cached !== '') { echo $__sige_fin_dash_cached; return; }
    ob_start();
}


$hoje = current_time('Y-m-d');
$amanha = date('Y-m-d', strtotime($hoje . ' +1 day'));
$mes_atual = current_time('m');
$ano_atual = current_time('Y');
$inicio_mes_atual = $ano_atual . '-' . $mes_atual . '-01';
$inicio_mes_seguinte = date('Y-m-d', strtotime($inicio_mes_atual . ' +1 month'));
$inicio_ano = (string)$ano . '-01-01';
$inicio_ano_seguinte = (string)($ano + 1) . '-01-01';
// Tabelas
$tP   = $wpdb->prefix . 'sige_fin_pagamentos';
$tL   = $wpdb->prefix . 'sige_fin_lancamentos';
$tS   = $wpdb->prefix . 'sige_fin_servicos';
$tA   = $wpdb->prefix . 'sige_alunos';
// Helper: saldo do lançamento (valor a receber)
// [FIX DASH-01] Fórmula completa: inclui transporte e extras.
// Versão anterior omitia estas parcelas, causando sub-reporte nos KPIs
// de dívida para alunos com transporte escolar ou serviços extras.
$saldo_expr = sige_fin_saldo_sql('l'); // usa função canónica do finance-core

// ----------------------------
// 1.B) [B13] Alerta de conciliação - divergências valor_pago vs pagamentos
// ----------------------------
$concil_total = 0;
$concil_soma = 0.0;
$tV_concil = $wpdb->prefix . 'sige_v_reconciliacao';
if ($wpdb->get_var("SHOW TABLES LIKE '$tV_concil'") === $tV_concil) {
    $concil_total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$tV_concil` WHERE escola_id = %d", $escola_id));
    $concil_soma = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(divergencia), 0) FROM `$tV_concil` WHERE escola_id = %d", $escola_id));
}

// ----------------------------
// 2) KPIs Executivos - delegados ao motor consolidado (fin-kpi-engine.php)
// ----------------------------
// [v13.3.0] Refactor Bloco 2: cálculos de KPIs passam a ser feitos por uma
// camada única (includes/fin-kpi-engine.php), eliminando duplicação de
// fórmulas entre dashboard, relatório mensal, extratos e devedores.
// Comportamento visível ao utilizador é idêntico ao da v13.2.1.
$total_previsto_recebido_ratio = sige_kpi_taxa_cobranca($escola_id, $ano, $centro_id_filtro);
$total_recebido = sige_kpi_receita_ano($escola_id, $ano, $centro_id_filtro);
$total_despesas_ano = sige_kpi_despesa_ano($escola_id, $ano, $centro_id_filtro);
$total_pendente = sige_kpi_pendente($escola_id, $ano, $centro_id_filtro);
$total_em_plano = sige_kpi_em_plano($escola_id, $centro_id_filtro);
$total_atraso = sige_kpi_atraso($escola_id, $ano, $centro_id_filtro);
$taxa_cobranca = $total_previsto_recebido_ratio;

// "Previsto" ainda é necessário para exibição (não só a taxa). Como o engine
// só expõe taxa, derivamos o previsto a partir das outras primitivas: se a
// taxa é recebido/previsto, então previsto = recebido/(taxa/100) quando
// taxa > 0. Caso contrário, calcular directamente.
if ($taxa_cobranca > 0) {
    $total_previsto = $total_recebido / ($taxa_cobranca / 100);
} else {
    // Fallback para ano sem pagamentos: query directa com mesma fórmula do engine
    $_bruto = sige_fin_total_bruto_sql('l');
    $_centro_sql = $centro_id_filtro > 0 ? ' AND l.centro_id = ' . (int)$centro_id_filtro : '';
    $total_previsto = (float)$wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM({$_bruto}), 0)
         FROM $tL l
         WHERE l.escola_id = %d
           AND LEFT(l.mes_referencia, 4) = %s
           AND l.status NOT IN ('cancelado','isento')
           {$_centro_sql}",
        $escola_id, (string)$ano
    ));
}

// Hoje/mês continuam a ser calculados inline (são janelas temporais diárias,
// não cobertas pelo contrato das 12 funções do engine Bloco 2).
// [v13.4.0] Filtro por centro aplicado directamente - mantém coerência com
// os KPIs principais da página.
$_centro_sql_p = $centro_id_filtro > 0 ? ' AND centro_id = ' . (int)$centro_id_filtro : '';
$total_hoje = (float)$wpdb->get_var(
    $wpdb->prepare("SELECT COALESCE(SUM(valor_pago),0) FROM $tP WHERE escola_id=%d AND data_pagamento >= %s AND data_pagamento < %s {$_centro_sql_p}", $escola_id, $hoje, $amanha)
);
$total_mes = (float)$wpdb->get_var(
    $wpdb->prepare("SELECT COALESCE(SUM(valor_pago),0) FROM $tP WHERE escola_id=%d AND data_pagamento >= %s AND data_pagamento < %s {$_centro_sql_p}", $escola_id, $inicio_mes_atual, $inicio_mes_seguinte)
);
// ----------------------------
// 3) Dados para gráficos - delegados ao motor consolidado
// ----------------------------
// 3.1 + 3.2 Previsto e Recebido por mês (num único call ao engine)
$__series = sige_kpi_por_mes($escola_id, $ano, $centro_id_filtro);
$previsto_mensal = array_fill(1, 12, 0.0);
$recebido_mensal = array_fill(1, 12, 0.0);
for ($m = 1; $m <= 12; $m++) {
    // Nota: bucket 0 ('YYYY-00', inscrições anuais) é somado ao mês 1
    // para não perder informação no gráfico principal (permanece inscrição
    // visível). Contrato do engine preserva o bucket 0 separado para quem
    // quiser discriminá-lo.
    $previsto_mensal[$m] = (float)($__series['previsto'][$m] ?? 0.0);
    $recebido_mensal[$m] = (float)($__series['recebido'][$m] ?? 0.0);
}
// Inscrições anuais (mês 0) são adicionadas ao mês 1 no gráfico mensal.
// Esta decisão mantém o comportamento visível da v13.2.1 (que usava
// SUBSTRING(mes_referencia,6,2) sem cast: '00' ordena antes de '01' e
// acabava a aparecer com chave 0, fora do range 1-12 do array_fill).
if (!empty($__series['previsto'][0])) {
    $previsto_mensal[1] += (float)$__series['previsto'][0];
}

// 3.3 Mix por serviço (saldo pendente)
$mix_servico = sige_kpi_por_servico($escola_id, $ano, $centro_id_filtro);
// Limitar a 8 entradas como estava antes (dashboard não quer lista gigante)
$mix_servico = array_slice($mix_servico, 0, 8);
$labels_serv = [];
$data_serv   = [];
foreach ($mix_servico as $r) {
    $labels_serv[] = (string)($r->nome ?: 'Serviço');
    $data_serv[]   = (float)$r->pendente;
}
// 3.4 Métodos de pagamento (total recebido)
$metodos = sige_kpi_por_metodo($escola_id, $ano, $centro_id_filtro);
$labels_met = [];
$data_met = [];
foreach ($metodos as $m) {
    $key = (string)$m->metodo;
    $lbl = function_exists('sige_fin_metodo_pagamento_label') ? sige_fin_metodo_pagamento_label($key) : ucfirst(str_replace('_', ' ', $key));
    $labels_met[] = $lbl;
    $data_met[] = (float)$m->total;
}
// ----------------------------
// 4) Tabelas (execução)
// ----------------------------
// Helper: verificar se uma coluna existe numa tabela (compatibilidade)
if (!function_exists('sige_fin_col_exists')) {
function sige_fin_col_exists($table, $col): bool {
    global $wpdb;
    static $cache = [];
    $col = trim((string)$col);
    if ($col === '') return false;
    $key = $table . '.' . $col;
    if (!array_key_exists($key, $cache)) {
        $r = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $table LIKE %s", $col));
        $cache[$key] = !empty($r);
    }
    return $cache[$key];
}
}
// 4.1 Top devedores - delegado ao motor consolidado
$top_devedores = sige_kpi_top_devedores($escola_id, 10, $centro_id_filtro, $ano);
// 4.2 Últimas transações
// Compatibilidade: alguns builds antigos usam nomes diferentes de colunas.
$col_data   = 'data_pagamento';
$col_valor  = 'valor_pago';
$col_metodo = 'metodo_pagamento';
$col_recibo = 'recibo_nr';
if (!sige_fin_col_exists($tP, $col_data)) {
    foreach (['data', 'data_registo', 'created_at', 'data_criacao'] as $c) {
        if (sige_fin_col_exists($tP, $c)) { $col_data = $c; break; }
    }
}
if (!sige_fin_col_exists($tP, $col_valor)) {
    foreach (['valor', 'total', 'montante', 'valor_total'] as $c) {
        if (sige_fin_col_exists($tP, $c)) { $col_valor = $c; break; }
    }
}
if (!sige_fin_col_exists($tP, $col_metodo)) {
    foreach (['metodo', 'forma_pagamento', 'tipo_pagamento'] as $c) {
        if (sige_fin_col_exists($tP, $c)) { $col_metodo = $c; break; }
    }
}
if (!sige_fin_col_exists($tP, $col_recibo)) {
    foreach (['recibo', 'numero_recibo', 'nr_recibo', 'recibo_numero'] as $c) {
        if (sige_fin_col_exists($tP, $c)) { $col_recibo = $c; break; }
    }
}
// [v13.4.0] Filtro por centro aplicado via concatenação segura (centro_id é int validado)
$_centro_sql_ult = $centro_id_filtro > 0 ? ' AND p.centro_id = ' . (int)$centro_id_filtro : '';
$ultimos_sql = "
    SELECT p.id AS pagamento_id,
           p.aluno_id,
           p.`$col_recibo` AS recibo_nr,
           p.`$col_metodo` AS metodo_pagamento,
           p.`$col_valor`  AS valor_pago,
           p.`$col_data`   AS data_pagamento,
           a.nome_completo
    FROM $tP p
    LEFT JOIN $tA a ON a.id = p.aluno_id
    WHERE p.escola_id = %d AND p.`$col_data` >= %s AND p.`$col_data` < %s
          {$_centro_sql_ult}
    ORDER BY p.`$col_data` DESC
    LIMIT 8
";
$ultimos = $wpdb->get_results($wpdb->prepare($ultimos_sql, $escola_id, $inicio_ano, $inicio_ano_seguinte));

// ─────────────────────────────────────────────────────────────────────────
// [12.9.77] Recebimentos operacionais por serviço/aluno no período filtrado
// ─────────────────────────────────────────────────────────────────────────
$dash_servicos = $wpdb->get_results($wpdb->prepare(
    "SELECT id, nome, tipo, ciclo FROM $tS WHERE escola_id = %d AND ativo = 1 ORDER BY nome ASC",
    $escola_id
));

$__dash_data_sql = function_exists('sige_fin_data_pag_sql_clause') ? sige_fin_data_pag_sql_clause('p') : 'DATE(p.data_pagamento)';
$__dash_where = [
    'p.escola_id = %d',
    "$__dash_data_sql BETWEEN %s AND %s",
    "p.`$col_valor` > 0",
    "LOWER(COALESCE(p.`$col_metodo`, '')) <> 'estorno'",
];
$__dash_params = [$escola_id, $dash_inicio, $dash_fim];
if ($centro_id_filtro > 0) {
    $__dash_where[] = 'p.centro_id = %d';
    $__dash_params[] = (int)$centro_id_filtro;
}
if ($dash_servico_id > 0) {
    $__dash_where[] = 'l.servico_id = %d';
    $__dash_params[] = (int)$dash_servico_id;
}
if ($dash_aluno_q !== '') {
    $__like_aluno = '%' . $wpdb->esc_like($dash_aluno_q) . '%';
    $__dash_where[] = '(a.nome_completo LIKE %s OR a.numero_processo LIKE %s)';
    $__dash_params[] = $__like_aluno;
    $__dash_params[] = $__like_aluno;
}
$__dash_where_sql = implode(' AND ', $__dash_where);
$__dash_from_sql = "
    FROM $tP p
    LEFT JOIN $tL l ON l.id = p.lancamento_id AND l.escola_id = p.escola_id
    LEFT JOIN $tS s ON s.id = l.servico_id AND s.escola_id = p.escola_id
    LEFT JOIN $tA a ON a.id = p.aluno_id AND a.escola_id = p.escola_id
    WHERE $__dash_where_sql
";

$dash_totais = $wpdb->get_row($wpdb->prepare(
    "SELECT COUNT(*) AS movimentos,
            COUNT(DISTINCT p.aluno_id) AS alunos,
            COUNT(DISTINCT COALESCE(l.servico_id, 0)) AS servicos,
            COALESCE(SUM(p.`$col_valor`), 0) AS total
     $__dash_from_sql",
    $__dash_params
));
$dash_total_recebido = (float)($dash_totais->total ?? 0);
$dash_total_movimentos = (int)($dash_totais->movimentos ?? 0);
$dash_total_alunos = (int)($dash_totais->alunos ?? 0);
$dash_total_servicos = (int)($dash_totais->servicos ?? 0);

$dash_por_servico = $wpdb->get_results($wpdb->prepare(
    "SELECT COALESCE(NULLIF(s.nome, ''), NULLIF(l.descricao, ''), 'Serviço não identificado') AS servico_nome,
            COUNT(*) AS movimentos,
            COUNT(DISTINCT p.aluno_id) AS alunos,
            COALESCE(SUM(p.`$col_valor`), 0) AS total
     $__dash_from_sql
     GROUP BY servico_nome
     ORDER BY total DESC, servico_nome ASC
     LIMIT 30",
    $__dash_params
));

$dash_por_aluno = $wpdb->get_results($wpdb->prepare(
    "SELECT p.aluno_id,
            COALESCE(a.nome_completo, CONCAT('Aluno #', p.aluno_id)) AS nome_completo,
            COALESCE(a.numero_processo, '') AS numero_processo,
            COUNT(*) AS movimentos,
            COUNT(DISTINCT COALESCE(l.servico_id, 0)) AS servicos,
            COALESCE(SUM(p.`$col_valor`), 0) AS total
     $__dash_from_sql
     GROUP BY p.aluno_id, nome_completo, numero_processo
     ORDER BY total DESC, nome_completo ASC
     LIMIT 30",
    $__dash_params
));

$dash_movimentos = $wpdb->get_results($wpdb->prepare(
    "SELECT p.id AS pagamento_id,
            p.aluno_id,
            p.`$col_recibo` AS recibo_nr,
            p.`$col_metodo` AS metodo_pagamento,
            p.`$col_valor` AS valor_pago,
            p.`$col_data` AS data_pagamento,
            COALESCE(a.nome_completo, CONCAT('Aluno #', p.aluno_id)) AS nome_completo,
            COALESCE(a.numero_processo, '') AS numero_processo,
            COALESCE(NULLIF(s.nome, ''), NULLIF(l.descricao, ''), 'Serviço não identificado') AS servico_nome,
            (
              SELECT CONCAT(COALESCE(t.classe, ''), CASE WHEN COALESCE(t.nome, '') <> '' THEN CONCAT(' - ', t.nome) ELSE '' END)
              FROM {$wpdb->prefix}sige_matriculas m
              LEFT JOIN {$wpdb->prefix}sige_turmas t ON t.id = m.turma_id AND t.escola_id = m.escola_id
              WHERE m.aluno_id = p.aluno_id AND m.escola_id = p.escola_id
              ORDER BY CASE WHEN m.status_matricula = 'activa' THEN 0 ELSE 1 END, m.ano_lectivo DESC, m.id DESC
              LIMIT 1
            ) AS turma_atual
     $__dash_from_sql
     ORDER BY p.`$col_data` DESC, p.id DESC
     LIMIT 120",
    $__dash_params
));

$dash_labels_serv_recebido = [];
$dash_data_serv_recebido = [];
foreach ((array)$dash_por_servico as $__srv) {
    $dash_labels_serv_recebido[] = (string)$__srv->servico_nome;
    $dash_data_serv_recebido[] = (float)$__srv->total;
}

if (!function_exists('sige_fin_money')) {
function sige_fin_money($v) {
    return number_format((float)$v, 2, ',', '.') . ' ' . sige_moeda();
}
}
?>
<?php echo sige_cdn_script("chartjs"); ?>

<?php
$sg_finpro_balance = (float)$total_recebido - (float)$total_despesas_ano;
$sg_finpro_taxa = max(0, min(100, (float)$taxa_cobranca));
$sg_finpro_previsto_ratio = $total_previsto > 0 ? max(0, min(100, ($total_recebido / $total_previsto) * 100)) : 0;
$sg_finpro_dash_period_url = '?page=sige-app&view=financeiro-dashboard&ano=' . rawurlencode((string)$ano) . ($centro_id_filtro > 0 ? '&centro_id=' . (int)$centro_id_filtro : '');
$sg_finpro_cards = [
    [
        'label' => 'Receita recebida',
        'value' => sige_fin_money($total_recebido),
        'note'  => 'Total confirmado em ' . (int)$ano,
        'icon'  => 'wallet',
        'tone'  => 'green',
    ],
    [
        'label' => 'Saldo em aberto',
        'value' => sige_fin_money($total_pendente),
        'note'  => 'Pendências por regularizar',
        'icon'  => 'trending',
        'tone'  => 'amber',
    ],
    [
        'label' => 'Despesas registadas',
        'value' => sige_fin_money($total_despesas_ano),
        'note'  => 'Saídas aprovadas no ano',
        'icon'  => 'activity',
        'tone'  => 'coral',
    ],
    [
        'label' => 'Taxa de cobrança',
        'value' => esc_html($taxa_cobranca) . '%',
        'note'  => 'Recebido face ao previsto',
        'icon'  => 'chart',
        'tone'  => 'blue',
    ],
];
$sg_finpro_quick_links = [
    ['Registar Pagamento', 'financeiro-pagamentos', 'money', 'purple'],
    ['Central de Cobranças', 'financeiro-devedores', 'trending', 'coral'],
    ['Extractos e Caixa', 'financeiro-extratos', 'file', 'blue'],
    ['Pagamentos / Turma', 'pagamentos-turma', 'clipboard', 'green'],
    ['Lançar Mensalidades', 'financeiro-gerador', 'rocket', 'amber'],
    ['Preços e Serviços', 'financeiro-config', 'settings', 'slate'],
];
?>

<div class="wrap sg-finpro-wrap">
  <section class="sg-finpro-hero" aria-label="Painel financeiro">
    <div class="sg-finpro-hero-copy">
      <div class="sg-finpro-kicker"><?php echo sige_ui_icon('wallet'); ?> Gestão financeira</div>
      <h1>Resumo financeiro da escola</h1>
      <p>Acompanhe receitas, cobranças, dívidas, despesas e movimentos da tesouraria com leitura simples para a tomada de decisão.</p>
      <div class="sg-finpro-hero-actions">
        <a class="sg-finpro-btn sg-finpro-btn-primary" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-pagamentos')); ?>"><?php echo sige_ui_icon('money'); ?> Registar pagamento</a>
        <a class="sg-finpro-btn sg-finpro-btn-light" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-devedores')); ?>"><?php echo sige_ui_icon('trending'); ?> Ver devedores</a>
      </div>
    </div>
    <div class="sg-finpro-hero-panel" aria-label="Indicadores principais">
      <div class="sg-finpro-mini-label">Balanço do ano</div>
      <strong class="<?php echo $sg_finpro_balance >= 0 ? 'is-positive' : 'is-negative'; ?>"><?php echo sige_fin_money($sg_finpro_balance); ?></strong>
      <span>Recebido menos despesas · Ano lectivo <?php echo esc_html($ano); ?></span>
      <div class="sg-finpro-progress"><i style="width:<?php echo esc_attr(round($sg_finpro_previsto_ratio, 2)); ?>%"></i></div>
      <small><?php echo esc_html(round($sg_finpro_previsto_ratio, 1)); ?>% do previsto já recebido</small>
    </div>
  </section>

  <section class="sg-finpro-kpi-grid" aria-label="Indicadores financeiros principais">
    <?php foreach ($sg_finpro_cards as $card): ?>
      <article class="sg-finpro-kpi sg-finpro-tone-<?php echo esc_attr($card['tone']); ?>">
        <div class="sg-finpro-kpi-icon"><?php echo sige_ui_icon($card['icon']); ?></div>
        <div>
          <span><?php echo esc_html($card['label']); ?></span>
          <strong><?php echo $card['value']; ?></strong>
          <small><?php echo esc_html($card['note']); ?></small>
        </div>
      </article>
    <?php endforeach; ?>
  </section>


  <?php if (file_exists(SIGE_PATH . 'admin/alertas/alertas-dashboard.php')): ?>
    <div class="sg-finpro-native-alerts"><?php include SIGE_PATH . 'admin/alertas/alertas-dashboard.php'; ?></div>
  <?php endif; ?>

  <form method="get" class="sg-finpro-filterbar" aria-label="Filtros do painel financeiro">
    <?php
      foreach ((array)$_GET as $k => $v) {
        if ($k === 'ano' || $k === 'centro_id') continue;
        if (is_array($v)) continue;
        $k = sanitize_key($k);
        if ($k === '') continue;
        echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string)$v) . '">';
      }
    ?>
    <div class="sg-finpro-filter-title">
      <?php echo sige_ui_icon('calendar'); ?>
      <span>Leitura do período</span>
    </div>
    <label>
      Ano lectivo
      <input type="number" name="ano" value="<?php echo esc_attr($ano); ?>" min="2000" max="2100">
    </label>
    <?php if (function_exists('sige_fin_render_filtro_centro')): ?>
      <div class="sg-finpro-center-filter"><?php echo sige_fin_render_filtro_centro(['selected' => $centro_id_filtro]); ?></div>
    <?php endif; ?>
    <button type="submit" class="sgk-btn sgk-btn-sec">Aplicar</button>
    <span class="sg-finpro-filter-hint">Indicadores calculados com os lançamentos e pagamentos já registados.</span>
  </form>

  <?php if ($concil_total > 0): ?>
  <section class="sg-finpro-alert sg-finpro-alert-danger">
    <div class="sg-finpro-alert-icon"><?php echo sige_ui_icon('shield'); ?></div>
    <div>
      <strong>Conciliação financeira precisa de atenção</strong>
      <p><?php echo (int)$concil_total; ?> divergência<?php echo $concil_total > 1 ? 's' : ''; ?> detectada<?php echo $concil_total > 1 ? 's' : ''; ?> no valor total de <?php echo number_format($concil_soma, 2, ',', '.'); ?> <?php echo esc_html(function_exists('sige_moeda') ? sige_moeda() : 'MT'); ?>.</p>
    </div>
  </section>
  <?php endif; ?>

  <section class="sg-finpro-grid sg-finpro-grid-main">
    <article class="sg-finpro-card sg-finpro-card-wide">
      <div class="sg-finpro-card-head">
        <div>
          <span class="sg-finpro-section-icon"><?php echo sige_ui_icon('chart'); ?></span>
          <div>
            <h2>Receitas e despesas</h2>
            <p>Compara o previsto, o recebido e a evolução da cobrança ao longo do ano.</p>
          </div>
        </div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-relatorio-mensal&ano=' . (int)$ano)); ?>">Ver relatório →</a>
      </div>
      <div class="sg-finpro-mini-kpis">
        <div><span>Previsto</span><strong><?php echo sige_fin_money($total_previsto); ?></strong></div>
        <div><span>Recebido</span><strong class="is-green"><?php echo sige_fin_money($total_recebido); ?></strong></div>
        <div><span>Despesas</span><strong class="is-coral"><?php echo sige_fin_money($total_despesas_ano); ?></strong></div>
        <div><span>Hoje</span><strong><?php echo sige_fin_money($total_hoje); ?></strong></div>
      </div>
      <div class="sg-finpro-chart-wrap"><canvas id="chartPrevistoRecebido" height="110"></canvas></div>
    </article>

    <article class="sg-finpro-card">
      <div class="sg-finpro-card-head">
        <div>
          <span class="sg-finpro-section-icon sg-finpro-soft-amber"><?php echo sige_ui_icon('trending'); ?></span>
          <div>
            <h2>Carteira em aberto</h2>
            <p>Dívida, atraso e cobrança do ano.</p>
          </div>
        </div>
      </div>
      <div class="sg-finpro-debt-focus">
        <div class="sg-finpro-ring" style="--sg-ring:<?php echo esc_attr(round($sg_finpro_taxa, 2)); ?>;"><strong><?php echo esc_html(round($sg_finpro_taxa)); ?>%</strong><span>cobrança</span></div>
        <div class="sg-finpro-debt-list">
          <div><span>Pendente</span><strong><?php echo sige_fin_money($total_pendente); ?></strong></div>
          <div><span>Em atraso</span><strong class="is-coral"><?php echo sige_fin_money($total_atraso); ?></strong></div>
          <div><span>Plano negociado</span><strong><?php echo sige_fin_money($total_em_plano); ?></strong></div>
        </div>
      </div>
      <a class="sg-finpro-full-link" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-devedores')); ?>">Abrir Central de Cobranças →</a>
    </article>
  </section>

  <section class="sg-finpro-grid sg-finpro-grid-secondary">
    <article class="sg-finpro-card">
      <div class="sg-finpro-card-head">
        <div>
          <span class="sg-finpro-section-icon sg-finpro-soft-blue"><?php echo sige_ui_icon('folder'); ?></span>
          <div><h2>Pendência por serviço</h2><p>Top 8 serviços com saldo em aberto.</p></div>
        </div>
      </div>
      <div class="sg-finpro-chart-wrap sg-finpro-chart-compact"><canvas id="chartServicos" height="120"></canvas></div>
    </article>

    <article class="sg-finpro-card">
      <div class="sg-finpro-card-head">
        <div>
          <span class="sg-finpro-section-icon sg-finpro-soft-green"><?php echo sige_ui_icon('wallet'); ?></span>
          <div><h2>Métodos de pagamento</h2><p>Distribuição dos recebimentos por canal.</p></div>
        </div>
      </div>
      <div class="sg-finpro-chart-wrap sg-finpro-chart-compact"><canvas id="chartMetodos" height="120"></canvas></div>
    </article>

    <article class="sg-finpro-card sg-finpro-quick-card">
      <div class="sg-finpro-card-head">
        <div>
          <span class="sg-finpro-section-icon"><?php echo sige_ui_icon('bolt'); ?></span>
          <div><h2>Acessos rápidos</h2><p>Operações financeiras mais usadas.</p></div>
        </div>
      </div>
      <div class="sg-finpro-quick-grid">
        <?php foreach ($sg_finpro_quick_links as $q): ?>
          <a class="sg-finpro-quick sg-finpro-quick-<?php echo esc_attr($q[3]); ?>" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=' . $q[1])); ?>">
            <span><?php echo sige_ui_icon($q[2]); ?></span>
            <strong><?php echo esc_html($q[0]); ?></strong>
          </a>
        <?php endforeach; ?>
      </div>
    </article>
  </section>

  <section class="sg-finpro-card sg-finpro-operational">
    <div class="sg-finpro-card-head">
      <div>
        <span class="sg-finpro-section-icon sg-finpro-soft-green"><?php echo sige_ui_icon('check'); ?></span>
        <div>
          <h2>Recebimentos por serviço e por aluno</h2>
          <p>Valores efectivamente recebidos no período seleccionado. Estornos ficam fora desta leitura operacional.</p>
        </div>
      </div>
      <span class="sg-finpro-period-pill"><?php echo esc_html($dash_periodo_label); ?></span>
    </div>

    <form method="get" class="sg-finpro-op-filter" id="sigeFindashOperacionalForm">
      <?php
        foreach ((array)$_GET as $k => $v) {
          if (in_array($k, ['dash_periodo','dash_dia','dash_mes','dash_ano','dash_servico_id','dash_aluno_q'], true)) continue;
          if (is_array($v)) continue;
          $k = sanitize_key($k);
          if ($k === '') continue;
          echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string)$v) . '">';
        }
      ?>
      <label>Período
        <select name="dash_periodo" id="dash_periodo">
          <option value="diario" <?php selected($dash_periodo, 'diario'); ?>>Diário</option>
          <option value="mensal" <?php selected($dash_periodo, 'mensal'); ?>>Mensal</option>
          <option value="anual" <?php selected($dash_periodo, 'anual'); ?>>Anual</option>
        </select>
      </label>
      <label class="dash-period-field" data-period="diario">Dia
        <input type="date" name="dash_dia" value="<?php echo esc_attr($dash_dia); ?>">
      </label>
      <label class="dash-period-field" data-period="mensal">Mês
        <input type="month" name="dash_mes" value="<?php echo esc_attr($dash_mes); ?>">
      </label>
      <label class="dash-period-field" data-period="anual">Ano
        <input type="number" name="dash_ano" value="<?php echo esc_attr($dash_ano); ?>" min="2000" max="2100">
      </label>
      <label>Serviço
        <select name="dash_servico_id">
          <option value="0">Todos os serviços</option>
          <?php foreach ((array)$dash_servicos as $__srv): ?>
            <option value="<?php echo (int)$__srv->id; ?>" <?php selected($dash_servico_id, (int)$__srv->id); ?>><?php echo esc_html($__srv->nome); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Aluno
        <input type="search" name="dash_aluno_q" value="<?php echo esc_attr($dash_aluno_q); ?>" placeholder="Nome ou processo">
      </label>
      <button type="submit" class="sgk-btn sgk-btn-sec">Aplicar filtros</button>
      <a class="sg-finpro-clear" href="<?php echo esc_url($sg_finpro_dash_period_url); ?>">Limpar</a>
    </form>

    <div class="sg-finpro-op-kpis">
      <div><span>Total recebido</span><strong><?php echo sige_fin_money($dash_total_recebido); ?></strong></div>
      <div><span>Movimentos</span><strong><?php echo (int)$dash_total_movimentos; ?></strong></div>
      <div><span>Alunos com pagamento</span><strong><?php echo (int)$dash_total_alunos; ?></strong></div>
      <div><span>Serviços recebidos</span><strong><?php echo (int)$dash_total_servicos; ?></strong></div>
    </div>

    <div class="sg-finpro-operational-grid">
      <div class="sg-finpro-card-inner">
        <h3>Recebido por serviço</h3>
        <div class="sg-finpro-chart-wrap sg-finpro-chart-compact"><canvas id="chartRecebidoServico" height="115"></canvas></div>
      </div>
      <div class="sg-finpro-card-inner sg-finpro-scroll-table">
        <h3>Top alunos no período</h3>
        <table class="sg-finpro-table">
          <thead><tr><th>Aluno</th><th>Mov.</th><th>Serv.</th><th class="is-money">Total</th></tr></thead>
          <tbody>
          <?php if (empty($dash_por_aluno)): ?>
            <tr><td colspan="4" class="sg-finpro-empty">Sem recebimentos no período seleccionado.</td></tr>
          <?php else: foreach ($dash_por_aluno as $__al): ?>
            <tr>
              <td><strong><?php echo esc_html($__al->nome_completo); ?></strong><small><?php echo esc_html($__al->numero_processo ?: 'Sem processo'); ?></small></td>
              <td><?php echo (int)$__al->movimentos; ?></td>
              <td><?php echo (int)$__al->servicos; ?></td>
              <td class="is-money"><?php echo sige_fin_money($__al->total); ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <?php
    $__ano_ini = (string)$ano . '-01-01';
    $__ano_fim = (string)$ano . '-12-31';
    $__centros_totais = function_exists('sige_fin_totais_por_centro') ? sige_fin_totais_por_centro($__ano_ini, $__ano_fim) : [];
    if (!empty($__centros_totais) && count($__centros_totais) > 1):
  ?>
  <section class="sg-finpro-card">
    <div class="sg-finpro-card-head">
      <div>
        <span class="sg-finpro-section-icon sg-finpro-soft-blue"><?php echo sige_ui_icon('building'); ?></span>
        <div><h2>Resultado por centro de custo</h2><p>Leitura anual por área de gestão.</p></div>
      </div>
      <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-centros')); ?>">Gerir centros →</a>
    </div>
    <div class="sg-finpro-centers-grid">
      <?php foreach ($__centros_totais as $__ct): ?>
        <article class="sg-finpro-center-card" style="--sg-center-color:<?php echo esc_attr($__ct->cor); ?>;">
          <h3><?php echo esc_html($__ct->nome); ?></h3>
          <div><span>Receita líquida</span><strong><?php echo sige_fin_money($__ct->receita_liquida); ?></strong></div>
          <div><span>Despesas</span><strong><?php echo sige_fin_money($__ct->despesa); ?></strong></div>
          <div class="sg-finpro-center-result"><span>Resultado</span><strong class="<?php echo $__ct->resultado >= 0 ? 'is-green' : 'is-coral'; ?>"><?php echo sige_fin_money($__ct->resultado); ?></strong></div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="sg-finpro-grid sg-finpro-grid-tables">
    <article class="sg-finpro-card sg-finpro-scroll-table">
      <div class="sg-finpro-card-head">
        <div><span class="sg-finpro-section-icon sg-finpro-soft-amber"><?php echo sige_ui_icon('users'); ?></span><div><h2>Top 10 devedores</h2><p>Alunos com maior saldo em aberto.</p></div></div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-devedores')); ?>">Ver todos →</a>
      </div>
      <table class="sg-finpro-table">
        <thead><tr><th>Aluno</th><th>Processo</th><th>Contacto</th><th class="is-money">Saldo</th></tr></thead>
        <tbody>
        <?php if (empty($top_devedores)): ?>
          <tr><td colspan="4" class="sg-finpro-empty">Sem pendências para o ano seleccionado.</td></tr>
        <?php else: foreach ($top_devedores as $d): ?>
          <?php $c = !empty($d->telemovel_pai) ? $d->telemovel_pai : (!empty($d->telemovel_mae) ? $d->telemovel_mae : ''); ?>
          <tr>
            <td><strong><?php echo esc_html($d->nome_completo); ?></strong></td>
            <td><?php echo esc_html($d->numero_processo); ?></td>
            <td><?php echo $c ? esc_html($c) : '<span class="sg-finpro-muted">-</span>'; ?></td>
            <td class="is-money"><?php echo sige_fin_money($d->saldo); ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </article>

    <article class="sg-finpro-card sg-finpro-scroll-table">
      <div class="sg-finpro-card-head">
        <div><span class="sg-finpro-section-icon sg-finpro-soft-green"><?php echo sige_ui_icon('clipboard'); ?></span><div><h2>Últimas transacções</h2><p>Movimentos financeiros recentes.</p></div></div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-extratos')); ?>">Abrir extractos →</a>
      </div>
      <table class="sg-finpro-table">
        <thead><tr><th>Data</th><th>Aluno</th><th>Método</th><th>Recibo</th><th class="is-money">Valor</th></tr></thead>
        <tbody>
        <?php if (empty($ultimos)): ?>
          <tr><td colspan="5" class="sg-finpro-empty">Sem transacções no ano seleccionado.</td></tr>
        <?php else: foreach ($ultimos as $u): ?>
          <?php $recibo_url = admin_url('admin.php?page=sige-app&sige_print=recibo&id=' . (int)$u->pagamento_id); $recibo_lbl = $u->recibo_nr ?: ('#' . (int)$u->pagamento_id); ?>
          <tr>
            <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($u->data_pagamento))); ?></td>
            <td><strong><?php echo esc_html($u->nome_completo ?: ('Aluno #' . (int)$u->aluno_id)); ?></strong></td>
            <td><?php echo esc_html(function_exists('sige_fin_metodo_pagamento_label') ? sige_fin_metodo_pagamento_label($u->metodo_pagamento) : ucfirst((string)$u->metodo_pagamento)); ?></td>
            <td><a class="sg-finpro-receipt" href="<?php echo esc_url($recibo_url); ?>" data-sige-act="sigeAbrirJanela" data-sige-prevent data-sige-window-name="ReciboSIGE" data-sige-window-features="width=980,height=850,scrollbars=yes,resizable=yes">Recibo <?php echo esc_html($recibo_lbl); ?></a></td>
            <td class="is-money"><?php echo sige_fin_money($u->valor_pago); ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </article>
  </section>

  <p class="sg-finpro-footnote">“Em atraso” considera o vencimento configurado para cada serviço e o mês de referência do lançamento. Esta versão reorganiza a experiência visual sem alterar as regras financeiras validadas.</p>
</div>

<script <?php echo sige_csp_script_attr(); ?>>
(() => {
  const opPeriodo = document.getElementById('dash_periodo');
  const opFields = Array.from(document.querySelectorAll('.dash-period-field'));
  function syncDashPeriodFields() {
    const val = opPeriodo ? opPeriodo.value : 'mensal';
    opFields.forEach(function(field){ field.style.display = field.getAttribute('data-period') === val ? '' : 'none'; });
  }
  if (opPeriodo) { opPeriodo.addEventListener('change', syncDashPeriodFields); syncDashPeriodFields(); }

  if (typeof Chart === 'undefined') return;
  Chart.defaults.font.family = "Poppins, Inter, Segoe UI, system-ui, sans-serif";
  Chart.defaults.color = '#667085';
  Chart.defaults.plugins.tooltip.backgroundColor = '#17172f';
  Chart.defaults.plugins.tooltip.padding = 12;
  Chart.defaults.plugins.tooltip.cornerRadius = 10;

  const meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
  const previsto = <?php echo wp_json_encode(array_values($previsto_mensal)); ?>;
  const recebido = <?php echo wp_json_encode(array_values($recebido_mensal)); ?>;
  const moeda = <?php echo wp_json_encode(function_exists('sige_moeda') ? sige_moeda() : 'MT'); ?>;
  const currencyLabel = (value) => {
    try { return new Intl.NumberFormat('pt-MZ', { maximumFractionDigits: 0 }).format(Number(value || 0)) + ' ' + moeda; }
    catch(e) { return String(value || 0) + ' ' + moeda; }
  };

  const ctx1 = document.getElementById('chartPrevistoRecebido');
  if (ctx1) {
    new Chart(ctx1, {
      type: 'line',
      data: {
        labels: meses,
        datasets: [
          { label: 'Previsto', data: previsto, tension: .38, borderColor: '#6d5dfc', backgroundColor: 'rgba(109,93,252,.10)', fill: true, pointRadius: 3, pointHoverRadius: 5 },
          { label: 'Recebido', data: recebido, tension: .38, borderColor: '#35c98b', backgroundColor: 'rgba(53,201,139,.12)', fill: true, pointRadius: 3, pointHoverRadius: 5 }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } }, tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': ' + currencyLabel(ctx.parsed.y) } } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(102,112,133,.12)' }, ticks: { callback: currencyLabel } }, x: { grid: { display:false } } } }
    });
  }

  const labelsServ = <?php echo wp_json_encode($labels_serv); ?>;
  const dataServ = <?php echo wp_json_encode($data_serv); ?>;
  const ctx2 = document.getElementById('chartServicos');
  if (ctx2) {
    new Chart(ctx2, {
      type: 'bar',
      data: { labels: labelsServ, datasets: [{ label: 'Pendente', data: dataServ, backgroundColor: '#6d5dfc', borderRadius: 12, maxBarThickness: 34 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display:false }, tooltip: { callbacks: { label: (ctx) => currencyLabel(ctx.parsed.y) } } }, scales: { y: { beginAtZero: true, grid: { color:'rgba(102,112,133,.12)' }, ticks: { callback: currencyLabel } }, x: { grid:{display:false}, ticks:{ maxRotation: 0, autoSkip: true } } } }
    });
  }

  const labelsMet = <?php echo wp_json_encode($labels_met); ?>;
  const dataMet = <?php echo wp_json_encode($data_met); ?>;
  const ctx3 = document.getElementById('chartMetodos');
  if (ctx3) {
    new Chart(ctx3, {
      type: 'doughnut',
      data: { labels: labelsMet, datasets: [{ data: dataMet, backgroundColor: ['#6d5dfc','#35c98b','#f6b64a','#ff647c','#4aa3ff','#9b7cff'], borderWidth: 0, hoverOffset: 6 }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '66%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } }, tooltip: { callbacks: { label: (ctx) => ctx.label + ': ' + currencyLabel(ctx.parsed) } } } }
    });
  }

  const labelsServRecebido = <?php echo wp_json_encode($dash_labels_serv_recebido); ?>;
  const dataServRecebido = <?php echo wp_json_encode($dash_data_serv_recebido); ?>;
  const ctxRecebidoServico = document.getElementById('chartRecebidoServico');
  if (ctxRecebidoServico) {
    new Chart(ctxRecebidoServico, {
      type: 'bar',
      data: { labels: labelsServRecebido, datasets: [{ label: 'Recebido', data: dataServRecebido, backgroundColor: '#35c98b', borderRadius: 12, maxBarThickness: 34 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display:false }, tooltip: { callbacks: { label: (ctx) => currencyLabel(ctx.parsed.y) } } }, scales: { y: { beginAtZero:true, grid:{ color:'rgba(102,112,133,.12)' }, ticks:{ callback: currencyLabel } }, x:{ grid:{display:false}, ticks:{ maxRotation:0, autoSkip:true } } } }
    });
  }
})();
</script>

<?php
if (isset($__sige_fin_dash_cache_key) && function_exists('set_transient') && ob_get_level() > 0) {
    $__sige_fin_dash_html = ob_get_contents();
    if (is_string($__sige_fin_dash_html) && $__sige_fin_dash_html !== '') { set_transient($__sige_fin_dash_cache_key, $__sige_fin_dash_html, 3 * MINUTE_IN_SECONDS); }
}
?>
