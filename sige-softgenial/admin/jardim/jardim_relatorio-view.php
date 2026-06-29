<?php
/**
 * Módulo Jardim de Infância - Diários & Análises (v2)
 * SIGE SoftGenial | SNE Moçambique
 *
 * v2.1 - Abril 2026
 * - 11× date() → wp_date()
 * - function_exists fallback → $escola_id
 * - Hero + toolbar com CSS classes + fadeInUp
 */
if (!defined('ABSPATH')) exit;
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['jardim.relatorio_ver','jardim.relatorio_emitir'],
    ['sige_assistente','sige_director','sige_educador','sige_secretario','sige_secretaria_geral']
)) return;
global $wpdb;
$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
$ano = sige_ano_lectivo_atual();
$sub = sanitize_text_field($_GET['sub'] ?? 'turma'); // turma | aluno
// ── PERÍODO: dia | mes | acumulado ──────────────────────────────────────────
// dia      → escolhe data específica
// mes      → escolhe mês específico
// acumulado→ desde o início do ano lectivo
$periodo   = sanitize_text_field($_GET['periodo'] ?? 'mes');
$dia_sel   = sanitize_text_field($_GET['dia_sel']  ?? wp_date('Y-m-d'));
$mes_sel   = sanitize_text_field($_GET['mes_sel']  ?? wp_date('Y-m')); // formato YYYY-MM
$hoje      = wp_date('Y-m-d');
// Calcular datas do período
switch ($periodo) {
    case 'dia':
        $data_ini   = $dia_sel;
        $data_fim   = $dia_sel;
        $label_p    = wp_date('d/m/Y', strtotime($dia_sel));
        break;
    case 'acumulado':
        $data_ini   = $ano.'-01-01';
        $data_fim   = $hoje;
        $label_p    = 'Acumulado '.$ano;
        break;
    default: // mes
        $data_ini   = $mes_sel.'-01';
        $data_fim   = wp_date('Y-m-t', strtotime($data_ini));
        $label_p    = wp_date('F Y', strtotime($data_ini));
}
// Garantir que data_fim não ultrapassa hoje
if ($data_fim > $hoje) $data_fim = $hoje;
// ── Turmas pré-escolar - fonte central v12.10.104 ──────────────────────
$turmas = function_exists('sige_jardim_get_preescolar_turmas_v104')
    ? sige_jardim_get_preescolar_turmas_v104((int)$escola_id, (int)$ano, true)
    : [];
$turma_sel = (int)($_GET['turma_id'] ?? ($turmas[0]->id ?? 0));
$aluno_sel = (int)($_GET['aluno_id'] ?? 0);
// ── Alunos da turma - fonte central v12.10.104 ─────────────────────────────────────────
$alunos = [];
if ($turma_sel && function_exists('sige_jardim_get_alunos_activos_turma_v104')) {
    $alunos = sige_jardim_get_alunos_activos_turma_v104((int)$turma_sel, (int)$escola_id, (int)$ano, "a.id, a.nome_completo, a.foto, a.genero");
}
// Se nenhum aluno seleccionado, usar o primeiro
if (!$aluno_sel && $alunos) $aluno_sel = (int)$alunos[0]->id;
// ── Encontrar objecto do aluno (fix: cast para int na comparação) ────────────
$aluno_obj = null;
foreach ($alunos as $a) {
    if ((int)$a->id === $aluno_sel) { $aluno_obj = $a; break; }
}
// ── Registos do período ──────────────────────────────────────────────────────
$t_d = $wpdb->prefix.'sige_jardim_diario';
$registos_turma = []; // [aluno_id][data] = row
if ($alunos) {
    $al_ids = implode(',', array_map('intval', array_column($alunos,'id')));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $t_d
         WHERE escola_id = %d
           AND turma_id = %d
           AND aluno_id IN ($al_ids)
           AND data_registo BETWEEN %s AND %s
           AND ano_lectivo = %d
         ORDER BY data_registo ASC",
        $escola_id, $turma_sel, $data_ini, $data_fim, $ano));
    foreach ($rows as $r) $registos_turma[(int)$r->aluno_id][$r->data_registo] = $r;
}
// ── Presenças no período ─────────────────────────────────────────────────────
$presencas_turma = [];
if ($alunos && function_exists('sige_jardim_presencas_table_v81')) {
    if (function_exists('sige_jardim_ensure_presencas_table_v81')) sige_jardim_ensure_presencas_table_v81();
    $t_p = sige_jardim_presencas_table_v81();
    $al_ids_p = implode(',', array_map('intval', array_column($alunos,'id')));
    $rows_p = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t_p WHERE escola_id=%d AND turma_id=%d AND aluno_id IN ($al_ids_p) AND data_registo BETWEEN %s AND %s AND ano_lectivo=%d ORDER BY data_registo ASC", $escola_id, $turma_sel, $data_ini, $data_fim, $ano));
    foreach ((array)$rows_p as $rp) $presencas_turma[(int)$rp->aluno_id][$rp->data_registo] = $rp;
}
$presenca_label = ['presente'=>'Presente','falta'=>'Falta','falta_justificada'=>'Falta justificada','atraso'=>'Atraso'];

// ── Dias úteis no período ────────────────────────────────────────────────────
$dias_uteis = [];
if ($periodo !== 'dia') {
    $d = new DateTime($data_ini);
    $fim_dt = new DateTime($data_fim);
    while ($d <= $fim_dt) {
        if ((int)$d->format('N') <= 5) $dias_uteis[] = $d->format('Y-m-d');
        $d->modify('+1 day');
    }
} else {
    $dias_uteis = [(int)wp_date('N', strtotime($dia_sel)) <= 5 ? $dia_sel : null];
    $dias_uteis = array_filter($dias_uteis);
}
$total_dias = count($dias_uteis);
// ── Mapeamentos ─────────────────────────────────────────────────────────────
$humor_icon  = ['feliz'=>'😄','calmo'=>'😌','triste'=>'😢','agitado'=>'😤','cansado'=>'😴',''=>'·'];
$humor_cor   = ['feliz'=>'var(--color-success-500)','calmo'=>'var(--color-info-500)','triste'=>'var(--color-danger-600)','agitado'=>'var(--color-warning-700)','cansado'=>'var(--color-brand-500)',''=>'var(--color-ink-100)'];
$humor_label = ['feliz'=>'Feliz','calmo'=>'Calmo/a','triste'=>'Triste','agitado'=>'Agitado/a','cansado'=>'Cansado/a'];
$comp_cor    = ['excelente'=>'var(--color-success-500)','bom'=>'var(--color-info-500)','satisfatorio'=>'var(--color-warning-700)','necessita_atencao'=>'var(--color-danger-600)',''=>'var(--color-ink-100)'];
$comp_label  = ['excelente'=>'Excelente','bom'=>'Bom','satisfatorio'=>'Satisfatório','necessita_atencao'=>'Necessita Atenção'];
// ── Resumo por aluno ─────────────────────────────────────────────────────────
$resumo = [];
foreach ($alunos as $al) {
    $aid  = (int)$al->id;
    $regs = $registos_turma[$aid] ?? [];
    $n_reg = count($regs);
    $humor_freq = []; $comp_freq = []; $sono_sim = 0; $sono_nao = 0; $min_sono = 0; $presente = 0; $falta = 0; $falta_justificada = 0; $atraso = 0;
    foreach ($regs as $r) {
        if ($r->humor)         $humor_freq[$r->humor]         = ($humor_freq[$r->humor] ?? 0) + 1;
        if ($r->comportamento) $comp_freq[$r->comportamento]  = ($comp_freq[$r->comportamento] ?? 0) + 1;
        $dorm = (string)$r->dormiu;
        if ($dorm === '1') { $sono_sim++; $min_sono += (int)$r->minutos_sono; }
        elseif ($dorm === '0') $sono_nao++;
    }
    foreach (($presencas_turma[$aid] ?? []) as $pr) { $st=(string)$pr->status; if ($st==='presente') $presente++; elseif ($st==='falta') $falta++; elseif ($st==='falta_justificada') $falta_justificada++; elseif ($st==='atraso') $atraso++; }
    arsort($humor_freq); arsort($comp_freq);
    $resumo[$aid] = [
        'registados'   => $n_reg,
        'pct'          => $total_dias > 0 ? round(($n_reg / $total_dias) * 100) : 0,
        'humor_top'    => array_key_first($humor_freq) ?? '',
        'humor_freq'   => $humor_freq,
        'comp_top'     => array_key_first($comp_freq) ?? '',
        'comp_freq'    => $comp_freq,
        'sono_sim'     => $sono_sim,
        'sono_nao'     => $sono_nao,
        'min_sono_avg' => $sono_sim > 0 ? round($min_sono / $sono_sim) : 0,
        'presente' => $presente,
        'falta' => $falta,
        'falta_justificada' => $falta_justificada,
        'atraso' => $atraso,
    ];
}
// ── Calendário (sempre mês seleccionado para vista aluno) ────────────────────
$cal_mes  = ($periodo === 'mes') ? $mes_sel : wp_date('Y-m');
$cal_ini  = $cal_mes.'-01';
$cal_fim  = wp_date('Y-m-t', strtotime($cal_ini));
$regs_cal = [];
if ($aluno_sel) {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $t_d WHERE aluno_id=%d AND data_registo BETWEEN %s AND %s AND ano_lectivo=%d
         ORDER BY data_registo ASC", $aluno_sel, $cal_ini, $cal_fim, $ano));
    foreach ($rows as $r) $regs_cal[$r->data_registo] = $r;
}
// ── Recados para os pais ─────────────────────────────────────────────────────
$recados = [];
if ($aluno_sel) {
    $recados = $wpdb->get_results($wpdb->prepare(
        "SELECT data_registo, recado_pais FROM $t_d
         WHERE aluno_id=%d AND recado_pais IS NOT NULL AND recado_pais != '' AND ano_lectivo=%d
         ORDER BY data_registo DESC LIMIT 30", $aluno_sel, $ano));
}
// ── Dados para gráficos ──────────────────────────────────────────────────────
$comp_semanal = []; $sono_semanal = [];
foreach ($alunos as $al) {
    $aid = (int)$al->id;
    foreach ($registos_turma[$aid] ?? [] as $data => $r) {
        $key = 'S'.ltrim(wp_date('W', strtotime($data)), '0');
        if (!isset($comp_semanal[$key])) $comp_semanal[$key] = ['excelente'=>0,'bom'=>0,'satisfatorio'=>0,'necessita_atencao'=>0];
        if (!isset($sono_semanal[$key])) $sono_semanal[$key] = ['sim'=>0,'nao'=>0];
        if ($r->comportamento && isset($comp_semanal[$key][$r->comportamento])) $comp_semanal[$key][$r->comportamento]++;
        $dorm = (string)$r->dormiu;
        if ($dorm==='1') $sono_semanal[$key]['sim']++;
        elseif ($dorm==='0') $sono_semanal[$key]['nao']++;
    }
}
ksort($comp_semanal); ksort($sono_semanal);
$semanas_all = array_unique(array_merge(array_keys($comp_semanal), array_keys($sono_semanal)));
sort($semanas_all);
$json_semanas   = json_encode(array_values($semanas_all));
$json_excelente = json_encode(array_values(array_map(fn($s)=>$comp_semanal[$s]['excelente']??0, $semanas_all)));
$json_bom       = json_encode(array_values(array_map(fn($s)=>$comp_semanal[$s]['bom']??0, $semanas_all)));
$json_satis     = json_encode(array_values(array_map(fn($s)=>$comp_semanal[$s]['satisfatorio']??0, $semanas_all)));
$json_atencao   = json_encode(array_values(array_map(fn($s)=>$comp_semanal[$s]['necessita_atencao']??0, $semanas_all)));
$json_sono_sim  = json_encode(array_values(array_map(fn($s)=>$sono_semanal[$s]['sim']??0, $semanas_all)));
$json_sono_nao  = json_encode(array_values(array_map(fn($s)=>$sono_semanal[$s]['nao']??0, $semanas_all)));
// ── Dados cumulativos por mês (para vista acumulada do aluno) ────────────────
$cumul_meses = [];
if ($periodo === 'acumulado' && $aluno_sel) {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE_FORMAT(data_registo,'%%Y-%%m') AS mes,
                COUNT(*) AS total,
                SUM(CASE WHEN dormiu=1 THEN 1 ELSE 0 END) AS sono,
                SUM(CASE WHEN humor='feliz' THEN 1 ELSE 0 END) AS feliz,
                SUM(CASE WHEN humor='triste' THEN 1 ELSE 0 END) AS triste,
                SUM(CASE WHEN humor='agitado' THEN 1 ELSE 0 END) AS agitado,
                SUM(CASE WHEN comportamento='excelente' THEN 1 ELSE 0 END) AS excelente,
                SUM(CASE WHEN comportamento='necessita_atencao' THEN 1 ELSE 0 END) AS necessita
         FROM $t_d WHERE aluno_id=%d AND ano_lectivo=%d
         GROUP BY mes ORDER BY mes ASC", $aluno_sel, $ano));
    foreach ($rows as $r) $cumul_meses[$r->mes] = $r;
}
// Meses para labels
$meses_pt = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
             '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];

$jrel_icon = static function (string $name): string {
    if (function_exists('sige_ui_icon')) {
        return sige_ui_icon($name);
    }
    $map = [
        'bar-chart' => '<path d="M3 3v18h18"/><path d="M7 16V9"/><path d="M12 16V5"/><path d="M17 16v-3"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'filter' => '<path d="M22 3H2l8 9.46V19l4 2v-8.54z"/>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/>',
        'smile' => '<circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/>',
        'arrow-left' => '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
        'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
    ];
    $path = $map[$name] ?? $map['bar-chart'];
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};

$jrel_turma_nome = '';
foreach ($turmas as $jt) {
    if ((int)$jt->id === (int)$turma_sel) {
        $jrel_turma_nome = trim(($jt->nome ?: $jt->classe) . (isset($jt->classe) && $jt->classe ? ' · ' . $jt->classe : ''), ' ·');
        break;
    }
}
$jrel_total_alunos = count($alunos);
$jrel_total_registos = 0;
$jrel_alunos_com_registo = 0;
foreach ($alunos as $ja) {
    $jr = $resumo[(int)$ja->id] ?? null;
    if (!$jr) continue;
    $jrel_total_registos += (int)($jr['registados'] ?? 0);
    if ((int)($jr['registados'] ?? 0) > 0) $jrel_alunos_com_registo++;
}
$jrel_cobertura_media = $jrel_total_alunos ? round(array_sum(array_column($resumo, 'pct')) / max(1, $jrel_total_alunos)) : 0;
$jrel_sub_label = ($sub === 'aluno') ? 'Aluno individual' : 'Vista da turma';
$jrel_periodo_label = (string)$label_p;

?>
<style id="sige-jardim-relatorio-produto-pro-v1210102">
/* SIGE SoftGenial v12.10.102 - Jardim Relatório: Compliance Visual Integral
   Escopo visual apenas: não altera queries, cálculos, gráficos, envio aos pais,
   permissões, notas, pagamentos, base de dados ou handlers. */
@keyframes fadeInUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
body.sige-admin-app.sige-view-jardim_relatorio .sg-product-page-head{display:none!important}
body.sige-admin-app.sige-view-jardim_relatorio .sg-app-page{
    max-width:none!important;
    width:100%!important;
    padding-top:0!important;
    overflow-x:hidden!important;
}
body.sige-admin-app.sige-view-jardim_relatorio .sg-app-content{
    padding-left:30px!important;
    padding-right:30px!important;
    overflow-x:hidden!important;
}
body.sige-admin-app.sige-view-jardim_relatorio .sg-app-topbar{max-width:none!important}

.sige-jrel-wrap{
    --jr-blue:var(--sg-theme-primary,var(--color-brand-500));
    --jr-blue-dark:var(--sg-theme-primary-800,var(--color-ink-700));
    --jr-purple:var(--color-brand-500);
    --jr-purple-soft:var(--color-brand-50);
    --jr-ink:var(--color-black);
    --jr-muted:var(--color-slate-700);
    --jr-line:var(--color-ink-100);
    --jr-green:var(--color-success-500);
    --jr-amber:var(--color-warning-500);
    --jr-red:var(--color-danger-500);
    width:100%!important;
    max-width:none!important;
    min-width:0!important;
    margin:0!important;
    padding:0 0 28px!important;
    display:flex!important;
    flex-direction:column!important;
    gap:18px!important;
    color:var(--jr-ink)!important;
    background:transparent!important;
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif)!important;
}
.sige-jrel-wrap *{box-sizing:border-box}
.sige-jrel-wrap svg{width:18px;height:18px;display:block;stroke:currentColor!important;color:currentColor!important;fill:none!important}

/* HERO - padrão Painel Principal */
.sige-jrel-hero{
    position:relative!important;
    overflow:hidden!important;
    min-height:178px!important;
    border-radius:var(--radius-xl)!important;
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-info-50) 100%)!important;
    border:1px solid rgba(92,64,187,.12)!important;
    box-shadow:var(--shadow-lg);
    padding:32px 34px!important;
    margin:0!important;
    color:var(--jr-ink)!important;
    display:grid!important;
    grid-template-columns:minmax(0,1.25fr) minmax(300px,.75fr)!important;
    gap:22px!important;
    align-items:center!important;
    animation:fadeInUp .45s ease-out both!important;
}
.sige-jrel-hero:before{
    content:"";
    position:absolute;
    inset:auto -80px -130px auto;
    width:420px;
    height:300px;
    border-radius:var(--radius-pill);
    background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);
    pointer-events:none;
}
.sige-jrel-hero > *{position:relative;z-index:1}
.sige-jrel-hero-main{min-width:0}
.sige-jrel-hero-kicker{
    display:inline-flex!important;
    align-items:center!important;
    gap:var(--space-2)!important;
    margin:0 0 10px!important;
    padding:0!important;
    border:0!important;
    background:transparent!important;
    color:var(--jr-blue)!important;
    font-size:12px!important;
    line-height:1.2!important;
    font-weight:700!important;
    letter-spacing:.11em!important;
    text-transform:uppercase!important;
}
.sige-jrel-hero h1{
    margin:0!important;
    max-width:760px!important;
    color:var(--color-black)!important;
    font-size:31px!important;
    line-height:1.08!important;
    font-weight:700!important;
    letter-spacing:-.04em!important;
    font-family:inherit!important;
}
.sige-jrel-hero p{
    max-width:780px!important;
    margin:var(--space-3) 0 0!important;
    color:var(--color-slate-700)!important;
    font-size:15px!important;
    line-height:1.65!important;
    font-weight:500!important;
}
.sige-jrel-hero-chips{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
.sige-jrel-hero-chips span{
    display:inline-flex;
    align-items:center;
    gap:var(--space-2);
    min-height:38px;
    padding:var(--space-2) var(--space-3);
    border-radius:var(--radius-pill);
    background:var(--color-white);
    border:1px solid var(--color-slate-100);
    color:var(--color-slate-700);
    font-size:12px;
    font-weight:700;
    box-shadow:var(--shadow-sm);
}
.sige-jrel-hero-chips svg{color:var(--jr-purple)}
.sige-jrel-hero-panel{
    position:relative;
    min-height:148px;
    border-radius:var(--radius-xl);
    background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.18));
    padding:22px;
    overflow:hidden;
    display:flex;
    flex-direction:column;
    justify-content:center;
    gap:10px;
    border:1px solid rgba(92,64,187,.08);
}
.sige-jrel-hero-panel:before{
    content:"";
    position:absolute;
    right:22px;
    bottom:16px;
    width:112px;
    height:92px;
    border-radius:22px 22px 12px 12px;
    background:rgba(109,93,252,.16);
    box-shadow:inset 0 0 0 2px rgba(109,93,252,.12);
}
.sige-jrel-hero-panel>*{position:relative;z-index:1}
.sige-jrel-panel-label{
    display:flex;
    align-items:center;
    gap:var(--space-2);
    color:var(--jr-purple);
    font-size:12px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.11em;
}
.sige-jrel-panel-value{
    display:block;
    color:var(--color-ink-900);
    font-size:36px;
    line-height:1.05;
    font-weight:700;
    letter-spacing:-.045em;
}
.sige-jrel-panel-text{
    display:block;
    max-width:330px;
    color:var(--color-slate-600);
    font-size:var(--fs-sm);
    line-height:1.55;
    font-weight:600;
}
.sige-jrel-hero-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}
.sige-jrel-hero a.btn-ghost,.sige-jrel-btn{
    min-height:44px!important;
    min-width:0!important;
    max-width:100%!important;
    display:inline-flex!important;
    align-items:center!important;
    justify-content:center!important;
    gap:var(--space-2)!important;
    border-radius:var(--radius-md)!important;
    padding:0 var(--space-4)!important;
    border:1px solid var(--color-ink-100)!important;
    background:var(--color-white)!important;
    color:var(--color-ink-900)!important;
    text-decoration:none!important;
    font-size:var(--fs-sm)!important;
    font-weight:700!important;
    cursor:pointer!important;
    font-family:inherit!important;
    box-shadow:var(--shadow-sm);
    transition:transform .18s ease,box-shadow .18s ease!important;
    white-space:normal!important;
    text-align:center!important;
}
.sige-jrel-hero a.btn-ghost:hover,.sige-jrel-btn:hover{transform:translateY(-1px)!important;box-shadow:0 4px 16px rgba(15,23,42,.08)}
.sige-jrel-btn.primary{
    background:linear-gradient(135deg,var(--jr-blue),var(--jr-blue-dark))!important;
    color:var(--color-white)!important;
    border-color:var(--jr-blue)!important;
    box-shadow:var(--shadow-sm);
}

/* KPIs */
.sige-jrel-kpis{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:var(--space-4);
    width:100%;
    min-width:0;
}
.sige-jrel-kpi{
    position:relative;
    overflow:hidden;
    display:grid;
    grid-template-columns:auto minmax(0,1fr);
    align-items:center;
    gap:14px;
    min-height:104px;
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    border-radius:var(--radius-xl);
    padding:18px 20px;
    box-shadow:var(--shadow-md);
}
.sige-jrel-kpi:after{content:"";position:absolute;right:-28px;top:-34px;width:92px;height:92px;border-radius:50%;background:var(--kpi-soft,var(--color-brand-50))}
.sige-jrel-kpi-icon{
    width:52px;height:52px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;
    background:var(--kpi-soft,var(--color-brand-50));color:var(--kpi-color,var(--color-brand-500));position:relative;z-index:1;
}
.sige-jrel-kpi-icon svg{width:24px;height:24px}
.sige-jrel-kpi > div{position:relative;z-index:1;min-width:0}
.sige-jrel-kpi .num{font-size:27px;font-weight:700;line-height:1;color:var(--color-black);letter-spacing:-.03em}
.sige-jrel-kpi .lbl{font-size:12px;color:var(--color-slate-600);margin-top:7px;font-weight:600}
.sige-jrel-kpi.alunos{--kpi-color:var(--color-brand-500);--kpi-soft:var(--color-brand-50)}
.sige-jrel-kpi.registos{--kpi-color:var(--color-success-500);--kpi-soft:var(--color-success-50)}
.sige-jrel-kpi.cobertura{--kpi-color:var(--sg-theme-primary,var(--color-brand-500));--kpi-soft:var(--color-info-50)}
.sige-jrel-kpi.periodo{--kpi-color:var(--color-warning-500);--kpi-soft:var(--color-warning-50)}

/* Alertas/envio */
.sige-jrel-alert,.sige-jrel-wrap div[style*="msg_envio"]{padding:13px 16px!important;border-radius:var(--radius-lg)!important;margin:0!important;font-weight:700!important;font-size:var(--fs-sm)!important;line-height:1.5!important;border:1px solid transparent!important;box-shadow:0 2px 8px rgba(15,23,42,.06)}
.sige-jrel-alert.success{background:var(--color-success-50)!important;color:var(--color-success-900)!important;border-color:var(--color-success-200)!important}
.sige-jrel-alert.error{background:var(--color-danger-50)!important;color:var(--color-danger-700)!important;border-color:var(--color-danger-200)!important}
.sige-jrel-send-card{
    background:var(--color-white)!important;
    border-radius:var(--radius-xl)!important;
    padding:18px!important;
    margin:0!important;
    box-shadow:var(--shadow-md);
    border:1px solid rgba(28,32,54,.08)!important;
    border-left:4px solid var(--jr-blue)!important;
}
.sige-jrel-send-card > div{display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:var(--space-4)!important;align-items:center!important}
.sige-jrel-send-card h3,.sige-jrel-send-title{display:flex;align-items:center;gap:9px;margin:0 0 5px!important;color:var(--color-ink-500)!important;font-size:15px!important;font-weight:700!important}
.sige-jrel-send-card p,.sige-jrel-send-text{font-size:var(--fs-sm)!important;color:var(--color-slate-500)!important;line-height:1.55!important;margin:0!important;max-width:760px!important}
.sige-jrel-send-card form{display:flex!important;gap:10px!important;align-items:center!important;flex-wrap:wrap!important;margin:0!important}
.sige-jrel-send-card select{height:44px!important;border:1px solid var(--color-ink-100)!important;border-radius:var(--radius-md)!important;padding:0 13px!important;font-size:var(--fs-sm)!important;font-weight:600!important;color:var(--color-ink-500)!important}

/* Toolbar */
.sige-jrel-toolbar{
    background:var(--color-white)!important;
    padding:18px!important;
    border-radius:var(--radius-xl)!important;
    box-shadow:var(--shadow-md);
    margin:0!important;
    border:1px solid rgba(28,32,54,.08)!important;
    animation:fadeInUp .45s ease-out .08s both!important;
}
.sige-jrel-toolbar > div{
    display:grid!important;
    grid-template-columns:minmax(210px,1fr) minmax(170px,.68fr) minmax(170px,.64fr) minmax(210px,1fr) minmax(128px,.42fr)!important;
    gap:var(--space-3)!important;
    align-items:end!important;
    width:100%!important;
}
.sige-jrel-toolbar label{
    display:block!important;
    font-size:var(--fs-xs)!important;
    font-weight:700!important;
    color:var(--color-slate-600)!important;
    text-transform:uppercase!important;
    letter-spacing:.07em!important;
    margin:0 0 7px!important;
}
.sige-jrel-toolbar select,.sige-jrel-toolbar input[type="date"],.sige-jrel-toolbar input[type="month"]{
    width:100%!important;
    min-width:0!important;
    min-height:44px!important;
    padding:0 13px!important;
    border:1px solid var(--color-ink-100)!important;
    border-radius:var(--radius-md)!important;
    font-size:var(--fs-sm)!important;
    color:var(--color-ink-500)!important;
    font-weight:600!important;
    background:var(--color-white)!important;
    box-shadow:var(--shadow-sm);
    outline:none!important;
}
.sige-jrel-toolbar select:focus,.sige-jrel-toolbar input:focus{border-color:rgba(90,63,214,.55)!important;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.sige-jrel-toolbar button[type="submit"],.sige-jrel-wrap button[type="submit"]{
    min-height:44px!important;
    display:inline-flex!important;
    align-items:center!important;
    justify-content:center!important;
    gap:var(--space-2)!important;
    background:linear-gradient(135deg,var(--jr-blue),var(--jr-blue-dark))!important;
    color:var(--color-white)!important;
    border:none!important;
    padding:0 18px!important;
    border-radius:var(--radius-md)!important;
    font-size:var(--fs-sm)!important;
    cursor:pointer!important;
    font-weight:700!important;
    box-shadow:var(--shadow-sm);
}
.sige-jrel-toolbar button:hover,.sige-jrel-wrap button[type="submit"]:hover{transform:translateY(-1px)!important;box-shadow:0 4px 16px rgba(15,23,42,.08)}

/* Tabs */
.sige-jrel-tabs{display:flex!important;flex-wrap:wrap!important;gap:var(--space-3)!important;margin:0!important;background:transparent!important;border-radius:0!important;overflow:visible!important;box-shadow:none!important}
.sige-jrel-tabs a{
    flex:1 1 220px!important;
    min-height:46px!important;
    display:inline-flex!important;
    align-items:center!important;
    justify-content:center!important;
    gap:9px!important;
    border-radius:var(--radius-md)!important;
    padding:0 var(--space-5)!important;
    font-size:var(--fs-base)!important;
    line-height:1!important;
    font-weight:700!important;
    text-decoration:none!important;
    border:1px solid var(--color-ink-100)!important;
    background:var(--color-white)!important;
    color:var(--color-ink-900)!important;
    box-shadow:var(--shadow-sm);
    transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease,background .18s ease!important;
}
.sige-jrel-tabs a.active{background:linear-gradient(135deg,var(--jr-blue),var(--jr-blue-dark))!important;border-color:var(--jr-blue)!important;color:var(--color-white)!important;box-shadow:0 4px 16px rgba(15,23,42,.08)}
.sige-jrel-tabs a:hover{transform:translateY(-1px)}

/* Content/cards/tables/charts - harmoniza também inline legado */
.sige-jrel-section{animation:fadeInUp .45s ease-out .15s both}
.sige-jrel-wrap > div[style*="background:white"],
.sige-jrel-wrap div[style*="background:white;border-radius"],
.sige-jrel-wrap div[style*="background:var(--color-white);border-radius"]{
    background:var(--color-white)!important;
    border-radius:var(--radius-xl)!important;
    border:1px solid rgba(28,32,54,.08)!important;
    box-shadow:var(--shadow-md);
}
.sige-jrel-wrap div[style*="display:grid;grid-template-columns:repeat(4,1fr)"]{
    display:grid!important;
    grid-template-columns:repeat(4,minmax(0,1fr))!important;
    gap:var(--space-4)!important;
    margin:0!important;
}
.sige-jrel-wrap div[style*="display:grid;grid-template-columns:3fr 2fr"],
.sige-jrel-wrap div[style*="display:grid;grid-template-columns:2fr 1fr"]{
    display:grid!important;
    grid-template-columns:minmax(0,1.35fr) minmax(320px,.65fr)!important;
    gap:18px!important;
    margin:0!important;
}
.sige-jrel-wrap h3{color:var(--color-ink-500)!important;font-weight:700!important}
.sige-jrel-wrap table{width:100%!important;border-collapse:separate!important;border-spacing:0!important;min-width:980px!important}
.sige-jrel-wrap thead tr{background:var(--color-slate-50)!important}
.sige-jrel-wrap th{
    padding:12px 14px!important;
    color:var(--color-slate-600)!important;
    font-size:var(--fs-xs)!important;
    font-weight:700!important;
    text-transform:uppercase!important;
    letter-spacing:.06em!important;
    border-bottom:1px solid var(--color-slate-100)!important;
}
.sige-jrel-wrap td{
    border-bottom:1px solid var(--color-slate-100)!important;
    color:var(--color-ink-800)!important;
}
.sige-jrel-wrap div[style*="overflow-x:auto"]{
    overflow-x:auto!important;
    -webkit-overflow-scrolling:touch!important;
}
.sige-jrel-wrap div[style*="overflow-x:auto"]::-webkit-scrollbar{height:8px}
.sige-jrel-wrap div[style*="overflow-x:auto"]::-webkit-scrollbar-thumb{background:var(--color-slate-200);border-radius:999px}
.sige-jrel-wrap canvas{max-width:100%!important}

/* Empty/warnings */
.sige-jrel-empty,.sige-jrel-wrap div[style*="Nenhuma turma Pré-Escolar"],.sige-jrel-wrap div[style*="Aluno não encontrado"]{
    background:var(--color-white)!important;
    border-radius:var(--radius-xl)!important;
    padding:42px 24px!important;
    text-align:center!important;
    border:1px dashed var(--color-ink-200)!important;
    box-shadow:var(--shadow-md);
    color:var(--color-slate-500)!important;
}

/* Responsividade */
@media(max-width:1450px){
    .sige-jrel-hero{grid-template-columns:minmax(0,1fr) minmax(280px,.52fr)!important}
    .sige-jrel-toolbar > div{grid-template-columns:repeat(3,minmax(0,1fr))!important}
}
@media(max-width:1280px){
    .sige-jrel-kpis{grid-template-columns:repeat(2,minmax(0,1fr))!important}
    .sige-jrel-wrap div[style*="display:grid;grid-template-columns:repeat(4,1fr)"]{grid-template-columns:repeat(2,minmax(0,1fr))!important}
    .sige-jrel-wrap div[style*="display:grid;grid-template-columns:3fr 2fr"],
    .sige-jrel-wrap div[style*="display:grid;grid-template-columns:2fr 1fr"]{grid-template-columns:1fr!important}
    .sige-jrel-send-card > div{grid-template-columns:1fr!important}
}
@media(max-width:980px){
    body.sige-admin-app.sige-view-jardim_relatorio .sg-app-content{padding-left:18px!important;padding-right:18px!important}
    .sige-jrel-hero{grid-template-columns:1fr!important}
    .sige-jrel-toolbar > div{grid-template-columns:repeat(2,minmax(0,1fr))!important}
}
@media(max-width:760px){
    body.sige-admin-app.sige-view-jardim_relatorio .sg-app-content{padding-left:14px!important;padding-right:14px!important}
    .sige-jrel-wrap{gap:16px!important}
    .sige-jrel-hero{padding:26px 22px!important}
    .sige-jrel-hero h1{font-size:24px!important}
    .sige-jrel-kpis{grid-template-columns:1fr!important}
    .sige-jrel-toolbar > div{grid-template-columns:1fr!important}
    .sige-jrel-tabs{display:grid!important;grid-template-columns:1fr!important}
    .sige-jrel-wrap div[style*="display:grid;grid-template-columns:repeat(4,1fr)"]{grid-template-columns:1fr!important}
    .sige-jrel-btn,.sige-jrel-toolbar button[type="submit"],.sige-jrel-wrap button[type="submit"],.sige-jrel-send-card select{width:100%!important}
}
</style>
<div class="wrap sige-jrel-wrap">

<!-- HERO -->
<section class="sige-jrel-hero" aria-label="Diários e Análises do Jardim">
  <div class="sige-jrel-hero-main">
    <div class="sige-jrel-hero-kicker">
      <?php echo $jrel_icon('bar-chart'); ?>
      <span>Pré-Escolar</span>
    </div>
    <h1>Diários e Análises</h1>
    <p>Relatórios de actividades, presença, comportamento, sono e comunicação com encarregados do Jardim de Infância.</p>
    <div class="sige-jrel-hero-chips">
      <span><?php echo $jrel_icon('calendar'); ?> Ano Lectivo <?php echo esc_html($ano); ?></span>
      <span><?php echo $jrel_icon('clock'); ?> <?php echo esc_html($jrel_periodo_label); ?></span>
      <?php if ($jrel_turma_nome): ?><span><?php echo $jrel_icon('users'); ?> <?php echo esc_html($jrel_turma_nome); ?></span><?php endif; ?>
      <span><?php echo $jrel_icon($sub === 'aluno' ? 'user' : 'users'); ?> <?php echo esc_html($jrel_sub_label); ?></span>
    </div>
    <div class="sige-jrel-hero-actions">
      <a href="?page=sige-app&view=jardim_diario&turma_id=<?php echo $turma_sel; ?>" class="btn-ghost">
        <?php echo $jrel_icon('book'); ?> Diário
      </a>
    </div>
  </div>
  <aside class="sige-jrel-hero-panel" aria-label="Estado dos registos">
    <div class="sige-jrel-panel-label"><?php echo $jrel_icon('activity'); ?><span>Estado do período</span></div>
    <strong class="sige-jrel-panel-value"><?php echo esc_html((string)$jrel_total_registos); ?></strong>
    <span class="sige-jrel-panel-text">Registo(s) encontrado(s) para a turma e período seleccionados.</span>
  </aside>
</section>


<?php if (isset($_GET['msg_envio'])): ?>
  <?php if ($_GET['msg_envio'] === 'ok' || $_GET['msg_envio'] === 'ok_ind'): ?>
    <div style="background:var(--color-success-50);border:1px solid var(--color-success-200);color:var(--color-success-900);border-radius:12px;padding:13px 16px;margin:14px 0;font-size:13px;font-weight:600;">
      ✅ <?php echo ($_GET['msg_envio'] === 'ok_ind') ? 'Resumo individual processado.' : 'Resumo mensal processado.'; ?> WhatsApp: <?php echo (int)($_GET['wpp'] ?? 0); ?> · E-mail: <?php echo (int)($_GET['email'] ?? 0); ?> · Sem contacto: <?php echo (int)($_GET['sem_contacto'] ?? 0); ?> · Sem dados no mês: <?php echo (int)($_GET['sem_dados'] ?? 0); ?>.
    </div>
  <?php elseif ($_GET['msg_envio'] === 'duplicado'): ?>
    <div style="background:var(--color-warning-50);border:1px solid var(--color-warning-200);color:var(--color-warning-800);border-radius:12px;padding:13px 16px;margin:14px 0;font-size:13px;font-weight:600;">
      ⚠️ Este envio já estava em processamento há instantes. Para evitar mensagens duplicadas aos encarregados, o sistema bloqueou o duplo clique.
    </div>
  <?php else: ?>
    <div style="background:var(--color-danger-50);border:1px solid var(--color-danger-200);color:var(--color-danger-700);border-radius:12px;padding:13px 16px;margin:14px 0;font-size:13px;font-weight:600;">
      ⚠️ Não foi possível processar o envio mensal. Confirme a turma e tente novamente.
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php if (!empty($turma_sel) && $periodo === 'mes'): ?>
<section class="sige-jrel-send-card">
  <div>
    <div>
      <div class="sige-jrel-send-title"><?php echo $jrel_icon('mail'); ?> Envio mensal aos encarregados</div>
      <p class="sige-jrel-send-text">Envio manual e controlado do resumo mensal dos Diários & Análises. Não é automático, para evitar mensagens indevidas ou repetidas aos pais.</p>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="sige_jardim_relatorio_enviar_mensal">
      <input type="hidden" name="turma_id" value="<?php echo esc_attr($turma_sel); ?>">
      <input type="hidden" name="ano_lectivo" value="<?php echo esc_attr($ano); ?>">
      <input type="hidden" name="mes_sel" value="<?php echo esc_attr($mes_sel); ?>">
      <?php wp_nonce_field('sige_jardim_relatorio_envio_nonce', '_jrel_envio_nonce'); ?>
      <select name="canal">
        <option value="ambos">WhatsApp + E-mail</option>
        <option value="whatsapp">Só WhatsApp</option>
        <option value="email">Só E-mail</option>
      </select>
      <button type="submit" class="sige-jrel-btn" data-sige-confirm="O resumo mensal do Jardim de Infância desta turma será enviado aos encarregados pelo canal escolhido." data-sige-titulo="Enviar resumo mensal" data-sige-confirmar="Enviar"><?php echo $jrel_icon('mail'); ?> Enviar resumo mensal</button>
    </form>
  </div>
</section>
<?php endif; ?>
<!-- FILTROS -->
<form method="get" id="formFiltros" class="sige-jrel-toolbar">
  <input type="hidden" name="page" value="sige-app">
  <input type="hidden" name="view" value="jardim_relatorio">
  <input type="hidden" name="sub"  value="<?php echo esc_attr($sub); ?>">
  <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
    <div>
      <label>Turma</label>
      <select name="turma_id">
        <?php foreach ($turmas as $t): ?>
          <option value="<?php echo esc_attr($t->id); ?>" <?php selected($t->id,$turma_sel); ?>>
            <?php echo esc_html($t->nome?:$t->classe); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Período</label>
      <select name="periodo" id="selectPeriodo" onchange="togglePeriodoUI()">
        <option value="dia"       <?php selected($periodo,'dia'); ?>>Dia Específico</option>
        <option value="mes"       <?php selected($periodo,'mes'); ?>>Mês</option>
        <option value="acumulado" <?php selected($periodo,'acumulado'); ?>>Acumulado (ano todo)</option>
      </select>
    </div>
    <div id="ui_dia" style="display:<?php echo $periodo==='dia'?'block':'none'; ?>">
      <label>Data</label>
      <input type="date" name="dia_sel" value="<?php echo esc_attr($dia_sel); ?>" max="<?php echo $hoje; ?>">
    </div>
    <div id="ui_mes" style="display:<?php echo $periodo==='mes'?'block':'none'; ?>">
      <label>Mês</label>
      <input type="month" name="mes_sel" value="<?php echo esc_attr($mes_sel); ?>" max="<?php echo wp_date('Y-m'); ?>">
    </div>
    <?php if ($sub === 'aluno'): ?>
    <div>
      <label>Aluno</label>
      <select name="aluno_id" style="min-width:200px;">
        <?php foreach ($alunos as $a): ?>
          <option value="<?php echo (int)$a->id; ?>" <?php selected((int)$a->id,$aluno_sel); ?>>
            <?php echo esc_html($a->nome_completo); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <button type="submit" class="sige-jrel-btn">
      <?php echo $jrel_icon('filter'); ?> Aplicar
    </button>
  </div>
</form>
<!-- TABS: TURMA | ALUNO -->
<nav class="sige-jrel-tabs" aria-label="Alternar relatório">
  <?php foreach(['turma'=>'Vista da Turma','aluno'=>'Aluno Individual'] as $tk=>$tl):
    $act = $sub === $tk;
    $href = '?page=sige-app&view=jardim_relatorio&turma_id='.$turma_sel.'&sub='.$tk
          .'&periodo='.urlencode($periodo).'&mes_sel='.urlencode($mes_sel).'&dia_sel='.urlencode($dia_sel)
          .($tk==='aluno'&&$aluno_sel?'&aluno_id='.$aluno_sel:'');
  ?>
    <a href="<?php echo esc_url($href); ?>" class="<?php echo $act ? 'active' : ''; ?>">
      <?php echo $jrel_icon($tk === 'aluno' ? 'user' : 'users'); ?>
      <?php echo esc_html($tl); ?>
    </a>
  <?php endforeach; ?>
</nav>
<?php if (empty($turmas)): ?>
  <div style="background:white;border-radius:10px;padding:40px;text-align:center;color:var(--color-slate-500);">
    Nenhuma turma Pré-Escolar encontrada.
  </div>
<?php elseif ($sub === 'turma'): ?>
<!-- ═══ VISTA DA TURMA ═══ -->
<?php
$total_regs  = array_sum(array_map(fn($a)=>$resumo[(int)$a->id]['registados']??0, $alunos));
$media_pct   = $alunos ? round(array_sum(array_column($resumo,'pct')) / count($alunos)) : 0;
$humor_geral = [];
foreach ($resumo as $r) { foreach ($r['humor_freq'] as $h=>$n) $humor_geral[$h] = ($humor_geral[$h]??0)+$n; }
arsort($humor_geral);
$humor_dom       = array_key_first($humor_geral) ?? '';
$alunos_c_reg    = count(array_filter($resumo, fn($r)=>$r['registados']>0));
?>
<!-- KPIs -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;">
  <?php foreach([
    ['📝','Registos Totais', $total_regs,        'var(--color-black)'],
    ['📅','Dias no Período',  $total_dias,         'var(--color-ink-800)'],
    ['✅','Cobertura Média',  $media_pct.'%',      'var(--color-success-500)'],
    ['😊','Humor Dominante',  $humor_dom?$humor_icon[$humor_dom].' '.($humor_label[$humor_dom]??''):'-', 'var(--color-brand-500)'],
  ] as [$ic,$lab,$val,$cor]): ?>
  <div style="background:white;border-radius:10px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,.06);border-top:3px solid <?php echo $cor; ?>;">
    <div style="font-size:22px;margin-bottom:6px;"><?php echo $ic; ?></div>
    <div style="font-size:22px;font-weight:700;color:<?php echo $cor; ?>;"><?php echo $val; ?></div>
    <div style="font-size:12px;color:var(--color-slate-500);margin-top:2px;"><?php echo $lab; ?></div>
  </div>
  <?php endforeach; ?>
</div>
<!-- Gráficos -->
<div style="display:grid;grid-template-columns:3fr 2fr;gap:16px;margin-bottom:20px;">
  <div style="background:white;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.06);">
    <h3 style="margin:0 0 16px;font-size:14px;color:var(--color-black);">📈 Comportamento Semanal da Turma</h3>
    <?php if (empty($semanas_all)): ?>
      <div style="text-align:center;padding:40px;color:var(--color-slate-400);font-size:13px;">Sem dados no período.</div>
    <?php else: ?>
      <canvas id="chartComp" height="180"></canvas>
    <?php endif; ?>
  </div>
  <div style="background:white;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.06);">
    <h3 style="margin:0 0 16px;font-size:14px;color:var(--color-black);">💤 Sono por Semana</h3>
    <?php if (empty($semanas_all)): ?>
      <div style="text-align:center;padding:40px;color:var(--color-slate-400);font-size:13px;">Sem dados no período.</div>
    <?php else: ?>
      <canvas id="chartSono" height="180"></canvas>
    <?php endif; ?>
  </div>
</div>
<!-- Tabela por aluno -->
<div style="background:white;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.06);overflow:hidden;margin-bottom:20px;">
  <div style="padding:16px 20px;border-bottom:1px solid var(--color-ink-50);display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;font-size:14px;color:var(--color-black);">👥 Resumo por Aluno - <?php echo esc_html($label_p); ?></h3>
    <span style="font-size:12px;color:var(--color-slate-400);"><?php echo $alunos_c_reg; ?>/<?php echo count($alunos); ?> com registos</span>
  </div>
  <?php if (empty($alunos)): ?>
    <div style="padding:30px;text-align:center;color:var(--color-slate-400);">Nenhum aluno matriculado nesta turma.</div>
  <?php else: ?>
  <div class="sige-u-oxa">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead>
      <tr style="background:var(--color-slate-50);">
        <?php foreach(['Aluno','Registos','Cobertura','Pres.','Faltas','Humor Dom.','Comportamento','Sono ✅','Méd.Sono','Envio',''] as $th): ?>
          <th style="padding:10px <?php echo $th==='Aluno'?'16px':'12px'; ?>;text-align:<?php echo $th==='Aluno'?'left':'center'; ?>;
              font-weight:700;color:var(--color-slate-800);border-bottom:2px solid var(--color-ink-100);"><?php echo $th; ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($alunos as $idx => $al):
      $aid  = (int)$al->id;
      $r    = $resumo[$aid];
      $bg   = $idx%2===0?'var(--color-white)':'var(--color-slate-50)';
      $foto = $al->foto ?: 'https://ui-avatars.com/api/?name='.urlencode($al->nome_completo).'&background=0f172a&color=fff&size=40';
      $pct_cor = $r['pct']>=80?'var(--color-success-500)':($r['pct']>=50?'var(--color-warning-700)':'var(--color-danger-600)');
      $h_top   = $r['humor_top'];
      $c_top   = $r['comp_top'];
    ?>
    <tr style="background:<?php echo $bg; ?>;">
      <td style="padding:10px 16px;border-bottom:1px solid var(--color-ink-50);">
        <div style="display:flex;align-items:center;gap:10px;">
          <img src="<?php echo esc_url($foto); ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
          <span class="sige-u-fw6"><?php echo esc_html($al->nome_completo); ?></span>
        </div>
      </td>
      <td style="padding:10px 12px;text-align:center;border-bottom:1px solid var(--color-ink-50);font-weight:700;">
        <?php echo $r['registados']; ?><span style="color:var(--color-slate-400);font-weight:400;">/<?php echo $total_dias; ?></span>
      </td>
      <td style="padding:10px 12px;text-align:center;border-bottom:1px solid var(--color-ink-50);">
        <div style="display:flex;align-items:center;gap:6px;justify-content:center;">
          <div style="width:55px;height:8px;background:var(--color-ink-50);border-radius:4px;overflow:hidden;">
            <div style="width:<?php echo $r['pct']; ?>%;height:100%;background:<?php echo $pct_cor; ?>;border-radius:4px;"></div>
          </div>
          <span style="font-size:12px;font-weight:700;color:<?php echo $pct_cor; ?>;"><?php echo $r['pct']; ?>%</span>
        </div>
      </td>
      <td style="padding:10px 12px;text-align:center;border-bottom:1px solid var(--color-ink-50);font-weight:700;color:var(--color-success-500);">
        <?php echo (int)$r['presente']; ?><?php if ((int)$r['atraso'] > 0): ?><span style="color:var(--color-warning-700);font-size:11px;display:block;">+<?php echo (int)$r['atraso']; ?> atraso</span><?php endif; ?>
      </td>
      <td style="padding:10px 12px;text-align:center;border-bottom:1px solid var(--color-ink-50);font-weight:700;color:var(--color-danger-600);">
        <?php echo (int)$r['falta']; ?><?php if ((int)$r['falta_justificada'] > 0): ?><span style="color:var(--color-info-500);font-size:11px;display:block;">+<?php echo (int)$r['falta_justificada']; ?> just.</span><?php endif; ?>
      </td>
      <td style="padding:10px 12px;text-align:center;border-bottom:1px solid var(--color-ink-50);">
        <?php echo $h_top ? '<span style="font-size:18px;" title="'.esc_attr($humor_label[$h_top]??'').'">'.$humor_icon[$h_top].'</span><span style="font-size:11px;color:var(--color-slate-500);display:block;">'.esc_html($humor_label[$h_top]??'').'</span>' : '<span style="color:var(--color-slate-200);">-</span>'; ?>
      </td>
      <td style="padding:10px 12px;text-align:center;border-bottom:1px solid var(--color-ink-50);">
        <?php echo $c_top ? '<span style="background:'.$comp_cor[$c_top].'20;color:'.$comp_cor[$c_top].';padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">'.esc_html($comp_label[$c_top]??$c_top).'</span>' : '<span style="color:var(--color-slate-200);">-</span>'; ?>
      </td>
      <td style="padding:10px 12px;text-align:center;border-bottom:1px solid var(--color-ink-50);">
        <span style="color:var(--color-success-500);font-weight:700;"><?php echo $r['sono_sim']; ?></span>
        <span style="color:var(--color-slate-400);font-size:11px;">/<?php echo $r['sono_sim']+$r['sono_nao']; ?></span>
      </td>
      <td style="padding:10px 12px;text-align:center;border-bottom:1px solid var(--color-ink-50);color:var(--color-slate-800);">
        <?php echo $r['min_sono_avg']>0?$r['min_sono_avg'].' min':'-'; ?>
      </td>
      <td style="padding:10px 12px;text-align:center;border-bottom:1px solid var(--color-ink-50);min-width:190px;">
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:5px;justify-content:center;align-items:center;margin:0;flex-wrap:wrap;">
          <input type="hidden" name="action" value="sige_jardim_relatorio_enviar_individual">
          <input type="hidden" name="turma_id" value="<?php echo esc_attr($turma_sel); ?>">
          <input type="hidden" name="aluno_id" value="<?php echo esc_attr($aid); ?>">
          <input type="hidden" name="ano_lectivo" value="<?php echo esc_attr($ano); ?>">
          <input type="hidden" name="mes_sel" value="<?php echo esc_attr($mes_sel); ?>">
          <?php wp_nonce_field('sige_jardim_relatorio_envio_individual_nonce', '_jrel_envio_ind_nonce'); ?>
          <select name="canal" title="Canal de envio" style="height:30px;border:1px solid var(--color-ink-200);border-radius:7px;padding:0 6px;font-size:11px;max-width:95px;">
            <option value="ambos">Ambos</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="email">E-mail</option>
          </select>
          <button type="submit" class="sgk-btn sgk-btn-sm" style="height:30px;background:var(--color-success-500);color:var(--color-white);padding:0 9px;font-size:11px;font-weight:800;" data-sige-confirm="O resumo individual mais recente deste aluno será enviado ao encarregado pelo canal escolhido." data-sige-titulo="Enviar resumo individual" data-sige-confirmar="Enviar">Enviar</button>
        </form>
      </td>
      <td style="padding:10px 12px;text-align:center;border-bottom:1px solid var(--color-ink-50);">
        <a href="?page=sige-app&view=jardim_relatorio&sub=aluno&turma_id=<?php echo $turma_sel; ?>&aluno_id=<?php echo $aid; ?>&periodo=<?php echo $periodo; ?>&mes_sel=<?php echo urlencode($mes_sel); ?>&dia_sel=<?php echo urlencode($dia_sel); ?>"
           style="background:var(--color-black);color:white;padding:5px 12px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;">
          Ver →
        </a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php else: ?>
<!-- ═══ VISTA DO ALUNO INDIVIDUAL ═══ -->
<?php if (!$aluno_obj): ?>
  <div style="background:var(--color-warning-200);border:1px solid var(--color-warning-300);border-radius:10px;padding:24px;color:var(--color-warning-800);">
    <strong>⚠️ Aluno não encontrado.</strong>
    <?php if (empty($alunos)): ?>
      <span>Nenhum aluno matriculado nesta turma no ano <?php echo $ano; ?>.</span>
    <?php else: ?>
      <span>Selecciona um aluno no filtro acima.</span>
    <?php endif; ?>
  </div>
<?php else:
  $r_al = $resumo[$aluno_sel] ?? ['registados'=>0,'pct'=>0,'humor_top'=>'','comp_top'=>'','sono_sim'=>0,'sono_nao'=>0,'min_sono_avg'=>0];
  $foto  = $aluno_obj->foto ?: 'https://ui-avatars.com/api/?name='.urlencode($aluno_obj->nome_completo).'&background=0f172a&color=fff&size=80';
?>
<!-- Cabeçalho do aluno -->
<div style="display:grid;grid-template-columns:auto 1fr;gap:20px;background:white;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:18px;align-items:center;">
  <img src="<?php echo esc_url($foto); ?>" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid var(--color-black);">
  <div>
    <div style="font-weight:700;font-size:18px;color:var(--color-black);"><?php echo esc_html($aluno_obj->nome_completo); ?></div>
    <div style="font-size:13px;color:var(--color-slate-500);margin-top:3px;"><?php echo $aluno_obj->genero==='M'?'👦 Masculino':'👧 Feminino'; ?> &nbsp;|&nbsp; <?php echo esc_html($label_p); ?></div>
    <div style="display:flex;gap:12px;margin-top:12px;flex-wrap:wrap;">
      <?php foreach([
        ['Registos',   $r_al['registados'].($total_dias?'/'.$total_dias:''),        'var(--color-black)'],
        ['Cobertura',  $r_al['pct'].'%',  $r_al['pct']>=80?'var(--color-success-500)':($r_al['pct']>=50?'var(--color-warning-700)':'var(--color-danger-600)')],
        ['Humor',      $r_al['humor_top']?$humor_icon[$r_al['humor_top']].' '.($humor_label[$r_al['humor_top']]??''):'-', 'var(--color-brand-500)'],
        ['Dormiu',     $r_al['sono_sim'].'/'.($r_al['sono_sim']+$r_al['sono_nao']).' dias', 'var(--color-info-500)'],
        ['Méd.Sono',   $r_al['min_sono_avg']>0?$r_al['min_sono_avg'].' min':'-',    'var(--color-black)'],
      ] as [$lab,$val,$cor]): ?>
        <div style="background:var(--color-slate-50);padding:8px 14px;border-radius:8px;border-left:3px solid <?php echo $cor; ?>;">
          <div style="font-size:10px;color:var(--color-slate-500);text-transform:uppercase;letter-spacing:.5px;"><?php echo $lab; ?></div>
          <div style="font-weight:700;color:<?php echo $cor; ?>;font-size:14px;"><?php echo $val; ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php if ($periodo === 'acumulado' && !empty($cumul_meses)): ?>
<!-- Vista cumulativa: gráfico por mês -->
<div style="background:white;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:18px;">
  <h3 style="margin:0 0 4px;font-size:14px;color:var(--color-black);">📈 Evolução ao Longo do Ano - <?php echo esc_html($aluno_obj->nome_completo); ?></h3>
  <p style="margin:0 0 16px;font-size:12px;color:var(--color-slate-400);">Registos por mês, sono e humor (dados cumulativos desde Janeiro <?php echo $ano; ?>)</p>
  <canvas id="chartCumul" height="120"></canvas>
</div>
<!-- Tabela cumulativa por mês -->
<div style="background:white;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.06);overflow:hidden;margin-bottom:18px;">
  <div style="padding:14px 20px;border-bottom:1px solid var(--color-ink-50);">
    <h3 style="margin:0;font-size:14px;color:var(--color-black);">📋 Detalhe Mensal</h3>
  </div>
  <div class="sige-u-oxa">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead>
      <tr style="background:var(--color-slate-50);">
        <?php foreach(['Mês','Registos','Dormiu','Feliz','Triste/Agitado','Excelente','Necessita Atenção'] as $th): ?>
          <th style="padding:10px 14px;text-align:<?php echo $th==='Mês'?'left':'center'; ?>;font-weight:700;color:var(--color-slate-800);border-bottom:2px solid var(--color-ink-100);"><?php echo $th; ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($cumul_meses as $mes => $mc):
      $mes_label = $meses_pt[substr($mes,5,2)] ?? $mes;
    ?>
      <tr style="border-bottom:1px solid var(--color-ink-50);">
        <td style="padding:10px 14px;font-weight:700;color:var(--color-black);"><?php echo $mes_label; ?></td>
        <td style="padding:10px 14px;text-align:center;font-weight:700;"><?php echo $mc->total; ?></td>
        <td style="padding:10px 14px;text-align:center;">
          <span style="color:var(--color-brand-500);font-weight:700;"><?php echo $mc->sono; ?></span>
          <span style="color:var(--color-slate-400);font-size:11px;">/<?php echo $mc->total; ?></span>
        </td>
        <td style="padding:10px 14px;text-align:center;">
          <?php if ($mc->feliz>0): ?><span style="color:var(--color-success-500);font-weight:700;"><?php echo $mc->feliz; ?></span><?php else: ?>-<?php endif; ?>
        </td>
        <td style="padding:10px 14px;text-align:center;">
          <?php $neg = (int)$mc->triste + (int)$mc->agitado; echo $neg>0?'<span style="color:var(--color-danger-600);font-weight:700;">'.$neg.'</span>':'-'; ?>
        </td>
        <td style="padding:10px 14px;text-align:center;">
          <?php if ($mc->excelente>0): ?><span style="color:var(--color-success-500);font-weight:700;"><?php echo $mc->excelente; ?></span><?php else: ?>-<?php endif; ?>
        </td>
        <td style="padding:10px 14px;text-align:center;">
          <?php if ($mc->necessita>0): ?><span style="color:var(--color-danger-600);font-weight:700;"><?php echo $mc->necessita; ?></span><?php else: ?>-<?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; // acumulado ?>
<!-- Calendário + Recados -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:18px;">
  <!-- Calendário de humor -->
  <div style="background:white;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.06);">
    <h3 style="margin:0 0 4px;font-size:14px;color:var(--color-black);">
      📅 Calendário de Humor - <?php echo wp_date('F Y', strtotime($cal_ini)); ?>
    </h3>
    <p style="margin:0 0 12px;font-size:12px;color:var(--color-slate-400);">Cada dia útil colorido com o humor do aluno</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
      <?php foreach($humor_label as $hk=>$hl): ?>
        <span style="display:flex;align-items:center;gap:4px;font-size:11px;">
          <span style="width:12px;height:12px;border-radius:3px;background:<?php echo $humor_cor[$hk]; ?>;display:inline-block;"></span>
          <?php echo $hl; ?>
        </span>
      <?php endforeach; ?>
    </div>
    <?php
    $dow_1 = (int)wp_date('N', strtotime($cal_ini));
    $n_dias = (int)wp_date('t', strtotime($cal_ini));
    ?>
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:4px;">
      <?php foreach(['Seg','Ter','Qua','Qui','Sex'] as $ds): ?>
        <div style="text-align:center;font-size:10px;font-weight:700;color:var(--color-slate-400);padding:3px 0;"><?php echo $ds; ?></div>
      <?php endforeach; ?>
      <?php for ($b=0;$b<min($dow_1-1,5);$b++): ?><div></div><?php endfor; ?>
      <?php for ($d=1;$d<=$n_dias;$d++):
        $dc  = $cal_mes.'-'.str_pad($d,2,'0',STR_PAD_LEFT);
        $dow = (int)wp_date('N',strtotime($dc));
        if ($dow>=6) continue;
        $reg = $regs_cal[$dc] ?? null;
        $h   = $reg->humor ?? '';
        $bg  = $h ? $humor_cor[$h] : ($reg?'var(--color-slate-500)':'white');
        $brd = $reg?'none':'1px dashed var(--color-ink-100)';
        $ic  = $h ? $humor_icon[$h] : ($reg?'·':'');
        $tod = $dc===$hoje;
      ?>
        <div title="<?php echo wp_date('d/m',strtotime($dc)).($reg?' - '.($humor_label[$h]??'Registado'):''); ?>"
             style="aspect-ratio:1;border-radius:7px;background:<?php echo $bg; ?>;
                    border:<?php echo $tod?'2px solid var(--color-black)':$brd; ?>;
                    display:flex;flex-direction:column;align-items:center;justify-content:center;">
          <span style="font-size:9px;color:<?php echo $reg?'rgba(255,255,255,0.85)':'var(--color-slate-200)'; ?>;font-weight:700;"><?php echo $d; ?></span>
          <?php if ($ic): ?><span style="font-size:11px;line-height:1;"><?php echo $ic; ?></span><?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>
  </div>
  <!-- Recados -->
  <div style="background:white;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.06);">
    <h3 style="margin:0 0 4px;font-size:14px;color:var(--color-black);">📨 Recados para os Pais</h3>
    <p style="margin:0 0 12px;font-size:12px;color:var(--color-slate-400);">
      <?php echo count($recados); ?> recado<?php echo count($recados)!=1?'s':''; ?> registado<?php echo count($recados)!=1?'s':''; ?>
    </p>
    <?php if (empty($recados)): ?>
      <div style="text-align:center;padding:30px;color:var(--color-slate-200);font-size:13px;">Sem recados.</div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:8px;max-height:360px;overflow-y:auto;">
      <?php foreach ($recados as $rec): ?>
        <div style="border-left:3px solid var(--color-warning-500);background:var(--color-warning-50);padding:10px 12px;border-radius:0 8px 8px 0;">
          <div style="font-size:10px;font-weight:700;color:var(--color-warning-700);margin-bottom:4px;">
            📅 <?php echo wp_date('d/m/Y',strtotime($rec->data_registo)); ?>
          </div>
          <div style="font-size:12px;color:var(--color-slate-800);line-height:1.5;"><?php echo nl2br(esc_html($rec->recado_pais)); ?></div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<!-- Tabela detalhada dos registos do período -->
<div style="background:white;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.06);overflow:hidden;">
  <div style="padding:16px 20px;border-bottom:1px solid var(--color-ink-50);">
    <h3 style="margin:0;font-size:14px;color:var(--color-black);">📋 Registos Detalhados - <?php echo esc_html($label_p); ?></h3>
  </div>
  <?php $regs_p = $registos_turma[$aluno_sel] ?? []; ?>
  <?php if (empty($regs_p)): ?>
    <div style="padding:30px;text-align:center;color:var(--color-slate-400);font-size:13px;">Sem registos no período seleccionado.</div>
  <?php else: ?>
  <div class="sige-u-oxa">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead>
      <tr style="background:var(--color-slate-50);">
        <?php foreach(['Data','Humor','Comportamento','Sono','Actividades','Observações'] as $th): ?>
          <th style="padding:10px <?php echo in_array($th,['Data'])?'16':'12'; ?>px;text-align:<?php echo in_array($th,['Actividades','Observações','Data'])?'left':'center'; ?>;font-weight:700;color:var(--color-slate-800);border-bottom:2px solid var(--color-ink-100);"><?php echo $th; ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
    <?php foreach (array_reverse($regs_p, true) as $data => $r):
      $h = $r->humor ?? ''; $c = $r->comportamento ?? '';
    ?>
      <tr style="border-bottom:1px solid var(--color-ink-50);">
        <td style="padding:10px 16px;font-weight:600;color:var(--color-slate-800);white-space:nowrap;">
          <?php echo wp_date('D d/m', strtotime($data)); ?>
        </td>
        <td style="padding:10px 12px;text-align:center;">
          <?php echo $h?'<span style="font-size:18px;" title="'.esc_attr($humor_label[$h]??'').'">'.$humor_icon[$h].'</span>':'<span style="color:var(--color-slate-200);">-</span>'; ?>
        </td>
        <td style="padding:10px 12px;text-align:center;">
          <?php echo $c?'<span style="background:'.$comp_cor[$c].'20;color:'.$comp_cor[$c].';padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">'.esc_html($comp_label[$c]??$c).'</span>':'<span style="color:var(--color-slate-200);">-</span>'; ?>
        </td>
        <td style="padding:10px 12px;text-align:center;">
          <?php
          $dorm = (string)$r->dormiu;
          if ($dorm==='1') echo '<span style="color:var(--color-success-500);font-weight:700;">✅'.($r->minutos_sono?' '.$r->minutos_sono.'m':'').'</span>';
          elseif ($dorm==='0') echo '<span style="color:var(--color-danger-600);">❌</span>';
          else echo '<span style="color:var(--color-slate-200);">-</span>';
          ?>
        </td>
        <td style="padding:10px 12px;max-width:180px;color:var(--color-slate-800);font-size:12px;">
          <?php echo $r->actividades?esc_html(mb_substr($r->actividades,0,80)).(mb_strlen($r->actividades)>80?'…':''):'<span style="color:var(--color-slate-200);">-</span>'; ?>
        </td>
        <td style="padding:10px 12px;max-width:180px;color:var(--color-slate-800);font-size:12px;">
          <?php echo $r->obs_comportamento?esc_html(mb_substr($r->obs_comportamento,0,80)).(mb_strlen($r->obs_comportamento)>80?'…':''):'<span style="color:var(--color-slate-200);">-</span>'; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; // aluno_obj ?>
<?php endif; // sub ?>
</div><!-- wrap -->
<?php echo sige_cdn_script("chartjs"); ?>
<script <?php echo sige_csp_script_attr(); ?>>
// Toggle campos de período
function togglePeriodoUI() {
  var p = document.getElementById('selectPeriodo').value;
  document.getElementById('ui_dia').style.display = p==='dia'  ? 'block' : 'none';
  document.getElementById('ui_mes').style.display = p==='mes'  ? 'block' : 'none';
}
// Gráfico comportamento semanal
(function(){
  var el = document.getElementById('chartComp');
  if (!el) return;
  new Chart(el, {
    type: 'bar',
    data: {
      labels: <?php echo $json_semanas; ?>,
      datasets: [
        { label:'Excelente',         data:<?php echo $json_excelente; ?>, backgroundColor:'#16a34a' },
        { label:'Bom',               data:<?php echo $json_bom; ?>,       backgroundColor:'#2563eb' },
        { label:'Satisfatório',      data:<?php echo $json_satis; ?>,     backgroundColor:'#d97706' },
        { label:'Necessita Atenção', data:<?php echo $json_atencao; ?>,   backgroundColor:'#dc2626' }
      ]
    },
    options:{ responsive:true, plugins:{ legend:{ position:'bottom', labels:{ font:{size:11} } } },
              scales:{ x:{stacked:true,grid:{display:false}}, y:{stacked:true,beginAtZero:true,ticks:{stepSize:1}} } }
  });
})();
// Gráfico sono semanal
(function(){
  var el = document.getElementById('chartSono');
  if (!el) return;
  new Chart(el, {
    type: 'bar',
    data: {
      labels: <?php echo $json_semanas; ?>,
      datasets: [
        { label:'Dormiu',     data:<?php echo $json_sono_sim; ?>, backgroundColor:'#7c3aed' },
        { label:'Não dormiu', data:<?php echo $json_sono_nao; ?>, backgroundColor:'#e5e7eb' }
      ]
    },
    options:{ responsive:true, plugins:{ legend:{ position:'bottom', labels:{ font:{size:11} } } },
              scales:{ x:{stacked:true,grid:{display:false}}, y:{stacked:true,beginAtZero:true,ticks:{stepSize:1}} } }
  });
})();
<?php if ($periodo==='acumulado' && !empty($cumul_meses)): ?>
// Gráfico cumulativo por mês
(function(){
  var el = document.getElementById('chartCumul');
  if (!el) return;
  var meses  = <?php echo json_encode(array_map(fn($m)=>$meses_pt[substr($m,5,2)]??$m, array_keys($cumul_meses))); ?>;
  var totais = <?php echo json_encode(array_map(fn($r)=>(int)$r->total, array_values($cumul_meses))); ?>;
  var sono   = <?php echo json_encode(array_map(fn($r)=>(int)$r->sono, array_values($cumul_meses))); ?>;
  var feliz  = <?php echo json_encode(array_map(fn($r)=>(int)$r->feliz, array_values($cumul_meses))); ?>;
  var neg    = <?php echo json_encode(array_map(fn($r)=>(int)$r->triste+(int)$r->agitado, array_values($cumul_meses))); ?>;
  new Chart(el, {
    type: 'line',
    data: {
      labels: meses,
      datasets: [
        { label:'Registos', data:totais, borderColor:'#0f172a', backgroundColor:'#0f172a20', fill:true, tension:.3 },
        { label:'Dormiu',   data:sono,   borderColor:'#7c3aed', backgroundColor:'transparent', tension:.3 },
        { label:'Feliz',    data:feliz,  borderColor:'#16a34a', backgroundColor:'transparent', tension:.3, borderDash:[4,2] },
        { label:'Triste/Agitado', data:neg, borderColor:'#dc2626', backgroundColor:'transparent', tension:.3, borderDash:[4,2] }
      ]
    },
    options:{ responsive:true, plugins:{ legend:{ position:'bottom', labels:{ font:{size:11} } } },
              scales:{ y:{ beginAtZero:true, ticks:{ stepSize:1 } } } }
  });
})();
<?php endif; ?>
</script>
