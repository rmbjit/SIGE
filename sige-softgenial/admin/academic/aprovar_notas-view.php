<?php
/**
 * SIGE SoftGenial - Aprovar Notas
 * Vista: ?page=sige-app&view=aprovar_notas
 * Acesso: Director Pedagógico, Director, Admin
 * [FIX A-01] Workflow de aprovação de notas
 */
if (!defined('ABSPATH')) exit;
// [12.9.6] Guarda centralizada - matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['academico.aprovar_notas'],
    ['sige_director','sige_pedagogico','sige_secretario']
)) return;

global $wpdb;
$p = $wpdb->prefix;
$tNotas  = $p . 'sige_notas';
$tAlunos = $p . 'sige_alunos';
$tTurmas = $p . 'sige_turmas';
$tDisc   = $p . 'sige_disciplinas';
$tProf   = $p . 'sige_professores';

// Verificar se colunas de aprovação existem
$_has_status = (bool)$wpdb->get_results("SHOW COLUMNS FROM $tNotas LIKE 'status'");
if (!$_has_status) {
    echo '<div class="notice notice-info" style="padding:20px;margin:20px;"><p>ℹ️ O módulo de aprovação de notas ainda não está activo. Aceda ao sistema como Administrador para inicializar as colunas necessárias.</p></div>';
    return;
}

$ano = function_exists('sige_get_ano_lectivo_atual') ? sige_get_ano_lectivo_atual() : (int)wp_date('Y');
// [MT-01] Multi-tenancy: escola_id obrigatório em todas as queries
$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
$ano_encerrado_aprov = function_exists('sige_is_ano_encerrado') ? (bool)sige_is_ano_encerrado((int)$ano, $eid) : false;

// ── PROCESSAR APROVAÇÃO/REJEIÇÃO ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_aprovar_notas_action'])) {
    // [AUTH-08] Capability check inline (defesa em profundidade)
    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director') && !current_user_can('sige_pedagogico') && !current_user_can('sige_secretario')) {
        wp_die('Sem permissao para aprovar/rejeitar notas.');
    }
    check_admin_referer('sige_aprovar_notas_nonce');
    $acao   = sanitize_text_field($_POST['sige_aprovar_notas_action']);
    $ids    = array_map('intval', (array)($_POST['nota_ids'] ?? []));

    if ($ano_encerrado_aprov) {
        echo '<div class="notice notice-error" style="margin:10px 0;"><p>🔒 Ano lectivo encerrado. Não é permitido aprovar ou rejeitar notas depois do encerramento académico.</p></div>';
        if (function_exists('sige_audit_log')) sige_audit_log('aprovar_notas_bloqueado_ano_encerrado', ['ano' => $ano, 'nota_ids' => $ids], 'notas');
    } elseif (!empty($ids)) {
        // [SQLI-01] Placeholders dinâmicos para IN() - nunca interpolar $ids_in
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        if ($acao === 'aprovar') {
            $wpdb->query($wpdb->prepare(
                "UPDATE $tNotas SET status='aprovado', aprovado_por=%d, aprovado_em=%s WHERE id IN ($placeholders) AND status='pendente' AND escola_id=%d",
                array_merge([get_current_user_id(), current_time('mysql')], $ids, [$eid])
            ));
            echo '<div class="notice notice-success" style="margin:10px 0;"><p>✅ ' . count($ids) . ' nota(s) aprovada(s) com sucesso!</p></div>';
            if (function_exists('sige_audit_log')) sige_audit_log('aprovar_notas', ['nota_ids' => $ids, 'qtd' => count($ids)], 'notas');
        } elseif ($acao === 'rejeitar') {
            // [SQLI-02] Rejeição agora usa prepare() com placeholders
            $wpdb->query($wpdb->prepare(
                "UPDATE $tNotas SET status='rejeitado' WHERE id IN ($placeholders) AND status='pendente' AND escola_id=%d",
                array_merge($ids, [$eid])
            ));
            echo '<div class="notice notice-warning" style="margin:10px 0;"><p>⚠️ ' . count($ids) . ' nota(s) rejeitada(s). O professor poderá re-submeter.</p></div>';
            if (function_exists('sige_audit_log')) sige_audit_log('rejeitar_notas', ['nota_ids' => $ids, 'qtd' => count($ids)], 'notas');
        }
    }
}

// ── BUSCAR NOTAS PENDENTES ──
// [SQLI-03] $ano agora usa prepare() com %d
$pendentes = $wpdb->get_results($wpdb->prepare("
    SELECT n.*, 
           a.nome_completo AS aluno_nome,
           t.nome AS turma_nome, t.classe,
           d.nome AS disciplina_nome,
           u.display_name AS professor_nome
    FROM $tNotas n
    LEFT JOIN $tAlunos a ON a.id = n.aluno_id
    LEFT JOIN $tTurmas t ON t.id = n.turma_id
    LEFT JOIN $tDisc d ON d.id = n.disciplina_id
    LEFT JOIN {$wpdb->users} u ON u.ID = n.submetido_por
    WHERE n.status = 'pendente' AND n.ano_lectivo = %d AND n.escola_id = %d
    ORDER BY t.classe ASC, t.nome ASC, d.nome ASC, n.trimestre ASC, a.nome_completo ASC
", $ano, $eid));

// Agrupar por turma/disciplina/trimestre
$grupos = [];
foreach ($pendentes as $n) {
    $key = ($n->turma_id ?? 0) . '_' . ($n->disciplina_id ?? 0) . '_' . ($n->trimestre ?? 0);
    if (!isset($grupos[$key])) {
        $grupos[$key] = [
            'turma'      => ($n->classe ?? '') . ' - ' . ($n->turma_nome ?? 'S/Turma'),
            'disciplina' => $n->disciplina_nome ?? 'S/Disciplina',
            'trimestre'  => (int)($n->trimestre ?? 0),
            'professor'  => $n->professor_nome ?? 'Desconhecido',
            'submetido_em' => $n->submetido_em ?? '',
            'notas'      => [],
        ];
    }
    $grupos[$key]['notas'][] = $n;
}

$total_pendentes = count($pendentes);
$total_grupos = count($grupos);

$apn_icon = static function (string $name): string {
    if (function_exists('sige_ui_icon')) {
        return sige_ui_icon($name);
    }
    $map = [
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
        'clipboard' => '<path d="M9 2h6a2 2 0 0 1 2 2v1h1a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h1V4a2 2 0 0 1 2-2z"/><path d="M9 5h6"/><path d="M8 11h8"/><path d="M8 15h8"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/>',
        'teacher' => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
        'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.8"/><rect x="14" y="3" width="7" height="7" rx="1.8"/><rect x="3" y="14" width="7" height="7" rx="1.8"/><rect x="14" y="14" width="7" height="7" rx="1.8"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    ];
    $path = $map[$name] ?? $map['clipboard'];
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};

?>

<style id="sige-aprovar-notas-produto-pro-v121082">
/* SIGE SoftGenial v12.10.82 - Aprovar Notas: Compliance Visual Integral
   Escopo visual apenas: não altera aprovação, rejeição, permissões, queries,
   estados de notas, cálculo académico, pautas ou regras de negócio. */
.aprovar-wrap{
    --apn-blue:var(--sg-theme-primary,var(--color-brand-500));
    --apn-blue-dark:var(--sg-theme-primary-800,var(--color-ink-700));
    --apn-purple:var(--color-brand-500);
    --apn-purple-soft:var(--color-brand-50);
    --apn-ink:var(--color-black);
    --apn-muted:var(--color-slate-700);
    --apn-line:var(--color-ink-100);
    --apn-green:var(--color-success-500);
    --apn-red:var(--color-danger-500);
    --apn-amber:var(--color-warning-500);
    width:100%;
    max-width:none;
    margin:0;
    padding:0 0 28px;
    display:flex;
    flex-direction:column;
    gap:18px;
    color:var(--apn-ink);
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif);
}
.aprovar-wrap *{box-sizing:border-box}
.aprovar-wrap svg{width:18px;height:18px;display:block;stroke:currentColor!important;color:currentColor!important;fill:none!important}

/* HERO - mesmo ADN do Painel Principal */
.apn-hero{
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
.apn-hero:before{content:"";position:absolute;inset:auto -80px -130px auto;width:420px;height:300px;border-radius:var(--radius-pill);background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);pointer-events:none}
.apn-hero-main,.apn-hero-panel{position:relative;z-index:1}
.apn-kicker{
    display:inline-flex;
    align-items:center;
    gap:var(--space-2);
    margin:0 0 10px;
    padding:0;
    border:0;
    background:transparent;
    color:var(--apn-blue);
    font-size:12px;
    line-height:1.2;
    font-weight:700;
    letter-spacing:.11em;
    text-transform:uppercase;
}
.apn-hero h1{
    margin:0;
    max-width:650px;
    color:var(--color-black);
    font-size:31px;
    line-height:1.08;
    font-weight:700;
    letter-spacing:-.04em;
    font-family:inherit;
}
.apn-hero p{
    max-width:650px;
    margin:var(--space-3) 0 0;
    color:var(--color-slate-700);
    font-size:15px;
    line-height:1.65;
    font-weight:500;
}
.apn-hero-chips{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
.apn-chip{
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
.apn-chip svg{color:var(--apn-purple)}
.apn-hero-panel{
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
.apn-hero-panel:before{content:"";position:absolute;right:22px;bottom:16px;width:112px;height:92px;border-radius:22px 22px 12px 12px;background:rgba(109,93,252,.16);box-shadow:inset 0 0 0 2px rgba(109,93,252,.12)}
.apn-hero-panel>*{position:relative;z-index:1}
.apn-panel-label{display:flex;align-items:center;gap:var(--space-2);color:var(--apn-purple);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.11em}
.apn-panel-value{display:block;color:var(--color-ink-900);font-size:36px;line-height:1.05;font-weight:700;letter-spacing:-.045em}
.apn-panel-text{display:block;max-width:330px;color:var(--color-slate-600);font-size:var(--fs-sm);line-height:1.55;font-weight:600}

/* alertas */
.apn-alert{
    display:flex;
    align-items:flex-start;
    gap:var(--space-3);
    padding:14px 16px;
    border-radius:var(--radius-lg);
    border:1px solid var(--color-danger-200);
    background:var(--color-danger-50);
    color:var(--color-danger-700);
    font-weight:600;
    box-shadow:var(--shadow-sm);
}
.apn-alert p{margin:0;color:inherit;font-size:var(--fs-sm);line-height:1.55}
.apn-alert svg{color:var(--color-danger-500);flex:0 0 auto;margin-top:1px}

/* KPIs */
.aprovar-stats{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:var(--space-4);
    margin:0;
}
.aprovar-stat{
    position:relative;
    overflow:hidden;
    display:grid;
    grid-template-columns:auto minmax(0,1fr);
    align-items:center;
    gap:var(--space-4);
    min-height:104px;
    padding:18px 20px;
    border-radius:var(--radius-xl);
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    box-shadow:var(--shadow-md);
}
.aprovar-stat:after{
    content:"";
    position:absolute;
    right:-28px;
    top:-34px;
    width:92px;
    height:92px;
    border-radius:50%;
    background:var(--kpi-soft,var(--color-brand-50));
}
.apn-kpi-icon{
    width:52px;
    height:52px;
    border-radius:var(--radius-lg);
    display:flex;
    align-items:center;
    justify-content:center;
    background:var(--kpi-soft,var(--color-brand-50));
    color:var(--kpi-color,var(--color-brand-500));
    position:relative;
    z-index:1;
}
.apn-kpi-icon svg{width:24px;height:24px}
.aprovar-stat > div{position:relative;z-index:1}
.aprovar-stat .val{
    font-size:27px;
    line-height:1;
    font-weight:700;
    color:var(--color-black);
    letter-spacing:-.03em;
}
.aprovar-stat .lbl{
    margin-top:7px;
    font-size:var(--fs-sm);
    color:var(--color-slate-600);
    font-weight:600;
}
.aprovar-stat.apn-kpi-pending{--kpi-color:var(--color-warning-500);--kpi-soft:var(--color-warning-50)}
.aprovar-stat.apn-kpi-groups{--kpi-color:var(--color-brand-500);--kpi-soft:var(--color-brand-50)}

/* Grupos */
.grupo-card{
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    border-radius:var(--radius-xl);
    margin:0 0 18px;
    box-shadow:var(--shadow-md);
    overflow:hidden;
}
.grupo-header{
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 62%,var(--color-info-50) 100%);
    color:var(--color-ink-500);
    padding:20px 22px;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    flex-wrap:wrap;
    gap:var(--space-3);
    border-bottom:1px solid var(--color-slate-100);
}
.grupo-title-row{display:flex;align-items:flex-start;gap:12px}
.grupo-icon{
    width:42px;
    height:42px;
    border-radius:var(--radius-md);
    display:flex;
    align-items:center;
    justify-content:center;
    background:var(--color-brand-50);
    color:var(--apn-purple);
    flex:0 0 auto;
}
.grupo-header h3{
    margin:0;
    font-size:17px;
    line-height:1.25;
    color:var(--color-ink-500);
    font-weight:700;
    letter-spacing:-.03em;
}
.grupo-meta{
    display:flex;
    flex-wrap:wrap;
    gap:var(--space-2);
    margin-top:10px;
    font-size:12px;
    color:var(--color-slate-600);
    opacity:1;
}
.grupo-meta span{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:7px 10px;
    border-radius:var(--radius-pill);
    background:var(--color-slate-50);
    border:1px solid var(--color-slate-100);
    color:var(--color-slate-700);
    font-size:12px;
    font-weight:700;
}
.grupo-meta svg{width:16px;height:16px;color:var(--apn-blue)}

.notas-table-wrap{width:100%;overflow:auto;background:var(--color-white)}
.notas-table{
    width:100%;
    min-width:760px;
    border-collapse:separate;
    border-spacing:0;
}
.notas-table th{
    background:var(--color-slate-50);
    padding:11px 12px;
    text-align:left;
    font-size:11.5px;
    color:var(--color-slate-800);
    border-bottom:1px solid var(--color-slate-100);
    text-transform:uppercase;
    letter-spacing:.04em;
    font-weight:700;
}
.notas-table td{
    padding:11px 12px;
    border-bottom:1px solid var(--color-info-50);
    font-size:var(--fs-sm);
    color:var(--color-ink-500);
}
.notas-table tr:hover td{background:var(--color-white)}
.notas-table input[type="checkbox"]{
    width:18px;
    height:18px;
    cursor:pointer;
    accent-color:var(--sg-theme-primary,var(--color-brand-500));
}
.nota-val{
    min-width:42px;
    min-height:30px;
    padding:6px 8px;
    border-radius:var(--radius-sm);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-weight:700;
    color:var(--sg-theme-primary,var(--color-brand-500));
    background:var(--sg-theme-soft,var(--color-brand-50));
    border:1px solid var(--sg-theme-soft,var(--color-brand-50));
}
.grupo-actions{
    padding:16px 22px;
    background:var(--color-white);
    display:flex;
    gap:10px;
    justify-content:flex-end;
    align-items:center;
    flex-wrap:wrap;
    border-top:1px solid var(--color-slate-100);
}
.grupo-actions .hint{
    flex:1;
    font-size:12px;
    color:var(--color-slate-500);
    font-weight:600;
}
.btn-aprovar,.btn-rejeitar{
    min-height:42px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:var(--space-2);
    border:none;
    padding:0 18px;
    border-radius:var(--radius-md);
    font-weight:700;
    cursor:pointer;
    font-size:var(--fs-sm);
    font-family:inherit;
    transition:transform .18s ease,box-shadow .18s ease,background .18s ease;
}
.btn-aprovar{
    background:linear-gradient(135deg,var(--color-success-500),var(--color-success-800));
    color:var(--color-white);
    box-shadow:var(--shadow-sm);
}
.btn-aprovar:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(15,23,42,.08)}
.btn-rejeitar{
    background:var(--color-white);
    color:var(--color-danger-700);
    border:1px solid var(--color-danger-200);
    box-shadow:var(--shadow-sm);
}
.btn-rejeitar:hover{transform:translateY(-1px);background:var(--color-danger-50);box-shadow:0 4px 16px rgba(15,23,42,.08)}

.empty-state{
    padding:42px 20px;
    text-align:center;
    color:var(--color-slate-500);
    background:var(--color-white);
    border-radius:var(--radius-xl);
    border:1px solid rgba(28,32,54,.08);
    box-shadow:var(--shadow-md);
}
.empty-state-icon{
    width:58px;height:58px;border-radius:var(--radius-lg);background:var(--color-success-50);color:var(--color-success-500);
    display:flex;align-items:center;justify-content:center;margin:0 auto 14px;
}
.empty-state h2{color:var(--color-black);margin:0 0 var(--space-2);font-size:var(--fs-lg);font-weight:700;letter-spacing:-.03em}
.empty-state p{color:var(--color-slate-500);margin:0;font-size:var(--fs-sm);line-height:1.55}
.check-all{cursor:pointer}

@media(max-width:980px){
    .apn-hero{grid-template-columns:1fr;padding:26px 24px}
    .aprovar-stats{grid-template-columns:1fr}
}
@media(max-width:720px){
    .apn-hero h1{font-size:24px}
    .grupo-actions{align-items:stretch}
    .grupo-actions .hint{flex-basis:100%}
    .btn-aprovar,.btn-rejeitar{width:100%}
}
</style>

<div class="aprovar-wrap">
    <section class="apn-hero" aria-label="Aprovar Notas">
        <div class="apn-hero-main">
            <div class="apn-kicker"><?php echo $apn_icon('shield'); ?><span>Académico</span></div>
            <h1>Aprovar Notas</h1>
            <p>Revise as notas submetidas pelos professores, aprove ou rejeite com segurança e mantenha as pautas alinhadas ao fluxo académico oficial.</p>
            <div class="apn-hero-chips">
                <span class="apn-chip"><?php echo $apn_icon('calendar'); ?> Ano Lectivo <?php echo esc_html((string)$ano); ?></span>
                <span class="apn-chip"><?php echo $apn_icon('clipboard'); ?> Notas pendentes de validação</span>
                <span class="apn-chip"><?php echo $apn_icon('shield'); ?> Aprovação pedagógica controlada</span>
            </div>
        </div>
        <aside class="apn-hero-panel" aria-label="Estado das notas">
            <div class="apn-panel-label"><?php echo $apn_icon('check'); ?><span>Estado</span></div>
            <strong class="apn-panel-value"><?php echo $total_pendentes > 0 ? esc_html((string)$total_pendentes) : 'Pronto'; ?></strong>
            <span class="apn-panel-text"><?php echo $total_pendentes > 0 ? 'Nota(s) aguardam validação antes de aparecerem oficialmente nas pautas.' : 'Não existem notas pendentes de aprovação neste momento.'; ?></span>
        </aside>
    </section>

    <?php if ($ano_encerrado_aprov): ?>
        <div class="apn-alert">
            <?php echo $apn_icon('alert'); ?>
            <p><strong>Ano lectivo encerrado.</strong> As notas deste ano já não podem ser aprovadas, rejeitadas ou alteradas enquanto o ano não for reaberto no módulo de Encerramento.</p>
        </div>
    <?php endif; ?>

    <div class="aprovar-stats">
        <div class="aprovar-stat apn-kpi-pending">
            <span class="apn-kpi-icon"><?php echo $apn_icon('clipboard'); ?></span>
            <div>
                <div class="val"><?php echo $total_pendentes; ?></div>
                <div class="lbl">Notas pendentes</div>
            </div>
        </div>
        <div class="aprovar-stat apn-kpi-groups">
            <span class="apn-kpi-icon"><?php echo $apn_icon('grid'); ?></span>
            <div>
                <div class="val"><?php echo $total_grupos; ?></div>
                <div class="lbl">Submissões agrupadas</div>
            </div>
        </div>
    </div>

    <?php if (empty($grupos)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><?php echo $apn_icon('check'); ?></div>
            <h2>Tudo aprovado</h2>
            <p>Não existem notas pendentes de aprovação neste momento.</p>
        </div>
    <?php else: ?>

        <?php foreach ($grupos as $gkey => $g): ?>
        <form method="POST">
            <?php wp_nonce_field('sige_aprovar_notas_nonce'); ?>
            <div class="grupo-card">
                <div class="grupo-header">
                    <div class="grupo-title-row">
                        <span class="grupo-icon"><?php echo $apn_icon('book'); ?></span>
                        <div>
                            <h3><?php echo esc_html($g['turma']); ?> - <?php echo esc_html($g['disciplina']); ?> - <?php echo (int)$g['trimestre']; ?>º Trimestre</h3>
                            <div class="grupo-meta">
                                <span><?php echo $apn_icon('teacher'); ?> Prof. <?php echo esc_html($g['professor']); ?></span>
                                <?php if ($g['submetido_em']): ?><span><?php echo $apn_icon('clock'); ?> <?php echo wp_date('d/m/Y H:i', strtotime($g['submetido_em'])); ?></span><?php endif; ?>
                                <span><?php echo $apn_icon('users'); ?> <?php echo count($g['notas']); ?> aluno(s)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="notas-table-wrap">
                <table class="notas-table">
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" class="check-all" data-sige-act="toggleGrupo"></th>
                            <th>Aluno</th>
                            <th class="sige-u-tac">ACS1</th>
                            <th class="sige-u-tac">ACS2</th>
                            <th class="sige-u-tac">AT/Exame</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($g['notas'] as $nota): ?>
                        <tr>
                            <td><input type="checkbox" name="nota_ids[]" value="<?php echo (int)$nota->id; ?>" checked></td>
                            <td><?php echo esc_html($nota->aluno_nome ?? 'Aluno #' . $nota->aluno_id); ?></td>
                            <td class="sige-u-tac"><span class="nota-val"><?php echo $nota->nota_ac !== null ? number_format((float)$nota->nota_ac, 1) : '-'; ?></span></td>
                            <td class="sige-u-tac"><span class="nota-val"><?php echo $nota->nota_acp !== null ? number_format((float)$nota->nota_acp, 1) : '-'; ?></span></td>
                            <td class="sige-u-tac"><span class="nota-val"><?php echo $nota->nota_exame !== null ? number_format((float)$nota->nota_exame, 1) : '-'; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <div class="grupo-actions">
                    <?php if ($ano_encerrado_aprov): ?>
                        <span class="hint" style="color:var(--color-danger-800);">Ano encerrado: acções de aprovação bloqueadas.</span>
                    <?php else: ?>
                        <span class="hint">Seleccione as notas e escolha uma acção:</span>
                        <button type="submit" name="sige_aprovar_notas_action" value="rejeitar" class="btn-rejeitar" data-sige-confirm="As notas seleccionadas serão rejeitadas e voltam ao professor para correcção." data-sige-titulo="Rejeitar notas" data-sige-confirmar="Rejeitar"><?php echo $apn_icon('x'); ?> Rejeitar</button>
                        <button type="submit" name="sige_aprovar_notas_action" value="aprovar" class="btn-aprovar" data-sige-confirm="As notas seleccionadas serão aprovadas e ficam validadas no sistema." data-sige-titulo="Aprovar notas" data-sige-confirmar="Aprovar"><?php echo $apn_icon('check'); ?> Aprovar</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

<script <?php echo sige_csp_script_attr(); ?>>
function toggleGrupo(master) {
    var card = master.closest('.grupo-card');
    var checks = card.querySelectorAll('input[name="nota_ids[]"]');
    checks.forEach(function(cb) { cb.checked = master.checked; });
}
</script>