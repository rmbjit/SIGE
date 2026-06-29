<?php
/**
 * SIGE SoftGenial - Matriz Curricular
 * 
 * v12.10.43 - Maio 2026
 * - Harmonia visual alinhada ao Painel Principal aprovado
 * - ABSPATH guard movido para o topo
 * - Removido date_default_timezone_set() - usa wp_date()
 * - XSS fix nos templates JS
 * - Contagem de disciplinas por classe na sidebar
 */

if (!defined('ABSPATH')) exit;

// Guard de acesso - Secretaria Académica
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard_allows(
    ['academico.matriz_ver','academico.matriz_gerir'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente']
)) {
    ?>
    <div class="sige-access-denied">
        <div class="sige-access-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <h2>Acesso Restrito</h2>
        <p>O seu perfil SIGE não tem permissão para aceder a este módulo.</p>
    </div>
    <style>
/* ========================================
   SIGE MATRIZ CURRICULAR - Harmonia Visual v12.10.43
   Referência: Painel Principal aprovado
   ======================================== */
.sige-matriz-page{
    --sgv2-primary:var(--sg-theme-primary,var(--color-brand-500));
    --sgv2-primary-dark:var(--sg-theme-primary-900,var(--color-brand-800));
    --sgv2-primary-rgb:var(--sg-theme-primary-rgb,90,63,214);
    --sgv2-soft:var(--sg-theme-soft,var(--color-brand-50));
    --sgv2-text:var(--color-black);
    --sgv2-muted:var(--color-slate-600);
    --sgv2-line:var(--color-slate-100);
    --sgv2-card:var(--color-white);
    --sgv2-bg:var(--color-brand-50);
    --sgv2-radius:28px;
    --sgv2-shadow:0 22px 60px rgba(45,37,93,.075);
    --sige-primary:var(--sgv2-primary);
    --sige-success:var(--color-success-700);
    --sige-error:var(--color-danger-500);
    font-family:'Poppins','Inter','Segoe UI',system-ui,sans-serif!important;
    color:var(--sgv2-text)!important;
    padding:0!important;
    margin:0!important;
    background:transparent!important;
}
.sige-matriz-page *{box-sizing:border-box!important;}
@keyframes sgFadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes sgSpin{to{transform:rotate(360deg)}}
@keyframes spin{to{transform:rotate(360deg)}}

/* Hero igual ao padrão do Painel Principal */
.sige-matriz-page .sige-hero{
    position:relative!important;overflow:hidden!important;border-radius:var(--radius-xl)!important;padding:36px!important;margin:0 0 var(--space-6)!important;
    background:linear-gradient(135deg,var(--color-white) 0%,var(--color-white) 46%,var(--sgv2-soft) 100%)!important;
    border:1px solid rgba(255,255,255,.82)!important;box-shadow:var(--shadow-xs);color:var(--sgv2-text)!important;animation:sgFadeUp .35s ease-out both!important;
}
.sige-matriz-page .sige-hero:before{content:""!important;position:absolute!important;right:-85px!important;top:-150px!important;width:360px!important;height:360px!important;border-radius:var(--radius-pill)!important;background:rgba(var(--sgv2-primary-rgb),.10)!important;pointer-events:none!important;}
.sige-matriz-page .sige-hero:after{content:""!important;position:absolute!important;right:90px!important;bottom:-130px!important;width:260px!important;height:260px!important;border-radius:var(--radius-pill)!important;background:rgba(17,153,142,.07)!important;pointer-events:none!important;}
.sige-matriz-page .sige-hero-grid{position:relative!important;z-index:1!important;display:grid!important;grid-template-columns:minmax(0,1fr) 360px!important;gap:28px!important;align-items:center!important;}
.sige-matriz-page .sige-hero-kicker{display:inline-flex!important;align-items:center!important;gap:var(--space-2)!important;margin:0 0 14px!important;padding:0!important;border:0!important;background:transparent!important;color:var(--sgv2-primary-dark)!important;text-transform:uppercase!important;letter-spacing:.18em!important;font-size:12px!important;font-weight:700!important;}
.sige-matriz-page .sige-hero-kicker svg{width:16px!important;height:16px!important;stroke:currentColor!important;}
.sige-matriz-page .sige-hero h1{margin:0 0 10px!important;color:var(--sgv2-text)!important;font-size:42px!important;line-height:1.06!important;font-weight:700!important;letter-spacing:-.055em!important;}
.sige-matriz-page .sige-hero-subtitle{max-width:760px!important;margin:0 0 22px!important;color:var(--color-slate-700)!important;font-size:var(--fs-md)!important;line-height:1.65!important;font-weight:600!important;}
.sige-matriz-page .sige-hero-actions{display:flex!important;flex-wrap:wrap!important;gap:14px!important;align-items:center!important;}
.sige-matriz-page .sige-btn{min-height:52px!important;border-radius:var(--radius-lg)!important;padding:0 22px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;text-decoration:none!important;border:0!important;cursor:pointer!important;font-weight:700!important;font-size:var(--fs-base)!important;line-height:1!important;transition:.18s ease!important;}
.sige-matriz-page .sige-btn svg{width:19px!important;height:19px!important;stroke:currentColor!important;}
.sige-matriz-page .sige-btn-primary{background:linear-gradient(135deg,var(--sgv2-primary),var(--sgv2-primary-dark))!important;color:var(--color-white)!important;box-shadow:var(--shadow-md);}
.sige-matriz-page .sige-btn-secondary{background:var(--color-white)!important;color:var(--sgv2-text)!important;border:1px solid rgba(31,35,70,.08)!important;box-shadow:var(--shadow-sm);}
.sige-matriz-page .sige-btn:hover{transform:translateY(-1px)!important;}
.sige-matriz-page .sige-hero-art{height:170px!important;border-radius:var(--radius-xl)!important;background:linear-gradient(135deg,rgba(var(--sgv2-primary-rgb),.08),rgba(var(--sgv2-primary-rgb),.14))!important;border:1px solid rgba(var(--sgv2-primary-rgb),.10)!important;position:relative!important;overflow:hidden!important;}
.sige-matriz-page .sige-hero-art:before{content:"";position:absolute;right:-45px;top:-55px;width:180px;height:180px;border-radius:var(--radius-pill);background:rgba(var(--sgv2-primary-rgb),.12)}
.sige-matriz-page .sige-hero-art:after{content:"";position:absolute;left:50%;top:50%;width:150px;height:90px;transform:translate(-50%,-35%);border-radius:var(--radius-lg);background:rgba(var(--sgv2-primary-rgb),.22);box-shadow:0 2px 8px rgba(15,23,42,.06)}
.sige-matriz-page .sige-hero-book{position:absolute;left:50%;top:48%;width:86px;height:58px;border:7px solid rgba(var(--sgv2-primary-rgb),.45);border-top:0;border-radius:0 0 20px 20px;transform:translate(-50%,-10%);z-index:1;}
.sige-matriz-page .sige-hero-book:before{content:"";position:absolute;left:50%;top:-32px;width:8px;height:54px;background:rgba(var(--sgv2-primary-rgb),.48);border-radius:var(--radius-pill);transform:translateX(-50%)}

/* Indicadores */
.sige-matriz-page .sige-stats-grid{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:18px!important;margin:0 0 var(--space-6)!important;animation:sgFadeUp .35s ease-out .05s both!important;}
.sige-matriz-page .sige-stat-card{position:relative!important;overflow:hidden!important;min-height:128px!important;border-radius:var(--radius-xl)!important;background:var(--color-white)!important;border:1px solid rgba(255,255,255,.86)!important;box-shadow:var(--shadow-xs);padding:24px 24px 20px 84px!important;}
.sige-matriz-page .sige-stat-card:after{content:""!important;position:absolute!important;right:-32px!important;top:-44px!important;width:120px!important;height:120px!important;border-radius:var(--radius-pill)!important;background:var(--color-ink-50)!important;opacity:.9!important;z-index:0!important;}
.sige-matriz-page .sige-stat-card>*{position:relative!important;z-index:1!important;}
.sige-matriz-page .sige-stat-icon{position:absolute!important;left:24px!important;top:28px!important;width:46px!important;height:46px!important;border-radius:var(--radius-lg)!important;display:flex!important;align-items:center!important;justify-content:center!important;background:var(--sgv2-soft)!important;color:var(--sgv2-primary)!important;z-index:2!important;}
.sige-matriz-page .sige-stat-icon svg{width:22px!important;height:22px!important;stroke:currentColor!important;}
.sige-matriz-page .sige-stat-label{color:var(--color-slate-600)!important;font-size:var(--fs-sm)!important;font-weight:700!important;margin:0 0 6px!important;}
.sige-matriz-page .sige-stat-value{color:var(--sgv2-text)!important;font-size:30px!important;line-height:1!important;font-weight:700!important;letter-spacing:-.035em!important;margin-bottom:8px!important;}
.sige-matriz-page .sige-stat-note{color:var(--color-slate-500)!important;font-size:12px!important;font-weight:600!important;line-height:1.35!important;}
.sige-matriz-page .stat-success .sige-stat-icon{background:var(--color-success-100)!important;color:var(--color-success-700)!important;}.sige-matriz-page .stat-info .sige-stat-icon{background:var(--color-info-50)!important;color:var(--color-info-400)!important;}.sige-matriz-page .stat-amber .sige-stat-icon{background:var(--color-warning-100)!important;color:var(--color-warning-500)!important;}

/* Layout principal */
.sige-matriz-page .sige-matriz-wrapper{display:grid!important;grid-template-columns:320px minmax(0,1fr)!important;gap:22px!important;align-items:start!important;animation:sgFadeUp .35s ease-out .1s both!important;}
.sige-matriz-page .sige-sidebar,.sige-matriz-page .sige-main-panel{background:var(--color-white)!important;border:1px solid rgba(255,255,255,.88)!important;border-radius:var(--radius-xl)!important;box-shadow:var(--shadow-xs);}
.sige-matriz-page .sige-sidebar{padding:18px!important;position:sticky!important;top:18px!important;max-height:calc(100vh - 140px)!important;overflow:auto!important;}
.sige-matriz-page .sige-sidebar::-webkit-scrollbar,.sige-matriz-page .sige-table-wrapper::-webkit-scrollbar{height:8px;width:8px}.sige-matriz-page .sige-sidebar::-webkit-scrollbar-thumb,.sige-matriz-page .sige-table-wrapper::-webkit-scrollbar-thumb{background:rgba(var(--sgv2-primary-rgb),.20);border-radius:22px}.sige-matriz-page .sige-sidebar::-webkit-scrollbar-track,.sige-matriz-page .sige-table-wrapper::-webkit-scrollbar-track{background:transparent}
.sige-matriz-page .sige-nav-group{margin:0 0 var(--space-5)!important;}.sige-matriz-page .sige-nav-group:last-child{margin-bottom:0!important;}
.sige-matriz-page .sige-nav-label{display:flex!important;align-items:center!important;gap:9px!important;padding:0 8px 10px!important;margin:0 0 var(--space-2)!important;border-bottom:1px solid var(--color-slate-100)!important;color:var(--color-slate-500)!important;font-size:10px!important;font-weight:700!important;text-transform:uppercase!important;letter-spacing:.12em!important;}
.sige-matriz-page .sige-nav-label svg{width:16px!important;height:16px!important;stroke:currentColor!important;}
.sige-matriz-page .sige-nav-link{display:flex!important;align-items:center!important;gap:11px!important;min-height:44px!important;margin:0 0 6px!important;padding:0 var(--space-3)!important;border-radius:var(--radius-lg)!important;color:var(--color-slate-700)!important;text-decoration:none!important;font-size:var(--fs-sm)!important;font-weight:700!important;transition:.18s ease!important;border:1px solid transparent!important;background:var(--color-white)!important;}
.sige-matriz-page .sige-nav-link svg{width:17px!important;height:17px!important;stroke:currentColor!important;opacity:.75!important;}
.sige-matriz-page .sige-nav-link:hover{background:var(--color-slate-50)!important;color:var(--sgv2-primary)!important;transform:translateX(3px)!important;}
.sige-matriz-page .sige-nav-link.active{background:linear-gradient(135deg,var(--sgv2-primary),var(--sgv2-primary-dark))!important;color:var(--color-white)!important;box-shadow:var(--shadow-sm);transform:none!important;}
.sige-matriz-page .sige-nav-count{margin-left:auto!important;min-width:24px!important;height:24px!important;border-radius:var(--radius-pill)!important;background:var(--color-slate-100)!important;color:var(--color-slate-600)!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;font-size:var(--fs-xs)!important;font-weight:700!important;padding:0 7px!important;}
.sige-matriz-page .sige-nav-link.active .sige-nav-count{background:rgba(255,255,255,.22)!important;color:var(--color-white)!important;}
.sige-matriz-page .sige-main-panel{padding:26px!important;min-height:560px!important;}
.sige-matriz-page .sige-empty-state{text-align:center!important;padding:84px 28px!important;}.sige-matriz-page .sige-empty-icon{width:86px!important;height:86px!important;border-radius:var(--radius-xl)!important;background:var(--sgv2-soft)!important;color:var(--sgv2-primary)!important;display:flex!important;align-items:center!important;justify-content:center!important;margin:0 auto var(--space-5)!important;}.sige-matriz-page .sige-empty-icon svg{width:40px!important;height:40px!important;stroke:currentColor!important;}.sige-matriz-page .sige-empty-state h3{margin:0 0 var(--space-2)!important;color:var(--sgv2-text)!important;font-size:22px!important;font-weight:700!important;}.sige-matriz-page .sige-empty-state p{margin:0 auto!important;max-width:480px!important;color:var(--color-slate-600)!important;font-size:var(--fs-base)!important;line-height:1.65!important;font-weight:600!important;}

/* Painel e formulário */
.sige-matriz-page .sige-panel-header{display:flex!important;justify-content:space-between!important;align-items:flex-start!important;gap:18px!important;margin:0 0 var(--space-5)!important;padding:0 0 var(--space-5)!important;border-bottom:1px solid var(--color-slate-100)!important;}
.sige-matriz-page .sige-panel-title{font-size:26px!important;line-height:1.1!important;letter-spacing:-.04em!important;font-weight:700!important;color:var(--sgv2-text)!important;margin:0 0 10px!important;}
.sige-matriz-page .sige-status-badge{display:inline-flex!important;align-items:center!important;gap:var(--space-2)!important;min-height:36px!important;padding:0 13px!important;border-radius:var(--radius-pill)!important;font-size:12px!important;font-weight:700!important;}
.sige-matriz-page .sige-status-badge svg{width:16px!important;height:16px!important;stroke:currentColor!important;}.sige-matriz-page .sige-status-loading svg{animation:sgSpin 1s linear infinite!important;}
.sige-matriz-page .sige-status-ok{background:var(--color-success-100)!important;color:var(--color-success-800)!important;border:1px solid rgba(47,169,92,.15)!important;}.sige-matriz-page .sige-status-warning{background:var(--color-warning-100)!important;color:var(--color-warning-800)!important;border:1px solid rgba(243,154,24,.18)!important;}.sige-matriz-page .sige-status-loading{background:var(--color-ink-50)!important;color:var(--color-slate-600)!important;border:1px solid var(--color-ink-100)!important;}
.sige-matriz-page .sige-btn-clone{min-height:46px!important;padding:0 18px!important;border-radius:var(--radius-lg)!important;background:var(--color-white)!important;color:var(--sgv2-primary)!important;border:1px solid rgba(var(--sgv2-primary-rgb),.18)!important;display:inline-flex!important;align-items:center!important;gap:9px!important;font-size:var(--fs-sm)!important;font-weight:700!important;box-shadow:var(--shadow-sm);cursor:pointer!important;}
.sige-matriz-page .sige-btn-clone svg{width:18px!important;height:18px!important;stroke:currentColor!important;}
.sige-matriz-page .sige-action-bar{display:grid!important;grid-template-columns:minmax(260px,1fr) minmax(230px,280px) auto!important;gap:14px!important;align-items:end!important;margin:0 0 22px!important;padding:18px!important;border-radius:var(--radius-xl)!important;background:var(--color-slate-50)!important;border:1px solid var(--color-slate-100)!important;}
.sige-matriz-page .sige-action-field{display:flex!important;flex-direction:column!important;gap:var(--space-2)!important;}.sige-matriz-page .sige-action-field label{color:var(--color-slate-700)!important;font-size:var(--fs-xs)!important;text-transform:uppercase!important;letter-spacing:.1em!important;font-weight:700!important;}
.sige-matriz-page .sige-action-field select,.sige-matriz-page .sige-action-field input{height:52px!important;border-radius:var(--radius-lg)!important;border:1px solid var(--color-ink-100)!important;background:var(--color-white)!important;color:var(--color-ink-500)!important;font-size:var(--fs-base)!important;font-weight:600!important;padding:0 15px!important;outline:none!important;box-shadow:var(--shadow-sm);}
.sige-matriz-page .sige-action-field select:focus,.sige-matriz-page .sige-action-field input:focus{border-color:rgba(var(--sgv2-primary-rgb),.42)!important;box-shadow:var(--shadow-xs);}
.sige-matriz-page .sige-carga-inputs{display:flex!important;align-items:center!important;gap:var(--space-2)!important;}.sige-matriz-page .sige-carga-inputs input{width:74px!important;text-align:center!important;}.sige-matriz-page .sige-carga-inputs span{font-weight:700!important;color:var(--color-slate-500)!important;font-size:12px!important;}
.sige-matriz-page .sige-btn-vincular{height:52px!important;border-radius:var(--radius-lg)!important;padding:0 22px!important;border:0!important;background:linear-gradient(135deg,var(--sgv2-primary),var(--sgv2-primary-dark))!important;color:var(--color-white)!important;font-size:var(--fs-sm)!important;font-weight:700!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:9px!important;box-shadow:var(--shadow-md);cursor:pointer!important;}
.sige-matriz-page .sige-btn-vincular svg{width:18px!important;height:18px!important;stroke:currentColor!important;}.sige-matriz-page .sige-btn-vincular:disabled{opacity:.68!important;cursor:not-allowed!important;}

/* Tabela */
.sige-matriz-page .sige-table-wrapper{overflow:auto!important;border:1px solid var(--color-slate-100)!important;border-radius:var(--radius-xl)!important;background:var(--color-white)!important;}.sige-matriz-page .sige-table{width:100%!important;border-collapse:separate!important;border-spacing:0!important;font-size:var(--fs-base)!important;min-width:720px!important;}.sige-matriz-page .sige-table thead th{padding:15px 18px!important;background:var(--color-slate-50)!important;color:var(--color-slate-600)!important;text-align:left!important;font-size:var(--fs-xs)!important;font-weight:700!important;text-transform:uppercase!important;letter-spacing:.09em!important;border-bottom:1px solid var(--color-slate-100)!important;}.sige-matriz-page .sige-table tbody td{padding:16px 18px!important;border-bottom:1px solid var(--color-slate-100)!important;vertical-align:middle!important;}.sige-matriz-page .sige-table tbody tr:hover{background:var(--color-white)!important;}.sige-matriz-page .sige-drag-handle{cursor:grab!important;color:var(--color-ink-300)!important;display:inline-flex!important;}.sige-matriz-page .sige-drag-handle svg{width:18px!important;height:18px!important;stroke:currentColor!important;}.sige-matriz-page .sige-disc-cell{display:flex!important;align-items:center!important;gap:var(--space-3)!important;}.sige-matriz-page .sige-disc-icon{width:42px!important;height:42px!important;border-radius:var(--radius-lg)!important;background:var(--sgv2-soft)!important;color:var(--sgv2-primary)!important;display:flex!important;align-items:center!important;justify-content:center!important;}.sige-matriz-page .sige-disc-icon svg{width:20px!important;height:20px!important;stroke:currentColor!important;}.sige-matriz-page .sige-disc-nome{font-weight:700!important;color:var(--color-ink-500)!important;}.sige-matriz-page .sige-sigla-badge{display:inline-flex!important;align-items:center!important;min-height:32px!important;padding:0 var(--space-3)!important;border-radius:var(--radius-pill)!important;background:var(--color-info-50)!important;border:1px solid var(--color-ink-100)!important;color:var(--sgv2-primary-dark)!important;font-weight:700!important;font-size:12px!important;}.sige-matriz-page .sige-carga-info{display:flex!important;align-items:baseline!important;gap:6px!important;}.sige-matriz-page .sige-carga-value{font-size:15px!important;font-weight:700!important;color:var(--color-ink-500)!important;}.sige-matriz-page .sige-carga-label{font-size:12px!important;color:var(--color-slate-500)!important;font-weight:600!important;}.sige-matriz-page .sige-btn-remove{width:38px!important;height:38px!important;border-radius:var(--radius-md)!important;border:1px solid rgba(239,68,68,.12)!important;background:var(--color-danger-50)!important;color:var(--color-danger-500)!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;cursor:pointer!important;}.sige-matriz-page .sige-btn-remove svg{width:17px!important;height:17px!important;stroke:currentColor!important;}.sortable-ghost{opacity:.45!important;background:var(--color-info-50)!important}.sortable-chosen{background:var(--color-brand-50)!important;box-shadow:var(--shadow-md);}

/* Pop-ups reais */
.sige-matriz-page .sige-modal{position:fixed!important;inset:0!important;background:rgba(18,22,40,.54)!important;backdrop-filter:blur(14px)!important;-webkit-backdrop-filter:blur(14px)!important;z-index:1000000!important;display:none;align-items:center!important;justify-content:center!important;padding:22px!important;}.sige-matriz-page .sige-modal-content{width:min(560px,calc(100vw - 44px))!important;border-radius:var(--radius-xl)!important;background:var(--color-white)!important;box-shadow:var(--shadow-lg);overflow:hidden!important;border:1px solid rgba(255,255,255,.86)!important;animation:sgFadeUp .22s ease both!important;}.sige-matriz-page .sige-modal-header{padding:24px 26px!important;background:linear-gradient(135deg,var(--color-white) 0%,var(--color-white) 56%,var(--sgv2-soft) 100%)!important;color:var(--sgv2-text)!important;border-bottom:1px solid var(--color-slate-100)!important;}.sige-matriz-page .sige-modal-header h3{margin:0!important;display:flex!important;align-items:center!important;gap:var(--space-3)!important;font-size:22px!important;line-height:1.12!important;font-weight:700!important;letter-spacing:-.035em!important;color:var(--sgv2-text)!important;}.sige-matriz-page .sige-modal-header h3 svg{width:42px!important;height:42px!important;padding:11px!important;border-radius:var(--radius-lg)!important;background:var(--sgv2-soft)!important;color:var(--sgv2-primary)!important;stroke:currentColor!important;}.sige-matriz-page .sige-modal-body{padding:22px 26px!important;}.sige-matriz-page .sige-modal-description{margin:0 0 18px!important;color:var(--color-slate-700)!important;font-size:var(--fs-base)!important;line-height:1.65!important;font-weight:600!important;}.sige-matriz-page .sige-modal-description strong{color:var(--sgv2-primary-dark)!important;}.sige-matriz-page .sige-modal-field{display:flex!important;flex-direction:column!important;gap:var(--space-2)!important;}.sige-matriz-page .sige-modal-field label{color:var(--color-slate-700)!important;font-size:var(--fs-xs)!important;font-weight:700!important;text-transform:uppercase!important;letter-spacing:.09em!important;}.sige-matriz-page .sige-modal-field select{height:52px!important;border-radius:var(--radius-lg)!important;border:1px solid var(--color-ink-100)!important;background:var(--color-white)!important;padding:0 15px!important;font-weight:600!important;}.sige-matriz-page .sige-modal-footer{display:flex!important;justify-content:flex-end!important;gap:var(--space-3)!important;padding:18px 26px!important;background:var(--color-white)!important;border-top:1px solid var(--color-slate-100)!important;}.sige-matriz-page .sige-btn-modal{min-height:50px!important;border-radius:var(--radius-lg)!important;padding:0 var(--space-5)!important;border:0!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:9px!important;font-size:var(--fs-base)!important;font-weight:700!important;cursor:pointer!important;}.sige-matriz-page .sige-btn-modal svg{width:18px!important;height:18px!important;stroke:currentColor!important;}.sige-matriz-page .sige-btn-cancel{background:var(--color-white)!important;border:1px solid var(--color-ink-100)!important;color:var(--color-slate-800)!important;}.sige-matriz-page .sige-btn-confirm{background:linear-gradient(135deg,var(--sgv2-primary),var(--sgv2-primary-dark))!important;color:var(--color-white)!important;box-shadow:var(--shadow-md);}
body.sige-modal-open{overflow:hidden!important;}

/* v12.10.139 - Matriz: modais fora do wrapper também recebem o padrão PRO.
   Evita que HTML de modais ocultos seja renderizado como conteúdo normal quando o escopo .sige-matriz-page não alcança o elemento. */
body.sige-admin-app.sige-view-matriz #modal-clone.sige-modal,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg.sige-modal{position:fixed!important;inset:0!important;background:rgba(18,22,40,.54)!important;backdrop-filter:blur(14px)!important;-webkit-backdrop-filter:blur(14px)!important;z-index:1000000!important;display:none;align-items:center!important;justify-content:center!important;padding:22px!important;box-sizing:border-box!important;}
body.sige-admin-app.sige-view-matriz #modal-clone.sige-modal[style*="display: flex"],
body.sige-admin-app.sige-view-matriz #modal-clone.sige-modal[style*="display:flex"],
body.sige-admin-app.sige-view-matriz #modal-matriz-msg.sige-modal[style*="display: flex"],
body.sige-admin-app.sige-view-matriz #modal-matriz-msg.sige-modal[style*="display:flex"]{display:flex!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-modal-content,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-modal-content{width:min(560px,calc(100vw - 44px))!important;border-radius:var(--radius-xl)!important;background:var(--color-white)!important;box-shadow:var(--shadow-lg);overflow:hidden!important;border:1px solid rgba(255,255,255,.86)!important;animation:sgFadeUp .22s ease both!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-modal-header,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-modal-header{padding:24px 26px!important;background:linear-gradient(135deg,var(--color-white) 0%,var(--color-white) 56%,var(--sg-theme-soft,var(--color-brand-50)) 100%)!important;color:var(--color-black)!important;border-bottom:1px solid var(--color-slate-100)!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-modal-header h3,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-modal-header h3{margin:0!important;display:flex!important;align-items:center!important;gap:var(--space-3)!important;font-size:22px!important;line-height:1.12!important;font-weight:700!important;letter-spacing:-.035em!important;color:var(--color-black)!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-modal-header h3 svg,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-modal-header h3 svg{width:42px!important;height:42px!important;padding:11px!important;border-radius:var(--radius-lg)!important;background:var(--sg-theme-soft,var(--color-brand-50))!important;color:var(--sg-theme-primary,var(--color-brand-500))!important;stroke:currentColor!important;flex:0 0 auto!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-modal-body,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-modal-body{padding:22px 26px!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-modal-footer,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-modal-footer{display:flex!important;justify-content:flex-end!important;gap:var(--space-3)!important;padding:18px 26px!important;background:var(--color-white)!important;border-top:1px solid var(--color-slate-100)!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-btn-modal,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-btn-modal{min-height:50px!important;border-radius:var(--radius-lg)!important;padding:0 var(--space-5)!important;border:0!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:9px!important;font-size:var(--fs-base)!important;font-weight:700!important;cursor:pointer!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-btn-modal svg,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-btn-modal svg{width:18px!important;height:18px!important;stroke:currentColor!important;flex:0 0 auto!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-btn-cancel,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-btn-cancel{background:var(--color-white)!important;border:1px solid var(--color-ink-100)!important;color:var(--color-slate-800)!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-btn-confirm,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-btn-confirm{background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-900,var(--color-brand-800)))!important;color:var(--color-white)!important;box-shadow:var(--shadow-md);}
@media (max-width:720px){body.sige-admin-app.sige-view-matriz #modal-clone.sige-modal,body.sige-admin-app.sige-view-matriz #modal-matriz-msg.sige-modal{padding:0!important;}body.sige-admin-app.sige-view-matriz #modal-clone .sige-modal-content,body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-modal-content{width:100%!important;max-height:100vh!important;border-radius:0!important;}body.sige-admin-app.sige-view-matriz #modal-clone .sige-modal-footer,body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-modal-footer{flex-direction:column!important;}body.sige-admin-app.sige-view-matriz #modal-clone .sige-btn-modal,body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-btn-modal{width:100%!important;}}


@media (max-width:1500px){.sige-matriz-page .sige-hero-grid{grid-template-columns:1fr!important}.sige-matriz-page .sige-hero-art{display:none!important}.sige-matriz-page .sige-stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;}}
@media (max-width:1100px){.sige-matriz-page .sige-matriz-wrapper{grid-template-columns:1fr!important}.sige-matriz-page .sige-sidebar{position:relative!important;top:0!important;max-height:none!important;display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:16px!important}.sige-matriz-page .sige-action-bar{grid-template-columns:1fr!important}.sige-matriz-page .sige-btn-vincular{width:100%!important}.sige-matriz-page .sige-panel-header{flex-direction:column!important;}}
@media (max-width:720px){.sige-matriz-page .sige-hero{padding:var(--space-6) var(--space-5)!important;border-radius:22px!important}.sige-matriz-page .sige-hero h1{font-size:28px!important}.sige-matriz-page .sige-stats-grid{grid-template-columns:1fr!important}.sige-matriz-page .sige-sidebar{grid-template-columns:1fr!important}.sige-matriz-page .sige-main-panel{padding:18px!important;border-radius:22px!important}.sige-matriz-page .sige-carga-inputs{display:grid!important;grid-template-columns:1fr auto 1fr auto!important}.sige-matriz-page .sige-carga-inputs input{width:100%!important}.sige-matriz-page .sige-modal{padding:0!important}.sige-matriz-page .sige-modal-content{width:100%!important;max-height:100vh!important;border-radius:0!important}.sige-matriz-page .sige-modal-footer{flex-direction:column!important}.sige-matriz-page .sige-btn-modal{width:100%!important}}

/* v12.10.96 - Ajuste laptop 17": KPIs da matriz continuam em linha e sem texto sobreposto */
@media (min-width:1181px) and (max-width:1500px){
    .sige-matriz-page .sige-stats-grid{
        grid-template-columns:repeat(4,minmax(0,1fr))!important;
        gap:14px!important;
    }
    .sige-matriz-page .sige-stat-card{
        min-height:112px!important;
        padding:18px 16px 16px 72px!important;
        min-width:0!important;
    }
    .sige-matriz-page .sige-stat-icon{
        left:18px!important;
        top:22px!important;
        width:42px!important;
        height:42px!important;
    }
    .sige-matriz-page .sige-stat-label,
    .sige-matriz-page .sige-stat-note{
        overflow-wrap:anywhere!important;
        line-height:1.34!important;
    }
    .sige-matriz-page .sige-stat-value{
        font-size:clamp(22px,2vw,28px)!important;
    }
    .sige-matriz-page .sige-matriz-wrapper{
        grid-template-columns:minmax(300px,370px) minmax(0,1fr)!important;
        gap:18px!important;
    }
}
@media (max-width:1180px){
    .sige-matriz-page .sige-stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;}
}
@media (max-width:720px){
    .sige-matriz-page .sige-stats-grid{grid-template-columns:1fr!important;}
}


/* v12.10.97 - Matriz Curricular: KPIs sem sobreposição em telas intermédias */
.sige-matriz-page .sige-stat-card{
    display:grid!important;
    grid-template-columns:58px minmax(0,1fr)!important;
    grid-template-areas:
        "icon label"
        "icon value"
        "icon note"!important;
    column-gap:18px!important;
    align-items:center!important;
    min-width:0!important;
    padding:22px 24px!important;
}
.sige-matriz-page .sige-stat-icon{
    position:relative!important;
    left:auto!important;
    top:auto!important;
    grid-area:icon!important;
    align-self:center!important;
    justify-self:center!important;
    width:52px!important;
    height:52px!important;
    border-radius:var(--radius-lg)!important;
    flex:0 0 auto!important;
}
.sige-matriz-page .sige-stat-label{
    grid-area:label!important;
    min-width:0!important;
    margin:0!important;
    padding:0!important;
    line-height:1.25!important;
    overflow-wrap:normal!important;
    word-break:normal!important;
}
.sige-matriz-page .sige-stat-value{
    grid-area:value!important;
    min-width:0!important;
    margin:var(--space-1) 0 var(--space-1)!important;
    line-height:1.05!important;
    overflow-wrap:anywhere!important;
}
.sige-matriz-page .sige-stat-note{
    grid-area:note!important;
    min-width:0!important;
    margin:0!important;
    overflow-wrap:normal!important;
    word-break:normal!important;
}
@media (min-width:1181px) and (max-width:1500px){
    .sige-matriz-page .sige-stats-grid{
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        gap:18px!important;
    }
    .sige-matriz-page .sige-stat-card{
        min-height:138px!important;
        padding:24px 28px!important;
        grid-template-columns:66px minmax(0,1fr)!important;
        column-gap:var(--space-5)!important;
    }
    .sige-matriz-page .sige-stat-icon{
        width:58px!important;
        height:58px!important;
    }
}
@media (max-width:1180px){
    .sige-matriz-page .sige-stat-card{
        min-height:128px!important;
        grid-template-columns:58px minmax(0,1fr)!important;
    }
}
@media (max-width:620px){
    .sige-matriz-page .sige-stat-card{
        grid-template-columns:50px minmax(0,1fr)!important;
        column-gap:14px!important;
        padding:var(--space-5)!important;
    }
    .sige-matriz-page .sige-stat-icon{
        width:48px!important;
        height:48px!important;
    }
}

</style>
    <?php
    return;
}

global $wpdb;
$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;

$classes_grupos = [
    'Pré-Escolar' => ['2º/3º Ano', '4º Ano', 'Pré-primário'],
    'Ensino Primário' => ['1ª', '2ª', '3ª', '4ª', '5ª', '6ª'],
    'Ensino Secundário (ESG1)' => ['7ª', '8ª', '9ª'],
    'Ensino Secundário (ESG2)' => ['10ª', '11ª A', '11ª B', '11ª C', '12ª A', '12ª B', '12ª C']
];

// Ícones SVG para cada grupo
$grupo_icons = [
    'Pré-Escolar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
    'Ensino Primário' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
    'Ensino Secundário (ESG1)' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
    'Ensino Secundário (ESG2)' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>'
];

$disciplinas_all = $wpdb->get_results($wpdb->prepare("SELECT id, nome, sigla, carga_horaria FROM {$wpdb->prefix}sige_disciplinas WHERE escola_id = %d ORDER BY nome ASC", $escola_id));

// [UX] Preload discipline count per class for sidebar badges
$_mc_counts_raw = $wpdb->get_results($wpdb->prepare(
    "SELECT classe, COUNT(*) as n FROM {$wpdb->prefix}sige_matriz_curricular WHERE escola_id = %d GROUP BY classe",
    $escola_id
));
$_mc_counts = [];
foreach ($_mc_counts_raw as $_r) {
    $_mc_counts[$_r->classe] = (int)$_r->n;
}

$_mc_total_vinculos = array_sum($_mc_counts);
$_mc_total_classes = 0;
foreach ($classes_grupos as $_lista_classes) { $_mc_total_classes += count($_lista_classes); }
$_mc_classes_configuradas = 0;
foreach ($_mc_counts as $_n) { if ((int)$_n > 0) { $_mc_classes_configuradas++; } }
$_mc_default_class = '1ª';
?>

<style>
/* ========================================
   SIGE MATRIZ CURRICULAR - Harmonia Visual v12.10.43
   Referência: Painel Principal aprovado
   ======================================== */
.sige-matriz-page{
    --sgv2-primary:var(--sg-theme-primary,var(--color-brand-500));
    --sgv2-primary-dark:var(--sg-theme-primary-900,var(--color-brand-800));
    --sgv2-primary-rgb:var(--sg-theme-primary-rgb,90,63,214);
    --sgv2-soft:var(--sg-theme-soft,var(--color-brand-50));
    --sgv2-text:var(--color-black);
    --sgv2-muted:var(--color-slate-600);
    --sgv2-line:var(--color-slate-100);
    --sgv2-card:var(--color-white);
    --sgv2-bg:var(--color-brand-50);
    --sgv2-radius:28px;
    --sgv2-shadow:0 22px 60px rgba(45,37,93,.075);
    --sige-primary:var(--sgv2-primary);
    --sige-success:var(--color-success-700);
    --sige-error:var(--color-danger-500);
    font-family:'Poppins','Inter','Segoe UI',system-ui,sans-serif!important;
    color:var(--sgv2-text)!important;
    padding:0!important;
    margin:0!important;
    background:transparent!important;
}
.sige-matriz-page *{box-sizing:border-box!important;}
@keyframes sgFadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes sgSpin{to{transform:rotate(360deg)}}
@keyframes spin{to{transform:rotate(360deg)}}

/* Hero igual ao padrão do Painel Principal */
.sige-matriz-page .sige-hero{
    position:relative!important;overflow:hidden!important;border-radius:var(--radius-xl)!important;padding:36px!important;margin:0 0 var(--space-6)!important;
    background:linear-gradient(135deg,var(--color-white) 0%,var(--color-white) 46%,var(--sgv2-soft) 100%)!important;
    border:1px solid rgba(255,255,255,.82)!important;box-shadow:var(--shadow-xs);color:var(--sgv2-text)!important;animation:sgFadeUp .35s ease-out both!important;
}
.sige-matriz-page .sige-hero:before{content:""!important;position:absolute!important;right:-85px!important;top:-150px!important;width:360px!important;height:360px!important;border-radius:var(--radius-pill)!important;background:rgba(var(--sgv2-primary-rgb),.10)!important;pointer-events:none!important;}
.sige-matriz-page .sige-hero:after{content:""!important;position:absolute!important;right:90px!important;bottom:-130px!important;width:260px!important;height:260px!important;border-radius:var(--radius-pill)!important;background:rgba(17,153,142,.07)!important;pointer-events:none!important;}
.sige-matriz-page .sige-hero-grid{position:relative!important;z-index:1!important;display:grid!important;grid-template-columns:minmax(0,1fr) 360px!important;gap:28px!important;align-items:center!important;}
.sige-matriz-page .sige-hero-kicker{display:inline-flex!important;align-items:center!important;gap:var(--space-2)!important;margin:0 0 14px!important;padding:0!important;border:0!important;background:transparent!important;color:var(--sgv2-primary-dark)!important;text-transform:uppercase!important;letter-spacing:.18em!important;font-size:12px!important;font-weight:700!important;}
.sige-matriz-page .sige-hero-kicker svg{width:16px!important;height:16px!important;stroke:currentColor!important;}
.sige-matriz-page .sige-hero h1{margin:0 0 10px!important;color:var(--sgv2-text)!important;font-size:42px!important;line-height:1.06!important;font-weight:700!important;letter-spacing:-.055em!important;}
.sige-matriz-page .sige-hero-subtitle{max-width:760px!important;margin:0 0 22px!important;color:var(--color-slate-700)!important;font-size:var(--fs-md)!important;line-height:1.65!important;font-weight:600!important;}
.sige-matriz-page .sige-hero-actions{display:flex!important;flex-wrap:wrap!important;gap:14px!important;align-items:center!important;}
.sige-matriz-page .sige-btn{min-height:52px!important;border-radius:var(--radius-lg)!important;padding:0 22px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;text-decoration:none!important;border:0!important;cursor:pointer!important;font-weight:700!important;font-size:var(--fs-base)!important;line-height:1!important;transition:.18s ease!important;}
.sige-matriz-page .sige-btn svg{width:19px!important;height:19px!important;stroke:currentColor!important;}
.sige-matriz-page .sige-btn-primary{background:linear-gradient(135deg,var(--sgv2-primary),var(--sgv2-primary-dark))!important;color:var(--color-white)!important;box-shadow:var(--shadow-md);}
.sige-matriz-page .sige-btn-secondary{background:var(--color-white)!important;color:var(--sgv2-text)!important;border:1px solid rgba(31,35,70,.08)!important;box-shadow:var(--shadow-sm);}
.sige-matriz-page .sige-btn:hover{transform:translateY(-1px)!important;}
.sige-matriz-page .sige-hero-art{height:170px!important;border-radius:var(--radius-xl)!important;background:linear-gradient(135deg,rgba(var(--sgv2-primary-rgb),.08),rgba(var(--sgv2-primary-rgb),.14))!important;border:1px solid rgba(var(--sgv2-primary-rgb),.10)!important;position:relative!important;overflow:hidden!important;}
.sige-matriz-page .sige-hero-art:before{content:"";position:absolute;right:-45px;top:-55px;width:180px;height:180px;border-radius:var(--radius-pill);background:rgba(var(--sgv2-primary-rgb),.12)}
.sige-matriz-page .sige-hero-art:after{content:"";position:absolute;left:50%;top:50%;width:150px;height:90px;transform:translate(-50%,-35%);border-radius:var(--radius-lg);background:rgba(var(--sgv2-primary-rgb),.22);box-shadow:0 2px 8px rgba(15,23,42,.06)}
.sige-matriz-page .sige-hero-book{position:absolute;left:50%;top:48%;width:86px;height:58px;border:7px solid rgba(var(--sgv2-primary-rgb),.45);border-top:0;border-radius:0 0 20px 20px;transform:translate(-50%,-10%);z-index:1;}
.sige-matriz-page .sige-hero-book:before{content:"";position:absolute;left:50%;top:-32px;width:8px;height:54px;background:rgba(var(--sgv2-primary-rgb),.48);border-radius:var(--radius-pill);transform:translateX(-50%)}

/* Indicadores */
.sige-matriz-page .sige-stats-grid{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:18px!important;margin:0 0 var(--space-6)!important;animation:sgFadeUp .35s ease-out .05s both!important;}
.sige-matriz-page .sige-stat-card{position:relative!important;overflow:hidden!important;min-height:128px!important;border-radius:var(--radius-xl)!important;background:var(--color-white)!important;border:1px solid rgba(255,255,255,.86)!important;box-shadow:var(--shadow-xs);padding:24px 24px 20px 84px!important;}
.sige-matriz-page .sige-stat-card:after{content:""!important;position:absolute!important;right:-32px!important;top:-44px!important;width:120px!important;height:120px!important;border-radius:var(--radius-pill)!important;background:var(--color-ink-50)!important;opacity:.9!important;z-index:0!important;}
.sige-matriz-page .sige-stat-card>*{position:relative!important;z-index:1!important;}
.sige-matriz-page .sige-stat-icon{position:absolute!important;left:24px!important;top:28px!important;width:46px!important;height:46px!important;border-radius:var(--radius-lg)!important;display:flex!important;align-items:center!important;justify-content:center!important;background:var(--sgv2-soft)!important;color:var(--sgv2-primary)!important;z-index:2!important;}
.sige-matriz-page .sige-stat-icon svg{width:22px!important;height:22px!important;stroke:currentColor!important;}
.sige-matriz-page .sige-stat-label{color:var(--color-slate-600)!important;font-size:var(--fs-sm)!important;font-weight:700!important;margin:0 0 6px!important;}
.sige-matriz-page .sige-stat-value{color:var(--sgv2-text)!important;font-size:30px!important;line-height:1!important;font-weight:700!important;letter-spacing:-.035em!important;margin-bottom:8px!important;}
.sige-matriz-page .sige-stat-note{color:var(--color-slate-500)!important;font-size:12px!important;font-weight:600!important;line-height:1.35!important;}
.sige-matriz-page .stat-success .sige-stat-icon{background:var(--color-success-100)!important;color:var(--color-success-700)!important;}.sige-matriz-page .stat-info .sige-stat-icon{background:var(--color-info-50)!important;color:var(--color-info-400)!important;}.sige-matriz-page .stat-amber .sige-stat-icon{background:var(--color-warning-100)!important;color:var(--color-warning-500)!important;}

/* Layout principal */
.sige-matriz-page .sige-matriz-wrapper{display:grid!important;grid-template-columns:320px minmax(0,1fr)!important;gap:22px!important;align-items:start!important;animation:sgFadeUp .35s ease-out .1s both!important;}
.sige-matriz-page .sige-sidebar,.sige-matriz-page .sige-main-panel{background:var(--color-white)!important;border:1px solid rgba(255,255,255,.88)!important;border-radius:var(--radius-xl)!important;box-shadow:var(--shadow-xs);}
.sige-matriz-page .sige-sidebar{padding:18px!important;position:sticky!important;top:18px!important;max-height:calc(100vh - 140px)!important;overflow:auto!important;}
.sige-matriz-page .sige-sidebar::-webkit-scrollbar,.sige-matriz-page .sige-table-wrapper::-webkit-scrollbar{height:8px;width:8px}.sige-matriz-page .sige-sidebar::-webkit-scrollbar-thumb,.sige-matriz-page .sige-table-wrapper::-webkit-scrollbar-thumb{background:rgba(var(--sgv2-primary-rgb),.20);border-radius:22px}.sige-matriz-page .sige-sidebar::-webkit-scrollbar-track,.sige-matriz-page .sige-table-wrapper::-webkit-scrollbar-track{background:transparent}
.sige-matriz-page .sige-nav-group{margin:0 0 var(--space-5)!important;}.sige-matriz-page .sige-nav-group:last-child{margin-bottom:0!important;}
.sige-matriz-page .sige-nav-label{display:flex!important;align-items:center!important;gap:9px!important;padding:0 8px 10px!important;margin:0 0 var(--space-2)!important;border-bottom:1px solid var(--color-slate-100)!important;color:var(--color-slate-500)!important;font-size:10px!important;font-weight:700!important;text-transform:uppercase!important;letter-spacing:.12em!important;}
.sige-matriz-page .sige-nav-label svg{width:16px!important;height:16px!important;stroke:currentColor!important;}
.sige-matriz-page .sige-nav-link{display:flex!important;align-items:center!important;gap:11px!important;min-height:44px!important;margin:0 0 6px!important;padding:0 var(--space-3)!important;border-radius:var(--radius-lg)!important;color:var(--color-slate-700)!important;text-decoration:none!important;font-size:var(--fs-sm)!important;font-weight:700!important;transition:.18s ease!important;border:1px solid transparent!important;background:var(--color-white)!important;}
.sige-matriz-page .sige-nav-link svg{width:17px!important;height:17px!important;stroke:currentColor!important;opacity:.75!important;}
.sige-matriz-page .sige-nav-link:hover{background:var(--color-slate-50)!important;color:var(--sgv2-primary)!important;transform:translateX(3px)!important;}
.sige-matriz-page .sige-nav-link.active{background:linear-gradient(135deg,var(--sgv2-primary),var(--sgv2-primary-dark))!important;color:var(--color-white)!important;box-shadow:var(--shadow-sm);transform:none!important;}
.sige-matriz-page .sige-nav-count{margin-left:auto!important;min-width:24px!important;height:24px!important;border-radius:var(--radius-pill)!important;background:var(--color-slate-100)!important;color:var(--color-slate-600)!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;font-size:var(--fs-xs)!important;font-weight:700!important;padding:0 7px!important;}
.sige-matriz-page .sige-nav-link.active .sige-nav-count{background:rgba(255,255,255,.22)!important;color:var(--color-white)!important;}
.sige-matriz-page .sige-main-panel{padding:26px!important;min-height:560px!important;}
.sige-matriz-page .sige-empty-state{text-align:center!important;padding:84px 28px!important;}.sige-matriz-page .sige-empty-icon{width:86px!important;height:86px!important;border-radius:var(--radius-xl)!important;background:var(--sgv2-soft)!important;color:var(--sgv2-primary)!important;display:flex!important;align-items:center!important;justify-content:center!important;margin:0 auto var(--space-5)!important;}.sige-matriz-page .sige-empty-icon svg{width:40px!important;height:40px!important;stroke:currentColor!important;}.sige-matriz-page .sige-empty-state h3{margin:0 0 var(--space-2)!important;color:var(--sgv2-text)!important;font-size:22px!important;font-weight:700!important;}.sige-matriz-page .sige-empty-state p{margin:0 auto!important;max-width:480px!important;color:var(--color-slate-600)!important;font-size:var(--fs-base)!important;line-height:1.65!important;font-weight:600!important;}

/* Painel e formulário */
.sige-matriz-page .sige-panel-header{display:flex!important;justify-content:space-between!important;align-items:flex-start!important;gap:18px!important;margin:0 0 var(--space-5)!important;padding:0 0 var(--space-5)!important;border-bottom:1px solid var(--color-slate-100)!important;}
.sige-matriz-page .sige-panel-title{font-size:26px!important;line-height:1.1!important;letter-spacing:-.04em!important;font-weight:700!important;color:var(--sgv2-text)!important;margin:0 0 10px!important;}
.sige-matriz-page .sige-status-badge{display:inline-flex!important;align-items:center!important;gap:var(--space-2)!important;min-height:36px!important;padding:0 13px!important;border-radius:var(--radius-pill)!important;font-size:12px!important;font-weight:700!important;}
.sige-matriz-page .sige-status-badge svg{width:16px!important;height:16px!important;stroke:currentColor!important;}.sige-matriz-page .sige-status-loading svg{animation:sgSpin 1s linear infinite!important;}
.sige-matriz-page .sige-status-ok{background:var(--color-success-100)!important;color:var(--color-success-800)!important;border:1px solid rgba(47,169,92,.15)!important;}.sige-matriz-page .sige-status-warning{background:var(--color-warning-100)!important;color:var(--color-warning-800)!important;border:1px solid rgba(243,154,24,.18)!important;}.sige-matriz-page .sige-status-loading{background:var(--color-ink-50)!important;color:var(--color-slate-600)!important;border:1px solid var(--color-ink-100)!important;}
.sige-matriz-page .sige-btn-clone{min-height:46px!important;padding:0 18px!important;border-radius:var(--radius-lg)!important;background:var(--color-white)!important;color:var(--sgv2-primary)!important;border:1px solid rgba(var(--sgv2-primary-rgb),.18)!important;display:inline-flex!important;align-items:center!important;gap:9px!important;font-size:var(--fs-sm)!important;font-weight:700!important;box-shadow:var(--shadow-sm);cursor:pointer!important;}
.sige-matriz-page .sige-btn-clone svg{width:18px!important;height:18px!important;stroke:currentColor!important;}
.sige-matriz-page .sige-action-bar{display:grid!important;grid-template-columns:minmax(260px,1fr) minmax(230px,280px) auto!important;gap:14px!important;align-items:end!important;margin:0 0 22px!important;padding:18px!important;border-radius:var(--radius-xl)!important;background:var(--color-slate-50)!important;border:1px solid var(--color-slate-100)!important;}
.sige-matriz-page .sige-action-field{display:flex!important;flex-direction:column!important;gap:var(--space-2)!important;}.sige-matriz-page .sige-action-field label{color:var(--color-slate-700)!important;font-size:var(--fs-xs)!important;text-transform:uppercase!important;letter-spacing:.1em!important;font-weight:700!important;}
.sige-matriz-page .sige-action-field select,.sige-matriz-page .sige-action-field input{height:52px!important;border-radius:var(--radius-lg)!important;border:1px solid var(--color-ink-100)!important;background:var(--color-white)!important;color:var(--color-ink-500)!important;font-size:var(--fs-base)!important;font-weight:600!important;padding:0 15px!important;outline:none!important;box-shadow:var(--shadow-sm);}
.sige-matriz-page .sige-action-field select:focus,.sige-matriz-page .sige-action-field input:focus{border-color:rgba(var(--sgv2-primary-rgb),.42)!important;box-shadow:var(--shadow-xs);}
.sige-matriz-page .sige-carga-inputs{display:flex!important;align-items:center!important;gap:var(--space-2)!important;}.sige-matriz-page .sige-carga-inputs input{width:74px!important;text-align:center!important;}.sige-matriz-page .sige-carga-inputs span{font-weight:700!important;color:var(--color-slate-500)!important;font-size:12px!important;}
.sige-matriz-page .sige-btn-vincular{height:52px!important;border-radius:var(--radius-lg)!important;padding:0 22px!important;border:0!important;background:linear-gradient(135deg,var(--sgv2-primary),var(--sgv2-primary-dark))!important;color:var(--color-white)!important;font-size:var(--fs-sm)!important;font-weight:700!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:9px!important;box-shadow:var(--shadow-md);cursor:pointer!important;}
.sige-matriz-page .sige-btn-vincular svg{width:18px!important;height:18px!important;stroke:currentColor!important;}.sige-matriz-page .sige-btn-vincular:disabled{opacity:.68!important;cursor:not-allowed!important;}

/* Tabela */
.sige-matriz-page .sige-table-wrapper{overflow:auto!important;border:1px solid var(--color-slate-100)!important;border-radius:var(--radius-xl)!important;background:var(--color-white)!important;}.sige-matriz-page .sige-table{width:100%!important;border-collapse:separate!important;border-spacing:0!important;font-size:var(--fs-base)!important;min-width:720px!important;}.sige-matriz-page .sige-table thead th{padding:15px 18px!important;background:var(--color-slate-50)!important;color:var(--color-slate-600)!important;text-align:left!important;font-size:var(--fs-xs)!important;font-weight:700!important;text-transform:uppercase!important;letter-spacing:.09em!important;border-bottom:1px solid var(--color-slate-100)!important;}.sige-matriz-page .sige-table tbody td{padding:16px 18px!important;border-bottom:1px solid var(--color-slate-100)!important;vertical-align:middle!important;}.sige-matriz-page .sige-table tbody tr:hover{background:var(--color-white)!important;}.sige-matriz-page .sige-drag-handle{cursor:grab!important;color:var(--color-ink-300)!important;display:inline-flex!important;}.sige-matriz-page .sige-drag-handle svg{width:18px!important;height:18px!important;stroke:currentColor!important;}.sige-matriz-page .sige-disc-cell{display:flex!important;align-items:center!important;gap:var(--space-3)!important;}.sige-matriz-page .sige-disc-icon{width:42px!important;height:42px!important;border-radius:var(--radius-lg)!important;background:var(--sgv2-soft)!important;color:var(--sgv2-primary)!important;display:flex!important;align-items:center!important;justify-content:center!important;}.sige-matriz-page .sige-disc-icon svg{width:20px!important;height:20px!important;stroke:currentColor!important;}.sige-matriz-page .sige-disc-nome{font-weight:700!important;color:var(--color-ink-500)!important;}.sige-matriz-page .sige-sigla-badge{display:inline-flex!important;align-items:center!important;min-height:32px!important;padding:0 var(--space-3)!important;border-radius:var(--radius-pill)!important;background:var(--color-info-50)!important;border:1px solid var(--color-ink-100)!important;color:var(--sgv2-primary-dark)!important;font-weight:700!important;font-size:12px!important;}.sige-matriz-page .sige-carga-info{display:flex!important;align-items:baseline!important;gap:6px!important;}.sige-matriz-page .sige-carga-value{font-size:15px!important;font-weight:700!important;color:var(--color-ink-500)!important;}.sige-matriz-page .sige-carga-label{font-size:12px!important;color:var(--color-slate-500)!important;font-weight:600!important;}.sige-matriz-page .sige-btn-remove{width:38px!important;height:38px!important;border-radius:var(--radius-md)!important;border:1px solid rgba(239,68,68,.12)!important;background:var(--color-danger-50)!important;color:var(--color-danger-500)!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;cursor:pointer!important;}.sige-matriz-page .sige-btn-remove svg{width:17px!important;height:17px!important;stroke:currentColor!important;}.sortable-ghost{opacity:.45!important;background:var(--color-info-50)!important}.sortable-chosen{background:var(--color-brand-50)!important;box-shadow:var(--shadow-md);}

/* Pop-ups reais */
.sige-matriz-page .sige-modal{position:fixed!important;inset:0!important;background:rgba(18,22,40,.54)!important;backdrop-filter:blur(14px)!important;-webkit-backdrop-filter:blur(14px)!important;z-index:1000000!important;display:none;align-items:center!important;justify-content:center!important;padding:22px!important;}.sige-matriz-page .sige-modal-content{width:min(560px,calc(100vw - 44px))!important;border-radius:var(--radius-xl)!important;background:var(--color-white)!important;box-shadow:var(--shadow-lg);overflow:hidden!important;border:1px solid rgba(255,255,255,.86)!important;animation:sgFadeUp .22s ease both!important;}.sige-matriz-page .sige-modal-header{padding:24px 26px!important;background:linear-gradient(135deg,var(--color-white) 0%,var(--color-white) 56%,var(--sgv2-soft) 100%)!important;color:var(--sgv2-text)!important;border-bottom:1px solid var(--color-slate-100)!important;}.sige-matriz-page .sige-modal-header h3{margin:0!important;display:flex!important;align-items:center!important;gap:var(--space-3)!important;font-size:22px!important;line-height:1.12!important;font-weight:700!important;letter-spacing:-.035em!important;color:var(--sgv2-text)!important;}.sige-matriz-page .sige-modal-header h3 svg{width:42px!important;height:42px!important;padding:11px!important;border-radius:var(--radius-lg)!important;background:var(--sgv2-soft)!important;color:var(--sgv2-primary)!important;stroke:currentColor!important;}.sige-matriz-page .sige-modal-body{padding:22px 26px!important;}.sige-matriz-page .sige-modal-description{margin:0 0 18px!important;color:var(--color-slate-700)!important;font-size:var(--fs-base)!important;line-height:1.65!important;font-weight:600!important;}.sige-matriz-page .sige-modal-description strong{color:var(--sgv2-primary-dark)!important;}.sige-matriz-page .sige-modal-field{display:flex!important;flex-direction:column!important;gap:var(--space-2)!important;}.sige-matriz-page .sige-modal-field label{color:var(--color-slate-700)!important;font-size:var(--fs-xs)!important;font-weight:700!important;text-transform:uppercase!important;letter-spacing:.09em!important;}.sige-matriz-page .sige-modal-field select{height:52px!important;border-radius:var(--radius-lg)!important;border:1px solid var(--color-ink-100)!important;background:var(--color-white)!important;padding:0 15px!important;font-weight:600!important;}.sige-matriz-page .sige-modal-footer{display:flex!important;justify-content:flex-end!important;gap:var(--space-3)!important;padding:18px 26px!important;background:var(--color-white)!important;border-top:1px solid var(--color-slate-100)!important;}.sige-matriz-page .sige-btn-modal{min-height:50px!important;border-radius:var(--radius-lg)!important;padding:0 var(--space-5)!important;border:0!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:9px!important;font-size:var(--fs-base)!important;font-weight:700!important;cursor:pointer!important;}.sige-matriz-page .sige-btn-modal svg{width:18px!important;height:18px!important;stroke:currentColor!important;}.sige-matriz-page .sige-btn-cancel{background:var(--color-white)!important;border:1px solid var(--color-ink-100)!important;color:var(--color-slate-800)!important;}.sige-matriz-page .sige-btn-confirm{background:linear-gradient(135deg,var(--sgv2-primary),var(--sgv2-primary-dark))!important;color:var(--color-white)!important;box-shadow:var(--shadow-md);}
body.sige-modal-open{overflow:hidden!important;}

/* v12.10.139 - Matriz: modais fora do wrapper também recebem o padrão PRO.
   Evita que HTML de modais ocultos seja renderizado como conteúdo normal quando o escopo .sige-matriz-page não alcança o elemento. */
body.sige-admin-app.sige-view-matriz #modal-clone.sige-modal,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg.sige-modal{position:fixed!important;inset:0!important;background:rgba(18,22,40,.54)!important;backdrop-filter:blur(14px)!important;-webkit-backdrop-filter:blur(14px)!important;z-index:1000000!important;display:none;align-items:center!important;justify-content:center!important;padding:22px!important;box-sizing:border-box!important;}
body.sige-admin-app.sige-view-matriz #modal-clone.sige-modal[style*="display: flex"],
body.sige-admin-app.sige-view-matriz #modal-clone.sige-modal[style*="display:flex"],
body.sige-admin-app.sige-view-matriz #modal-matriz-msg.sige-modal[style*="display: flex"],
body.sige-admin-app.sige-view-matriz #modal-matriz-msg.sige-modal[style*="display:flex"]{display:flex!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-modal-content,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-modal-content{width:min(560px,calc(100vw - 44px))!important;border-radius:var(--radius-xl)!important;background:var(--color-white)!important;box-shadow:var(--shadow-lg);overflow:hidden!important;border:1px solid rgba(255,255,255,.86)!important;animation:sgFadeUp .22s ease both!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-modal-header,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-modal-header{padding:24px 26px!important;background:linear-gradient(135deg,var(--color-white) 0%,var(--color-white) 56%,var(--sg-theme-soft,var(--color-brand-50)) 100%)!important;color:var(--color-black)!important;border-bottom:1px solid var(--color-slate-100)!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-modal-header h3,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-modal-header h3{margin:0!important;display:flex!important;align-items:center!important;gap:var(--space-3)!important;font-size:22px!important;line-height:1.12!important;font-weight:700!important;letter-spacing:-.035em!important;color:var(--color-black)!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-modal-header h3 svg,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-modal-header h3 svg{width:42px!important;height:42px!important;padding:11px!important;border-radius:var(--radius-lg)!important;background:var(--sg-theme-soft,var(--color-brand-50))!important;color:var(--sg-theme-primary,var(--color-brand-500))!important;stroke:currentColor!important;flex:0 0 auto!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-modal-body,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-modal-body{padding:22px 26px!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-modal-footer,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-modal-footer{display:flex!important;justify-content:flex-end!important;gap:var(--space-3)!important;padding:18px 26px!important;background:var(--color-white)!important;border-top:1px solid var(--color-slate-100)!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-btn-modal,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-btn-modal{min-height:50px!important;border-radius:var(--radius-lg)!important;padding:0 var(--space-5)!important;border:0!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:9px!important;font-size:var(--fs-base)!important;font-weight:700!important;cursor:pointer!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-btn-modal svg,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-btn-modal svg{width:18px!important;height:18px!important;stroke:currentColor!important;flex:0 0 auto!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-btn-cancel,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-btn-cancel{background:var(--color-white)!important;border:1px solid var(--color-ink-100)!important;color:var(--color-slate-800)!important;}
body.sige-admin-app.sige-view-matriz #modal-clone .sige-btn-confirm,
body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-btn-confirm{background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-900,var(--color-brand-800)))!important;color:var(--color-white)!important;box-shadow:var(--shadow-md);}
@media (max-width:720px){body.sige-admin-app.sige-view-matriz #modal-clone.sige-modal,body.sige-admin-app.sige-view-matriz #modal-matriz-msg.sige-modal{padding:0!important;}body.sige-admin-app.sige-view-matriz #modal-clone .sige-modal-content,body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-modal-content{width:100%!important;max-height:100vh!important;border-radius:0!important;}body.sige-admin-app.sige-view-matriz #modal-clone .sige-modal-footer,body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-modal-footer{flex-direction:column!important;}body.sige-admin-app.sige-view-matriz #modal-clone .sige-btn-modal,body.sige-admin-app.sige-view-matriz #modal-matriz-msg .sige-btn-modal{width:100%!important;}}


@media (max-width:1500px){.sige-matriz-page .sige-hero-grid{grid-template-columns:1fr!important}.sige-matriz-page .sige-hero-art{display:none!important}.sige-matriz-page .sige-stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;}}
@media (max-width:1100px){.sige-matriz-page .sige-matriz-wrapper{grid-template-columns:1fr!important}.sige-matriz-page .sige-sidebar{position:relative!important;top:0!important;max-height:none!important;display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:16px!important}.sige-matriz-page .sige-action-bar{grid-template-columns:1fr!important}.sige-matriz-page .sige-btn-vincular{width:100%!important}.sige-matriz-page .sige-panel-header{flex-direction:column!important;}}
@media (max-width:720px){.sige-matriz-page .sige-hero{padding:var(--space-6) var(--space-5)!important;border-radius:22px!important}.sige-matriz-page .sige-hero h1{font-size:28px!important}.sige-matriz-page .sige-stats-grid{grid-template-columns:1fr!important}.sige-matriz-page .sige-sidebar{grid-template-columns:1fr!important}.sige-matriz-page .sige-main-panel{padding:18px!important;border-radius:22px!important}.sige-matriz-page .sige-carga-inputs{display:grid!important;grid-template-columns:1fr auto 1fr auto!important}.sige-matriz-page .sige-carga-inputs input{width:100%!important}.sige-matriz-page .sige-modal{padding:0!important}.sige-matriz-page .sige-modal-content{width:100%!important;max-height:100vh!important;border-radius:0!important}.sige-matriz-page .sige-modal-footer{flex-direction:column!important}.sige-matriz-page .sige-btn-modal{width:100%!important}}

/* v12.10.97 - Matriz Curricular: KPIs sem sobreposição em telas intermédias */
.sige-matriz-page .sige-stat-card{
    display:grid!important;
    grid-template-columns:58px minmax(0,1fr)!important;
    grid-template-areas:
        "icon label"
        "icon value"
        "icon note"!important;
    column-gap:18px!important;
    align-items:center!important;
    min-width:0!important;
    padding:22px 24px!important;
}
.sige-matriz-page .sige-stat-icon{
    position:relative!important;
    left:auto!important;
    top:auto!important;
    grid-area:icon!important;
    align-self:center!important;
    justify-self:center!important;
    width:52px!important;
    height:52px!important;
    border-radius:var(--radius-lg)!important;
    flex:0 0 auto!important;
}
.sige-matriz-page .sige-stat-label{
    grid-area:label!important;
    min-width:0!important;
    margin:0!important;
    padding:0!important;
    line-height:1.25!important;
    overflow-wrap:normal!important;
    word-break:normal!important;
}
.sige-matriz-page .sige-stat-value{
    grid-area:value!important;
    min-width:0!important;
    margin:var(--space-1) 0 var(--space-1)!important;
    line-height:1.05!important;
    overflow-wrap:anywhere!important;
}
.sige-matriz-page .sige-stat-note{
    grid-area:note!important;
    min-width:0!important;
    margin:0!important;
    overflow-wrap:normal!important;
    word-break:normal!important;
}
@media (min-width:1181px) and (max-width:1500px){
    .sige-matriz-page .sige-stats-grid{
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        gap:18px!important;
    }
    .sige-matriz-page .sige-stat-card{
        min-height:138px!important;
        padding:24px 28px!important;
        grid-template-columns:66px minmax(0,1fr)!important;
        column-gap:var(--space-5)!important;
    }
    .sige-matriz-page .sige-stat-icon{
        width:58px!important;
        height:58px!important;
    }
}
@media (max-width:1180px){
    .sige-matriz-page .sige-stat-card{
        min-height:128px!important;
        grid-template-columns:58px minmax(0,1fr)!important;
    }
}
@media (max-width:620px){
    .sige-matriz-page .sige-stat-card{
        grid-template-columns:50px minmax(0,1fr)!important;
        column-gap:14px!important;
        padding:var(--space-5)!important;
    }
    .sige-matriz-page .sige-stat-icon{
        width:48px!important;
        height:48px!important;
    }
}

</style>

<div class="wrap sige-matriz-page">

    <!-- ========================================
         HERO SECTION
         ======================================== -->
    <section class="sige-hero">
        <div class="sige-hero-grid">
            <div class="sige-hero-copy">
                <div class="sige-hero-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('grid') : ''; ?> Académico</div>
                <h1>Matriz Curricular</h1>
                <p class="sige-hero-subtitle">Configure as disciplinas por classe, organize a carga horária semanal e mantenha a estrutura curricular pronta para turmas, notas, pautas e documentos académicos.</p>
                <div class="sige-hero-actions">
                    <button type="button" class="sige-btn sige-btn-primary" data-sige-act="sigeAbrirClassePadrao" data-sige-noargs>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                        Começar configuração
                    </button>
                    <a href="?page=sige-app&view=disciplinas" class="sige-btn sige-btn-secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                        Gerir disciplinas
                    </a>
                </div>
            </div>
            <div class="sige-hero-art" aria-hidden="true"><span class="sige-hero-book"></span></div>
        </div>
    </section>

    <!-- ========================================
         INDICADORES
         ======================================== -->
    <section class="sige-stats-grid">
        <div class="sige-stat-card stat-primary">
            <div class="sige-stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            </div>
            <div class="sige-stat-label">Classes configuradas</div>
            <div class="sige-stat-value"><?php echo (int) $_mc_classes_configuradas; ?></div>
            <div class="sige-stat-note">de <?php echo (int) $_mc_total_classes; ?> classes acompanhadas</div>
        </div>
        <div class="sige-stat-card stat-success">
            <div class="sige-stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div class="sige-stat-label">Disciplinas vinculadas</div>
            <div class="sige-stat-value"><?php echo (int) $_mc_total_vinculos; ?></div>
            <div class="sige-stat-note">ligações activas na matriz</div>
        </div>
        <div class="sige-stat-card stat-info">
            <div class="sige-stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </div>
            <div class="sige-stat-label">Catálogo de disciplinas</div>
            <div class="sige-stat-value"><?php echo (int) count($disciplinas_all); ?></div>
            <div class="sige-stat-note">disponíveis para vincular</div>
        </div>
        <div class="sige-stat-card stat-amber">
            <div class="sige-stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </div>
            <div class="sige-stat-label">Carga horária</div>
            <div class="sige-stat-value">semanal</div>
            <div class="sige-stat-note">definida por disciplina e classe</div>
        </div>
    </section>

    <!-- ========================================
         MAIN LAYOUT
         ======================================== -->
    <div class="sige-matriz-wrapper">

        <!-- SIDEBAR NAVIGATION -->
        <nav class="sige-sidebar">
            <?php 
            $grupo_classes = ['Pré-Escolar' => 'label-pre', 'Ensino Primário' => 'label-ep', 'Ensino Secundário (ESG1)' => 'label-esg1', 'Ensino Secundário (ESG2)' => 'label-esg2'];
            foreach($classes_grupos as $grupo => $lista): 
                $label_class = $grupo_classes[$grupo] ?? '';
            ?>
                <div class="sige-nav-group">
                    <div class="sige-nav-label <?php echo $label_class; ?>">
                        <?php echo $grupo_icons[$grupo]; ?>
                        <?php echo $grupo; ?>
                    </div>
                    <?php foreach($lista as $c): 
                        $_cnt = $_mc_counts[$c] ?? 0;
                    ?>
                        <a href="javascript:void(0)" class="sige-nav-link" data-classe="<?php echo esc_attr($c); ?>" data-sige-act="carregarMatriz" data-sige-args="<?php echo esc_attr(wp_json_encode([$c])); ?>" data-sige-self>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                            <?php echo esc_html($c); ?>
                            <?php if ($_cnt > 0): ?>
                                <span class="sige-nav-count"><?php echo $_cnt; ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <!-- MAIN PANEL -->
        <div class="sige-main-panel" id="painel-matriz">
            <div class="sige-empty-state">
                <div class="sige-empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                </div>
                <h3>Seleccione uma classe</h3>
                <p>Escolha uma classe no painel lateral para consultar, organizar e actualizar as disciplinas que fazem parte da matriz curricular.</p>
            </div>
        </div>

    </div>

</div>

<!-- Select oculto: PHP renderiza as options, JS lê o innerHTML -->
<select id="sige-disc-source" style="display:none;">
    <option value="">-- Seleccionar Disciplina --</option>
    <?php foreach($disciplinas_all as $d): ?>
    <option value="<?php echo (int)$d->id; ?>" data-carga="<?php echo (int)$d->carga_horaria; ?>"><?php echo esc_html($d->nome); ?> (<?php echo esc_attr($d->sigla); ?>)</option>
    <?php endforeach; ?>
</select>

<!-- MODAL DE CLONAGEM -->
<div id="modal-clone" class="sige-modal" style="display:none;" aria-hidden="true">
    <div class="sige-modal-content" role="dialog" aria-modal="true" aria-labelledby="sige-matriz-clone-title">
        <div class="sige-modal-header">
            <h3 id="sige-matriz-clone-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                Clonar matriz curricular
            </h3>
        </div>
        <div class="sige-modal-body">
            <p class="sige-modal-description">
                Copie toda a estrutura curricular de uma classe existente para a <strong id="clone-destino-label"></strong>. Esta acção irá duplicar todas as disciplinas e cargas horárias.
            </p>
            <div class="sige-modal-field">
                <label>Seleccione a classe de origem</label>
                <select id="sel-origem-clone">
                    <option value="">-- Escolher classe --</option>
                    <?php foreach($classes_grupos as $g => $l): ?>
                        <?php foreach($l as $cl): ?>
                            <option value="<?php echo esc_attr($cl); ?>"><?php echo esc_attr($cl); ?> Classe</option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="sige-modal-footer">
            <button data-sige-act="sigeFecharModalClone" data-sige-noargs class="sige-btn-modal sige-btn-cancel">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Cancelar
            </button>
            <button data-sige-act="executarClonagem" data-sige-noargs class="sige-btn-modal sige-btn-confirm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                Confirmar Clonagem
            </button>
        </div>
    </div>
</div>

<!-- POP-UP DE CONFIRMAÇÃO / AVISO -->
<div id="modal-matriz-msg" class="sige-modal" style="display:none;" aria-hidden="true">
    <div class="sige-modal-content" role="dialog" aria-modal="true" aria-labelledby="matriz-msg-title">
        <div class="sige-modal-header">
            <h3 id="matriz-msg-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                Atenção
            </h3>
        </div>
        <div class="sige-modal-body">
            <p class="sige-modal-description" id="matriz-msg-body">Confirme a operação para continuar.</p>
        </div>
        <div class="sige-modal-footer" id="matriz-msg-actions">
            <button type="button" class="sige-btn-modal sige-btn-confirm" data-sige-act="sigeMatrizFecharMsg" data-sige-noargs>Entendi</button>
        </div>
    </div>
</div>

<script <?php echo sige_csp_script_attr(); ?>>
// Fecho do modal de clonagem (substitui a cadeia jQuery do onclick por vanilla fiel).
// O :visible do jQuery e reproduzido por offsetWidth/offsetHeight/getClientRects.
window.sigeFecharModalClone = function () {
    var m = document.getElementById('modal-clone');
    if (m) { m.style.display = 'none'; m.setAttribute('aria-hidden', 'true'); }
    var modais = document.querySelectorAll('.sige-modal');
    var algumVisivel = false;
    for (var i = 0; i < modais.length; i++) {
        var e = modais[i];
        if (e.offsetWidth > 0 || e.offsetHeight > 0 || e.getClientRects().length > 0) { algumVisivel = true; break; }
    }
    if (!algumVisivel) { document.body.classList.remove('sige-modal-open'); }
};
var sigeDiscHtml = document.getElementById('sige-disc-source').innerHTML;
var classeAtual = '';
var sigeMatrizConfirmCallback = null;

// [SEC] Prevent XSS in innerHTML templates
function escapeHtml(t) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(t));
    return d.innerHTML;
}

function sigeMatrizAbrirMsg(titulo, mensagem, confirmar, textoConfirmar) {
    var actions = document.getElementById('matriz-msg-actions');
    document.getElementById('matriz-msg-title').innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>' + escapeHtml(titulo || 'Atenção');
    document.getElementById('matriz-msg-body').innerHTML = mensagem || '';
    if (typeof confirmar === 'function') {
        sigeMatrizConfirmCallback = confirmar;
        actions.innerHTML = '<button type="button" class="sige-btn-modal sige-btn-cancel" data-sige-act="sigeMatrizFecharMsg" data-sige-noargs>Cancelar</button><button type="button" class="sige-btn-modal sige-btn-confirm" data-sige-act="sigeMatrizConfirmarMsg" data-sige-noargs>' + escapeHtml(textoConfirmar || 'Confirmar') + '</button>';
    } else {
        sigeMatrizConfirmCallback = null;
        actions.innerHTML = '<button type="button" class="sige-btn-modal sige-btn-confirm" data-sige-act="sigeMatrizFecharMsg" data-sige-noargs>Entendi</button>';
    }
    jQuery('#modal-matriz-msg').css('display','flex').attr('aria-hidden','false');
    jQuery('body').addClass('sige-modal-open');
}
function sigeMatrizFecharMsg() {
    jQuery('#modal-matriz-msg').hide().attr('aria-hidden','true');
    if (!jQuery('.sige-modal:visible').length) jQuery('body').removeClass('sige-modal-open');
    sigeMatrizConfirmCallback = null;
}
function sigeMatrizConfirmarMsg() {
    var cb = sigeMatrizConfirmCallback;
    sigeMatrizFecharMsg();
    if (typeof cb === 'function') cb();
}
function sigeAbrirClassePadrao() {
    var el = document.querySelector('.sige-nav-link[data-classe="<?php echo esc_js($_mc_default_class); ?>"]') || document.querySelector('.sige-nav-link');
    if (el) { el.click(); }
}

// ========================================
// CARREGAR MATRIZ
// ========================================
function carregarMatriz(classe, el) {
    classeAtual = classe;
    jQuery('.sige-nav-link').removeClass('active');
    jQuery(el).addClass('active');
    renderizarInterface();
}

// ========================================
// RENDERIZAR INTERFACE
// ========================================
function renderizarInterface() {
    var html = `
        <div class="sige-panel-header">
            <div>
                <h2 class="sige-panel-title">${escapeHtml(classeAtual)}</h2>
                <div id="status-container">
                    <span class="sige-status-badge sige-status-loading">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                        A calcular carga horária...
                    </span>
                </div>
            </div>
            <button data-sige-act="abrirModalClone" data-sige-noargs class="sige-btn-clone">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                Clonar de outra classe
            </button>
        </div>
        
        <div class="sige-action-bar">
            <div class="sige-action-field">
                <label>Disciplina curricular</label>
                <select id="sel-disc">${sigeDiscHtml}</select>
            </div>
            <div class="sige-action-field">
                <label>Carga horária semanal</label>
                <div class="sige-carga-inputs">
                    <input type="number" id="num-carga-h" value="0" min="0" max="40" title="Horas">
                    <span>h</span>
                    <input type="number" id="num-carga-m" value="0" min="0" max="59" title="Minutos">
                    <span>min</span>
                    <input type="hidden" id="num-carga" value="0">
                </div>
            </div>
            <button data-sige-act="salvarNaMatriz" data-sige-noargs class="sige-btn-vincular">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Vincular
            </button>
        </div>
        
        <div class="sige-table-wrapper">
            <table class="sige-table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;"></th>
                        <th>Disciplina</th>
                        <th style="width: 120px;">Código</th>
                        <th style="width: 160px;">Carga Horária</th>
                        <th style="width: 60px;"></th>
                    </tr>
                </thead>
                <tbody id="lista-matriz-corpo"></tbody>
            </table>
        </div>
    `;
    
    jQuery('#painel-matriz').html(html);
    buscarItensMatriz();
    
    // Auto-preencher carga ao seleccionar disciplina
    jQuery('#sel-disc').on('change', function(){
        var carga = parseInt(jQuery(this).find(':selected').data('carga')) || 0;
        jQuery('#num-carga-h').val(Math.floor(carga / 60));
        jQuery('#num-carga-m').val(carga % 60);
        jQuery('#num-carga').val(carga);
    });
    
    // Sincronizar h+min com hidden em tempo real
    jQuery('#num-carga-h, #num-carga-m').on('input', function(){
        var h = parseInt(jQuery('#num-carga-h').val()) || 0;
        var m = parseInt(jQuery('#num-carga-m').val()) || 0;
        jQuery('#num-carga').val(h * 60 + m);
    });
}

// ========================================
// BUSCAR ITENS DA MATRIZ
// ========================================
function buscarItensMatriz() {
    jQuery.post(ajaxurl, {action: 'sige_get_matriz', classe: classeAtual}, function(res) {
        var html = '';
        var total = 0;
        
        if(res.success && res.data.length > 0) {
            res.data.forEach(function(item) {
                total += parseInt(item.carga_horaria);
                html += `
                    <tr data-id="${item.id}" class="sortable-row">
                        <td class="sige-u-tac">
                            <span class="sige-drag-handle" title="Arrastar para reordenar">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/><polyline points="19 12 12 5 5 12"/></svg>
                            </span>
                        </td>
                        <td>
                            <div class="sige-disc-cell">
                                <div class="sige-disc-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                                </div>
                                <span class="sige-disc-nome">${escapeHtml(item.nome)}</span>
                            </div>
                        </td>
                        <td><span class="sige-sigla-badge">${escapeHtml(item.sigla)}</span></td>
                        <td>
                            <div class="sige-carga-info">
                                <span class="sige-carga-value">${sigeCargaDisplay(item.carga_horaria)}</span>
                                <span class="sige-carga-label">semanal</span>
                            </div>
                        </td>
                        <td class="sige-u-tac">
                            <button data-sige-act="removerDaMatriz" data-sige-args='[${item.id}]' class="sige-btn-remove" title="Remover disciplina">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </td>
                    </tr>
                `;
            });
        } else {
            html = `
                <tr>
                    <td colspan="5">
                        <div class="sige-empty-state" style="padding: 60px 20px;">
                            <div class="sige-empty-icon" style="width: 70px; height: 70px; margin-bottom: 16px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 32px; height: 32px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                            </div>
                            <h3 style="font-size: 1rem;">Nenhuma disciplina vinculada</h3>
                            <p>Use o formulário acima para adicionar disciplinas à matriz.</p>
                        </div>
                    </td>
                </tr>
            `;
        }
        
        jQuery('#lista-matriz-corpo').html(html);
        
        // Inicializar drag-and-drop com SortableJS
        var tbody = document.getElementById('lista-matriz-corpo');
        if (tbody && window.Sortable) {
            if (tbody._sortable) tbody._sortable.destroy();
            tbody._sortable = Sortable.create(tbody, {
                handle: '.sige-drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onEnd: function(evt) {
                    guardarOrdemCompleta();
                }
            });
        }
        
        // Status badge
        var badge = total >= 1200 ? 
            `<span class="sige-status-badge sige-status-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                Carga Completa: ${sigeCargaDisplay(total)}
            </span>` : 
            `<span class="sige-status-badge sige-status-warning">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Carga Parcial: ${sigeCargaDisplay(total)}
            </span>`;
        
        jQuery('#status-container').html(badge);
    });
}

// ========================================
// HELPERS
// ========================================
function sigeCargaDisplay(minutos) {
    var m = parseInt(minutos) || 0;
    var h = Math.floor(m / 60);
    var min = m % 60;
    if (h > 0 && min > 0) return h + 'h ' + String(min).padStart(2,'0') + 'm';
    if (h > 0) return h + 'h';
    return min + 'm';
}

// ========================================
// MODAL CLONE
// ========================================
function abrirModalClone() {
    jQuery('#clone-destino-label').text(classeAtual + ' Classe');
    jQuery('#modal-clone').css('display', 'flex').attr('aria-hidden','false');
    jQuery('body').addClass('sige-modal-open');
}

function executarClonagem() {
    var origem = jQuery('#sel-origem-clone').val();
    if(!origem) {
        sigeMatrizAbrirMsg('Escolha a classe de origem', 'Seleccione a classe que deseja usar como base para a clonagem da matriz curricular.');
        return;
    }
    
    var btn = jQuery('#modal-clone .sige-btn-confirm');
    btn.html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> A copiar...').prop('disabled', true);
    
    jQuery.post(ajaxurl, { 
        action: 'sige_clonar_matriz', 
        origem: origem, 
        destino: classeAtual 
    }, function(res) {
        if(res.success) {
            jQuery('#modal-clone').hide().attr('aria-hidden','true');
            if (!jQuery('.sige-modal:visible').length) jQuery('body').removeClass('sige-modal-open');
            buscarItensMatriz();
        } else {
            sigeMatrizAbrirMsg('Não foi possível clonar', escapeHtml(res.data || 'Não foi possível clonar a matriz curricular neste momento.'));
        }
        btn.html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><polyline points="20 6 9 17 4 12"/></svg> Confirmar Clonagem').prop('disabled', false);
    });
}

// ========================================
// VINCULAR DISCIPLINA
// ========================================
function salvarNaMatriz() {
    var did = jQuery('#sel-disc').val();
    var h = parseInt(jQuery('#num-carga-h').val()) || 0;
    var m = parseInt(jQuery('#num-carga-m').val()) || 0;
    jQuery('#num-carga').val(h * 60 + m);
    var carga = h * 60 + m;
    
    if (carga <= 0) { 
        sigeMatrizAbrirMsg('Carga horária em falta', 'Defina a carga horária semanal antes de vincular a disciplina à matriz.'); 
        return; 
    }
    if (!did) { 
        sigeMatrizAbrirMsg('Seleccione uma disciplina', 'Escolha a disciplina que pretende adicionar à matriz curricular desta classe.'); 
        return; 
    }
    
    var btn = jQuery('.sige-action-bar .sige-btn-vincular');
    var originalHtml = btn.html();
    btn.html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> A vincular...').prop('disabled', true);
    
    jQuery.post(ajaxurl, { 
        action: 'sige_vincular_matriz', 
        classe: classeAtual, 
        disciplina_id: did, 
        carga: carga 
    }, function(res) {
        if(res.success) {
            buscarItensMatriz();
            jQuery('#sel-disc').val('');
            jQuery('#num-carga-h').val('0');
            jQuery('#num-carga-m').val('0');
            jQuery('#num-carga').val('0');
        } else {
            sigeMatrizAbrirMsg('Não foi possível vincular', escapeHtml(res.data || 'Não foi possível vincular a disciplina neste momento.'));
        }
        btn.html(originalHtml).prop('disabled', false);
    });
}

// ========================================
// REMOVER DA MATRIZ
// ========================================
function removerDaMatriz(id) {
    sigeMatrizAbrirMsg(
        'Remover disciplina da matriz?',
        'Esta acção remove apenas a ligação da disciplina à classe seleccionada. A disciplina continuará disponível no catálogo da escola.',
        function(){
            jQuery.post(ajaxurl, {
                action: 'sige_remover_matriz', 
                id: id
            }, function() { 
                buscarItensMatriz(); 
            });
        },
        'Remover'
    );
}

// ========================================
// GUARDAR ORDEM (DRAG & DROP)
// ========================================
var _ordemSaveTimer = null;
function guardarOrdemCompleta() {
    clearTimeout(_ordemSaveTimer);
    _ordemSaveTimer = setTimeout(function() {
        var ordens = [];
        jQuery('#lista-matriz-corpo tr.sortable-row').each(function(idx) {
            ordens.push({ id: jQuery(this).data('id'), ordem: idx + 1 });
        });
        if (!ordens.length) return;
        
        // Visual feedback
        jQuery('#lista-matriz-corpo tr.sortable-row').css('outline', '2px solid var(--sige-primary)');
        
        jQuery.post(ajaxurl, {
            action: 'sige_update_ordem_matriz_lote',
            ordens: JSON.stringify(ordens)
        }, function(res) {
            if (res.success) {
                jQuery('#lista-matriz-corpo tr.sortable-row').css('outline', '2px solid var(--sige-success)');
                setTimeout(function() {
                    jQuery('#lista-matriz-corpo tr.sortable-row').css('outline', '');
                }, 800);
            } else {
                jQuery('#lista-matriz-corpo tr.sortable-row').css('outline', '2px solid var(--sige-error)');
                setTimeout(function() {
                    jQuery('#lista-matriz-corpo tr.sortable-row').css('outline', '');
                }, 1200);
            }
        });
    }, 300);
}

// ========================================
// FECHAR MODAL COM ESC / CLICK FORA
// ========================================
jQuery(document).on('keydown', function(e) {
    if (e.key === 'Escape') {
        jQuery('.sige-modal').hide();
        jQuery('body').removeClass('sige-modal-open');
    }
});

jQuery('.sige-modal').on('click', function(e) {
    if (e.target === this) {
        jQuery(this).hide();
        if (!jQuery('.sige-modal:visible').length) jQuery('body').removeClass('sige-modal-open');
    }
});

// ========================================
// CARREGAR SORTABLEJS
// ========================================
if (!window.Sortable) {
    // [CDN-10] Sortable é carregado via tag script no topo - ver abaixo
    var _waitSort = setInterval(function() {
        if (window.Sortable) { clearInterval(_waitSort); if (classeAtual) buscarItensMatriz(); }
    }, 100);
}
</script>
<?php echo sige_cdn_script('sortable'); ?>