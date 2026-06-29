<?php
/**
 * SIGE SoftGenial - Módulo de Auditoria
 * Vista: ?page=sige-app&view=auditoria_notas
 * Acesso: Director, Dir. Pedagógico, Chefe de Secretaria, TI
 */
if (!defined('ABSPATH')) exit;

// Guard de acesso - [T7] Dir. Pedagógico adicionado (supervisão da qualidade académica)
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['academico.auditoria_notas_ver'],
    ['sige_director','sige_pedagogico','sige_secretaria_geral']
)) return;

global $wpdb;
$tA    = $wpdb->prefix . 'sige_logs_auditoria';
$hoje  = current_time('Y-m-d');
// [MT-03] Multi-tenancy
$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
$mes_inicio = wp_date('Y-m-01');

// ── Filtros ───────────────────────────────────────────────────────────────────
$f_modulo  = isset($_GET['f_modulo'])  ? sanitize_text_field($_GET['f_modulo'])  : '';
$f_user    = isset($_GET['f_user'])    ? (int)$_GET['f_user']                    : 0;
$f_de      = isset($_GET['f_de'])      ? sanitize_text_field($_GET['f_de'])      : $mes_inicio;
$f_ate     = isset($_GET['f_ate'])     ? sanitize_text_field($_GET['f_ate'])     : $hoje;
$f_busca   = isset($_GET['f_busca'])   ? sanitize_text_field($_GET['f_busca'])   : '';
$tab       = isset($_GET['tab'])       ? sanitize_text_field($_GET['tab'])       : 'tabela';
$pagina    = max(1, (int)($_GET['pg'] ?? 1));
$por_pagina = 50;
$offset    = ($pagina - 1) * $por_pagina;

// ── Módulos disponíveis ───────────────────────────────────────────────────────
$modulos_config = [
    'financeiro'  => ['label' => 'Financeiro',  'icon' => '💰', 'cor' => 'var(--sg-theme-primary-800,var(--color-ink-700))'],
    'alunos'      => ['label' => 'Alunos',       'icon' => '👥', 'cor' => 'var(--color-success-800)'],
    'notas'       => ['label' => 'Notas',        'icon' => '📝', 'cor' => 'var(--color-danger-500)'],
    'turmas'      => ['label' => 'Turmas',       'icon' => '🏫', 'cor' => 'var(--color-info-600)'],
    'professores' => ['label' => 'Professores',  'icon' => '👨‍🏫', 'cor' => 'var(--color-success-800)'],
    'jardim'      => ['label' => 'Jardim',       'icon' => '🧸', 'cor' => 'var(--color-brand-700)'],
    'acesso'      => ['label' => 'Acesso',       'icon' => '🔐', 'cor' => 'var(--color-brand-700)'],
    'sistema'     => ['label' => 'Sistema',      'icon' => '⚙️', 'cor' => 'var(--color-slate-800)'],
];

$acoes_labels = [
    // Financeiro
    'pagamento_registado'         => 'Pagamento registado',
    'geracao_lote_pacote_mensal'  => 'Mensalidades geradas em lote',
    'upsert_lancamento_erro'      => 'Erro ao criar lançamento',
    'whatsapp_enfileirado'        => 'WhatsApp enfileirado',
    'caixa_fechado'               => 'Caixa fechada',
    'caixa_reaberto'              => 'Caixa reaberta',
    'estorno_pagamento'           => 'Estorno de pagamento',
    'doc_impresso'                => 'Documento impresso',
    'config_financeira_alterada'  => 'Config. financeira alterada',
    // Alunos
    'aluno_criado'                => 'Aluno criado',
    'aluno_editado'               => 'Aluno editado',
    'aluno_removido'              => 'Aluno removido',
    'aluno_matriculado'           => 'Aluno matriculado',
    // Notas
    'nota_lancada'                => 'Notas lançadas',
    'nota_editada'                => 'Nota editada',
    'nota_aprovada'               => 'Pauta aprovada',
    // Turmas
    'turma_criada'                => 'Turma criada',
    'turma_editada'               => 'Turma editada',
    'turma_removida'              => 'Turma removida',
    'docente_alocado'             => 'Docente alocado',
    'horario_guardado'            => 'Horário guardado',
    // Professores / Staff
    'staff_criado'                => 'Utilizador staff criado',
    'staff_editado'               => 'Utilizador staff editado',
    'staff_removido'              => 'Utilizador staff removido',
    'staff_status_alterado'       => 'Estado de staff alterado',
    'senha_resetada'              => 'Senha resetada',
    // Jardim
    'jardim_diario_registado'     => 'Diário de actividades registado',
    'jardim_saude_registado'      => 'Saúde/nutrição registada',
    'jardim_avaliacao_guardada'   => 'Avaliação jardim guardada',
    // Acesso
    'login_sucesso'               => 'Login com sucesso',
    'login_falhado'               => 'Tentativa de login falhada',
    'logout'                      => 'Sessão terminada',
    'portaria_acesso'             => 'Acesso registado na portaria',
    // Sistema
    'config_alterada'             => 'Configuração alterada',
    'config_avancada_alterada'    => 'Config. avançada alterada',
    'whatsapp_config_alterada'    => 'Configuração WhatsApp alterada',
    'preco_alterado'              => 'Preço alterado',
    'matriz_vinculada'            => 'Disciplina vinculada à matriz',
    'matriz_removida'             => 'Disciplina removida da matriz',
    'matriz_clonada'              => 'Matriz curricular clonada',
    'ano_encerrado'               => 'Ano lectivo encerrado',
    'ano_aberto'                  => 'Ano lectivo aberto',
    'dados_limpos'                => 'Dados limpos (produção)',
];

// ── Query base ────────────────────────────────────────────────────────────────
$where  = ["escola_id = %d", "data_hora BETWEEN %s AND %s"];
$params = [$eid, $f_de . ' 00:00:00', $f_ate . ' 23:59:59'];

if ($f_modulo) { $where[] = 'modulo = %s'; $params[] = $f_modulo; }
if ($f_user)   { $where[] = 'user_id = %d'; $params[] = $f_user; }
if ($f_busca)  { $where[] = '(acao LIKE %s OR detalhes LIKE %s OR user_display LIKE %s)';
                 $like = '%' . $wpdb->esc_like($f_busca) . '%';
                 $params[] = $like; $params[] = $like; $params[] = $like; }

$where_sql = implode(' AND ', $where);

$total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tA WHERE $where_sql", ...$params));

$logs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $tA WHERE $where_sql ORDER BY data_hora DESC LIMIT %d OFFSET %d",
    ...array_merge($params, [$por_pagina, $offset])
));

// ── Utilizadores distintos para filtro ───────────────────────────────────────
$utilizadores = $wpdb->get_results($wpdb->prepare(
    "SELECT DISTINCT user_id, user_display FROM $tA WHERE escola_id = %d AND user_id > 0 ORDER BY user_display", $eid
));

// ── Estatísticas resumo ───────────────────────────────────────────────────────
$stats = $wpdb->get_results($wpdb->prepare("
    SELECT modulo, COUNT(*) as total
    FROM $tA
    WHERE escola_id = %d AND data_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY modulo
", $eid));
$stats_map = array_column($stats, 'total', 'modulo');

$logins_hoje = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $tA WHERE escola_id=%d AND acao='login_sucesso' AND DATE(data_hora)=%s", $eid, $hoje
));
$falhas_hoje = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $tA WHERE escola_id=%d AND acao='login_falhado' AND DATE(data_hora)=%s", $eid, $hoje
));
$total_hoje  = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $tA WHERE escola_id=%d AND DATE(data_hora)=%s", $eid, $hoje
));

// ── URL helper ────────────────────────────────────────────────────────────────
function sige_audit_url(array $extra = []): string {
    $base = ['page' => 'sige-app', 'view' => 'auditoria_notas'];
    foreach (['f_modulo','f_user','f_de','f_ate','f_busca','tab'] as $k) {
        if (isset($_GET[$k]) && $_GET[$k] !== '') $base[$k] = $_GET[$k];
    }
    return admin_url('admin.php?' . http_build_query(array_merge($base, $extra)));
}

// Resolve o nome para mostrar - usa user_display se preenchido,
// caso contrário busca ao WordPress (para registos antigos antes da migration)
function sige_audit_nome(string $display, int $user_id): string {
    if (!empty(trim($display))) return $display;
    if ($user_id > 0) {
        $u = get_userdata($user_id);
        return $u ? ($u->display_name ?: $u->user_login) : 'Utilizador #' . $user_id;
    }
    return 'Sistema';
}


$audit_icon = static function (string $name): string {
    if (function_exists('sige_ui_icon')) {
        return sige_ui_icon($name);
    }
    $map = [
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.8"/><rect x="14" y="3" width="7" height="7" rx="1.8"/><rect x="3" y="14" width="7" height="7" rx="1.8"/><rect x="14" y="14" width="7" height="7" rx="1.8"/>',
        'table' => '<path d="M3 3h18v18H3z"/><path d="M3 9h18"/><path d="M3 15h18"/><path d="M9 3v18"/><path d="M15 3v18"/>',
        'timeline' => '<path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-6"/>',
        'filter' => '<path d="M22 3H2l8 9.46V19l4 2v-8.54z"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'money' => '<path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14.5a3.5 3.5 0 0 1 0 7H6"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/>',
        'settings' => '<path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-.4-1.1 1.65 1.65 0 0 0-1-.6 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1 1.65 1.65 0 0 0-1.1-.4H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.1-.4 1.65 1.65 0 0 0 .6-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-.6 1.65 1.65 0 0 0 .4-1.1V3a2 2 0 1 1 4 0v.09c0 .41.15.8.4 1.1.25.3.6.52 1 .6.6.1 1.2-.02 1.7-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.1.4.31.75.6 1 .3.25.69.4 1.1.4H21a2 2 0 1 1 0 4h-.09c-.41 0-.8.15-1.1.4-.29.25-.5.6-.6 1Z"/>',
    ];
    $path = $map[$name] ?? $map['shield'];
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};

$audit_module_icon = static function (string $modulo) use ($audit_icon): string {
    $map = [
        'financeiro' => 'money',
        'alunos' => 'users',
        'notas' => 'book',
        'turmas' => 'grid',
        'professores' => 'user',
        'jardim' => 'book',
        'acesso' => 'shield',
        'sistema' => 'settings',
    ];
    return $audit_icon($map[$modulo] ?? 'grid');
};

?>
<style id="sige-auditoria-notas-produto-pro-v121083">
/* SIGE SoftGenial v12.10.83 - Auditoria Académica: Compliance Visual Integral
   Escopo visual apenas: não altera logs, filtros, exportação, queries,
   permissões, notas, aprovação, pautas, DEC, ACTA ou regras académicas. */
.sige-audit-page{
    --aud-blue:var(--sg-theme-primary,var(--color-brand-500));
    --aud-blue-dark:var(--sg-theme-primary-800,var(--color-ink-700));
    --aud-purple:var(--color-brand-500);
    --aud-purple-soft:var(--color-brand-50);
    --aud-ink:var(--color-black);
    --aud-muted:var(--color-slate-700);
    --aud-line:var(--color-ink-100);
    --aud-green:var(--color-success-500);
    --aud-red:var(--color-danger-500);
    --aud-amber:var(--color-warning-500);
    width:100%;
    max-width:none;
    margin:0;
    padding:0 0 28px;
    display:flex;
    flex-direction:column;
    gap:18px;
    color:var(--aud-ink);
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif);
}
.sige-audit-page *{box-sizing:border-box}
.sige-audit-page svg{width:18px;height:18px;display:block;stroke:currentColor!important;color:currentColor!important;fill:none!important}

/* HERO - padrão Painel Principal */
.audit-hero{
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
}
.audit-hero:before{content:"";position:absolute;inset:auto -80px -130px auto;width:420px;height:300px;border-radius:var(--radius-pill);background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);pointer-events:none}
.audit-hero-main,.audit-hero-panel{position:relative;z-index:1}
.audit-kicker{
    display:inline-flex;
    align-items:center;
    gap:var(--space-2);
    margin:0 0 10px;
    padding:0;
    border:0;
    background:transparent;
    color:var(--aud-blue);
    font-size:12px;
    line-height:1.2;
    font-weight:700;
    letter-spacing:.11em;
    text-transform:uppercase;
}
.audit-hero h1{
    margin:0;
    max-width:650px;
    color:var(--color-black);
    font-size:31px;
    line-height:1.08;
    font-weight:700;
    letter-spacing:-.04em;
    font-family:inherit;
}
.audit-hero p{
    max-width:650px;
    margin:var(--space-3) 0 0;
    color:var(--color-slate-700);
    font-size:15px;
    line-height:1.65;
    font-weight:500;
}
.audit-hero-actions{display:flex;flex-wrap:wrap;gap:var(--space-3);margin-top:24px}
.audit-btn{
    min-height:44px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:9px;
    padding:0 18px;
    border-radius:var(--radius-md);
    font-size:var(--fs-sm);
    font-weight:700;
    text-decoration:none;
    border:1px solid transparent;
    cursor:pointer;
    font-family:inherit;
    transition:transform .18s ease,box-shadow .18s ease,background .18s ease,border-color .18s ease;
}
.audit-btn-primary{
    background:linear-gradient(135deg,var(--aud-blue),var(--aud-blue-dark));
    color:var(--color-white);
    box-shadow:var(--shadow-sm);
}
.audit-btn-primary:hover{transform:translateY(-1px);box-shadow:var(--shadow-md);color:var(--color-white)}
.audit-btn-light{
    background:var(--color-white);
    color:var(--color-ink-900);
    border-color:var(--color-ink-100);
    box-shadow:var(--shadow-sm);
}
.audit-btn-light:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(15,23,42,.08)}
.audit-hero-panel{
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
.audit-hero-panel:before{content:"";position:absolute;right:22px;bottom:16px;width:112px;height:92px;border-radius:22px 22px 12px 12px;background:rgba(109,93,252,.16);box-shadow:inset 0 0 0 2px rgba(109,93,252,.12)}
.audit-hero-panel>*{position:relative;z-index:1}
.audit-panel-label{display:flex;align-items:center;gap:var(--space-2);color:var(--aud-purple);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.11em}
.audit-panel-value{display:block;color:var(--color-ink-900);font-size:36px;line-height:1.05;font-weight:700;letter-spacing:-.045em}
.audit-panel-text{display:block;max-width:330px;color:var(--color-slate-600);font-size:var(--fs-sm);line-height:1.55;font-weight:600}

/* KPIs */
.audit-stats-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(190px,1fr));
    gap:var(--space-4);
    margin:0;
}
.audit-card{
    background:var(--color-white);
    border-radius:var(--radius-xl);
    padding:var(--space-5);
    border:1px solid rgba(28,32,54,.08);
    box-shadow:var(--shadow-md);
}
.audit-stat{
    position:relative;
    overflow:hidden;
    display:grid;
    grid-template-columns:auto minmax(0,1fr);
    align-items:center;
    gap:14px;
    min-height:100px;
    padding:18px 20px;
}
.audit-stat:after{
    content:"";
    position:absolute;
    right:-28px;
    top:-34px;
    width:92px;
    height:92px;
    border-radius:50%;
    background:var(--kpi-soft,var(--color-brand-50));
}
.audit-stat-icon{
    width:52px;height:52px;border-radius:var(--radius-lg);
    display:flex;align-items:center;justify-content:center;
    background:var(--kpi-soft,var(--color-brand-50));
    color:var(--kpi-color,var(--color-brand-500));
    position:relative;z-index:1;
}
.audit-stat-icon svg{width:24px;height:24px}
.audit-stat > div{position:relative;z-index:1}
.audit-stat .num{font-size:27px;font-weight:700;line-height:1;color:var(--color-black);letter-spacing:-.03em}
.audit-stat .lbl{font-size:12px;color:var(--color-slate-600);margin-top:7px;font-weight:600}
.audit-stat.today{--kpi-color:var(--sg-theme-primary,var(--color-brand-500));--kpi-soft:var(--color-info-50)}
.audit-stat.logins{--kpi-color:var(--color-success-500);--kpi-soft:var(--color-success-100)}
.audit-stat.failures{--kpi-color:var(--color-danger-500);--kpi-soft:var(--color-danger-50)}
.audit-stat.module{--kpi-color:var(--color-brand-500);--kpi-soft:var(--color-brand-50)}

/* filtros */
.audit-filter-card{padding:18px}
.filter-form{
    display:grid;
    grid-template-columns:repeat(5,minmax(150px,1fr));
    gap:var(--space-3);
    align-items:end;
}
.filter-form label{
    font-size:var(--fs-xs);
    font-weight:700;
    color:var(--color-slate-600);
    display:block;
    margin:0 0 7px;
    text-transform:uppercase;
    letter-spacing:.07em;
}
.filter-form input,.filter-form select{
    width:100%;
    min-width:0;
    min-height:44px;
    padding:0 13px;
    border:1px solid var(--color-ink-100);
    border-radius:var(--radius-md);
    font-size:var(--fs-sm);
    color:var(--color-ink-500);
    font-weight:600;
    background:var(--color-white);
    box-shadow:var(--shadow-sm);
    outline:none;
}
.filter-form input:focus,.filter-form select:focus{
    border-color:rgba(90,63,214,.55);
    box-shadow:var(--shadow-xs);
}
.audit-filter-actions{
    display:grid;
    grid-template-columns:repeat(2,minmax(140px,1fr));
    gap:var(--space-2);
    align-items:stretch;
    min-width:0;
}
.audit-filter-actions .audit-btn{
    width:100%;
    min-width:0;
    padding-left:14px;
    padding-right:14px;
    white-space:nowrap;
}
.filter-form .audit-filter-actions{
    grid-column:span 1;
}
.audit-result-bar{
    display:flex;
    justify-content:space-between;
    gap:var(--space-3);
    flex-wrap:wrap;
    align-items:center;
    color:var(--color-slate-600);
    font-size:var(--fs-sm);
    font-weight:600;
    padding:0 var(--space-1);
}

/* tabs */
.audit-tabs{
    display:flex;
    flex-wrap:wrap;
    gap:var(--space-3);
    border-bottom:0;
    margin:0;
}
.audit-tab{
    min-height:46px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    border-radius:var(--radius-md);
    padding:0 22px;
    font-size:var(--fs-base);
    line-height:1;
    font-weight:700;
    text-decoration:none;
    border:1px solid var(--color-ink-100);
    background:var(--color-white);
    color:var(--color-ink-900);
    box-shadow:var(--shadow-sm);
    transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease,background .18s ease;
}
.audit-tab:hover{transform:translateY(-1px);border-color:var(--color-info-100);box-shadow:var(--shadow-md);color:var(--sg-theme-primary,var(--color-brand-500))}
.audit-tab.active{
    background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-800,var(--color-ink-700)));
    border-color:var(--sg-theme-primary,var(--color-brand-500));
    color:var(--color-white);
    box-shadow:var(--shadow-md);
}

/* tabela */
.audit-table-card{padding:0;overflow:hidden}
.audit-table-wrap{width:100%;overflow:auto}
.audit-table{
    width:100%;
    min-width:1080px;
    border-collapse:separate;
    border-spacing:0;
    font-size:var(--fs-sm);
    background:var(--color-white);
}
.audit-table th{
    background:var(--color-slate-50);
    padding:12px 13px;
    text-align:left;
    border-bottom:1px solid var(--color-slate-100);
    font-size:11.5px;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:var(--color-slate-800);
    font-weight:700;
}
.audit-table td{
    padding:11px 13px;
    border-bottom:1px solid var(--color-info-50);
    vertical-align:top;
    color:var(--color-ink-500);
}
.audit-table tr:hover td{background:var(--color-white)}
.audit-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:34px;
    height:34px;
    border-radius:var(--radius-md);
    color:var(--color-white);
}
.audit-badge svg{width:18px;height:18px}
.audit-detalhes{
    font-size:var(--fs-xs);
    color:var(--color-slate-600);
    font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;
    max-width:360px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    cursor:pointer;
    display:inline-block;
    background:var(--color-slate-50);
    border:1px solid var(--color-slate-100);
    padding:7px 9px;
    border-radius:var(--radius-sm);
}
.audit-empty{
    text-align:center;
    padding:44px 20px;
    color:var(--color-slate-500);
}
.audit-empty strong{display:block;color:var(--color-black);font-size:18px;margin-bottom:6px}

/* timeline */
.timeline-day{margin-bottom:24px}
.timeline-day-label{
    display:inline-flex;
    align-items:center;
    gap:var(--space-2);
    font-size:12px;
    font-weight:700;
    color:var(--color-slate-700);
    text-transform:uppercase;
    letter-spacing:.08em;
    margin-bottom:12px;
    padding:9px 12px;
    border-radius:var(--radius-pill);
    background:var(--color-white);
    border:1px solid var(--color-slate-100);
    box-shadow:var(--shadow-sm);
}
.timeline-item{display:flex;gap:var(--space-3);margin-bottom:10px;align-items:flex-start}
.timeline-icon{
    width:42px;height:42px;border-radius:var(--radius-md);
    display:flex;align-items:center;justify-content:center;
    flex-shrink:0;
}
.timeline-body{
    background:var(--color-white);
    border-radius:var(--radius-lg);
    padding:13px 15px;
    flex:1;
    border:1px solid rgba(28,32,54,.08);
    box-shadow:var(--shadow-sm);
}
.timeline-body .tl-acao{font-weight:700;font-size:var(--fs-sm);color:var(--color-ink-500)}
.timeline-body .tl-meta{font-size:12px;color:var(--color-slate-600);margin-top:4px;font-weight:600}
.timeline-body .tl-det{
    font-size:var(--fs-xs);
    color:var(--color-slate-600);
    font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;
    margin-top:8px;
    cursor:pointer;
    background:var(--color-slate-50);
    border:1px solid var(--color-slate-100);
    padding:7px 9px;
    border-radius:var(--radius-sm);
    display:inline-block;
}

/* paginação */
.audit-pagination-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    flex-wrap:wrap;
    margin-top:20px;
}
.pagination{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.pagination a,.pagination span{
    min-height:36px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:0 13px;
    border-radius:var(--radius-md);
    font-size:var(--fs-sm);
    border:1px solid var(--color-ink-100);
    text-decoration:none;
    color:var(--color-slate-900);
    background:var(--color-white);
    font-weight:700;
}
.pagination a:hover{background:var(--color-brand-50);color:var(--color-brand-500)}
.pagination .current{background:var(--sg-theme-primary,var(--color-brand-500));color:var(--color-white);border-color:var(--sg-theme-primary,var(--color-brand-500))}

@media(max-width:1480px){
    .filter-form{grid-template-columns:repeat(4,minmax(160px,1fr))}
    .filter-form .audit-filter-actions{grid-column:1 / -1; justify-self:stretch}
}
@media(max-width:1180px){
    .filter-form{grid-template-columns:repeat(3,minmax(170px,1fr))}
}
@media(max-width:980px){
    .audit-hero{grid-template-columns:1fr;padding:26px 24px}
    .filter-form{grid-template-columns:1fr 1fr}
    .filter-form .audit-filter-actions{grid-column:1 / -1}
}
@media(max-width:680px){
    .audit-hero h1{font-size:24px}
    .filter-form{grid-template-columns:1fr}
    .audit-filter-actions{grid-template-columns:1fr}
    .audit-filter-actions,.audit-btn,.audit-tab{width:100%}
    .audit-tabs{display:grid;grid-template-columns:1fr}
}
</style>

<div class="sige-audit-page">

    <section class="audit-hero" aria-label="Registo de Auditoria">
        <div class="audit-hero-main">
            <div class="audit-kicker"><?php echo $audit_icon('shield'); ?><span>Auditoria</span></div>
            <h1>Registo de Auditoria</h1>
            <p>Acompanhe as acções realizadas no sistema, filtre eventos sensíveis e mantenha rastreabilidade operacional e académica.</p>
            <div class="audit-hero-actions">
                <a class="audit-btn audit-btn-primary" href="<?php echo esc_url(sige_audit_url(['export' => '1'])); ?>">
                    <?php echo $audit_icon('download'); ?> Exportar CSV
                </a>
                <a class="audit-btn audit-btn-light" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=auditoria_notas&tab=' . $tab)); ?>">
                    <?php echo $audit_icon('x'); ?> Limpar filtros
                </a>
            </div>
        </div>
        <aside class="audit-hero-panel" aria-label="Estado da auditoria">
            <div class="audit-panel-label"><?php echo $audit_icon('clock'); ?><span>Hoje</span></div>
            <strong class="audit-panel-value"><?php echo esc_html((string)$total_hoje); ?></strong>
            <span class="audit-panel-text">Acção(ões) registadas hoje. O histórico mantém rastreabilidade por utilizador, módulo, data e IP.</span>
        </aside>
    </section>

    <!-- Estatísticas de resumo -->
    <div class="audit-stats-grid">
        <div class="audit-card audit-stat today">
            <span class="audit-stat-icon"><?php echo $audit_icon('clock'); ?></span>
            <div>
                <div class="num"><?php echo esc_html($total_hoje); ?></div>
                <div class="lbl">Acções hoje</div>
            </div>
        </div>
        <div class="audit-card audit-stat logins">
            <span class="audit-stat-icon"><?php echo $audit_icon('users'); ?></span>
            <div>
                <div class="num"><?php echo esc_html($logins_hoje); ?></div>
                <div class="lbl">Logins hoje</div>
            </div>
        </div>
        <div class="audit-card audit-stat failures">
            <span class="audit-stat-icon"><?php echo $audit_icon('alert'); ?></span>
            <div>
                <div class="num"><?php echo esc_html($falhas_hoje); ?></div>
                <div class="lbl">Tentativas falhadas</div>
            </div>
        </div>
        <?php foreach ($modulos_config as $slug => $mc): ?>
        <div class="audit-card audit-stat module">
            <span class="audit-stat-icon"><?php echo $audit_module_icon($slug); ?></span>
            <div>
                <div class="num"><?php echo esc_html($stats_map[$slug] ?? 0); ?></div>
                <div class="lbl"><?php echo esc_html($mc['label']); ?> · 30 dias</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filtros -->
    <div class="audit-card audit-filter-card">
        <form method="GET" action="" class="filter-form">
            <input type="hidden" name="page" value="sige-app">
            <input type="hidden" name="view" value="auditoria_notas">
            <input type="hidden" name="tab"  value="<?php echo esc_attr($tab); ?>">

            <div>
                <label>Módulo</label>
                <select name="f_modulo">
                    <option value="">Todos</option>
                    <?php foreach ($modulos_config as $slug => $mc): ?>
                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($f_modulo, $slug); ?>>
                        <?php echo esc_html($mc['label']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Utilizador</label>
                <select name="f_user">
                    <option value="">Todos</option>
                    <?php foreach ($utilizadores as $u): ?>
                    <option value="<?php echo (int)$u->user_id; ?>" <?php selected($f_user, (int)$u->user_id); ?>>
                        <?php echo esc_html($u->user_display ?: sige_audit_nome('', (int)$u->user_id)); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>De</label>
                <input type="date" name="f_de" value="<?php echo esc_attr($f_de); ?>">
            </div>
            <div>
                <label>Até</label>
                <input type="date" name="f_ate" value="<?php echo esc_attr($f_ate); ?>">
            </div>
            <div>
                <label>Pesquisar</label>
                <input type="text" name="f_busca" value="<?php echo esc_attr($f_busca); ?>" placeholder="acção, utilizador, detalhe…">
            </div>
            <div>
                <label>&nbsp;</label>
                <div class="audit-filter-actions">
                    <button type="submit" class="audit-btn audit-btn-primary"><?php echo $audit_icon('filter'); ?> Filtrar</button>
                    <a class="audit-btn audit-btn-light" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=auditoria_notas&tab=' . $tab)); ?>"><?php echo $audit_icon('x'); ?> Limpar</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Resultado -->
    <div class="audit-result-bar">
        <span><?php echo esc_html(number_format($total)); ?> registo(s) encontrado(s)</span>
        <?php if ($f_modulo || $f_user || $f_busca): ?><span>Filtros activos</span><?php endif; ?>
    </div>

    <!-- Tabs -->
    <div class="audit-tabs">
        <a href="<?php echo esc_url(sige_audit_url(['tab' => 'tabela', 'pg' => 1])); ?>"
           class="audit-tab <?php echo $tab === 'tabela' ? 'active' : ''; ?>"><?php echo $audit_icon('table'); ?> Tabela</a>
        <a href="<?php echo esc_url(sige_audit_url(['tab' => 'timeline', 'pg' => 1])); ?>"
           class="audit-tab <?php echo $tab === 'timeline' ? 'active' : ''; ?>"><?php echo $audit_icon('timeline'); ?> Timeline</a>
    </div>

    <?php if ($tab === 'tabela'): ?>
    <!-- ═══════════════════════════════════ TAB TABELA ══════════════════════════════════ -->
    <div class="audit-card audit-table-card">
        <div class="audit-table-wrap">
        <table class="audit-table">
            <thead>
                <tr>
                    <th style="width:140px;">Data / Hora</th>
                    <th style="width:60px;">Módulo</th>
                    <th style="width:150px;">Utilizador</th>
                    <th style="width:180px;">Acção</th>
                    <th>Detalhes</th>
                    <th style="width:110px;">IP</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="6" class="audit-empty"><strong>Nenhum registo encontrado</strong> Ajuste os filtros para consultar outros eventos.</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log):
                    $mc    = $modulos_config[$log->modulo] ?? ['cor' => 'var(--color-slate-500)', 'label' => $log->modulo];
                    $label = $acoes_labels[$log->acao] ?? $log->acao;
                    $det   = $log->detalhes ?? '';
                    // Formatar JSON para leitura
                    $det_decoded = json_decode($det, true);
                    $det_fmt = '';
                    if ($det_decoded) {
                        $parts = [];
                        foreach ($det_decoded as $k => $v) {
                            if (is_array($v)) $parts[] = $k . ': {…}';
                            else $parts[] = $k . ': ' . $v;
                        }
                        $det_fmt = implode(' · ', $parts);
                    } else {
                        $det_fmt = $det;
                    }
                    $is_erro = str_contains($log->acao, 'falhado') || str_contains($log->acao, 'erro');
                ?>
                <tr style="<?php echo $is_erro ? 'background:var(--color-warning-50);' : ''; ?>">
                    <td style="font-size:12px; color:var(--color-slate-700); white-space:nowrap;">
                        <?php echo esc_html(date('d/m/Y', strtotime($log->data_hora))); ?><br>
                        <span style="color:var(--color-slate-400);"><?php echo esc_html(date('H:i:s', strtotime($log->data_hora))); ?></span>
                    </td>
                    <td class="sige-u-tac">
                        <span class="audit-badge" style="background:<?php echo esc_attr($mc['cor']); ?>;" title="<?php echo esc_attr($mc['label']); ?>">
                            <?php echo $audit_module_icon((string)$log->modulo); ?>
                        </span>
                    </td>
                    <td style="font-size:13px;">
                        <?php echo esc_html(sige_audit_nome($log->user_display ?? '', (int)$log->user_id)); ?>
                    </td>
                    <td style="<?php echo $is_erro ? 'color:var(--color-danger-600); font-weight:600;' : 'font-weight:500;'; ?>">
                        <?php echo esc_html($label); ?>
                    </td>
                    <td>
                        <span class="audit-detalhes" title="<?php echo esc_attr($det); ?>"
                              data-sige-act="sigeAlternarQuebraTexto">
                            <?php echo esc_html($det_fmt ?: '-'); ?>
                        </span>
                    </td>
                    <td style="font-size:11px; color:var(--color-slate-400); font-family:monospace;">
                        <?php echo esc_html($log->ip_address ?: '-'); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?php else: ?>
    <!-- ═══════════════════════════════════ TAB TIMELINE ══════════════════════════════════ -->
    <?php
    // Agrupar por dia
    $por_dia = [];
    foreach ($logs as $log) {
        $dia = date('Y-m-d', strtotime($log->data_hora));
        $por_dia[$dia][] = $log;
    }
    ?>
    <?php if (empty($por_dia)): ?>
        <div class="audit-card audit-empty">
            <strong>Nenhum registo encontrado</strong>
            Ajuste os filtros para consultar outros eventos.
        </div>
    <?php else: ?>
    <?php foreach ($por_dia as $dia => $itens): ?>
    <div class="timeline-day">
        <div class="timeline-day-label">
            <?php
            $ts = strtotime($dia);
            $labels_dia = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
            echo esc_html($labels_dia[wp_date('w', $ts)] . ', ' . wp_date('d/m/Y', $ts));
            echo ' <span style="font-weight:400; color:var(--color-slate-300);">('. count($itens) .' acções)</span>';
            ?>
        </div>
        <?php foreach ($itens as $log):
            $mc    = $modulos_config[$log->modulo] ?? ['cor' => 'var(--color-slate-500)'];
            $label = $acoes_labels[$log->acao] ?? $log->acao;
            $det   = $log->detalhes ?? '';
            $det_decoded = json_decode($det, true);
            $det_resumo = '';
            if ($det_decoded) {
                $partes = [];
                foreach (['nome','aluno_id','valor','recibo','username','alteracoes'] as $chave) {
                    if (isset($det_decoded[$chave])) {
                        if ($chave === 'alteracoes' && is_array($det_decoded[$chave])) {
                            $partes[] = count($det_decoded[$chave]) . ' campo(s) alterado(s)';
                        } else {
                            $partes[] = $det_decoded[$chave];
                        }
                    }
                }
                $det_resumo = implode(' · ', $partes);
            }
            $is_erro = str_contains($log->acao, 'falhado') || str_contains($log->acao, 'erro');
        ?>
        <div class="timeline-item">
            <div class="timeline-icon" style="background:<?php echo esc_attr($mc['cor']); ?>22;">
                <?php echo $audit_module_icon((string)$log->modulo); ?>
            </div>
            <div class="timeline-body" style="<?php echo $is_erro ? 'border-left:3px solid var(--color-danger-600);' : ''; ?>">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <span class="tl-acao" style="<?php echo $is_erro ? 'color:var(--color-danger-600);' : ''; ?>">
                        <?php echo esc_html($label); ?>
                    </span>
                    <span style="font-size:11px; color:var(--color-slate-300); white-space:nowrap; margin-left:12px;">
                        <?php echo esc_html(date('H:i', strtotime($log->data_hora))); ?>
                    </span>
                </div>
                <div class="tl-meta">
                    <?php echo esc_html(sige_audit_nome($log->user_display ?? '', (int)$log->user_id)); ?>
                    <?php if ($log->ip_address): ?>
                        <span style="color:var(--color-ink-200);"> · </span>
                        <span style="font-family:monospace;"><?php echo esc_html($log->ip_address); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($det_resumo): ?>
                <div class="tl-det" data-sige-act="sigeAlternarQuebraTexto"
                     title="<?php echo esc_attr($det); ?>">
                    <?php echo esc_html($det_resumo); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Paginação -->
    <?php if ($total > $por_pagina): ?>
    <div class="audit-pagination-row">
        <div style="color:var(--color-slate-600); font-size:13px; font-weight:700;">
            Página <?php echo esc_html($pagina); ?> de <?php echo esc_html(ceil($total / $por_pagina)); ?>
        </div>
        <div class="pagination">
            <?php if ($pagina > 1): ?>
                <a href="<?php echo esc_url(sige_audit_url(['pg' => $pagina - 1])); ?>">Anterior</a>
            <?php endif; ?>
            <?php
            $total_pags = (int)ceil($total / $por_pagina);
            $inicio_pag = max(1, $pagina - 2);
            $fim_pag    = min($total_pags, $pagina + 2);
            for ($p = $inicio_pag; $p <= $fim_pag; $p++):
            ?>
                <?php if ($p === $pagina): ?>
                    <span class="current"><?php echo esc_html($p); ?></span>
                <?php else: ?>
                    <a href="<?php echo esc_url(sige_audit_url(['pg' => $p])); ?>"><?php echo esc_html($p); ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($pagina < $total_pags): ?>
                <a href="<?php echo esc_url(sige_audit_url(['pg' => $pagina + 1])); ?>">Seguinte</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php
// ── Exportação CSV ────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === '1') {
    $all_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $tA WHERE $where_sql ORDER BY data_hora DESC LIMIT 5000",
        ...$params
    ));

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="auditoria-' . wp_date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    fputcsv($out, ['ID','Data/Hora','Módulo','Utilizador','Acção','Detalhes','IP'], ';');
    foreach ($all_logs as $l) {
        fputcsv($out, [
            $l->id,
            $l->data_hora,
            $l->modulo,
            $l->user_display ? $l->user_display : sige_audit_nome('', (int)$l->user_id),
            $l->acao,
            $l->detalhes,
            $l->ip_address,
        ], ';');
    }
    fclose($out);
    exit;
}
?>