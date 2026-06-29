<?php
/**
 * SIGE SoftGenial - Motor Multicurrículo / Currículos
 *
 * v12.11.9.34 - Junho 2026
 * - Visual harmonizado com o Painel Principal / Dashboard V2 MJS-grade
 * - Hero próprio, KPIs, cartões PRO, tabela premium e fluxo de próximas fases
 * - Mantém intactos: permissões, formulários admin-post, nonces, sincronização e regras académicas
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sige_curriculum_can_manage') || !sige_curriculum_can_manage()) {
    if (function_exists('sige_ui_render_access_unavailable')) {
        sige_ui_render_access_unavailable(true);
    } else {
        echo '<div class="notice notice-warning"><p>Acesso não disponível.</p></div>';
    }
    return;
}

$eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
$ready = function_exists('sige_curriculum_tables_ready') && sige_curriculum_tables_ready();

if ($ready && function_exists('sige_curriculum_seed_school')) {
    sige_curriculum_seed_school($eid);
}

$stats = $ready && function_exists('sige_curriculum_get_stats')
    ? sige_curriculum_get_stats($eid)
    : ['ready' => false];

$profiles  = $ready && function_exists('sige_curriculum_get_profile_options') ? sige_curriculum_get_profile_options($eid) : [];
$active    = $stats['active_profile'] ?? null;
$last_sync = is_array($stats['last_sync'] ?? null) ? $stats['last_sync'] : [];

$sg_curriculum_icon = static function (string $name): string {
    if (function_exists('sige_ui_icon')) {
        return sige_ui_icon($name);
    }
    return '<svg class="sg-svg-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.8"/><rect x="14" y="3" width="7" height="7" rx="1.8"/><rect x="3" y="14" width="7" height="7" rx="1.8"/><rect x="14" y="14" width="7" height="7" rx="1.8"/></svg>';
};

$active_name  = $active->nome ?? 'Moçambique / SNE';
$active_code  = $active->code ?? 'mz_sne';
$active_scale = $active->assessment_scale ?? '0-20';
$active_mode  = $active->period_model ?? 'Trimestral';
$profile_total = (int)($stats['profiles'] ?? count($profiles));
$grades_total  = (int)($stats['grades'] ?? 0);
$subjects_total = (int)($stats['subjects'] ?? 0);
$last_sync_rows = (int)($last_sync['rows_found'] ?? 0);
$last_sync_at   = (string)($last_sync['synced_at'] ?? '');
?>

<style id="sg-curriculum-dashboard-grade">
/* ============================================================================
   SoftGenial Produto PRO - Currículos alinhado ao Painel Principal
   Escopo: camada visual/UX. Não altera permissões, regras académicas ou dados.
   ============================================================================ */
body.sige-view-curriculos .sg-product-page-head{display:none!important;}

@keyframes sgCurFadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
@keyframes sgCurPulse{0%,100%{box-shadow:0 1px 2px rgba(15,23,42,.04)}50%{box-shadow:0 1px 2px rgba(15,23,42,.04)}}
@keyframes sgCurFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}

.sg-curriculum-v2{
    --sgc-purple:var(--sg-theme-primary,var(--color-brand-500));
    --sgc-purple-dark:var(--sg-theme-primary-800,var(--color-brand-700));
    --sgc-purple-soft:var(--sg-theme-soft,var(--color-brand-50));
    --sgc-ink:var(--color-ink-500);
    --sgc-muted:var(--color-slate-500);
    --sgc-line:var(--color-ink-100);
    --sgc-bg:var(--color-ink-50);
    --sgc-green:var(--color-success-700);
    --sgc-red:var(--color-danger-500);
    --sgc-amber:var(--color-warning-500);
    --sgc-blue:var(--color-info-400);
    font-family:'Poppins','Inter','Segoe UI',system-ui,sans-serif;
    color:var(--sgc-ink);
}
.sg-curriculum-v2 *{box-sizing:border-box;}
.sg-cur-shell{display:flex;flex-direction:column;gap:var(--space-5);animation:sgCurFadeUp .45s ease-out both;}

/* HERO - mesmo DNA visual do Painel Principal */
.sg-cur-hero{
    position:relative;
    overflow:hidden;
    min-height:178px;
    border-radius:var(--radius-xl);
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-brand-100) 100%);
    border:1px solid rgba(92,64,187,.12);
    box-shadow:var(--shadow-lg);
    padding:32px 34px;
    display:grid;
    grid-template-columns:minmax(0,1.04fr) minmax(340px,.96fr);
    gap:22px;
    align-items:center;
}
.sg-cur-hero:before{content:"";position:absolute;inset:auto -80px -130px auto;width:420px;height:300px;background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);pointer-events:none;}
.sg-cur-hero-copy,.sg-cur-hero-panel{position:relative;z-index:1;}
.sg-cur-kicker{display:flex;align-items:center;gap:var(--space-2);margin-bottom:10px;color:var(--sgc-purple);font-size:12px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;}
.sg-cur-kicker svg{width:18px!important;height:18px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sg-cur-title{margin:0;color:var(--color-black);font-size:31px;line-height:1.08;font-weight:700;letter-spacing:-.04em;}
.sg-cur-subtitle{max-width:720px;margin:var(--space-3) 0 0;color:var(--color-slate-700);font-size:15px;line-height:1.65;font-weight:500;}
.sg-cur-hero-actions{display:flex;flex-wrap:wrap;gap:var(--space-3);margin-top:24px;}
.sg-cur-chip{min-height:42px;display:inline-flex;align-items:center;gap:9px;border-radius:var(--radius-pill);padding:0 var(--space-4);border:1px solid var(--color-ink-100);background:var(--color-white);color:var(--color-ink-800);font-size:var(--fs-sm);font-weight:700;box-shadow:var(--shadow-sm);}
.sg-cur-chip svg{width:17px!important;height:17px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sg-cur-chip.primary{background:linear-gradient(135deg,var(--color-brand-400),var(--color-brand-600));border-color:transparent;color:var(--color-white);box-shadow:var(--shadow-md);}
.sg-cur-chip.soft{background:var(--color-brand-50);border-color:var(--color-brand-100);color:var(--sgc-purple);box-shadow:none;}

.sg-cur-hero-panel{min-height:148px;border-radius:var(--radius-xl);background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.18));padding:22px;overflow:hidden;border:1px solid rgba(92,64,187,.08);display:flex;flex-direction:column;justify-content:center;gap:10px;}
.sg-cur-hero-panel:before{content:"";position:absolute;right:26px;bottom:22px;width:150px;height:96px;border-radius:var(--radius-lg);background:rgba(109,93,252,.18);box-shadow:inset 0 0 0 2px rgba(109,93,252,.12);}
.sg-cur-hero-panel:after{content:"";position:absolute;right:55px;bottom:45px;width:92px;height:52px;border-radius:var(--radius-md);background:rgba(255,255,255,.48);box-shadow:var(--shadow-sm);animation:sgCurFloat 4.6s ease-in-out infinite;}
.sg-cur-hero-panel>*{position:relative;z-index:1;}
.sg-cur-panel-label{display:flex;align-items:center;gap:var(--space-2);margin:0;color:var(--sgc-purple);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.11em;}
.sg-cur-panel-label svg{width:17px!important;height:17px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;}
.sg-cur-hero-panel strong{display:block;color:var(--color-ink-900);font-size:30px;line-height:1.05;font-weight:700;letter-spacing:-.04em;}
.sg-cur-hero-panel small{display:block;max-width:350px;margin:0;color:var(--color-slate-600);font-size:var(--fs-sm);line-height:1.55;font-weight:600;}
.sg-cur-panel-steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:var(--space-2);margin-top:6px;}
.sg-cur-panel-step{border-radius:var(--radius-md);background:rgba(255,255,255,.68);border:1px solid rgba(255,255,255,.75);padding:10px;color:var(--color-slate-800);font-size:var(--fs-xs);font-weight:700;line-height:1.25;}
.sg-cur-panel-step span{display:block;margin-bottom:4px;color:var(--sgc-purple);font-size:10px;text-transform:uppercase;letter-spacing:.08em;}

/* KPIs */
.sg-cur-kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--space-4);}
.sg-cur-kpi-card{position:relative;overflow:hidden;display:grid;grid-template-columns:auto minmax(0,1fr);gap:var(--space-4);align-items:center;min-height:104px;padding:18px 20px;border-radius:var(--radius-xl);background:var(--color-white);border:1px solid rgba(28,32,54,.08);box-shadow:var(--shadow-md);}
.sg-cur-kpi-card:after{content:"";position:absolute;right:-28px;top:-34px;width:92px;height:92px;border-radius:50%;background:var(--kpi-soft,var(--color-brand-50));}
.sg-cur-kpi-icon{width:52px;height:52px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;background:var(--kpi-soft,var(--color-brand-50));color:var(--kpi-color,var(--sgc-purple));position:relative;z-index:1;}
.sg-cur-kpi-icon svg{width:24px!important;height:24px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sg-cur-kpi-label{font-size:var(--fs-sm);font-weight:600;color:var(--color-slate-600);margin-bottom:6px;}
.sg-cur-kpi-value{font-size:27px;line-height:1;font-weight:700;letter-spacing:-.03em;color:var(--color-black);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.sg-cur-kpi-note{margin-top:7px;font-size:12px;font-weight:600;color:var(--color-ink-400);line-height:1.35;}
.sg-cur-kpi-note strong{color:var(--kpi-color,var(--sgc-purple));}

/* CARDS / PANELS */
.sg-cur-grid{display:grid;grid-template-columns:minmax(0,1.18fr) minmax(360px,.82fr);gap:18px;align-items:start;}
.sg-cur-card{background:var(--color-white);border:1px solid rgba(30,34,60,.08);border-radius:var(--radius-xl);box-shadow:var(--shadow-md);overflow:hidden;min-width:0;}
.sg-cur-card-header{display:flex;align-items:center;justify-content:space-between;gap:var(--space-4);padding:20px 22px 14px;}
.sg-cur-card-title{display:flex;align-items:center;gap:var(--space-3);min-width:0;}
.sg-cur-card-icon{width:40px;height:40px;border-radius:var(--radius-md);background:var(--icon-bg,var(--color-brand-50));color:var(--icon-color,var(--sgc-purple));display:flex;align-items:center;justify-content:center;flex:0 0 auto;}
.sg-cur-card-icon svg{width:20px!important;height:20px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sg-cur-card-title h3{margin:0;color:var(--color-ink-500);font-size:17px;line-height:1.1;font-weight:700;letter-spacing:-.03em;}
.sg-cur-card-title p{margin:5px 0 0;color:var(--color-ink-400);font-size:12px;font-weight:600;}
.sg-cur-card-badge{white-space:nowrap;border-radius:var(--radius-pill);background:var(--color-brand-50);color:var(--sgc-purple);padding:8px 11px;font-size:12px;font-weight:700;}
.sg-cur-card-body{padding:10px 22px 22px;}

.sg-cur-alert{display:grid;grid-template-columns:auto minmax(0,1fr);gap:var(--space-3);align-items:start;border-radius:var(--radius-lg);padding:14px 15px;margin:0 0 var(--space-4);font-size:var(--fs-sm);line-height:1.55;font-weight:600;border:1px solid var(--alert-line,var(--color-slate-100));background:var(--alert-bg,var(--color-slate-50));color:var(--alert-color,var(--color-slate-800));}
.sg-cur-alert svg{width:18px!important;height:18px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;margin-top:1px;}
.sg-cur-alert strong{font-weight:700;}
.sg-cur-alert.ok{--alert-bg:var(--color-success-100);--alert-line:var(--color-success-200);--alert-color:var(--color-success-900);}
.sg-cur-alert.warn{--alert-bg:var(--color-warning-100);--alert-line:var(--color-warning-200);--alert-color:var(--color-warning-800);}
.sg-cur-alert.info{--alert-bg:var(--color-info-50);--alert-line:var(--color-info-100);--alert-color:var(--color-info-700);}

.sg-cur-profile-form{display:grid;grid-template-columns:minmax(260px,1fr) auto;gap:10px;margin-bottom:16px;padding:var(--space-3);border-radius:var(--radius-lg);background:var(--color-white);border:1px solid var(--color-slate-100);box-shadow:var(--shadow-sm);}
.sg-cur-select{width:100%;min-height:48px;border:1px solid var(--color-ink-100)!important;background:var(--color-white);border-radius:var(--radius-md)!important;padding:0 13px!important;color:var(--color-ink-500)!important;font-size:var(--fs-sm)!important;font-weight:600!important;box-shadow:none!important;outline:none!important;}
.sg-cur-select:focus{border-color:var(--color-brand-200)!important;box-shadow:var(--shadow-xs);}
.sg-cur-btn{min-height:48px;display:inline-flex;align-items:center;justify-content:center;gap:9px;border:0;border-radius:var(--radius-md);background:linear-gradient(135deg,var(--color-brand-400),var(--color-brand-600));color:var(--color-white);padding:0 18px;font-size:var(--fs-sm);font-weight:700;cursor:pointer;text-decoration:none;box-shadow:var(--shadow-sm);transition:transform .18s ease,box-shadow .18s ease;}
.sg-cur-btn:hover{transform:translateY(-1px);box-shadow:var(--shadow-md);color:var(--color-white);}
.sg-cur-btn svg{width:17px!important;height:17px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sg-cur-btn.secondary{background:var(--color-white);color:var(--color-ink-900);border:1px solid var(--color-ink-100);box-shadow:var(--shadow-sm);}
.sg-cur-btn.secondary:hover{box-shadow:var(--shadow-sm);color:var(--sgc-purple);border-color:var(--color-info-100);}

.sg-cur-table-wrap{overflow:auto;border:1px solid var(--color-slate-100);border-radius:var(--radius-lg);background:var(--color-white);}
.sg-cur-table{width:100%;border-collapse:separate;border-spacing:0;min-width:860px;}
.sg-cur-table th{position:sticky;top:0;z-index:1;background:var(--color-slate-50);color:var(--color-slate-600);font-size:var(--fs-xs);text-transform:uppercase;letter-spacing:.08em;text-align:left;padding:13px 14px;font-weight:700;border-bottom:1px solid var(--color-slate-100);}
.sg-cur-table td{padding:15px 14px;border-top:1px solid var(--color-slate-100);font-size:var(--fs-sm);color:var(--color-ink-500);vertical-align:top;line-height:1.42;}
.sg-cur-table tr:first-child td{border-top:0;}
.sg-cur-table tr:hover td{background:var(--color-white);}
.sg-cur-code{display:inline-flex;align-items:center;max-width:100%;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;background:var(--color-slate-50);border:1px solid var(--color-slate-100);border-radius:var(--radius-pill);padding:var(--space-1) var(--space-2);font-size:var(--fs-xs);color:var(--color-slate-700);font-weight:700;}
.sg-cur-muted{color:var(--color-slate-500);font-size:12px;line-height:1.5;font-weight:600;}
.sg-cur-badge{display:inline-flex;align-items:center;justify-content:center;gap:6px;border-radius:var(--radius-pill);padding:7px 10px;font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.06em;white-space:nowrap;}
.sg-cur-badge.active{background:var(--color-success-100);color:var(--color-success-500);}
.sg-cur-badge.model{background:var(--color-brand-50);color:var(--sgc-purple);}
.sg-cur-badge.beta{background:var(--color-warning-100);color:var(--color-warning-800);}

.sg-cur-side-stack{display:flex;flex-direction:column;gap:18px;}
.sg-cur-sync-box{border-radius:var(--radius-lg);background:linear-gradient(135deg,var(--color-slate-50) 0%,var(--color-white) 100%);border:1px solid var(--color-slate-100);padding:var(--space-4);}
.sg-cur-sync-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:14px 0;}
.sg-cur-sync-mini{border-radius:var(--radius-md);background:var(--color-white);border:1px solid var(--color-slate-100);padding:var(--space-3);}
.sg-cur-sync-mini small{display:block;margin-bottom:5px;color:var(--color-ink-400);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;}
.sg-cur-sync-mini strong{display:block;color:var(--color-ink-500);font-size:var(--fs-sm);font-weight:700;line-height:1.35;word-break:break-word;}

.sg-cur-steps{display:flex;flex-direction:column;gap:10px;}
.sg-cur-step{display:grid;grid-template-columns:auto minmax(0,1fr);gap:var(--space-3);align-items:start;border-radius:var(--radius-lg);background:var(--color-slate-50);border:1px solid var(--color-slate-100);padding:13px 14px;}
.sg-cur-step-number{width:32px;height:32px;border-radius:var(--radius-md);background:var(--step-bg,var(--color-brand-50));color:var(--step-color,var(--sgc-purple));display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;}
.sg-cur-step b{display:block;margin:0 0 var(--space-1);color:var(--color-ink-500);font-size:var(--fs-sm);font-weight:700;}
.sg-cur-step span{display:block;color:var(--color-slate-500);font-size:12px;line-height:1.5;font-weight:600;}

.sg-cur-quick-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;}
.sg-cur-quick{min-height:70px;border:1px solid var(--color-slate-100);border-radius:var(--radius-lg);background:var(--color-white);display:flex;align-items:center;gap:11px;padding:var(--space-3);text-decoration:none;color:var(--color-ink-800);font-size:var(--fs-sm);font-weight:700;transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease;}
.sg-cur-quick:hover{transform:translateY(-1px);box-shadow:var(--shadow-sm);border-color:var(--color-info-100);color:var(--sgc-purple);}
.sg-cur-quick-ico{width:38px;height:38px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;background:var(--qbg,var(--color-brand-50));color:var(--qcolor,var(--sgc-purple));flex:0 0 auto;}
.sg-cur-quick-ico svg{width:19px!important;height:19px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}

.sg-cur-empty{border-radius:var(--radius-lg);border:1px dashed var(--color-slate-200);background:var(--color-white);padding:22px;text-align:center;color:var(--color-slate-500);font-size:var(--fs-sm);font-weight:600;line-height:1.55;}
.sg-cur-empty svg{display:block;width:34px!important;height:34px!important;margin:0 auto 10px;color:var(--sgc-purple)!important;stroke:currentColor!important;fill:none!important;opacity:1!important;}

@media (max-width:1500px){.sg-cur-hero{grid-template-columns:1fr}.sg-cur-hero-panel{display:none}.sg-cur-grid{grid-template-columns:1fr 1fr}}
@media (max-width:1100px){.sg-cur-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.sg-cur-grid{grid-template-columns:1fr}.sg-cur-quick-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:720px){.sg-cur-hero{padding:24px 20px}.sg-cur-title{font-size:24px}.sg-cur-hero-actions{gap:9px}.sg-cur-chip{width:100%;justify-content:center}.sg-cur-kpi-grid{grid-template-columns:1fr}.sg-cur-card-header{align-items:flex-start;flex-direction:column}.sg-cur-card-badge{white-space:normal}.sg-cur-card-body{padding:8px 16px 18px}.sg-cur-profile-form{grid-template-columns:1fr}.sg-cur-btn{width:100%}.sg-cur-sync-meta{grid-template-columns:1fr}.sg-cur-quick-grid{grid-template-columns:1fr}}
</style>

<div class="sg-curriculum-v2" aria-label="Currículos SoftGenial">
    <div class="sg-cur-shell">
        <section class="sg-cur-hero" aria-label="Resumo do Motor Multicurrículo">
            <div class="sg-cur-hero-copy">
                <div class="sg-cur-kicker"><?php echo $sg_curriculum_icon('book'); ?> Área Académica</div>
                <h1 class="sg-cur-title">Currículos</h1>
                <p class="sg-cur-subtitle">Configure a base curricular que prepara o SoftGenial para diferentes modelos de ensino, mantendo o modo seguro: esta área ainda não altera notas, pautas, boletins, DEC, transição, fórmulas ou documentos oficiais validados.</p>
                <div class="sg-cur-hero-actions" aria-label="Estado do módulo">
                    <span class="sg-cur-chip primary"><?php echo $sg_curriculum_icon('shield'); ?> Modo fundação segura</span>
                    <span class="sg-cur-chip"><?php echo $sg_curriculum_icon('school'); ?> Perfil activo: <?php echo esc_html($active_name); ?></span>
                    <span class="sg-cur-chip soft"><?php echo $sg_curriculum_icon('activity'); ?> Sem impacto operacional automático</span>
                </div>
            </div>
            <div class="sg-cur-hero-panel" aria-hidden="true">
                <div class="sg-cur-panel-label"><?php echo $sg_curriculum_icon('grid'); ?> Gestão de Currículos</div>
                <strong>Preparado para crescer</strong>
                <small>A escola mantém o SNE como operação principal enquanto o motor multicurrículo evolui por fases controladas.</small>
                <div class="sg-cur-panel-steps">
                    <div class="sg-cur-panel-step"><span>1</span>Mapear matriz</div>
                    <div class="sg-cur-panel-step"><span>2</span>Validar perfis</div>
                    <div class="sg-cur-panel-step"><span>3</span>Activar por fases</div>
                </div>
            </div>
        </section>

        <?php if (isset($_GET['curriculum_sync'])): ?>
            <div class="sg-cur-alert <?php echo $_GET['curriculum_sync'] === 'ok' ? 'ok' : 'warn'; ?>">
                <?php echo $sg_curriculum_icon($_GET['curriculum_sync'] === 'ok' ? 'check' : 'shield'); ?>
                <div>Sincronização concluída. Inseridos: <strong><?php echo (int)($_GET['inserted'] ?? 0); ?></strong>. Actualizados: <strong><?php echo (int)($_GET['updated'] ?? 0); ?></strong>.</div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['profile_saved'])): ?>
            <div class="sg-cur-alert ok">
                <?php echo $sg_curriculum_icon('check'); ?>
                <div>Perfil seleccionado guardado em modo fundação. Esta selecção ainda não muda cálculos académicos.</div>
            </div>
        <?php endif; ?>

        <?php if (!$ready): ?>
            <article class="sg-cur-card">
                <div class="sg-cur-card-header">
                    <div class="sg-cur-card-title">
                        <div class="sg-cur-card-icon" style="--icon-bg:var(--color-warning-50);--icon-color:var(--color-warning-500);"><?php echo $sg_curriculum_icon('shield'); ?></div>
                        <div>
                            <h3>Motor ainda não preparado</h3>
                            <p>As tabelas de currículos ainda não estão disponíveis.</p>
                        </div>
                    </div>
                    <span class="sg-cur-card-badge">Atenção</span>
                </div>
                <div class="sg-cur-card-body">
                    <div class="sg-cur-alert warn">
                        <?php echo $sg_curriculum_icon('shield'); ?>
                        <div><strong>Tabelas ainda não criadas.</strong> Entre como administrador e actualize esta página para permitir a preparação da base de dados. Se persistir, reactive o plugin no ambiente de testes.</div>
                    </div>
                </div>
            </article>
        <?php else: ?>
            <section class="sg-cur-kpi-grid" aria-label="Indicadores do Motor Multicurrículo">
                <article class="sg-cur-kpi-card" style="--kpi-soft:var(--color-brand-50);--kpi-color:var(--color-brand-400);">
                    <div class="sg-cur-kpi-icon"><?php echo $sg_curriculum_icon('book'); ?></div>
                    <div>
                        <div class="sg-cur-kpi-label">Perfil activo</div>
                        <div class="sg-cur-kpi-value" title="<?php echo esc_attr($active_name); ?>"><?php echo esc_html($active_name); ?></div>
                        <div class="sg-cur-kpi-note">Código: <strong><?php echo esc_html($active_code); ?></strong></div>
                    </div>
                </article>
                <article class="sg-cur-kpi-card" style="--kpi-soft:var(--color-info-50);--kpi-color:var(--color-info-500);">
                    <div class="sg-cur-kpi-icon"><?php echo $sg_curriculum_icon('grid'); ?></div>
                    <div>
                        <div class="sg-cur-kpi-label">Perfis disponíveis</div>
                        <div class="sg-cur-kpi-value"><?php echo (int)$profile_total; ?></div>
                        <div class="sg-cur-kpi-note">Modelos preparados para evolução controlada.</div>
                    </div>
                </article>
                <article class="sg-cur-kpi-card" style="--kpi-soft:var(--color-success-50);--kpi-color:var(--color-success-500);">
                    <div class="sg-cur-kpi-icon"><?php echo $sg_curriculum_icon('school'); ?></div>
                    <div>
                        <div class="sg-cur-kpi-label">Classes mapeadas</div>
                        <div class="sg-cur-kpi-value"><?php echo (int)$grades_total; ?></div>
                        <div class="sg-cur-kpi-note">Cópia estrutural da matriz actual.</div>
                    </div>
                </article>
                <article class="sg-cur-kpi-card" style="--kpi-soft:var(--color-warning-50);--kpi-color:var(--color-warning-500);">
                    <div class="sg-cur-kpi-icon"><?php echo $sg_curriculum_icon('clipboard'); ?></div>
                    <div>
                        <div class="sg-cur-kpi-label">Disciplinas mapeadas</div>
                        <div class="sg-cur-kpi-value"><?php echo (int)$subjects_total; ?></div>
                        <div class="sg-cur-kpi-note">Sem afectar a matriz original.</div>
                    </div>
                </article>
            </section>

            <section class="sg-cur-grid" aria-label="Configuração de Currículos">
                <article class="sg-cur-card">
                    <div class="sg-cur-card-header">
                        <div class="sg-cur-card-title">
                            <div class="sg-cur-card-icon" style="--icon-bg:var(--color-brand-50);--icon-color:var(--color-brand-400);"><?php echo $sg_curriculum_icon('book'); ?></div>
                            <div>
                                <h3>Perfis curriculares</h3>
                                <p>Selecção e leitura dos modelos preparados no sistema.</p>
                            </div>
                        </div>
                        <span class="sg-cur-card-badge">Fundação</span>
                    </div>
                    <div class="sg-cur-card-body">
                        <div class="sg-cur-alert info">
                            <?php echo $sg_curriculum_icon('shield'); ?>
                            <div>A selecção abaixo é guardada apenas como preparação. O núcleo académico validado continua a operar como Moçambique/SNE até fazermos a integração por fases.</div>
                        </div>

                        <?php if (!empty($profiles)): ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sg-cur-profile-form">
                                <?php wp_nonce_field('sige_curriculum_select_profile'); ?>
                                <input type="hidden" name="action" value="sige_curriculum_select_profile">
                                <select name="profile_id" class="sg-cur-select" aria-label="Seleccionar perfil curricular">
                                    <?php foreach ($profiles as $p): ?>
                                        <option value="<?php echo (int)$p->id; ?>" <?php selected((int)($active->id ?? 0), (int)$p->id); ?>><?php echo esc_html($p->nome . ' - ' . $p->status); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="sg-cur-btn"><?php echo $sg_curriculum_icon('check'); ?> Guardar perfil</button>
                            </form>

                            <div class="sg-cur-table-wrap">
                                <table class="sg-cur-table">
                                    <thead>
                                        <tr>
                                            <th>Perfil</th>
                                            <th>País / Modelo</th>
                                            <th>Escala</th>
                                            <th>Períodos</th>
                                            <th>Estado</th>
                                            <th>Descrição</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($profiles as $p): ?>
                                            <?php
                                                $is_active = ((int)($active->id ?? 0) === (int)$p->id);
                                                $badge_class = $is_active ? 'active' : (((string)$p->status === 'beta') ? 'beta' : 'model');
                                                $badge_label = $is_active ? 'Activo' : (string)$p->status;
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo esc_html($p->nome); ?></strong><br>
                                                    <span class="sg-cur-code"><?php echo esc_html($p->code); ?></span>
                                                </td>
                                                <td>
                                                    <?php echo esc_html($p->pais); ?><br>
                                                    <span class="sg-cur-muted"><?php echo esc_html($p->tipo); ?></span>
                                                </td>
                                                <td><?php echo esc_html($p->assessment_scale); ?></td>
                                                <td><?php echo esc_html($p->period_model); ?></td>
                                                <td><span class="sg-cur-badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html($badge_label); ?></span></td>
                                                <td><?php echo esc_html($p->descricao); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="sg-cur-empty">
                                <?php echo $sg_curriculum_icon('book'); ?>
                                Ainda não existem perfis curriculares disponíveis para esta escola. Actualize a página ou reactive o plugin no ambiente de testes para recriar os modelos base.
                            </div>
                        <?php endif; ?>
                    </div>
                </article>

                <aside class="sg-cur-side-stack">
                    <article class="sg-cur-card">
                        <div class="sg-cur-card-header">
                            <div class="sg-cur-card-title">
                                <div class="sg-cur-card-icon" style="--icon-bg:var(--color-info-50);--icon-color:var(--color-info-500);"><?php echo $sg_curriculum_icon('activity'); ?></div>
                                <div>
                                    <h3>Sincronização</h3>
                                    <p>Cópia segura da matriz actual.</p>
                                </div>
                            </div>
                            <span class="sg-cur-card-badge">Modo seguro</span>
                        </div>
                        <div class="sg-cur-card-body">
                            <div class="sg-cur-sync-box">
                                <div class="sg-cur-alert info">
                                    <?php echo $sg_curriculum_icon('shield'); ?>
                                    <div>Esta acção copia a matriz curricular existente para as novas tabelas de currículos. Não apaga, não substitui e não muda a matriz original usada pelo sistema.</div>
                                </div>
                                <div class="sg-cur-sync-meta">
                                    <div class="sg-cur-sync-mini"><small>Última sincronização</small><strong><?php echo $last_sync_at !== '' ? esc_html($last_sync_at) : 'Ainda sem registo'; ?></strong></div>
                                    <div class="sg-cur-sync-mini"><small>Linhas encontradas</small><strong><?php echo (int)$last_sync_rows; ?></strong></div>
                                    <div class="sg-cur-sync-mini"><small>Escala activa</small><strong><?php echo esc_html($active_scale); ?></strong></div>
                                    <div class="sg-cur-sync-mini"><small>Modelo de período</small><strong><?php echo esc_html($active_mode); ?></strong></div>
                                </div>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <?php wp_nonce_field('sige_curriculum_sync_legacy'); ?>
                                    <input type="hidden" name="action" value="sige_curriculum_sync_legacy">
                                    <button type="submit" class="sg-cur-btn secondary"><?php echo $sg_curriculum_icon('activity'); ?> Sincronizar matriz actual</button>
                                </form>
                            </div>
                        </div>
                    </article>

                    <article class="sg-cur-card">
                        <div class="sg-cur-card-header">
                            <div class="sg-cur-card-title">
                                <div class="sg-cur-card-icon" style="--icon-bg:var(--color-success-50);--icon-color:var(--color-success-500);"><?php echo $sg_curriculum_icon('bolt'); ?></div>
                                <div>
                                    <h3>Acessos rápidos</h3>
                                    <p>Áreas relacionadas com a operação académica.</p>
                                </div>
                            </div>
                        </div>
                        <div class="sg-cur-card-body">
                            <div class="sg-cur-quick-grid">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=disciplinas')); ?>" class="sg-cur-quick"><span class="sg-cur-quick-ico" style="--qbg:var(--color-brand-50);--qcolor:var(--color-brand-500);"><?php echo $sg_curriculum_icon('book'); ?></span>Disciplinas</a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=matriz')); ?>" class="sg-cur-quick"><span class="sg-cur-quick-ico" style="--qbg:var(--color-info-50);--qcolor:var(--color-info-500);"><?php echo $sg_curriculum_icon('grid'); ?></span>Matriz curricular</a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=turmas')); ?>" class="sg-cur-quick"><span class="sg-cur-quick-ico" style="--qbg:var(--color-success-50);--qcolor:var(--color-success-500);"><?php echo $sg_curriculum_icon('school'); ?></span>Turmas</a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=pautas')); ?>" class="sg-cur-quick"><span class="sg-cur-quick-ico" style="--qbg:var(--color-warning-50);--qcolor:var(--color-warning-500);"><?php echo $sg_curriculum_icon('clipboard'); ?></span>Pautas</a>
                            </div>
                        </div>
                    </article>
                </aside>
            </section>

            <article class="sg-cur-card">
                <div class="sg-cur-card-header">
                    <div class="sg-cur-card-title">
                        <div class="sg-cur-card-icon" style="--icon-bg:var(--color-brand-50);--icon-color:var(--color-brand-500);"><?php echo $sg_curriculum_icon('rocket'); ?></div>
                        <div>
                            <h3>Próximas fases controladas</h3>
                            <p>Evolução prevista sem regressão no SNE validado.</p>
                        </div>
                    </div>
                    <span class="sg-cur-card-badge">Roteiro seguro</span>
                </div>
                <div class="sg-cur-card-body">
                    <div class="sg-cur-steps">
                        <div class="sg-cur-step" style="--step-bg:var(--color-brand-50);--step-color:var(--color-brand-500);"><div class="sg-cur-step-number">1</div><div><b>Inventário académico completo</b><span>Mapear classes, disciplinas, períodos, documentos oficiais e regras actualmente fixas no sistema.</span></div></div>
                        <div class="sg-cur-step" style="--step-bg:var(--color-info-50);--step-color:var(--color-info-500);"><div class="sg-cur-step-number">2</div><div><b>Leitura por perfil curricular</b><span>Ler disciplinas e ordem a partir do perfil curricular, mantendo Moçambique/SNE como padrão operacional.</span></div></div>
                        <div class="sg-cur-step" style="--step-bg:var(--color-success-50);--step-color:var(--color-success-500);"><div class="sg-cur-step-number">3</div><div><b>Regras de avaliação por perfil</b><span>Activar avaliação e progressão por currículo, sem fórmulas livres inseguras nem impacto oculto.</span></div></div>
                        <div class="sg-cur-step" style="--step-bg:var(--color-warning-50);--step-color:var(--color-warning-500);"><div class="sg-cur-step-number">4</div><div><b>Perfis internacionais e documentos próprios</b><span>Preparar Cambridge, Angola, Brasil e currículos personalizados com documentos específicos por modelo.</span></div></div>
                    </div>
                </div>
            </article>
        <?php endif; ?>
    </div>
</div>
