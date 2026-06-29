<?php
/**
 * SIGE SoftGenial - Admin Shell (UI)
 * Ficheiro: includes/admin-shell.php
 *
 * Contém todo o UI do painel de administração:
 *   • CSS de transição (admin_head)
 *   • Assets JS/CSS + nonces (admin_enqueue_scripts)
 *   • Registo do menu WP (admin_menu)
 *   • Função principal de renderização (sidebar, topbar, routing)
 *
 * Extraído do sige-softgenial.php na Fase 4 da reestruturação.
 *
 * @since 11.0
 */

if (!defined("ABSPATH")) exit;
if (file_exists(SIGE_PATH . 'includes/ui-components.php')) { require_once SIGE_PATH . 'includes/ui-components.php'; }

// ============================================================================
// FEEDBACK VISÍVEL DE ACÇÕES - v12.10.110
// ============================================================================
// Objectivo: garantir que acções de guardar/salvar que já redireccionam com
// marcadores de sucesso/erro tenham uma mensagem visível, padronizada e no
// padrão Produto PRO, mesmo quando a view específica ainda não renderiza aviso.
if (!function_exists('sige_app_flash_notice_from_query')) {
    function sige_app_flash_notice_from_query(string $view = ''): ?array {
        if (!is_admin()) return null;
        if (($_GET['page'] ?? '') !== 'sige-app') return null;

        $view = $view !== '' ? $view : sanitize_key((string)($_GET['view'] ?? 'dashboard'));
        $type = '';
        $title = '';
        $message = '';

        $is_jardim = strpos($view, 'jardim_') === 0;

        $clean = static function($v): string {
            return sanitize_text_field(wp_unslash((string)$v));
        };

        // Marcador explícito preferencial para novas intervenções.
        if (isset($_GET['sg_notice'])) {
            $sg = sanitize_key((string)$_GET['sg_notice']);
            if (in_array($sg, ['success','ok','saved','guardado'], true)) {
                $type = 'success';
                $title = 'Operação concluída';
                $message = 'Alterações guardadas com sucesso.';
            } elseif (in_array($sg, ['error','erro','failed','falha'], true)) {
                $type = 'error';
                $title = 'Operação não concluída';
                $message = 'Não foi possível concluir a operação. Verifique os dados e tente novamente.';
            } elseif (in_array($sg, ['warning','aviso','duplicado'], true)) {
                $type = 'warning';
                $title = 'Atenção';
                $message = 'A operação foi interrompida para evitar duplicação ou inconsistência.';
            } else {
                $type = 'info';
                $title = 'Informação';
                $message = 'Operação processada.';
            }

            if (!empty($_GET['sg_message'])) {
                $message = $clean($_GET['sg_message']);
            }
        }

        // Feedback técnico de módulos que já enviam mensagem textual.
        if ($type === '' && isset($_GET['feedback'])) {
            $ftipo = sanitize_key((string)($_GET['ftipo'] ?? 'success'));
            $type = in_array($ftipo, ['error','erro'], true) ? 'error' : (in_array($ftipo, ['warning','aviso'], true) ? 'warning' : 'success');
            $title = $type === 'success' ? 'Operação concluída' : ($type === 'warning' ? 'Atenção' : 'Operação não concluída');
            $message = $clean($_GET['feedback']);
        }

        // ACTA - aprovação/aplicação de nota votada.
        if ($type === '' && isset($_GET['acta_apply'])) {
            $ok = sanitize_key((string)$_GET['acta_apply']) === 'ok';
            $type = $ok ? 'success' : 'error';
            $title = $ok ? 'Nota aplicada' : 'Nota não aplicada';
            $message = !empty($_GET['acta_msg']) ? $clean($_GET['acta_msg']) : ($ok ? 'A nota votada foi aprovada e aplicada à pauta.' : 'Não foi possível aplicar a nota votada.');
        }

        // Marcadores comuns usados em várias views/handlers.
        if ($type === '' && (isset($_GET['saved']) || isset($_GET['updated']) || isset($_GET['success']))) {
            $type = 'success';
            $title = 'Alterações guardadas';
            $message = 'A informação foi guardada com sucesso.';
        }

        // Estado de licença / saúde do sistema.
        if ($type === '' && isset($_GET['license'])) {
            $license = sanitize_key((string)$_GET['license']);
            if ($license === 'checked') {
                $type = 'success';
                $title = 'Licença verificada';
                $message = 'A verificação da licença foi concluída com sucesso.';
            } elseif ($license === 'check_failed') {
                $type = 'error';
                $title = 'Licença não verificada';
                $message = 'Não foi possível verificar a licença neste momento.';
            }
        }

        // WhatsApp health/config.
        if ($type === '' && isset($_GET['wpp_health_updated'])) {
            $type = 'success';
            $title = 'Configuração guardada';
            $message = 'A política de saúde do WhatsApp foi actualizada com sucesso.';
        }

        // Módulos com msg=ok/msg=erro. No Jardim existe feedback local próprio,
        // por isso evitamos duplicação visual nesse grupo.
        if ($type === '' && !$is_jardim && isset($_GET['msg'])) {
            $msg = sanitize_key((string)$_GET['msg']);
            if (in_array($msg, ['ok','success','saved','guardado','criado','actualizado','atualizado'], true)) {
                $type = 'success';
                $title = 'Operação concluída';
                $message = 'Alterações guardadas com sucesso.';
            } elseif (in_array($msg, ['erro','error','failed','falha'], true)) {
                $type = 'error';
                $title = 'Operação não concluída';
                $message = 'Não foi possível concluir a operação. Verifique os dados e tente novamente.';
            } elseif (in_array($msg, ['duplicado','warning','aviso'], true)) {
                $type = 'warning';
                $title = 'Atenção';
                $message = 'A operação foi bloqueada para evitar duplicação.';
            }
        }

        if ($type === '') return null;

        return [
            'type' => $type,
            'title' => $title ?: 'Informação',
            'message' => $message ?: 'Operação processada.',
        ];
    }
}

if (!function_exists('sige_app_render_flash_notice')) {
    function sige_app_render_flash_notice(string $view = ''): void {
        $notice = sige_app_flash_notice_from_query($view);
        if (empty($notice)) return;

        $type = sanitize_key((string)$notice['type']);
        if (!in_array($type, ['success','error','warning','info'], true)) $type = 'info';

        $icons = [
            'success' => '<path d="M20 6 9 17l-5-5"/>',
            'error'   => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
            'warning' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
            'info'    => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        ];
        ?>
        <style id="sige-app-flash-style-v1210112">
        .sige-pro-popup-backdrop{
            position:fixed;inset:0;z-index:999999;display:flex;align-items:center;justify-content:center;
            padding:22px;background:rgba(15,23,42,.42);backdrop-filter:blur(5px);animation:sigePopupFade .18s ease-out both;
        }
        .sige-pro-popup-card{
            width:min(520px,100%);display:grid;grid-template-columns:auto minmax(0,1fr);gap:15px;align-items:flex-start;
            padding:22px 22px 20px;border-radius:24px;border:1px solid rgba(255,255,255,.70);background:#fff;
            box-shadow:0 28px 90px rgba(15,23,42,.34);color:#202037;position:relative;
            font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif);
            animation:sigePopupScale .22s ease-out both;
        }
        .sige-pro-popup-card svg{width:22px;height:22px;display:block;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .sige-app-flash-icon{width:52px;height:52px;border-radius:18px;display:flex;align-items:center;justify-content:center;flex:0 0 auto;}
        .sige-pro-popup-card strong{display:block;margin:2px 42px 6px 0;font-size:18px;line-height:1.18;font-weight:900;color:inherit;letter-spacing:-.02em}
        .sige-pro-popup-card p{margin:0;color:inherit;font-size:var(--fs-base);line-height:1.55;font-weight:650}
        .sige-pro-popup-close{position:absolute;top:13px;right:13px;width:34px;height:34px;border-radius:13px;border:1px solid rgba(15,23,42,.08);background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;color:inherit;font-size:var(--fs-lg);line-height:1;font-weight:800;box-shadow:0 8px 18px rgba(15,23,42,.08)}
        .sige-app-flash-success{background:#ecfdf5;border-color:#bbf7d0;color:#166534}.sige-app-flash-success .sige-app-flash-icon{background:#dcfce7;color:#16a34a}
        .sige-app-flash-error{background:#fef2f2;border-color:#fecaca;color:#991b1b}.sige-app-flash-error .sige-app-flash-icon{background:#fee2e2;color:#dc2626}
        .sige-app-flash-warning{background:#fff7ed;border-color:#fed7aa;color:#92400e}.sige-app-flash-warning .sige-app-flash-icon{background:#ffedd5;color:#f59e0b}
        .sige-app-flash-info{background:var(--sg-theme-soft,#f1edff);border-color:var(--sg-theme-soft,#f1edff);color:var(--sg-theme-primary,#5a3fd6)}.sige-app-flash-info .sige-app-flash-icon{background:var(--sg-theme-soft,#f1edff);color:#2563eb}
        @keyframes sigePopupFade{from{opacity:0}to{opacity:1}}@keyframes sigePopupScale{from{opacity:0;transform:translateY(10px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
        @media(max-width:760px){.sige-pro-popup-backdrop{align-items:flex-end;padding:14px}.sige-pro-popup-card{border-radius:var(--radius-xl);grid-template-columns:1fr;text-align:center}.sige-app-flash-icon{margin:0 auto}.sige-pro-popup-card strong{margin-right:0}}
        </style>
        <div id="sige-app-flash-backdrop" class="sige-pro-popup-backdrop" role="presentation">
          <div id="sige-app-flash" class="sige-pro-popup-card sige-app-flash-<?php echo esc_attr($type); ?>" role="dialog" aria-modal="true" aria-live="polite">
            <span class="sige-app-flash-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><?php echo $icons[$type] ?? $icons['info']; ?></svg>
            </span>
            <div>
                <strong><?php echo esc_html((string)$notice['title']); ?></strong>
                <p><?php echo esc_html((string)$notice['message']); ?></p>
            </div>
            <button type="button" class="sige-pro-popup-close" aria-label="Fechar aviso" data-sige-act="sigeFecharPopupBackdrop">×</button>
          </div>
        </div>
        <?php
    }
}



// ============================================================================
// UI APP SHELL - transição suave sem mostrar backend do WordPress
// ============================================================================
add_action('admin_head', function () {
    if (!is_admin()) return;
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    if ($page !== 'sige-app') return;

    echo '<style id="sige-app-shell-transition-head">'
        . 'html.wp-toolbar{padding-top:0 !important;}'
        . 'body.sige-admin-app #wpadminbar,'
        . 'body.sige-admin-app #adminmenumain,'
        . 'body.sige-admin-app #screen-meta-links,'
        . 'body.sige-admin-app #wpfooter{display:none !important;}'
        . 'body.sige-admin-app #wpcontent,body.sige-admin-app #wpbody-content{margin-left:0 !important;padding-left:0 !important;padding-right:0 !important;}'
        . 'body.sige-admin-app #wpcontent{padding-top:0 !important;}'
        . 'body.sige-admin-app #wpbody-content{padding-bottom:0 !important;}'
        . 'body.sige-admin-app .wrap{margin:0 !important;}'
        . '@media (min-width:861px){body.sige-admin-app .sg-app-topbar-logo{display:none !important;}}'
        . 'body.sige-admin-app.folded #wpcontent,body.sige-admin-app.auto-fold #wpcontent{margin-left:0 !important;padding-left:0 !important;}'
        . 'body.sige-admin-app #sige-layout{min-height:100vh;}'
        . 'body.sige-admin-app.sige-app-preload #wpbody-content > *{opacity:0 !important;}'
        . 'body.sige-admin-app.sige-app-preload::before{content:"";position:fixed;inset:0;background:linear-gradient(135deg,#f6f8fc 0%,#eef3ff 100%);z-index:99998;}'
        . 'body.sige-admin-app.sige-app-ready::before{display:none !important;}'
        . 'body.sige-admin-app #sige-page-transition{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;background:rgba(246,248,252,.58);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);opacity:0;visibility:hidden;transition:opacity .18s ease,visibility .18s ease;z-index:99999;}'
        . 'body.sige-admin-app.sige-is-transitioning #sige-page-transition{opacity:1;visibility:visible;pointer-events:auto;}'
        . 'body.sige-admin-app #sige-page-transition .sige-transition-card{display:flex;align-items:center;gap:var(--space-3);padding:14px 18px;border-radius:18px;background:#ffffff;border:1px solid rgba(28,41,84,.08);box-shadow:0 18px 50px rgba(17,24,39,.14);font:600 14px/1.2 Segoe UI,Tahoma,sans-serif;color:#1f2a44;}'
        . 'body.sige-admin-app #sige-page-transition .sige-spinner{width:18px;height:18px;border-radius:var(--radius-pill);border:2px solid rgba(37,99,235,.18);border-top-color:#2563eb;animation:sigeSpin .65s linear infinite;}'
        . 'body.sige-admin-app .notice,body.sige-admin-app div.updated,body.sige-admin-app div.error{box-sizing:border-box !important;background:#ffffff !important;color:#0f172a !important;border:1px solid rgba(15,23,42,.10) !important;border-left:5px solid #2563eb !important;border-radius:var(--radius-lg)!important;margin:18px 24px !important;padding:14px 18px !important;box-shadow:0 18px 45px rgba(15,23,42,.10) !important;font:600 14px/1.55 Segoe UI,Tahoma,sans-serif !important;position:relative !important;z-index:99990 !important;}'
        . 'body.sige-admin-app .notice p,body.sige-admin-app div.updated p,body.sige-admin-app div.error p{color:#0f172a !important;margin:.25em 0 !important;font-weight:600 !important;line-height:1.55 !important;}'
        . 'body.sige-admin-app .notice strong,body.sige-admin-app .notice b{color:#0f172a !important;font-weight:900 !important;}'
        . 'body.sige-admin-app .notice code{background:#eef2ff !important;color:var(--sg-theme-primary-800,#3b2f8d) !important;border-radius:var(--radius-sm)!important;padding:3px 7px !important;font-weight:900 !important;}'
        . 'body.sige-admin-app .notice-success,body.sige-admin-app div.updated{border-left-color:#16a34a !important;background:#f0fdf4 !important;}'
        . 'body.sige-admin-app .notice-error,body.sige-admin-app div.error{border-left-color:#dc2626 !important;background:#fef2f2 !important;}'
        . 'body.sige-admin-app .notice-warning{border-left-color:#f59e0b !important;background:#fffbeb !important;}'
        . 'body.sige-admin-app .notice-info{border-left-color:#2563eb !important;background:var(--sg-theme-soft,#f1edff) !important;}'
        . 'body.sige-admin-app .notice .notice-dismiss{top:10px !important;right:10px !important;color:#334155 !important;}'
        . 'body.sige-admin-app .sige-hub-bill-inline{background:linear-gradient(135deg,#fff7ed,#ffffff) !important;border:1px solid #fed7aa !important;border-left:6px solid #f59e0b !important;color:#7c2d12 !important;border-radius:18px !important;margin:18px 24px !important;padding:16px 18px !important;box-shadow:0 18px 50px rgba(124,45,18,.10) !important;font:600 14px/1.55 Segoe UI,Tahoma,sans-serif !important;position:relative !important;z-index:99991 !important;}'
        . 'body.sige-admin-app .sige-hub-bill-inline.sige-danger{background:linear-gradient(135deg,#fef2f2,#ffffff) !important;border-color:#fecaca !important;border-left-color:#dc2626 !important;color:#7f1d1d !important;}'
        . 'body.sige-admin-app .sige-hub-bill-inline h3{margin:0 0 6px !important;color:inherit !important;font-size:var(--fs-md)!important;font-weight:950 !important;}'
        . 'body.sige-admin-app .sige-hub-bill-inline p{margin:0 !important;color:inherit !important;font-weight:650 !important;}'
        . 'body.sige-admin-app .sige-hub-bill-inline code{background:rgba(255,255,255,.72) !important;color:var(--sg-theme-primary-800,#3b2f8d) !important;border-radius:var(--radius-sm)!important;padding:3px 7px !important;font-weight:900 !important;}'
        . 'body.sige-admin-app #sige-page-transition{z-index:99999;}'
        . 'body.sige-admin-app #sige-top-progress{position:fixed;top:0;left:0;height:3px;width:100%;transform-origin:left center;transform:scaleX(0);background:linear-gradient(90deg,#2563eb 0%,#38bdf8 100%);box-shadow:0 0 18px rgba(37,99,235,.35);transition:transform .24s ease;z-index:100000;}'
        . 'body.sige-admin-app.sige-is-transitioning #sige-top-progress{transform:scaleX(.82);}'
        . '@keyframes sigeSpin{to{transform:rotate(360deg);}}'
        . '</style>';

    echo '<style id="sige-global-mobile-app-grade-v1211958">
    /* v12.11.9.58 - Mobile App Visual System Global PRO
       Globaliza as deliberações validadas no módulo Alunos para todo o SIGE, sem regras de negócio. */
    body.sige-admin-app .sg-mobile-global-bottom-nav{display:none!important;}
    @media (max-width:760px){
        html.wp-toolbar{padding-top:0!important;}
        body.sige-admin-app{background:#f4f7ff!important;overflow-x:hidden!important;}
        body.sige-admin-app #wpcontent,body.sige-admin-app #wpbody-content{padding-top:0!important;margin-top:0!important;}
        body.sige-admin-app #sige-layout.sg-product-pro-shell{display:block!important;min-height:100vh!important;background:linear-gradient(180deg,#f7faff 0%,#eef3ff 100%)!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-content{
            padding:0 14px 110px!important;
            min-height:100vh!important;
            background:linear-gradient(180deg,#f8fbff 0%,#eef3ff 100%)!important;
            overflow:visible!important;
        }
        body.sige-admin-app .sg-product-pro-shell .sg-app-page{padding:0!important;margin:0!important;max-width:none!important;width:100%!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar{
            position:sticky!important;top:0!important;z-index:10050!important;
            min-height:86px!important;height:86px!important;
            margin:0 -14px 18px!important;
            padding:14px 18px 14px!important;
            border-radius:0 0 28px 28px!important;
            border:0!important;
            background:linear-gradient(135deg,#00498f 0%,#0650a8 48%,#6e44e6 100%)!important;
            box-shadow:0 18px 40px rgba(8,42,104,.22)!important;
            display:flex!important;align-items:center!important;gap:var(--space-3)!important;color:#fff!important;
            overflow:hidden!important;
        }
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar .sg-app-hamburger{
            display:flex!important;align-items:center!important;justify-content:center!important;
            position:static!important;left:auto!important;top:auto!important;
            width:42px!important;height:42px!important;min-width:42px!important;
            border:0!important;border-radius:14px!important;background:transparent!important;color:#fff!important;box-shadow:none!important;
        }
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar .sg-app-hamburger svg{width:28px!important;height:28px!important;stroke:#fff!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-headings{display:flex!important;align-items:center!important;gap:9px!important;min-width:0!important;flex:1 1 auto!important;padding:0!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-logo{
            display:inline-flex!important;align-items:center!important;justify-content:center!important;
            width:40px!important;height:40px!important;min-width:40px!important;border-radius:13px!important;
            background:rgba(255,255,255,.18)!important;border:1px solid rgba(255,255,255,.30)!important;
            box-shadow:inset 0 0 0 1px rgba(255,255,255,.08),0 8px 18px rgba(0,0,0,.10)!important;overflow:hidden!important;
        }
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-logo img{display:block!important;width:100%!important;height:100%!important;object-fit:cover!important;border-radius:var(--radius-md)!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-logo.is-fallback:before{content:"S"!important;color:#fff!important;font-weight:950!important;font-size:17px!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-kicker{display:none!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-title{
            display:block!important;font-size:0!important;line-height:1!important;max-width:130px!important;min-width:0!important;overflow:hidden!important;white-space:nowrap!important;text-overflow:ellipsis!important;color:#fff!important;
        }
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-title:before{content:"SoftGenial"!important;font-family:var(--sg-theme-font-family,"Plus Jakarta Sans","Inter","Segoe UI",system-ui,sans-serif)!important;font-size:18px!important;font-weight:950!important;color:#fff!important;letter-spacing:-.05em!important;text-shadow:0 2px 12px rgba(0,0,0,.15)!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-meta{display:flex!important;align-items:center!important;gap:10px!important;flex:0 0 auto!important;width:auto!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-chip-year{display:inline-flex!important;align-items:center!important;justify-content:center!important;height:38px!important;padding:0 14px!important;border-radius:var(--radius-pill)!important;background:rgba(255,255,255,.14)!important;border:1px solid rgba(255,255,255,.28)!important;color:#fff!important;font-size:12px!important;font-weight:900!important;white-space:nowrap!important;box-shadow:none!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-user{padding:0!important;background:transparent!important;border:0!important;box-shadow:none!important;gap:0!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-user-meta{display:none!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-avatar{width:46px!important;height:46px!important;border-radius:50%!important;border:3px solid rgba(255,255,255,.90)!important;background:#fff!important;box-shadow:0 8px 20px rgba(4,21,55,.22)!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-sidebar{z-index:10070!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-overlay{z-index:10060!important;}

        body.sige-admin-app:not(.sige-view-alunos_lista):not(.sige-view-aluno_portal) .sg-mobile-global-bottom-nav{
            position:fixed!important;left:14px!important;right:14px!important;bottom:calc(10px + env(safe-area-inset-bottom,0px))!important;z-index:10040!important;
            min-height:76px!important;display:grid!important;grid-template-columns:repeat(auto-fit,minmax(0,1fr))!important;align-items:center!important;gap:2px!important;
            padding:8px 10px 9px!important;border-radius:24px!important;background:rgba(255,255,255,.97)!important;border:1px solid rgba(35,48,96,.08)!important;box-shadow:0 -12px 34px rgba(22,32,71,.16)!important;backdrop-filter:blur(12px)!important;-webkit-backdrop-filter:blur(12px)!important;
        }
        body.sige-admin-app:not(.sige-view-alunos_lista):not(.sige-view-aluno_portal) .sg-mobile-global-bottom-nav a,
        body.sige-admin-app:not(.sige-view-alunos_lista):not(.sige-view-aluno_portal) .sg-mobile-global-bottom-nav button{
            appearance:none!important;border:0!important;background:transparent!important;position:relative!important;display:flex!important;flex-direction:column!important;align-items:center!important;justify-content:center!important;gap:var(--space-1)!important;min-width:0!important;height:58px!important;color:#626b80!important;text-decoration:none!important;font-size:var(--fs-xs)!important;font-weight:800!important;font-family:inherit!important;cursor:pointer!important;
        }
        body.sige-admin-app:not(.sige-view-alunos_lista):not(.sige-view-aluno_portal) .sg-mobile-global-bottom-nav svg{width:25px!important;height:25px!important;stroke:currentColor!important;fill:none!important;}
        body.sige-admin-app:not(.sige-view-alunos_lista):not(.sige-view-aluno_portal) .sg-mobile-global-bottom-nav .is-active{color:#0a4fd7!important;font-weight:950!important;}
        body.sige-admin-app:not(.sige-view-alunos_lista):not(.sige-view-aluno_portal) .sg-mobile-global-bottom-nav .is-active:before{content:""!important;position:absolute!important;top:-9px!important;width:70px!important;height:4px!important;border-radius:var(--radius-pill)!important;background:#0a4fd7!important;box-shadow:0 5px 12px rgba(10,79,215,.25)!important;}

        body.sige-admin-app .sg-app-page .sige-card,
        body.sige-admin-app .sg-app-page .sige-stat-card,
        body.sige-admin-app .sg-app-page .sg-kpi-card,
        body.sige-admin-app .sg-app-page .sige-table-card,
        body.sige-admin-app .sg-app-page .sige-panel{
            border-radius:var(--radius-xl)!important;border:1px solid rgba(35,48,96,.08)!important;background:#fff!important;box-shadow:0 14px 34px rgba(31,44,98,.075)!important;
        }
        body.sige-admin-app .sg-app-page .sige-toolbar,
        body.sige-admin-app .sg-app-page .sg-toolbar,
        body.sige-admin-app .sg-app-page form.sige-toolbar{border-radius:var(--radius-xl)!important;box-shadow:0 14px 34px rgba(31,44,98,.065)!important;}
        body.sige-admin-app .sg-app-page input:not([type="checkbox"]):not([type="radio"]),
        body.sige-admin-app .sg-app-page select,
        body.sige-admin-app .sg-app-page textarea{min-height:46px;border-radius:var(--radius-lg)!important;}
        body.sige-admin-app .sg-app-page .button,
        body.sige-admin-app .sg-app-page .sige-btn,
        body.sige-admin-app .sg-app-page .sige-btn-toolbar{min-height:44px;border-radius:14px!important;}
        body.sige-admin-app .sg-app-page table{font-size:var(--fs-sm);}
    }
    @media (max-width:430px){
        body.sige-admin-app .sg-product-pro-shell .sg-app-content{padding-left:12px!important;padding-right:12px!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar{margin-left:-12px!important;margin-right:-12px!important;padding-left:16px!important;padding-right:16px!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-chip-year{display:none!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-title:before{font-size:17px!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-avatar{width:42px!important;height:42px!important;}
    }
    </style>';

    echo '<style id="sige-mobile-header-permission-nav-v1211960">
    /* v12.11.9.60 - Mobile Header Consistency & Permission Nav PRO
       Corrige folga superior/curvas da topbar global e reforça navegação inferior consciente de permissões. */
    @media (max-width:760px){
        html.wp-toolbar,
        body.wp-admin,
        body.sige-admin-app,
        body.sige-admin-app.admin-bar,
        body.sige-admin-app #wpwrap,
        body.sige-admin-app #wpcontent,
        body.sige-admin-app #wpbody,
        body.sige-admin-app #wpbody-content,
        body.sige-admin-app #sige-layout.sg-product-pro-shell,
        body.sige-admin-app .sg-product-pro-shell,
        body.sige-admin-app .sg-product-pro-shell .sg-app-content{
            margin-top:0!important;
            padding-top:0!important;
        }
        body.sige-admin-app #wpadminbar{display:none!important;height:0!important;min-height:0!important;}
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar{
            top:0!important;
            margin-top:0!important;
            border-bottom-left-radius:28px!important;
            border-bottom-right-radius:28px!important;
            border-top-left-radius:0!important;
            border-top-right-radius:0!important;
            clip-path:inset(0 0 0 0 round 0 0 28px 28px)!important;
            overflow:hidden!important;
        }
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-logo img{
            max-width:100%!important;
            max-height:100%!important;
            object-fit:contain!important;
        }
        body.sige-admin-app:not(.sige-view-alunos_lista):not(.sige-view-aluno_portal) .sg-mobile-global-bottom-nav{
            grid-template-columns:repeat(auto-fit,minmax(72px,1fr))!important;
        }
        body.sige-admin-app:not(.sige-view-alunos_lista):not(.sige-view-aluno_portal) .sg-mobile-global-bottom-nav .is-active:before{
            max-width:72px!important;
        }
    }
    </style>';



echo '<style id="sige-mobile-fine-tune">
/* v12.11.9.61 - Smoke Hardening Mobile Global PRO
   Pós-smoke: mantém a navegação mobile consistente, reforça acessibilidade e evita micro-regressões visuais. */
@media (max-width:760px){
    body.sige-admin-app .sg-product-pro-shell .sg-app-topbar{
        transform:translateZ(0)!important;
        -webkit-mask-image:-webkit-radial-gradient(white,black)!important;
    }
    body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-logo{
        flex:0 0 40px!important;
    }
    body.sige-admin-app:not(.sige-view-alunos_lista):not(.sige-view-aluno_portal) .sg-mobile-global-bottom-nav{
        min-height:78px!important;
        padding-bottom:calc(9px + env(safe-area-inset-bottom,0px))!important;
    }
    body.sige-admin-app:not(.sige-view-alunos_lista):not(.sige-view-aluno_portal) .sg-mobile-global-bottom-nav a:focus-visible,
    body.sige-admin-app:not(.sige-view-alunos_lista):not(.sige-view-aluno_portal) .sg-mobile-global-bottom-nav button:focus-visible{
        outline:3px solid rgba(10,79,215,.28)!important;
        outline-offset:2px!important;
        border-radius:18px!important;
    }
    body.sige-admin-app .sg-app-page .sige-modal,
    body.sige-admin-app .sg-app-page .sg-modal{
        overscroll-behavior:contain!important;
    }
    body.sige-admin-app .sg-app-page .sige-table-wrap,
    body.sige-admin-app .sg-app-page .table-responsive{
        -webkit-overflow-scrolling:touch!important;
    }
}
@media (max-width:380px){
    body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-title:before{font-size:var(--fs-md)!important;}
    body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-logo{width:36px!important;height:36px!important;flex-basis:36px!important;}
}
</style>';

echo '<script id="sige-mobile-nav-accessibility-v1211961">
(function(){
  document.addEventListener("click", function(ev){
    var btn = ev.target && ev.target.closest ? ev.target.closest(".sg-mobile-global-bottom-nav button[aria-controls=\"sige-sidebar\"], .sige-mobile-bottom-nav button[aria-controls=\"sige-sidebar\"]") : null;
    if(!btn) return;
    setTimeout(function(){
      var side = document.getElementById("sige-sidebar");
      btn.setAttribute("aria-expanded", side && side.classList.contains("open") ? "true" : "false");
    }, 0);
  }, true);
  document.addEventListener("click", function(ev){
    if(!ev.target || !ev.target.closest) return;
    if(ev.target.closest("#sige-overlay") || ev.target.closest(".sige-menu-item")){
      var btns = document.querySelectorAll(".sg-mobile-global-bottom-nav button[aria-controls=\"sige-sidebar\"], .sige-mobile-bottom-nav button[aria-controls=\"sige-sidebar\"]");
      btns.forEach(function(b){ b.setAttribute("aria-expanded", "false"); });
    }
  }, true);
})();
</script>';

    echo <<<'HTML'
<style id="sige-mobile-ux-compliance-v1211959">
/* v12.11.9.59 - Mobile UX Compliance Global PRO
   Camada transversal de consistência mobile: modais, tabelas, toolbars, formulários, estados e alvos tácteis.
   Não altera regras de negócio, AJAX, permissões, finanças, notas, importação ou base de dados. */
@media (max-width:760px){
    body.sige-admin-app{
        --sg-mobile-page-bg:#f4f7ff;
        --sg-mobile-card-bg:#ffffff;
        --sg-mobile-border:rgba(35,48,96,.08);
        --sg-mobile-shadow:0 14px 34px rgba(31,44,98,.075);
        --sg-mobile-radius:22px;
        --sg-mobile-primary:#06439c;
        --sg-mobile-primary-2:#6e44e6;
        --sg-mobile-text:#111827;
        --sg-mobile-muted:#5f6a82;
        touch-action:manipulation;
    }
    body.sige-admin-app .sg-app-page,
    body.sige-admin-app .sg-app-page *{box-sizing:border-box;}
    body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-logo{background:#fff!important;padding:3px!important;}
    body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-logo img{object-fit:contain!important;border-radius:10px!important;background:#fff!important;}
    body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-logo.is-fallback{background:rgba(255,255,255,.16)!important;padding:0!important;}
    body.sige-admin-app .sg-product-pro-shell .sg-app-content{padding-bottom:calc(112px + env(safe-area-inset-bottom,0px))!important;}
    body.sige-admin-app .sg-app-page > .wrap,
    body.sige-admin-app .sg-app-page .wrap{width:100%!important;max-width:none!important;margin:0!important;padding:0!important;}
    body.sige-admin-app .sg-app-page h1,
    body.sige-admin-app .sg-app-page h2,
    body.sige-admin-app .sg-app-page h3{letter-spacing:-.035em;}

    body.sige-admin-app .sg-app-page .sige-card,
    body.sige-admin-app .sg-app-page .sg-card,
    body.sige-admin-app .sg-app-page .sige-panel,
    body.sige-admin-app .sg-app-page .sg-panel,
    body.sige-admin-app .sg-app-page .sige-box,
    body.sige-admin-app .sg-app-page .sg-box,
    body.sige-admin-app .sg-app-page .postbox,
    body.sige-admin-app .sg-app-page .sige-table-card,
    body.sige-admin-app .sg-app-page .sg-table-card{
        max-width:100%!important;
        border-radius:var(--sg-mobile-radius)!important;
        border:1px solid var(--sg-mobile-border)!important;
        background:var(--sg-mobile-card-bg)!important;
        box-shadow:var(--sg-mobile-shadow)!important;
        overflow:hidden!important;
    }
    body.sige-admin-app .sg-app-page .postbox{padding:0!important;}
    body.sige-admin-app .sg-app-page .inside{padding:14px!important;margin:0!important;}

    body.sige-admin-app .sg-app-page .sige-hero,
    body.sige-admin-app .sg-app-page .sg-hero,
    body.sige-admin-app .sg-app-page .sige-page-hero,
    body.sige-admin-app .sg-app-page .sg-page-hero,
    body.sige-admin-app .sg-app-page .sige-header,
    body.sige-admin-app .sg-app-page .sg-header{
        max-width:100%!important;
        border-radius:24px!important;
        overflow:hidden!important;
        margin:0 0 14px!important;
    }

    body.sige-admin-app .sg-app-page .sige-toolbar,
    body.sige-admin-app .sg-app-page .sg-toolbar,
    body.sige-admin-app .sg-app-page form.sige-toolbar,
    body.sige-admin-app .sg-app-page .sige-filters,
    body.sige-admin-app .sg-app-page .sg-filters,
    body.sige-admin-app .sg-app-page .tablenav,
    body.sige-admin-app .sg-app-page .actions{
        display:flex!important;
        flex-direction:column!important;
        align-items:stretch!important;
        gap:10px!important;
        width:100%!important;
        max-width:100%!important;
        margin:0 0 14px!important;
        padding:var(--space-3)!important;
        border-radius:var(--radius-xl)!important;
        border:1px solid var(--sg-mobile-border)!important;
        background:#fff!important;
        box-shadow:0 12px 30px rgba(31,44,98,.06)!important;
        height:auto!important;
        overflow:visible!important;
    }
    body.sige-admin-app .sg-app-page .tablenav{clear:both!important;}
    body.sige-admin-app .sg-app-page .sige-toolbar > *,
    body.sige-admin-app .sg-app-page .sg-toolbar > *,
    body.sige-admin-app .sg-app-page .sige-filters > *,
    body.sige-admin-app .sg-app-page .sg-filters > *,
    body.sige-admin-app .sg-app-page .tablenav > *,
    body.sige-admin-app .sg-app-page .actions > *{max-width:100%!important;}

    body.sige-admin-app .sg-app-page input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),
    body.sige-admin-app .sg-app-page select,
    body.sige-admin-app .sg-app-page textarea{
        width:100%!important;
        max-width:100%!important;
        min-height:46px!important;
        border-radius:var(--radius-lg)!important;
        font-size:var(--fs-md)!important;
        line-height:1.35!important;
        padding:10px 12px!important;
    }
    body.sige-admin-app .sg-app-page input[type="file"]{padding:10px!important;background:#fff!important;}
    body.sige-admin-app .sg-app-page textarea{min-height:110px!important;}
    body.sige-admin-app .sg-app-page label{font-weight:800;color:#243047;}

    body.sige-admin-app .sg-app-page .button,
    body.sige-admin-app .sg-app-page button,
    body.sige-admin-app .sg-app-page .sige-btn,
    body.sige-admin-app .sg-app-page .sg-btn,
    body.sige-admin-app .sg-app-page .sige-btn-toolbar,
    body.sige-admin-app .sg-app-page .page-title-action,
    body.sige-admin-app .sg-app-page input[type="submit"]{
        min-height:44px!important;
        border-radius:14px!important;
        max-width:100%;
    }
    body.sige-admin-app .sg-app-page .sige-toolbar .button,
    body.sige-admin-app .sg-app-page .sg-toolbar .button,
    body.sige-admin-app .sg-app-page .sige-toolbar button,
    body.sige-admin-app .sg-app-page .sg-toolbar button,
    body.sige-admin-app .sg-app-page .sige-toolbar a,
    body.sige-admin-app .sg-app-page .sg-toolbar a,
    body.sige-admin-app .sg-app-page .tablenav .button,
    body.sige-admin-app .sg-app-page .actions .button{width:100%!important;justify-content:center!important;text-align:center!important;}

    body.sige-admin-app .sg-app-page .sige-grid,
    body.sige-admin-app .sg-app-page .sg-grid,
    body.sige-admin-app .sg-app-page .sige-form-grid,
    body.sige-admin-app .sg-app-page .sg-form-grid,
    body.sige-admin-app .sg-app-page .form-grid,
    body.sige-admin-app .sg-app-page .sige-columns,
    body.sige-admin-app .sg-app-page .sg-columns,
    body.sige-admin-app .sg-app-page .sige-two-col,
    body.sige-admin-app .sg-app-page .sg-two-col{
        display:grid!important;
        grid-template-columns:1fr!important;
        gap:var(--space-3)!important;
    }
    body.sige-admin-app .sg-app-page .sige-stats-grid,
    body.sige-admin-app .sg-app-page .sg-stats-grid,
    body.sige-admin-app .sg-app-page .sige-kpi-grid,
    body.sige-admin-app .sg-app-page .sg-kpi-grid,
    body.sige-admin-app .sg-app-page .sige-dashboard-grid{
        display:grid!important;
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        gap:var(--space-3)!important;
    }
    body.sige-admin-app .sg-app-page .sige-stat-card,
    body.sige-admin-app .sg-app-page .sg-stat-card,
    body.sige-admin-app .sg-app-page .sige-kpi-card,
    body.sige-admin-app .sg-app-page .sg-kpi-card{min-width:0!important;}

    body.sige-admin-app .sg-app-page .sg-mobile-table-scroll{
        width:100%!important;
        max-width:100%!important;
        overflow-x:auto!important;
        -webkit-overflow-scrolling:touch!important;
        border-radius:18px!important;
        border:1px solid var(--sg-mobile-border)!important;
        background:#fff!important;
        box-shadow:0 12px 30px rgba(31,44,98,.06)!important;
        margin:var(--space-3) 0!important;
    }
    body.sige-admin-app .sg-app-page .sg-mobile-table-scroll table,
    body.sige-admin-app .sg-app-page table.widefat,
    body.sige-admin-app .sg-app-page table.wp-list-table{
        min-width:720px!important;
        width:100%!important;
        margin:0!important;
        border:0!important;
        box-shadow:none!important;
        border-collapse:separate!important;
        border-spacing:0!important;
        font-size:var(--fs-sm)!important;
    }
    body.sige-admin-app .sg-app-page table th,
    body.sige-admin-app .sg-app-page table td{white-space:nowrap!important;vertical-align:middle!important;padding:10px 12px!important;}
    body.sige-admin-app .sg-app-page table td:last-child,
    body.sige-admin-app .sg-app-page table th:last-child{padding-right:14px!important;}

    body.sige-admin-app .sige-modal,
    body.sige-admin-app .sg-modal,
    body.sige-admin-app [id^="modal-"]{
        padding:var(--space-2)!important;
        align-items:center!important;
        justify-content:center!important;
        overscroll-behavior:contain!important;
    }
    body.sige-admin-app .sige-modal-content,
    body.sige-admin-app .sg-modal-content,
    body.sige-admin-app .modal-content{
        width:calc(100vw - 16px)!important;
        max-width:calc(100vw - 16px)!important;
        height:auto!important;
        max-height:calc(100dvh - 16px)!important;
        border-radius:var(--radius-xl)!important;
        display:flex!important;
        flex-direction:column!important;
        overflow:hidden!important;
        margin:0!important;
    }
    body.sige-admin-app .sige-modal-header,
    body.sige-admin-app .sg-modal-header,
    body.sige-admin-app .modal-header{
        flex:0 0 auto!important;
        padding:var(--space-4)!important;
        gap:var(--space-3)!important;
        min-height:0!important;
    }
    body.sige-admin-app .sige-modal-header h2,
    body.sige-admin-app .sg-modal-header h2,
    body.sige-admin-app .modal-header h2{font-size:var(--fs-lg)!important;line-height:1.15!important;margin:0!important;}
    body.sige-admin-app .sige-modal-body,
    body.sige-admin-app .sg-modal-body,
    body.sige-admin-app .modal-body{
        flex:1 1 auto!important;
        min-height:0!important;
        overflow:auto!important;
        -webkit-overflow-scrolling:touch!important;
        padding:var(--space-4)!important;
    }
    body.sige-admin-app .sige-modal-footer,
    body.sige-admin-app .sg-modal-footer,
    body.sige-admin-app .modal-footer{
        flex:0 0 auto!important;
        position:sticky!important;
        bottom:0!important;
        z-index:3!important;
        display:flex!important;
        flex-direction:column-reverse!important;
        gap:10px!important;
        padding:12px 16px calc(12px + env(safe-area-inset-bottom,0px))!important;
        background:#fff!important;
        border-top:1px solid rgba(35,48,96,.08)!important;
        box-shadow:0 -10px 22px rgba(31,44,98,.08)!important;
    }
    body.sige-admin-app .sige-modal-footer .button,
    body.sige-admin-app .sige-modal-footer button,
    body.sige-admin-app .sg-modal-footer .button,
    body.sige-admin-app .sg-modal-footer button,
    body.sige-admin-app .modal-footer .button,
    body.sige-admin-app .modal-footer button{width:100%!important;justify-content:center!important;}
    body.sige-admin-app.sige-modal-open{overflow:hidden!important;}

    body.sige-admin-app .notice,
    body.sige-admin-app .updated,
    body.sige-admin-app .error,
    body.sige-admin-app .is-dismissible,
    body.sige-admin-app .sige-alert,
    body.sige-admin-app .sg-alert,
    body.sige-admin-app .sige-empty,
    body.sige-admin-app .sg-empty,
    body.sige-admin-app .sige-empty-state,
    body.sige-admin-app .sg-empty-state{
        max-width:100%!important;
        margin:10px 0!important;
        border-radius:18px!important;
        box-shadow:0 10px 24px rgba(31,44,98,.06)!important;
    }
    body.sige-admin-app a:focus-visible,
    body.sige-admin-app button:focus-visible,
    body.sige-admin-app input:focus-visible,
    body.sige-admin-app select:focus-visible,
    body.sige-admin-app textarea:focus-visible,
    body.sige-admin-app summary:focus-visible{
        outline:3px solid rgba(37,99,235,.35)!important;
        outline-offset:3px!important;
    }
}
@media (max-width:430px){
    body.sige-admin-app .sg-app-page .sige-stats-grid,
    body.sige-admin-app .sg-app-page .sg-stats-grid,
    body.sige-admin-app .sg-app-page .sige-kpi-grid,
    body.sige-admin-app .sg-app-page .sg-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:10px!important;}
    body.sige-admin-app .sg-app-page .sige-stat-card,
    body.sige-admin-app .sg-app-page .sg-stat-card,
    body.sige-admin-app .sg-app-page .sige-kpi-card,
    body.sige-admin-app .sg-app-page .sg-kpi-card{padding:var(--space-3)!important;}
    body.sige-admin-app .sg-app-page table.widefat,
    body.sige-admin-app .sg-app-page table.wp-list-table,
    body.sige-admin-app .sg-app-page .sg-mobile-table-scroll table{min-width:640px!important;}
}
@media print{
    body.sige-admin-app .sg-mobile-global-bottom-nav{display:none!important;}
    body.sige-admin-app .sg-app-page .sg-mobile-table-scroll{overflow:visible!important;border:0!important;box-shadow:none!important;}
}
</style>
<script id="sige-mobile-ux-compliance-v1211959-js">
(function(){
    var mq = window.matchMedia ? window.matchMedia('(max-width: 760px)') : null;
    function isMobile(){ return !mq || mq.matches; }
    function closest(el, sel){ return el && el.closest ? el.closest(sel) : null; }
    function enhanceMobile(){
        if (!document.body || !isMobile()) return;
        document.body.classList.add('sige-mobile-ux-compliance-ready');
        var page = document.querySelector('.sg-app-page');
        if (!page) return;
        page.querySelectorAll('table').forEach(function(table){
            if (!table || table.dataset.sgMobileWrapped === '1') return;
            if (closest(table, '.sg-mobile-table-scroll,.sige-no-mobile-wrap,.sige-print-area,.sige-recibo,.sige-document-preview')) return;
            var parent = table.parentNode;
            if (!parent || parent.nodeType !== 1) return;
            var wrapper = document.createElement('div');
            wrapper.className = 'sg-mobile-table-scroll';
            wrapper.setAttribute('role', 'region');
            wrapper.setAttribute('aria-label', 'Tabela com deslocamento horizontal');
            parent.insertBefore(wrapper, table);
            wrapper.appendChild(table);
            table.dataset.sgMobileWrapped = '1';
        });
        page.querySelectorAll('.sige-toolbar,.sg-toolbar,form.sige-toolbar,.sige-filters,.sg-filters,.tablenav').forEach(function(el){
            el.classList.add('sg-mobile-compliance-toolbar');
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enhanceMobile);
    } else {
        enhanceMobile();
    }
    window.addEventListener('orientationchange', function(){ window.setTimeout(enhanceMobile, 250); });
    if (mq && mq.addEventListener) mq.addEventListener('change', enhanceMobile);
})();
</script>
HTML;


echo <<<'HTML'
<style id="sige-mobile-deep-smoke-hardening-v1211962">
/* v12.11.9.62 - Deep Smoke Hardening Áreas Não Cobertas Mobile PRO
   Cobre áreas que ainda não tinham sido profundamente verificadas: modais customizados, tabelas injectadas por AJAX e micro-estados mobile globais. */
@media (max-width:760px){
    body.sige-admin-app .sg-modal-backdrop,
    body.sige-admin-app .sg-expense-modal-backdrop,
    body.sige-admin-app .sg-extracts-modal-overlay,
    body.sige-admin-app .sige-modal-overlay,
    body.sige-admin-app .wppc-modal-bg{
        padding:10px!important;
        align-items:flex-end!important;
        justify-content:center!important;
        overscroll-behavior:contain!important;
    }
    body.sige-admin-app .sige-lanc-modal,
    body.sige-admin-app .sg-paypro-modal,
    body.sige-admin-app .sg-paypro-result-modal,
    body.sige-admin-app .sg-plans-modal-card,
    body.sige-admin-app .sg-inspro-modal-box,
    body.sige-admin-app .sg-generator-modal-card,
    body.sige-admin-app .sg-expense-modal,
    body.sige-admin-app .sg-extracts-modal-box,
    body.sige-admin-app .sige-modal-box,
    body.sige-admin-app .wppc-modal{
        width:100%!important;
        max-width:100%!important;
        max-height:calc(100vh - 22px)!important;
        max-height:calc(100dvh - 22px)!important;
        border-radius:24px 24px 18px 18px!important;
        overflow:hidden!important;
        display:flex!important;
        flex-direction:column!important;
        box-sizing:border-box!important;
    }
    body.sige-admin-app .sige-lanc-modal-header,
    body.sige-admin-app .sg-plans-modal-head,
    body.sige-admin-app .sg-inspro-modal-head,
    body.sige-admin-app .sg-extracts-modal-hdr,
    body.sige-admin-app .sige-modal-hdr,
    body.sige-admin-app .wppc-modal-h{
        flex:0 0 auto!important;
    }
    body.sige-admin-app .sige-lanc-modal-body,
    body.sige-admin-app .sg-plans-modal-body,
    body.sige-admin-app .sg-inspro-modal-body,
    body.sige-admin-app .sg-extracts-modal-body,
    body.sige-admin-app .wppc-modal-body{
        flex:1 1 auto!important;
        min-height:0!important;
        overflow:auto!important;
        -webkit-overflow-scrolling:touch!important;
    }
    body.sige-admin-app .sige-lanc-modal-footer,
    body.sige-admin-app .sg-plans-modal-footer,
    body.sige-admin-app .sg-inspro-modal-footer,
    body.sige-admin-app .sg-extracts-modal-ftr,
    body.sige-admin-app .sige-modal-ftr{
        flex:0 0 auto!important;
        position:sticky!important;
        bottom:0!important;
        background:#fff!important;
        border-top:1px solid rgba(35,48,96,.08)!important;
        z-index:2!important;
    }
    body.sige-admin-app .sg-app-page .sg-mobile-table-scroll table[data-sg-mobile-wrapped="1"]{
        margin:0!important;
    }
    body.sige-admin-app .sg-app-page .sg-mobile-compliance-toolbar[hidden],
    body.sige-admin-app .sg-app-page [hidden]{
        display:none!important;
    }
    body.sige-admin-app .sg-app-page .sg-mobile-compliance-toolbar .button[disabled],
    body.sige-admin-app .sg-app-page .sg-mobile-compliance-toolbar button[disabled],
    body.sige-admin-app .sg-app-page .sg-mobile-compliance-toolbar input[type="submit"][disabled]{
        opacity:.55!important;
        cursor:not-allowed!important;
    }
    body.sige-admin-app .sg-app-page .sg-mobile-table-scroll:focus-within{
        outline:3px solid rgba(37,99,235,.18)!important;
        outline-offset:3px!important;
    }
}
</style>
<script id="sige-mobile-deep-smoke-hardening-v1211962-js">
(function(){
    var mq = window.matchMedia ? window.matchMedia('(max-width: 760px)') : null;
    function isMobile(){ return !mq || mq.matches; }
    function closest(el, sel){ return el && el.closest ? el.closest(sel) : null; }
    var scheduled = false;
    function enhance(){
        if (!isMobile()) return;
        var page = document.querySelector('.sg-app-page');
        if (!page) return;
        page.querySelectorAll('table').forEach(function(table){
            if (!table || table.dataset.sgMobileWrapped === '1') return;
            if (closest(table, '.sg-mobile-table-scroll,.sige-no-mobile-wrap,.sige-print-area,.sige-recibo,.sige-document-preview,.wp-editor-container')) return;
            var parent = table.parentNode;
            if (!parent || parent.nodeType !== 1) return;
            var wrapper = document.createElement('div');
            wrapper.className = 'sg-mobile-table-scroll';
            wrapper.setAttribute('role', 'region');
            wrapper.setAttribute('aria-label', 'Tabela com deslocamento horizontal');
            parent.insertBefore(wrapper, table);
            wrapper.appendChild(table);
            table.dataset.sgMobileWrapped = '1';
            table.setAttribute('data-sg-mobile-wrapped', '1');
        });
        page.querySelectorAll('.sige-toolbar,.sg-toolbar,form.sige-toolbar,.sige-filters,.sg-filters,.tablenav').forEach(function(el){
            if (el) el.classList.add('sg-mobile-compliance-toolbar');
        });
    }
    function scheduleEnhance(){
        if (scheduled) return;
        scheduled = true;
        window.setTimeout(function(){ scheduled = false; enhance(); }, 80);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', enhance); else enhance();
    if ('MutationObserver' in window) {
        var obs = new MutationObserver(function(muts){
            if (!isMobile()) return;
            for (var i=0;i<muts.length;i++){
                if (muts[i].addedNodes && muts[i].addedNodes.length){ scheduleEnhance(); break; }
            }
        });
        document.addEventListener('DOMContentLoaded', function(){
            var page = document.querySelector('.sg-app-page');
            if (page) obs.observe(page, {childList:true, subtree:true});
        });
    }
    document.addEventListener('keydown', function(ev){
        if (ev.key !== 'Escape') return;
        var side = document.getElementById('sige-sidebar');
        var overlay = document.getElementById('sige-overlay');
        if (side && side.classList.contains('open')) {
            side.classList.remove('open');
            if (overlay) overlay.classList.remove('show');
            document.body.classList.remove('sg-app-menu-open');
            document.querySelectorAll('.sg-mobile-global-bottom-nav button[aria-controls="sige-sidebar"], .sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]').forEach(function(btn){ btn.setAttribute('aria-expanded','false'); });
        }
    }, true);
})();
</script>
HTML;

});

// v12.11.9.63 - Overrides tardios para Tablet UX.
// Necessário porque as camadas históricas do admin_head escondem logo/bottom-nav por defeito.
add_action('admin_head', function () {
    if (!is_admin()) return;
    $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
    if ($page !== 'sige-app') return;
    ?>
    <style id="sige-mobile-tablet-ux-late-v1211963">
    @media (min-width:761px) and (max-width:1100px){
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-logo{display:inline-flex!important;}
        body.sige-admin-app:not(.sige-view-alunos_lista):not(.sige-view-aluno_portal) .sg-mobile-global-bottom-nav{display:grid!important;}
    }
    </style>
    <?php
}, 99);

// v12.16.0 RC7 - Mobile Header Hotfix.
// Escopo: apenas composicao visual mobile do header global.
// Nao altera rotas, permissoes, dados, financeiro, academico, AJAX ou REST.
add_action('admin_head', function () {
    if (!is_admin()) return;
    $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
    if ($page !== 'sige-app') return;
    ?>
    <style id="sige-mobile-header-professor-rc7-v121600">
    @media (max-width:760px){
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar{
            display:grid!important;
            grid-template-columns:var(--space-10) minmax(0,1fr) var(--space-10)!important;
            align-items:center!important;
            gap:var(--space-2)!important;
            min-height:calc(var(--space-10) + var(--space-8))!important;
            height:auto!important;
            padding:var(--space-3) var(--space-4)!important;
            width:auto!important;
            max-width:none!important;
            overflow:hidden!important;
        }
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar .sg-app-hamburger{
            grid-column:1!important;
            width:var(--space-10)!important;
            height:var(--space-10)!important;
            min-width:var(--space-10)!important;
            flex-basis:var(--space-10)!important;
        }
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-headings{
            grid-column:2!important;
            min-width:0!important;
            max-width:100%!important;
            overflow:hidden!important;
            display:flex!important;
            align-items:center!important;
            gap:var(--space-2)!important;
            padding:0!important;
        }
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-logo{
            width:var(--space-10)!important;
            height:var(--space-10)!important;
            min-width:var(--space-10)!important;
            flex:0 0 var(--space-10)!important;
        }
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-title{
            max-width:100%!important;
            min-width:0!important;
            overflow:hidden!important;
            white-space:nowrap!important;
            text-overflow:ellipsis!important;
        }
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-title:before{
            font-size:var(--fs-md)!important;
            font-weight:800!important;
            letter-spacing:0!important;
        }
        body.sige-admin-app .sg-product-pro-shell .sg-gsearch,
        body.sige-admin-app .sg-product-pro-shell .sg-app-chip-year,
        body.sige-admin-app .sg-product-pro-shell .sg-app-user-meta{
            display:none!important;
        }
        body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-meta{
            grid-column:3!important;
            justify-self:end!important;
            display:flex!important;
            align-items:center!important;
            justify-content:flex-end!important;
            gap:0!important;
            width:var(--space-10)!important;
            min-width:var(--space-10)!important;
            max-width:var(--space-10)!important;
            overflow:hidden!important;
        }
        body.sige-admin-app .sg-product-pro-shell .sg-app-user{
            width:var(--space-10)!important;
            height:var(--space-10)!important;
            min-width:var(--space-10)!important;
            padding:0!important;
            background:transparent!important;
            border:0!important;
            box-shadow:none!important;
        }
        body.sige-admin-app .sg-product-pro-shell .sg-app-avatar{
            width:var(--space-10)!important;
            height:var(--space-10)!important;
            border:var(--space-1) solid var(--color-white)!important;
            box-shadow:var(--shadow-sm)!important;
            background:var(--color-white)!important;
        }
    }
    </style>
    <?php
}, 100);

// ============================================================================
// ADMIN ASSETS (CSS, JS, Nonces)
// ============================================================================
add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'sige-app') === false) return;

    // v12.15.13 - Portal enxuto. O encarregado/aluno na Pagina do Aluno nao tem
    // uploader nem AJAX; evitar wp_enqueue_media() poupa toda a maquinaria de
    // media do WordPress (dezenas de scripts, CSS e os templates impressos no
    // rodape). Staff que inspecciona o portal mantem a shell completa. Reversivel
    // por option sige_portal_lean_assets_v121513_enabled = 0. Degradacao segura:
    // se a politica nao existir, $sige_portal_lean fica falso e a media carrega.
    $sige_portal_lean = function_exists('sige_portal_lean_is_active') && sige_portal_lean_is_active();

    if (!$sige_portal_lean) {
        wp_enqueue_media();
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // DESIGN SYSTEM CSS - SoftGenial v1.0 (SaaS-grade UI)
    // ═══════════════════════════════════════════════════════════════════════
    // v12.15.14 - Fase 2: split chrome vs views. No portal enxuto, servimos a folha
    // de chrome (~109 KB) em vez do style.css completo (~302 KB), poupando os ~194 KB
    // de modulos de view (Financeiro, Pagamentos por Turma, Extractos) que o portal
    // nunca renderiza. Mesmo handle 'sige-design-system' para que os dependentes
    // resolvam.
    // v12.15.16 - Fase 2 fechada em producao apos prova visual em browser: a flag
    // sige_portal_chrome_css_v121514_enabled passa a LIGADA por defeito. Continua
    // reversivel: definir a option a '0' repoe o style.css completo no portal.
    $sige_portal_chrome = $sige_portal_lean
        && function_exists('get_option')
        && get_option('sige_portal_chrome_css_v121514_enabled', '1') === '1'
        && is_file(SIGE_PATH . 'assets/style-portal-chrome.css');

    if ($sige_portal_chrome) {
        wp_enqueue_style(
            'sige-design-system',
            SIGE_URL . 'assets/style-portal-chrome.css',
            [],
            SIGE_VERSION . '.' . filemtime(SIGE_PATH . 'assets/style-portal-chrome.css')
        );
    } else {
        wp_enqueue_style(
            'sige-design-system',
            SIGE_URL . 'assets/style.css',
            [],
            SIGE_VERSION
        );
    }

    // v12.11.9.63 - Jornada Mobile + Tablet UX PRO: camada adaptativa carregada após o design system.
    if (file_exists(SIGE_PATH . 'assets/mobile-tablet-ux.css')) {
        wp_enqueue_style(
            'sige-mobile-tablet-ux',
            SIGE_URL . 'assets/mobile-tablet-ux.css',
            ['sige-design-system'],
            SIGE_VERSION
        );
    }

    // v12.12.46 - Fase 4: utilitarios de apresentacao para a migracao dos estilos inline.
    if (file_exists(SIGE_PATH . 'assets/sige-utilities.css')) {
        wp_enqueue_style(
            'sige-utilities',
            SIGE_URL . 'assets/sige-utilities.css',
            ['sige-design-system'],
            SIGE_VERSION
        );
    }
    
    // Script core com nonces AJAX
    wp_register_script('sige-core-ajax', false, [], false, true);
    wp_enqueue_script('sige-core-ajax');
    
    // Auto-inject nonce CSRF em todos os pedidos POST
    wp_add_inline_script('sige-core-ajax', "jQuery.ajaxPrefilter(function(o){if(o.type&&o.type.toUpperCase()==='POST'&&typeof sigeAjax!=='undefined'){if(typeof o.data==='string'){if(o.data.indexOf('_sige_nonce_g=')===-1)o.data+='&_sige_nonce_g='+encodeURIComponent(sigeAjax.nonce_global);}else if(o.data instanceof FormData){if(!o.data.has('_sige_nonce_g'))o.data.append('_sige_nonce_g',sigeAjax.nonce_global);}else if(o.data&&typeof o.data==='object'){if(!o.data._sige_nonce_g)o.data._sige_nonce_g=sigeAjax.nonce_global;}}});");
    
    wp_localize_script('sige-core-ajax', 'sigeAjax', [
        'url'          => admin_url('admin-ajax.php'),
        'nonce'        => wp_create_nonce('sige_testar_wpp_nonce'),
        'nonce_cfg'    => wp_create_nonce('sige_cfg_global'),
        'nonce_alunos' => wp_create_nonce('sige_alunos_action'),
        'nonce_turmas' => wp_create_nonce('sige_turmas_action'),
        'nonce_equipe' => wp_create_nonce('sige_equipe_action'),
        'nonce_global' => wp_create_nonce('sige_global_action'),
    ]);

    // Pesquisa Global (P3): folha e script da caixa de pesquisa da barra de topo.
    // O script auto-desactiva-se se a caixa nao estiver presente (sem permissao).
    if (file_exists(SIGE_PATH . 'assets/views/pesquisa-global.css')) {
        wp_enqueue_style('sige-pesquisa-global', SIGE_URL . 'assets/views/pesquisa-global.css', [], SIGE_VERSION);
    }
    if (file_exists(SIGE_PATH . 'assets/views/pesquisa-global.js')) {
        wp_enqueue_script('sige-pesquisa-global', SIGE_URL . 'assets/views/pesquisa-global.js', ['jquery', 'sige-core-ajax'], SIGE_VERSION, true);
    }

    if (file_exists(SIGE_PATH . 'assets/mobile-tablet-ux.js')) {
        wp_enqueue_script(
            'sige-mobile-tablet-ux',
            SIGE_URL . 'assets/mobile-tablet-ux.js',
            ['sige-core-ajax'],
            SIGE_VERSION,
            true
        );
    }

    wp_add_inline_script('sige-core-ajax', <<<'JS'
(function(){
    var sigeSkipNextBeforeUnload = false;
    var sigeSkipResetTimer = null;

    function setReady(){
        if (!document.body) return;
        document.body.classList.remove('sige-app-preload');
        document.body.classList.add('sige-app-ready');
    }

    function showTransition(){
        if (!document.body) return;
        document.body.classList.add('sige-is-transitioning');
    }

    function clearTransition(){
        if (!document.body) return;
        document.body.classList.remove('sige-is-transitioning');
    }

    function markNoTransitionDownload(){
        sigeSkipNextBeforeUnload = true;
        if (sigeSkipResetTimer) {
            window.clearTimeout(sigeSkipResetTimer);
        }
        sigeSkipResetTimer = window.setTimeout(function(){
            sigeSkipNextBeforeUnload = false;
            clearTransition();
        }, 2200);
    }

    function isNoTransitionLink(link, href){
        href = String(href || '').toLowerCase();
        if (!link || !href) return false;
        if (link.getAttribute('data-sige-no-transition') === '1') return true;
        if (link.hasAttribute('download')) return true;
        if (link.classList && (link.classList.contains('sige-no-transition') || link.classList.contains('sige-download-link'))) return true;
        if (href.indexOf('action=sige_download') !== -1 || href.indexOf('action=sige_export') !== -1) return true;
        if (href.indexOf('admin-post.php') !== -1 && (href.indexOf('download') !== -1 || href.indexOf('export') !== -1 || href.indexOf('modelo') !== -1)) return true;
        if (href.indexOf('blob:') === 0 || href.indexOf('data:') === 0 || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) return true;
        return false;
    }

    window.sigeMarkNoTransitionDownload = markNoTransitionDownload;

    document.addEventListener('DOMContentLoaded', function(){
        setReady();

        document.addEventListener('click', function(e){
            var target = e.target;
            var link = target && target.closest ? target.closest('a[href]') : null;
            if (!link) return;
            var href = link.getAttribute('href') || '';
            if (isNoTransitionLink(link, href)) {
                markNoTransitionDownload();
            }
        }, true);

        var selector = '#sige-sidebar a[href], .sige-menu-item[href], .sg-app-sidebar a[href], .sg-app-topbar a[href], a.sige-brand-link[href]';
        document.querySelectorAll(selector).forEach(function(link){
            link.addEventListener('click', function(e){
                var href = link.getAttribute('href') || '';
                if (!href || href.charAt(0) === '#' || link.target === '_blank' || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || isNoTransitionLink(link, href)) return;
                showTransition();
                window.requestAnimationFrame(function(){
                    window.location.href = href;
                });
                e.preventDefault();
            });
        });

        window.addEventListener('beforeunload', function(){
            if (sigeSkipNextBeforeUnload) {
                window.setTimeout(function(){
                    sigeSkipNextBeforeUnload = false;
                    clearTransition();
                }, 1400);
                return;
            }
            showTransition();
        });
    });

    window.addEventListener('pageshow', function(){
        setReady();
        sigeSkipNextBeforeUnload = false;
        clearTransition();
    });
})();
JS);
});



// ============================================================================
// PRODUTO PRO - Compliance visual global dos módulos harmonizados
// Mantém o Painel Principal como referência inegociável sem tocar em regras.
// v12.10.49: remove pontos verdes decorativos também dos kickers/cabeçalhos PRO e reforça ícones canónicos nos heros.
// ============================================================================
add_action('admin_footer', function () {
    if (!is_admin()) return;
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    if ($page !== 'sige-app') return;
    ?>
    <style id="sige-produto-pro-compliance-v121048">
    body.sige-admin-app{
        --sg-pro-font:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif);
        --sg-pro-ink:#15152c;
        --sg-pro-muted:#5c6173;
        --sg-pro-line:rgba(30,34,60,.06);
        --sg-pro-soft:var(--sg-theme-soft,#efeaff);
        --sg-pro-primary:var(--sg-theme-primary,#5a3fd6);
        --sg-pro-primary-rgb:var(--sg-theme-primary-rgb,90,63,214);
    }
    body.sige-admin-app .sg-app-main,
    body.sige-admin-app .sg-app-page,
    body.sige-admin-app .sg-dashboard-v2,
    body.sige-admin-app .sg-finpro-wrap,
    body.sige-admin-app .sgcc-pro,
    body.sige-admin-app .sg-classpay-wrap,
    body.sige-admin-app .sg-extracts-wrap,
    body.sige-admin-app .sg-finreport-wrap,
    body.sige-admin-app .sg-fincfg-wrap,
    body.sige-admin-app .sige-alunos-page,
    body.sige-admin-app .sige-turmas-page,
    body.sige-admin-app .sige-disciplinas,
    body.sige-admin-app .sige-matriz-page,
    body.sige-admin-app .sige-ap-page,
    body.sige-admin-app .sige-rh,
    body.sige-admin-app .sgcc-pro{
        font-family:var(--sg-pro-font)!important;
        color:var(--sg-pro-ink)!important;
    }
    body.sige-admin-app .sg-dash-hero,
    body.sige-admin-app .sg-finpro-hero,
    body.sige-admin-app .sg-paypro-hero,
    body.sige-admin-app .sgcc-hero,
    body.sige-admin-app .sgcc-hero-v306,
    body.sige-admin-app .sg-extracts-hero,
    body.sige-admin-app .sg-finreport-hero,
    body.sige-admin-app .sg-fincfg-hero,
    body.sige-admin-app .sige-alunos-page > .sige-hero,
    body.sige-admin-app .sige-turmas-page > .sige-hero,
    body.sige-admin-app .sige-disciplinas > .sige-hero,
    body.sige-admin-app .sige-matriz-page > .sige-hero,
    body.sige-admin-app .sige-ap-page > .sige-hero,
    body.sige-admin-app .sige-rh .sg-dash-hero{
        position:relative!important;
        overflow:hidden!important;
        min-height:178px!important;
        border-radius:24px!important;
        background:linear-gradient(110deg,#ffffff 0%,#ffffff 46%,var(--sg-pro-soft) 100%)!important;
        border:1px solid rgba(var(--sg-pro-primary-rgb),.12)!important;
        box-shadow:0 24px 70px rgba(45,36,96,.10)!important;
        padding:32px 34px!important;
        margin:0 0 22px!important;
        color:var(--sg-pro-ink)!important;
        font-family:var(--sg-pro-font)!important;
    }
    body.sige-admin-app .sg-dash-hero:before,
    body.sige-admin-app .sg-finpro-hero:before,
    body.sige-admin-app .sg-paypro-hero:before,
    body.sige-admin-app .sgcc-hero:before,
    body.sige-admin-app .sgcc-hero-v306:before,
    body.sige-admin-app .sg-extracts-hero:before,
    body.sige-admin-app .sg-finreport-hero:before,
    body.sige-admin-app .sg-fincfg-hero:before,
    body.sige-admin-app .sige-alunos-page > .sige-hero:before,
    body.sige-admin-app .sige-turmas-page > .sige-hero:before,
    body.sige-admin-app .sige-disciplinas > .sige-hero:before,
    body.sige-admin-app .sige-matriz-page > .sige-hero:before,
    body.sige-admin-app .sige-ap-page > .sige-hero:before,
    body.sige-admin-app .sige-rh .sg-dash-hero:before{
        content:""!important;
        position:absolute!important;
        inset:auto -80px -130px auto!important;
        width:420px!important;
        height:300px!important;
        border-radius:var(--radius-pill)!important;
        background:radial-gradient(circle,rgba(var(--sg-pro-primary-rgb),.18),rgba(var(--sg-pro-primary-rgb),0) 67%)!important;
        pointer-events:none!important;
        z-index:0!important;
    }
    body.sige-admin-app .sg-dash-hero:after,
    body.sige-admin-app .sg-finpro-hero:after,
    body.sige-admin-app .sg-paypro-hero:after,
    body.sige-admin-app .sgcc-hero:after,
    body.sige-admin-app .sgcc-hero-v306:after,
    body.sige-admin-app .sg-extracts-hero:after,
    body.sige-admin-app .sg-finreport-hero:after,
    body.sige-admin-app .sg-fincfg-hero:after,
    body.sige-admin-app .sige-alunos-page > .sige-hero:after,
    body.sige-admin-app .sige-turmas-page > .sige-hero:after,
    body.sige-admin-app .sige-disciplinas > .sige-hero:after,
    body.sige-admin-app .sige-matriz-page > .sige-hero:after,
    body.sige-admin-app .sige-ap-page > .sige-hero:after,
    body.sige-admin-app .sige-rh .sg-dash-hero:after{
        display:none!important;
        content:none!important;
    }
    body.sige-admin-app .sg-hero-dot,
    body.sige-admin-app .sige-hero-dot,
    body.sige-admin-app .sige-hero-dot-mini,
    body.sige-admin-app .sg-paypro-kicker:before,
    body.sige-admin-app .sg-paypro-kicker::before,
    body.sige-admin-app .sgcc-pro-kicker:before,
    body.sige-admin-app .sgcc-pro-kicker::before,
    body.sige-admin-app .sg-hero-kicker:before,
    body.sige-admin-app .sg-hero-kicker::before,
    body.sige-admin-app .sg-finpro-kicker:before,
    body.sige-admin-app .sg-finpro-kicker::before,
    body.sige-admin-app .sg-extracts-kicker:before,
    body.sige-admin-app .sg-extracts-kicker::before,
    body.sige-admin-app .sg-finreport-kicker:before,
    body.sige-admin-app .sg-finreport-kicker::before,
    body.sige-admin-app .sg-fincfg-kicker:before,
    body.sige-admin-app .sg-fincfg-kicker::before,
    body.sige-admin-app .sgcc-kicker:before,
    body.sige-admin-app .sgcc-kicker::before,
    body.sige-admin-app .sige-hero-kicker:before,
    body.sige-admin-app .sige-hero-kicker::before{
        display:none!important;
        content:none!important;
        box-shadow:none!important;
        background:transparent!important;
    }
    body.sige-admin-app .sg-finpro-hero,
    body.sige-admin-app .sg-paypro-hero,
    body.sige-admin-app .sgcc-hero,
    body.sige-admin-app .sgcc-hero-v306,
    body.sige-admin-app .sg-extracts-hero,
    body.sige-admin-app .sg-finreport-hero,
    body.sige-admin-app .sg-fincfg-hero{
        display:grid!important;
        grid-template-columns:minmax(0,1.04fr) minmax(320px,.96fr)!important;
        gap:22px!important;
        align-items:center!important;
    }
    body.sige-admin-app .sige-alunos-page > .sige-hero .sige-hero-content,
    body.sige-admin-app .sige-turmas-page > .sige-hero .sige-hero-content,
    body.sige-admin-app .sige-disciplinas > .sige-hero .sige-hero-grid,
    body.sige-admin-app .sige-matriz-page > .sige-hero .sige-hero-grid,
    body.sige-admin-app .sige-rh .sg-dash-hero{
        display:grid!important;
        grid-template-columns:minmax(0,1.04fr) minmax(320px,.96fr)!important;
        gap:22px!important;
        align-items:center!important;
    }
    body.sige-admin-app .sg-hero-copy,
    body.sige-admin-app .sg-finpro-hero-copy,
    body.sige-admin-app .sg-paypro-hero-content,
    body.sige-admin-app .sgcc-hero-v306-text,
    body.sige-admin-app .sgcc-pro-hero-text,
    body.sige-admin-app .sg-extracts-hero-copy,
    body.sige-admin-app .sg-finreport-hero-copy,
    body.sige-admin-app .sg-fincfg-hero-copy,
    body.sige-admin-app .sige-hero-text,
    body.sige-admin-app .sige-hero-copy,
    body.sige-admin-app .sige-hero-main,
    body.sige-admin-app .sg-finpro-hero-panel,
    body.sige-admin-app .sg-paypro-hero-panel,
    body.sige-admin-app .sg-extracts-hero-panel,
    body.sige-admin-app .sg-finreport-hero-panel,
    body.sige-admin-app .sg-fincfg-hero-panel,
    body.sige-admin-app .sgcc-hero-v306-badges,
    body.sige-admin-app .sg-hero-art,
    body.sige-admin-app .sige-hero-art{
        position:relative!important;
        z-index:1!important;
    }
    body.sige-admin-app .sg-hero-kicker,
    body.sige-admin-app .sg-finpro-kicker,
    body.sige-admin-app .sg-paypro-kicker,
    body.sige-admin-app .sgcc-pro-kicker,
    body.sige-admin-app .sg-extracts-kicker,
    body.sige-admin-app .sg-finreport-kicker,
    body.sige-admin-app .sg-fincfg-kicker,
    body.sige-admin-app .sgcc-kicker,
    body.sige-admin-app .sige-hero-kicker{
        display:inline-flex!important;
        align-items:center!important;
        gap:var(--space-2)!important;
        margin:0 0 10px!important;
        padding:0!important;
        border:0!important;
        border-radius:0!important;
        background:transparent!important;
        color:var(--sg-pro-primary)!important;
        font-family:var(--sg-pro-font)!important;
        font-size:12px!important;
        line-height:1.2!important;
        font-weight:850!important;
        letter-spacing:.11em!important;
        text-transform:uppercase!important;
        box-shadow:none!important;
    }
    body.sige-admin-app .sg-hero-kicker svg,
    body.sige-admin-app .sg-finpro-kicker svg,
    body.sige-admin-app .sg-paypro-kicker svg,
    body.sige-admin-app .sgcc-pro-kicker svg,
    body.sige-admin-app .sg-extracts-kicker svg,
    body.sige-admin-app .sg-finreport-kicker svg,
    body.sige-admin-app .sg-fincfg-kicker svg,
    body.sige-admin-app .sgcc-kicker svg,
    body.sige-admin-app .sige-hero-kicker svg{
        width:18px!important;
        height:18px!important;
        color:currentColor!important;
        stroke:currentColor!important;
        flex:0 0 auto!important;
    }
    body.sige-admin-app .sg-dash-hero h1,
    body.sige-admin-app .sg-dash-hero .sg-hero-title,
    body.sige-admin-app .sg-finpro-hero h1,
    body.sige-admin-app .sg-paypro-hero h1,
    body.sige-admin-app .sgcc-hero h1,
    body.sige-admin-app .sgcc-hero-v306 h1,
    body.sige-admin-app .sg-extracts-hero h1,
    body.sige-admin-app .sg-finreport-hero h1,
    body.sige-admin-app .sg-fincfg-hero h1,
    body.sige-admin-app .sige-alunos-page > .sige-hero h1,
    body.sige-admin-app .sige-turmas-page > .sige-hero h1,
    body.sige-admin-app .sige-disciplinas > .sige-hero h1,
    body.sige-admin-app .sige-matriz-page > .sige-hero h1,
    body.sige-admin-app .sige-ap-page > .sige-hero h1,
    body.sige-admin-app .sige-rh .sg-dash-hero h1{
        margin:0!important;
        max-width:900px!important;
        color:var(--sg-pro-ink)!important;
        font-family:var(--sg-pro-font)!important;
        font-size:clamp(30px,2.45vw,42px)!important;
        line-height:1.08!important;
        font-weight:850!important;
        letter-spacing:-.04em!important;
        text-shadow:none!important;
    }
    body.sige-admin-app .sg-dash-hero p,
    body.sige-admin-app .sg-dash-hero .sg-hero-subtitle,
    body.sige-admin-app .sg-finpro-hero p,
    body.sige-admin-app .sg-paypro-hero p,
    body.sige-admin-app .sgcc-hero p,
    body.sige-admin-app .sgcc-hero-v306 p,
    body.sige-admin-app .sg-extracts-hero p,
    body.sige-admin-app .sg-finreport-hero p,
    body.sige-admin-app .sg-fincfg-hero p,
    body.sige-admin-app .sige-alunos-page > .sige-hero .sige-hero-subtitle,
    body.sige-admin-app .sige-turmas-page > .sige-hero .sige-hero-subtitle,
    body.sige-admin-app .sige-disciplinas > .sige-hero .sige-hero-subtitle,
    body.sige-admin-app .sige-matriz-page > .sige-hero .sige-hero-subtitle,
    body.sige-admin-app .sige-ap-page > .sige-hero p,
    body.sige-admin-app .sige-rh .sg-dash-hero .sg-hero-subtitle{
        max-width:760px!important;
        margin:var(--space-3) 0 0!important;
        color:var(--sg-pro-muted)!important;
        font-family:var(--sg-pro-font)!important;
        font-size:15px!important;
        line-height:1.65!important;
        font-weight:500!important;
        text-shadow:none!important;
    }
    body.sige-admin-app .sg-hero-actions,
    body.sige-admin-app .sg-finpro-hero-actions,
    body.sige-admin-app .sg-paypro-hero-actions,
    body.sige-admin-app .sgcc-pro-actions,
    body.sige-admin-app .sg-extracts-actions,
    body.sige-admin-app .sg-finreport-hero-actions,
    body.sige-admin-app .sg-fincfg-hero-actions,
    body.sige-admin-app .sige-hero-actions{
        display:flex!important;
        flex-wrap:wrap!important;
        align-items:center!important;
        gap:var(--space-3)!important;
        margin-top:24px!important;
    }
    body.sige-admin-app .sg-finpro-hero-panel,
    body.sige-admin-app .sg-paypro-hero-panel,
    body.sige-admin-app .sg-extracts-hero-panel,
    body.sige-admin-app .sg-finreport-hero-panel,
    body.sige-admin-app .sg-fincfg-hero-panel,
    body.sige-admin-app .sgcc-hero-v306-badges,
    body.sige-admin-app .sgcc-pro-flow{
        border-radius:var(--radius-xl)!important;
        background:linear-gradient(135deg,rgba(255,255,255,.78),rgba(248,246,255,.94))!important;
        border:1px solid rgba(var(--sg-pro-primary-rgb),.14)!important;
        box-shadow:0 18px 42px rgba(45,36,96,.08)!important;
        color:var(--sg-pro-ink)!important;
        text-shadow:none!important;
    }
    body.sige-admin-app .sg-dash-hero .sg-hero-art,
    body.sige-admin-app .sige-hero-art,
    body.sige-admin-app .sg-hero-art{
        border-radius:var(--radius-xl)!important;
        background:linear-gradient(135deg,rgba(var(--sg-pro-primary-rgb),.08),rgba(var(--sg-pro-primary-rgb),.18))!important;
        overflow:hidden!important;
    }
    body.sige-admin-app .sige-stat-card,
    body.sige-admin-app .sg-finpro-kpi,
    body.sige-admin-app .sg-kpi-card-v2,
    body.sige-admin-app .sg-dash-card,
    body.sige-admin-app .sg-finpro-card{
        position:relative!important;
        overflow:hidden!important;
        isolation:isolate!important;
        border-radius:20px!important;
        background:#fff!important;
        border:1px solid rgba(30,34,60,.06)!important;
        box-shadow:0 20px 55px rgba(45,36,96,.075)!important;
    }
    body.sige-admin-app .sige-stat-card > *,
    body.sige-admin-app .sg-finpro-kpi > *,
    body.sige-admin-app .sg-kpi-card-v2 > *,
    body.sige-admin-app .sg-dash-card > *,
    body.sige-admin-app .sg-finpro-card > *{
        position:relative!important;
        z-index:2!important;
    }
    body.sige-admin-app .sige-stat-card:before,
    body.sige-admin-app .sige-stat-card:after,
    body.sige-admin-app .sg-finpro-kpi:before,
    body.sige-admin-app .sg-finpro-kpi:after,
    body.sige-admin-app .sg-kpi-card-v2:before,
    body.sige-admin-app .sg-kpi-card-v2:after{
        z-index:0!important;
        pointer-events:none!important;
    }
    body.sige-admin-app .sige-stat-value,
    body.sige-admin-app .sg-finpro-kpi strong,
    body.sige-admin-app .sg-kpi-card-v2 strong{
        color:var(--sg-pro-ink)!important;
        font-family:var(--sg-pro-font)!important;
        letter-spacing:-.04em!important;
    }
    body.sige-admin-app .sige-stat-label,
    body.sige-admin-app .sg-finpro-kpi span,
    body.sige-admin-app .sg-kpi-card-v2 span{
        font-family:var(--sg-pro-font)!important;
        color:#687084!important;
        letter-spacing:.035em!important;
    }
    @media (max-width:1500px){
        body.sige-admin-app .sg-dash-hero,
        body.sige-admin-app .sg-finpro-hero,
        body.sige-admin-app .sg-paypro-hero,
        body.sige-admin-app .sgcc-hero,
        body.sige-admin-app .sgcc-hero-v306,
        body.sige-admin-app .sg-extracts-hero,
        body.sige-admin-app .sg-finreport-hero,
        body.sige-admin-app .sg-fincfg-hero,
        body.sige-admin-app .sige-alunos-page > .sige-hero .sige-hero-content,
        body.sige-admin-app .sige-turmas-page > .sige-hero .sige-hero-content,
        body.sige-admin-app .sige-disciplinas > .sige-hero .sige-hero-grid,
        body.sige-admin-app .sige-matriz-page > .sige-hero .sige-hero-grid,
        body.sige-admin-app .sige-rh .sg-dash-hero{
            grid-template-columns:1fr!important;
        }
        body.sige-admin-app .sg-hero-art,
        body.sige-admin-app .sige-hero-art{display:none!important;}
    }
    @media (max-width:760px){
        body.sige-admin-app .sg-dash-hero,
        body.sige-admin-app .sg-finpro-hero,
        body.sige-admin-app .sg-paypro-hero,
        body.sige-admin-app .sgcc-hero,
        body.sige-admin-app .sgcc-hero-v306,
        body.sige-admin-app .sg-extracts-hero,
        body.sige-admin-app .sg-finreport-hero,
        body.sige-admin-app .sg-fincfg-hero,
        body.sige-admin-app .sige-alunos-page > .sige-hero,
        body.sige-admin-app .sige-turmas-page > .sige-hero,
        body.sige-admin-app .sige-disciplinas > .sige-hero,
        body.sige-admin-app .sige-matriz-page > .sige-hero,
        body.sige-admin-app .sige-ap-page > .sige-hero,
        body.sige-admin-app .sige-rh .sg-dash-hero{
            padding:var(--space-6) var(--space-5)!important;
            border-radius:var(--radius-xl)!important;
            min-height:0!important;
        }
        body.sige-admin-app .sg-dash-hero h1,
        body.sige-admin-app .sg-dash-hero .sg-hero-title,
        body.sige-admin-app .sg-finpro-hero h1,
        body.sige-admin-app .sg-paypro-hero h1,
        body.sige-admin-app .sgcc-hero h1,
        body.sige-admin-app .sgcc-hero-v306 h1,
        body.sige-admin-app .sg-extracts-hero h1,
        body.sige-admin-app .sg-finreport-hero h1,
        body.sige-admin-app .sg-fincfg-hero h1,
        body.sige-admin-app .sige-hero h1{
            font-size:var(--fs-xl)!important;
        }
        body.sige-admin-app .sg-hero-actions,
        body.sige-admin-app .sg-finpro-hero-actions,
        body.sige-admin-app .sg-paypro-hero-actions,
        body.sige-admin-app .sgcc-pro-actions,
        body.sige-admin-app .sg-extracts-actions,
        body.sige-admin-app .sg-finreport-hero-actions,
        body.sige-admin-app .sg-fincfg-hero-actions,
        body.sige-admin-app .sige-hero-actions{
            display:grid!important;
            grid-template-columns:1fr!important;
        }
    }
    /* v12.19.5 - Painel Principal simplificado: herói compacto e cartões coerentes.
       Escopo SÓ ao dashboard (body.sige-view-dashboard) para não afectar os heróis
       partilhados de Financeiro/Alunos/Turmas/RH, que mantêm o tema PRO. */
    body.sige-admin-app.sige-view-dashboard .sg-dash-hero{
        min-height:0!important;
        padding:var(--space-5) var(--space-6)!important;
        border-radius:var(--radius-lg)!important;
        box-shadow:var(--shadow-sm)!important;
    }
    body.sige-admin-app.sige-view-dashboard .sg-dash-hero:before,
    body.sige-admin-app.sige-view-dashboard .sg-dash-hero:after{
        display:none!important;
    }
    body.sige-admin-app.sige-view-dashboard .sg-dash-hero .sg-hero-title{
        font-size:var(--fs-xl)!important;
        line-height:1.15!important;
        font-weight:700!important;
        letter-spacing:-.02em!important;
    }
    body.sige-admin-app.sige-view-dashboard .sg-dash-card{
        border-radius:var(--radius-lg)!important;
        box-shadow:var(--shadow-sm)!important;
    }
    </style>
    <?php
}, 999);

// ============================================================================
// ADMIN MENU
// ============================================================================
add_action('admin_menu', function () {
    add_menu_page(
        'SIGE Escolar',
        'SIGE SoftGenial',
        'read',
        'sige-app',
        'sige_render_software_interface',
        'dashicons-bank',
        2
    );
});


add_filter('admin_body_class', function ($classes) {
    if (isset($_GET['page']) && $_GET['page'] === 'sige-app') {
        $classes .= ' sige-admin-app sige-app-preload';
        if (!empty($_GET['view'])) {
            $classes .= ' sige-view-' . sanitize_html_class((string)$_GET['view']);
        }
    }
    return $classes;
});

// ============================================================================
// HOOK DE IMPRESSÃO UNIVERSAL
// ============================================================================
add_action('admin_init', 'sige_processar_impressoes_universal', 1);

// ============================================================================
// CSP DO SHELL ADMIN - delegado para includes/csp-zero-inline.php desde v12.14.1
// ----------------------------------------------------------------------------
// A política efectiva é enviada em admin_init prioridade 0 pelo Zero-Inline Guard.
// Este bloco permanece apenas como marcador histórico da fase v12.14.0.
// ============================================================================

// ============================================================================

// ============================================================================
// INTERFACE PRINCIPAL
// ============================================================================
function sige_render_software_interface()
{
    echo '<style>body.sige-admin-app #adminmenuback, body.sige-admin-app #adminmenuwrap, body.sige-admin-app #wpadminbar, body.sige-admin-app #wpfooter { display:none !important; } body.sige-admin-app #wpcontent { margin-left:0 !important; padding:0 !important; } body.sige-admin-app.auto-fold #wpcontent { padding-left:0 !important; } body.sige-admin-app html { margin-top:0 !important; }</style>';
    $escola = sige_get_escola_perfil();
    // v12.11.1 - Fonte canónica de módulos activos.
    // Antes o menu lia JSON cru da escola e comparava chaves literais. Isso fazia
    // áreas activas desaparecerem quando uma camada usava alias legado
    // (ex.: logistica/mod_logistica) e outra esperava mod_transporte.
    $modulos = function_exists('sige_get_modulos_ativos') ? sige_get_modulos_ativos() : json_decode($escola->modulos_ativos ?: '[]', true);
    if (!is_array($modulos)) $modulos = [];
    if (function_exists('sige_normalizar_modulos_ativos')) $modulos = sige_normalizar_modulos_ativos($modulos);
    if (!in_array('mod_academico', $modulos, true)) $modulos[] = 'mod_academico';
    $u = wp_get_current_user();
    // v12.11.9.65 - role operacional activa usada também antes da renderização do menu.
    $sige_active_role_slug = '';
    if (function_exists('sige_permissions_get_active_role')) {
        $sige_active_role = sige_permissions_get_active_role((int)$u->ID);
        if ($sige_active_role && !empty($sige_active_role->slug)) {
            $sige_active_role_slug = sanitize_key((string)$sige_active_role->slug);
        }
    }
    $is_ti = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_admin_ti');
    $is_core_tech = class_exists('SIGE_Core') ? SIGE_Core::can_access_core_admin() : (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'));
    $is_director = current_user_can('sige_director') || $is_ti;
    $is_sec_geral = current_user_can('sige_secretaria_geral') || $is_ti;
    $is_assistente = current_user_can('sige_assistente') || $is_sec_geral;
    $is_prof = current_user_can('sige_professor') || $is_ti;
    $is_encarregado = current_user_can('sige_encarregado');
    $is_financeiro = current_user_can('sige_financeiro');
    $is_secretario  = current_user_can('sige_secretario') || $is_ti;
    $is_gestor_rh   = current_user_can('sige_gestor_rh') || $is_ti;
    $is_pedagogico  = current_user_can('sige_pedagogico') || $is_ti;
    $is_educador    = current_user_can('sige_educador') || $is_ti;
    $is_guarda      = current_user_can('sige_guarda') || $sige_active_role_slug === 'guarda';

    // [v12.11.9.22] Identidade do utilizador para a barra de topo (avatar + papel).
    $sg_topbar_name = (string) ($u->display_name ?: ($u->first_name ?: 'Utilizador'));
    if ($is_ti)             { $sg_role_label = 'ADMINISTRAÇÃO'; }
    elseif ($is_director)   { $sg_role_label = 'DIRECÇÃO'; }
    elseif ($is_assistente) { $sg_role_label = 'SECRETARIA'; }
    elseif ($is_financeiro) { $sg_role_label = 'TESOURARIA'; }
    elseif ($is_secretario) { $sg_role_label = 'SECRETÁRIO'; }
    elseif ($is_prof)       { $sg_role_label = 'DOCENTE'; }
    elseif ($is_gestor_rh)  { $sg_role_label = 'RH'; }
    elseif ($is_pedagogico) { $sg_role_label = 'DIRECÇÃO PEDAGÓGICA'; }
    elseif ($is_educador)   { $sg_role_label = 'EDUCADOR(A)'; }
    elseif ($is_guarda)     { $sg_role_label = 'GUARDA / PORTARIA'; }
    else                    { $sg_role_label = 'UTILIZADOR'; }
    $sg_name_parts = preg_split('/\s+/', trim($sg_topbar_name)) ?: array();
    $sg_initials = '';
    if (!empty($sg_name_parts)) {
        $sg_initials = mb_strtoupper(mb_substr((string) $sg_name_parts[0], 0, 1));
        if (count($sg_name_parts) > 1) {
            $sg_initials .= mb_strtoupper(mb_substr((string) end($sg_name_parts), 0, 1));
        }
    }
    if ($sg_initials === '') { $sg_initials = 'U'; }
    $sg_avatar_url = function_exists('get_avatar_url') ? (string) get_avatar_url($u->ID, array('size' => 96)) : '';
    $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'dashboard';

    // [FASE 2.1] Feature flags - apenas gating de menus/rotas, sem alterar lógica interna.
    $ff_cadastro   = function_exists('sige_feature') ? sige_feature('cadastro_base') : true;
    $ff_academico  = function_exists('sige_feature') ? sige_feature('academico') : true;
    $ff_financeiro = function_exists('sige_feature') ? sige_feature('financeiro') : true;
    $ff_jardim     = function_exists('sige_feature') ? sige_feature('jardim') : true;
    $ff_rh         = function_exists('sige_feature') ? sige_feature('rh') : true;
    $ff_transporte = function_exists('sige_feature') ? sige_feature('transporte') : true;
    // v12.11.1: logística é alias legado do módulo Transporte; o menu deve seguir a feature canónica.
    $ff_logistica  = $ff_transporte;
    $ff_portaria   = function_exists('sige_feature') ? sige_feature('portaria') : true;

    $mod_financeiro_ativo = function_exists('sige_modulo_ativo') ? sige_modulo_ativo('mod_financeiro') : in_array('mod_financeiro', $modulos, true);
    $mod_jardim_ativo     = function_exists('sige_modulo_ativo') ? sige_modulo_ativo('mod_preescolar') : in_array('mod_preescolar', $modulos, true);
    $mod_transporte_ativo = function_exists('sige_modulo_ativo') ? sige_modulo_ativo('mod_transporte') : in_array('mod_transporte', $modulos, true);
    // [12.9.3] Menu Permission Enforcement - matriz controla visibilidade do menu.
    // [12.9.6] Bypass restringido a admins WP reais. Utilizadores com perfil SIGE
    // `admin_ti` continuam a ver tudo via matriz (porque essa role tem
    // `$all_permissions` no seed), mas remoções explícitas de permissão pelo
    // administrador passam a ser efectivas em vez de silenciosamente ignoradas.
    $sige_menu_super = function_exists('sige_page_guard_is_real_admin') ? sige_page_guard_is_real_admin() : (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'));
    $sige_menu_can = function (string $permission) use ($sige_menu_super): bool {
        if ($sige_menu_super) return true;
        return function_exists("sige_can") ? sige_can($permission) : false;
    };
    $sige_menu_can_any = function (array $permissions) use ($sige_menu_can): bool {
        foreach ($permissions as $permission) { if ($sige_menu_can($permission)) return true; }
        return false;
    };
    // [V7.2.5] Escopo docente: Professor fica limitado à área operacional própria.
    $sige_scoped_professor = function_exists('sige_is_scoped_professor_user') ? sige_is_scoped_professor_user() : (
        current_user_can('sige_professor') && !(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director') && !current_user_can('sige_pedagogico') && !current_user_can('sige_secretario')
    );
    $sige_view_permission_map = [
        "dashboard" => ["academico.dashboard_ver","financeiro.dashboard_ver","rh.equipe_ver","jardim.ver","sistema.estado_ver"],
        "equipe" => ["rh.equipe_ver"],
        "alunos_lista" => ["alunos.ver"], "aluno_portal" => ["alunos.ver","portal.ver"], "turmas" => ["academico.turmas_ver"], "aluno_contas" => ["alunos.contas_ver"],
        "disciplinas" => ["academico.disciplinas_ver"], "matriz" => ["academico.matriz_ver"], "curriculos" => ["academico.matriz_ver","configuracoes.ver"], "boletim" => ["academico.boletins_ver"],
        "encerramento" => ["academico.fechar_ano"], "abertura" => ["academico.reabrir_ano"], "portaria" => ["portaria.ver","portaria.validar_acesso"],
        "jardim_diario" => ["jardim.diario_ver"], "jardim_saude" => ["jardim.saude_ver"], "jardim_boletim" => ["jardim.boletim_ver"], "jardim_relatorio" => ["jardim.relatorio_ver"], "jardim_presencas" => ["jardim.presencas_ver"],
        "financeiro-pagamentos" => ["financeiro.pagar"], "financeiro-dashboard" => ["financeiro.dashboard_ver","financeiro.ver"], "financeiro-devedores" => ["financeiro.cobrancas_ver"],
        "pagamentos-turma" => ["financeiro.pagamentos_turma_ver"], "financeiro-relatorio-mensal" => ["financeiro.relatorio_mensal_ver"], "financeiro-auditoria" => ["financeiro.auditoria_ver"], "financeiro-extratos" => ["financeiro.extractos_ver"],
        "financeiro-lancamentos" => ["financeiro.lancamentos_ver"], "financeiro-despesas" => ["financeiro.despesas_ver"], "financeiro-centros" => ["financeiro.centros_custo_ver"],
        "financeiro-gerador" => ["financeiro.lancar_mensalidades"], "financeiro-inscricoes" => ["financeiro.lancamentos_gerir","financeiro.lancar_mensalidades"], "financeiro-planos" => ["financeiro.planos_ver","financeiro.planos_gerir"], "financeiro-config" => ["financeiro.servicos_ver","financeiro.configurar_precos"],
        "minhas_turmas" => ["academico.turmas_ver"], "notas" => ["academico.lancar_notas"], "pautas" => ["academico.pautas_ver"],
        "dec" => ["academico.dec_ver"], "pauta_final" => ["academico.pauta_final_ver"], "acta" => ["academico.actas_ver"], "aprovar_notas" => ["academico.aprovar_notas"],
        "auditoria_notas" => ["academico.auditoria_notas_ver"], "estatisticas_demo" => ["academico.estatisticas_ver"], "transporte" => ["transporte.ver"],
        "sige_core_status" => ["sistema.estado_ver"], "sige_permissoes" => ["usuarios.gerir_permissoes"], "config" => ["configuracoes.ver"], "config_center" => ["sistema.estado_ver","configuracoes.ver"],
        // [12.9.6] Views previamente sem mapa - ficavam acessíveis sem qualquer guarda.
        "vincular" => ["alunos.ver","matriculas.editar"],
        "alocacao" => ["academico.alocacao_ver"],
        // v12.12.0 - views antes allowlisted sem matriz explicita. Todas passam a ter permissao declarada.
        "comunicacoes_central" => ["comunicacao.central_ver"], "financeiro-mpesa" => ["financeiro.mobile_payments_gerir"], "presencas" => ["academico.presencas_ver"],
        "whatsapp_central" => ["comunicacao.whatsapp_ver"], "whatsapp_circulares" => ["comunicacao.circulares_enviar"], "whatsapp_diag" => ["comunicacao.whatsapp_diagnostico_ver"],
        // v12.12.22 - Fase 7: views entregues sem entrada na matriz. Permissoes identicas as guardas internas
        // (reconciliacao usa sige_mpesa_pode_gerir; aprovacoes exige financeiro.estornar OU financeiro.caixa_reabrir).
        "financeiro-reconciliacao" => ["financeiro.mobile_payments_gerir"], "financeiro-aprovacoes" => ["financeiro.estornar","financeiro.caixa_reabrir"],
        // v12.12.23 - Fase 8 Incr 1: ecra de governanca de dados (inventario de PII), so leitura.
        "privacidade-dados" => ["privacidade.inventario_ver"],
        "privacidade-acesso" => ["privacidade.acesso_exportar"],
        "privacidade-apagamento" => ["privacidade.apagamento_executar"],
        "privacidade-retencao" => ["privacidade.retencao_ver"],
    ];
    // [SEGURANÇA] Whitelist de views para prevenir LFI
    $_views_ok = ['dashboard','turmas','alunos_lista','aluno_portal','disciplinas','matriz','curriculos','boletim','notas','pautas',
        'minhas_turmas','aprovar_notas','auditoria_notas','dec','pauta_final','acta','config','equipe','transporte','portaria',
        'aluno_contas','whatsapp_central','vincular','alocacao','encerramento','abertura',
        'financeiro-dashboard','financeiro-pagamentos','financeiro-extratos','financeiro-gerador','financeiro-inscricoes','financeiro-mpesa',
        'financeiro-config','financeiro-despesas','financeiro-centros','financeiro-devedores','financeiro-lancamentos',
        'financeiro-auditoria','pagamentos-turma','financeiro-relatorio-mensal','financeiro-planos',
        // v12.12.22 - Fase 7: reconciliacao (Incr 1, v12.12.20) e aprovacoes (Incr 2, v12.12.21, quatro-olhos).
        // Estavam no mapa de despacho mas em falta nesta allowlist; a guarda anti-LFI reescrevia o pedido para
        // o painel inicial (linha do in_array abaixo). Reposto o roteamento das duas views entregues na Fase 7.
        'financeiro-reconciliacao','financeiro-aprovacoes',
        'jardim_diario','jardim_saude','jardim_boletim','jardim_relatorio','jardim_presencas',
        'estatisticas_demo','sige_core_status','sige_permissoes','config_center',
        // v12.12.23 - Fase 8 Incr 1: inventario de dados pessoais (so leitura).
        'privacidade-dados',
        // v12.12.24 - Fase 8 Incr 2: acesso e portabilidade (dossie do titular).
        'privacidade-acesso',
        // v12.12.25 - Fase 8 Incr 3: apagamento por anonimizacao (direito ao apagamento).
        'privacidade-apagamento',
        // v12.12.27 - Fase 8 Incr 4: retencao e expurgo (so leitura).
        'privacidade-retencao',
        // [v12.9.56] Comunicação WhatsApp - Central de Mensagens + Diagnóstico
        'whatsapp_diag','whatsapp_circulares','presencas',
        // [v12.11.9.161] Central de Comunicações (E-mail + WhatsApp)
        'comunicacoes_central',
        ]; 
    if (!in_array($view, $_views_ok, true)) $view = 'dashboard';

    // [v12.11.4] Permissões reais vencem bloqueios cosméticos por perfil.
    // Antes, o perfil Professor era forçado para apenas dashboard/minhas_turmas/notas/pautas
    // antes da matriz ser avaliada. Assim, permissões concedidas para DEC/ACTA/Pauta Final
    // ficavam decorativas: existiam na UI, mas nunca chegavam à rota.
    // A partir daqui, a matriz de permissões é a fonte de verdade também para professores.
    // O escopo docente continua a ser aplicado dentro das páginas que mostram dados
    // operacionais de turmas/disciplinas, mas a navegação já não é bloqueada por role fixa.

    // [FASE 2.1.1] Bloqueio directo por feature flag, sem wp_die.
    // Motivo: neste app shell o wp_die pode ficar invisível por causa do preload/ocultação do WP Admin.
    // A rota é bloqueada, mas a mensagem é renderizada dentro do layout SIGE para evitar página branca.
    // [12.9.6] Movido para ANTES da verificação de permissão para preservar a precedência feature → permissão.
    $sige_feature_blocked = false;
    $sige_feature_blocked_feature = '';
    if (function_exists('sige_view_feature_allowed') && !sige_view_feature_allowed($view)) {
        $sige_feature_blocked = true;
        if (function_exists('sige_view_feature_map')) {
            $_sige_view_feature_map = sige_view_feature_map();
            $sige_feature_blocked_feature = $_sige_view_feature_map[sanitize_key($view)] ?? 'desconhecida';
        } else {
            $sige_feature_blocked_feature = 'desconhecida';
        }
    }

    // v12.10.118 - Página do Aluno: contexto real de portal.
    // Aluno/Encarregado deve poder abrir apenas &view=aluno_portal,
    // mesmo sem permissões administrativas como alunos.ver.
    $sige_portal_active_role_slug = $sige_active_role_slug;
    $sige_is_aluno_portal_user = (
        current_user_can('sige_encarregado')
        || current_user_can('sige_aluno')
        || in_array($sige_portal_active_role_slug, ['encarregado','aluno'], true)
        || (function_exists('sige_can') && sige_can('portal.ver') && !(function_exists('sige_can') && sige_can('alunos.ver')))
    );

    // [FASE 2.1.3] Entrada inteligente por plano.
    // v12.11.5 - guardamos a rota pedida para poder recalcular permissões
    // quando o sistema redirecciona dashboard -> primeira área permitida.
    $sige_requested_view_before_redirect = $view;
    // [12.9.3] Bloqueio suave de rota directa por permissão de menu.
    // [12.9.6] Bug fix: anteriormente a verificação acontecia antes de
    // `$sige_feature_blocked` ser inicializada, levando a `empty($sige_feature_blocked)`
    // ser sempre verdadeiro e a permissão ganhar contra a feature flag.
    $sige_permission_blocked = false;
    $sige_permission_blocked_permission = "";
    if ($view === 'aluno_portal' && !empty($sige_is_aluno_portal_user)) {
        $sige_permission_blocked = false;
        $sige_permission_blocked_permission = "";
    } elseif (empty($sige_feature_blocked) && isset($sige_view_permission_map[$view]) && !$sige_menu_can_any((array)$sige_view_permission_map[$view])) {
        $sige_permission_blocked = true;
        $sige_permission_blocked_permission = implode(", ", (array)$sige_view_permission_map[$view]);
    }
    // Plano apenas Tesouraria: não deve abrir o dashboard executivo geral/académico.
    // O admin entra directamente no Painel Financeiro; o cadastro fica acessível como dependência mínima.
    if ($view === 'dashboard') {
        if (!$ff_academico && $ff_financeiro) {
            $view = 'financeiro-dashboard';
        } elseif (!$ff_academico && !$ff_financeiro && $ff_cadastro) {
            $view = 'alunos_lista';
        } elseif (!$ff_academico && !$ff_financeiro && $ff_rh) {
            $view = 'equipe';
        }
    }

    $show_main_dashboard = ($ff_academico || $ff_jardim || $ff_rh || $ff_transporte || $ff_portaria);
    // [FIX R-05 / v12.11.4] Redireccionar roles sem dashboard para a sua área,
    // mas sem anular permissões explícitas concedidas na matriz.
    if ($view === 'dashboard') {
        $can_dashboard_by_matrix = $sige_menu_can_any((array)($sige_view_permission_map['dashboard'] ?? []));
        if (!$can_dashboard_by_matrix && $is_guarda && $ff_portaria && $sige_menu_can_any(['portaria.ver','portaria.validar_acesso'])) {
            $view = 'portaria';
        } elseif (!$can_dashboard_by_matrix && current_user_can('sige_professor') && !(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director') && !current_user_can('sige_secretario') && !current_user_can('sige_secretaria_geral') && !current_user_can('sige_assistente') && !current_user_can('sige_pedagogico')) {
            $view = 'minhas_turmas';
        } elseif (!$can_dashboard_by_matrix && current_user_can('sige_educador') && !(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director') && !current_user_can('sige_secretario') && !current_user_can('sige_secretaria_geral') && !current_user_can('sige_assistente') && !current_user_can('sige_secretario')) {
            $view = 'jardim_diario';
        } elseif (!$can_dashboard_by_matrix && current_user_can('sige_gestor_rh') && !(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director')) {
            $view = 'equipe';
        } elseif (!$can_dashboard_by_matrix && current_user_can('sige_financeiro') && !(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director') && !current_user_can('sige_secretario') && !current_user_can('sige_secretaria_geral') && !current_user_can('sige_assistente') && !current_user_can('sige_secretario')) {
            $view = 'financeiro-dashboard';
        } elseif (!$can_dashboard_by_matrix && current_user_can('sige_pedagogico') && !(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director')) {
            $view = 'aprovar_notas';
        }
    }

    // v12.11.5 - se a rota foi redireccionada, recalculamos feature/permissão
    // para a nova view. Sem isto, um professor sem dashboard podia ser movido
    // para Minhas Turmas mas continuar bloqueado pela permissão do dashboard.
    if ($view !== $sige_requested_view_before_redirect) {
        $sige_feature_blocked = false;
        $sige_feature_blocked_feature = '';
        if (function_exists('sige_view_feature_allowed') && !sige_view_feature_allowed($view)) {
            $sige_feature_blocked = true;
            if (function_exists('sige_view_feature_map')) {
                $_sige_view_feature_map = sige_view_feature_map();
                $sige_feature_blocked_feature = $_sige_view_feature_map[sanitize_key($view)] ?? 'desconhecida';
            } else {
                $sige_feature_blocked_feature = 'desconhecida';
            }
        }
        $sige_permission_blocked = false;
        $sige_permission_blocked_permission = '';
        if ($view === 'aluno_portal' && !empty($sige_is_aluno_portal_user)) {
            $sige_permission_blocked = false;
        } elseif (empty($sige_feature_blocked) && isset($sige_view_permission_map[$view]) && !$sige_menu_can_any((array)$sige_view_permission_map[$view])) {
            $sige_permission_blocked = true;
            $sige_permission_blocked_permission = implode(', ', (array)$sige_view_permission_map[$view]);
        }
    }
    // [V89] Blindagem de acesso ao Core: protege também rota interna view=sige_core_status.
    if ($view === 'sige_core_status' && !$is_core_tech) {
        if (class_exists('SIGE_Core')) SIGE_Core::deny_core_access();
        wp_die('Sem permissão para aceder ao Saúde do Sistema.', 'Acesso restrito', ['response' => 403]);
    }

    // Boletim pre-escolar: pagina standalone sem wrapper WP.
    // [12.9.6] Antes corria SEM verificação de permissão. Agora bloqueia se a
    // matriz não conceder `jardim.boletim_ver`.
    if ($view === 'jardim_boletim' && !$sige_permission_blocked && !$sige_feature_blocked) {
        $file = SIGE_PATH . 'admin/jardim_boletim-view.php';
        if (file_exists($file)) { include $file; return; }
    }
?>
    <div id="sige-top-progress" aria-hidden="true"></div>
    <div id="sige-page-transition" aria-hidden="true">
        <div class="sige-transition-card">
            <span class="sige-spinner" aria-hidden="true"></span>
            <span>A abrir área...</span>
        </div>
    </div>
    <div id="sige-layout" class="sg-app-shell sg-product-pro-shell">
        <div class="sg-app-bg-orb sg-app-bg-orb-1"></div>
        <div class="sg-app-bg-orb sg-app-bg-orb-2"></div>
        <!-- [FIX RESP] Overlay do menu (o botao hamburger vive agora dentro da barra de topo) -->
        <div id="sige-overlay" class="sg-app-overlay" data-sige-act="sigeFecharSidebar"></div>
        <aside id="sige-sidebar" class="sg-app-sidebar" style="--sg-school-primary: <?php echo esc_attr($escola->cor_primaria ?: 'var(--sg-theme-primary-800,#3b2f8d)'); ?>;">
            <?php
            $sige_hidden_menu_views = [];
            foreach ($sige_view_permission_map as $_view_key => $_perms) {
                // v12.10.122 - Página do Aluno: não esconder a navegação do próprio portal.
                // O acesso ao aluno_portal já é validado pelo contexto real de portal;
                // esconder por regra de menu administrativo fazia os links laterais desaparecerem.
                if ($_view_key === 'aluno_portal' && !empty($sige_is_aluno_portal_user)) { continue; }
                if (!$sige_menu_can_any((array)$_perms)) { $sige_hidden_menu_views[] = $_view_key; }
            }
            if (!empty($sige_hidden_menu_views)): ?>
                <style id="sige-menu-permission-enforcement">
                <?php foreach ($sige_hidden_menu_views as $_hidden_view): ?>
                    #sige-sidebar a[href*="view=<?php echo esc_attr($_hidden_view); ?>"]{display:none!important;}
                <?php endforeach; ?>
                </style>
                <script <?php echo sige_csp_script_attr(); ?>>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("#sige-sidebar .menu-label").forEach(function(label){var n=label.nextElementSibling,has=false;while(n&&!n.classList.contains("menu-label")){if(n.matches&&n.matches("a.sige-menu-item")&&getComputedStyle(n).display!=="none"){has=true;break;}n=n.nextElementSibling;}if(!has)label.style.display="none";});});</script>
            <?php endif; ?>
            <div class="sg-app-sidebar-inner">
            <div class="sg-app-brand">
                <div class="sg-app-brand-row">
                    <?php if (!empty($escola->logo_sistema_url)): ?>
                        <img src="<?php echo esc_url($escola->logo_sistema_url); ?>" class="sg-app-brand-logo" alt="SoftGenial">
                    <?php else: ?>
                        <div class="sg-app-brand-mark" aria-hidden="true">S</div>
                    <?php endif; ?>
                    <div>
                        <div class="sg-app-brand-kicker">Gestão Escolar</div>
                        <span class="sg-app-brand-title">SoftGenial</span>
                    </div>
                </div>
                <strong class="sg-app-user-name">Olá, <?php echo esc_html($u->first_name ?: $u->display_name); ?></strong>
                <small class="sg-app-user-role">
                    <?php
                    if ($is_ti) echo "ADMINISTRAÇÃO";
                    elseif ($is_director) echo "DIRECÇÃO";
                    elseif ($is_assistente) echo "SECRETARIA";
                    elseif ($is_financeiro) echo "TESOURARIA";
                    elseif ($is_secretario) echo "SECRETÁRIO";
                    elseif ($is_prof) echo "DOCENTE";
                    elseif ($is_gestor_rh) echo "RH";
                    elseif ($is_pedagogico) echo "DIRECÇÃO PEDAGÓGICA";
                    elseif ($is_educador) echo "EDUCADOR(A)";
                    elseif ($is_guarda) echo "GUARDA / PORTARIA";
                    ?>
                </small>
            </div>
            <?php
            // [v12.16.0 RC3] Navegação institucional de topo: read-only, filtrada pelo mapa operacional.
            $sige_v121600_nav_groups = function_exists('sige_institutional_navigation_groups_v121600') ? sige_institutional_navigation_groups_v121600(3, 3) : [];
            $sige_v121600_primary_nav = (!empty($sige_v121600_nav_groups) && is_array($sige_v121600_nav_groups[0])) ? $sige_v121600_nav_groups[0] : [];
            ?>
            <?php if (!empty($sige_v121600_primary_nav['actions']) && is_array($sige_v121600_primary_nav['actions'])): ?>
                <style id="sige-v121600-operational-rail-style">
                    .sg-operational-nav-rail{margin:var(--space-3) var(--space-4) var(--space-3);padding:var(--space-3);border-radius:var(--radius-xl);border:1px solid var(--color-slate-700);background:var(--sg-theme-primary-900);box-shadow:var(--shadow-inner)}
                    .sg-operational-nav-rail-kicker{display:block;margin-bottom:var(--space-1);font-size:var(--fs-xs);line-height:var(--lh-tight);text-transform:uppercase;letter-spacing:.13em;font-weight:950;color:var(--color-slate-200)}
                    .sg-operational-nav-rail strong{display:block;font-size:var(--fs-sm);line-height:var(--lh-tight);font-weight:950;color:var(--color-white);letter-spacing:-.02em}
                    .sg-operational-nav-rail small{display:block;margin-top:var(--space-1);color:var(--color-slate-200);font-size:var(--fs-xs);line-height:var(--lh-normal);font-weight:650}
                    .sg-operational-nav-links{display:grid;gap:var(--space-2);margin-top:var(--space-3)}
                    .sg-operational-nav-link{display:flex;align-items:center;justify-content:space-between;gap:var(--space-2);min-height:var(--space-8);padding:var(--space-2) var(--space-3);border-radius:var(--radius-lg);text-decoration:none;color:var(--color-slate-100);background:var(--color-slate-700);font-size:var(--fs-xs);line-height:var(--lh-tight);font-weight:850}
                    .sg-operational-nav-link:hover,.sg-operational-nav-link:focus{color:var(--color-white);background:var(--sg-theme-primary);outline:0}
                    .sg-operational-nav-link.is-active{color:var(--color-white);background:var(--sg-theme-primary);box-shadow:var(--shadow-inner)}
                    .sg-operational-nav-link span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
                    .sg-operational-nav-link svg{width:var(--space-4);height:var(--space-4);flex:0 0 auto;opacity:.72;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
                    .sg-operational-nav-more{margin-top:var(--space-2);color:var(--color-slate-200);font-size:var(--fs-xs);line-height:var(--lh-normal);font-weight:750}
                </style>
                <section class="sg-operational-nav-rail" aria-label="Navegação operacional prioritária">
                    <span class="sg-operational-nav-rail-kicker">Comece aqui</span>
                    <strong><?php echo esc_html((string)($sige_v121600_primary_nav['label'] ?? 'Rotina operacional')); ?></strong>
                    <?php if (!empty($sige_v121600_primary_nav['focus'])): ?>
                        <small><?php echo esc_html((string)$sige_v121600_primary_nav['focus']); ?></small>
                    <?php endif; ?>
                    <div class="sg-operational-nav-links">
                        <?php foreach ((array)$sige_v121600_primary_nav['actions'] as $sige_v121600_nav_action): ?>
                            <?php
                            $sige_v121600_nav_view = (string)($sige_v121600_nav_action['view'] ?? '');
                            $sige_v121600_nav_href = (string)($sige_v121600_nav_action['href'] ?? '');
                            $sige_v121600_nav_label = (string)($sige_v121600_nav_action['label'] ?? 'Abrir área');
                            if ($sige_v121600_nav_view === '' || $sige_v121600_nav_href === '') { continue; }
                            ?>
                            <a class="sg-operational-nav-link <?php echo $view === $sige_v121600_nav_view ? 'is-active' : ''; ?>" href="<?php echo esc_url($sige_v121600_nav_href); ?>">
                                <span><?php echo esc_html($sige_v121600_nav_label); ?></span>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($sige_v121600_nav_groups) > 1): ?>
                        <div class="sg-operational-nav-more">
                            Outras áreas disponíveis: <?php
                            $sige_v121600_other_labels = [];
                            foreach (array_slice($sige_v121600_nav_groups, 1) as $sige_v121600_other_group) {
                                if (!empty($sige_v121600_other_group['label'])) { $sige_v121600_other_labels[] = (string)$sige_v121600_other_group['label']; }
                            }
                            echo esc_html(implode(' · ', array_slice($sige_v121600_other_labels, 0, 2)));
                            ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
            <nav class="sg-app-nav">
                <!-- Dashboard - apenas gestão -->
                <?php if (!empty($show_main_dashboard) && $sige_menu_can_any((array)($sige_view_permission_map['dashboard'] ?? []))): ?>
                <a href="?page=sige-app&view=dashboard" class="sige-menu-item <?php echo $view == 'dashboard' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg> Painel Principal</a>
                <?php endif; ?>
                <!-- RH - Director + Gestor RH -->
                <?php if ($ff_rh && $sige_menu_can_any((array)($sige_view_permission_map['equipe'] ?? []))): ?>
                    <div class="menu-label" style="--ml-color:#14b8a6;">RECURSOS HUMANOS</div>
                    <a href="?page=sige-app&view=equipe" class="sige-menu-item <?php echo $view == 'equipe' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Equipa e Professores</a>
                <?php endif; ?>
                <!-- Cadastro Escolar Base - dependência mínima para Tesouraria -->
                <?php if (empty($sige_scoped_professor) && !$ff_academico && $ff_cadastro && $sige_menu_can_any(['alunos.ver','academico.turmas_ver','alunos.contas_ver'])): ?>
                    <div class="menu-label" style="--ml-color:#60a5fa;">SECRETARIA</div>
                    <?php if ($sige_menu_can_any(['alunos.ver'])): ?><a href="?page=sige-app&view=alunos_lista" class="sige-menu-item <?php echo $view == 'alunos_lista' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Alunos</a><?php endif; ?>
                    <?php if ($sige_menu_can_any(['academico.turmas_ver'])): ?><a href="?page=sige-app&view=turmas" class="sige-menu-item <?php echo $view == 'turmas' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> Turmas</a><?php endif; ?>
                    <?php if ($sige_menu_can_any(['alunos.contas_ver'])): ?><a href="?page=sige-app&view=aluno_contas" class="sige-menu-item <?php echo $view == 'aluno_contas' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg> Contas de Alunos</a><?php endif; ?>
                <?php endif; ?>

                <!-- Secretaria - Director + Secretário + Assistente -->
                <?php if (empty($sige_scoped_professor) && $ff_academico && $sige_menu_can_any(['academico.disciplinas_ver','academico.matriz_ver','academico.turmas_ver','academico.boletins_ver','academico.fechar_ano','academico.reabrir_ano','alunos.contas_ver','configuracoes.ver'])): ?>
                    <div class="menu-label" style="--ml-color:#3b82f6;">ACADÉMICO</div>
                    <?php if ($sige_menu_can_any(['academico.disciplinas_ver'])): ?><a href="?page=sige-app&view=disciplinas" class="sige-menu-item <?php echo $view === 'disciplinas' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg> Disciplinas</a><?php endif; ?>
                    <?php if ($sige_menu_can_any(['academico.matriz_ver'])): ?><a href="?page=sige-app&view=matriz" class="sige-menu-item <?php echo $view === 'matriz' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg> Matriz Curricular</a><?php endif; ?>
                    <?php if ($sige_menu_can_any(['academico.matriz_ver','configuracoes.ver'])): ?><a href="?page=sige-app&view=curriculos" class="sige-menu-item <?php echo $view === 'curriculos' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><path d="M8 6h8"/><path d="M8 10h8"/></svg> Currículos <span style="margin-left:auto;font-size:9px;font-weight:900;background:rgba(255,255,255,.16);padding:2px 6px;border-radius:999px;">BETA</span></a><?php endif; ?>
                    <?php if ($sige_menu_can_any(['academico.turmas_ver'])): ?><a href="?page=sige-app&view=turmas" class="sige-menu-item <?php echo $view == 'turmas' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> Turmas</a><?php endif; ?>
                    <?php if ($sige_menu_can_any(['alunos.ver'])): ?><a href="?page=sige-app&view=alunos_lista" class="sige-menu-item <?php echo $view == 'alunos_lista' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Alunos</a><?php endif; ?>
                    <?php if ($sige_menu_can_any(['academico.boletins_ver'])): ?><a href="?page=sige-app&view=boletim" class="sige-menu-item <?php echo $view == 'boletim' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> Aproveitamento</a><?php endif; ?>
                    <?php if ($sige_menu_can_any((array)($sige_view_permission_map['encerramento'] ?? []))): ?>
                        <a href="?page=sige-app&view=encerramento" class="sige-menu-item <?php echo $view == 'encerramento' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Encerramento</a>
                        <a href="?page=sige-app&view=abertura" class="sige-menu-item <?php echo $view == 'abertura' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg> Abertura</a>
                    <?php endif; ?>
                    <?php if ($sige_menu_can_any(['alunos.contas_ver'])): ?><a href="?page=sige-app&view=aluno_contas" class="sige-menu-item <?php echo $view == 'aluno_contas' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg> Contas de Alunos</a><?php endif; ?>
                <?php endif; ?>
                <!-- Portaria - Guarda/Recepção/Direcção com permissão própria -->
                <?php if ($ff_portaria && $sige_menu_can_any(['portaria.ver','portaria.validar_acesso'])): ?>
                    <div class="menu-label" style="--ml-color:#6366f1;">PORTARIA</div>
                    <a href="?page=sige-app&view=portaria" class="sige-menu-item <?php echo $view == 'portaria' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Portaria Digital</a>
                <?php endif; ?>
                <?php if ($sige_menu_can_any(['alunos.ver']) && !$sige_menu_can_any(['academico.disciplinas_ver','academico.matriz_ver','academico.turmas_ver','academico.boletins_ver','alunos.contas_ver','configuracoes.ver'])): ?>
                    <div class="menu-label" style="--ml-color:#0ea5e9;">CONSULTA</div>
                    <a href="?page=sige-app&view=alunos_lista" class="sige-menu-item <?php echo $view == 'alunos_lista' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Alunos</a>
                <?php endif; ?>
                <!-- Jardim - Director + Secretário + Assistente + Educador -->
                <?php if ($ff_jardim && $mod_jardim_ativo && $sige_menu_can_any(['jardim.diario_ver','jardim.saude_ver','jardim.boletim_ver','jardim.relatorio_ver','jardim.presencas_ver'])): ?>
                    <div class="menu-label" style="--ml-color:#f472b6;">JARDIM DE INFÂNCIA</div>
                    <a href="?page=sige-app&view=jardim_diario" class="sige-menu-item <?php echo $view=='jardim_diario' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg> Diário de Actividades</a>
                    <a href="?page=sige-app&view=jardim_saude" class="sige-menu-item <?php echo $view=='jardim_saude' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M12 2a5 5 0 0 0-5 5c0 4 5 11 5 11s5-7 5-11a5 5 0 0 0-5-5z"/></svg> Nutrição e Saúde</a>
                    <a href="?page=sige-app&view=jardim_boletim" class="sige-menu-item <?php echo $view=='jardim_boletim' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg> Boletim Pré-Escolar</a>
                    <a href="?page=sige-app&view=jardim_relatorio" class="sige-menu-item <?php echo $view=='jardim_relatorio' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg> Diários & Análises</a>
                    <a href="?page=sige-app&view=jardim_presencas" class="sige-menu-item <?php echo $view=='jardim_presencas' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg> Presenças</a>
                <?php endif; ?>
                <!-- Tesouraria -->
                <?php if ($ff_financeiro && $mod_financeiro_ativo): ?>
                    <?php if ($sige_menu_can_any(['financeiro.pagar','financeiro.dashboard_ver','financeiro.ver','financeiro.cobrancas_ver','financeiro.auditoria_ver','financeiro.pagamentos_turma_ver','financeiro.relatorio_mensal_ver','financeiro.extractos_ver'])): ?>
                    <div class="menu-label" style="--ml-color:#10b981;">TESOURARIA</div>
                        <a href="?page=sige-app&view=financeiro-pagamentos" class="sige-menu-item <?php echo $view == 'financeiro-pagamentos' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg> Registar Pagamento</a>
                        <?php if (function_exists('sige_mpesa_pode_gerir') && sige_mpesa_pode_gerir()): ?>
                        <a href="?page=sige-app&view=financeiro-mpesa" class="sige-menu-item <?php echo $view == 'financeiro-mpesa' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg> Pagamentos Móveis</a>
                        <a href="?page=sige-app&view=financeiro-reconciliacao" class="sige-menu-item <?php echo $view == 'financeiro-reconciliacao' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg> Reconciliação</a>
                        <?php endif; ?>
                        <?php if (function_exists('sige_fin_aprovacao_pode_aceder') && sige_fin_aprovacao_pode_aceder()): ?>
                        <a href="?page=sige-app&view=financeiro-aprovacoes" class="sige-menu-item <?php echo $view == 'financeiro-aprovacoes' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Aprovações</a>
                        <?php endif; ?>
                        <a href="?page=sige-app&view=financeiro-dashboard" class="sige-menu-item <?php echo $view == 'financeiro-dashboard' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg> Painel Financeiro</a>
                        <a href="?page=sige-app&view=financeiro-devedores" class="sige-menu-item <?php echo $view == 'financeiro-devedores' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg> Central de Cobranças</a>
                        <a href="?page=sige-app&view=financeiro-auditoria" class="sige-menu-item <?php echo $view == 'financeiro-auditoria' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg> Auditoria Financeira</a>
                        <a href="?page=sige-app&view=pagamentos-turma" class="sige-menu-item <?php echo $view == 'pagamentos-turma' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg> Pagamentos / Turma</a>
                        <a href="?page=sige-app&view=financeiro-relatorio-mensal" class="sige-menu-item <?php echo $view == 'financeiro-relatorio-mensal' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Relatório Mensal</a>
                        <a href="?page=sige-app&view=financeiro-extratos" class="sige-menu-item <?php echo $view == 'financeiro-extratos' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="12" y2="17"/></svg> Extractos e Caixa</a>
                    <?php endif; ?>
                    <?php if ($sige_menu_can_any(['financeiro.lancamentos_ver','financeiro.lancamentos_gerir','financeiro.despesas_ver','financeiro.centros_custo_ver','financeiro.lancar_mensalidades','financeiro.servicos_ver','financeiro.configurar_precos'])): ?>
                        <a href="?page=sige-app&view=financeiro-lancamentos" class="sige-menu-item <?php echo $view == 'financeiro-lancamentos' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg> Lançamentos</a>
                        <a href="?page=sige-app&view=financeiro-despesas" class="sige-menu-item <?php echo $view == 'financeiro-despesas' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M2 12h6l3-9 3 18 3-9h5"/></svg> Despesas</a>
                        <a href="?page=sige-app&view=financeiro-centros" class="sige-menu-item <?php echo $view == 'financeiro-centros' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg> Centros de Custo</a>
                        <a href="?page=sige-app&view=financeiro-gerador" class="sige-menu-item <?php echo $view == 'financeiro-gerador' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/></svg> Lançar Mensalidades</a>
                        <a href="?page=sige-app&view=financeiro-inscricoes" class="sige-menu-item <?php echo $view == 'financeiro-inscricoes' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/></svg> Inscrições e Renovações</a>
                        <a href="?page=sige-app&view=financeiro-planos" class="sige-menu-item <?php echo $view == 'financeiro-planos' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M9 11h6"/><path d="M9 15h6"/><path d="M9 7h6"/><rect x="5" y="3" width="14" height="18" rx="2"/></svg> Planos de Pagamento</a>
                        <a href="?page=sige-app&view=financeiro-config" class="sige-menu-item <?php echo $view == 'financeiro-config' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> Preços e Serviços</a>
                    <?php endif; ?>
                <?php endif; ?>
                <!-- Comunicação - quem tiver permissões de comunicação (independente de finanças) -->
                <?php if ($sige_menu_can_any(['comunicacao.central_ver','comunicacao.whatsapp_ver']) || (function_exists('sige_circular_pode_enviar') && sige_circular_pode_enviar())): ?>
                    <div class="menu-label">COMUNICAÇÃO</div>
                    <?php if ($sige_menu_can_any(['comunicacao.central_ver'])): ?><a href="?page=sige-app&view=comunicacoes_central" class="sige-menu-item <?php echo $view == 'comunicacoes_central' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg> Central de Comunicações</a><?php endif; ?>
                    <?php if ($sige_menu_can_any(['comunicacao.whatsapp_ver'])): ?><a href="?page=sige-app&view=whatsapp_central" class="sige-menu-item <?php echo $view == 'whatsapp_central' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg> Operação WhatsApp</a><?php endif; ?>
                    <?php if (function_exists('sige_circular_pode_enviar') && sige_circular_pode_enviar()): ?><a href="?page=sige-app&view=whatsapp_circulares" class="sige-menu-item <?php echo $view == 'whatsapp_circulares' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg> Circulares</a><?php endif; ?>
                <?php endif; ?>
                <!-- Área Docente - Professor + Dir. Pedagógico + Secretário + Director -->
                <?php if ($ff_academico && $sige_menu_can_any(['academico.turmas_ver','academico.lancar_notas','academico.pautas_ver','academico.boletins_ver','academico.dec_ver','academico.pauta_final_ver','academico.actas_ver','academico.aprovar_notas'])): ?>
                    <div class="menu-label" style="--ml-color:#f59e0b;">DOCENTES</div>
                    <?php if ($sige_menu_can_any(['academico.turmas_ver','academico.lancar_notas'])): ?>
                    <a href="?page=sige-app&view=minhas_turmas" class="sige-menu-item <?php echo $view == 'minhas_turmas' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg> Minhas Turmas</a>
                    <a href="?page=sige-app&view=notas" class="sige-menu-item <?php echo $view == 'notas' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Lançar Notas</a>
                    <?php endif; ?>
                    <a href="?page=sige-app&view=pautas" class="sige-menu-item <?php echo $view == 'pautas' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Pautas</a>
                    <?php if (function_exists('sige_presencas_pode_ver') && sige_presencas_pode_ver()): ?>
                    <a href="?page=sige-app&view=presencas" class="sige-menu-item <?php echo $view == 'presencas' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/></svg> Presenças</a>
                    <?php endif; ?>
                    <?php if ($sige_menu_can_any(['academico.boletins_ver','academico.dec_ver','academico.pauta_final_ver','academico.actas_ver','academico.aprovar_notas'])): ?>
                    <!-- [T7] Aproveitamento visível para DP (consulta individual de aluno) -->
                    <?php if ($sige_menu_can_any(['academico.boletins_ver'])): ?><a href="?page=sige-app&view=boletim" class="sige-menu-item <?php echo $view == 'boletim' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> Aproveitamento</a><?php endif; ?>
                    <a href="?page=sige-app&view=dec" class="sige-menu-item <?php echo $view === 'dec' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> DEC Estat&iacute;stico</a>
                    <a href="?page=sige-app&view=pauta_final" class="sige-menu-item <?php echo $view === 'pauta_final' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg> Pauta Final Oficial</a>
                    <a href="?page=sige-app&view=acta" class="sige-menu-item <?php echo $view === 'acta' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg> Acta do Conselho</a>
                    <a href="?page=sige-app&view=aprovar_notas" class="sige-menu-item <?php echo $view == 'aprovar_notas' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><polyline points="20 6 9 17 4 12"/></svg> Aprovar Notas</a>
                    <?php endif; ?>
                <?php endif; ?>
                <!-- Gestão Estratégica - Director + Dir. Pedagógico -->
                <?php if ($ff_academico && $sige_menu_can_any(['academico.auditoria_notas_ver','academico.estatisticas_ver'])): ?>
                    <div class="menu-label" style="--ml-color:#a855f7;">GESTÃO ESCOLAR</div>
                    <?php if ($sige_menu_can_any((array)($sige_view_permission_map['auditoria_notas'] ?? []))): ?>
                    <a href="?page=sige-app&view=auditoria_notas" class="sige-menu-item <?php echo $view == 'auditoria_notas' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Auditoria</a>
                    <?php endif; ?>
                    <a href="?page=sige-app&view=estatisticas_demo" class="sige-menu-item <?php echo $view === 'estatisticas_demo' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg> Estatísticas da Escola</a>
                <?php endif; ?>
                <!-- OPERAÇÃO ESCOLAR -->
                <?php if ($ff_transporte && $mod_transporte_ativo && $sige_menu_can_any(['transporte.ver','transporte.rotas_gerir','transporte.alunos_gerir'])): ?>
                <div class="menu-label" style="--ml-color:#ffb300;">OPERAÇÃO ESCOLAR</div>
                    <a href="?page=sige-app&view=transporte" class="sige-menu-item <?php echo $view == 'transporte' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M10 17h4V5H2v12h3"/><path d="M20 17h2v-3.34a4 4 0 0 0-1.17-2.83L18 8h-4v9h1"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg> Transporte</a>
                <?php endif; ?>
                <!-- Sistema - Admin -->
                <?php $sige_pode_permissoes = $sige_menu_can_any(['usuarios.gerir_permissoes']); ?>
                <?php if ($is_core_tech || $sige_pode_permissoes): ?>
                    <div class="menu-label" style="--ml-color:#b8afff;">CONFIGURAÇÕES</div>
                    <?php if ($is_core_tech): ?>
                    <a href="?page=sige-app&view=sige_core_status" class="sige-menu-item <?php echo $view == 'sige_core_status' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg> Saúde do Sistema</a>
                    <?php endif; ?>
                    <?php if ($is_core_tech || $sige_pode_permissoes): ?>
                    <a href="?page=sige-app&view=sige_permissoes" class="sige-menu-item <?php echo $view == 'sige_permissoes' ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 11l-3 3-2-2"/><path d="M17 14l5-5"/></svg> Perfis e Permissões</a>
                    <?php endif; ?>
                    <?php if ($is_core_tech): ?>
                    <a href="?page=sige-app&view=config_center" class="sige-menu-item <?php echo in_array($view, ['config_center','config'], true) ? 'active' : ''; ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><path d="M4 4h16v16H4z"/><path d="M9 9h6"/><path d="M9 13h6"/><path d="M9 17h3"/></svg> Centro de Configuração</a>
                    <?php endif; ?>
                <?php endif; ?>
                <!-- Privacidade e dados (Fase 8) -->
                <?php
                $sige_priv_pode_inv = $sige_menu_can_any((array)($sige_view_permission_map['privacidade-dados'] ?? ['privacidade.inventario_ver']));
                $sige_priv_pode_acesso = $sige_menu_can_any((array)($sige_view_permission_map['privacidade-acesso'] ?? ['privacidade.acesso_exportar']));
                $sige_priv_pode_apagar = $sige_menu_can_any((array)($sige_view_permission_map['privacidade-apagamento'] ?? ['privacidade.apagamento_executar']));
                $sige_priv_pode_retencao = $sige_menu_can_any((array)($sige_view_permission_map['privacidade-retencao'] ?? ['privacidade.retencao_ver']));
                ?>
                <?php if ($sige_priv_pode_inv || $sige_priv_pode_acesso || $sige_priv_pode_apagar || $sige_priv_pode_retencao): ?>
                    <div class="menu-label sige-priv-menu-label">PRIVACIDADE E DADOS</div>
                    <?php if ($sige_priv_pode_inv): ?>
                    <a href="?page=sige-app&view=privacidade-dados" class="sige-menu-item <?php echo $view === 'privacidade-dados' ? 'active' : ''; ?>"><svg class="sige-priv-ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 11h6"/><path d="M9 14h4"/></svg> Inventário de Dados</a>
                    <?php endif; ?>
                    <?php if ($sige_priv_pode_acesso): ?>
                    <a href="?page=sige-app&view=privacidade-acesso" class="sige-menu-item <?php echo $view === 'privacidade-acesso' ? 'active' : ''; ?>"><svg class="sige-priv-ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/></svg> Acesso e Portabilidade</a>
                    <?php endif; ?>
                    <?php if ($sige_priv_pode_apagar): ?>
                    <a href="?page=sige-app&view=privacidade-apagamento" class="sige-menu-item <?php echo $view === 'privacidade-apagamento' ? 'active' : ''; ?>"><svg class="sige-priv-ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg> Apagamento de Dados</a>
                    <?php endif; ?>
                    <?php if ($sige_priv_pode_retencao): ?>
                    <a href="?page=sige-app&view=privacidade-retencao" class="sige-menu-item <?php echo $view === 'privacidade-retencao' ? 'active' : ''; ?>"><svg class="sige-priv-ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><path d="M12 14v4"/><path d="M10 16h4"/></svg> Retenção e Expurgo</a>
                    <?php endif; ?>
                <?php endif; ?>
                        </nav>
            <?php if ($view === 'aluno_portal' && !empty($sige_is_aluno_portal_user)): ?>
                <?php
                $sg_student_secao = isset($_GET['secao']) ? sanitize_key((string)$_GET['secao']) : 'resumo';
                $sg_student_allowed = ['resumo','academico','financeiro','documentos','contactos','senha'];
                if (!in_array($sg_student_secao, $sg_student_allowed, true)) { $sg_student_secao = 'resumo'; }

                $sg_student_aluno_id = 0;
                if (function_exists('sige_aluno_portal_current_student_id_v117')) {
                    $sg_student_aluno_id = (int)sige_aluno_portal_current_student_id_v117((int)get_current_user_id());
                }
                if ($sg_student_aluno_id <= 0) {
                    $sg_student_aluno_id = isset($_GET['aluno_id']) ? absint($_GET['aluno_id']) : 0;
                }

                $sg_student_base_url = admin_url('admin.php?page=sige-app&view=aluno_portal' . ($sg_student_aluno_id > 0 ? '&aluno_id=' . $sg_student_aluno_id : ''));
                $sg_student_menu = [
                    'resumo'     => ['Resumo',     '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>'],
                    'academico'  => ['Académico',  '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/>'],
                    'financeiro' => ['Financeiro', '<path d="M19 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2"/><path d="M16 12h6v5h-6a2.5 2.5 0 0 1 0-5z"/>'],
                    'documentos' => ['Documentos', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>'],
                    'contactos'  => ['Contactos',  '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.32 1.78.59 2.63a2 2 0 0 1-.45 2.11L8 9.7a16 16 0 0 0 6.3 6.3l1.24-1.24a2 2 0 0 1 2.11-.45c.85.27 1.73.47 2.63.59A2 2 0 0 1 22 16.92Z"/>'],
                    'senha'      => ['Alterar senha', '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>'],
                ];
                ?>
                <div class="sg-student-sidebar-nav sg-student-sidebar-nav-server" aria-label="Menu da Página do Aluno">
                    <div class="sg-student-sidebar-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span>Página do Aluno</span>
                    </div>
                    <div class="sg-student-sidebar-links" role="navigation" aria-label="Menu da Página do Aluno">
                        <?php foreach ($sg_student_menu as $sg_student_key => $sg_student_item): ?>
                            <a class="<?php echo $sg_student_secao === $sg_student_key ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg('secao', $sg_student_key, $sg_student_base_url)); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $sg_student_item[1]; ?></svg>
                                <span><?php echo esc_html($sg_student_item[0]); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="sg-app-sidebar-footer"><a href="<?php echo wp_logout_url(); ?>" class="sg-app-logout"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sige-u-shrink-0"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Terminar sessão</a></div>
            </div>
        </aside>
        <main id="sige-content" class="sg-app-content">
            <?php
            $sg_topbar_logo_url = '';
            if (!empty($escola->logo_sistema_url)) {
                $sg_topbar_logo_url = (string) $escola->logo_sistema_url;
            } elseif (defined('SIGE_URL')) {
                $sg_topbar_logo_url = SIGE_URL . 'assets/icons/sg/school.svg';
            }
            ?>
            <div class="sg-app-topbar">
                <button id="sige-hamburger" class="sg-app-hamburger" data-sige-act="sigeAlternarSidebar" data-sige-noargs aria-label="Abrir menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="sg-app-topbar-headings">
                    <span class="sg-app-topbar-logo<?php echo empty($escola->logo_sistema_url) ? ' is-fallback' : ''; ?>" aria-hidden="true">
                        <?php if ($sg_topbar_logo_url): ?>
                            <img src="<?php echo esc_url($sg_topbar_logo_url); ?>" alt="" loading="lazy" onerror="this.style.display='none';this.parentNode.classList.add('is-fallback');">
                        <?php endif; ?>
                    </span>
                    <div class="sg-app-topbar-kicker">Sistema de gestão escolar</div>
                    <div class="sg-app-topbar-title"><?php echo esc_html($escola->nome_escola ?: 'SIGE SoftGenial'); ?></div>
                </div>
                <?php if ($sige_menu_can_any(['alunos.ver','academico.turmas_ver','financeiro.extractos_ver','financeiro.pagar','financeiro.despesas_ver','financeiro.planos_ver'])): ?>
                <div class="sg-gsearch" role="search">
                    <div class="sg-gsearch-box">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="search" id="sgGSearchInput" class="sg-gsearch-input" placeholder="Pesquisar alunos, turmas, recibos..." autocomplete="off" role="combobox" aria-label="Pesquisa global" aria-expanded="false" aria-controls="sgGSearchPanel">
                        <kbd class="sg-gsearch-kbd">/</kbd>
                    </div>
                    <div id="sgGSearchPanel" class="sg-gsearch-panel" role="listbox" aria-label="Resultados da pesquisa" hidden></div>
                </div>
                <?php endif; ?>
                <div class="sg-app-topbar-meta">
                    <span class="sg-app-chip sg-app-chip-year">Ano Lectivo <?php echo (int) sige_ano_lectivo_atual(); ?></span>
                    <div class="sg-app-user" title="<?php echo esc_attr($sg_topbar_name . ' - ' . $sg_role_label); ?>">
                        <span class="sg-app-avatar" data-initials="<?php echo esc_attr($sg_initials); ?>">
                            <?php if ($sg_avatar_url): ?><img src="<?php echo esc_url($sg_avatar_url); ?>" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.style.display='none';"><?php endif; ?>
                        </span>
                        <span class="sg-app-user-meta">
                            <strong><?php echo esc_html($sg_topbar_name); ?></strong>
                            <small><?php echo esc_html($sg_role_label); ?></small>
                        </span>
                    </div>
                </div>
            </div>
            <div class="sg-app-page">
            <?php
            if (!empty($sige_feature_blocked) || !empty($sige_permission_blocked)) {
                if (function_exists('sige_ui_render_access_unavailable')) {
                    sige_ui_render_access_unavailable(!empty($sige_permission_blocked));
                } else {
                    echo '<div class="notice notice-warning"><p>' . esc_html(!empty($sige_permission_blocked) ? 'Acesso não disponível.' : 'Área não activa.') . '</p></div>';
                }
            } else {
                $sige_views_with_own_header = ['dashboard', 'portaria', 'alunos_lista', 'aluno_portal', 'aluno_contas', 'minhas_turmas', 'notas', 'pautas', 'dec', 'pauta_final', 'acta', 'aprovar_notas', 'auditoria_notas', 'estatisticas_demo', 'sige_core_status', 'sige_permissoes', 'jardim_diario', 'jardim_saude', 'jardim_boletim', 'jardim_relatorio', 'jardim_presencas', 'whatsapp_central', 'turmas', 'disciplinas', 'matriz', 'curriculos', 'equipe', 'encerramento', 'abertura', 'financeiro-dashboard', 'financeiro-pagamentos', 'financeiro-devedores', 'financeiro-extratos', 'financeiro-relatorio-mensal', 'financeiro-lancamentos', 'financeiro-despesas', 'financeiro-auditoria', 'financeiro-centros', 'financeiro-gerador', 'financeiro-inscricoes', 'financeiro-planos', 'financeiro-config', 'pagamentos-turma', 'config_center', 'config'];
                if (function_exists('sige_ui_render_module_header') && !in_array((string)$view, $sige_views_with_own_header, true)) {
                    sige_ui_render_module_header((string)$view, $escola, $u);
                }
                if (function_exists('sige_app_render_flash_notice')) {
                    sige_app_render_flash_notice((string)$view);
                }
            // ── Routing Map v11.0 (Fase 3: pastas por módulo) ──────────────
            // Estrutura: admin/{módulo}/{ficheiro}
            // Fallback: admin/{ficheiro} (compatibilidade durante migração)
            $map = [
                // ── Finance ─────────────────────────────────────────────
                'financeiro-pagamentos'       => 'admin/finance/financeiro-pagamentos.php',
                'financeiro-extratos'         => 'admin/finance/financeiro-extratos.php',
                'financeiro-gerador'          => 'admin/finance/financeiro-gerador.php',
                'financeiro-inscricoes'       => 'admin/finance/financeiro-inscricoes-view.php',
                'financeiro-planos'           => 'admin/finance/financeiro-planos-view.php',
                'financeiro-config'           => 'admin/finance/financeiro-config.php',
                'financeiro-despesas'         => 'admin/finance/financeiro-despesas-view.php',
                'financeiro-centros'          => 'admin/finance/financeiro-centros-view.php',
                'financeiro-devedores'        => 'admin/finance/financeiro-devedores-view.php',
                'financeiro-dashboard'        => 'admin/finance/financeiro-dashboard.php',
                'financeiro-lancamentos'      => 'admin/finance/financeiro-lancamentos-view.php',
                'financeiro-relatorio-mensal' => 'admin/finance/financeiro-relatorio-mensal-view.php',
                'financeiro-auditoria'       => 'admin/finance/financeiro-auditoria-view.php',
                'pagamentos-turma'            => 'admin/finance/pagamentos-turma-view.php',

                // ── Academic ────────────────────────────────────────────
                'alunos_lista'    => 'admin/academic/alunos_lista.php',
                'aluno_portal'    => 'admin/academic/aluno-portal-view.php',
                'turmas'          => 'admin/academic/turmas-view.php',
                'disciplinas'     => 'admin/academic/disciplinas-view.php',
                'matriz'          => 'admin/academic/matriz-view.php',
                'curriculos'      => 'admin/system/curriculum-engine-view.php',
                'notas'           => 'admin/academic/notas-view.php',
                'pautas'          => 'admin/academic/pautas-view.php',
                'boletim'         => 'admin/academic/boletim-view.php',
                'minhas_turmas'   => 'admin/academic/minhas_turmas-view.php',
                'aprovar_notas'   => 'admin/academic/aprovar_notas-view.php',
                'auditoria_notas' => 'admin/academic/auditoria_notas-view.php',
                'dec'             => 'admin/academic/dec-view.php',
                'pauta_final'     => 'admin/academic/pauta-final-view.php',
                'acta'            => 'admin/academic/acta-view.php',
                'encerramento'    => 'admin/academic/encerramento-view.php',
                'abertura'        => 'admin/academic/abertura-view.php',
                'aluno_contas'    => 'admin/academic/aluno_contas-view.php',
                'alocacao'        => 'admin/academic/alocacao-view.php',
                'vincular'        => 'admin/academic/vincular-view.php',
                'estatisticas_demo' => 'admin/academic/estatisticas-demograficas-view.php',

                // ── HR ──────────────────────────────────────────────────
                'equipe' => 'admin/hr/equipe-view.php',

                // ── Jardim ──────────────────────────────────────────────
                'jardim_diario'    => 'admin/jardim/jardim_diario-view.php',
                'jardim_saude'     => 'admin/jardim/jardim_saude-view.php',
                'jardim_boletim'   => 'admin/jardim/jardim_boletim-view.php',
                'jardim_relatorio' => 'admin/jardim/jardim_relatorio-view.php',
                'jardim_presencas' => 'admin/jardim/jardim_presencas-view.php',

                // ── System ──────────────────────────────────────────────
                'dashboard' => 'admin/system/dashboard-view.php',
                'config'    => 'admin/system/config-view.php',
                'config_center' => 'admin/system/config-center-view.php',
                'sige_core_status' => 'admin/system/core-status-view.php',
                'sige_permissoes' => 'admin/system/permissions-ui.php',
                'privacidade-dados' => 'admin/system/privacidade-view.php',
                'privacidade-acesso' => 'admin/system/privacidade-acesso-view.php',
                'privacidade-apagamento' => 'admin/system/privacidade-apagamento-view.php',
                'privacidade-retencao' => 'admin/system/privacidade-retencao-view.php',
                'portaria'  => 'admin/system/portaria-view.php',

                // -- Comunicação (WhatsApp) -----------------------------
                'whatsapp_central' => 'admin/whatsapp_central-view.php',
                'comunicacoes_central' => 'admin/comunicacoes_central-view.php',
                'whatsapp_diag'    => 'admin/whatsapp_diag-view.php',
                'whatsapp_circulares' => 'admin/whatsapp_circulares-view.php',
                'presencas'        => 'admin/academic/presencas-view.php',
                'financeiro-mpesa' => 'admin/finance/mpesa-view.php',
                'financeiro-reconciliacao' => 'admin/finance/reconciliacao-view.php',
                'financeiro-aprovacoes' => 'admin/finance/aprovacoes-view.php',

                // -- Logistics -------------------------------------------
                'transporte' => 'admin/logistics/transporte-view.php',
            ];

            // Resolver path: tenta novo → fallback antigo → "em construção"
            $file_path = '';
            if (isset($map[$view])) {
                $file_path = SIGE_PATH . $map[$view];
                // Fallback: ficheiro ainda na pasta antiga durante migração
                if (!file_exists($file_path)) {
                    $file_path = SIGE_PATH . 'admin/' . basename($map[$view]);
                }
            } else {
                // Views sem mapa (módulos futuros). $view já passou pela allowlist
                // $_views_ok acima; o filtro extra é defesa em profundidade contra LFI.
                $view_safe = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$view));
                $file_path = SIGE_PATH . 'admin/' . $view_safe . '-view.php';
            }

            if (file_exists($file_path)) {
                include $file_path;
            } else {
                if (function_exists('sige_ui_render_missing_module')) {
                    sige_ui_render_missing_module();
                } else {
                    echo '<div class="notice notice-warning"><p>Área indisponível.</p></div>';
                }
            }
            }
            ?>
        </div>

        <?php
        // v12.11.9.58 - Bottom navigation global mobile.
        // Exclui Alunos porque esse módulo já possui navegação mobile própria e validada; exclui Portal do Aluno por ter lógica própria.
        $sg_mobile_global_bottom_views_excluded = ['alunos_lista', 'aluno_portal'];
        if (!in_array((string)$view, $sg_mobile_global_bottom_views_excluded, true)):
            $sg_mobile_nav_items = [];
            $sg_can_dash_mobile = !empty($show_main_dashboard) && $sige_menu_can_any((array)($sige_view_permission_map['dashboard'] ?? []));
            $sg_can_portaria_mobile = $ff_portaria && $sige_menu_can_any((array)($sige_view_permission_map['portaria'] ?? ['portaria.ver']));
            $sg_can_alunos_mobile = $sige_menu_can_any((array)($sige_view_permission_map['alunos_lista'] ?? ['alunos.ver']));
            $sg_can_pay_mobile = $sige_menu_can_any((array)($sige_view_permission_map['financeiro-pagamentos'] ?? ['financeiro.pagamentos']));

            if ($sg_can_dash_mobile) {
                $sg_mobile_nav_items[] = [
                    'type' => 'link',
                    'label' => 'Início',
                    'url' => admin_url('admin.php?page=sige-app&view=dashboard'),
                    'active' => ((string)$view === 'dashboard'),
                    'icon' => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/>'
                ];
            }
            if ($sg_can_portaria_mobile) {
                $sg_mobile_nav_items[] = [
                    'type' => 'link',
                    'label' => 'Portaria',
                    'url' => admin_url('admin.php?page=sige-app&view=portaria'),
                    'active' => ((string)$view === 'portaria'),
                    'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-5"/>'
                ];
            }
            if ($sg_can_alunos_mobile) {
                $sg_mobile_nav_items[] = [
                    'type' => 'link',
                    'label' => 'Alunos',
                    'url' => admin_url('admin.php?page=sige-app&view=alunos_lista'),
                    'active' => in_array((string)$view, ['alunos_lista','aluno_contas'], true),
                    'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'
                ];
            }
            if ($sg_can_pay_mobile) {
                $sg_mobile_nav_items[] = [
                    'type' => 'link',
                    'label' => 'Pagamentos',
                    'url' => admin_url('admin.php?page=sige-app&view=financeiro-pagamentos'),
                    'active' => (strpos((string)$view, 'financeiro') === 0 || (string)$view === 'pagamentos-turma'),
                    'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/><path d="M7 15h4"/>'
                ];
            }
            $sg_mobile_more_active = !(((string)$view === 'dashboard') || ((string)$view === 'portaria') || in_array((string)$view, ['alunos_lista','aluno_contas'], true) || strpos((string)$view, 'financeiro') === 0 || (string)$view === 'pagamentos-turma');
            $sg_mobile_nav_items[] = [
                'type' => 'button',
                'label' => 'Mais',
                'url' => '#',
                'active' => $sg_mobile_more_active,
                'icon' => '<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>'
            ];
            if (!empty($sg_mobile_nav_items)):
        ?>
        <nav class="sg-mobile-global-bottom-nav" aria-label="Navegação principal mobile">
            <?php foreach ($sg_mobile_nav_items as $sg_mobile_item): ?>
                <?php if (($sg_mobile_item['type'] ?? '') === 'button'): ?>
                    <button type="button" class="<?php echo !empty($sg_mobile_item['active']) ? 'is-active' : ''; ?>" data-sige-act="sigeAlternarSidebarMais" aria-label="Abrir mais opções" aria-controls="sige-sidebar" aria-expanded="false" <?php echo !empty($sg_mobile_item['active']) ? 'aria-current="page"' : ''; ?>>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $sg_mobile_item['icon']; ?></svg>
                        <span><?php echo esc_html($sg_mobile_item['label']); ?></span>
                    </button>
                <?php else: ?>
                    <a href="<?php echo esc_url((string)$sg_mobile_item['url']); ?>" class="<?php echo !empty($sg_mobile_item['active']) ? 'is-active' : ''; ?>" <?php echo !empty($sg_mobile_item['active']) ? 'aria-current="page"' : ''; ?>>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $sg_mobile_item['icon']; ?></svg>
                        <span><?php echo esc_html($sg_mobile_item['label']); ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <?php endif; endif; ?>
        </main>
    </div>
    <style>
    .sige-menu-item{display:flex;align-items:center;gap:10px;color:rgba(255,255,255,.82);text-decoration:none;padding:12px 14px;border-radius:14px;margin-bottom:4px;font-size:var(--fs-sm);font-weight:500;transition:all .18s ease;backdrop-filter:blur(6px);}
    .sige-menu-item svg{opacity:.7;transition:opacity .18s ease;}
    .sige-menu-item:hover svg,.sige-menu-item.active svg{opacity:1;}
    .sige-menu-item:hover{background:rgba(255,255,255,.11);color:#fff;transform:translateX(2px);}
    .sige-menu-item.active{background:linear-gradient(135deg,rgba(255,255,255,.18),rgba(255,255,255,.08));color:#fff;font-weight:700;box-shadow:inset 0 0 0 1px rgba(255,255,255,.1),0 12px 24px rgba(0,0,0,.18);}

    .menu-label{font-size:10px;font-weight:700;color:var(--ml-color, rgba(255,255,255,.42));margin:18px 0 8px 12px;letter-spacing:.14em;text-transform:uppercase;position:relative;padding-left:12px;}
    .menu-label::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:14px;border-radius:2px;background:var(--ml-color, rgba(255,255,255,.25));}
    </style>
    <script <?php echo sige_csp_script_attr(); ?>>
    document.addEventListener('click', function(e){
        const item = e.target.closest('.sige-menu-item');
        if(item && window.innerWidth <= 768){
            const sidebar = document.getElementById('sige-sidebar');
            const overlay = document.getElementById('sige-overlay');
            if(sidebar) sidebar.classList.remove('open');
            if(overlay) overlay.classList.remove('show');
            document.body.classList.remove('sg-app-menu-open');
        }
    });
    </script>
<?php
}




/**
 * SIGE V7.2.3 - Persistência segura de contexto visual.
 *
 * Resolve três situações:
 * 1) refresh manual mantém a posição;
 * 2) menu lateral mantém o item activo visível depois da navegação;
 * 3) formulários/acções dentro dos módulos voltam à zona onde o utilizador estava.
 *
 * Seguro:
 * - Actua apenas em page=sige-app.
 * - Não altera regras financeiras/académicas.
 * - Não depende de ficheiro JS externo.
 */
add_action('admin_footer', function () {
    if (!is_admin()) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
    if ($page !== 'sige-app') {
        return;
    }
    ?>
    <script id="sige-scroll-context-safe">
    (function () {
        'use strict';

        var WIN_KEY = 'sige_win_scroll_v723';
        var VIEW_KEY = 'sige_view_key_v723';
        var NAV_KEY = 'sige_nav_scroll_v723';
        var ACTION_KEY = 'sige_action_scroll_v723';
        var ACTION_TIME_KEY = 'sige_action_time_v723';

        function params() {
            try { return new URLSearchParams(window.location.search || ''); }
            catch (e) { return null; }
        }

        function currentViewKey() {
            var p = params();
            if (!p) return window.location.pathname;
            return [
                p.get('page') || '',
                p.get('view') || '',
                p.get('aluno_id') || '',
                p.get('turma_id') || '',
                p.get('mes') || '',
                p.get('ano') || '',
                p.get('tab') || ''
            ].join('|');
        }

        function findSideNav() {
            return document.querySelector(
                '.sige-sidebar, #sige-sidebar, .sige-side-menu, .sige-app-sidebar, ' +
                '.sige-menu, .sige-nav, aside, nav'
            );
        }

        function findMainScrollContainer() {
            var candidates = [
                '.sige-content',
                '.sige-main',
                '.sige-app-content',
                '.sige-page',
                '#wpbody-content'
            ];

            for (var i = 0; i < candidates.length; i++) {
                var el = document.querySelector(candidates[i]);
                if (!el) continue;
                var style = window.getComputedStyle(el);
                var overflowY = style.overflowY || '';
                if ((overflowY === 'auto' || overflowY === 'scroll') && el.scrollHeight > el.clientHeight + 20) {
                    return el;
                }
            }
            return null;
        }

        function saveWindowScroll() {
            try {
                sessionStorage.setItem(WIN_KEY, String(window.scrollY || window.pageYOffset || 0));
                sessionStorage.setItem(VIEW_KEY, currentViewKey());
            } catch (e) {}
        }

        function saveNavScroll() {
            try {
                var nav = findSideNav();
                if (nav) {
                    sessionStorage.setItem(NAV_KEY, String(nav.scrollTop || 0));
                }
            } catch (e) {}
        }

        function saveActionScroll() {
            try {
                saveWindowScroll();
                saveNavScroll();

                var main = findMainScrollContainer();
                var data = {
                    y: window.scrollY || window.pageYOffset || 0,
                    view: currentViewKey(),
                    mainSelector: '',
                    mainY: main ? (main.scrollTop || 0) : 0
                };

                sessionStorage.setItem(ACTION_KEY, JSON.stringify(data));
                sessionStorage.setItem(ACTION_TIME_KEY, String(Date.now()));
            } catch (e) {}
        }

        function restoreNavScroll() {
            try {
                var nav = findSideNav();
                if (!nav) return;

                var saved = sessionStorage.getItem(NAV_KEY);
                if (saved !== null) {
                    var y = parseInt(saved, 10);
                    if (!isNaN(y)) nav.scrollTop = y;
                }

                // Mesmo quando o scroll salvo falha, garante que o menu activo fica visível.
                var active = nav.querySelector('.active, [aria-current="page"]');
                if (active && active.scrollIntoView) {
                    window.setTimeout(function () {
                        active.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                    }, 280);
                }
            } catch (e) {}
        }

        function restoreWindowScroll() {
            try {
                var savedView = sessionStorage.getItem(VIEW_KEY);
                var pos = sessionStorage.getItem(WIN_KEY);
                if (pos === null || savedView !== currentViewKey()) {
                    return;
                }

                var y = parseInt(pos, 10);
                if (isNaN(y) || y < 0) return;

                window.setTimeout(function () {
                    window.scrollTo({ top: y, left: 0, behavior: 'auto' });
                    sessionStorage.removeItem(WIN_KEY);
                    sessionStorage.removeItem(VIEW_KEY);
                }, 260);
            } catch (e) {}
        }

        function restoreActionScroll() {
            try {
                var raw = sessionStorage.getItem(ACTION_KEY);
                var t = parseInt(sessionStorage.getItem(ACTION_TIME_KEY) || '0', 10);
                if (!raw || !t) return;

                // Só restaura acções recentes, para não "prender" o utilizador dias depois.
                if ((Date.now() - t) > 120000) {
                    sessionStorage.removeItem(ACTION_KEY);
                    sessionStorage.removeItem(ACTION_TIME_KEY);
                    return;
                }

                var data = JSON.parse(raw);
                if (!data || data.view !== currentViewKey()) return;

                window.setTimeout(function () {
                    var y = parseInt(data.y || 0, 10);
                    if (!isNaN(y) && y > 0) {
                        window.scrollTo({ top: y, left: 0, behavior: 'auto' });
                    }

                    var main = findMainScrollContainer();
                    if (main && data.mainY) {
                        main.scrollTop = parseInt(data.mainY, 10) || 0;
                    }

                    sessionStorage.removeItem(ACTION_KEY);
                    sessionStorage.removeItem(ACTION_TIME_KEY);
                }, 320);
            } catch (e) {}
        }

        function isSideMenuClick(el) {
            if (!el) return false;
            return !!(el.closest && el.closest(
                '.sige-sidebar, #sige-sidebar, .sige-side-menu, .sige-app-sidebar, .sige-menu, .sige-nav, aside, nav'
            ));
        }

        document.addEventListener('submit', function () {
            saveActionScroll();
        }, true);

        document.addEventListener('click', function (ev) {
            var el = ev.target && ev.target.closest ? ev.target.closest('a,button,input[type="submit"]') : null;
            if (!el) return;

            saveNavScroll();

            if (isSideMenuClick(el)) {
                // Para menus laterais: preservar scroll do menu, mas não forçar scroll do conteúdo antigo.
                return;
            }

            // Para botões/acções dentro do módulo: preservar a zona actual do conteúdo.
            saveActionScroll();
        }, true);

        window.addEventListener('beforeunload', function () {
            saveWindowScroll();
            saveNavScroll();
        });

        window.addEventListener('load', function () {
            restoreNavScroll();
            restoreActionScroll();
            restoreWindowScroll();
        });
    })();
    </script>
    <?php
}, 99);


/**
 * SIGE v12.10.33 - App Shell com scroll inteligente.
 * Mantém sidebar e botão de saída fixos; conteúdo e menu rolam de forma independente.
 * Camada visual/UX apenas.
 */
add_action('admin_footer', function () {
    if (!is_admin()) {
        return;
    }
    $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
    if ($page !== 'sige-app') {
        return;
    }
    ?>
    <script id="sige-independent-scroll-shell-v121033">
    (function () {
        'use strict';

        var NAV_KEY = 'sige_app_nav_scroll_v121033';
        var CONTENT_KEY = 'sige_app_content_scroll_v121033';

        function params() {
            try { return new URLSearchParams(window.location.search || ''); }
            catch (e) { return null; }
        }

        function viewKey() {
            var p = params();
            if (!p) return window.location.pathname;
            return [
                p.get('page') || '',
                p.get('view') || '',
                p.get('aluno_id') || '',
                p.get('turma_id') || '',
                p.get('mes') || '',
                p.get('ano') || '',
                p.get('tab') || ''
            ].join('|');
        }

        function nav() {
            return document.querySelector('#sige-sidebar .sg-app-nav');
        }

        function content() {
            return document.getElementById('sige-content') || document.querySelector('.sg-app-content');
        }

        function canUseIndependentScroll() {
            return window.matchMedia && window.matchMedia('(min-width: 1101px)').matches;
        }

        function save() {
            try {
                var n = nav();
                var c = content();
                if (n) sessionStorage.setItem(NAV_KEY, String(n.scrollTop || 0));
                if (c) sessionStorage.setItem(CONTENT_KEY, JSON.stringify({ key: viewKey(), y: c.scrollTop || 0, t: Date.now() }));
            } catch (e) {}
        }

        function restore() {
            try {
                var n = nav();
                if (n) {
                    var y = parseInt(sessionStorage.getItem(NAV_KEY) || '0', 10);
                    if (!isNaN(y) && y > 0) n.scrollTop = y;

                    var active = n.querySelector('.sige-menu-item.active');
                    if (active && (!y || y < 2)) {
                        window.setTimeout(function () {
                            try { active.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'auto' }); }
                            catch (e) { active.scrollIntoView(false); }
                        }, 80);
                    }
                }

                var raw = sessionStorage.getItem(CONTENT_KEY);
                if (!raw) return;
                var data = JSON.parse(raw);
                if (!data || data.key !== viewKey()) return;
                if ((Date.now() - (parseInt(data.t || 0, 10) || 0)) > 120000) return;

                var c = content();
                if (c && canUseIndependentScroll()) {
                    window.setTimeout(function () {
                        c.scrollTop = parseInt(data.y || 0, 10) || 0;
                    }, 120);
                }
            } catch (e) {}
        }

        function markShellReady() {
            var c = content();
            var n = nav();
            if (c) c.setAttribute('data-scroll-area', 'conteudo');
            if (n) n.setAttribute('data-scroll-area', 'menu');
        }

        document.addEventListener('DOMContentLoaded', function () {
            markShellReady();
            restore();

            var n = nav();
            var c = content();
            if (n) n.addEventListener('scroll', save, { passive: true });
            if (c) c.addEventListener('scroll', save, { passive: true });
        });

        document.addEventListener('click', function (ev) {
            var trigger = ev.target && ev.target.closest ? ev.target.closest('a,button,input[type="submit"]') : null;
            if (!trigger) return;
            save();
        }, true);

        document.addEventListener('submit', save, true);
        window.addEventListener('beforeunload', save);
        window.addEventListener('resize', function () { window.setTimeout(restore, 120); });
    })();
    </script>
    <?php
}, 120);
