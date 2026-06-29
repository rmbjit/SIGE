<?php
/**
 * SIGE SoftGenial - Portaria Digital (Controlo de Acesso)
 *
 * v12.11.9.80 - Junho 2026
 * - Copy simplificada para o utilizador final da Portaria.
 * - Resultado autorizado/negado aparece no próprio espaço da câmara em mobile/tablet.
 * - Câmara pausa após leitura do QR e oferece "Ler próximo crachá"/Processo manual sem scroll.
 * - Mantém fallback por foto/capture do QR e permissões próprias: portaria.ver + portaria.validar_acesso.
 * - Mantém o role Guarda/Portaria isolado.
 */

if (!defined('ABSPATH')) exit;

// Guard de acesso - Portaria Digital.
// v12.11.9.65+: matriz SIGE manda; WP caps ficam como fallback legado.
if (!sige_page_guard(
    ['portaria.ver','portaria.validar_acesso'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_recepcao','sige_guarda']
)) return;

$sg_portaria_icon = static function (string $name): string {
    if (function_exists('sige_ui_icon')) {
        return sige_ui_icon($name);
    }
    return '<svg class="sg-svg-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
};

$sg_portaria_now = function_exists('wp_date') ? wp_date('d/m/Y H:i') : date_i18n('d/m/Y H:i');
$sg_portaria_safe_camera_url = function_exists('sige_portaria_camera_safe_url') ? sige_portaria_camera_safe_url() : add_query_arg(['sige_portaria_camera' => '1'], home_url('/'));
?>

<?php echo function_exists('sige_cdn_script') ? sige_cdn_script("html5qrcode") : ''; ?>

<style id="sg-portaria-dashboard-grade">
/* ============================================================================
   SoftGenial Produto PRO - Portaria Digital Mobile/Tablet UX
   Escopo: camada visual/UX e fluxo da câmara. Não altera BD nem regras financeiras/académicas.
   ============================================================================ */
body.sige-view-portaria .sg-product-page-head{display:none!important;}

@keyframes sgPortariaFadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
@keyframes sgPortariaPulse{0%,100%{box-shadow:0 1px 2px rgba(15,23,42,.04)}50%{box-shadow:0 1px 2px rgba(15,23,42,.04)}}
@keyframes sgPortariaScan{0%{top:12%}50%{top:82%}100%{top:12%}}
@keyframes sgPortariaShake{0%,100%{transform:translateX(0)}25%{transform:translateX(-4px)}75%{transform:translateX(4px)}}

.sg-portaria-v2{
    --sgp-purple:var(--sg-theme-primary,var(--color-brand-500));
    --sgp-purple-dark:var(--sg-theme-primary-800,var(--color-ink-700));
    --sgp-purple-soft:var(--sg-theme-soft,var(--color-brand-50));
    --sgp-ink:var(--color-ink-500);
    --sgp-muted:var(--color-slate-500);
    --sgp-line:var(--color-ink-100);
    --sgp-bg:var(--color-ink-50);
    --sgp-green:var(--color-success-700);
    --sgp-red:var(--color-danger-500);
    --sgp-amber:var(--color-warning-500);
    --sgp-blue:var(--color-info-400);
    font-family:'Poppins','Inter','Segoe UI',system-ui,sans-serif;
    color:var(--sgp-ink);
}
.sg-portaria-v2 *{box-sizing:border-box;min-width:0;}
.sg-portaria-shell{display:flex;flex-direction:column;gap:var(--space-5);animation:sgPortariaFadeUp .45s ease-out both;}

/* HERO */
.sg-portaria-hero{
    position:relative;
    overflow:hidden;
    min-height:176px;
    border-radius:var(--radius-xl);
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 48%,var(--color-brand-100) 100%);
    border:1px solid rgba(92,64,187,.12);
    box-shadow:var(--shadow-lg);
    padding:30px 32px;
    display:grid;
    grid-template-columns:minmax(0,1.04fr) minmax(320px,.96fr);
    gap:22px;
    align-items:center;
}
.sg-portaria-hero:before{content:"";position:absolute;inset:auto -80px -130px auto;width:420px;height:300px;background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);pointer-events:none;}
.sg-portaria-hero-copy,.sg-portaria-hero-panel{position:relative;z-index:1;}
.sg-portaria-kicker{display:flex;align-items:center;gap:var(--space-2);margin-bottom:10px;color:var(--sgp-purple);font-size:12px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;}
.sg-portaria-kicker svg,.sg-portaria-chip svg,.sg-portaria-panel-label svg,.sg-camera-tip svg,.sg-portaria-history-head svg{width:18px!important;height:18px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sg-portaria-title{margin:0;color:var(--color-black);font-size:31px;line-height:1.08;font-weight:700;letter-spacing:-.04em;overflow-wrap:anywhere;}
.sg-portaria-subtitle{max-width:680px;margin:var(--space-3) 0 0;color:var(--color-slate-700);font-size:15px;line-height:1.65;font-weight:500;overflow-wrap:anywhere;}
.sg-portaria-hero-actions{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:22px;}
.sg-portaria-chip{min-height:44px;display:flex;align-items:center;justify-content:center;gap:var(--space-2);border-radius:var(--radius-pill);padding:0 14px;border:1px solid var(--color-ink-100);background:var(--color-white);color:var(--color-ink-800);font-size:12px;font-weight:700;box-shadow:var(--shadow-sm);text-align:center;white-space:normal;overflow-wrap:anywhere;}
.sg-portaria-chip.primary{background:linear-gradient(135deg,var(--color-brand-400),var(--color-brand-600));border-color:transparent;color:var(--color-white);box-shadow:var(--shadow-md);}
.sg-portaria-chip.soft{background:var(--color-brand-50);border-color:var(--color-brand-100);color:var(--sgp-purple);box-shadow:none;}

.sg-portaria-hero-panel{min-height:148px;border-radius:var(--radius-xl);background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.18));padding:22px;overflow:hidden;border:1px solid rgba(92,64,187,.08);display:flex;flex-direction:column;justify-content:center;gap:10px;}
.sg-portaria-hero-panel:before{content:"";position:absolute;right:22px;bottom:16px;width:112px;height:92px;border-radius:22px 22px 12px 12px;background:rgba(109,93,252,.16);box-shadow:inset 0 0 0 2px rgba(109,93,252,.12);}
.sg-portaria-hero-panel:after{content:"";position:absolute;right:62px;bottom:46px;width:36px;height:50px;border-radius:16px 18px 8px 8px;background:rgba(109,93,252,.32);}
.sg-portaria-hero-panel>*{position:relative;z-index:1;}
.sg-portaria-panel-label{display:flex;align-items:center;gap:var(--space-2);margin:0;color:var(--sgp-purple);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.11em;}
.sg-portaria-hero-panel strong{display:block;color:var(--color-ink-900);font-size:28px;line-height:1.05;font-weight:700;letter-spacing:-.04em;}
.sg-portaria-hero-panel small{display:block;max-width:340px;margin:0;color:var(--color-slate-600);font-size:var(--fs-sm);line-height:1.55;font-weight:600;}
.sg-portaria-panel-steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:var(--space-2);margin-top:6px;}
.sg-portaria-panel-step{border-radius:var(--radius-md);background:rgba(255,255,255,.68);border:1px solid rgba(255,255,255,.75);padding:10px;color:var(--color-slate-800);font-size:var(--fs-xs);font-weight:700;line-height:1.25;}
.sg-portaria-panel-step span{display:block;margin-bottom:4px;color:var(--sgp-purple);font-size:10px;text-transform:uppercase;letter-spacing:.08em;}

/* GRELHA PRINCIPAL */
.sg-portaria-grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(350px,.95fr);gap:18px;align-items:start;}
.sg-portaria-card{background:var(--color-white);border:1px solid rgba(30,34,60,.08);border-radius:var(--radius-xl);box-shadow:var(--shadow-md);overflow:hidden;min-width:0;}
.sg-portaria-card-header{display:flex;align-items:center;justify-content:space-between;gap:var(--space-4);padding:20px 22px 14px;}
.sg-portaria-card-title{display:flex;align-items:center;gap:var(--space-3);min-width:0;}
.sg-portaria-card-icon{width:40px;height:40px;border-radius:var(--radius-md);background:var(--icon-bg,var(--color-brand-50));color:var(--icon-color,var(--sgp-purple));display:flex;align-items:center;justify-content:center;flex:0 0 auto;}
.sg-portaria-card-icon svg{width:20px!important;height:20px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sg-portaria-card-title h3{margin:0;color:var(--color-ink-500);font-size:17px;line-height:1.1;font-weight:700;letter-spacing:-.03em;overflow-wrap:anywhere;}
.sg-portaria-card-title p{margin:5px 0 0;color:var(--color-ink-400);font-size:12px;font-weight:600;overflow-wrap:anywhere;}
.sg-portaria-card-badge{white-space:nowrap;border-radius:var(--radius-pill);background:var(--color-brand-50);color:var(--sgp-purple);padding:8px 11px;font-size:12px;font-weight:700;}
.sg-portaria-card-body{padding:10px 22px 22px;}

/* CÂMARA / SCANNER */
.sg-camera-frame{position:relative;overflow:hidden;min-height:432px;border-radius:var(--radius-xl);background:linear-gradient(135deg,var(--color-black) 0%,var(--color-ink-800) 55%,var(--color-brand-500) 100%);padding:14px;box-shadow:var(--shadow-md);}
.sg-camera-frame:before{content:"";position:absolute;inset:-80px auto auto -90px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.12);pointer-events:none;}
.sg-camera-frame.is-running:after{content:"";position:absolute;left:34px;right:34px;top:12%;height:3px;border-radius:var(--radius-pill);background:linear-gradient(90deg,rgba(255,255,255,0),rgba(255,255,255,.78),rgba(255,255,255,0));box-shadow:var(--shadow-xs);animation:sgPortariaScan 2.7s ease-in-out infinite;pointer-events:none;z-index:3;}
#reader{position:relative;z-index:2;width:100%;min-height:404px;border-radius:var(--radius-lg);overflow:hidden;background:var(--color-black);color:var(--color-white);display:flex;align-items:center;justify-content:center;}
#reader video{width:100%!important;height:100%!important;min-height:404px;border-radius:var(--radius-lg);object-fit:cover;}
.sg-camera-permission{position:absolute;z-index:4;inset:14px;border-radius:var(--radius-lg);background:linear-gradient(180deg,rgba(15,18,48,.92),rgba(15,18,48,.78));border:1px solid rgba(255,255,255,.16);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:var(--space-6);color:var(--color-white);}
.sg-camera-frame.is-running .sg-camera-permission{display:none;}
.sg-camera-icon{width:74px;height:74px;border-radius:var(--radius-xl);display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.18);margin-bottom:16px;animation:sgPortariaPulse 2.4s ease-in-out infinite;}
.sg-camera-icon svg{width:34px!important;height:34px!important;stroke:var(--color-white)!important;color:var(--color-white)!important;fill:none!important;}
.sg-camera-permission h4{margin:0;color:var(--color-white);font-size:var(--fs-lg);line-height:1.2;font-weight:700;letter-spacing:-.03em;}
.sg-camera-permission p{max-width:430px;margin:10px auto 18px;color:rgba(255,255,255,.80);font-size:var(--fs-sm);line-height:1.55;font-weight:600;}
.sg-camera-actions{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:10px;margin-top:14px;align-items:center;}
.sg-camera-select{width:100%;min-height:46px;border-radius:var(--radius-lg);border:1px solid var(--color-ink-100);background:var(--color-white);color:var(--color-ink-500);padding:0 var(--space-3);font-size:var(--fs-sm);font-weight:600;}
.sg-camera-button,.sg-portaria-manual button{min-height:46px;border:0;border-radius:var(--radius-lg);padding:0 15px;display:inline-flex;align-items:center;justify-content:center;gap:var(--space-2);background:linear-gradient(135deg,var(--color-brand-400),var(--color-brand-600));color:var(--color-white);font-size:var(--fs-sm);font-weight:700;cursor:pointer;box-shadow:var(--shadow-sm);transition:transform .18s ease,box-shadow .18s ease,opacity .18s ease;white-space:nowrap;}
.sg-camera-button:hover,.sg-portaria-manual button:hover{transform:translateY(-1px);box-shadow:var(--shadow-sm);}
.sg-camera-button:disabled,.sg-portaria-manual button:disabled{opacity:.62;cursor:not-allowed;transform:none;}
.sg-camera-button.secondary{background:var(--color-white);color:var(--color-ink-800);border:1px solid var(--color-ink-100);box-shadow:var(--shadow-sm);}
.sg-camera-button svg,.sg-portaria-manual button svg{width:17px!important;height:17px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;}
.sg-camera-status{margin-top:12px;border-radius:var(--radius-lg);border:1px solid var(--color-slate-100);background:var(--color-slate-50);padding:13px 14px;display:flex;align-items:flex-start;gap:10px;}
.sg-camera-status-dot{width:10px;height:10px;border-radius:var(--radius-pill);background:var(--camera-dot,var(--color-ink-400));box-shadow:var(--shadow-xs);margin-top:5px;flex:0 0 auto;}
.sg-camera-status strong{display:block;margin:0 0 3px;color:var(--color-ink-500);font-size:var(--fs-sm);font-weight:700;}
.sg-camera-status span{display:block;color:var(--color-slate-500);font-size:12px;line-height:1.45;font-weight:600;}
.sg-camera-diagnostic{margin-top:10px;border-radius:var(--radius-lg);background:var(--color-white);border:1px dashed var(--color-slate-200);padding:12px 13px;color:var(--color-slate-700);font-size:var(--fs-xs);line-height:1.55;font-weight:600;overflow-wrap:anywhere;}
.sg-camera-diagnostic strong{display:block;margin-bottom:4px;color:var(--color-ink-500);font-size:12px;font-weight:700;}
.sg-camera-diagnostic code{display:inline-block;max-width:100%;border-radius:var(--radius-sm);background:var(--color-slate-50);border:1px solid var(--color-slate-100);padding:2px 6px;color:var(--color-slate-700);font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:10px;white-space:normal;}
.sg-camera-frame.is-running + .sg-camera-status{--camera-dot:var(--color-success-700);--camera-soft:rgba(52,168,83,.14);}
.sg-camera-frame.is-starting + .sg-camera-status{--camera-dot:var(--color-info-400);--camera-soft:rgba(59,130,246,.14);}
.sg-camera-frame.is-error + .sg-camera-status,.sg-camera-frame.is-denied + .sg-camera-status{--camera-dot:var(--color-danger-500);--camera-soft:rgba(239,68,68,.14);}
.sg-camera-tip{display:flex;align-items:flex-start;justify-content:center;gap:var(--space-2);margin:13px 0 0;color:var(--color-ink-400);font-size:12px;font-weight:600;text-align:center;line-height:1.45;}

/* INPUT MANUAL */
.sg-portaria-manual{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;margin-top:16px;padding:var(--space-3);border-radius:var(--radius-lg);background:var(--color-white);border:1px solid var(--color-slate-100);box-shadow:var(--shadow-sm);}
.sg-portaria-manual input{width:100%;min-height:48px;border:0;outline:0;background:var(--color-slate-50);border-radius:var(--radius-md);padding:0 15px;color:var(--color-ink-500);font-size:var(--fs-base);font-weight:600;}
.sg-portaria-manual input::placeholder{color:var(--color-slate-400);font-weight:600;}
.sg-portaria-manual input:focus{box-shadow:var(--shadow-xs);background:var(--color-white);}
.sg-portaria-manual.shake{animation:sgPortariaShake .25s linear 2;}

/* RESULTADO */
.result-box{position:relative;min-height:432px;margin:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;border-radius:var(--radius-xl);background:linear-gradient(180deg,var(--color-white) 0%,var(--color-white) 100%);border:1px solid rgba(30,34,60,.08);box-shadow:var(--shadow-md);overflow:hidden;padding:28px;transition:background .25s ease,border-color .25s ease,box-shadow .25s ease;}
.result-box:before{content:"";position:absolute;right:-48px;top:-58px;width:158px;height:158px;border-radius:50%;background:var(--state-soft,var(--color-brand-50));opacity:.9;}
.result-box:after{content:"";position:absolute;left:-56px;bottom:-70px;width:170px;height:170px;border-radius:50%;background:rgba(109,93,252,.06);}
.sg-result-content{position:relative;z-index:1;width:100%;display:flex;flex-direction:column;align-items:center;}
.sg-result-topline{position:absolute;top:18px;left:20px;right:20px;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:10px;}
.sg-result-topline span{display:inline-flex;align-items:center;gap:var(--space-2);border-radius:var(--radius-pill);background:var(--color-brand-50);color:var(--sgp-purple);padding:7px 10px;font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.07em;}
.sg-result-topline small{color:var(--color-ink-400);font-size:var(--fs-xs);font-weight:600;}
.sg-result-topline svg{width:15px!important;height:15px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;}
.sg-result-ring{position:relative;width:154px;height:154px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:26px 0 20px;background:var(--color-white);border:1px solid var(--color-slate-100);box-shadow:var(--shadow-md);}
.sg-result-ring:before{content:"";position:absolute;inset:-8px;border-radius:50%;border:8px solid var(--state-soft,var(--color-brand-50));}
.res-foto{position:relative;z-index:1;width:132px;height:132px;border-radius:50%;object-fit:cover;border:5px solid var(--color-white);background:var(--color-white);box-shadow:var(--shadow-sm);transition:border-color .25s ease;}
.res-status{margin:0 0 10px;font-size:22px;line-height:1.12;font-weight:700;letter-spacing:-.03em;text-transform:uppercase;color:var(--color-slate-400);overflow-wrap:anywhere;}
.res-nome{max-width:100%;margin:0;color:var(--color-black);font-size:var(--fs-lg);line-height:1.25;font-weight:700;letter-spacing:-.025em;overflow-wrap:anywhere;}
.res-turma{margin-top:10px;display:inline-flex;align-items:center;justify-content:center;gap:var(--space-2);min-height:34px;border-radius:var(--radius-pill);background:var(--color-slate-50);border:1px solid var(--color-slate-100);color:var(--color-slate-700);padding:0 var(--space-4);font-size:var(--fs-sm);font-weight:700;max-width:100%;white-space:normal;overflow-wrap:anywhere;}
.res-obs{display:none;width:100%;margin-top:18px;border-radius:var(--radius-lg);background:var(--color-danger-50);border:1px solid var(--color-danger-100);color:var(--color-danger-700);padding:13px 15px;font-size:var(--fs-sm);line-height:1.45;font-weight:700;overflow-wrap:anywhere;}
.sg-result-foot{margin-top:18px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;width:100%;}
.sg-result-foot-item{border-radius:var(--radius-md);background:var(--color-slate-50);border:1px solid var(--color-slate-100);padding:11px 12px;text-align:left;}
.sg-result-foot-item small{display:block;margin-bottom:4px;color:var(--color-ink-400);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;}
.sg-result-foot-item strong{display:block;color:var(--color-ink-800);font-size:12px;font-weight:700;line-height:1.3;overflow-wrap:anywhere;}
.result-box.waiting{--state-soft:var(--color-brand-50);}
.result-box.waiting .sg-result-ring{animation:sgPortariaPulse 2s ease-in-out infinite;}
.result-box.reading{--state-soft:var(--color-info-50);border-color:var(--color-info-100);background:linear-gradient(180deg,var(--color-white) 0%,var(--color-slate-50) 100%);}
.result-box.reading .sg-result-ring{animation:sgPortariaPulse 1.25s ease-in-out infinite;}
.result-box.success{--state-soft:var(--color-success-100);border-color:rgba(52,168,83,.36);background:linear-gradient(180deg,var(--color-white) 0%,var(--color-success-50) 100%);box-shadow:var(--shadow-lg);}
.result-box.success .sg-result-ring:before{border-color:var(--color-success-100);}
.result-box.success .res-foto{border-color:var(--color-success-100);}
.result-box.success .sg-result-topline span{background:var(--color-success-100);color:var(--color-success-500);}
.result-box.error{--state-soft:var(--color-danger-50);border-color:rgba(239,68,68,.32);background:linear-gradient(180deg,var(--color-white) 0%,var(--color-warning-50) 100%);box-shadow:var(--shadow-lg);}
.result-box.error .sg-result-ring:before{border-color:var(--color-danger-100);}
.result-box.error .res-foto{border-color:var(--color-danger-50);}
.result-box.error .sg-result-topline span{background:var(--color-danger-50);color:var(--color-danger-500);}

/* HISTÓRICO LOCAL */
.sg-portaria-history{margin-top:16px;border-radius:var(--radius-lg);border:1px solid var(--color-slate-100);background:var(--color-white);padding:14px;box-shadow:var(--shadow-sm);}
.sg-portaria-history-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;color:var(--color-ink-500);font-size:var(--fs-sm);font-weight:700;}
.sg-portaria-history-head span{display:flex;align-items:center;gap:var(--space-2);}
.sg-portaria-history-head small{color:var(--color-ink-400);font-size:var(--fs-xs);font-weight:700;}
.sg-portaria-history-list{display:flex;flex-direction:column;gap:var(--space-2);}
.sg-portaria-history-empty{border-radius:var(--radius-md);background:var(--color-slate-50);border:1px dashed var(--color-info-100);color:var(--color-slate-500);padding:var(--space-3);text-align:center;font-size:12px;font-weight:600;}
.sg-history-item{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:10px;align-items:center;border-radius:var(--radius-md);background:var(--color-slate-50);border:1px solid var(--color-slate-100);padding:10px;}
.sg-history-dot{width:10px;height:10px;border-radius:var(--radius-pill);background:var(--h-color,var(--color-ink-400));box-shadow:var(--shadow-xs);}
.sg-history-item.success{--h-color:var(--color-success-700);--h-soft:rgba(52,168,83,.13);}
.sg-history-item.error{--h-color:var(--color-danger-500);--h-soft:rgba(239,68,68,.13);}
.sg-history-name{display:block;color:var(--color-ink-500);font-size:12px;font-weight:700;line-height:1.25;overflow-wrap:anywhere;}
.sg-history-meta{display:block;margin-top:2px;color:var(--color-slate-500);font-size:var(--fs-xs);font-weight:600;overflow-wrap:anywhere;}
.sg-history-time{color:var(--color-ink-400);font-size:var(--fs-xs);font-weight:700;white-space:nowrap;}

@media (max-width:1500px){.sg-portaria-hero{grid-template-columns:1fr}.sg-portaria-hero-panel{display:none}.sg-portaria-grid{grid-template-columns:1fr 1fr}}
@media (max-width:1100px){.sg-portaria-grid{grid-template-columns:1fr}.result-box,.sg-camera-frame{min-height:390px}.sg-portaria-hero-actions{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (max-width:760px){
    .sg-portaria-shell{gap:var(--space-4);}
    .sg-portaria-hero{padding:var(--space-6) var(--space-5);border-radius:var(--radius-xl);}
    .sg-portaria-title{font-size:25px;}
    .sg-portaria-subtitle{font-size:var(--fs-base);line-height:1.55;}
    .sg-portaria-hero-actions{grid-template-columns:1fr;gap:9px;}
    .sg-portaria-chip{width:100%;justify-content:center;border-radius:var(--radius-lg);padding:11px 12px;}
    .sg-portaria-card{border-radius:var(--radius-xl);}
    .sg-portaria-card-header{align-items:flex-start;flex-direction:column;padding:18px 16px 10px;}
    .sg-portaria-card-badge{white-space:normal;}
    .sg-portaria-card-body{padding:8px 16px 18px;}
    .sg-camera-frame{min-height:330px;padding:10px;border-radius:var(--radius-xl);}
    .sg-camera-permission{inset:10px;padding:18px;}
    .sg-camera-permission h4{font-size:18px;}
    .sg-camera-permission p{font-size:12px;}
    #reader{min-height:310px;}
    #reader video{min-height:310px;}
    .sg-camera-actions{grid-template-columns:1fr;}
    .sg-camera-button,.sg-camera-select{width:100%;}
    .sg-portaria-manual{grid-template-columns:1fr;}
    .sg-portaria-manual button{width:100%;}
    .result-box{min-height:360px;padding:24px 18px;}
    .sg-result-topline{position:relative;top:auto;left:auto;right:auto;margin-bottom:8px;flex-direction:column;}
    .sg-result-ring{width:138px;height:138px;margin-top:10px;}
    .res-foto{width:118px;height:118px;}
    .res-status{font-size:var(--fs-lg);}
    .res-nome{font-size:18px;}
    .sg-result-foot{grid-template-columns:1fr;}
    .sg-history-item{grid-template-columns:auto minmax(0,1fr);}
    .sg-history-time{grid-column:2;text-align:left;}
}
@media (max-width:380px){.sg-portaria-hero{padding:22px 16px}.sg-portaria-title{font-size:23px}.sg-portaria-card-body{padding-left:12px;padding-right:12px}.sg-camera-permission{padding:14px}.sg-camera-icon{width:64px;height:64px}.result-box{padding-left:14px;padding-right:14px}}
</style>

<div class="sg-portaria-v2" aria-label="Portaria Digital SoftGenial">
    <div class="sg-portaria-shell">
        <section class="sg-portaria-hero" aria-label="Resumo da Portaria Digital">
            <div class="sg-portaria-hero-copy">
                <div class="sg-portaria-kicker"><?php echo $sg_portaria_icon('shield'); ?> Operação Escolar</div>
                <h1 class="sg-portaria-title">Portaria Digital</h1>
                <p class="sg-portaria-subtitle">Escaneie o crachá, confirme a foto/turma e permita a entrada apenas para alunos activos. Suspensos, transferidos ou desistentes devem ser encaminhados à Secretaria.</p>
                <div class="sg-portaria-hero-actions" aria-label="Funcionalidades principais">
                    <span class="sg-portaria-chip primary"><?php echo $sg_portaria_icon('shield'); ?> Só alunos activos</span>
                    <span class="sg-portaria-chip"><?php echo $sg_portaria_icon('users'); ?> Identidade do aluno</span>
                    <span class="sg-portaria-chip soft"><?php echo $sg_portaria_icon('activity'); ?> Resposta visual e sonora</span>
                </div>
            </div>
            <div class="sg-portaria-hero-panel" aria-hidden="true">
                <div class="sg-portaria-panel-label"><?php echo $sg_portaria_icon('bolt'); ?> Posto de controlo</div>
                <strong>Fluxo simples para o guarda</strong>
                <small>Activar câmara, apontar para o QR do crachá e decidir com base no resultado apresentado.</small>
                <div class="sg-portaria-panel-steps">
                    <div class="sg-portaria-panel-step"><span>1</span>Permitir câmara</div>
                    <div class="sg-portaria-panel-step"><span>2</span>Escanear crachá</div>
                    <div class="sg-portaria-panel-step"><span>3</span>Permitir ou bloquear</div>
                </div>
            </div>
        </section>

        <section class="sg-portaria-grid" aria-label="Scanner e resultado da Portaria Digital">
            <article class="sg-portaria-card">
                <div class="sg-portaria-card-header">
                    <div class="sg-portaria-card-title">
                        <div class="sg-portaria-card-icon" style="--icon-bg:var(--color-brand-50);--icon-color:var(--color-brand-400);"><?php echo $sg_portaria_icon('grid'); ?></div>
                        <div>
                            <h3>Leitura do crachá</h3>
                            <p>Abra a câmara, aponte para o QR Code e veja o resultado no próprio ecrã.</p>
                        </div>
                    </div>
                    <span class="sg-portaria-card-badge">Câmara · Manual</span>
                </div>
                <div class="sg-portaria-card-body">
                    <div class="sg-camera-frame is-waiting" id="sg-camera-frame">
                        <div id="reader" aria-label="Pré-visualização da câmara para leitura do QR Code"></div>
                        <div class="sg-camera-permission" id="sg-camera-permission">
                            <div class="sg-camera-icon"><?php echo $sg_portaria_icon('grid'); ?></div>
                            <h4 id="sg-camera-permission-title">Abrir câmara</h4>
                            <p id="sg-camera-permission-text">Aponte para o QR Code do crachá. Depois da leitura, o resultado aparece no próprio ecrã.</p>
                            <button type="button" class="sg-camera-button" id="sg-camera-start"><?php echo $sg_portaria_icon('check'); ?> Abrir câmara</button>
                        </div>
                    </div>
                    <div class="sg-camera-status" id="sg-camera-status" aria-live="polite" role="status">
                        <span class="sg-camera-status-dot" aria-hidden="true"></span>
                        <div>
                            <strong id="sg-camera-status-title">Leitor ainda não iniciado</strong>
                            <span id="sg-camera-status-text">Toque em “Abrir câmara”. Depois da leitura, toque em “Ler próximo crachá” para continuar.</span>
                        </div>
                    </div>
                    <div class="sg-camera-diagnostic" id="sg-camera-diagnostic" hidden aria-hidden="true"></div>
                    <div class="sg-camera-actions" aria-label="Controlos da câmara">
                        <select id="sg-camera-select" class="sg-camera-select" aria-label="Seleccionar câmara" hidden>
                            <option value="">Câmara traseira recomendada</option>
                        </select>
                        <button type="button" class="sg-camera-button secondary" id="sg-camera-retry"><?php echo $sg_portaria_icon('activity'); ?> Abrir câmara</button>
                        <button type="button" class="sg-camera-button secondary" id="sg-camera-stop" hidden><?php echo $sg_portaria_icon('shield'); ?> Pausar</button>
                        <a class="sg-camera-button secondary" id="sg-camera-safe-newtab" href="<?php echo esc_url($sg_portaria_safe_camera_url); ?>" target="_blank" rel="noopener"><?php echo $sg_portaria_icon('grid'); ?> Ecrã completo</a>
                    </div>
                    <div class="sg-portaria-manual" id="sg-portaria-manual-box">
                        <input type="text" id="manual-proc" inputmode="numeric" pattern="[0-9]*" maxlength="20" autocomplete="off" placeholder="Digitar número de processo se o QR falhar..." aria-label="Número de processo do aluno">
                        <button type="button" id="sg-portaria-manual-btn" class="sgk-btn sgk-btn-sec"><?php echo $sg_portaria_icon('check'); ?> Verificar</button>
                    </div>
                    <p class="sg-camera-tip"><?php echo $sg_portaria_icon('pin'); ?> Dica: no telemóvel, o resultado aparece no próprio leitor. Se a câmara não abrir, use “Foto do QR” ou digite o número de processo.</p>
                </div>
            </article>

            <article class="sg-portaria-card">
                <div class="sg-portaria-card-header">
                    <div class="sg-portaria-card-title">
                        <div class="sg-portaria-card-icon" style="--icon-bg:var(--color-success-50);--icon-color:var(--color-success-500);"><?php echo $sg_portaria_icon('activity'); ?></div>
                        <div>
                            <h3>Resultado da verificação</h3>
                            <p>Confirmação visual para a equipa da portaria.</p>
                        </div>
                    </div>
                    <span class="sg-portaria-card-badge">Ao vivo</span>
                </div>
                <div class="sg-portaria-card-body">
                    <div id="result-panel" class="result-box waiting" aria-live="polite" aria-atomic="true" role="status">
                        <div class="sg-result-topline">
                            <span id="r-chip"><?php echo $sg_portaria_icon('shield'); ?> Aguardando</span>
                            <small id="r-time"><?php echo esc_html($sg_portaria_now); ?></small>
                        </div>
                        <div class="sg-result-content">
                            <div class="sg-result-ring">
                                <img id="r-foto" src="<?php echo esc_url(SIGE_URL . 'assets/img/avatar-default.svg'); ?>" class="res-foto" alt="Foto do aluno">
                            </div>
                            <div id="r-status" class="res-status">AGUARDANDO...</div>
                            <div id="r-nome" class="res-nome">--</div>
                            <div id="r-turma" class="res-turma">--</div>
                            <div id="r-obs" class="res-obs"></div>
                            <div class="sg-result-foot" aria-label="Resumo da verificação">
                                <div class="sg-result-foot-item"><small>Identificação</small><strong id="r-id-help">Cartão ou processo</strong></div>
                                <div class="sg-result-foot-item"><small>Acção</small><strong id="r-action-help">Aguardar leitura</strong></div>
                            </div>
                        </div>
                    </div>
                    <div class="sg-portaria-history" aria-label="Últimas leituras neste dispositivo">
                        <div class="sg-portaria-history-head">
                            <span><?php echo $sg_portaria_icon('activity'); ?> Últimas leituras</span>
                            <small>Local</small>
                        </div>
                        <div class="sg-portaria-history-list" id="sg-portaria-history-list">
                            <div class="sg-portaria-history-empty">Ainda não há leituras nesta sessão.</div>
                        </div>
                    </div>
                </div>
            </article>
        </section>
    </div>
</div>

<audio id="audio-success" src="<?php echo esc_url(SIGE_URL . 'assets/audio/success.ogg'); ?>" preload="auto"></audio>
<audio id="audio-error" src="<?php echo esc_url(SIGE_URL . 'assets/audio/error.ogg'); ?>" preload="auto"></audio>

<script <?php echo sige_csp_script_attr(); ?>>
var isScanning = true;
var isValidating = false;
var portariaNonce = <?php echo wp_json_encode(wp_create_nonce("sige_portaria_acesso")); ?>;
var portariaAjaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
var portariaAvatarDefault = <?php echo wp_json_encode(SIGE_URL . 'assets/img/avatar-default.svg'); ?>;
var portariaSafeCameraUrl = <?php echo wp_json_encode($sg_portaria_safe_camera_url); ?>;
var portariaResetTimer = null;
var sgPortariaQr = null;
var sgPortariaStarted = false;
var sgPortariaStarting = false;
var sgPortariaCameras = [];
var sgPortariaActiveCameraId = '';
var sgPortariaLastScan = { code: '', at: 0 };
var sgPortariaHistory = [];
var sgPortariaNativeStream = null;
var sgPortariaNativeVideo = null;
var sgPortariaNativeLoop = 0;
var sgPortariaStartedMode = '';

function sgPortariaNow() {
    var d = new Date();
    var dia = String(d.getDate()).padStart(2, '0');
    var mes = String(d.getMonth() + 1).padStart(2, '0');
    var ano = d.getFullYear();
    var hora = String(d.getHours()).padStart(2, '0');
    var min = String(d.getMinutes()).padStart(2, '0');
    return dia + '/' + mes + '/' + ano + ' ' + hora + ':' + min;
}

function sgPortariaSetText(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = value;
}

function sgPortariaPlay(audioId) {
    var audio = document.getElementById(audioId);
    if (!audio || typeof audio.play !== 'function') return;
    try {
        audio.currentTime = 0;
        var playPromise = audio.play();
        if (playPromise && typeof playPromise.catch === 'function') {
            playPromise.catch(function(){});
        }
    } catch(e) {}
}

function sgPortariaFrameSet(mode) {
    var frame = document.getElementById('sg-camera-frame');
    if (!frame) return;
    frame.classList.remove('is-waiting','is-starting','is-running','is-error','is-denied');
    frame.classList.add('is-' + mode);
}

function sgPortariaSetCameraStatus(mode, title, text) {
    sgPortariaFrameSet(mode || 'waiting');
    sgPortariaSetText('sg-camera-status-title', title || 'Estado da câmara');
    sgPortariaSetText('sg-camera-status-text', text || '');
    sgPortariaSetText('sg-camera-permission-title', title || 'Activar leitura por câmara');
    sgPortariaSetText('sg-camera-permission-text', text || 'Toque no botão para iniciar a câmara.');
}

function sgPortariaSetButtonBusy(isBusy) {
    var start = document.getElementById('sg-camera-start');
    var retry = document.getElementById('sg-camera-retry');
    if (start) {
        start.disabled = !!isBusy;
        start.setAttribute('aria-busy', isBusy ? 'true' : 'false');
        start.textContent = isBusy ? 'A abrir câmara...' : 'Abrir câmara';
    }
    if (retry) {
        retry.disabled = !!isBusy;
        retry.setAttribute('aria-busy', isBusy ? 'true' : 'false');
    }
}

function sgPortariaSetManualBusy(isBusy) {
    var btn = document.getElementById('sg-portaria-manual-btn');
    var input = document.getElementById('manual-proc');
    if (btn) btn.disabled = !!isBusy;
    if (input) input.disabled = !!isBusy;
    var panel = document.getElementById('result-panel');
    if (panel) panel.setAttribute('aria-busy', isBusy ? 'true' : 'false');
}

function sgPortariaErrorName(err) {
    return err && err.name ? String(err.name) : 'ErroDesconhecido';
}

function sgPortariaIsConstraintError(err) {
    var name = sgPortariaErrorName(err);
    return name === 'NotFoundError' || name === 'DevicesNotFoundError' || name === 'OverconstrainedError' || name === 'ConstraintNotSatisfiedError';
}

async function sgPortariaCameraPermissionState() {
    if (!navigator.permissions || typeof navigator.permissions.query !== 'function') return 'indisponível';
    try {
        var permission = await navigator.permissions.query({ name: 'camera' });
        return permission && permission.state ? String(permission.state) : 'indisponível';
    } catch(e) {
        return 'indisponível';
    }
}

function sgPortariaCameraPolicyState() {
    return sgPortariaCameraPolicyStatus();
}

async function sgPortariaVideoInputs() {
    if (!navigator.mediaDevices || typeof navigator.mediaDevices.enumerateDevices !== 'function') return [];
    try {
        var devices = await navigator.mediaDevices.enumerateDevices();
        return (devices || []).filter(function(device){ return device && device.kind === 'videoinput'; });
    } catch(e) {
        return [];
    }
}

function sgPortariaCameraPolicyStatus() {
        try {
        if (document.permissionsPolicy && typeof document.permissionsPolicy.allowsFeature === 'function') {
            return document.permissionsPolicy.allowsFeature('camera') ? 'permitida' : 'bloqueada';
        }
    } catch(e) {}
    try {
        if (document.featurePolicy && typeof document.featurePolicy.allowsFeature === 'function') {
            return document.featurePolicy.allowsFeature('camera') ? 'permitida' : 'bloqueada';
        }
    } catch(e) {}
    return 'indisponível';
}

function sgPortariaRenderDiagnostic(context) {
    var box = document.getElementById('sg-camera-diagnostic');
    if (!box) return;
    box.hidden = true;
    box.innerHTML = '';
}
function firstErrorNameSafe(value) {
    return String(value || '-').replace(/[<>&"']/g, function(ch){
        return {'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#039;'}[ch] || ch;
    });
}

function sgPortariaCameraErrorInfo(err, context) {
    var name = sgPortariaErrorName(err);
    var permissionState = context && context.permissionState ? String(context.permissionState) : 'indisponível';
    var policyStatus = context && context.policyStatus ? String(context.policyStatus) : sgPortariaCameraPolicyStatus();
    var nativeProbeOk = !!(context && context.nativeProbeOk);
    var firstErrorName = context && context.firstErrorName ? String(context.firstErrorName) : '';
    var phase = context && context.phase ? String(context.phase) : '';

    if (name === 'PermissionsPolicyError') {
        return {
            mode: 'error',
            title: 'Câmara indisponível',
            text: 'Não foi possível iniciar a câmara. Use Foto do QR ou digite o número de processo.',
            resultName: 'Câmara indisponível'
        };
    }

    if (nativeProbeOk && /html5qrcode|qr|barcode/i.test(phase + ' ' + firstErrorName)) {
        return {
            mode: 'error',
            title: 'Leitor não iniciou',
            text: 'Não foi possível iniciar a leitura. Tente novamente, escolha outra câmara ou use o processo manual.',
            resultName: 'Leitor não iniciou'
        };
    }

    if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
        if (policyStatus === 'bloqueada') {
            return {
                mode: 'denied',
                title: 'Câmara não autorizada',
                text: 'Não foi possível usar a câmara. Permita o acesso à câmara ou use Foto do QR/Processo manual.',
                resultName: 'Câmara não autorizada'
            };
        }
        if (permissionState === 'granted') {
            return {
                mode: 'denied',
                title: 'Câmara não iniciou',
                text: 'Não foi possível iniciar a câmara. Tente novamente, escolha outra câmara ou use o processo manual.',
                resultName: 'Câmara não iniciou'
            };
        }
        if (permissionState === 'denied') {
            return {
                mode: 'denied',
                title: 'Câmara não autorizada',
                text: 'Permita o uso da câmara para continuar, ou use Foto do QR/Processo manual.',
                resultName: 'Câmara não autorizada'
            };
        }
        return {
            mode: 'denied',
            title: 'Câmara não autorizada',
            text: 'Não foi possível usar a câmara. Use Foto do QR ou Processo manual.',
            resultName: 'Câmara não autorizada'
        };
    }
    if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
        return {
            mode: 'error',
            title: 'Nenhuma câmara encontrada',
            text: 'Não foi encontrada uma câmara utilizável neste dispositivo. Use o processo manual se necessário.',
            resultName: 'Câmara não encontrada'
        };
    }
    if (name === 'NotReadableError' || name === 'TrackStartError' || name === 'AbortError') {
        return {
            mode: 'error',
            title: 'A câmara não conseguiu iniciar',
            text: 'A câmara não iniciou. Tente novamente, escolha outra câmara se aparecer no selector ou use o processo manual.',
            resultName: 'Câmara não iniciou'
        };
    }
    if (name === 'OverconstrainedError' || name === 'ConstraintNotSatisfiedError') {
        return {
            mode: 'error',
            title: 'Câmara indisponível',
            text: 'A câmara seleccionada não está disponível. Escolha outra câmara ou valide pelo processo manual.',
            resultName: 'Câmara incompatível'
        };
    }
    if (name === 'SecurityError') {
        return {
            mode: 'error',
            title: 'Câmara indisponível',
            text: 'Não foi possível iniciar a câmara. Use o processo manual.',
            resultName: 'Câmara indisponível'
        };
    }
    if (name === 'NotSupportedError') {
        return {
            mode: 'error',
            title: 'Câmara não suportada',
            text: 'Não foi possível usar a câmara neste dispositivo. Valide pelo número de processo.',
            resultName: 'Dispositivo não suportado'
        };
    }
    if (name === 'LibraryMissing') {
        return {
            mode: 'error',
            title: 'Leitura do QR indisponível',
            text: 'O leitor de QR não carregou. Tente novamente ou use o número de processo.',
            resultName: 'QR indisponível'
        };
    }
    if (name === 'BarcodeDetectorMissing') {
        return {
            mode: 'error',
            title: 'Leitor QR indisponível',
            text: 'Não foi possível ler o QR neste dispositivo. Use o número de processo.',
            resultName: 'QR indisponível'
        };
    }
    return {
        mode: 'error',
        title: 'Não foi possível abrir a câmara',
        text: 'Use a validação manual ou tente novamente.',
        resultName: 'Câmara indisponível'
    };
}

function sgPortariaIsSecureCameraContext() {
    var host = window.location && window.location.hostname ? window.location.hostname : '';
    return !!(window.isSecureContext || (window.location && window.location.protocol === 'https:') || host === 'localhost' || host === '127.0.0.1');
}

function sgPortariaPopulateCameras(cameras) {
    sgPortariaCameras = Array.isArray(cameras) ? cameras : [];
    var select = document.getElementById('sg-camera-select');
    if (!select) return;
    select.innerHTML = '<option value="">Câmara traseira recomendada</option>';
    sgPortariaCameras.forEach(function(cam, index){
        var opt = document.createElement('option');
        opt.value = cam.id || '';
        opt.textContent = cam.label || ('Câmara ' + (index + 1));
        select.appendChild(opt);
    });
    select.hidden = sgPortariaCameras.length < 2;
}

function sgPortariaChooseCamera(cameras) {
    cameras = Array.isArray(cameras) ? cameras : [];
    if (!cameras.length) return '';
    var back = cameras.find(function(cam){ return /back|rear|traseira|environment|posterior/i.test(cam.label || ''); });
    return (back && back.id) ? back.id : (cameras[0].id || '');
}

function sgPortariaSleep(ms) {
    return new Promise(function(resolve){ setTimeout(resolve, ms || 0); });
}

async function sgPortariaLoadCameraList() {
    var cameras = [];
    if (typeof Html5Qrcode !== 'undefined' && typeof Html5Qrcode.getCameras === 'function') {
        try { cameras = await Html5Qrcode.getCameras(); } catch(e) { cameras = []; }
    }
    if (!cameras || !cameras.length) {
        var inputs = await sgPortariaVideoInputs();
        cameras = inputs.map(function(device, index){
            return { id: device.deviceId || '', label: device.label || ('Câmara ' + (index + 1)) };
        }).filter(function(cam){ return !!cam.id; });
    }
    sgPortariaPopulateCameras(cameras || []);
    if (!sgPortariaActiveCameraId && cameras && cameras.length) {
        sgPortariaActiveCameraId = sgPortariaChooseCamera(cameras);
        var select = document.getElementById('sg-camera-select');
        if (select && sgPortariaActiveCameraId) select.value = sgPortariaActiveCameraId;
    }
    return cameras || [];
}

function sgPortariaCameraConfigKey(config) {
    if (typeof config === 'string') return 'id:' + config;
    try { return 'obj:' + JSON.stringify(config || {}); } catch(e) { return String(config); }
}

function sgPortariaBuildCameraConfigCandidates(preferSelected) {
    var out = [];
    var seen = {};
    function add(config) {
        if (!config) return;
        var key = sgPortariaCameraConfigKey(config);
        if (seen[key]) return;
        seen[key] = true;
        out.push(config);
    }
    if (preferSelected && sgPortariaActiveCameraId) add(sgPortariaActiveCameraId);
    if (sgPortariaCameras && sgPortariaCameras.length) {
        var chosen = sgPortariaChooseCamera(sgPortariaCameras);
        if (chosen) add(chosen);
        sgPortariaCameras.forEach(function(cam){ if (cam && cam.id) add(cam.id); });
    }
    add({ facingMode: { ideal: 'environment' } });
    add({ facingMode: 'environment' });
    add({ facingMode: 'user' });
    return out;
}

async function sgPortariaClearQrInstance(delayMs) {
    if (sgPortariaQr) {
        try { await sgPortariaQr.stop(); } catch(e) {}
        try { sgPortariaQr.clear(); } catch(e) {}
        sgPortariaQr = null;
        if (delayMs) await sgPortariaSleep(delayMs);
    }
}

async function sgPortariaResetQrInstance() {
    await sgPortariaStopNativePreview(false);
    await sgPortariaClearQrInstance(0);
    sgPortariaQr = new Html5Qrcode('reader', false);
}

async function sgPortariaStartQrSequence(stage, preferSelected) {
    var configs = sgPortariaBuildCameraConfigCandidates(!!preferSelected);
    var lastErr = null;
    for (var i = 0; i < configs.length; i++) {
        await sgPortariaResetQrInstance();
        if (i > 0) await sgPortariaSleep(450);
        try {
            await sgPortariaQr.start(
                configs[i],
                sgPortariaScannerConfig(),
                onScanSuccess,
                function(){ /* erros de leitura por frame são esperados */ }
            );
            return { ok: true, phase: stage + '-' + (i + 1), config: configs[i] };
        } catch(e) {
            lastErr = e || { name: 'QrStartError' };
            lastErr._sgPhase = stage + '-' + (i + 1);
            if (sgPortariaErrorName(lastErr) === 'NotAllowedError' || sgPortariaErrorName(lastErr) === 'PermissionDeniedError') break;
            if (sgPortariaErrorName(lastErr) === 'NotReadableError' || sgPortariaErrorName(lastErr) === 'TrackStartError') break;
        }
    }
    if (!lastErr) lastErr = { name: 'QrStartError', _sgPhase: stage };
    throw lastErr;
}

function sgPortariaCameraConfig() {
    if (sgPortariaActiveCameraId) return sgPortariaActiveCameraId;
    var firstAvailable = sgPortariaChooseCamera(sgPortariaCameras);
    if (firstAvailable) return firstAvailable;
    return { facingMode: { ideal: 'environment' } };
}

function sgPortariaScannerConfig() {
    return {
        fps: 10,
        qrbox: function(viewfinderWidth, viewfinderHeight) {
            var edge = Math.floor(Math.min(viewfinderWidth, viewfinderHeight) * 0.72);
            edge = Math.max(170, Math.min(edge, 280));
            return { width: edge, height: edge };
        },
        aspectRatio: 1.333334,
        disableFlip: false,
        rememberLastUsedCamera: true
    };
}

function sgPortariaStopProbeStream(stream) {
    if (stream && typeof stream.getTracks === 'function') {
        stream.getTracks().forEach(function(track){ try { track.stop(); } catch(e) {} });
    }
}

function sgPortariaNativeConstraintCandidates(preferSelected) {
    var out = [];
    var seen = {};
    function add(config) {
        try {
            var key = JSON.stringify(config || {});
            if (seen[key]) return;
            seen[key] = true;
            out.push(config);
        } catch(e) {
            out.push(config);
        }
    }
    if (preferSelected && sgPortariaActiveCameraId) {
        add({ video: { deviceId: { exact: sgPortariaActiveCameraId } }, audio: false });
    }
    // Primeiro genérico: é o mais seguro para disparar a permissão e evita
    // Overconstrained/NotReadable causados por escolher câmara errada cedo demais.
    add({ video: true, audio: false });
    add({ video: { facingMode: { ideal: 'environment' } }, audio: false });
    add({ video: { facingMode: 'user' }, audio: false });
    return out;
}

async function sgPortariaGetNativeStream(preferSelected) {
    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
        throw { name: 'NotSupportedError', _sgPhase: 'api-check' };
    }
    var candidates = sgPortariaNativeConstraintCandidates(!!preferSelected);
    var lastErr = null;
    for (var i = 0; i < candidates.length; i++) {
        try {
            return await navigator.mediaDevices.getUserMedia(candidates[i]);
        } catch(e) {
            lastErr = e || { name: 'NativeCameraError' };
            lastErr._sgPhase = 'native-getUserMedia-' + (i + 1);
            // Se o utilizador/sistema recusou, repetir constraints não resolve.
            if (sgPortariaErrorName(lastErr) === 'NotAllowedError' || sgPortariaErrorName(lastErr) === 'PermissionDeniedError') break;
        }
    }
    if (!lastErr) lastErr = { name: 'NativeCameraError', _sgPhase: 'native-getUserMedia' };
    throw lastErr;
}

async function sgPortariaStopNativePreview(resetReader) {
    if (sgPortariaNativeLoop) {
        try { cancelAnimationFrame(sgPortariaNativeLoop); } catch(e) {}
        sgPortariaNativeLoop = 0;
    }
    if (sgPortariaNativeVideo) {
        try { sgPortariaNativeVideo.pause(); } catch(e) {}
        try { sgPortariaNativeVideo.srcObject = null; } catch(e) {}
        try { sgPortariaNativeVideo.remove(); } catch(e) {}
        sgPortariaNativeVideo = null;
    }
    if (sgPortariaNativeStream) {
        sgPortariaStopProbeStream(sgPortariaNativeStream);
        sgPortariaNativeStream = null;
    }
    if (resetReader) {
        var reader = document.getElementById('reader');
        if (reader) reader.innerHTML = '';
    }
}

async function sgPortariaStartNativeQrFallback(reasonErr) {
    await sgPortariaClearQrInstance(750);
    var detectorSupported = false;
    try {
        detectorSupported = 'BarcodeDetector' in window;
        if (detectorSupported && typeof BarcodeDetector.getSupportedFormats === 'function') {
            var formats = await BarcodeDetector.getSupportedFormats();
            detectorSupported = Array.isArray(formats) ? formats.indexOf('qr_code') !== -1 : true;
        }
    } catch(e) {
        detectorSupported = false;
    }
    if (!detectorSupported) {
        var missing = { name: 'BarcodeDetectorMissing', _sgPhase: 'native-fallback-support' };
        missing._sgFirstErrorName = sgPortariaErrorName(reasonErr);
        throw missing;
    }

    await sgPortariaStopNativePreview(true);
    var stream = await sgPortariaGetNativeStream(true);
    sgPortariaNativeStream = stream;

    var reader = document.getElementById('reader');
    if (!reader) throw { name: 'ReaderMissing', _sgPhase: 'native-fallback-reader' };
    reader.innerHTML = '';
    var video = document.createElement('video');
    video.setAttribute('autoplay', '');
    video.setAttribute('muted', '');
    video.setAttribute('playsinline', '');
    video.muted = true;
    video.playsInline = true;
    video.srcObject = stream;
    reader.appendChild(video);
    sgPortariaNativeVideo = video;

    try { await video.play(); } catch(e) {}

    var detector = new BarcodeDetector({ formats: ['qr_code'] });
    sgPortariaStarted = true;
    sgPortariaStartedMode = 'native-barcode-detector';
    isScanning = true;

    var tick = async function() {
        if (!sgPortariaStarted || sgPortariaStartedMode !== 'native-barcode-detector' || !sgPortariaNativeVideo) return;
        if (!document.hidden && isScanning && !isValidating && sgPortariaNativeVideo.readyState >= 2) {
            try {
                var codes = await detector.detect(sgPortariaNativeVideo);
                if (codes && codes.length) {
                    var value = codes[0].rawValue || (codes[0].rawValue === 0 ? '0' : '');
                    if (value) onScanSuccess(String(value));
                }
            } catch(e) {
                // Falhas por frame são normais. Mantém o ciclo activo.
            }
        }
        sgPortariaNativeLoop = requestAnimationFrame(tick);
    };
    sgPortariaNativeLoop = requestAnimationFrame(tick);
    return { ok: true, mode: 'native-barcode-detector' };
}

async function sgPortariaProbePermission() {
    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
        var unsupported = { name: 'NotSupportedError', _sgPhase: 'api-check' };
        unsupported._sgPermissionState = 'indisponível';
        unsupported._sgVideoCount = 0;
        unsupported._sgPolicyStatus = sgPortariaCameraPolicyStatus();
        throw unsupported;
    }

    var beforePermission = await sgPortariaCameraPermissionState();
    var beforeInputs = await sgPortariaVideoInputs();
    var stream = null;
    try {
        // v12.11.9.75: bootstrap seguro. O pedido nativo genérico acontece no
        // clique do utilizador, confirma a permissão real e evita iniciar o
        stream = await sgPortariaGetNativeStream(false);
        sgPortariaStopProbeStream(stream);
        stream = null;
        await sgPortariaSleep(750);
        var afterInputs = await sgPortariaVideoInputs();
        return {
            permissionState: await sgPortariaCameraPermissionState(),
            policyStatus: sgPortariaCameraPolicyStatus(),
            videoInputs: afterInputs.length ? afterInputs : beforeInputs,
            phase: 'native-bootstrap-getUserMedia'
        };
    } catch(err) {
        sgPortariaStopProbeStream(stream);
        err._sgPhase = err && err._sgPhase ? err._sgPhase : 'native-bootstrap-getUserMedia';
        err._sgPermissionState = await sgPortariaCameraPermissionState();
        if (!err._sgPermissionState || err._sgPermissionState === 'indisponível') err._sgPermissionState = beforePermission;
        err._sgPolicyStatus = sgPortariaCameraPolicyStatus();
        var afterFailInputs = await sgPortariaVideoInputs();
        err._sgVideoCount = (afterFailInputs.length ? afterFailInputs : beforeInputs).length;
        throw err;
    }
}

async function sgPortariaStartCamera() {
    if (sgPortariaStarting || sgPortariaStarted) return;
    sgPortariaStarting = true;
    sgPortariaSetButtonBusy(true);
    sgPortariaRenderDiagnostic(null);
    sgPortariaSetCameraStatus('starting', 'A abrir câmara', 'A preparar a leitura.');

    try {
        if (!sgPortariaIsSecureCameraContext()) {
            throw { name: 'SecurityError', _sgPhase: 'secure-context', _sgPolicyStatus: sgPortariaCameraPolicyStatus() };
        }
        var hasHtml5Qrcode = (typeof Html5Qrcode !== 'undefined');

        var initialPolicyHint = sgPortariaCameraPolicyStatus();
        if (initialPolicyHint === 'bloqueada') {
            sgPortariaRenderDiagnostic({
                permissionState: await sgPortariaCameraPermissionState(),
                policyStatus: initialPolicyHint,
                videoCount: '-',
                phase: 'policy-api-warning-before-real-test',
                nativeProbe: 'pendente',
                mode: 'aviso',
                errorName: 'PolicyApiSignalOnly'
            });
            sgPortariaSetCameraStatus('starting', 'A abrir câmara', 'A preparar a leitura.');
        }

        var probe = await sgPortariaProbePermission();
        var camerasFromProbe = (probe && probe.videoInputs ? probe.videoInputs : []).map(function(device, index){
            return { id: device.deviceId || '', label: device.label || ('Câmara ' + (index + 1)) };
        }).filter(function(cam){ return !!cam.id; });
        if (camerasFromProbe.length) sgPortariaPopulateCameras(camerasFromProbe);
        await sgPortariaLoadCameraList();

        if (!hasHtml5Qrcode) {
            var missingLibraryErr = { name: 'LibraryMissing', _sgPhase: 'library-load', _sgPermissionState: probe && probe.permissionState ? probe.permissionState : await sgPortariaCameraPermissionState(), _sgPolicyStatus: probe && probe.policyStatus ? probe.policyStatus : sgPortariaCameraPolicyStatus(), _sgVideoCount: camerasFromProbe.length };
            try {
                await sgPortariaStartNativeQrFallback(missingLibraryErr);
                sgPortariaRenderDiagnostic({
                    permissionState: missingLibraryErr._sgPermissionState,
                    videoCount: missingLibraryErr._sgVideoCount,
                    phase: 'native-barcode-detector-no-html5qrcode',
                    policyStatus: missingLibraryErr._sgPolicyStatus,
                    nativeProbe: 'ok',
                    mode: 'compatível',
                    firstErrorName: 'LibraryMissing',
                    errorName: 'NativeQrFallbackActive'
                });
                sgPortariaSetCameraStatus('running', 'Câmara activa', 'Aponte o QR Code do crachá para a área de leitura.');
                var stopNoLib = document.getElementById('sg-camera-stop');
                if (stopNoLib) stopNoLib.hidden = false;
                return;
            } catch(nativeOnlyErr) {
                nativeOnlyErr._sgFirstErrorName = 'LibraryMissing';
                nativeOnlyErr._sgNativeProbeOk = true;
                nativeOnlyErr._sgPermissionState = missingLibraryErr._sgPermissionState;
                nativeOnlyErr._sgPolicyStatus = missingLibraryErr._sgPolicyStatus;
                nativeOnlyErr._sgVideoCount = missingLibraryErr._sgVideoCount;
                throw nativeOnlyErr;
            }
        }

        try {
            await sgPortariaStartQrSequence('html5qrcode-after-native-bootstrap', true);
        } catch(qrErr) {
            qrErr._sgFirstErrorName = sgPortariaErrorName(qrErr);
            qrErr._sgNativeProbeOk = true;
            qrErr._sgPermissionState = probe && probe.permissionState ? probe.permissionState : await sgPortariaCameraPermissionState();
            qrErr._sgPolicyStatus = probe && probe.policyStatus ? probe.policyStatus : sgPortariaCameraPolicyStatus();
            qrErr._sgVideoCount = camerasFromProbe.length || (await sgPortariaVideoInputs()).length;
            qrErr._sgPhase = qrErr && qrErr._sgPhase ? qrErr._sgPhase : 'html5qrcode-after-native-bootstrap';

            sgPortariaSetCameraStatus('starting', 'A preparar leitor', 'A tentar novamente.');
            try {
                await sgPortariaStartNativeQrFallback(qrErr);
                sgPortariaRenderDiagnostic({
                    permissionState: qrErr._sgPermissionState,
                    videoCount: qrErr._sgVideoCount,
                    phase: 'native-barcode-detector-fallback',
                    policyStatus: qrErr._sgPolicyStatus,
                    nativeProbe: 'ok',
                    mode: 'compatível',
                    firstErrorName: qrErr._sgFirstErrorName,
                    errorName: 'Html5QrcodeFallbackActive'
                });
                sgPortariaSetCameraStatus('running', 'Câmara activa', 'Aponte o QR Code do crachá para a área de leitura.');
                var stopCompat = document.getElementById('sg-camera-stop');
                if (stopCompat) stopCompat.hidden = false;
                return;
            } catch(nativeFallbackErr) {
                nativeFallbackErr._sgFirstErrorName = qrErr._sgFirstErrorName;
                nativeFallbackErr._sgNativeProbeOk = true;
                nativeFallbackErr._sgPermissionState = qrErr._sgPermissionState;
                nativeFallbackErr._sgPolicyStatus = qrErr._sgPolicyStatus;
                nativeFallbackErr._sgVideoCount = qrErr._sgVideoCount;
                nativeFallbackErr._sgPhase = nativeFallbackErr._sgPhase || 'native-barcode-detector-fallback';
                throw nativeFallbackErr;
            }
        }

        sgPortariaStarted = true;
        sgPortariaStartedMode = 'html5qrcode';
        isScanning = true;
        sgPortariaRenderDiagnostic(null);
        sgPortariaSetCameraStatus('running', 'Câmara activa', 'Aponte o QR Code do crachá para a área de leitura.');
        var stop = document.getElementById('sg-camera-stop');
        if (stop) stop.hidden = false;
    } catch(err) {
        sgPortariaStarted = false;
        sgPortariaStartedMode = '';
        await sgPortariaStopNativePreview(false);
        var permissionState = err && err._sgPermissionState ? err._sgPermissionState : await sgPortariaCameraPermissionState();
        var videoInputs = await sgPortariaVideoInputs();
        var videoCount = err && typeof err._sgVideoCount === 'number' ? err._sgVideoCount : videoInputs.length;
        var phase = err && err._sgPhase ? err._sgPhase : 'camera-start';
        var policyStatus = err && err._sgPolicyStatus ? err._sgPolicyStatus : sgPortariaCameraPolicyStatus();
        var info = sgPortariaCameraErrorInfo(err, {
            permissionState: permissionState,
            videoCount: videoCount,
            phase: phase,
            policyStatus: policyStatus,
            nativeProbeOk: !!(err && err._sgNativeProbeOk),
            firstErrorName: err && err._sgFirstErrorName ? err._sgFirstErrorName : ''
        });
        sgPortariaRenderDiagnostic({
            permissionState: permissionState,
            videoCount: videoCount,
            phase: phase,
            policyStatus: policyStatus,
            nativeProbe: err && err._sgNativeProbeOk ? 'ok' : (String(phase).indexOf('native-') === 0 ? 'falhou' : 'não concluído'),
            mode: sgPortariaStartedMode || 'erro',
            firstErrorName: err && err._sgFirstErrorName ? err._sgFirstErrorName : '',
            errorName: sgPortariaErrorName(err)
        });
        sgPortariaSetCameraStatus(info.mode, info.title, info.text);
        mostrarResultado('ERRO', info.resultName, '-', '', '#ef4444', 'Use o processo manual', info.text, 'error');
    } finally {
        sgPortariaStarting = false;
        sgPortariaSetButtonBusy(false);
    }
}

async function sgPortariaStopCamera(showStatus) {
    if (portariaResetTimer) clearTimeout(portariaResetTimer);
    isScanning = false;
    if (sgPortariaQr) {
        try { await sgPortariaQr.stop(); } catch(e) {}
        try { sgPortariaQr.clear(); } catch(e) {}
        sgPortariaQr = null;
    }
    await sgPortariaStopNativePreview(true);
    sgPortariaStarted = false;
    sgPortariaStartedMode = '';
    var stop = document.getElementById('sg-camera-stop');
    if (stop) stop.hidden = true;
    if (showStatus !== false) {
        sgPortariaRenderDiagnostic(null);
        sgPortariaSetCameraStatus('waiting', 'Câmara pausada', 'Toque em “Iniciar câmara” para continuar.');
    }
}

async function sgPortariaSwitchCamera() {
    var select = document.getElementById('sg-camera-select');
    sgPortariaActiveCameraId = select ? String(select.value || '') : '';
    if (sgPortariaStarted) {
        await sgPortariaStopCamera(false);
        await sgPortariaStartCamera();
    }
}

function sgPortariaSetReading(codigo) {
    var painel = document.getElementById('result-panel');
    if (painel) painel.className = 'result-box reading';
    sgPortariaSetText('r-status', 'A VERIFICAR...');
    var st = document.getElementById('r-status');
    if (st) st.style.color = '#3b82f6';
    sgPortariaSetText('r-nome', 'A confirmar dados');
    sgPortariaSetText('r-turma', 'Processo: ' + codigo);
    sgPortariaSetText('r-id-help', 'Processo ' + codigo);
    sgPortariaSetText('r-action-help', 'A validar');
    sgPortariaSetText('r-chip', 'A verificar');
    sgPortariaSetText('r-time', sgPortariaNow());
    var obsDiv = document.getElementById('r-obs');
    if (obsDiv) obsDiv.style.display = 'none';
}

function onScanSuccess(decodedText) {
    var codigo = String(decodedText || '').trim();
    if(!isScanning || isValidating || !codigo) return;
    var now = Date.now();
    if (sgPortariaLastScan.code === codigo && (now - sgPortariaLastScan.at) < 3500) return;
    sgPortariaLastScan = { code: codigo, at: now };
    isScanning = false;
    validarAcesso(codigo, 'camera');
    if (portariaResetTimer) clearTimeout(portariaResetTimer);
}

function validarManual() {
    var procInput = document.getElementById('manual-proc');
    var proc = procInput ? String(procInput.value || '').replace(/[^0-9]/g, '').trim() : '';
    var box = document.getElementById('sg-portaria-manual-box');
    if(!proc) {
        if (box) {
            box.classList.remove('shake');
            void box.offsetWidth;
            box.classList.add('shake');
        }
        if (procInput) procInput.focus();
        return;
    }
    if (procInput) procInput.value = proc;
    validarAcesso(proc, 'manual');
}

function validarAcesso(codigo, origem) {
    var codigoOriginal = String(codigo || '').trim();
    var codigoDisplay = codigoOriginal.replace(/[^0-9]/g, '').slice(0, 32) || codigoOriginal;
    if (!codigoOriginal || isValidating) return;
    isValidating = true;
    sgPortariaSetManualBusy(true);
    sgPortariaSetReading(codigoDisplay);

    if (typeof jQuery === 'undefined' || typeof jQuery.post !== 'function') {
        isValidating = false;
        sgPortariaSetManualBusy(false);
        mostrarResultado('ERRO', 'Ligação indisponível', '-', '', '#ef4444', 'Falha de ligação', 'Não foi possível validar agora. Tente novamente.', 'error');
        return;
    }

    jQuery.post(portariaAjaxUrl, {
        action: 'sige_validar_acesso',
        qr_code: codigoOriginal,
        origem: origem || 'camera',
        _sige_nonce: portariaNonce
    }, function(response) {
        if(response && response.success) {
            var d = response.data || {};
            mostrarResultado(d.status, d.nome, d.turma, d.foto, d.cor, d.mensagem, d.obs, d.som, d.processo || codigoDisplay, d.acao || '', d.permitido === true || d.acesso_permitido === true);
        } else {
            var msg = 'Não foi possível validar o acesso.';
            if (response && response.data) {
                msg = (typeof response.data === 'string') ? response.data : (response.data.msg || msg);
            }
            mostrarResultado('ERRO', 'Não encontrado', '-', '', '#ef4444', 'ACESSO NÃO VALIDADO', msg, 'error', codigoDisplay);
        }
    }).fail(function() {
        mostrarResultado('ERRO', 'Falha de ligação', '-', '', '#ef4444', 'Sem comunicação', 'Não foi possível comunicar com o sistema. Verifique a rede e tente novamente.', 'error', codigoDisplay);
    }).always(function(){
        isValidating = false;
        sgPortariaSetManualBusy(false);
        if (portariaResetTimer) clearTimeout(portariaResetTimer);
        portariaResetTimer = setTimeout(function() { isScanning = !!sgPortariaStarted; resetDisplay(); }, 4200);
    });
}

function sgPortariaAddHistory(kind, nome, turma, processo, mensagem) {
    sgPortariaHistory.unshift({
        kind: kind === 'success' ? 'success' : 'error',
        nome: nome || 'Sem identificação',
        turma: turma || '-',
        processo: processo || '-',
        mensagem: mensagem || '',
        time: sgPortariaNow().slice(11)
    });
    sgPortariaHistory = sgPortariaHistory.slice(0, 5);
    var list = document.getElementById('sg-portaria-history-list');
    if (!list) return;
    list.innerHTML = '';
    sgPortariaHistory.forEach(function(item){
        var row = document.createElement('div');
        row.className = 'sg-history-item ' + item.kind;
        var dot = document.createElement('span');
        dot.className = 'sg-history-dot';
        dot.setAttribute('aria-hidden', 'true');
        var info = document.createElement('div');
        var name = document.createElement('span');
        name.className = 'sg-history-name';
        name.textContent = item.nome;
        var meta = document.createElement('span');
        meta.className = 'sg-history-meta';
        meta.textContent = 'Proc. ' + item.processo + ' · ' + item.turma;
        info.appendChild(name);
        info.appendChild(meta);
        var time = document.createElement('span');
        time.className = 'sg-history-time';
        time.textContent = item.time;
        row.appendChild(dot);
        row.appendChild(info);
        row.appendChild(time);
        list.appendChild(row);
    });
}


function sgPortariaBoolTrue(v) {
    return v === true || v === 1 || v === '1' || String(v || '').toLowerCase() === 'true';
}

function mostrarResultado(status, nome, turma, foto, cor, msg, obs, som, processo, acao, permitido) {
    var painel = document.getElementById('result-panel');
    var textoEstado = String([msg, status, obs, acao].filter(Boolean).join(' '));
    var explicitlyAllowed = sgPortariaBoolTrue(permitido);
    var isBlocked = !explicitlyAllowed || /bloqueado|suspenso|transferido|desistente|cancelado|inactivo|inativo|não activa|nao activa|não activo|nao activo/i.test(textoEstado);
    var isSuccess = explicitlyAllowed && !isBlocked;
    var effectiveColor = isSuccess ? '#4caf50' : '#ef4444';
    if (painel) {
        painel.className = 'result-box';
        painel.classList.add(isSuccess ? 'success' : 'error');
    }

    sgPortariaSetText('r-status', isSuccess ? (msg || 'ENTRADA AUTORIZADA') : (msg || 'ACESSO BLOQUEADO'));
    var statusEl = document.getElementById('r-status');
    if (statusEl) statusEl.style.color = effectiveColor;
    sgPortariaSetText('r-nome', nome || '--');
    sgPortariaSetText('r-turma', turma || '--');
    sgPortariaSetText('r-chip', isSuccess ? 'AUTORIZADO' : (isBlocked ? 'BLOQUEADO' : 'ATENÇÃO'));
    sgPortariaSetText('r-time', sgPortariaNow());
    sgPortariaSetText('r-id-help', processo ? ('Processo ' + processo) : (status || 'Verificado'));
    sgPortariaSetText('r-action-help', isSuccess ? (acao || 'Permitir entrada') : 'Encaminhar à Secretaria');

    var fotoEl = document.getElementById('r-foto');
    if(fotoEl) fotoEl.src = foto || portariaAvatarDefault;

    var obsDiv = document.getElementById('r-obs');
    if(obsDiv) {
        if(obs) { obsDiv.textContent = obs; obsDiv.style.display = 'block'; }
        else { obsDiv.textContent = ''; obsDiv.style.display = 'none'; }
    }

    if (processo) {
        sgPortariaAddHistory(isSuccess ? 'success' : 'error', nome, turma, processo, msg || status);
    }
    sgPortariaPlay(isSuccess ? 'audio-success' : 'audio-error');
}

function resetDisplay() {
    var painel = document.getElementById('result-panel');
    if (painel) painel.className = 'result-box waiting';
    sgPortariaSetText('r-status', 'AGUARDANDO...');
    var statusEl = document.getElementById('r-status');
    if (statusEl) statusEl.style.color = '#94a3b8';
    sgPortariaSetText('r-nome', '--');
    sgPortariaSetText('r-turma', '--');
    sgPortariaSetText('r-chip', 'Aguardando');
    sgPortariaSetText('r-time', sgPortariaNow());
    sgPortariaSetText('r-id-help', 'Cartão ou processo');
    sgPortariaSetText('r-action-help', 'Aguardar leitura');
    var fotoEl = document.getElementById('r-foto');
    if (fotoEl) fotoEl.src = portariaAvatarDefault;
    var obsDiv = document.getElementById('r-obs');
    if (obsDiv) { obsDiv.textContent = ''; obsDiv.style.display = 'none'; }
    var manual = document.getElementById('manual-proc');
    if (manual) manual.value = '';
}


function sgPortariaOpenSafeCamera() {
    if (!portariaSafeCameraUrl) return;
    window.location.assign(portariaSafeCameraUrl);
}

function sgPortariaInit() {
    var manual = document.getElementById('manual-proc');
    var manualBtn = document.getElementById('sg-portaria-manual-btn');
    var start = document.getElementById('sg-camera-start');
    var retry = document.getElementById('sg-camera-retry');
    var stop = document.getElementById('sg-camera-stop');
    var select = document.getElementById('sg-camera-select');

    if (manual) {
        manual.addEventListener('input', function(){
            manual.value = String(manual.value || '').replace(/[^0-9]/g, '');
        });
        manual.addEventListener('keydown', function(e){
            if (e.key === 'Enter') {
                e.preventDefault();
                validarManual();
            }
        });
    }
    if (manualBtn) manualBtn.addEventListener('click', validarManual);
    if (start) start.addEventListener('click', function(e){ e.preventDefault(); sgPortariaOpenSafeCamera(); });
    if (retry) retry.addEventListener('click', function(e){ e.preventDefault(); sgPortariaOpenSafeCamera(); });
    if (stop) stop.addEventListener('click', function(e){ e.preventDefault(); sgPortariaStopCamera(true); });
    if (select) select.addEventListener('change', function(){ sgPortariaSwitchCamera(); });

    sgPortariaRenderDiagnostic(null);
    sgPortariaSetCameraStatus('waiting', 'Leitor de crachás', 'Toque em “Abrir leitor”. Se a câmara não abrir, use Foto do QR ou Processo manual.');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', sgPortariaInit);
} else {
    sgPortariaInit();
}

window.addEventListener('pagehide', function(){ sgPortariaStopCamera(false); });
document.addEventListener('visibilitychange', function(){
    if (document.hidden && sgPortariaStarted) {
        sgPortariaStopCamera(true);
    }
});
</script>
