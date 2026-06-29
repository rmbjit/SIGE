<?php
/**
 * SIGE SoftGenial - Portaria Digital Leitor de Crachás
 * v12.11.9.80
 *
 * Ecrã dedicado para leitura de crachás com resultado no próprio
 * espaço da câmara em mobile/tablet, evitando scroll após confirmação.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_portaria_camera_safe_is_request')) {
    function sige_portaria_camera_safe_is_request(): bool {
        return isset($_GET['sige_portaria_camera']) && (string) wp_unslash($_GET['sige_portaria_camera']) === '1';
    }
}

if (!function_exists('sige_portaria_camera_safe_url')) {
    function sige_portaria_camera_safe_url(): string {
        return add_query_arg([
            'sige_portaria_camera' => '1',
            'sgv' => defined('SIGE_VERSION') ? SIGE_VERSION : time(),
        ], home_url('/'));
    }
}

if (!function_exists('sige_portaria_camera_safe_headers')) {
    function sige_portaria_camera_safe_headers(): void {
        if (!sige_portaria_camera_safe_is_request() || headers_sent()) return;
        nocache_headers();
        header('Permissions-Policy: camera=(self), microphone=(), geolocation=(), payment=()', true);
        header('Content-Security-Policy: ' . (function_exists('sige_csp_zero_inline_policy') ? sige_csp_zero_inline_policy() : "default-src 'self'; object-src 'none';"), true);
        header("Feature-Policy: camera 'self'; microphone 'none'; geolocation 'none'; payment 'none'", true);
        header('X-Robots-Tag: noindex, nofollow', true);
    }
}
add_action('send_headers', 'sige_portaria_camera_safe_headers', 1000);

if (!function_exists('sige_portaria_camera_safe_wp_headers')) {
    function sige_portaria_camera_safe_wp_headers(array $headers): array {
        if (sige_portaria_camera_safe_is_request()) {
            $headers['Permissions-Policy'] = 'camera=(self), microphone=(), geolocation=(), payment=()';
            $headers['Feature-Policy'] = "camera 'self'; microphone 'none'; geolocation 'none'; payment 'none'";
            $headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
            $headers['Pragma'] = 'no-cache';
            $headers['X-Robots-Tag'] = 'noindex, nofollow';
        }
        return $headers;
    }
}
add_filter('wp_headers', 'sige_portaria_camera_safe_wp_headers', 1000, 1);

if (!function_exists('sige_portaria_camera_safe_allowed')) {
    function sige_portaria_camera_safe_allowed(): bool {
        if (!is_user_logged_in()) return false;
        if (function_exists('sige_page_guard_allows')) {
            return (bool) sige_page_guard_allows(
                ['portaria.ver', 'portaria.validar_acesso'],
                ['sige_director', 'sige_secretario', 'sige_secretaria_geral', 'sige_assistente', 'sige_recepcao', 'sige_guarda']
            );
        }
        return current_user_can('manage_options') || current_user_can('sige_guarda') || current_user_can('sige_recepcao') || current_user_can('sige_secretario');
    }
}

if (!function_exists('sige_portaria_camera_safe_render')) {
    function sige_portaria_camera_safe_render(): void {
        if (!sige_portaria_camera_safe_is_request()) return;
        sige_portaria_camera_safe_headers();

        if (!is_user_logged_in()) {
            auth_redirect();
            exit;
        }
        if (!sige_portaria_camera_safe_allowed()) {
            status_header(403);
            wp_die('Sem permissão para usar a Portaria Digital.', 'Acesso restrito', ['response' => 403]);
        }

        $back_url = add_query_arg(['page' => 'sige-app', 'view' => 'portaria'], admin_url('admin.php'));
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('sige_portaria_acesso');
        $avatar = SIGE_URL . 'assets/img/avatar-default.svg';
        $ok_audio = SIGE_URL . 'assets/audio/success.ogg';
        $err_audio = SIGE_URL . 'assets/audio/error.ogg';
        $html5qrcode = function_exists('sige_cdn_script') ? sige_cdn_script('html5qrcode') : '<script src="' . esc_url(SIGE_URL . 'assets/vendor/html5qrcode/html5-qrcode.min.js') . '"></script>';
        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>Portaria Digital - Leitor de Crachás</title>
<?php echo $html5qrcode; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<style <?php echo function_exists('sige_csp_style_attr') ? sige_csp_style_attr() : ''; ?>>
:root{
  --p:#5a3fd6;--p2:#6d5dfc;--ink:#16172f;--muted:#737b91;--line:#e8ebf4;
  --ok:#16a34a;--ok2:#22c55e;--red:#ef4444;--red2:#be123c;--blue:#2563eb;--amber:#f59e0b;
}
*{box-sizing:border-box;min-width:0}
html{min-height:100%;background:#f4f6fb}
body{margin:0;min-height:100vh;background:radial-gradient(circle at top right,#eee9ff,#f8f7fc 40%,#f4f6fb);font-family:Poppins,Inter,Segoe UI,system-ui,sans-serif;color:var(--ink);padding:clamp(12px,3vw,26px);padding-bottom:calc(24px + env(safe-area-inset-bottom));overflow-x:hidden}
.wrap{width:min(1120px,100%);margin:auto;display:grid;gap:16px}
.hero{border-radius:26px;background:linear-gradient(135deg,#fff,#f1edff);border:1px solid rgba(90,63,214,.12);box-shadow:0 24px 70px rgba(44,37,89,.12);padding:22px;display:flex;align-items:center;justify-content:space-between;gap:14px}
.kicker{margin:0 0 6px;color:var(--p);font-size:12px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}
.hero h1{margin:0;font-size:clamp(25px,4vw,38px);line-height:1.05;font-weight:950;letter-spacing:-.05em;overflow-wrap:anywhere}
.hero p{margin:9px 0 0;color:#697085;font-size:14px;line-height:1.55;font-weight:650;max-width:780px}
.back{min-height:44px;border-radius:999px;background:#fff;color:var(--p);border:1px solid #e8e4ff;padding:0 16px;text-decoration:none;font-size:13px;font-weight:900;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 12px 24px rgba(72,57,143,.08);white-space:nowrap}
.grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(330px,.95fr);gap:16px;align-items:start}
.card{border-radius:24px;background:#fff;border:1px solid rgba(28,32,54,.08);box-shadow:0 18px 45px rgba(34,34,64,.075);overflow:hidden}
.head{padding:18px 20px 10px;display:flex;justify-content:space-between;gap:12px}
.head h2{margin:0;font-size:18px;font-weight:950;overflow-wrap:anywhere}
.head p{margin:5px 0 0;color:#81889b;font-size:12px;font-weight:700;line-height:1.45}
.badge{border-radius:999px;background:#f1edff;color:var(--p);padding:8px 10px;font-size:11px;font-weight:900;white-space:nowrap;align-self:start}
.body{padding:10px 20px 20px}
.frame{position:relative;overflow:hidden;min-height:430px;border-radius:22px;background:linear-gradient(135deg,#11142f,#302975 62%,#5a3fd6);padding:12px;box-shadow:0 18px 42px rgba(45,37,100,.2);isolation:isolate}
.frame.running:after{content:"";position:absolute;left:32px;right:32px;top:18%;height:3px;border-radius:999px;background:linear-gradient(90deg,transparent,rgba(255,255,255,.85),transparent);box-shadow:0 0 26px rgba(255,255,255,.45);animation:scan 2.4s ease-in-out infinite;z-index:3;pointer-events:none}
@keyframes scan{0%,100%{top:18%}50%{top:82%}}
#reader{position:relative;z-index:2;min-height:404px;border-radius:18px;background:#0f1230;display:flex;align-items:center;justify-content:center;color:#fff;text-align:center;overflow:hidden;transition:filter .25s ease,transform .25s ease,opacity .25s ease}
#reader video{width:100%!important;height:100%!important;min-height:404px;object-fit:cover;border-radius:18px}
.frame.has-result #reader{filter:blur(2px) saturate(.8);opacity:.34;transform:scale(1.015)}
.placeholder{padding:26px;max-width:420px}
.placeholder strong{display:block;font-size:20px;font-weight:950}
.placeholder span{display:block;margin-top:10px;color:rgba(255,255,255,.76);font-size:13px;line-height:1.55;font-weight:650}
.scan-result-overlay{position:absolute;inset:12px;z-index:6;border-radius:19px;background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(248,249,255,.98));border:1px solid rgba(255,255,255,.65);box-shadow:0 20px 60px rgba(13,18,45,.28);display:none;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:18px;gap:10px;color:var(--ink);overflow:auto;overscroll-behavior:contain}
.frame.has-result .scan-result-overlay{display:flex}
.scan-result-overlay.success{background:radial-gradient(circle at 50% 0%,#dcfce7,#f7fff9 52%,#ffffff);border-color:rgba(22,163,74,.25)}
.scan-result-overlay.error{background:radial-gradient(circle at 50% 0%,#ffe4e6,#fff8f8 54%,#ffffff);border-color:rgba(239,68,68,.28)}
.scan-result-overlay.reading{background:radial-gradient(circle at 50% 0%,#e0e7ff,#f9faff 54%,#ffffff);border-color:rgba(37,99,235,.18)}
.overlay-top{position:absolute;top:12px;left:14px;right:14px;display:flex;align-items:center;justify-content:space-between;gap:8px}
.overlay-chip{border-radius:999px;background:#f1edff;color:var(--p);padding:7px 10px;font-size:10px;font-weight:950;text-transform:uppercase;letter-spacing:.08em;max-width:58%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.scan-result-overlay.success .overlay-chip{background:#dcfce7;color:#166534}.scan-result-overlay.error .overlay-chip{background:#fee2e2;color:#991b1b}
.overlay-time{color:#8a91a3;font-size:11px;font-weight:850;white-space:nowrap}
.overlay-ring{width:116px;height:116px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#fff;border:1px solid #edf0f7;box-shadow:0 18px 45px rgba(34,34,64,.1);margin-top:18px;flex:0 0 auto}
.overlay-ring img{width:98px;height:98px;border-radius:50%;object-fit:cover;border:4px solid #fff;background:#eaf0f7}
.overlay-status{margin:0;color:#17172f;font-size:clamp(20px,5vw,30px);line-height:1.08;font-weight:950;text-transform:uppercase;overflow-wrap:anywhere;max-width:100%}
.overlay-name{margin:0;color:#17172f;font-size:clamp(18px,4.2vw,25px);font-weight:950;line-height:1.18;overflow-wrap:anywhere;max-width:100%}
.overlay-turma{border-radius:999px;background:#f7f8fc;border:1px solid #edf0f7;color:#4b5264;padding:8px 14px;font-size:13px;font-weight:850;max-width:100%;overflow-wrap:anywhere}
.overlay-obs{display:none;border-radius:15px;background:#fff1f2;border:1px solid #ffe0e4;color:#b4232f;padding:11px 13px;font-size:12px;line-height:1.45;font-weight:850;overflow-wrap:anywhere;max-width:100%}
.scan-result-overlay.success .overlay-obs{background:#f0fdf4;border-color:#bbf7d0;color:#166534}
.overlay-actions{width:100%;display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:4px;max-width:460px}
.overlay-manual{width:min(460px,100%);display:grid;grid-template-columns:1fr auto;gap:8px;margin-top:2px}.overlay-manual[hidden]{display:none!important}
.tools{display:grid;grid-template-columns:1fr auto auto;gap:10px;margin-top:12px}
.select,.input{min-height:48px;border-radius:16px;border:1px solid var(--line);background:#fff;color:var(--ink);padding:0 12px;font:700 14px/1.2 inherit;outline:none}.select:focus,.input:focus{border-color:#b8afff;box-shadow:0 0 0 4px rgba(90,63,214,.12)}
.btn{min-height:48px;border:0;border-radius:16px;display:inline-flex;align-items:center;justify-content:center;padding:0 16px;background:linear-gradient(135deg,var(--p2),var(--p));color:#fff;font:900 13px/1 inherit;cursor:pointer;box-shadow:0 14px 26px rgba(90,63,214,.24);white-space:nowrap;text-decoration:none;touch-action:manipulation}.btn.secondary{background:#fff;color:#30364e;border:1px solid var(--line);box-shadow:0 10px 20px rgba(33,36,56,.06)}.btn.danger{background:#fff1f2;color:#be123c;border:1px solid #ffe4e6;box-shadow:none}.btn:disabled{opacity:.62;cursor:not-allowed}.btn:focus-visible{outline:3px solid rgba(90,63,214,.25);outline-offset:2px}
.file{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}
.qr-file-reader{position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden}
.manual-card{margin-top:16px}
.status{margin-top:12px;border-radius:17px;border:1px solid var(--line);background:#f8f9fd;padding:13px 14px;display:flex;gap:10px;align-items:flex-start}.dot{width:10px;height:10px;border-radius:99px;background:var(--dot,#8b93a7);box-shadow:0 0 0 5px var(--soft,rgba(139,147,167,.14));margin-top:5px;flex:0 0 auto}.status.ok{--dot:var(--ok);--soft:rgba(22,163,74,.14)}.status.err{--dot:var(--red);--soft:rgba(239,68,68,.14)}.status.wait{--dot:var(--blue);--soft:rgba(37,99,235,.14)}.status strong{display:block;font-size:13px;font-weight:950}.status span{display:block;margin-top:3px;color:#71798f;font-size:12px;line-height:1.45;font-weight:700}
.diag{margin-top:10px;border-radius:15px;border:1px dashed #d9deeb;background:#fff;padding:11px 12px;color:#5e6578;font-size:11px;line-height:1.55;font-weight:750;overflow-wrap:anywhere}.diag code{border-radius:7px;background:#f6f7fb;border:1px solid #edf0f7;padding:2px 5px;font:700 10px/1.2 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:#3f4658}.diag[hidden]{display:none!important}
.result{position:relative;min-height:430px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;border-radius:24px;background:linear-gradient(180deg,#fff,#fbfbff);border:1px solid rgba(28,32,54,.08);padding:24px;overflow:hidden}.result.success{background:#f4fbf6;border-color:rgba(22,163,74,.25)}.result.error{background:#fff7f8;border-color:rgba(239,68,68,.25)}.topline{position:absolute;top:16px;left:18px;right:18px;display:flex;align-items:center;justify-content:space-between;gap:10px}.chip{border-radius:999px;background:#f1edff;color:var(--p);padding:7px 10px;font-size:11px;font-weight:950;text-transform:uppercase;letter-spacing:.07em}.time{color:#8a91a3;font-size:11px;font-weight:850}.ring{width:148px;height:148px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#fff;border:1px solid #edf0f7;box-shadow:0 18px 45px rgba(34,34,64,.08);margin:28px 0 18px}.ring img{width:128px;height:128px;border-radius:50%;object-fit:cover;border:5px solid #fff}.rstatus{margin:0 0 9px;color:#94a3b8;font-size:23px;line-height:1.1;font-weight:950;text-transform:uppercase;overflow-wrap:anywhere}.rname{margin:0;color:#17172f;font-size:20px;font-weight:950;line-height:1.25;overflow-wrap:anywhere}.rturma{margin-top:10px;border-radius:999px;background:#f7f8fc;border:1px solid #edf0f7;color:#4b5264;padding:8px 14px;font-size:13px;font-weight:850;max-width:100%;overflow-wrap:anywhere}.obs{display:none;margin-top:16px;border-radius:15px;background:#fff1f2;border:1px solid #ffe0e4;color:#b4232f;padding:12px 14px;font-size:13px;line-height:1.45;font-weight:850;overflow-wrap:anywhere}.manual{margin-top:14px;display:grid;grid-template-columns:1fr auto;gap:10px}.help{margin-top:14px;border-radius:18px;background:#fff;border:1px solid rgba(28,32,54,.08);padding:14px;color:#697085;font-size:12px;line-height:1.55;font-weight:700}.help strong{display:block;color:#202037;font-size:13px;font-weight:950;margin-bottom:4px}.help ul{margin:8px 0 0;padding-left:18px}.help li{margin:5px 0}
@media(max-width:920px){.grid{grid-template-columns:1fr}.frame,.result{min-height:380px}.hero{align-items:flex-start;flex-direction:column}.back{width:100%}.desktop-result{display:none}.scan-result-overlay{display:flex;visibility:hidden;opacity:0;pointer-events:none}.frame.has-result .scan-result-overlay{visibility:visible;opacity:1;pointer-events:auto}body.sg-portaria-result-active .tools{display:none}}
@media(min-width:921px){.scan-result-overlay{display:none}.frame.has-result .scan-result-overlay{display:none}.frame.has-result #reader{filter:none;opacity:1;transform:none}}
@media(max-width:620px){body{padding:8px;padding-bottom:calc(12px + env(safe-area-inset-bottom))}.wrap{gap:10px}.hero{border-radius:20px;padding:14px 14px 12px}.hero h1{font-size:21px;letter-spacing:-.035em}.hero p{display:none}.back{min-height:42px}.card{border-radius:19px}.head{padding:14px 14px 6px;flex-direction:column}.head h2{font-size:17px}.head p{font-size:11px}.body{padding:8px 10px 12px}.frame{min-height:min(64vh,430px);padding:8px;border-radius:19px}#reader{min-height:calc(min(64vh,430px) - 16px);border-radius:15px}#reader video{min-height:calc(min(64vh,430px) - 16px);border-radius:15px}.scan-result-overlay{inset:8px;border-radius:16px;padding:14px 12px;gap:8px}.overlay-top{top:9px;left:10px;right:10px}.overlay-ring{width:98px;height:98px;margin-top:24px}.overlay-ring img{width:82px;height:82px}.overlay-status{font-size:20px}.overlay-name{font-size:18px}.overlay-turma{font-size:12px;padding:7px 11px}.overlay-actions,.overlay-manual{grid-template-columns:1fr}.tools{grid-template-columns:1fr}.btn,.select{width:100%}.manual{grid-template-columns:1fr}.manual .btn{width:100%}.help{display:none}.status{padding:11px 12px}.diag{font-size:10px}}
</style>
</head>
<body>
<div class="wrap">
  <section class="hero">
    <div>
      <p class="kicker">Portaria Digital</p>
      <h1>Leitor de crachás</h1>
      <p>Aponte para o QR Code. Só alunos activos recebem autorização; outros estados são bloqueados com nota clara.</p>
    </div>
    <a class="back" href="<?php echo esc_url($back_url); ?>">← Voltar à Portaria</a>
  </section>

  <main class="grid">
    <section class="card">
      <div class="head">
        <div>
          <h2>Leitura do crachá</h2>
          <p>Aponte para o QR Code. Depois da leitura, o resultado aparece aqui mesmo.</p>
        </div>
        <span class="badge">Câmara</span>
      </div>
      <div class="body">
        <div class="frame" id="frame">
          <div id="reader">
            <div class="placeholder">
              <strong>Câmara pronta</strong>
              <span>Toque em Iniciar câmara e aponte para o QR Code do crachá.</span>
            </div>
          </div>
          <div class="scan-result-overlay reading" id="scan-result-overlay" role="dialog" aria-live="assertive" aria-modal="false" aria-labelledby="ov-status">
            <div class="overlay-top">
              <span class="overlay-chip" id="ov-chip">A verificar</span>
              <span class="overlay-time" id="ov-time">--/--/---- --:--</span>
            </div>
            <div class="overlay-ring"><img id="ov-photo" src="<?php echo esc_url($avatar); ?>" alt="Foto do aluno"></div>
            <h2 class="overlay-status" id="ov-status">A VERIFICAR...</h2>
            <p class="overlay-name" id="ov-name">A confirmar dados</p>
            <div class="overlay-turma" id="ov-turma">--</div>
            <div class="overlay-obs" id="ov-obs"></div>
            <div class="overlay-actions">
              <button type="button" class="btn" id="next-scan">Ler próximo crachá</button>
              <button type="button" class="btn secondary" id="overlay-manual-toggle">Processo manual</button>
            </div>
            <div class="overlay-manual" id="overlay-manual-wrap" hidden>
              <input type="tel" inputmode="numeric" class="input" id="overlay-manual" placeholder="Nº de processo">
              <button type="button" class="btn" id="overlay-manual-btn">Validar</button>
            </div>
          </div>
        </div>

        <div id="file-reader" class="qr-file-reader" aria-hidden="true"></div>
        <div class="tools">
          <select id="camera-select" class="select" aria-label="Seleccionar câmara" hidden><option value="">Câmara recomendada</option></select>
          <button type="button" class="btn" id="start">Iniciar câmara</button>
          <button type="button" class="btn secondary" id="file-btn">Foto do QR</button>
          <button type="button" class="btn danger" id="stop" hidden>Pausar</button>
          <input type="file" id="file" class="file" accept="image/*" capture="environment">
        </div>
        <div class="status wait" id="status" role="status" aria-live="polite">
          <span class="dot" aria-hidden="true"></span>
          <div><strong id="status-title">Pronto para iniciar</strong><span id="status-text">Toque em Iniciar câmara. Se não abrir, use Foto do QR ou Processo manual.</span></div>
        </div>
        <div class="diag" id="diag" hidden aria-hidden="true"></div>
        <div class="help"><strong>Fluxo da Portaria</strong><ul><li>Inicie a câmara e aponte para o crachá.</li><li>Veja Autorizado ou Bloqueado no mesmo espaço.</li><li>Toque em “Ler próximo crachá” para continuar.</li></ul></div>
      </div>
    </section>

    <aside>
      <section class="result desktop-result" id="result">
        <div class="topline"><span class="chip" id="chip">Aguardando</span><span class="time" id="time">--/--/---- --:--</span></div>
        <div class="ring"><img id="photo" src="<?php echo esc_url($avatar); ?>" alt="Foto do aluno"></div>
        <h2 class="rstatus" id="res-status">AGUARDANDO...</h2>
        <p class="rname" id="res-name">--</p>
        <div class="rturma" id="res-turma">--</div>
        <div class="obs" id="res-obs"></div>
      </section>
      <section class="card manual-card">
        <div class="head"><div><h2>Processo manual</h2><p>Use quando o crachá estiver danificado ou quando preferir digitar.</p></div></div>
        <div class="body"><div class="manual"><input type="tel" inputmode="numeric" class="input" id="manual" placeholder="Nº de processo"><button type="button" class="btn" id="manual-btn">Validar</button></div></div>
      </section>
    </aside>
  </main>
</div>
<audio id="ok-audio" src="<?php echo esc_url($ok_audio); ?>" preload="auto"></audio>
<audio id="err-audio" src="<?php echo esc_url($err_audio); ?>" preload="auto"></audio>
<script <?php echo sige_csp_script_attr(); ?>>
(function(){'use strict';
var ajaxUrl=<?php echo wp_json_encode($ajax_url); ?>, nonce=<?php echo wp_json_encode($nonce); ?>, avatar=<?php echo wp_json_encode($avatar); ?>;
var stream=null, video=null, qr=null, detector=null, loop=0, mode='', activeCameraId='', last={code:'',at:0}, validating=false;
function $(id){return document.getElementById(id)}
function setText(id,v){var e=$(id); if(e)e.textContent=v}
function now(){var d=new Date();return String(d.getDate()).padStart(2,'0')+'/'+String(d.getMonth()+1).padStart(2,'0')+'/'+d.getFullYear()+' '+String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0')}
function esc(v){return String(v||'-').replace(/[<>&"']/g,function(c){return{'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#039;'}[c]||c})}
function secure(){var h=location.hostname||'';return !!(window.isSecureContext||location.protocol==='https:'||h==='localhost'||h==='127.0.0.1')}
function status(cls,t,m){var s=$('status'); if(s)s.className='status '+(cls||'wait'); setText('status-title',t||'Estado'); setText('status-text',m||'')}
function diag(c){var d=$('diag'); if(!d)return; d.hidden=true; d.innerHTML=''}
function play(id){var a=$(id);try{if(a&&a.play){a.currentTime=0;var p=a.play();if(p&&p.catch)p.catch(function(){})}}catch(e){}}
function buzz(kind){try{if(navigator.vibrate)navigator.vibrate(kind==='error'?[80,60,120]:[70])}catch(e){}}
function running(on){var f=$('frame'); if(f)f.classList.toggle('running',!!on)}
function stopTracks(s){if(s&&s.getTracks)s.getTracks().forEach(function(t){try{t.stop()}catch(e){}})}
async function perm(){if(!navigator.permissions||!navigator.permissions.query)return'indisponível';try{var p=await navigator.permissions.query({name:'camera'});return p&&p.state?p.state:'indisponível'}catch(e){return'indisponível'}}
async function pol(){try{if(document.permissionsPolicy&&document.permissionsPolicy.allowsFeature)return document.permissionsPolicy.allowsFeature('camera')?'permitida':'bloqueada'}catch(e){}try{if(document.featurePolicy&&document.featurePolicy.allowsFeature)return document.featurePolicy.allowsFeature('camera')?'permitida':'bloqueada'}catch(e){}return'indisponível'}
async function cams(){if(!navigator.mediaDevices||!navigator.mediaDevices.enumerateDevices)return[];try{var d=await navigator.mediaDevices.enumerateDevices();return(d||[]).filter(function(x){return x&&x.kind==='videoinput'})}catch(e){return[]}}
async function populate(){var c=await cams(),sel=$('camera-select');if(!sel)return c;sel.innerHTML='<option value="">Câmara recomendada</option>';c.forEach(function(cam,i){var o=document.createElement('option');o.value=cam.deviceId||'';o.textContent=cam.label||('Câmara '+(i+1));sel.appendChild(o)});sel.hidden=c.length<2;return c}
function constraints(){return activeCameraId?{video:{deviceId:{exact:activeCameraId}},audio:false}:{video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false}}
async function hasBD(){try{if(!('BarcodeDetector'in window))return false;if(BarcodeDetector.getSupportedFormats){var f=await BarcodeDetector.getSupportedFormats();return!Array.isArray(f)||f.indexOf('qr_code')!==-1}return true}catch(e){return false}}
async function stopCam(){if(loop){try{cancelAnimationFrame(loop)}catch(e){}loop=0} if(qr){try{await qr.stop()}catch(e){}try{qr.clear()}catch(e){}qr=null} if(video){try{video.pause()}catch(e){}try{video.srcObject=null}catch(e){}try{video.remove()}catch(e){}video=null} stopTracks(stream);stream=null;mode='';running(false);var st=$('stop');if(st)st.hidden=true}
function frame(){return $('frame')}
function overlay(){return $('scan-result-overlay')}
function overlayManual(show){var w=$('overlay-manual-wrap'),i=$('overlay-manual');if(!w)return;w.hidden=!show;if(show&&i){setTimeout(function(){try{i.focus()}catch(e){}},30)}}
function overlayVisible(on, cls){var f=frame(),ov=overlay(),finalResult=!!on&&cls&&cls!=='reading';if(!f||!ov)return;f.classList.toggle('has-result',!!on);document.body.classList.toggle('sg-portaria-result-active',finalResult);ov.className='scan-result-overlay '+(cls||'reading');if(!on)overlayManual(false)}
function resetResult(){overlayVisible(false);setText('chip','Aguardando');setText('time',now());setText('res-status','AGUARDANDO...');setText('res-name','--');setText('res-turma','--');var img=$('photo');if(img)img.src=avatar;var ob=$('res-obs');if(ob){ob.textContent='';ob.style.display='none'}}
function boolTrue(v){return v===true||v===1||v==='1'||String(v||'').toLowerCase()==='true'}
function accessDecision(d){
  d=d||{};
  var state=String(d.resultado||d.ui_estado||d.estado_portaria||'').toLowerCase();
  var explicitlyAllowed=boolTrue(d.acesso_permitido)||boolTrue(d.permitido)||state==='autorizado';
  var explicitlyBlocked=boolTrue(d.bloqueado)||state==='bloqueado'||state==='negado';
  var ok=explicitlyAllowed&&!explicitlyBlocked;
  return {
    ok: ok,
    cls: ok?'success':'error',
    chip: ok?'AUTORIZADO':'BLOQUEADO',
    msg: ok?'ENTRADA AUTORIZADA':'ACESSO BLOQUEADO',
    obs: ok?(d.obs||'Aluno activo. Permitir entrada.'):((d.obs||d.motivo_bloqueio)||'Não permitir entrada. Encaminhar à Secretaria.'),
    status: ok?'Entrada autorizada':'Acesso bloqueado',
    help: ok?'Aluno activo. Toque em “Ler próximo crachá” para continuar.':((d.obs||d.motivo_bloqueio)||'Não permitir entrada. Encaminhar à Secretaria.')
  };
}
function showResult(cls,st,nome,turma,foto,obs,chipText){
  cls=cls||'reading'; foto=foto||avatar; chipText=chipText||(cls==='success'?'AUTORIZADO':(cls==='error'&&/bloqueado/i.test(String(st||obs||''))?'BLOQUEADO':(cls==='error'?'ATENÇÃO':'A VERIFICAR')));
  var side=$('result'); if(side)side.className='result desktop-result '+(cls||'');
  setText('chip',chipText); setText('time',now()); setText('res-status',st||'AGUARDANDO...'); setText('res-name',nome||'--'); setText('res-turma',turma||'--');
  var img=$('photo'); if(img)img.src=foto; var ob=$('res-obs'); if(ob){ob.textContent=obs||'';ob.style.display=obs?'block':'none'}
  overlayVisible(true,cls); setText('ov-chip',chipText); setText('ov-time',now()); setText('ov-status',st||'AGUARDANDO...'); setText('ov-name',nome||'--'); setText('ov-turma',turma||'--');
  var oi=$('ov-photo'); if(oi)oi.src=foto; var oo=$('ov-obs'); if(oo){oo.textContent=obs||'';oo.style.display=obs?'block':'none'}
}
function onCode(raw){var code=String(raw||'').trim();if(!code||validating)return;var ts=Date.now();if(last.code===code&&(ts-last.at)<3000)return;last={code:code,at:ts};validar(code,'camera-safe',{pauseScanner:true})}
async function startNative(){await stopCam();detector=new BarcodeDetector({formats:['qr_code']});try{stream=await navigator.mediaDevices.getUserMedia(constraints())}catch(first){if(activeCameraId){activeCameraId='';stream=await navigator.mediaDevices.getUserMedia({video:true,audio:false})}else throw first}await populate();var r=$('reader');r.innerHTML='';video=document.createElement('video');video.autoplay=true;video.muted=true;video.playsInline=true;video.setAttribute('playsinline','');video.setAttribute('muted','');video.srcObject=stream;r.appendChild(video);try{await video.play()}catch(e){}mode='native-barcode-detector';running(true);var st=$('stop');if(st)st.hidden=false;var tick=async function(){if(mode!=='native-barcode-detector'||!video)return;if(!document.hidden&&!validating&&video.readyState>=2){try{var found=await detector.detect(video);if(found&&found.length&&found[0].rawValue)onCode(found[0].rawValue)}catch(e){}}loop=requestAnimationFrame(tick)};loop=requestAnimationFrame(tick)}
async function startHtml5(){await stopCam();if(typeof Html5Qrcode==='undefined')throw{name:'Html5QrcodeMissing'};qr=new Html5Qrcode('reader',false);var cfg=activeCameraId||{facingMode:'environment'};try{await qr.start(cfg,{fps:10,qrbox:function(w,h){var e=Math.max(170,Math.min(280,Math.floor(Math.min(w,h)*.72)));return{width:e,height:e}},aspectRatio:1.333334,disableFlip:false,rememberLastUsedCamera:false},onCode,function(){})}catch(first){if(activeCameraId){activeCameraId='';await qr.start({facingMode:'environment'},{fps:10,qrbox:240,disableFlip:false,rememberLastUsedCamera:false},onCode,function(){})}else throw first}await populate();mode='html5qrcode';running(true);var st=$('stop');if(st)st.hidden=false}
async function start(){var b=$('start');if(b)b.disabled=true;resetResult();diag(null);status('wait','A abrir câmara','A preparar o leitor.');try{if(!secure())throw{name:'SecurityError',_phase:'secure-context'};if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia)throw{name:'NotSupportedError',_phase:'api-check'};if(await hasBD())await startNative();else await startHtml5();status('ok','Câmara activa','Aponte o QR Code do crachá para a área de leitura.');diag({mode:mode,phase:'running',error:'-',cameras:(await cams()).length})}catch(e){await stopCam();var pe=await perm(),po=await pol(),cs=await cams(),name=e&&e.name?e.name:'CameraError',phase=e&&e._phase?e._phase:'start-camera',msg='Não foi possível iniciar a câmara. Use Foto do QR ou Processo manual.';if(name==='NotAllowedError'||name==='PermissionDeniedError')msg='Não foi possível usar a câmara. Use Foto do QR ou Processo manual.';if(name==='NotReadableError'||name==='TrackStartError'||name==='AbortError')msg='A câmara não iniciou. Use Foto do QR ou Processo manual.';status('err','Câmara indisponível',msg);diag(null);showResult('error','CÂMARA INDISPONÍVEL','Use Foto do QR ou Processo manual','-',avatar,msg)}finally{if(b)b.disabled=false}}
async function scanFile(file){if(!file)return;diag(null);status('wait','A ler foto do QR','A processar a imagem do crachá.');try{var res='';if(typeof Html5Qrcode!=='undefined'){var fq=new Html5Qrcode('file-reader',false);try{res=await fq.scanFile(file,true)}finally{try{fq.clear()}catch(e){}}}if(!res&&await hasBD()){var bm=await createImageBitmap(file),bd=new BarcodeDetector({formats:['qr_code']}),codes=await bd.detect(bm);if(codes&&codes.length)res=codes[0].rawValue||''}if(!res)throw{name:'QrImageNotFound'};validar(res,'foto-safe',{pauseScanner:true});status('ok','QR lido pela foto','A validar o crachá.')}catch(e){status('err','Não consegui ler o QR da foto','Aproxime o crachá, garanta boa luz e tente novamente, ou use o número de processo.');diag(null);showResult('error','QR NÃO LIDO','Foto sem QR detectável','-',avatar,'Aproxime o crachá, garanta boa luz e tente novamente, ou use o número de processo.')}}
function validar(codigo,origem,opts){codigo=String(codigo||'').trim();if(!codigo||validating)return;validating=true;overlayManual(false);showResult('reading','A VERIFICAR...','A confirmar dados','Código: '+codigo,avatar,'','A VERIFICAR');if(opts&&opts.pauseScanner)stopCam();var fd=new FormData();fd.append('action','sige_validar_acesso');fd.append('_sige_nonce',nonce);fd.append('qr_code',codigo);fd.append('origem',origem||'camera-safe');fetch(ajaxUrl,{method:'POST',credentials:'same-origin',body:fd}).then(function(r){return r.json()}).then(function(j){if(j&&j.success){var d=j.data||{},dec=accessDecision(d);showResult(dec.cls,dec.msg,d.nome||'--',d.turma||'--',d.foto||avatar,dec.obs,dec.chip);play(dec.ok?'ok-audio':'err-audio');buzz(dec.ok?'success':'error');status(dec.ok?'ok':'err',dec.status,dec.help)}else{var m=(j&&j.data&&(j.data.msg||j.data))?(j.data.msg||j.data):'Aluno não encontrado ou acesso bloqueado.';showResult('error','ACESSO NÃO VALIDADO','Crachá não validado','-',avatar,String(m),'BLOQUEADO');play('err-audio');buzz('error');status('err','Verificação não autorizada',String(m)+' Toque em “Ler próximo crachá” ou use o processo manual.')}}).catch(function(){showResult('error','ERRO DE LIGAÇÃO','Não foi possível validar','-',avatar,'Confirme a internet e tente novamente.','ATENÇÃO');play('err-audio');buzz('error');status('err','Falha de ligação','Não foi possível validar agora. Tente novamente.')}).finally(function(){validating=false})}
function init(){
  setText('time',now()); setText('ov-time',now());
  var s=$('start'),st=$('stop'),fb=$('file-btn'),f=$('file'),m=$('manual'),mb=$('manual-btn'),sel=$('camera-select'),next=$('next-scan'),omt=$('overlay-manual-toggle'),omi=$('overlay-manual'),omb=$('overlay-manual-btn');
  if(s)s.addEventListener('click',function(e){e.preventDefault();start()});
  if(next)next.addEventListener('click',function(e){e.preventDefault();start()});
  if(st)st.addEventListener('click',function(e){e.preventDefault();stopCam().then(function(){status('wait','Câmara pausada','Toque em iniciar para voltar à leitura.');diag(null)})});
  if(fb&&f)fb.addEventListener('click',function(e){e.preventDefault();f.click()});
  if(f)f.addEventListener('change',function(){if(f.files&&f.files[0])scanFile(f.files[0]);f.value=''});
  function onlyNumbers(el){if(el)el.value=String(el.value||'').replace(/[^0-9]/g,'')}
  if(m){m.addEventListener('input',function(){onlyNumbers(m)});m.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();validar(m.value,'manual-safe',{pauseScanner:false})}})}
  if(mb)mb.addEventListener('click',function(e){e.preventDefault();validar(m?m.value:'','manual-safe',{pauseScanner:false})});
  if(omt)omt.addEventListener('click',function(e){e.preventDefault();overlayManual(true)});
  if(omi){omi.addEventListener('input',function(){onlyNumbers(omi)});omi.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();validar(omi.value,'manual-overlay-safe',{pauseScanner:true})}})}
  if(omb)omb.addEventListener('click',function(e){e.preventDefault();validar(omi?omi.value:'','manual-overlay-safe',{pauseScanner:true})});
  if(sel)sel.addEventListener('change',function(){activeCameraId=String(sel.value||'');if(mode)start()});
  if(!secure())status('err','Câmara indisponível','Use Processo manual.');
  populate();
  window.addEventListener('pagehide',function(){stopCam()});
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
</script>
</body></html>
        <?php
        exit;
    }
}
add_action('template_redirect', 'sige_portaria_camera_safe_render', 0);
