<?php
/**
 * SIGE SoftGenial - Configuração Geral (redirector legacy, v12.10.0+)
 *
 * A página antiga foi substituída pelo Centro de Configuração schema-driven.
 * Este redirector preserva o URL ?view=config para bookmarks e links externos.
 */
if (!defined('ABSPATH')) exit;
if (!sige_page_guard(['configuracoes.editar', 'configuracoes.ver'], [])) return;

$__target_url = admin_url('admin.php?page=sige-app&view=config_center');
?>
<div style="max-width:780px;margin:32px auto;padding:24px 28px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,.04);font-family:-apple-system,Segoe UI,Roboto,sans-serif;color:#1e293b;">
    <h2 style="margin:0 0 8px;font-size:18px;color:#0d1259;font-weight:700;">Configuração movida</h2>
    <p style="margin:0 0 16px;color:#475569;font-size:13.5px;line-height:1.55;">A página antiga foi substituída pelo <b>Centro de Configuração</b>. Os formulários e integrações que ainda apontem para os endpoints AJAX antigos continuam a funcionar - são interceptados de forma transparente.</p>
    <a href="<?php echo esc_url($__target_url); ?>" style="display:inline-block;background:#0d1259;color:#fff;text-decoration:none;border-radius:6px;padding:9px 18px;font-weight:700;font-size:13px;">Abrir Centro de Configuração</a>
</div>
