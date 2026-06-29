<?php
if (!defined('ABSPATH')) exit;

/**
 * SIGE SoftGenial - Gestão de Disciplinas
 * 
 * v2.1 - Abril 2026
 * - Paleta alinhada com Design System sg-* (Navy/Amber)
 * - Removido date_default_timezone_set() - usa wp_date()
 * - N+1 fix: $escola_id consistente
 * - XSS fix: criterio text escapado no JS
 */

// ========================================
// VERIFICAÇÃO DE SEGURANÇA
// ========================================
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard_allows(
    ['academico.disciplinas_ver','academico.disciplinas_gerir'],
    ['sige_director','sige_secretario','sige_pedagogico','sige_admin_ti']
)) {
    ?>
    <div class="sige-access-denied">
        <div class="sige-access-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <h2>Acesso Restrito</h2>
        <p>O seu perfil SIGE não tem permissão para aceder a este módulo.</p>
    </div>
    <style>
    .sige-access-denied{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:400px;text-align:center;padding:48px;background:var(--color-slate-50);border-radius:var(--radius-xl);border:1px solid var(--color-ink-100);font-family:'Inter',system-ui,sans-serif;}
    .sige-access-icon{width:80px;height:80px;border-radius:50%;background:var(--color-danger-100);display:flex;align-items:center;justify-content:center;margin-bottom:24px;}
    .sige-access-icon svg{width:40px;height:40px;stroke:var(--color-danger-500);}
    .sige-access-denied h2{font-size:1.5rem;font-weight:700;color:var(--color-black);margin:0 0 var(--space-2);}
    .sige-access-denied p{color:var(--color-slate-500);margin:0;}
    

</style>
    <?php
    return;
}

global $wpdb;
$tabela = $wpdb->prefix . 'sige_disciplinas';
$escola_id = sige_require_escola_id('disciplinas');

// ========================================
// HELPERS
// ========================================
if (!function_exists('sige_carga_display')) {
function sige_carga_display($minutos) {
    $m = max(0, (int)$minutos);
    $h = (int)floor($m / 60);
    $min = $m % 60;
    if ($h > 0 && $min > 0) return $h . 'h ' . str_pad($min, 2, '0', STR_PAD_LEFT) . 'm';
    if ($h > 0) return $h . 'h';
    return $min . 'm';
}
}

// ========================================
// PROCESSAR AÇÕES (com nonce + permissões + escola_id)
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_disciplina'])) {
    // [AUTH-09] Capability check inline (defesa em profundidade)
    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director') && !current_user_can('sige_secretario') && !current_user_can('sige_pedagogico') && !current_user_can('sige_admin_ti')) {
        wp_die('Sem permissao para gerir disciplinas.');
    }
    // Verificar nonce
    if (!isset($_POST['_sige_nonce']) || !wp_verify_nonce($_POST['_sige_nonce'], 'sige_disciplina_action')) {
        wp_die('Sessão expirada. Recarregue a página.');
    }
    $nome = sanitize_text_field($_POST['nome']);
    $sigla = strtoupper(sanitize_text_field($_POST['sigla']));
    $carga_h   = max(0, intval($_POST['carga_horas'] ?? 0));
    $carga_min = max(0, min(59, intval($_POST['carga_minutos'] ?? 0)));
    $carga = ($carga_h * 60) + $carga_min;
    $chefe = !empty($_POST['chefe_grupo_id']) ? intval($_POST['chefe_grupo_id']) : NULL;
    $area = sanitize_text_field($_POST['area_esg2']);
    $categoria = sanitize_text_field($_POST['categoria']);
    $ordem = intval($_POST['ordem']);
    $ciclos = isset($_POST['ciclos']) ? json_encode($_POST['ciclos']) : '[]';
    
    $dados = [
        'nome' => $nome,
        'sigla' => $sigla,
        'carga_horaria' => $carga,
        'chefe_grupo_id' => $chefe,
        'area_esg2' => $area,
        'categoria' => $categoria,
        'ordem' => $ordem,
        'ciclos' => $ciclos
    ];
    
    if (!empty($_POST['id_disciplina'])) {
        $wpdb->update($tabela, $dados, ['id' => intval($_POST['id_disciplina']), 'escola_id' => $escola_id]);
        if (function_exists('sige_audit_log')) sige_audit_log('editar_disciplina', ['id' => intval($_POST['id_disciplina']), 'nome' => $nome], 'notas');
    } else {
        $dados['escola_id'] = $escola_id;
        $wpdb->insert($tabela, $dados);
        if (function_exists('sige_audit_log')) sige_audit_log('criar_disciplina', ['nome' => $nome, 'sigla' => $sigla], 'notas');
    }
    
    echo "<script " . sige_csp_script_attr() . ">location.reload();</script>";
}

if (isset($_GET['del'])) {
    if (!isset($_GET['_nonce']) || !wp_verify_nonce($_GET['_nonce'], 'sige_del_disciplina')) {
        wp_die('Link expirado. Volte à página e tente novamente.');
    }
    $del_id = intval($_GET['del']);
    $wpdb->delete($tabela, ['id' => $del_id, 'escola_id' => $escola_id]);
    if (function_exists('sige_audit_log')) sige_audit_log('eliminar_disciplina', ['id' => $del_id], 'notas');
    echo "<script " . sige_csp_script_attr() . ">location.href='?page=sige-app&view=disciplinas';</script>";
}

// ========================================
// CARREGAR DADOS
// ========================================
$disciplinas = $wpdb->get_results($wpdb->prepare("
    SELECT d.*, p.nome_completo as chefe_nome 
    FROM $tabela d 
    LEFT JOIN {$wpdb->prefix}sige_professores p ON d.chefe_grupo_id = p.id 
    WHERE d.escola_id = %d
    ORDER BY d.ordem ASC
", $escola_id));

$professores = $wpdb->get_results($wpdb->prepare("
    SELECT id, nome_completo 
    FROM {$wpdb->prefix}sige_professores 
    WHERE escola_id = %d AND status_ativo = 1 
    ORDER BY nome_completo ASC
", $escola_id));

// ========================================
// CARREGAR CARGAS DA MATRIZ CURRICULAR
// ========================================
$ciclo_grupos = [
    'Pré'  => ['2º/3º Ano', '4º Ano', 'Pré-primário'],
    'EP1'  => ['1ª', '2ª', '3ª'],
    'EP2'  => ['4ª', '5ª', '6ª'],
    'ESG1' => ['7ª', '8ª', '9ª'],
    'ESG2' => ['10ª', '11ª A', '11ª B', '11ª C', '12ª A', '12ª B', '12ª C'],
];

$tMatriz = $wpdb->prefix . 'sige_matriz_curricular';
$rows_matriz = $wpdb->get_results($wpdb->prepare("SELECT disciplina_id, classe, carga_horaria FROM $tMatriz WHERE escola_id = %d ORDER BY disciplina_id", $escola_id));
$mapa_cargas = [];
foreach ((array)$rows_matriz as $row) {
    $mapa_cargas[(int)$row->disciplina_id][$row->classe] = (int)$row->carga_horaria;
}

if (!function_exists('sige_carga_por_ciclo')) {
function sige_carga_por_ciclo($disc_id, $mapa, $grupos) {
    $result = [];
    if (!isset($mapa[$disc_id])) return $result;
    foreach ($grupos as $label => $classes) {
        $vals = [];
        foreach ($classes as $cls) {
            if (isset($mapa[$disc_id][$cls]) && $mapa[$disc_id][$cls] > 0)
                $vals[] = $mapa[$disc_id][$cls];
        }
        if (empty($vals)) continue;
        $unique = array_unique($vals); sort($unique);
        $result[$label] = $unique;
    }
    return $result;
}
}

// ========================================
// CALCULAR ESTATÍSTICAS
// ========================================
$total_disciplinas = count($disciplinas);
$total_com_matriz  = count(array_filter($disciplinas, function($d) use ($mapa_cargas) { return isset($mapa_cargas[(int)$d->id]); }));
$carga_total = 0;
$count_pre = 0;
$count_academicas = 0;
$count_nucleares = 0;
$count_complementares = 0;

foreach($disciplinas as $item) {
    $carga_total += $item->carga_horaria;
    $ciclos = json_decode($item->ciclos);
    
    if(is_array($ciclos) && in_array('PreEscolar', $ciclos)) {
        $count_pre++;
    } else {
        $count_academicas++;
    }
    
    if($item->categoria === 'nuclear') {
        $count_nucleares++;
    } elseif($item->categoria === 'complementar') {
        $count_complementares++;
    }
}
?>

<style>
/* ========================================
   SIGE DISCIPLINAS - Design System v2.1
   Aligned with sg-* (Navy/Amber)
   ======================================== */

/* Google Fonts already loaded by style.css */

:root {
    --sige-font-display: 'Plus Jakarta Sans', system-ui, sans-serif;
    --sige-font-body: 'Inter', system-ui, sans-serif;
    
    --sige-navy: var(--color-info-900);
    --sige-navy-light: var(--sg-theme-primary-800,var(--color-ink-700));
    --sige-primary: var(--sg-theme-primary,var(--color-brand-500));
    --sige-primary-light: var(--color-ink-600);
    --sige-primary-dark: var(--color-info-700);
    
    --sige-slate-50: var(--color-slate-50);
    --sige-slate-100: var(--color-ink-50);
    --sige-slate-200: var(--color-ink-100);
    --sige-slate-300: var(--color-ink-200);
    --sige-slate-400: var(--color-slate-400);
    --sige-slate-500: var(--color-slate-500);
    --sige-slate-600: var(--color-slate-700);
    --sige-slate-700: var(--color-slate-800);
    --sige-slate-800: var(--color-ink-900);
    --sige-slate-900: var(--color-black);
    
    --sige-success: var(--color-success-700);
    --sige-success-light: var(--color-success-100);
    --sige-warning: var(--color-warning-500);
    --sige-warning-light: var(--color-warning-100);
    --sige-error: var(--color-danger-500);
    --sige-error-light: var(--color-danger-50);
    --sige-info: var(--sg-theme-primary,var(--color-brand-500));
    --sige-info-light: var(--sg-theme-soft,var(--color-brand-50));
    
    --sige-rose: var(--color-danger-500);
    --sige-rose-light: var(--color-danger-100);
    --sige-teal: var(--color-warning-500);
    --sige-amber: var(--color-warning-500);
    
    --sige-shadow-sm: 0 1px 2px rgba(13,18,89,0.04);
    --sige-shadow: 0 4px 12px rgba(13,18,89,0.08);
    --sige-shadow-lg: 0 12px 32px rgba(13,18,89,0.10);
    --sige-shadow-xl: 0 20px 48px rgba(13,18,89,0.14);
    
    --sige-radius: 12px;
    --sige-radius-lg: 16px;
    --sige-radius-xl: 20px;
    --sige-radius-2xl: 24px;
}

/* Reset */
.sige-disciplinas {
    font-family: var(--sige-font-body);
    color: var(--sige-slate-800);
    display: flex;
    flex-direction: column;
    gap:var(--space-6);
    padding: 0;
    max-width: none;
    margin: 0;
}

.sige-disciplinas * {
    box-sizing: border-box;
}

/* Animations */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes modalSlideIn {
    from { opacity: 0; transform: translateY(-14px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.sige-disciplinas > * {
    animation: fadeInUp 0.5s ease-out both;
}

.sige-disciplinas > *:nth-child(1) { animation-delay: 0s; }
.sige-disciplinas > *:nth-child(2) { animation-delay: 0.05s; }
.sige-disciplinas > *:nth-child(3) { animation-delay: 0.1s; }

/* ========================================
   HERO SECTION
   ======================================== */
.sige-hero {
    position: relative;
    overflow: hidden;
    border-radius: var(--sige-radius-2xl);
    padding:var(--space-8);
    background: linear-gradient(135deg, var(--sige-navy) 0%, var(--sige-navy-light) 50%, var(--sige-primary-dark) 100%);
    color: var(--color-white);
    box-shadow:var(--shadow-xs);
}

.sige-hero::before,
.sige-hero::after {
    content: "";
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
}

.sige-hero::before {
    width: 350px;
    height: 350px;
    right: -120px;
    top: -120px;
    background: radial-gradient(circle, rgba(63, 81, 181, 0.25) 0%, transparent 70%);
}

.sige-hero::after {
    width: 250px;
    height: 250px;
    left: -80px;
    bottom: -100px;
    background: radial-gradient(circle, rgba(129, 140, 248, 0.2) 0%, transparent 70%);
}

.sige-hero-grid {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap:var(--space-6);
}

.sige-hero-kicker {
    display: inline-flex;
    align-items: center;
    gap:var(--space-2);
    padding: 8px 14px;
    border-radius:var(--radius-pill);
    background: rgba(255,255,255,0.12);
    font-size: 0.7rem;
    font-weight:600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 12px;
}

.sige-hero-kicker svg {
    width: 16px;
    height: 16px;
}

.sige-hero h1 {
    font-family: var(--sige-font-display);
    font-size: clamp(1.75rem, 3vw, 2.25rem);
    font-weight:700;
    line-height: 1.1;
    letter-spacing: -0.02em;
    margin:0 0 var(--space-3);
    color: var(--color-white);
}

.sige-hero-subtitle {
    font-size: 0.95rem;
    color: rgba(255,255,255,0.8);
    max-width: 600px;
    line-height: 1.5;
    margin: 0;
}

.sige-hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: flex-end;
    align-items: flex-start;
}

.sige-chip {
    display: inline-flex;
    align-items: center;
    gap:var(--space-2);
    padding: 10px 16px;
    border-radius:var(--radius-pill);
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.12);
    font-size: 0.8rem;
    font-weight:600;
    color: var(--color-white);
    white-space: nowrap;
}

.sige-chip svg {
    width: 16px;
    height: 16px;
    opacity: 0.9;
}

.sige-btn {
    display: inline-flex;
    align-items: center;
    gap:var(--space-2);
    padding:var(--space-3) var(--space-5);
    border-radius: var(--sige-radius);
    font-family: var(--sige-font-body);
    font-size: 0.875rem;
    font-weight:600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all var(--duration-normal) ease;
}

.sige-btn svg {
    width: 18px;
    height: 18px;
}

.sige-btn-white {
    background: var(--color-white);
    color: var(--sige-navy);
    box-shadow:var(--shadow-xs);
}

.sige-btn-white:hover {
    transform: translateY(-2px);
    box-shadow:var(--shadow-sm);
}

/* ========================================
   STATS GRID
   ======================================== */
.sige-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap:var(--space-4);
}

.sige-stat-card {
    position: relative;
    padding:var(--space-5);
    border-radius: var(--sige-radius-xl);
    background: var(--color-white);
    border: 1px solid var(--sige-slate-100);
    box-shadow:var(--shadow-xs);
    overflow: hidden;
    transition: all var(--duration-normal) ease;
}

.sige-stat-card:hover {
    transform: translateY(-2px);
    box-shadow:var(--shadow-xs);
}

.sige-stat-card::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    border-radius: var(--sige-radius-xl) 0 0 var(--sige-radius-xl);
}

.sige-stat-card.stat-primary::before { background: linear-gradient(180deg, var(--sige-primary), var(--sige-primary-light)); }
.sige-stat-card.stat-success::before { background: linear-gradient(180deg, var(--sige-success), var(--color-success-400)); }
.sige-stat-card.stat-rose::before { background: linear-gradient(180deg, var(--sige-rose), var(--color-danger-300)); }
.sige-stat-card.stat-amber::before { background: linear-gradient(180deg, var(--sige-amber), var(--color-warning-400)); }

.sige-stat-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--sige-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
}

.sige-stat-card.stat-primary .sige-stat-icon { background: rgba(63, 81, 181, 0.1); }
.sige-stat-card.stat-success .sige-stat-icon { background: rgba(16, 185, 129, 0.1); }
.sige-stat-card.stat-rose .sige-stat-icon { background: rgba(244, 63, 94, 0.1); }
.sige-stat-card.stat-amber .sige-stat-icon { background: rgba(245, 158, 11, 0.1); }

.sige-stat-icon svg {
    width: 20px;
    height: 20px;
}

.sige-stat-card.stat-primary .sige-stat-icon svg { stroke: var(--sige-primary); }
.sige-stat-card.stat-success .sige-stat-icon svg { stroke: var(--sige-success); }
.sige-stat-card.stat-rose .sige-stat-icon svg { stroke: var(--sige-rose); }
.sige-stat-card.stat-amber .sige-stat-icon svg { stroke: var(--sige-amber); }

.sige-stat-label {
    font-size: 0.7rem;
    font-weight:600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--sige-slate-500);
    margin-bottom: 4px;
}

.sige-stat-value {
    font-family: var(--sige-font-display);
    font-size: 1.75rem;
    font-weight:700;
    color: var(--sige-slate-900);
    line-height: 1.1;
}

.sige-stat-note {
    font-size: 0.8rem;
    color: var(--sige-slate-500);
    margin-top: 4px;
}

/* ========================================
   TABLE CARD
   ======================================== */
.sige-table-card {
    background: var(--color-white);
    border-radius: var(--sige-radius-2xl);
    border: 1px solid var(--sige-slate-100);
    box-shadow:var(--shadow-xs);
    overflow: hidden;
}

.sige-table-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap:var(--space-5);
    padding:var(--space-6);
    background: linear-gradient(180deg, var(--sige-slate-50), var(--color-white));
    border-bottom: 1px solid var(--sige-slate-100);
}

.sige-table-header h3 {
    font-family: var(--sige-font-display);
    font-size: 1.125rem;
    font-weight:700;
    color: var(--sige-slate-900);
    margin: 0;
}

.sige-table-header p {
    font-size: 0.8rem;
    color: var(--sige-slate-500);
    margin:var(--space-1) 0 0;
}

.sige-table-meta {
    display: flex;
    gap:var(--space-2);
    flex-wrap: wrap;
    justify-content: flex-end;
}

.sige-mini-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding:var(--space-2) var(--space-3);
    border-radius:var(--radius-pill);
    background: rgba(63, 81, 181, 0.08);
    border: 1px solid rgba(63, 81, 181, 0.15);
    font-size: 0.75rem;
    font-weight:600;
    color: var(--sige-primary-dark);
}

.sige-mini-chip svg {
    width: 14px;
    height: 14px;
}

/* Table */
.sige-table-wrapper {
    overflow-x: auto;
}

.sige-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
    min-width: 900px;
}

.sige-table thead th {
    padding: 14px 16px;
    text-align: left;
    font-size: 0.7rem;
    font-weight:600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--sige-slate-500);
    background: var(--sige-slate-50);
    border-bottom: 1px solid var(--sige-slate-200);
}

.sige-table tbody tr {
    border-bottom: 1px solid var(--sige-slate-100);
    transition: background 0.15s ease;
}

.sige-table tbody tr:hover {
    background: rgba(63, 81, 181, 0.02);
}

.sige-table tbody td {
    padding:var(--space-4);
    vertical-align: middle;
}

/* Ordem badge */
.sige-ordem-badge {
    width: 36px;
    height: 36px;
    border-radius:var(--radius-pill);
    background: linear-gradient(135deg, var(--sige-slate-100), var(--sige-slate-50));
    border: 1px solid var(--sige-slate-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight:700;
    font-size: 0.8rem;
    color: var(--sige-slate-600);
}

/* Sigla badge */
.sige-sigla-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: var(--sige-radius);
    background: linear-gradient(135deg, var(--sige-navy), var(--sige-navy-light));
    color: var(--color-white);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.7rem;
    font-weight:600;
    letter-spacing: 0.08em;
    box-shadow:var(--shadow-xs);
}

/* Disciplina info */
.sige-disc-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.sige-disc-name {
    font-weight:600;
    color: var(--sige-slate-900);
    font-size: 0.9rem;
}

.sige-disc-type {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    color: var(--sige-slate-500);
}

.sige-disc-type svg {
    width: 14px;
    height: 14px;
}

/* Carga tags */
.sige-carga-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.sige-carga-tag {
    display: inline-flex;
    align-items: center;
    gap:var(--space-1);
    padding: 4px 10px;
    border-radius:var(--radius-pill);
    background: rgba(63, 81, 181, 0.08);
    border: 1px solid rgba(63, 81, 181, 0.15);
    font-size: 0.7rem;
    font-weight:600;
    color: var(--sige-primary-dark);
}

.sige-carga-tag strong {
    font-weight:700;
}

.sige-no-matriz {
    color: var(--sige-slate-400);
    font-size: 0.75rem;
    font-style: italic;
}

/* Ciclo tags */
.sige-ciclo-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.sige-ciclo-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius:var(--radius-pill);
    font-size: 0.7rem;
    font-weight:600;
    border: 1px solid transparent;
}

.sige-ciclo-tag svg {
    width: 14px;
    height: 14px;
}

.tag-pre { background: var(--color-danger-50); color: var(--color-danger-600); border-color: var(--color-danger-100); }
.tag-pre svg { stroke: var(--color-danger-600); }
.tag-ep { background: var(--color-success-50); color: var(--color-success-800); border-color: var(--color-success-200); }
.tag-ep svg { stroke: var(--color-success-800); }
.tag-esg1 { background: var(--color-warning-50); color: var(--color-danger-600); border-color: var(--color-warning-200); }
.tag-esg1 svg { stroke: var(--color-danger-600); }
.tag-esg2 { background: var(--sg-theme-soft,var(--color-brand-50)); color: var(--color-info-600); border-color: var(--sg-theme-soft,var(--color-brand-50)); }
.tag-esg2 svg { stroke: var(--color-info-600); }

/* Actions */
.sige-table-actions {
    display: flex;
    gap:var(--space-2);
    justify-content: flex-end;
}

.sige-action-btn {
    width: 36px;
    height: 36px;
    border-radius: var(--sige-radius);
    border: 1px solid var(--sige-slate-200);
    background: var(--color-white);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--duration-normal) ease;
    position: relative;
}

.sige-action-btn svg {
    width: 16px;
    height: 16px;
}

.sige-action-btn[data-tooltip]::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 6px 10px;
    background: var(--sige-navy);
    color: var(--color-white);
    font-size: 0.7rem;
    font-weight:600;
    white-space: nowrap;
    border-radius:var(--radius-xs);
    opacity: 0;
    visibility: hidden;
    transition: all var(--duration-normal) ease;
    margin-bottom: 6px;
}

.sige-action-btn:hover[data-tooltip]::after {
    opacity: 1;
    visibility: visible;
}

.sige-action-btn:hover {
    transform: translateY(-2px);
    box-shadow:var(--shadow-xs);
}

.sige-action-btn.btn-criteria {
    background: var(--sige-rose-light);
    border-color: var(--color-danger-200);
}
.sige-action-btn.btn-criteria svg { stroke: var(--sige-rose); }
.sige-action-btn.btn-criteria:hover { background: var(--sige-rose); border-color: var(--sige-rose); }
.sige-action-btn.btn-criteria:hover svg { stroke: var(--color-white); }

.sige-action-btn.btn-edit {
    background: var(--sige-info-light);
    border-color: var(--sg-theme-soft,var(--color-brand-50));
}
.sige-action-btn.btn-edit svg { stroke: var(--sige-info); }
.sige-action-btn.btn-edit:hover { background: var(--sige-info); border-color: var(--sige-info); }
.sige-action-btn.btn-edit:hover svg { stroke: var(--color-white); }

.sige-action-btn.btn-delete {
    background: var(--sige-error-light);
    border-color: var(--color-danger-200);
}
.sige-action-btn.btn-delete svg { stroke: var(--sige-error); }
.sige-action-btn.btn-delete:hover { background: var(--sige-error); border-color: var(--sige-error); }
.sige-action-btn.btn-delete:hover svg { stroke: var(--color-white); }

/* Empty state */
.sige-empty-state {
    text-align: center;
    padding: 80px 24px;
}

.sige-empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--sige-slate-100);
    display: flex;
    align-items: center;
    justify-content: center;
    margin:0 auto var(--space-5);
}

.sige-empty-icon svg {
    width: 36px;
    height: 36px;
    stroke: var(--sige-slate-400);
}

.sige-empty-state h3 {
    font-family: var(--sige-font-display);
    font-size: 1.125rem;
    font-weight:700;
    color: var(--sige-slate-900);
    margin:0 0 var(--space-2);
}

.sige-empty-state p {
    color: var(--sige-slate-500);
    font-size: 0.875rem;
    margin: 0;
}

/* ========================================
   MODAL
   ======================================== */
.sige-modal {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(8px);
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
    padding:var(--space-5);
}

.sige-modal-content {
    width: 100%;
    max-width: 680px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: var(--sige-radius-2xl);
    background: var(--color-white);
    box-shadow:var(--shadow-xs);
    animation: modalSlideIn 0.25s ease;
}

.sige-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap:var(--space-4);
    padding:var(--space-5) var(--space-6);
    background: linear-gradient(135deg, var(--sige-navy), var(--sige-navy-light));
    color: var(--color-white);
}

.sige-modal-header.header-rose {
    background: linear-gradient(135deg, var(--color-danger-600), var(--color-danger-500));
}

.sige-modal-header h3 {
    font-family: var(--sige-font-display);
    font-size: 1.125rem;
    font-weight:600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--color-white);
}

.sige-modal-header h3 svg {
    width: 22px;
    height: 22px;
}

.sige-modal-close {
    width: 36px;
    height: 36px;
    border-radius:var(--radius-pill);
    border: none;
    background: rgba(255,255,255,0.15);
    color: var(--color-white);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--duration-normal) ease;
}

.sige-modal-close:hover {
    background: rgba(255,255,255,0.25);
    transform: rotate(90deg);
}

.sige-modal-close svg {
    width: 20px;
    height: 20px;
}

.sige-modal-body {
    padding:var(--space-6);
    overflow-y: auto;
    flex: 1;
}

.sige-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap:var(--space-3);
    padding:var(--space-4) var(--space-6);
    background: var(--sige-slate-50);
    border-top: 1px solid var(--sige-slate-100);
}

/* Form elements */
.sige-fieldset {
    margin-bottom: 20px;
    padding:var(--space-5);
    border: 1px solid var(--sige-slate-200);
    border-radius: var(--sige-radius-lg);
    background: var(--sige-slate-50);
}

.sige-fieldset legend {
    padding: 0 10px;
    font-size: 0.75rem;
    font-weight:600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--sige-primary-dark);
    background: var(--color-white);
    display: flex;
    align-items: center;
    gap: 6px;
}

.sige-fieldset legend svg {
    width: 16px;
    height: 16px;
}

.sige-check-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.sige-check-label {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-radius: var(--sige-radius);
    border: 1px solid var(--sige-slate-200);
    background: var(--color-white);
    cursor: pointer;
    transition: all var(--duration-normal) ease;
    font-weight:600;
    font-size: 0.875rem;
    color: var(--sige-slate-700);
}

.sige-check-label:hover {
    border-color: var(--sige-primary-light);
    background: rgba(63, 81, 181, 0.02);
}

.sige-check-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--sige-primary);
    cursor: pointer;
}

.sige-check-label svg {
    width: 18px;
    height: 18px;
}

.sige-form-grid {
    display: grid;
    gap:var(--space-4);
}

.sige-form-grid-2 {
    grid-template-columns: repeat(2, 1fr);
}

.sige-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.sige-field.span-2 {
    grid-column: span 2;
}

.sige-field label {
    font-size: 0.7rem;
    font-weight:600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--sige-slate-500);
}

.sige-field input,
.sige-field select {
    width: 100%;
    height: 46px;
    padding: 0 14px;
    border: 1px solid var(--sige-slate-200);
    border-radius: var(--sige-radius);
    font-family: var(--sige-font-body);
    font-size: 0.875rem;
    background: var(--color-white);
    transition: all var(--duration-normal) ease;
}

.sige-field input:focus,
.sige-field select:focus {
    outline: none;
    border-color: var(--sige-primary);
    box-shadow:var(--shadow-xs);
}

.sige-field small {
    font-size: 0.75rem;
    color: var(--sige-slate-500);
}

.sige-highlight-box {
    margin-top: 16px;
    padding:var(--space-4);
    border-radius: var(--sige-radius);
    background: linear-gradient(135deg, var(--sg-theme-soft,var(--color-brand-50)), var(--sg-theme-soft,var(--color-brand-50)));
    border: 1px solid var(--sg-theme-soft,var(--color-brand-50));
}

.sige-highlight-box label {
    color: var(--color-info-600) !important;
}

.sige-btn-modal {
    display: inline-flex;
    align-items: center;
    gap:var(--space-2);
    padding:var(--space-3) var(--space-5);
    border-radius: var(--sige-radius);
    font-family: var(--sige-font-body);
    font-size: 0.875rem;
    font-weight:600;
    cursor: pointer;
    transition: all var(--duration-normal) ease;
    border: none;
}

.sige-btn-modal svg {
    width: 18px;
    height: 18px;
}

.sige-btn-cancel {
    background: var(--color-white);
    border: 1px solid var(--sige-slate-200);
    color: var(--sige-slate-600);
}

.sige-btn-cancel:hover {
    background: var(--sige-slate-50);
    border-color: var(--sige-slate-300);
}

.sige-btn-submit {
    background: linear-gradient(135deg, var(--sige-navy), var(--sige-navy-light));
    color: var(--color-white);
    box-shadow:var(--shadow-xs);
}

.sige-btn-submit:hover {
    transform: translateY(-1px);
    box-shadow:var(--shadow-sm);
}

.sige-btn-submit.btn-rose {
    background: linear-gradient(135deg, var(--color-danger-600), var(--color-danger-500));
    box-shadow:var(--shadow-xs);
}

/* Criterios modal */
.sige-criterio-input {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.sige-criterio-input input {
    flex: 1;
    height: 46px;
    padding: 0 14px;
    border: 1px solid var(--sige-slate-200);
    border-radius: var(--sige-radius);
    font-size: 0.875rem;
}

.sige-criterio-input input:focus {
    outline: none;
    border-color: var(--sige-rose);
    box-shadow:var(--shadow-xs);
}

.sige-criterio-list {
    max-height: 350px;
    overflow-y: auto;
}

.sige-criterio-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap:var(--space-3);
    padding: 14px 16px;
    margin-bottom: 10px;
    border-radius: var(--sige-radius);
    border: 1px solid var(--sige-slate-200);
    background: var(--color-white);
    transition: all var(--duration-normal) ease;
}

.sige-criterio-item:hover {
    border-color: var(--color-danger-200);
    background: var(--color-danger-50);
}

.sige-criterio-text {
    flex: 1;
    font-size: 0.875rem;
    font-weight:600;
    color: var(--sige-slate-700);
}

.sige-criterio-remove {
    width: 30px;
    height: 30px;
    border-radius:var(--radius-pill);
    border: none;
    background: var(--sige-error-light);
    color: var(--sige-error);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--duration-normal) ease;
}

.sige-criterio-remove:hover {
    background: var(--sige-error);
    color: var(--color-white);
}

.sige-criterio-remove svg {
    width: 16px;
    height: 16px;
}

/* ========================================
   RESPONSIVE
   ======================================== */
@media (max-width: 1100px) {
    .sige-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .sige-hero-grid {
        flex-direction: column;
    }
    
    .sige-hero-meta {
        justify-content: flex-start;
    }
}

@media (max-width: 768px) {
    .sige-stats-grid,
    .sige-check-grid,
    .sige-form-grid-2 {
        grid-template-columns: 1fr;
    }
    
    .sige-hero {
        padding:var(--space-6);
    }
    
    .sige-table-header {
        flex-direction: column;
    }
    
    .sige-table-meta {
        justify-content: flex-start;
    }
    
    .sige-modal-content {
        max-width: 100%;
        max-height: 100vh;
        border-radius: 0;
    }
    
    .sige-field.span-2 {
        grid-column: span 1;
    }
}
/* ============================================================================
   v12.10.41 - Disciplinas: Harmonia Visual alinhada ao Painel Principal
   ============================================================================ */
body.sige-view-disciplinas .sg-product-page-head{display:none!important;}
body.sige-view-disciplinas .sg-app-page{padding-top:0!important;}
.sige-disciplinas{
    --sgv2-purple:var(--sg-theme-primary,var(--color-brand-500));
    --sgv2-purple-dark:var(--sg-theme-primary-900,var(--color-brand-700));
    --sgv2-purple-soft:var(--sg-theme-soft,var(--color-brand-50));
    --sgv2-ink:var(--color-ink-500);
    --sgv2-muted:var(--color-slate-500);
    --sgv2-line:var(--color-ink-100);
    --sgv2-bg:var(--color-ink-50);
    --sgv2-green:var(--color-success-700);
    --sgv2-red:var(--color-danger-500);
    --sgv2-amber:var(--color-warning-500);
    --sgv2-blue:var(--color-info-400);
    font-family:'Poppins','Inter','Segoe UI',system-ui,sans-serif!important;
    color:var(--sgv2-ink)!important;
    gap:var(--space-5)!important;
    width:100%!important;
}
.sige-disciplinas *{box-sizing:border-box;}
.sige-disciplinas .sige-hero{
    position:relative!important;
    overflow:hidden!important;
    min-height:178px!important;
    border-radius:var(--radius-xl)!important;
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--sgv2-purple-soft) 100%)!important;
    border:1px solid rgba(var(--sg-theme-primary-rgb,90,63,214),.12)!important;
    box-shadow:var(--shadow-lg);
    padding:32px 34px!important;
    color:var(--sgv2-ink)!important;
}
.sige-disciplinas .sige-hero:before{content:""!important;position:absolute!important;inset:auto -80px -130px auto!important;width:420px!important;height:300px!important;background:radial-gradient(circle,rgba(var(--sg-theme-primary-rgb,90,63,214),.18),rgba(var(--sg-theme-primary-rgb,90,63,214),0) 67%)!important;pointer-events:none!important;}
.sige-disciplinas .sige-hero:after{display:none!important;}
.sige-disciplinas .sige-hero-grid{position:relative!important;z-index:1!important;display:grid!important;grid-template-columns:minmax(0,1.04fr) minmax(340px,.96fr)!important;gap:22px!important;align-items:center!important;}
.sige-disciplinas .sige-hero-kicker{font-size:12px!important;font-weight:700!important;letter-spacing:.11em!important;text-transform:uppercase!important;color:var(--sgv2-purple)!important;margin:0 0 10px!important;background:transparent!important;padding:0!important;border-radius:0!important;}
.sige-disciplinas .sige-hero h1{margin:0!important;font-size:31px!important;line-height:1.08!important;font-weight:700!important;letter-spacing:-.045em!important;color:var(--color-black)!important;}
.sige-disciplinas .sige-hero-subtitle{max-width:720px!important;margin:var(--space-3) 0 0!important;font-size:15px!important;line-height:1.65!important;color:var(--color-slate-700)!important;font-weight:500!important;}
.sige-disciplinas .sige-hero-actions{display:flex!important;flex-wrap:wrap!important;gap:var(--space-3)!important;margin-top:24px!important;}
.sige-disciplinas .sige-btn{min-height:46px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;border-radius:var(--radius-md)!important;padding:0 22px!important;font-size:var(--fs-base)!important;font-weight:700!important;text-decoration:none!important;border:1px solid transparent!important;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease!important;background:var(--color-white)!important;color:var(--color-slate-900)!important;box-shadow:none!important;}
.sige-disciplinas .sige-btn svg{width:18px!important;height:18px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sige-disciplinas .sige-btn-primary{background:linear-gradient(135deg,var(--sgv2-purple),var(--sgv2-purple-dark))!important;color:var(--color-white)!important;box-shadow:var(--shadow-md);}
.sige-disciplinas .sige-btn-secondary{background:var(--color-white)!important;color:var(--color-ink-900)!important;border-color:var(--color-ink-100)!important;box-shadow:var(--shadow-sm);}
.sige-disciplinas .sige-btn:hover{transform:translateY(-1px)!important;box-shadow:var(--shadow-md);}
.sige-disciplinas .sige-hero-art{position:relative!important;min-height:148px!important;border-radius:var(--radius-xl)!important;background:linear-gradient(135deg,rgba(var(--sg-theme-primary-rgb,90,63,214),.08),rgba(var(--sg-theme-primary-rgb,90,63,214),.18))!important;overflow:hidden!important;}
.sige-disciplinas .sige-hero-art:before{content:"";position:absolute;left:42px;bottom:24px;width:90px;height:90px;border-radius:50%;background:rgba(var(--sg-theme-primary-rgb,90,63,214),.16);}
.sige-disciplinas .sige-hero-art:after{content:"";position:absolute;right:24px;bottom:24px;width:54px;height:72px;border-radius:22px 38px 12px 12px;background:rgba(var(--sg-theme-primary-rgb,90,63,214),.28);box-shadow:var(--shadow-sm);}
.sige-disciplinas .sige-hero-school{position:absolute!important;right:84px!important;bottom:31px!important;width:220px!important;height:92px!important;color:var(--sgv2-purple)!important;}
.sige-disciplinas .sg-disc-book{position:absolute;left:28px;bottom:0;width:48px;height:72px;border-radius:8px 8px 8px 10px;background:rgba(var(--sg-theme-primary-rgb,90,63,214),.30);box-shadow:inset 8px 0 0 rgba(255,255,255,.22);}
.sige-disciplinas .sg-disc-book-2{left:84px;height:88px;background:rgba(var(--sg-theme-primary-rgb,90,63,214),.46);}
.sige-disciplinas .sg-disc-book-3{left:140px;height:64px;background:rgba(var(--sg-theme-primary-rgb,90,63,214),.24);}
.sige-disciplinas .sg-disc-line{position:absolute;left:16px;right:22px;bottom:-14px;height:8px;border-radius:var(--radius-pill);background:rgba(var(--sg-theme-primary-rgb,90,63,214),.30);}
.sige-disciplinas .sg-disc-line-2{bottom:-30px;left:52px;right:58px;background:rgba(var(--sg-theme-primary-rgb,90,63,214),.20);}
.sige-disciplinas .sg-disc-mini-card{position:absolute;left:24px;top:22px;min-width:128px;border-radius:var(--radius-lg);background:rgba(255,255,255,.72);border:1px solid rgba(255,255,255,.78);box-shadow:var(--shadow-sm);padding:13px 15px;}
.sige-disciplinas .sg-disc-mini-card strong{display:block;font-size:var(--fs-xl);line-height:1;font-weight:700;color:var(--color-black);}
.sige-disciplinas .sg-disc-mini-card span{display:block;margin-top:5px;font-size:12px;font-weight:700;color:var(--color-slate-500);}
.sige-disciplinas .sige-stats-grid{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:var(--space-4)!important;}
.sige-disciplinas .sige-stat-card{position:relative!important;overflow:hidden!important;display:grid!important;grid-template-columns:auto minmax(0,1fr)!important;gap:var(--space-4)!important;align-items:center!important;min-height:104px!important;padding:18px 20px!important;border-radius:var(--radius-xl)!important;background:var(--color-white)!important;border:1px solid rgba(28,32,54,.08)!important;box-shadow:var(--shadow-md);isolation:isolate!important;}
.sige-disciplinas .sige-stat-card:before{display:none!important;}
.sige-disciplinas .sige-stat-card:after{content:""!important;position:absolute!important;right:-28px!important;top:-34px!important;width:92px!important;height:92px!important;border-radius:50%!important;background:var(--kpi-soft,var(--color-brand-50))!important;z-index:0!important;pointer-events:none!important;opacity:.72!important;}
.sige-disciplinas .sige-stat-card > *{position:relative!important;z-index:1!important;}
.sige-disciplinas .sige-stat-card.stat-primary{--kpi-soft:var(--sgv2-purple-soft);--kpi-color:var(--sgv2-purple);}
.sige-disciplinas .sige-stat-card.stat-success{--kpi-soft:var(--color-success-100);--kpi-color:var(--sgv2-green);}
.sige-disciplinas .sige-stat-card.stat-rose{--kpi-soft:var(--color-danger-50);--kpi-color:var(--color-danger-400);}
.sige-disciplinas .sige-stat-card.stat-amber{--kpi-soft:var(--color-warning-100);--kpi-color:var(--sgv2-amber);}
.sige-disciplinas .sige-stat-icon{width:52px!important;height:52px!important;border-radius:var(--radius-lg)!important;display:flex!important;align-items:center!important;justify-content:center!important;margin:0!important;background:var(--kpi-soft)!important;color:var(--kpi-color)!important;}
.sige-disciplinas .sige-stat-icon svg{width:24px!important;height:24px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sige-disciplinas .sige-stat-label{font-size:var(--fs-sm)!important;font-weight:600!important;color:var(--color-slate-600)!important;text-transform:none!important;letter-spacing:0!important;margin:0 0 6px!important;}
.sige-disciplinas .sige-stat-value{font-size:27px!important;line-height:1!important;font-weight:700!important;letter-spacing:-.035em!important;color:var(--color-black)!important;}
.sige-disciplinas .sige-stat-note{margin-top:7px!important;font-size:12px!important;font-weight:600!important;color:var(--color-ink-400)!important;}
.sige-disciplinas .sige-table-card{background:var(--color-white)!important;border:1px solid rgba(30,34,60,.08)!important;border-radius:var(--radius-xl)!important;box-shadow:var(--shadow-md);overflow:hidden!important;min-width:0!important;}
.sige-disciplinas .sige-table-header{padding:20px 22px 14px!important;background:var(--color-white)!important;border-bottom:1px solid var(--color-slate-100)!important;}
.sige-disciplinas .sige-table-header h3{margin:0!important;font-size:18px!important;line-height:1.15!important;font-weight:700!important;letter-spacing:-.035em!important;color:var(--color-black)!important;}
.sige-disciplinas .sige-table-header p{margin:5px 0 0!important;font-size:12px!important;font-weight:600!important;color:var(--color-ink-400)!important;}
.sige-disciplinas .sige-mini-chip{min-height:34px!important;border-radius:var(--radius-pill)!important;background:var(--sgv2-purple-soft)!important;color:var(--sgv2-purple)!important;border:1px solid rgba(var(--sg-theme-primary-rgb,90,63,214),.14)!important;font-weight:700!important;}
.sige-disciplinas .sige-table-wrapper{overflow:auto!important;-webkit-overflow-scrolling:touch!important;padding:0 var(--space-4) var(--space-4)!important;}
.sige-disciplinas .sige-table{width:100%!important;min-width:940px!important;border-collapse:separate!important;border-spacing:0 8px!important;font-size:var(--fs-sm)!important;}
.sige-disciplinas .sige-table thead th{background:transparent!important;border:0!important;text-align:left!important;color:var(--color-slate-500)!important;font-size:var(--fs-xs)!important;font-weight:700!important;text-transform:uppercase!important;letter-spacing:.06em!important;padding:0 var(--space-3) var(--space-1)!important;}
.sige-disciplinas .sige-table tbody tr{border:0!important;}
.sige-disciplinas .sige-table tbody td{background:var(--color-white)!important;border-top:1px solid var(--color-slate-100)!important;border-bottom:1px solid var(--color-slate-100)!important;padding:13px 12px!important;color:var(--color-slate-900)!important;font-size:var(--fs-sm)!important;font-weight:600!important;vertical-align:middle!important;}
.sige-disciplinas .sige-table tbody td:first-child{border-left:1px solid var(--color-slate-100)!important;border-radius:12px 0 0 13px!important;}
.sige-disciplinas .sige-table tbody td:last-child{border-right:1px solid var(--color-slate-100)!important;border-radius:0 13px 13px 0!important;}
.sige-disciplinas .sige-table tbody tr:hover td{background:var(--color-white)!important;}
.sige-disciplinas .sige-sigla-badge{background:linear-gradient(135deg,var(--sgv2-purple),var(--sgv2-purple-dark))!important;border-radius:var(--radius-md)!important;box-shadow:var(--shadow-sm);}
.sige-disciplinas .sige-action-btn{border-radius:var(--radius-md)!important;border:1px solid var(--color-slate-100)!important;background:var(--color-white)!important;box-shadow:var(--shadow-sm);}
.sige-disciplinas .sige-action-btn svg{stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sige-disciplinas .sige-action-btn.btn-edit{background:var(--color-info-50)!important;color:var(--color-info-400)!important;border-color:var(--color-info-100)!important;}
.sige-disciplinas .sige-action-btn.btn-delete{background:var(--color-danger-50)!important;color:var(--color-danger-500)!important;border-color:var(--color-danger-100)!important;}
.sige-disciplinas .sige-action-btn.btn-criteria{background:var(--color-warning-100)!important;color:var(--color-warning-600)!important;border-color:var(--color-warning-200)!important;}
.sige-disciplinas .sige-action-btn:hover{background:var(--sgv2-purple)!important;border-color:var(--sgv2-purple)!important;color:var(--color-white)!important;}

/* Modal Nova/Editar Disciplina - modelo claro e profissional */
.sige-disciplinas .sige-modal{z-index:1000000!important;background:rgba(18,22,40,.54)!important;backdrop-filter:blur(14px)!important;-webkit-backdrop-filter:blur(14px)!important;padding:22px!important;}
.sige-disciplinas .sige-modal-content{width:min(1080px,calc(100vw - 44px))!important;max-width:1080px!important;max-height:92vh!important;border-radius:var(--radius-xl)!important;border:1px solid rgba(255,255,255,.86)!important;background:var(--color-white)!important;box-shadow:var(--shadow-lg);overflow:hidden!important;}
.sige-disciplinas .sige-modal-header{position:relative!important;overflow:hidden!important;min-height:118px!important;padding:28px 32px!important;background:linear-gradient(135deg,var(--color-white) 0%,var(--color-white) 52%,var(--sgv2-purple-soft) 100%)!important;color:var(--color-black)!important;border-bottom:1px solid rgba(30,34,60,.06)!important;}
.sige-disciplinas .sige-modal-header:after{content:""!important;position:absolute!important;right:-110px!important;top:-150px!important;width:360px!important;height:360px!important;border-radius:var(--radius-pill)!important;background:rgba(var(--sg-theme-primary-rgb,90,63,214),.10)!important;pointer-events:none!important;}
.sige-disciplinas .sige-modal-header h3{position:relative!important;z-index:1!important;margin:0!important;color:var(--color-black)!important;font-family:'Poppins','Inter','Segoe UI',system-ui,sans-serif!important;font-size:30px!important;line-height:1.08!important;font-weight:700!important;letter-spacing:-.045em!important;display:flex!important;align-items:center!important;gap:14px!important;}
.sige-disciplinas .sige-modal-header h3 svg{width:52px!important;height:52px!important;padding:14px!important;border-radius:var(--radius-lg)!important;background:var(--sgv2-purple-soft)!important;color:var(--sgv2-purple)!important;stroke:currentColor!important;box-shadow:var(--shadow-sm);}
.sige-disciplinas .sige-modal-close{position:relative!important;z-index:2!important;width:46px!important;height:46px!important;border-radius:var(--radius-lg)!important;background:var(--color-white)!important;color:var(--color-ink-500)!important;border:1px solid rgba(30,34,60,.08)!important;box-shadow:var(--shadow-md);}
.sige-disciplinas .sige-modal-close:hover{background:var(--sgv2-purple-soft)!important;color:var(--sgv2-purple)!important;transform:none!important;}
.sige-disciplinas .sige-modal-body{background:linear-gradient(180deg,var(--color-white) 0%,var(--color-slate-50) 100%)!important;padding:24px 28px!important;overflow-y:auto!important;}
.sige-disciplinas .sige-fieldset{margin-bottom:18px!important;padding:22px!important;border:1px solid rgba(30,34,60,.07)!important;border-radius:var(--radius-xl)!important;background:var(--color-white)!important;box-shadow:var(--shadow-md);}
.sige-disciplinas .sige-fieldset legend{padding:0 var(--space-3)!important;background:var(--color-white)!important;color:var(--sgv2-purple)!important;font-size:12px!important;font-weight:700!important;letter-spacing:.09em!important;}
.sige-disciplinas .sige-check-grid{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:var(--space-3)!important;}
.sige-disciplinas .sige-check-label{min-height:64px!important;border-radius:var(--radius-lg)!important;border:1px solid var(--color-ink-100)!important;background:var(--color-white)!important;color:var(--color-ink-800)!important;font-size:var(--fs-base)!important;font-weight:700!important;box-shadow:var(--shadow-sm);}
.sige-disciplinas .sige-check-label:hover{border-color:rgba(var(--sg-theme-primary-rgb,90,63,214),.24)!important;background:var(--color-white)!important;}
.sige-disciplinas .sige-check-label input[type="checkbox"]{accent-color:var(--sgv2-purple)!important;width:19px!important;height:19px!important;}
.sige-disciplinas .sige-field label{margin-bottom:7px!important;color:var(--color-slate-700)!important;font-size:var(--fs-xs)!important;font-weight:700!important;letter-spacing:.09em!important;}
.sige-disciplinas .sige-field input,.sige-disciplinas .sige-field select{height:54px!important;border-radius:var(--radius-lg)!important;border:1px solid var(--color-ink-100)!important;background:var(--color-white)!important;color:var(--color-ink-500)!important;font-size:var(--fs-base)!important;font-weight:600!important;box-shadow:var(--shadow-sm);}
.sige-disciplinas .sige-field input::placeholder{color:var(--color-slate-400)!important;font-weight:600!important;}
.sige-disciplinas .sige-field input:focus,.sige-disciplinas .sige-field select:focus{border-color:rgba(var(--sg-theme-primary-rgb,90,63,214),.42)!important;box-shadow:var(--shadow-xs);}
.sige-disciplinas .sige-highlight-box{border-radius:var(--radius-xl)!important;background:linear-gradient(135deg,var(--color-white),var(--sgv2-purple-soft))!important;border:1px solid rgba(var(--sg-theme-primary-rgb,90,63,214),.14)!important;}
.sige-disciplinas .sige-modal-footer{padding:18px 28px!important;background:var(--color-white)!important;border-top:1px solid var(--color-slate-100)!important;}
.sige-disciplinas .sige-btn-modal{min-height:52px!important;border-radius:var(--radius-lg)!important;padding:0 22px!important;font-size:var(--fs-base)!important;font-weight:700!important;}
.sige-disciplinas .sige-btn-cancel{background:var(--color-white)!important;border:1px solid var(--color-ink-100)!important;color:var(--color-slate-800)!important;}
.sige-disciplinas .sige-btn-submit{background:linear-gradient(135deg,var(--sgv2-purple),var(--sgv2-purple-dark))!important;color:var(--color-white)!important;box-shadow:var(--shadow-md);}
.sige-disciplinas .sige-btn-submit.btn-rose{background:linear-gradient(135deg,var(--color-danger-400),var(--color-danger-600))!important;}
.sige-disciplinas .sige-criterio-input{display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:var(--space-3)!important;}
.sige-disciplinas .sige-criterio-input input{height:54px!important;border-radius:var(--radius-lg)!important;border:1px solid var(--color-ink-100)!important;padding:0 var(--space-4)!important;font-weight:600!important;}
.sige-disciplinas .sige-disc-confirm-modal{display:none;align-items:center;justify-content:center;}
.sige-disciplinas .sige-disc-confirm-box{width:min(520px,calc(100vw - 42px));border-radius:var(--radius-xl);background:var(--color-white);box-shadow:var(--shadow-lg);overflow:hidden;border:1px solid rgba(255,255,255,.82);}
.sige-disciplinas .sige-disc-confirm-head{padding:22px 24px;background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--sgv2-purple-soft) 100%);border-bottom:1px solid rgba(92,64,187,.12);}
.sige-disciplinas .sige-disc-confirm-head h3{margin:0;color:var(--color-black);font-size:21px;line-height:1.1;font-weight:700;letter-spacing:-.035em;}
.sige-disciplinas .sige-disc-confirm-body{padding:var(--space-5) var(--space-6);color:var(--color-slate-700);font-size:var(--fs-base);line-height:1.6;font-weight:600;}
.sige-disciplinas .sige-disc-confirm-actions{display:flex;gap:var(--space-3);justify-content:flex-end;padding:16px 24px 22px;background:var(--color-white);}
@media (max-width:1500px){.sige-disciplinas .sige-hero-grid{grid-template-columns:1fr!important}.sige-disciplinas .sige-hero-art{display:none!important}}
@media (max-width:1100px){.sige-disciplinas .sige-stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}.sige-disciplinas .sige-check-grid{grid-template-columns:1fr!important}}
@media (max-width:720px){.sige-disciplinas .sige-hero{padding:24px 20px!important}.sige-disciplinas .sige-hero h1{font-size:24px!important}.sige-disciplinas .sige-stats-grid{grid-template-columns:1fr!important}.sige-disciplinas .sige-hero-actions{display:grid!important;grid-template-columns:1fr!important}.sige-disciplinas .sige-btn{width:100%!important}.sige-disciplinas .sige-modal{padding:0!important}.sige-disciplinas .sige-modal-content{width:100%!important;max-height:100vh!important;border-radius:0!important}.sige-disciplinas .sige-modal-header h3{font-size:23px!important}.sige-disciplinas .sige-form-grid-2{grid-template-columns:1fr!important}.sige-disciplinas .sige-field.span-2{grid-column:span 1!important}.sige-disciplinas .sige-criterio-input{grid-template-columns:1fr!important}}
body.sige-modal-open{overflow:hidden!important;}

/* Modais montados no body para visibilidade total */
body.sige-view-disciplinas .sige-modal{z-index:1000000!important;background:rgba(18,22,40,.54)!important;backdrop-filter:blur(14px)!important;-webkit-backdrop-filter:blur(14px)!important;padding:22px!important;}
body.sige-view-disciplinas .sige-modal-content{width:min(1080px,calc(100vw - 44px))!important;max-width:1080px!important;max-height:92vh!important;border-radius:var(--radius-xl)!important;border:1px solid rgba(255,255,255,.86)!important;background:var(--color-white)!important;box-shadow:var(--shadow-lg);overflow:hidden!important;}
body.sige-view-disciplinas .sige-modal-header{position:relative!important;overflow:hidden!important;min-height:118px!important;padding:28px 32px!important;background:linear-gradient(135deg,var(--color-white) 0%,var(--color-white) 52%,var(--sg-theme-soft,var(--color-brand-50)) 100%)!important;color:var(--color-black)!important;border-bottom:1px solid rgba(30,34,60,.06)!important;}
body.sige-view-disciplinas .sige-modal-header:after{content:""!important;position:absolute!important;right:-110px!important;top:-150px!important;width:360px!important;height:360px!important;border-radius:var(--radius-pill)!important;background:rgba(var(--sg-theme-primary-rgb,90,63,214),.10)!important;pointer-events:none!important;}
body.sige-view-disciplinas .sige-modal-header h3{position:relative!important;z-index:1!important;margin:0!important;color:var(--color-black)!important;font-family:'Poppins','Inter','Segoe UI',system-ui,sans-serif!important;font-size:30px!important;line-height:1.08!important;font-weight:700!important;letter-spacing:-.045em!important;display:flex!important;align-items:center!important;gap:14px!important;}
body.sige-view-disciplinas .sige-modal-header h3 svg{width:52px!important;height:52px!important;padding:14px!important;border-radius:var(--radius-lg)!important;background:var(--sg-theme-soft,var(--color-brand-50))!important;color:var(--sg-theme-primary,var(--color-brand-500))!important;stroke:currentColor!important;box-shadow:var(--shadow-sm);}
body.sige-view-disciplinas .sige-modal-close{position:relative!important;z-index:2!important;width:46px!important;height:46px!important;border-radius:var(--radius-lg)!important;background:var(--color-white)!important;color:var(--color-ink-500)!important;border:1px solid rgba(30,34,60,.08)!important;box-shadow:var(--shadow-md);}
body.sige-view-disciplinas .sige-modal-close:hover{background:var(--sg-theme-soft,var(--color-brand-50))!important;color:var(--sg-theme-primary,var(--color-brand-500))!important;transform:none!important;}
body.sige-view-disciplinas .sige-modal-body{background:linear-gradient(180deg,var(--color-white) 0%,var(--color-slate-50) 100%)!important;padding:24px 28px!important;overflow-y:auto!important;}
body.sige-view-disciplinas .sige-modal-footer{padding:18px 28px!important;background:var(--color-white)!important;border-top:1px solid var(--color-slate-100)!important;}
body.sige-view-disciplinas .sige-btn-modal{min-height:52px!important;border-radius:var(--radius-lg)!important;padding:0 22px!important;font-size:var(--fs-base)!important;font-weight:700!important;}
body.sige-view-disciplinas .sige-btn-cancel{background:var(--color-white)!important;border:1px solid var(--color-ink-100)!important;color:var(--color-slate-800)!important;}
body.sige-view-disciplinas .sige-btn-submit{background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-900,var(--color-brand-700)))!important;color:var(--color-white)!important;box-shadow:var(--shadow-md);}
body.sige-view-disciplinas .sige-btn-submit.btn-rose{background:linear-gradient(135deg,var(--color-danger-400),var(--color-danger-600))!important;}
body.sige-view-disciplinas .sige-fieldset{margin-bottom:18px!important;padding:22px!important;border:1px solid rgba(30,34,60,.07)!important;border-radius:var(--radius-xl)!important;background:var(--color-white)!important;box-shadow:var(--shadow-md);}
body.sige-view-disciplinas .sige-fieldset legend{padding:0 var(--space-3)!important;background:var(--color-white)!important;color:var(--sg-theme-primary,var(--color-brand-500))!important;font-size:12px!important;font-weight:700!important;letter-spacing:.09em!important;}
body.sige-view-disciplinas .sige-check-grid{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:var(--space-3)!important;}
body.sige-view-disciplinas .sige-check-label{min-height:64px!important;border-radius:var(--radius-lg)!important;border:1px solid var(--color-ink-100)!important;background:var(--color-white)!important;color:var(--color-ink-800)!important;font-size:var(--fs-base)!important;font-weight:700!important;box-shadow:var(--shadow-sm);}
body.sige-view-disciplinas .sige-field label{margin-bottom:7px!important;color:var(--color-slate-700)!important;font-size:var(--fs-xs)!important;font-weight:700!important;letter-spacing:.09em!important;}
body.sige-view-disciplinas .sige-field input,body.sige-view-disciplinas .sige-field select{height:54px!important;border-radius:var(--radius-lg)!important;border:1px solid var(--color-ink-100)!important;background:var(--color-white)!important;color:var(--color-ink-500)!important;font-size:var(--fs-base)!important;font-weight:600!important;box-shadow:var(--shadow-sm);}
body.sige-view-disciplinas .sige-highlight-box{border-radius:var(--radius-xl)!important;background:linear-gradient(135deg,var(--color-white),var(--sg-theme-soft,var(--color-brand-50)))!important;border:1px solid rgba(var(--sg-theme-primary-rgb,90,63,214),.14)!important;}
body.sige-view-disciplinas .sige-criterio-input{display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:var(--space-3)!important;}
body.sige-view-disciplinas .sige-criterio-input input{height:54px!important;border-radius:var(--radius-lg)!important;border:1px solid var(--color-ink-100)!important;padding:0 var(--space-4)!important;font-weight:600!important;}
body.sige-view-disciplinas .sige-disc-confirm-box{width:min(520px,calc(100vw - 42px))!important;border-radius:var(--radius-xl)!important;background:var(--color-white)!important;box-shadow:var(--shadow-lg);overflow:hidden!important;border:1px solid rgba(255,255,255,.82)!important;}
body.sige-view-disciplinas .sige-disc-confirm-head{padding:22px 24px!important;background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--sg-theme-soft,var(--color-brand-50)) 100%)!important;border-bottom:1px solid rgba(92,64,187,.12)!important;}
body.sige-view-disciplinas .sige-disc-confirm-head h3{margin:0!important;color:var(--color-black)!important;font-size:21px!important;line-height:1.1!important;font-weight:700!important;letter-spacing:-.035em!important;}
body.sige-view-disciplinas .sige-disc-confirm-body{padding:var(--space-5) var(--space-6)!important;color:var(--color-slate-700)!important;font-size:var(--fs-base)!important;line-height:1.6!important;font-weight:600!important;}
body.sige-view-disciplinas .sige-disc-confirm-actions{display:flex!important;gap:var(--space-3)!important;justify-content:flex-end!important;padding:16px 24px 22px!important;background:var(--color-white)!important;}
@media (max-width:720px){body.sige-view-disciplinas .sige-modal{padding:0!important}body.sige-view-disciplinas .sige-modal-content{width:100%!important;max-height:100vh!important;border-radius:0!important}body.sige-view-disciplinas .sige-modal-header h3{font-size:23px!important}body.sige-view-disciplinas .sige-form-grid-2{grid-template-columns:1fr!important}body.sige-view-disciplinas .sige-field.span-2{grid-column:span 1!important}body.sige-view-disciplinas .sige-criterio-input{grid-template-columns:1fr!important}}

/* v12.11.9.36 - Hotfix: Nova/Editar Disciplina com scroll interno e rodapé sempre visível */
body.sige-view-disciplinas #modal-disc.sige-modal,
.sige-disciplinas #modal-disc.sige-modal{
    align-items:center!important;
    justify-content:center!important;
    overflow-y:auto!important;
    overscroll-behavior:contain!important;
    -webkit-overflow-scrolling:touch!important;
}
body.sige-view-disciplinas #modal-disc .sige-modal-content,
.sige-disciplinas #modal-disc .sige-modal-content{
    height:min(860px, calc(100vh - 44px))!important;
    max-height:calc(100vh - 44px)!important;
    display:flex!important;
    flex-direction:column!important;
    overflow:hidden!important;
}
body.sige-view-disciplinas #modal-disc .sige-modal-header,
.sige-disciplinas #modal-disc .sige-modal-header{
    flex:0 0 auto!important;
}
body.sige-view-disciplinas #modal-disc form,
.sige-disciplinas #modal-disc form{
    flex:1 1 auto!important;
    min-height:0!important;
    display:flex!important;
    flex-direction:column!important;
    overflow:hidden!important;
}
body.sige-view-disciplinas #modal-disc .sige-modal-body,
.sige-disciplinas #modal-disc .sige-modal-body{
    flex:1 1 auto!important;
    min-height:0!important;
    max-height:none!important;
    overflow-y:auto!important;
    overscroll-behavior:contain!important;
    -webkit-overflow-scrolling:touch!important;
    scrollbar-gutter:stable!important;
    padding-bottom:30px!important;
}
body.sige-view-disciplinas #modal-disc .sige-modal-footer,
.sige-disciplinas #modal-disc .sige-modal-footer{
    flex:0 0 auto!important;
    position:relative!important;
    z-index:4!important;
    box-shadow:var(--shadow-md);
}
body.sige-view-disciplinas #modal-disc .sige-modal-body::-webkit-scrollbar,
.sige-disciplinas #modal-disc .sige-modal-body::-webkit-scrollbar{width:10px!important;}
body.sige-view-disciplinas #modal-disc .sige-modal-body::-webkit-scrollbar-track,
.sige-disciplinas #modal-disc .sige-modal-body::-webkit-scrollbar-track{background:var(--color-ink-50)!important;border-radius:var(--radius-pill)!important;}
body.sige-view-disciplinas #modal-disc .sige-modal-body::-webkit-scrollbar-thumb,
.sige-disciplinas #modal-disc .sige-modal-body::-webkit-scrollbar-thumb{background:rgba(var(--sg-theme-primary-rgb,90,63,214),.28)!important;border-radius:var(--radius-pill)!important;}
@media (max-width:720px){
    body.sige-view-disciplinas #modal-disc.sige-modal,
    .sige-disciplinas #modal-disc.sige-modal{padding:0!important;align-items:stretch!important;}
    body.sige-view-disciplinas #modal-disc .sige-modal-content,
    .sige-disciplinas #modal-disc .sige-modal-content{width:100%!important;height:100vh!important;max-height:100vh!important;border-radius:0!important;}
    body.sige-view-disciplinas #modal-disc .sige-modal-header,
    .sige-disciplinas #modal-disc .sige-modal-header{min-height:94px!important;padding:20px 18px!important;}
    body.sige-view-disciplinas #modal-disc .sige-modal-body,
    .sige-disciplinas #modal-disc .sige-modal-body{padding:18px 16px 28px!important;}
    body.sige-view-disciplinas #modal-disc .sige-modal-footer,
    .sige-disciplinas #modal-disc .sige-modal-footer{display:grid!important;grid-template-columns:1fr!important;gap:10px!important;padding:14px 16px 18px!important;}
    body.sige-view-disciplinas #modal-disc .sige-btn-modal,
    .sige-disciplinas #modal-disc .sige-btn-modal{width:100%!important;justify-content:center!important;}
}


/* Utilitárias da consolidação CSS (v106+); substituem style= inline equivalentes */
.sg-disc-th-num{width:60px;text-align:center;}
.sg-disc-th-sigla{width:100px;}
.sg-disc-th-accoes{width:140px;text-align:right;}
.sg-td-center{text-align:center;}
.sg-input-upper{text-transform:uppercase;}
.sg-hint{display:block;margin-top:12px;color:var(--sige-slate-500);}
.sg-modal-550{max-width:550px;}
.sg-empty-msg{text-align:center;padding:var(--space-10);color:var(--color-slate-400);}
.sg-empty-pad{padding:var(--space-10) var(--space-5);}
.sg-empty-ico{width:60px;height:60px;}
.sg-empty-svg{width:28px;height:28px;}
.sg-empty-h3{font-size:1rem;}
</style>

<div class="wrap sige-disciplinas">

    <!-- ========================================
         HERO SECTION
         ======================================== -->
    <section class="sige-hero">
        <div class="sige-hero-grid">
            <div class="sige-hero-copy">
                <div class="sige-hero-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('book') : ''; ?> Académico</div>
                <h1>Gestão de Disciplinas</h1>
                <p class="sige-hero-subtitle">Organize as disciplinas da escola, confirme os ciclos de ensino, acompanhe a ligação à matriz curricular e mantenha a equipa pedagógica com informação clara.</p>
                <div class="sige-hero-actions">
                    <button data-sige-act="novaDisciplina" data-sige-noargs class="sige-btn sige-btn-primary" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Nova disciplina
                    </button>
                    <a href="?page=sige-app&view=matriz" class="sige-btn sige-btn-secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        Ver matriz curricular
                    </a>
                </div>
            </div>
            <div class="sige-hero-art" aria-hidden="true">
                <div class="sige-hero-school">
                    <span class="sg-disc-book"></span>
                    <span class="sg-disc-book sg-disc-book-2"></span>
                    <span class="sg-disc-book sg-disc-book-3"></span>
                    <span class="sg-disc-line"></span>
                    <span class="sg-disc-line sg-disc-line-2"></span>
                </div>
                <div class="sg-disc-mini-card">
                    <strong><?php echo (int) $total_com_matriz; ?></strong>
                    <span>com matriz</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================
         STATS GRID
         ======================================== -->
    <section class="sige-stats-grid">
        <div class="sige-stat-card stat-primary">
            <div class="sige-stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </div>
            <div class="sige-stat-label">Total de Disciplinas</div>
            <div class="sige-stat-value"><?php echo $total_disciplinas; ?></div>
            <div class="sige-stat-note">registadas para gestão curricular</div>
        </div>
        
        <div class="sige-stat-card stat-success">
            <div class="sige-stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div class="sige-stat-label">Na Matriz Curricular</div>
            <div class="sige-stat-value"><?php echo $total_com_matriz; ?></div>
            <div class="sige-stat-note">com carga horária definida</div>
        </div>
        
        <div class="sige-stat-card stat-rose">
            <div class="sige-stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </div>
            <div class="sige-stat-label">Áreas Pré-Escolar</div>
            <div class="sige-stat-value"><?php echo $count_pre; ?></div>
            <div class="sige-stat-note">com foco no desenvolvimento infantil</div>
        </div>
        
        <div class="sige-stat-card stat-amber">
            <div class="sige-stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            </div>
            <div class="sige-stat-label">Disciplinas Académicas</div>
            <div class="sige-stat-value"><?php echo $count_academicas; ?></div>
            <div class="sige-stat-note">para o ensino primário e secundário</div>
        </div>
    </section>

    <!-- ========================================
         TABELA DE DISCIPLINAS
         ======================================== -->
    <section class="sige-table-card">
        <div class="sige-table-header">
            <div>
                <h3>Disciplinas registadas</h3>
                <p>Lista das disciplinas activas no sistema, com ciclos de ensino, carga definida na matriz e acções de manutenção.</p>
            </div>
            <div class="sige-table-meta">
                <span class="sige-mini-chip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    <?php echo (int) $total_disciplinas; ?> disciplina(s)
                </span>
                <span class="sige-mini-chip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    <?php echo (int) $total_com_matriz; ?> com matriz
                </span>
            </div>
        </div>
        
        <div class="sige-table-wrapper">
            <table class="sige-table">
                <thead>
                    <tr>
                        <th class="sg-disc-th-num">#</th>
                        <th class="sg-disc-th-sigla">Sigla</th>
                        <th>Designação</th>
                        <th>Carga por Ciclo</th>
                        <th>Ciclos Activos</th>
                        <th class="sg-disc-th-accoes">Acções</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($disciplinas): ?>
                        <?php foreach($disciplinas as $d): 
                            $ciclos = json_decode($d->ciclos);
                            if(!is_array($ciclos)) $ciclos = [];
                            $isPre = in_array('PreEscolar', $ciclos);
                            $json = htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td class="sg-td-center">
                                <span class="sige-ordem-badge"><?php echo $d->ordem; ?></span>
                            </td>
                            <td>
                                <span class="sige-sigla-badge"><?php echo esc_html($d->sigla); ?></span>
                            </td>
                            <td>
                                <div class="sige-disc-info">
                                    <span class="sige-disc-name"><?php echo esc_html($d->nome); ?></span>
                                    <span class="sige-disc-type">
                                        <?php if($isPre): ?>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                            Desenvolvimento Infantil
                                        <?php else: ?>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                                            <?php echo ucfirst($d->categoria); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php
                                $cc = sige_carga_por_ciclo($d->id, $mapa_cargas, $ciclo_grupos);
                                if (!empty($cc)): ?>
                                    <div class="sige-carga-tags">
                                    <?php foreach ($cc as $lbl => $vals):
                                        $disp = implode('-', array_map('sige_carga_display', $vals));
                                    ?>
                                        <span class="sige-carga-tag"><strong><?php echo esc_html($lbl); ?>:</strong> <?php echo esc_html($disp); ?></span>
                                    <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="sige-no-matriz">Sem matriz definida</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="sige-ciclo-tags">
                                    <?php if($isPre): ?>
                                        <span class="sige-ciclo-tag tag-pre">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                            Pré-Escolar
                                        </span>
                                    <?php endif; ?>
                                    <?php if(in_array('Primario1', $ciclos)): ?>
                                        <span class="sige-ciclo-tag tag-ep">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                                            EP1 (1-3)
                                        </span>
                                    <?php endif; ?>
                                    <?php if(in_array('Primario2', $ciclos)): ?>
                                        <span class="sige-ciclo-tag tag-ep">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                                            EP2 (4-6)
                                        </span>
                                    <?php endif; ?>
                                    <?php if(in_array('ESG1', $ciclos)): ?>
                                        <span class="sige-ciclo-tag tag-esg1">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                            ESG1 (7-9)
                                        </span>
                                    <?php endif; ?>
                                    <?php if(in_array('ESG2', $ciclos)): ?>
                                        <span class="sige-ciclo-tag tag-esg2">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                                            ESG2 (10-12)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="sige-table-actions">
                                    <?php if($isPre): ?>
                                        <button data-sige-act="gerirCriterios" data-sige-args="<?php echo esc_attr(wp_json_encode([$d->id, $d->nome])); ?>" class="sige-action-btn btn-criteria" data-tooltip="Indicadores">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                                        </button>
                                    <?php endif; ?>
                                    <button data-sige-act="sigeExecutarJsonData" data-sige-json-fn="editarDisciplina" data-sige-json='<?php echo $json; ?>' class="sige-action-btn btn-edit" data-tooltip="Editar">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button data-sige-act="apagarDisciplina" data-sige-args="<?php echo esc_attr(wp_json_encode([$d->id, $d->nome])); ?>" class="sige-action-btn btn-delete" data-tooltip="Remover">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="sige-empty-state">
                                    <div class="sige-empty-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                                    </div>
                                    <h3>Nenhuma disciplina registada</h3>
                                    <p>Use o botão "Nova Disciplina" para iniciar o registo curricular.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ========================================
         MODAL: CONFIGURAR DISCIPLINA
         ======================================== -->
    <div id="modal-disc" class="sige-modal">
        <div class="sige-modal-content">
            <div class="sige-modal-header">
                <h3 id="modal-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Nova Disciplina
                </h3>
                <button type="button" data-sige-act="fecharModal" data-sige-noargs class="sige-modal-close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            
            <form method="post">
                <input type="hidden" name="action_disciplina" value="1">
                <?php wp_nonce_field('sige_disciplina_action', '_sige_nonce'); ?>
                <input type="hidden" name="id_disciplina" id="id_disciplina">
                <input type="hidden" name="carga_horaria" id="carga_horaria" value="0">
                <input type="hidden" name="carga_horas" value="0">
                <input type="hidden" name="carga_minutos" value="0">
                
                <div class="sige-modal-body">
                    
                    <fieldset class="sige-fieldset">
                        <legend>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                            Ciclos de ensino
                        </legend>
                        <div class="sige-check-grid">
                            <label class="sige-check-label">
                                <input type="checkbox" name="ciclos[]" value="PreEscolar" id="chk_pre" onchange="ajustarForm()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="var(--color-brand-600)" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                Pré-Escolar
                            </label>
                            <label class="sige-check-label">
                                <input type="checkbox" name="ciclos[]" value="Primario1" id="chk_p1" onchange="ajustarForm()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="var(--color-success-700)" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                                EP1 (1ª-3ª)
                            </label>
                            <label class="sige-check-label">
                                <input type="checkbox" name="ciclos[]" value="Primario2" id="chk_p2" onchange="ajustarForm()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="var(--color-success-700)" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                                EP2 (4ª-6ª)
                            </label>
                            <label class="sige-check-label">
                                <input type="checkbox" name="ciclos[]" value="ESG1" id="chk_esg1" onchange="ajustarForm()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="var(--color-warning-700)" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                ESG1 (7ª-9ª)
                            </label>
                            <label class="sige-check-label">
                                <input type="checkbox" name="ciclos[]" value="ESG2" id="chk_esg2" onchange="ajustarForm()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="var(--color-info-700)" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                                ESG2 (10ª-12ª)
                            </label>
                        </div>
                    </fieldset>
                    
                    <fieldset class="sige-fieldset">
                        <legend>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                            Identificação
                        </legend>
                        <div class="sige-form-grid sige-form-grid-2">
                            <div class="sige-field span-2">
                                <label for="nome">Nome Completo *</label>
                                <input type="text" name="nome" id="nome" required placeholder="Ex: Ciências Naturais">
                            </div>
                            <div class="sige-field">
                                <label for="sigla">Sigla / Abreviatura *</label>
                                <input type="text" name="sigla" id="sigla" required placeholder="Ex: CN" class="sg-input-upper">
                            </div>
                            <div class="sige-field">
                                <label for="ordem">Ordem na Pauta</label>
                                <input type="number" name="ordem" id="ordem" value="1" min="1">
                            </div>
                        </div>
                        <small class="sg-hint">
                            A carga horária desta disciplina é definida na <strong>Matriz Curricular</strong>, por ciclo e classe.
                        </small>
                    </fieldset>
                    
                    <fieldset class="sige-fieldset" id="config-standard">
                        <legend>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                            Configurações académicas
                        </legend>
                        <div id="div-categoria">
                            <div class="sige-field">
                                <label for="categoria">Categoria Académica</label>
                                <select name="categoria" id="categoria">
                                    <option value="nuclear">Nuclear (Com Exame Final)</option>
                                    <option value="complementar">Prática / Complementar</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="box-esg2" class="sige-highlight-box" style="display: none;">
                            <div class="sige-field">
                                <label for="area_esg2">Área de Especialização (ESG2)</label>
                                <select name="area_esg2" id="area_esg2">
                                    <option value="comum">Tronco Comum (Todos os Alunos)</option>
                                    <option value="A">Área A - Comunicação e Linguagens</option>
                                    <option value="B">Área B - Ciências e Tecnologias</option>
                                    <option value="C">Área C - Ciências Sociais e Humanas</option>
                                </select>
                            </div>
                        </div>
                    </fieldset>
                    
                    <div class="sige-field">
                        <label for="chefe_grupo_id">Responsável pedagógico (opcional)</label>
                        <select name="chefe_grupo_id" id="chefe_grupo_id">
                            <option value="">-- Seleccionar Professor --</option>
                            <?php foreach($professores as $p): ?>
                                <option value="<?php echo esc_attr($p->id); ?>">
                                    <?php echo esc_html($p->nome_completo); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                </div>
                
                <div class="sige-modal-footer">
                    <button type="button" data-sige-act="fecharModal" data-sige-noargs class="sige-btn-modal sige-btn-cancel">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        Cancelar
                    </button>
                    <button type="submit" class="sige-btn-modal sige-btn-submit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Guardar Disciplina
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================
         MODAL: INDICADORES DE AVALIAÇÃO
         ======================================== -->
    <div id="modal-criterios" class="sige-modal">
        <div class="sige-modal-content sg-modal-550">
            <div class="sige-modal-header header-rose">
                <h3 id="crit-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                    Indicadores de avaliação
                </h3>
                <button type="button" data-sige-act="sigeCloseModal" data-sige-arg="#modal-criterios" class="sige-modal-close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            
            <div class="sige-modal-body">
                <div class="sige-criterio-input">
                    <input type="text" id="novo_crit_txt" placeholder="Descreva o indicador (ex: Salta à corda com coordenação)">
                    <button data-sige-act="addCriterio" data-sige-noargs class="sige-btn-modal sige-btn-submit btn-rose">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Adicionar
                    </button>
                </div>
                
                <div id="lista-indicadores" class="sige-criterio-list">
                    <!-- Conteúdo dinâmico -->
                </div>
            </div>
        </div>
    </div>

    <div id="sige-disc-confirm" class="sige-modal sige-disc-confirm-modal">
        <div class="sige-disc-confirm-box">
            <div class="sige-disc-confirm-head">
                <h3 id="sige-disc-confirm-title">Confirmar acção</h3>
            </div>
            <div class="sige-disc-confirm-body" id="sige-disc-confirm-message">Confirme para continuar.</div>
            <div class="sige-disc-confirm-actions">
                <button type="button" class="sige-btn-modal sige-btn-cancel" data-sige-act="sigeDiscConfirmClose" data-sige-args='[false]'>Cancelar</button>
                <button type="button" class="sige-btn-modal sige-btn-submit" id="sige-disc-confirm-primary" data-sige-act="sigeDiscConfirmClose" data-sige-args='[true]'>Confirmar</button>
            </div>
        </div>
    </div>

</div>

<script <?php echo sige_csp_script_attr(); ?>>
// ========================================
// VARIÁVEL GLOBAL
// ========================================
var current_did = null;
var sigeDiscNonce = '<?php echo wp_create_nonce("sige_del_disciplina"); ?>';

// [SEC] Helper to prevent XSS in innerHTML templates
function escapeHtml(t) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(t));
    return d.innerHTML;
}

function sigeOpenModal(selector) {
    var $modal = jQuery(selector);
    if (!$modal.length) return;
    if (!$modal.parent().is('body')) {
        $modal.appendTo(document.body);
    }
    jQuery('body').addClass('sige-modal-open');
    $modal.css('display', 'flex').hide().fadeIn(180);
}

function sigeCloseModal(selector) {
    var $modal = jQuery(selector);
    $modal.fadeOut(160, function(){
        if (!jQuery('.sige-modal:visible').length) {
            jQuery('body').removeClass('sige-modal-open');
        }
    });
}

var sigeDiscConfirmCallback = null;
function sigeDiscConfirm(title, message, primaryText, callback) {
    sigeDiscConfirmCallback = callback;
    jQuery('#sige-disc-confirm-title').text(title || 'Confirmar acção');
    jQuery('#sige-disc-confirm-message').html(message || 'Confirme para continuar.');
    jQuery('#sige-disc-confirm-primary').text(primaryText || 'Confirmar');
    sigeOpenModal('#sige-disc-confirm');
}
function sigeDiscConfirmClose(ok) {
    var cb = sigeDiscConfirmCallback;
    sigeDiscConfirmCallback = null;
    sigeCloseModal('#sige-disc-confirm');
    if (ok && typeof cb === 'function') cb();
}

// ========================================
// GESTÃO DE MODAIS
// ========================================
function novaDisciplina() {
    jQuery('#modal-disc form')[0].reset();
    jQuery('#id_disciplina').val('');
    jQuery('#modal-title').html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Nova Disciplina');
    sigeOpenModal('#modal-disc');
    jQuery('#modal-disc .sige-modal-body').scrollTop(0);
    ajustarForm();
}

function fecharModal() {
    sigeCloseModal('#modal-disc');
}

// ========================================
// AJUSTAR FORMULÁRIO DINAMICAMENTE
// ========================================
function ajustarForm() {
    var isPre = jQuery('#chk_pre').is(':checked');
    var isESG2 = jQuery('#chk_esg2').is(':checked');
    var outrosCiclos = jQuery('#chk_p1, #chk_p2, #chk_esg1, #chk_esg2').is(':checked');
    
    if(isESG2) {
        jQuery('#box-esg2').slideDown(200);
    } else {
        jQuery('#box-esg2').slideUp(200);
    }
    
    if(isPre && !outrosCiclos) {
        jQuery('#div-categoria').slideUp(200);
    } else {
        jQuery('#div-categoria').slideDown(200);
    }
}

// ========================================
// EDITAR DISCIPLINA
// ========================================
function editarDisciplina(data) {
    jQuery('#id_disciplina').val(data.id);
    jQuery('#nome').val(data.nome);
    jQuery('#sigla').val(data.sigla);
    jQuery('#categoria').val(data.categoria);
    jQuery('#ordem').val(data.ordem);
    jQuery('#chefe_grupo_id').val(data.chefe_grupo_id || '');
    jQuery('#area_esg2').val(data.area_esg2 || 'comum');
    
    var ciclos = JSON.parse(data.ciclos || '[]');
    jQuery('#chk_pre').prop('checked', ciclos.includes('PreEscolar'));
    jQuery('#chk_p1').prop('checked', ciclos.includes('Primario1'));
    jQuery('#chk_p2').prop('checked', ciclos.includes('Primario2'));
    jQuery('#chk_esg1').prop('checked', ciclos.includes('ESG1'));
    jQuery('#chk_esg2').prop('checked', ciclos.includes('ESG2'));
    
    jQuery('#modal-title').html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Editar disciplina');
    ajustarForm();
    sigeOpenModal('#modal-disc');
    jQuery('#modal-disc .sige-modal-body').scrollTop(0);
}

// ========================================
// GERIR CRITÉRIOS/INDICADORES
// ========================================
function gerirCriterios(id, nome) {
    current_did = id;
    jQuery('#crit-title').html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg> Indicadores: <strong>' + nome + '</strong>');
    jQuery('#lista-indicadores').html('<p class="sg-empty-msg">Carregando indicadores...</p>');
    sigeOpenModal('#modal-criterios');
    
    jQuery.post(ajaxurl, {
        action: 'sige_listar_criterios',
        disciplina_id: id
    }, function(res) {
        var html = '';
        
        if(res.data && res.data.length > 0) {
            res.data.forEach(function(c) {
                html += `
                    <div class="sige-criterio-item">
                        <span class="sige-criterio-text">${escapeHtml(c.criterio)}</span>
                        <button data-sige-act="delCriterio" data-sige-args='[${c.id}]' data-sige-self class="sige-criterio-remove" title="Remover">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                `;
            });
        } else {
            html = `
                <div class="sige-empty-state sg-empty-pad">
                    <div class="sige-empty-icon sg-empty-ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="sg-empty-svg"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                    </div>
                    <h3 class="sg-empty-h3">Nenhum indicador definido</h3>
                    <p>Adicione os critérios de avaliação para esta área de desenvolvimento</p>
                </div>
            `;
        }
        
        jQuery('#lista-indicadores').html(html);
    });
}

// ========================================
// ADICIONAR CRITÉRIO
// ========================================
function addCriterio() {
    var txt = jQuery('#novo_crit_txt').val().trim();
    
    if(!txt) {
        sigeDiscConfirm('Indicador em falta', 'Digite a descrição do indicador antes de adicionar.', 'Entendido', function(){});
        return;
    }
    
    jQuery.post(ajaxurl, {
        action: 'sige_salvar_criterio',
        disciplina_id: current_did,
        criterio: txt
    }, function(res) {
        if(res.success) {
            jQuery('#novo_crit_txt').val('');
            gerirCriterios(current_did, jQuery('#crit-title').text().replace('Indicadores: ', ''));
        } else {
            sigeDiscConfirm('Não foi possível adicionar', 'O indicador não foi gravado. Tente novamente.', 'Entendido', function(){});
        }
    });
}

// ========================================
// REMOVER CRITÉRIO
// ========================================
function delCriterio(id, btn) {
    sigeDiscConfirm('Remover indicador?', 'Este indicador será retirado da disciplina seleccionada.', 'Remover', function(){
        jQuery.post(ajaxurl, {
            action: 'sige_remover_criterio',
            id: id
        }, function(res) {
            if(res.success) {
                jQuery(btn).closest('.sige-criterio-item').fadeOut(200, function() {
                    jQuery(this).remove();
                    
                    if(jQuery('.sige-criterio-item').length === 0) {
                        jQuery('#lista-indicadores').html(`
                            <div class="sige-empty-state sg-empty-pad">
                                <div class="sige-empty-icon sg-empty-ico">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="sg-empty-svg"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                                </div>
                                <h3 class="sg-empty-h3">Nenhum indicador definido</h3>
                                <p>Adicione os critérios de avaliação para esta área de desenvolvimento</p>
                            </div>
                        `);
                    }
                });
            } else {
                sigeDiscConfirm('Não foi possível remover', 'O indicador não foi removido. Tente novamente.', 'Entendido', function(){});
            }
        });
    });
}

// ========================================
// APAGAR DISCIPLINA
// ========================================
async function apagarDisciplina(id, nome) {
    var ok = await sigeUi.confirm({
        titulo: 'Remover disciplina',
        texto: 'A disciplina "' + nome + '" será removida. Isto pode afectar pautas de avaliação, horários de turmas e matrizes curriculares onde ela seja usada.',
        confirmar: 'Remover',
        perigo: true
    });
    if (ok) {
        window.location.href = '?page=sige-app&view=disciplinas&del=' + id + '&_nonce=' + sigeDiscNonce;
    }
}

// ========================================
// FECHAR MODAL COM ESC
// ========================================
jQuery(document).on('keydown', function(e) {
    if (e.key === 'Escape') {
        jQuery('.sige-modal:visible').each(function(){ sigeCloseModal('#' + this.id); });
    }
});

// ========================================
// FECHAR MODAL AO CLICAR FORA
// ========================================
jQuery(document).on('click', '.sige-modal', function(e) {
    if (e.target === this && this.id !== 'sige-disc-confirm') {
        sigeCloseModal('#' + this.id);
    }
});

// ========================================
// ENTER PARA ADICIONAR CRITÉRIO
// ========================================
jQuery('#novo_crit_txt').on('keypress', function(e) {
    if(e.which === 13) {
        e.preventDefault();
        addCriterio();
    }
});
</script>
