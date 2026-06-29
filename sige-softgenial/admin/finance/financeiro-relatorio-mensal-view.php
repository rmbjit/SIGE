<?php

if (!defined('ABSPATH')) exit;

global $wpdb;

// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['financeiro.relatorio_mensal_ver'],
    ['sige_financeiro','sige_secretario','sige_director']
)) return;

$tP = $wpdb->prefix . 'sige_fin_pagamentos';

$tF = $wpdb->prefix . 'sige_fin_fechos_caixa';


// [12.9.46] Datas financeiras robustas: sem wp_date(strtotime(...)) para
// evitar início/fim de mês invertidos em instâncias com timezone diferente.
if (!function_exists('sige_fin_normalize_month_ym')) {
    function sige_fin_normalize_month_ym($value) {
        $value = trim((string)$value);
        if (preg_match('/^\d{4}-\d{2}$/', $value)) return $value;
        if (preg_match('/^(\d{1,2})[\/\.\-](\d{4})$/', $value, $m)) return sprintf('%04d-%02d', (int)$m[2], (int)$m[1]);
        return function_exists('sige_mz_date') ? sige_mz_date('Y-m') : wp_date('Y-m');
    }
}
if (!function_exists('sige_fin_month_bounds')) {
    function sige_fin_month_bounds($ym) {
        $ym = sige_fin_normalize_month_ym($ym);
        $start = $ym . '-01';
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        return [$start, $dt->modify('last day of this month')->format('Y-m-d')];
    }
}
if (!function_exists('sige_fin_month_days')) {
    function sige_fin_month_days($start, $end) {
        $out = [];
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $e = DateTimeImmutable::createFromFormat('!Y-m-d', $end);
        while ($d && $e && $d <= $e) { $out[] = $d->format('Y-m-d'); $d = $d->modify('+1 day'); }
        return $out;
    }
}

// ── escola_id centralizado ─────────────────────────────────────────────────
$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
if ($escola_id <= 0) {
 echo '<div class="notice notice-error"><p>Escola não identificada. Relatório financeiro bloqueado para evitar acesso ao tenant errado.</p></div>';
 return;
}

$mes = sige_fin_normalize_month_ym(sige_fin_get_param('mes')); // YYYY-MM

// [v13.4.0 BLOCO 3] Filtro universal por centro
$centro_id_filtro = function_exists('sige_fin_centro_ativo') ? sige_fin_centro_ativo() : 0;
$_centro_sql_p = $centro_id_filtro > 0 ? ' AND centro_id = ' . (int)$centro_id_filtro : '';

[$inicio, $fim] = sige_fin_month_bounds($mes);

$rows = $wpdb->get_results($wpdb->prepare("

 SELECT DATE(data_pagamento) as dia, metodo_pagamento, SUM(valor_pago) as total

 FROM $tP

 WHERE escola_id = %d AND data_pagamento >= %s AND data_pagamento <= %s
 {$_centro_sql_p}

 GROUP BY DATE(data_pagamento), metodo_pagamento

 ORDER BY dia ASC

", $escola_id, $inicio . ' 00:00:00', $fim . ' 23:59:59'));

// NOTA [v13.4.0]: sige_fin_fechos_caixa é tabela agregada diária que NÃO tem
// centro_id (é um log do caixa inteiro, consolidado). Portanto os fechos
// listados abaixo mostram sempre o consolidado, independentemente do filtro
// de centro. Isto é coerente - um fecho de caixa é por natureza do dia todo.
$fechos = $wpdb->get_results($wpdb->prepare("

 SELECT data_caixa, total_bruto, total_estornos, total_liquido, fechado_por, fechado_em

 FROM $tF

 WHERE escola_id = %d AND data_caixa BETWEEN %s AND %s AND status = 'fechado'

 ORDER BY data_caixa ASC

", $escola_id, $inicio, $fim), OBJECT_K);

// Consolidação

$por_metodo = [];

$total_bruto = 0.0;

$total_estornos = 0.0;

$total_liquido = 0.0;

$por_dia = [];

foreach ($rows as $r) {

 $dia = $r->dia;

 $met = (string)$r->metodo_pagamento;

 $val = (float)$r->total;

 if (!isset($por_dia[$dia])) $por_dia[$dia] = [];

 if (!isset($por_dia[$dia][$met])) $por_dia[$dia][$met] = 0.0;

 $por_dia[$dia][$met] += $val;

 if (!isset($por_metodo[$met])) $por_metodo[$met] = 0.0;

 $por_metodo[$met] += $val;

 // bruto/estornos/liq

 if ($val > 0 && $met !== 'estorno') $total_bruto += $val;

 if ($val < 0 || $met === 'estorno') $total_estornos += abs($val);

 $total_liquido += $val;

}


// ── Taxa de cobrança: emitido vs cobrado por mes_referencia ──────────────
// "De tudo o que foi lançado para este mês, quanto foi efectivamente pago?"
// [v13.3.0 Bloco 2] Usar expressão canónica sige_fin_total_bruto_sql() em vez
// de duplicar a fórmula inline. Mesmo resultado, zero duplicação.
$tL = $wpdb->prefix . 'sige_fin_lancamentos';
$__bruto_expr = sige_fin_total_bruto_sql('');
// A query não usa alias - remover prefix do alias inexistente
$__bruto_expr = str_replace('.valor_', 'valor_', $__bruto_expr);
$taxa_row = $wpdb->get_row($wpdb->prepare(
 "SELECT
 COALESCE(SUM({$__bruto_expr}), 0) AS emitido,
 COALESCE(SUM(valor_pago), 0) AS cobrado,
 COUNT(*) AS total_lanc,
 SUM(CASE WHEN status = 'pago' THEN 1 ELSE 0 END) AS n_pagos,
 SUM(CASE WHEN status IN ('pendente','parcial','em_plano') THEN 1 ELSE 0 END) AS n_pendentes
 FROM $tL
 WHERE escola_id = %d
 AND LEFT(mes_referencia, 7) = %s
 AND status NOT IN ('cancelado', 'isento')
 {$_centro_sql_p}",
 $escola_id, $mes
));
$emitido_mes = (float)($taxa_row->emitido ?? 0);
$cobrado_mes = (float)($taxa_row->cobrado ?? 0);
$pendente_mes = max(0, $emitido_mes - $cobrado_mes);
$taxa_cobranca = $emitido_mes > 0 ? round(($cobrado_mes / $emitido_mes) * 100, 1) : 0;
$n_lanc_total = (int)($taxa_row->total_lanc ?? 0);
$n_lanc_pagos = (int)($taxa_row->n_pagos ?? 0);
$n_lanc_pend = (int)($taxa_row->n_pendentes ?? 0);

// Cor da taxa
$taxa_cor = $taxa_cobranca >= 80 ? '#166534' : ($taxa_cobranca >= 50 ? '#92400e' : '#991b1b');
$taxa_bg = $taxa_cobranca >= 80 ? '#f0fdf4' : ($taxa_cobranca >= 50 ? '#fefce8' : '#fef2f2');
$taxa_borda = $taxa_cobranca >= 80 ? '#bbf7d0' : ($taxa_cobranca >= 50 ? '#fde68a' : '#fecaca');

?>

<?php echo sige_cdn_script("exceljs"); ?>

<div class="sg-finreport-wrap">

  <section class="sg-finreport-hero">
    <div class="sg-finreport-hero-copy">
      <div class="sg-finreport-kicker">
        <?php echo function_exists('sige_ui_icon') ? sige_ui_icon('calendar') : ''; ?>
        <span>Relatório financeiro</span>
      </div>
      <h1>Relatório Mensal</h1>
      <p>Consolide receitas, estornos, despesas, fechos de caixa e taxa de cobrança do mês seleccionado, com leitura clara para a direcção e tesouraria.</p>
      <div class="sg-finreport-hero-actions">
        <button type="button" class="sg-finreport-btn sg-finreport-btn-primary" data-sige-act="exportarMensalExcel" data-sige-noargs>
          <?php echo function_exists('sige_ui_icon') ? sige_ui_icon('file') : ''; ?>
          Baixar Excel
        </button>
        <a class="sg-finreport-btn sg-finreport-btn-light" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-extratos')); ?>">
          <?php echo function_exists('sige_ui_icon') ? sige_ui_icon('money') : ''; ?>
          Ver caixa
        </a>
      </div>
    </div>
    <div class="sg-finreport-hero-panel">
      <span>Total líquido do mês</span>
      <strong><?php echo number_format($total_liquido, 2); ?> <?php echo esc_html(sige_moeda()); ?></strong>
      <small>Período: <?php echo esc_html(DateTimeImmutable::createFromFormat('!Y-m-d', $inicio)->format('d/m/Y')); ?> a <?php echo esc_html(DateTimeImmutable::createFromFormat('!Y-m-d', $fim)->format('d/m/Y')); ?></small>
      <div class="sg-finreport-hero-split">
        <div><b><?php echo number_format($total_bruto, 2); ?> <?php echo esc_html(sige_moeda()); ?></b><em>Bruto</em></div>
        <div><b><?php echo number_format($total_estornos, 2); ?> <?php echo esc_html(sige_moeda()); ?></b><em>Estornos</em></div>
      </div>
    </div>
  </section>

  <section class="sg-finreport-filterbar">
    <div class="sg-finreport-filter-title">
      <?php echo function_exists('sige_ui_icon') ? sige_ui_icon('settings') : ''; ?>
      <span>Definir período</span>
    </div>
    <form method="get" class="sg-finreport-filter-form">
      <input type="hidden" name="page" value="sige-app">
      <input type="hidden" name="view" value="financeiro-relatorio-mensal">
      <label>
        <span>Mês</span>
        <input type="month" name="mes" value="<?php echo esc_attr($mes); ?>">
      </label>
      <?php
        if (function_exists('sige_fin_render_filtro_centro')) {
          echo '<label class="sg-finreport-center-filter"><span>Centro</span>';
          echo sige_fin_render_filtro_centro([
            'selected' => $centro_id_filtro,
            'style'    => '',
          ]);
          echo '</label>';
        }
      ?>
      <button type="submit" class="sgk-btn sgk-btn-sec">Filtrar relatório</button>
    </form>
  </section>

  <section class="sg-finreport-kpi-grid">
    <article class="sg-finreport-kpi sg-finreport-tone-purple">
      <div class="sg-finreport-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('money') : ''; ?></div>
      <div><span>Total líquido</span><strong><?php echo number_format($total_liquido, 2); ?> <?php echo esc_html(sige_moeda()); ?></strong><small>Depois de estornos</small></div>
    </article>
    <article class="sg-finreport-kpi sg-finreport-tone-green">
      <div class="sg-finreport-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('trending') : ''; ?></div>
      <div><span>Total bruto</span><strong><?php echo number_format($total_bruto, 2); ?> <?php echo esc_html(sige_moeda()); ?></strong><small>Receitas registadas</small></div>
    </article>
    <article class="sg-finreport-kpi sg-finreport-tone-coral">
      <div class="sg-finreport-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('activity') : ''; ?></div>
      <div><span>Estornos</span><strong><?php echo number_format($total_estornos, 2); ?> <?php echo esc_html(sige_moeda()); ?></strong><small>Movimentos revertidos</small></div>
    </article>
    <article class="sg-finreport-kpi sg-finreport-tone-blue">
      <div class="sg-finreport-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('chart') : ''; ?></div>
      <div><span>Taxa de cobrança</span><strong><?php echo esc_html($taxa_cobranca); ?>%</strong><small><?php echo $n_lanc_pagos; ?>/<?php echo $n_lanc_total; ?> lançamentos pagos</small></div>
    </article>
  </section>

  <section class="sg-finreport-grid sg-finreport-grid-main">
    <article class="sg-finreport-card">
      <div class="sg-finreport-card-head">
        <div>
          <span class="sg-finreport-section-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('calendar') : ''; ?></span>
          <div>
            <h2>Movimentos por dia</h2>
            <p>Leitura diária do caixa, com estado de fecho e detalhe por método de pagamento.</p>
          </div>
        </div>
      </div>
      <div class="sg-finreport-scroll-table">
        <table class="sg-finreport-table" id="tabela-mensal">
          <thead>
            <tr>
              <th>Dia</th>
              <th>Estado</th>
              <th class="sige-u-tar">Líquido</th>
              <th class="sige-u-tar">Bruto</th>
              <th class="sige-u-tar">Estornos</th>
              <th>Detalhe por método</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach (sige_fin_month_days($inicio, $fim) as $dia):
            $metodos = $por_dia[$dia] ?? [];
            $liq = 0.0; $bru = 0.0; $est = 0.0;
            foreach ($metodos as $m => $v) {
              $liq += (float)$v;
              if ($v > 0 && $m !== 'estorno') $bru += (float)$v;
              if ($v < 0 || $m === 'estorno') $est += abs((float)$v);
            }
            $is_closed = isset($fechos[$dia]);
          ?>
            <tr>
              <td><strong><?php echo esc_html(wp_date('d/m/Y', strtotime($dia))); ?></strong></td>
              <td><?php if ($is_closed): ?><span class="sg-finreport-badge sg-finreport-badge-closed">Fechado</span><?php else: ?><span class="sg-finreport-badge sg-finreport-badge-open">Aberto</span><?php endif; ?></td>
              <td class="is-money"><?php echo number_format($liq, 2); ?> <?php echo esc_html(sige_moeda()); ?></td>
              <td class="is-money is-neutral"><?php echo number_format($bru, 2); ?> <?php echo esc_html(sige_moeda()); ?></td>
              <td class="is-money is-danger"><?php echo number_format($est, 2); ?> <?php echo esc_html(sige_moeda()); ?></td>
              <td>
                <?php if (empty($metodos)): ?>
                  <span class="sg-finreport-muted">-</span>
                <?php else: ?>
                  <div class="sg-finreport-method-list">
                    <?php foreach ($metodos as $m => $v): ?>
                      <span><b><?php echo esc_html(function_exists('sige_fin_metodo_pagamento_label') ? sige_fin_metodo_pagamento_label($m) : (function_exists('sige_fin_metodo_pagamento_label') ? sige_fin_metodo_pagamento_label($m) : (function_exists('sige_fin_metodo_pagamento_label') ? sige_fin_metodo_pagamento_label($m) : ucfirst($m)))); ?></b> <?php echo number_format((float)$v, 2); ?> <?php echo esc_html(sige_moeda()); ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </article>

    <aside class="sg-finreport-side-stack">
      <article class="sg-finreport-card">
        <div class="sg-finreport-card-head">
          <div>
            <span class="sg-finreport-section-icon sg-finreport-soft-green"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('chart') : ''; ?></span>
            <div>
              <h2>Taxa de cobrança</h2>
              <p>Comparação entre o lançado e o cobrado no mês.</p>
            </div>
          </div>
        </div>
        <?php if ($n_lanc_total > 0): ?>
          <div class="sg-finreport-rate-card" style="--sg-rate:<?php echo esc_attr(min(100, $taxa_cobranca)); ?>%;">
            <div class="sg-finreport-rate-top">
              <strong style="color:<?php echo esc_attr($taxa_cor); ?>;"><?php echo esc_html($taxa_cobranca); ?>%</strong>
              <span><?php if ($taxa_cobranca >= 80) echo 'Boa performance'; elseif ($taxa_cobranca >= 50) echo 'Performance moderada'; else echo 'Atenção necessária'; ?></span>
            </div>
            <div class="sg-finreport-rate-track"><i style="background:<?php echo esc_attr($taxa_cor); ?>;"></i></div>
          </div>
          <div class="sg-finreport-mini-grid">
            <div><span>Emitido</span><strong><?php echo number_format($emitido_mes, 2); ?> <?php echo esc_html(sige_moeda()); ?></strong><small><?php echo $n_lanc_total; ?> lançamentos</small></div>
            <div><span>Cobrado</span><strong><?php echo number_format($cobrado_mes, 2); ?> <?php echo esc_html(sige_moeda()); ?></strong><small><?php echo $n_lanc_pagos; ?> pagos</small></div>
            <div><span>Pendente</span><strong><?php echo number_format($pendente_mes, 2); ?> <?php echo esc_html(sige_moeda()); ?></strong><small><?php echo $n_lanc_pend; ?> por regularizar</small></div>
          </div>
        <?php else: ?>
          <div class="sg-finreport-empty">Sem lançamentos para calcular a taxa de cobrança deste mês.</div>
        <?php endif; ?>
      </article>

      <article class="sg-finreport-card">
        <div class="sg-finreport-card-head">
          <div>
            <span class="sg-finreport-section-icon sg-finreport-soft-amber"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('money') : ''; ?></span>
            <div>
              <h2>Totais por método</h2>
              <p>Resumo dos canais utilizados no período.</p>
            </div>
          </div>
        </div>
        <?php if (empty($por_metodo)): ?>
          <div class="sg-finreport-empty">Nenhum pagamento registado neste período.</div>
        <?php else: ?>
          <div class="sg-finreport-method-cards">
            <?php foreach ($por_metodo as $m => $v): ?>
              <div><span><?php echo esc_html(function_exists('sige_fin_metodo_pagamento_label') ? sige_fin_metodo_pagamento_label($m) : (function_exists('sige_fin_metodo_pagamento_label') ? sige_fin_metodo_pagamento_label($m) : (function_exists('sige_fin_metodo_pagamento_label') ? sige_fin_metodo_pagamento_label($m) : ucfirst($m)))); ?></span><strong><?php echo number_format((float)$v, 2); ?> <?php echo esc_html(sige_moeda()); ?></strong></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </article>
    </aside>
  </section>

<?php
$tDesp = $wpdb->prefix . 'sige_fin_despesas';
if ($wpdb->get_var("SHOW TABLES LIKE '$tDesp'") === $tDesp && !empty($mes)):
  $despesas_mes = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $tDesp WHERE escola_id = %d AND data_despesa LIKE %s AND LOWER(status) != 'anulado' {$_centro_sql_p} ORDER BY data_despesa ASC",
    $escola_id, $mes . '%'
  ));
  $total_despesas = 0;
  foreach ($despesas_mes as $dm) $total_despesas += (float)$dm->valor;
  $total_receitas_mes = 0;
  foreach ($wpdb->get_results($wpdb->prepare(
    "SELECT SUM(valor_pago) as t FROM {$wpdb->prefix}sige_fin_pagamentos WHERE escola_id = %d AND data_pagamento >= %s AND data_pagamento <= %s {$_centro_sql_p}",
    $escola_id, $inicio . ' 00:00:00', $fim . ' 23:59:59'
  )) as $rm) $total_receitas_mes += (float)($rm->t ?? 0);
  $balanco = $total_receitas_mes - $total_despesas;
?>
  <section class="sg-finreport-grid sg-finreport-grid-balance">
    <article class="sg-finreport-card">
      <div class="sg-finreport-card-head">
        <div>
          <span class="sg-finreport-section-icon sg-finreport-soft-coral"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('activity') : ''; ?></span>
          <div>
            <h2>Despesas do mês</h2>
            <p>Saídas financeiras registadas no mês seleccionado.</p>
          </div>
        </div>
      </div>
      <?php if (empty($despesas_mes)): ?>
        <div class="sg-finreport-empty">Nenhuma despesa registada neste mês.</div>
      <?php else: ?>
        <div class="sg-finreport-scroll-table">
          <table class="sg-finreport-table sg-finreport-table-compact">
            <thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th>Fornecedor</th><th class="sige-u-tar">Valor</th></tr></thead>
            <tbody>
              <?php foreach ($despesas_mes as $dm): ?>
                <tr>
                  <td><?php echo esc_html($dm->data_despesa); ?></td>
                  <td><strong><?php echo esc_html($dm->descricao); ?></strong></td>
                  <td><?php echo esc_html(ucfirst(str_replace('_',' ',$dm->categoria))); ?></td>
                  <td><?php echo esc_html($dm->fornecedor ?: '-'); ?></td>
                  <td class="is-money is-danger"><?php echo number_format((float)$dm->valor, 2); ?> <?php echo esc_html(sige_moeda()); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="4">Total de despesas</td><td><?php echo number_format($total_despesas, 2); ?> <?php echo esc_html(sige_moeda()); ?></td></tr></tfoot>
          </table>
        </div>
      <?php endif; ?>
    </article>

    <article class="sg-finreport-card">
      <div class="sg-finreport-card-head">
        <div>
          <span class="sg-finreport-section-icon sg-finreport-soft-blue"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('chart') : ''; ?></span>
          <div>
            <h2>Balanço do mês</h2>
            <p>Comparação directa entre receitas e despesas.</p>
          </div>
        </div>
      </div>
      <div class="sg-finreport-balance-grid">
        <div class="is-green"><span>Receitas</span><strong><?php echo number_format($total_receitas_mes, 2); ?> <?php echo esc_html(sige_moeda()); ?></strong></div>
        <div class="is-red"><span>Despesas</span><strong><?php echo number_format($total_despesas, 2); ?> <?php echo esc_html(sige_moeda()); ?></strong></div>
        <div class="<?php echo $balanco >= 0 ? 'is-purple' : 'is-red'; ?>"><span>Balanço</span><strong><?php echo number_format($balanco, 2); ?> <?php echo esc_html(sige_moeda()); ?></strong></div>
      </div>
    </article>
  </section>
<?php endif; ?>

</div>

<?php
// ── Dados PHP para exportação Excel ────────────────────────────────────────
$dias_export = [];
foreach (sige_fin_month_days($inicio, $fim) as $dia_exp) {
 $metodos_exp = $por_dia[$dia_exp] ?? [];
 $liq_exp = 0.0; $bru_exp = 0.0; $est_exp = 0.0;
 foreach ($metodos_exp as $m_exp => $v_exp) {
 $liq_exp += (float)$v_exp;
 if ($v_exp > 0 && $m_exp !== 'estorno') $bru_exp += (float)$v_exp;
 if ($v_exp < 0 || $m_exp === 'estorno') $est_exp += abs((float)$v_exp);
 }
 $dias_export[] = [
 'dia' => wp_date('d/m/Y', strtotime($dia_exp)),
 'status' => isset($fechos[$dia_exp]) ? 'Fechado' : 'Aberto',
 'liquido' => round($liq_exp, 2),
 'bruto' => round($bru_exp, 2),
 'estornos' => round($est_exp, 2),
 'metodos' => array_map('floatval', $metodos_exp),
 ];
}

// Despesas para export (já calculadas acima se o bloco existir)
$desp_export = [];
if (!empty($despesas_mes)) {
 foreach ($despesas_mes as $de) {
 $desp_export[] = [
 'data' => esc_js($de->data_despesa),
 'descricao' => esc_js($de->descricao),
 'categoria' => esc_js(ucfirst(str_replace('_', ' ', $de->categoria))),
 'fornecedor'=> esc_js($de->fornecedor ?: '-'),
 'metodo' => esc_js($de->metodo_pagamento ?? ''),
 'valor' => round((float)$de->valor, 2),
 ];
 }
}

$export_meta = [
 'mes' => esc_js($mes),
 'periodo' => esc_js(wp_date('d/m/Y', strtotime($inicio)) . ' a ' . wp_date('d/m/Y', strtotime($fim))),
 'totalBruto' => round($total_bruto, 2),
 'totalEstornos' => round($total_estornos, 2),
 'totalLiquido' => round($total_liquido, 2),
 'emitidoMes' => round($emitido_mes, 2),
 'cobradoMes' => round($cobrado_mes, 2),
 'pendenteMes' => round($pendente_mes, 2),
 'taxaCobranca' => $taxa_cobranca,
 'nLancTotal' => $n_lanc_total,
 'nLancPagos' => $n_lanc_pagos,
 'nLancPend' => $n_lanc_pend,
 'totalDespesas' => round(isset($total_despesas) ? $total_despesas : 0, 2),
 'totalReceitas' => round(isset($total_receitas_mes) ? $total_receitas_mes : $total_bruto, 2),
 'balanco' => round(isset($balanco) ? $balanco : $total_bruto - (isset($total_despesas) ? $total_despesas : 0), 2),
 'porMetodo' => $por_metodo,
];
?>

<script <?php echo sige_csp_script_attr(); ?>>
var sigeExportDias = <?php echo json_encode($dias_export); ?>;
var sigeExportDesp = <?php echo json_encode($desp_export); ?>;
var sigeExportMeta = <?php echo json_encode($export_meta); ?>;

async function exportarMensalExcel() {
 const wb = new ExcelJS.Workbook();
 wb.creator = 'SIGE SoftGenial';
 wb.created = new Date();

 // ── Paleta ────────────────────────────────────────────────────────────
 const C = {
 navy: '1E3A5F',
 navyLight: 'EEF2F9',
 gold: 'B8860B',
 green: '166534',
 greenBg: 'DCFCE7',
 red: '991B1B',
 redBg: 'FEE2E2',
 gray: '64748B',
 grayBg: 'F8FAFC',
 white: 'FFFFFF',
 border: 'CBD5E1',
 closed: 'FEF3C7',
 open: 'F0FDF4',
 };

 const fmtMT = '#,##0.00 "MT"';

 function hdr(txt, size, color) {
 return { value: txt, font: { bold: true, size: size||11, color: {argb:'FF'+color} } };
 }

 function cellStyle(fill, fontColor, bold, numFmt) {
 return {
 fill: { type:'pattern', pattern:'solid', fgColor:{argb:'FF'+fill} },
 font: { bold: !!bold, color:{argb:'FF'+(fontColor||'000000')}, size:10 },
 alignment: { vertical:'middle', horizontal:'center', wrapText:true },
 border: {
 top: {style:'thin', color:{argb:'FF'+C.border}},
 bottom: {style:'thin', color:{argb:'FF'+C.border}},
 left: {style:'thin', color:{argb:'FF'+C.border}},
 right: {style:'thin', color:{argb:'FF'+C.border}},
 },
 numFmt: numFmt || null,
 };
 }

 function applyCell(cell, val, styleDef) {
 cell.value = val;
 if (styleDef.fill) cell.fill = styleDef.fill;
 if (styleDef.font) cell.font = styleDef.font;
 if (styleDef.alignment) cell.alignment = styleDef.alignment;
 if (styleDef.border) cell.border = styleDef.border;
 if (styleDef.numFmt) cell.numFmt = styleDef.numFmt;
 }

 function mergeFill(ws, range, val, fillColor, fontColor, size, bold) {
 ws.mergeCells(range);
 const cell = ws.getCell(range.split(':')[0]);
 cell.value = val;
 cell.font = { bold:!!bold, size:size||11, color:{argb:'FF'+(fontColor||C.white)} };
 cell.fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF'+fillColor} };
 cell.alignment = { vertical:'middle', horizontal:'center', wrapText:true };
 }

 // ════════════════════════════════════════════════════════════════════
 // FOLHA 1 - Receitas por Dia
 // ════════════════════════════════════════════════════════════════════
 const ws1 = wb.addWorksheet('Receitas por Dia', {
 views: [{ showGridLines: false }],
 pageSetup: { paperSize: 9, orientation: 'landscape', fitToPage: true, fitToWidth: 1 }
 });

 ws1.columns = [
 { width: 14 }, // Dia
 { width: 12 }, // Status
 { width: 18 }, // Líquido
 { width: 18 }, // Bruto
 { width: 16 }, // Estornos
 { width: 40 }, // Detalhe por método
 ];

 // Título
 mergeFill(ws1, 'A1:F1', 'RELATÓRIO MENSAL CONSOLIDADO - ' + sigeExportMeta.mes, C.navy, C.white, 14, true);
 ws1.getRow(1).height = 36;

 // Subtítulo
 mergeFill(ws1, 'A2:F2', 'Período: ' + sigeExportMeta.periodo, C.navyLight, C.navy, 10, false);
 ws1.getRow(2).height = 20;

 // KPI bar
 ws1.getRow(3).height = 28;
 const kpiStyle = cellStyle(C.navyLight, C.navy, true, fmtMT);
 const kpiLabels = [
 ['A3:B3', 'Líquido Total: ' + sigeExportMeta.totalLiquido.toFixed(2) + ' <?php echo esc_js(sige_moeda()); ?>'],
 ['C3:D3', 'Bruto Total: ' + sigeExportMeta.totalBruto.toFixed(2) + ' <?php echo esc_js(sige_moeda()); ?>'],
 ['E3:F3', 'Estornos: ' + sigeExportMeta.totalEstornos.toFixed(2) + ' <?php echo esc_js(sige_moeda()); ?>'],
 ];
 kpiLabels.forEach(([range, label]) => {
 ws1.mergeCells(range);
 const c = ws1.getCell(range.split(':')[0]);
 c.value = label;
 c.font = { bold:true, size:10, color:{argb:'FF'+C.navy} };
 c.fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF'+C.navyLight} };
 c.alignment = { vertical:'middle', horizontal:'center' };
 c.border = { top:{style:'thin',color:{argb:'FF'+C.border}}, bottom:{style:'medium',color:{argb:'FF'+C.navy}}, left:{style:'thin',color:{argb:'FF'+C.border}}, right:{style:'thin',color:{argb:'FF'+C.border}} };
 });

 // Linha vazia
 ws1.addRow([]);

 // Cabeçalhos da tabela (linha 5)
 const hdrs1 = ['Dia', 'Estado', 'Líquido (<?php echo esc_html(sige_moeda()); ?>)', 'Bruto (<?php echo esc_html(sige_moeda()); ?>)', 'Estornos (<?php echo esc_html(sige_moeda()); ?>)', 'Detalhe por Método'];
 const hdrRow = ws1.addRow(hdrs1);
 hdrRow.height = 24;
 hdrRow.eachCell(cell => {
 cell.fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF'+C.navy} };
 cell.font = { bold:true, color:{argb:'FF'+C.white}, size:10 };
 cell.alignment = { vertical:'middle', horizontal:'center', wrapText:true };
 cell.border = { top:{style:'thin',color:{argb:'FF'+C.white}}, bottom:{style:'thin',color:{argb:'FF'+C.white}}, left:{style:'thin',color:{argb:'FF'+C.white}}, right:{style:'thin',color:{argb:'FF'+C.white}} };
 });

 // Linhas de dados
 let totLiq = 0, totBru = 0, totEst = 0;
 sigeExportDias.forEach((d, i) => {
 const isClosed = d.status === 'Fechado';
 const rowBg = isClosed ? C.closed : C.open;
 const metStr = Object.entries(d.metodos).filter(([,v])=>v>0).map(([m,v])=> m.toUpperCase()+': '+parseFloat(v).toFixed(2)+' <?php echo esc_js(sige_moeda()); ?>').join(' | ') || '-';

 const row = ws1.addRow([d.dia, d.status, d.liquido, d.bruto, d.estornos, metStr]);
 row.height = 20;

 const baseStyle = {
 fill: { type:'pattern', pattern:'solid', fgColor:{argb:'FF'+rowBg} },
 alignment: { vertical:'middle', horizontal:'center', wrapText:true },
 border: { top:{style:'thin',color:{argb:'FF'+C.border}}, bottom:{style:'thin',color:{argb:'FF'+C.border}}, left:{style:'thin',color:{argb:'FF'+C.border}}, right:{style:'thin',color:{argb:'FF'+C.border}} },
 };

 row.eachCell((cell, colNum) => {
 cell.fill = baseStyle.fill;
 cell.alignment = baseStyle.alignment;
 cell.border = baseStyle.border;
 cell.font = { size:10, color:{argb:'FF334155'} };
 if (colNum >= 3 && colNum <= 5) {
 cell.numFmt = fmtMT;
 cell.alignment = { vertical:'middle', horizontal:'right' };
 cell.font = { bold: colNum===3, size:10, color:{argb: colNum===5 && d.estornos>0 ? 'FF'+C.red : 'FF'+C.navy} };
 }
 if (colNum === 1) cell.font = { bold:true, size:10 };
 if (colNum === 2) {
 cell.font = { bold:true, size:9, color:{argb: isClosed ? 'FF'+C.gold : 'FF'+C.green} };
 }
 if (colNum === 6) {
 cell.alignment = { vertical:'middle', horizontal:'left', wrapText:true };
 cell.font = { size:9, color:{argb:'FF'+C.gray} };
 }
 });

 totLiq += d.liquido; totBru += d.bruto; totEst += d.estornos;
 });

 // Linha de TOTAL
 const totRow = ws1.addRow(['TOTAL', '', totLiq, totBru, totEst, '']);
 totRow.height = 26;
 totRow.eachCell((cell, colNum) => {
 cell.fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF'+C.navy} };
 cell.font = { bold:true, size:10, color:{argb:'FF'+C.white} };
 cell.border = { top:{style:'medium',color:{argb:'FF'+C.white}}, bottom:{style:'medium',color:{argb:'FF'+C.white}}, left:{style:'thin',color:{argb:'FF'+C.white}}, right:{style:'thin',color:{argb:'FF'+C.white}} };
 cell.alignment = { vertical:'middle', horizontal: colNum >= 3 && colNum <= 5 ? 'right' : 'center' };
 if (colNum >= 3 && colNum <= 5) cell.numFmt = fmtMT;
 });
 ws1.mergeCells('A' + totRow.number + ':B' + totRow.number);

 // ════════════════════════════════════════════════════════════════════
 // FOLHA 2 - Despesas do Mês
 // ════════════════════════════════════════════════════════════════════
 const ws2 = wb.addWorksheet(' Despesas', { views:[{showGridLines:false}] });

 ws2.columns = [
 { width: 14 }, // Data
 { width: 38 }, // Descrição
 { width: 20 }, // Categoria
 { width: 24 }, // Fornecedor
 { width: 20 }, // Método
 { width: 18 }, // Valor
 ];

 mergeFill(ws2, 'A1:F1', ' DESPESAS DO MÊS - ' + sigeExportMeta.mes, 'C0392B', C.white, 14, true);
 ws2.getRow(1).height = 36;
 mergeFill(ws2, 'A2:F2', 'Período: ' + sigeExportMeta.periodo, 'FEE2E2', C.red, 10, false);
 ws2.getRow(2).height = 20;
 ws2.addRow([]);

 const hdrs2 = ['Data', 'Descrição', 'Categoria', 'Fornecedor', 'Método Pag.', 'Valor (<?php echo esc_html(sige_moeda()); ?>)'];
 const hdrRow2 = ws2.addRow(hdrs2);
 hdrRow2.height = 24;
 hdrRow2.eachCell(cell => {
 cell.fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FFC0392B'} };
 cell.font = { bold:true, color:{argb:'FF'+C.white}, size:10 };
 cell.alignment = { vertical:'middle', horizontal:'center', wrapText:true };
 cell.border = { top:{style:'thin',color:{argb:'FF'+C.white}}, bottom:{style:'thin',color:{argb:'FF'+C.white}}, left:{style:'thin',color:{argb:'FF'+C.white}}, right:{style:'thin',color:{argb:'FF'+C.white}} };
 });

 let totDesp = 0;
 if (sigeExportDesp.length === 0) {
 const emptyRow = ws2.addRow(['Nenhuma despesa registada neste mês.','','','','','']);
 ws2.mergeCells('A' + emptyRow.number + ':F' + emptyRow.number);
 emptyRow.getCell(1).alignment = { horizontal:'center', vertical:'middle' };
 emptyRow.getCell(1).font = { italic:true, color:{argb:'FF'+C.gray} };
 } else {
 sigeExportDesp.forEach((d, i) => {
 const bg = i % 2 === 0 ? 'FFFFFF' : 'FEF2F2';
 const row = ws2.addRow([d.data, d.descricao, d.categoria, d.fornecedor, d.metodo, d.valor]);
 row.height = 20;
 row.eachCell((cell, colNum) => {
 cell.fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF'+bg} };
 cell.alignment = { vertical:'middle', horizontal: colNum===6 ? 'right' : 'left', wrapText:true };
 cell.border = { top:{style:'thin',color:{argb:'FF'+C.border}}, bottom:{style:'thin',color:{argb:'FF'+C.border}}, left:{style:'thin',color:{argb:'FF'+C.border}}, right:{style:'thin',color:{argb:'FF'+C.border}} };
 cell.font = { size:10, bold: colNum===2, color:{argb: colNum===6 ? 'FF'+C.red : 'FF334155'} };
 if (colNum === 6) cell.numFmt = fmtMT;
 });
 totDesp += d.valor;
 });

 const totRow2 = ws2.addRow(['TOTAL DESPESAS', '', '', '', '', totDesp]);
 totRow2.height = 26;
 ws2.mergeCells('A' + totRow2.number + ':E' + totRow2.number);
 totRow2.eachCell((cell, colNum) => {
 cell.fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FFC0392B'} };
 cell.font = { bold:true, size:10, color:{argb:'FF'+C.white} };
 cell.alignment = { vertical:'middle', horizontal: colNum===6 ? 'right' : 'center' };
 cell.border = { top:{style:'medium',color:{argb:'FF'+C.white}}, bottom:{style:'medium',color:{argb:'FF'+C.white}}, left:{style:'thin',color:{argb:'FF'+C.white}}, right:{style:'thin',color:{argb:'FF'+C.white}} };
 if (colNum === 6) cell.numFmt = fmtMT;
 });
 }

 // ════════════════════════════════════════════════════════════════════
 // FOLHA 3 - Balanço
 // ════════════════════════════════════════════════════════════════════
 const ws3 = wb.addWorksheet(' Balanço', { views:[{showGridLines:false}] });

 ws3.columns = [{ width:30 }, { width:28 }, { width:20 }];

 mergeFill(ws3, 'A1:C1', ' BALANÇO - ' + sigeExportMeta.mes, C.navy, C.white, 14, true);
 ws3.getRow(1).height = 36;
 mergeFill(ws3, 'A2:C2', 'Período: ' + sigeExportMeta.periodo, C.navyLight, C.navy, 10, false);
 ws3.getRow(2).height = 20;
 ws3.addRow([]);

 // Cabeçalho
 const hdrRow3 = ws3.addRow(['Indicador', 'Descrição', 'Valor (<?php echo esc_html(sige_moeda()); ?>)']);
 hdrRow3.height = 24;
 hdrRow3.eachCell(cell => {
 cell.fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF'+C.navy} };
 cell.font = { bold:true, color:{argb:'FF'+C.white}, size:10 };
 cell.alignment = { vertical:'middle', horizontal:'center' };
 cell.border = { top:{style:'thin',color:{argb:'FF'+C.white}}, bottom:{style:'thin',color:{argb:'FF'+C.white}}, left:{style:'thin',color:{argb:'FF'+C.white}}, right:{style:'thin',color:{argb:'FF'+C.white}} };
 });

 const balanco = sigeExportMeta.totalReceitas - totDesp;
 const balancoPos = balanco >= 0;
 const taxaColor = sigeExportMeta.taxaCobranca >= 80 ? C.green : (sigeExportMeta.taxaCobranca >= 50 ? C.gold : C.red);
 const taxaBg = sigeExportMeta.taxaCobranca >= 80 ? C.greenBg : (sigeExportMeta.taxaCobranca >= 50 ? 'FEFCE8' : C.redBg);

 const balRows = [
 ['Receitas Brutas', 'Total cobrado no mês (antes de estornos)', sigeExportMeta.totalBruto, C.green, C.greenBg],
 ['← Estornos', 'Pagamentos estornados/anulados', sigeExportMeta.totalEstornos, C.red, C.redBg],
 ['Receitas Líquidas', 'Receitas brutas menos estornos', sigeExportMeta.totalLiquido, C.navy, C.navyLight],
 [' Despesas', 'Total de saídas financeiras do mês', totDesp, C.red, C.redBg],
 [balancoPos ? 'Balanço Positivo' : 'Balanço Negativo',
 'Receitas líquidas menos despesas', balanco, balancoPos ? C.green : C.red, balancoPos ? C.greenBg : C.redBg],
 ];

 balRows.forEach(([label, desc, val, fontC, bgC]) => {
 const row = ws3.addRow([label, desc, val]);
 row.height = 28;
 row.eachCell((cell, colNum) => {
 cell.fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF'+bgC} };
 cell.alignment = { vertical:'middle', horizontal: colNum===3 ? 'right' : 'left', wrapText:true };
 cell.border = { top:{style:'thin',color:{argb:'FF'+C.border}}, bottom:{style:'thin',color:{argb:'FF'+C.border}}, left:{style:'thin',color:{argb:'FF'+C.border}}, right:{style:'thin',color:{argb:'FF'+C.border}} };
 cell.font = { bold: colNum!==2, size:10, color:{argb:'FF'+fontC} };
 if (colNum===3) { cell.numFmt = fmtMT; }
 });
 });

 // ── Taxa de Cobrança - bloco dedicado no Excel ──────────────────────
 if (sigeExportMeta.nLancTotal > 0) {
 ws3.addRow([]);
 mergeFill(ws3, 'A'+(ws3.lastRow.number)+':C'+(ws3.lastRow.number), 'TAXA DE COBRANÇA - ' + sigeExportMeta.mes, C.navy, C.white, 10, true);
 ws3.lastRow.height = 22;

 const taxaRows = [
 ['Emitido', sigeExportMeta.nLancTotal + ' lançamentos', sigeExportMeta.emitidoMes, C.navy, C.navyLight],
 ['Cobrado', sigeExportMeta.nLancPagos + ' pagos', sigeExportMeta.cobradoMes, C.green, C.greenBg],
 ['⏳ Pendente', sigeExportMeta.nLancPend + ' por regularizar',sigeExportMeta.pendenteMes, C.red, C.redBg],
 ];
 taxaRows.forEach(([label, desc, val, fontC, bgC]) => {
 const row = ws3.addRow([label, desc, val]);
 row.height = 24;
 row.eachCell((cell, colNum) => {
 cell.fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF'+bgC} };
 cell.alignment = { vertical:'middle', horizontal: colNum===3 ? 'right' : 'left' };
 cell.border = { top:{style:'thin',color:{argb:'FF'+C.border}}, bottom:{style:'thin',color:{argb:'FF'+C.border}}, left:{style:'thin',color:{argb:'FF'+C.border}}, right:{style:'thin',color:{argb:'FF'+C.border}} };
 cell.font = { bold: colNum!==2, size:10, color:{argb:'FF'+fontC} };
 if (colNum===3) cell.numFmt = fmtMT;
 });
 });
 // Linha da taxa percentual - destaque
 const taxaRow = ws3.addRow(['Taxa de Cobrança', 'Cobrado ÷ Emitido × 100', sigeExportMeta.taxaCobranca + '%']);
 taxaRow.height = 30;
 taxaRow.eachCell((cell, colNum) => {
 cell.fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF'+taxaBg} };
 cell.font = { bold:true, size:13, color:{argb:'FF'+taxaColor} };
 cell.alignment = { vertical:'middle', horizontal: colNum===3 ? 'right' : 'left' };
 cell.border = { top:{style:'medium',color:{argb:'FF'+taxaColor}}, bottom:{style:'medium',color:{argb:'FF'+taxaColor}}, left:{style:'thin',color:{argb:'FF'+C.border}}, right:{style:'thin',color:{argb:'FF'+C.border}} };
 });
 }

 // Totais por método - bloco separado
 ws3.addRow([]);
 mergeFill(ws3, 'A' + (ws3.lastRow.number) + ':C' + (ws3.lastRow.number), 'RECEITAS POR MÉTODO DE PAGAMENTO', C.navy, C.white, 10, true);
 ws3.lastRow.height = 22;

 const metodoLabel = (met) => ({
   numerario:'Numerário', transferencia:'Transferência Bancária', mpesa:'M-Pesa', emola:'E-Mola',
   emola_comerciante:'E-Mola Comerciante', bim:'Millennium BIM', bci:'BCI', nib:'Transferência (NIB)',
   pagafacil:'Paga Fácil', pos_bci:'POS BCI', pos_bim:'POS BIM', pos_stbank:'POS STBANK',
   pos_moza:'POS MOZA', pos_nedbank:'POS NEDBANK', pos_fnb:'POS FNB', pos:'POS',
   banco:'Banco', cheque:'Cheque', cartao:'Cartão', estorno:'Estorno'
 }[met] || String(met||'').replace(/[_-]/g,' ').replace(/\b\w/g, c => c.toUpperCase()));
 Object.entries(sigeExportMeta.porMetodo).forEach(([met, val]) => {
 const row = ws3.addRow([metodoLabel(met), '', parseFloat(val)]);
 row.height = 22;
 row.eachCell((cell, colNum) => {
 cell.fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF'+C.grayBg} };
 cell.alignment = { vertical:'middle', horizontal: colNum===3 ? 'right' : 'left' };
 cell.border = { top:{style:'thin',color:{argb:'FF'+C.border}}, bottom:{style:'thin',color:{argb:'FF'+C.border}}, left:{style:'thin',color:{argb:'FF'+C.border}}, right:{style:'thin',color:{argb:'FF'+C.border}} };
 cell.font = { size:10, bold: colNum===1 };
 if (colNum===3) cell.numFmt = fmtMT;
 });
 if (row.getCell(1).value) ws3.mergeCells('A'+row.number+':B'+row.number);
 });

 // ── Download ─────────────────────────────────────────────────────────
 const buffer = await wb.xlsx.writeBuffer();
 const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
 const url = URL.createObjectURL(blob);
 const a = document.createElement('a');
 a.href = url;
 a.download = 'Relatorio_Mensal_' + sigeExportMeta.mes + '.xlsx';
 a.click();
 URL.revokeObjectURL(url);
}
</script>
