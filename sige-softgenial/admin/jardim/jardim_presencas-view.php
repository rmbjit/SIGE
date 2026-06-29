<?php
/** SIGE SoftGenial - Jardim de Infância: Controlo de Presenças v81 */
if (!defined('ABSPATH')) exit;
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['jardim.presencas_ver','jardim.presencas_gerir'],
    ['sige_assistente','sige_director','sige_educador','sige_secretario','sige_secretaria_geral']
)) return;
global $wpdb;
$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
$ano = function_exists('sige_ano_lectivo_atual') ? (int)sige_ano_lectivo_atual() : (int)wp_date('Y');
$data_sel = sanitize_text_field($_GET['data_reg'] ?? wp_date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_sel)) $data_sel = wp_date('Y-m-d');

if (function_exists('sige_jardim_ensure_presencas_table_v81')) sige_jardim_ensure_presencas_table_v81();

// ── Turmas pré-escolar - fonte central v12.10.104 ──────────────────────
$turmas = function_exists('sige_jardim_get_preescolar_turmas_v104')
    ? sige_jardim_get_preescolar_turmas_v104((int)$escola_id, (int)$ano, true)
    : [];
$turma_sel = (int)($_GET['turma_id'] ?? ($turmas[0]->id ?? 0));
// ── Alunos da turma - fonte central v12.10.104 ─────────────────────────
$alunos = [];
if ($turma_sel && function_exists('sige_jardim_get_alunos_activos_turma_v104')) {
    $alunos = sige_jardim_get_alunos_activos_turma_v104((int)$turma_sel, (int)$escola_id, (int)$ano, "a.id, a.nome_completo, a.foto, a.genero");
}
$presencas = [];
if ($turma_sel && $alunos && function_exists('sige_jardim_presencas_table_v81')) {
    $t = sige_jardim_presencas_table_v81();
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE turma_id=%d AND data_registo=%s AND ano_lectivo=%d AND escola_id=%d", $turma_sel, $data_sel, $ano, $escola_id));
    foreach ((array)$rows as $r) $presencas[(int)$r->aluno_id] = $r;
}
$status_cfg = [
    'presente' => ['Presente', 'var(--color-success-500)', '✅'],
    'atraso' => ['Atraso', 'var(--color-warning-600)', '⏰'],
    'falta' => ['Falta', 'var(--color-danger-600)', '❌'],
    'falta_justificada' => ['Falta justificada', 'var(--color-info-500)', '📝'],
];
$total_pres = ['presente'=>0,'atraso'=>0,'falta'=>0,'falta_justificada'=>0];
foreach ($alunos as $al) {
    $st = $presencas[(int)$al->id]->status ?? 'presente';
    if (isset($total_pres[$st])) $total_pres[$st]++;
}

$jpres_icon = static function (string $name): string {
    if (function_exists('sige_ui_icon')) {
        return sige_ui_icon($name);
    }
    $map = [
        'check-square' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'filter' => '<path d="M22 3H2l8 9.46V19l4 2v-8.54z"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
        'bar-chart' => '<path d="M3 3v18h18"/><path d="M7 16V9"/><path d="M12 16V5"/><path d="M17 16v-3"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'x-circle' => '<circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6"/><path d="M9 9l6 6"/>',
        'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>',
    ];
    $path = $map[$name] ?? $map['check-square'];
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};

$jpres_total_alunos = count($alunos);
$jpres_total_marcados = count($presencas);
$jpres_turma_nome = '';
foreach ($turmas as $jt) {
    if ((int)$jt->id === (int)$turma_sel) {
        $jpres_turma_nome = trim(($jt->nome ?: $jt->classe) . (isset($jt->classe) && $jt->classe ? ' · ' . $jt->classe : ''), ' ·');
        break;
    }
}

?>
<style id="sige-jardim-presencas-produto-pro-v1210103">
/* SIGE SoftGenial v12.10.103 - Jardim Presenças: Compliance Visual Integral
   Escopo visual apenas: não altera handler, nonces, base de dados, permissões,
   notas, pagamentos ou regras de presença. */
@keyframes fadeInUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
body.sige-admin-app.sige-view-jardim_presencas .sg-product-page-head{display:none!important}
body.sige-admin-app.sige-view-jardim_presencas .sg-app-page{
    max-width:none!important;
    width:100%!important;
    padding-top:0!important;
    overflow-x:hidden!important;
}
body.sige-admin-app.sige-view-jardim_presencas .sg-app-content{
    padding-left:30px!important;
    padding-right:30px!important;
    overflow-x:hidden!important;
}
body.sige-admin-app.sige-view-jardim_presencas .sg-app-topbar{max-width:none!important}

.sige-jpres-wrap{
    --jp-blue:var(--sg-theme-primary,var(--color-brand-500));
    --jp-blue-dark:var(--sg-theme-primary-800,var(--color-ink-700));
    --jp-purple:var(--color-brand-500);
    --jp-purple-soft:var(--color-brand-50);
    --jp-ink:var(--color-black);
    --jp-muted:var(--color-slate-700);
    --jp-line:var(--color-ink-100);
    --jp-green:var(--color-success-500);
    --jp-amber:var(--color-warning-500);
    --jp-red:var(--color-danger-500);
    width:100%!important;
    max-width:none!important;
    min-width:0!important;
    margin:0!important;
    padding:0 0 28px!important;
    display:flex!important;
    flex-direction:column!important;
    gap:18px!important;
    color:var(--jp-ink)!important;
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif)!important;
}
.sige-jpres-wrap *{box-sizing:border-box}
.sige-jpres-wrap svg{width:18px;height:18px;display:block;stroke:currentColor!important;color:currentColor!important;fill:none!important}

/* HERO */
.sige-jpres-hero{
    position:relative!important;
    overflow:hidden!important;
    min-height:178px!important;
    border-radius:var(--radius-xl)!important;
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-info-50) 100%)!important;
    border:1px solid rgba(92,64,187,.12)!important;
    box-shadow:var(--shadow-lg);
    padding:32px 34px!important;
    margin:0!important;
    color:var(--jp-ink)!important;
    display:grid!important;
    grid-template-columns:minmax(0,1.25fr) minmax(300px,.75fr)!important;
    gap:22px!important;
    align-items:center!important;
    animation:fadeInUp .45s ease-out both!important;
}
.sige-jpres-hero:before{
    content:"";
    position:absolute;
    inset:auto -80px -130px auto;
    width:420px;
    height:300px;
    border-radius:var(--radius-pill);
    background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);
    pointer-events:none;
}
.sige-jpres-hero > *{position:relative;z-index:1}
.sige-jpres-hero-main{min-width:0}
.sige-jpres-hero-kicker{
    display:inline-flex!important;
    align-items:center!important;
    gap:var(--space-2)!important;
    margin:0 0 10px!important;
    padding:0!important;
    background:transparent!important;
    color:var(--jp-blue)!important;
    font-size:12px!important;
    line-height:1.2!important;
    font-weight:700!important;
    letter-spacing:.11em!important;
    text-transform:uppercase!important;
}
.sige-jpres-hero h1{
    margin:0!important;
    max-width:760px!important;
    color:var(--color-black)!important;
    font-size:31px!important;
    line-height:1.08!important;
    font-weight:700!important;
    letter-spacing:-.04em!important;
    font-family:inherit!important;
}
.sige-jpres-hero p{
    max-width:780px!important;
    margin:var(--space-3) 0 0!important;
    color:var(--color-slate-700)!important;
    font-size:15px!important;
    line-height:1.65!important;
    font-weight:500!important;
}
.sige-jpres-hero-chips{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
.sige-jpres-hero-chips span{
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
.sige-jpres-hero-chips svg{color:var(--jp-purple)}
.sige-jpres-hero-panel{
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
.sige-jpres-hero-panel:before{
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
.sige-jpres-hero-panel>*{position:relative;z-index:1}
.sige-jpres-panel-label{
    display:flex;
    align-items:center;
    gap:var(--space-2);
    color:var(--jp-purple);
    font-size:12px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.11em;
}
.sige-jpres-panel-value{
    display:block;
    color:var(--color-ink-900);
    font-size:36px;
    line-height:1.05;
    font-weight:700;
    letter-spacing:-.045em;
}
.sige-jpres-panel-text{
    display:block;
    max-width:330px;
    color:var(--color-slate-600);
    font-size:var(--fs-sm);
    line-height:1.55;
    font-weight:600;
}
.sige-jpres-hero-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}
.sige-jpres-btn{
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
    line-height:1.2!important;
}
.sige-jpres-btn:hover{transform:translateY(-1px)!important;box-shadow:0 4px 16px rgba(15,23,42,.08)}
.sige-jpres-primary,.sige-jpres-green,.sige-jpres-btn.primary{
    background:linear-gradient(135deg,var(--jp-blue),var(--jp-blue-dark))!important;
    color:var(--color-white)!important;
    border-color:var(--jp-blue)!important;
    box-shadow:var(--shadow-sm);
}
.sige-jpres-green{background:linear-gradient(135deg,var(--color-success-500),var(--color-success-800))!important;border-color:var(--color-success-500)!important}

/* KPIs */
.sige-jpres-kpis{
    display:grid!important;
    grid-template-columns:repeat(4,minmax(0,1fr))!important;
    gap:var(--space-4)!important;
    margin:0!important;
    width:100%!important;
    min-width:0!important;
}
.sige-jpres-kpi{
    position:relative!important;
    overflow:hidden!important;
    display:grid!important;
    grid-template-columns:auto minmax(0,1fr)!important;
    align-items:center!important;
    gap:14px!important;
    min-height:104px!important;
    background:var(--color-white)!important;
    border:1px solid rgba(28,32,54,.08)!important;
    border-top:0!important;
    border-radius:var(--radius-xl)!important;
    padding:18px 20px!important;
    box-shadow:var(--shadow-md);
}
.sige-jpres-kpi:after{content:"";position:absolute;right:-28px;top:-34px;width:92px;height:92px;border-radius:50%;background:color-mix(in srgb,var(--c) 12%,white)}
.sige-jpres-kpi-icon{
    width:52px;height:52px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;
    background:color-mix(in srgb,var(--c) 12%,white);color:var(--c);position:relative;z-index:1;
}
.sige-jpres-kpi-icon svg{width:24px;height:24px}
.sige-jpres-kpi > div{position:relative;z-index:1;min-width:0}
.sige-jpres-kpi strong{display:block!important;font-size:27px!important;font-weight:700!important;line-height:1!important;color:var(--color-black)!important;letter-spacing:-.03em!important}
.sige-jpres-kpi .lbl{font-size:12px;color:var(--color-slate-600);margin-top:7px;font-weight:600}

/* Alertas */
.sige-jpres-alert{
    padding:13px 16px!important;
    border-radius:var(--radius-lg)!important;
    margin:0!important;
    font-weight:700!important;
    font-size:var(--fs-sm)!important;
    line-height:1.5!important;
    border:1px solid transparent!important;
    box-shadow:var(--shadow-sm);
}
.sige-jpres-alert.ok{background:var(--color-success-50)!important;color:var(--color-success-900)!important;border-color:var(--color-success-200)!important}
.sige-jpres-alert.erro{background:var(--color-danger-50)!important;color:var(--color-danger-700)!important;border-color:var(--color-danger-200)!important}

/* Cards / filtros */
.sige-jpres-card{
    background:var(--color-white)!important;
    border-radius:var(--radius-xl)!important;
    box-shadow:var(--shadow-md);
    border:1px solid rgba(28,32,54,.08)!important;
    overflow:hidden!important;
}
.sige-jpres-filter{
    display:grid!important;
    grid-template-columns:minmax(260px,1fr) minmax(180px,.45fr) minmax(130px,.24fr)!important;
    gap:var(--space-3)!important;
    align-items:end!important;
    padding:18px!important;
    margin:0!important;
}
.sige-jpres-filter label{
    display:flex!important;
    flex-direction:column!important;
    gap:7px!important;
    font-size:var(--fs-xs)!important;
    font-weight:700!important;
    color:var(--color-slate-600)!important;
    text-transform:uppercase!important;
    letter-spacing:.07em!important;
}
.sige-jpres-filter select,.sige-jpres-filter input{
    width:100%!important;
    min-width:0!important;
    min-height:44px!important;
    height:44px!important;
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
.sige-jpres-filter select:focus,.sige-jpres-filter input:focus{border-color:rgba(90,63,214,.55)!important;box-shadow:0 1px 2px rgba(15,23,42,.04)}

.sige-jpres-list-head{
    padding:18px 20px!important;
    border-bottom:1px solid var(--color-slate-100)!important;
    display:flex!important;
    justify-content:space-between!important;
    gap:var(--space-3)!important;
    align-items:center!important;
    flex-wrap:wrap!important;
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 72%,var(--color-slate-50) 100%)!important;
}
.sige-jpres-list-head strong{
    display:flex!important;
    align-items:center!important;
    gap:9px!important;
    color:var(--color-ink-500)!important;
    font-size:15px!important;
    font-weight:700!important;
}

/* Tabela/lista */
.sige-jpres-table{
    width:100%!important;
    border-collapse:separate!important;
    border-spacing:0!important;
    font-size:var(--fs-sm)!important;
    min-width:980px!important;
}
.sige-jpres-table th{
    background:var(--color-slate-50)!important;
    color:var(--color-slate-600)!important;
    text-align:left!important;
    padding:12px 14px!important;
    border-bottom:1px solid var(--color-slate-100)!important;
    font-size:var(--fs-xs)!important;
    font-weight:700!important;
    text-transform:uppercase!important;
    letter-spacing:.06em!important;
}
.sige-jpres-table td{
    padding:14px!important;
    border-bottom:1px solid var(--color-slate-100)!important;
    color:var(--color-ink-800)!important;
    vertical-align:middle!important;
}
.sige-jpres-table img{border:1px solid var(--color-ink-100)!important;background:var(--color-slate-50)!important}
.sige-jpres-table strong{font-weight:700!important;color:var(--color-ink-500)!important}
.sige-jpres-st{
    display:grid!important;
    grid-template-columns:repeat(4,minmax(118px,1fr))!important;
    gap:var(--space-2)!important;
}
.sige-jpres-st label{
    position:relative!important;
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    gap:7px!important;
    min-height:42px!important;
    border:1px solid var(--color-ink-100)!important;
    border-radius:var(--radius-md)!important;
    padding:8px 10px!important;
    text-align:center!important;
    font-size:12px!important;
    font-weight:700!important;
    cursor:pointer!important;
    background:var(--color-white)!important;
    transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease!important;
}
.sige-jpres-st label:hover{transform:translateY(-1px);box-shadow:0 2px 8px rgba(15,23,42,.06)}
.sige-jpres-st input{margin:0!important;accent-color:var(--jp-blue)}
.sige-jpres-obs{
    width:100%!important;
    min-height:44px!important;
    border:1px solid var(--color-ink-100)!important;
    border-radius:var(--radius-md)!important;
    padding:10px 12px!important;
    font-size:var(--fs-sm)!important;
    font-weight:600!important;
    color:var(--color-ink-500)!important;
    box-shadow:var(--shadow-sm);
    resize:vertical!important;
}
.sige-jpres-obs:focus{outline:none!important;border-color:rgba(90,63,214,.55)!important;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.sige-jpres-table-scroll{overflow-x:auto!important;-webkit-overflow-scrolling:touch!important}
.sige-jpres-table-scroll::-webkit-scrollbar{height:8px}
.sige-jpres-table-scroll::-webkit-scrollbar-thumb{background:var(--color-slate-200);border-radius:999px}

.sige-jpres-empty{
    padding:42px 24px!important;
    text-align:center!important;
    color:var(--color-slate-500)!important;
    border:1px dashed var(--color-ink-200)!important;
    border-radius:var(--radius-xl)!important;
    background:var(--color-white)!important;
    box-shadow:var(--shadow-md);
}
.sige-jpres-save-footer{padding:18px 20px!important;text-align:right!important;background:var(--color-white)!important}

.sige-pro-popup-backdrop{position:fixed;inset:0;z-index:999999;display:flex;align-items:center;justify-content:center;padding:22px;background:rgba(15,23,42,.42);backdrop-filter:blur(5px)}.sige-pro-popup-card{width:min(520px,100%);display:grid;grid-template-columns:auto minmax(0,1fr);gap:15px;align-items:flex-start;padding:22px;border-radius:var(--radius-xl);border:1px solid rgba(255,255,255,.70);background:var(--color-white);box-shadow:var(--shadow-lg);color:var(--color-ink-500);position:relative}.sige-pro-popup-icon{width:52px;height:52px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;background:var(--color-success-100);color:var(--color-success-500)}.sige-pro-popup-card.is-error .sige-pro-popup-icon{background:var(--color-danger-100);color:var(--color-danger-600)}.sige-pro-popup-close{position:absolute;top:13px;right:13px;width:34px;height:34px;border-radius:var(--radius-md);border:1px solid rgba(15,23,42,.08);background:var(--color-white);cursor:pointer;color:var(--color-ink-500);font-size:var(--fs-lg);font-weight:700}.sige-pro-popup-card strong{display:block;margin:2px 42px 6px 0;font-size:18px;font-weight:700}.sige-pro-popup-card p{margin:0;color:var(--color-slate-700);font-size:var(--fs-base);line-height:1.55;font-weight:600}
/* Responsividade */
@media(max-width:1450px){
    .sige-jpres-hero{grid-template-columns:minmax(0,1fr) minmax(280px,.52fr)!important}
}
@media(max-width:1280px){
    .sige-jpres-kpis{grid-template-columns:repeat(2,minmax(0,1fr))!important}
    .sige-jpres-filter{grid-template-columns:repeat(2,minmax(0,1fr))!important}
    .sige-jpres-filter .sige-jpres-btn{width:100%!important}
    .sige-jpres-st{grid-template-columns:repeat(2,minmax(128px,1fr))!important}
}
@media(max-width:980px){
    body.sige-admin-app.sige-view-jardim_presencas .sg-app-content{padding-left:18px!important;padding-right:18px!important}
    .sige-jpres-hero{grid-template-columns:1fr!important}
}
@media(max-width:760px){
    body.sige-admin-app.sige-view-jardim_presencas .sg-app-content{padding-left:14px!important;padding-right:14px!important}
    .sige-jpres-wrap{gap:16px!important}
    .sige-jpres-hero{padding:26px 22px!important}
    .sige-jpres-hero h1{font-size:24px!important}
    .sige-jpres-kpis{grid-template-columns:1fr!important}
    .sige-jpres-filter{grid-template-columns:1fr!important}
    .sige-jpres-st{grid-template-columns:1fr!important}
    .sige-jpres-list-head{display:grid!important;grid-template-columns:1fr!important}
    .sige-jpres-btn,.sige-jpres-save-footer .sige-jpres-btn{width:100%!important}
    .sige-jpres-save-footer{text-align:stretch!important}
}
</style>
<div class="sige-jpres-wrap">
  <section class="sige-jpres-hero" aria-label="Presenças do Jardim">
    <div class="sige-jpres-hero-main">
      <div class="sige-jpres-hero-kicker">
        <?php echo $jpres_icon('check-square'); ?>
        <span>Pré-Escolar</span>
      </div>
      <h1>Presenças do Jardim</h1>
      <p>Registe presença, atraso, falta e falta justificada dos alunos do Jardim de Infância com leitura clara por turma e data.</p>
      <div class="sige-jpres-hero-chips">
        <span><?php echo $jpres_icon('calendar'); ?> Ano Lectivo <?php echo esc_html($ano); ?></span>
        <span><?php echo $jpres_icon('clock'); ?> <?php echo esc_html(wp_date('d/m/Y', strtotime($data_sel))); ?></span>
        <?php if ($jpres_turma_nome): ?><span><?php echo $jpres_icon('users'); ?> <?php echo esc_html($jpres_turma_nome); ?></span><?php endif; ?>
      </div>
      <div class="sige-jpres-hero-actions">
        <a href="?page=sige-app&view=jardim_relatorio&turma_id=<?php echo esc_attr($turma_sel); ?>" class="sige-jpres-btn">
          <?php echo $jpres_icon('bar-chart'); ?> Ver relatório
        </a>
      </div>
    </div>
    <aside class="sige-jpres-hero-panel" aria-label="Estado das presenças">
      <div class="sige-jpres-panel-label"><?php echo $jpres_icon('check-square'); ?><span>Estado da chamada</span></div>
      <strong class="sige-jpres-panel-value"><?php echo esc_html((string)$jpres_total_marcados); ?>/<?php echo esc_html((string)$jpres_total_alunos); ?></strong>
      <span class="sige-jpres-panel-text">Registo(s) já guardado(s) para a turma e data seleccionadas.</span>
    </aside>
  </section>
  <?php if (isset($_GET['msg'])):
    $jpres_msg = sanitize_key((string)$_GET['msg']);
?>
  <div class="sige-pro-popup-backdrop" role="presentation">
    <div class="sige-pro-popup-card <?php echo $jpres_msg === 'ok' ? '' : 'is-error'; ?>" role="dialog" aria-modal="true" aria-live="polite">
      <span class="sige-pro-popup-icon"><?php echo $jpres_icon($jpres_msg === 'ok' ? 'check-square' : 'x-circle'); ?></span>
      <div>
        <strong><?php echo $jpres_msg === 'ok' ? 'Guardado com sucesso' : 'Não foi possível guardar'; ?></strong>
        <p><?php echo $jpres_msg === 'ok' ? 'Presenças guardadas com sucesso.' : 'Confirme se há alunos activos e tente novamente.'; ?></p>
      </div>
      <button type="button" class="sige-pro-popup-close" aria-label="Fechar aviso" data-sige-act="sigeFecharPopupBackdrop">×</button>
    </div>
  </div>
<?php endif; ?>
  <form method="get" class="sige-jpres-card sige-jpres-filter">
    <input type="hidden" name="page" value="sige-app"><input type="hidden" name="view" value="jardim_presencas">
    <label>Turma<select name="turma_id"><?php foreach($turmas as $t): ?><option value="<?php echo esc_attr($t->id); ?>" <?php selected($turma_sel,(int)$t->id); ?>><?php echo esc_html(($t->nome ?: $t->classe)); ?></option><?php endforeach; ?></select></label>
    <label>Data<input type="date" name="data_reg" value="<?php echo esc_attr($data_sel); ?>"></label>
    <button class="sige-jpres-btn sige-jpres-primary" type="submit"><?php echo $jpres_icon('filter'); ?> Filtrar</button>
  </form>
  <div class="sige-jpres-kpis">
    <?php
      $jpres_status_icons = [
        'presente' => 'check-square',
        'atraso' => 'clock',
        'falta' => 'x-circle',
        'falta_justificada' => 'file-text',
      ];
      foreach($status_cfg as $k=>$c):
    ?>
      <div class="sige-jpres-kpi" style="--c:<?php echo esc_attr($c[1]); ?>">
        <span class="sige-jpres-kpi-icon"><?php echo $jpres_icon($jpres_status_icons[$k] ?? 'check-square'); ?></span>
        <div>
          <strong><?php echo (int)$total_pres[$k]; ?></strong>
          <div class="lbl"><?php echo esc_html($c[0]); ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (!$turma_sel || empty($alunos)): ?>
    <div class="sige-jpres-empty">Nenhuma turma ou aluno da Pré-Primária encontrado.</div>
  <?php else: ?>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sige-jpres-card">
    <input type="hidden" name="action" value="sige_jardim_presencas_salvar"><input type="hidden" name="turma_id" value="<?php echo esc_attr($turma_sel); ?>"><input type="hidden" name="data_registo" value="<?php echo esc_attr($data_sel); ?>"><input type="hidden" name="ano_lectivo" value="<?php echo esc_attr($ano); ?>"><?php wp_nonce_field('sige_jardim_presencas_nonce','_jpres_nonce'); ?>
    <div class="sige-jpres-list-head"><strong><?php echo $jpres_icon('calendar'); ?> Lista de presença - <?php echo esc_html(wp_date('d/m/Y', strtotime($data_sel))); ?></strong><button type="submit" class="sige-jpres-btn sige-jpres-green"><?php echo $jpres_icon('save'); ?> Guardar presenças</button></div>
    <div class="sige-jpres-table-scroll"><table class="sige-jpres-table"><thead><tr><th>Aluno</th><th style="min-width:440px">Estado</th><th>Observação</th></tr></thead><tbody>
    <?php foreach($alunos as $al): $aid=(int)$al->id; $row=$presencas[$aid]??null; $st=$row->status??'presente'; $obs=$row->observacao??''; $foto=$al->foto ?: 'https://ui-avatars.com/api/?name='.urlencode($al->nome_completo).'&background=0f172a&color=fff&size=40'; ?>
      <tr><td><div style="display:flex;align-items:center;gap:10px"><img src="<?php echo esc_url($foto); ?>" style="width:34px;height:34px;border-radius:50%;object-fit:cover"><strong><?php echo esc_html($al->nome_completo); ?></strong></div></td><td><div class="sige-jpres-st"><?php foreach($status_cfg as $k=>$c): ?><label style="border-color:<?php echo $st===$k?esc_attr($c[1]):'var(--color-slate-200)'; ?>;background:<?php echo $st===$k?esc_attr($c[1]).'16':'#fff'; ?>"><input type="radio" name="presenca[<?php echo $aid; ?>][status]" value="<?php echo esc_attr($k); ?>" <?php checked($st,$k); ?>><?php echo $c[2].' '.esc_html($c[0]); ?></label><?php endforeach; ?></div></td><td><textarea class="sige-jpres-obs" name="presenca[<?php echo $aid; ?>][observacao]" placeholder="Ex.: chegou às 08h20, justificativo entregue, indisposição..."><?php echo esc_textarea($obs); ?></textarea></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <div class="sige-jpres-save-footer"><button type="submit" class="sige-jpres-btn sige-jpres-green"><?php echo $jpres_icon('save'); ?> Guardar presenças</button></div>
  </form>
  <?php endif; ?>
</div>
