<?php
/**
 * Módulo Jardim de Infância - Diário + Avaliação por Critérios
 * SIGE SoftGenial | SNE Moçambique
 *
 * v2.1 - Abril 2026
 * - SQL concatenation eliminada → $wpdb->prepare()
 * - date() → wp_date()
 * - Função jardim_calcular_nivel guardada
 * - CSS override: purple → navy (design system)
 */
if (!defined('ABSPATH')) exit;
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['jardim.diario_ver','jardim.diario_gerir'],
    ['sige_assistente','sige_director','sige_educador','sige_secretario','sige_secretaria_geral']
)) return;
global $wpdb;
$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
$ano     = sige_ano_lectivo_atual();
$user_id = get_current_user_id();
$tab     = sanitize_text_field($_GET['tab'] ?? 'diario');
// ── Turmas pré-escolar - fonte central v12.10.104 ──────────────────────
$turmas = function_exists('sige_jardim_get_preescolar_turmas_v104')
    ? sige_jardim_get_preescolar_turmas_v104((int)$escola_id, (int)$ano, true)
    : [];
$turma_sel = (int)($_GET['turma_id'] ?? ($turmas[0]->id ?? 0));
$data_sel  = sanitize_text_field($_GET['data_reg'] ?? wp_date('Y-m-d'));
$trim_sel  = max(1, min(3, (int)($_GET['trimestre'] ?? 1)));
// ── Alunos da turma - fonte central v12.10.104 ─────────────────────────────────────────
$alunos = [];
if ($turma_sel && function_exists('sige_jardim_get_alunos_activos_turma_v104')) {
    $alunos = sige_jardim_get_alunos_activos_turma_v104((int)$turma_sel, (int)$escola_id, (int)$ano, "a.id, a.nome_completo, a.foto, a.genero");
}
// ── Registos diário ──────────────────────────────────────────────────────────
$registos = [];
if ($turma_sel && $alunos) {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sige_jardim_diario
         WHERE turma_id=%d AND data_registo=%s AND ano_lectivo=%d AND escola_id=%d",
        $turma_sel, $data_sel, $ano, sige_get_escola_id()));
    foreach ($rows as $r) $registos[$r->aluno_id] = $r;
}
// ── Disciplinas/áreas da turma - fonte central v12.10.104 ──────────────
$discs = function_exists('sige_jardim_get_disciplinas_turma_v104')
    ? sige_jardim_get_disciplinas_turma_v104((int)$turma_sel, (int)$escola_id)
    : [];
// ── Critérios por disciplina ─────────────────────────────────────────────────
$criterios = [];
$tbl_crit = $wpdb->prefix . 'sige_jardim_criterios';
$crit_exists = (int)$wpdb->get_var("SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tbl_crit'");
if ($crit_exists && $discs) {
    $disc_ids = implode(',', array_map('intval', array_column($discs, 'id')));
    $crit_extra = '';
    if (function_exists('sige_jardim_column_exists_v104') && sige_jardim_column_exists_v104($tbl_crit, 'ativo')) {
        $crit_extra .= " AND (ativo IS NULL OR ativo=1)";
    }
    if (function_exists('sige_jardim_column_exists_v104') && sige_jardim_column_exists_v104($tbl_crit, 'ano_lectivo')) {
        $crit_extra .= " AND (ano_lectivo IS NULL OR ano_lectivo=0 OR ano_lectivo=" . (int)$ano . ")";
    }
    if (function_exists('sige_jardim_column_exists_v104') && sige_jardim_column_exists_v104($tbl_crit, 'trimestre')) {
        $crit_extra .= " AND (trimestre IS NULL OR trimestre=0 OR trimestre=" . (int)$trim_sel . ")";
    }
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $tbl_crit WHERE escola_id=%d AND disciplina_id IN ($disc_ids) {$crit_extra} ORDER BY disciplina_id, ordem ASC", $escola_id));
    foreach ($rows as $c) $criterios[$c->disciplina_id][] = $c;
}

$jardim_total_criterios_v106 = 0;
foreach ((array)$criterios as $__lista_crit_v106) {
    $jardim_total_criterios_v106 += count((array)$__lista_crit_v106);
}
// jardim_criterios_fonte_v106
// ── Respostas de critérios ───────────────────────────────────────────────────
$respostas = [];
$tbl_resp = $wpdb->prefix . 'sige_jardim_criterios_respostas';
$resp_exists = (int)$wpdb->get_var("SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tbl_resp'");
if ($resp_exists && $alunos) {
    $al_ids = implode(',', array_map('intval', array_column($alunos, 'id')));
    $resp_turma_extra = '';
    if (function_exists('sige_jardim_column_exists_v104') && sige_jardim_column_exists_v104($tbl_resp, 'turma_id')) {
        $resp_turma_extra = " AND (turma_id=" . (int)$turma_sel . " OR turma_id IS NULL OR turma_id=0)";
    }
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $tbl_resp
         WHERE escola_id=%d
           AND aluno_id IN ($al_ids)
           AND trimestre=%d
           AND ano_lectivo=%d
           {$resp_turma_extra}
         ORDER BY aluno_id ASC, criterio_id ASC, CASE WHEN turma_id=" . (int)$turma_sel . " THEN 1 ELSE 0 END ASC, id ASC",
        $escola_id, $trim_sel, $ano));
    foreach ($rows as $r) $respostas[$r->aluno_id][$r->criterio_id] = $r->status;
}
// ── Avaliações qualitativas guardadas ───────────────────────────────────────
$avaliacoes = [];
if ($alunos) {
    $al_ids = implode(',', array_map('intval', array_column($alunos, 'id')));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sige_jardim_avaliacoes
         WHERE aluno_id IN ($al_ids) AND turma_id=%d AND ano_lectivo=%d AND escola_id=%d", $turma_sel, $ano, $escola_id));
    foreach ($rows as $r) $avaliacoes[$r->aluno_id][$r->disciplina_id][$r->trimestre] = $r;
}
// ── Calcular nível automático ────────────────────────────────────────────────
if (!function_exists('jardim_calcular_nivel')) {
function jardim_calcular_nivel(array $crit_disc, array $resp_aluno): array {
    $total = count($crit_disc);
    if ($total === 0) return ['nivel'=>'','pct'=>0];
    $pts = 0.0;
    foreach ($crit_disc as $c) {
        $s = $resp_aluno[$c->id] ?? '';
        if ($s === 'atingido')     $pts += 1.0;
        elseif ($s === 'progresso') $pts += 0.5;
    }
    $pct   = ($pts / $total) * 100;
    $nivel = $pct >= 85 ? 'MB' : ($pct >= 70 ? 'B' : ($pct >= 50 ? 'S' : 'NS'));
    $algum = array_filter(array_column($crit_disc, 'id'), fn($id) => !empty($resp_aluno[$id]));
    if (!$algum) $nivel = '';
    return ['nivel'=>$nivel, 'pct'=>round($pct)];
}
}
$msg = '';
if (isset($_GET['saved'])) $msg = $_GET['saved'] == 1 ? 'success' : 'error';
if (isset($_GET['msg'])) {
    $m = sanitize_key((string)$_GET['msg']);
    if (in_array($m, ['ok','success','saved','guardado'], true)) $msg = 'success';
    if (in_array($m, ['erro','error','failed','falha'], true)) $msg = 'error';
}
$nivel_cores = [
    'MB'=>['var(--color-success-500)','var(--color-success-100)'],'B'=>['var(--color-info-500)','var(--sg-theme-soft,#f1edff)'],
    'S' =>['var(--color-warning-600)','var(--color-warning-100)'],'NS'=>['var(--color-danger-600)','var(--color-danger-100)'],
    ''  =>['var(--color-slate-400)','var(--color-slate-100)'],
];

$jardim_icon = static function (string $name): string {
    if (function_exists('sige_ui_icon')) {
        return sige_ui_icon($name);
    }
    $map = [
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'clipboard' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/>',
        'printer' => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/>',
        'health' => '<path d="M12 21s-6-4.35-9-8.35C.63 9.49 2.73 4 7 4c2.12 0 3.31 1.17 5 3 1.69-1.83 2.88-3 5-3 4.27 0 6.37 5.49 4 8.65C18 16.65 12 21 12 21Z"/>',
        'filter' => '<path d="M22 3H2l8 9.46V19l4 2v-8.54z"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
        'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    ];
    $path = $map[$name] ?? $map['heart'];
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};

$jardim_total_alunos     = count($alunos);
$jardim_diario_feito     = count($registos);
$jardim_total_disciplinas = count($discs);
$jardim_total_criterios  = array_sum(array_map('count', $criterios));
$jardim_turma_nome       = '';
foreach ($turmas as $jt) {
    if ((int)$jt->id === (int)$turma_sel) {
        $jardim_turma_nome = trim(($jt->nome ?: $jt->classe) . ' · ' . $jt->classe, ' ·');
        break;
    }
}

?>
<style id="sige-jardim-diario-produto-pro-v121098">
/* SIGE SoftGenial v12.10.98 - Jardim Diário: Compliance Visual Integral
   Escopo visual apenas: não altera regras pedagógicas, cálculos, handlers,
   notas, pagamentos, permissões ou base de dados. */
.sige-jardim-wrap{
    --jd-blue:var(--sg-theme-primary,var(--color-brand-500));
    --jd-blue-dark:var(--sg-theme-primary-800,var(--color-ink-700));
    --jd-purple:var(--color-brand-500);
    --jd-purple-soft:var(--color-brand-50);
    --jd-ink:var(--color-black);
    --jd-muted:var(--color-slate-700);
    --jd-line:var(--color-ink-100);
    --jd-green:var(--color-success-500);
    --jd-amber:var(--color-warning-500);
    --jd-red:var(--color-danger-500);
    width:100%;
    max-width:none!important;
    margin:0;
    padding:0 0 28px;
    display:flex;
    flex-direction:column;
    gap:18px;
    color:var(--jd-ink);
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif);
}
.sige-jardim-wrap *{box-sizing:border-box}
.sige-jardim-wrap svg{width:18px;height:18px;display:block;stroke:currentColor!important;color:currentColor!important;fill:none!important}

/* HERO - padrão Painel Principal */
.sige-jardim-hero{
    position:relative!important;
    overflow:hidden!important;
    min-height:178px!important;
    border-radius:var(--radius-xl)!important;
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-info-50) 100%)!important;
    border:1px solid rgba(92,64,187,.12)!important;
    box-shadow:var(--shadow-lg);
    padding:32px 34px!important;
    margin:0!important;
    color:var(--jd-ink)!important;
}
.sige-jardim-hero:before{content:"";position:absolute;inset:auto -80px -130px auto;width:420px;height:300px;border-radius:var(--radius-pill);background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);pointer-events:none}
.sige-jardim-hero-inner{
    position:relative;z-index:1;
    display:grid!important;
    grid-template-columns:minmax(0,1.04fr) minmax(320px,.96fr)!important;
    gap:22px!important;
    align-items:center!important;
}
.sige-jardim-hero-main{min-width:0}
.sige-jardim-hero-kicker{
    display:inline-flex!important;
    align-items:center!important;
    gap:var(--space-2)!important;
    margin:0 0 10px!important;
    padding:0!important;
    border:0!important;
    background:transparent!important;
    color:var(--jd-blue)!important;
    font-size:12px!important;
    line-height:1.2!important;
    font-weight:700!important;
    letter-spacing:.11em!important;
    text-transform:uppercase!important;
}
.sige-jardim-hero h1{
    margin:0!important;
    max-width:720px!important;
    color:var(--color-black)!important;
    font-size:31px!important;
    line-height:1.08!important;
    font-weight:700!important;
    letter-spacing:-.04em!important;
    font-family:inherit!important;
}
.sige-jardim-hero p{
    max-width:720px!important;
    margin:var(--space-3) 0 0!important;
    color:var(--color-slate-700)!important;
    font-size:15px!important;
    line-height:1.65!important;
    font-weight:500!important;
}
.sige-jardim-hero-chips{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
.sige-jardim-hero-chips span{
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
.sige-jardim-hero-chips svg{color:var(--jd-purple)}
.sige-jardim-hero-panel{
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
.sige-jardim-hero-panel:before{content:"";position:absolute;right:22px;bottom:16px;width:112px;height:92px;border-radius:22px 22px 12px 12px;background:rgba(109,93,252,.16);box-shadow:inset 0 0 0 2px rgba(109,93,252,.12)}
.sige-jardim-hero-panel>*{position:relative;z-index:1}
.sige-jardim-panel-label{display:flex;align-items:center;gap:var(--space-2);color:var(--jd-purple);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.11em}
.sige-jardim-panel-value{display:block;color:var(--color-ink-900);font-size:36px;line-height:1.05;font-weight:700;letter-spacing:-.045em}
.sige-jardim-panel-text{display:block;max-width:330px;color:var(--color-slate-600);font-size:var(--fs-sm);line-height:1.55;font-weight:600}
.sige-jardim-hero-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}
.sige-jardim-hero-actions a{
    min-height:42px!important;
    display:inline-flex!important;
    align-items:center!important;
    justify-content:center!important;
    gap:var(--space-2)!important;
    padding:0 var(--space-4)!important;
    border-radius:var(--radius-md)!important;
    font-size:12.5px!important;
    font-weight:700!important;
    cursor:pointer!important;
    text-decoration:none!important;
    border:1px solid transparent!important;
    transition:transform .18s ease,box-shadow .18s ease,background .18s ease!important;
}
.sige-jardim-hero-actions .btn-solid{background:linear-gradient(135deg,var(--jd-blue),var(--jd-blue-dark))!important;color:var(--color-white)!important;box-shadow:0 2px 8px rgba(15,23,42,.06)}
.sige-jardim-hero-actions .btn-ghost{background:var(--color-white)!important;color:var(--color-ink-900)!important;border-color:var(--color-ink-100)!important;box-shadow:0 2px 8px rgba(15,23,42,.06)}
.sige-jardim-hero-actions a:hover{transform:translateY(-1px)}

/* KPIs */
.sige-jardim-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:var(--space-4);margin:0}
.sige-jardim-kpi{
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
.sige-jardim-kpi:after{content:"";position:absolute;right:-28px;top:-34px;width:92px;height:92px;border-radius:50%;background:var(--kpi-soft,var(--color-brand-50))}
.sige-jardim-kpi-icon{
    width:52px;height:52px;border-radius:var(--radius-lg);
    display:flex;align-items:center;justify-content:center;
    background:var(--kpi-soft,var(--color-brand-50));
    color:var(--kpi-color,var(--color-brand-500));
    position:relative;z-index:1;
}
.sige-jardim-kpi-icon svg{width:24px;height:24px}
.sige-jardim-kpi > div{position:relative;z-index:1;min-width:0}
.sige-jardim-kpi .num{font-size:27px;font-weight:700;line-height:1;color:var(--color-black);letter-spacing:-.03em}
.sige-jardim-kpi .lbl{font-size:12px;color:var(--color-slate-600);margin-top:7px;font-weight:600}
.sige-jardim-kpi.alunos{--kpi-color:var(--color-brand-500);--kpi-soft:var(--color-brand-50)}
.sige-jardim-kpi.diario{--kpi-color:var(--color-success-500);--kpi-soft:var(--color-success-50)}
.sige-jardim-kpi.disciplinas{--kpi-color:var(--sg-theme-primary,var(--color-brand-500));--kpi-soft:var(--color-info-50)}
.sige-jardim-kpi.criterios{--kpi-color:var(--color-warning-500);--kpi-soft:var(--color-warning-50)}

/* Alertas e vazio */
.sige-jardim-alert{padding:13px 16px!important;border-radius:var(--radius-lg)!important;margin:0!important;font-weight:700!important;font-size:var(--fs-sm)!important;line-height:1.5!important;border:1px solid transparent!important;box-shadow:0 2px 8px rgba(15,23,42,.06)}
.sige-jardim-alert.success{background:var(--color-success-50)!important;color:var(--color-success-900)!important;border-color:var(--color-success-200)!important}
.sige-jardim-alert.error{background:var(--color-danger-50)!important;color:var(--color-danger-700)!important;border-color:var(--color-danger-200)!important}
.sige-jardim-empty{background:var(--color-white)!important;border-radius:var(--radius-xl)!important;padding:42px 24px!important;text-align:center!important;border:1px dashed var(--color-ink-200)!important;box-shadow:0 4px 16px rgba(15,23,42,.08)}
.sige-jardim-empty h3{color:var(--color-black)!important;font-size:var(--fs-lg)!important;font-weight:700!important;margin:0 0 8px!important}
.sige-jardim-empty p{color:var(--color-slate-500)!important;margin:0 0 var(--space-4)!important;font-size:var(--fs-sm)!important;line-height:1.55!important}
.sige-jardim-empty a{background:linear-gradient(135deg,var(--jd-blue),var(--jd-blue-dark))!important;color:var(--color-white)!important;border-radius:var(--radius-md)!important;padding:11px 18px!important;text-decoration:none!important;font-weight:700!important}

/* Toolbar e tabs */
.sige-jardim-toolbar{
    display:grid!important;
    grid-template-columns:repeat(4,minmax(160px,1fr)) auto!important;
    gap:var(--space-3)!important;
    align-items:end!important;
    background:var(--color-white)!important;
    padding:18px!important;
    border-radius:var(--radius-xl)!important;
    box-shadow:var(--shadow-md);
    margin:0!important;
    border:1px solid rgba(28,32,54,.08)!important;
}
.sige-jardim-toolbar label{font-size:var(--fs-xs)!important;font-weight:700!important;color:var(--color-slate-600)!important;display:block!important;margin:0 0 7px!important;text-transform:uppercase!important;letter-spacing:.07em!important}
.sige-jardim-toolbar select,.sige-jardim-toolbar input[type="date"]{
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
.sige-jardim-toolbar select:focus,.sige-jardim-toolbar input[type="date"]:focus{border-color:rgba(90,63,214,.55)!important;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.sige-jardim-toolbar button[type="submit"]{
    min-height:44px!important;
    display:inline-flex!important;
    align-items:center!important;
    justify-content:center!important;
    gap:var(--space-2)!important;
    background:linear-gradient(135deg,var(--jd-blue),var(--jd-blue-dark))!important;
    color:var(--color-white)!important;
    border:none!important;
    padding:0 18px!important;
    border-radius:var(--radius-md)!important;
    font-size:var(--fs-sm)!important;
    cursor:pointer!important;
    font-weight:700!important;
    box-shadow:var(--shadow-sm);
}
.sige-jardim-toolbar button:hover{transform:translateY(-1px)!important;box-shadow:0 4px 16px rgba(15,23,42,.08)}
.sige-jardim-tabs{
    display:flex!important;
    flex-wrap:wrap!important;
    gap:var(--space-3)!important;
    margin:0!important;
    background:transparent!important;
    border-radius:0!important;
    overflow:visible!important;
    box-shadow:none!important;
    border:0!important;
}
.sige-jardim-tabs a{
    flex:0 0 auto!important;
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
.sige-jardim-tabs a:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(15,23,42,.08)}
.sige-jardim-tabs a.active{background:linear-gradient(135deg,var(--jd-blue),var(--jd-blue-dark))!important;border-color:var(--jd-blue)!important;color:var(--color-white)!important;box-shadow:0 4px 16px rgba(15,23,42,.08)}

/* Cards do diário - override do legado inline */
.sige-jardim-card{
    background:var(--color-white)!important;
    border-radius:var(--radius-xl)!important;
    box-shadow:var(--shadow-md);
    overflow:hidden!important;
    border:1px solid rgba(28,32,54,.08)!important;
    border-left:0!important;
}
.sige-jardim-card > div:first-child{
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 72%,var(--color-slate-50) 100%)!important;
    border-bottom:1px solid var(--color-slate-100)!important;
    padding:var(--space-4) var(--space-5)!important;
}
.sige-jardim-card img{border-color:var(--color-ink-100)!important}
.sige-jardim-card div[style*="color:var(--color-brand-700)"]{color:var(--color-ink-500)!important;font-weight:700!important;font-size:15px!important}
.sige-jardim-card div[style*="color:var(--color-brand-500)"]{color:var(--color-slate-500)!important}
.sige-jardim-card > div[style*="display:grid;grid-template-columns:1fr 1fr 1fr"]{
    display:grid!important;
    grid-template-columns:repeat(3,minmax(0,1fr))!important;
    gap:14px!important;
    padding:18px 20px!important;
}
.sige-jardim-card label[style*="color:var(--color-brand-700)"]{color:var(--color-slate-600)!important;font-weight:700!important}
.sige-jardim-card textarea,.sige-jardim-card select,.sige-jardim-card input[type="number"]{
    border:1px solid var(--color-ink-100)!important;
    border-radius:var(--radius-md)!important;
    min-height:42px!important;
    font-weight:600!important;
    color:var(--color-ink-500)!important;
    box-shadow:var(--shadow-sm);
}
.sige-jardim-card textarea:focus,.sige-jardim-card select:focus,.sige-jardim-card input[type="number"]:focus{outline:none!important;border-color:rgba(90,63,214,.55)!important;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.sige-jardim-card .crit-row{background:var(--color-slate-50)!important;border:1px solid var(--color-slate-100)!important;border-radius:var(--radius-md)!important;padding:8px 10px!important}
.sige-jardim-card div[style*="border:1px solid var(--color-brand-100)"]{border-color:var(--color-slate-100)!important;border-radius:var(--radius-lg)!important;background:var(--color-white)!important;box-shadow:0 2px 8px rgba(15,23,42,.06)}
.sige-jardim-card span[style*="background:var(--color-success-100)"],.sige-jardim-card span[style*="background:var(--color-warning-200)"]{font-weight:700!important;border-radius:999px!important}
.sige-jardim-card > div[style*="grid-template-columns:1fr 1fr"]{
    display:grid!important;
    grid-template-columns:repeat(2,minmax(0,1fr))!important;
    gap:var(--space-4)!important;
    padding:18px 20px!important;
}
.sige-jardim-card button,.sige-jardim-wrap button[style*="background:var(--color-brand-500)"]{
    background:linear-gradient(135deg,var(--jd-blue),var(--jd-blue-dark))!important;
    color:var(--color-white)!important;
    border:none!important;
    border-radius:var(--radius-md)!important;
    min-height:44px!important;
    padding:0 18px!important;
    font-weight:700!important;
    cursor:pointer!important;
    box-shadow:var(--shadow-sm);
}
.sige-jardim-wrap a[style*="background:var(--color-ink-50)"]{
    display:inline-flex!important;
    align-items:center!important;
    justify-content:center!important;
    gap:var(--space-2)!important;
    min-height:44px!important;
    border-radius:var(--radius-md)!important;
    background:var(--color-white)!important;
    color:var(--color-ink-900)!important;
    border:1px solid var(--color-ink-100)!important;
    box-shadow:var(--shadow-sm);
}
.sige-jardim-wrap div[style*="background:white;border-radius:var(--radius-lg);padding:30px"]{
    border-radius:var(--radius-xl)!important;
    border:1px solid rgba(28,32,54,.08)!important;
    box-shadow:var(--shadow-md);
    color:var(--color-slate-500)!important;
}

/* Legenda */
.sige-jardim-wrap div[style*="Cálculo automático"]{border-radius:22px!important}
.sige-jardim-wrap div[style*="background:white;border-radius:8px"]{
    border-radius:var(--radius-xl)!important;
    border:1px solid rgba(28,32,54,.08)!important;
    box-shadow:var(--shadow-md);
}


/* Aplicação rápida e pop-up Produto PRO - v12.10.112 */
.sige-jardim-bulk-panel{background:var(--color-white);border:1px solid rgba(28,32,54,.08);border-left:4px solid var(--sg-theme-primary,var(--color-brand-500));border-radius:var(--radius-xl);box-shadow:var(--shadow-md);padding:18px 20px;margin:0 0 var(--space-4);display:grid;gap:14px}
.sige-jardim-bulk-title{display:flex;align-items:center;gap:10px;color:var(--color-ink-500);font-size:15px;font-weight:700;margin:0}.sige-jardim-bulk-text{margin:var(--space-1) 0 0;color:var(--color-slate-500);font-size:var(--fs-sm);line-height:1.55;font-weight:600}.sige-jardim-bulk-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--space-3);align-items:end}.sige-jardim-bulk-grid label{font-size:var(--fs-xs);font-weight:700;color:var(--color-slate-600);text-transform:uppercase;letter-spacing:.07em;display:flex;flex-direction:column;gap:7px}.sige-jardim-bulk-grid input,.sige-jardim-bulk-grid select,.sige-jardim-bulk-grid textarea{width:100%;min-height:42px;border:1px solid var(--color-ink-100);border-radius:var(--radius-md);padding:var(--space-2) var(--space-3);font-size:var(--fs-sm);font-weight:600;color:var(--color-ink-500);background:var(--color-white);box-shadow:var(--shadow-sm);outline:none}.sige-jardim-bulk-grid textarea{min-height:68px;resize:vertical}.sige-jardim-bulk-action{min-height:44px!important;background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-800,var(--color-ink-700)))!important;color:var(--color-white)!important;border:none!important;border-radius:var(--radius-md)!important;padding:0 18px!important;font-weight:700!important;box-shadow:var(--shadow-sm);cursor:pointer!important}.sige-jardim-bulk-action.secondary{background:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-700))!important}.sige-pro-popup-backdrop{position:fixed;inset:0;z-index:999999;display:flex;align-items:center;justify-content:center;padding:22px;background:rgba(15,23,42,.42);backdrop-filter:blur(5px)}.sige-pro-popup-card{width:min(520px,100%);display:grid;grid-template-columns:auto minmax(0,1fr);gap:15px;align-items:flex-start;padding:22px;border-radius:var(--radius-xl);border:1px solid rgba(255,255,255,.70);background:var(--color-white);box-shadow:var(--shadow-lg);color:var(--color-ink-500);position:relative;animation:sigeJPopup .22s ease-out both}.sige-pro-popup-icon{width:52px;height:52px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;background:var(--color-success-100);color:var(--color-success-500)}.sige-pro-popup-card.is-error .sige-pro-popup-icon{background:var(--color-danger-100);color:var(--color-danger-600)}.sige-pro-popup-icon svg{width:24px!important;height:24px!important}.sige-pro-popup-card strong{display:block;margin:2px 42px 6px 0;font-size:18px;line-height:1.18;font-weight:700;color:inherit}.sige-pro-popup-card p{margin:0;color:var(--color-slate-700);font-size:var(--fs-base);line-height:1.55;font-weight:600}.sige-pro-popup-close{position:absolute;top:13px;right:13px;width:34px;height:34px;border-radius:var(--radius-md);border:1px solid rgba(15,23,42,.08);background:var(--color-white);cursor:pointer;color:var(--color-ink-500);font-size:var(--fs-lg);font-weight:700;box-shadow:0 2px 8px rgba(15,23,42,.06)}@keyframes sigeJPopup{from{opacity:0;transform:translateY(10px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
@media(max-width:1180px){.sige-jardim-bulk-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:760px){.sige-jardim-bulk-grid{grid-template-columns:1fr}.sige-pro-popup-backdrop{align-items:flex-end;padding:14px}.sige-pro-popup-card{grid-template-columns:1fr;text-align:center}.sige-pro-popup-icon{margin:0 auto}.sige-pro-popup-card strong{margin-right:0}}

/* Responsividade */
@media(max-width:1280px){
    .sige-jardim-toolbar{grid-template-columns:repeat(3,minmax(160px,1fr))!important}
    .sige-jardim-toolbar button[type="submit"]{grid-column:1 / -1!important}
}
@media(max-width:1100px){
    .sige-jardim-hero-inner{grid-template-columns:1fr!important}
    .sige-jardim-card > div[style*="display:grid;grid-template-columns:1fr 1fr 1fr"]{grid-template-columns:1fr 1fr!important}
    .sige-jardim-card > div[style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr!important}
}
@media(max-width:760px){
    .sige-jardim-hero{padding:26px 22px!important}
    .sige-jardim-hero h1{font-size:24px!important}
    .sige-jardim-toolbar{grid-template-columns:1fr!important}
    .sige-jardim-tabs{display:grid!important;grid-template-columns:1fr!important}
    .sige-jardim-tabs a{width:100%!important}
    .sige-jardim-kpis{grid-template-columns:1fr!important}
    .sige-jardim-card > div:first-child{align-items:flex-start!important}
    .sige-jardim-card > div[style*="display:grid;grid-template-columns:1fr 1fr 1fr"]{grid-template-columns:1fr!important}
    .sige-jardim-wrap div[style*="display:flex;justify-content:space-between;align-items:center;margin-top"]{display:grid!important;grid-template-columns:1fr!important;gap:10px!important}
    .sige-jardim-wrap button[type="submit"],.sige-jardim-wrap a{width:100%!important}
}

.sige-jardim-wrap .sg-bulk-applied{background:linear-gradient(135deg,var(--color-success-500),var(--color-success-800))!important;border-color:var(--color-success-500)!important;color:var(--color-white)!important;}

.sige-jardim-wrap [data-jd-bulk-minutos-wrap].is-hidden,
.sige-jardim-wrap [data-jd-minutos-input].is-hidden{
    display:none!important;
}
</style>
<div class="wrap sige-jardim-wrap">

<!-- HERO -->
<section class="sige-jardim-hero" aria-label="Diário do Jardim">
  <div class="sige-jardim-hero-inner">
    <div class="sige-jardim-hero-main">
      <div class="sige-jardim-hero-kicker">
        <?php echo $jardim_icon('heart'); ?>
        <span>Pré-Escolar</span>
      </div>
      <h1>Diário do Jardim</h1>
      <p>Registe actividades diárias, acompanhe comportamento, sono, recados aos pais e avaliação por critérios do pré-escolar.</p>
      <div class="sige-jardim-hero-chips">
        <span><?php echo $jardim_icon('calendar'); ?> Ano Lectivo <?php echo esc_html($ano); ?></span>
        <?php if ($jardim_turma_nome): ?><span><?php echo $jardim_icon('users'); ?> <?php echo esc_html($jardim_turma_nome); ?></span><?php endif; ?>
        <span><?php echo $jardim_icon('book'); ?> <?php echo $tab === 'diario' ? 'Diário de actividades' : 'Avaliação por critérios'; ?></span>
      </div>
      <div class="sige-jardim-hero-actions">
        <a href="?page=sige-app&view=jardim_saude<?php echo $turma_sel?'&turma_id='.$turma_sel:''; ?>" class="btn-ghost">
          <?php echo $jardim_icon('health'); ?> Saúde
        </a>
        <a href="?page=sige-app&view=jardim_boletim<?php echo $turma_sel?'&turma_id='.$turma_sel:''; ?>&trimestre=<?php echo esc_attr($trim_sel); ?>" class="btn-solid">
          <?php echo $jardim_icon('printer'); ?> Boletim
        </a>
      </div>
    </div>
    <aside class="sige-jardim-hero-panel" aria-label="Estado do diário">
      <div class="sige-jardim-panel-label"><?php echo $jardim_icon('activity'); ?><span>Estado</span></div>
      <strong class="sige-jardim-panel-value"><?php echo esc_html((string)$jardim_diario_feito); ?>/<?php echo esc_html((string)$jardim_total_alunos); ?></strong>
      <span class="sige-jardim-panel-text">Registo(s) diário(s) já preenchidos para a turma e data seleccionadas.</span>
    </aside>
  </div>
</section>

<?php if ($msg === 'success' || $msg === 'error'): ?>
  <div class="sige-pro-popup-backdrop" role="presentation">
    <div class="sige-pro-popup-card <?php echo $msg === 'error' ? 'is-error' : ''; ?>" role="dialog" aria-modal="true" aria-live="polite">
      <span class="sige-pro-popup-icon"><?php echo $jardim_icon($msg === 'error' ? 'alert' : 'check'); ?></span>
      <div>
        <strong><?php echo $msg === 'success' ? 'Guardado com sucesso' : 'Não foi possível guardar'; ?></strong>
        <p><?php echo $msg === 'success' ? ($tab === 'avaliacao' ? 'Avaliação por critérios guardada com sucesso.' : 'Diário de actividades guardado com sucesso.') : 'Confirme se há alunos activos, dados preenchidos e tente novamente.'; ?></p>
      </div>
      <button type="button" class="sige-pro-popup-close" aria-label="Fechar aviso" data-sige-act="sigeFecharPopupBackdrop">×</button>
    </div>
  </div>
<?php endif; ?>

<?php if (empty($turmas)): ?>
  <div class="sige-jardim-empty">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
    <h3>Nenhuma turma Pré-Escolar encontrada</h3>
    <p>Crie uma turma ou execute o SQL de migração para marcar turmas como pré-escolar.</p>
    <a href="?page=sige-app&view=turmas">Ir para Turmas</a>
  </div>
<?php else: ?>

<!-- FILTROS -->
<form method="get" class="sige-jardim-toolbar">
  <input type="hidden" name="page" value="sige-app">
  <input type="hidden" name="view" value="jardim_diario">
  <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">
  <label>Turma:</label>
  <select name="turma_id">
    <?php foreach ($turmas as $t): ?>
      <option value="<?php echo $t->id; ?>" <?php selected($t->id,$turma_sel); ?>><?php echo esc_html(($t->nome?:$t->classe).' - '.$t->classe); ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($tab === 'diario'): ?>
    <label>Data:</label>
    <input type="date" name="data_reg" value="<?php echo esc_attr($data_sel); ?>" max="<?php echo wp_date('Y-m-d'); ?>">
  <?php else: ?>
    <label>Trimestre:</label>
    <select name="trimestre">
      <?php foreach([1=>'1º Trimestre',2=>'2º Trimestre',3=>'3º Trimestre'] as $v=>$l): ?>
        <option value="<?php echo esc_attr($v); ?>" <?php selected($v,$trim_sel); ?>><?php echo $l; ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>
  <button type="submit" class="sgk-btn sgk-btn-sec">
    <?php echo $jardim_icon('filter'); ?>
    Carregar
  </button>
</form>

<!-- TABS -->
<div class="sige-jardim-tabs">
  <?php
  $t1_url = "?page=sige-app&view=jardim_diario&turma_id=$turma_sel&data_reg=".urlencode($data_sel)."&tab=diario";
  $t2_url = "?page=sige-app&view=jardim_diario&turma_id=$turma_sel&trimestre=$trim_sel&tab=avaliacao";
  ?>
  <a href="<?php echo $t1_url; ?>" class="<?php echo $tab==='diario'?'active':''; ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    Diário de Actividades
  </a>
  <a href="<?php echo $t2_url; ?>" class="<?php echo $tab==='avaliacao'?'active':''; ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
    Avaliação por Critérios
  </a>
</div>

<?php if (empty($alunos)): ?>
  <div style="background:white;border-radius:16px;padding:30px;text-align:center;color:var(--color-slate-500);box-shadow:0 4px 12px rgba(13,18,89,.06);">Nenhum aluno matriculado nesta turma.</div>
<?php elseif ($tab === 'diario'): ?>
<!-- ═══ TAB DIÁRIO ═══ -->
<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
  <input type="hidden" name="action" value="sige_jardim_diario_salvar">
  <input type="hidden" name="turma_id" value="<?php echo esc_attr($turma_sel); ?>">
  <input type="hidden" name="data_registo" value="<?php echo esc_attr($data_sel); ?>">
  <input type="hidden" name="ano_lectivo" value="<?php echo esc_attr($ano); ?>">
  <?php wp_nonce_field('sige_jardim_diario_nonce','_jardim_nonce'); ?>
  <section class="sige-jardim-bulk-panel" aria-label="Aplicar padrão do diário a todos">
    <div>
      <div class="sige-jardim-bulk-title"><?php echo $jardim_icon('save'); ?> Aplicar padrão a todos</div>
      <p class="sige-jardim-bulk-text">Preencha o que normalmente acontece com a turma, clique em aplicar e depois ajuste apenas a criança que teve uma situação diferente.</p>
    </div>
    <div class="sige-jardim-bulk-grid">
      <label>Actividades<textarea data-jd-bulk="actividades" rows="2" placeholder="Ex.: pintura, música, história..."></textarea></label>
      <label>Humor<select data-jd-bulk="humor"><option value="">- manter vazio -</option><option value="feliz">😄 Feliz</option><option value="calmo">😌 Calmo/a</option><option value="triste">😢 Triste</option><option value="agitado">😤 Agitado/a</option><option value="cansado">😴 Cansado/a</option></select></label>
      <label>Comportamento<select data-jd-bulk="comportamento"><option value="">- manter vazio -</option><option value="excelente">⭐⭐⭐ Excelente</option><option value="bom">⭐⭐ Bom</option><option value="satisfatorio">⭐ Satisfatório</option><option value="necessita_atencao">⚠️ Necessita atenção</option></select></label>
      <label>Sono<select data-jd-bulk="dormiu"><option value="">- manter vazio -</option><option value="1">Dormiu</option><option value="0">Não dormiu</option></select></label>
      <label data-jd-bulk-minutos-wrap>Minutos de sono<input type="number" min="0" max="180" data-jd-bulk="minutos_sono" placeholder="Ex.: 60"></label>
      <label>Obs. comportamento<textarea data-jd-bulk="obs_comportamento" rows="2" placeholder="Opcional"></textarea></label>
      <label>Recado aos pais<textarea data-jd-bulk="recado_pais" rows="2" placeholder="Opcional"></textarea></label>
      <button type="button" class="sige-jardim-bulk-action" data-jd-apply-all>Aplicar a todos</button>
    </div>
  </section>
  <div style="display:grid;gap:14px;">
  <?php foreach ($alunos as $aluno):
    $r    = $registos[$aluno->id] ?? null;
    $foto = $aluno->foto ?: 'https://ui-avatars.com/api/?name='.urlencode($aluno->nome_completo).'&background=7c3aed&color=fff&size=60';
    $n    = 'diario['.$aluno->id.']';
  ?>
    <div class="sige-jardim-card" style="background:white;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.07);overflow:hidden;border-left:4px solid var(--color-brand-400);">
      <div style="display:flex;align-items:center;gap:14px;padding:12px 20px;background:var(--color-brand-50);border-bottom:1px solid var(--color-brand-100);">
        <img src="<?php echo esc_url($foto); ?>" style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid var(--color-brand-200);">
        <div>
          <div style="font-weight:700;color:var(--color-brand-700);font-size:14px;"><?php echo esc_html($aluno->nome_completo); ?></div>
          <div style="font-size:11px;color:var(--color-brand-500);"><?php echo $aluno->genero==='M'?'👦':'👧'; ?></div>
        </div>
        <div style="margin-left:auto;">
          <?php if ($r): ?>
            <span style="background:var(--color-success-100);color:var(--color-success-900);padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;">✅ Registado</span>
          <?php else: ?>
            <span style="background:var(--color-warning-200);color:var(--color-warning-800);padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;">⏳ Por registar</span>
          <?php endif; ?>
        </div>
      </div>
      <div style="padding:14px 20px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
        <div style="grid-column:1/-1;">
          <label style="font-size:11px;font-weight:700;color:var(--color-brand-700);text-transform:uppercase;letter-spacing:.5px;">🎨 Actividades do Dia</label>
          <textarea name="<?php echo $n; ?>[actividades]" rows="2" placeholder="Música, pintura, jogo simbólico, história..."
            style="width:100%;margin-top:4px;padding:8px;border:1px solid var(--color-brand-200);border-radius:8px;font-size:13px;resize:vertical;box-sizing:border-box;"><?php echo esc_textarea($r->actividades??''); ?></textarea>
        </div>
        <div>
          <label style="font-size:11px;font-weight:700;color:var(--color-brand-700);text-transform:uppercase;letter-spacing:.5px;">😊 Humor</label>
          <select name="<?php echo $n; ?>[humor]" style="width:100%;margin-top:4px;padding:7px;border:1px solid var(--color-brand-200);border-radius:8px;font-size:13px;">
            <option value="">-</option>
            <?php foreach(['feliz'=>'😄 Feliz','calmo'=>'😌 Calmo/a','triste'=>'😢 Triste','agitado'=>'😤 Agitado/a','cansado'=>'😴 Cansado/a'] as $k=>$v):
              echo '<option value="'.esc_attr($k).'"'.selected($r->humor??'',$k,false).'>'.esc_html($v).'</option>';
            endforeach; ?>
          </select>
        </div>
        <div>
          <label style="font-size:11px;font-weight:700;color:var(--color-brand-700);text-transform:uppercase;letter-spacing:.5px;">⭐ Comportamento</label>
          <select name="<?php echo $n; ?>[comportamento]" style="width:100%;margin-top:4px;padding:7px;border:1px solid var(--color-brand-200);border-radius:8px;font-size:13px;">
            <option value="">-</option>
            <?php foreach(['excelente'=>'⭐⭐⭐ Excelente','bom'=>'⭐⭐ Bom','satisfatorio'=>'⭐ Satisfatório','necessita_atencao'=>'⚠️ Necessita Atenção'] as $k=>$v):
              echo '<option value="'.esc_attr($k).'"'.selected($r->comportamento??'',$k,false).'>'.esc_html($v).'</option>';
            endforeach; ?>
          </select>
        </div>
        <div>
          <label style="font-size:11px;font-weight:700;color:var(--color-brand-700);text-transform:uppercase;letter-spacing:.5px;">💤 Sono</label>
          <div style="display:flex;gap:10px;margin-top:6px;">
            <label style="font-size:13px;display:flex;align-items:center;gap:4px;cursor:pointer;"><input type="radio" name="<?php echo $n; ?>[dormiu]" value="1" <?php checked($r->dormiu??null,'1'); ?>> Sim</label>
            <label style="font-size:13px;display:flex;align-items:center;gap:4px;cursor:pointer;"><input type="radio" name="<?php echo $n; ?>[dormiu]" value="0" <?php checked($r->dormiu??null,'0'); ?>> Não</label>
          </div>
          <input type="number" name="<?php echo $n; ?>[minutos_sono]" data-jd-minutos-input min="0" max="180" value="<?php echo esc_attr($r->minutos_sono??''); ?>" placeholder="Minutos"
            style="width:100%;margin-top:6px;padding:7px;border:1px solid var(--color-brand-200);border-radius:8px;font-size:13px;box-sizing:border-box;">
        </div>
        <div>
          <label style="font-size:11px;font-weight:700;color:var(--color-brand-700);text-transform:uppercase;letter-spacing:.5px;">📝 Obs. Comportamento</label>
          <textarea name="<?php echo $n; ?>[obs_comportamento]" rows="2" placeholder="Observações..."
            style="width:100%;margin-top:4px;padding:8px;border:1px solid var(--color-brand-200);border-radius:8px;font-size:13px;resize:vertical;box-sizing:border-box;"><?php echo esc_textarea($r->obs_comportamento??''); ?></textarea>
        </div>
        <div>
          <label style="font-size:11px;font-weight:700;color:var(--color-brand-700);text-transform:uppercase;letter-spacing:.5px;">📨 Recado para os Pais</label>
          <textarea name="<?php echo $n; ?>[recado_pais]" rows="2" placeholder="Avisos, pedidos de material..."
            style="width:100%;margin-top:4px;padding:8px;border:1px solid var(--color-brand-200);border-radius:8px;font-size:13px;resize:vertical;box-sizing:border-box;"><?php echo esc_textarea($r->recado_pais??''); ?></textarea>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
  <div style="text-align:right;margin-top:18px;padding-bottom:30px;">
    <button type="submit" class="sgk-btn sgk-btn-primario" style="background:var(--color-brand-500);border-color:var(--color-brand-500);padding:11px 28px;font-size:14px;font-weight:700;">
      💾 Guardar Diário - <?php echo wp_date('d/m/Y',strtotime($data_sel)); ?>
    </button>
  </div>
</form>
<?php else: ?>
<!-- ═══ TAB AVALIAÇÃO POR CRITÉRIOS ═══ -->
<?php if (empty($criterios)): ?>
  <div style="background:var(--color-warning-200);border:1px solid var(--color-warning-300);border-radius:10px;padding:24px;text-align:center;color:var(--color-warning-800);">
    <strong>⚠️ Critérios não carregados.</strong><br>
    Os critérios ainda não estão configurados para esta turma/disciplina. A estrutura PRO já está preparada; configure/importa os critérios pelo fluxo institucional do sistema, sem necessidade de phpMyAdmin.
  </div>
<?php else: ?>
<!-- Legenda da escala -->
<div style="background:white;border-radius:10px;padding:14px 20px;margin-bottom:16px;display:flex;gap:16px;align-items:center;flex-wrap:wrap;box-shadow:0 2px 8px rgba(0,0,0,.06);">
  <span style="font-size:13px;font-weight:700;color:var(--color-slate-800);">Cálculo automático:</span>
  <?php foreach(['MB'=>['var(--color-success-500)','≥85%'],'B'=>['var(--color-info-500)','70-84%'],'S'=>['var(--color-warning-700)','50-69%'],'NS'=>['var(--color-danger-600)','<50%']] as $nv=>[$cor,$pct]): ?>
    <span style="background:<?php echo $cor; ?>15;color:<?php echo $cor; ?>;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;"><?php echo $nv; ?> = <?php echo $pct; ?></span>
  <?php endforeach; ?>
  <span style="font-size:11px;color:var(--color-slate-400);margin-left:auto;">✅=1pt &nbsp; 🔄=0.5pt &nbsp; ❌=0pt</span>
</div>
<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
  <input type="hidden" name="action" value="sige_jardim_criterios_salvar">
  <input type="hidden" name="turma_id" value="<?php echo esc_attr($turma_sel); ?>">
  <input type="hidden" name="trimestre" value="<?php echo esc_attr($trim_sel); ?>">
  <input type="hidden" name="ano_lectivo" value="<?php echo esc_attr($ano); ?>">
  <?php wp_nonce_field('sige_jardim_criterios_nonce','_jcrit_nonce'); ?>
  <section class="sige-jardim-bulk-panel" aria-label="Aplicar avaliação por critérios a todos">
    <div>
      <div class="sige-jardim-bulk-title"><?php echo $jardim_icon('clipboard'); ?> Aplicar avaliação a todos</div>
      <p class="sige-jardim-bulk-text">Escolha o estado padrão dos critérios da turma e depois ajuste apenas os critérios/alunos com situação diferente.</p>
    </div>
    <div class="sige-jardim-bulk-grid">
      <label>Estado padrão<select data-jcrit-bulk-status><option value="atingido">✅ Atingido</option><option value="progresso">🔄 Em progresso</option><option value="nao_atingido">❌ Não atingido</option></select></label>
      <button type="button" class="sige-jardim-bulk-action secondary" data-jcrit-apply-all>Aplicar a todos os critérios</button>
    </div>
  </section>
  <?php foreach ($alunos as $aluno):
    $foto       = $aluno->foto ?: 'https://ui-avatars.com/api/?name='.urlencode($aluno->nome_completo).'&background=7c3aed&color=fff&size=50';
    $resp_aluno = $respostas[$aluno->id] ?? [];
  ?>
  <div class="sige-jardim-card" style="background:white;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.07);margin-bottom:16px;overflow:hidden;">
    <!-- Cabeçalho: nome + resumo de níveis -->
    <div style="display:flex;align-items:center;gap:14px;padding:14px 20px;background:var(--color-brand-50);border-bottom:2px solid var(--color-brand-100);flex-wrap:wrap;">
      <img src="<?php echo esc_url($foto); ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid var(--color-brand-200);">
      <div style="font-weight:700;color:var(--color-brand-700);font-size:14px;"><?php echo esc_html($aluno->nome_completo); ?></div>
      <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;">
        <?php foreach ($discs as $d):
          $crit_d = $criterios[$d->id] ?? [];
          if (!$crit_d) continue;
          $calc = jardim_calcular_nivel($crit_d, $resp_aluno);
          [$cor,$bg] = $nivel_cores[$calc['nivel']] ?? ['var(--color-slate-400)','var(--color-ink-50)'];
        ?>
          <span style="background:<?php echo $bg; ?>;color:<?php echo $cor; ?>;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;"
                title="<?php echo esc_attr($d->nome.': '.$calc['pct'].'%'); ?>">
            <?php echo esc_html($d->sigla); ?>: <?php echo $calc['nivel'] ?: '-'; ?>
          </span>
        <?php endforeach; ?>
      </div>
    </div>
    <!-- Grid de critérios por disciplina: 2 por linha -->
    <div style="padding:16px 20px;display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <?php foreach ($discs as $d):
      $crit_d = $criterios[$d->id] ?? [];
      if (!$crit_d) continue;
      $calc    = jardim_calcular_nivel($crit_d, $resp_aluno);
      [$cor,$bg] = $nivel_cores[$calc['nivel']] ?? ['var(--color-slate-400)','var(--color-ink-50)'];
      $av_exist = $avaliacoes[$aluno->id][$d->id][$trim_sel] ?? null;
    ?>
      <div style="border:1px solid var(--color-brand-100);border-radius:10px;padding:14px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
          <div>
            <span style="font-weight:700;color:var(--color-brand-700);font-size:13px;"><?php echo esc_html($d->nome); ?></span>
            <span style="font-size:11px;color:var(--color-slate-400);margin-left:6px;">(<?php echo esc_html($d->sigla); ?>)</span>
          </div>
          <span style="background:<?php echo $bg; ?>;color:<?php echo $cor; ?>;padding:3px 12px;border-radius:20px;font-size:12px;font-weight:700;">
            <?php echo $calc['nivel'] ?: '-'; ?>
            <?php if ($calc['nivel']): ?>(<?php echo $calc['pct']; ?>%)<?php endif; ?>
          </span>
        </div>
        <!-- Critérios como checklist visual -->
        <?php foreach ($crit_d as $crit):
          $status = $resp_aluno[$crit->id] ?? '';
          $fname  = "resp[{$aluno->id}][{$crit->id}]";
          $crit_uid = 'c'.$aluno->id.'_'.$crit->id;
        ?>
        <div class="crit-row" style="display:flex;align-items:center;gap:8px;margin-bottom:7px;padding:6px 8px;background:var(--color-brand-50);border-radius:8px;">
          <!-- Botões de status: JS actualiza visual ao clicar -->
          <?php foreach([
            'atingido'     =>['✅','var(--color-success-500)','Atingido'],
            'progresso'    =>['🔄','var(--color-warning-700)','Em progresso'],
            'nao_atingido' =>['❌','var(--color-danger-600)','Não atingido'],
          ] as $sv=>[$ic,$sc,$sl]): ?>
            <label
              data-color="<?php echo $sc; ?>"
              data-val="<?php echo $sv; ?>"
              data-group="<?php echo esc_attr($crit_uid); ?>"
              title="<?php echo $sl; ?>"
              style="cursor:pointer;padding:4px 8px;border-radius:6px;font-size:14px;line-height:1;
                border:2px solid <?php echo $status===$sv?$sc:'var(--color-ink-100)'; ?>;
                background:<?php echo $status===$sv?$sc.'20':'white'; ?>;
                user-select:none;transition:border .15s,background .15s;">
              <input type="radio" name="<?php echo esc_attr($fname); ?>" value="<?php echo $sv; ?>"
                <?php checked($status,$sv); ?> style="display:none;">
              <?php echo $ic; ?>
            </label>
          <?php endforeach; ?>
          <span style="font-size:12px;color:var(--color-slate-800);flex:1;line-height:1.4;"><?php echo esc_html($crit->descricao); ?></span>
        </div>
        <?php endforeach; ?>
        <!-- Comentário para o boletim -->
        <div style="margin-top:10px;">
          <label style="font-size:11px;font-weight:700;color:var(--color-brand-700);text-transform:uppercase;letter-spacing:.5px;">💬 Comentário (boletim)</label>
          <textarea name="coment[<?php echo $aluno->id; ?>][<?php echo $d->id; ?>]" rows="2"
            placeholder="Observação para constar no boletim do aluno..."
            style="width:100%;margin-top:4px;padding:7px;border:1px solid var(--color-info-100);border-radius:8px;font-size:12px;resize:vertical;box-sizing:border-box;"><?php echo esc_textarea($av_exist->comentario_trimestre??''); ?></textarea>
        </div>
      </div>
    <?php endforeach; // discs ?>
    </div>
  </div>
  <?php endforeach; // alunos ?>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:4px;padding-bottom:30px;">
    <a href="?page=sige-app&view=jardim_boletim&turma_id=<?php echo esc_attr($turma_sel); ?>&trimestre=<?php echo esc_attr($trim_sel); ?>"
       target="_blank"
       style="background:var(--color-ink-50);color:var(--color-slate-800);padding:11px 22px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;">
      🖨️ Pré-visualizar Boletim
    </a>
    <button type="submit" class="sgk-btn sgk-btn-primario" style="background:var(--color-brand-500);border-color:var(--color-brand-500);padding:11px 28px;font-size:14px;font-weight:700;">
      💾 Guardar - <?php echo ['','1º','2º','3º'][$trim_sel]; ?> Trimestre
    </button>
  </div>
</form>
<?php endif; // criterios ?>
<?php endif; // tab avaliacao ?>
<?php endif; // alunos ?>
<script <?php echo sige_csp_script_attr(); ?>>
(function(){
  function formFields(form, prefix, field){
    var suffix = '[' + field + ']';
    return Array.prototype.filter.call(form.elements, function(el){
      return el.name && el.name.indexOf(prefix + '[') === 0 && el.name.slice(-suffix.length) === suffix;
    });
  }

  function setFieldValue(el, value){
    if (!el) return false;

    if (el.type === 'checkbox') {
      el.checked = value === '1' || value === 1 || value === true || value === 'true';
    } else if (el.type === 'radio') {
      el.checked = String(el.value) === String(value);
    } else {
      el.value = value;
    }

    try {
      el.dispatchEvent(new Event('input', {bubbles:true}));
      el.dispatchEvent(new Event('change', {bubbles:true}));
    } catch(e) {}

    return true;
  }

  function setValues(form, prefix, field, value){
    if (value === null || typeof value === 'undefined') return 0;
    var count = 0;

    formFields(form, prefix, field).forEach(function(el){
      if (setFieldValue(el, value)) count++;
    });

    return count;
  }

  function flashBulk(btn, message, count){
    var old = btn.getAttribute('data-original-text') || btn.textContent;
    btn.setAttribute('data-original-text', old);
    btn.textContent = message + (typeof count === 'number' ? ' (' + count + ')' : '');
    btn.classList.add('sg-bulk-applied');

    setTimeout(function(){
      btn.textContent = old;
      btn.classList.remove('sg-bulk-applied');
    }, 2200);
  }

  function toggleBulkMinutos(form){
    if (!form) return;

    var dormiu = form.querySelector('[data-jd-bulk="dormiu"]');
    var wrap = form.querySelector('[data-jd-bulk-minutos-wrap]');
    var input = form.querySelector('[data-jd-bulk="minutos_sono"]');

    if (!dormiu || !wrap) return;

    var deveMostrar = dormiu.value === '1';
    wrap.classList.toggle('is-hidden', !deveMostrar);

    if (!deveMostrar && input) {
      input.value = '';
      try {
        input.dispatchEvent(new Event('input', {bubbles:true}));
        input.dispatchEvent(new Event('change', {bubbles:true}));
      } catch(e) {}
    }
  }

  function toggleMinutosForCard(card){
    if (!card) return;

    var input = card.querySelector('[data-jd-minutos-input]');
    if (!input) return;

    var selected = '';
    Array.prototype.forEach.call(card.querySelectorAll('input[type="radio"]'), function(r){
      if (r.name && r.name.slice(-8) === '[dormiu]' && r.checked) {
        selected = r.value;
      }
    });

    var deveMostrar = selected === '1';
    input.classList.toggle('is-hidden', !deveMostrar);

    if (!deveMostrar) {
      input.value = '';
      try {
        input.dispatchEvent(new Event('input', {bubbles:true}));
        input.dispatchEvent(new Event('change', {bubbles:true}));
      } catch(e) {}
    }
  }

  function toggleAllMinutos(root){
    (root || document).querySelectorAll('.sige-jardim-card').forEach(toggleMinutosForCard);
    (root || document).querySelectorAll('form').forEach(toggleBulkMinutos);
  }

  document.addEventListener('change', function(e){
    if (e.target.matches('[data-jd-bulk="dormiu"]')) {
      toggleBulkMinutos(e.target.closest('form'));
    }

    if (e.target.matches('input[type="radio"][name^="diario["][name$="[dormiu]"]')) {
      toggleMinutosForCard(e.target.closest('.sige-jardim-card'));
    }
  });

  document.addEventListener('click', function(e){
    var btn = e.target.closest('[data-jd-apply-all]');
    if (!btn) return;
    e.preventDefault();

    var form = btn.closest('form');
    if (!form) return;

    var applied = 0;
    var dormiu = form.querySelector('[data-jd-bulk="dormiu"]');
    var dormiuValue = dormiu ? dormiu.value : '';

    ['actividades','humor','comportamento','obs_comportamento','recado_pais'].forEach(function(field){
      var src = form.querySelector('[data-jd-bulk="' + field + '"]');
      if (!src || src.value === '') return;
      applied += setValues(form, 'diario', field, src.value);
    });

    if (dormiuValue !== '') {
      applied += setValues(form, 'diario', 'dormiu', dormiuValue);

      if (dormiuValue === '1') {
        var minSrc = form.querySelector('[data-jd-bulk="minutos_sono"]');
        if (minSrc && minSrc.value !== '') {
          applied += setValues(form, 'diario', 'minutos_sono', minSrc.value);
        }
      } else {
        applied += setValues(form, 'diario', 'minutos_sono', '');
      }

      toggleAllMinutos(form);
    } else {
      var minSrcOnly = form.querySelector('[data-jd-bulk="minutos_sono"]');
      if (minSrcOnly && minSrcOnly.value !== '') {
        applied += setValues(form, 'diario', 'minutos_sono', minSrcOnly.value);
      }
    }

    flashBulk(btn, applied > 0 ? 'Aplicado a todos ✓' : 'Nada para aplicar', applied);
  });

  document.addEventListener('click', function(e){
    var btn = e.target.closest('[data-jcrit-apply-all]');
    if (!btn) return;
    e.preventDefault();

    var form = btn.closest('form');
    if (!form) return;

    var sel = form.querySelector('[data-jcrit-bulk-status]');
    var val = sel ? sel.value : 'atingido';
    var applied = 0;

    Array.prototype.forEach.call(form.elements, function(el){
      if (!el.name || el.name.indexOf('resp[') !== 0 || el.type !== 'radio') return;
      if (String(el.value) !== String(val)) return;

      el.checked = true;
      try { el.dispatchEvent(new Event('change', {bubbles:true})); } catch(e) {}
      applied++;

      var lbl = el.closest('label[data-group]');
      if (lbl) {
        var group = lbl.dataset.group;
        var color = lbl.dataset.color || '#0b4a8f';

        document.querySelectorAll('label[data-group="' + group + '"]').forEach(function(l) {
          l.style.border = '2px solid #e5e7eb';
          l.style.background = 'white';
        });

        lbl.style.border = '2px solid ' + color;
        lbl.style.background = color + '20';
      }
    });

    flashBulk(btn, applied > 0 ? 'Avaliação aplicada ✓' : 'Nada para aplicar', applied);
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ toggleAllMinutos(document); });
  } else {
    toggleAllMinutos(document);
  }
})();

// Actualizar visual dos botões atingido/progresso/nao_atingido ao clicar
document.addEventListener('click', function(e) {
  var lbl = e.target.closest('label[data-group]');
  if (!lbl) return;

  var group = lbl.dataset.group;
  var color = lbl.dataset.color;
  var radio = lbl.querySelector('input[type=radio]');

  if (radio) {
    radio.checked = true;
    try { radio.dispatchEvent(new Event('change', {bubbles:true})); } catch(e) {}
  }

  document.querySelectorAll('label[data-group="' + group + '"]').forEach(function(l) {
    l.style.border = '2px solid #e5e7eb';
    l.style.background = 'white';
  });

  lbl.style.border = '2px solid ' + color;
  lbl.style.background = color + '20';
});
</script>
</div>