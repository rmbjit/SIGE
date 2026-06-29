<?php
if (!defined('ABSPATH')) exit;

// Guard de acesso - Tesouraria (Despesas)
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['financeiro.despesas_ver','financeiro.despesas_gerir'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_financeiro']
)) return;

/**
 * SIGE SoftGenial - Módulo de Despesas (Saídas Financeiras)
 * Ficheiro: admin/financeiro-despesas-view.php
 * Rota: ?page=sige-app&view=financeiro-despesas
 */

if (!defined('ABSPATH')) exit;

global $wpdb;
$p = $wpdb->prefix;
$tD = $p . 'sige_fin_despesas';

// ── escola_id centralizado ──────────────────────────────────────────────────
$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
if ($escola_id <= 0) {
 echo '<div style="padding:30px;color:#991b1b;background:#fef2f2;border-radius:8px;margin:20px;">Escola não identificada. Operações de despesas bloqueadas para evitar escrita no tenant errado.</div>';
 return;
}

// Verificar se tabela existe
if ($wpdb->get_var("SHOW TABLES LIKE '$tD'") !== $tD) {
 echo '<div style="padding:30px;color:#991b1b;background:#fef2f2;border-radius:8px;margin:20px;">A tabela de despesas ainda não foi criada. Recarregue a página como administrador para activar o módulo.</div>';
 return;
}

$msg        = '';
$err = '';
$is_admin = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'));
$is_director = current_user_can('sige_director') || $is_admin;

// ═══════════════════════════════════════════════════════════════════════════
// POST HANDLERS
// ═══════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

 // ── Registar nova despesa ───────────────────────────────────────────────
 if (sige_fin_post_param('acao') === 'nova_despesa') {
 // [AUTH-05] Capability check inline (defesa em profundidade)
 if (!((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director') || current_user_can('sige_secretario') || current_user_can('sige_secretaria_geral') || current_user_can('sige_financeiro'))) {
 $err = 'Sem permissao para registar despesas.';
 } elseif (!isset($_POST['sige_despesa_nonce']) || !wp_verify_nonce($_POST['sige_despesa_nonce'], 'sige_nova_despesa')) {
 $err = 'Nonce inválido.';
 } else {
 $data_despesa = sige_fin_post_param('data_despesa');
 $categoria = sige_fin_post_param('categoria', 'geral');
 $descricao = sige_fin_post_param('descricao');
 $valor = max(0.0, sige_fin_post_float('valor'));
 $metodo = sige_fin_post_param('metodo_pagamento', 'numerario');
 $referencia = sige_fin_post_param('referencia');
 $fornecedor = sige_fin_post_param('fornecedor');
 $observacoes = sanitize_textarea_field(wp_unslash($_POST['observacoes'] ?? ''));
 // [v13.1] Centro de custo (default = primeiro centro da escola)
 $centro_id = isset($_POST['centro_id']) ? (int)$_POST['centro_id'] : 0;
 if ($centro_id <= 0 && function_exists('sige_fin_get_centro_default_id')) {
     $centro_id = sige_fin_get_centro_default_id();
 }
 if ($centro_id <= 0) {
     $err = 'Centro de custo não identificado para esta escola.';
 }

 if ($err) {
 // Erro já definido acima: não escrever na base de dados.
 } elseif (!$data_despesa || !$descricao || $valor <= 0) {
 $err = 'Preencha os campos obrigatórios: data, descrição e valor.';
 } else {
 $inserted = $wpdb->insert($tD, [
 'escola_id' => $escola_id, // ← FIX: sempre explícito
 'centro_id' => $centro_id, // [v13.1] centro de custo
 'data_despesa' => $data_despesa,
 'categoria' => $categoria,
 'descricao' => $descricao,
 'valor' => $valor,
 'metodo_pagamento' => $metodo,
 'referencia' => $referencia,
 'fornecedor' => $fornecedor,
 'observacoes' => $observacoes,
 'registado_por' => get_current_user_id(),
 'status' => 'registado',
 ]);
 if ($inserted) {
                    $msg = 'Despesa #' . $wpdb->insert_id . ' registada com sucesso.';
                    $sige_despesa_id = (int)$wpdb->insert_id;
                    sige_fin_log('registar_despesa', ['despesa_id' => $sige_despesa_id, 'categoria' => $categoria, 'valor' => $valor, 'metodo' => $metodo]);
                    if (function_exists('sige_ledger_append')) {
                        sige_ledger_append('fin_criar_despesa', 'despesa', $sige_despesa_id, (float)$valor, ['categoria' => $categoria, 'metodo' => $metodo], (int)$escola_id);
                    }
 } else {
 $err = 'Falha ao registar a despesa.';
 }
 }
 }
 }

 // ── Aprovar despesa ─────────────────────────────────────────────────────
 // FIX: adicionado nonce + escola_id na WHERE
 // [v13.6.0] State machine formal - valida transição registado→aprovado
 // e regista audit em caso de bloqueio. Fallback legacy preservado para
 // o caso (improvável) do helper não estar carregado.
 if (sige_fin_post_param('acao') === 'aprovar_despesa') {
 if (!$is_director) {
 $err = 'Sem permissão para aprovar despesas.';
 } elseif (!isset($_POST['sige_nonce_aprovar']) || !wp_verify_nonce($_POST['sige_nonce_aprovar'], 'sige_aprovar_despesa')) {
 $err = 'Nonce inválido (aprovação).';
 } else {
 $did = sige_fin_post_int('despesa_id');
 if ($did) {
 $belongs = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$tD} WHERE id = %d AND escola_id = %d LIMIT 1", $did, $escola_id));
 if ($belongs <= 0) {
     $err = 'Despesa não pertence a esta escola.';
 } elseif (function_exists('sige_fin_despesa_transitar_status')) {
 // [v13.6.0] Via state machine - valida matriz de transições
 $ok = sige_fin_despesa_transitar_status(
 $did,
 'aprovado',
 'aprovação manual via UI',
 ['aprovado_por' => get_current_user_id()]
 );
 if ($ok) {
 $msg = "Despesa #{$did} aprovada.";
 } else {
 $err = "Não foi possível aprovar a despesa #{$did}: estado actual não permite aprovação.";
 }
 } else {
 // Fallback legacy - retrocompat se o helper faltar
 $wpdb->update(
 $tD,
 ['status' => 'aprovado', 'aprovado_por' => get_current_user_id()],
 ['id' => $did, 'escola_id' => $escola_id] // ← FIX: scoped
 );
                $msg = "Despesa #{$did} aprovada.";
                sige_fin_log('aprovar_despesa', ['despesa_id' => $did]);
 }
 }
 }
 }

 // ── Anular despesa ──────────────────────────────────────────────────────
 // FIX CRÍTICO: adicionado nonce + permissão director + escola_id na WHERE
 // [v13.6.0] State machine formal - permite anular a partir de registado
 // ou aprovado; bloqueia dupla-anulação. Fallback legacy preservado.
 if (sige_fin_post_param('acao') === 'anular_despesa') {
 if (!$is_director) {
 $err = 'Sem permissão para anular despesas. Apenas o Director pode anular.';
 } elseif (!isset($_POST['sige_nonce_anular']) || !wp_verify_nonce($_POST['sige_nonce_anular'], 'sige_anular_despesa')) {
 $err = 'Nonce inválido (anulação).';
 } else {
 $did = sige_fin_post_int('despesa_id');
 if ($did) {
 $belongs = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$tD} WHERE id = %d AND escola_id = %d LIMIT 1", $did, $escola_id));
 if ($belongs <= 0) {
     $err = 'Despesa não pertence a esta escola.';
 } elseif (function_exists('sige_fin_despesa_transitar_status')) {
 // [v13.6.0] Via state machine
 $ok = sige_fin_despesa_transitar_status(
 $did,
 'anulado',
 'anulação manual via UI'
 );
 if ($ok) {
 $msg = "Despesa #{$did} anulada.";
 } else {
 $err = "Não foi possível anular a despesa #{$did}: pode já estar anulada.";
 }
 } else {
 // Fallback legacy - retrocompat se o helper faltar
 $wpdb->update(
 $tD,
 ['status' => 'anulado'],
 ['id' => $did, 'escola_id' => $escola_id] // ← FIX: scoped
 );
                $msg = "Despesa #{$did} anulada.";
                sige_fin_log('anular_despesa', ['despesa_id' => $did]);
 }
 }
 }
 }
}

// ═══════════════════════════════════════════════════════════════════════════
// FILTROS
// ═══════════════════════════════════════════════════════════════════════════
$f_data_de = sige_fin_get_param('data_de');
$f_data_ate = sige_fin_get_param('data_ate');
$f_categoria = sige_fin_get_param('cat');
$f_status = sige_fin_get_param('st');
$f_busca = sige_fin_get_param('q');
// [v13.7.0 BLOCO 3 RESIDUAL] Filtro universal por Centro de Custo.
// Lido via sige_fin_centro_ativo() - valida contra lista de centros activos
// da escola (defesa multi-tenant, ver centros-helpers.php).
$centro_id_filtro = function_exists('sige_fin_centro_ativo') ? sige_fin_centro_ativo() : 0;
$per_page = 25;
$page_num = max(1, sige_fin_get_int('p', 1));
$offset = ($page_num - 1) * $per_page;

// FIX: escola_id sempre presente na cláusula base
$where = ["escola_id = %d"];
$params = [$escola_id];

if ($f_data_de) { $where[] = "data_despesa >= %s"; $params[] = $f_data_de; }
if ($f_data_ate) { $where[] = "data_despesa <= %s"; $params[] = $f_data_ate; }
if ($f_categoria){ $where[] = "categoria = %s"; $params[] = $f_categoria; }
if ($f_status) { $where[] = "LOWER(status) = %s"; $params[] = strtolower($f_status); }
if ($f_busca) {
 $like = '%' . $wpdb->esc_like($f_busca) . '%';
 $where[] = "(descricao LIKE %s OR fornecedor LIKE %s OR referencia LIKE %s)";
 $params[] = $like; $params[] = $like; $params[] = $like;
}

$where_sql = implode(' AND ', $where);

// [v13.7.0] Fragmento de filtro por centro pronto-a-concatenar. Devolve '' se
// "Todos". Valor é interpolado com cast int, sem passar pelo array $params.
$_centro_sql = function_exists('sige_fin_centro_where_clause')
    ? sige_fin_centro_where_clause($centro_id_filtro)
    : '';

// KPIs - escola_id já incluso em $where_sql via $params
$kpi_total = (float)$wpdb->get_var(
 $wpdb->prepare("SELECT COALESCE(SUM(valor),0) FROM $tD WHERE $where_sql AND LOWER(status) != 'anulado' {$_centro_sql}", $params)
);

$kpi_count = (int)$wpdb->get_var(
 $wpdb->prepare("SELECT COUNT(*) FROM $tD WHERE $where_sql AND LOWER(status) != 'anulado' {$_centro_sql}", $params)
);

$kpi_mes_atual = (float)$wpdb->get_var($wpdb->prepare(
 "SELECT COALESCE(SUM(valor),0) FROM $tD WHERE escola_id = %d AND data_despesa LIKE %s AND LOWER(status) != 'anulado' {$_centro_sql}",
 $escola_id, wp_date('Y-m') . '%'
));

$kpi_registado_count = (int)$wpdb->get_var(
 $wpdb->prepare("SELECT COUNT(*) FROM $tD WHERE $where_sql AND LOWER(status) = 'registado' {$_centro_sql}", $params)
);

$kpi_aprovado_count = (int)$wpdb->get_var(
 $wpdb->prepare("SELECT COUNT(*) FROM $tD WHERE $where_sql AND LOWER(status) = 'aprovado' {$_centro_sql}", $params)
);

$kpi_anulado_count = (int)$wpdb->get_var(
 $wpdb->prepare("SELECT COUNT(*) FROM $tD WHERE $where_sql AND LOWER(status) = 'anulado' {$_centro_sql}", $params)
);

$sg_mz_now = new DateTime('now', new DateTimeZone('Africa/Maputo'));

// Total rows + pagination
$total_rows = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tD WHERE $where_sql {$_centro_sql}", $params));
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// Fetch rows
$list_params = array_merge($params, [$per_page, $offset]);
$rows = $wpdb->get_results(
 $wpdb->prepare("SELECT * FROM $tD WHERE $where_sql {$_centro_sql} ORDER BY data_despesa DESC, id DESC LIMIT %d OFFSET %d", $list_params)
);

// Categorias existentes
$categorias_db = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT categoria FROM $tD WHERE escola_id = %d ORDER BY categoria", $escola_id));
$categorias_default = ['material_escolar','salários','manutenção','transporte','alimentação','utilidades','equipamento','serviços','geral','outro'];
$todas_categorias = array_unique(array_merge($categorias_default, $categorias_db));
sort($todas_categorias);

// Labels bonitos
function sige_desp_cat_label($cat) {
 $map = [
 'material_escolar' => 'Material Escolar',
 'salários' => 'Salários',
 'manutenção' => 'Manutenção',
 'transporte' => 'Transporte',
 'alimentação' => 'Alimentação',
 'utilidades' => 'Utilidades (água, luz)',
 'equipamento' => 'Equipamento',
 'serviços' => 'Serviços Externos',
 'geral' => 'Geral',
 'outro' => 'Outro',
 ];
 return $map[$cat] ?? ucfirst(str_replace('_', ' ', $cat));
}

function sige_desp_status_badge($s) {
 $s = strtolower((string)$s);
 if ($s === 'aprovado') return '<span class="sg-badge sg-badge-success sg-badge-dot">Aprovado</span>';
 if ($s === 'anulado') return '<span class="sg-badge sg-badge-error sg-badge-dot">Anulado</span>';
 return '<span class="sg-badge sg-badge-warning sg-badge-dot">Registado</span>';
}
?>


<div class="sg-finpro-wrap sg-expense-wrap">

 <section class="sg-finpro-hero sg-expense-hero" aria-label="Despesas da escola">
  <div class="sg-finpro-hero-copy">
   <div class="sg-finpro-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('activity') : ''; ?> Financeiro</div>
   <h1>Despesas da Escola</h1>
   <p>Registe, acompanhe e aprove saídas financeiras com uma leitura clara por período, categoria, centro e estado.</p>
   <div class="sg-finpro-hero-actions">
    <button type="button" class="sg-finpro-btn sg-finpro-btn-primary" data-sige-act="sigeDespOpenNew" data-sige-noargs><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('money') : ''; ?> Nova Despesa</button>
    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array_filter([
      'sige_desp_print' => 'relatorio',
      'data_de' => $f_data_de,
      'data_ate' => $f_data_ate,
      'cat' => $f_categoria,
      'st' => $f_status,
      'centro_id' => $centro_id_filtro ?: null,
    ]), home_url()), 'sige_desp_print')); ?>" target="_blank" class="sg-finpro-btn sg-finpro-btn-light"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('file') : ''; ?> Imprimir Relatório</a>
    <a href="?page=sige-app&view=financeiro-extratos" class="sg-finpro-btn sg-finpro-btn-light"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('wallet') : ''; ?> Extractos e Caixa</a>
   </div>
  </div>
  <div class="sg-finpro-hero-panel sg-expense-hero-panel">
   <div class="sg-finpro-mini-label">Despesas no período</div>
   <strong class="is-negative"><?php echo esc_html(number_format((float)$kpi_total, 2, ',', '.')); ?> <?php echo esc_html(sige_moeda()); ?></strong>
   <span><?php echo (int)$kpi_count; ?> registos encontrados nos filtros actuais</span>
   <div class="sg-finpro-progress"><i style="width:<?php echo esc_attr(min(100, $kpi_count > 0 ? 76 : 0)); ?>%"></i></div>
   <small>Actualizado em <?php echo esc_html($sg_mz_now->format('d/m/Y H:i')); ?> · Hora de Moçambique</small>
  </div>
 </section>

 <?php if ($msg): ?>
 <div class="sg-alert sg-alert-success sg-mb-4"><span class="sg-alert-icon"></span><div class="sg-alert-content"><strong>Sucesso</strong> - <?php echo esc_html($msg); ?></div></div>
 <?php endif; ?>
 <?php if ($err): ?>
 <div class="sg-alert sg-alert-error sg-mb-4"><span class="sg-alert-icon"></span><div class="sg-alert-content"><strong>Atenção</strong> - <?php echo esc_html($err); ?></div></div>
 <?php endif; ?>

 <div class="sg-finpro-kpi-grid sg-expense-kpi-grid">
  <article class="sg-finpro-kpi sg-finpro-tone-coral">
   <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('activity') : ''; ?></div>
   <div><span>Total das despesas</span><strong><?php echo esc_html(number_format((float)$kpi_total, 2, ',', '.')); ?></strong><small><?php echo esc_html(sige_moeda()); ?> nos filtros actuais</small></div>
  </article>
  <article class="sg-finpro-kpi sg-finpro-tone-blue">
   <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('file') : ''; ?></div>
   <div><span>Registos encontrados</span><strong><?php echo (int)$kpi_count; ?></strong><small>Inclui todos os estados</small></div>
  </article>
  <article class="sg-finpro-kpi sg-finpro-tone-amber">
   <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('calendar') : ''; ?></div>
   <div><span>Despesas deste mês</span><strong><?php echo esc_html(number_format((float)$kpi_mes_atual, 2, ',', '.')); ?></strong><small><?php echo esc_html(wp_date('m/Y')); ?></small></div>
  </article>
  <article class="sg-finpro-kpi sg-finpro-tone-green">
   <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('check') : ''; ?></div>
   <div><span>Estado dos registos</span><strong><?php echo (int)$kpi_aprovado_count; ?> aprovados</strong><small><?php echo (int)$kpi_registado_count; ?> por aprovar · <?php echo (int)$kpi_anulado_count; ?> anulados</small></div>
  </article>
 </div>

 <section class="sg-finpro-card sg-expense-card">
  <div class="sg-finpro-card-head">
   <div>
    <div class="sg-finpro-section-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('grid') : ''; ?></div>
    <div><h2>Pesquisa e filtros</h2><p>Filtre por período, categoria, estado, centro ou fornecedor.</p></div>
   </div>
  </div>

  <form method="get" class="sg-expense-filterbar">
   <input type="hidden" name="page" value="sige-app">
   <input type="hidden" name="view" value="financeiro-despesas">
   <div class="sg-filters-row">
    <?php
    if (function_exists('sige_fin_render_filtro_centro')) {
        $__centro_select = sige_fin_render_filtro_centro([
            'selected'      => $centro_id_filtro,
            'include_label' => false,
            'class'         => 'sg-select',
        ]);
        if ($__centro_select !== '') {
            echo '<div class="sg-form-group sg-form-group-inline"><label class="sg-label">Centro</label>' . $__centro_select . '</div>';
        }
    }
    ?>
    <div class="sg-form-group sg-form-group-inline"><label class="sg-label">De</label><input class="sg-input" type="date" name="data_de" value="<?php echo esc_attr($f_data_de); ?>"></div>
    <div class="sg-form-group sg-form-group-inline"><label class="sg-label">Até</label><input class="sg-input" type="date" name="data_ate" value="<?php echo esc_attr($f_data_ate); ?>"></div>
    <div class="sg-form-group sg-form-group-inline"><label class="sg-label">Categoria</label><select class="sg-select" name="cat"><option value="">Todas</option><?php foreach ($todas_categorias as $cat): ?><option value="<?php echo esc_attr($cat); ?>" <?php selected($f_categoria, $cat); ?>><?php echo esc_html(sige_desp_cat_label($cat)); ?></option><?php endforeach; ?></select></div>
    <div class="sg-form-group sg-form-group-inline"><label class="sg-label">Estado</label><select class="sg-select" name="st"><option value="">Todos</option><option value="registado" <?php selected($f_status, 'registado'); ?>>Registado</option><option value="aprovado" <?php selected($f_status, 'aprovado'); ?>>Aprovado</option><option value="anulado" <?php selected($f_status, 'anulado'); ?>>Anulado</option></select></div>
    <div class="sg-form-group sg-form-group-inline"><label class="sg-label">Pesquisa</label><input class="sg-input" type="text" name="q" value="<?php echo esc_attr($f_busca); ?>" placeholder="Descrição, fornecedor ou referência"></div>
    <div class="sg-filters-actions">
     <button type="submit" class="sg-finpro-btn sg-finpro-btn-primary"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('grid') : ''; ?> Filtrar</button>
     <a href="?page=sige-app&view=financeiro-despesas" class="sg-finpro-btn sg-finpro-btn-light">Limpar</a>
    </div>
   </div>
  </form>
 </section>

 <section class="sg-finpro-card sg-expense-card">
  <div class="sg-finpro-card-head">
   <div>
    <div class="sg-finpro-section-icon sg-finpro-soft-coral"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('wallet') : ''; ?></div>
    <div><h2>Lista de despesas</h2><p>Acompanhe despesas registadas, aprovações, anulações e comprovativos.</p></div>
   </div>
  </div>

  <div class="sg-table-wrapper sg-expense-table-wrap">
   <table class="sg-table sg-expense-table">
    <thead>
     <tr>
      <th>#</th><th>Data</th><th>Categoria</th><th>Descrição</th><th>Fornecedor</th><th class="sg-text-right">Valor</th><th>Método</th><th>Estado</th><th>Acções</th>
     </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
     <tr><td colspan="9"><div class="sg-empty-state sg-py-6"><p class="sg-empty-title">Nenhuma despesa encontrada</p><p class="sg-empty-desc">Tente ajustar os filtros ou registe uma nova despesa.</p></div></td></tr>
    <?php else: ?>
     <?php foreach ($rows as $r):
      $st = strtolower($r->status ?? 'registado');
      $metodos_label = function_exists('sige_fin_metodos_pagamento_map') ? sige_fin_metodos_pagamento_map('all') : [
        'numerario' => 'Numerário',
        'transferencia'=> 'Transferência Bancária',
        'mpesa' => 'M-Pesa',
        'emola'             => 'E-Mola',
        'emola_comerciante' => 'E-Mola Comerciante',
        'pagafacil' => 'Paga Fácil',
        'pos_bci' => 'POS BCI',
        'pos_bim' => 'POS BIM',
        'pos_stbank' => 'POS STBANK',
        'pos_moza' => 'POS MOZA',
        'pos_nedbank' => 'POS NEDBANK',
        'pos_fnb' => 'POS FNB',
        'pos' => 'POS (Banco não especificado)',
        'cheque' => 'Cheque',
        'cartao' => 'Cartão',
      ];
      $valor_fmt = number_format((float)$r->valor, 2, ',', '.');
      $descricao_curta = mb_strimwidth((string)$r->descricao, 0, 90, '...');
     ?>
     <tr class="<?php echo $st === 'anulado' ? 'sg-expense-row-muted' : ''; ?>">
      <td class="sg-mono">#<?php echo (int)$r->id; ?></td>
      <td class="sg-mono"><?php echo esc_html($r->data_despesa); ?></td>
      <td><?php echo esc_html(sige_desp_cat_label($r->categoria)); ?></td>
      <td><strong><?php echo esc_html($descricao_curta); ?></strong><?php if ($r->observacoes): ?><small class="sg-expense-note"><?php echo esc_html(mb_strimwidth($r->observacoes, 0, 70, '...')); ?></small><?php endif; ?></td>
      <td><?php echo esc_html($r->fornecedor ?: '-'); ?></td>
      <td class="sg-text-right sg-expense-money"><?php echo esc_html($valor_fmt); ?> <?php echo esc_html(sige_moeda()); ?></td>
      <td><span class="sg-badge sg-badge-muted"><?php echo esc_html($metodos_label[$r->metodo_pagamento] ?? ucfirst($r->metodo_pagamento ?? '')); ?></span></td>
      <td><?php echo sige_desp_status_badge($r->status); ?></td>
      <td>
       <div class="sg-expense-actions">
        <?php if ($st === 'registado' && $is_director): ?>
        <form method="post" class="sg-expense-action-form" data-sg-expense-confirm="approve" data-title="Aprovar despesa" data-message="Confirma que esta despesa deve ser aprovada?" data-expense="#<?php echo (int)$r->id; ?> · <?php echo esc_attr($descricao_curta); ?>" data-amount="<?php echo esc_attr($valor_fmt . ' ' . sige_moeda()); ?>">
         <?php wp_nonce_field('sige_aprovar_despesa', 'sige_nonce_aprovar'); ?>
         <input type="hidden" name="acao" value="aprovar_despesa"><input type="hidden" name="despesa_id" value="<?php echo (int)$r->id; ?>">
         <button type="submit" class="sg-btn sg-btn-sm sg-btn-success">Aprovar</button>
        </form>
        <?php endif; ?>
        <?php if ($st !== 'anulado' && $is_director): ?>
        <form method="post" class="sg-expense-action-form" data-sg-expense-confirm="cancel" data-title="Anular despesa" data-message="Confirma que esta despesa deve ser anulada?" data-expense="#<?php echo (int)$r->id; ?> · <?php echo esc_attr($descricao_curta); ?>" data-amount="<?php echo esc_attr($valor_fmt . ' ' . sige_moeda()); ?>">
         <?php wp_nonce_field('sige_anular_despesa', 'sige_nonce_anular'); ?>
         <input type="hidden" name="acao" value="anular_despesa"><input type="hidden" name="despesa_id" value="<?php echo (int)$r->id; ?>">
         <button type="submit" class="sg-btn sg-btn-sm sg-btn-danger">Anular</button>
        </form>
        <?php endif; ?>
        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['sige_desp_print'=>'comprovativo','id'=>(int)$r->id], home_url()), 'sige_desp_print')); ?>" target="_blank" class="sg-btn sg-btn-sm sg-btn-ghost">Comprovativo</a>
       </div>
      </td>
     </tr>
     <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
   </table>
  </div>

  <?php if ($total_pages > 1): ?>
  <div class="sg-expense-pagination">
   <small>Total: <?php echo (int)$total_rows; ?> · Página <?php echo (int)$page_num; ?> de <?php echo (int)$total_pages; ?></small>
   <div>
   <?php
   $base_url = add_query_arg(array_filter([
     'page' => 'sige-app', 'view' => 'financeiro-despesas',
     'data_de' => $f_data_de, 'data_ate' => $f_data_ate,
     'cat' => $f_categoria, 'st' => $f_status, 'q' => $f_busca,
     'centro_id' => $centro_id_filtro ?: null,
   ]));
   if ($page_num > 1): ?><a href="<?php echo esc_url(add_query_arg('p', $page_num - 1, $base_url)); ?>">Anterior</a><?php endif;
   for ($i = max(1, $page_num - 2); $i <= min($total_pages, $page_num + 2); $i++):
     if ($i === $page_num): ?><span class="current"><?php echo (int)$i; ?></span><?php else: ?><a href="<?php echo esc_url(add_query_arg('p', $i, $base_url)); ?>"><?php echo (int)$i; ?></a><?php endif;
   endfor;
   if ($page_num < $total_pages): ?><a href="<?php echo esc_url(add_query_arg('p', $page_num + 1, $base_url)); ?>">Seguinte</a><?php endif; ?>
   </div>
  </div>
  <?php endif; ?>
 </section>
</div>

<div class="sg-modal-backdrop sg-expense-modal-backdrop" id="desp-modal-novo" aria-hidden="true">
 <div class="sige-lanc-modal sg-expense-modal sg-expense-modal-lg">
  <div class="sige-lanc-modal-header"><h3 class="sige-lanc-modal-title">Registar nova despesa</h3></div>
  <form method="post" class="sg-expense-form">
   <div class="sige-lanc-modal-body">
    <?php wp_nonce_field('sige_nova_despesa', 'sige_despesa_nonce'); ?>
    <input type="hidden" name="acao" value="nova_despesa">
    <div class="sg-expense-form-grid">
     <div class="sg-form-group"><label class="sg-label">Data *</label><input class="sg-input" type="date" name="data_despesa" required value="<?php echo esc_attr(wp_date('Y-m-d')); ?>"></div>
     <div class="sg-form-group"><label class="sg-label">Valor (<?php echo esc_html(sige_moeda()); ?>) *</label><input class="sg-input" type="number" name="valor" step="0.01" min="0.01" required placeholder="Ex.: 15000.00"></div>
    </div>
    <div class="sg-form-group"><label class="sg-label">Descrição *</label><input class="sg-input" type="text" name="descricao" required placeholder="Ex.: Compra de material didáctico" maxlength="500"></div>
    <div class="sg-expense-form-grid">
     <div class="sg-form-group"><label class="sg-label">Categoria</label><select class="sg-select" name="categoria"><?php foreach ($todas_categorias as $cat): ?><option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html(sige_desp_cat_label($cat)); ?></option><?php endforeach; ?></select></div>
     <div class="sg-form-group"><label class="sg-label">Método de pagamento</label><select class="sg-select" name="metodo_pagamento"><?php echo function_exists('sige_fin_metodos_pagamento_options_html') ? sige_fin_metodos_pagamento_options_html('', ['context' => 'despesa']) : '<option value="numerario">Numerário</option><option value="transferencia">Transferência Bancária</option><option value="mpesa">M-Pesa</option><option value="emola">E-Mola</option><option value="emola_comerciante">E-Mola Comerciante</option><option value="pagafacil">Paga Fácil</option><option value="pos_bci">POS BCI</option><option value="pos_bim">POS BIM</option><option value="pos_stbank">POS STBANK</option><option value="pos_moza">POS MOZA</option><option value="pos_nedbank">POS NEDBANK</option><option value="pos_fnb">POS FNB</option><option value="cheque">Cheque</option><option value="cartao">Cartão</option>'; ?></select></div>
    </div>
    <?php if (function_exists('sige_fin_get_centros') && count(sige_fin_get_centros()) > 1): ?>
    <div class="sg-form-group"><label class="sg-label">Centro de custo</label><?php echo sige_fin_dropdown_centros(['name' => 'centro_id', 'id' => 'despesa_centro_id', 'class' => 'sg-select']); ?></div>
    <?php endif; ?>
    <div class="sg-expense-form-grid">
     <div class="sg-form-group"><label class="sg-label">Fornecedor</label><input class="sg-input" type="text" name="fornecedor" placeholder="Nome do fornecedor" maxlength="255"></div>
     <div class="sg-form-group"><label class="sg-label">Referência / Nº Factura</label><input class="sg-input" type="text" name="referencia" placeholder="Ex.: FAT-2026-001" maxlength="100"></div>
    </div>
    <div class="sg-form-group"><label class="sg-label">Observações</label><textarea class="sg-input" name="observacoes" placeholder="Notas adicionais" maxlength="1000"></textarea></div>
   </div>
   <div class="sige-lanc-modal-footer">
    <button type="button" class="sg-btn sg-btn-ghost" data-sige-act="sigeDespCloseNew" data-sige-noargs>Cancelar</button>
    <button type="submit" class="sg-btn sg-btn-primary">Registar Despesa</button>
   </div>
  </form>
 </div>
</div>

<div class="sg-modal-backdrop sg-expense-modal-backdrop" id="sigeExpenseConfirmModal" aria-hidden="true">
 <div class="sige-lanc-modal sg-expense-modal">
  <div class="sige-lanc-modal-header"><h3 class="sige-lanc-modal-title" id="sigeExpenseConfirmTitle">Confirmar acção</h3></div>
  <div class="sige-lanc-modal-body">
   <p class="sg-expense-confirm-text" id="sigeExpenseConfirmMessage">Confirme para continuar.</p>
   <div class="sg-expense-confirm-box">
    <span>Despesa</span><strong id="sigeExpenseConfirmItem">-</strong>
    <span>Valor</span><strong id="sigeExpenseConfirmAmount">-</strong>
   </div>
  </div>
  <div class="sige-lanc-modal-footer">
   <button type="button" class="sg-btn sg-btn-ghost" data-sige-act="sigeExpenseCloseConfirm" data-sige-noargs>Voltar</button>
   <button type="button" class="sg-btn sg-btn-primary" id="sigeExpenseConfirmButton">Confirmar</button>
  </div>
 </div>
</div>

<script <?php echo sige_csp_script_attr(); ?>>
(function(){
 var pendingForm = null;
 function openModal(el){ if(!el) return; el.classList.add('is-open'); el.style.display = 'flex'; el.setAttribute('aria-hidden','false'); document.body.classList.add('sg-modal-open'); }
 function closeModal(el){ if(!el) return; el.classList.remove('is-open'); el.style.display = 'none'; el.setAttribute('aria-hidden','true'); document.body.classList.remove('sg-modal-open'); }
 window.sigeDespOpenNew = function(){ openModal(document.getElementById('desp-modal-novo')); };
 window.sigeDespCloseNew = function(){ closeModal(document.getElementById('desp-modal-novo')); };
 window.sigeExpenseCloseConfirm = function(){ pendingForm = null; closeModal(document.getElementById('sigeExpenseConfirmModal')); };
 document.querySelectorAll('.sg-expense-modal-backdrop').forEach(function(backdrop){
  backdrop.addEventListener('click', function(e){ if(e.target === backdrop){ closeModal(backdrop); } });
 });
 document.querySelectorAll('[data-sg-expense-confirm]').forEach(function(form){
  form.addEventListener('submit', function(e){
   e.preventDefault(); pendingForm = form;
   document.getElementById('sigeExpenseConfirmTitle').textContent = form.getAttribute('data-title') || 'Confirmar acção';
   document.getElementById('sigeExpenseConfirmMessage').textContent = form.getAttribute('data-message') || 'Confirme para continuar.';
   document.getElementById('sigeExpenseConfirmItem').textContent = form.getAttribute('data-expense') || '-';
   document.getElementById('sigeExpenseConfirmAmount').textContent = form.getAttribute('data-amount') || '-';
   openModal(document.getElementById('sigeExpenseConfirmModal'));
  });
 });
 var btn = document.getElementById('sigeExpenseConfirmButton');
 if(btn){ btn.addEventListener('click', function(){ if(pendingForm){ var f = pendingForm; pendingForm = null; f.submit(); } }); }
 document.addEventListener('keydown', function(e){ if(e.key === 'Escape'){ closeModal(document.getElementById('desp-modal-novo')); closeModal(document.getElementById('sigeExpenseConfirmModal')); } });
})();
</script>
