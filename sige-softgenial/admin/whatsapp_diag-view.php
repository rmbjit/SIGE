<?php
/**
 * SIGE SoftGenial - Diagnóstico WhatsApp
 * Ficheiro: admin/whatsapp_diag-view.php
 *
 * Painel para verificar:
 *  - Estado da sessão Z-API (online/disconnected)
 *  - Estatísticas da queue por status e período
 *  - Últimas mensagens com erro
 *  - Próxima execução do cron `sige_processar_whatsapp_queue`
 *  - Botão "Forçar processamento da queue agora"
 *
 * @since 12.9.8.3
 * @updated 12.11.8 - teste de envio movido para Centro de Configuração → Comunicação
 *
 * Acede via: WP-Admin → SIGE SoftGenial → URL ?page=sige-app&view=whatsapp_diag
 */

if (!defined('ABSPATH')) exit;

// ── Permissão ─────────────────────────────────────────────────────────────
if (!(function_exists('sige_can') && sige_can('comunicacao.whatsapp_diagnostico_ver'))
    && !(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
    && !current_user_can('sige_director')
    && !current_user_can('sige_admin')
    && !current_user_can('sige_financeiro')
    && !current_user_can('sige_secretario')) {
    echo '<div class="notice notice-error"><p>Sem permissão para aceder a esta página.</p></div>';
    return;
}

global $wpdb;
$eid     = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
$tQ      = $wpdb->prefix . 'sige_whatsapp_queue';
$tCfg    = $wpdb->prefix . 'sige_config';

// ── Config actual (URL + token mascarado) ────────────────────────────────
$cfg = $wpdb->get_row($wpdb->prepare(
    "SELECT whatsapp_url, whatsapp_token FROM {$tCfg} WHERE escola_id = %d LIMIT 1",
    $eid
));
$cfg_url   = $cfg && $cfg->whatsapp_url ? $cfg->whatsapp_url : '';
$cfg_token = $cfg && $cfg->whatsapp_token
    ? (function_exists('sige_decrypt_token') ? sige_decrypt_token($cfg->whatsapp_token) : $cfg->whatsapp_token)
    : '';
$cfg_token_mask = $cfg_token
    ? (substr($cfg_token, 0, 4) . str_repeat('•', max(0, strlen($cfg_token) - 8)) . substr($cfg_token, -4))
    : '(vazio)';

// ── Stats da queue ────────────────────────────────────────────────────────
$stats_globais = $wpdb->get_results($wpdb->prepare(
    "SELECT status, COUNT(*) c FROM {$tQ} WHERE escola_id=%d GROUP BY status",
    $eid
));
$totais = ['enviado' => 0, 'pendente' => 0, 'falhou' => 0, 'cancelado' => 0];
foreach ($stats_globais as $s) {
    $totais[$s->status] = (int) $s->c;
}

$ultimas_24h = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tQ}
     WHERE escola_id=%d AND criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
    $eid
));
$ultimas_7d_por_status = $wpdb->get_results($wpdb->prepare(
    "SELECT DATE(criado_em) d, status, COUNT(*) c
       FROM {$tQ}
      WHERE escola_id=%d AND criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)
      GROUP BY d, status
      ORDER BY d DESC, status",
    $eid
));

// ── Últimas mensagens (20) ────────────────────────────────────────────────
$ultimas_msgs = $wpdb->get_results($wpdb->prepare(
    "SELECT id, status, tipo, telefone, tentativas, criado_em, enviado_em, erro,
            LEFT(mensagem, 80) AS msg_preview
       FROM {$tQ}
      WHERE escola_id=%d
      ORDER BY id DESC
      LIMIT 20",
    $eid
));

// ── Estado do cron ────────────────────────────────────────────────────────
$next_queue = wp_next_scheduled('sige_processar_whatsapp_queue');
$next_diario = wp_next_scheduled('sige_evento_diario');
$now = time();

$ajaxurl = admin_url('admin-ajax.php');
$nonce   = wp_create_nonce('sige_wpp_diag');
?>
<div class="wrap" style="max-width:1200px; padding:20px;">
    <h1 style="display:flex; align-items:center; gap:12px; color:#0d1259;">
        <span style="font-size:32px;">📡</span>
        Diagnóstico WhatsApp (Z-API)
    </h1>
    <p style="color:#666; margin-top:0;">Use este painel para confirmar se a sessão Z-API está activa e se as mensagens estão realmente a sair.</p>

    <!-- ============================================================ -->
    <!-- BLOCO 1 - CONFIG + TESTE DE SESSÃO -->
    <!-- ============================================================ -->
    <div style="background:#fff; border-radius:10px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,.06); margin-bottom:20px;">
        <h2 style="margin-top:0; color:#0d1259;">🔐 Configuração actual</h2>
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="width:160px; padding:6px 0; color:#555;"><strong>Endpoint URL</strong></td>
                <td style="padding:6px 0; font-family:monospace; word-break:break-all;"><?php echo $cfg_url ? esc_html($cfg_url) : '<em style="color:#c62828;">(vazio)</em>'; ?></td>
            </tr>
            <tr>
                <td style="padding:6px 0; color:#555;"><strong>Client-Token</strong></td>
                <td style="padding:6px 0; font-family:monospace;"><?php echo esc_html($cfg_token_mask); ?></td>
            </tr>
        </table>

        <hr style="margin:18px 0; border:0; border-top:1px solid #eee;">

        <h3 style="color:#0d1259;">🩺 Verificação directa da sessão Z-API</h3>
        <p style="color:#666; font-size:13px; margin-top:0;">
            Liga ao endpoint <code>/status</code> da Z-API. Se a sessão estiver desligada,
            todas as mensagens são marcadas como "enviadas" mas <strong>nunca chegam</strong> aos destinatários.
        </p>
        <button type="button" id="btn-check-session" class="button button-primary" style="background:#1976d2; border-color:#0d47a1;">
            🔍 Verificar Estado da Sessão
        </button>
        <button type="button" id="btn-force-cron" class="button" style="margin-left:10px;">
            ⚡ Forçar Processamento da Queue Agora
        </button>
        <div id="diag-result" style="margin-top:14px;"></div>
    </div>

    <!-- ============================================================ -->
    <!-- BLOCO 2 - ESTATÍSTICAS DA QUEUE -->
    <!-- ============================================================ -->
    <div style="background:#fff; border-radius:10px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,.06); margin-bottom:20px;">
        <h2 style="margin-top:0; color:#0d1259;">📊 Estatísticas da queue</h2>
        <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:18px;">
            <div style="padding:14px; border-radius:8px; background:#e8f5e9; border-left:4px solid #2e7d32;">
                <div style="font-size:11px; color:#1b5e20; text-transform:uppercase; font-weight:700;">Enviadas</div>
                <div style="font-size:26px; font-weight:800; color:#1b5e20;"><?php echo number_format($totais['enviado'], 0, ',', '.'); ?></div>
            </div>
            <div style="padding:14px; border-radius:8px; background:#fff3e0; border-left:4px solid #ef6c00;">
                <div style="font-size:11px; color:#e65100; text-transform:uppercase; font-weight:700;">Pendentes</div>
                <div style="font-size:26px; font-weight:800; color:#e65100;"><?php echo number_format($totais['pendente'], 0, ',', '.'); ?></div>
            </div>
            <div style="padding:14px; border-radius:8px; background:#ffebee; border-left:4px solid #c62828;">
                <div style="font-size:11px; color:#b71c1c; text-transform:uppercase; font-weight:700;">Falhadas</div>
                <div style="font-size:26px; font-weight:800; color:#b71c1c;"><?php echo number_format($totais['falhou'], 0, ',', '.'); ?></div>
            </div>
            <div style="padding:14px; border-radius:8px; background:#eceff1; border-left:4px solid #546e7a;">
                <div style="font-size:11px; color:#37474f; text-transform:uppercase; font-weight:700;">Canceladas</div>
                <div style="font-size:26px; font-weight:800; color:#37474f;"><?php echo number_format($totais['cancelado'], 0, ',', '.'); ?></div>
            </div>
        </div>

        <p style="color:#555;"><strong>Últimas 24 horas:</strong> <?php echo $ultimas_24h; ?> mensagens criadas.</p>

        <h3 style="color:#0d1259; font-size:15px;">Distribuição por dia (últimos 7 dias)</h3>
        <table class="widefat striped" style="max-width:600px;">
            <thead>
                <tr><th>Data</th><th>Status</th><th style="text-align:right;">Total</th></tr>
            </thead>
            <tbody>
                <?php if (empty($ultimas_7d_por_status)): ?>
                    <tr><td colspan="3" style="padding:14px; text-align:center; color:#999;">Sem mensagens nos últimos 7 dias.</td></tr>
                <?php else: foreach ($ultimas_7d_por_status as $r): ?>
                    <tr>
                        <td><?php echo esc_html(wp_date('d/m/Y', strtotime($r->d))); ?></td>
                        <td><?php
                            $cor = ['enviado' => '#2e7d32', 'pendente' => '#ef6c00', 'falhou' => '#c62828', 'cancelado' => '#546e7a'][$r->status] ?? '#555';
                            echo '<span style="display:inline-block; padding:2px 8px; border-radius:4px; background:' . $cor . '20; color:' . $cor . '; font-weight:600; font-size:12px;">' . esc_html($r->status) . '</span>';
                        ?></td>
                        <td style="text-align:right; font-family:monospace;"><?php echo (int) $r->c; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ============================================================ -->
    <!-- BLOCO 4 - ESTADO DO CRON -->
    <!-- ============================================================ -->
    <div style="background:#fff; border-radius:10px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,.06); margin-bottom:20px;">
        <h2 style="margin-top:0; color:#0d1259;">⏰ Tarefas automáticas de envio</h2>
        <table style="width:100%; border-collapse:collapse;">
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:8px 0; color:#555; width:280px;"><code>sige_processar_whatsapp_queue</code> (5 min)</td>
                <td style="padding:8px 0;">
                    <?php if ($next_queue): ?>
                        <?php
                        $delta = $next_queue - $now;
                        $cor   = $delta < 0 ? '#c62828' : ($delta < 360 ? '#2e7d32' : '#ef6c00');
                        $label = $delta < 0 ? ('atrasado ' . abs($delta) . 's') : ('em ' . $delta . 's');
                        ?>
                        Próxima execução: <strong style="color:<?php echo $cor; ?>;"><?php echo esc_html(wp_date('d/m/Y H:i:s', $next_queue)); ?></strong>
                        <span style="color:#666; font-size:12px;">(<?php echo esc_html($label); ?>)</span>
                    <?php else: ?>
                        <span style="color:#c62828;">⚠️ NÃO AGENDADO</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td style="padding:8px 0; color:#555;"><code>sige_evento_diario</code> (lembretes, contratos)</td>
                <td style="padding:8px 0;">
                    <?php if ($next_diario): ?>
                        Próxima: <strong><?php echo esc_html(wp_date('d/m/Y H:i:s', $next_diario)); ?></strong>
                    <?php else: ?>
                        <span style="color:#c62828;">⚠️ NÃO AGENDADO</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <p style="margin-top:12px; color:#666; font-size:13px;">
            💡 O WP-Cron é disparado por tráfego no site. Se a escola tem pouco tráfego no painel,
            considere adicionar um cron real do servidor:
            <code>* * * * * curl -s https://softgenial.edu.mz/malisa/wp-cron.php?doing_wp_cron &gt; /dev/null</code>
        </p>
    </div>

    <!-- ============================================================ -->
    <!-- BLOCO 5 - ÚLTIMAS MENSAGENS -->
    <!-- ============================================================ -->
    <div style="background:#fff; border-radius:10px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,.06);">
        <h2 style="margin-top:0; color:#0d1259;">📜 Últimas 20 mensagens</h2>
        <table class="widefat striped" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th style="width:90px;">Status</th>
                    <th style="width:100px;">Tipo</th>
                    <th style="width:130px;">Telefone</th>
                    <th>Mensagem (preview)</th>
                    <th style="width:50px;">Tent.</th>
                    <th style="width:140px;">Criada</th>
                    <th>Erro</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ultimas_msgs)): ?>
                    <tr><td colspan="8" style="padding:20px; text-align:center; color:#999;">Nenhuma mensagem na queue.</td></tr>
                <?php else: foreach ($ultimas_msgs as $m): ?>
                    <tr>
                        <td><?php echo (int) $m->id; ?></td>
                        <td><?php
                            $cor = ['enviado' => '#2e7d32', 'pendente' => '#ef6c00', 'falhou' => '#c62828', 'cancelado' => '#546e7a'][$m->status] ?? '#555';
                            echo '<span style="display:inline-block; padding:2px 8px; border-radius:4px; background:' . $cor . '20; color:' . $cor . '; font-weight:600;">' . esc_html($m->status) . '</span>';
                        ?></td>
                        <td><?php echo esc_html($m->tipo); ?></td>
                        <td style="font-family:monospace; font-size:12px;"><?php echo esc_html($m->telefone); ?></td>
                        <td style="max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo esc_attr($m->msg_preview); ?>"><?php echo esc_html($m->msg_preview); ?>…</td>
                        <td style="text-align:center;"><?php echo (int) $m->tentativas; ?></td>
                        <td style="font-family:monospace; font-size:11px;"><?php echo esc_html($m->criado_em); ?></td>
                        <td style="max-width:280px; color:#c62828; font-size:11px; word-break:break-word;"><?php echo esc_html($m->erro ?? ''); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script <?php echo sige_csp_script_attr(); ?>>
(function(){
    'use strict';
    var ajaxurl = '<?php echo esc_js($ajaxurl); ?>';
    var nonce   = '<?php echo esc_js($nonce); ?>';
    var resBox  = document.getElementById('diag-result');

    function esc(s){
        return String(s == null ? '' : s).replace(/[&<>\"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[c];
        });
    }
    function show(html, kind){
        if (!resBox) return;
        var palette = {
            ok:   {bg:'#e8f5e9', border:'#2e7d32', color:'#1b5e20'},
            err:  {bg:'#ffebee', border:'#c62828', color:'#7f1d1d'},
            warn: {bg:'#fff3e0', border:'#ef6c00', color:'#7c2d12'}
        };
        var p = palette[kind || 'warn'] || palette.warn;
        resBox.innerHTML = '<div style="padding:12px 14px; border-radius:8px; background:' + p.bg + '; border-left:4px solid ' + p.border + '; color:' + p.color + '; font-weight:700;">' + html + '</div>';
    }

    function call(action, btn, before){
        var orig = btn.textContent;
        btn.disabled = true;
        btn.textContent = before;
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', nonce);
        return fetch(ajaxurl, { method:'POST', body: fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .finally(function(){ btn.disabled = false; btn.textContent = orig; });
    }

    var btnCheck = document.getElementById('btn-check-session');
    if (btnCheck) {
        btnCheck.addEventListener('click', function(){
            show('A verificar sessão Z-API… aguarde.', 'warn');
            call('sige_wpp_diag_status', btnCheck, '⏳ A verificar…')
                .then(function(d){
                    if (d.success) {
                        var data = d.data || {};
                        var connected = data.connected === true;
                        var lines = [];
                        lines.push('<strong>HTTP:</strong> ' + (data.http || '?'));
                        if (data.connected === true) lines.push('✅ <strong>Sessão CONECTADA</strong> - mensagens devem estar a ser entregues.');
                        else if (data.connected === false) lines.push('🔴 <strong>Sessão DESCONECTADA</strong> - é por isto que as mensagens não estão a chegar.');
                        else lines.push('⚠️ <strong>Resposta inconclusiva</strong> - verificar painel Z-API directamente.');
                        if (data.body) lines.push('<details><summary style="cursor:pointer; margin-top:8px;">Ver resposta completa</summary><pre style="background:#fff; padding:10px; border-radius:6px; overflow:auto; max-height:200px; font-size:11px; color:#333; margin-top:6px;">' + esc(data.body) + '</pre></details>');
                        show(lines.join('<br>'), connected ? 'ok' : 'err');
                    } else {
                        show('❌ ' + (d.data || 'Erro desconhecido'), 'err');
                    }
                })
                .catch(function(e){ show('❌ Erro de ligação: ' + e.message, 'err'); });
        });
    }

    var btnForce = document.getElementById('btn-force-cron');
    if (btnForce) {
        btnForce.addEventListener('click', function(){
            show('A forçar processamento da queue… aguarde.', 'warn');
            call('sige_wpp_diag_force_cron', btnForce, '⏳ A processar…')
                .then(function(d){
                    if (d.success) {
                        var data = d.data || {};
                        var msg = '✅ Processamento concluído. Pendentes processadas: <strong>' + (data.processadas || 0) + '</strong>'
                                + ' | Sucessos: <strong>' + (data.enviadas || 0) + '</strong>'
                                + ' | Falhas: <strong>' + (data.falhas || 0) + '</strong>.';
                        msg += '<br><span style="font-weight:400; font-size:13px;">Recarregue a página para ver a tabela actualizada.</span>';
                        show(msg, (data.falhas > 0 && data.enviadas === 0) ? 'err' : 'ok');
                    } else {
                        show('❌ ' + (d.data || 'Erro desconhecido'), 'err');
                    }
                })
                .catch(function(e){ show('❌ Erro de ligação: ' + e.message, 'err'); });
        });
    }


})();
</script>
