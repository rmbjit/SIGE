<?php
if (!defined('ABSPATH')) exit;
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['financeiro.pagamentos_turma_ver'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_financeiro']
)) return;

global $wpdb;
$p = $wpdb->prefix;

// ── escola_id centralizado ──────────────────────────────────────────────────
$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;

$ano_lectivo = function_exists('sige_ano_lectivo_atual') ? (int) sige_ano_lectivo_atual() : (int) wp_date('Y');


// [v12.11.9.88.1] Estado operacional canónico para pagamentos por turma.
// O status principal do aluno é a fonte de verdade; evita que alunos importados
// ou reactivados fiquem invisíveis por status_matricula legado/desalinhado.
$__sige_a_activo_pag_turma = function_exists('sige_aluno_activo_sql')
    ? sige_aluno_activo_sql('a')
    : "(a.status IS NULL OR TRIM(LOWER(a.status)) IN ('activo','ativo','activa','ativa'))";

// [v13.4.0 BLOCO 3] Filtro universal por centro. Aplicado à subquery de
// lançamentos - quando activo, restringe o cálculo de pago/parcial/isento
// aos lançamentos do centro escolhido. Estados "pago_total" e "em_plano"
// passam a ser relativos àquele centro.
$centro_id_filtro = function_exists('sige_fin_centro_ativo') ? sige_fin_centro_ativo() : 0;
$_centro_sql_lanc = $centro_id_filtro > 0 ? ' AND centro_id = ' . (int)$centro_id_filtro : '';

// FIX: adicionado escola_id na query de turmas
$turmas = $wpdb->get_results($wpdb->prepare(
 "SELECT t.id, t.nome, t.classe
 FROM {$p}sige_turmas t
 WHERE t.escola_id = %d
 AND EXISTS (
 SELECT 1
 FROM {$p}sige_matriculas m
 JOIN {$p}sige_alunos a ON a.id = m.aluno_id AND a.escola_id = m.escola_id
 WHERE m.turma_id = t.id
 AND m.escola_id = %d
 AND m.ano_lectivo = %d
 AND {$__sige_a_activo_pag_turma}
 )
 ORDER BY t.classe ASC, t.nome ASC",
 $escola_id, $escola_id, $ano_lectivo
));

$turma_id = sige_fin_get_int('turma_id', ($turmas[0]->id ?? 0));
$mes_ref = sige_fin_get_param('mes_ref') ?: wp_date('Y-m');

// Validar formato mes_ref
if (!preg_match('/^\d{4}-\d{2}$/', $mes_ref)) $mes_ref = wp_date('Y-m');

$alunos = [];
// FIX: inicializar sem_lancamento e isento nos totais
$totais = [
 'pago_total' => 0,
 'pago_parcial' => 0,
 'em_plano' => 0,
 'nao_pago' => 0,
 'isento' => 0,
 'sem_lancamento' => 0, // ← FIX: estava em falta
 'total' => 0,
];

if ($turma_id > 0) {
 $alunos = $wpdb->get_results($wpdb->prepare("
 SELECT a.id, a.nome_completo, a.numero_processo,
 COALESCE(fin.valor_total, 0) as valor_total,
 COALESCE(fin.valor_pago, 0) as valor_pago,
 COALESCE(fin.n_lancamentos, 0) as n_lancamentos,
 COALESCE(fin.n_pagos, 0) as n_pagos,
 COALESCE(fin.n_parciais, 0) as n_parciais,
 COALESCE(fin.n_isentos, 0) as n_isentos
 FROM {$p}sige_alunos a
 JOIN {$p}sige_matriculas m
 ON a.id = m.aluno_id
 AND m.turma_id = %d
 AND m.escola_id = %d
 AND m.ano_lectivo = %d
 LEFT JOIN (
 SELECT aluno_id,
 -- [FIX FORMULA-08] Usar multa canónica com NULLIF + fallback
 -- ANTES: COALESCE(multa_cobrada, 0) ignorava multas pendentes (cobrada=NULL)
 SUM(GREATEST(
 COALESCE(valor_original, 0)
 + COALESCE(valor_transporte, 0)
 + COALESCE(valor_extras, 0)
 + COALESCE(NULLIF(valor_multa_cobrada, 0), valor_multa, 0)
 - COALESCE(valor_desconto, 0)
 - COALESCE(valor_desconto_especial, 0)
 , 0)) as valor_total,
 SUM(valor_pago) as valor_pago,
 COUNT(*) as n_lancamentos,
 SUM(CASE WHEN status = 'pago' THEN 1 ELSE 0 END) as n_pagos,
 SUM(CASE WHEN status = 'parcial' THEN 1 ELSE 0 END) as n_parciais,
 SUM(CASE WHEN status = 'isento' THEN 1 ELSE 0 END) as n_isentos,
 SUM(CASE WHEN status = 'em_plano' THEN 1 ELSE 0 END) as n_em_plano
 FROM {$p}sige_fin_lancamentos
 WHERE escola_id = %d -- FIX: escola_id na subquery
 AND mes_referencia = %s
 AND status != 'cancelado'
 {$_centro_sql_lanc}
 GROUP BY aluno_id
 ) fin ON fin.aluno_id = a.id
 WHERE a.escola_id = %d -- FIX: escola_id na query principal
 AND {$__sige_a_activo_pag_turma}
 ORDER BY CASE WHEN (m.status_matricula IS NULL OR TRIM(LOWER(m.status_matricula)) IN ('activa','ativa','activo','ativo')) THEN 0 ELSE 1 END,
          a.nome_completo ASC
 ", $turma_id, $escola_id, $ano_lectivo, $escola_id, $mes_ref, $escola_id));

 foreach ($alunos as $a) {
 $totais['total']++;

 if ($a->n_lancamentos == 0) {
 $a->estado = 'sem_lancamento';
 $totais['sem_lancamento']++; // ← FIX: agora é contado

 } elseif ($a->n_isentos > 0 && $a->n_isentos == $a->n_lancamentos) {
 $a->estado = 'isento';
 $totais['isento']++;

 } elseif ($a->valor_pago >= $a->valor_total && $a->valor_total > 0) {
 $a->estado = 'pago_total';
 $totais['pago_total']++;

 } elseif ($a->valor_pago > 0) {
 $a->estado = 'pago_parcial';
 $totais['pago_parcial']++;

 } elseif (!empty($a->n_em_plano) && (int)$a->n_em_plano > 0) {
 // Lançamentos congelados em plano negociado - não é dívida livre
 $a->estado = 'em_plano';
 $totais['em_plano']++;

 } else {
 $a->estado = 'nao_pago';
 $totais['nao_pago']++;
 }

 $a->saldo = max(0, $a->valor_total - $a->valor_pago);
 }
}

$turma_info = '';
foreach ($turmas as $t) {
 if ($t->id == $turma_id) { $turma_info = $t->classe . ' - ' . $t->nome; break; }
}

$mes_label = wp_date('F/Y', strtotime($mes_ref . '-01'));
?>
<?php
$soma_valor_total = !empty($alunos) ? array_sum(array_map(fn($a) => (float)$a->valor_total, $alunos)) : 0;
$soma_valor_pago  = !empty($alunos) ? array_sum(array_map(fn($a) => (float)$a->valor_pago, $alunos)) : 0;
$soma_saldo       = !empty($alunos) ? array_sum(array_map(fn($a) => (float)$a->saldo, $alunos)) : 0;
$taxa_cobranca    = $soma_valor_total > 0 ? min(100, round(($soma_valor_pago / $soma_valor_total) * 100, 1)) : 0;
$moeda            = function_exists('sige_moeda') ? sige_moeda() : 'MT';
$turma_label      = $turma_info ?: 'Seleccione uma turma';
$turmas_count     = is_array($turmas) ? count($turmas) : 0;
$mes_curto        = wp_date('M Y', strtotime($mes_ref . '-01'));
?>

<div class="sg-classpay-wrap">
  <section class="sg-finpro-hero sg-classpay-hero" aria-label="Pagamentos por turma">
    <div>
      <div class="sg-finpro-kicker"><?php echo sige_ui_icon('clipboard'); ?> Tesouraria</div>
      <h1>Pagamentos por Turma</h1>
      <p>Acompanhe a situação de cobrança de cada turma, confirme quem já pagou, quem está pendente e actue sem perder a leitura geral da operação.</p>
      <div class="sg-finpro-hero-actions">
        <a class="sg-finpro-btn sg-finpro-btn-primary" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-pagamentos')); ?>">
          <?php echo sige_ui_icon('money'); ?> Registar Pagamento
        </a>
        <button type="button" class="sg-finpro-btn sg-finpro-btn-light" data-sige-act="sigeImprimirPagina" data-sige-noargs>
          <?php echo sige_ui_icon('file'); ?> Imprimir lista
        </button>
      </div>
    </div>
    <div class="sg-finpro-hero-panel sg-classpay-hero-panel">
      <span class="sg-finpro-mini-label">Turma seleccionada</span>
      <strong><?php echo esc_html($turma_label); ?></strong>
      <small><?php echo esc_html($mes_label); ?> · <?php echo (int)$totais['total']; ?> aluno(s) activos nesta consulta</small>
      <div class="sg-finpro-progress" aria-hidden="true"><i style="width:<?php echo esc_attr($taxa_cobranca); ?>%"></i></div>
      <small><?php echo esc_html($taxa_cobranca); ?>% do valor esperado já recebido.</small>
    </div>
  </section>

  <form method="GET" class="sg-finpro-filterbar sg-classpay-filter" aria-label="Filtros de pagamentos por turma">
    <input type="hidden" name="page" value="sige-app">
    <input type="hidden" name="view" value="pagamentos-turma">
    <div class="sg-finpro-filter-title">
      <strong>Consultar turma</strong>
      <span>Escolha a turma, o mês e o centro de custo quando aplicável.</span>
    </div>
    <label>
      <span>Turma</span>
      <select name="turma_id">
        <?php foreach ($turmas as $t): ?>
          <option value="<?php echo esc_attr($t->id); ?>" <?php selected($turma_id, $t->id); ?>>
            <?php echo esc_html($t->classe . ' - ' . $t->nome); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      <span>Mês</span>
      <input type="month" name="mes_ref" value="<?php echo esc_attr($mes_ref); ?>">
    </label>
    <?php
      if (function_exists('sige_fin_render_filtro_centro')) {
        $__sel = sige_fin_render_filtro_centro([
          'selected'      => $centro_id_filtro,
          'include_label' => false,
          'class'         => '',
          'style'         => '',
        ]);
        if ($__sel !== '') {
          echo '<label><span>Centro</span>' . $__sel . '</label>';
        }
      }
    ?>
    <button type="submit" class="sg-finpro-btn sg-finpro-btn-primary">Aplicar</button>
  </form>

  <?php if ($turma_id > 0 && !empty($alunos)): ?>

  <section class="sg-finpro-kpi-grid sg-classpay-kpis" aria-label="Resumo da turma">
    <article class="sg-finpro-kpi sg-finpro-kpi-green">
      <span class="sg-finpro-kpi-icon"><?php echo sige_ui_icon('check'); ?></span>
      <div><span>Pagos</span><strong><?php echo (int)$totais['pago_total']; ?></strong><small>Regularizados no mês</small></div>
    </article>
    <article class="sg-finpro-kpi sg-finpro-kpi-amber">
      <span class="sg-finpro-kpi-icon"><?php echo sige_ui_icon('activity'); ?></span>
      <div><span>Parciais</span><strong><?php echo (int)$totais['pago_parcial']; ?></strong><small>Com saldo por concluir</small></div>
    </article>
    <article class="sg-finpro-kpi sg-finpro-kpi-red">
      <span class="sg-finpro-kpi-icon"><?php echo sige_ui_icon('alert'); ?></span>
      <div><span>Pendentes</span><strong><?php echo (int)$totais['nao_pago']; ?></strong><small>Sem pagamento registado</small></div>
    </article>
    <article class="sg-finpro-kpi sg-finpro-kpi-blue">
      <span class="sg-finpro-kpi-icon"><?php echo sige_ui_icon('users'); ?></span>
      <div><span>Total da turma</span><strong><?php echo (int)$totais['total']; ?></strong><small><?php echo esc_html($mes_curto); ?></small></div>
    </article>
  </section>

  <section class="sg-finpro-grid sg-classpay-grid">
    <article class="sg-finpro-card sg-classpay-status-card">
      <div class="sg-finpro-card-head">
        <div>
          <span class="sg-finpro-section-icon sg-icon-soft-green"><?php echo sige_ui_icon('chart'); ?></span>
          <div><h2>Leitura da cobrança</h2><p>Resumo financeiro da turma no mês seleccionado.</p></div>
        </div>
      </div>
      <div class="sg-finpro-mini-kpis sg-classpay-money-grid">
        <div><span>Valor esperado</span><strong><?php echo number_format($soma_valor_total, 2); ?> <?php echo esc_html($moeda); ?></strong></div>
        <div><span>Total recebido</span><strong class="sg-classpay-green"><?php echo number_format($soma_valor_pago, 2); ?> <?php echo esc_html($moeda); ?></strong></div>
        <div><span>Saldo em aberto</span><strong class="sg-classpay-red"><?php echo number_format($soma_saldo, 2); ?> <?php echo esc_html($moeda); ?></strong></div>
      </div>
      <div class="sg-classpay-progress-block">
        <div><strong>Taxa de cobrança</strong><span><?php echo esc_html($taxa_cobranca); ?>%</span></div>
        <div class="sg-finpro-progress"><i style="width:<?php echo esc_attr($taxa_cobranca); ?>%"></i></div>
      </div>
    </article>

    <article class="sg-finpro-card sg-classpay-status-card">
      <div class="sg-finpro-card-head">
        <div>
          <span class="sg-finpro-section-icon sg-icon-soft-purple"><?php echo sige_ui_icon('grid'); ?></span>
          <div><h2>Estado dos alunos</h2><p>Distribuição operacional da turma.</p></div>
        </div>
      </div>
      <?php
        $status_rows = [
          ['Pago total', $totais['pago_total'], '#34a853'],
          ['Pago parcial', $totais['pago_parcial'], '#f4a621'],
          ['Em plano', $totais['em_plano'], '#6d5dfc'],
          ['Isento', $totais['isento'], '#3b82f6'],
          ['Sem lançamento', $totais['sem_lancamento'], '#9aa0af'],
          ['Não pago', $totais['nao_pago'], '#ef4444'],
        ];
      ?>
      <div class="sg-classpay-status-list">
        <?php foreach ($status_rows as $row):
          $pct = $totais['total'] > 0 ? round(((int)$row[1] / max(1, (int)$totais['total'])) * 100, 1) : 0;
        ?>
        <div class="sg-classpay-status-row">
          <div><span style="background:<?php echo esc_attr($row[2]); ?>"></span><?php echo esc_html($row[0]); ?></div>
          <strong><?php echo (int)$row[1]; ?></strong>
          <i><b style="width:<?php echo esc_attr($pct); ?>%;background:<?php echo esc_attr($row[2]); ?>"></b></i>
        </div>
        <?php endforeach; ?>
      </div>
    </article>
  </section>

  <article class="sg-finpro-card sg-classpay-table-card">
    <div class="sg-finpro-card-head">
      <div>
        <span class="sg-finpro-section-icon sg-icon-soft-blue"><?php echo sige_ui_icon('clipboard'); ?></span>
        <div><h2>Lista de alunos</h2><p>Valores esperados, pagos e saldo por aluno.</p></div>
      </div>
      <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-devedores')); ?>">Ver cobranças →</a>
    </div>

    <div class="sg-finpro-scroll-table">
      <table class="sg-finpro-table sg-classpay-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Aluno</th>
            <th>Nº processo</th>
            <th class="sg-right">Valor esperado</th>
            <th class="sg-right">Pago</th>
            <th class="sg-right">Saldo</th>
            <th class="sg-center">Estado</th>
            <th class="sg-center">Acção</th>
          </tr>
        </thead>
        <tbody>
          <?php $n = 0; foreach ($alunos as $a): $n++;
            $badge = 'Sem lançamento'; $badge_class = 'sg-badge-muted';
            switch ($a->estado) {
              case 'pago_total': $badge = 'Pago'; $badge_class = 'sg-badge-green'; break;
              case 'pago_parcial': $badge = 'Parcial'; $badge_class = 'sg-badge-amber'; break;
              case 'em_plano': $badge = 'Em plano'; $badge_class = 'sg-badge-purple'; break;
              case 'nao_pago': $badge = 'Não pago'; $badge_class = 'sg-badge-red'; break;
              case 'isento': $badge = 'Isento'; $badge_class = 'sg-badge-blue'; break;
              default: $badge = 'Sem lançamento'; $badge_class = 'sg-badge-muted'; break;
            }
          ?>
          <tr>
            <td><?php echo (int)$n; ?></td>
            <td><strong><?php echo esc_html($a->nome_completo); ?></strong></td>
            <td><span class="sg-classpay-processo"><?php echo esc_html($a->numero_processo); ?></span></td>
            <td class="sg-right"><?php echo $a->n_lancamentos > 0 ? esc_html(number_format((float)$a->valor_total, 2) . ' ' . $moeda) : '-'; ?></td>
            <td class="sg-right sg-classpay-green"><?php echo $a->valor_pago > 0 ? esc_html(number_format((float)$a->valor_pago, 2) . ' ' . $moeda) : '-'; ?></td>
            <td class="sg-right <?php echo $a->saldo > 0 ? 'sg-classpay-red' : 'sg-classpay-green'; ?>"><?php echo $a->n_lancamentos > 0 ? esc_html(number_format((float)$a->saldo, 2) . ' ' . $moeda) : '-'; ?></td>
            <td class="sg-center"><span class="sg-classpay-badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html($badge); ?></span></td>
            <td class="sg-center">
              <?php if ($a->estado === 'nao_pago' || $a->estado === 'pago_parcial'): ?>
                <a class="sg-classpay-action" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-pagamentos&aluno_id=' . (int)$a->id)); ?>">Pagar</a>
              <?php elseif ($a->estado === 'sem_lancamento'): ?>
                <a class="sg-classpay-action sg-classpay-action-light" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-lancamentos&aluno_id=' . (int)$a->id)); ?>">Lançar</a>
              <?php else: ?>
                <span class="sg-classpay-ok">-</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3">Totais da turma</td>
            <td class="sg-right"><?php echo esc_html(number_format($soma_valor_total, 2) . ' ' . $moeda); ?></td>
            <td class="sg-right"><?php echo esc_html(number_format($soma_valor_pago, 2) . ' ' . $moeda); ?></td>
            <td class="sg-right"><?php echo esc_html(number_format($soma_saldo, 2) . ' ' . $moeda); ?></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </article>

  <?php elseif ($turma_id > 0): ?>
    <div class="sg-product-state">
      <div class="sg-product-state-icon"><?php echo sige_ui_icon('users'); ?></div>
      <div>
        <h2>Sem alunos nesta turma</h2>
        <p>Nenhum aluno activo foi encontrado nesta turma para o ano lectivo <?php echo esc_html($ano_lectivo); ?>.</p>
      </div>
    </div>
  <?php elseif (empty($turmas)): ?>
    <div class="sg-product-state">
      <div class="sg-product-state-icon"><?php echo sige_ui_icon('school'); ?></div>
      <div>
        <h2>Sem turmas activas</h2>
        <p>Não foram encontradas turmas com matrículas activas para o ano lectivo <?php echo esc_html($ano_lectivo); ?>.</p>
      </div>
    </div>
  <?php endif; ?>
</div>

<style>
@media print {
  #sige-sidebar, #sige-hamburger, #sige-overlay, .sg-app-topbar, .sg-finpro-hero-actions, .sg-classpay-filter, .sg-classpay-table th:last-child, .sg-classpay-table td:last-child { display: none !important; }
  .sg-app-content, .sg-app-page { padding: 0 !important; background: #fff !important; }
  .sg-finpro-card, .sg-finpro-kpi, .sg-finpro-hero { box-shadow: none !important; border: 1px solid #ddd !important; }
  .sg-finpro-scroll-table { overflow: visible !important; }
  .sg-finpro-table { min-width: 100% !important; }
}
</style>
