<?php
/**
 * SIGE SoftGenial - Gestão de Turmas
 *
 * v2.1 - Abril 2026
 * - Paleta alinhada com sg-* Design System (Navy/Amber)
 * - ABSPATH guard no topo, removido DateTimeZone wrapper
 * - XSS fix nos templates JS
 *
 * TEMPLATES DE IMPRESSÃO PRESERVADOS:
 * - exportarExcel(), hImprimir(), imprimirListaAlunos()
 */

if (!defined('ABSPATH')) exit;

// Guard de acesso - Secretaria Académica
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard_allows(
    ['academico.turmas_ver','academico.turmas_gerir'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente']
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
$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;

// ========================================
// 1. CARREGAR DADOS DA ESCOLA
// ========================================
$escola = sige_get_escola_perfil(); 

$logo_final = SIGE_URL . 'assets/img/avatar-default.svg';
if (!empty($escola->logo_docs_url)) {
    $logo_final = $escola->logo_docs_url;
} elseif (!empty($escola->logo_sistema_url)) {
    $logo_final = $escola->logo_sistema_url;
}

// ========================================
// 2. CARREGAR TURMAS E PROFESSORES
// ========================================
$professores = $wpdb->get_results($wpdb->prepare("
    SELECT id, nome_completo 
    FROM {$wpdb->prefix}sige_professores 
    WHERE status_ativo = 1 AND escola_id = %d
    ORDER BY nome_completo ASC
", sige_get_escola_id()));

$turmas = $wpdb->get_results($wpdb->prepare("
    SELECT t.*, p.nome_completo as director_nome 
    FROM {$wpdb->prefix}sige_turmas t 
    LEFT JOIN {$wpdb->prefix}sige_professores p ON t.director_turma_id = p.id 
    WHERE t.escola_id = %d
    ORDER BY t.classe ASC, t.nome ASC
", sige_get_escola_id()));

// ========================================
// ANO LECTIVO ACTIVO
// ========================================
$ano_lectivo_ativo = (int)wp_date('Y');
if (function_exists('sige_fin_get_ano_letivo_master')) {
    $tmp = (int)sige_fin_get_ano_letivo_master();
    if ($tmp > 2000) $ano_lectivo_ativo = $tmp;
} else {
    $tC = $wpdb->prefix . 'sige_config';
    $tmp = (int)$wpdb->get_var($wpdb->prepare("SELECT ano_lectivo FROM {$tC} WHERE escola_id = %d ORDER BY id DESC LIMIT 1", $escola_id));
    if ($tmp > 2000) $ano_lectivo_ativo = $tmp;
}

if (!function_exists('sige_contar_matriculados_turma')) {
    function sige_contar_matriculados_turma($turma_id, $ano_lectivo_ativo) {
        global $wpdb;
        $tM = $wpdb->prefix . 'sige_matriculas';
        $turma_id = (int)$turma_id;
        $ano = (int)$ano_lectivo_ativo;
        static $_col_ano = null;
        if ($_col_ano === null) {
            $has_c = $wpdb->get_var("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tM}' AND COLUMN_NAME = 'ano_lectivo'");
            $_col_ano = ((int)$has_c > 0) ? 'ano_lectivo' : 'ano_letivo';
        }
        $r = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tM}
             WHERE turma_id = %d
               AND `{$_col_ano}` = %d
               AND aluno_id > 0
               AND (status_matricula = 'activa' OR status_matricula IS NULL)",
            $turma_id, $ano
        ));
        return (int)$r;
    }
}

$classes_list = [
    '2º/3º Ano', '4º Ano', 'Pré-primário', 
    '1ª', '2ª', '3ª', '4ª', '5ª', '6ª', '7ª', '8ª', '9ª', '10ª',
    '11ª A', '11ª B', '11ª C', '12ª A', '12ª B', '12ª C'
];

// ========================================
// 3. CALCULAR ESTATÍSTICAS
// ========================================
$total_turmas = count($turmas);
$total_vagas = 0;
$turmas_manha = 0;
$turmas_tarde = 0;
$turmas_noite = 0;

foreach($turmas as $t) {
    $total_vagas += intval($t->capacidade);
    switch($t->turno) {
        case 'Manhã': $turmas_manha++; break;
        case 'Tarde': $turmas_tarde++; break;
        case 'Noite': $turmas_noite++; break;
    }
}

if (!function_exists('get_turno_class')) {
    function get_turno_class($turno) {
        $turno_normalizado = strtolower(str_replace(['ã', 'á'], ['a', 'a'], $turno));
        return "sige-turno-$turno_normalizado";
    }
}
?>

<style>
/* ========================================
   SIGE TURMAS - Design System v2.1
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
    
    --sige-amber: var(--color-warning-500);
    --sige-rose: var(--color-danger-500);
    --sige-orange: var(--color-warning-600);
    
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
.sige-turmas-page {
    font-family: var(--sige-font-body);
    color: var(--sige-slate-800);
    background: var(--sige-slate-50);
    min-height: 100vh;
    padding:var(--space-6);
}

.sige-turmas-page * {
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

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

/* ========================================
   HERO HEADER
   ======================================== */
.sige-hero {
    position: relative;
    overflow: hidden;
    border-radius: var(--sige-radius-2xl);
    padding:var(--space-8);
    margin-bottom: 24px;
    background: linear-gradient(135deg, var(--sige-navy) 0%, var(--sige-navy-light) 50%, var(--sige-primary-dark) 100%);
    color: var(--color-white);
    box-shadow:var(--shadow-xs);
    animation: fadeInUp 0.5s ease-out;
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
    background: radial-gradient(circle, rgba(20, 184, 166, 0.2) 0%, transparent 70%);
}

.sige-hero-content {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap:var(--space-5);
    flex-wrap: wrap;
}

.sige-hero-text {
    flex: 1;
    min-width: 280px;
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
    margin:0 0 var(--space-2);
    color: var(--color-white);
}

.sige-hero-subtitle {
    font-size: 0.95rem;
    color: rgba(255,255,255,0.8);
    margin:0 0 var(--space-4);
}

.sige-hero-meta {
    display: inline-flex;
    align-items: center;
    gap:var(--space-2);
    padding: 8px 14px;
    border-radius:var(--radius-pill);
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.12);
    font-size: 0.75rem;
    font-weight:600;
}

.sige-hero-meta svg {
    width: 14px;
    height: 14px;
    opacity: 0.8;
}

.sige-btn-hero {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 28px;
    border-radius: var(--sige-radius);
    background: var(--color-white);
    color: var(--sige-navy);
    font-family: var(--sige-font-body);
    font-weight:700;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border: none;
    cursor: pointer;
    transition: all var(--duration-normal) ease;
    box-shadow:var(--shadow-xs);
}

.sige-btn-hero svg {
    width: 18px;
    height: 18px;
}

.sige-btn-hero:hover {
    transform: translateY(-2px);
    box-shadow:var(--shadow-sm);
}

/* ========================================
   STATS GRID
   ======================================== */
.sige-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap:var(--space-4);
    margin-bottom: 24px;
    animation: fadeInUp 0.5s ease-out 0.1s both;
}

.sige-stat-card {
    background: var(--color-white);
    border-radius: var(--sige-radius-lg);
    padding:var(--space-5);
    border: 1px solid var(--sige-slate-100);
    box-shadow:var(--shadow-xs);
    display: flex;
    align-items: center;
    gap: 14px;
    transition: all var(--duration-normal) ease;
}

.sige-stat-card:hover {
    transform: translateY(-3px);
    box-shadow:var(--shadow-xs);
}

.sige-stat-card.highlight {
    background: linear-gradient(135deg, var(--sige-navy) 0%, var(--sige-navy-light) 100%);
    color: var(--color-white);
    border: none;
}

.sige-stat-icon {
    width: 44px;
    height: 44px;
    border-radius: var(--sige-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.sige-stat-icon svg {
    width: 22px;
    height: 22px;
}

.sige-stat-icon.icon-primary { background: rgba(63, 81, 181, 0.1); }
.sige-stat-icon.icon-primary svg { stroke: var(--sige-primary); }

.sige-stat-card.highlight .sige-stat-icon {
    background: rgba(255,255,255,0.15);
}
.sige-stat-card.highlight .sige-stat-icon svg {
    stroke: var(--color-white);
}

.sige-stat-icon.icon-amber { background: rgba(245, 158, 11, 0.1); border-left: 3px solid var(--sige-amber); }
.sige-stat-icon.icon-amber svg { stroke: var(--sige-amber); }

.sige-stat-icon.icon-orange { background: rgba(249, 115, 22, 0.1); border-left: 3px solid var(--sige-orange); }
.sige-stat-icon.icon-orange svg { stroke: var(--sige-orange); }

.sige-stat-icon.icon-navy { background: rgba(15, 23, 42, 0.08); border-left: 3px solid var(--sige-navy); }
.sige-stat-icon.icon-navy svg { stroke: var(--sige-navy); }

.sige-stat-info {
    flex: 1;
    min-width: 0;
}

.sige-stat-label {
    font-size: 0.75rem;
    font-weight:600;
    color: var(--sige-slate-500);
    margin-bottom: 2px;
}

.sige-stat-card.highlight .sige-stat-label {
    color: rgba(255,255,255,0.7);
}

.sige-stat-value {
    font-family: var(--sige-font-display);
    font-size: 1.5rem;
    font-weight:700;
    color: var(--sige-slate-900);
}

.sige-stat-card.highlight .sige-stat-value {
    color: var(--color-white);
}

/* ========================================
   TOOLBAR
   ======================================== */
.sige-toolbar {
    background: var(--color-white);
    border-radius: var(--sige-radius-lg);
    padding:var(--space-4) var(--space-5);
    border: 1px solid var(--sige-slate-100);
    box-shadow:var(--shadow-xs);
    display: flex;
    gap: 14px;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 24px;
    animation: fadeInUp 0.5s ease-out 0.15s both;
}

.sige-search-box {
    flex: 1;
    min-width: 250px;
    position: relative;
}

.sige-search-box input {
    width: 100%;
    height: 44px;
    padding: 0 16px 0 44px;
    border: 1px solid var(--sige-slate-200);
    border-radius: var(--sige-radius);
    font-family: var(--sige-font-body);
    font-size: 0.875rem;
    color: var(--sige-slate-800);
    transition: all var(--duration-normal) ease;
}

.sige-search-box input::placeholder {
    color: var(--sige-slate-400);
}

.sige-search-box input:focus {
    outline: none;
    border-color: var(--sige-primary);
    box-shadow:var(--shadow-xs);
}

.sige-search-box svg {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    stroke: var(--sige-slate-400);
    pointer-events: none;
}

.sige-filter-select {
    height: 44px;
    padding: 0 36px 0 14px;
    border: 1px solid var(--sige-slate-200);
    border-radius: var(--sige-radius);
    font-family: var(--sige-font-body);
    font-size: 0.875rem;
    font-weight:600;
    color: var(--sige-slate-700);
    background: var(--color-white) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 12px center;
    cursor: pointer;
    transition: all var(--duration-normal) ease;
    appearance: none;
}

.sige-filter-select:focus {
    outline: none;
    border-color: var(--sige-primary);
    box-shadow:var(--shadow-xs);
}

.sige-btn-toolbar {
    height: 44px;
    padding: 0 18px;
    border-radius: var(--sige-radius);
    border: 1px solid var(--sige-slate-200);
    background: var(--color-white);
    font-family: var(--sige-font-body);
    font-weight:600;
    font-size: 0.8rem;
    color: var(--sige-slate-600);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap:var(--space-2);
    transition: all var(--duration-normal) ease;
}

.sige-btn-toolbar svg {
    width: 16px;
    height: 16px;
}

.sige-btn-toolbar:hover {
    background: var(--sige-slate-50);
    border-color: var(--sige-primary);
    color: var(--sige-primary);
}

.sige-btn-toolbar.btn-excel {
    color: var(--sige-success);
    border-color: var(--sige-success);
}

.sige-btn-toolbar.btn-excel:hover {
    background: var(--sige-success-light);
}

/* ========================================
   TURMA GRID
   ======================================== */
.sige-turma-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap:var(--space-5);
    animation: fadeInUp 0.5s ease-out 0.2s both;
}

/* ========================================
   TURMA CARD
   ======================================== */
.sige-turma-card {
    background: var(--color-white);
    border-radius: var(--sige-radius-lg);
    padding:var(--space-6);
    border: 1px solid var(--sige-slate-100);
    box-shadow:var(--shadow-xs);
    border-top: 4px solid var(--sige-slate-300);
    transition: all 0.25s ease;
    position: relative;
}

.sige-turma-card:hover {
    transform: translateY(-4px);
    box-shadow:var(--shadow-xs);
}

/* Cores por turno */
.sige-turno-manha { border-top-color: var(--sige-amber); }
.sige-turno-tarde { border-top-color: var(--sige-orange); }
.sige-turno-noite { border-top-color: var(--sige-navy); }

.sige-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--sige-slate-100);
    gap:var(--space-3);
}

.sige-card-title h3 {
    font-family: var(--sige-font-display);
    font-size: 1.15rem;
    font-weight:700;
    color: var(--sige-slate-900);
    margin:0 0 var(--space-2);
}

.sige-sala-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    border-radius: var(--sige-radius);
    background: var(--sige-slate-50);
    border: 1px solid var(--sige-slate-200);
    font-size: 0.7rem;
    font-weight:600;
    color: var(--sige-slate-600);
}

.sige-sala-badge svg {
    width: 12px;
    height: 12px;
}

.sige-classe-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius:var(--radius-pill);
    background: linear-gradient(135deg, rgba(63, 81, 181, 0.1), rgba(63, 81, 181, 0.05));
    border: 1px solid rgba(63, 81, 181, 0.2);
    font-size: 0.7rem;
    font-weight:700;
    color: var(--sige-primary-dark);
    white-space: nowrap;
}

.sige-card-body {
    font-size: 0.85rem;
    color: var(--sige-slate-600);
}

.sige-card-info {
    display: flex;
    align-items: center;
    gap:var(--space-2);
    margin-bottom: 12px;
}

.sige-card-info svg {
    width: 16px;
    height: 16px;
    stroke: var(--sige-slate-400);
    flex-shrink: 0;
}

.sige-card-info strong {
    color: var(--sige-slate-800);
    font-weight:600;
}

/* Ocupação */
.sige-ocupacao {
    margin:var(--space-4) 0;
    padding: 14px;
    background: var(--sige-slate-50);
    border-radius: var(--sige-radius);
}

.sige-ocupacao-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 0.7rem;
    font-weight:600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--sige-slate-500);
}

.sige-ocupacao-bar {
    height: 8px;
    background: var(--sige-slate-200);
    border-radius:var(--radius-sm);
    overflow: hidden;
    position: relative;
}

.sige-ocupacao-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--sige-primary-light), var(--sige-success));
    border-radius:var(--radius-sm);
    transition: width 0.6s ease;
    position: relative;
}

.sige-ocupacao-fill::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    animation: shimmer 2s infinite;
}

/* Card Actions */
.sige-card-actions {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap:var(--space-2);
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--sige-slate-100);
}

.sige-btn-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap:var(--space-1);
    padding: 10px 6px;
    border-radius: var(--sige-radius);
    border: 1px solid var(--sige-slate-200);
    background: var(--color-white);
    font-size: 0.65rem;
    font-weight:600;
    color: var(--sige-slate-600);
    cursor: pointer;
    transition: all var(--duration-normal) ease;
}

.sige-btn-card svg {
    width: 18px;
    height: 18px;
    stroke: var(--sige-slate-500);
    transition: stroke var(--duration-normal) ease;
}

.sige-btn-card:hover {
    background: var(--sige-slate-50);
    border-color: var(--sige-primary);
    color: var(--sige-primary);
}

.sige-btn-card:hover svg {
    stroke: var(--sige-primary);
}

.sige-btn-card.btn-danger:hover {
    background: var(--sige-error-light);
    border-color: var(--sige-error);
    color: var(--sige-error);
}

.sige-btn-card.btn-danger:hover svg {
    stroke: var(--sige-error);
}

/* ========================================
   EMPTY STATE
   ======================================== */
.sige-empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 40px;
    background: var(--color-white);
    border-radius: var(--sige-radius-xl);
    border: 1px solid var(--sige-slate-100);
}

.sige-empty-icon {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: var(--sige-slate-100);
    display: flex;
    align-items: center;
    justify-content: center;
    margin:0 auto var(--space-6);
}

.sige-empty-icon svg {
    width: 48px;
    height: 48px;
    stroke: var(--sige-slate-300);
}

.sige-empty-state h3 {
    font-family: var(--sige-font-display);
    font-size: 1.25rem;
    font-weight:600;
    color: var(--sige-slate-600);
    margin:0 0 var(--space-2);
}

.sige-empty-state p {
    color: var(--sige-slate-400);
    font-size: 0.9rem;
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
    max-width: 560px;
    background: var(--color-white);
    border-radius: var(--sige-radius-xl);
    box-shadow:var(--shadow-xs);
    animation: modalSlideIn 0.25s ease;
    overflow: hidden;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}

.sige-modal-content.modal-wide {
    max-width: 720px;
}

.sige-modal-content.modal-horario {
    max-width: 920px;
}

.sige-modal-content.modal-alunos {
    max-width: 900px;
}

.sige-modal-header {
    padding:var(--space-5) var(--space-6);
    background: linear-gradient(135deg, var(--color-black), var(--color-ink-900));
    color: var(--color-white);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap:var(--space-4);
}

.sige-modal-header h2 {
    font-family: var(--sige-font-display);
    font-size: 1.15rem;
    font-weight:600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--color-white);
}

.sige-modal-header h2 svg {
    width: 22px;
    height: 22px;
    stroke: var(--color-white);
}

.sige-modal-header p {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.7);
    margin:var(--space-1) 0 0;
}

.sige-modal-close {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    border: none;
    color: var(--color-white);
    font-size: 1.25rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background var(--duration-normal) ease;
    flex-shrink: 0;
}

.sige-modal-close:hover {
    background: rgba(255,255,255,0.25);
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

/* Form Fields */
.sige-field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap:var(--space-4);
    margin-bottom: 16px;
}

.sige-field-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.sige-field-group label {
    font-size: 0.75rem;
    font-weight:600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--sige-slate-600);
}

.sige-field-group input,
.sige-field-group select {
    height: 46px;
    padding: 0 14px;
    border: 1px solid var(--sige-slate-200);
    border-radius: var(--sige-radius);
    font-family: var(--sige-font-body);
    font-size: 0.9rem;
    color: var(--sige-slate-800);
    background: var(--color-white);
    transition: all var(--duration-normal) ease;
}

.sige-field-group input:focus,
.sige-field-group select:focus {
    outline: none;
    border-color: var(--sige-primary);
    box-shadow:var(--shadow-xs);
}

/* Buttons */
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

.sige-btn-submit:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.sige-btn-success {
    background: linear-gradient(135deg, var(--sige-success), var(--color-success-500));
    color: var(--color-white);
}

/* Alunos Modal */
.sige-alunos-summary {
    display: flex;
    gap:var(--space-3);
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.sige-alunos-pill {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius:var(--radius-pill);
    font-size: 0.75rem;
    font-weight:600;
}

.sige-alunos-pill.pill-total {
    background: rgba(63, 81, 181, 0.1);
    color: var(--sige-primary-dark);
}

.sige-alunos-pill.pill-masc {
    background: var(--sige-info-light);
    color: var(--sg-theme-primary,var(--color-brand-500));
}

.sige-alunos-pill.pill-fem {
    background: rgba(244, 63, 94, 0.1);
    color: var(--color-danger-700);
}

.sige-alunos-table-wrap {
    max-height: 420px;
    overflow-y: auto;
    border: 1px solid var(--sige-slate-200);
    border-radius: var(--sige-radius);
}

.sige-alunos-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
}

.sige-alunos-table thead th {
    padding: 12px 14px;
    text-align: left;
    font-size: 0.7rem;
    font-weight:600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-white) !important;
    background: var(--color-black) !important;
    position: sticky;
    top: 0;
    z-index: 2;
}

.sige-alunos-table tbody tr {
    border-bottom: 1px solid var(--sige-slate-100);
}

.sige-alunos-table tbody tr:nth-child(even) {
    background: var(--sige-slate-50);
}

.sige-alunos-table tbody tr:hover {
    background: rgba(63, 81, 181, 0.04);
}

.sige-alunos-table tbody td {
    padding: 10px 14px;
    vertical-align: middle;
}

/* Horário Modal */
.sige-horario-controls {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.sige-horario-controls label {
    font-size: 0.75rem;
    font-weight:600;
    color: var(--sige-slate-600);
}

.sige-horario-controls select {
    padding:var(--space-2) var(--space-3);
    border: 1px solid var(--sige-slate-200);
    border-radius: var(--sige-radius);
    font-weight:600;
}

.sige-btn-horario {
    padding: 8px 14px;
    border-radius: var(--sige-radius);
    border: 1px solid;
    background: var(--color-white);
    font-weight:600;
    font-size: 0.8rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all var(--duration-normal) ease;
}

.sige-btn-horario svg {
    width: 14px;
    height: 14px;
}

.sige-btn-horario.btn-limpar {
    border-color: var(--sige-error);
    color: var(--sige-error);
}

.sige-btn-horario.btn-limpar:hover {
    background: var(--sige-error-light);
}

.sige-btn-horario.btn-imprimir {
    border-color: var(--sige-info);
    color: var(--sige-info);
}

.sige-btn-horario.btn-imprimir:hover {
    background: var(--sige-info-light);
}

.sige-horario-grid-wrap {
    overflow-x: auto;
    border: 1px solid var(--sige-slate-200);
    border-radius: var(--sige-radius);
    padding:var(--space-3);
    background: var(--sige-slate-50);
    min-height: 200px;
}

/* Docentes Lista */
.sige-disciplinas-lista {
    display: flex;
    flex-direction: column;
    gap:var(--space-3);
}

.sige-disc-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap:var(--space-4);
    padding: 14px;
    background: var(--sige-slate-50);
    border-radius: var(--sige-radius);
    border: 1px solid var(--sige-slate-100);
}

.sige-disc-item .disc-nome {
    font-weight:600;
    color: var(--sige-slate-800);
    min-width: 140px;
}

.sige-disc-item select {
    flex: 1;
    max-width: 280px;
    padding:var(--space-2) var(--space-3);
    border: 1px solid var(--sige-slate-200);
    border-radius: var(--sige-radius);
    font-size: 0.85rem;
}

/* ========================================
   PRINT AREA (HIDDEN)
   ======================================== */
#area-impressao {
    display: none;
}

/* ========================================
   RESPONSIVE
   ======================================== */
@media (max-width: 900px) {
    .sige-turmas-page { padding:var(--space-4); }
    .sige-hero { padding:var(--space-6); }
    .sige-hero-content { flex-direction: column; }
    .sige-btn-hero { width: 100%; justify-content: center; }
    .sige-turma-grid { grid-template-columns: 1fr; }
    .sige-field-row { grid-template-columns: 1fr; }
    .sige-modal-content { margin:var(--space-3); }
}

@media (max-width: 640px) {
    .sige-turmas-page { padding:var(--space-3); }
    .sige-hero { padding:var(--space-5); border-radius: var(--sige-radius-lg); }
    .sige-hero h1 { font-size: 1.5rem; }
    .sige-stats-grid { grid-template-columns: 1fr 1fr; }
    .sige-toolbar { flex-direction: column; }
    .sige-search-box { min-width: 100%; }
    .sige-filter-select { width: 100%; }
    .sige-card-actions { grid-template-columns: repeat(3, 1fr); }
    .sige-modal-footer { flex-direction: column; }
    .sige-modal-footer button { width: 100%; }
}


/* ========================================
   TURMAS - Harmonia Visual V2 (referência: Painel Principal aprovado)
   ======================================== */
body.sige-admin-app.sige-view-turmas .sg-product-page-head{display:none!important;}
body.sige-admin-app.sige-view-turmas .sg-app-page{max-width:none!important;width:100%!important;padding-top:0!important;}
body.sige-admin-app.sige-view-turmas .sg-app-content{padding-left:30px!important;padding-right:30px!important;}
body.sige-admin-app.sige-view-turmas .sg-app-page > .wrap.sige-turmas-page{margin:0!important;max-width:none!important;width:100%!important;padding:0!important;background:transparent!important;min-height:auto!important;font-family:'Inter',system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif!important;}
.sige-turmas-page{--sgv2-purple:var(--color-brand-400);--sgv2-purple-dark:var(--color-brand-600);--sgv2-ink:var(--color-black);--sgv2-muted:var(--color-slate-700);--sgv2-soft:var(--color-brand-50);--sgv2-line:rgba(28,32,54,.08);display:grid!important;gap:18px!important;}
.sige-turmas-page svg{stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sige-turmas-page .sige-hero{position:relative!important;overflow:hidden!important;display:block!important;padding:var(--space-6) var(--space-8)!important;margin:0!important;border-radius:var(--radius-xl)!important;background:linear-gradient(135deg,var(--color-white) 0%,var(--color-white) 54%,var(--color-brand-50) 100%)!important;color:var(--sgv2-ink)!important;border:1px solid rgba(109,93,252,.10)!important;box-shadow:var(--shadow-md);animation:none!important;}
.sige-turmas-page .sige-hero:before,.sige-turmas-page .sige-hero:after{display:none!important;}
.sige-turmas-page .sige-hero-content{display:block!important;position:relative!important;z-index:2!important;}
.sige-turmas-page .sige-hero-text{max-width:720px!important;min-width:0!important;}
.sige-turmas-page .sige-hero-kicker{display:flex!important;align-items:center!important;gap:var(--space-2)!important;padding:0!important;border-radius:0!important;background:transparent!important;color:var(--sgv2-purple)!important;font-size:12px!important;font-weight:700!important;text-transform:uppercase!important;letter-spacing:.12em!important;margin:0 0 var(--space-3)!important;}
.sige-turmas-page .sige-hero-kicker svg{width:18px!important;height:18px!important;}
.sige-turmas-page .sige-hero h1{margin:0!important;font-family:'Inter',system-ui,sans-serif!important;font-size:31px!important;line-height:1.08!important;font-weight:700!important;letter-spacing:-.045em!important;color:var(--sgv2-ink)!important;}
.sige-turmas-page .sige-hero-subtitle{max-width:650px!important;margin:var(--space-3) 0 0!important;font-size:15px!important;line-height:1.65!important;color:var(--sgv2-muted)!important;font-weight:500!important;}
.sige-turmas-page .sige-hero-meta{display:none!important;}
.sige-turmas-page .sige-hero-actions{display:flex!important;flex-wrap:wrap!important;gap:var(--space-3)!important;margin-top:24px!important;}
.sige-turmas-page .sige-btn-hero{min-height:46px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;border-radius:var(--radius-md)!important;padding:0 22px!important;font-size:var(--fs-base)!important;font-weight:700!important;text-decoration:none!important;border:1px solid transparent!important;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease!important;background:linear-gradient(135deg,var(--color-brand-400),var(--color-brand-600))!important;color:var(--color-white)!important;box-shadow:var(--shadow-md);text-transform:none!important;letter-spacing:0!important;}
.sige-turmas-page .sige-btn-hero svg{width:18px!important;height:18px!important;}
.sige-turmas-page .sige-btn-hero-secondary{background:var(--color-white)!important;color:var(--color-ink-900)!important;border-color:var(--color-ink-100)!important;box-shadow:var(--shadow-sm);}
.sige-turmas-page .sige-btn-hero:hover{transform:translateY(-1px)!important;box-shadow:var(--shadow-md);}
/* Herói compacto (v12.23.0): faixa única, sem ilustração. Regras de arte
   (.sige-hero-art/.sige-hero-*/.sige-school-*) removidas com o respectivo HTML. */
.sige-turmas-page .sige-stats-grid{display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr))!important;gap:var(--space-4)!important;margin:0!important;animation:none!important;}
.sige-turmas-page .sige-stat-card,.sige-turmas-page .sige-stat-card.highlight{position:relative!important;overflow:hidden!important;display:grid!important;grid-template-columns:auto minmax(0,1fr)!important;gap:var(--space-4)!important;align-items:center!important;min-height:104px!important;padding:18px 20px!important;border-radius:var(--radius-xl)!important;background:var(--color-white)!important;border:1px solid rgba(28,32,54,.08)!important;box-shadow:var(--shadow-md);color:var(--sgv2-ink)!important;animation:none!important;}
.sige-turmas-page .sige-stat-card:after{content:""!important;position:absolute!important;right:-28px!important;top:-34px!important;width:92px!important;height:92px!important;border-radius:50%!important;background:var(--kpi-soft,var(--color-brand-50))!important;}
.sige-turmas-page .sige-stat-icon{width:52px!important;height:52px!important;border-radius:var(--radius-lg)!important;display:flex!important;align-items:center!important;justify-content:center!important;background:var(--kpi-soft,var(--color-brand-50))!important;color:var(--kpi-color,var(--sgv2-purple))!important;position:relative!important;z-index:1!important;border-left:0!important;}
.sige-turmas-page .sige-stat-icon svg{width:24px!important;height:24px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;}
.sige-turmas-page .sige-stat-icon.icon-primary{--kpi-color:var(--color-info-500);--kpi-soft:var(--color-info-50);}
.sige-turmas-page .sige-stat-icon.icon-amber{--kpi-color:var(--color-warning-500);--kpi-soft:var(--color-warning-100);}
.sige-turmas-page .sige-stat-icon.icon-orange{--kpi-color:var(--color-warning-600);--kpi-soft:var(--color-warning-100);}
.sige-turmas-page .sige-stat-icon.icon-navy{--kpi-color:var(--color-brand-500);--kpi-soft:var(--color-brand-50);}
.sige-turmas-page .sige-stat-info{position:relative!important;z-index:1!important;min-width:0!important;}
.sige-turmas-page .sige-stat-label{font-size:var(--fs-sm)!important;font-weight:600!important;color:var(--color-slate-600)!important;margin-bottom:6px!important;text-transform:none!important;letter-spacing:0!important;}
.sige-turmas-page .sige-stat-value{font-size:27px!important;line-height:1!important;font-weight:700!important;letter-spacing:-.03em!important;color:var(--color-black)!important;}
.sige-turmas-page .sige-toolbar{background:var(--color-white)!important;border:1px solid rgba(30,34,60,.08)!important;border-radius:var(--radius-xl)!important;box-shadow:var(--shadow-md);padding:20px 22px!important;display:grid!important;grid-template-columns:minmax(280px,1fr) minmax(210px,.28fr) auto auto!important;gap:var(--space-3)!important;align-items:center!important;margin:0!important;animation:none!important;}
.sige-turmas-page .sige-search-box{min-width:0!important;}
.sige-turmas-page .sige-search-box input,.sige-turmas-page .sige-filter-select{height:46px!important;border-radius:var(--radius-md)!important;background:var(--color-white)!important;border:1px solid var(--color-slate-100)!important;color:var(--color-ink-800)!important;font-size:var(--fs-sm)!important;font-weight:600!important;}
.sige-turmas-page .sige-search-box input:focus,.sige-turmas-page .sige-filter-select:focus{border-color:var(--color-info-100)!important;box-shadow:var(--shadow-xs);outline:none!important;}
.sige-turmas-page .sige-search-box svg{stroke:var(--color-ink-400)!important;color:var(--color-ink-400)!important;}
.sige-turmas-page .sige-btn-toolbar{min-height:46px!important;height:46px!important;border-radius:var(--radius-md)!important;padding:0 17px!important;font-size:var(--fs-sm)!important;font-weight:700!important;border:1px solid var(--color-slate-100)!important;background:var(--color-white)!important;color:var(--color-ink-800)!important;box-shadow:none!important;text-transform:none!important;letter-spacing:0!important;}
.sige-turmas-page .sige-btn-toolbar.btn-excel{color:var(--color-success-500)!important;border-color:var(--color-success-100)!important;background:var(--color-success-50)!important;}
.sige-turmas-page .sige-btn-toolbar:hover{border-color:var(--color-info-100)!important;color:var(--sgv2-purple)!important;background:var(--color-slate-50)!important;}
.sige-turmas-page .sige-turma-grid{display:grid!important;grid-template-columns:repeat(auto-fill,minmax(430px,1fr))!important;gap:18px!important;margin:0!important;animation:none!important;}
.sige-turmas-page .sige-turma-card{background:var(--color-white)!important;border:1px solid rgba(30,34,60,.08)!important;border-radius:var(--radius-xl)!important;box-shadow:var(--shadow-md);padding:20px 22px!important;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease!important;border-top:0!important;}
.sige-turmas-page .sige-turma-card:hover{transform:translateY(-1px)!important;box-shadow:var(--shadow-md);border-color:var(--color-info-100)!important;}
.sige-turmas-page .sige-card-header{border-bottom:1px solid var(--color-slate-100)!important;margin-bottom:16px!important;padding-bottom:14px!important;}
.sige-turmas-page .sige-card-title h3{font-size:18px!important;line-height:1.16!important;font-weight:700!important;letter-spacing:-.035em!important;color:var(--color-ink-500)!important;margin:0 0 var(--space-2)!important;}
.sige-turmas-page .sige-sala-badge,.sige-turmas-page .sige-classe-badge{border-radius:var(--radius-pill)!important;background:var(--color-slate-50)!important;border:1px solid var(--color-slate-100)!important;color:var(--color-slate-600)!important;font-size:12px!important;font-weight:700!important;}
.sige-turmas-page .sige-card-info{color:var(--color-slate-600)!important;font-size:var(--fs-sm)!important;font-weight:600!important;}
.sige-turmas-page .sige-card-info strong{color:var(--color-ink-500)!important;font-weight:700!important;}
.sige-turmas-page .sige-card-info svg{color:var(--color-ink-400)!important;stroke:currentColor!important;}
.sige-turmas-page .sige-ocupacao{background:var(--color-slate-50)!important;border:1px solid var(--color-slate-100)!important;border-radius:var(--radius-lg)!important;padding:14px!important;}
.sige-turmas-page .sige-ocupacao-header{color:var(--color-slate-600)!important;font-size:12px!important;font-weight:700!important;text-transform:none!important;letter-spacing:0!important;}
.sige-turmas-page .sige-ocupacao-bar{height:8px!important;background:var(--color-ink-100)!important;}
.sige-turmas-page .sige-ocupacao-fill{background:linear-gradient(90deg,var(--color-brand-400),var(--color-success-400))!important;}
.sige-turmas-page .sige-card-actions{display:flex!important;flex-wrap:wrap!important;gap:var(--space-2)!important;border-top:1px solid var(--color-slate-100)!important;padding-top:14px!important;margin-top:16px!important;}
.sige-turmas-page .sige-btn-card{min-height:42px!important;flex:1 1 auto!important;display:inline-flex!important;flex-direction:row!important;align-items:center!important;justify-content:center!important;gap:7px!important;padding:0 11px!important;border-radius:var(--radius-md)!important;border:1px solid var(--color-slate-100)!important;background:var(--color-white)!important;color:var(--color-ink-800)!important;font-size:12px!important;font-weight:700!important;}
.sige-turmas-page .sige-btn-card svg{width:16px!important;height:16px!important;color:currentColor!important;stroke:currentColor!important;}
.sige-turmas-page .sige-btn-card:hover{background:var(--color-slate-50)!important;border-color:var(--color-info-100)!important;color:var(--sgv2-purple)!important;}
.sige-turmas-page .sige-btn-card.btn-danger{flex:0 0 42px!important;color:var(--color-danger-500)!important;background:var(--color-warning-50)!important;border-color:var(--color-danger-100)!important;}
.sige-turmas-page .sige-empty-state{background:var(--color-white)!important;border:1px solid rgba(30,34,60,.08)!important;border-radius:var(--radius-xl)!important;box-shadow:var(--shadow-md);}
.sige-turmas-page .sige-modal{z-index:999999!important;background:rgba(31,34,49,.56)!important;backdrop-filter:blur(8px)!important;}
.sige-turmas-page .sige-modal-content{border-radius:var(--radius-xl)!important;border:1px solid rgba(30,34,60,.08)!important;box-shadow:var(--shadow-lg);}
.sige-turmas-page .sige-modal-header{background:var(--color-white)!important;color:var(--color-black)!important;border-bottom:1px solid var(--color-slate-100)!important;padding:var(--space-5) var(--space-6)!important;}
.sige-turmas-page .sige-modal-header h2{color:var(--color-black)!important;font-weight:700!important;letter-spacing:-.035em!important;}
.sige-turmas-page .sige-modal-header h2 svg{color:var(--sgv2-purple)!important;stroke:currentColor!important;}
.sige-turmas-page .sige-modal-header p{color:var(--color-ink-400)!important;}
.sige-turmas-page .sige-modal-close{background:var(--color-brand-50)!important;color:var(--sgv2-purple)!important;font-weight:700!important;}
.sige-turmas-page .sige-field-group label{color:var(--color-slate-600)!important;font-size:12px!important;letter-spacing:.05em!important;font-weight:700!important;}
.sige-turmas-page .sige-field-group input,.sige-turmas-page .sige-field-group select{height:46px!important;border-radius:var(--radius-md)!important;border:1px solid var(--color-slate-100)!important;color:var(--color-ink-800)!important;font-weight:600!important;}
.sige-turmas-page .sige-field-group input:focus,.sige-turmas-page .sige-field-group select:focus{border-color:var(--color-info-100)!important;box-shadow:var(--shadow-xs);}
.sige-turmas-page .sige-btn-submit{background:linear-gradient(135deg,var(--color-brand-400),var(--color-brand-600))!important;color:var(--color-white)!important;box-shadow:var(--shadow-md);}
.sige-turmas-page .sige-btn-cancel{background:var(--color-white)!important;color:var(--color-ink-800)!important;border:1px solid var(--color-slate-100)!important;}
.sige-turmas-page .sige-confirm-panel{padding:var(--space-6)!important;text-align:left!important;}
.sige-turmas-page .sige-confirm-icon{width:54px;height:54px;border-radius:var(--radius-lg);background:var(--color-warning-100);color:var(--color-warning-500);display:flex;align-items:center;justify-content:center;margin-bottom:16px;}
.sige-turmas-page .sige-confirm-icon svg{width:26px;height:26px;}
.sige-turmas-page .sige-confirm-text{font-size:var(--fs-base);line-height:1.6;color:var(--color-slate-700);margin:var(--space-2) 0 0;}
.sige-turmas-page .sige-confirm-detail{margin-top:14px;padding:13px 14px;border-radius:var(--radius-lg);background:var(--color-slate-50);border:1px solid var(--color-slate-100);color:var(--color-ink-500);font-weight:700;}

body.sige-admin-app.sige-view-turmas .sige-modal{z-index:999999!important;background:rgba(31,34,49,.56)!important;backdrop-filter:blur(8px)!important;}
body.sige-admin-app.sige-view-turmas .sige-modal-content{border-radius:var(--radius-xl)!important;border:1px solid rgba(30,34,60,.08)!important;box-shadow:var(--shadow-lg);}
body.sige-admin-app.sige-view-turmas .sige-modal-header{background:var(--color-white)!important;color:var(--color-black)!important;border-bottom:1px solid var(--color-slate-100)!important;padding:var(--space-5) var(--space-6)!important;}
body.sige-admin-app.sige-view-turmas .sige-modal-header h2{color:var(--color-black)!important;font-weight:700!important;letter-spacing:-.035em!important;}
body.sige-admin-app.sige-view-turmas .sige-modal-header h2 svg{color:var(--color-brand-400)!important;stroke:currentColor!important;}
body.sige-admin-app.sige-view-turmas .sige-modal-header p{color:var(--color-ink-400)!important;}
body.sige-admin-app.sige-view-turmas .sige-modal-close{background:var(--color-brand-50)!important;color:var(--color-brand-400)!important;font-weight:700!important;}
body.sige-admin-app.sige-view-turmas .sige-modal-footer{background:var(--color-slate-50)!important;border-top:1px solid var(--color-slate-100)!important;}
body.sige-admin-app.sige-view-turmas .sige-field-group label{color:var(--color-slate-600)!important;font-size:12px!important;letter-spacing:.05em!important;font-weight:700!important;}
body.sige-admin-app.sige-view-turmas .sige-field-group input,body.sige-admin-app.sige-view-turmas .sige-field-group select{height:46px!important;border-radius:var(--radius-md)!important;border:1px solid var(--color-slate-100)!important;color:var(--color-ink-800)!important;font-weight:600!important;}
body.sige-admin-app.sige-view-turmas .sige-field-group input:focus,body.sige-admin-app.sige-view-turmas .sige-field-group select:focus{border-color:var(--color-info-100)!important;box-shadow:var(--shadow-xs);}
body.sige-admin-app.sige-view-turmas .sige-btn-submit{background:linear-gradient(135deg,var(--color-brand-400),var(--color-brand-600))!important;color:var(--color-white)!important;box-shadow:var(--shadow-md);}
body.sige-admin-app.sige-view-turmas .sige-btn-cancel{background:var(--color-white)!important;color:var(--color-ink-800)!important;border:1px solid var(--color-slate-100)!important;}
body.sige-admin-app.sige-view-turmas .sige-confirm-panel{padding:var(--space-6)!important;text-align:left!important;}
body.sige-admin-app.sige-view-turmas .sige-confirm-icon{width:54px;height:54px;border-radius:var(--radius-lg);background:var(--color-warning-100);color:var(--color-warning-500);display:flex;align-items:center;justify-content:center;margin-bottom:16px;}
body.sige-admin-app.sige-view-turmas .sige-confirm-icon svg{width:26px;height:26px;}
body.sige-admin-app.sige-view-turmas .sige-confirm-text{font-size:var(--fs-base);line-height:1.6;color:var(--color-slate-700);margin:var(--space-2) 0 0;}
body.sige-admin-app.sige-view-turmas .sige-confirm-detail{margin-top:14px;padding:13px 14px;border-radius:var(--radius-lg);background:var(--color-slate-50);border:1px solid var(--color-slate-100);color:var(--color-ink-500);font-weight:700;}
body.sige-admin-app.sige-modal-open{overflow:hidden!important;}

@media (max-width:1500px){.sige-turmas-page .sige-stats-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important}.sige-turmas-page .sige-hero{grid-template-columns:1fr!important}.sige-turmas-page .sige-hero-art{display:none!important}.sige-turmas-page .sige-toolbar{grid-template-columns:1fr 220px auto auto!important}.sige-turmas-page .sige-turma-grid{grid-template-columns:repeat(auto-fill,minmax(380px,1fr))!important}}
@media (max-width:1100px){body.sige-admin-app.sige-view-turmas .sg-app-content{padding-left:22px!important;padding-right:22px!important}.sige-turmas-page .sige-stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}.sige-turmas-page .sige-toolbar{grid-template-columns:1fr!important}.sige-turmas-page .sige-filter-select,.sige-turmas-page .sige-btn-toolbar{width:100%!important}.sige-turmas-page .sige-turma-grid{grid-template-columns:1fr!important}}
@media (max-width:720px){body.sige-admin-app.sige-view-turmas .sg-app-content{padding-left:16px!important;padding-right:16px!important}.sige-turmas-page .sige-hero{padding:var(--space-6) var(--space-5)!important;border-radius:22px!important}.sige-turmas-page .sige-hero h1{font-size:24px!important}.sige-turmas-page .sige-stats-grid{grid-template-columns:1fr!important}.sige-turmas-page .sige-card-actions{display:grid!important;grid-template-columns:1fr 1fr!important}.sige-turmas-page .sige-btn-card.btn-danger{flex:auto!important}.sige-turmas-page .sige-field-row{grid-template-columns:1fr!important}.sige-turmas-page .sige-modal-content{max-width:calc(100vw - 24px)!important;border-radius:var(--radius-xl)!important;}.sige-turmas-page .sige-modal-footer{display:grid!important;grid-template-columns:1fr!important}.sige-turmas-page .sige-modal-footer button{width:100%!important}}

/* v12.23.0 - Modais acima da barra lateral: .sg-app-content e contexto de
   empilhamento (z-index:1) abaixo da sidebar; enquanto ha modal aberto
   (sige-modal-open) elevamos o conteudo. Scoped a esta view. */
body.sige-admin-app.sige-view-turmas.sige-modal-open .sg-app-content{z-index:10090!important;}
/* v12.23.0 - Herois sem ilustracao: faixa de uma coluna a toda a largura; botoes
   compactos numa linha em portateis (largura de conteudo = janela menos a barra
   lateral), so empilham em ecra estreito. */
body.sige-admin-app.sige-view-turmas .sige-turmas-page .sige-hero-content{display:block!important;width:100%!important;}
body.sige-admin-app.sige-view-turmas .sige-turmas-page .sige-hero h1{max-width:none!important;}
body.sige-admin-app.sige-view-turmas .sige-turmas-page .sige-hero-actions{display:flex!important;flex-wrap:nowrap!important;gap:var(--space-2)!important;}
body.sige-admin-app.sige-view-turmas .sige-turmas-page .sige-btn-hero{flex:0 1 auto!important;min-width:0!important;padding:0 var(--space-3)!important;gap:var(--space-2)!important;font-size:var(--fs-sm)!important;white-space:nowrap!important;}
body.sige-admin-app.sige-view-turmas .sige-turmas-page .sige-btn-hero svg{flex:0 0 auto!important;width:16px!important;height:16px!important;}
@media (max-width:720px){
    body.sige-admin-app.sige-view-turmas .sige-turmas-page .sige-hero-actions{display:grid!important;grid-template-columns:1fr!important;flex-wrap:wrap!important;}
    body.sige-admin-app.sige-view-turmas .sige-turmas-page .sige-btn-hero{width:100%!important;font-size:var(--fs-base)!important;white-space:normal!important;}
}
</style>
<div class="wrap sige-turmas-page">

    <section class="sige-hero">
        <div class="sige-hero-content">
            <div class="sige-hero-text">
                <div class="sige-hero-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('school') : ''; ?> Turmas</div>
                <h1>Gestão de Turmas</h1>
                <p class="sige-hero-subtitle">Organize as turmas, salas, turnos, directores de turma e ocupação dos alunos para o ano lectivo <?php echo esc_html($ano_lectivo_ativo); ?>.</p>
                <div class="sige-hero-actions">
                    <button data-sige-act="novaTurma" data-sige-noargs class="sige-btn-hero">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Nova Turma
                    </button>
                    <button data-sige-act="imprimirMapaTurmas" data-sige-noargs class="sige-btn-hero sige-btn-hero-secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Imprimir mapa
                    </button>
                </div>
            </div>
        </div>
    </section>

    <div class="sige-stats-grid">
        <div class="sige-stat-card highlight">
            <div class="sige-stat-icon" style="--kpi-color:var(--color-brand-500);--kpi-soft:var(--color-white);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            </div>
            <div class="sige-stat-info">
                <div class="sige-stat-label">Total de Turmas</div>
                <div class="sige-stat-value"><?php echo $total_turmas; ?></div>
            </div>
        </div>
        <div class="sige-stat-card">
            <div class="sige-stat-icon icon-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="sige-stat-info">
                <div class="sige-stat-label">Total de Vagas</div>
                <div class="sige-stat-value"><?php echo $total_vagas; ?></div>
            </div>
        </div>
        <div class="sige-stat-card">
            <div class="sige-stat-icon icon-amber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            </div>
            <div class="sige-stat-info">
                <div class="sige-stat-label">Manhã</div>
                <div class="sige-stat-value"><?php echo $turmas_manha; ?></div>
            </div>
        </div>
        <div class="sige-stat-card">
            <div class="sige-stat-icon icon-orange">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
            </div>
            <div class="sige-stat-info">
                <div class="sige-stat-label">Tarde</div>
                <div class="sige-stat-value"><?php echo $turmas_tarde; ?></div>
            </div>
        </div>
        <div class="sige-stat-card">
            <div class="sige-stat-icon icon-navy">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </div>
            <div class="sige-stat-info">
                <div class="sige-stat-label">Noite</div>
                <div class="sige-stat-value"><?php echo $turmas_noite; ?></div>
            </div>
        </div>
    </div>

    <div class="sige-toolbar">
        <div class="sige-search-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="filtro-texto" placeholder="Pesquisar turma, director ou sala..." data-sige-on-keyup="filtrarTurmas()" autocomplete="off">
        </div>
        
        <select id="filtro-turno" class="sige-filter-select" data-sige-on-change="filtrarTurmas()">
            <option value="">Todos os Turnos</option>
            <option value="Manhã">Manhã</option>
            <option value="Tarde">Tarde</option>
            <option value="Noite">Noite</option>
        </select>
        
        <button data-sige-act="imprimirMapaTurmas" data-sige-noargs class="sige-btn-toolbar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Imprimir
        </button>
        
        <button data-sige-act="exportarExcel" data-sige-noargs class="sige-btn-toolbar btn-excel">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            Excel
        </button>
    </div>

    <div class="sige-turma-grid" id="grid-turmas">
        <?php if($turmas): ?>
            <?php foreach($turmas as $t): 
                $json = htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8');
                $matriculados = sige_contar_matriculados_turma($t->id, $ano_lectivo_ativo);
                $capacidade = intval($t->capacidade) ?: 1;
                $percentual = ($capacidade > 0) ? (($matriculados / $capacidade) * 100) : 0;
                $percentual_barra = max(0, min(100, $percentual));
            ?>
            <div class="sige-turma-card <?php echo get_turno_class($t->turno); ?>" 
                 data-nome="<?php echo esc_attr(strtolower($t->nome)); ?>" 
                 data-director="<?php echo strtolower($t->director_nome ?? ''); ?>" 
                 data-sala="<?php echo strtolower($t->sala ?? ''); ?>"
                 data-turno="<?php echo $t->turno; ?>">
                 
                <div class="sige-card-header">
                    <div class="sige-card-title">
                        <h3><?php echo esc_html($t->nome); ?></h3>
                        <span class="sige-sala-badge">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <?php echo esc_html($t->sala ?: 'Sem sala'); ?>
                        </span>
                    </div>
                    <span class="sige-classe-badge"><?php echo esc_html($t->classe); ?></span>
                </div>
                
                <div class="sige-card-body">
                    <div class="sige-card-info">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span>Turno:</span>
                        <strong><?php echo esc_html($t->turno); ?></strong>
                    </div>
                    
                    <div class="sige-ocupacao">
                        <div class="sige-ocupacao-header">
                            <span>Ocupação (<?php echo number_format($percentual, 0); ?>%)</span>
                            <span><?php echo $matriculados; ?> / <?php echo $t->capacidade; ?></span>
                        </div>
                        <div class="sige-ocupacao-bar">
                            <div class="sige-ocupacao-fill" style="width: <?php echo $percentual_barra; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="sige-card-info">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span>Director:</span>
                        <strong><?php echo esc_html($t->director_nome ?: 'Não atribuído'); ?></strong>
                    </div>
                </div>
                
                <div class="sige-card-actions">
                    <button type="button" class="sige-btn-card" data-sige-act="sigeExecutarJsonData" data-sige-json-fn="editarTurma" data-sige-json='<?php echo $json; ?>' title="Editar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Editar
                    </button>
                    
                    <button type="button" class="sige-btn-card" data-sige-act="alocarProfessores" data-sige-args="<?php echo esc_attr(wp_json_encode([(int)$t->id, $t->nome])); ?>" title="Docentes">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Docentes
                    </button>
                    
                    <button type="button" class="sige-btn-card" data-sige-act="verAlunos" data-sige-args="<?php echo esc_attr(wp_json_encode([(int)$t->id, $t->nome, $t->classe])); ?>" title="Alunos">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                        Alunos
                    </button>
                    
                    <button type="button" class="sige-btn-card" data-sige-act="gerirHorario" data-sige-args="[<?php echo (int)$t->id; ?>]" title="Horário">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Horário
                    </button>
                    
                    <button type="button" class="sige-btn-card btn-danger" data-sige-act="apagarTurma" data-sige-args="<?php echo esc_attr(wp_json_encode([(int)$t->id, $t->nome])); ?>" title="Eliminar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="sige-empty-state">
                <div class="sige-empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                </div>
                <h3>Nenhuma turma registada</h3>
                <p>Comece por criar a primeira turma do ano lectivo <?php echo esc_html($ano_lectivo_ativo); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div id="area-impressao">
        <table id="tabela-exportar" style="display: none;">
            <thead>
                <tr>
                    <th style="width: 15%;">TURMA</th>
                    <th style="width: 12%;">CLASSE</th>
                    <th style="width: 10%;">TURNO</th>
                    <th style="width: 18%;">SALA / BLOCO</th>
                    <th style="width: 35%;">DIRECTOR DE TURMA</th>
                    <th style="width: 10%; text-align: center;">VAGAS</th>
                </tr>
            </thead>
            <tbody>
                <?php if($turmas): ?>
                    <?php foreach($turmas as $t): ?>
                    <tr>
                        <td><strong><?php echo esc_html($t->nome); ?></strong></td>
                        <td><?php echo esc_html($t->classe); ?></td>
                        <td><?php echo esc_html($t->turno); ?></td>
                        <td><?php echo esc_html($t->sala ?: '---'); ?></td>
                        <td><?php echo esc_html($t->director_nome ?: 'Não atribuído'); ?></td>
                        <td class="sige-u-tac"><strong><?php echo $t->capacidade; ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<div id="modal-turma" class="sige-modal">
    <div class="sige-modal-content">
        <div class="sige-modal-header">
            <div>
                <h2 id="modal-titulo-turma">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Nova Turma
                </h2>
            </div>
            <button type="button" data-sige-act="sigeFecharModalJq" data-sige-arg="#modal-turma" class="sige-modal-close">&times;</button>
        </div>
        
        <form id="form-turma">
            <div class="sige-modal-body">
                <input type="hidden" name="id_turma" id="id_turma">
                <input type="hidden" name="_sige_nonce" value="<?php echo esc_attr(wp_create_nonce('sige_turmas_action')); ?>">
                
                <div class="sige-field-row">
                    <div class="sige-field-group">
                        <label for="turma_nome">Nome da Turma *</label>
                        <input type="text" name="nome" id="turma_nome" placeholder="Ex: Turma A" required autocomplete="off">
                    </div>
                    <div class="sige-field-group">
                        <label for="turma_classe">Classe *</label>
                        <select name="classe" id="turma_classe" required>
                            <?php foreach($classes_list as $cl): ?>
                                <option value="<?php echo esc_attr($cl); ?>"><?php echo esc_attr($cl); ?> Classe</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="sige-field-row">
                    <div class="sige-field-group">
                        <label for="turma_turno">Turno *</label>
                        <select name="turno" id="turma_turno" required>
                            <option value="Manhã">Manhã</option>
                            <option value="Tarde">Tarde</option>
                            <option value="Noite">Noite</option>
                        </select>
                    </div>
                    <div class="sige-field-group">
                        <label for="turma_sala">Sala / Bloco</label>
                        <input type="text" name="sala" id="turma_sala" placeholder="Ex: Sala 04 - Bloco B" autocomplete="off">
                    </div>
                </div>
                
                <div class="sige-field-row">
                    <div class="sige-field-group">
                        <label for="turma_capacidade">Capacidade (Vagas)</label>
                        <input type="number" name="capacidade" id="turma_capacidade" value="40" min="1" max="100">
                    </div>
                    <div class="sige-field-group">
                        <label for="turma_director">Director de Turma</label>
                        <select name="director_turma_id" id="turma_director">
                            <option value="">-- Seleccionar Professor --</option>
                            <?php foreach($professores as $p): ?>
                                <option value="<?php echo esc_attr($p->id); ?>"><?php echo esc_html($p->nome_completo); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="sige-modal-footer">
                <button type="button" data-sige-act="sigeFecharModalJq" data-sige-arg="#modal-turma" class="sige-btn-modal sige-btn-cancel">Cancelar</button>
                <button type="submit" id="btn-submit-turma" class="sige-btn-modal sige-btn-submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    Guardar Turma
                </button>
            </div>
        </form>
    </div>
</div>

<div id="modal-docentes" class="sige-modal">
    <div class="sige-modal-content modal-wide">
        <div class="sige-modal-header">
            <div>
                <h2 id="docentes-turma-nome">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Alocação de Professores
                </h2>
            </div>
            <button type="button" data-sige-act="sigeFecharModalJq" data-sige-arg="#modal-docentes" class="sige-modal-close">&times;</button>
        </div>
        
        <div class="sige-modal-body">
            <div id="lista-disciplinas-prof" class="sige-disciplinas-lista">
                <div style="text-align: center; padding: 40px; color: var(--sige-slate-400);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:40px;height:40px;animation:spin 1s linear infinite;margin-bottom:12px;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                    <p>A carregar matriz curricular...</p>
                </div>
            </div>
        </div>
        
        <div class="sige-modal-footer">
            <button data-sige-act="sigeFecharModalJq" data-sige-arg="#modal-docentes" class="sige-btn-modal sige-btn-submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                Concluir
            </button>
        </div>
    </div>
</div>

<div id="modal-alunos" class="sige-modal">
    <div class="sige-modal-content modal-alunos">
        <div class="sige-modal-header">
            <div>
                <h2 id="al-titulo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                    Alunos da Turma
                </h2>
                <p id="al-sub"></p>
            </div>
            <button type="button" data-sige-act="sigeFecharModalJq" data-sige-arg="#modal-alunos" class="sige-modal-close">&times;</button>
        </div>
        
        <div class="sige-modal-body">
            <div class="sige-alunos-summary">
                <span class="sige-alunos-pill pill-total" id="al-total">Total: -</span>
                <span class="sige-alunos-pill pill-masc" id="al-m">Masc: -</span>
                <span class="sige-alunos-pill pill-fem" id="al-f">Fem: -</span>
            </div>
            <div class="sige-alunos-table-wrap" id="al-wrap">
                <div style="text-align:center;padding:60px;color:var(--sige-slate-400);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:40px;height:40px;animation:spin 1s linear infinite;margin-bottom:12px;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                    <p>A carregar lista...</p>
                </div>
            </div>
        </div>
        
        <div class="sige-modal-footer" style="justify-content:space-between;">
            <button type="button" data-sige-act="imprimirListaAlunos" data-sige-noargs class="sige-btn-modal sige-btn-success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Descarregar PDF
            </button>
            <button type="button" data-sige-act="sigeFecharModalJq" data-sige-arg="#modal-alunos" class="sige-btn-modal sige-btn-cancel">Fechar</button>
        </div>
    </div>
</div>

<div id="modal-horario" class="sige-modal">
    <div class="sige-modal-content modal-horario">
        <div class="sige-modal-header">
            <div>
                <h2>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Horário da Turma
                </h2>
                <p>Clique numa célula para editar disciplina e professor</p>
            </div>
            <button type="button" data-sige-act="sigeFecharModalJq" data-sige-arg="#modal-horario" class="sige-modal-close">&times;</button>
        </div>
        
        <div class="sige-modal-body">
            <input type="hidden" id="h-turma-id">
            <div class="sige-horario-controls">
                <label>Nº de Períodos:</label>
                <select id="h-periodos" data-sige-on-change="renderHorario()">
                    <option value="5">5 períodos</option>
                    <option value="6" selected>6 períodos</option>
                    <option value="7">7 períodos</option>
                    <option value="8">8 períodos</option>
                    <option value="9">9 períodos</option>
                    <option value="10">10 períodos</option>
                </select>
                <button data-sige-act="hLimpar" data-sige-noargs class="sige-btn-horario btn-limpar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    Limpar
                </button>
                <button data-sige-act="hImprimir" data-sige-noargs class="sige-btn-horario btn-imprimir">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Imprimir
                </button>
            </div>
            <div id="h-grid-wrap" class="sige-horario-grid-wrap">
                <div style="text-align:center;padding:40px;color:var(--sige-slate-400);">A carregar...</div>
            </div>
        </div>
        
        <div class="sige-modal-footer">
            <button data-sige-act="sigeFecharModalJq" data-sige-arg="#modal-horario" class="sige-btn-modal sige-btn-cancel">Fechar</button>
            <button id="h-btn-guardar" data-sige-act="hGuardar" data-sige-noargs class="sige-btn-modal sige-btn-submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                Guardar
            </button>
        </div>
    </div>
</div>

<div id="modal-turmas-confirmacao" class="sige-modal" aria-hidden="true">
    <div class="sige-modal-content" style="max-width:520px;">
        <div class="sige-modal-header">
            <div><h2 id="sige-turmas-confirm-title">Confirmar acção</h2></div>
            <button type="button" data-sige-act="sigeTurmasCloseConfirm" data-sige-noargs class="sige-modal-close">&times;</button>
        </div>
        <div class="sige-confirm-panel">
            <div class="sige-confirm-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <p id="sige-turmas-confirm-message" class="sige-confirm-text"></p>
            <div id="sige-turmas-confirm-detail" class="sige-confirm-detail" style="display:none;"></div>
        </div>
        <div class="sige-modal-footer">
            <button type="button" data-sige-act="sigeTurmasCloseConfirm" data-sige-noargs class="sige-btn-modal sige-btn-cancel">Voltar</button>
            <button type="button" id="sige-turmas-confirm-ok" class="sige-btn-modal sige-btn-submit">Confirmar</button>
        </div>
    </div>
</div>

<script <?php echo sige_csp_script_attr(); ?>>
var sigeAjax = { nonce_turmas: '<?php echo esc_js(wp_create_nonce('sige_turmas_action')); ?>' };

var sigeTurmasConfirmCallback = null;
function sigeTurmasShowConfirm(opts, callback) {
    opts = opts || {};
    sigeTurmasConfirmCallback = (typeof callback === 'function') ? callback : null;
    jQuery('#sige-turmas-confirm-title').text(opts.title || 'Confirmar acção');
    jQuery('#sige-turmas-confirm-message').text(opts.message || 'Confirme para continuar.');
    if (opts.detail) {
        jQuery('#sige-turmas-confirm-detail').text(opts.detail).show();
    } else {
        jQuery('#sige-turmas-confirm-detail').hide().text('');
    }
    jQuery('#sige-turmas-confirm-ok').text(opts.confirmText || 'Confirmar');
    jQuery('#modal-turmas-confirmacao').css('display', 'flex').hide().fadeIn(180);
    jQuery('body').addClass('sige-modal-open');
}
function sigeTurmasCloseConfirm() {
    jQuery('#modal-turmas-confirmacao').fadeOut(160);
    jQuery('body').removeClass('sige-modal-open');
    sigeTurmasConfirmCallback = null;
}
jQuery(document).on('click', '#sige-turmas-confirm-ok', function() {
    var cb = sigeTurmasConfirmCallback;
    sigeTurmasCloseConfirm();
    if (cb) cb();
});
function sigeTurmasShowNotice(title, message, detail) {
    sigeTurmasShowConfirm({title:title || 'Aviso', message: message || '', detail: detail || '', confirmText: 'Entendi'}, null);
}

// [SEC] Prevent XSS in innerHTML templates
function escapeHtml(t) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(t));
    return d.innerHTML;
}

// ========================================
// IMPRIMIR MAPA DE TURMAS (NOVO)
// ========================================
function imprimirMapaTurmas() {
    var nomeEsc = <?php echo json_encode($escola->nome_escola ?: 'SISTEMA INTEGRADO DE GESTÃO ESCOLAR'); ?>;
    var logo    = <?php echo json_encode($logo_final); ?>;
    var ano     = <?php echo $ano_lectivo_ativo; ?>;
    var mzTime  = '<?php echo esc_js(wp_date('d/m/Y H:i')); ?>';
    
    // Pegamos a tabela que está oculta, sem a regra inline "display: none"
    var tabelaRef = document.getElementById("tabela-exportar");
    var tabelaHTML = tabelaRef.outerHTML.replace('style="display: none;"', '');
    
    var w = window.open('', '_blank', 'width=1100,height=700');
    w.document.write('<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8"><title>Mapa de Turmas</title>'
        + '<style><?php echo sige_utilities_inline_css(); ?>'
        + 'body{font-family:Arial,sans-serif;margin:20px;font-size:10pt;color:#000;}'
        + '.hdr{text-align:center;border-bottom:3px solid #000;padding-bottom:15px;margin-bottom:20px;}'
        + '.hdr img{height:90px;display:block;margin:0 auto 15px;-webkit-print-color-adjust:exact;print-color-adjust:exact;}'
        + '.hdr h1{font-size:18pt;font-weight:900;text-transform:uppercase;color:#000;margin:8px 0;letter-spacing:1px;}'
        + '.hdr p{font-size:12pt;margin:5px 0;font-weight:700;color:#333;}'
        + 'table{width:100%;border-collapse:collapse;margin-top:15px;}'
        + 'th{background-color:#e0e0e0!important;border:1.5px solid #000;padding:10px 8px;text-transform:uppercase;font-weight:800;font-size:10pt;color:#000;text-align:left;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}'
        + 'td{border:1px solid #333;padding:10px 8px;vertical-align:middle;font-size:10pt;color:#000;}'
        + 'tr{page-break-inside:avoid;}'
        + 'tr:nth-child(even) td{background-color:#f5f5f5!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}'
        + '.sigs{margin-top:60px;display:flex;justify-content:space-between;padding:0 80px;page-break-inside:avoid;}'
        + '.sig p{margin-bottom:40px;font-weight:600;font-size:11pt;text-align:center;}'
        + '.sig-line{border-top:1.5px solid #000;width:200px;margin:0 auto;}'
        + '.ftr{margin-top:40px;text-align:center;font-size:8pt;color:#666;border-top:1px solid #ccc;padding-top:8px;page-break-inside:avoid;}'
        + '@media print{@page{size:A4 landscape;margin:1cm;}}'
        + '</style></head><body>'
        + '<div class="hdr"><img src="' + logo + '" onerror="this.style.display=\'none\'">'
        + '<h1>' + nomeEsc + '</h1>'
        + '<p>MAPA DE TURMAS - ANO LECTIVO ' + ano + '</p></div>'
        + tabelaHTML
        + '<div class="sigs">'
        + '<div class="sig"><p>O Chefe da Secretaria</p><div class="sig-line"></div></div>'
        + '<div class="sig"><p>O Director da Escola</p><div class="sig-line"></div></div>'
        + '</div>'
        + '<div class="ftr">Processado pelo SIGE SoftGenial - Hora de Moçambique: ' + mzTime + '</div>'
        + '</body></html>');
    w.document.close();
    setTimeout(function() { w.print(); }, 500);
}

// ========================================
// EXPORTAR PARA EXCEL (PRESERVADO)
// ========================================
function exportarExcel() {
    const tabelaOriginal = document.getElementById("tabela-exportar");
    const nomeEscola = <?php echo json_encode($escola->nome_escola ?: 'SIGE'); ?>;
    
    const html = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" 
              xmlns:x="urn:schemas-microsoft-com:office:excel" 
              xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; }
                h3 { text-align: center; color: #1a237e; font-size: 16pt; margin-bottom: 5px; }
                p { text-align: center; font-size: 11pt; margin-top: 0; }
                table { border-collapse: collapse; width: 100%; margin-top: 20px; }
                th { background-color: #e0e0e0; border: 1px solid #000; font-weight: bold; padding: 8px; text-align: left; font-size: 10pt; }
                td { border: 1px solid #000; padding: 8px; font-size: 10pt; }
                tr:nth-child(even) { background-color: #f5f5f5; }
            </style>
        </head>
        <body>
            <h3>${nomeEscola}</h3>
            <p><strong>MAPA DE TURMAS - ANO LECTIVO <?php echo esc_html($ano_lectivo_ativo); ?></strong></p>
            <p>Processado em (Moçambique): <?php echo esc_html(wp_date('d/m/Y H:i')); ?></p>
            ${tabelaOriginal.outerHTML.replace('style="display: none;"', '')}
        </body>
        </html>`;
    const blob = new Blob(['\ufeff', html], { type: 'application/vnd.ms-excel;charset=utf-8' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `Mapa_Turmas_${new Date().toISOString().slice(0,10)}.xls`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
    
    const btnExcel = document.querySelector('.btn-excel');
    const textoOriginal = btnExcel.innerHTML;
    btnExcel.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><polyline points="20 6 9 17 4 12"/></svg> Exportado!';
    btnExcel.style.background = 'var(--sige-success-light)';
    setTimeout(() => {
        btnExcel.innerHTML = textoOriginal;
        btnExcel.style.background = '';
    }, 2000);
}

// ========================================
// FILTROS
// ========================================
function filtrarTurmas() {
    const texto = jQuery('#filtro-texto').val().toLowerCase();
    const turno = jQuery('#filtro-turno').val();
    jQuery('.sige-turma-card').each(function() {
        const card = jQuery(this);
        const nome = (card.data('nome') || '').toString();
        const director = (card.data('director') || '').toString();
        const sala = (card.data('sala') || '').toString();
        const cardTurno = card.data('turno');
        const matchTexto = nome.includes(texto) || director.includes(texto) || sala.includes(texto);
        const matchTurno = turno === "" || cardTurno === turno;
        if (matchTexto && matchTurno) { card.fadeIn(200); } else { card.fadeOut(200); }
    });
}

// ========================================
// MODAL TURMA
// ========================================
function novaTurma() {
    jQuery('#form-turma')[0].reset();
    jQuery('#id_turma').val('');
    jQuery('#modal-titulo-turma').html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:22px;height:22px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Nova Turma');
    jQuery('#btn-submit-turma').html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><polyline points="20 6 9 17 4 12"/></svg> Guardar Turma');
    jQuery('#modal-turma').css('display', 'flex').hide().fadeIn(300);
    jQuery('body').addClass('sige-modal-open');
}

function editarTurma(data) {
    jQuery('#id_turma').val(data.id);
    jQuery('#turma_nome').val(data.nome);
    jQuery('#turma_classe').val(data.classe);
    jQuery('#turma_turno').val(data.turno);
    jQuery('#turma_sala').val(data.sala || '');
    jQuery('#turma_capacidade').val(data.capacidade);
    jQuery('#turma_director').val(data.director_turma_id || '');
    jQuery('#modal-titulo-turma').html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:22px;height:22px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Editar Turma');
    jQuery('#btn-submit-turma').html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><polyline points="20 6 9 17 4 12"/></svg> Actualizar Turma');
    jQuery('#modal-turma').css('display', 'flex').hide().fadeIn(300);
    jQuery('body').addClass('sige-modal-open');
}

jQuery('#form-turma').on('submit', function(e) {
    e.preventDefault();
    const btn = jQuery('#btn-submit-turma');
    const textoOriginal = btn.html();
    btn.html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Processando...').prop('disabled', true);
    jQuery.post(ajaxurl, jQuery(this).serialize() + '&action=sige_salvar_turma', function(res) {
        if (res.success) { location.reload(); } 
        else { sigeTurmasShowNotice('Não foi possível guardar', String(res.data || 'Tente novamente.')); btn.html(textoOriginal).prop('disabled', false); }
    }).fail(function() { sigeTurmasShowNotice('Erro de ligação', 'Não foi possível comunicar com o servidor.'); btn.html(textoOriginal).prop('disabled', false); });
});

// ========================================
// DOCENTES
// ========================================
function alocarProfessores(id, nomeTurma) {
    jQuery('#docentes-turma-nome').html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:22px;height:22px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Docentes: <strong>' + nomeTurma + '</strong>');
    jQuery('#lista-disciplinas-prof').html('<div style="text-align: center; padding: 40px; color: var(--sige-slate-400);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:40px;height:40px;animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg><p style="margin-top:12px;">A carregar matriz curricular...</p></div>');
    jQuery('#modal-docentes').fadeIn(300).css('display', 'flex');
    jQuery('body').addClass('sige-modal-open');
    jQuery.post(ajaxurl, {action: 'sige_listar_docentes_turma', turma_id: id, _sige_nonce: sigeAjax.nonce_turmas}, function(res) {
        if (res.success && res.data && res.data.disciplinas && res.data.disciplinas.length > 0) {
            var classe = res.data.classe || '';
            var fonte  = res.data.fonte  || '';
            var profOpts = `<?php foreach($professores as $p): ?><option value="<?php echo esc_attr($p->id); ?>"><?php echo esc_js($p->nome_completo); ?></option><?php endforeach; ?>`;
            var aviso = (fonte === 'matriz')
                ? `<div style="background:var(--sige-info-light);border:1px solid #93c5fd;border-radius:var(--sige-radius);padding:12px 16px;margin-bottom:16px;font-size:0.8rem;color:#1e40af;display:flex;align-items:center;gap:10px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>Disciplinas carregadas da <strong>Matriz Curricular</strong> para a classe <strong>${classe}</strong>.</div>`
                : `<div style="background:var(--sige-warning-light);border:1px solid #fde68a;border-radius:var(--sige-radius);padding:12px 16px;margin-bottom:16px;font-size:0.8rem;color:#92400e;display:flex;align-items:center;gap:10px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Nenhuma entrada na Matriz Curricular para <strong>${classe}</strong>. A mostrar vínculos existentes.</div>`;
            let html = aviso;
            res.data.disciplinas.forEach(function(item) {
                var badge = item.sigla ? `<span style="background:rgba(63,81,181,0.1);color:var(--sige-primary-dark);font-size:0.65rem;font-weight:700;padding:3px 8px;border-radius:999px;margin-left:8px;">${escapeHtml(item.sigla)}</span>` : '';
                var carga = item.carga_horaria ? `<span style="color:var(--sige-slate-400);font-size:0.7rem;margin-left:8px;">${escapeHtml(String(item.carga_horaria))}h/sem</span>` : '';
                var selVal = item.professor_id ? item.professor_id.toString() : '';
                html += `<div class="sige-disc-item">` +
                    `<div class="disc-nome">${escapeHtml(item.disciplina_nome)}${badge}${carga}</div>` +
                    `<select onchange="salvarDocente(${item.id}, this.value, this)">` +
                    `<option value="">-- Seleccionar Professor --</option>` +
                    profOpts.replace('value="'+selVal+'"', 'value="'+selVal+'" selected') +
                    `</select></div>`;
            });
            jQuery('#lista-disciplinas-prof').html(html);
        } else if (res.success && res.data && res.data.disciplinas && res.data.disciplinas.length === 0) {
            var cls = res.data.classe || '';
            jQuery('#lista-disciplinas-prof').html('<div style="padding:50px;text-align:center;"><svg viewBox="0 0 24 24" fill="none" stroke="var(--sige-slate-300)" stroke-width="2" style="width:50px;height:50px;margin-bottom:16px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><h3 style="color:var(--sige-slate-600);margin:0 0 8px;">Sem disciplinas na Matriz</h3><p style="color:var(--sige-slate-400);font-size:0.85rem;">Adicione disciplinas para a classe <strong>' + cls + '</strong> no módulo de Matriz Curricular.</p></div>');
        } else {
            jQuery('#lista-disciplinas-prof').html('<div style="padding:50px;text-align:center;"><svg viewBox="0 0 24 24" fill="none" stroke="var(--sige-slate-300)" stroke-width="2" style="width:50px;height:50px;margin-bottom:16px;"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg><h3 style="color:var(--sige-slate-600);">Nenhuma disciplina encontrada</h3></div>');
        }
    });
}

function salvarDocente(idVinculo, professorId, element) {
    const select = jQuery(element);
    const corOriginal = select.css('border-color');
    select.css('border-color', 'var(--sige-warning)');
    jQuery.post(ajaxurl, {action: 'sige_salvar_docente_disciplina', id_vinculo: idVinculo, professor_id: professorId, _sige_nonce: sigeAjax.nonce_turmas}, function(res) {
        if (res.success) {
            select.css('border-color', 'var(--sige-success)');
            setTimeout(() => select.css('border-color', corOriginal), 2000);
        } else {
            sigeTurmasShowNotice('Não foi possível guardar', 'Verifique a ligação e tente novamente.');
            select.css('border-color', 'var(--sige-error)');
        }
    });
}

function apagarTurma(id, nome) {
    sigeTurmasShowConfirm({
        title: 'Eliminar turma?',
        message: 'Esta acção remove a turma seleccionada. Confirme apenas se tem certeza.',
        detail: nome,
        confirmText: 'Eliminar turma'
    }, function() {
        jQuery.post(ajaxurl, {action: 'sige_remover_turma', id: id, _sige_nonce: sigeAjax.nonce_turmas}, function(res) {
            if (res.success) location.reload();
            else sigeTurmasShowNotice('Não foi possível eliminar', 'A turma pode ter dados associados ou a operação não foi concluída.');
        }).fail(function() {
            sigeTurmasShowNotice('Erro de ligação', 'Não foi possível comunicar com o servidor.');
        });
    });
}

// ========================================
// HORÁRIO
// ========================================
var _hTurmaId = 0;
var _hPeriodos = 6;
var _hDias = ['Segunda','Terça','Quarta','Quinta','Sexta'];
var _hDados = {};

function gerirHorario(turmaId) {
    _hTurmaId = turmaId;
    jQuery('#h-turma-id').val(turmaId);
    jQuery('#modal-horario').css('display','flex').hide().fadeIn(300);
    jQuery('body').addClass('sige-modal-open');
    carregarHorario(turmaId);
}

function carregarHorario(turmaId) {
    jQuery('#h-grid-wrap').html('<div style="text-align:center;padding:40px;color:var(--sige-slate-400);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:40px;height:40px;animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg><p style="margin-top:12px;">A carregar horário...</p></div>');
    jQuery.post(ajaxurl, {action:'sige_carregar_horario_turma', turma_id: turmaId, _sige_nonce: sigeAjax.nonce_turmas}, function(res) {
        if (res.success && res.data) {
            var raw = res.data.horario;
            _hDados = (raw && !Array.isArray(raw)) ? raw : {};
            _hPeriodos = res.data.periodos || 6;
            jQuery('#h-periodos').val(_hPeriodos);
        } else {
            _hDados = {};
        }
        renderHorario();
    }).fail(function() { _hDados = {}; renderHorario(); });
}

function renderHorario() {
    _hPeriodos = parseInt(jQuery('#h-periodos').val()) || 6;
    var tempos = _hDados._tempos || {};
    var html = '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
    html += '<thead><tr><th style="width:55px;padding:8px;background:var(--sige-navy);color:white;border:1px solid var(--sige-navy-light);text-align:center;font-size:0.7rem;font-weight:700;">Per.</th>';
    html += '<th style="width:95px;padding:8px;background:var(--sige-navy);color:white;border:1px solid var(--sige-navy-light);text-align:center;font-size:0.7rem;font-weight:700;">Horário</th>';
    _hDias.forEach(function(d) {
        html += '<th style="padding:8px;background:var(--sige-navy);color:white;border:1px solid var(--sige-navy-light);text-align:center;font-size:0.7rem;font-weight:700;">'+d+'</th>';
    });
    html += '</tr></thead><tbody>';
    for (var p = 1; p <= _hPeriodos; p++) {
        var inicio = (tempos[p] && tempos[p].inicio) ? tempos[p].inicio : '';
        var fim    = (tempos[p] && tempos[p].fim)    ? tempos[p].fim    : '';
        html += '<tr>';
        html += '<td style="text-align:center;font-weight:700;background:var(--sige-slate-100);border:1px solid var(--sige-slate-200);padding:8px;">'+p+'º</td>';
        html += '<td style="border:1px solid var(--sige-slate-200);padding:6px;background:var(--sige-slate-50);">'
              + '<input type="time" value="'+inicio+'" onchange="hSetTempo('+p+',\'inicio\',this.value)" style="width:72px;font-size:11px;border:1px solid var(--sige-slate-200);border-radius:6px;padding:4px;">'
              + '<br><input type="time" value="'+fim+'" onchange="hSetTempo('+p+',\'fim\',this.value)" style="width:72px;font-size:11px;border:1px solid var(--sige-slate-200);border-radius:6px;padding:4px;margin-top:4px;">'
              + '</td>';
        _hDias.forEach(function(d) {
            var cel = (_hDados[d] && _hDados[d][p]) ? _hDados[d][p] : {disciplina:'', professor:''};
            var hasData = cel.disciplina || cel.professor;
            var bg = hasData ? 'var(--sige-success-light)' : 'white';
            var txt = hasData
                ? '<strong style="color:#047857;font-size:11px;">'+_eh(cel.disciplina)+'</strong><br><span style="color:var(--sige-slate-500);font-size:10px;">'+_eh(cel.professor)+'</span>'
                : '<span style="color:var(--sige-slate-300);font-size:10px;">-</span>';
            html += '<td style="border:1px solid var(--sige-slate-200);padding:8px;text-align:center;background:'+bg+';cursor:pointer;min-width:90px;transition:background 0.2s;" '
                  + 'onmouseover="this.style.background=\'var(--sige-slate-100)\'" onmouseout="this.style.background=\''+bg+'\'" '
                  + 'onclick="hEditarCelula(\''+d+'\','+p+')" title="Clique para editar">'+txt+'</td>';
        });
        html += '</tr>';
    }
    html += '</tbody></table>';
    jQuery('#h-grid-wrap').html(html);
}

function hSetTempo(periodo, campo, valor) {
    if (!_hDados._tempos) _hDados._tempos = {};
    if (!_hDados._tempos[periodo]) _hDados._tempos[periodo] = {inicio:'', fim:''};
    _hDados._tempos[periodo][campo] = valor;
}

async function hEditarCelula(dia, periodo) {
    if (!_hDados[dia]) _hDados[dia] = {};
    if (!_hDados[dia][periodo]) _hDados[dia][periodo] = {disciplina:'', professor:''};
    var cel = _hDados[dia][periodo];
    var disc = await sigeUi.prompt({
        titulo: 'Horário: ' + dia + ', ' + periodo + 'º período',
        texto: 'Disciplina para este tempo (deixar vazio limpa a célula):',
        valor: cel.disciplina || '',
        placeholder: 'Ex.: Matemática',
        confirmar: 'Continuar'
    });
    if (disc === null) return;
    var prof = await sigeUi.prompt({
        titulo: 'Horário: ' + dia + ', ' + periodo + 'º período',
        texto: 'Professor(a) para ' + (disc.trim() !== '' ? disc.trim() : 'este tempo') + ':',
        valor: cel.professor || '',
        placeholder: 'Nome do professor',
        confirmar: 'Guardar'
    });
    if (prof === null) return;
    _hDados[dia][periodo] = {disciplina: disc.trim(), professor: prof.trim()};
    renderHorario();
}

function hLimpar() {
    sigeTurmasShowConfirm({
        title: 'Limpar horário?',
        message: 'Esta acção limpa o horário actualmente montado no ecrã antes de guardar.',
        detail: 'A turma continuará registada normalmente.',
        confirmText: 'Limpar horário'
    }, function() {
        _hDados = {};
        renderHorario();
    });
}

function hGuardar() {
    _hDados._periodos = parseInt(jQuery('#h-periodos').val()) || 6;
    var btn = jQuery('#h-btn-guardar');
    btn.prop('disabled', true).html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> A guardar...');
    jQuery.post(ajaxurl, {
        action: 'sige_salvar_horario_turma',
        turma_id: _hTurmaId,
        horario_json: JSON.stringify(_hDados),
        _sige_nonce: sigeAjax.nonce_turmas
    }, function(res) {
        if (res.success) {
            btn.html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><polyline points="20 6 9 17 4 12"/></svg> Guardado!').css('background','var(--sige-success)');
            setTimeout(function() {
                btn.prop('disabled', false).html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><polyline points="20 6 9 17 4 12"/></svg> Guardar').css('background','');
            }, 2500);
        } else {
            sigeTurmasShowNotice('Não foi possível guardar', String(res.data || 'Tente novamente.'));
            btn.prop('disabled', false).html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><polyline points="20 6 9 17 4 12"/></svg> Guardar');
        }
    }).fail(function(xhr) {
        sigeTurmasShowNotice('Erro de ligação', 'Não foi possível comunicar com o servidor.');
        btn.prop('disabled', false).html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><polyline points="20 6 9 17 4 12"/></svg> Guardar');
    });
}

// ========================================
// IMPRIMIR HORÁRIO (ORIGINAL)
// ========================================
function hImprimir() {
    _hPeriodos = parseInt(jQuery('#h-periodos').val()) || 6;
    var tempos = _hDados._tempos || {};
    var nomeEsc = <?php echo json_encode($escola->nome_escola ?: 'ESCOLA'); ?>;
    var logo    = <?php echo json_encode($logo_final); ?>;
    var ano     = <?php echo $ano_lectivo_ativo; ?>;
    var hoje    = new Date().toLocaleDateString('pt-PT',{day:'2-digit',month:'long',year:'numeric'});
    var rows = '';
    for (var p = 1; p <= _hPeriodos; p++) {
        var inicio = (tempos[p] && tempos[p].inicio) ? tempos[p].inicio : '-';
        var fim    = (tempos[p] && tempos[p].fim)    ? tempos[p].fim    : '-';
        rows += '<tr><td class="sige-u-tac sige-u-fw7">'+p+'º</td><td style="text-align:center;font-size:9pt;">'+inicio+'<br>'+fim+'</td>';
        _hDias.forEach(function(d) {
            var cel = (_hDados[d] && _hDados[d][p]) ? _hDados[d][p] : {disciplina:'', professor:''};
            rows += '<td class="sige-u-tac"><strong>'+_eh(cel.disciplina||'-')+'</strong><br><small>'+_eh(cel.professor||'')+'</small></td>';
        });
        rows += '</tr>';
    }
    var w = window.open('','_blank','width=1100,height=700');
    w.document.write('<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8"><title>Horário</title>'
        +'<style>body{font-family:Arial,sans-serif;margin:15px;font-size:10pt;}'
        +'.hdr{text-align:center;border-bottom:3px solid #1a237e;padding-bottom:10px;margin-bottom:14px;}'
        +'.hdr img{height:70px;display:block;margin:0 auto 8px;}'
        +'.hdr h1{font-size:14pt;font-weight:900;text-transform:uppercase;color:#1a237e;margin:3px 0;}'
        +'.hdr p{font-size:11pt;margin:2px 0;font-weight:700;}'
        +'table{width:100%;border-collapse:collapse;}th{background:#1a237e;color:white;padding:8px;border:1px solid #283593;font-size:9pt;}'
        +'td{border:1px solid #ccc;padding:7px;vertical-align:middle;font-size:9.5pt;}'
        +'tr:nth-child(even) td{background:#f5f5f5;}'
        +'.sigs{margin-top:40px;display:flex;justify-content:space-around;}'
        +'.sig p{margin-bottom:35px;font-weight:700;text-align:center;font-size:10pt;}'
        +'.sig-line{border-top:1.5px solid #000;width:180px;margin:0 auto;}'
        +'@media print{@page{size:A4 landscape;margin:1.5cm;}}</style>'
        +'</head><body>'
        +'<div class="hdr"><img src="'+logo+'" onerror="this.style.display=\'none\'">'
        +'<h1>'+nomeEsc+'</h1><p>HORÁRIO - ANO LECTIVO '+ano+'</p></div>'
        +'<table><thead><tr><th style="width:40px;">Per.</th><th style="width:80px;">Horário</th>'
        +'<th>Segunda</th><th>Terça</th><th>Quarta</th><th>Quinta</th><th>Sexta</th>'
        +'</tr></thead><tbody>'+rows+'</tbody></table>'
        +'<div class="sigs">'
        +'<div class="sig"><p>O Director de Turma</p><div class="sig-line"></div></div>'
        +'<div class="sig"><p>O Director da Escola</p><div class="sig-line"></div></div>'
        +'</div>'
        +'<div style="margin-top:15px;text-align:center;font-size:8pt;color:#888;border-top:1px solid #eee;padding-top:5px;">SIGE SoftGenial - '+hoje+'</div>'
        +'</body></html>');
    w.document.close();
    setTimeout(function() { w.print(); }, 500);
}

// ========================================
// ALUNOS
// ========================================
var _alData = [], _alTurmaId = 0, _alInfo = {};

function verAlunos(turmaId, nome, classe) {
    _alTurmaId = turmaId;
    _alInfo = { nome: nome, classe: classe };
    jQuery('#al-titulo').html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:22px;height:22px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg> Alunos: ' + nome);
    jQuery('#al-sub').text(classe + ' - Ano Lectivo <?php echo $ano_lectivo_ativo; ?>');
    jQuery('#al-total').text('Total: -');
    jQuery('#al-m').text('Masc: -');
    jQuery('#al-f').text('Fem: -');
    jQuery('#al-wrap').html('<div style="text-align:center;padding:60px;color:var(--sige-slate-400);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:40px;height:40px;animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg><p style="margin-top:12px;">A carregar...</p></div>');
    jQuery('#modal-alunos').css('display', 'flex').hide().fadeIn(300);
    jQuery('body').addClass('sige-modal-open');
    jQuery.post(ajaxurl, {
        action: 'sige_listar_alunos_turma',
        turma_id: turmaId,
        ano_lectivo: <?php echo $ano_lectivo_ativo; ?>,
        _sige_nonce: sigeAjax.nonce_turmas
    }, function(res) {
        if (!res.success) {
            jQuery('#al-wrap').html('<p style="padding:30px;color:var(--sige-error);">Erro: ' + (res.data || 'Falhou') + '</p>');
            return;
        }
        _alData = res.data || [];
        _renderAlunos();
    }).fail(function() {
        jQuery('#al-wrap').html('<p style="padding:30px;color:var(--sige-error);">Erro de ligação.</p>');
    });
}

function _renderAlunos() {
    var total = _alData.length, m = 0, f = 0;
    _alData.forEach(function(a) {
        var g = (a.genero || '').toLowerCase().charAt(0);
        if (g === 'm') m++; else if (g === 'f') f++;
    });
    jQuery('#al-total').text('Total: ' + total);
    jQuery('#al-m').text('Masc: ' + m);
    jQuery('#al-f').text('Fem: ' + f);
    if (!total) {
        jQuery('#al-wrap').html('<div style="text-align:center;padding:60px;color:var(--sige-slate-400);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:50px;height:50px;margin-bottom:16px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><p>Nenhum estudante matriculado.</p></div>');
        return;
    }
    var h = '<table class="sige-alunos-table"><thead><tr>'
          + '<th style="width:35px;color:#fff!important;background:#0f172a!important;">#</th><th style="color:#fff!important;background:#0f172a!important;">Nome Completo</th><th style="width:85px;color:#fff!important;background:#0f172a!important;">Processo</th>'
          + '<th style="width:55px;color:#fff!important;background:#0f172a!important;">Sexo</th><th style="width:95px;color:#fff!important;background:#0f172a!important;">Nasc.</th>'
          + '<th style="color:#fff!important;background:#0f172a!important;">Encarregado</th><th style="width:105px;color:#fff!important;background:#0f172a!important;">Contacto</th>'
          + '</tr></thead><tbody>';
    _alData.forEach(function(a, i) {
        h += '<tr>'
           + '<td style="text-align:center;font-weight:700;color:var(--sige-slate-500);">' + (i+1) + '</td>'
           + '<td><strong>' + _eh(a.nome_completo) + '</strong></td>'
           + '<td style="font-size:11px;color:var(--sige-slate-500);">' + _eh(a.numero_processo || '-') + '</td>'
           + '<td class="sige-u-tac">' + ((a.genero || '-').charAt(0).toUpperCase()) + '</td>'
           + '<td style="font-size:12px;">' + ((a.data_nascimento || '-').split(' ')[0]) + '</td>'
           + '<td style="font-size:12px;">' + _eh(a.nome_pai || a.nome_mae || '-') + '</td>'
           + '<td style="font-size:12px;">' + _eh(a.contacto_encarregado || '-') + '</td>'
           + '</tr>';
    });
    h += '</tbody></table>';
    jQuery('#al-wrap').html(h);
}

// ========================================
// IMPRIMIR LISTA DE ALUNOS (ORIGINAL)
// ========================================
function imprimirListaAlunos() {
    if (!_alData.length) { sigeTurmasShowNotice('Sem alunos para imprimir', 'Esta turma ainda não tem alunos listados para impressão.'); return; }
    var nomeEsc = <?php echo json_encode($escola->nome_escola ?: 'ESCOLA'); ?>;
    var logo    = <?php echo json_encode($logo_final); ?>;
    var ano     = <?php echo $ano_lectivo_ativo; ?>;
    var hoje    = new Date().toLocaleDateString('pt-PT', {day:'2-digit',month:'long',year:'numeric'});
    var total   = _alData.length, m = 0, f = 0;
    _alData.forEach(function(a) { var g=(a.genero||'').toLowerCase().charAt(0); if(g==='m')m++; else if(g==='f')f++; });
    var rows = '';
    _alData.forEach(function(a, i) {
        var bg = i % 2 === 0 ? '#ffffff' : '#f8fafc';
        rows += '<tr style="background:' + bg + ';">'
              + '<td style="text-align:center;font-weight:700;color:#555;">' + (i+1) + '</td>'
              + '<td><strong>' + _eh(a.nome_completo) + '</strong></td>'
              + '<td>' + _eh(a.numero_processo || '-') + '</td>'
              + '<td class="sige-u-tac">' + ((a.genero||'-').charAt(0).toUpperCase()) + '</td>'
              + '<td>' + ((a.data_nascimento || '-').split(' ')[0]) + '</td>'
              + '<td>' + _eh(a.nome_pai || a.nome_mae || '-') + '</td>'
              + '<td>' + _eh(a.contacto_encarregado || '-') + '</td>'
              + '</tr>';
    });
    var w = window.open('', '_blank', 'width=920,height=720');
    w.document.write('<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8"><title>Lista de Alunos</title>'
        + '<style><?php echo sige_utilities_inline_css(); ?>'
        + 'body{font-family:Arial,sans-serif;margin:20px;font-size:10pt;color:#000;}'
        + '.hdr{text-align:center;border-bottom:3px solid #1a237e;padding-bottom:14px;margin-bottom:16px;}'
        + '.hdr img{height:80px;display:block;margin:0 auto 10px;}'
        + '.hdr h1{font-size:15pt;font-weight:900;text-transform:uppercase;color:#1a237e;margin:4px 0;}'
        + '.hdr p{font-size:11pt;margin:3px 0;font-weight:700;}'
        + '.info{display:flex;justify-content:space-between;background:#e8eaf6;padding:8px 12px;border-radius:6px;margin-bottom:12px;font-size:10pt;font-weight:700;}'
        + '.stats{font-size:9pt;color:#555;margin-bottom:10px;}'
        + 'table{width:100%;border-collapse:collapse;}'
        + 'thead tr{background:#1a237e!important;color:white;}'
        + 'th{padding:8px 10px;text-align:left;font-size:9pt;font-weight:800;text-transform:uppercase;}'
        + 'td{border:1px solid #ccc;padding:7px 10px;font-size:10pt;}'
        + '.sigs{margin-top:50px;display:flex;justify-content:space-around;}'
        + '.sig p{margin-bottom:40px;font-weight:700;font-size:10pt;text-align:center;}'
        + '.sig-line{border-top:1.5px solid #000;width:180px;margin:0 auto;}'
        + '.ftr{margin-top:20px;text-align:center;font-size:8pt;color:#888;border-top:1px solid #eee;padding-top:5px;}'
        + '@media print{@page{size:A4 portrait;margin:1.5cm;}}'
        + '</style></head><body>'
        + '<div class="hdr"><img src="' + logo + '" onerror="this.style.display=\'none\'">'
        + '<h1>' + nomeEsc + '</h1>'
        + '<p>LISTA DE ALUNOS - ANO LECTIVO ' + ano + '</p></div>'
        + '<div class="info"><span>Turma: ' + _alInfo.nome + ' | ' + _alInfo.classe + '</span><span>' + hoje + '</span></div>'
        + '<div class="stats">Total: <strong>' + total + '</strong> | Masculino: <strong>' + m + '</strong> | Feminino: <strong>' + f + '</strong></div>'
        + '<table><thead><tr>'
        + '<th style="width:32px;">#</th><th>Nome Completo</th><th style="width:80px;">Processo</th>'
        + '<th style="width:50px;">Sexo</th><th style="width:88px;">Nasc.</th>'
        + '<th>Encarregado</th><th style="width:100px;">Contacto</th>'
        + '</tr></thead><tbody>' + rows + '</tbody></table>'
        + '<div class="sigs">'
        + '<div class="sig"><p>O Director de Turma</p><div class="sig-line"></div></div>'
        + '<div class="sig"><p>O Director da Escola</p><div class="sig-line"></div></div>'
        + '</div>'
        + '<div class="ftr">Processado pelo SIGE SoftGenial - ' + hoje + '</div>'
        + '</body></html>');
    w.document.close();
    setTimeout(function() { w.print(); }, 500);
}

// ========================================
// HELPERS
// ========================================
function _eh(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// ========================================
// EVENT LISTENERS
// ========================================
// Fechar modais: alem de esconder, retira a classe sige-modal-open (senao o
// body fica com overflow:hidden e a pagina deixa de fazer scroll, e o conteudo
// fica elevado). Cobre ESC, clique fora e o botao X (sigeFecharModalJq).
function sigeTurmasFecharModais() {
    jQuery('.sige-modal:visible').fadeOut(200);
    jQuery('body').removeClass('sige-modal-open');
    sigeTurmasConfirmCallback = null;
}
jQuery(document).on('keydown', function(e) {
    if (e.key === 'Escape') sigeTurmasFecharModais();
});
jQuery('.sige-modal').on('click', function(e) {
    if (e.target === this) { jQuery(this).fadeOut(200); jQuery('body').removeClass('sige-modal-open'); }
});
// O X dos modais usa o despachante partilhado sigeFecharModalJq (so faz fadeOut);
// aqui garantimos a limpeza da classe nesta view.
jQuery(document).on('click', '[data-sige-act="sigeFecharModalJq"]', function() {
    jQuery('body').removeClass('sige-modal-open');
});
</script>