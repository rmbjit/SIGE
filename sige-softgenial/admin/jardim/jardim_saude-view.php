<?php
/**
 * Módulo Jardim de Infância - Nutrição e Saúde
 * SIGE SoftGenial | SNE Moçambique - Pré-Escolar
 *
 * v2.1 - Abril 2026
 * - CSS: hero + toolbar reescritos com design system Navy
 * - date() → wp_date(), função guardada
 */
if (!defined('ABSPATH')) exit;
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['jardim.saude_ver','jardim.saude_gerir'],
    ['sige_assistente','sige_director','sige_educador','sige_secretario','sige_secretaria_geral']
)) return;
global $wpdb;
$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
$ano     = sige_ano_lectivo_atual();
$user_id = get_current_user_id();
// ── Turmas pré-escolar - fonte central v12.10.104 ──────────────────────
$turmas = function_exists('sige_jardim_get_preescolar_turmas_v104')
    ? sige_jardim_get_preescolar_turmas_v104((int)$escola_id, (int)$ano, true)
    : [];
$turma_sel = (int)($_GET['turma_id'] ?? ($turmas[0]->id ?? 0));
$data_sel  = sanitize_text_field($_GET['data_reg'] ?? wp_date('Y-m-d'));
$aluno_ficha = (int)($_GET['ficha_id'] ?? 0); // Para ver ficha médica de um aluno
// ── Alunos da turma - fonte central v12.10.104 ─────────────────────────────────────────
$alunos = [];
if ($turma_sel && function_exists('sige_jardim_get_alunos_activos_turma_v104')) {
    $alunos = sige_jardim_get_alunos_activos_turma_v104((int)$turma_sel, (int)$escola_id, (int)$ano, "a.id, a.nome_completo, a.foto, a.genero, a.data_nascimento, a.grupo_sanguineo, a.alergias, a.condicoes_medicas, a.hospital_preferencia, a.contacto_encarregado");
}
// ── Registos de saúde existentes ────────────────────────────────────────────
$registos = [];
if ($alunos) {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sige_jardim_saude
         WHERE turma_id = %d AND data_registo = %s AND ano_lectivo = %d AND escola_id = %d",
        $turma_sel, $data_sel, $ano, $escola_id
    ));
    foreach ($rows as $r) $registos[$r->aluno_id] = $r;
}
// ── Histórico de ocorrências (últimos 30 dias) ──────────────────────────────
$ocorrencias = [];
if ($turma_sel) {
    $ocorrencias = $wpdb->get_results($wpdb->prepare(
        "SELECT s.*, a.nome_completo
         FROM {$wpdb->prefix}sige_jardim_saude s
         JOIN {$wpdb->prefix}sige_alunos a ON a.id = s.aluno_id
         WHERE s.turma_id = %d AND s.ano_lectivo = %d AND s.escola_id = %d
           AND (s.febre = 1 OR s.queda_acidente = 1)
           AND s.data_registo >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         ORDER BY s.data_registo DESC
         LIMIT 20",
        $turma_sel, $ano, $escola_id
    ));}
$msg = '';
if (isset($_GET['saved']) && $_GET['saved'] == 1) $msg = 'success';
if (isset($_GET['saved']) && $_GET['saved'] == 0) $msg = 'error';
if (isset($_GET['msg'])) {
    $m = sanitize_key((string)$_GET['msg']);
    if (in_array($m, ['ok','success','saved','guardado'], true)) $msg = 'success';
    if (in_array($m, ['erro','error','failed','falha'], true)) $msg = 'error';
}
// Helper: ícone de refeição
if (!function_exists('jardim_icone_refeicao')) {
function jardim_icone_refeicao($val) {
    return match($val) {
        'tudo'    => '🍽️ Tudo',
        'metade'  => '🍴 Metade',
        'pouco'   => '😐 Pouco',
        'nao_comeu' => '❌ Não comeu',
        default   => '-'
    };
}
}

$jsaude_icon = static function (string $name): string {
    if (function_exists('sige_ui_icon')) {
        return sige_ui_icon($name);
    }
    $map = [
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/>',
        'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'book' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'filter' => '<path d="M22 3H2l8 9.46V19l4 2v-8.54z"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'droplet' => '<path d="M12 2.69 6.5 9A7 7 0 1 0 17.5 9L12 2.69Z"/>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.32 1.78.59 2.63a2 2 0 0 1-.45 2.11L8 9.7a16 16 0 0 0 6.3 6.3l1.24-1.24a2 2 0 0 1 2.11-.45c.85.27 1.73.47 2.63.59A2 2 0 0 1 22 16.92Z"/>',
    ];
    $path = $map[$name] ?? $map['heart'];
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};

$jsaude_total_alunos = count($alunos);
$jsaude_registos_feitos = count($registos);
$jsaude_alertas_medicos = 0;
foreach ($alunos as $ja) {
    if (!empty($ja->alergias) || !empty($ja->condicoes_medicas) || !empty($ja->grupo_sanguineo)) {
        $jsaude_alertas_medicos++;
    }
}
$jsaude_ocorrencias_count = count($ocorrencias);
$jsaude_turma_nome = '';
foreach ($turmas as $jt) {
    if ((int)$jt->id === (int)$turma_sel) {
        $jsaude_turma_nome = trim(($jt->nome ?: $jt->classe) . ' · ' . $jt->classe, ' ·');
        break;
    }
}

?>
<style id="sige-jardim-saude-produto-pro-v121099">
/* SIGE SoftGenial v12.10.99 - Jardim Saúde: Compliance Visual Integral
   Escopo visual apenas: não altera handlers de gravação, regras de saúde,
   base de dados, permissões, notas ou pagamentos. */
.sige-jsaude-wrap{
    --jh-blue:var(--sg-theme-primary,var(--color-brand-500));
    --jh-blue-dark:var(--sg-theme-primary-800,var(--color-ink-700));
    --jh-purple:var(--color-brand-500);
    --jh-purple-soft:var(--color-brand-50);
    --jh-ink:var(--color-black);
    --jh-muted:var(--color-slate-700);
    --jh-line:var(--color-ink-100);
    --jh-green:var(--color-success-500);
    --jh-amber:var(--color-warning-500);
    --jh-red:var(--color-danger-500);
    width:100%;
    max-width:none!important;
    margin:0;
    padding:0 0 28px;
    display:flex;
    flex-direction:column;
    gap:18px;
    color:var(--jh-ink);
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif);
}
.sige-jsaude-wrap *{box-sizing:border-box}
.sige-jsaude-wrap svg{width:18px;height:18px;display:block;stroke:currentColor!important;color:currentColor!important;fill:none!important}

/* HERO - padrão Painel Principal */
.sige-jsaude-hero{
    position:relative!important;
    overflow:hidden!important;
    min-height:178px!important;
    border-radius:var(--radius-xl)!important;
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-info-50) 100%)!important;
    border:1px solid rgba(92,64,187,.12)!important;
    box-shadow:var(--shadow-lg);
    padding:32px 34px!important;
    margin:0!important;
    color:var(--jh-ink)!important;
}
.sige-jsaude-hero:before{content:"";position:absolute;inset:auto -80px -130px auto;width:420px;height:300px;border-radius:var(--radius-pill);background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);pointer-events:none}
.sige-jsaude-hero-inner{
    position:relative;z-index:1;
    display:grid!important;
    grid-template-columns:minmax(0,1.04fr) minmax(320px,.96fr)!important;
    gap:22px!important;
    align-items:center!important;
}
.sige-jsaude-hero-main{min-width:0}
.sige-jsaude-hero-kicker{
    display:inline-flex!important;
    align-items:center!important;
    gap:var(--space-2)!important;
    margin:0 0 10px!important;
    padding:0!important;
    border:0!important;
    background:transparent!important;
    color:var(--jh-blue)!important;
    font-size:12px!important;
    line-height:1.2!important;
    font-weight:700!important;
    letter-spacing:.11em!important;
    text-transform:uppercase!important;
}
.sige-jsaude-hero h1{
    margin:0!important;
    max-width:720px!important;
    color:var(--color-black)!important;
    font-size:31px!important;
    line-height:1.08!important;
    font-weight:700!important;
    letter-spacing:-.04em!important;
    font-family:inherit!important;
}
.sige-jsaude-hero p{
    max-width:720px!important;
    margin:var(--space-3) 0 0!important;
    color:var(--color-slate-700)!important;
    font-size:15px!important;
    line-height:1.65!important;
    font-weight:500!important;
}
.sige-jsaude-hero-chips{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
.sige-jsaude-hero-chips span{
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
.sige-jsaude-hero-chips svg{color:var(--jh-purple)}
.sige-jsaude-hero-panel{
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
.sige-jsaude-hero-panel:before{content:"";position:absolute;right:22px;bottom:16px;width:112px;height:92px;border-radius:22px 22px 12px 12px;background:rgba(109,93,252,.16);box-shadow:inset 0 0 0 2px rgba(109,93,252,.12)}
.sige-jsaude-hero-panel>*{position:relative;z-index:1}
.sige-jsaude-panel-label{display:flex;align-items:center;gap:var(--space-2);color:var(--jh-purple);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.11em}
.sige-jsaude-panel-value{display:block;color:var(--color-ink-900);font-size:36px;line-height:1.05;font-weight:700;letter-spacing:-.045em}
.sige-jsaude-panel-text{display:block;max-width:330px;color:var(--color-slate-600);font-size:var(--fs-sm);line-height:1.55;font-weight:600}
.sige-jsaude-hero-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}
.sige-jsaude-hero a.btn-ghost,.sige-jsaude-hero-actions a{
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
    border:1px solid var(--color-ink-100)!important;
    background:var(--color-white)!important;
    color:var(--color-ink-900)!important;
    box-shadow:var(--shadow-sm);
    transition:transform .18s ease,box-shadow .18s ease!important;
}
.sige-jsaude-hero a.btn-ghost:hover,.sige-jsaude-hero-actions a:hover{transform:translateY(-1px)!important;box-shadow:0 4px 16px rgba(15,23,42,.08)}

/* KPIs */
.sige-jsaude-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:var(--space-4);margin:0}
.sige-jsaude-kpi{
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
.sige-jsaude-kpi:after{content:"";position:absolute;right:-28px;top:-34px;width:92px;height:92px;border-radius:50%;background:var(--kpi-soft,var(--color-brand-50))}
.sige-jsaude-kpi-icon{
    width:52px;height:52px;border-radius:var(--radius-lg);
    display:flex;align-items:center;justify-content:center;
    background:var(--kpi-soft,var(--color-brand-50));
    color:var(--kpi-color,var(--color-brand-500));
    position:relative;z-index:1;
}
.sige-jsaude-kpi-icon svg{width:24px;height:24px}
.sige-jsaude-kpi > div{position:relative;z-index:1;min-width:0}
.sige-jsaude-kpi .num{font-size:27px;font-weight:700;line-height:1;color:var(--color-black);letter-spacing:-.03em}
.sige-jsaude-kpi .lbl{font-size:12px;color:var(--color-slate-600);margin-top:7px;font-weight:600}
.sige-jsaude-kpi.alunos{--kpi-color:var(--color-brand-500);--kpi-soft:var(--color-brand-50)}
.sige-jsaude-kpi.registos{--kpi-color:var(--color-success-500);--kpi-soft:var(--color-success-50)}
.sige-jsaude-kpi.alertas{--kpi-color:var(--color-warning-500);--kpi-soft:var(--color-warning-50)}
.sige-jsaude-kpi.ocorrencias{--kpi-color:var(--color-danger-500);--kpi-soft:var(--color-danger-50)}

/* Alertas e vazio */
.sige-jsaude-alert{padding:13px 16px!important;border-radius:var(--radius-lg)!important;margin:0!important;font-weight:700!important;font-size:var(--fs-sm)!important;line-height:1.5!important;border:1px solid transparent!important;box-shadow:0 2px 8px rgba(15,23,42,.06)}
.sige-jsaude-alert.success{background:var(--color-success-50)!important;color:var(--color-success-900)!important;border-color:var(--color-success-200)!important}
.sige-jsaude-alert.error{background:var(--color-danger-50)!important;color:var(--color-danger-700)!important;border-color:var(--color-danger-200)!important}
.sige-jsaude-empty{background:var(--color-white)!important;border-radius:var(--radius-xl)!important;padding:42px 24px!important;text-align:center!important;border:1px dashed var(--color-ink-200)!important;box-shadow:0 4px 16px rgba(15,23,42,.08)}
.sige-jsaude-empty h3{color:var(--color-black)!important;font-size:var(--fs-lg)!important;font-weight:700!important;margin:0 0 14px!important}
.sige-jsaude-empty a{background:linear-gradient(135deg,var(--jh-blue),var(--jh-blue-dark))!important;color:var(--color-white)!important;border-radius:var(--radius-md)!important;padding:11px 18px!important;text-decoration:none!important;font-weight:700!important}

/* Toolbar */
.sige-jsaude-toolbar{
    display:grid!important;
    grid-template-columns:minmax(220px,1fr) minmax(190px,.6fr) auto!important;
    gap:var(--space-3)!important;
    align-items:end!important;
    background:var(--color-white)!important;
    padding:18px!important;
    border-radius:var(--radius-xl)!important;
    box-shadow:var(--shadow-md);
    margin:0!important;
    border:1px solid rgba(28,32,54,.08)!important;
}
.sige-jsaude-toolbar label{font-size:var(--fs-xs)!important;font-weight:700!important;color:var(--color-slate-600)!important;display:block!important;margin:0 0 7px!important;text-transform:uppercase!important;letter-spacing:.07em!important}
.sige-jsaude-toolbar select,.sige-jsaude-toolbar input[type="date"]{
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
.sige-jsaude-toolbar select:focus,.sige-jsaude-toolbar input[type="date"]:focus{border-color:rgba(90,63,214,.55)!important;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.sige-jsaude-toolbar button[type="submit"]{
    min-height:44px!important;
    display:inline-flex!important;
    align-items:center!important;
    justify-content:center!important;
    gap:var(--space-2)!important;
    background:linear-gradient(135deg,var(--jh-blue),var(--jh-blue-dark))!important;
    color:var(--color-white)!important;
    border:none!important;
    padding:0 18px!important;
    border-radius:var(--radius-md)!important;
    font-size:var(--fs-sm)!important;
    cursor:pointer!important;
    font-weight:700!important;
    box-shadow:var(--shadow-sm);
}
.sige-jsaude-toolbar button:hover{transform:translateY(-1px)!important;box-shadow:0 4px 16px rgba(15,23,42,.08)}

/* Layout principal */
.sige-jsaude-grid{
    display:grid!important;
    grid-template-columns:minmax(0,1fr) minmax(280px,340px)!important;
    gap:var(--space-5)!important;
    align-items:start!important;
}
.sige-jsaude-sidebar{display:grid!important;gap:var(--space-4)!important;min-width:0}

/* Cards de saúde - override do legado inline */
.sige-jsaude-card{
    background:var(--color-white)!important;
    border-radius:var(--radius-xl)!important;
    box-shadow:var(--shadow-md);
    overflow:hidden!important;
    border:1px solid rgba(28,32,54,.08)!important;
    border-left:0!important;
}
.sige-jsaude-card > div:first-child{
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 72%,var(--color-slate-50) 100%)!important;
    border-bottom:1px solid var(--color-slate-100)!important;
    padding:var(--space-4) var(--space-5)!important;
}
.sige-jsaude-card img{border-color:var(--color-ink-100)!important}
.sige-jsaude-card div[style*="color:var(--color-success-900)"]{color:var(--color-ink-500)!important;font-weight:700!important;font-size:15px!important}
.sige-jsaude-card div[style*="color:var(--color-success-500)"]{color:var(--color-slate-500)!important}
.sige-jsaude-card > div[style*="display:grid;grid-template-columns:1fr 1fr 1fr"]{
    display:grid!important;
    grid-template-columns:repeat(3,minmax(0,1fr))!important;
    gap:14px!important;
    padding:18px 20px!important;
}
.sige-jsaude-card label[style*="color:var(--color-success-900)"]{color:var(--color-slate-600)!important;font-weight:700!important}
.sige-jsaude-card select,.sige-jsaude-card input[type="text"],.sige-jsaude-card input[type="number"],.sige-jsaude-card textarea{
    width:100%!important;
    border:1px solid var(--color-ink-100)!important;
    border-radius:var(--radius-md)!important;
    min-height:42px!important;
    font-weight:600!important;
    color:var(--color-ink-500)!important;
    box-shadow:var(--shadow-sm);
}
.sige-jsaude-card textarea{min-height:86px!important}
.sige-jsaude-card select:focus,.sige-jsaude-card input[type="text"]:focus,.sige-jsaude-card input[type="number"]:focus,.sige-jsaude-card textarea:focus{outline:none!important;border-color:rgba(90,63,214,.55)!important;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.sige-jsaude-card span[style*="background:var(--color-success-100)"]{font-weight:700!important;border-radius:var(--radius-pill)!important;background:var(--color-success-50)!important;color:var(--color-success-900)!important}
.sige-jsaude-card input[type="checkbox"]{accent-color:var(--sg-theme-primary,var(--color-brand-500))}

/* Sidebar cards */
.sige-jsaude-sidebar > div{
    background:var(--color-white)!important;
    border-radius:var(--radius-xl)!important;
    box-shadow:var(--shadow-md);
    border:1px solid rgba(28,32,54,.08)!important;
    padding:18px!important;
}
.sige-jsaude-sidebar h4{
    display:flex!important;
    align-items:center!important;
    gap:9px!important;
    margin:0 0 14px!important;
    color:var(--color-ink-500)!important;
    font-size:15px!important;
    font-weight:700!important;
}
.sige-jsaude-sidebar div[style*="border:1px solid"]{
    border-radius:var(--radius-lg)!important;
    padding:var(--space-3)!important;
}
.sige-jsaude-sidebar div[style*="background:var(--color-warning-50)"]{
    background:var(--color-warning-50)!important;
    border-color:var(--color-warning-200)!important;
}
.sige-jsaude-sidebar div[style*="background:var(--color-slate-50)"]{
    background:var(--color-slate-50)!important;
    border-color:var(--color-slate-100)!important;
}
.sige-jsaude-sidebar div[style*="border-left:3px solid"]{
    border-radius:var(--radius-lg)!important;
    border:1px solid var(--color-slate-100)!important;
    border-left-width:4px!important;
    background:var(--color-slate-50)!important;
}

/* Botões de acção */
.sige-jsaude-wrap button[style*="background:var(--color-success-500)"],.sige-jsaude-wrap button[type="submit"]{
    background:linear-gradient(135deg,var(--jh-blue),var(--jh-blue-dark))!important;
    color:var(--color-white)!important;
    border:none!important;
    border-radius:var(--radius-md)!important;
    min-height:44px!important;
    padding:0 18px!important;
    font-weight:700!important;
    cursor:pointer!important;
    box-shadow:var(--shadow-sm);
}
.sige-jsaude-wrap a[style*="background:var(--color-ink-50)"]{
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
    padding:0 18px!important;
}
.sige-jsaude-wrap div[style*="background:white;border-radius:var(--radius-sm);padding:30px"]{
    border-radius:var(--radius-xl)!important;
    border:1px solid rgba(28,32,54,.08)!important;
    box-shadow:var(--shadow-md);
    color:var(--color-slate-500)!important;
}


/* Aplicação rápida e pop-up Produto PRO - v12.10.112 */
.sige-jsaude-bulk-panel{background:var(--color-white);border:1px solid rgba(28,32,54,.08);border-left:4px solid var(--sg-theme-primary,var(--color-brand-500));border-radius:var(--radius-xl);box-shadow:var(--shadow-md);padding:18px 20px;margin:0 0 var(--space-4);display:grid;gap:14px}.sige-jsaude-bulk-title{display:flex;align-items:center;gap:10px;color:var(--color-ink-500);font-size:15px;font-weight:700;margin:0}.sige-jsaude-bulk-text{margin:var(--space-1) 0 0;color:var(--color-slate-500);font-size:var(--fs-sm);line-height:1.55;font-weight:600}.sige-jsaude-bulk-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--space-3);align-items:end}.sige-jsaude-bulk-grid label{font-size:var(--fs-xs);font-weight:700;color:var(--color-slate-600);text-transform:uppercase;letter-spacing:.07em;display:flex;flex-direction:column;gap:7px}.sige-jsaude-bulk-grid input,.sige-jsaude-bulk-grid select,.sige-jsaude-bulk-grid textarea{width:100%;min-height:42px;border:1px solid var(--color-ink-100);border-radius:var(--radius-md);padding:var(--space-2) var(--space-3);font-size:var(--fs-sm);font-weight:600;color:var(--color-ink-500);background:var(--color-white);box-shadow:var(--shadow-sm);outline:none}.sige-jsaude-bulk-grid textarea{min-height:68px;resize:vertical}.sige-jsaude-bulk-action{min-height:44px!important;background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-800,var(--color-ink-700)))!important;color:var(--color-white)!important;border:none!important;border-radius:var(--radius-md)!important;padding:0 18px!important;font-weight:700!important;box-shadow:var(--shadow-sm);cursor:pointer!important}.sige-pro-popup-backdrop{position:fixed;inset:0;z-index:999999;display:flex;align-items:center;justify-content:center;padding:22px;background:rgba(15,23,42,.42);backdrop-filter:blur(5px)}.sige-pro-popup-card{width:min(520px,100%);display:grid;grid-template-columns:auto minmax(0,1fr);gap:15px;align-items:flex-start;padding:22px;border-radius:var(--radius-xl);border:1px solid rgba(255,255,255,.70);background:var(--color-white);box-shadow:var(--shadow-lg);color:var(--color-ink-500);position:relative;animation:sigeJPopup .22s ease-out both}.sige-pro-popup-icon{width:52px;height:52px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;background:var(--color-success-100);color:var(--color-success-500)}.sige-pro-popup-card.is-error .sige-pro-popup-icon{background:var(--color-danger-100);color:var(--color-danger-600)}.sige-pro-popup-icon svg{width:24px!important;height:24px!important}.sige-pro-popup-card strong{display:block;margin:2px 42px 6px 0;font-size:18px;line-height:1.18;font-weight:700;color:inherit}.sige-pro-popup-card p{margin:0;color:var(--color-slate-700);font-size:var(--fs-base);line-height:1.55;font-weight:600}.sige-pro-popup-close{position:absolute;top:13px;right:13px;width:34px;height:34px;border-radius:var(--radius-md);border:1px solid rgba(15,23,42,.08);background:var(--color-white);cursor:pointer;color:var(--color-ink-500);font-size:var(--fs-lg);font-weight:700;box-shadow:0 2px 8px rgba(15,23,42,.06)}@keyframes sigeJPopup{from{opacity:0;transform:translateY(10px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
@media(max-width:1180px){.sige-jsaude-bulk-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:760px){.sige-jsaude-bulk-grid{grid-template-columns:1fr}.sige-pro-popup-backdrop{align-items:flex-end;padding:14px}.sige-pro-popup-card{grid-template-columns:1fr;text-align:center}.sige-pro-popup-icon{margin:0 auto}.sige-pro-popup-card strong{margin-right:0}}

/* Responsividade */
@media(max-width:1280px){
    .sige-jsaude-grid{grid-template-columns:1fr!important}
    .sige-jsaude-sidebar{grid-template-columns:repeat(2,minmax(0,1fr))!important}
}
@media(max-width:1100px){
    .sige-jsaude-hero-inner{grid-template-columns:1fr!important}
    .sige-jsaude-card > div[style*="display:grid;grid-template-columns:1fr 1fr 1fr"]{grid-template-columns:1fr 1fr!important}
}
@media(max-width:760px){
    .sige-jsaude-hero{padding:26px 22px!important}
    .sige-jsaude-hero h1{font-size:24px!important}
    .sige-jsaude-toolbar{grid-template-columns:1fr!important}
    .sige-jsaude-kpis{grid-template-columns:1fr!important}
    .sige-jsaude-sidebar{grid-template-columns:1fr!important}
    .sige-jsaude-card > div:first-child{align-items:flex-start!important}
    .sige-jsaude-card > div[style*="display:grid;grid-template-columns:1fr 1fr 1fr"]{grid-template-columns:1fr!important}
    .sige-jsaude-wrap div[style*="display:flex;justify-content:flex-end"]{display:grid!important;grid-template-columns:1fr!important}
    .sige-jsaude-wrap button[type="submit"],.sige-jsaude-wrap a{width:100%!important}
}

.sige-jsaude-wrap .sg-bulk-applied{background:linear-gradient(135deg,var(--color-success-500),var(--color-success-800))!important;border-color:var(--color-success-500)!important;color:var(--color-white)!important;}
</style>
<div class="wrap sige-jsaude-wrap">

<!-- HERO -->
<section class="sige-jsaude-hero" aria-label="Saúde do Jardim">
  <div class="sige-jsaude-hero-inner">
    <div class="sige-jsaude-hero-main">
      <div class="sige-jsaude-hero-kicker">
        <?php echo $jsaude_icon('activity'); ?>
        <span>Nutrição e Saúde</span>
      </div>
      <h1>Saúde do Jardim</h1>
      <p>Registe refeições, estado de saúde, febre, quedas, acidentes e alertas médicos dos alunos do pré-escolar.</p>
      <div class="sige-jsaude-hero-chips">
        <span><?php echo $jsaude_icon('calendar'); ?> Ano Lectivo <?php echo esc_html($ano); ?></span>
        <?php if ($jsaude_turma_nome): ?><span><?php echo $jsaude_icon('users'); ?> <?php echo esc_html($jsaude_turma_nome); ?></span><?php endif; ?>
        <span><?php echo $jsaude_icon('heart'); ?> <?php echo esc_html(wp_date('d/m/Y', strtotime($data_sel))); ?></span>
      </div>
      <div class="sige-jsaude-hero-actions">
        <a href="?page=sige-app&view=jardim_diario<?php echo $turma_sel ? '&turma_id='.$turma_sel : ''; ?>" class="btn-ghost">
          <?php echo $jsaude_icon('book'); ?> Diário de Actividades
        </a>
      </div>
    </div>
    <aside class="sige-jsaude-hero-panel" aria-label="Estado dos registos de saúde">
      <div class="sige-jsaude-panel-label"><?php echo $jsaude_icon('check'); ?><span>Estado</span></div>
      <strong class="sige-jsaude-panel-value"><?php echo esc_html((string)$jsaude_registos_feitos); ?>/<?php echo esc_html((string)$jsaude_total_alunos); ?></strong>
      <span class="sige-jsaude-panel-text">Registo(s) de saúde preenchidos para a turma e data seleccionadas.</span>
    </aside>
  </div>
</section>

<?php if ($msg === 'success' || $msg === 'error'): ?>
  <div class="sige-pro-popup-backdrop" role="presentation">
    <div class="sige-pro-popup-card <?php echo $msg === 'error' ? 'is-error' : ''; ?>" role="dialog" aria-modal="true" aria-live="polite">
      <span class="sige-pro-popup-icon"><?php echo $jsaude_icon($msg === 'error' ? 'alert' : 'check'); ?></span>
      <div>
        <strong><?php echo $msg === 'success' ? 'Guardado com sucesso' : 'Não foi possível guardar'; ?></strong>
        <p><?php echo $msg === 'success' ? 'Registos de saúde e nutrição guardados com sucesso.' : 'Confirme se há alunos activos, dados preenchidos e tente novamente.'; ?></p>
      </div>
      <button type="button" class="sige-pro-popup-close" aria-label="Fechar aviso" data-sige-act="sigeFecharPopupBackdrop">×</button>
    </div>
  </div>
<?php endif; ?>

<?php if (empty($turmas)): ?>
  <div class="sige-jsaude-empty">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
    <h3>Nenhuma turma Pré-Escolar encontrada</h3>
    <a href="?page=sige-app&view=turmas">Ir para Turmas</a>
  </div>
<?php else: ?>

<!-- FILTROS -->
<form method="get" class="sige-jsaude-toolbar">
  <input type="hidden" name="page" value="sige-app">
  <input type="hidden" name="view" value="jardim_saude">
  <label>Turma:</label>
  <select name="turma_id">
    <?php foreach ($turmas as $t): ?>
      <option value="<?php echo $t->id; ?>" <?php selected($t->id, $turma_sel); ?>><?php echo esc_html($t->nome ?: $t->classe); ?></option>
    <?php endforeach; ?>
  </select>
  <label>Data:</label>
  <input type="date" name="data_reg" value="<?php echo esc_attr($data_sel); ?>" max="<?php echo wp_date('Y-m-d'); ?>">
  <button type="submit" class="sgk-btn sgk-btn-sec">
    <?php echo $jsaude_icon('filter'); ?>
    Carregar
  </button>
</form>

<div class="sige-jsaude-grid" style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">
  <!-- FORMULÁRIO PRINCIPAL -->
  <div>
  <?php if (empty($alunos)): ?>
    <div style="background:white;border-radius:10px;padding:30px;text-align:center;color:var(--color-slate-500);">Nenhum aluno matriculado nesta turma.</div>
  <?php else: ?>
  <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
    <input type="hidden" name="action" value="sige_jardim_saude_salvar">
    <input type="hidden" name="turma_id" value="<?php echo esc_attr($turma_sel); ?>">
    <input type="hidden" name="data_registo" value="<?php echo esc_attr($data_sel); ?>">
    <input type="hidden" name="ano_lectivo" value="<?php echo esc_attr($ano); ?>">
    <?php wp_nonce_field('sige_jardim_saude_nonce', '_jsaude_nonce'); ?>
    <section class="sige-jsaude-bulk-panel" aria-label="Aplicar padrão de saúde a todos">
      <div>
        <div class="sige-jsaude-bulk-title"><?php echo $jsaude_icon('save'); ?> Aplicar padrão a todos</div>
        <p class="sige-jsaude-bulk-text">Use para marcar a rotina normal da turma e depois ajuste apenas a criança que teve febre, acidente, alimentação diferente ou outra ocorrência.</p>
      </div>
      <div class="sige-jsaude-bulk-grid">
        <label>Pequeno-almoço<select data-js-bulk="pequeno_almoco"><option value="">- manter vazio -</option><option value="tudo">🍽️ Tudo</option><option value="metade">🍴 Metade</option><option value="pouco">😐 Pouco</option><option value="nao_comeu">❌ Não comeu</option></select></label>
        <label>Almoço<select data-js-bulk="almoco"><option value="">- manter vazio -</option><option value="tudo">🍽️ Tudo</option><option value="metade">🍴 Metade</option><option value="pouco">😐 Pouco</option><option value="nao_comeu">❌ Não comeu</option></select></label>
        <label>Lanche<select data-js-bulk="lanche"><option value="">- manter vazio -</option><option value="tudo">🍽️ Tudo</option><option value="metade">🍴 Metade</option><option value="pouco">😐 Pouco</option><option value="nao_comeu">❌ Não comeu</option></select></label>
        <label>Febre<select data-js-bulk="febre"><option value="0">Não</option><option value="1">Sim</option><option value="">- não alterar -</option></select></label>
        <label>Temperatura<input type="number" min="35" max="42" step="0.1" data-js-bulk="temperatura" placeholder="Opcional"></label>
        <label>Queda/acidente<select data-js-bulk="queda_acidente"><option value="0">Não</option><option value="1">Sim</option><option value="">- não alterar -</option></select></label>
        <label>Obs. alimentação<input type="text" data-js-bulk="obs_alimentacao" placeholder="Opcional"></label>
        <label>Ocorrência<textarea data-js-bulk="desc_ocorrencia" rows="2" placeholder="Opcional"></textarea></label>
        <button type="button" class="sige-jsaude-bulk-action" data-js-apply-all>Aplicar a todos</button>
      </div>
    </section>
    <div style="display:grid;gap:14px;">
    <?php foreach ($alunos as $aluno):
      $r    = $registos[$aluno->id] ?? null;
      $foto = $aluno->foto ?: 'https://ui-avatars.com/api/?name='.urlencode($aluno->nome_completo).'&background=059669&color=fff&size=60';
      $n    = 'saude['.$aluno->id.']';
      $idade = (int)((time() - strtotime($aluno->data_nascimento)) / 31557600);
    ?>
      <div class="sige-jsaude-card" style="background:white;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.07);overflow:hidden;border-left:4px solid var(--color-success-600);">
        <!-- Cabeçalho -->
        <div style="display:flex;align-items:center;gap:14px;padding:12px 20px;background:var(--color-success-50);border-bottom:1px solid var(--color-success-200);">
          <img src="<?php echo esc_url($foto); ?>" style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid var(--color-success-300);">
          <div>
            <div style="font-weight:700;color:var(--color-success-900);font-size:14px;"><?php echo esc_html($aluno->nome_completo); ?></div>
            <div style="font-size:11px;color:var(--color-success-500);"><?php echo $idade; ?> anos &nbsp;|&nbsp; <?php echo $aluno->genero === 'M' ? '👦' : '👧'; ?>
              <?php if ($aluno->grupo_sanguineo): ?> &nbsp;|&nbsp; 🩸 <?php echo esc_html($aluno->grupo_sanguineo); ?><?php endif; ?>
              <?php if ($aluno->alergias): ?> &nbsp;|&nbsp; ⚠️ Alergia<?php endif; ?>
            </div>
          </div>
          <?php if ($r): ?>
            <div style="margin-left:auto;background:var(--color-success-100);color:var(--color-success-900);padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;">✅ Registado</div>
          <?php endif; ?>
        </div>
        <div style="padding:14px 20px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
          <!-- Refeições -->
          <div>
            <label style="font-size:11px;font-weight:700;color:var(--color-success-900);text-transform:uppercase;letter-spacing:.5px;">☕ Pequeno-Almoço</label>
            <select name="<?php echo $n; ?>[pequeno_almoco]" style="width:100%;margin-top:4px;padding:7px;border:1px solid var(--color-success-200);border-radius:8px;font-size:13px;">
              <option value="">-</option>
              <?php foreach (['tudo'=>'🍽️ Tudo','metade'=>'🍴 Metade','pouco'=>'😐 Pouco','nao_comeu'=>'❌ Não comeu'] as $k=>$v): ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($r->pequeno_almoco??'',$k); ?>><?php echo $v; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="font-size:11px;font-weight:700;color:var(--color-success-900);text-transform:uppercase;letter-spacing:.5px;">🍲 Almoço</label>
            <select name="<?php echo $n; ?>[almoco]" style="width:100%;margin-top:4px;padding:7px;border:1px solid var(--color-success-200);border-radius:8px;font-size:13px;">
              <option value="">-</option>
              <?php foreach (['tudo'=>'🍽️ Tudo','metade'=>'🍴 Metade','pouco'=>'😐 Pouco','nao_comeu'=>'❌ Não comeu'] as $k=>$v): ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($r->almoco??'',$k); ?>><?php echo $v; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="font-size:11px;font-weight:700;color:var(--color-success-900);text-transform:uppercase;letter-spacing:.5px;">🍌 Lanche</label>
            <select name="<?php echo $n; ?>[lanche]" style="width:100%;margin-top:4px;padding:7px;border:1px solid var(--color-success-200);border-radius:8px;font-size:13px;">
              <option value="">-</option>
              <?php foreach (['tudo'=>'🍽️ Tudo','metade'=>'🍴 Metade','pouco'=>'😐 Pouco','nao_comeu'=>'❌ Não comeu'] as $k=>$v): ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($r->lanche??'',$k); ?>><?php echo $v; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <!-- Obs alimentação -->
          <div style="grid-column:1/-1;">
            <label style="font-size:11px;font-weight:700;color:var(--color-success-900);text-transform:uppercase;letter-spacing:.5px;">📝 Obs. Alimentação</label>
            <input type="text" name="<?php echo $n; ?>[obs_alimentacao]" value="<?php echo esc_attr($r->obs_alimentacao??''); ?>"
              placeholder="Ex: pediu repetição, recusou legumes..."
              style="width:100%;margin-top:4px;padding:8px 10px;border:1px solid var(--color-success-200);border-radius:8px;font-size:13px;box-sizing:border-box;">
          </div>
          <!-- Saúde -->
          <div>
            <label style="font-size:11px;font-weight:700;color:var(--color-success-900);text-transform:uppercase;letter-spacing:.5px;">🌡️ Febre</label>
            <div style="display:flex;gap:10px;margin-top:6px;">
              <label style="font-size:13px;display:flex;align-items:center;gap:4px;cursor:pointer;">
                <input type="checkbox" name="<?php echo $n; ?>[febre]" value="1" <?php checked($r->febre??0, 1); ?>> Sim
              </label>
            </div>
            <input type="number" name="<?php echo $n; ?>[temperatura]" min="35" max="42" step="0.1"
              value="<?php echo esc_attr($r->temperatura??''); ?>"
              placeholder="°C"
              style="width:100%;margin-top:6px;padding:7px;border:1px solid var(--color-success-200);border-radius:8px;font-size:13px;box-sizing:border-box;">
          </div>
          <div>
            <label style="font-size:11px;font-weight:700;color:var(--color-success-900);text-transform:uppercase;letter-spacing:.5px;">🩹 Queda / Acidente</label>
            <div style="display:flex;gap:10px;margin-top:6px;">
              <label style="font-size:13px;display:flex;align-items:center;gap:4px;cursor:pointer;">
                <input type="checkbox" name="<?php echo $n; ?>[queda_acidente]" value="1" <?php checked($r->queda_acidente??0,1); ?>> Ocorreu
              </label>
            </div>
            <input type="text" name="<?php echo $n; ?>[medicamento_dado]" value="<?php echo esc_attr($r->medicamento_dado??''); ?>"
              placeholder="Medicamento dado (se aplicável)"
              style="width:100%;margin-top:6px;padding:7px;border:1px solid var(--color-success-200);border-radius:8px;font-size:13px;box-sizing:border-box;">
          </div>
          <div>
            <label style="font-size:11px;font-weight:700;color:var(--color-success-900);text-transform:uppercase;letter-spacing:.5px;">📋 Descrição da Ocorrência</label>
            <textarea name="<?php echo $n; ?>[desc_ocorrencia]" rows="3"
              placeholder="Descreva o que aconteceu..."
              style="width:100%;margin-top:4px;padding:8px;border:1px solid var(--color-success-200);border-radius:8px;font-size:13px;resize:vertical;box-sizing:border-box;"><?php echo esc_textarea($r->desc_ocorrencia??''); ?></textarea>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:18px;padding-bottom:30px;">
      <a href="?page=sige-app&view=jardim_saude&turma_id=<?php echo esc_attr($turma_sel); ?>&data_reg=<?php echo esc_attr($data_sel); ?>"
         style="background:var(--color-ink-50);color:var(--color-slate-700);padding:11px 24px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;">↩ Cancelar</a>
      <button type="submit" class="sgk-btn sgk-btn-primario" style="background:var(--color-success-500);border-color:var(--color-success-500);padding:11px 28px;font-size:14px;font-weight:700;">
        💾 Guardar Saúde - <?php echo wp_date('d/m/Y', strtotime($data_sel)); ?>
      </button>
    </div>
  </form>
  <?php endif; // alunos ?>
  </div>
  <!-- COLUNA LATERAL: fichas médicas + ocorrências -->
  <div class="sige-jsaude-sidebar" style="display:grid;gap:16px;">
    <!-- Fichas médicas dos alunos -->
    <div style="background:white;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.07);padding:18px;">
      <h4 style="margin:0 0 14px;color:var(--color-success-900);font-size:14px;"><?php echo $jsaude_icon('heart'); ?> Fichas Médicas</h4>
      <?php foreach ($alunos as $aluno):
        $tem_info = $aluno->alergias || $aluno->condicoes_medicas || $aluno->grupo_sanguineo;
      ?>
        <div style="border:1px solid <?php echo $tem_info ? 'var(--color-warning-300)' : 'var(--color-ink-100)'; ?>;border-radius:8px;padding:10px 12px;margin-bottom:8px;background:<?php echo $tem_info ? 'var(--color-warning-50)' : 'var(--color-slate-50)'; ?>;">
          <div style="font-weight:700;font-size:13px;color:var(--color-slate-800);"><?php echo esc_html($aluno->nome_completo); ?></div>
          <?php if ($aluno->grupo_sanguineo): ?>
            <div style="font-size:11px;color:var(--color-danger-600);margin-top:2px;">🩸 Grupo: <?php echo esc_html($aluno->grupo_sanguineo); ?></div>
          <?php endif; ?>
          <?php if ($aluno->alergias): ?>
            <div style="font-size:11px;color:var(--color-warning-700);margin-top:2px;">⚠️ Alergia: <?php echo esc_html($aluno->alergias); ?></div>
          <?php endif; ?>
          <?php if ($aluno->condicoes_medicas): ?>
            <div style="font-size:11px;color:var(--color-brand-500);margin-top:2px;">🏥 <?php echo esc_html($aluno->condicoes_medicas); ?></div>
          <?php endif; ?>
          <?php if ($aluno->hospital_preferencia): ?>
            <div style="font-size:11px;color:var(--color-success-500);margin-top:2px;">🏨 <?php echo esc_html($aluno->hospital_preferencia); ?></div>
          <?php endif; ?>
          <?php if (!$tem_info): ?>
            <div style="font-size:11px;color:var(--color-slate-400);margin-top:2px;">Sem alertas médicos</div>
          <?php endif; ?>
          <?php if ($aluno->contacto_encarregado): ?>
            <div style="font-size:11px;color:var(--color-success-500);margin-top:3px;">📞 <?php echo esc_html($aluno->contacto_encarregado); ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <!-- Ocorrências recentes -->
    <?php if (!empty($ocorrencias)): ?>
    <div style="background:white;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.07);padding:18px;">
      <h4 style="margin:0 0 14px;color:var(--color-success-900);font-size:14px;"><?php echo $jsaude_icon('alert'); ?> Ocorrências (30 dias)</h4>
      <?php foreach ($ocorrencias as $oc): ?>
        <div style="border-left:3px solid <?php echo $oc->febre ? 'var(--color-danger-500)' : 'var(--color-warning-500)'; ?>;padding:8px 10px;margin-bottom:8px;background:var(--color-slate-50);border-radius:0 6px 6px 0;">
          <div style="font-size:12px;font-weight:700;color:var(--color-slate-800);"><?php echo esc_html($oc->nome_completo); ?></div>
          <div style="font-size:11px;color:var(--color-slate-600);"><?php echo wp_date('d/m/Y', strtotime($oc->data_registo)); ?>
            <?php if ($oc->febre): ?> - 🌡️ Febre <?php echo $oc->temperatura ? esc_html($oc->temperatura).'°C' : ''; ?><?php endif; ?>
            <?php if ($oc->queda_acidente): ?> - 🩹 Acidente<?php endif; ?>
          </div>
          <?php if ($oc->desc_ocorrencia): ?>
            <div style="font-size:11px;color:var(--color-slate-400);margin-top:2px;"><?php echo esc_html(substr($oc->desc_ocorrencia,0,80)).(strlen($oc->desc_ocorrencia)>80?'...':''); ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div><!-- lateral -->
  </div><!-- grid -->
  <?php endif; // turmas ?>
<script <?php echo sige_csp_script_attr(); ?>>
(function(){
  function formFields(form, field){
    var suffix = '[' + field + ']';

    return Array.prototype.filter.call(form.elements, function(el){
      return el.name && el.name.indexOf('saude[') === 0 && el.name.slice(-suffix.length) === suffix;
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

  function setValues(form, field, value){
    if (value === null || typeof value === 'undefined') return 0;

    var count = 0;
    formFields(form, field).forEach(function(el){
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

  document.addEventListener('click', function(e){
    var btn = e.target.closest('[data-js-apply-all]');
    if (!btn) return;
    e.preventDefault();

    var form = btn.closest('form');
    if (!form) return;

    var applied = 0;

    ['pequeno_almoco','almoco','lanche','temperatura','obs_alimentacao','desc_ocorrencia'].forEach(function(field){
      var src = form.querySelector('[data-js-bulk="' + field + '"]');
      if (!src || src.value === '') return;
      applied += setValues(form, field, src.value);
    });

    ['febre','queda_acidente'].forEach(function(field){
      var src = form.querySelector('[data-js-bulk="' + field + '"]');
      if (!src || src.value === '') return;
      applied += setValues(form, field, src.value);
    });

    flashBulk(btn, applied > 0 ? 'Aplicado a todos ✓' : 'Nada para aplicar', applied);
  });
})();
</script>

</div>