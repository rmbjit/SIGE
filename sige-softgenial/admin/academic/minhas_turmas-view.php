<?php
/**
 * SIGE SoftGenial - Minhas Turmas (Área Docente)
 * Vista: ?page=sige-app&view=minhas_turmas
 * Acesso: Professor (sige_professor) e superiores
 */
if (!defined('ABSPATH')) exit;

if (!sige_page_guard(
    ['academico.turmas_ver','academico.lancar_notas'],
    ['sige_professor','sige_director','sige_pedagogico']
)) return;

global $wpdb;

$tP  = $wpdb->prefix . 'sige_professores';
$tT  = $wpdb->prefix . 'sige_turmas';
$tM  = $wpdb->prefix . 'sige_matriculas';
$tA  = $wpdb->prefix . 'sige_alunos';
$tN  = $wpdb->prefix . 'sige_notas';

// [MT-02] Multi-tenancy
$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;

// ── Ano lectivo activo ───────────────────────────────────────────────────────
$ano = 0;
if (function_exists('sige_fin_get_ano_letivo_master')) {
    $ano = (int)sige_fin_get_ano_letivo_master();
}
if (!$ano) {
    $tCfg = $wpdb->prefix . 'sige_config';
    if ($wpdb->get_var("SHOW TABLES LIKE '$tCfg'") === $tCfg) {
        $ano = (int)$wpdb->get_var($wpdb->prepare("SELECT ano_lectivo FROM $tCfg WHERE escola_id = %d LIMIT 1", $eid));
    }
}
if (!$ano) $ano = (int)wp_date('Y');

// ── Identificar registo do professor na tabela sige_professores ───────────────
// [FIX D01] Usar função centralizada sige_get_professor_atual()
$prof_row      = function_exists('sige_get_professor_atual') ? sige_get_professor_atual() : null;
$prof_sige_id  = $prof_row ? (int)$prof_row->id : 0;

$is_admin = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'));

// ── Descobrir tabela de vínculos disciplina-professor ────────────────────────
// O academic-logic.php pode chamar a tabela de formas diferentes.
// Testamos as mais prováveis e usamos a primeira que existir.
$possiveis_tbls = [
    $wpdb->prefix . 'sige_turma_disciplinas',
    $wpdb->prefix . 'sige_matriz_curricular',
    $wpdb->prefix . 'sige_docentes_turma',
    $wpdb->prefix . 'sige_disciplinas_turma',
];
$tD = null;
foreach ($possiveis_tbls as $tbl) {
    if ($wpdb->get_var("SHOW TABLES LIKE '$tbl'") === $tbl) {
        $tD = $tbl;
        break;
    }
}

// ── Turmas do professor ───────────────────────────────────────────────────────
// 1. Como director de turma
$turmas_director = [];
if ($prof_sige_id) {
    $turmas_director = $wpdb->get_results($wpdb->prepare(
        "SELECT t.*, 'director' as vinculo, '' as disciplina_nome
         FROM $tT t
         WHERE t.director_turma_id = %d AND t.ano_lectivo = %d AND t.escola_id = %d
         ORDER BY t.classe, t.nome",
        $prof_sige_id, $ano, $eid
    )) ?: [];
}

// 2. Como docente de disciplina(s)
$turmas_docente = [];
if ($prof_sige_id && $tD) {
    $cols = array_column((array)$wpdb->get_results("SHOW COLUMNS FROM $tD"), 'Field');
    $has_turma_id      = in_array('turma_id', $cols);
    $has_professor_id  = in_array('professor_id', $cols);
    $col_disc_nome     = in_array('disciplina_nome', $cols) ? 'disciplina_nome'
                       : (in_array('nome', $cols) ? 'nome' : null);

    if ($has_turma_id && $has_professor_id && $col_disc_nome) {
        $turmas_docente = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, 'docente' as vinculo, d.$col_disc_nome as disciplina_nome
             FROM $tD d
             JOIN $tT t ON t.id = d.turma_id
             WHERE d.professor_id = %d AND t.ano_lectivo = %d AND t.escola_id = %d
             ORDER BY t.classe, t.nome, d.$col_disc_nome",
            $prof_sige_id, $ano, $eid
        )) ?: [];
    }
}

// Admins vêem todas as turmas
if ($is_admin && !$prof_sige_id) {
    $turmas_director = $wpdb->get_results($wpdb->prepare(
        "SELECT t.*, 'director' as vinculo, '' as disciplina_nome
         FROM $tT t WHERE t.ano_lectivo = %d AND t.escola_id = %d ORDER BY t.classe, t.nome",
        $ano, $eid
    )) ?: [];
}

// ── Consolidar turmas únicas e agrupar disciplinas ────────────────────────────
$turmas_map = []; // turma_id => ['turma' => obj, 'director' => bool, 'disciplinas' => []]
foreach ($turmas_director as $t) {
    $id = (int)$t->id;
    if (!isset($turmas_map[$id])) {
        $turmas_map[$id] = ['turma' => $t, 'director' => true, 'disciplinas' => []];
    } else {
        $turmas_map[$id]['director'] = true;
    }
}
foreach ($turmas_docente as $t) {
    $id = (int)$t->id;
    if (!isset($turmas_map[$id])) {
        $turmas_map[$id] = ['turma' => $t, 'director' => false, 'disciplinas' => []];
    }
    if (!empty($t->disciplina_nome)) {
        $turmas_map[$id]['disciplinas'][] = $t->disciplina_nome;
    }
}

// ── Sumário de notas por turma ────────────────────────────────────────────────
$trimestre_sel = isset($_GET['tri']) ? (int)$_GET['tri'] : 1;
if ($trimestre_sel < 1 || $trimestre_sel > 3) $trimestre_sel = 1;

foreach ($turmas_map as $tid => &$item) {
    $total_alunos = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $tM WHERE turma_id=%d AND ano_lectivo=%d AND escola_id=%d AND status_matricula != 'cancelada'",
        $tid, $ano, $eid
    ));

    $com_notas = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT aluno_id) FROM $tN
         WHERE turma_id=%d AND ano_lectivo=%d AND trimestre=%d AND escola_id=%d
           AND (nota_ac IS NOT NULL OR nota_acp IS NOT NULL OR nota_exame IS NOT NULL)",
        $tid, $ano, $trimestre_sel, $eid
    ));

    $item['total_alunos'] = $total_alunos;
    $item['com_notas']    = $com_notas;
    $item['sem_notas']    = max(0, $total_alunos - $com_notas);
    $item['pct']          = $total_alunos > 0 ? round(($com_notas / $total_alunos) * 100) : 0;
}
unset($item);

// ── Turma seleccionada para detalhe ──────────────────────────────────────────
$turma_sel_id = isset($_GET['turma']) ? (int)$_GET['turma'] : 0;
$turma_sel    = ($turma_sel_id && isset($turmas_map[$turma_sel_id])) ? $turmas_map[$turma_sel_id] : null;
$alunos_turma = [];
if ($turma_sel) {
    $alunos_turma = $wpdb->get_results($wpdb->prepare(
        "SELECT a.id, a.nome_completo, a.genero, a.foto, a.data_nascimento,
                a.nome_pai, a.telemovel_pai, a.nome_mae, a.telemovel_mae,
                a.grupo_sanguineo, a.alergias
         FROM $tA a
         INNER JOIN $tM m ON m.aluno_id = a.id
         WHERE m.turma_id = %d AND m.ano_lectivo = %d AND m.escola_id = %d AND m.status_matricula != 'cancelada'
         ORDER BY a.nome_completo",
        $turma_sel_id, $ano, $eid
    )) ?: [];
}

$foto_default = SIGE_URL . 'assets/img/avatar-default.svg';

// ── Helper URL ────────────────────────────────────────────────────────────────
function mt_url(array $extra = []): string {
    $base = ['page' => 'sige-app', 'view' => 'minhas_turmas'];
    foreach (['turma', 'tri'] as $k) {
        if (isset($_GET[$k])) $base[$k] = $_GET[$k];
    }
    return admin_url('admin.php?' . http_build_query(array_merge($base, $extra)));
}
?>

<style>
/* SIGE SoftGenial v12.10.63 - Minhas Turmas: Compliance Visual Integral
   Referência mandatória: Painel Principal / Dashboard V2 MJS-grade.
   Escopo: camada visual apenas. Sem alteração de permissões, consultas, notas, pautas ou regras académicas. */
.sg-mt-page{
    --mt-blue:var(--sg-theme-primary,var(--color-brand-500));
    --mt-blue-dark:var(--sg-theme-primary-800,var(--color-ink-700));
    --mt-purple:var(--color-brand-500);
    --mt-purple-soft:var(--color-brand-50);
    --mt-green:var(--color-success-500);
    --mt-green-soft:var(--color-success-100);
    --mt-amber:var(--color-warning-500);
    --mt-amber-soft:var(--color-warning-50);
    --mt-red:var(--color-danger-500);
    --mt-red-soft:var(--color-danger-50);
    --mt-ink:var(--color-black);
    --mt-muted:var(--color-slate-700);
    --mt-line:var(--color-ink-100);
    --mt-card:var(--color-white);
    --mt-shadow:0 18px 45px rgba(34,34,64,.075);
    --mt-shadow-lg:0 24px 70px rgba(45,36,96,.10);
    display:flex;
    flex-direction:column;
    gap:18px;
    color:var(--mt-ink);
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif);
}
.sg-mt-page *{box-sizing:border-box;}
.sg-mt-page svg{stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;display:block;}

body.sige-view-minhas_turmas .sg-product-page-head{display:none!important;}
body.sige-view-minhas_turmas .sg-app-page{padding-top:0!important;}

/* HERO - padrão Painel Principal */
.sg-mt-hero{
    position:relative;
    overflow:hidden;
    min-height:178px;
    border-radius:var(--radius-xl);
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-info-50) 100%);
    border:1px solid rgba(92,64,187,.12);
    box-shadow:var(--shadow-xs);
    padding:32px 34px;
    display:grid;
    grid-template-columns:minmax(0,1.04fr) minmax(320px,.96fr);
    gap:22px;
    align-items:center;
}
.sg-mt-hero:before{content:"";position:absolute;inset:auto -80px -130px auto;width:420px;height:300px;border-radius:var(--radius-pill);background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);pointer-events:none;}
.sg-mt-hero:after{display:none!important;content:none!important;}
.sg-mt-hero-main,.sg-mt-hero-panel{position:relative;z-index:1;}
.sg-mt-kicker{display:inline-flex;align-items:center;gap:var(--space-2);margin:0 0 10px;padding:0;border:0;border-radius:0;background:transparent;color:var(--mt-blue);font-size:12px;line-height:1.2;font-weight:700;letter-spacing:.11em;text-transform:uppercase;box-shadow:none;}
.sg-mt-kicker:before,.sg-mt-kicker:after{display:none!important;content:none!important;}
.sg-mt-kicker svg{width:18px!important;height:18px!important;}
.sg-mt-title{margin:0;max-width:650px;color:var(--color-black);font-size:31px;line-height:1.08;font-weight:700;letter-spacing:-.04em;font-family:inherit;}
.sg-mt-subtitle{max-width:650px;margin:var(--space-3) 0 0;color:var(--color-slate-700);font-size:15px;line-height:1.65;font-weight:500;}
.sg-mt-hero-actions{display:flex;flex-wrap:wrap;gap:var(--space-3);margin-top:24px;}
.sg-mt-btn{min-height:42px;display:inline-flex;align-items:center;justify-content:center;gap:9px;border-radius:var(--radius-md);padding:0 18px;font-size:var(--fs-sm);font-weight:700;text-decoration:none;border:1px solid transparent;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease;cursor:pointer;font-family:inherit;}
.sg-mt-btn svg{width:18px!important;height:18px!important;}
.sg-mt-btn-primary{background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-800,var(--color-ink-700)));color:var(--color-white)!important;box-shadow:var(--shadow-md);}
.sg-mt-btn-secondary,.sg-mt-btn-light{background:var(--color-white);color:var(--color-ink-900)!important;border-color:var(--color-ink-100);box-shadow:var(--shadow-sm);}
.sg-mt-btn-soft{background:var(--color-brand-50);color:var(--color-brand-500)!important;border-color:var(--color-brand-100);}
.sg-mt-btn:hover{transform:translateY(-1px);box-shadow:var(--shadow-md);}
.sg-mt-hero-panel{min-height:148px;border-radius:var(--radius-xl);background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.18));padding:22px;overflow:hidden;display:flex;flex-direction:column;justify-content:center;gap:10px;border:1px solid rgba(92,64,187,.08);}
.sg-mt-hero-panel:before{content:"";position:absolute;right:22px;bottom:16px;width:112px;height:92px;border-radius:22px 22px 12px 12px;background:rgba(109,93,252,.16);box-shadow:inset 0 0 0 2px rgba(109,93,252,.12);}
.sg-mt-hero-panel>*{position:relative;z-index:1;}
.sg-mt-panel-label{display:flex;align-items:center;gap:var(--space-2);color:var(--color-brand-500);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.11em;}
.sg-mt-panel-label svg{width:18px!important;height:18px!important;}
.sg-mt-panel-number{font-size:40px;line-height:1;font-weight:700;letter-spacing:-.045em;color:var(--color-ink-900);}
.sg-mt-panel-text{font-size:var(--fs-sm);color:var(--color-slate-600);line-height:1.55;margin:0;max-width:320px;font-weight:600;}
.sg-mt-panel-track{height:10px;border-radius:var(--radius-pill);background:rgba(255,255,255,.7);overflow:hidden;margin-top:4px;}
.sg-mt-panel-track span{display:block;height:100%;border-radius:var(--radius-pill);background:linear-gradient(90deg,var(--color-brand-400),var(--color-brand-600));}

/* Tabs */
.tri-tabs{display:flex;gap:var(--space-2);margin:0;flex-wrap:wrap;border:0;}
.tri-tab{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 15px;border-radius:var(--radius-md);background:var(--color-white);border:1px solid var(--color-slate-100);box-shadow:var(--shadow-sm);text-decoration:none;color:var(--color-slate-700);font-size:var(--fs-sm);font-weight:700;transition:all .18s ease;}
.tri-tab:hover{border-color:var(--sg-theme-soft,var(--color-brand-50));color:var(--mt-blue);}
.tri-tab.active{background:var(--mt-blue);border-color:var(--mt-blue);color:var(--color-white);box-shadow:var(--shadow-sm);}

/* KPIs */
.sg-mt-summary-grid,.sg-mt-detail-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:var(--space-4);margin:0;}
.sg-mt-detail-stats{grid-template-columns:repeat(4,minmax(0,1fr));}
.sg-mt-kpi{position:relative;overflow:hidden;display:grid;grid-template-columns:auto minmax(0,1fr);align-items:center;gap:var(--space-4);min-height:104px;padding:18px 20px;border-radius:var(--radius-xl);background:var(--color-white);border:1px solid rgba(28,32,54,.08);box-shadow:var(--shadow-xs);}
.sg-mt-kpi:after{content:"";position:absolute;right:-28px;top:-34px;width:92px;height:92px;border-radius:50%;background:var(--kpi-soft,var(--color-brand-50));}
.sg-mt-kpi-icon{width:52px;height:52px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;background:var(--kpi-soft,var(--color-brand-50));color:var(--kpi-color,var(--color-brand-500));position:relative;z-index:1;}
.sg-mt-kpi-icon svg{width:24px!important;height:24px!important;}
.sg-mt-kpi>div{position:relative;z-index:1;}
.sg-mt-kpi strong{display:block;color:var(--color-black);font-size:27px;line-height:1;font-weight:700;letter-spacing:-.03em;}
.sg-mt-kpi span{display:block;margin-top:7px;color:var(--color-slate-600);font-size:var(--fs-sm);font-weight:600;}
.sg-mt-kpi small{display:block;margin-top:6px;color:var(--color-ink-400);font-size:12px;font-weight:600;}
.sg-mt-kpi.purple{--kpi-color:var(--color-brand-500);--kpi-soft:var(--color-brand-50);}
.sg-mt-kpi.green{--kpi-color:var(--color-success-500);--kpi-soft:var(--color-success-100);}
.sg-mt-kpi.amber{--kpi-color:var(--color-warning-500);--kpi-soft:var(--color-warning-50);}
.sg-mt-kpi.blue{--kpi-color:var(--sg-theme-primary,var(--color-brand-500));--kpi-soft:var(--color-info-50);}
.sg-mt-kpi.red{--kpi-color:var(--color-danger-500);--kpi-soft:var(--color-danger-50);}

/* Cards de turmas */
.mt-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:18px;}
.mt-card{background:var(--color-white);border-radius:var(--radius-xl);border:1px solid rgba(28,32,54,.08);box-shadow:var(--shadow-xs);overflow:hidden;transition:transform .18s ease,box-shadow .18s ease;}
.mt-card:hover{transform:translateY(-1px);box-shadow:var(--shadow-lg);}
.mt-card-header{padding:18px 20px!important;display:flex;justify-content:space-between;align-items:flex-start;gap:var(--space-3);background:var(--color-white)!important;border-bottom:1px solid var(--color-slate-100);}
.mt-card-title{font-size:17px;font-weight:700;color:var(--color-ink-500);letter-spacing:-.03em;}
.mt-card-meta{font-size:12px;color:var(--color-ink-400);margin-top:4px;font-weight:600;}
.mt-card-body{padding:18px 20px;}
.mt-badge{display:inline-flex;align-items:center;justify-content:center;padding:6px 10px;border-radius:var(--radius-pill);font-size:var(--fs-xs);font-weight:700;white-space:nowrap;}
.mt-badge.director{background:var(--color-success-100);color:var(--color-success-900);}
.mt-badge.docente{background:var(--color-info-50);color:var(--sg-theme-primary,var(--color-brand-500));}
.disc-tag{display:inline-flex;background:var(--color-brand-50);color:var(--color-brand-500);border-radius:var(--radius-pill);padding:5px 9px;font-size:var(--fs-xs);font-weight:700;margin:2px;}
.mt-progress-label{display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:12px;color:var(--color-slate-600);margin-bottom:7px;font-weight:600;}
.mt-progress-bar{height:10px;background:var(--color-slate-100);border-radius:var(--radius-pill);overflow:hidden;margin-top:6px;}
.mt-progress-fill{height:100%;border-radius:var(--radius-pill);transition:.4s;background:linear-gradient(90deg,var(--color-brand-400),var(--color-brand-600))!important;}
.mt-stat-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:14px;}
.mt-stat{background:var(--color-slate-50);border:1px solid var(--color-slate-100);border-radius:var(--radius-lg);padding:var(--space-3);text-align:left;}
.mt-stat .n{font-size:var(--fs-xl);font-weight:700;line-height:1;color:var(--color-black)!important;letter-spacing:-.03em;}
.mt-stat .l{font-size:12px;color:var(--color-ink-400);margin-top:6px;font-weight:600;}
.mt-card-actions{display:grid;grid-template-columns:1fr 1fr;gap:var(--space-2);margin-top:14px;}
.mt-btn{display:inline-flex;align-items:center;justify-content:center;gap:var(--space-2);min-height:38px;padding:0 13px;border-radius:var(--radius-md);font-size:12px;font-weight:700;text-decoration:none;cursor:pointer;border:1px solid transparent;}
.mt-btn svg{width:16px!important;height:16px!important;}
.mt-btn-primary{background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-800,var(--color-ink-700)));color:var(--color-white)!important;box-shadow:var(--shadow-sm);}
.mt-btn-outline{background:var(--color-white);color:var(--color-ink-900)!important;border-color:var(--color-ink-100);box-shadow:var(--shadow-sm);}
.mt-btn-sm{min-height:36px;padding:0 var(--space-3);font-size:12px;}

/* Detalhe da turma */
.sg-mt-progress-card{background:var(--color-white);border:1px solid rgba(28,32,54,.08);border-radius:var(--radius-xl);box-shadow:var(--shadow-xs);padding:18px 20px;}
.sg-mt-progress-card-head{display:flex;justify-content:space-between;align-items:center;gap:var(--space-3);color:var(--color-ink-500);font-size:var(--fs-sm);font-weight:700;margin-bottom:8px;}
.sg-mt-search{margin:0;}
.sg-mt-search input{width:100%;max-width:380px;min-height:44px;padding:0 14px;border:1px solid var(--color-ink-100);border-radius:var(--radius-md);background:var(--color-white);color:var(--color-ink-500);font-size:var(--fs-sm);font-weight:600;box-shadow:var(--shadow-sm);outline:none;}
.sg-mt-search input:focus{border-color:rgba(90,63,214,.55);box-shadow:var(--shadow-xs);}
.aluno-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:var(--space-4);}
.aluno-card{background:var(--color-white);border:1px solid rgba(28,32,54,.08);border-radius:var(--radius-xl);box-shadow:var(--shadow-md);padding:var(--space-4);display:flex;gap:var(--space-3);align-items:flex-start;}
.aluno-foto{width:48px;height:48px;border-radius:var(--radius-lg);object-fit:cover;flex-shrink:0;border:2px solid var(--color-slate-100);}
.aluno-info .nome{font-weight:700;font-size:var(--fs-sm);color:var(--color-ink-500);line-height:1.35;}
.aluno-info .meta{font-size:11.5px;color:var(--color-slate-500);margin-top:4px;font-weight:600;}
.sg-mt-status-dot{position:absolute;bottom:-2px;right:-2px;width:14px;height:14px;border-radius:50%;border:2px solid var(--color-white);background:var(--status-color,var(--color-warning-500));}
.sg-mt-inline-status{display:inline-flex;align-items:center;align-self:center;font-size:var(--fs-xs);font-weight:700;color:var(--color-slate-500);}
.sg-mt-inline-status.ok{color:var(--color-success-900);}
.sg-mt-inline-status.pending{color:var(--color-warning-800);}

/* Estados vazios */
.sg-mt-empty{background:var(--color-white);border:1px solid rgba(28,32,54,.08);border-radius:var(--radius-xl);box-shadow:var(--shadow-xs);padding:42px;text-align:center;}
.sg-mt-empty-icon{width:52px;height:52px;margin:0 auto 14px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;background:var(--color-brand-50);color:var(--color-brand-500);}
.sg-mt-empty-icon svg{width:24px!important;height:24px!important;}
.sg-mt-empty h3{margin:0 0 var(--space-2);color:var(--color-ink-500);font-size:18px;font-weight:700;letter-spacing:-.03em;}
.sg-mt-empty p{margin:0;color:var(--color-slate-600);font-size:var(--fs-sm);line-height:1.6;font-weight:600;}
.sg-mt-empty.warning .sg-mt-empty-icon{background:var(--color-warning-50);color:var(--color-warning-500);}

@media(max-width:980px){
    .sg-mt-hero{grid-template-columns:1fr;padding:26px 24px;}
    .sg-mt-summary-grid,.sg-mt-detail-stats{grid-template-columns:repeat(2,minmax(0,1fr));}
}
@media(max-width:680px){
    .sg-mt-title{font-size:var(--fs-xl);}
    .sg-mt-hero-actions{display:grid;grid-template-columns:1fr;}
    .sg-mt-btn{width:100%;}
    .sg-mt-summary-grid,.sg-mt-detail-stats,.mt-grid,.aluno-grid{grid-template-columns:1fr;}
    .mt-card-actions{grid-template-columns:1fr;}
    .tri-tabs{display:grid;grid-template-columns:1fr;}
}

/* v12.10.64 - Minhas Turmas: Tabs de trimestre em compliance total com o Painel Principal.
   Ajuste visual apenas: não altera trimestre seleccionado, links, notas ou consultas. */
.sg-mt-page .tri-tabs{
    display:flex!important;
    flex-wrap:wrap!important;
    align-items:center!important;
    gap:var(--space-3)!important;
    margin:0 0 18px!important;
    padding:0!important;
    border:0!important;
    background:transparent!important;
}
.sg-mt-page .tri-tab{
    min-height:46px!important;
    display:inline-flex!important;
    align-items:center!important;
    justify-content:center!important;
    gap:10px!important;
    border-radius:var(--radius-md)!important;
    padding:0 22px!important;
    font-size:var(--fs-base)!important;
    line-height:1!important;
    font-weight:700!important;
    letter-spacing:0!important;
    text-decoration:none!important;
    border:1px solid var(--color-ink-100)!important;
    background:var(--color-white)!important;
    color:var(--color-ink-900)!important;
    box-shadow:var(--shadow-sm);
    transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease,background .18s ease!important;
}
.sg-mt-page .tri-tab svg{
    width:18px!important;
    height:18px!important;
    stroke:currentColor!important;
    color:currentColor!important;
    fill:none!important;
    opacity:1!important;
    flex:0 0 auto!important;
}
.sg-mt-page .tri-tab span{
    display:inline-flex!important;
    align-items:center!important;
    margin:0!important;
    color:inherit!important;
    font:inherit!important;
    white-space:nowrap!important;
}
.sg-mt-page .tri-tab:hover{
    transform:translateY(-1px)!important;
    border-color:var(--color-info-100)!important;
    box-shadow:var(--shadow-md);
    color:var(--sg-theme-primary,var(--color-brand-500))!important;
}
.sg-mt-page .tri-tab.active{
    background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-800,var(--color-ink-700)))!important;
    border-color:var(--sg-theme-primary,var(--color-brand-500))!important;
    color:var(--color-white)!important;
    box-shadow:var(--shadow-md);
}
.sg-mt-page .tri-tab.active:hover{
    color:var(--color-white)!important;
    box-shadow:var(--shadow-md);
}
@media(max-width:680px){
    .sg-mt-page .tri-tabs{
        display:grid!important;
        grid-template-columns:1fr!important;
        gap:10px!important;
    }
    .sg-mt-page .tri-tab{
        width:100%!important;
    }
}


/* v12.10.65 - Minhas Turmas: centralização rigorosa dos ícones dos KPIs
   Ajuste visual apenas: centraliza os ícones dentro dos blocos dos indicadores. */
.sg-mt-page .sg-mt-kpi-icon{
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    text-align:center!important;
}
.sg-mt-page .sg-mt-kpi-icon svg{
    width:24px!important;
    height:24px!important;
    margin:0 auto!important;
    display:block!important;
    flex:0 0 auto!important;
}

</style>

<div class="sg-mt-page">
    <?php
    $sg_mt_total_turmas = count($turmas_map);
    $sg_mt_total_alunos = array_sum(array_column(array_values($turmas_map), 'total_alunos'));
    $sg_mt_total_completas = count(array_filter(array_values($turmas_map), fn($x) => (int)$x['pct'] === 100));
    $sg_mt_hero_title = 'Minhas Turmas';
    $sg_mt_hero_subtitle = 'Acompanhe as turmas atribuídas, consulte alunos e siga o progresso de lançamento de notas por trimestre.';
    $sg_mt_panel_label = 'Ano lectivo';
    $sg_mt_panel_number = (string)$ano;
    $sg_mt_panel_text = $sg_mt_total_turmas . ' turma(s), ' . $sg_mt_total_alunos . ' aluno(s) e ' . $sg_mt_total_completas . ' turma(s) com notas completas no trimestre seleccionado.';
    $sg_mt_panel_pct = $sg_mt_total_turmas > 0 ? round(($sg_mt_total_completas / max(1, $sg_mt_total_turmas)) * 100) : 0;
    if ($turma_sel) {
        $nt = $turma_sel['turma'];
        $nome_t = !empty($nt->nome_turma) ? $nt->nome_turma : $nt->nome;
        $sg_mt_ti = $turmas_map[$turma_sel_id] ?? ['total_alunos'=>0,'pct'=>0,'com_notas'=>0,'sem_notas'=>0];
        $sg_mt_hero_title = $nome_t;
        $sg_mt_hero_subtitle = 'Consulte a lista de alunos, acompanhe o progresso de notas e aceda rapidamente às pautas desta turma.';
        $sg_mt_panel_label = 'Progresso da turma';
        $sg_mt_panel_number = ((int)$sg_mt_ti['pct']) . '%';
        $sg_mt_panel_text = ((int)$sg_mt_ti['total_alunos']) . ' aluno(s) activo(s), ' . ((int)$sg_mt_ti['com_notas']) . ' com notas e ' . ((int)$sg_mt_ti['sem_notas']) . ' por lançar no ' . ((int)$trimestre_sel) . 'º trimestre.';
        $sg_mt_panel_pct = (int)$sg_mt_ti['pct'];
    }
    ?>
    <section class="sg-mt-hero" aria-label="Área Docente - Minhas Turmas">
        <div class="sg-mt-hero-main">
            <div class="sg-mt-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('book') : ''; ?><span>Área Docente</span></div>
            <h1 class="sg-mt-title"><?php echo esc_html($sg_mt_hero_title); ?></h1>
            <p class="sg-mt-subtitle"><?php echo esc_html($sg_mt_hero_subtitle); ?></p>
            <div class="sg-mt-hero-actions">
                <?php if ($turma_sel): ?>
                    <a href="<?php echo esc_url(mt_url(['turma'=>''])); ?>" class="sg-mt-btn sg-mt-btn-secondary"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('grid') : ''; ?> Minhas Turmas</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=notas&turma_id=' . $turma_sel_id . '&tri=' . $trimestre_sel)); ?>" class="sg-mt-btn sg-mt-btn-primary"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('edit') : ''; ?> Lançar Notas</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=pautas&turma_id=' . $turma_sel_id)); ?>" class="sg-mt-btn sg-mt-btn-light"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('clipboard') : ''; ?> Ver Pautas</a>
                <?php else: ?>
                    <span class="sg-mt-btn sg-mt-btn-soft"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('calendar') : ''; ?> Ano Lectivo <?php echo esc_html($ano); ?></span>
                    <span class="sg-mt-btn sg-mt-btn-light"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('check') : ''; ?> <?php echo esc_html($trimestre_sel); ?>º Trimestre</span>
                <?php endif; ?>
            </div>
        </div>
        <aside class="sg-mt-hero-panel" aria-label="Resumo docente">
            <div class="sg-mt-panel-label"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('shield') : ''; ?><span><?php echo esc_html($sg_mt_panel_label); ?></span></div>
            <div class="sg-mt-panel-number"><?php echo esc_html($sg_mt_panel_number); ?></div>
            <p class="sg-mt-panel-text"><?php echo esc_html($sg_mt_panel_text); ?></p>
            <div class="sg-mt-panel-track" aria-hidden="true"><span style="width:<?php echo esc_attr($sg_mt_panel_pct); ?>%;"></span></div>
        </aside>
    </section>
<?php if (empty($turmas_map) && !$turma_sel): ?>
    <?php if (!$is_admin && !$prof_sige_id): ?>
    <!-- Professor não vinculado -->
    <div class="sg-mt-empty warning">
        <div class="sg-mt-empty-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('alert') : ''; ?></div>
        <h3>Conta não vinculada</h3>
        <p>O seu utilizador (<em><?php echo esc_html(wp_get_current_user()->user_email); ?></em>) não está vinculado a nenhum registo de professor no SIGE.<br>
        Contacte a administração para que o seu perfil seja vinculado correctamente no módulo <strong>Equipa &amp; Professores</strong>.</p>
    </div>
    <?php else: ?>
    <!-- Sem turmas atribuídas -->
    <div class="sg-mt-empty">
        <div class="sg-mt-empty-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('folder') : ''; ?></div>
        <h3>Nenhuma turma atribuída</h3>
        <p>Ainda não tens turmas atribuídas para o ano <?php echo esc_html($ano); ?>.<br>
        Contacta a Secretaria para que te atribuam turmas no módulo de <strong>Turmas</strong>.</p>
    </div>
    <?php endif; ?>

    <?php elseif (!$turma_sel): ?>
    <!-- ══════════════════ LISTA DE TURMAS ══════════════════ -->

    <!-- Selector de trimestre -->
    <div class="tri-tabs">
        <?php for ($t = 1; $t <= 3; $t++): ?>
        <a href="<?php echo esc_url(mt_url(['tri' => $t])); ?>"
           class="tri-tab <?php echo $trimestre_sel === $t ? 'active' : ''; ?>">
            <?php echo function_exists('sige_ui_icon') ? sige_ui_icon($trimestre_sel === $t ? 'check' : 'calendar') : ''; ?>
            <span><?php echo $t; ?>º Trimestre</span>
        </a>
        <?php endfor; ?>
    </div>
    <!-- Resumo rápido -->
    <?php
    $total_t = count($turmas_map);
    $total_a = array_sum(array_column(array_values($turmas_map), 'total_alunos'));
    $completo = count(array_filter(array_values($turmas_map), fn($x) => $x['pct'] === 100));
    ?>
    <section class="sg-mt-summary-grid" aria-label="Resumo das turmas">
        <article class="sg-mt-kpi purple">
            <span class="sg-mt-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('school') : ''; ?></span>
            <div><strong><?php echo (int)$total_t; ?></strong><span>Turmas</span><small>Atribuídas ao perfil</small></div>
        </article>
        <article class="sg-mt-kpi green">
            <span class="sg-mt-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('users') : ''; ?></span>
            <div><strong><?php echo (int)$total_a; ?></strong><span>Alunos no total</span><small>Nas turmas listadas</small></div>
        </article>
        <article class="sg-mt-kpi amber">
            <span class="sg-mt-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('check') : ''; ?></span>
            <div><strong><?php echo (int)$completo; ?>/<?php echo (int)$total_t; ?></strong><span>Turmas completas</span><small>No trimestre seleccionado</small></div>
        </article>
    </section>
<!-- Cards de turmas -->
    <div class="mt-grid">
    <?php foreach ($turmas_map as $tid => $item):
        $t   = $item['turma'];
        $nome = !empty($t->nome_turma) ? $t->nome_turma : $t->nome;
        $pct  = $item['pct'];
        $cor  = $pct === 100 ? 'var(--color-success-700)' : ($pct >= 50 ? 'var(--color-warning-600)' : 'var(--color-danger-600)');
    ?>
    <div class="mt-card">
        <div class="mt-card-header" style="background:<?php echo $item['director'] ? 'var(--color-success-50)' : 'var(--sg-theme-soft,#f1edff)'; ?>;">
            <div>
                <div class="mt-card-title"><?php echo esc_html($nome); ?></div>
                <div class="mt-card-meta">Classe <?php echo esc_html($t->classe ?? ''); ?> · <?php echo esc_html($item['total_alunos']); ?> aluno(s)</div>
            </div>
            <span class="mt-badge <?php echo $item['director'] ? 'director' : 'docente'; ?>">
                <?php echo $item['director'] ? 'Director' : 'Docente'; ?>
            </span>
        </div>
        <div class="mt-card-body">
            <?php if (!empty($item['disciplinas'])): ?>
            <div style="margin-bottom:10px;">
                <?php foreach (array_unique($item['disciplinas']) as $disc): ?>
                <span class="disc-tag"><?php echo esc_html($disc); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Progresso de notas -->
            <div class="mt-progress-label"><span>Notas <?php echo esc_html($trimestre_sel); ?>º Trimestre</span><strong><?php echo esc_html($pct); ?>% · <?php echo esc_html($item['com_notas']); ?>/<?php echo esc_html($item['total_alunos']); ?></strong></div>
            <div class="mt-progress-bar">
                <div class="mt-progress-fill" style="width:<?php echo esc_attr($pct); ?>%; background:<?php echo esc_attr($cor); ?>;"></div>
            </div>

            <div class="mt-stat-row">
                <div class="mt-stat">
                    <div class="n" style="color:var(--color-success-700);"><?php echo esc_html($item['com_notas']); ?></div>
                    <div class="l">Com notas</div>
                </div>
                <div class="mt-stat">
                    <div class="n" style="color:<?php echo $item['sem_notas'] > 0 ? 'var(--color-danger-600)' : 'var(--color-success-700)'; ?>;"><?php echo esc_html($item['sem_notas']); ?></div>
                    <div class="l">Por lançar</div>
                </div>
            </div>

            <div class="mt-card-actions">
                <a href="<?php echo esc_url(mt_url(['turma' => $tid, 'tri' => $trimestre_sel])); ?>"
                   class="mt-btn mt-btn-outline mt-btn-sm" ><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('users') : ''; ?> Ver Alunos</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=notas&turma_id=' . $tid . '&tri=' . $trimestre_sel)); ?>"
                   class="mt-btn mt-btn-primary mt-btn-sm" ><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('edit') : ''; ?> Notas</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- ══════════════════ DETALHE DA TURMA ══════════════════ -->

    <!-- Selector de trimestre -->
    <div class="tri-tabs">
        <?php for ($t = 1; $t <= 3; $t++): ?>
        <a href="<?php echo esc_url(mt_url(['turma' => $turma_sel_id, 'tri' => $t])); ?>"
           class="tri-tab <?php echo $trimestre_sel === $t ? 'active' : ''; ?>">
            <?php echo $t; ?>º Trimestre
        </a>
        <?php endfor; ?>
    </div>
    <!-- Estatísticas da turma -->
    <section class="sg-mt-detail-stats" aria-label="Estatísticas da turma">
        <?php
        $ti = $turmas_map[$turma_sel_id];
        $pct_t = $ti['pct'];
        $cor_t = $pct_t === 100 ? 'var(--color-success-700)' : ($pct_t >= 50 ? 'var(--color-warning-600)' : 'var(--color-danger-600)');
        ?>
        <article class="sg-mt-kpi blue"><span class="sg-mt-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('users') : ''; ?></span><div><strong><?php echo esc_html($ti['total_alunos']); ?></strong><span>Total de alunos</span><small>Matriculados na turma</small></div></article>
        <article class="sg-mt-kpi green"><span class="sg-mt-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('check') : ''; ?></span><div><strong><?php echo esc_html($ti['com_notas']); ?></strong><span>Com notas</span><small>No trimestre seleccionado</small></div></article>
        <article class="sg-mt-kpi amber"><span class="sg-mt-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('edit') : ''; ?></span><div><strong><?php echo esc_html($ti['sem_notas']); ?></strong><span>Por lançar</span><small>Alunos ainda pendentes</small></div></article>
        <article class="sg-mt-kpi purple"><span class="sg-mt-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('chart') : ''; ?></span><div><strong><?php echo esc_html($pct_t); ?>%</strong><span>Completo</span><small>Progresso de notas</small></div></article>
    </section>
    <!-- Barra de progresso global -->
    <section class="sg-mt-progress-card" aria-label="Progresso de notas">
        <div class="sg-mt-progress-card-head">
            <span>Progresso de notas - <?php echo esc_html($trimestre_sel); ?>º Trimestre</span>
            <strong><?php echo esc_html($pct_t); ?>%</strong>
        </div>
        <div class="mt-progress-bar" style="height:12px;">
            <div class="mt-progress-fill" style="width:<?php echo esc_attr($pct_t); ?>%;"></div>
        </div>
    </section>
<!-- Lista de alunos -->
    <?php if (empty($alunos_turma)): ?>
    <div class="sg-mt-empty"><div class="sg-mt-empty-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('users') : ''; ?></div><h3>Nenhum aluno matriculado</h3><p>Nenhum aluno activo foi encontrado nesta turma para o ano lectivo seleccionado.</p></div>
    <?php else: ?>
    <!-- Pesquisa rápida -->
    <div class="sg-mt-search">
        <input type="text" id="mt-busca-aluno" placeholder="Pesquisar aluno…" oninput="filtrarAlunos(this.value)">
    </div>

    <div class="aluno-grid" id="mt-aluno-grid">
    <?php
    // Notas do trimestre para badges individuais
    $notas_por_aluno = [];
    if (!empty($alunos_turma)) {
        $ids = implode(',', array_map(fn($a) => (int)$a->id, $alunos_turma));
        $rows_notas = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT aluno_id FROM $tN
            WHERE turma_id=%d AND ano_lectivo=%d AND trimestre=%d AND escola_id=%d
              AND (nota_ac IS NOT NULL OR nota_acp IS NOT NULL OR nota_exame IS NOT NULL)
              AND aluno_id IN ($ids)
        ", $turma_sel_id, $ano, $trimestre_sel, $eid));
        foreach ($rows_notas as $rn) $notas_por_aluno[(int)$rn->aluno_id] = true;
    }
    foreach ($alunos_turma as $a):
        $tem_nota = isset($notas_por_aluno[(int)$a->id]);
        $foto = !empty($a->foto) ? $a->foto : $foto_default;
        $genero = strtolower($a->genero ?? '');
        $genero_label = $genero === 'f' || $genero === 'feminino' ? '♀' : ($genero === 'm' || $genero === 'masculino' ? '♂' : '');
    ?>
    <div class="aluno-card" data-nome="<?php echo esc_attr(strtolower($a->nome_completo)); ?>">
        <div style="position:relative; flex-shrink:0;">
            <img src="<?php echo esc_url($foto); ?>" class="aluno-foto" alt="<?php echo esc_attr($a->nome_completo); ?>"
                 onerror="this.src='<?php echo esc_url($foto_default); ?>'">
            <span class="sg-mt-status-dot" style="--status-color:<?php echo $tem_nota ? 'var(--color-success-500)' : 'var(--color-warning-500)'; ?>;" title="<?php echo $tem_nota ? 'Notas lançadas' : 'Sem notas'; ?>"></span>
        </div>
        <div class="aluno-info" style="min-width:0;">
            <div class="nome"><?php echo esc_html($a->nome_completo); ?> <?php echo $genero_label; ?></div>
            <?php if (!empty($a->data_nascimento)): ?>
            <div class="meta">Nascimento: <?php echo esc_html(wp_date('d/m/Y', strtotime($a->data_nascimento))); ?></div>
            <?php endif; ?>
            <?php if (!empty($a->telemovel_pai) || !empty($a->telemovel_mae)): ?>
            <div class="meta">Contacto: <?php echo esc_html($a->telemovel_pai ?: $a->telemovel_mae); ?></div>
            <?php endif; ?>
            <?php if (!empty($a->alergias)): ?>
            <div class="meta" style="color:var(--color-danger-600);" title="<?php echo esc_attr($a->alergias); ?>">Alergias</div>
            <?php endif; ?>
            <div style="margin-top:6px; display:flex; gap:6px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=notas&turma_id=' . $turma_sel_id . '&aluno_id=' . $a->id . '&tri=' . $trimestre_sel)); ?>"
                   class="mt-btn mt-btn-primary mt-btn-sm"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('edit') : ''; ?> Notas</a>
                <?php if ($tem_nota): ?>
                <span class="sg-mt-inline-status ok">Com notas</span>
                <?php else: ?>
                <span class="sg-mt-inline-status pending">Pendente</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>

<script <?php echo sige_csp_script_attr(); ?>>
function filtrarAlunos(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('#mt-aluno-grid .aluno-card').forEach(function(card) {
        var nome = card.dataset.nome || '';
        card.style.display = (!q || nome.includes(q)) ? '' : 'none';
    });
}
</script>