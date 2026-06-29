<?php
/**
 * SIGE SoftGenial - Centro de Configuração Produto PRO
 * @updated v12.10.5 - Wide Layout Alignment
 */
if (!defined('ABSPATH')) exit;

$__cfg_can_view = function_exists('sige_page_guard_allows')
    ? sige_page_guard_allows(['configuracoes.ver', 'configuracoes.editar'], [])
    : (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'));

if (!$__cfg_can_view) {
    if (function_exists('sige_page_guard_render_denied')) {
        sige_page_guard_render_denied('Acesso restrito', 'O Centro de Configuração exige permissão de configurações.');
        return;
    }
    wp_die('Acesso restrito.', 'Acesso restrito', ['response' => 403]);
}

if (!class_exists('SIGE_Settings_Registry')
    || !class_exists('SIGE_Settings_Repository')
    || !class_exists('SIGE_Settings_Controller')
    || !class_exists('SIGE_Settings_View_Renderer')
    || !class_exists('SIGE_Settings_Policy')) {
    echo '<div class="notice notice-error"><p><strong>SIGE SoftGenial:</strong> Camada de configurações não carregada.</p></div>';
    return;
}

$__cfg_version       = defined('SIGE_VERSION') ? SIGE_VERSION : 'N/D';
$__cfg_tech_active   = SIGE_Settings_Policy::is_technical_mode_active();
$__cfg_can_tech      = SIGE_Settings_Policy::can_enter_technical_mode();
$__cfg_tech_nonce    = wp_create_nonce('sige_settings_technical_mode');
$__cfg_tech_remaining = class_exists('SIGE_Settings_Technical_Mode') && $__cfg_tech_active
    ? SIGE_Settings_Technical_Mode::remaining_label() : '';

if (!function_exists('sige_cfg_group_progress')) {
    function sige_cfg_group_progress(string $group): array {
        $keys = SIGE_Settings_Registry::by_group($group);
        $visible = 0;
        $filled = 0;
        foreach ($keys as $key => $meta) {
            if (!SIGE_Settings_Policy::can_view($key)) continue;
            if (!empty($meta['readonly']) || !empty($meta['tech_only'])) continue;
            $visible++;
            $val = SIGE_Settings_Repository::get($key, $meta['default'] ?? '');
            if (is_array($val)) {
                if (!empty($val)) $filled++;
            } elseif (trim((string)$val) !== '') {
                $filled++;
            }
        }
        $pct = $visible > 0 ? (int)round(($filled / $visible) * 100) : 100;
        return ['filled' => $filled, 'total' => $visible, 'pct' => max(0, min(100, $pct))];
    }
}

$__cfg_p_identidade = sige_cfg_group_progress('identidade');
$__cfg_p_documentos = sige_cfg_group_progress('documentos');
$__cfg_p_local      = sige_cfg_group_progress('localizacao');
$__cfg_ano          = SIGE_Settings_Repository::get('academico.ano_lectivo', function_exists('sige_ano_lectivo_atual') ? sige_ano_lectivo_atual() : wp_date('Y'));
$__cfg_escola_nome  = SIGE_Settings_Repository::get('escola.nome', 'Escola');
?>
<div class="sgcc-wrap sgcc-pro">
<style>
body.sige-view-config_center .sg-product-page-head,
body.sige-view-config .sg-product-page-head{display:none!important;}
body.sige-view-config_center .sg-app-page,
body.sige-view-config .sg-app-page{max-width:none!important;width:100%!important;padding-top:0;}
body.sige-view-config_center .sg-app-content,
body.sige-view-config .sg-app-content{padding-left:30px;padding-right:30px;}
.sgcc-pro{width:100%;max-width:none;margin:0;padding:0 0 28px;font-family:-apple-system,BlinkMacSystemFont,"Inter","Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:var(--color-black);}
.sgcc-pro *{box-sizing:border-box;}
.sgcc-pro svg{width:18px;height:18px;display:block;color:currentColor;stroke:currentColor;}
.sgcc-hero{position:relative;overflow:hidden;border-radius:var(--radius-xl);margin:0 0 var(--space-5);padding:32px 34px;background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-brand-100) 100%);box-shadow:var(--shadow-lg);border:1px solid rgba(92,64,187,.12);display:grid;grid-template-columns:minmax(0,1.35fr) minmax(360px,.65fr);gap:26px;align-items:stretch;min-height:178px;}
.sgcc-hero:before{content:"";position:absolute;right:-120px;top:-140px;width:360px;height:360px;border-radius:var(--radius-pill);background:rgba(108,77,245,.10);}
.sgcc-hero:after{content:"";position:absolute;right:120px;bottom:-120px;width:260px;height:260px;border-radius:var(--radius-pill);background:rgba(97,210,168,.12);}
.sgcc-hero-main,.sgcc-hero-side{position:relative;z-index:1;}
.sgcc-kicker{display:inline-flex;align-items:center;gap:var(--space-2);color:var(--color-brand-500);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.14em;margin-bottom:12px;}
.sgcc-hero h1{font-size:34px;line-height:1.06;margin:0 0 10px;font-weight:700;color:var(--color-black);letter-spacing:-.04em;}
.sgcc-hero p{margin:0;max-width:760px;color:var(--color-slate-700);font-size:15px;line-height:1.7;font-weight:600;}
.sgcc-hero-actions{display:flex;gap:var(--space-3);flex-wrap:wrap;margin-top:22px;}
.sgcc-hero-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;height:48px;padding:0 18px;border-radius:var(--radius-md);text-decoration:none;border:1px solid rgba(91,61,245,.16);font-weight:700;font-size:var(--fs-sm);color:var(--color-black);background:var(--color-white);box-shadow:var(--shadow-sm);cursor:pointer;}
.sgcc-hero-btn.primary{background:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-600));color:var(--color-white);border-color:transparent;box-shadow:0 4px 14px rgba(124,58,237,.25);}
.sgcc-hero-side{display:grid;align-content:stretch;gap:var(--space-3);}
.sgcc-score-card{background:rgba(255,255,255,.84);border:1px solid rgba(105,84,205,.12);border-radius:var(--radius-xl);padding:18px;box-shadow:var(--shadow-md);backdrop-filter:blur(10px);}
.sgcc-score-top{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;}
.sgcc-score-top strong{font-size:var(--fs-sm);color:var(--color-black);font-weight:700;}
.sgcc-score-top span{font-size:12px;color:var(--color-slate-600);font-weight:700;}
.sgcc-score-bar{height:10px;border-radius:var(--radius-pill);background:var(--color-ink-100);overflow:hidden;}
.sgcc-score-bar i{display:block;height:100%;border-radius:var(--radius-pill);background:linear-gradient(90deg,var(--color-brand-500),var(--color-success-400));}
.sgcc-hero-mini{display:grid;grid-template-columns:1fr 1fr;gap:var(--space-3);}
.sgcc-mini{border-radius:var(--radius-lg);background:rgba(255,255,255,.78);border:1px solid rgba(105,84,205,.12);padding:14px;}
.sgcc-mini small{display:block;color:var(--color-slate-500);font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;}
.sgcc-mini strong{display:block;font-size:18px;font-weight:700;color:var(--color-black);line-height:1.1;}
.sgcc-mini span{display:block;font-size:12px;color:var(--color-slate-600);font-weight:400;margin-top:5px;}
.sgcc-overview{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--space-4);margin-bottom:18px;}
.sgcc-overview-card{background:var(--color-white);border:1px solid rgba(30,34,60,.08);border-radius:var(--radius-xl);padding:18px 20px;box-shadow:var(--shadow-md);display:flex;align-items:center;gap:var(--space-4);min-height:112px;}
.sgcc-overview-icon{width:46px;height:46px;border-radius:var(--radius-lg);display:grid;place-items:center;background:var(--color-brand-50);color:var(--color-brand-500);flex:0 0 46px;}
.sgcc-overview-icon.green{background:var(--color-success-100);color:var(--color-success-500);}.sgcc-overview-icon.orange{background:var(--color-warning-100);color:var(--color-warning-600);}.sgcc-overview-icon.blue{background:var(--color-info-50);color:var(--color-info-400);}
.sgcc-overview-card small{display:block;font-size:12px;color:var(--color-slate-500);font-weight:700;margin-bottom:6px;}
.sgcc-overview-card strong{display:block;font-size:22px;color:var(--color-black);font-weight:700;line-height:1;}
.sgcc-overview-card span{display:block;margin-top:6px;font-size:12px;color:var(--color-slate-600);font-weight:400;}
.sgcc-tech-bar{background:var(--color-white);border:1px solid rgba(30,34,60,.08);border-radius:var(--radius-lg);padding:14px 18px;margin:0 0 18px;display:flex;align-items:center;gap:var(--space-3);flex-wrap:wrap;font-size:var(--fs-sm);color:var(--color-slate-700);box-shadow:var(--shadow-md);}
.sgcc-tech-bar b{color:var(--color-black);font-weight:700;}.sgcc-tech-bar.off{background:var(--color-white);}
.sgcc-btn-link{margin-left:auto;background:var(--color-brand-50);border:0;color:var(--color-brand-500);font-weight:700;cursor:pointer;padding:10px 14px;font-size:12px;border-radius:var(--radius-md);}
.sgcc-tabs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:0 0 18px;border:0;padding:0;}
.sgcc-tab{min-height:88px;text-align:left;display:flex;align-items:flex-start;gap:13px;padding:17px 18px;border-radius:var(--radius-xl);background:var(--color-white);color:var(--color-black);text-decoration:none;font-size:var(--fs-sm);font-weight:700;border:1px solid rgba(30,34,60,.08);cursor:pointer;box-shadow:var(--shadow-md);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease,background .18s ease;color:var(--color-black);}
.sgcc-tab:hover{transform:translateY(-2px);box-shadow:var(--shadow-md);border-color:rgba(91,61,245,.22);background:var(--color-white);}
.sgcc-tab.active{background:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-600));color:var(--color-white);border-color:transparent;box-shadow:0 4px 14px rgba(124,58,237,.25);}
.sgcc-tab-icon{width:38px;height:38px;border-radius:var(--radius-md);display:grid;place-items:center;background:var(--color-brand-50);color:var(--color-brand-500);flex:0 0 38px;}.sgcc-tab.active .sgcc-tab-icon{background:rgba(255,255,255,.18);color:var(--color-white);}
.sgcc-tab-text{display:block;min-width:0;}.sgcc-tab strong{display:block;font-size:var(--fs-base);line-height:1.2;margin-bottom:5px;font-weight:700;}.sgcc-tab small{display:block;font-size:11.5px;line-height:1.35;color:var(--color-slate-500);font-weight:400;}.sgcc-tab.active small{color:rgba(255,255,255,.76);}
.sgcc-panel{display:none;}.sgcc-panel.active{display:block;}
.sgcc-form{background:var(--color-white);border-radius:var(--radius-xl);padding:24px 26px;box-shadow:var(--shadow-lg);border:1px solid rgba(30,34,60,.08);}
.sgcc-section-head{display:flex;align-items:center;gap:14px;margin:0 0 var(--space-5);padding-bottom:18px;border-bottom:1px solid var(--color-ink-100);}
.sgcc-section-icon{width:44px;height:44px;border-radius:var(--radius-lg);display:grid;place-items:center;background:var(--color-brand-50);color:var(--color-brand-500);flex:0 0 44px;}
.sgcc-section-head strong{display:block;font-size:19px;font-weight:700;color:var(--color-black);letter-spacing:-.02em;}.sgcc-section-head small{display:block;margin-top:4px;font-size:var(--fs-sm);color:var(--color-slate-500);font-weight:400;}
.sgcc-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--space-4);}
.sgcc-field{display:flex;flex-direction:column;background:var(--color-white);border:1px solid var(--color-ink-100);border-radius:var(--radius-lg);padding:14px;}
.sgcc-field-full{grid-column:1/-1;}.sgcc-field-readonly{background:var(--color-slate-50);}
.sgcc-field label{display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:12px;font-weight:700;color:var(--color-slate-800);margin:0 0 var(--space-2);letter-spacing:.01em;}.sgcc-field label span:first-child{display:inline-block;}
.sgcc-field input[type=text],.sgcc-field input[type=email],.sgcc-field input[type=url],.sgcc-field input[type=number],.sgcc-field input[type=password],.sgcc-field input[type=date],.sgcc-field select,.sgcc-field textarea{width:100%;box-sizing:border-box;padding:12px 13px;border:1px solid var(--color-brand-100);border-radius:var(--radius-md);font-size:13.5px;font-family:inherit;background:var(--color-white);color:var(--color-black);transition:border-color .15s ease,box-shadow .15s ease;min-height:44px;font-weight:600;}
.sgcc-field input:focus,.sgcc-field select:focus,.sgcc-field textarea:focus{outline:0;border-color:var(--color-brand-500);box-shadow:0 4px 14px rgba(124,58,237,.25);}
.sgcc-field input:disabled,.sgcc-field select:disabled,.sgcc-field textarea:disabled{background:var(--color-ink-50);color:var(--color-slate-500);cursor:not-allowed;}
.sgcc-field textarea{resize:vertical;min-height:88px;line-height:1.55;}.sgcc-field small{display:block;color:var(--color-ink-400);margin-top:8px;font-size:12px;line-height:1.45;font-weight:400;}
.sgcc-req{color:var(--color-danger-500);font-weight:700;}.sgcc-pill{display:inline-flex;align-items:center;height:21px;padding:0 var(--space-2);border-radius:var(--radius-pill);font-size:10px;font-weight:700;letter-spacing:.02em;line-height:1;text-transform:lowercase;}.sgcc-pill-info{background:var(--color-warning-100);color:var(--color-warning-800);}.sgcc-pill-hub{background:var(--color-info-50);color:var(--color-info-500);}.sgcc-pill-ro{background:var(--color-slate-100);color:var(--color-slate-500);}
.sgcc-bool{display:inline-flex;align-items:center;gap:var(--space-2);font-size:var(--fs-sm);color:var(--color-slate-800);cursor:pointer;padding:11px 13px;background:var(--color-white);border:1px solid var(--color-brand-100);border-radius:var(--radius-md);align-self:flex-start;font-weight:700;}.sgcc-bool input{width:auto;min-height:auto;}
.sgcc-checks{display:flex;flex-wrap:wrap;gap:var(--space-2);}.sgcc-check{display:inline-flex;align-items:center;gap:var(--space-2);padding:10px 12px;background:var(--color-white);border:1px solid var(--color-brand-100);border-radius:var(--radius-md);cursor:pointer;font-size:var(--fs-sm);color:var(--color-slate-800);font-weight:700;}.sgcc-check input{width:auto;min-height:auto;}.sgcc-check input:checked + span{color:var(--color-brand-500);font-weight:700;}
.sgcc-upload{display:flex;gap:var(--space-2);}.sgcc-upload input{flex:1;}.sgcc-color-row{display:flex;align-items:center;gap:10px;}.sgcc-color-row input[type=color]{width:54px;height:44px;padding:3px;border-radius:var(--radius-md);border:1px solid var(--color-brand-100);cursor:pointer;min-height:44px;}.sgcc-color-row code{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;color:var(--color-slate-700);background:var(--color-white);padding:7px 10px;border-radius:var(--radius-sm);border:1px solid var(--color-brand-100);font-weight:700;}
.sgcc-smtp{background:var(--color-white);border:1px solid var(--color-ink-100);border-radius:var(--radius-xl);padding:var(--space-4);margin-top:2px;}
.sgcc-actions{display:flex;gap:var(--space-3);align-items:center;margin-top:20px;padding-top:18px;border-top:1px solid var(--color-ink-100);}.sgcc-actions-readonly{justify-content:flex-end;}
.sgcc-btn-primary{background:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-600));color:var(--color-white);border:0;border-radius:var(--radius-md);padding:13px 20px;font-size:var(--fs-sm);font-weight:700;cursor:pointer;font-family:inherit;box-shadow:0 4px 14px rgba(124,58,237,.25);}.sgcc-btn-primary:hover{filter:brightness(.98);}.sgcc-btn-primary:disabled{background:var(--color-slate-400);cursor:not-allowed;box-shadow:none;}
.sgcc-btn-light{background:var(--color-white);color:var(--color-brand-500);border:1px solid var(--color-brand-100);border-radius:var(--radius-md);padding:0 14px;font-size:var(--fs-sm);font-weight:700;cursor:pointer;font-family:inherit;min-height:44px;}.sgcc-btn-light:hover{background:var(--color-slate-50);border-color:var(--color-brand-500);}.sgcc-msg{font-size:12.5px;font-weight:700;}.sgcc-msg.ok{color:var(--color-success-500);}.sgcc-msg.err{color:var(--color-danger-600);}

.sgcc-wpp-test-card{background:linear-gradient(135deg,var(--color-white),var(--color-white));border:1px solid rgba(91,61,245,.14);box-shadow:var(--shadow-md);}
.sgcc-wpp-test-head{display:flex;align-items:flex-start;gap:13px;margin:0 0 14px;}
.sgcc-wpp-test-icon{width:42px;height:42px;border-radius:var(--radius-lg);display:grid;place-items:center;background:var(--color-brand-50);color:var(--color-brand-500);flex:0 0 42px;}
.sgcc-wpp-test-head strong{display:block;font-size:15px;font-weight:700;color:var(--color-black);letter-spacing:-.01em;margin-bottom:3px;}
.sgcc-wpp-test-head small{display:block;color:var(--color-slate-500);font-size:12.5px;line-height:1.45;font-weight:400;margin:0;}
.sgcc-wpp-test-last{margin:0 0 14px;padding:11px 13px;border-radius:var(--radius-md);font-size:12px;font-weight:700;border:1px solid transparent;}
.sgcc-wpp-test-last.ok{background:var(--color-success-50);color:var(--color-success-900);border-color:var(--color-success-200);}
.sgcc-wpp-test-last.err{background:var(--color-danger-50);color:var(--color-danger-700);border-color:var(--color-danger-200);}
.sgcc-wpp-test-grid{display:grid;grid-template-columns:minmax(220px,320px) minmax(260px,1fr) auto;gap:var(--space-3);align-items:end;}
.sgcc-wpp-test-grid label{display:flex!important;flex-direction:column!important;align-items:stretch!important;gap:7px!important;margin:0!important;}
.sgcc-wpp-test-grid label span{display:block!important;font-size:12px!important;font-weight:700!important;color:var(--color-slate-800)!important;}
.sgcc-wpp-test-grid textarea{min-height:44px!important;}
.sgcc-wpp-test-btn{min-height:44px;white-space:nowrap;}
.sgcc-wpp-test-result{margin-top:13px;display:none;padding:12px 14px;border-radius:var(--radius-md);font-size:12.5px;line-height:1.55;font-weight:700;}
.sgcc-wpp-test-result.ok{display:block;background:var(--color-success-50);color:var(--color-success-900);border:1px solid var(--color-success-200);}
.sgcc-wpp-test-result.err{display:block;background:var(--color-danger-50);color:var(--color-danger-700);border:1px solid var(--color-danger-200);}
.sgcc-wpp-test-result.warn{display:block;background:var(--color-warning-50);color:var(--color-warning-800);border:1px solid var(--color-warning-200);}
.sgcc-wpp-test-result code{background:rgba(255,255,255,.72);border:1px solid rgba(0,0,0,.06);border-radius:var(--radius-sm);padding:2px 6px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:11.5px;}
.sgcc-wpp-test-result pre{white-space:pre-wrap;word-break:break-word;background:var(--color-white);border:1px solid rgba(0,0,0,.06);padding:10px;border-radius:var(--radius-sm);overflow:auto;max-height:180px;font-size:var(--fs-xs);color:var(--color-slate-800);margin-top:6px;}
.sgcc-smtp-test-card{margin-top:16px;background:linear-gradient(135deg,var(--color-white),var(--color-white));border:1px solid rgba(91,61,245,.14);box-shadow:var(--shadow-md);border-radius:var(--radius-xl);padding:var(--space-4);}
.sgcc-smtp-test-head{display:flex;align-items:flex-start;gap:13px;margin:0 0 14px;}
.sgcc-smtp-test-icon{width:42px;height:42px;border-radius:var(--radius-lg);display:grid;place-items:center;background:var(--color-brand-50);color:var(--color-brand-500);flex:0 0 42px;}
.sgcc-smtp-test-head strong{display:block;font-size:15px;font-weight:700;color:var(--color-black);letter-spacing:-.01em;margin-bottom:3px;}
.sgcc-smtp-test-head small{display:block;color:var(--color-slate-500);font-size:12.5px;line-height:1.45;font-weight:400;margin:0;}
.sgcc-smtp-provider{margin:0 0 14px;padding:13px 14px;border-radius:var(--radius-lg);background:var(--color-white);border:1px dashed rgba(91,61,245,.22);display:flex;gap:var(--space-3);align-items:flex-start;justify-content:space-between;flex-wrap:wrap;}
.sgcc-smtp-provider strong{display:block;font-size:var(--fs-sm);color:var(--color-black);font-weight:700;margin-bottom:3px;}
.sgcc-smtp-provider span{display:block;font-size:12px;line-height:1.45;color:var(--color-slate-600);font-weight:400;}
.sgcc-smtp-presets{display:flex;gap:var(--space-2);flex-wrap:wrap;}
.sgcc-smtp-preset{border:0;background:var(--color-brand-50);color:var(--color-brand-500);font-weight:700;border-radius:var(--radius-md);padding:9px 11px;font-size:12px;cursor:pointer;}
.sgcc-smtp-test-last{margin:0 0 14px;padding:11px 13px;border-radius:var(--radius-md);font-size:12px;font-weight:700;border:1px solid transparent;}
.sgcc-smtp-test-last.ok{background:var(--color-success-50);color:var(--color-success-900);border-color:var(--color-success-200);}
.sgcc-smtp-test-last.err,.sgcc-smtp-test-last.warn{background:var(--color-warning-50);color:var(--color-warning-800);border-color:var(--color-warning-200);}
.sgcc-smtp-test-grid{display:grid;grid-template-columns:minmax(240px,380px) auto;gap:var(--space-3);align-items:end;}
.sgcc-smtp-test-grid label{display:flex!important;flex-direction:column!important;align-items:stretch!important;gap:7px!important;margin:0!important;}
.sgcc-smtp-test-grid label span{display:block!important;font-size:12px!important;font-weight:700!important;color:var(--color-slate-800)!important;}
.sgcc-smtp-test-btn{min-height:44px;white-space:nowrap;}
.sgcc-smtp-test-result{margin-top:13px;display:none;padding:12px 14px;border-radius:var(--radius-md);font-size:12.5px;line-height:1.55;font-weight:700;}
.sgcc-smtp-test-result.ok{display:block;background:var(--color-success-50);color:var(--color-success-900);border:1px solid var(--color-success-200);}
.sgcc-smtp-test-result.err{display:block;background:var(--color-danger-50);color:var(--color-danger-700);border:1px solid var(--color-danger-200);}
.sgcc-smtp-test-result.warn{display:block;background:var(--color-warning-50);color:var(--color-warning-800);border:1px solid var(--color-warning-200);}
.sgcc-smtp-test-result code{background:rgba(255,255,255,.72);border:1px solid rgba(0,0,0,.06);border-radius:var(--radius-sm);padding:2px 6px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:11.5px;}
.sgcc-smtp-deliverability{margin-top:14px;padding:13px 14px;border-radius:var(--radius-lg);background:var(--color-slate-50);border:1px solid var(--color-ink-100);color:var(--color-slate-600);font-size:12.5px;line-height:1.5;font-weight:600;}
.sgcc-smtp-deliverability b{display:block;color:var(--color-black);font-size:12px;text-transform:uppercase;letter-spacing:.08em;margin-bottom:5px;}

@media (min-width:1500px){
    .sgcc-hero{grid-template-columns:minmax(0,1.58fr) minmax(430px,.42fr);}
    .sgcc-tabs{grid-template-columns:repeat(3,minmax(0,1fr));}
    .sgcc-grid{grid-template-columns:repeat(3,minmax(0,1fr));}
    .sgcc-field-full{grid-column:1/-1;}
    .sgcc-smtp .sgcc-grid{grid-template-columns:repeat(3,minmax(0,1fr));}
}
@media (min-width:1500px){
    .sgcc-field:nth-child(1),
    .sgcc-field:nth-child(2){min-height:92px;}
}
@media (max-width:1100px){.sgcc-hero{grid-template-columns:1fr}.sgcc-overview{grid-template-columns:repeat(2,minmax(0,1fr));}.sgcc-tabs{grid-template-columns:repeat(2,minmax(0,1fr));}.sgcc-wpp-test-grid,.sgcc-smtp-test-grid{grid-template-columns:1fr}.sgcc-wpp-test-btn,.sgcc-smtp-test-btn{width:100%;}}
@media (max-width:720px){.sgcc-hero{padding:22px;border-radius:22px}.sgcc-hero h1{font-size:28px}.sgcc-overview,.sgcc-tabs,.sgcc-grid{grid-template-columns:1fr}.sgcc-upload{flex-direction:column}.sgcc-btn-link{margin-left:0}.sgcc-hero-mini{grid-template-columns:1fr}.sgcc-actions{align-items:flex-start;flex-direction:column}.sgcc-btn-primary{width:100%;}}

/* v12.10.31 theme preview */
.sgcc-overview-icon.purple{background:var(--sg-theme-soft,var(--color-brand-50));color:var(--sg-theme-primary,var(--color-brand-500));}
.sgcc-theme-preview{font-family:var(--preview-font,var(--sg-theme-font-family,inherit));display:grid;grid-template-columns:180px 1fr;gap:var(--space-4);margin:0 0 18px;padding:var(--space-4);border:1px solid var(--color-ink-100);border-radius:var(--radius-xl);background:linear-gradient(135deg,var(--color-white),var(--color-white));box-shadow:var(--shadow-md);overflow:hidden;}
.sgcc-theme-preview-side{min-height:210px;border-radius:var(--radius-xl);padding:18px;background:linear-gradient(180deg,var(--preview-primary,var(--color-brand-500)),var(--preview-secondary,var(--color-brand-700)));color:var(--color-white);display:flex;flex-direction:column;gap:10px;}
.sgcc-theme-preview-side span{width:42px;height:42px;border-radius:var(--radius-lg);background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.22);}
.sgcc-theme-preview-side b{font-size:17px;font-weight:700;letter-spacing:-.03em;}.sgcc-theme-preview-side small{opacity:.82;font-weight:700;}
.sgcc-theme-preview-side i{display:block;height:32px;border-radius:var(--radius-md);background:rgba(255,255,255,.15);}.sgcc-theme-preview-side i:nth-of-type(1){margin-top:8px;background:var(--color-white);}.sgcc-theme-preview-main{display:flex;flex-direction:column;gap:14px;min-width:0;}
.sgcc-theme-preview-hero{min-height:124px;border-radius:var(--radius-xl);padding:22px 24px;background:linear-gradient(135deg,var(--color-white) 0%,var(--color-white) 48%,var(--preview-soft,var(--color-brand-50)) 100%);border:1px solid var(--color-ink-100);}
.sgcc-theme-preview-hero span{display:block;color:var(--preview-primary,var(--color-brand-500));font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.12em;margin-bottom:7px;}.sgcc-theme-preview-hero strong{display:block;font-size:27px;line-height:1.05;color:var(--color-ink-500);font-weight:700;letter-spacing:-.05em;}.sgcc-theme-preview-hero small{display:block;margin-top:8px;color:var(--color-slate-600);font-size:var(--fs-sm);line-height:1.45;font-weight:400;}.sgcc-theme-preview-hero button{margin-top:14px;border:0;border-radius:var(--radius-md);min-height:42px;padding:0 17px;background:linear-gradient(135deg,var(--preview-primary,var(--color-brand-500)),var(--preview-secondary,var(--color-brand-700)));color:var(--color-white);font-weight:700;}
.sgcc-theme-preview-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:var(--space-3);}.sgcc-theme-preview-cards span{height:76px;border-radius:var(--radius-lg);background:var(--color-white);border:1px solid var(--color-ink-100);box-shadow:var(--shadow-sm);position:relative;overflow:hidden;}.sgcc-theme-preview-cards span:before{content:"";position:absolute;left:14px;top:16px;width:36px;height:36px;border-radius:var(--radius-md);background:var(--preview-soft,var(--color-brand-50));}.sgcc-theme-preview-cards span:after{content:"";position:absolute;right:-18px;top:-22px;width:76px;height:76px;border-radius:var(--radius-pill);background:rgba(0,0,0,.04);}.sgcc-theme-preview-cards span:nth-child(1):before{background:var(--preview-soft,var(--color-brand-50));}.sgcc-theme-preview-cards span:nth-child(2):before{background:rgba(52,168,83,.14);}.sgcc-theme-preview-cards span:nth-child(3):before{background:color-mix(in srgb,var(--preview-accent,var(--color-success-700)) 18%,var(--color-white));}
.sgcc-theme-tools{display:flex;align-items:center;gap:var(--space-3);margin:-4px 0 18px;color:var(--color-slate-500);font-size:12.5px;font-weight:600;}.sgcc-theme-tools .sgcc-btn-light{min-height:40px;}.sgcc-theme-note{margin:0 0 18px;padding:14px 16px;border:1px solid rgba(91,61,245,.12);border-radius:var(--radius-lg);background:linear-gradient(135deg,var(--color-white),var(--color-white));display:flex;gap:10px;align-items:flex-start;}.sgcc-theme-note strong{display:block;color:var(--sg-theme-primary,var(--color-brand-500));font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;min-width:148px;}.sgcc-theme-note span{display:block;color:var(--color-slate-600);font-size:var(--fs-sm);font-weight:400;line-height:1.45;}
@media (max-width:900px){.sgcc-theme-preview{grid-template-columns:1fr}.sgcc-theme-preview-side{min-height:auto}.sgcc-theme-preview-cards{grid-template-columns:1fr}.sgcc-theme-tools{align-items:flex-start;flex-direction:column}}

</style>

<section class="sgcc-hero" aria-label="Centro de Configuração SoftGenial">
    <div class="sgcc-hero-main">
        <div class="sgcc-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('settings') : ''; ?> Configuração da escola</div>
        <h1>Centro de Configuração</h1>
        <p>Organize a identidade, documentos, ano lectivo, contactos e preferências da <?php echo esc_html($__cfg_escola_nome ?: 'escola'); ?> num único lugar, com uma experiência limpa e pronta para uso diário.</p>
        <div class="sgcc-hero-actions">
            <button type="button" class="sgcc-hero-btn primary" data-sgcc-target="identidade"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('school') : ''; ?> Dados da escola</button>
            <button type="button" class="sgcc-hero-btn" data-sgcc-target="documentos"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('file') : ''; ?> Marca e documentos</button>
            <button type="button" class="sgcc-hero-btn" data-sgcc-target="aparencia"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('palette') : ''; ?> Aparência</button>
            <button type="button" class="sgcc-hero-btn" data-sgcc-target="academico"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('calendar') : ''; ?> Ano lectivo</button>
        </div>
    </div>
    <aside class="sgcc-hero-side">
        <div class="sgcc-score-card">
            <div class="sgcc-score-top"><strong>Dados principais</strong><span><?php echo (int)$__cfg_p_identidade['pct']; ?>%</span></div>
            <div class="sgcc-score-bar"><i style="width:<?php echo (int)$__cfg_p_identidade['pct']; ?>%"></i></div>
        </div>
        <div class="sgcc-hero-mini">
            <div class="sgcc-mini"><small>Ano lectivo</small><strong><?php echo esc_html((string)$__cfg_ano); ?></strong><span>Activo no sistema</span></div>
            <div class="sgcc-mini"><small>Documentos</small><strong><?php echo (int)$__cfg_p_documentos['pct']; ?>%</strong><span>Marca configurada</span></div>
        </div>
    </aside>
</section>

<div class="sgcc-overview" aria-label="Resumo da configuração">
    <div class="sgcc-overview-card"><span class="sgcc-overview-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('school') : ''; ?></span><span><small>Perfil da escola</small><strong><?php echo (int)$__cfg_p_identidade['pct']; ?>%</strong><span><?php echo (int)$__cfg_p_identidade['filled']; ?> de <?php echo (int)$__cfg_p_identidade['total']; ?> campos preenchidos</span></span></div>
    <div class="sgcc-overview-card"><span class="sgcc-overview-icon green"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('pin') : ''; ?></span><span><small>Contactos</small><strong><?php echo (int)$__cfg_p_local['pct']; ?>%</strong><span>Dados úteis para documentos</span></span></div>
    <div class="sgcc-overview-card"><span class="sgcc-overview-icon orange"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('file') : ''; ?></span><span><small>Documentos</small><strong><?php echo (int)$__cfg_p_documentos['pct']; ?>%</strong><span>Logótipos e cabeçalho</span></span></div>
    <div class="sgcc-overview-card"><span class="sgcc-overview-icon purple"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('palette') : ''; ?></span><span><small>Aparência</small><strong><?php echo esc_html((function_exists('sige_theme_current_palette') && (sige_theme_current_palette()['mode'] ?? 'softgenial') === 'escola') ? 'Personalizada' : 'Padrão'); ?></strong><span>Cores e fonte da aplicação</span></span></div>
    <div class="sgcc-overview-card"><span class="sgcc-overview-icon blue"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('shield') : ''; ?></span><span><small>Modo avançado</small><strong><?php echo $__cfg_tech_active ? 'Activo' : 'Fechado'; ?></strong><span><?php echo $__cfg_tech_active ? 'Suporte habilitado' : 'Experiência protegida'; ?></span></span></div>
</div>

<?php if ($__cfg_can_tech): ?>
    <?php if ($__cfg_tech_active): ?>
        <div class="sgcc-tech-bar">
            <b>Opções avançadas activas</b> · disponíveis por mais <?php echo esc_html($__cfg_tech_remaining); ?>.
            <button type="button" class="sgcc-btn-link" id="sgcc-exit-tech">Fechar opções avançadas</button>
        </div>
    <?php else: ?>
        <div class="sgcc-tech-bar off">
            <b>Experiência protegida</b> · a escola vê apenas as opções seguras do uso diário. As opções avançadas ficam reservadas ao suporte SoftGenial.
            <button type="button" class="sgcc-btn-link" id="sgcc-enter-tech">Abrir opções avançadas</button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php SIGE_Settings_View_Renderer::render_all(); ?>

<script <?php echo sige_csp_script_attr(); ?>>
(function ($) {
    if (typeof $ === 'undefined') return;

    function activateTab(id) {
        if (!id) return;
        $('.sgcc-tab').removeClass('active').attr('aria-selected', 'false');
        var $tab = $('.sgcc-tab[data-tab="' + id + '"]');
        $tab.addClass('active').attr('aria-selected', 'true');
        $('.sgcc-panel').removeClass('active');
        $('#sgcc-panel-' + id).addClass('active');
        if ($tab.length) {
            $('html, body').animate({ scrollTop: Math.max(0, $('.sgcc-tabs').offset().top - 24) }, 220);
        }
    }

    $(document).on('click', '.sgcc-tab', function () {
        activateTab($(this).data('tab'));
    });

    $(document).on('click', '[data-sgcc-target]', function () {
        activateTab($(this).data('sgcc-target'));
    });

    $(document).on('click', '.sgcc-upload-btn', function () {
        var target = $(this).data('target');
        if (typeof wp === 'undefined' || !wp.media) return;
        var frame = wp.media({ title: 'Seleccionar ficheiro', button: { text: 'Usar' }, multiple: false });
        frame.on('select', function () {
            var a = frame.state().get('selection').first().toJSON();
            $('#' + target).val(a.url);
        });
        frame.open();
    });

    $(document).on('change', '.sgcc-csv-check', function () {
        var $box = $(this).closest('.sgcc-checks');
        var key = $box.data('csv-for');
        var vals = [];
        $box.find('.sgcc-csv-check:checked').each(function () { vals.push($(this).val()); });
        $('input[data-csv-target="' + key + '"]').val(vals.join(', '));
    });

    $(document).on('input change', '.sgcc-color-row input[type=color]', function () {
        $(this).next('code').text($(this).val());
    });

    function showMsg($form, ok, msg) {
        var $m = $form.find('.sgcc-msg');
        $m.removeClass('ok err').addClass(ok ? 'ok' : 'err').text(msg || '');
        if (ok && msg) setTimeout(function () { $m.text(''); }, 4000);
    }

    function postAction(action, data, $form, reloadAfter) {
        var $btn = $form.find('[type=submit], .sgcc-btn-primary').first();
        var oldText = $btn.length ? $btn.text() : '';
        if ($btn.length) $btn.prop('disabled', true).text('A guardar…');
        showMsg($form, true, '');

        $.post(ajaxurl, data + '&action=' + encodeURIComponent(action))
            .done(function (res) {
                if (res && res.success) {
                    showMsg($form, true, (res.data && res.data.message) ? res.data.message : 'Guardado com sucesso.');
                    if (reloadAfter) setTimeout(function () { location.reload(); }, 550);
                } else {
                    showMsg($form, false, (res && res.data && res.data.message) ? res.data.message : 'Não foi possível guardar.');
                }
            })
            .fail(function () { showMsg($form, false, 'Erro de comunicação.'); })
            .always(function () { if ($btn.length) $btn.prop('disabled', false).text(oldText); });
    }

    $(document).on('submit', '.sgcc-form', function (e) {
        e.preventDefault();
        var $f = $(this);
        postAction($f.data('action'), $f.serialize(), $f, $f.closest('#sgcc-panel-aparencia').length > 0);
    });

    $('#sgcc-enter-tech, #sgcc-exit-tech').on('click', function () {
        var action = this.id === 'sgcc-enter-tech' ? 'sige_settings_enter_technical_mode' : 'sige_settings_exit_technical_mode';
        var $fake = $('<form><span class="sgcc-msg"></span><button type="submit" class="sgk-btn sgk-btn-sec"></button></form>');
        postAction(action, 'nonce=<?php echo esc_js($__cfg_tech_nonce); ?>', $fake, true);
    });

    function sgccEsc(v) {
        return String(v === undefined || v === null ? '' : v).replace(/[&<>"]/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
        });
    }

    function sgccWppShow($box, html, type) {
        $box.removeClass('ok err warn').addClass(type || 'warn').html(html || '');
    }

    function sgccSmtpShow($box, html, type) {
        $box.removeClass('ok err warn').addClass(type || 'warn').html(html || '');
    }

    function sgccSmtpField($form, field) {
        return $form.find('[name="settings[comunicacao.smtp_config][' + field + ']"]');
    }

    $(document).on('click', '.sgcc-smtp-preset', function () {
        var preset = $(this).data('sgcc-smtp-preset') || 'zoho_ssl';
        var $form = $(this).closest('form');
        sgccSmtpField($form, 'host').val('smtp.zoho.com');
        sgccSmtpField($form, 'auth').val('1');
        sgccSmtpField($form, 'enabled').val('1');
        if (preset === 'zoho_tls') {
            sgccSmtpField($form, 'port').val('587');
            sgccSmtpField($form, 'encryption').val('tls');
        } else {
            sgccSmtpField($form, 'port').val('465');
            sgccSmtpField($form, 'encryption').val('ssl');
        }
        var $result = $(this).closest('[data-sgcc-smtp-test]').find('.sgcc-smtp-test-result');
        sgccSmtpShow($result, 'Configuração Zoho aplicada no formulário. Reintroduza/valide a palavra-passe e clique em <strong>Enviar teste agora</strong>.', 'warn');
    });

    $(document).on('click', '.sgcc-smtp-test-btn', function () {
        var $btn = $(this);
        var $card = $btn.closest('[data-sgcc-smtp-test]');
        var $form = $btn.closest('form');
        var $result = $card.find('.sgcc-smtp-test-result');
        var testTo = $.trim($card.find('.sgcc-smtp-test-to').val() || '');
        var nonce = $card.data('nonce') || '';

        if (!testTo) {
            sgccSmtpShow($result, 'Informe o e-mail que deve receber o teste.', 'err');
            $card.find('.sgcc-smtp-test-to').trigger('focus');
            return;
        }

        var smtp = {
            enabled: sgccSmtpField($form, 'enabled').val() || '0',
            host: sgccSmtpField($form, 'host').val() || '',
            port: sgccSmtpField($form, 'port').val() || '',
            encryption: sgccSmtpField($form, 'encryption').val() || 'ssl',
            auth: sgccSmtpField($form, 'auth').val() || '1',
            username: sgccSmtpField($form, 'username').val() || '',
            password: sgccSmtpField($form, 'password').val() || '',
            from_email: sgccSmtpField($form, 'from_email').val() || '',
            from_name: sgccSmtpField($form, 'from_name').val() || '',
            reply_to: sgccSmtpField($form, 'reply_to').val() || ''
        };

        var original = $btn.text();
        $btn.prop('disabled', true).text('A testar SMTP…');
        sgccSmtpShow($result, 'A enviar e-mail de teste pela configuração SMTP/Zoho indicada…', 'warn');

        $.post(ajaxurl, {
            action: 'sige_testar_smtp_config',
            nonce: nonce,
            test_to: testTo,
            smtp: smtp
        }).done(function (res) {
            var data = (res && res.data) ? res.data : {};
            var diag = data.diagnostics || {};
            if (res && res.success) {
                var lines = [];
                lines.push('✅ <strong>' + sgccEsc(data.message || 'Email de teste enviado.') + '</strong>');
                if (diag.host) lines.push('Servidor: <code>' + sgccEsc(diag.host) + ':' + sgccEsc(diag.port || '') + '</code> / <strong>' + sgccEsc(String(diag.encryption || '').toUpperCase()) + '</strong> · tempo: <strong>' + sgccEsc(diag.elapsed_ms || 0) + 'ms</strong>');
                if (diag.from_email) lines.push('Remetente usado: <code>' + sgccEsc(diag.from_email) + '</code>');
                lines.push('<span style="font-weight:600;font-size:12px;">Confirme agora a caixa de entrada e a pasta SPAM do e-mail de teste.</span>');
                sgccSmtpShow($result, lines.join('<br>'), 'ok');
            } else {
                var msg = data.message || data.error || 'Falha no teste SMTP.';
                var detail = '';
                if (diag.host) detail += '<br>Servidor: <code>' + sgccEsc(diag.host) + ':' + sgccEsc(diag.port || '') + '</code> / <strong>' + sgccEsc(String(diag.encryption || '').toUpperCase()) + '</strong>';
                if (diag.username) detail += '<br>Utilizador: <code>' + sgccEsc(diag.username) + '</code>';
                sgccSmtpShow($result, '❌ ' + sgccEsc(msg) + detail, 'err');
            }
        }).fail(function (xhr) {
            var msg = 'Erro de comunicação no teste SMTP.';
            var data = xhr && xhr.responseJSON ? xhr.responseJSON.data : null;
            if (data && data.message) msg = data.message;
            sgccSmtpShow($result, '❌ ' + sgccEsc(msg), 'err');
        }).always(function () {
            $btn.prop('disabled', false).text(original);
        });
    });

    $(document).on('click', '.sgcc-wpp-test-btn', function () {
        var $btn = $(this);
        var $card = $btn.closest('[data-sgcc-whatsapp-test]');
        var $number = $card.find('.sgcc-wpp-test-number');
        var $message = $card.find('.sgcc-wpp-test-message');
        var $result = $card.find('.sgcc-wpp-test-result');
        var numero = $.trim($number.val() || '');
        var mensagem = $.trim($message.val() || '');
        var nonce = $card.data('nonce') || '';

        if (!numero) {
            sgccWppShow($result, 'Informe o número de teste com indicativo do país. Ex.: <strong>25884xxxxxxx</strong>.', 'err');
            $number.trigger('focus');
            return;
        }

        var original = $btn.text();
        $btn.prop('disabled', true).text('A enviar teste…');
        sgccWppShow($result, 'A enviar mensagem de teste com a configuração WhatsApp guardada…', 'warn');

        $.post(ajaxurl, {
            action: 'sige_wpp_diag_send_test',
            nonce: nonce,
            numero: numero,
            mensagem: mensagem
        }).done(function (res) {
            var data = (res && res.data) ? res.data : {};
            if (res && res.success) {
                var lines = [];
                lines.push('✅ <strong>Teste enviado para ' + sgccEsc(data.phone_mask || numero) + '.</strong>');
                lines.push('HTTP: <strong>' + sgccEsc(data.http || '?') + '</strong> · tempo: <strong>' + sgccEsc(data.elapsed_ms || 0) + 'ms</strong>');
                if (data.message_id) lines.push('ID da mensagem: <code>' + sgccEsc(data.message_id) + '</code>');
                if (data.body_preview) lines.push('<details><summary style="cursor:pointer;margin-top:8px;">Ver resposta da API</summary><pre>' + sgccEsc(data.body_preview) + '</pre></details>');
                lines.push('<span style="font-weight:600;font-size:12px;">Confirme agora no telefone se a mensagem foi recebida.</span>');
                sgccWppShow($result, lines.join('<br>'), 'ok');
            } else {
                var msg = data.message || data.error || 'Falha no teste.';
                var detail = '';
                if (data.http) detail += '<br>HTTP: <strong>' + sgccEsc(data.http) + '</strong>';
                if (data.body_preview) detail += '<details><summary style="cursor:pointer;margin-top:8px;">Ver resposta da API</summary><pre>' + sgccEsc(data.body_preview) + '</pre></details>';
                sgccWppShow($result, '❌ ' + sgccEsc(msg) + detail, 'err');
            }
        }).fail(function (xhr) {
            var msg = 'Erro de comunicação.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) msg = xhr.responseJSON.data.message;
            sgccWppShow($result, '❌ ' + sgccEsc(msg), 'err');
        }).always(function () {
            $btn.prop('disabled', false).text(original);
        });
    });

    var requestedTab = (new URLSearchParams(window.location.search)).get('sgcc_tab') || (window.location.hash ? window.location.hash.replace('#','') : '');
    if (requestedTab && $('.sgcc-tab[data-tab="' + requestedTab + '"]').length) {
        activateTab(requestedTab);
    }

    function sgccThemeUpdatePreview(){
        var $panel = $('#sgcc-panel-aparencia');
        if (!$panel.length) return;
        var $mode = $panel.find('select[name="settings[aparencia.tema]"]');
        var mode = $mode.val() || 'softgenial';
        var p = $panel.find('input[name="settings[aparencia.primaria]"]').val() || '#5a3fd6';
        var s2 = $panel.find('input[name="settings[aparencia.secundaria]"]').val() || '#3f2c9f';
        var a = $panel.find('input[name="settings[aparencia.destaque]"]').val() || '#34a853';
        var fontKey = $panel.find('select[name="settings[aparencia.fonte]"]').val() || 'softgenial';
        var fonts = {
            softgenial: "'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif",
            inter: "'Inter','Plus Jakarta Sans','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif",
            system: "system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif",
            segoe: "'Segoe UI',Roboto,Arial,sans-serif",
            arial: "Arial,Helvetica,sans-serif",
            verdana: "Verdana,Geneva,sans-serif",
            serif: "Georgia,'Times New Roman',serif"
        };
        var custom = (p.toLowerCase() !== '#5a3fd6' || s2.toLowerCase() !== '#3f2c9f' || a.toLowerCase() !== '#34a853');
        if (custom && mode !== 'escola') { $mode.val('escola'); mode = 'escola'; }
        if (mode !== 'escola') { p = '#5a3fd6'; s2 = '#3f2c9f'; a = '#34a853'; }
        $('.sgcc-theme-preview').css({'--preview-primary':p,'--preview-secondary':s2,'--preview-accent':a,'--preview-soft':p + '22','--preview-font':fonts[fontKey] || fonts.softgenial}).toggleClass('is-school-theme', mode === 'escola');
    }
    $(document).on('input change', '#sgcc-panel-aparencia input[type=color]', function () {
        var $panel = $('#sgcc-panel-aparencia');
        $panel.find('select[name="settings[aparencia.tema]"]').val('escola');
        sgccThemeUpdatePreview();
    });
    $(document).on('change', '#sgcc-panel-aparencia select[name="settings[aparencia.tema]"], #sgcc-panel-aparencia select[name="settings[aparencia.fonte]"]', sgccThemeUpdatePreview);
    $(document).on('click', '#sgcc-theme-reset', function(){
        var $panel = $('#sgcc-panel-aparencia');
        $panel.find('select[name="settings[aparencia.tema]"]').val('softgenial');
        $panel.find('input[name="settings[aparencia.primaria]"]').val('#5a3fd6').trigger('input');
        $panel.find('input[name="settings[aparencia.secundaria]"]').val('#3f2c9f').trigger('input');
        $panel.find('input[name="settings[aparencia.destaque]"]').val('#34a853').trigger('input');
        $panel.find('select[name="settings[aparencia.fonte]"]').val('softgenial');
        $panel.find('select[name="settings[aparencia.tema]"]').val('softgenial');
        sgccThemeUpdatePreview();
    });
    sgccThemeUpdatePreview();

})(jQuery);
</script>
</div>
