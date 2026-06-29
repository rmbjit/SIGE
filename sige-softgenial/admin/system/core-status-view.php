<?php
if (!defined('ABSPATH')) exit;

// [12.9.6] Guarda dupla:
// 1) Matriz SIGE - permissão `sistema.estado_ver` é a fonte de verdade.
// 2) SIGE_Core::can_access_core_admin() preservado como camada de defesa
//    em profundidade para a classe Core, que tem regras adicionais.
if (!sige_page_guard(
    ['sistema.estado_ver'],
    [] // só admin WP real passa via bypass; legado fica delegado ao SIGE_Core
)) return;

if (class_exists('SIGE_Core') && !SIGE_Core::can_access_core_admin()) {
    SIGE_Core::deny_core_access();
}
if (!class_exists('SIGE_Core') && !(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) {
    wp_die('Sem permissão para aceder ao estado do sistema.', 'Acesso restrito', ['response' => 403]);
}

$data = class_exists('SIGE_Diagnostics') ? SIGE_Diagnostics::collect() : [];
$license = $data['license'] ?? [];
$tables = $data['tables'] ?? [];
$queue = $data['queue'] ?? [];
$logs = $data['logs'] ?? [];
$build = $data['build'] ?? [];
$status = $license['status'] ?? 'unknown';
$status_label = [
    'active' => 'Activa',
    'grace' => 'Tolerância local',
    'offline' => 'Servidor indisponível',
    'invalid' => 'Inválida',
    'unknown' => 'Não validada',
][$status] ?? ucfirst((string)$status);
$status_class = $status === 'active' ? 'ok' : ($status === 'grace' ? 'grace' : 'warn');
$days_to_expiry = isset($license['days_to_expiry']) && is_int($license['days_to_expiry']) ? (int)$license['days_to_expiry'] : null;
$expires_at = $license['expires_at'] ?? '';
$modules = isset($license['modules']) && is_array($license['modules']) ? $license['modules'] : [];
$enforcement = class_exists('SIGE_License') ? SIGE_License::enforcement_preview($license) : ['level'=>'none','title'=>'Sem restrições','message'=>'','actions'=>[]];
$timezone_warning = (($data['timezone'] ?? '') !== 'Africa/Maputo');

$core_icon = static function (string $name): string {
    if (function_exists('sige_ui_icon')) {
        return sige_ui_icon($name);
    }
    $map = [
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/>',
        'key' => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="M12 12l8-8"/><path d="M15 5l4 4"/><path d="M17 3l4 4"/>',
        'server' => '<rect x="2" y="3" width="20" height="8" rx="2"/><rect x="2" y="13" width="20" height="8" rx="2"/><path d="M6 7h.01"/><path d="M6 17h.01"/>',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/>',
        'queue' => '<path d="M3 7h18"/><path d="M3 12h18"/><path d="M3 17h18"/><path d="M7 7v10"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.8"/><rect x="14" y="3" width="7" height="7" rx="1.8"/><rect x="3" y="14" width="7" height="7" rx="1.8"/><rect x="14" y="14" width="7" height="7" rx="1.8"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
        'refresh' => '<path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
    ];
    $path = $map[$name] ?? $map['shield'];
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};

?>
<div class="sg-core-wrap">
    <section class="sg-core-hero" aria-label="Saúde do Sistema">
        <div class="sg-core-hero-main">
            <div class="sg-core-kicker"><?php echo $core_icon('heart'); ?><span>Configurações</span></div>
            <h1>Saúde do Sistema</h1>
            <p>Acompanhe a condição operacional, licença, comunicação, versionamento e preparação da instalação para uso seguro em escala.</p>
            <div class="sg-core-chips">
                <span><?php echo $core_icon('server'); ?> Core <?php echo esc_html($data['core_version'] ?? 'N/D'); ?></span>
                <span><?php echo $core_icon('file'); ?> Plugin <?php echo esc_html($data['plugin_version'] ?? 'N/D'); ?></span>
                <span><?php echo $core_icon('clock'); ?> <?php echo esc_html($data['timezone'] ?? 'N/D'); ?></span>
            </div>
        </div>
        <aside class="sg-core-badge <?php echo esc_attr($status_class); ?>" aria-label="Estado da licença">
            <span><?php echo $core_icon('key'); ?> Licença</span>
            <strong><?php echo esc_html($status_label); ?></strong>
            <small><?php echo $days_to_expiry === null ? 'Validação conforme cache local.' : esc_html((string)$days_to_expiry) . ' dia(s) restante(s).'; ?></small>
        </aside>
    </section>

    <?php if (!empty($_GET['saved'])): ?>
        <div class="sg-core-alert ok">Configuração guardada com sucesso.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['license'])): ?>
        <div class="sg-core-alert <?php echo $_GET['license'] === 'checked' ? 'ok' : 'warn'; ?>">
            <?php echo $_GET['license'] === 'checked' ? 'Licença validada ou mantida pela cache local.' : 'Não foi possível validar a licença neste momento. Verifique a configuração abaixo.'; ?>
        </div>
    <?php endif; ?>

    <?php if ($days_to_expiry !== null && $days_to_expiry >= 0 && $days_to_expiry <= 7): ?>
        <div class="sg-core-alert warn">A licença expira em <?php echo esc_html((string)$days_to_expiry); ?> dia(s). Regularize antes do fim do prazo para evitar entrada em tolerância.</div>
    <?php elseif ($status === 'grace'): ?>
        <div class="sg-core-alert grace">O sistema está a funcionar pela tolerância local. Valide novamente quando a ligação ao servidor central estiver estável.</div>
    <?php elseif (in_array($status, ['invalid','offline','unknown'], true)): ?>
        <div class="sg-core-alert warn">A licença requer atenção. Nesta versão não há bloqueio operacional; apenas monitoria e aviso preventivo.</div>
    <?php endif; ?>

    <?php if ($timezone_warning): ?>
        <div class="sg-core-alert info">Fuso horário detectado: <?php echo esc_html($data['timezone'] ?? 'N/D'); ?>. Para consistência em Moçambique, recomenda-se configurar WordPress para <strong>Africa/Maputo</strong>.</div>
    <?php endif; ?>

    <div class="sg-core-grid">
        <section class="sg-core-card">
            <h2><?php echo $core_icon('key'); ?> Licenciamento central</h2>
            <p class="sg-muted">Valida com o servidor central, guarda cache local e mantém tolerância quando a internet falha.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sg-core-form">
                <?php wp_nonce_field('sige_core_save_license_settings'); ?>
                <input type="hidden" name="action" value="sige_core_save_license_settings">
                <label>ID do cliente
                    <input type="text" name="client_id" value="<?php echo esc_attr(get_option('sige_license_client_id', '')); ?>">
                </label>
                <label>Chave da licença
                    <input type="password" name="license_key" value="" autocomplete="new-password" placeholder="<?php echo get_option('sige_license_key') ? 'Chave guardada - deixe em branco para manter' : 'Ex.: SG-MALISA-2026-XXXX'; ?>">
                    <small class="sg-field-note">Por segurança, a chave guardada nunca é exibida. Preencha apenas para substituir.</small>
                </label>
                <label>Endpoint do servidor central
                    <input type="url" name="server_url" value="<?php echo esc_attr(get_option('sige_license_server_url', '')); ?>">
                </label>
                <label>Dias de tolerância local
                    <input type="number" min="1" max="30" name="grace_days" value="<?php echo esc_attr((int)get_option('sige_license_grace_days', 7)); ?>">
                </label>
                <label class="sg-checkbox-line">
                    <input type="checkbox" name="clear_license_key" value="1">
                    Remover chave actual
                </label>
                <button class="sg-core-btn" type="submit"><?php echo $core_icon('save'); ?> Guardar configuração</button>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px;">
                <?php wp_nonce_field('sige_core_force_license_check'); ?>
                <input type="hidden" name="action" value="sige_core_force_license_check">
                <button class="sg-core-btn secondary" type="submit"><?php echo $core_icon('refresh'); ?> Validar agora</button>
            </form>
        </section>

        <section class="sg-core-card">
            <h2><?php echo $core_icon('server'); ?> Resumo operacional</h2>
            <dl class="sg-core-dl">
                <dt>Plugin</dt><dd><?php echo esc_html($data['plugin_version'] ?? 'N/D'); ?></dd>
                <dt>Build</dt><dd><?php echo esc_html($build['build_id'] ?? 'N/D'); ?></dd>
                <dt>Versionamento</dt><dd><?php echo !empty($build['ok']) ? '<span class="ok">OK</span>' : '<span class="warn">Rever</span>'; ?></dd>
                <dt>Core</dt><dd><?php echo esc_html($data['core_version'] ?? 'N/D'); ?></dd>
                <dt>WordPress</dt><dd><?php echo esc_html($data['wp_version'] ?? 'N/D'); ?></dd>
                <dt>PHP</dt><dd><?php echo esc_html($data['php_version'] ?? 'N/D'); ?></dd>
                <dt>MySQL</dt><dd><?php echo esc_html($data['mysql_version'] ?? 'N/D'); ?></dd>
                <dt>Fuso horário</dt><dd><?php echo esc_html($data['timezone'] ?? 'N/D'); ?></dd>
                <dt>Expiração</dt><dd><?php echo esc_html($expires_at ?: 'N/D'); ?></dd>
                <dt>Dias restantes</dt><dd><?php echo $days_to_expiry === null ? 'N/D' : esc_html((string)$days_to_expiry); ?></dd>
                <dt>Plano</dt><dd><?php echo esc_html($license['plan'] ?? 'N/D'); ?></dd>
                <dt>Versão header</dt><dd><?php echo esc_html($build['header_version'] ?? 'N/D'); ?></dd>
                <dt>Versão manifesto</dt><dd><?php echo esc_html($build['manifest_version'] ?? 'N/D'); ?></dd>
            </dl>
            <div class="sg-core-note">
                <strong>Estado da licença:</strong><br>
                <?php echo esc_html($license['message'] ?? 'Sem informação.'); ?><br>
                <?php if (!empty($license['checked_at'])): ?>Última validação/cache: <?php echo esc_html(wp_date('Y-m-d H:i:s', (int)$license['checked_at'])); ?><?php endif; ?>
            </div>
        </section>
    </div>

    <div class="sg-core-grid lower">
        <?php if (function_exists('sige_saude_operacional_card')) sige_saude_operacional_card(); ?>
        <section class="sg-core-card">
            <h2><?php echo $core_icon('database'); ?> Tabelas críticas</h2>
            <div class="sg-table-list">
                <?php foreach ($tables as $name => $state): ?>
                    <div class="sg-table-row"><code><?php echo esc_html($name); ?></code><span class="<?php echo $state === 'ok' ? 'ok' : 'warn'; ?>"><?php echo esc_html($state); ?></span></div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="sg-core-card">
            <h2><?php echo $core_icon('queue'); ?> Fila futura de notificações</h2>
            <p class="sg-muted">Base preparada para cobranças, WhatsApp e E-mail em fila, sem travar páginas.</p>
            <dl class="sg-core-dl compact">
                <dt>Total</dt><dd><?php echo esc_html((string)($queue['total'] ?? 0)); ?></dd>
                <dt>Pendentes</dt><dd><?php echo esc_html((string)($queue['pending'] ?? 0)); ?></dd>
                <dt>Concluídas</dt><dd><?php echo esc_html((string)($queue['done'] ?? 0)); ?></dd>
                <dt>Falhadas</dt><dd><?php echo esc_html((string)($queue['failed'] ?? 0)); ?></dd>
            </dl>
        </section>
    </div>

    <div class="sg-core-grid lower">
        <section class="sg-core-card">
            <h2><?php echo $core_icon('bell'); ?> Avisos e bloqueios progressivos</h2>
            <p class="sg-muted">Preparação de enforcement leve, sem bloquear dados nem interromper a operação da escola nesta versão.</p>
            <div class="sg-enforcement <?php echo esc_attr($enforcement['level'] ?? 'none'); ?>">
                <strong><?php echo esc_html($enforcement['title'] ?? 'Sem restrições'); ?></strong>
                <p><?php echo esc_html($enforcement['message'] ?? ''); ?></p>
                <?php if (!empty($enforcement['actions']) && is_array($enforcement['actions'])): ?>
                    <ul>
                        <?php foreach ($enforcement['actions'] as $action): ?>
                            <li><?php echo esc_html($action); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="sg-core-card">
            <h2><?php echo $core_icon('grid'); ?> Módulos do plano</h2>
            <p class="sg-muted">Visão de permissões por plano. A aplicação real de limites fica preparada para a próxima fase.</p>
            <?php if (!$modules): ?>
                <p class="sg-muted">Nenhum módulo retornado pelo servidor central. Será usado o perfil padrão do plano.</p>
            <?php else: ?>
                <div class="sg-module-list">
                    <?php foreach ($modules as $module): ?>
                        <span><?php echo esc_html(str_replace('_', ' ', $module)); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <section class="sg-core-card logs">
        <h2><?php echo $core_icon('file'); ?> Últimos registos operacionais</h2>
        <?php if (!$logs): ?>
            <p class="sg-muted">Ainda não existem registos operacionais recentes.</p>
        <?php else: ?>
            <div class="sg-log-list">
                <?php foreach ($logs as $log): ?>
                    <div class="sg-log-row">
                        <span><?php echo esc_html($log['time'] ?? ''); ?></span>
                        <strong><?php echo esc_html(strtoupper($log['level'] ?? 'INFO')); ?></strong>
                        <em><?php echo esc_html($log['message'] ?? ''); ?></em>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<style id="sige-core-status-produto-pro-v121088">
/* SIGE SoftGenial v12.10.88 - Saúde do Sistema: Compliance Visual Integral
   Escopo visual apenas: não altera diagnósticos, licença, segurança, permissões,
   tabelas, logs, fila, base de dados, notas, ACTA, DEC, pautas ou regras académicas. */
.sg-core-wrap{
    --core-blue:var(--sg-theme-primary,var(--color-brand-500));
    --core-blue-dark:var(--sg-theme-primary-800,var(--color-ink-700));
    --core-purple:var(--color-brand-500);
    --core-purple-soft:var(--color-brand-50);
    --core-ink:var(--color-black);
    --core-muted:var(--color-slate-700);
    --core-line:var(--color-ink-100);
    --core-green:var(--color-success-500);
    --core-red:var(--color-danger-500);
    --core-amber:var(--color-warning-500);
    width:100%;
    max-width:none;
    margin:0;
    padding:0 0 28px;
    display:flex;
    flex-direction:column;
    gap:18px;
    color:var(--core-ink);
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif);
}
.sg-core-wrap *{box-sizing:border-box}
.sg-core-wrap svg{width:18px;height:18px;display:block;stroke:currentColor!important;color:currentColor!important;fill:none!important}

/* HERO - padrão Painel Principal / Dashboard V2 MJS-grade */
.sg-core-hero{
    position:relative;
    overflow:hidden;
    min-height:178px;
    border-radius:var(--radius-xl);
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-info-50) 100%);
    border:1px solid rgba(92,64,187,.12);
    box-shadow:var(--shadow-lg);
    padding:32px 34px;
    margin:0;
    display:grid;
    grid-template-columns:minmax(0,1.04fr) minmax(320px,.96fr);
    gap:22px;
    align-items:center;
    color:var(--core-ink);
}
.sg-core-hero:before{
    content:"";
    position:absolute;
    inset:auto -80px -130px auto;
    width:420px;
    height:300px;
    border-radius:var(--radius-pill);
    background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);
    pointer-events:none;
}
.sg-core-hero-main,.sg-core-badge{position:relative;z-index:1}
.sg-core-kicker{
    display:inline-flex;
    align-items:center;
    gap:var(--space-2);
    margin:0 0 10px;
    color:var(--core-blue);
    font-size:12px;
    line-height:1.2;
    font-weight:700;
    letter-spacing:.11em;
    text-transform:uppercase;
}
.sg-core-hero h1{
    margin:0;
    max-width:650px;
    color:var(--color-black);
    font-size:31px;
    line-height:1.08;
    font-weight:700;
    letter-spacing:-.04em;
    font-family:inherit;
}
.sg-core-hero p{
    max-width:650px;
    margin:var(--space-3) 0 0;
    color:var(--color-slate-700);
    font-size:15px;
    line-height:1.65;
    font-weight:500;
}
.sg-core-chips{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
.sg-core-chips span{
    display:inline-flex;
    align-items:center;
    gap:var(--space-2);
    min-height:38px;
    padding:var(--space-2) var(--space-3);
    border-radius:var(--radius-pill);
    background:var(--color-white);
    border:1px solid var(--color-slate-100);
    color:var(--color-slate-700);
    font-size:12px;
    font-weight:700;
    box-shadow:var(--shadow-sm);
}
.sg-core-chips svg{color:var(--core-purple)}
.sg-core-badge{
    min-height:148px;
    border-radius:var(--radius-xl);
    background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.18));
    padding:22px;
    overflow:hidden;
    display:flex;
    flex-direction:column;
    justify-content:center;
    gap:10px;
    border:1px solid rgba(92,64,187,.08);
}
.sg-core-badge:before{
    content:"";
    position:absolute;
    right:22px;
    bottom:16px;
    width:112px;
    height:92px;
    border-radius:22px 22px 12px 12px;
    background:rgba(109,93,252,.16);
    box-shadow:inset 0 0 0 2px rgba(109,93,252,.12);
}
.sg-core-badge>*{position:relative;z-index:1}
.sg-core-badge span{
    display:flex;
    align-items:center;
    gap:var(--space-2);
    color:var(--core-purple);
    font-size:12px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.11em;
}
.sg-core-badge strong{
    display:block;
    color:var(--color-ink-900);
    font-size:36px;
    line-height:1.05;
    font-weight:700;
    letter-spacing:-.045em;
}
.sg-core-badge small{
    display:block;
    max-width:330px;
    color:var(--color-slate-600);
    font-size:var(--fs-sm);
    line-height:1.55;
    font-weight:600;
}
.sg-core-badge.ok{background:linear-gradient(135deg,rgba(22,163,74,.08),rgba(22,163,74,.16))}
.sg-core-badge.ok span{color:var(--color-success-800)}
.sg-core-badge.grace{background:linear-gradient(135deg,rgba(11,74,143,.08),rgba(11,74,143,.16))}
.sg-core-badge.warn{background:linear-gradient(135deg,rgba(245,158,11,.10),rgba(245,158,11,.20))}
.sg-core-badge.warn span{color:var(--color-warning-800)}

/* alertas */
.sg-core-alert{
    display:flex;
    align-items:flex-start;
    gap:10px;
    padding:13px 16px;
    border-radius:var(--radius-lg);
    margin:0;
    font-weight:700;
    font-size:var(--fs-sm);
    line-height:1.5;
    border:1px solid transparent;
    box-shadow:var(--shadow-sm);
}
.sg-core-alert.ok{background:var(--color-success-50);color:var(--color-success-900);border-color:var(--color-success-200)}
.sg-core-alert.warn{background:var(--color-warning-50);color:var(--color-warning-800);border-color:var(--color-warning-200)}
.sg-core-alert.grace{background:var(--sg-theme-soft,var(--color-brand-50));color:var(--color-info-600);border-color:var(--sg-theme-soft,var(--color-brand-50))}
.sg-core-alert.info{background:var(--color-slate-50);color:var(--color-slate-800);border-color:var(--color-ink-100)}

/* grids/cards */
.sg-core-grid{
    display:grid;
    grid-template-columns:minmax(0,1.15fr) minmax(0,.85fr);
    gap:18px;
    margin:0;
}
.sg-core-grid.lower{grid-template-columns:repeat(2,minmax(0,1fr))}
.sg-core-card{
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    border-radius:var(--radius-xl);
    padding:22px;
    box-shadow:var(--shadow-md);
    overflow:hidden;
}
.sg-core-card h2{
    display:flex;
    align-items:center;
    gap:10px;
    margin:0 0 10px;
    font-size:17px;
    line-height:1.25;
    color:var(--color-ink-500);
    font-weight:700;
    letter-spacing:-.03em;
}
.sg-core-card h2 svg{
    width:38px;
    height:38px;
    padding:10px;
    border-radius:var(--radius-md);
    background:var(--color-brand-50);
    color:var(--core-purple)!important;
    flex:0 0 auto;
}
.sg-muted{color:var(--color-slate-500);margin:0 0 var(--space-4);font-size:var(--fs-sm);line-height:1.55;font-weight:600}

/* formulários */
.sg-core-form{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:14px;
    margin-top:14px;
}
.sg-core-form label{
    display:flex;
    flex-direction:column;
    gap:7px;
    font-weight:700;
    font-size:var(--fs-xs);
    color:var(--color-slate-600);
    text-transform:uppercase;
    letter-spacing:.07em;
}
.sg-core-form input{
    width:100%;
    min-width:0;
    min-height:44px;
    margin:0;
    border:1px solid var(--color-ink-100);
    border-radius:var(--radius-md);
    padding:0 13px;
    font-size:var(--fs-sm);
    color:var(--color-ink-500);
    font-weight:600;
    background:var(--color-white);
    box-shadow:var(--shadow-sm);
    outline:none;
}
.sg-core-form input:focus{
    border-color:rgba(90,63,214,.55);
    box-shadow:var(--shadow-xs);
}
.sg-field-note{
    display:block;
    margin-top:0;
    color:var(--color-slate-500);
    font-weight:600;
    line-height:1.35;
    letter-spacing:0;
    text-transform:none;
}
.sg-checkbox-line{
    display:flex!important;
    flex-direction:row!important;
    align-items:center;
    gap:9px;
    margin-top:4px;
    letter-spacing:0!important;
    text-transform:none!important;
}
.sg-checkbox-line input{width:auto!important;min-height:auto!important;margin:0!important;box-shadow:none!important}
.sg-core-btn{
    min-height:44px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:var(--space-2);
    border:0;
    border-radius:var(--radius-md);
    background:linear-gradient(135deg,var(--core-blue),var(--core-blue-dark));
    color:var(--color-white);
    padding:0 var(--space-4);
    font-weight:700;
    cursor:pointer;
    font-size:var(--fs-sm);
    font-family:inherit;
    box-shadow:var(--shadow-sm);
    transition:transform .18s ease,box-shadow .18s ease,background .18s ease;
}
.sg-core-btn:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(15,23,42,.08)}
.sg-core-btn.secondary{
    background:var(--color-white);
    color:var(--color-ink-900);
    border:1px solid var(--color-ink-100);
    box-shadow:var(--shadow-sm);
}
.sg-core-btn.secondary:hover{box-shadow:0 4px 16px rgba(15,23,42,.08)}

/* detalhes */
.sg-core-dl{
    display:grid;
    grid-template-columns:minmax(128px,.7fr) minmax(0,1fr);
    gap:10px 16px;
    margin:14px 0;
}
.sg-core-dl dt{
    font-size:12px;
    font-weight:700;
    color:var(--color-slate-500);
}
.sg-core-dl dd{
    margin:0;
    font-weight:700;
    color:var(--color-ink-500);
    min-width:0;
    overflow-wrap:anywhere;
}
.sg-core-dl.compact{grid-template-columns:minmax(120px,.6fr) minmax(0,1fr)}
.sg-core-dl .ok,.sg-core-dl .warn{display:inline-flex;align-items:center;padding:5px 9px;border-radius:var(--radius-pill);font-size:var(--fs-xs);font-weight:700}
.sg-core-dl .ok{background:var(--color-success-50);color:var(--color-success-900)}
.sg-core-dl .warn{background:var(--color-warning-50);color:var(--color-warning-800)}
.sg-core-note{
    background:var(--color-slate-50);
    border:1px solid var(--color-ink-100);
    border-radius:var(--radius-lg);
    padding:14px;
    color:var(--color-slate-800);
    font-size:var(--fs-sm);
    line-height:1.55;
    font-weight:600;
}

/* listas */
.sg-table-list,.sg-log-list{display:grid;gap:var(--space-2);margin-top:12px}
.sg-table-row,.sg-log-row{
    display:flex;
    justify-content:space-between;
    gap:var(--space-3);
    align-items:center;
    padding:11px 12px;
    background:var(--color-slate-50);
    border:1px solid var(--color-slate-100);
    border-radius:var(--radius-md);
    min-width:0;
}
.sg-table-row code{
    color:var(--color-ink-500);
    font-size:12px;
    overflow-wrap:anywhere;
}
.sg-table-row span.ok,.sg-table-row span.warn{
    display:inline-flex;
    align-items:center;
    padding:5px 9px;
    border-radius:var(--radius-pill);
    font-size:var(--fs-xs);
    font-weight:700;
    flex:0 0 auto;
}
.sg-table-row span.ok{background:var(--color-success-50);color:var(--color-success-900)}
.sg-table-row span.warn{background:var(--color-warning-50);color:var(--color-warning-800)}
.sg-log-row strong{
    font-size:var(--fs-xs);
    border-radius:var(--radius-pill);
    background:var(--color-ink-100);
    color:var(--color-slate-800);
    padding:5px 9px;
    flex:0 0 auto;
}
.sg-log-row span{
    font-size:12px;
    color:var(--color-slate-500);
    font-weight:600;
    flex:0 0 auto;
}
.sg-log-row em{
    font-style:normal;
    flex:1;
    text-align:right;
    color:var(--color-slate-800);
    min-width:0;
    overflow-wrap:anywhere;
    font-size:var(--fs-sm);
}

/* enforcement e módulos */
.sg-enforcement{
    background:var(--color-slate-50);
    border:1px solid var(--color-ink-100);
    border-radius:var(--radius-lg);
    padding:14px;
}
.sg-enforcement strong{display:block;margin-bottom:6px;color:var(--color-black);font-weight:700}
.sg-enforcement p{margin:0 0 var(--space-2);color:var(--color-slate-700);font-size:var(--fs-sm);line-height:1.55;font-weight:600}
.sg-enforcement ul{margin:8px 0 0 18px;color:var(--color-slate-700);font-size:var(--fs-sm);line-height:1.55}
.sg-enforcement.warning{background:var(--color-warning-50);border-color:var(--color-warning-300)}
.sg-enforcement.notice{background:var(--sg-theme-soft,var(--color-brand-50));border-color:var(--sg-theme-soft,var(--color-brand-50))}
.sg-enforcement.soft_lock_ready{background:var(--color-warning-50);border-color:var(--color-warning-200)}
.sg-module-list{display:flex;flex-wrap:wrap;gap:var(--space-2);margin-top:12px}
.sg-module-list span{
    background:var(--color-brand-50);
    color:var(--color-brand-700);
    border:1px solid var(--color-brand-100);
    border-radius:var(--radius-pill);
    padding:8px 11px;
    font-size:12px;
    font-weight:700;
    text-transform:capitalize;
}

/* responsividade */
@media(max-width:1180px){
    .sg-core-hero{grid-template-columns:1fr;padding:26px 24px}
    .sg-core-grid,.sg-core-grid.lower{grid-template-columns:1fr}
}
@media(max-width:760px){
    .sg-core-hero h1{font-size:24px}
    .sg-core-form{grid-template-columns:1fr}
    .sg-core-dl,.sg-core-dl.compact{grid-template-columns:1fr}
    .sg-core-card{padding:18px 16px}
    .sg-core-btn{width:100%}
    .sg-table-row,.sg-log-row{align-items:flex-start;flex-direction:column}
    .sg-log-row em{text-align:left}
}
</style>
