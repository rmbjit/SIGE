<?php
/**
 * SIGE SoftGenial - Central de Comunicacoes (E-mail + WhatsApp unificados)
 * Ficheiro: admin/comunicacoes_central-view.php
 * Acesso: ?page=sige-app&view=comunicacoes_central
 *
 * Fila UNIFICADA: consome o modelo canonico de includes/comunicacoes-core.php,
 * que normaliza as filas de e-mail e WhatsApp. Permite filtrar por canal e
 * estado, seleccionar varios e aplicar accoes em massa (cancelar/reenviar/
 * eliminar), com as MESMAS regras de estado de cada canal. Separador Modelos
 * para editar o texto dos e-mails.
 *
 * Seguranca: capability + nonce (sige_emc_nonce) + escola_id em tudo. Nao altera
 * regras financeiras nem academicas. UX nos dialogos canonicos do kit (sigeUi).
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sige_emc_user_can') || !sige_emc_user_can() || !function_exists('sige_comm_query')) {
    echo '<div style="padding:var(--space-6)"><strong>Sem permissão para aceder à Central de Comunicações.</strong></div>';
    return;
}

$ajaxurl = admin_url('admin-ajax.php');
$nonce   = wp_create_nonce('sige_emc_nonce');
$stats   = sige_comm_stats();

$tabs_validos = ['fila', 'modelos', 'encarregado', 'saude'];
$tab = isset($_GET['ctab']) ? sanitize_key((string) $_GET['ctab']) : 'fila';
if (!in_array($tab, $tabs_validos, true)) $tab = 'fila';

$canais_validos = ['todos', 'email', 'whatsapp'];
$canal = isset($_GET['cn']) ? sanitize_key((string) $_GET['cn']) : 'todos';
if (!in_array($canal, $canais_validos, true)) $canal = 'todos';

$estados_canonicos = sige_comm_estados_canonicos();
$estado = isset($_GET['st']) ? sanitize_key((string) $_GET['st']) : 'all';
if ($estado !== 'all' && !isset($estados_canonicos[$estado])) $estado = 'all';

$por_pagina = 20;
$pag = isset($_GET['pp']) ? max(1, absint($_GET['pp'])) : 1;

$res = sige_comm_query(['canal' => $canal, 'estado' => $estado, 'pagina' => $pag, 'por_pagina' => $por_pagina]);
$rows  = $res['rows'];
$total = $res['total'];
$total_paginas = max(1, (int) ceil($total / $por_pagina));
if ($pag > $total_paginas) $pag = $total_paginas;

$templates = function_exists('sige_email_templates_get') ? sige_email_templates_get() : [];

$estado_cor = ['pendente' => 'warning', 'a_enviar' => 'info', 'enviado' => 'success', 'falhou' => 'danger', 'cancelado' => 'ink'];
$url_base = admin_url('admin.php?page=sige-app&view=comunicacoes_central');

if (!function_exists('sige_emc_fmt_dt')) {
    function sige_emc_fmt_dt($dt) {
        $dt = (string) $dt;
        if ($dt === '' || $dt === '0000-00-00 00:00:00') return '-';
        $ts = strtotime($dt);
        return $ts ? esc_html(wp_date('d/m/Y H:i', $ts)) : esc_html($dt);
    }
}
$qbase = function ($extra) use ($url_base, $canal, $estado) {
    return esc_url(add_query_arg(array_merge(['ctab' => 'fila', 'cn' => $canal, 'st' => $estado], $extra), $url_base));
};
?>
<div class="emc-wrap">

  <nav class="emc-tabs" role="tablist">
    <a class="emc-tab <?php echo $tab === 'fila' ? 'is-active' : ''; ?>" href="<?php echo esc_url($url_base . '&ctab=fila'); ?>">Fila de envios</a>
    <a class="emc-tab <?php echo $tab === 'encarregado' ? 'is-active' : ''; ?>" href="<?php echo esc_url($url_base . '&ctab=encarregado'); ?>">Por encarregado</a>
    <a class="emc-tab <?php echo $tab === 'modelos' ? 'is-active' : ''; ?>" href="<?php echo esc_url($url_base . '&ctab=modelos'); ?>">Modelos de texto</a>
    <a class="emc-tab <?php echo $tab === 'saude' ? 'is-active' : ''; ?>" href="<?php echo esc_url($url_base . '&ctab=saude'); ?>">Saúde do e-mail</a>
  </nav>

  <?php if ($tab === 'fila'): ?>
  <section class="emc-kpi-grid" aria-label="Resumo de envios">
    <?php
    $cards = [
        ['Total', $stats['total'], 'purple', 'message'],
        ['Pendentes', $stats['pendente'], 'amber', 'activity'],
        ['Enviados', $stats['enviado'], 'green', 'check'],
        ['Falhados', $stats['falhou'], 'red', 'bolt'],
        ['Cancelados', $stats['cancelado'], 'blue', 'lock'],
    ];
    foreach ($cards as $c): ?>
      <article class="sg-finpro-kpi sg-finpro-tone-<?php echo esc_attr($c[2]); ?>">
        <div class="sg-finpro-kpi-icon"><?php echo sige_ui_icon($c[3]); ?></div>
        <div>
          <span><?php echo esc_html($c[0]); ?></span>
          <strong><?php echo (int) $c[1]; ?></strong>
        </div>
      </article>
    <?php endforeach; ?>
  </section>

  <section class="sg-finpro-card">

    <div class="emc-toolbar">
      <div class="emc-filtros-grupo">
        <span class="emc-flabel">Canal:</span>
        <?php foreach (['todos' => 'Todos', 'email' => 'E-mail', 'whatsapp' => 'WhatsApp'] as $k => $lbl): ?>
          <a class="emc-chip <?php echo $canal === $k ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['ctab' => 'fila', 'cn' => $k, 'st' => $estado], $url_base)); ?>"><?php echo esc_html($lbl); ?><?php if ($k === 'email') echo ' (' . (int) $stats['email'] . ')'; elseif ($k === 'whatsapp') echo ' (' . (int) $stats['whatsapp'] . ')'; ?></a>
        <?php endforeach; ?>
      </div>
      <div class="emc-filtros-grupo">
        <span class="emc-flabel">Estado:</span>
        <a class="emc-chip <?php echo $estado === 'all' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['ctab' => 'fila', 'cn' => $canal, 'st' => 'all'], $url_base)); ?>">Todos</a>
        <?php foreach ($estados_canonicos as $k => $lbl): ?>
          <a class="emc-chip <?php echo $estado === $k ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['ctab' => 'fila', 'cn' => $canal, 'st' => $k], $url_base)); ?>"><?php echo esc_html($lbl); ?></a>
        <?php endforeach; ?>
      </div>
      <div class="emc-toolbar-acoes">
        <button type="button" class="sg-finpro-btn sg-finpro-btn-light" id="emc-process">Processar e-mails</button>
        <a class="sg-finpro-btn sg-finpro-btn-light" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=whatsapp_central')); ?>">Ferramentas WhatsApp</a>
      </div>
    </div>

    <div class="emc-bulkbar" id="emc-bulkbar" hidden>
      <span class="emc-bulk-count"><strong id="emc-bulk-n">0</strong> seleccionado(s)</span>
      <button type="button" class="emc-bulk sg-finpro-btn sg-finpro-btn-light" data-accao="cancel">Cancelar</button>
      <button type="button" class="emc-bulk sg-finpro-btn sg-finpro-btn-light" data-accao="retry">Reenviar</button>
      <button type="button" class="emc-bulk sg-finpro-btn is-danger" data-accao="delete">Eliminar</button>
    </div>

    <div class="emc-tablewrap">
      <table class="sg-finpro-table">
        <thead>
          <tr>
            <th class="emc-col-chk"><input type="checkbox" id="emc-chk-all" aria-label="Seleccionar todos"></th>
            <th>Canal</th>
            <th>Destinatário</th>
            <th>Mensagem</th>
            <th>Estado</th>
            <th>Data</th>
            <th class="emc-col-acoes">Acções</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="7" class="emc-empty">Sem envios para este filtro.</td></tr>
          <?php else: foreach ($rows as $r):
            $cor = $estado_cor[$r['estado']] ?? 'ink';
            $canal_lbl = $r['canal'] === 'whatsapp' ? 'WhatsApp' : 'E-mail';
            $canal_cor = $r['canal'] === 'whatsapp' ? 'success' : 'info';
            $dest = $r['nome'] !== '' ? $r['nome'] : $r['sub'];
            $pode_cancel = ($r['canal'] === 'email' && $r['estado_raw'] === 'pending') || ($r['canal'] === 'whatsapp' && in_array($r['estado_raw'], ['pendente', 'forcar_envio', 'agendada'], true));
            $pode_retry  = in_array($r['estado'], ['falhou', 'cancelado'], true);
            $pode_del    = in_array($r['estado'], ['enviado', 'falhou', 'cancelado'], true);
          ?>
            <tr data-id="<?php echo (int) $r['id']; ?>" data-canal="<?php echo esc_attr($r['canal']); ?>">
              <td class="emc-col-chk"><input type="checkbox" class="emc-chk" data-id="<?php echo (int) $r['id']; ?>" data-canal="<?php echo esc_attr($r['canal']); ?>"></td>
              <td><span class="emc-badge emc-badge--<?php echo esc_attr($canal_cor); ?>"><?php echo esc_html($canal_lbl); ?></span></td>
              <td>
                <div class="emc-dest"><?php echo esc_html($dest !== '' ? $dest : '-'); ?></div>
                <?php if ($r['nome'] !== '' && $r['sub'] !== ''): ?><div class="emc-dest-mail"><?php echo esc_html($r['sub']); ?></div><?php endif; ?>
              </td>
              <td class="emc-assunto"><?php echo esc_html(wp_strip_all_tags($r['resumo'])); ?>
                  <?php if ($r['contexto'] !== ''): ?><span class="emc-ctx"><?php echo esc_html($r['contexto']); ?></span><?php endif; ?></td>
              <td><span class="emc-badge emc-badge--<?php echo esc_attr($cor); ?>"><?php echo esc_html($estados_canonicos[$r['estado']] ?? $r['estado']); ?></span>
                  <?php if ($r['tentativas'] > 0): ?><span class="emc-att"><?php echo (int) $r['tentativas']; ?></span><?php endif; ?></td>
              <td class="emc-dt"><?php echo sige_emc_fmt_dt($r['data']); ?></td>
              <td class="emc-col-acoes">
                <button type="button" class="emc-act emc-act-view" data-id="<?php echo (int) $r['id']; ?>" data-canal="<?php echo esc_attr($r['canal']); ?>">Ver</button>
                <?php if ($pode_cancel): ?><button type="button" class="emc-act emc-act-do" data-accao="cancel" data-id="<?php echo (int) $r['id']; ?>" data-canal="<?php echo esc_attr($r['canal']); ?>">Cancelar</button><?php endif; ?>
                <?php if ($pode_retry): ?><button type="button" class="emc-act emc-act-do" data-accao="retry" data-id="<?php echo (int) $r['id']; ?>" data-canal="<?php echo esc_attr($r['canal']); ?>">Reenviar</button><?php endif; ?>
                <?php if ($pode_del): ?><button type="button" class="emc-act emc-act-do" data-accao="delete" data-id="<?php echo (int) $r['id']; ?>" data-canal="<?php echo esc_attr($r['canal']); ?>">Eliminar</button><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_paginas > 1): ?>
    <div class="emc-pag">
      <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
        <a class="emc-pag-i <?php echo $i === $pag ? 'is-active' : ''; ?>" href="<?php echo $qbase(['pp' => $i]); ?>"><?php echo (int) $i; ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </section>

  <?php elseif ($tab === 'encarregado'): ?>
  <section class="sg-finpro-card">
    <p class="emc-help">Veja todo o histórico de comunicações de um aluno, e-mail e WhatsApp juntos, por ordem cronológica. Procure pelo nome do aluno ou número de processo.</p>
    <div class="emc-busca">
      <input type="text" id="emc-busca-q" class="emc-input" placeholder="Procurar aluno por nome ou processo..." autocomplete="off">
      <div class="emc-busca-res" id="emc-busca-res" hidden></div>
    </div>
    <div id="emc-hist" class="emc-hist" data-aluno="<?php echo isset($_GET['aluno_id']) ? (int) $_GET['aluno_id'] : 0; ?>"></div>
  </section>

  <?php elseif ($tab === 'modelos'): ?>
  <section class="sg-finpro-card">
    <p class="emc-help">Edite o texto dos e-mails que a escola envia. Use as variáveis entre chavetas (ex. <code>{nome_escola}</code>) e elas serão substituídas no envio. Pode repor o texto original a qualquer momento.</p>
    <div class="emc-vars">
      <strong>Variáveis:</strong>
      <code>{nome_escola}</code> <code>{nome_aluno}</code> <code>{primeiro_nome}</code> <code>{nome_encarregado}</code> <code>{valor}</code> <code>{descricao}</code> <code>{recibo_numero}</code> <code>{mes}</code> <code>{data}</code> <code>{link_recibo}</code>
    </div>
    <?php foreach ($templates as $key => $tpl):
        if (!is_array($tpl)) continue;
        $label = (string) ($tpl['label'] ?? $key); ?>
    <div class="emc-modelo" data-key="<?php echo esc_attr($key); ?>">
      <div class="emc-modelo-head">
        <h3><?php echo esc_html($label); ?></h3>
        <div>
          <button type="button" class="emc-act emc-act-test" data-key="<?php echo esc_attr($key); ?>">Enviar teste</button>
          <button type="button" class="emc-act emc-act-reset" data-key="<?php echo esc_attr($key); ?>">Repor original</button>
        </div>
      </div>
      <label class="emc-field"><span>Assunto</span>
        <input type="text" class="emc-input emc-subject" value="<?php echo esc_attr((string) ($tpl['subject'] ?? '')); ?>"></label>
      <label class="emc-field"><span>Corpo (HTML simples)</span>
        <textarea class="emc-input emc-body" rows="7"><?php echo esc_textarea((string) ($tpl['body'] ?? '')); ?></textarea></label>
    </div>
    <?php endforeach; ?>
    <div class="emc-save-bar">
      <button type="button" class="sg-finpro-btn sg-finpro-btn-primary" id="emc-save-templates">Guardar modelos</button>
    </div>
  </section>
  <?php elseif ($tab === 'saude'):
      $smtp = function_exists('sige_smtp_get_config') ? sige_smtp_get_config() : [];
      $smtp_on = !empty($smtp['enabled']) && (string) $smtp['enabled'] !== '0';
      $from_email = trim((string) ($smtp['from_email'] ?? ''));
      $from_name  = trim((string) ($smtp['from_name'] ?? ''));
      $smtp_host  = trim((string) ($smtp['host'] ?? ''));
      $smtp_port  = (string) ($smtp['port'] ?? '');
      global $wpdb; $te = sige_comm_email_table(); $eid = sige_emc_eid();
      $q_pend = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$te} WHERE escola_id=%d AND status='pending'", $eid));
      $q_fail = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$te} WHERE escola_id=%d AND status='failed'", $eid));
      $q_sent = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$te} WHERE escola_id=%d AND status='sent'", $eid));
      $ult_erros = $wpdb->get_results($wpdb->prepare("SELECT recipient, last_error, updated_at FROM {$te} WHERE escola_id=%d AND status='failed' AND last_error IS NOT NULL AND last_error<>'' ORDER BY id DESC LIMIT 5", $eid));
      // sinal de gralha provavel no remetente (ex. nonreplay em vez de noreply)
      $from_suspeito = $from_email !== '' && (bool) preg_match('/nonreplay|no-?repl[ay]{1,2}@/i', $from_email) && stripos($from_email, 'noreply') === false && stripos($from_email, 'no-reply') === false;
      $pode_smtp = function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options');
  ?>
  <section class="sg-finpro-card">
    <p class="emc-help">Estado operacional do e-mail da escola: configuração de envio (SMTP), saúde da fila e teste rápido. O equivalente, para o WhatsApp, está em <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=whatsapp_central')); ?>">Operação WhatsApp</a>.</p>

    <div class="emc-saude-grid">
      <div class="emc-fichacard">
        <h3>Configuração de envio (SMTP)</h3>
        <div class="emc-kv">
          <span class="emc-kv-k">Estado</span>
          <span class="emc-kv-v"><span class="emc-badge emc-badge--<?php echo $smtp_on ? 'success' : 'danger'; ?>"><?php echo $smtp_on ? 'Activo' : 'Desligado'; ?></span></span>
        </div>
        <div class="emc-kv"><span class="emc-kv-k">Remetente (nome)</span><span class="emc-kv-v"><?php echo esc_html($from_name !== '' ? $from_name : '-'); ?></span></div>
        <div class="emc-kv"><span class="emc-kv-k">Remetente (e-mail)</span><span class="emc-kv-v"><?php echo esc_html($from_email !== '' ? $from_email : 'NÃO DEFINIDO'); ?><?php if ($from_suspeito): ?> <span class="emc-badge emc-badge--warning">verificar</span><?php endif; ?></span></div>
        <div class="emc-kv"><span class="emc-kv-k">Servidor</span><span class="emc-kv-v"><?php echo esc_html($smtp_host !== '' ? $smtp_host . ($smtp_port !== '' ? ':' . $smtp_port : '') : '-'); ?></span></div>
        <?php if ($from_email === ''): ?><p class="emc-aviso">Sem remetente definido, os e-mails podem não sair ou ir para spam. Defina-o na configuração SMTP.</p><?php endif; ?>
        <?php if ($from_suspeito): ?><p class="emc-aviso">O endereço do remetente parece ter uma gralha. O habitual é <code>noreply@</code>. Confirme na configuração SMTP.</p><?php endif; ?>
        <div class="emc-saude-acoes">
          <?php if ($pode_smtp): ?>
            <a class="sg-finpro-btn sg-finpro-btn-light" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=config_center&sgcc_tab=comunicacao#comunicacao')); ?>">Abrir configuração SMTP</a>
            <button type="button" class="sg-finpro-btn sg-finpro-btn-primary" id="emc-smtp-test">Enviar e-mail de teste</button>
          <?php else: ?>
            <p class="emc-aviso">A configuração do SMTP cabe apenas ao administrador principal. Fale com ele se for preciso alterar o remetente ou o servidor.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="emc-fichacard">
        <h3>Saúde da fila de e-mail</h3>
        <div class="emc-saude-nums">
          <div class="emc-stat emc-stat--warning"><span class="emc-stat-n"><?php echo $q_pend; ?></span><span class="emc-stat-l">Pendentes</span></div>
          <div class="emc-stat emc-stat--danger"><span class="emc-stat-n"><?php echo $q_fail; ?></span><span class="emc-stat-l">Falhados</span></div>
          <div class="emc-stat emc-stat--success"><span class="emc-stat-n"><?php echo $q_sent; ?></span><span class="emc-stat-l">Enviados</span></div>
        </div>
        <div class="emc-saude-acoes">
          <button type="button" class="sg-finpro-btn sg-finpro-btn-light" id="emc-saude-process">Processar fila agora</button>
        </div>
        <?php if (!empty($ult_erros)): ?>
        <h4 class="emc-sub-h">Últimos erros</h4>
        <ul class="emc-erros">
          <?php foreach ($ult_erros as $e): ?>
            <li><strong><?php echo esc_html((string) $e->recipient); ?></strong>: <?php echo esc_html((string) $e->last_error); ?> <span class="emc-hist-data"><?php echo sige_emc_fmt_dt($e->updated_at); ?></span></li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="emc-help emc-help-mt">Sem erros recentes na fila de e-mail.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?php endif; ?>
  <div class="emc-modal-box">
    <div class="emc-modal-head"><strong id="emc-modal-title">Pré-visualização</strong>
      <button type="button" class="emc-modal-x" id="emc-modal-x">&times;</button></div>
    <div class="emc-modal-meta" id="emc-modal-meta"></div>
    <div class="emc-modal-body" id="emc-modal-body"></div>
  </div>
</div>

<style>
.emc-wrap{color:var(--color-ink-800);font-size:var(--fs-base)}
.emc-tabs{display:flex;gap:var(--space-2);border-bottom:1px solid var(--color-ink-200);margin-bottom:var(--space-5)}
.emc-tab{padding:var(--space-3) var(--space-4);text-decoration:none;color:var(--color-ink-600);font-weight:600;border-bottom:2px solid transparent;margin-bottom:-1px}
.emc-tab.is-active{color:var(--color-brand-700);border-bottom-color:var(--color-brand-600)}
.emc-stat{background:var(--color-ink-50);border:1px solid var(--color-ink-200);border-radius:var(--radius-lg);padding:var(--space-3) var(--space-4);display:flex;flex-direction:column;gap:var(--space-1)}
.emc-stat-n{font-size:var(--fs-2xl);font-weight:800;color:var(--color-ink-900)}
.emc-stat-l{font-size:var(--fs-sm);color:var(--color-ink-500)}
.emc-stat--warning .emc-stat-n{color:var(--color-warning-700)}
.emc-stat--success .emc-stat-n{color:var(--color-success-700)}
.emc-stat--danger .emc-stat-n{color:var(--color-danger-700)}
.emc-toolbar{display:flex;flex-wrap:wrap;gap:var(--space-3);align-items:center;margin-bottom:var(--space-3)}
.emc-filtros-grupo{display:flex;gap:var(--space-2);align-items:center;flex-wrap:wrap}
.emc-flabel{font-size:var(--fs-xs);color:var(--color-ink-400);font-weight:700;text-transform:uppercase}
.emc-toolbar-acoes{margin-left:auto;display:flex;gap:var(--space-2)}
.emc-chip{padding:var(--space-1) var(--space-3);border-radius:var(--radius-pill);background:var(--color-ink-100);color:var(--color-ink-600);text-decoration:none;font-size:var(--fs-sm);font-weight:600}
.emc-chip.is-active{background:var(--color-brand-600);color:var(--color-white)}
.emc-bulkbar{display:flex;align-items:center;gap:var(--space-3);background:var(--color-brand-50);border:1px solid var(--color-brand-200);border-radius:var(--radius-md);padding:var(--space-2) var(--space-4);margin-bottom:var(--space-3)}
.emc-bulk-count{color:var(--color-brand-800);font-size:var(--fs-sm)}
.emc-tablewrap{overflow-x:auto}
.emc-col-chk{width:34px}
.emc-dest{font-weight:600;color:var(--color-ink-900)}
.emc-dest-mail{color:var(--color-ink-500);font-size:var(--fs-xs)}
.emc-assunto{max-width:320px}
.emc-ctx{background:var(--color-ink-100);color:var(--color-ink-600);border-radius:var(--radius-sm);padding:2px var(--space-2);font-size:var(--fs-xs);margin-left:var(--space-1)}
.emc-badge{border-radius:var(--radius-pill);padding:2px var(--space-2);font-size:var(--fs-xs);font-weight:700}
.emc-badge--warning{background:var(--color-warning-100);color:var(--color-warning-800)}
.emc-badge--info{background:var(--color-info-100);color:var(--color-info-800)}
.emc-badge--success{background:var(--color-success-100);color:var(--color-success-800)}
.emc-badge--danger{background:var(--color-danger-100);color:var(--color-danger-800)}
.emc-badge--ink{background:var(--color-ink-200);color:var(--color-ink-700)}
.emc-att{color:var(--color-ink-400);font-size:var(--fs-xs);margin-left:var(--space-1)}
.emc-col-acoes{white-space:nowrap}
.emc-act{border:1px solid var(--color-ink-200);background:var(--color-white);color:var(--color-ink-700);border-radius:var(--radius-sm);padding:2px var(--space-2);font-size:var(--fs-xs);font-weight:600;cursor:pointer;margin-right:var(--space-1)}
.emc-act:hover{background:var(--color-ink-50)}
.emc-empty{text-align:center;color:var(--color-ink-400);padding:var(--space-6)}
.emc-dt{color:var(--color-ink-500);white-space:nowrap}
.emc-pag{display:flex;gap:var(--space-1);margin-top:var(--space-3);flex-wrap:wrap}
.emc-pag-i{padding:var(--space-1) var(--space-3);border-radius:var(--radius-sm);background:var(--color-ink-100);color:var(--color-ink-600);text-decoration:none;font-size:var(--fs-sm)}
.emc-pag-i.is-active{background:var(--color-brand-600);color:var(--color-white)}
.emc-help,.emc-vars{background:var(--color-info-50);border:1px solid var(--color-info-200);border-radius:var(--radius-md);padding:var(--space-3);margin-bottom:var(--space-3);font-size:var(--fs-sm);color:var(--color-ink-700)}
.emc-vars code,.emc-help code{background:var(--color-ink-100);border-radius:var(--radius-xs);padding:1px var(--space-1);font-size:var(--fs-xs);margin:2px}
.emc-modelo{border:1px solid var(--color-ink-200);border-radius:var(--radius-lg);padding:var(--space-4);margin-bottom:var(--space-3)}
.emc-modelo-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-3)}
.emc-modelo-head h3{margin:0;font-size:var(--fs-md);color:var(--color-ink-900)}
.emc-field{display:block;margin-bottom:var(--space-3)}
.emc-field span{display:block;font-size:var(--fs-sm);font-weight:600;color:var(--color-ink-600);margin-bottom:var(--space-1)}
.emc-input{width:100%;border:1px solid var(--color-ink-300);border-radius:var(--radius-md);padding:var(--space-2) var(--space-3);font-size:var(--fs-sm);font-family:inherit;color:var(--color-ink-900)}
.emc-input:focus{outline:none;border-color:var(--color-brand-500)}
.emc-save-bar{position:sticky;bottom:0;background:var(--color-white);padding:var(--space-3) 0}
.emc-modal{position:fixed;inset:0;background:rgba(15,23,42,.45);display:flex;align-items:center;justify-content:center;z-index:var(--z-modal);padding:var(--space-4)}
.emc-modal[hidden]{display:none}
.emc-modal-box{background:var(--color-white);border-radius:var(--radius-lg);max-width:680px;width:100%;max-height:84vh;overflow:auto}
.emc-modal-head{display:flex;justify-content:space-between;align-items:center;padding:var(--space-4);border-bottom:1px solid var(--color-ink-200)}
.emc-modal-x{border:0;background:none;font-size:var(--fs-xl);cursor:pointer;color:var(--color-ink-500);line-height:1}
.emc-modal-meta{padding:var(--space-3) var(--space-4);color:var(--color-ink-500);font-size:var(--fs-sm);border-bottom:1px solid var(--color-ink-100)}
.emc-modal-body{padding:var(--space-4);white-space:pre-wrap}
.emc-busca{position:relative;max-width:520px;margin-bottom:var(--space-4)}
.emc-busca-res{position:absolute;left:0;right:0;top:100%;background:var(--color-white);border:1px solid var(--color-ink-200);border-radius:var(--radius-md);box-shadow:0 8px 24px rgba(15,23,42,.10);z-index:var(--z-dropdown);max-height:320px;overflow:auto;margin-top:var(--space-1)}
.emc-busca-item{display:block;width:100%;text-align:left;border:0;background:none;padding:var(--space-2) var(--space-3);cursor:pointer;border-bottom:1px solid var(--color-ink-100)}
.emc-busca-item:hover{background:var(--color-ink-50)}
.emc-busca-item b{color:var(--color-ink-900)}
.emc-busca-item span{color:var(--color-ink-500);font-size:var(--fs-xs)}
.emc-fichacard{background:var(--color-ink-50);border:1px solid var(--color-ink-200);border-radius:var(--radius-lg);padding:var(--space-4);margin-bottom:var(--space-4)}
.emc-fichacard h3{margin:0 0 var(--space-1);color:var(--color-ink-900);font-size:var(--fs-lg)}
.emc-fichacard .emc-ficha-meta{color:var(--color-ink-600);font-size:var(--fs-sm);display:flex;flex-wrap:wrap;gap:var(--space-3);margin-top:var(--space-2)}
.emc-ficha-pref{display:inline-flex;gap:var(--space-1);align-items:center}
.emc-hist-item{display:flex;gap:var(--space-3);padding:var(--space-3);border-bottom:1px solid var(--color-ink-100)}
.emc-hist-dot{flex-shrink:0;width:10px;height:10px;border-radius:var(--radius-pill);margin-top:6px}
.emc-hist-dot--email{background:var(--color-info-500)}
.emc-hist-dot--whatsapp{background:var(--color-success-500)}
.emc-hist-main{flex:1;min-width:0}
.emc-hist-top{display:flex;gap:var(--space-2);align-items:center;flex-wrap:wrap}
.emc-hist-resumo{color:var(--color-ink-700);font-size:var(--fs-sm);margin-top:2px}
.emc-hist-data{color:var(--color-ink-400);font-size:var(--fs-xs);white-space:nowrap}
.emc-hist-vazio{color:var(--color-ink-400);padding:var(--space-6);text-align:center}
.emc-saude-grid{display:grid;grid-template-columns:1fr 1fr;gap:var(--space-4)}
.emc-kv{display:flex;justify-content:space-between;gap:var(--space-3);padding:var(--space-2) 0;border-bottom:1px solid var(--color-ink-100)}
.emc-kv-k{color:var(--color-ink-500);font-size:var(--fs-sm)}
.emc-kv-v{color:var(--color-ink-900);font-weight:600;font-size:var(--fs-sm);text-align:right;word-break:break-all}
.emc-aviso{background:var(--color-warning-50);border:1px solid var(--color-warning-200);color:var(--color-warning-800);border-radius:var(--radius-md);padding:var(--space-2) var(--space-3);font-size:var(--fs-sm);margin:var(--space-3) 0 0}
.emc-saude-acoes{display:flex;gap:var(--space-2);flex-wrap:wrap;margin-top:var(--space-3)}
.emc-saude-nums{display:grid;grid-template-columns:repeat(3,1fr);gap:var(--space-2)}
.emc-sub-h{margin:var(--space-4) 0 var(--space-2);font-size:var(--fs-sm);color:var(--color-ink-600)}
.emc-erros{list-style:none;margin:0;padding:0;font-size:var(--fs-sm)}
.emc-erros li{padding:var(--space-2);border-bottom:1px solid var(--color-ink-100);color:var(--color-ink-700)}
.emc-help-mt{margin-top:var(--space-3)}
@media(max-width:760px){.emc-assunto{max-width:160px}.emc-toolbar-acoes{margin-left:0}.emc-saude-grid{grid-template-columns:1fr}}
.is-danger{background:var(--color-danger-600)!important;color:var(--color-white)!important}
.emc-hist-list{display:flex;flex-direction:column}
.emc-kpi-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:var(--space-4);margin:0 0 var(--space-4)}
@media(max-width:1100px){.emc-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.emc-kpi-grid{grid-template-columns:1fr}}
.emc-toolbar-acoes .sg-finpro-btn,.emc-bulkbar .sg-finpro-btn,.emc-saude-acoes .sg-finpro-btn,.emc-save-bar .sg-finpro-btn,.emc-modelo-head .sg-finpro-btn{width:auto}
</style>

<script <?php echo sige_csp_script_attr(); ?>>
(function(){
  var ajaxurl = <?php echo wp_json_encode($ajaxurl); ?>;
  var nonce   = <?php echo wp_json_encode($nonce); ?>;

  function post(action, data){
    var fd = new FormData();
    fd.append('action', action); fd.append('_wpnonce', nonce);
    Object.keys(data||{}).forEach(function(k){ fd.append(k, data[k]); });
    return fetch(ajaxurl, {method:'POST', credentials:'same-origin', body:fd}).then(function(r){ return r.json(); });
  }
  function errMsg(res){ return (res && res.data && res.data.message) ? res.data.message : 'Operação falhou.'; }

  function seleccionados(){
    return Array.prototype.map.call(document.querySelectorAll('.emc-chk:checked'), function(c){
      return { c: c.getAttribute('data-canal'), id: parseInt(c.getAttribute('data-id'),10) };
    });
  }
  function refreshBulk(){
    var n = document.querySelectorAll('.emc-chk:checked').length;
    var bar = document.getElementById('emc-bulkbar');
    if (bar){ bar.hidden = n === 0; document.getElementById('emc-bulk-n').textContent = n; }
  }
  var rotulos = { cancel: 'Cancelar', retry: 'Reenviar', delete: 'Eliminar' };

  async function aplicar(accao, itens){
    if (!itens.length) return;
    var dlg = {
      cancel: { titulo:'Cancelar envios', texto: itens.length+' envio(s). Apenas os pendentes serão cancelados; os restantes ficam como estão.', confirmar:'Cancelar envios' },
      retry:  { titulo:'Reenviar', texto: itens.length+' envio(s). Apenas os falhados/cancelados voltam à fila como pendentes.', confirmar:'Reenviar' },
      'delete': { titulo:'Eliminar do histórico', texto: itens.length+' envio(s) serão removidos definitivamente (apenas enviados, falhados ou cancelados). Esta acção não pode ser revertida.', confirmar:'Eliminar', perigo:true }
    }[accao];
    if (!(await sigeUi.confirm(dlg))) return;
    var res = await post('sige_comm_action', { accao: accao, itens: JSON.stringify(itens) });
    if (!res.success){ sigeUi.toast(errMsg(res), 'erro'); return; }
    var d = res.data;
    var msg = d.ok + ' concluído(s)' + (d.falhou ? ', ' + d.falhou + ' ignorado(s)' : '');
    sigeUi.toast(msg, d.falhou && !d.ok ? 'erro' : 'ok');
    setTimeout(function(){ location.reload(); }, 700);
  }

  document.addEventListener('change', function(ev){
    if (ev.target.id === 'emc-chk-all'){
      document.querySelectorAll('.emc-chk').forEach(function(c){ c.checked = ev.target.checked; });
      refreshBulk();
    } else if (ev.target.classList.contains('emc-chk')){
      refreshBulk();
    }
  });

  document.addEventListener('click', async function(ev){
    var b = ev.target.closest('.emc-act, .emc-bulk, #emc-process, #emc-save-templates, #emc-modal-x, .emc-modal, #emc-smtp-test, #emc-saude-process');
    if (!b) return;

    if (b.classList.contains('emc-act-view')){
      var res = await post('sige_comm_get_full', { id: b.getAttribute('data-id'), canal: b.getAttribute('data-canal') });
      if (!res.success){ sigeUi.toast(errMsg(res), 'erro'); return; }
      var d = res.data;
      document.getElementById('emc-modal-title').textContent = d.titulo || 'Pre-visualizacao';
      document.getElementById('emc-modal-meta').textContent = 'Para: ' + d.destinatario + '  -  Estado: ' + d.estado + (d.erro ? '  -  ' + d.erro : '');
      var body = document.getElementById('emc-modal-body');
      if (d.canal === 'whatsapp'){ body.textContent = d.corpo_texto || ''; } else { body.innerHTML = d.corpo_html || ''; }
      document.getElementById('emc-modal').hidden = false;
      return;
    }
    if (b.id === 'emc-modal-x' || (b.classList.contains('emc-modal') && !ev.target.closest('.emc-modal-box'))){
      document.getElementById('emc-modal').hidden = true; return;
    }
    if (b.classList.contains('emc-act-do')){
      aplicar(b.getAttribute('data-accao'), [{ c: b.getAttribute('data-canal'), id: parseInt(b.getAttribute('data-id'),10) }]);
      return;
    }
    if (b.classList.contains('emc-bulk')){
      var sel = seleccionados();
      if (!sel.length){ sigeUi.toast('Seleccione pelo menos um envio.', 'erro'); return; }
      aplicar(b.getAttribute('data-accao'), sel);
      return;
    }
    if (b.id === 'emc-process'){
      b.disabled = true; var old = b.textContent; b.textContent = 'A processar...';
      var rp = await post('sige_emc_process_now', {});
      if (rp.success){ sigeUi.toast('E-mails processados.', 'ok'); setTimeout(function(){ location.reload(); }, 600); }
      else { sigeUi.toast(errMsg(rp), 'erro'); b.disabled = false; b.textContent = old; }
      return;
    }
    if (b.id === 'emc-saude-process'){
      b.disabled = true; var old2 = b.textContent; b.textContent = 'A processar...';
      var rp2 = await post('sige_emc_process_now', {});
      if (rp2.success){ sigeUi.toast('Fila de e-mail processada.', 'ok'); setTimeout(function(){ location.reload(); }, 600); }
      else { sigeUi.toast(errMsg(rp2), 'erro'); b.disabled = false; b.textContent = old2; }
      return;
    }
    if (b.id === 'emc-smtp-test'){
      var dest = await sigeUi.prompt({ titulo:'Enviar e-mail de teste', texto:'Para que endereço quer enviar o teste? Deixe vazio para usar o seu e-mail de utilizador.', placeholder:'nome@exemplo.mz', confirmar:'Enviar teste' });
      if (dest === null) return;
      b.disabled = true; var old3 = b.textContent; b.textContent = 'A enviar...';
      var rt2 = await post('sige_emc_smtp_test', { to: dest });
      if (rt2.success){ sigeUi.toast('E-mail de teste enviado para ' + rt2.data.to + '. Confirme a recepção.', 'ok'); }
      else { sigeUi.toast(errMsg(rt2), 'erro'); }
      b.disabled = false; b.textContent = old3;
      return;
    }

    // Modelos
    if (b.classList.contains('emc-act-reset')){
      if (!(await sigeUi.confirm({ titulo:'Repor texto original', texto:'O texto deste modelo volta ao original do sistema. As suas alterações guardadas para este modelo serão removidas.', confirmar:'Repor original' }))) return;
      var rr = await post('sige_emc_reset_template', { key: b.getAttribute('data-key') });
      if (!rr.success){ sigeUi.toast(errMsg(rr), 'erro'); return; }
      var box = b.closest('.emc-modelo');
      box.querySelector('.emc-subject').value = rr.data.subject || '';
      box.querySelector('.emc-body').value = rr.data.body || '';
      sigeUi.toast('Texto original reposto. Lembre-se de guardar.', 'ok');
      return;
    }
    if (b.classList.contains('emc-act-test')){
      var to = await sigeUi.prompt({ titulo:'Enviar e-mail de teste', texto:'Para que endereço quer enviar o teste? Deixe vazio para usar o seu e-mail de utilizador.', placeholder:'nome@exemplo.mz', confirmar:'Enviar teste' });
      if (to === null) return;
      var rt = await post('sige_emc_test_template', { key: b.getAttribute('data-key'), to: to });
      if (rt.success){ sigeUi.toast('E-mail de teste enviado para ' + rt.data.to + '.', 'ok'); } else { sigeUi.toast(errMsg(rt), 'erro'); }
      return;
    }
    if (b.id === 'emc-save-templates'){
      var fd = new FormData();
      fd.append('action', 'sige_emc_save_templates'); fd.append('_wpnonce', nonce);
      document.querySelectorAll('.emc-modelo').forEach(function(m){
        var k = m.getAttribute('data-key');
        fd.append('subject[' + k + ']', m.querySelector('.emc-subject').value);
        fd.append('body[' + k + ']', m.querySelector('.emc-body').value);
      });
      b.disabled = true;
      try { var resp = await fetch(ajaxurl, {method:'POST', credentials:'same-origin', body:fd}); var rs = await resp.json();
        sigeUi.toast(rs.success ? 'Modelos guardados.' : errMsg(rs), rs.success ? 'ok' : 'erro');
      } catch(e){ sigeUi.toast('Falha de ligação. Tente novamente.', 'erro'); }
      b.disabled = false;
      return;
    }
  });

  // ----- Por encarregado (historico cross-canal) -----
  var buscaQ = document.getElementById('emc-busca-q');
  var buscaRes = document.getElementById('emc-busca-res');
  var histBox = document.getElementById('emc-hist');
  var buscaTimer = null;
  function esc(s){ var d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; }
  var estadoLbl = { pendente:'Pendente', a_enviar:'A enviar', enviado:'Enviado', falhou:'Falhou', cancelado:'Cancelado' };
  var estadoCor = { pendente:'warning', a_enviar:'info', enviado:'success', falhou:'danger', cancelado:'ink' };
  function fmtData(s){ if(!s) return '-'; var d = new Date(String(s).replace(' ','T')); if (isNaN(d)) return esc(s); return d.toLocaleDateString('pt-PT') + ' ' + d.toLocaleTimeString('pt-PT', {hour:'2-digit', minute:'2-digit'}); }

  if (buscaQ){
    buscaQ.addEventListener('input', function(){
      clearTimeout(buscaTimer);
      var q = buscaQ.value.trim();
      if (q.length < 2){ buscaRes.hidden = true; buscaRes.innerHTML = ''; return; }
      buscaTimer = setTimeout(async function(){
        var r = await post('sige_comm_buscar', { q: q });
        if (!r.success){ return; }
        var lst = (r.data && r.data.alunos) || [];
        if (!lst.length){ buscaRes.innerHTML = '<div class="emc-busca-item">Sem resultados.</div>'; buscaRes.hidden = false; return; }
        buscaRes.innerHTML = lst.map(function(a){
          var sub = (a.processo ? 'Proc. ' + esc(a.processo) : '') + (a.encarregado ? ' - ' + esc(a.encarregado) : '');
          return '<button type="button" class="emc-busca-item" data-id="' + a.id + '"><b>' + esc(a.nome) + '</b> <span>' + sub + '</span></button>';
        }).join('');
        buscaRes.hidden = false;
      }, 250);
    });
    document.addEventListener('click', function(ev){
      var it = ev.target.closest('.emc-busca-item');
      if (it && it.getAttribute('data-id')){ carregarHistorico(parseInt(it.getAttribute('data-id'), 10)); buscaRes.hidden = true; buscaQ.value = ''; }
      else if (!ev.target.closest('.emc-busca')){ if (buscaRes) buscaRes.hidden = true; }
    });
  }

  async function carregarHistorico(alunoId){
    if (!histBox) return;
    histBox.innerHTML = '<div class="emc-hist-vazio">A carregar...</div>';
    var r = await post('sige_comm_historico', { aluno_id: alunoId });
    if (!r.success){ histBox.innerHTML = '<div class="emc-hist-vazio">' + esc(errMsg(r)) + '</div>'; return; }
    var f = r.data.ficha, rows = r.data.rows || [];
    var contactos = [];
    (f.emails || []).forEach(function(e){ contactos.push(esc(e)); });
    (f.telefones || []).forEach(function(t){ contactos.push(esc(t)); });
    var pref = f.canal_pref ? '<span class="emc-ficha-pref">Canal preferido: <span class="emc-badge emc-badge--' + (f.canal_pref === 'email' ? 'info' : 'success') + '">' + esc(f.canal_pref) + '</span></span>' : '';
    var html = '<div class="emc-fichacard"><h3>' + esc(f.nome) + '</h3><div class="emc-ficha-meta">'
      + (f.processo ? '<span>Processo: ' + esc(f.processo) + '</span>' : '')
      + (f.encarregado ? '<span>Encarregado: ' + esc(f.encarregado) + (f.parentesco ? ' (' + esc(f.parentesco) + ')' : '') + '</span>' : '')
      + (contactos.length ? '<span>' + contactos.join(' - ') + '</span>' : '')
      + pref + '</div></div>';
    if (!rows.length){
      html += '<div class="emc-hist-vazio">Ainda não há comunicações registadas para este aluno.</div>';
    } else {
      html += '<div class="emc-hist-list">';
      rows.forEach(function(x){
        html += '<div class="emc-hist-item"><span class="emc-hist-dot emc-hist-dot--' + (x.canal === 'whatsapp' ? 'whatsapp' : 'email') + '"></span>'
          + '<div class="emc-hist-main"><div class="emc-hist-top">'
          + '<span class="emc-badge emc-badge--' + (x.canal === 'whatsapp' ? 'success' : 'info') + '">' + (x.canal === 'whatsapp' ? 'WhatsApp' : 'E-mail') + '</span>'
          + '<span class="emc-badge emc-badge--' + (estadoCor[x.estado] || 'ink') + '">' + (estadoLbl[x.estado] || esc(x.estado)) + '</span>'
          + (x.contexto ? '<span class="emc-ctx">' + esc(x.contexto) + '</span>' : '')
          + '</div><div class="emc-hist-resumo">' + esc(x.resumo) + '</div></div>'
          + '<div class="emc-hist-data">' + fmtData(x.data) + '</div></div>';
      });
      html += '</div>';
    }
    histBox.innerHTML = html;
  }

  if (histBox && parseInt(histBox.getAttribute('data-aluno'), 10) > 0){
    carregarHistorico(parseInt(histBox.getAttribute('data-aluno'), 10));
  }
})();
</script>
