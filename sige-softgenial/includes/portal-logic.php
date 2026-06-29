<?php
if (!defined('ABSPATH')) exit;

// v12.10.117 - compatibilidade do shortcode/página antiga.
// Qualquer página que ainda chame [sige_portal_aluno] ou sige_render_portal_aluno()
// deixa de renderizar o portal antigo e passa a conduzir para a Página do Aluno integrada.
if (!function_exists('sige_render_portal_aluno')) {
    function sige_render_portal_aluno(): string {
        $target = function_exists('sige_aluno_portal_url_v117')
            ? sige_aluno_portal_url_v117(get_current_user_id())
            : admin_url('admin.php?page=sige-app&view=aluno_portal');

        if (!is_user_logged_in()) {
            return '<div style="min-height:60vh;display:flex;align-items:center;justify-content:center;background:#f8fafc;">
                <div style="text-align:center;font-family:Segoe UI,Tahoma,sans-serif;background:#fff;border-radius:22px;box-shadow:0 20px 60px rgba(15,23,42,.12);padding:44px 36px;max-width:460px;">
                    <div style="font-size:48px;margin-bottom:16px;">🎓</div>
                    <h3 style="margin:0 0 10px;color:var(--sg-theme-primary,#5a3fd6);font-size:22px;">Página do Aluno</h3>
                    <p style="color:#64748b;margin:0 0 22px;font-size:14px;line-height:1.6;">A página do aluno foi integrada no SIGE. Inicie sessão para continuar.</p>
                    <a href="' . esc_url(wp_login_url($target)) . '" style="background:var(--sg-theme-primary,#5a3fd6);color:#fff;text-decoration:none;padding:12px 24px;border-radius:999px;font-weight:800;font-size:14px;display:inline-block;">Entrar na Página do Aluno</a>
                </div>
            </div>';
        }

        if (!headers_sent()) {
            wp_safe_redirect($target, 302);
            exit;
        }

        return '<div style="padding:36px;text-align:center;background:#fff;border-radius:18px;font-family:Segoe UI,Tahoma,sans-serif;">
            <h3 style="color:var(--sg-theme-primary,#5a3fd6);margin-top:0;">Página do Aluno integrada</h3>
            <p>O portal antigo foi substituído pela página integrada no SIGE.</p>
            <p><a href="' . esc_url($target) . '" style="background:var(--sg-theme-primary,#5a3fd6);color:#fff;text-decoration:none;padding:11px 22px;border-radius:999px;font-weight:800;">Abrir Página do Aluno</a></p>
        </div>';
    }
}

/**

 * SIGE SoftGenial - Portal do Estudante

 * Ficheiro: includes/portal-logic.php

 * Carregado automaticamente por sige-softgenial.php

 * Shortcode: [sige_portal]

 */

if (!defined('ABSPATH')) exit;

// ── Funções auxiliares ────────────────────────────────────────────────────────

function sige_portal_url(string $secao = '', array $extra = []): string {

    $params = array_merge(['secao' => $secao], $extra);

    return add_query_arg($params, get_permalink() ?: home_url(add_query_arg([])));

}

function sige_portal_nota_cor(float $nota): string {

    if ($nota >= 14) return '#2e7d32';

    if ($nota >= 10) return '#f57c00';

    return '#c62828';

}

function sige_portal_nota_badge(float $nota): string {

    if ($nota >= 14) return 'Bom';

    if ($nota >= 10) return 'Suficiente';

    return 'Negativa';

}

function sige_portal_mes_nome(string $mes_ref): string {

    $meses = [

        '01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril',

        '05'=>'Maio','06'=>'Junho','07'=>'Julho','08'=>'Agosto',

        '09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro',

    ];

    if (preg_match('/(\d{4})-(\d{2})/', $mes_ref, $m)) {

        return ($meses[$m[2]] ?? $m[2]) . ' ' . $m[1];

    }

    return $mes_ref;

}

if (!function_exists('sige_portal_fin_saldo_sql')) {
    function sige_portal_fin_saldo_sql(string $alias = 'l'): string {
        if (function_exists('sige_fin_saldo_sql')) {
            return sige_fin_saldo_sql($alias);
        }
        $a = preg_replace('/[^A-Za-z0-9_]/', '', $alias) ?: 'l';
        return "GREATEST(
            COALESCE({$a}.valor_original, 0)
            + COALESCE({$a}.valor_transporte, 0)
            + COALESCE({$a}.valor_extras, 0)
            + COALESCE(NULLIF({$a}.valor_multa_cobrada, 0), {$a}.valor_multa, 0)
            - COALESCE({$a}.valor_desconto, 0)
            - COALESCE({$a}.valor_desconto_especial, 0)
            - COALESCE({$a}.valor_pago, 0)
        , 0)";
    }
}

// ── Renderização principal ────────────────────────────────────────────────────

function sige_render_portal_aluno_legacy_v117(): string {

    if (!is_user_logged_in()) {

        return '<div style="min-height:60vh;display:flex;align-items:center;justify-content:center;">

            <div style="text-align:center;font-family:\'Segoe UI\',sans-serif;background:#fff;border-radius:20px;

                        box-shadow:0 20px 60px rgba(0,0,0,0.12);padding:60px 40px;max-width:400px;">

                <div style="font-size:56px;margin-bottom:20px;">🎓</div>

                <h3 style="margin:0 0 10px;color:var(--sg-theme-primary-800,#3b2f8d);font-size:22px;">Portal do Estudante</h3>

                <p style="color:#666;margin:0 0 24px;font-size:14px;">Inicia sessão para acederes ao teu portal.</p>

                <a href="' . esc_url(wp_login_url(get_permalink())) . '"

                   style="background:var(--sg-theme-primary-800,#3b2f8d);color:#fff;text-decoration:none;padding:12px 30px;

                          border-radius:30px;font-weight:700;font-size:14px;display:inline-block;">

                   Iniciar Sessão

                </a>

            </div>

        </div>';

    }

    global $wpdb;

    $p        = $wpdb->prefix;

    $user     = wp_get_current_user();

    $is_admin = in_array('administrator', (array)$user->roles, true);

    // ── Identificar aluno ─────────────────────────────────────────────────────

    $_eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;

    if ($is_admin) {

        $aluno        = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}sige_alunos WHERE escola_id=%d AND status='activo' LIMIT 1", $_eid));

        $modo_preview = true;

    } else {

        $aluno_id = (int)get_user_meta($user->ID, 'sige_aluno_id', true);

        if (!$aluno_id) {

            return '<div style="padding:40px;text-align:center;background:#fff;border-radius:16px;font-family:sans-serif;">

                <div style="font-size:48px;">⚠️</div>

                <h3 style="color:#c62828;">Conta não associada</h3>

                <p style="color:#666;">O teu utilizador não está associado a nenhum aluno. Contacta a Secretaria.</p>

                <a href="' . esc_url(wp_logout_url(home_url())) . '" style="color:var(--sg-theme-primary-800,#3b2f8d);">Terminar sessão</a>

            </div>';

        }

        $aluno        = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}sige_alunos WHERE id=%d AND escola_id=%d", $aluno_id, $_eid));

        $modo_preview = false;

    }

    if (!$aluno) return '<div style="padding:30px;color:red;">Ficha de aluno não encontrada.</div>';

    // ── Estado bloqueado ──────────────────────────────────────────────────────

    $status_real = strtolower($aluno->status ?: 'activo');

    if ($status_real !== 'activo' && !$is_admin) {

        $msgs = [

            'suspenso'    => ['⛔', 'Acesso Suspenso',     '#c62828', 'A tua conta encontra-se suspensa. Dirija-te à Secretaria.'],

            'transferido' => ['📂', 'Aluno Transferido',   '#ef6c00', 'Este processo foi arquivado devido à transferência.'],

            'desistente'  => ['✖',  'Matrícula Cancelada', '#424242', 'Consta como desistente no sistema.'],

        ];

        [$ico, $tit, $cor, $msg] = $msgs[$status_real] ?? ['⛔', 'Acesso Restrito', '#c62828', 'Conta inactiva.'];

        return "<div style='max-width:500px;margin:60px auto;background:#fff;border-radius:16px;border-top:6px solid $cor;

                            padding:50px 40px;text-align:center;font-family:sans-serif;box-shadow:0 10px 40px rgba(0,0,0,0.1);'>

            <div style='font-size:56px;margin-bottom:16px;'>$ico</div>

            <h2 style='color:$cor;margin:0 0 12px;'>$tit</h2>

            <p style='color:#666;margin:0 0 28px;line-height:1.6;'>$msg</p>

            <a href='" . esc_url(wp_logout_url(home_url())) . "' style='background:$cor;color:#fff;text-decoration:none;

               padding:10px 24px;border-radius:30px;font-weight:700;font-size:13px;'>Terminar Sessão</a>

        </div>";

    }

    // ── Dados base ────────────────────────────────────────────────────────────

    $ano = 0;

    if (function_exists('sige_get_ano_lectivo_atual')) $ano = (int)sige_get_ano_lectivo_atual();

    if (!$ano) {

        $tCfg = $p . 'sige_fin_configuracoes';

        if ($wpdb->get_var("SHOW TABLES LIKE '$tCfg'") === $tCfg) {

            $ano = (int)$wpdb->get_var("SELECT ano_lectivo FROM $tCfg ORDER BY id DESC LIMIT 1");

        }

    }

    if (!$ano) $ano = (int)wp_date('Y');

    // [STD] Nome da escola dinâmico
    $escola_nome_portal = function_exists('sige_get_escola_perfil') ? (sige_get_escola_perfil()->nome_escola ?? get_bloginfo('name')) : get_bloginfo('name');

    $turma = $wpdb->get_row($wpdb->prepare(

        "SELECT t.id, t.nome, t.classe, t.nome_turma

         FROM {$p}sige_matriculas m

         JOIN {$p}sige_turmas t ON m.turma_id = t.id

         WHERE m.aluno_id=%d AND m.ano_lectivo=%d AND m.escola_id=%d AND m.status_matricula!='cancelada' LIMIT 1",

        (int)$aluno->id, $ano, $_eid

    ));

    $turma_id   = $turma ? (int)$turma->id : 0;

    $nome_turma = $turma ? (!empty($turma->nome_turma) ? $turma->nome_turma : $turma->nome) : '-';

    $classe     = $turma ? $turma->classe : '-';

    $foto          = !empty($aluno->foto) ? $aluno->foto : plugins_url('assets/img/avatar-default.svg', dirname(__FILE__));

    $primeiro_nome = explode(' ', trim($aluno->nome_completo))[0];

    $secao         = isset($_GET['secao']) ? sanitize_key($_GET['secao']) : 'inicio';

    // ── Contadores dashboard ──────────────────────────────────────────────────

    // v12.11.9.14 - alinhamento com a semântica financeira canónica:
    // só status pendente/parcial com saldo real geram pendência no portal.
    $sp_saldo_expr = sige_portal_fin_saldo_sql('l');

    $dividas = (int)$wpdb->get_var($wpdb->prepare(

        "SELECT COUNT(*)
         FROM {$p}sige_fin_lancamentos l
         WHERE l.aluno_id=%d
           AND l.escola_id=%d
           AND LOWER(COALESCE(l.status,'')) IN ('pendente','parcial')
           AND {$sp_saldo_expr} > 0.009",

        (int)$aluno->id, $_eid

    ));
    $_portal_tem_divida = ($dividas > 0);

    $total_divida = (float)$wpdb->get_var($wpdb->prepare(

        "SELECT COALESCE(SUM({$sp_saldo_expr}),0)
         FROM {$p}sige_fin_lancamentos l
         WHERE l.aluno_id=%d
           AND l.escola_id=%d
           AND LOWER(COALESCE(l.status,'')) IN ('pendente','parcial')
           AND {$sp_saldo_expr} > 0.009",

        (int)$aluno->id, $_eid

    ));

    ob_start();

?>


<style>

:root {

    --navy:var(--sg-theme-primary-800,#3b2f8d); --navy2:#283593; --gold:#f9a825;

    --green:#2e7d32; --red:#c62828; --orange:#f57c00;

    --bg:#f0f2f8; --card:#fff; --border:#e8eaf0;

    --text:#1c2340; --muted:#7c85a3;

    --radius:16px; --shadow:0 4px 24px rgba(26,35,126,.08);

}

.sp-wrap *{box-sizing:border-box;margin:0;padding:0;}

.sp-wrap{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);max-width:900px;margin:0 auto;padding:0 16px 60px;}

.sp-header{background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 60%,#3949ab 100%);color:#fff;

           padding:36px 30px 80px;border-radius:0 0 40px 40px;position:relative;overflow:hidden;margin-bottom:-52px;}

.sp-header::before{content:'';position:absolute;inset:0;

  background:url("data:image/svg+xml,%3Csvg width='60' height='60' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='30' cy='30' r='20' fill='none' stroke='rgba(255,255,255,.05)' stroke-width='1'/%3E%3C/svg%3E") repeat;}

.sp-header-top{display:flex;justify-content:space-between;align-items:flex-start;position:relative;}

.sp-escola{font-size:12px;opacity:.7;letter-spacing:1px;text-transform:uppercase;}

.sp-logout{color:rgba(255,255,255,.75);text-decoration:none;font-size:12px;font-weight:600;

           border:1px solid rgba(255,255,255,.3);padding:5px 14px;border-radius:20px;transition:.2s;}

.sp-logout:hover{background:rgba(255,255,255,.15);color:#fff;}

.sp-profile{display:flex;align-items:center;gap:16px;margin-top:24px;position:relative;}

.sp-avatar{width:72px;height:72px;border-radius:50%;object-fit:cover;

           border:3px solid rgba(255,255,255,.4);box-shadow:0 4px 16px rgba(0,0,0,.2);}

.sp-nome{font-size:22px;font-weight:700;}

.sp-meta{font-size:12px;opacity:.75;margin-top:3px;}

.sp-badge-admin{background:var(--gold);color:#5d3200;font-size:10px;font-weight:800;

                padding:2px 8px;border-radius:8px;margin-left:8px;vertical-align:middle;}

.sp-nav{background:var(--card);border-radius:20px;margin:0 8px 28px;padding:8px;

        display:flex;gap:4px;box-shadow:var(--shadow);position:relative;z-index:10;

        overflow-x:auto;scrollbar-width:none;}

.sp-nav::-webkit-scrollbar{display:none;}

.sp-nav a{flex:1;min-width:72px;display:flex;flex-direction:column;align-items:center;gap:3px;

          padding:10px 8px;border-radius:14px;text-decoration:none;color:var(--muted);

          font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;

          transition:.2s;white-space:nowrap;}

.sp-nav a .nav-ico{font-size:18px;}

.sp-nav a.active{background:var(--navy);color:#fff;}

.sp-nav a:hover:not(.active){background:var(--bg);color:var(--navy);}

.sp-card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px;}

.sp-card-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;}

.sp-card-header h3{font-size:15px;font-weight:700;color:var(--navy);}

.sp-card-body{padding:22px;}

.sp-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;}

.sp-stat{background:var(--card);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow);text-align:center;}

.sp-stat .n{font-size:1.6rem;font-weight:800;line-height:1;}

.sp-stat .l{font-size:11px;color:var(--muted);margin-top:5px;font-weight:500;}

.lanc-item{display:flex;justify-content:space-between;align-items:flex-start;

           padding:14px 0;border-bottom:1px solid var(--border);gap:12px;}

.lanc-item:last-child{border:none;}

.lanc-left .desc{font-weight:600;font-size:13px;}

.lanc-left .sub{font-size:11px;color:var(--muted);margin-top:3px;}

.lanc-right{text-align:right;flex-shrink:0;}

.lanc-valor{font-weight:700;font-size:14px;}

.sp-pill{display:inline-block;padding:2px 10px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase;}

.pill-pago{background:#e8f5e9;color:#2e7d32;}

.pill-pendente{background:#fff3e0;color:#e65100;}

.pill-parcial{background:var(--sg-theme-soft,#f1edff);color:var(--sg-theme-primary,#5a3fd6);}

.pill-vencido{background:#ffebee;color:#c62828;}

.pag-item{display:flex;gap:12px;align-items:center;padding:12px 0;border-bottom:1px solid var(--border);}

.pag-item:last-child{border:none;}

.pag-icon{width:40px;height:40px;background:#e8f5e9;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}

.pag-info .desc{font-size:13px;font-weight:600;}

.pag-info .sub{font-size:11px;color:var(--muted);margin-top:2px;}

.pag-valor{margin-left:auto;font-weight:700;color:var(--green);white-space:nowrap;}

.pag-recibo{font-size:10px;color:var(--muted);margin-top:2px;text-align:right;}

.nota-disc{display:grid;grid-template-columns:1fr auto auto auto auto;align-items:center;

           gap:10px;padding:12px 0;border-bottom:1px solid var(--border);}

.nota-disc:last-child{border:none;}

.nota-disc .nome{font-weight:600;font-size:13px;}

.nota-disc .sigla{font-size:10px;color:var(--muted);background:var(--bg);padding:2px 6px;border-radius:6px;}

.nota-col{text-align:center;width:44px;}

.nota-col .val{font-weight:700;font-size:13px;}

.nota-col .lab{font-size:9px;color:var(--muted);margin-top:1px;}

.nota-media{font-weight:800;font-size:15px;}

.boletim-row{display:flex;justify-content:space-between;align-items:center;

             padding:14px 0;border-bottom:1px solid var(--border);}

.boletim-row:last-child{border:none;}

.boletim-nf{font-weight:800;font-size:16px;}

.sit-badge{display:inline-block;padding:3px 12px;border-radius:20px;font-size:11px;font-weight:800;}

.sit-progride{background:#e8f5e9;color:#2e7d32;}

.sit-reprova{background:#ffebee;color:#c62828;}

.sit-transita{background:var(--sg-theme-soft,#f1edff);color:var(--sg-theme-primary,#5a3fd6);}

.horario-grid{display:grid;grid-template-columns:auto repeat(5,1fr);gap:4px;font-size:12px;}

.h-header{background:var(--navy);color:#fff;padding:8px;border-radius:8px;text-align:center;font-weight:700;font-size:11px;}

.h-time{background:var(--bg);padding:8px;border-radius:8px;text-align:center;color:var(--muted);font-size:10px;font-weight:600;}

.h-cell{background:var(--bg);padding:8px;border-radius:8px;text-align:center;min-height:44px;display:flex;align-items:center;justify-content:center;}

.h-cell.filled{background:linear-gradient(135deg,var(--sg-theme-soft,#f1edff),#c5cae9);color:var(--navy);font-weight:600;}

.h-cell.vazio{color:#ccc;font-size:10px;}

.perfil-avatar-wrap{text-align:center;padding:28px;border-bottom:1px solid var(--border);}

.perfil-avatar{width:90px;height:90px;border-radius:50%;object-fit:cover;border:4px solid var(--border);}

.perfil-nome{font-size:18px;font-weight:700;margin-top:12px;color:var(--navy);}

.perfil-processo{font-size:12px;color:var(--muted);margin-top:3px;}

.info-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border);font-size:13px;}

.info-row:last-child{border:none;}

.info-label{color:var(--muted);font-weight:500;}

.info-val{font-weight:600;text-align:right;max-width:60%;}

.doc-item{display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px solid var(--border);}

.doc-item:last-child{border:none;}

.doc-info .doc-nome{font-weight:600;font-size:13px;}

.doc-info .doc-sub{font-size:11px;color:var(--muted);margin-top:2px;}

.doc-btn-ver{background:var(--navy);color:#fff;border:none;padding:7px 18px;border-radius:20px;font-size:12px;font-weight:700;cursor:pointer;}

.doc-pendente{font-size:12px;color:var(--muted);font-style:italic;}

.sp-viewer{position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:99999;

           display:none;flex-direction:column;align-items:center;justify-content:center;}

.sp-viewer-bar{width:90%;max-width:760px;display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;}

.sp-viewer-bar a{color:#00e676;text-decoration:none;font-size:12px;font-weight:700;

                  background:rgba(0,230,118,.15);padding:5px 14px;border-radius:20px;}

.sp-viewer-close{color:#fff;font-size:28px;cursor:pointer;line-height:1;}

.sp-viewer-frame{width:90%;max-width:760px;height:75vh;background:#fff;border-radius:12px;border:none;}

.sp-preview-bar{background:var(--gold);color:#5d3200;font-size:12px;font-weight:700;text-align:center;padding:8px;border-radius:10px 10px 0 0;}

.sp-empty{text-align:center;padding:40px 20px;color:var(--muted);}

.sp-empty .ico{font-size:40px;margin-bottom:12px;}

.sp-empty p{font-size:13px;line-height:1.6;}

.sp-atalho-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-top:4px;}

.sp-atalho{background:var(--card);border-radius:var(--radius);padding:22px 16px;text-align:center;

           text-decoration:none;box-shadow:var(--shadow);display:block;transition:.2s;}

.sp-atalho:hover{transform:translateY(-4px);box-shadow:0 8px 32px rgba(26,35,126,.15);}

.sp-atalho .a-ico{font-size:32px;margin-bottom:10px;}

.sp-atalho .a-tit{font-weight:700;font-size:14px;color:var(--navy);}

.sp-atalho .a-sub{font-size:11px;color:var(--muted);margin-top:3px;}

@media(max-width:600px){

  .sp-stats{gap:10px;}

  .sp-stat .n{font-size:1.3rem;}

  .nota-disc .sigla{display:none;}

  .horario-grid{font-size:10px;}

}

</style>

<div class="sp-wrap">

<?php if ($modo_preview): ?>

<div class="sp-preview-bar">👑 Modo Admin - a visualizar: <?php echo esc_html($aluno->nome_completo); ?></div>

<?php endif; ?>

<!-- HEADER -->

<div class="sp-header">

    <div class="sp-header-top">

        <div class="sp-escola"><?php echo esc_html($escola_nome_portal ?? get_bloginfo('name')); ?> · <?php echo esc_html($ano); ?></div>

        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="sp-logout">Sair</a>

    </div>

    <div class="sp-profile">

        <img src="<?php echo esc_url($foto); ?>" class="sp-avatar" alt="Foto"

             onerror="this.src='" . esc_url(plugins_url('assets/img/avatar-default.svg', dirname(__FILE__))) . "'">

        <div>

            <div class="sp-nome">Olá, <?php echo esc_html($primeiro_nome); ?>!

                <?php if ($modo_preview): ?><span class="sp-badge-admin">ADMIN</span><?php endif; ?>

            </div>

            <div class="sp-meta">

                <?php echo esc_html($classe); ?>ª Classe · Turma <?php echo esc_html($nome_turma); ?>

                · Nº <?php echo esc_html($aluno->numero_processo); ?>

            </div>

        </div>

    </div>

</div>

<!-- NAV -->

<nav class="sp-nav">

    <?php

    $nav_items = [

        ['inicio',      '🏠', 'Início'],

        ['financeiro',  '💳', 'Financeiro'],

        ['notas',       '📊', 'Notas'],

        // ['horario',     '📅', 'Horário'], // Em desenvolvimento

        ['perfil',      '👤', 'Perfil'],

        ['documentos',  '📂', 'Documentos'],

    ];

    foreach ($nav_items as [$slug, $ico, $label]):

    ?>

<?php if ($slug === 'notas' && $_portal_tem_divida): ?>
<a href="<?php echo esc_url(sige_portal_url($slug)); ?>"
   class="sp-atalho" style="border-bottom:4px solid #991b1b; opacity:0.85;">
    <div class="a-ico">🔒</div>
    <div class="a-tit"><?php echo $label; ?></div>
    <div class="a-sub" style="color:#991b1b;">Bloqueado - dívida pendente</div>
</a>
<?php else: ?>
    <a href="<?php echo esc_url(sige_portal_url($slug)); ?>" class="<?php echo $secao === $slug ? 'active' : ''; ?>">

        <span class="nav-ico"><?php echo $ico; ?></span><?php echo $label; ?>

    </a>
<?php endif; ?>

    <?php endforeach; ?>

</nav>

<?php

// ══════════════════════════════════════════════════════════════════════════════

// INÍCIO

// ══════════════════════════════════════════════════════════════════════════════

if ($secao === 'inicio'):

    $pags_mes = (int)$wpdb->get_var($wpdb->prepare(

        "SELECT COUNT(*) FROM {$p}sige_fin_pagamentos

         WHERE aluno_id=%d AND escola_id=%d AND data_pagamento >= DATE_SUB(NOW(), INTERVAL 30 DAY)",

        (int)$aluno->id, $_eid

    ));

?>

<div class="sp-stats">

    <div class="sp-stat">

        <div class="n" style="color:<?php echo $dividas > 0 ? 'var(--red)' : 'var(--green)'; ?>">

            <?php echo $dividas > 0 ? $dividas : '✓'; ?>

        </div>

        <div class="l"><?php echo $dividas > 0 ? 'Propina(s) em dívida' : 'Situação financeira ok'; ?></div>

    </div>

    <div class="sp-stat">

        <div class="n" style="color:var(--navy)"><?php echo esc_html($classe); ?></div>

        <div class="l">Classe · <?php echo esc_html($nome_turma); ?></div>

    </div>

    <div class="sp-stat">

        <div class="n" style="color:var(--green)"><?php echo esc_html($pags_mes); ?></div>

        <div class="l">Pagamento(s) este mês</div>

    </div>

</div>

<?php if ($dividas > 0): ?>

<div class="sp-card" style="border-left:4px solid var(--red);margin-bottom:20px;">

    <div class="sp-card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">

        <div>

            <div style="font-weight:700;color:var(--red);">⚠️ <?php echo $dividas; ?> propina(s) em dívida</div>

            <div style="font-size:12px;color:var(--muted);margin-top:3px;">Total em falta: <strong><?php echo number_format($total_divida, 2); ?> MT</strong></div>

        </div>

        <a href="<?php echo esc_url(sige_portal_url('financeiro')); ?>"

           style="background:var(--red);color:#fff;text-decoration:none;padding:8px 20px;border-radius:20px;font-size:12px;font-weight:700;">

            Ver detalhes

        </a>

    </div>

</div>

<?php endif; ?>

<div class="sp-atalho-grid">

<?php

$_portal_tem_divida = ($dividas > 0);
$atalhos = [

    ['financeiro', '💳', 'Financeiro',  'Propinas e pagamentos', '#4caf50'],

    ['notas',      '📊', 'Notas',       'Resultados académicos',  'var(--sg-theme-primary,#5a3fd6)'],

    // ['horario',    '📅', 'Horário',     'Horário da turma',       '#ff9800'], // Módulo em desenvolvimento

    ['perfil',     '👤', 'Meu Perfil',  'Dados pessoais',         '#9c27b0'],

    ['documentos', '📂', 'Documentos',  'Arquivo digital',        '#607d8b'],

];

foreach ($atalhos as [$slug, $ico, $tit, $desc, $cor]):

?>

<a href="<?php echo esc_url(sige_portal_url($slug)); ?>"

   class="sp-atalho" style="border-bottom:4px solid <?php echo $cor; ?>;">

    <div class="a-ico"><?php echo $ico; ?></div>

    <div class="a-tit"><?php echo $tit; ?></div>

    <div class="a-sub"><?php echo $desc; ?></div>

</a>

<?php endforeach; ?>

</div>

<?php

// ══════════════════════════════════════════════════════════════════════════════

// FINANCEIRO

// ══════════════════════════════════════════════════════════════════════════════

elseif ($secao === 'financeiro'):

    $lancamentos = $wpdb->get_results($wpdb->prepare(

        "SELECT l.*, s.nome as servico_nome

         FROM {$p}sige_fin_lancamentos l

         LEFT JOIN {$p}sige_fin_servicos s ON s.id = l.servico_id

         WHERE l.aluno_id=%d AND l.escola_id=%d

         ORDER BY FIELD(l.status,'pendente','parcial','pago','cancelado'), l.data_vencimento DESC",

        (int)$aluno->id, $_eid

    ));

    // Pagamentos: só totais para o primeiro render (paginação via AJAX)

    $pag_por_pag   = 8;

    $pag_offset    = 0;

    $filtro_mes    = ''; // preenchido pelo AJAX

    $total_pags    = (int)$wpdb->get_var($wpdb->prepare(

        "SELECT COUNT(*) FROM {$p}sige_fin_pagamentos WHERE aluno_id=%d AND escola_id=%d",

        (int)$aluno->id, $_eid

    ));

    $total_pag_valor = (float)$wpdb->get_var($wpdb->prepare(

        "SELECT COALESCE(SUM(valor_pago),0) FROM {$p}sige_fin_pagamentos WHERE aluno_id=%d AND escola_id=%d",

        (int)$aluno->id, $_eid

    ));

    // Anos com pagamentos (para o filtro de ano)

    $anos_pag = $wpdb->get_col($wpdb->prepare(

        "SELECT DISTINCT YEAR(data_pagamento) as ano FROM {$p}sige_fin_pagamentos

         WHERE aluno_id=%d AND escola_id=%d ORDER BY ano DESC",

        (int)$aluno->id, $_eid

    ));

    $pagamentos = $wpdb->get_results($wpdb->prepare(

        "SELECT p.*, l.descricao as lanc_desc, l.mes_referencia

         FROM {$p}sige_fin_pagamentos p

         LEFT JOIN {$p}sige_fin_lancamentos l ON l.id = p.lancamento_id

         WHERE p.aluno_id=%d AND p.escola_id=%d

         ORDER BY p.data_pagamento DESC LIMIT %d OFFSET 0",

        (int)$aluno->id, $_eid, $pag_por_pag

    ));

    $metodos_icon = ['dinheiro'=>'💵','numerario'=>'💵','transferencia'=>'🏦','nib'=>'🏦','mpesa'=>'📱','cheque'=>'📄','emola'=>'📱','emola_comerciante'=>'📱','pagafacil'=>'💳','cartao'=>'💳','bim'=>'🏦','bci'=>'🏦','pos_bci'=>'💳','pos_bim'=>'💳','pos_stbank'=>'💳','pos_moza'=>'💳','pos_nedbank'=>'💳','pos_fnb'=>'💳','pos'=>'💳'];

    $lancamentos_divida = array_filter($lancamentos, fn($l) => in_array(strtolower((string)($l->status ?? '')), ['pendente','parcial'], true) && (function_exists('sige_fin_saldo_lancamento') ? sige_fin_saldo_lancamento($l) : max(0, (float)($l->valor_original??0) + (float)($l->valor_transporte??0) + (float)($l->valor_extras??0) + (float)($l->valor_multa??0) - (float)($l->valor_desconto??0) - (float)($l->valor_desconto_especial??0) - (float)($l->valor_pago??0))) > 0.009);

?>

<!-- Propinas em dívida -->

<div class="sp-card">

    <div class="sp-card-header">

        <span>⚠️</span><h3>Propinas em Dívida</h3>

        <?php if (!empty($lancamentos_divida)): ?>

        <span class="sp-pill pill-pendente" style="margin-left:auto;"><?php echo count($lancamentos_divida); ?> em falta</span>

        <?php endif; ?>

    </div>

    <div class="sp-card-body">

    <?php if (empty($lancamentos_divida)): ?>

        <div class="sp-empty"><div class="ico">✅</div><p>Nenhuma propina em dívida.<br>Situação financeira regularizada.</p></div>

    <?php else:

        foreach ($lancamentos_divida as $l):

            $em_falta = function_exists('sige_fin_saldo_lancamento')
                ? sige_fin_saldo_lancamento($l)
                : max(0,
                    (float)($l->valor_original??0)
                    + (float)($l->valor_transporte??0)
                    + (float)($l->valor_extras??0)
                    + (float)($l->valor_multa??0)
                    - (float)($l->valor_desconto??0)
                    - (float)($l->valor_desconto_especial??0)
                    - (float)($l->valor_pago??0)
                  );

            $vencido  = !empty($l->data_vencimento) && strtotime($l->data_vencimento) < time();

            $pill_cls = $vencido ? 'pill-vencido' : ($l->status==='parcial' ? 'pill-parcial' : 'pill-pendente');

            $pill_txt = $vencido ? 'Vencido' : ($l->status==='parcial' ? 'Parcial' : 'Pendente');

    ?>

        <div class="lanc-item">

            <div class="lanc-left">

                <div class="desc"><?php echo esc_html($l->descricao ?: ($l->servico_nome ?: 'Lançamento')); ?></div>

                <div class="sub">

                    <?php echo esc_html(sige_portal_mes_nome($l->mes_referencia ?? '')); ?>

                    <?php if (!empty($l->data_vencimento)): ?> · Vence <?php echo esc_html(date('d/m/Y', strtotime($l->data_vencimento))); ?><?php endif; ?>

                    <?php if ((float)($l->valor_multa??0) > 0): ?> · <span style="color:var(--red);font-weight:600;">⚠️ Multa: <?php echo number_format((float)$l->valor_multa,2); ?> MT</span><?php endif; ?>

                    <?php if ((float)($l->valor_desconto??0) > 0): ?> · <span style="color:var(--green);font-weight:600;">➖ Desc: <?php echo number_format((float)$l->valor_desconto,2); ?> MT</span><?php endif; ?>

                    <?php if ((float)($l->valor_transporte??0) > 0): ?> · <span style="color:var(--sg-theme-primary,#5a3fd6);">🚌 Transp: <?php echo number_format((float)$l->valor_transporte,2); ?> MT</span><?php endif; ?>

                </div>

            </div>

            <div class="lanc-right">

                <div class="lanc-valor" style="color:var(--red);"><?php echo number_format($em_falta,2); ?> MT</div>

                <span class="sp-pill <?php echo $pill_cls; ?>" style="margin-top:4px;display:inline-block;"><?php echo $pill_txt; ?></span>

            </div>

        </div>

    <?php endforeach; endif; ?>

    </div>

</div>

<!-- Histórico de pagamentos -->

<div class="sp-card">

    <div class="sp-card-header">

        <span>🧾</span>

        <h3>Histórico de Pagamentos</h3>

        <span style="margin-left:auto;font-size:12px;color:var(--muted);">

            Total pago: <strong style="color:var(--green);"><?php echo number_format($total_pag_valor, 2); ?> MT</strong>

        </span>

    </div>

    <!-- Filtros -->

    <div style="padding:14px 22px;border-bottom:1px solid var(--border);display:flex;gap:10px;flex-wrap:wrap;align-items:center;">

        <select id="pag-filtro-ano" style="padding:6px 12px;border-radius:8px;border:1px solid var(--border);font-size:12px;font-family:inherit;color:var(--text);background:var(--bg);">

            <option value="">Todos os anos</option>

            <?php foreach ($anos_pag as $a): ?>

            <option value="<?php echo esc_attr($a); ?>"><?php echo esc_html($a); ?></option>

            <?php endforeach; ?>

        </select>

        <select id="pag-filtro-mes" style="padding:6px 12px;border-radius:8px;border:1px solid var(--border);font-size:12px;font-family:inherit;color:var(--text);background:var(--bg);">

            <option value="">Todos os meses</option>

            <?php

            $meses_nomes = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho','07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];

            foreach ($meses_nomes as $num => $nome): ?>

            <option value="<?php echo esc_attr($num); ?>"><?php echo esc_html($nome); ?></option>

            <?php endforeach; ?>

        </select>

        <button id="pag-btn-filtrar" onclick="portalPagamentos.filtrar()"

                style="padding:6px 16px;border-radius:8px;background:var(--navy);color:#fff;border:none;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;">

            Filtrar

        </button>

        <button id="pag-btn-limpar" onclick="portalPagamentos.limpar()"

                style="padding:6px 16px;border-radius:8px;background:var(--bg);color:var(--muted);border:1px solid var(--border);font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;">

            Limpar

        </button>

        <span id="pag-resultado-info" style="font-size:11px;color:var(--muted);margin-left:auto;"></span>

    </div>

    <div class="sp-card-body" id="pag-lista" style="padding-top:8px;min-height:80px;">

    <?php if (empty($pagamentos)): ?>

        <div class="sp-empty"><div class="ico">💸</div><p>Nenhum pagamento registado.</p></div>

    <?php else:

        foreach ($pagamentos as $pag):

            $ico_m = $metodos_icon[strtolower($pag->metodo_pagamento??'')] ?? '💳';

    ?>

        <div class="pag-item">

            <div class="pag-icon"><?php echo $ico_m; ?></div>

            <div class="pag-info">

                <div class="desc"><?php echo esc_html($pag->lanc_desc ?: 'Pagamento'); ?></div>

                <div class="sub">

                    <?php echo esc_html(sige_portal_mes_nome($pag->mes_referencia??'')); ?>

                    · <?php echo esc_html(date('d/m/Y', strtotime($pag->data_pagamento))); ?>

                    <?php if ($pag->metodo_pagamento): ?> · <?php echo esc_html(function_exists('sige_fin_metodo_pagamento_label') ? sige_fin_metodo_pagamento_label($pag->metodo_pagamento) : ucfirst($pag->metodo_pagamento)); ?><?php endif; ?>

                </div>

            </div>

            <div>

                <div class="pag-valor"><?php echo number_format((float)$pag->valor_pago,2); ?> MT</div>

                <?php if ($pag->recibo_numero): ?><div class="pag-recibo"><?php echo esc_html($pag->recibo_numero); ?></div><?php endif; ?>

            </div>

        </div>

    <?php endforeach; endif; ?>

    </div>

    <!-- Paginação -->

    <?php if ($total_pags > $pag_por_pag): ?>

    <div id="pag-footer" style="padding:16px 22px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">

        <div style="font-size:12px;color:var(--muted);">

            A mostrar <span id="pag-showing">1-<?php echo min($pag_por_pag, $total_pags); ?></span>

            de <span id="pag-total"><?php echo $total_pags; ?></span> pagamentos

        </div>

        <div style="display:flex;gap:6px;" id="pag-controls">

            <button id="pag-prev" onclick="portalPagamentos.ir(-1)"

                    style="padding:6px 14px;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-size:12px;cursor:pointer;font-family:inherit;" disabled>‹ Anterior</button>

            <span id="pag-page-info" style="padding:6px 14px;font-size:12px;color:var(--text);font-weight:600;">Página 1</span>

            <button id="pag-next" onclick="portalPagamentos.ir(1)"

                    style="padding:6px 14px;border-radius:8px;background:var(--navy);color:#fff;border:none;font-size:12px;cursor:pointer;font-family:inherit;">Seguinte ›</button>

        </div>

    </div>

    <?php endif; ?>

</div>

<script>

var portalPagamentos = (function() {

    var estado = {

        pagina:   1,

        porPag:   <?php echo (int)$pag_por_pag; ?>,

        total:    <?php echo (int)$total_pags; ?>,

        ano:      '',

        mes:      '',

        aluno_id: <?php echo (int)$aluno->id; ?>,

        nonce:    <?php echo wp_json_encode(wp_create_nonce('sige_portal_pag_nonce')); ?>

    };

    var metodos = {

        'dinheiro':'💵','numerario':'💵','transferencia':'🏦','nib':'🏦','mpesa':'📱',

        'cheque':'📄','emola':'📱','emola_comerciante':'📱','pagafacil':'💳','cartao':'💳',

        'bim':'🏦','bci':'🏦','pos_bci':'💳','pos_bim':'💳','pos_stbank':'💳','pos_moza':'💳','pos_nedbank':'💳','pos_fnb':'💳','pos':'💳'

    };

    var metodoLabels = <?php echo wp_json_encode(function_exists('sige_fin_metodos_pagamento_map') ? sige_fin_metodos_pagamento_map('all') : []); ?>;

    function meses_nomes() {

        return {'01':'Janeiro','02':'Fevereiro','03':'Março','04':'Abril',

                '05':'Maio','06':'Junho','07':'Julho','08':'Agosto',

                '09':'Setembro','10':'Outubro','11':'Novembro','12':'Dezembro'};

    }

    function mesNome(ref) {

        if (!ref) return '';

        var m = ref.match(/(\d{4})-(\d{2})/);

        if (m) { var n = meses_nomes()[m[2]]; return n ? n + ' ' + m[1] : ref; }

        return ref;

    }

    function icoMetodo(m) { return metodos[(m||'').toLowerCase()] || '💳'; }

    function labelMetodo(m) { var k = (m||'').toLowerCase(); return metodoLabels[k] || ucfirst(String(m||'').replace(/[_-]/g, ' ')); }

    function renderLista(rows, total) {

        estado.total = total;

        var lista = document.getElementById('pag-lista');

        if (!rows || !rows.length) {

            lista.innerHTML = '<div class="sp-empty" style="padding:30px 0;"><div class="ico">🔍</div><p>Nenhum pagamento encontrado com esses filtros.</p></div>';

        } else {

            lista.innerHTML = rows.map(function(p) {

                var ico = icoMetodo(p.metodo_pagamento);

                var sub = [mesNome(p.mes_referencia), p.data_fmt, p.metodo_pagamento ? (p.metodo_label || labelMetodo(p.metodo_pagamento)) : ''].filter(Boolean).join(' · ');

                return '<div class="pag-item">'

                    + '<div class="pag-icon">' + ico + '</div>'

                    + '<div class="pag-info"><div class="desc">' + esc(p.lanc_desc || 'Pagamento') + '</div>'

                    + '<div class="sub">' + esc(sub) + '</div></div>'

                    + '<div><div class="pag-valor">' + fmt(p.valor_pago) + ' ' + (typeof sigePortalMoeda !== 'undefined' ? sigePortalMoeda : 'MT') + '</div>'

                    + (p.recibo_numero ? '<div class="pag-recibo">' + esc(p.recibo_numero) + '</div>' : '')

                    + '</div></div>';

            }).join('');

        }

        actualizarPaginacao(total);

    }

    function actualizarPaginacao(total) {

        var inicio = (estado.pagina - 1) * estado.porPag + 1;

        var fim    = Math.min(estado.pagina * estado.porPag, total);

        var totalPags = Math.ceil(total / estado.porPag);

        var el = function(id) { return document.getElementById(id); };

        if (el('pag-showing'))   el('pag-showing').textContent   = total > 0 ? inicio + '-' + fim : '0';

        if (el('pag-total'))     el('pag-total').textContent     = total;

        if (el('pag-page-info')) el('pag-page-info').textContent = 'Página ' + estado.pagina + ' / ' + Math.max(1, totalPags);

        if (el('pag-prev'))      el('pag-prev').disabled         = estado.pagina <= 1;

        if (el('pag-next'))      el('pag-next').disabled         = estado.pagina >= totalPags;

        if (el('pag-footer'))    el('pag-footer').style.display  = total > estado.porPag ? 'flex' : (total === 0 ? 'none' : 'flex');

        if (el('pag-resultado-info')) {

            el('pag-resultado-info').textContent = (estado.ano || estado.mes)

                ? total + ' resultado(s) encontrado(s)'

                : '';

        }

    }

    function carregar() {

        var lista = document.getElementById('pag-lista');

        lista.style.opacity = '0.4';

        lista.style.pointerEvents = 'none';

        var offset = (estado.pagina - 1) * estado.porPag;

        var data = new URLSearchParams({

            action:   'sige_portal_pagamentos',

            nonce:    estado.nonce,

            aluno_id: estado.aluno_id,

            offset:   offset,

            por_pag:  estado.porPag,

            ano:      estado.ano,

            mes:      estado.mes

        });

        fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {

            method: 'POST',

            headers: {'Content-Type': 'application/x-www-form-urlencoded'},

            body: data.toString()

        })

        .then(function(r) { return r.json(); })

        .then(function(res) {

            lista.style.opacity = '1';

            lista.style.pointerEvents = '';

            if (res.success) {

                renderLista(res.data.rows, res.data.total);

            }

        })

        .catch(function() {

            lista.style.opacity = '1';

            lista.style.pointerEvents = '';

        });

    }

    function ir(delta) {

        var totalPags = Math.ceil(estado.total / estado.porPag);

        estado.pagina = Math.max(1, Math.min(estado.pagina + delta, totalPags));

        carregar();

        document.getElementById('pag-lista').scrollIntoView({behavior:'smooth', block:'start'});

    }

    function filtrar() {

        estado.ano    = (document.getElementById('pag-filtro-ano').value  || '').trim();

        estado.mes    = (document.getElementById('pag-filtro-mes').value  || '').trim();

        estado.pagina = 1;

        carregar();

    }

    function limpar() {

        document.getElementById('pag-filtro-ano').value = '';

        document.getElementById('pag-filtro-mes').value = '';

        estado.ano = ''; estado.mes = ''; estado.pagina = 1;

        carregar();

    }

    function esc(s) {

        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

    }

    function fmt(v) {

        return parseFloat(v||0).toLocaleString('pt-MZ', {minimumFractionDigits:2, maximumFractionDigits:2});

    }

    function ucfirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }

    return { ir: ir, filtrar: filtrar, limpar: limpar };

})();

</script>

<?php

// ══════════════════════════════════════════════════════════════════════════════

// NOTAS

// ══════════════════════════════════════════════════════════════════════════════

elseif ($secao === 'notas'):

    // [D1] Bloqueio de notas por dívida (usa $dividas calculado no topo)
    if ($dividas > 0):
?>
<div class="sp-card">
    <div style="padding:40px 20px; text-align:center;">
        <div style="font-size:48px; margin-bottom:15px;">🔒</div>
        <h3 style="margin:0 0 10px; color:#991b1b;">Notas Indisponíveis</h3>
        <p style="color:#64748b; font-size:14px; max-width:400px; margin:0 auto 20px;">
            O acesso às notas está temporariamente suspenso devido a mensalidades pendentes.
        </p>
        <p style="color:#64748b; font-size:13px;">
            Por favor, regularize a situação financeira junto da secretaria para desbloquear o acesso.
        </p>
        <div style="margin-top:20px; padding:12px; background:#fef2f2; border-radius:8px; border:1px solid #fecaca; display:inline-block;">
            <span style="color:#991b1b; font-weight:700;">📋 <?php echo $dividas; ?> lançamento(s) pendente(s)</span>
        </div>
    </div>
</div>
<?php
    else:

    $eh_fim_ciclo = false;

    if (function_exists('sige_is_fim_ciclo_by_turma') && $turma_id) {

        $eh_fim_ciclo = sige_is_fim_ciclo_by_turma($turma_id, $ano);

    }

    $notas_raw = [];

    if ($turma_id) {

        $notas_raw = $wpdb->get_results($wpdb->prepare(

            "SELECT n.trimestre, n.nota_ac, n.nota_acp, n.nota_exame,

                    d.id as disc_id, d.nome as disc_nome, d.sigla, d.categoria,

                    COALESCE(mc.ordem_pauta, d.ordem, 99) as ordem

             FROM {$p}sige_notas n

             JOIN {$p}sige_disciplinas d ON d.id = n.disciplina_id

             LEFT JOIN {$p}sige_matriz_curricular mc ON mc.disciplina_id = d.id AND mc.classe = %s

             WHERE n.aluno_id=%d AND n.turma_id=%d AND n.ano_lectivo=%d AND n.escola_id=%d

             ORDER BY ordem, d.nome, n.trimestre",

            $classe, (int)$aluno->id, $turma_id, $ano, $_eid

        ));

    }

    $disciplinas_mapa = [];

    foreach ($notas_raw as $n) {

        $did = (int)$n->disc_id;

        if (!isset($disciplinas_mapa[$did])) {

            $disciplinas_mapa[$did] = ['nome'=>$n->disc_nome,'sigla'=>$n->sigla,'trimestres'=>[]];

        }

        $disciplinas_mapa[$did]['trimestres'][$n->trimestre] = $n;

    }

    if (empty($disciplinas_mapa)):

?>

<div class="sp-card">

    <div class="sp-empty" style="padding:60px;">

        <div class="ico">📭</div>

        <p>Ainda não há notas lançadas para <?php echo esc_html($ano); ?>.</p>

    </div>

</div>

<?php else:

    // Notas por trimestre

    for ($tri = 1; $tri <= 3; $tri++):

        $tem = array_filter($disciplinas_mapa, fn($d) => isset($d['trimestres'][$tri]));

        if (empty($tem)) continue;

        $tri_icons = ['📗','📘','📕'];

?>

<div class="sp-card">

    <div class="sp-card-header">

        <span><?php echo $tri_icons[$tri-1]; ?></span>

        <h3><?php echo $tri; ?>º Trimestre</h3>

    </div>

    <div class="sp-card-body" style="padding-top:8px;">

        <div style="display:grid;grid-template-columns:1fr auto auto auto auto;gap:8px;

                    font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;

                    padding-bottom:8px;border-bottom:2px solid var(--border);margin-bottom:4px;">

            <span>Disciplina</span>

            <span style="width:44px;text-align:center;">AC</span>

            <span style="width:44px;text-align:center;">ACP</span>

            <span style="width:44px;text-align:center;"><?php echo ($tri < 3 || !$eh_fim_ciclo) ? 'AT' : 'Exame'; ?></span>

            <span style="width:52px;text-align:center;">Média</span>

        </div>

        <?php foreach ($disciplinas_mapa as $disc):

            $n    = $disc['trimestres'][$tri] ?? null;

            $ac   = $n ? $n->nota_ac   : null;

            $acp  = $n ? $n->nota_acp  : null;

            $at   = $n ? $n->nota_exame: null;

            $media = null;

            if ($ac !== null && $acp !== null && $at !== null) {

                $med_acs = ((float)$ac + (float)$acp) / 2;

                $media   = round((2 * $med_acs + (float)$at) / 3, 1);

            }

        ?>

        <div class="nota-disc">

            <div>

                <div class="nome"><?php echo esc_html($disc['nome']); ?></div>

                <?php if ($disc['sigla']): ?><div class="sigla"><?php echo esc_html($disc['sigla']); ?></div><?php endif; ?>

            </div>

            <?php foreach ([$ac, $acp, $at] as $val): ?>

            <div class="nota-col" style="width:44px;">

                <?php if ($val !== null): ?>

                <div class="val" style="color:<?php echo sige_portal_nota_cor((float)$val); ?>;"><?php echo number_format((float)$val,1); ?></div>

                <?php else: ?><div style="color:#ccc;font-size:16px;">-</div><?php endif; ?>

            </div>

            <?php endforeach; ?>

            <div class="nota-col" style="width:52px;">

                <?php if ($media !== null): ?>

                <div class="nota-media" style="color:<?php echo sige_portal_nota_cor($media); ?>;"><?php echo number_format($media,1); ?></div>

                <div style="font-size:9px;color:<?php echo sige_portal_nota_cor($media); ?>;margin-top:2px;"><?php echo sige_portal_nota_badge($media); ?></div>

                <?php else: ?><div style="color:#ccc;">-</div><?php endif; ?>

            </div>

        </div>

        <?php endforeach; ?>

    </div>

</div>

<?php endfor;

// Boletim Final

$tem_t3 = !empty(array_filter($disciplinas_mapa, fn($d) => isset($d['trimestres'][3])));

if ($tem_t3):

    $medias_finais = [];

    foreach ($disciplinas_mapa as $did => $disc) {

        $mts = [];

        for ($t = 1; $t <= 3; $t++) {

            $n = $disc['trimestres'][$t] ?? null;

            if ($n && $n->nota_ac !== null && $n->nota_acp !== null && $n->nota_exame !== null && ($t < 3 || !$eh_fim_ciclo)) {

                $med_acs = ((float)$n->nota_ac + (float)$n->nota_acp) / 2;

                $mts[$t] = round((2 * $med_acs + (float)$n->nota_exame) / 3, 1);

            }

        }

        $n3 = $disc['trimestres'][3] ?? null;

        if ($n3 && !empty($mts)) {

            if ($eh_fim_ciclo && $n3->nota_exame !== null && count($mts) >= 2) {

                $nf = round((array_sum($mts) / count($mts) * 2 + (float)$n3->nota_exame) / 3, 1);

            } else {

                $all = $mts;

                if (isset($disc['trimestres'][3]) && $eh_fim_ciclo === false) {

                    // media dos trimestres incluindo T3

                }

                $nf = round(array_sum($all) / count($all), 1);

            }

            $medias_finais[$did] = ['nome' => $disc['nome'], 'nf' => $nf];

        }

    }

    if (!empty($medias_finais)):

        $negativas = count(array_filter($medias_finais, fn($d) => $d['nf'] < 10));

        $reprova   = $negativas > count($medias_finais) * 0.4;

        $sit_geral = $reprova ? 'Reprova' : ($eh_fim_ciclo ? 'Transita' : 'Progride');

        $sit_class = ['Progride'=>'sit-progride','Transita'=>'sit-transita','Reprova'=>'sit-reprova'][$sit_geral];

?>

<div class="sp-card">

    <div class="sp-card-header" style="background:<?php echo $reprova ? '#ffebee' : '#e8f5e9'; ?>;">

        <span>🎓</span><h3>Boletim Final <?php echo esc_html($ano); ?></h3>

        <span class="sit-badge <?php echo $sit_class; ?>" style="margin-left:auto;font-size:13px;padding:6px 16px;">

            <?php echo esc_html($sit_geral); ?>

        </span>

    </div>

    <div class="sp-card-body" style="padding-top:8px;">

        <?php foreach ($medias_finais as $d): ?>

        <div class="boletim-row">

            <div style="font-weight:600;font-size:13px;"><?php echo esc_html($d['nome']); ?></div>

            <div style="display:flex;align-items:center;gap:10px;">

                <div class="boletim-nf" style="color:<?php echo sige_portal_nota_cor($d['nf']); ?>;"><?php echo number_format($d['nf'],1); ?></div>

                <span class="sp-pill <?php echo $d['nf']>=10 ? 'pill-pago' : 'pill-vencido'; ?>">

                    <?php echo $d['nf']>=10 ? 'Positiva' : 'Negativa'; ?>

                </span>

            </div>

        </div>

        <?php endforeach; ?>

    </div>

</div>

<?php endif; endif; endif;
    endif; // debt check
 ?>

<?php

// ══════════════════════════════════════════════════════════════════════════════

// HORÁRIO

// ══════════════════════════════════════════════════════════════════════════════

elseif ($secao === 'horario'):

    $tHor = $p . 'sige_horarios';

    $tem_hor = $wpdb->get_var("SHOW TABLES LIKE '$tHor'") === $tHor;

    $horario_data = [];

    if ($tem_hor && $turma_id) {

        $rows = $wpdb->get_results($wpdb->prepare(

            "SELECT h.*, d.nome as disc_nome, d.sigla

             FROM $tHor h LEFT JOIN {$p}sige_disciplinas d ON d.id = h.disciplina_id

             WHERE h.turma_id=%d AND h.ano_lectivo=%d AND h.escola_id=%d ORDER BY h.hora_inicio",

            $turma_id, $ano, $_eid

        ));

        foreach ($rows as $r) $horario_data[$r->dia_semana][$r->hora_inicio] = $r;

    }

?>

<div class="sp-card">

    <div class="sp-card-header"><span>📅</span><h3>Horário · Turma <?php echo esc_html($nome_turma); ?></h3></div>

    <div class="sp-card-body">

    <?php if (!$tem_hor): ?>

        <div class="sp-empty"><div class="ico">🔧</div><p>O módulo de horários ainda não está activo.</p></div>

    <?php elseif (empty($horario_data)): ?>

        <div class="sp-empty"><div class="ico">📭</div><p>Nenhum horário registado para esta turma.</p></div>

    <?php else:

        $dias  = ['Segunda','Terça','Quarta','Quinta','Sexta'];

        $horas = array_unique(array_merge(...array_map('array_keys', $horario_data)));

        sort($horas);

    ?>

        <div style="overflow-x:auto;">

        <div class="horario-grid">

            <div class="h-header">Hora</div>

            <?php foreach ($dias as $dia): ?><div class="h-header"><?php echo esc_html($dia); ?></div><?php endforeach; ?>

            <?php foreach ($horas as $hora):

                $h_fim = wp_date('H:i', strtotime($hora) + 50*60);

            ?>

            <div class="h-time"><?php echo esc_html(substr($hora,0,5)); ?><br><?php echo esc_html($h_fim); ?></div>

            <?php foreach ($dias as $dia):

                $cel = $horario_data[$dia][$hora] ?? null;

            ?>

            <div class="h-cell <?php echo $cel ? 'filled' : 'vazio'; ?>">

                <?php if ($cel): ?><div><div style="font-size:11px;font-weight:700;"><?php echo esc_html($cel->sigla ?: substr($cel->disc_nome,0,6)); ?></div><?php if (!empty($cel->sala)): ?><div style="font-size:9px;color:var(--muted);"><?php echo esc_html($cel->sala); ?></div><?php endif; ?></div>

                <?php else: echo '·'; endif; ?>

            </div>

            <?php endforeach; ?>

            <?php endforeach; ?>

        </div>

        </div>

    <?php endif; ?>

    </div>

</div>

<?php

// ══════════════════════════════════════════════════════════════════════════════

// PERFIL

// ══════════════════════════════════════════════════════════════════════════════

elseif ($secao === 'perfil'):

?>

<div class="sp-card">

    <div class="perfil-avatar-wrap">

        <img src="<?php echo esc_url($foto); ?>" class="perfil-avatar" alt="Foto"

             onerror="this.src='" . esc_url(plugins_url('assets/img/avatar-default.svg', dirname(__FILE__))) . "'">

        <div class="perfil-nome"><?php echo esc_html($aluno->nome_completo); ?></div>

        <div class="perfil-processo">Processo: <?php echo esc_html($aluno->numero_processo); ?></div>

    </div>

    <div class="sp-card-body">

        <h4 style="font-size:11px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">Dados Pessoais</h4>

        <?php

        $campos = [

            'Género'             => ucfirst($aluno->genero ?? ''),

            'Nacionalidade'      => $aluno->nacionalidade ?? '',

            'Nº Documento'       => $aluno->documento_nr ?? '',

            'Bairro'             => $aluno->bairro ?? '',

            'Turma'              => $classe . 'ª Classe · ' . $nome_turma,

            'Grupo Sanguíneo'    => $aluno->grupo_sanguineo ?? '',

        ];

        foreach ($campos as $label => $val):

            if (!$val) continue;

        ?>

        <div class="info-row">

            <span class="info-label"><?php echo esc_html($label); ?></span>

            <span class="info-val"><?php echo esc_html($val); ?></span>

        </div>

        <?php endforeach; ?>

        <h4 style="font-size:11px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin:20px 0 12px;">Encarregados</h4>

        <?php

        $enc = [['Pai',$aluno->nome_pai??'',$aluno->telemovel_pai??''],['Mãe',$aluno->nome_mae??'',$aluno->telemovel_mae??'']];

        foreach ($enc as [$rel, $nome, $tel]):

            if (!$nome) continue;

        ?>

        <div class="info-row">

            <span class="info-label"><?php echo esc_html($rel); ?></span>

            <span class="info-val"><?php echo esc_html($nome); ?><?php if ($tel): ?><br><span style="font-size:11px;color:var(--muted);">📱 <?php echo esc_html($tel); ?></span><?php endif; ?></span>

        </div>

        <?php endforeach; ?>

        <?php if (!empty($aluno->alergias)): ?>

        <div class="info-row" style="border-left:3px solid var(--red);padding-left:12px;margin-top:8px;">

            <span class="info-label" style="color:var(--red);">⚠️ Alergias</span>

            <span class="info-val"><?php echo esc_html($aluno->alergias); ?></span>

        </div>

        <?php endif; ?>

    </div>

</div>

<!-- Alterar Senha -->
<div class="sp-card" style="margin-top:20px;">
    <div class="sp-card-header">
        <span>🔐</span><h3>Alterar Senha</h3>
    </div>
    <div class="sp-card-body">
        <div id="sige-pwd-msg" style="display:none; padding:10px; border-radius:6px; margin-bottom:12px;"></div>
        <input type="hidden" id="sige-pwd-nonce" value="<?php echo esc_attr(wp_create_nonce('sige_portal_senha')); ?>">
        <div style="max-width:380px;">
            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px; color:#475569;">Senha actual</label>
                <input type="password" id="sige-pwd-atual" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;" placeholder="••••••">
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px; color:#475569;">Nova senha</label>
                <input type="password" id="sige-pwd-nova" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;" placeholder="Mínimo 8 caracteres">
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px; color:#475569;">Confirmar nova senha</label>
                <input type="password" id="sige-pwd-conf" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;" placeholder="Repita a nova senha">
            </div>
            <button type="button" id="sige-pwd-btn" onclick="sigeAlterarSenha()" style="background:var(--sg-theme-primary-800,#3b2f8d); color:white; border:none; padding:10px 24px; border-radius:6px; font-size:14px; font-weight:700; cursor:pointer;">
                🔐 Alterar Senha
            </button>
        </div>
    </div>
</div>
<script>
function sigeAlterarSenha() {
    var atual = document.getElementById('sige-pwd-atual').value;
    var nova  = document.getElementById('sige-pwd-nova').value;
    var conf  = document.getElementById('sige-pwd-conf').value;
    var nonce = document.getElementById('sige-pwd-nonce').value;
    var msg   = document.getElementById('sige-pwd-msg');
    var btn   = document.getElementById('sige-pwd-btn');
    msg.style.display = 'none';
    if (!atual || !nova || !conf) { msg.style.display='block'; msg.style.background='#fef2f2'; msg.style.color='#991b1b'; msg.textContent='Preencha todos os campos.'; return; }
    if (nova.length < 8) { msg.style.display='block'; msg.style.background='#fef2f2'; msg.style.color='#991b1b'; msg.textContent='A nova senha deve ter pelo menos 8 caracteres.'; return; }
    if (nova !== conf) { msg.style.display='block'; msg.style.background='#fef2f2'; msg.style.color='#991b1b'; msg.textContent='A nova senha e a confirmação não coincidem.'; return; }
    btn.disabled = true; btn.textContent = '⏳ A alterar...';
    jQuery.post('<?php echo admin_url("admin-ajax.php"); ?>', {
        action: 'sige_alterar_senha_portal', _nonce: nonce, senha_atual: atual, senha_nova: nova, senha_confirmar: conf
    }, function(res) {
        msg.style.display = 'block';
        if (res.success) {
            msg.style.background='#f0fdf4'; msg.style.color='#166534'; msg.textContent='✅ ' + res.data;
            document.getElementById('sige-pwd-atual').value=''; document.getElementById('sige-pwd-nova').value=''; document.getElementById('sige-pwd-conf').value='';
        } else {
            msg.style.background='#fef2f2'; msg.style.color='#991b1b'; msg.textContent='❌ ' + (res.data || 'Erro.');
        }
        btn.disabled = false; btn.textContent = '🔐 Alterar Senha';
    }).fail(function() {
        msg.style.display='block'; msg.style.background='#fef2f2'; msg.style.color='#991b1b'; msg.textContent='❌ Erro de rede.';
        btn.disabled = false; btn.textContent = '🔐 Alterar Senha';
    });
}
</script>

<?php

// ══════════════════════════════════════════════════════════════════════════════

// DOCUMENTOS

// ══════════════════════════════════════════════════════════════════════════════

elseif ($secao === 'documentos'):

    $docs = [

        ['🆔', 'BI / Cédula',      'Documento de Identificação', function_exists('sige_secure_document_url') && !empty($aluno->doc_bi_url) ? sige_secure_document_url((int)$aluno->id, 'doc_bi_url') : ($aluno->doc_bi_url ?? '')],

        ['🎓', 'Certificado',       'Habilitações Literárias',    function_exists('sige_secure_document_url') && !empty($aluno->doc_cert_url) ? sige_secure_document_url((int)$aluno->id, 'doc_cert_url') : ($aluno->doc_cert_url ?? '')],

        ['❤️', 'Boletim de Saúde', 'Registo de Vacinas',         function_exists('sige_secure_document_url') && !empty($aluno->doc_vacina_url) ? sige_secure_document_url((int)$aluno->id, 'doc_vacina_url') : ($aluno->doc_vacina_url ?? '')],

    ];

?>

<div class="sp-card">

    <div class="sp-card-header"><span>📂</span><h3>Arquivo Digital</h3></div>

    <div class="sp-card-body">

        <p style="font-size:12px;color:var(--muted);margin-bottom:16px;">Documentos digitalizados na tua ficha de aluno.</p>

        <?php foreach ($docs as [$ico, $nome, $sub, $url]): ?>

        <div class="doc-item">

            <div class="doc-info">

                <div class="doc-nome"><?php echo $ico; ?> <?php echo esc_html($nome); ?></div>

                <div class="doc-sub"><?php echo esc_html($sub); ?></div>

            </div>

            <?php if ($url): ?>

            <button type="button" class="doc-btn-ver sp-open-doc" data-url="<?php echo esc_url($url); ?>">Ver</button>

            <?php else: ?>

            <span class="doc-pendente">Pendente</span>

            <?php endif; ?>

        </div>

        <?php endforeach; ?>

    </div>

</div>

<?php endif; ?>

</div><!-- /.sp-wrap -->

<!-- Viewer de documentos -->

<div id="sp-viewer" class="sp-viewer">

    <div class="sp-viewer-bar">

        <span style="color:#fff;font-size:13px;">Documento</span>

        <div style="display:flex;gap:16px;align-items:center;">

            <a id="sp-dl-link" href="#" target="_blank" download>⬇ Baixar</a>

            <span class="sp-viewer-close" id="sp-viewer-close">&times;</span>

        </div>

    </div>

    <iframe id="sp-viewer-frame" class="sp-viewer-frame" src=""></iframe>

</div>

<script>

(function(){

    document.addEventListener('click', function(e) {

        var btn = e.target.closest('.sp-open-doc');

        if (!btn) return;

        var url = btn.dataset.url;

        document.getElementById('sp-viewer-frame').src = url;

        document.getElementById('sp-dl-link').href = url;

        document.getElementById('sp-viewer').style.display = 'flex';

    });

    var close = function() {

        document.getElementById('sp-viewer').style.display = 'none';

        document.getElementById('sp-viewer-frame').src = '';

    };

    document.getElementById('sp-viewer-close').addEventListener('click', close);

    document.getElementById('sp-viewer').addEventListener('click', function(e) {

        if (e.target === this) close();

    });

})();

</script>

<?php

    return ob_get_clean();

}

// ─────────────────────────────────────────────────────────────────────────────

// AJAX: paginação + filtro do histórico de pagamentos

// ─────────────────────────────────────────────────────────────────────────────

add_action('wp_ajax_sige_portal_pagamentos',        'sige_ajax_portal_pagamentos');

// [AUTH-10] Removido: nopriv desnecessário - handler já verifica is_user_logged_in()
// add_action('wp_ajax_nopriv_sige_portal_pagamentos', 'sige_ajax_portal_pagamentos');

function sige_ajax_portal_pagamentos(): void {

    check_ajax_referer('sige_portal_pag_nonce', 'nonce');

    if (!is_user_logged_in()) { wp_send_json_error('Não autenticado.'); }

    global $wpdb;

    $p = $wpdb->prefix;

    $user     = wp_get_current_user();

    $is_admin = in_array('administrator', (array)$user->roles, true);

    $aluno_id_req = isset($_POST['aluno_id']) ? (int)$_POST['aluno_id'] : 0;

    if ($is_admin) {

        $aluno_id = $aluno_id_req > 0 ? $aluno_id_req : 0;

    } else {

        $aluno_id = (int)get_user_meta($user->ID, 'sige_aluno_id', true);

        if ($aluno_id_req && $aluno_id_req !== $aluno_id) {

            wp_send_json_error('Sem permissão.');

        }

    }

    if (!$aluno_id) { wp_send_json_error('Aluno inválido.'); }

    $_eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;

    $por_pag = max(1, min(50, (int)($_POST['por_pag'] ?? 8)));

    $offset  = max(0, (int)($_POST['offset'] ?? 0));

    $ano     = isset($_POST['ano']) ? (int)preg_replace('/[^0-9]/', '', $_POST['ano']) : 0;

    $mes     = isset($_POST['mes']) ? (int)preg_replace('/[^0-9]/', '', $_POST['mes']) : 0;

    // ── Construir SQL sem DATE_FORMAT (formatação feita em PHP) ──────────────

    if ($ano && $mes) {

        $sql_count = $wpdb->prepare(

            "SELECT COUNT(*) FROM {$p}sige_fin_pagamentos

             WHERE aluno_id=%d AND escola_id=%d AND YEAR(data_pagamento)=%d AND MONTH(data_pagamento)=%d",

            $aluno_id, $_eid, $ano, $mes

        );

        $sql_rows = $wpdb->prepare(

            "SELECT p.id, p.recibo_numero, p.valor_pago, p.metodo_pagamento, p.data_pagamento,

                    l.descricao as lanc_desc, l.mes_referencia

             FROM {$p}sige_fin_pagamentos p

             LEFT JOIN {$p}sige_fin_lancamentos l ON l.id = p.lancamento_id

             WHERE p.aluno_id=%d AND p.escola_id=%d AND YEAR(p.data_pagamento)=%d AND MONTH(p.data_pagamento)=%d

             ORDER BY p.data_pagamento DESC LIMIT %d OFFSET %d",

            $aluno_id, $_eid, $ano, $mes, $por_pag, $offset

        );

    } elseif ($ano) {

        $sql_count = $wpdb->prepare(

            "SELECT COUNT(*) FROM {$p}sige_fin_pagamentos

             WHERE aluno_id=%d AND escola_id=%d AND YEAR(data_pagamento)=%d",

            $aluno_id, $_eid, $ano

        );

        $sql_rows = $wpdb->prepare(

            "SELECT p.id, p.recibo_numero, p.valor_pago, p.metodo_pagamento, p.data_pagamento,

                    l.descricao as lanc_desc, l.mes_referencia

             FROM {$p}sige_fin_pagamentos p

             LEFT JOIN {$p}sige_fin_lancamentos l ON l.id = p.lancamento_id

             WHERE p.aluno_id=%d AND p.escola_id=%d AND YEAR(p.data_pagamento)=%d

             ORDER BY p.data_pagamento DESC LIMIT %d OFFSET %d",

            $aluno_id, $_eid, $ano, $por_pag, $offset

        );

    } elseif ($mes) {

        $sql_count = $wpdb->prepare(

            "SELECT COUNT(*) FROM {$p}sige_fin_pagamentos

             WHERE aluno_id=%d AND escola_id=%d AND MONTH(data_pagamento)=%d",

            $aluno_id, $_eid, $mes

        );

        $sql_rows = $wpdb->prepare(

            "SELECT p.id, p.recibo_numero, p.valor_pago, p.metodo_pagamento, p.data_pagamento,

                    l.descricao as lanc_desc, l.mes_referencia

             FROM {$p}sige_fin_pagamentos p

             LEFT JOIN {$p}sige_fin_lancamentos l ON l.id = p.lancamento_id

             WHERE p.aluno_id=%d AND p.escola_id=%d AND MONTH(p.data_pagamento)=%d

             ORDER BY p.data_pagamento DESC LIMIT %d OFFSET %d",

            $aluno_id, $_eid, $mes, $por_pag, $offset

        );

    } else {

        $sql_count = $wpdb->prepare(

            "SELECT COUNT(*) FROM {$p}sige_fin_pagamentos WHERE aluno_id=%d AND escola_id=%d",

            $aluno_id, $_eid

        );

        $sql_rows = $wpdb->prepare(

            "SELECT p.id, p.recibo_numero, p.valor_pago, p.metodo_pagamento, p.data_pagamento,

                    l.descricao as lanc_desc, l.mes_referencia

             FROM {$p}sige_fin_pagamentos p

             LEFT JOIN {$p}sige_fin_lancamentos l ON l.id = p.lancamento_id

             WHERE p.aluno_id=%d AND p.escola_id=%d

             ORDER BY p.data_pagamento DESC LIMIT %d OFFSET %d",

            $aluno_id, $_eid, $por_pag, $offset

        );

    }

    $total = (int)$wpdb->get_var($sql_count);

    $rows_raw = $wpdb->get_results($sql_rows, ARRAY_A);

    // Formatar data em PHP (evita DATE_FORMAT no SQL)

    $rows = [];

    foreach ((array)$rows_raw as $r) {

        $r['data_fmt'] = !empty($r['data_pagamento'])

            ? date('d/m/Y', strtotime($r['data_pagamento']))

            : '';

        $r['metodo_label'] = function_exists('sige_fin_metodo_pagamento_label') ? sige_fin_metodo_pagamento_label($r['metodo_pagamento'] ?? '') : ucfirst((string)($r['metodo_pagamento'] ?? ''));

        $rows[] = $r;

    }

    wp_send_json_success(['total' => $total, 'rows' => $rows]);

}

// ─────────────────────────────────────────────────────────────────────────────

// Registar shortcode (substitui o fallback do sige-softgenial.php)

// ─────────────────────────────────────────────────────────────────────────────

if (function_exists('shortcode_exists') && !shortcode_exists('sige_portal')) {

    add_shortcode('sige_portal', 'sige_render_portal_aluno');

} elseif (!function_exists('shortcode_exists')) {

    add_shortcode('sige_portal', 'sige_render_portal_aluno');

}