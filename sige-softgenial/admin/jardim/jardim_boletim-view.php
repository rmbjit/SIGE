<?php
/**
 * Módulo Jardim de Infância - Boletim Imprimível
 * SIGE SoftGenial | SNE Moçambique
 *
 * v2.1 - Abril 2026
 * - SQL concatenation eliminada → $wpdb->prepare()
 * - function_exists fallbacks → $escola_id
 * - date() → wp_date(), função guardada
 * - Font: Segoe UI → Inter
 */
if (!defined('ABSPATH')) exit;
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['jardim.boletim_ver','jardim.boletim_emitir'],
    ['sige_assistente','sige_director','sige_educador','sige_secretario','sige_secretaria_geral']
)) return;
global $wpdb;
$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
$ano      = sige_ano_lectivo_atual();
$trim_sel = max(1, min(3, (int)($_GET['trimestre'] ?? 1)));
// Configuração da escola
$escola = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1", $escola_id));
$logo   = $escola->logo_documentos_url ?: $escola->logo_sistema_url ?: '';
// Turmas pré-escolar
// ── Turmas pré-escolar - fonte central v12.10.104 ──────────────────────
$turmas = function_exists('sige_jardim_get_preescolar_turmas_v104')
    ? sige_jardim_get_preescolar_turmas_v104((int)$escola_id, (int)$ano, true)
    : [];
$turma_sel = (int)($_GET['turma_id'] ?? ($turmas[0]->id ?? 0));
$turma_obj = null;
foreach ($turmas as $t) { if ((int)$t->id === $turma_sel) { $turma_obj = $t; break; } }
// ── Alunos da turma - fonte central v12.10.104 ─────────────────────────────────────────
$alunos = [];
if ($turma_sel && function_exists('sige_jardim_get_alunos_activos_turma_v104')) {
    $alunos = sige_jardim_get_alunos_activos_turma_v104((int)$turma_sel, (int)$escola_id, (int)$ano, "a.id, a.nome_completo, a.foto, a.genero, a.data_nascimento, a.nome_pai, a.nome_mae, a.contacto_encarregado");
}
// ── Disciplinas/áreas da turma - fonte central v12.10.104 ──────────────
$discs = function_exists('sige_jardim_get_disciplinas_turma_v104')
    ? sige_jardim_get_disciplinas_turma_v104((int)$turma_sel, (int)$escola_id)
    : [];
// Critérios
$criterios = [];
$tbl_crit = $wpdb->prefix.'sige_jardim_criterios';
$crit_tbl_ok = (int)$wpdb->get_var("SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tbl_crit'");
if ($crit_tbl_ok && $discs) {
    $disc_ids = implode(',', array_map('intval', array_column($discs,'id')));
    $crit_extra = '';
    if (function_exists('sige_jardim_column_exists_v104') && sige_jardim_column_exists_v104($tbl_crit, 'ativo')) {
        $crit_extra .= " AND (ativo IS NULL OR ativo=1)";
    }
    if (function_exists('sige_jardim_column_exists_v104') && sige_jardim_column_exists_v104($tbl_crit, 'ano_lectivo')) {
        $crit_extra .= " AND (ano_lectivo IS NULL OR ano_lectivo=0 OR ano_lectivo=" . (int)$ano . ")";
    }
    if (function_exists('sige_jardim_column_exists_v104') && sige_jardim_column_exists_v104($tbl_crit, 'trimestre')) {
        $crit_extra .= " AND (trimestre IS NULL OR trimestre=0 OR trimestre=" . (int)$trim_sel . ")";
    }
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tbl_crit WHERE escola_id=%d AND disciplina_id IN ($disc_ids) {$crit_extra} ORDER BY disciplina_id, ordem ASC", $escola_id));
    foreach ($rows as $c) $criterios[$c->disciplina_id][] = $c;
}
// Respostas de critérios (todos os trimestres)
$respostas = []; // [aluno_id][trim][criterio_id] = status
if ($alunos) {
    $al_ids = implode(',', array_map('intval', array_column($alunos,'id')));
    $tbl_resp = $wpdb->prefix.'sige_jardim_criterios_respostas';
    $resp_ok = (int)$wpdb->get_var("SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tbl_resp'");
    if ($resp_ok) {
        $resp_turma_extra = '';
        if (function_exists('sige_jardim_column_exists_v104') && sige_jardim_column_exists_v104($tbl_resp, 'turma_id')) {
            $resp_turma_extra = " AND (turma_id=" . (int)$turma_sel . " OR turma_id IS NULL OR turma_id=0)";
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $tbl_resp
             WHERE escola_id=%d
               AND aluno_id IN ($al_ids)
               AND ano_lectivo=%d
               {$resp_turma_extra}
             ORDER BY aluno_id ASC, trimestre ASC, criterio_id ASC, CASE WHEN turma_id=" . (int)$turma_sel . " THEN 1 ELSE 0 END ASC, id ASC", $escola_id, $ano));
        foreach ($rows as $r) $respostas[$r->aluno_id][$r->trimestre][$r->criterio_id] = $r->status;
    }
}
// Avaliações qualitativas (com comentários)
$avaliacoes = [];
if ($alunos) {
    $al_ids = implode(',', array_map('intval', array_column($alunos,'id')));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sige_jardim_avaliacoes
         WHERE aluno_id IN ($al_ids) AND turma_id=%d AND ano_lectivo=%d AND escola_id=%d", $turma_sel, $ano, $escola_id));
    foreach ($rows as $r) $avaliacoes[$r->aluno_id][$r->disciplina_id][$r->trimestre] = $r;
}
// Calcular nível
if (!function_exists('boletim_nivel')) {
function boletim_nivel(array $crit_disc, array $resp): array {
    $total = count($crit_disc);
    if (!$total) return ['nivel'=>'','pct'=>0];
    $pts = 0.0;
    foreach ($crit_disc as $c) {
        $s = $resp[$c->id] ?? '';
        if ($s==='atingido') $pts+=1.0;
        elseif ($s==='progresso') $pts+=0.5;
    }
    $algum = array_filter(array_column($crit_disc,'id'), fn($id)=>!empty($resp[$id]));
    if (!$algum) return ['nivel'=>'','pct'=>0];
    $pct = ($pts/$total)*100;
    return ['nivel'=>($pct>=85?'MB':($pct>=70?'B':($pct>=50?'S':'NS'))), 'pct'=>round($pct)];
}
}
$nivel_label = ['MB'=>'Muito Bom','B'=>'Bom','S'=>'Satisfatório','NS'=>'Não Satisfatório',''=>'-'];
$nivel_cor   = ['MB'=>'var(--color-success-500)','B'=>'var(--color-info-500)','S'=>'var(--color-warning-700)','NS'=>'var(--color-danger-600)',''=>'var(--color-slate-400)'];
$trimestres_label = [1=>'1º Trimestre', 2=>'2º Trimestre', 3=>'3º Trimestre'];

$jboletim_icon = static function (string $name): string {
    if (function_exists('sige_ui_icon')) {
        return sige_ui_icon($name);
    }
    $map = [
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/>',
        'printer' => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.8"/><rect x="14" y="3" width="7" height="7" rx="1.8"/><rect x="3" y="14" width="7" height="7" rx="1.8"/><rect x="14" y="14" width="7" height="7" rx="1.8"/>',
        'clipboard' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'filter' => '<path d="M22 3H2l8 9.46V19l4 2v-8.54z"/>',
        'arrow-left' => '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
    ];
    $path = $map[$name] ?? $map['book'];
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};

$jboletim_total_alunos = count($alunos);
$jboletim_total_disciplinas = count($discs);
$jboletim_total_criterios = array_sum(array_map('count', $criterios));
$jboletim_turma_nome = $turma_obj ? trim(($turma_obj->nome ?: $turma_obj->classe) . (($turma_obj->turno ?? '') ? ' · ' . $turma_obj->turno : ''), ' ·') : '';
$jboletim_total_avaliados = 0;
foreach ($alunos as $jaluno) {
    $tem_avaliacao = false;
    foreach ($discs as $jd) {
        $crit_d = $criterios[$jd->id] ?? [];
        if (!$crit_d) continue;
        $resp_trim = $respostas[$jaluno->id][$trim_sel] ?? [];
        $calc = boletim_nivel($crit_d, $resp_trim);
        $av_guard = $avaliacoes[$jaluno->id][$jd->id][$trim_sel] ?? null;
        if (!empty($av_guard->nivel) || !empty($calc['nivel'])) {
            $tem_avaliacao = true;
            break;
        }
    }
    if ($tem_avaliacao) $jboletim_total_avaliados++;
}

if (!headers_sent()) {
    header('Content-Security-Policy: ' . (function_exists('sige_csp_zero_inline_policy') ? sige_csp_zero_inline_policy() : "default-src 'self'; object-src 'none';"), true);
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Boletim Pré-Escolar - <?php echo esc_html($escola->nome_escola??''); ?> - <?php echo $trimestres_label[$trim_sel]; ?></title>
<style id="sige-jardim-boletim-produto-pro-v1210100" <?php echo function_exists('sige_csp_style_attr') ? sige_csp_style_attr() : ''; ?>>
/* SIGE SoftGenial v12.10.100 - Jardim Boletim: Compliance Visual Integral
   Escopo visual apenas: não altera cálculos, geração, dados gravados,
   permissões, notas, pagamentos ou base de dados. */
*{box-sizing:border-box;margin:0;padding:0}
body{
    font-family:'Inter','Plus Jakarta Sans','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif;
    font-size:13px;
    color:var(--color-black);
    background:var(--color-brand-50);
}
body:before{
    content:"";
    position:fixed;
    inset:0;
    background:
      radial-gradient(circle at 18% 12%,rgba(109,93,252,.10),transparent 28%),
      radial-gradient(circle at 88% 18%,rgba(11,74,143,.09),transparent 24%),
      linear-gradient(135deg,var(--color-slate-50) 0%,var(--color-brand-50) 100%);
    z-index:-1;
}

/* v12.10.101 - wide layout dentro do App Shell.
   A largura deve respeitar o contentor real do sistema, não 100vw,
   porque 100vw ignora a sidebar e causa corte lateral em laptops. */
body.sige-admin-app.sige-view-jardim_boletim .sg-product-page-head{display:none!important;}
body.sige-admin-app.sige-view-jardim_boletim .sg-app-page{
    max-width:none!important;
    width:100%!important;
    padding-top:0!important;
    overflow-x:hidden!important;
}
body.sige-admin-app.sige-view-jardim_boletim .sg-app-content{
    padding-left:30px!important;
    padding-right:30px!important;
    overflow-x:hidden!important;
}
body.sige-admin-app.sige-view-jardim_boletim .sg-app-topbar{
    max-width:none!important;
}

/* Screen shell */
.sige-jboletim-screen{
    width:100%;
    max-width:none;
    min-width:0;
    margin:0 0 18px;
    display:flex;
    flex-direction:column;
    gap:18px;
    color:var(--color-black);
}
.sige-jboletim-screen svg{width:18px;height:18px;display:block;stroke:currentColor!important;color:currentColor!important;fill:none!important}
.sige-jboletim-hero{
    position:relative;
    overflow:hidden;
    min-height:178px;
    border-radius:22px;
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-info-50) 100%);
    border:1px solid rgba(92,64,187,.12);
    box-shadow:0 12px 32px rgba(15,23,42,.12);
    padding:32px 34px;
    display:grid;
    grid-template-columns:minmax(0,1.25fr) minmax(300px,.75fr);
    gap:22px;
    align-items:center;
}
.sige-jboletim-hero:before{
    content:"";
    position:absolute;
    inset:auto -80px -130px auto;
    width:420px;
    height:300px;
    border-radius:999px;
    background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);
    pointer-events:none;
}
.sige-jboletim-hero-main,.sige-jboletim-hero-panel{position:relative;z-index:1}
.sige-jboletim-kicker{
    display:inline-flex;
    align-items:center;
    gap:8px;
    margin:0 0 10px;
    color:var(--color-info-700);
    font-size:12px;
    line-height:1.2;
    font-weight:700;
    letter-spacing:.11em;
    text-transform:uppercase;
}
.sige-jboletim-hero h1{
    margin:0;
    max-width:720px;
    color:var(--color-black);
    font-size:31px;
    line-height:1.08;
    font-weight:700;
    letter-spacing:-.04em;
}
.sige-jboletim-hero p{
    max-width:720px;
    margin:12px 0 0;
    color:var(--color-slate-700);
    font-size:15px;
    line-height:1.65;
    font-weight:500;
}
.sige-jboletim-chips{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
.sige-jboletim-chips span{
    display:inline-flex;
    align-items:center;
    gap:8px;
    min-height:38px;
    padding:8px 12px;
    border-radius:999px;
    background:var(--color-white);
    border:1px solid var(--color-slate-100);
    color:var(--color-slate-700);
    font-size:12px;
    font-weight:700;
    box-shadow:0 2px 8px rgba(15,23,42,.06);
}
.sige-jboletim-chips svg{color:var(--color-brand-500)}
.sige-jboletim-hero-panel{
    min-height:148px;
    border-radius:22px;
    background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.18));
    padding:22px;
    overflow:hidden;
    display:flex;
    flex-direction:column;
    justify-content:center;
    gap:10px;
    border:1px solid rgba(92,64,187,.08);
}
.sige-jboletim-hero-panel:before{
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
.sige-jboletim-hero-panel>*{position:relative;z-index:1}
.sige-jboletim-panel-label{
    display:flex;
    align-items:center;
    gap:8px;
    color:var(--color-brand-500);
    font-size:12px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.11em;
}
.sige-jboletim-panel-value{
    display:block;
    color:var(--color-ink-900);
    font-size:36px;
    line-height:1.05;
    font-weight:700;
    letter-spacing:-.045em;
}
.sige-jboletim-panel-text{
    display:block;
    max-width:330px;
    color:var(--color-slate-600);
    font-size:13px;
    line-height:1.55;
    font-weight:600;
}

/* KPIs */
.sige-jboletim-kpis{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:16px;
    width:100%;
    min-width:0;
}
.sige-jboletim-kpi{
    position:relative;
    overflow:hidden;
    display:grid;
    grid-template-columns:auto minmax(0,1fr);
    align-items:center;
    gap:14px;
    min-height:104px;
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    border-radius:22px;
    padding:18px 20px;
    box-shadow:0 4px 16px rgba(15,23,42,.08);
}
.sige-jboletim-kpi:after{
    content:"";
    position:absolute;
    right:-28px;
    top:-34px;
    width:92px;
    height:92px;
    border-radius:50%;
    background:var(--kpi-soft,var(--color-brand-50));
}
.sige-jboletim-kpi-icon{
    width:52px;
    height:52px;
    border-radius:16px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:var(--kpi-soft,var(--color-brand-50));
    color:var(--kpi-color,var(--color-brand-500));
    position:relative;
    z-index:1;
}
.sige-jboletim-kpi-icon svg{width:24px;height:24px}
.sige-jboletim-kpi > div{position:relative;z-index:1;min-width:0}
.sige-jboletim-kpi .num{font-size:27px;font-weight:700;line-height:1;color:var(--color-black);letter-spacing:-.03em}
.sige-jboletim-kpi .lbl{font-size:12px;color:var(--color-slate-600);margin-top:7px;font-weight:600}
.sige-jboletim-kpi.alunos{--kpi-color:var(--color-brand-500);--kpi-soft:var(--color-brand-50)}
.sige-jboletim-kpi.avaliados{--kpi-color:var(--color-success-500);--kpi-soft:var(--color-success-50)}
.sige-jboletim-kpi.disciplinas{--kpi-color:var(--color-info-700);--kpi-soft:var(--color-info-50)}
.sige-jboletim-kpi.criterios{--kpi-color:var(--color-warning-500);--kpi-soft:var(--color-warning-50)}

/* Toolbar */
.sige-jboletim-toolbar{
    display:grid;
    grid-template-columns:minmax(240px,1fr) minmax(180px,.7fr) minmax(128px,.32fr) minmax(154px,.38fr) minmax(112px,.26fr);
    gap:12px;
    width:100%;
    min-width:0;
    align-items:end;
    background:var(--color-white);
    padding:18px;
    border-radius:22px;
    box-shadow:0 4px 16px rgba(15,23,42,.08);
    border:1px solid rgba(28,32,54,.08);
}
.sige-jboletim-toolbar form{display:contents}
.sige-jboletim-field label{
    display:block;
    font-size:11px;
    font-weight:700;
    color:var(--color-slate-600);
    margin:0 0 7px;
    text-transform:uppercase;
    letter-spacing:.07em;
}
.sige-jboletim-toolbar select{
    width:100%;
    min-width:0;
    min-height:44px;
    padding:0 13px;
    border:1px solid var(--color-ink-100);
    border-radius:12px;
    font-size:13px;
    color:var(--color-ink-500);
    font-weight:600;
    background:var(--color-white);
    box-shadow:0 2px 8px rgba(15,23,42,.06);
    outline:none;
}
.sige-jboletim-toolbar select:focus{
    border-color:rgba(90,63,214,.55);
    box-shadow:0 1px 2px rgba(15,23,42,.04);
}
.sige-jboletim-btn{
    min-height:44px;
    min-width:0;
    max-width:100%;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    border-radius:12px;
    padding:0 16px;
    border:1px solid var(--color-ink-100);
    background:var(--color-white);
    color:var(--color-ink-900);
    text-decoration:none;
    font-size:13px;
    font-weight:700;
    cursor:pointer;
    font-family:inherit;
    box-shadow:0 2px 8px rgba(15,23,42,.06);
    transition:transform .18s ease,box-shadow .18s ease;
    white-space:normal;
    text-align:center;
    line-height:1.2;
}
.sige-jboletim-btn:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(15,23,42,.08)}
.sige-jboletim-btn.primary{
    background:linear-gradient(135deg,var(--color-info-700),var(--color-info-800));
    color:var(--color-white);
    border-color:var(--color-info-700);
    box-shadow:0 2px 8px rgba(15,23,42,.06);
}
.sige-jboletim-btn.primary:hover{box-shadow:0 4px 16px rgba(15,23,42,.08)}
.sige-jboletim-preview-note{
    display:flex;
    align-items:center;
    gap:9px;
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    border-radius:16px;
    padding:12px 16px;
    color:var(--color-slate-500);
    font-size:13px;
    font-weight:600;
    box-shadow:0 2px 8px rgba(15,23,42,.06);
}
.sige-jboletim-preview-note strong{color:var(--color-ink-500)}

/* Empty state */
.sige-jboletim-empty{
    width:100%;
    max-width:none;
    margin:18px 0;
    background:var(--color-white);
    border-radius:22px;
    padding:42px 24px;
    text-align:center;
    border:1px dashed var(--color-ink-200);
    box-shadow:0 4px 16px rgba(15,23,42,.08);
}
.sige-jboletim-empty h3{color:var(--color-black);font-size:20px;font-weight:700;margin:0 0 8px}
.sige-jboletim-empty p{color:var(--color-slate-500);margin:0;font-size:13px;line-height:1.55}

/* A4 preview and print content */
.boletim-page{
    width:min(210mm,100%);
    min-height:297mm;
    margin:22px auto;
    background:var(--color-white);
    padding:16mm 16mm 20mm;
    page-break-after:always;
    position:relative;
    box-shadow:0 12px 32px rgba(15,23,42,.12);
    border-radius:4px;
}
.escola-header{
    display:flex;
    align-items:center;
    gap:16px;
    border-bottom:3px solid var(--color-info-700);
    padding-bottom:12px;
    margin-bottom:14px;
}
.escola-logo{width:64px;height:64px;object-fit:contain}
.escola-nome{font-size:15px;font-weight:700;color:var(--color-info-700)}
.escola-sub{font-size:12px;color:var(--color-slate-700);line-height:1.4}
.doc-title{text-align:center;font-size:16px;font-weight:700;color:var(--color-info-700);margin:10px 0 4px;text-transform:uppercase;letter-spacing:1px}
.doc-sub{text-align:center;font-size:12px;color:var(--color-slate-700);margin-bottom:14px}
.aluno-info{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:6px 20px;
    background:var(--color-slate-50);
    border-radius:8px;
    padding:12px 14px;
    margin-bottom:14px;
    border-left:4px solid var(--color-brand-500);
}
.aluno-info .field{font-size:12px}
.aluno-info .label{font-weight:700;color:var(--color-info-700);font-size:10px;text-transform:uppercase;letter-spacing:.05em}
.aval-table{width:100%;border-collapse:collapse;margin-bottom:14px}
.aval-table th{background:var(--color-info-700);color:var(--color-white);padding:7px 10px;font-size:11px;text-align:center}
.aval-table th.disc{text-align:left}
.aval-table td{padding:7px 10px;border:1px solid var(--color-ink-100);font-size:12px;vertical-align:top}
.aval-table tr:nth-child(even) td{background:var(--color-slate-50)}
.nivel-badge{display:inline-block;padding:2px 10px;border-radius:22px;font-weight:700;font-size:12px}
.crit-section{margin-bottom:14px}
.crit-disc-title{font-weight:700;color:var(--color-info-700);font-size:12px;margin-bottom:6px;padding-bottom:3px;border-bottom:1px solid var(--color-ink-100)}
.crit-grid{display:grid;grid-template-columns:1fr 1fr;gap:3px 16px}
.crit-item{display:flex;align-items:center;gap:6px;font-size:11px;padding:2px 0}
.crit-status{font-size:12px;flex-shrink:0}
.comentario-box{background:var(--color-warning-50);border:1px solid var(--color-warning-300);border-radius:4px;padding:8px 12px;font-size:12px;color:var(--color-slate-800);margin-top:4px;font-style:italic;min-height:28px}
.assinaturas{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-top:20px}
.assinatura-linha{border-top:1px solid var(--color-slate-400);padding-top:4px;text-align:center;font-size:11px;color:var(--color-slate-700)}
.page-footer{
    position:absolute;
    bottom:12mm;
    left:16mm;
    right:16mm;
    display:flex;
    justify-content:space-between;
    font-size:10px;
    color:var(--color-slate-400);
    border-top:1px solid var(--color-slate-100);
    padding-top:6px;
}
.print-one-btn{
    position:absolute;
    top:8px;
    right:8px;
    background:linear-gradient(135deg,var(--color-info-700),var(--color-info-800))!important;
    color:var(--color-white)!important;
    border:none!important;
    padding:6px 14px!important;
    border-radius:8px!important;
    font-size:12px!important;
    cursor:pointer!important;
    display:flex!important;
    align-items:center!important;
    gap:6px!important;
    z-index:10;
    box-shadow:0 2px 8px rgba(15,23,42,.06);
}

/* Responsividade do ecrã */
@media(max-width:1450px){
    .sige-jboletim-hero{grid-template-columns:minmax(0,1fr) minmax(280px,.52fr)}
    .sige-jboletim-toolbar{
        grid-template-columns:minmax(240px,1fr) minmax(170px,.7fr) minmax(130px,.5fr) minmax(150px,.55fr) minmax(112px,.4fr);
    }
}
@media(max-width:1280px){
    .sige-jboletim-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
    .sige-jboletim-toolbar{grid-template-columns:repeat(2,minmax(0,1fr))}
    .sige-jboletim-toolbar .sige-jboletim-btn{width:100%}
}
@media(max-width:980px){
    body.sige-admin-app.sige-view-jardim_boletim .sg-app-content{padding-left:18px!important;padding-right:18px!important}
    .sige-jboletim-hero{grid-template-columns:1fr}
    .boletim-page{
        width:100%;
        min-height:auto;
        padding:28px;
        overflow:auto;
    }
}
@media(max-width:760px){
    body.sige-admin-app.sige-view-jardim_boletim .sg-app-content{padding-left:14px!important;padding-right:14px!important}
    .sige-jboletim-screen{width:100%;margin:0 0 16px}
    .sige-jboletim-hero{padding:26px 22px}
    .sige-jboletim-hero h1{font-size:24px}
    .sige-jboletim-toolbar{grid-template-columns:1fr}
    .sige-jboletim-kpis{grid-template-columns:1fr}
    .boletim-page{width:100%;padding:18px}
    .aluno-info,.crit-grid,.assinaturas{grid-template-columns:1fr}
    .page-footer{position:static;margin-top:24px}
}

/* Impressão */
@media print{
    body{background:var(--color-white)!important}
    body:before{display:none!important}
    .no-print,.sige-jboletim-screen{display:none!important}
    .boletim-page{
        margin:0!important;
        box-shadow:none!important;
        border-radius:0!important;
        page-break-after:always!important;
        width:210mm!important;
        min-height:297mm!important;
        padding:16mm 16mm 20mm!important;
        overflow:visible!important;
    }
    .boletim-page:last-child{page-break-after:auto!important}
    @page{size:A4 portrait;margin:0}
}
</style>
</head>
<body>
<!-- CONTROLO / PRÉ-VISUALIZAÇÃO (só ecrã) -->
<section class="sige-jboletim-screen no-print" aria-label="Boletim Pré-Escolar">
  <div class="sige-jboletim-hero">
    <div class="sige-jboletim-hero-main">
      <div class="sige-jboletim-kicker"><?php echo $jboletim_icon('book'); ?><span>Pré-Escolar</span></div>
      <h1>Boletim do Jardim</h1>
      <p>Pré-visualize, filtre e imprima os boletins de avaliação do pré-escolar com leitura limpa e pronta para entrega aos encarregados.</p>
      <div class="sige-jboletim-chips">
        <span><?php echo $jboletim_icon('calendar'); ?> Ano Lectivo <?php echo esc_html($ano); ?></span>
        <span><?php echo $jboletim_icon('file'); ?> <?php echo esc_html($trimestres_label[$trim_sel]); ?></span>
        <?php if ($jboletim_turma_nome): ?><span><?php echo $jboletim_icon('users'); ?> <?php echo esc_html($jboletim_turma_nome); ?></span><?php endif; ?>
      </div>
    </div>
    <aside class="sige-jboletim-hero-panel">
      <div class="sige-jboletim-panel-label"><?php echo $jboletim_icon('printer'); ?><span>Prontos para imprimir</span></div>
      <strong class="sige-jboletim-panel-value"><?php echo esc_html((string)$jboletim_total_alunos); ?></strong>
      <span class="sige-jboletim-panel-text">Boletim(ns) disponíveis para a turma e trimestre seleccionados.</span>
    </aside>
  </div>

  <div class="sige-jboletim-kpis">
    <div class="sige-jboletim-kpi alunos">
      <span class="sige-jboletim-kpi-icon"><?php echo $jboletim_icon('users'); ?></span>
      <div><div class="num"><?php echo esc_html((string)$jboletim_total_alunos); ?></div><div class="lbl">Alunos na turma</div></div>
    </div>
    <div class="sige-jboletim-kpi avaliados">
      <span class="sige-jboletim-kpi-icon"><?php echo $jboletim_icon('check'); ?></span>
      <div><div class="num"><?php echo esc_html((string)$jboletim_total_avaliados); ?></div><div class="lbl">Com avaliação</div></div>
    </div>
    <div class="sige-jboletim-kpi disciplinas">
      <span class="sige-jboletim-kpi-icon"><?php echo $jboletim_icon('grid'); ?></span>
      <div><div class="num"><?php echo esc_html((string)$jboletim_total_disciplinas); ?></div><div class="lbl">Áreas/disciplinas</div></div>
    </div>
    <div class="sige-jboletim-kpi criterios">
      <span class="sige-jboletim-kpi-icon"><?php echo $jboletim_icon('clipboard'); ?></span>
      <div><div class="num"><?php echo esc_html((string)$jboletim_total_criterios); ?></div><div class="lbl">Critérios configurados</div></div>
    </div>
  </div>

  <div class="sige-jboletim-toolbar">
    <form method="get">
      <input type="hidden" name="page" value="sige-app">
      <input type="hidden" name="view" value="jardim_boletim">
      <div class="sige-jboletim-field">
        <label>Turma</label>
        <select name="turma_id">
          <?php foreach ($turmas as $t): ?>
            <option value="<?php echo $t->id; ?>" <?php selected($t->id,$turma_sel); ?>><?php echo esc_html($t->nome?:$t->classe); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="sige-jboletim-field">
        <label>Trimestre</label>
        <select name="trimestre">
          <?php foreach($trimestres_label as $v=>$l): ?>
            <option value="<?php echo esc_attr($v); ?>" <?php selected($v,$trim_sel); ?>><?php echo $l; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="sige-jboletim-btn primary" type="submit"><?php echo $jboletim_icon('filter'); ?> Carregar</button>
    </form>
    <button class="sige-jboletim-btn primary" type="button" data-sige-print-all><?php echo $jboletim_icon('printer'); ?> Imprimir Todos</button>
    <a class="sige-jboletim-btn" href="?page=sige-app&view=jardim_diario&turma_id=<?php echo $turma_sel; ?>&tab=avaliacao"><?php echo $jboletim_icon('arrow-left'); ?> Voltar</a>
  </div>

  <div class="sige-jboletim-preview-note">
    <?php echo $jboletim_icon('file'); ?>
    <span><strong>Pré-visualização A4:</strong> o conteúdo abaixo mantém o formato de impressão; os controlos desaparecem automaticamente ao imprimir.</span>
  </div>
</section>
<?php if (empty($alunos)): ?>
  <div class="sige-jboletim-empty no-print"><h3>Nenhum aluno encontrado</h3><p>Seleccione outra turma ou confirme se existem alunos activos matriculados nesta turma do pré-escolar.</p></div>
<?php else: ?>
<?php foreach ($alunos as $idx => $aluno):
  $foto  = $aluno->foto ?: '';
  $idade = $aluno->data_nascimento ? (int)((time()-strtotime($aluno->data_nascimento))/31557600) : '';
  $enc   = $aluno->nome_pai ?: $aluno->nome_mae ?: '-';
?>
<div class="boletim-page">
  <!-- Botão imprimir individual (só ecrã) -->
  <button class="no-print print-one-btn" data-sige-print-one="<?php echo (int)$idx; ?>" type="button"><?php echo $jboletim_icon('printer'); ?> Imprimir</button>
  <!-- Cabeçalho da escola -->
  <div class="escola-header">
    <?php if ($logo): ?>
      <img src="<?php echo esc_url($logo); ?>" class="escola-logo" alt="Logo">
    <?php endif; ?>
    <div>
      <div class="escola-nome"><?php echo esc_html($escola->nome_escola??''); ?></div>
      <div class="escola-sub"><?php echo esc_html($escola->endereco_escola??''); ?>
        <?php if ($escola->telefone_oficial): ?> | Tel: <?php echo esc_html($escola->telefone_oficial); ?><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="doc-title">Boletim de Avaliação - Pré-Escolar</div>
  <div class="doc-sub"><?php echo $trimestres_label[$trim_sel]; ?> | Ano Lectivo <?php echo $ano; ?></div>
  <!-- Dados do aluno -->
  <div class="aluno-info">
    <div>
      <div class="label">Nome Completo</div>
      <div class="field" style="font-weight:700;font-size:13px;"><?php echo esc_html($aluno->nome_completo); ?></div>
    </div>
    <div>
      <div class="label">Turma</div>
      <div class="field"><?php echo esc_html($turma_obj ? ($turma_obj->nome ?: $turma_obj->classe) : '-'); ?> <?php echo ($turma_obj && $turma_obj->turno)?'('.$turma_obj->turno.')':''; ?></div>
    </div>
    <div>
      <div class="label">Data de Nascimento</div>
      <div class="field"><?php echo $aluno->data_nascimento ? wp_date('d/m/Y',strtotime($aluno->data_nascimento)).' ('.$idade.' anos)' : '-'; ?></div>
    </div>
    <div>
      <div class="label">Encarregado de Educação</div>
      <div class="field"><?php echo esc_html($enc); ?>
        <?php if ($aluno->contacto_encarregado): ?> | <?php echo esc_html($aluno->contacto_encarregado); ?><?php endif; ?>
      </div>
    </div>
  </div>
  <!-- Tabela resumo de avaliações -->
  <table class="aval-table">
    <thead>
      <tr>
        <th class="disc" style="width:38%;">Área de Desenvolvimento</th>
        <th style="width:16%;">Avaliação</th>
        <th style="width:46%;">Comentário do Professor</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($discs as $d):
      $crit_d   = $criterios[$d->id] ?? [];
      $resp_trim = $respostas[$aluno->id][$trim_sel] ?? [];
      $calc     = boletim_nivel($crit_d, $resp_trim);
      // Nível: calculado automaticamente, mas pode ter override guardado
      $av_guard = $avaliacoes[$aluno->id][$d->id][$trim_sel] ?? null;
      $nivel    = $av_guard->nivel ?? $calc['nivel'];
      $coment   = $av_guard->comentario_trimestre ?? '';
      $cor      = $nivel_cor[$nivel] ?? 'var(--color-slate-400)';
    ?>
      <tr>
        <td style="font-weight:600;"><?php echo esc_html($d->nome); ?></td>
        <td style="text-align:center;">
          <span class="nivel-badge" style="background:<?php echo $cor; ?>20;color:<?php echo $cor; ?>;border:1px solid <?php echo $cor; ?>;">
            <?php echo $nivel ?: '-'; ?>
          </span><br>
          <span style="font-size:10px;color:var(--color-slate-700);"><?php echo esc_html($nivel_label[$nivel]??'Por avaliar'); ?></span>
        </td>
        <td style="font-size:11px;font-style:<?php echo $coment?'normal':'italic'; ?>;color:<?php echo $coment?'var(--color-slate-800)':'var(--color-slate-400)'; ?>;">
          <?php echo $coment ? esc_html($coment) : 'Sem comentário registado.'; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <!-- Detalhe de critérios por disciplina -->
  <div style="font-weight:700;font-size:12px;color:var(--color-info-800);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px;">
    Detalhe de Critérios Avaliados
  </div>
  <?php foreach ($discs as $d):
    $crit_d    = $criterios[$d->id] ?? [];
    if (!$crit_d) continue;
    $resp_trim = $respostas[$aluno->id][$trim_sel] ?? [];
  ?>
  <div class="crit-section">
    <div class="crit-disc-title"><?php echo esc_html($d->nome); ?> (<?php echo esc_html($d->sigla); ?>)</div>
    <div class="crit-grid">
    <?php foreach ($crit_d as $c):
      $s = $resp_trim[$c->id] ?? '';
      $icon = $s==='atingido' ? '✅' : ($s==='progresso' ? '🔄' : ($s==='nao_atingido' ? '❌' : '○'));
      $cor_t = $s==='atingido' ? 'var(--color-success-500)' : ($s==='progresso' ? 'var(--color-warning-700)' : ($s==='nao_atingido' ? 'var(--color-danger-600)' : 'var(--color-slate-400)'));
    ?>
      <div class="crit-item">
        <span class="crit-status"><?php echo $icon; ?></span>
        <span style="color:<?php echo $cor_t; ?>;"><?php echo esc_html($c->descricao); ?></span>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <!-- Legenda -->
  <div style="display:flex;gap:16px;margin-top:10px;margin-bottom:16px;background:var(--color-slate-50);padding:8px 12px;border-radius:6px;">
    <span style="font-size:10px;font-weight:700;color:var(--color-slate-800);">Legenda:</span>
    <?php foreach(['MB'=>'Muito Bom','B'=>'Bom','S'=>'Satisfatório','NS'=>'Não Satisfatório'] as $nv=>$nl): ?>
      <span style="font-size:10px;color:<?php echo $nivel_cor[$nv]; ?>;font-weight:700;"><?php echo $nv; ?> = <?php echo $nl; ?></span>
    <?php endforeach; ?>
    <span style="font-size:10px;color:var(--color-slate-700);margin-left:auto;">✅ Atingido &nbsp; 🔄 Em progresso &nbsp; ❌ Não atingido</span>
  </div>
  <!-- Assinaturas -->
  <div class="assinaturas">
    <div>
      <div class="assinatura-linha">Educador/a de Infância<br><br><br></div>
    </div>
    <div>
      <div class="assinatura-linha">Director/a Pedagógico/a<br><br><br></div>
    </div>
    <div>
      <div class="assinatura-linha">Encarregado de Educação<br><br><br></div>
    </div>
  </div>
  <!-- Rodapé -->
  <div class="page-footer">
    <span><?php echo esc_html($escola->nome_escola??''); ?> </span>
    <span>Emitido em <?php echo wp_date('d/m/Y H:i'); ?></span>
    <span>Aluno <?php echo $idx+1; ?> de <?php echo count($alunos); ?></span>
  </div>
</div><!-- boletim-page -->
<?php endforeach; // alunos ?>
<?php endif; ?>
<script <?php echo sige_csp_script_attr(); ?>>
var SIGE_CSP_NONCE = <?php echo wp_json_encode(function_exists('sige_csp_nonce') ? sige_csp_nonce() : ''); ?>;
function sigePrintStyleOpen() { return SIGE_CSP_NONCE ? '<style nonce="' + SIGE_CSP_NONCE + '">' : '<style>'; }
function sigeImprimirBoletins(alunoIdx) {
    var pages = document.querySelectorAll('.boletim-page');
    if (!pages.length) { sigeUi.toast('Não há boletins para imprimir com a selecção actual.', 'aviso'); return; }
    // Se alunoIdx definido, imprimir só esse; senão todos
    var html = '';
    if (typeof alunoIdx === 'number') {
        if (pages[alunoIdx]) html = pages[alunoIdx].outerHTML;
    } else {
        for (var i = 0; i < pages.length; i++) html += pages[i].outerHTML;
    }
    // Extrair CSS da página actual
    var styles = '';
    var styleEls = document.querySelectorAll('style');
    for (var i = 0; i < styleEls.length; i++) styles += sigePrintStyleOpen() + styleEls[i].innerHTML + '</style>';
    var w = window.open('', '_blank', 'width=900,height=700');
    w.document.write('<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8">');
    w.document.write('<title>Boletim Pré-Escolar</title>');
    w.document.write(styles);
    w.document.write(sigePrintStyleOpen());
    w.document.write('body { background: white !important; margin: 0; padding: 0; }');
    w.document.write('.no-print { display: none !important; }');
    w.document.write('.boletim-page { margin: 0; box-shadow: none; page-break-after: always; width: 210mm; }');
    w.document.write('.boletim-page:last-child { page-break-after: auto; }');
    w.document.write('@page { size: A4 portrait; margin: 0; }');
    w.document.write('</style>');
    w.document.write('</head><body>');
    w.document.write(html);
    w.document.write('</body></html>');
    w.document.close();
    // Esperar renderização antes de imprimir
    w.onload = function() { w.focus(); w.print(); };
    // Fallback para navegadores que não disparam onload
    setTimeout(function() { w.focus(); w.print(); }, 500);
}
document.querySelectorAll('[data-sige-print-all]').forEach(function(el){ el.addEventListener('click', function(){ sigeImprimirBoletins(); }); });
document.querySelectorAll('[data-sige-print-one]').forEach(function(el){ el.addEventListener('click', function(){ sigeImprimirBoletins(parseInt(el.getAttribute('data-sige-print-one'), 10)); }); });
</script>
</body>
</html>
