<?php
/**
 * SIGE SoftGenial - Login Page Personalizada
 * Hooks: login_enqueue_scripts (fonts), login_head (CSS), login_form (JS)
 * @since 12.0
 */
if (!defined('ABSPATH')) exit;

// ── 1. Fontes locais/sistema ─────────────────────────────────────────────────
// v12.14.0: remove dependência externa do Google Fonts no login. A pilha visual
// passa a usar fontes do sistema para permitir CSP/privacidade mais fortes.
add_action('login_enqueue_scripts', function () {
    // Sem CDN externo por desenho.
}, 999);

// ── 2. Inline CSS (login_head = dentro do <head> da login page) ──────────────
add_action('login_head', function () {
    global $wpdb;
    $logo_url = '';
    $nome_escola = 'SoftGenial';
    $t = $wpdb->prefix . 'sige_config';
    if ($wpdb->get_var("SHOW TABLES LIKE '$t'") === $t) {
        $cfg = $wpdb->get_row("SELECT logo_sistema_url, nome_escola FROM $t ORDER BY id DESC LIMIT 1");
        if ($cfg) {
            if (!empty($cfg->logo_sistema_url)) $logo_url = $cfg->logo_sistema_url;
            if (!empty($cfg->nome_escola))      $nome_escola = $cfg->nome_escola;
        }
    }
    ?>
    <style>
    :root{--sn:#0d1259;--sn8:#1a1f6e;--sa:#f9a825;--ss:#64748b;--sbg:#f0f2f8}
    body.login,body.login.wp-core-ui{background:var(--sbg)!important;background-image:none!important;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif!important;display:flex!important;align-items:center!important;justify-content:center!important;min-height:100vh!important;margin:0!important;padding:20px!important;overflow:hidden!important}
    body.login::before{content:'';position:fixed;top:-50%;left:-50%;width:200%;height:200%;background:radial-gradient(ellipse at 20% 50%,rgba(13,18,89,.06) 0%,transparent 50%),radial-gradient(ellipse at 80% 20%,rgba(249,168,37,.05) 0%,transparent 50%),radial-gradient(ellipse at 50% 80%,rgba(13,18,89,.04) 0%,transparent 50%);animation:sige-bg 20s ease-in-out infinite;z-index:0}
    @keyframes sige-bg{0%,100%{transform:translate(0,0)}33%{transform:translate(2%,-1%) rotate(1deg)}66%{transform:translate(-1%,2%) rotate(-1deg)}}
    #login{width:100%!important;max-width:420px!important;padding:0!important;margin:0!important;position:relative;z-index:1;animation:sige-up .6s cubic-bezier(.16,1,.3,1)}
    @keyframes sige-up{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
    #login h1 a{background-image:url('<?php echo esc_url($logo_url ?: plugin_dir_url(dirname(__FILE__)) . 'assets/img/avatar-default.svg'); ?>')!important;background-size:contain!important;background-position:center!important;background-repeat:no-repeat!important;width:80px!important;height:80px!important;margin:0 auto 12px!important;border-radius:20px;box-shadow:0 8px 32px rgba(13,18,89,.12);transition:transform .3s ease}
    #login h1 a:hover{transform:scale(1.05)}
    #login h1{text-align:center;margin-bottom:8px!important}
    #login h1::after{content:'<?php echo esc_js($nome_escola); ?>';display:block;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;font-size:20px;font-weight:800;color:var(--sn);letter-spacing:-.3px;margin-top:12px}
    .login form,#loginform{background:#fff!important;border:none!important;border-radius:16px!important;box-shadow:0 20px 60px rgba(13,18,89,.15)!important;padding:36px 32px 28px!important;margin-top:0!important}
    .login label{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif!important;font-size:13px!important;font-weight:600!important;color:var(--sn)!important}
    .login input[type="text"],.login input[type="password"]{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif!important;font-size:15px!important;padding:12px 16px!important;border:2px solid #e2e8f0!important;border-radius:12px!important;background:#f8fafc!important;color:var(--sn)!important;transition:all .2s ease!important;width:100%!important;box-sizing:border-box!important;margin-top:6px!important}
    .login input[type="text"]:focus,.login input[type="password"]:focus{border-color:var(--sn)!important;background:#fff!important;box-shadow:0 0 0 4px rgba(13,18,89,.08)!important;outline:none!important}
    .login .button-primary,.wp-core-ui .button-primary{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif!important;background:var(--sn)!important;border:none!important;border-color:var(--sn)!important;border-radius:12px!important;padding:14px 24px!important;font-size:15px!important;font-weight:700!important;letter-spacing:.3px!important;text-shadow:none!important;box-shadow:0 4px 16px rgba(13,18,89,.25)!important;width:100%!important;margin-top:8px!important;cursor:pointer!important;color:#fff!important}
    .login .button-primary:hover,.wp-core-ui .button-primary:hover{background:var(--sn8)!important;border-color:var(--sn8)!important;box-shadow:0 6px 24px rgba(13,18,89,.35)!important}
    .login .forgetmenot{margin-top:4px!important}
    .login .forgetmenot label{font-size:12px!important;color:var(--ss)!important;font-weight:500!important}
    #login #nav,#login #backtoblog{text-align:center!important;margin:12px 0 0!important;padding:0!important}
    #login #nav a,#login #backtoblog a{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif!important;color:var(--ss)!important;font-size:13px!important;font-weight:500!important;text-decoration:none!important}
    #login #nav a:hover,#login #backtoblog a:hover{color:var(--sn)!important}
    .login .message,.login #login_error{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif!important;border-radius:12px!important;border:none!important;padding:14px 18px!important;margin-bottom:16px!important;font-size:13px!important}
    .login .message{background:#ecfdf5!important;color:#065f46!important;border-left:4px solid #10b981!important}
    .login #login_error{background:#fef2f2!important;color:#991b1b!important;border-left:4px solid #ef4444!important}
    .login .privacy-policy-page-link,.login .language-switcher{display:none!important}
    #login::after{content:'Powered by SoftGenial \00B7  RMBJ Consultoria';display:block;text-align:center;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;font-size:11px;color:var(--ss);margin-top:32px}
    .login .wp-pwd .button.wp-hide-pw{border-radius:0 10px 10px 0!important;border:2px solid #e2e8f0!important;border-left:none!important;background:#f8fafc!important;color:var(--ss)!important}
    @media(max-width:480px){#login{max-width:100%!important}.login form,#loginform{padding:28px 24px 24px!important;border-radius:12px!important}#login h1 a{width:64px!important;height:64px!important}}
    .login #login_error~form{animation:sige-shake .5s ease}
    @keyframes sige-shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-8px)}40%{transform:translateX(8px)}60%{transform:translateX(-4px)}80%{transform:translateX(4px)}}
    </style>
    <?php
}, 999);

// ── 3. Logo URL e texto ──────────────────────────────────────────────────────
add_filter('login_headerurl', function () { return home_url('/'); });
add_filter('login_headertext', function () {
    global $wpdb;
    $t = $wpdb->prefix . 'sige_config';
    if ($wpdb->get_var("SHOW TABLES LIKE '$t'") !== $t) return 'SoftGenial';
    $cfg = $wpdb->get_row("SELECT nome_escola FROM $t ORDER BY id DESC LIMIT 1");
    return $cfg->nome_escola ?? 'SoftGenial';
});

// ── 4. Placeholders ──────────────────────────────────────────────────────────
add_action('login_form', function () {
    echo '<script>document.addEventListener("DOMContentLoaded",function(){var u=document.getElementById("user_login"),p=document.getElementById("user_pass");if(u)u.placeholder="Email ou utilizador";if(p)p.placeholder="\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022";});</script>';
});
