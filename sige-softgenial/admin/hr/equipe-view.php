<?php
if (!defined('ABSPATH')) exit;
// ========================================
// VERIFICAÇÃO DE SEGURANÇA
// ========================================
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard_allows(
    ['rh.equipe_ver','rh.equipe_gerir'],
    ['sige_director','sige_gestor_rh','sige_admin_ti']
)) {
    echo "<div class='sige-access-denied'>
        <div class='access-denied-icon'>
            <svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                <circle cx='12' cy='12' r='10'/>
                <path d='M15 9l-6 6M9 9l6 6'/>
            </svg>
        </div>
        <h2>Acesso Restrito</h2>
        <p>O seu perfil SIGE não possui permissões para visualizar esta página.</p>
    </div>";
    return;
}
global $wpdb;
// ========================================
// 1. CARREGAR DADOS DA ESCOLA
// ========================================
$escola = sige_get_escola_perfil();
$logo_final = defined('SIGE_URL') ? SIGE_URL . 'assets/img/avatar-default.svg' : '';
if (!empty($escola->logo_docs_url)) {
    $logo_final = $escola->logo_docs_url;
} elseif (!empty($escola->logo_sistema_url)) {
    $logo_final = $escola->logo_sistema_url;
}
// ========================================
// 2. BUSCAR EQUIPA DE FUNCIONÁRIOS (scoped por escola)
// ========================================
$escola_id = function_exists('sige_get_escola_id') ? sige_get_escola_id() : 0;
$can_manage_equipe = function_exists('sige_page_guard_allows') ? sige_page_guard_allows(['rh.equipe_gerir'], ['sige_director','sige_gestor_rh','sige_admin_ti']) : (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'));
$can_export_folha = $can_manage_equipe;

if (!function_exists('sige_hr_json_array')) {
    function sige_hr_json_array($value, array $fallback = []) {
        if (empty($value)) {
            return $fallback;
        }
        $decoded = json_decode((string)$value, true);
        if (!is_array($decoded)) {
            $decoded = json_decode(stripslashes((string)$value), true);
        }
        return is_array($decoded) ? array_merge($fallback, $decoded) : $fallback;
    }
}
if (!function_exists('sige_hr_role_label')) {
    function sige_hr_role_label($role_slug) {
        $role_slug = (string)$role_slug;
        $map = [
            'sige_professor' => 'Professor',
            'sige_admin_ti' => 'Admin TI',
            'sige_director' => 'Direcção',
            'sige_financeiro' => 'Tesouraria',
            'sige_secretaria_geral' => 'Secretaria',
            'sige_assistente' => 'Assistente',
            'sige_educador'  => 'Educador',
            'sige_motorista' => 'Motorista',
            'sige_limpeza'   => 'Limpeza',
            'sige_secretario' => 'Secretário',
            'sige_gestor_rh'  => 'Gestor RH',
            'sige_pedagogico' => 'Dir. Pedagógico',
            'sige_recepcao'   => 'Recepção',
            'sige_guarda'     => 'Guarda / Portaria',
        ];
        if (isset($map[$role_slug])) {
            return $map[$role_slug];
        }
        return $role_slug !== '' ? ucfirst(str_replace('sige_', '', $role_slug)) : 'Sem perfil activo';
    }
}
// v12.30.0 - Fonte de verdade completa da equipa (corrige equipa invisivel + KPIs).
// Antes a lista usava SO get_users(role__in + meta sige_escola_id): quem tinha um
// PERFIL SIGE atribuido nesta escola (tabela sige_user_roles) mas sem a role WP ou
// sem a meta de escola ficava INVISIVEL na Equipa, embora aparecesse em Permissoes
// e Perfis. Como os KPIs iteram sobre $staff, ficavam tambem subcontados.
// Correccao: unir as duas fontes (A) staff WP scoped por escola e (B) perfil SIGE
// activo nesta escola (mesma fonte da pagina de Permissoes). O SUPER admin /
// administrador WP real NUNCA entra: so aparecem quem tem perfil SIGE.
$staff_role_slugs = [
    'sige_admin_ti', 'sige_director', 'sige_secretaria_geral',
    'sige_assistente', 'sige_financeiro', 'sige_professor',
    'sige_educador', 'sige_motorista', 'sige_limpeza',
    'sige_secretario', 'sige_gestor_rh', 'sige_pedagogico', 'sige_recepcao', 'sige_guarda'
];

// (A) Utilizadores WP com role de staff, scoped a escola pela meta sige_escola_id.
$__staff_ids_meta = array_map('intval', (array) get_users(array(
    'role__in'   => $staff_role_slugs,
    'meta_key'   => 'sige_escola_id',
    'meta_value' => $escola_id,
    'fields'     => 'ID',
)));

// (B) Utilizadores com PERFIL SIGE actual nesta escola.
//     FONTE DE VERDADE UNICA: includes/sige-staff-roster.php, partilhada com a
//     pagina de Permissoes e Perfis. As duas paginas usam EXACTAMENTE o mesmo
//     criterio (ur.ativo=1, sem r.ativo, excluindo portal), por isso as listas
//     nunca divergem. Foi a divergencia deste criterio que antes deixava parte
//     da equipa invisivel na Equipa apesar de aparecer em Permissoes.
$__staff_ids_sige = function_exists('sige_staff_active_profile_user_ids')
    ? sige_staff_active_profile_user_ids((int) $escola_id)
    : [];

$__staff_ids = array_values(array_unique(array_filter(array_merge($__staff_ids_meta, $__staff_ids_sige))));

// Fallback de migracao: se nada foi encontrado por (A)/(B), recuperar por email na
// tabela sige_professores (compatibilidade com dados antigos) e fixar a meta de escola.
if (empty($__staff_ids)) {
    $emails_escola = $wpdb->get_col($wpdb->prepare(
        "SELECT email FROM {$wpdb->prefix}sige_professores WHERE escola_id = %d",
        $escola_id
    ));
    if (!empty($emails_escola)) {
        $emails_escola = array_map('strtolower', (array) $emails_escola);
        $all_staff = get_users(array('role__in' => $staff_role_slugs, 'fields' => ['ID', 'user_email']));
        foreach ($all_staff as $u) {
            if (in_array(strtolower((string) $u->user_email), $emails_escola, true)) {
                update_user_meta((int) $u->ID, 'sige_escola_id', $escola_id); // migrar meta (one-time)
                $__staff_ids[] = (int) $u->ID;
            }
        }
        $__staff_ids = array_values(array_unique($__staff_ids));
    }
}

// Carregar objectos WP_User completos (render e KPIs precisam de ->roles, etc.).
$staff = !empty($__staff_ids) ? get_users(array(
    'include' => $__staff_ids,
    'orderby' => 'display_name',
    'order'   => 'ASC',
)) : [];

// SEGURANCA/REQUISITO: o SUPER admin / administrador WP real nunca aparece na
// equipa - so perfis SIGE. (sige_admin_ti continua, pois e perfil SIGE, nao admin WP.)
if (function_exists('sige_is_real_wp_admin_user')) {
    $staff = array_values(array_filter((array) $staff, function ($u) {
        return !sige_is_real_wp_admin_user((int) $u->ID);
    }));
}
// v12.11.9.8 - remoção RH é soft delete: ocultar utilizadores removidos sem apagar histórico.
$staff = array_values(array_filter((array)$staff, function($u) {
    return empty(get_user_meta((int)$u->ID, 'sige_staff_removed_at', true));
}));

// v12.11.9.9 - Arquivo RH: colaboradores removidos ficam preservados no backend,
// mas aparecem apenas numa área de consulta controlada, sem reactivação acidental.
$removed_staff = [];
if ($can_manage_equipe) {
    $removed_staff = get_users([
        'orderby' => 'display_name',
        'order' => 'ASC',
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => 'sige_staff_removed_escola_id',
                'value' => (string)$escola_id,
                'compare' => '=',
            ],
            [
                'key' => 'sige_staff_removed_at',
                'compare' => 'EXISTS',
            ],
        ],
    ]);
    $removed_staff = array_values(array_filter((array)$removed_staff, function($u) use ($escola_id) {
        return (int)get_user_meta((int)$u->ID, 'sige_staff_removed_escola_id', true) === (int)$escola_id
            && (string)get_user_meta((int)$u->ID, 'sige_staff_removed_at', true) !== '';
    }));
}
$total_removidos = count($removed_staff);
// ========================================
// 3. CALCULAR ESTATÍSTICAS
// ========================================
$total_funcionarios = count($staff);
$total_docentes = 0;
$total_administrativos = 0;
$total_apoio = 0;
$custo_salarial_mensal = 0;
$efectivos = 0;
$contratos = 0;
$total_activos = 0;
$total_inactivos = 0;
$roles_docentes = ['sige_professor', 'sige_educador', 'sige_pedagogico'];
$roles_apoio = ['sige_motorista', 'sige_limpeza', 'sige_recepcao', 'sige_guarda'];

// v12.30.1 - Perfil SIGE ACTUAL por colaborador (mesma fonte da pagina de
// Permissoes e Perfis). E a fonte de verdade do cargo e da categorizacao: um
// colaborador pode ter um papel WP legado (ex.: sige_guarda) diferente do perfil
// SIGE atribuido agora (ex.: professor). Antes, o cargo e os KPIs liam o papel
// WP -> mostravam cargo errado e contavam na categoria errada. Aqui traduzimos o
// slug do perfil actual (tabela sige_roles, sem prefixo) para o slug WP usado
// pelos rotulos/categorias. ASC na chave -> fica a atribuicao mais recente.
// Mapa user_id => papel WP do perfil SIGE actual, vindo da MESMA fonte unica.
$__perfil_sige_wp = []; // user_id => slug estilo WP (sige_*) do perfil SIGE actual
if (function_exists('sige_staff_active_profile_map')) {
    foreach (sige_staff_active_profile_map((int) $escola_id) as $__uid => $__perfil) {
        if (!empty($__perfil['wp'])) {
            $__perfil_sige_wp[(int) $__uid] = $__perfil['wp'];
        }
    }
}
// Papel efectivo do colaborador = perfil SIGE actual; recurso ao papel WP legado.
$sige_rh_eff_role = function ($s) use ($__perfil_sige_wp) {
    if (isset($__perfil_sige_wp[(int) $s->ID])) {
        return $__perfil_sige_wp[(int) $s->ID];
    }
    $wp = reset($s->roles);
    return $wp ?: '';
};

// [PERF] Preload all professores data in 1 query (eliminates N+1 in both loops)
$_profs_raw = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}sige_professores WHERE escola_id = %d ORDER BY id ASC",
    $escola_id
));
$_profs_map = [];
$_profs_by_id = [];
foreach ($_profs_raw as $_pr) {
    $_profs_map[strtolower($_pr->email)] = $_pr; // com ORDER BY id ASC, em duplicados fica a ficha mais recente no mapa por email
    $_profs_by_id[(int)$_pr->id] = $_pr;
}

// [v12.35.0] Registos normalizados para os Relatórios de RH. Construídos a partir
// da MESMA fonte das KPIs e da lista (staff + ficha de RH), pelo que os números
// reconciliam. A agregação fica numa função pura (includes/rh-relatorios.php).
$sige_rh_people = [];
foreach($staff as $s) {
    $__eff_role = $sige_rh_eff_role($s); // perfil SIGE actual (ou papel WP legado)
    if(in_array($__eff_role, $roles_docentes, true)) {
        $total_docentes++;
        $__cat = 'docente';
    } elseif(in_array($__eff_role, $roles_apoio, true)) {
        $total_apoio++;
        $__cat = 'apoio';
    } else {
        $total_administrativos++;
        $__cat = 'administrativo';
    }

    $prof_meta_id = (int)get_user_meta($s->ID, 'sige_professor_id', true);
    $rh = ($prof_meta_id > 0 && isset($_profs_by_id[$prof_meta_id])) ? $_profs_by_id[$prof_meta_id] : ($_profs_map[strtolower($s->user_email)] ?? null);

    $__ativo = true;
    if($rh) {
        $custo_salarial_mensal += (floatval($rh->salario_base) + floatval($rh->subsidio));
        if($rh->tipo_contrato === 'efectivo') {
            $efectivos++;
        } else {
            $contratos++;
        }
        if(isset($rh->status_ativo) && !(int)$rh->status_ativo) {
            $total_inactivos++;
            $__ativo = false;
        } else {
            $total_activos++;
        }
    } else {
        $total_activos++;
    }

    $sige_rh_people[] = [
        'categoria'      => $__cat,
        'tem_ficha'      => (bool) $rh,
        'ativo'          => $__ativo,
        'tipo_contrato'  => $rh ? (string) $rh->tipo_contrato : '',
        'nivel_carreira' => $rh ? (string) ($rh->nivel_carreira ?? '') : '',
        'regime_trabalho'=> $rh ? (string) ($rh->regime_trabalho ?? '') : '',
        'data_admissao'  => $rh ? (string) ($rh->data_admissao ?? '') : '',
        'salario'        => $rh ? (floatval($rh->salario_base) + floatval($rh->subsidio)) : 0.0,
        'nuit'           => $rh ? (string) ($rh->nuit ?? '') : '',
        'telemovel'      => $rh ? (string) ($rh->telemovel ?? '') : '',
        'email'          => (string) $s->user_email,
        'foto'           => $rh ? (trim((string) ($rh->foto_perfil ?? '')) !== '') : false,
    ];
}
?>
<style>
/* ========================================
   CSS CUSTOM PROPERTIES - Aligned with sg-* Design System v2.1
   ======================================== */
:root {
    /* Core: Navy (aligned with --sg-primary-*) */
    --sige-navy: var(--color-info-900);
    --sige-navy-light: var(--sg-theme-primary-800,var(--color-ink-700));
    --sige-slate-900: var(--color-black);
    --sige-slate-800: var(--color-ink-900);
    --sige-slate-700: var(--color-slate-800);
    --sige-slate-600: var(--color-slate-700);
    --sige-slate-500: var(--color-slate-500);
    --sige-slate-400: var(--color-slate-400);
    --sige-slate-300: var(--color-ink-200);
    --sige-slate-200: var(--color-ink-100);
    --sige-slate-100: var(--color-ink-50);
    --sige-slate-50: var(--color-slate-50);
    
    /* Primary: Navy (was Indigo var(--color-brand-400)) */
    --sige-primary: var(--sg-theme-primary,var(--color-brand-500));
    --sige-primary-dark: var(--color-info-700);
    --sige-primary-light: var(--color-ink-600);
    --sige-primary-50: var(--sg-theme-soft,var(--color-brand-50));
    
    /* Semantic Colors (aligned with --sg-*) */
    --sige-success: var(--color-success-700);
    --sige-success-light: var(--color-success-100);
    --sige-warning: var(--color-warning-500);
    --sige-warning-light: var(--color-warning-100);
    --sige-danger: var(--color-danger-500);
    --sige-danger-light: var(--color-danger-50);
    --sige-info: var(--sg-theme-primary,var(--color-brand-500));
    --sige-info-light: var(--sg-theme-soft,var(--color-brand-50));
    
    /* Category Colors (aligned with Navy palette) */
    --sige-docente: var(--color-success-800);
    --sige-admin: var(--sg-theme-primary,var(--color-brand-500));
    --sige-apoio: var(--color-brand-700);
    --sige-finance: var(--color-warning-500);
    
    /* Sombras e raios: herdados do sistema de design (assets/sige-tokens.css).
       Não redefinir aqui para não sombrear a fonte única de verdade nem
       contaminar o resto da página (este :root é global na rota da Equipa).
       --radius-full mapeia para o pill do sistema. */
    --radius-full: var(--radius-pill);
    
    /* Transitions */
    --ease-out: cubic-bezier(0.4, 0, 0.2, 1);
    --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
    --duration-fast: 150ms;
    --duration-normal: 250ms;
    --duration-slow: 400ms;
    
    /* Typography (fonts already loaded by style.css - no duplicate @import) */
    --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    --font-display: 'Plus Jakarta Sans', var(--font-sans);
}
/* ========================================
   RESET & BASE
   ======================================== */
.wrap.sige-rh {
    font-family: var(--font-sans);
    max-width: 1440px;
    margin: 0 auto;
    padding:var(--space-6);
    color: var(--sige-slate-800);
    background: linear-gradient(135deg, var(--sige-slate-50) 0%, var(--color-white) 100%);
    min-height: 100vh;
}
/* ========================================
   TOAST NOTIFICATIONS
   ======================================== */
.sige-toast-container {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 100000;
    display: flex;
    flex-direction: column;
    gap:var(--space-3);
    pointer-events: none;
}
.sige-toast {
    display: flex;
    align-items: center;
    gap:var(--space-3);
    padding:var(--space-4) var(--space-5);
    background: var(--sige-navy);
    color: white;
    border-radius: var(--radius-lg);
    box-shadow:var(--shadow-xs);
    transform: translateX(120%);
    opacity: 0;
    transition: all var(--duration-normal) var(--ease-spring);
    pointer-events: auto;
    max-width: 380px;
}
.sige-toast.show {
    transform: translateX(0);
    opacity: 1;
}
.sige-toast.success { border-left: 4px solid var(--sige-success); }
.sige-toast.error { border-left: 4px solid var(--sige-danger); }
.sige-toast.warning { border-left: 4px solid var(--sige-warning); }
.sige-toast.info { border-left: 4px solid var(--sige-info); }
.sige-toast-icon {
    width: 22px;
    height: 22px;
    flex-shrink: 0;
}
.sige-toast-content {
    flex: 1;
}
.sige-toast-title {
    font-weight:600;
    font-size:var(--fs-base);
    margin-bottom: 2px;
}
.sige-toast-message {
    font-size:var(--fs-sm);
    opacity: 0.85;
}
.sige-toast-close {
    background: none;
    border: none;
    color: white;
    opacity: 0.5;
    cursor: pointer;
    padding:var(--space-1);
    transition: opacity var(--duration-fast);
}
.sige-toast-close:hover { opacity: 1; }
/* ========================================
   PAGE HEADER
   ======================================== */
.sige-page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 32px;
    gap:var(--space-6);
}
.sige-page-title-group {
    display: flex;
    align-items: center;
    gap:var(--space-4);
}
.sige-page-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, var(--sige-primary) 0%, var(--sige-primary-dark) 100%);
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow:var(--shadow-xs);
}
.sige-page-icon svg {
    width: 28px;
    height: 28px;
    color: white;
}
.sige-page-title {
    font-family: var(--font-display);
    font-size:var(--fs-2xl);
    font-weight:700;
    color: var(--sige-navy);
    margin: 0;
    letter-spacing: -0.5px;
    line-height: 1.2;
}
.sige-page-subtitle {
    color: var(--sige-slate-500);
    font-size:var(--fs-base);
    margin:var(--space-1) 0 0;
    font-weight:500;
}
.sige-page-subtitle strong {
    color: var(--sige-primary);
    font-weight:600;
}
/* Header Actions */
.header-actions {
    display: flex;
    gap:var(--space-3);
    flex-wrap: wrap;
}
.btn-header {
    height: 44px;
    padding:0 var(--space-5);
    border-radius: var(--radius-md);
    border: 1.5px solid var(--sige-slate-200);
    background: white;
    font-weight:600;
    font-size:var(--fs-sm);
    cursor: pointer;
    transition: all var(--duration-fast) var(--ease-out);
    display: inline-flex;
    align-items: center;
    gap:var(--space-2);
    color: var(--sige-slate-700);
    white-space: nowrap;
}
.btn-header svg {
    width: 18px;
    height: 18px;
    opacity: 0.7;
}
.btn-header:hover {
    background: var(--sige-slate-50);
    border-color: var(--sige-slate-300);
    transform: translateY(-1px);
    box-shadow:var(--shadow-xs);
}
.btn-header.btn-primary {
    background: linear-gradient(135deg, var(--sige-primary) 0%, var(--sige-primary-dark) 100%);
    color: white;
    border: none;
    box-shadow:var(--shadow-xs);
}
.btn-header.btn-primary svg { opacity: 1; }
.btn-header.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow:var(--shadow-sm);
}
/* ========================================
   DASHBOARD STATS - BENTO GRID
   ======================================== */
.sige-stats-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap:var(--space-4);
    margin-bottom: 28px;
}
.sige-stat-card {
    background: white;
    border-radius: var(--radius-lg);
    padding:var(--space-5);
    border: 1px solid var(--sige-slate-200);
    transition: all var(--duration-normal) var(--ease-out);
    position: relative;
    overflow: hidden;
}
.sige-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--card-accent, var(--sige-slate-300));
    opacity: 0;
    transition: opacity var(--duration-fast);
}
.sige-stat-card:hover {
    transform: translateY(-2px);
    box-shadow:var(--shadow-xs);
    border-color: transparent;
}
.sige-stat-card:hover::before { opacity: 1; }
.sige-stat-card.highlight {
    background: linear-gradient(135deg, var(--sige-navy) 0%, var(--sige-slate-800) 100%);
    border: none;
    color: white;
}
.sige-stat-card.highlight .sige-stat-label { color: var(--sige-slate-400); }
.sige-stat-card.highlight .sige-stat-value { color: white; }
.sige-stat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}
.sige-stat-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
}
.sige-stat-icon svg {
    width: 22px;
    height: 22px;
}
.sige-stat-card.highlight .sige-stat-icon {
    background: rgba(255,255,255,0.1);
}
.sige-stat-card.highlight .sige-stat-icon svg { color: white; }
.sige-stat-label {
    font-size:var(--fs-sm);
    font-weight:600;
    color: var(--sige-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.sige-stat-value {
    font-family: var(--font-display);
    font-size:var(--fs-2xl);
    font-weight:700;
    color: var(--sige-navy);
    line-height: 1.1;
}
.sige-stat-subtitle {
    font-size:var(--fs-sm);
    color: var(--sige-slate-400);
    margin-top: 4px;
}
/* Stat Card Variants */
.sige-stat-card[data-type="docentes"] { --card-accent: var(--sige-docente); }
.sige-stat-card[data-type="docentes"] .sige-stat-icon { background: rgba(5, 150, 105, 0.1); color: var(--sige-docente); }
.sige-stat-card[data-type="admin"] { --card-accent: var(--sige-admin); }
.sige-stat-card[data-type="admin"] .sige-stat-icon { background: rgba(63, 81, 181, 0.1); color: var(--sige-admin); }
.sige-stat-card[data-type="apoio"] { --card-accent: var(--sige-apoio); }
.sige-stat-card[data-type="apoio"] .sige-stat-icon { background: rgba(139, 92, 246, 0.1); color: var(--sige-apoio); }
.sige-stat-card[data-type="salario"] { --card-accent: var(--sige-finance); }
.sige-stat-card[data-type="salario"] .sige-stat-icon { background: rgba(245, 158, 11, 0.1); color: var(--sige-finance); }
.sige-stat-card[data-type="contratos"] { --card-accent: var(--sige-info); }
.sige-stat-card[data-type="contratos"] .sige-stat-icon { background: rgba(59, 130, 246, 0.1); color: var(--sige-info); }
/* ========================================
   TOOLBAR
   ======================================== */
.sige-toolbar {
    background: white;
    border-radius: var(--radius-lg);
    padding:var(--space-4) var(--space-5);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap:var(--space-4);
    margin-bottom: 20px;
    border: 1px solid var(--sige-slate-200);
    box-shadow:var(--shadow-xs);
}
.toolbar-filters {
    display: flex;
    gap:var(--space-2);
    background: var(--sige-slate-100);
    padding:var(--space-1);
    border-radius: var(--radius-md);
}
.filter-btn {
    padding:var(--space-3) var(--space-4);
    border: none;
    background: transparent;
    border-radius: var(--radius-sm);
    cursor: pointer;
    font-weight:600;
    font-size:var(--fs-sm);
    color: var(--sige-slate-600);
    transition: all var(--duration-fast) var(--ease-out);
    display: flex;
    align-items: center;
    gap:var(--space-2);
}
.filter-btn svg {
    width: 16px;
    height: 16px;
    opacity: 0.6;
}
.filter-btn:hover {
    color: var(--sige-slate-800);
}
.filter-btn.active {
    background: white;
    color: var(--sige-primary);
    box-shadow:var(--shadow-xs);
}
.filter-btn.active svg { opacity: 1; }
.filter-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding:0 var(--space-2);
    border-radius: var(--radius-full);
    font-size:var(--fs-xs);
    font-weight:600;
    background: var(--sige-slate-200);
    color: var(--sige-slate-600);
    line-height: 1;
}
.filter-btn.active .filter-count {
    background: var(--sige-primary);
    color: white;
}
/* Search */
.sige-search {
    position: relative;
    flex: 1;
    max-width: 320px;
}
.sige-search input {
    width: 100%;
    height: 44px;
    padding:0 var(--space-4) 0 var(--space-10);
    border: 2px solid var(--sige-slate-200);
    border-radius: var(--radius-full);
    font-size:var(--fs-base);
    font-family: inherit;
    transition: all var(--duration-fast) var(--ease-out);
    background: var(--sige-slate-50);
}
.sige-search input::placeholder {
    color: var(--sige-slate-400);
}
.sige-search input:focus {
    outline: none;
    border-color: var(--sige-primary);
    background: white;
    box-shadow:var(--shadow-xs);
}
.sige-search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--sige-slate-400);
    width: 20px;
    height: 20px;
    pointer-events: none;
    transition: color var(--duration-fast);
}
.sige-search input:focus + .sige-search-icon,
.sige-search input:not(:placeholder-shown) + .sige-search-icon {
    color: var(--sige-primary);
}
/* Clear Search */
.sige-search-clear {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    border: none;
    background: var(--sige-slate-300);
    color: white;
    border-radius: var(--radius-full);
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    transition: all var(--duration-fast);
}
.sige-search-clear:hover { background: var(--sige-slate-500); }
.sige-search input:not(:placeholder-shown) ~ .sige-search-clear {
    display: flex;
}
/* ========================================
   TABLE CARD
   ======================================== */
.sige-table-card {
    background: white;
    border-radius: var(--radius-xl);
    border: 1px solid var(--sige-slate-200);
    overflow: hidden;
    box-shadow:var(--shadow-xs);
}
.sige-table {
    width: 100%;
    border-collapse: collapse;
}
.sige-table thead {
    background: var(--sige-slate-50);
    border-bottom: 1px solid var(--sige-slate-200);
}
.sige-table thead th {
    padding:var(--space-4) var(--space-4);
    font-size:var(--fs-xs);
    font-weight:600;
    color: var(--sige-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.6px;
    text-align: left;
    white-space: nowrap;
}
.sige-table tbody tr {
    border-bottom: 1px solid var(--sige-slate-100);
    transition: all var(--duration-fast) var(--ease-out);
}
.sige-table tbody tr:last-child { border-bottom: none; }
.sige-table tbody tr:hover {
    background: var(--sige-primary-50);
}
.sige-table tbody td {
    padding:var(--space-4);
    vertical-align: middle;
    font-size:var(--fs-base);
}
/* Inactive Row */
tr.inactive-row {
    opacity: 0.5;
    background: var(--sige-slate-50) !important;
}
tr.inactive-row:hover { opacity: 0.7; }
/* Staff Cell */
.staff-cell {
    display: flex;
    align-items: center;
    gap:var(--space-4);
}
.staff-avatar {
    width: 44px;
    height: 44px;
    border-radius: var(--radius-md);
    object-fit: cover;
    border: 2px solid var(--sige-slate-200);
    transition: all var(--duration-fast) var(--ease-out);
    background: var(--sige-slate-100);
}
.sige-table tbody tr:hover .staff-avatar {
    border-color: var(--sige-primary);
    transform: scale(1.05);
}
.staff-info { min-width: 0; }
.staff-name {
    font-weight:600;
    color: var(--sige-navy);
    font-size:var(--fs-base);
    margin-bottom: 2px;
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.staff-email {
    font-size:var(--fs-sm);
    color: var(--sige-slate-500);
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
/* Role Badge */
.role-badge {
    display: inline-flex;
    align-items: center;
    gap:var(--space-2);
    padding:var(--space-2) var(--space-3);
    border-radius: var(--radius-full);
    font-size:var(--fs-xs);
    font-weight:600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.role-badge svg {
    width: 12px;
    height: 12px;
}
.role-badge.docente { background: rgba(5, 150, 105, 0.1); color: var(--sige-docente); }
.role-badge.director { background: rgba(63, 81, 181, 0.1); color: var(--sige-admin); }
.role-badge.admin { background: var(--sige-slate-100); color: var(--sige-slate-700); }
.role-badge.financeiro { background: rgba(245, 158, 11, 0.1); color: var(--sige-finance); }
.role-badge.apoio { background: rgba(139, 92, 246, 0.1); color: var(--sige-apoio); }
/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap:var(--space-2);
    padding:var(--space-2) var(--space-3);
    border-radius: var(--radius-full);
    font-size:var(--fs-xs);
    font-weight:600;
}
.status-badge .status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
    animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
.status-badge.active {
    background: var(--sige-success-light);
    color: var(--sige-success);
}
.status-badge.inactive {
    background: var(--sige-slate-100);
    color: var(--sige-slate-500);
}
.status-badge.inactive .status-dot { animation: none; }
/* Validity */
.validity-cell {
    font-size:var(--fs-sm);
    font-weight:600;
    color: var(--sige-slate-700);
}
/* Actions */
.table-actions {
    display: flex;
    gap:var(--space-1);
    justify-content: flex-end;
    opacity: 0.7;
    transition: opacity var(--duration-fast);
}
.sige-table tbody tr:hover .table-actions { opacity: 1; }
.btn-action {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-sm);
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--duration-fast) var(--ease-out);
    position: relative;
}
.btn-action svg {
    width: 18px;
    height: 18px;
}
.btn-action::before {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%) translateY(-4px);
    padding:var(--space-2) var(--space-3);
    background: var(--sige-navy);
    color: white;
    font-size:var(--fs-xs);
    font-weight:600;
    border-radius: var(--radius-sm);
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all var(--duration-fast);
    z-index: 100;
}
.btn-action:hover::before {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(-8px);
}
.btn-action.btn-cracha { background: rgba(63, 81, 181, 0.1); color: var(--sige-admin); }
.btn-action.btn-cracha:hover { background: var(--sige-admin); color: white; }
.btn-action.btn-edit { background: rgba(59, 130, 246, 0.1); color: var(--sige-info); }
.btn-action.btn-edit:hover { background: var(--sige-info); color: white; }
.btn-action.btn-key { background: rgba(245, 158, 11, 0.1); color: var(--sige-warning); }
.btn-action.btn-key:hover { background: var(--sige-warning); color: white; }
.btn-action.btn-toggle-on { background: rgba(16, 185, 129, 0.1); color: var(--sige-success); }
.btn-action.btn-toggle-on:hover { background: var(--sige-success); color: white; }
.btn-action.btn-toggle-off { background: rgba(239, 68, 68, 0.1); color: var(--sige-danger); }
.btn-action.btn-toggle-off:hover { background: var(--sige-danger); color: white; }
.btn-action.btn-delete { background: rgba(239, 68, 68, 0.1); color: var(--sige-danger); }
.btn-action.btn-delete:hover { background: var(--sige-danger); color: white; }
/* ========================================
   EMPTY STATE
   ======================================== */
.sige-empty-state {
    padding:var(--space-10) var(--space-10);
    text-align: center;
}
.sige-empty-icon {
    width: 80px;
    height: 80px;
    margin:0 auto var(--space-6);
    background: var(--sige-slate-100);
    border-radius: var(--radius-xl);
    display: flex;
    align-items: center;
    justify-content: center;
}
.sige-empty-icon svg {
    width: 40px;
    height: 40px;
    color: var(--sige-slate-400);
}
.sige-empty-title {
    font-size:var(--fs-lg);
    font-weight:600;
    color: var(--sige-slate-700);
    margin-bottom: 8px;
}
.sige-empty-desc {
    font-size:var(--fs-base);
    color: var(--sige-slate-500);
    max-width: 300px;
    margin: 0 auto;
}
/* ========================================
   MODAL
   ======================================== */
.sige-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(8px);
    z-index: 100000;
    display: none;
    align-items: center;
    justify-content: center;
    padding:var(--space-6);
    opacity: 0;
    transition: opacity var(--duration-normal) var(--ease-out);
}
.sige-modal.active {
    display: flex;
    opacity: 1;
}
.modal-content {
    background: white;
    border-radius: var(--radius-xl);
    width: 100%;
    max-width: 720px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow:var(--shadow-xs);
    transform: translateY(20px) scale(0.95);
    opacity: 0;
    animation: modalEnter var(--duration-normal) var(--ease-spring) forwards;
}
@keyframes modalEnter {
    to {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
}
.modal-header {
    background: linear-gradient(135deg, var(--sige-navy) 0%, var(--sige-slate-800) 100%);
    color: white;
    padding:var(--space-6) var(--space-8);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header h3 {
    margin: 0;
    font-family: var(--font-display);
    font-weight:600;
    font-size:var(--fs-lg);
    color: var(--color-white);
}
.modal-close {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-md);
    background: rgba(255,255,255,0.1);
    border: none;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--duration-fast);
}
.modal-close:hover {
    background: rgba(255,255,255,0.2);
    transform: rotate(90deg);
}
.modal-close svg {
    width: 20px;
    height: 20px;
}
/* Modal Tabs - Stepper Style */
.modal-tabs {
    display: flex;
    background: var(--sige-slate-50);
    border-bottom: 1px solid var(--sige-slate-200);
    padding: 0;
}
.tab-btn {
    flex: 1;
    padding:var(--space-4) var(--space-5);
    border: none;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap:var(--space-3);
    font-size:var(--fs-sm);
    font-weight:600;
    color: var(--sige-slate-500);
    transition: all var(--duration-fast);
    position: relative;
}
.tab-btn::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--sige-primary);
    transform: scaleX(0);
    transition: transform var(--duration-fast);
}
.tab-btn:hover { color: var(--sige-slate-700); }
.tab-btn.active {
    color: var(--sige-primary);
    background: white;
}
.tab-btn.active::after { transform: scaleX(1); }
.tab-step {
    width: 26px;
    height: 26px;
    border-radius: var(--radius-full);
    background: var(--sige-slate-200);
    color: var(--sige-slate-600);
    font-size:var(--fs-sm);
    font-weight:600;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--duration-fast);
}
.tab-btn.active .tab-step {
    background: var(--sige-primary);
    color: white;
}
/* Modal Body */
.modal-body {
    padding:var(--space-8);
    overflow-y: auto;
    flex: 1;
}
.tab-content {
    display: none;
    animation: fadeIn var(--duration-fast) var(--ease-out);
}
.tab-content.active { display: block; }
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
/* Form Grid */
.form-grid {
    display: grid;
    gap:var(--space-5);
}
.form-grid-2 { grid-template-columns: repeat(2, 1fr); }
.form-grid-3 { grid-template-columns: repeat(3, 1fr); }
.field-group {
    display: flex;
    flex-direction: column;
}
.field-group.span-2 { grid-column: span 2; }
.field-group.span-3 { grid-column: span 3; }
.field-group label {
    font-size:var(--fs-sm);
    font-weight:600;
    color: var(--sige-slate-600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}
.field-group label .required {
    color: var(--sige-danger);
}
.field-group input,
.field-group select {
    height: 48px;
    padding:0 var(--space-4);
    border: 2px solid var(--sige-slate-200);
    border-radius: var(--radius-md);
    font-size:var(--fs-base);
    font-family: inherit;
    transition: all var(--duration-fast) var(--ease-out);
    background: white;
}
.field-group input:focus,
.field-group select:focus {
    outline: none;
    border-color: var(--sige-primary);
    box-shadow:var(--shadow-xs);
}
.field-group input.error,
.field-group select.error {
    border-color: var(--sige-danger);
    background: var(--sige-danger-light);
}
.field-hint {
    font-size:var(--fs-sm);
    color: var(--sige-slate-500);
    margin-top: 6px;
}
.field-error {
    font-size:var(--fs-sm);
    color: var(--sige-danger);
    margin-top: 6px;
    display: none;
}
/* Photo Upload */
.photo-upload-area {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap:var(--space-3);
    padding:var(--space-5);
    background: var(--sige-slate-50);
    border-radius: var(--radius-lg);
    border: 2px dashed var(--sige-slate-300);
    transition: all var(--duration-fast);
}
.photo-upload-area:hover {
    border-color: var(--sige-primary);
    background: var(--sige-primary-50);
}
.photo-preview {
    width: 100px;
    height: 100px;
    border-radius: var(--radius-lg);
    object-fit: cover;
    border: 3px solid white;
    box-shadow:var(--shadow-xs);
}
.btn-upload {
    padding:var(--space-3) var(--space-5);
    background: var(--sige-primary);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    font-weight:600;
    font-size:var(--fs-sm);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap:var(--space-2);
    transition: all var(--duration-fast);
}
.btn-upload:hover {
    background: var(--sige-primary-dark);
    transform: translateY(-1px);
}
.btn-upload svg {
    width: 16px;
    height: 16px;
}
/* Info Box */
.info-box {
    padding:var(--space-4) var(--space-5);
    background: var(--sige-info-light);
    border-radius: var(--radius-md);
    border-left: 4px solid var(--sige-info);
    margin-top: 20px;
}
.info-box p {
    margin: 0;
    font-size:var(--fs-sm);
    color: var(--sige-slate-700);
    display: flex;
    align-items: flex-start;
    gap:var(--space-3);
}
.info-box svg {
    width: 18px;
    height: 18px;
    color: var(--sige-info);
    flex-shrink: 0;
    margin-top: 1px;
}
/* Highlighted Section */
.highlighted-section {
    background: linear-gradient(135deg, var(--sige-success-light) 0%, var(--color-success-200) 100%);
    padding:var(--space-6);
    border-radius: var(--radius-lg);
    border-left: 4px solid var(--sige-success);
}
/* Modal Footer */
.modal-footer {
    padding:var(--space-5) var(--space-8);
    background: var(--sige-slate-50);
    border-top: 1px solid var(--sige-slate-200);
    display: flex;
    gap:var(--space-3);
    justify-content: flex-end;
}
.btn-modal {
    height: 48px;
    padding:0 var(--space-8);
    border-radius: var(--radius-md);
    font-weight:600;
    font-size:var(--fs-base);
    cursor: pointer;
    transition: all var(--duration-fast) var(--ease-out);
    border: none;
    display: inline-flex;
    align-items: center;
    gap:var(--space-2);
}
.btn-modal svg {
    width: 18px;
    height: 18px;
}
.btn-modal.btn-cancel {
    background: white;
    color: var(--sige-slate-700);
    border: 2px solid var(--sige-slate-200);
}
.btn-modal.btn-cancel:hover {
    background: var(--sige-slate-100);
    border-color: var(--sige-slate-300);
}
.btn-modal.btn-submit {
    background: linear-gradient(135deg, var(--sige-primary) 0%, var(--sige-primary-dark) 100%);
    color: white;
    box-shadow:var(--shadow-xs);
}
.btn-modal.btn-submit:hover {
    transform: translateY(-1px);
    box-shadow:var(--shadow-xs);
}
.btn-modal:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}
/* Loading State */
.btn-modal.loading {
    pointer-events: none;
}
.btn-modal.loading::after {
    content: '';
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top-color: currentColor;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-6px); }
    40%, 80% { transform: translateX(6px); }
}
/* ========================================
   RESPONSIVENESS
   ======================================== */
@media (max-width: 1200px) {
    .sige-stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (max-width: 900px) {
    .sige-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .sige-page-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .header-actions {
        justify-content: flex-start;
    }
}
@media (max-width: 768px) {
    .wrap.sige-rh { padding:var(--space-4); }
    
    .sige-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .sige-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .toolbar-filters {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .sige-search { max-width: none; }
    
    .form-grid-2,
    .form-grid-3 {
        grid-template-columns: 1fr;
    }
    
    .field-group.span-2,
    .field-group.span-3 {
        grid-column: span 1;
    }
    
    .modal-content {
        max-height: 100vh;
        border-radius: 0;
    }
    
    .table-actions { flex-wrap: wrap; }
}
/* Metadados ocultos para exportação */
.meta-data {
    display: none;
}
/* ========================================
   ACCESS DENIED
   ======================================== */
.sige-access-denied {
    text-align: center;
    padding:var(--space-10) var(--space-10);
    max-width: 400px;
    margin: 0 auto;
}
.access-denied-icon {
    width: 80px;
    height: 80px;
    background: var(--sige-danger-light);
    border-radius: var(--radius-xl);
    display: flex;
    align-items: center;
    justify-content: center;
    margin:0 auto var(--space-6);
}
.access-denied-icon svg {
    width: 40px;
    height: 40px;
    color: var(--sige-danger);
}
.sige-access-denied h2 {
    font-size:var(--fs-xl);
    font-weight:600;
    color: var(--sige-slate-800);
    margin:0 0 var(--space-2);
}
.sige-access-denied p {
    color: var(--sige-slate-500);
    margin: 0;
}


/* ========================================
   EQUIPA E PROFESSORES - Harmonia visual Produto PRO v12.10.36
   Painel Principal como referência visual
   ======================================== */
body.sige-admin-app.sige-view-equipe .sg-product-page-head{display:none!important;}
body.sige-admin-app.sige-view-equipe .sg-app-page{max-width:none!important;width:100%!important;padding-top:0!important;}
body.sige-admin-app.sige-view-equipe .sg-app-content{padding-left:30px!important;padding-right:30px!important;}
body.sige-admin-app.sige-view-equipe .sg-app-page > .wrap.sige-rh{margin:0!important;max-width:none!important;width:100%!important;padding:0!important;background:transparent!important;min-height:auto!important;font-family:'Inter',system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif!important;}
.sige-rh{--sige-primary:var(--sg-theme-primary,var(--sg-primary-600,var(--color-brand-500)));--sige-primary-dark:var(--sg-theme-primary-900,var(--sg-primary-900,var(--color-brand-700)));--sige-primary-light:var(--sg-theme-primary-100,var(--color-brand-100));--sige-navy:var(--sg-theme-ink,var(--color-ink-500));--sige-navy-light:var(--sg-theme-primary-700,var(--color-brand-500));--sige-slate-50:var(--color-slate-50);--sige-slate-100:var(--color-ink-50);--sige-slate-200:var(--color-ink-100);--sige-slate-300:var(--color-slate-200);--sige-slate-400:var(--color-slate-400);--sige-slate-500:var(--color-slate-500);--sige-slate-600:var(--color-slate-700);--sige-slate-700:var(--color-slate-800);--sige-slate-800:var(--color-slate-900);--sige-slate-900:var(--color-black);--shadow-xs:0 1px 2px rgba(22,24,40,.04);--shadow-sm:0 10px 28px rgba(28,25,72,.06);--shadow-md:0 18px 44px rgba(28,25,72,.08);--shadow-lg:0 24px 70px rgba(28,25,72,.12);--shadow-xl:0 32px 90px rgba(28,25,72,.20);--shadow-glow:0 0 0 4px rgba(var(--sg-theme-primary-rgb,90,63,214),.12);}
.sige-rh *{box-sizing:border-box;}
.sige-rh .sige-hero{position:relative;overflow:hidden;border-radius:var(--radius-xl);background:linear-gradient(135deg,var(--color-white) 0%,var(--color-white) 45%,var(--sg-theme-primary-50,var(--color-brand-50)) 100%);border:1px solid rgba(30,34,60,.06);box-shadow:var(--shadow-lg);padding:var(--space-8) var(--space-10);margin:0 0 var(--space-6);}
.sige-rh .sige-hero:after{content:'';position:absolute;right:-90px;top:-120px;width:340px;height:340px;border-radius:var(--radius-pill);background:rgba(var(--sg-theme-primary-rgb,90,63,214),.10);}
.sige-rh .sige-hero-content{position:relative;z-index:1;display:grid;grid-template-columns:minmax(0,1.35fr) minmax(260px,.65fr);gap:var(--space-6);align-items:center;}
.sige-rh .sige-hero-kicker{display:inline-flex;align-items:center;gap:var(--space-2);margin-bottom:12px;font-size:var(--fs-sm);font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--sg-theme-primary-800,var(--sige-primary-dark));}
.sige-rh .sige-hero-dot-mini{width:8px;height:8px;border-radius:var(--radius-pill);background:var(--sg-theme-accent,var(--color-success-700));box-shadow:var(--shadow-xs);}
.sige-rh .sige-hero h1{font-family:var(--font-display,'Plus Jakarta Sans',system-ui,sans-serif);font-size:var(--fs-4xl);line-height:1.05;font-weight:700;letter-spacing:-.05em;color:var(--color-black);margin:0 0 var(--space-3);}
.sige-rh .sige-hero p{max-width:720px;margin:0;color:var(--color-slate-700);font-size:var(--fs-md);line-height:1.65;font-weight:600;}
.sige-rh .sige-hero-actions{display:flex;gap:var(--space-3);flex-wrap:wrap;margin-top:24px;}
.sige-rh .sige-btn-primary-soft,.sige-rh .sige-btn-ghost-soft{height:48px;padding:0 var(--space-6);border-radius:var(--radius-md);border:0;display:inline-flex;align-items:center;justify-content:center;gap:var(--space-3);font-size:var(--fs-base);font-weight:700;cursor:pointer;text-decoration:none;transition:all .18s ease;white-space:nowrap;}
.sige-rh .sige-btn-primary-soft{background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-900,var(--color-brand-700)));color:var(--color-white);box-shadow:var(--shadow-md);}
.sige-rh .sige-btn-primary-soft:hover{transform:translateY(-2px);box-shadow:var(--shadow-md);color:var(--color-white);}
.sige-rh .sige-btn-ghost-soft{background:var(--color-white);color:var(--color-ink-900);border:1px solid rgba(30,34,60,.08);box-shadow:var(--shadow-sm);}
.sige-rh .sige-btn-ghost-soft:hover{transform:translateY(-1px);color:var(--sg-theme-primary-800,var(--color-brand-700));border-color:rgba(var(--sg-theme-primary-rgb,90,63,214),.18);}
.sige-rh .sige-btn-primary-soft svg,.sige-rh .sige-btn-ghost-soft svg{width:18px;height:18px;stroke:currentColor;}
.sige-rh .sige-hero-panel{min-height:150px;border-radius:var(--radius-xl);background:linear-gradient(135deg,rgba(var(--sg-theme-primary-rgb,90,63,214),.09),rgba(var(--sg-theme-primary-rgb,90,63,214),.03));border:1px solid rgba(var(--sg-theme-primary-rgb,90,63,214),.08);display:flex;align-items:center;justify-content:center;}
.sige-rh .sige-rh-illustration{position:relative;width:260px;height:130px;}
.sige-rh .sige-rh-person{position:absolute;bottom:38px;width:54px;height:54px;border-radius:var(--radius-lg);background:var(--color-white);box-shadow:var(--shadow-md);}
.sige-rh .sige-rh-person:before{content:'';position:absolute;left:50%;top:10px;transform:translateX(-50%);width:18px;height:18px;border-radius:var(--radius-pill);background:var(--sg-theme-primary,var(--color-brand-500));opacity:.78;}
.sige-rh .sige-rh-person:after{content:'';position:absolute;left:50%;bottom:10px;transform:translateX(-50%);width:30px;height:14px;border-radius:999px 999px 8px 8px;background:var(--sg-theme-primary-100,var(--color-brand-100));}
.sige-rh .sige-rh-person.p1{left:28px;transform:rotate(-4deg);}
.sige-rh .sige-rh-person.p2{left:102px;bottom:58px;width:64px;height:64px;background:var(--sg-theme-primary,var(--color-brand-500));}
.sige-rh .sige-rh-person.p2:before{background:var(--color-white);}.sige-rh .sige-rh-person.p2:after{background:rgba(255,255,255,.70);}
.sige-rh .sige-rh-person.p3{right:26px;transform:rotate(4deg);}
.sige-rh .sige-rh-card-line{position:absolute;height:9px;border-radius:var(--radius-pill);background:rgba(var(--sg-theme-primary-rgb,90,63,214),.18);}
.sige-rh .sige-rh-card-line.w1{left:22px;right:22px;bottom:18px;}.sige-rh .sige-rh-card-line.w2{left:54px;right:54px;bottom:0;opacity:.65}.sige-rh .sige-rh-card-line.w3{left:86px;right:86px;top:4px;opacity:.45}
.sige-rh .sige-stats-grid{grid-template-columns:repeat(6,minmax(0,1fr))!important;gap:var(--space-4)!important;margin:0 0 var(--space-6)!important;}
.sige-rh .sige-stat-card{min-height:132px;border-radius:var(--radius-xl)!important;background:var(--color-white)!important;border:1px solid rgba(30,34,60,.06)!important;box-shadow:var(--shadow-md);padding:var(--space-6)!important;}
.sige-rh .sige-stat-card:before{display:none!important;}
.sige-rh .sige-stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-lg);}
.sige-rh .sige-stat-card.highlight{background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-900,var(--color-brand-600)))!important;color:var(--color-white)!important;}
.sige-rh .sige-stat-icon{width:48px!important;height:48px!important;border-radius:var(--radius-lg)!important;background:var(--sg-theme-primary-50,var(--color-brand-50))!important;color:var(--sg-theme-primary,var(--color-brand-500))!important;}
.sige-rh .sige-stat-card.highlight .sige-stat-icon{background:rgba(255,255,255,.15)!important;color:var(--color-white)!important;}
.sige-rh .sige-stat-label{font-size:var(--fs-sm)!important;font-weight:700!important;letter-spacing:.08em!important;color:var(--color-slate-500)!important;}
.sige-rh .sige-stat-card.highlight .sige-stat-label{color:rgba(255,255,255,.72)!important;}
.sige-rh .sige-stat-value{font-family:var(--font-display,'Plus Jakarta Sans',system-ui,sans-serif)!important;font-size:var(--fs-3xl)!important;font-weight:700!important;letter-spacing:-.05em!important;color:var(--color-black)!important;}
.sige-rh .sige-stat-card.highlight .sige-stat-value{color:var(--color-white)!important;}
.sige-rh .sige-toolbar{border-radius:var(--radius-xl)!important;background:var(--color-white)!important;border:1px solid rgba(30,34,60,.06)!important;box-shadow:var(--shadow-md);padding:var(--space-5)!important;margin-bottom:20px!important;}
.sige-rh .toolbar-filters{background:var(--color-ink-50)!important;border-radius:var(--radius-lg)!important;padding:var(--space-2)!important;}
.sige-rh .filter-btn{border-radius:var(--radius-md)!important;color:var(--color-slate-700)!important;font-weight:700!important;}
.sige-rh .filter-btn.active{background:var(--color-white)!important;color:var(--sg-theme-primary-800,var(--color-brand-700))!important;box-shadow:var(--shadow-sm);}
.sige-rh .filter-btn.active .filter-count{background:var(--sg-theme-primary,var(--color-brand-500))!important;color:var(--color-white)!important;}
.sige-rh .sige-search input{height:48px!important;border:1px solid rgba(30,34,60,.10)!important;border-radius:var(--radius-lg)!important;background:var(--color-white)!important;color:var(--color-ink-800)!important;font-weight:600!important;}
.sige-rh .sige-search input:focus{border-color:rgba(var(--sg-theme-primary-rgb,90,63,214),.35)!important;box-shadow:var(--shadow-xs);}
.sige-rh .sige-table-card{border-radius:var(--radius-xl)!important;border:1px solid rgba(30,34,60,.06)!important;box-shadow:var(--shadow-md);overflow:hidden!important;background:var(--color-white)!important;}
.sige-rh .sige-table thead{background:var(--color-slate-50)!important;}
.sige-rh .sige-table thead th{color:var(--color-slate-500)!important;font-weight:700!important;letter-spacing:.08em!important;padding:var(--space-4) var(--space-5)!important;}
.sige-rh .sige-table tbody td{padding:var(--space-5)!important;}
.sige-rh .staff-avatar{width:52px!important;height:52px!important;border-radius:var(--radius-lg)!important;border:1px solid var(--color-slate-100)!important;background:var(--color-slate-50)!important;}
.sige-rh .staff-name{font-size:var(--fs-md)!important;color:var(--color-black)!important;font-weight:700!important;}
.sige-rh .role-badge,.sige-rh .status-badge{border-radius:var(--radius-pill)!important;font-weight:700!important;text-transform:none!important;letter-spacing:0!important;padding:var(--space-2) var(--space-3)!important;}
.sige-rh .table-actions{opacity:1!important;gap:var(--space-2)!important;}
.sige-rh .btn-action{width:36px!important;height:36px!important;border-radius:var(--radius-md)!important;}
.sige-rh .sige-modal{background:rgba(31,34,49,.56)!important;backdrop-filter:blur(8px)!important;z-index:999999!important;}
/* Modal acima da barra lateral: .sg-app-content e um contexto de empilhamento
   (position:relative;z-index:1) abaixo da sidebar (z-index alto no shell PRO),
   pelo que um modal filho do conteudo, por mais alto que seja o seu z-index,
   fica tapado pela barra lateral. Enquanto um modal da Equipa esta aberto,
   elevamos o conteudo acima da sidebar; o fundo do modal passa a cobrir tambem
   a barra lateral (UX correcta). Scoped a esta view: nao afecta outros ecrans. */
body.sige-admin-app.sige-view-equipe.sige-rh-modal-open .sg-app-content{z-index:10090!important;}
.sige-rh .modal-content{border-radius:var(--radius-xl)!important;border:1px solid rgba(30,34,60,.08)!important;box-shadow:var(--shadow-lg);max-height:90vh;overflow-y:auto;}
.sige-rh .modal-header{background:var(--color-white)!important;color:var(--color-black)!important;border-bottom:1px solid var(--color-slate-100)!important;padding:var(--space-6) var(--space-6)!important;}
.sige-rh .modal-header h3{color:var(--color-black)!important;font-weight:700!important;letter-spacing:-.035em!important;}
.sige-rh .modal-close{background:var(--color-brand-50)!important;color:var(--sg-theme-primary,var(--color-brand-500))!important;}
.sige-rh .tab-btn.active{color:var(--sg-theme-primary-800,var(--color-brand-700))!important;}
.sige-rh .tab-btn:after{background:var(--sg-theme-primary,var(--color-brand-500))!important;}
.sige-rh .tab-btn.active .tab-step{background:var(--sg-theme-primary,var(--color-brand-500))!important;color:var(--color-white)!important;}
.sige-rh .field-group label{color:var(--color-slate-600)!important;font-size:var(--fs-sm)!important;letter-spacing:.05em!important;font-weight:700!important;}
.sige-rh .field-group input,.sige-rh .field-group select{height:48px!important;border-radius:var(--radius-lg)!important;border:1px solid var(--color-slate-100)!important;color:var(--color-ink-800)!important;font-weight:600!important;}
.sige-rh .field-group input:focus,.sige-rh .field-group select:focus{border-color:rgba(var(--sg-theme-primary-rgb,90,63,214),.35)!important;box-shadow:var(--shadow-xs);}
.sige-rh .btn-modal.btn-submit{background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-900,var(--color-brand-700)))!important;color:var(--color-white)!important;box-shadow:var(--shadow-md);}
.sige-rh .btn-header{border-radius:var(--radius-md)!important;height:46px!important;font-weight:700!important;}
.sige-rh .btn-header.btn-primary{background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-900,var(--color-brand-700)))!important;}
.sige-rh-confirm .modal-content{max-width:520px!important;}
.sige-rh-confirm .sige-confirm-panel{padding:var(--space-6);text-align:left;}
.sige-rh-confirm .sige-confirm-icon{width:58px;height:58px;border-radius:var(--radius-xl);background:var(--sg-theme-primary-50,var(--color-brand-50));color:var(--sg-theme-primary,var(--color-brand-500));display:flex;align-items:center;justify-content:center;margin-bottom:16px;}
.sige-rh-confirm .sige-confirm-icon svg{width:28px;height:28px;}
.sige-rh-confirm .sige-confirm-title{font-family:var(--font-display,'Plus Jakarta Sans',system-ui,sans-serif);font-size:var(--fs-xl);font-weight:700;letter-spacing:-.04em;color:var(--color-black);margin:0 0 var(--space-2);}
.sige-rh-confirm .sige-confirm-message{font-size:var(--fs-base);line-height:1.6;color:var(--color-slate-700);margin:0;}
.sige-rh-confirm .sige-confirm-detail{margin-top:14px;padding:var(--space-3) var(--space-4);border-radius:var(--radius-lg);background:var(--color-slate-50);border:1px solid var(--color-slate-100);color:var(--color-ink-500);font-weight:700;}
@media (max-width:1200px){.sige-rh .sige-stats-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important}.sige-rh .sige-hero-content{grid-template-columns:1fr!important}.sige-rh .sige-hero-panel{display:none!important}}
@media (max-width:900px){body.sige-admin-app.sige-view-equipe .sg-app-content{padding-left:22px!important;padding-right:22px!important}.sige-rh .sige-stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}.sige-rh .sige-toolbar{display:grid!important;grid-template-columns:1fr!important}.sige-rh .sige-search{max-width:none!important}.sige-rh .toolbar-filters{overflow:auto!important}}
@media (max-width:720px){body.sige-admin-app.sige-view-equipe .sg-app-content{padding-left:16px!important;padding-right:16px!important}.sige-rh .sige-hero{padding:var(--space-6) var(--space-5)!important;border-radius:22px!important}.sige-rh .sige-hero h1{font-size:var(--fs-2xl)!important}.sige-rh .sige-hero-actions{display:grid!important;grid-template-columns:1fr!important}.sige-rh .sige-btn-primary-soft,.sige-rh .sige-btn-ghost-soft{width:100%!important}.sige-rh .sige-stats-grid{grid-template-columns:1fr!important}.sige-rh .sige-table-card{overflow-x:auto!important}.sige-rh .modal-content{max-width:calc(100vw - 24px)!important;border-radius:22px!important}.sige-rh .modal-footer{display:grid!important;grid-template-columns:1fr!important}.sige-rh .modal-footer button{width:100%!important}.sige-rh .modal-tabs{overflow-x:auto!important}.sige-rh .tab-btn{min-width:160px!important}}


/* ============================================================================
   v12.10.38 - Equipa: KPIs sem sobreposição visual
   ============================================================================ */
.sige-rh.sg-dashboard-v2{--sgv2-purple:var(--sg-theme-primary,var(--color-brand-500));--sgv2-purple-dark:var(--sg-theme-primary-800,var(--color-brand-700));--sgv2-purple-soft:var(--sg-theme-soft,var(--color-brand-50));--sgv2-ink:var(--color-ink-500);--sgv2-muted:var(--color-slate-500);--sgv2-line:var(--color-ink-100);--sgv2-bg:var(--color-ink-50);--sgv2-green:var(--color-success-700);--sgv2-red:var(--color-danger-500);--sgv2-amber:var(--color-warning-500);--sgv2-blue:var(--color-info-400);font-family:'Poppins','Inter','Segoe UI',system-ui,sans-serif;color:var(--sgv2-ink);}
.sige-rh.sg-dashboard-v2 *{box-sizing:border-box;}
.sige-rh .sg-dash-shell{display:flex;flex-direction:column;gap:var(--space-5);}
.sige-rh .sg-dash-hero{position:relative;overflow:hidden;border-radius:var(--radius-xl);background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--sg-theme-soft,var(--color-brand-100)) 100%)!important;border:1px solid rgba(var(--sg-theme-primary-rgb,92,64,187),.12)!important;box-shadow:var(--shadow-md);padding:var(--space-6) var(--space-8)!important;display:block!important;margin:0!important;}
.sige-rh .sg-hero-kicker{font-size:var(--fs-sm)!important;font-weight:700!important;letter-spacing:.11em!important;text-transform:uppercase!important;color:var(--sg-theme-primary,var(--color-brand-500))!important;margin-bottom:10px!important;}
.sige-rh .sg-hero-title{margin:0!important;font-size:var(--fs-xl)!important;line-height:1.15!important;font-weight:700!important;letter-spacing:-.02em!important;color:var(--color-black)!important;font-family:'Poppins','Inter','Segoe UI',system-ui,sans-serif!important;}
.sige-rh .sg-hero-subtitle{max-width:680px!important;margin:var(--space-2) 0 0!important;font-size:var(--fs-base)!important;line-height:1.55!important;color:var(--color-slate-700)!important;font-weight:500!important;}
.sige-rh .sg-hero-actions{display:flex!important;flex-wrap:wrap!important;gap:var(--space-3)!important;margin-top:var(--space-4)!important;}
.sige-rh .sg-v2-btn{min-height:46px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:var(--space-3)!important;border-radius:var(--radius-md)!important;padding:0 var(--space-6)!important;font-size:var(--fs-base)!important;font-weight:700!important;text-decoration:none!important;border:1px solid transparent!important;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease!important;background:var(--color-white)!important;color:var(--color-slate-900)!important;cursor:pointer!important;}
.sige-rh .sg-v2-btn svg{width:18px;height:18px;stroke:currentColor;color:currentColor;fill:none;opacity:1;}
.sige-rh .sg-v2-btn-primary{background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-400)),var(--sg-theme-primary-800,var(--color-brand-600)))!important;color:var(--color-white)!important;box-shadow:var(--shadow-md);}
.sige-rh .sg-v2-btn-secondary{background:var(--color-white)!important;color:var(--color-ink-900)!important;border-color:var(--color-ink-100)!important;box-shadow:var(--shadow-sm);}
.sige-rh .sg-v2-btn:hover{transform:translateY(-1px);box-shadow:var(--shadow-md);}
/* Herói compacto (v12.21.0): faixa única, sem ilustração. Regras de arte
   (.sg-hero-art/.sg-hero-*/.sg-school-*) removidas com o respectivo HTML. */
.sige-rh .sg-kpi-grid{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:var(--space-4)!important;margin:0!important;}
.sige-rh .sg-kpi-card{position:relative!important;overflow:hidden!important;display:grid!important;grid-template-columns:auto minmax(0,1fr)!important;gap:var(--space-4)!important;align-items:center!important;min-height:104px!important;padding:var(--space-5) var(--space-5)!important;border-radius:var(--radius-xl)!important;background:var(--color-white)!important;border:1px solid rgba(28,32,54,.08)!important;box-shadow:var(--shadow-md);}
.sige-rh .sg-kpi-card:after{content:"";position:absolute;right:-28px;top:-34px;width:92px;height:92px;border-radius:50%;background:var(--kpi-soft,var(--color-brand-50));z-index:0!important;pointer-events:none!important;opacity:.72!important;}
.sige-rh .sg-kpi-card > *{position:relative!important;z-index:1!important;}
.sige-rh .sg-kpi-card > div:not(.sg-kpi-icon){min-width:0!important;overflow:visible!important;}
.sige-rh .sg-kpi-card .sg-kpi-label,.sige-rh .sg-kpi-card .sg-kpi-value,.sige-rh .sg-kpi-card .sg-kpi-note{position:relative!important;z-index:2!important;}
.sige-rh .sg-kpi-card{isolation:isolate!important;}
.sige-rh .sg-kpi-icon{width:52px!important;height:52px!important;border-radius:var(--radius-lg)!important;display:flex!important;align-items:center!important;justify-content:center!important;background:var(--kpi-soft,var(--color-brand-50))!important;color:var(--kpi-color,var(--sg-theme-primary,var(--color-brand-500)))!important;position:relative!important;z-index:1!important;}
.sige-rh .sg-kpi-icon svg{width:24px;height:24px;stroke:currentColor;color:currentColor;fill:none;opacity:1;}
.sige-rh .sg-kpi-label{font-size:var(--fs-sm)!important;font-weight:600!important;color:var(--color-slate-600)!important;margin-bottom:6px!important;}
.sige-rh .sg-kpi-value{font-size:var(--fs-2xl)!important;line-height:1!important;font-weight:700!important;letter-spacing:-.03em!important;color:var(--color-black)!important;}
.sige-rh .sg-kpi-note{margin-top:7px!important;font-size:var(--fs-sm)!important;font-weight:600!important;color:var(--color-ink-400)!important;}
.sige-rh .sg-kpi-note strong{color:var(--kpi-color,var(--sg-theme-primary,var(--color-brand-500)))!important;}
.sige-rh .sg-dash-card{background:var(--color-white)!important;border:1px solid rgba(30,34,60,.08)!important;border-radius:var(--radius-xl)!important;box-shadow:var(--shadow-md);overflow:hidden!important;min-width:0!important;}
.sige-rh .sige-toolbar{padding:var(--space-4)!important;margin:0!important;display:flex!important;align-items:center!important;justify-content:space-between!important;gap:var(--space-4)!important;}
.sige-rh .sige-table-card{margin:0!important;}
.sige-rh .sige-table thead{background:var(--color-slate-50)!important;}
.sige-rh .sige-table tbody tr:hover{background:var(--color-white)!important;}
@media (max-width:1100px){.sige-rh .sg-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}}
@media (max-width:720px){.sige-rh .sg-dash-hero{padding:var(--space-6) var(--space-5)!important}.sige-rh .sg-hero-title{font-size:var(--fs-xl)!important}.sige-rh .sg-kpi-grid{grid-template-columns:1fr!important}.sige-rh .sg-hero-actions{display:grid!important;grid-template-columns:1fr!important}.sige-rh .sg-v2-btn{width:100%!important}.sige-rh .sige-toolbar{display:grid!important;grid-template-columns:1fr!important}.sige-rh .toolbar-filters{overflow:auto!important}}

/* v12.10.38 - Proteção visual dos KPIs: os círculos decorativos ficam sempre atrás dos textos. */
.sige-rh.sg-dashboard-v2 .sg-kpi-card{padding-right:26px!important;}
.sige-rh.sg-dashboard-v2 .sg-kpi-card:after{mix-blend-mode:normal!important;}
.sige-rh.sg-dashboard-v2 .sg-kpi-label{max-width:100%!important;white-space:normal!important;}
.sige-rh.sg-dashboard-v2 .sg-kpi-value{max-width:100%!important;white-space:normal!important;}
.sige-rh.sg-dashboard-v2 .sg-kpi-note{max-width:100%!important;white-space:normal!important;}
@media (min-width:1101px) and (max-width:1380px){.sige-rh .sg-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}}

/* ── Alertas de contrato (RH) ─ só tokens do design system ───────────────── */
.sige-rh .sg-rh-alertas{margin:var(--space-5) 0 0;background:var(--color-white);border:1px solid var(--color-ink-100);border-radius:var(--radius-xl);box-shadow:var(--shadow-md);padding:var(--space-5) var(--space-6);}
.sige-rh .sg-rh-alertas.has-alerts{border-left:4px solid var(--color-warning-500);}
.sige-rh .sg-rh-alertas-head{display:flex;align-items:flex-start;justify-content:space-between;gap:var(--space-4);flex-wrap:wrap;}
.sige-rh .sg-rh-alertas-title{display:flex;align-items:center;gap:var(--space-3);}
.sige-rh .sg-rh-alertas-ic{width:40px;height:40px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;background:var(--color-warning-100);color:var(--color-warning-700);flex:0 0 auto;}
.sige-rh .sg-rh-alertas-ic svg{width:20px;height:20px;}
.sige-rh .sg-rh-alertas-title h2{margin:0;font-size:var(--fs-md);font-weight:700;color:var(--color-black);}
.sige-rh .sg-rh-alertas-title p{margin:2px 0 0;font-size:var(--fs-sm);color:var(--color-slate-600);}
.sige-rh .sg-rh-alertas-chips{display:flex;gap:var(--space-2);flex-wrap:wrap;align-items:center;}
.sige-rh .sg-rh-chip{font-size:var(--fs-sm);font-weight:700;padding:4px 12px;border-radius:var(--radius-pill);}
.sige-rh .sg-rh-chip.is-exp{background:var(--color-danger-50);color:var(--color-danger-700);}
.sige-rh .sg-rh-chip.is-crit{background:var(--color-warning-100);color:var(--color-warning-700);}
.sige-rh .sg-rh-chip.is-warn{background:var(--color-slate-100);color:var(--color-slate-600);}
.sige-rh .sg-rh-alertas-ok{display:flex;align-items:center;gap:var(--space-2);margin-top:var(--space-3);font-size:var(--fs-sm);font-weight:600;color:var(--color-success-700);}
.sige-rh .sg-rh-alertas-ok svg{width:18px;height:18px;}
.sige-rh .sg-rh-alertas-list{list-style:none;margin:var(--space-4) 0 0;padding:0;display:grid;gap:var(--space-2);}
.sige-rh .sg-rh-alert{display:flex;align-items:center;gap:var(--space-3);padding:var(--space-3);border:1px solid var(--color-ink-100);border-radius:var(--radius-md);background:var(--color-slate-50);}
.sige-rh .sg-rh-alert-dot{width:8px;height:8px;border-radius:50%;flex:0 0 auto;background:var(--color-slate-400);}
.sige-rh .sg-rh-alert.is-expirado .sg-rh-alert-dot{background:var(--color-danger-500);}
.sige-rh .sg-rh-alert.is-critico .sg-rh-alert-dot{background:var(--color-warning-500);}
.sige-rh .sg-rh-alert-main{flex:1 1 auto;min-width:0;display:flex;flex-direction:column;}
.sige-rh .sg-rh-alert-nome{font-size:var(--fs-base);font-weight:600;color:var(--color-ink-700);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.sige-rh .sg-rh-alert-meta{font-size:var(--fs-sm);color:var(--color-slate-500);}
.sige-rh .sg-rh-alert-badge{flex:0 0 auto;font-size:var(--fs-sm);font-weight:700;padding:3px 10px;border-radius:var(--radius-pill);background:var(--color-slate-100);color:var(--color-slate-600);}
.sige-rh .sg-rh-alert.is-expirado .sg-rh-alert-badge{background:var(--color-danger-50);color:var(--color-danger-700);}
.sige-rh .sg-rh-alert.is-critico .sg-rh-alert-badge{background:var(--color-warning-100);color:var(--color-warning-700);}
.sige-rh .sg-rh-alertas-more{margin-top:var(--space-3);font-size:var(--fs-sm);color:var(--color-slate-500);}
@media (max-width:720px){.sige-rh .sg-rh-alertas-head{flex-direction:column;}.sige-rh .sg-rh-alert{flex-wrap:wrap;}}

/* ========================================
   ABAS RH (Equipa / Relatórios) - v12.35.0
   ======================================== */
.sige-rh .sg-rh-tabs{display:flex;gap:var(--space-1);align-items:flex-end;border-bottom:1px solid var(--color-ink-100);margin:0;padding:0 var(--space-1);}
.sige-rh .sg-rh-tab{appearance:none;border:0;background:transparent;cursor:pointer;display:inline-flex;align-items:center;gap:var(--space-2);padding:var(--space-3) var(--space-4);font-family:inherit;font-size:var(--fs-base);font-weight:600;color:var(--color-slate-500);border-bottom:2px solid transparent;margin-bottom:-1px;border-radius:var(--radius-md) var(--radius-md) 0 0;transition:color .15s ease,border-color .15s ease,background .15s ease;}
.sige-rh .sg-rh-tab:hover{color:var(--color-slate-700);background:var(--color-ink-50);}
.sige-rh .sg-rh-tab .sg-rh-tab-ic{width:18px;height:18px;flex:0 0 auto;}
.sige-rh .sg-rh-tab .sg-rh-tab-ic svg{width:18px;height:18px;display:block;}
.sige-rh .sg-rh-tab.is-active{color:var(--sg-theme-primary,var(--color-brand-600));border-bottom-color:var(--sg-theme-primary,var(--color-brand-600));}
.sige-rh .sg-rh-tab-count{font-size:var(--fs-xs);font-weight:700;line-height:1;padding:2px 7px;border-radius:var(--radius-pill);background:var(--color-ink-100);color:var(--color-slate-600);}
.sige-rh .sg-rh-tab.is-active .sg-rh-tab-count{background:var(--sg-theme-soft,var(--color-brand-50));color:var(--sg-theme-primary,var(--color-brand-700));}
.sige-rh .sg-rh-tabpanel{display:flex;flex-direction:column;gap:var(--space-5);}
.sige-rh .sg-rh-tabpanel[hidden]{display:none!important;}

/* ----- Painel de Relatórios ----- */
.sige-rh .sg-rh-rep-intro{display:flex;align-items:flex-start;gap:var(--space-3);}
.sige-rh .sg-rh-rep-intro .sg-rh-rep-ic{width:40px;height:40px;flex:0 0 auto;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;background:var(--sg-theme-soft,var(--color-brand-50));color:var(--sg-theme-primary,var(--color-brand-600));}
.sige-rh .sg-rh-rep-intro .sg-rh-rep-ic svg{width:22px;height:22px;}
.sige-rh .sg-rh-rep-intro h2{margin:0;font-size:var(--fs-lg);font-weight:700;letter-spacing:-.02em;color:var(--color-black);}
.sige-rh .sg-rh-rep-intro p{margin:2px 0 0;font-size:var(--fs-sm);color:var(--color-slate-500);}
.sige-rh .sg-rh-rep-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--space-4);}
.sige-rh .sg-rh-rep-stat{background:var(--color-white);border:1px solid rgba(28,32,54,.08);border-radius:var(--radius-xl);box-shadow:var(--shadow-md);padding:var(--space-5);display:flex;flex-direction:column;gap:6px;}
.sige-rh .sg-rh-rep-stat .v{font-size:var(--fs-2xl);font-weight:700;line-height:1;letter-spacing:-.03em;color:var(--color-black);}
.sige-rh .sg-rh-rep-stat .l{font-size:var(--fs-sm);font-weight:600;color:var(--color-slate-600);}
.sige-rh .sg-rh-rep-stat .n{font-size:var(--fs-xs);color:var(--color-slate-500);}
.sige-rh .sg-rh-rep-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--space-4);}
.sige-rh .sg-rh-rep-card{background:var(--color-white);border:1px solid rgba(30,34,60,.08);border-radius:var(--radius-xl);box-shadow:var(--shadow-md);padding:var(--space-5) var(--space-6);min-width:0;}
.sige-rh .sg-rh-rep-card.is-wide{grid-column:1 / -1;}
.sige-rh .sg-rh-rep-card h3{display:flex;align-items:center;gap:var(--space-2);margin:0 0 var(--space-4);font-size:var(--fs-base);font-weight:700;color:var(--color-black);}
.sige-rh .sg-rh-rep-card h3 svg{width:18px;height:18px;color:var(--color-slate-400);flex:0 0 auto;}
.sige-rh .sg-rh-rep-empty{font-size:var(--fs-sm);color:var(--color-slate-400);padding:var(--space-3) 0;}
/* Barras horizontais */
.sige-rh .sg-rh-bars{display:flex;flex-direction:column;gap:var(--space-3);}
.sige-rh .sg-rh-bar-row{display:grid;grid-template-columns:minmax(120px,38%) 1fr auto;align-items:center;gap:var(--space-3);}
.sige-rh .sg-rh-bar-label{font-size:var(--fs-sm);font-weight:600;color:var(--color-slate-700);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.sige-rh .sg-rh-bar-track{position:relative;height:10px;border-radius:var(--radius-pill);background:var(--color-ink-100);overflow:hidden;}
.sige-rh .sg-rh-bar-fill{position:absolute;inset:0 auto 0 0;height:100%;border-radius:var(--radius-pill);background:var(--bar,var(--sg-theme-primary,var(--color-brand-500)));min-width:3px;transition:width .4s ease;}
.sige-rh .sg-rh-bar-val{font-size:var(--fs-sm);font-weight:700;color:var(--color-slate-800);min-width:62px;text-align:right;}
.sige-rh .sg-rh-bar-val small{font-weight:600;color:var(--color-slate-400);}
/* Admissões por ano (colunas) */
.sige-rh .sg-rh-cols{display:flex;align-items:flex-end;gap:var(--space-3);height:140px;padding-top:var(--space-2);}
.sige-rh .sg-rh-col{flex:1 1 0;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:6px;height:100%;min-width:0;}
.sige-rh .sg-rh-col-val{font-size:var(--fs-sm);font-weight:700;color:var(--color-slate-700);}
.sige-rh .sg-rh-col-bar{width:100%;max-width:46px;border-radius:var(--radius-md) var(--radius-md) 0 0;background:linear-gradient(180deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-800,var(--color-brand-700)));min-height:4px;transition:height .4s ease;}
.sige-rh .sg-rh-col-year{font-size:var(--fs-xs);color:var(--color-slate-500);font-weight:600;}
/* Qualidade de dados */
.sige-rh .sg-rh-qual{display:flex;flex-direction:column;gap:var(--space-4);}
.sige-rh .sg-rh-qual-row{display:grid;grid-template-columns:minmax(120px,30%) 1fr auto;align-items:center;gap:var(--space-3);}
.sige-rh .sg-rh-qual-label{font-size:var(--fs-sm);font-weight:600;color:var(--color-slate-700);}
.sige-rh .sg-rh-qual-track{position:relative;height:8px;border-radius:var(--radius-pill);background:var(--color-ink-100);overflow:hidden;}
.sige-rh .sg-rh-qual-fill{position:absolute;inset:0 auto 0 0;height:100%;border-radius:var(--radius-pill);background:var(--color-success-500);transition:width .4s ease;}
.sige-rh .sg-rh-qual-fill.is-warn{background:var(--color-warning-500);}
.sige-rh .sg-rh-qual-fill.is-bad{background:var(--color-danger-500);}
.sige-rh .sg-rh-qual-val{font-size:var(--fs-sm);font-weight:700;color:var(--color-slate-800);min-width:96px;text-align:right;}
.sige-rh .sg-rh-qual-val small{font-weight:600;color:var(--color-slate-400);}
@media (max-width:1100px){.sige-rh .sg-rh-rep-stats{grid-template-columns:repeat(2,minmax(0,1fr));}.sige-rh .sg-rh-rep-grid{grid-template-columns:1fr;}}
@media (max-width:720px){.sige-rh .sg-rh-rep-stats{grid-template-columns:1fr;}.sige-rh .sg-rh-bar-row,.sige-rh .sg-rh-qual-row{grid-template-columns:1fr auto;}.sige-rh .sg-rh-bar-track,.sige-rh .sg-rh-qual-track{grid-column:1 / -1;order:3;}.sige-rh .sg-rh-tab{padding:var(--space-3) var(--space-3);font-size:var(--fs-sm);}}

/* ========================================
   FICHA DO COLABORADOR (perfil 360, só leitura) - v12.36.0
   ======================================== */
.sige-rh .btn-action.btn-ficha{background:var(--sg-theme-soft,var(--color-brand-50));color:var(--sg-theme-primary,var(--color-brand-600));}
.sige-rh .btn-action.btn-ficha:hover{background:var(--sg-theme-primary,var(--color-brand-500));color:var(--color-white);}
#box-ficha .modal-content{width:min(760px,calc(100vw - 44px))!important;max-width:760px!important;max-height:92vh!important;border-radius:var(--radius-xl)!important;border:1px solid rgba(255,255,255,.86)!important;background:var(--color-white)!important;box-shadow:var(--shadow-lg);overflow:hidden!important;display:flex!important;flex-direction:column!important;}
#box-ficha .modal-body{padding:0!important;flex:1 1 auto!important;min-height:0!important;overflow-y:auto!important;-webkit-overflow-scrolling:touch!important;}
.sg-ficha{display:flex;flex-direction:column;}
/* Cabeçalho de identidade (fundo neutro: a cor já vem do cabeçalho padrão do modal) */
.sg-ficha-head{display:flex;align-items:center;gap:var(--space-4);padding:var(--space-5) var(--space-6);background:var(--color-white);border-bottom:1px solid var(--color-ink-100);}
.sg-ficha-photo{width:72px;height:72px;flex:0 0 auto;border-radius:var(--radius-pill);object-fit:cover;border:3px solid var(--color-white);box-shadow:var(--shadow-md);background:var(--color-ink-100);}
.sg-ficha-idwrap{min-width:0;flex:1 1 auto;}
.sg-ficha-name{font-size:var(--fs-lg);font-weight:700;letter-spacing:-.02em;color:var(--color-black);margin:0;}
.sg-ficha-email{font-size:var(--fs-sm);color:var(--color-slate-500);margin:2px 0 0;word-break:break-all;}
.sg-ficha-badges{display:flex;flex-wrap:wrap;gap:var(--space-2);margin-top:var(--space-2);}
.sg-ficha-badge{display:inline-flex;align-items:center;gap:6px;font-size:var(--fs-xs);font-weight:700;padding:3px 10px;border-radius:var(--radius-pill);background:var(--color-ink-100);color:var(--color-slate-700);}
.sg-ficha-badge.is-on{background:var(--color-success-50);color:var(--color-success-700);}
.sg-ficha-badge.is-off{background:var(--color-slate-100);color:var(--color-slate-600);}
.sg-ficha-badge .dot{width:7px;height:7px;border-radius:var(--radius-pill);background:currentColor;}
/* Banner de estado do contrato */
.sg-ficha-alert{display:flex;align-items:center;gap:var(--space-3);margin:var(--space-5) var(--space-6) 0;padding:var(--space-3) var(--space-4);border-radius:var(--radius-lg);font-size:var(--fs-sm);font-weight:600;}
.sg-ficha-alert svg{width:18px;height:18px;flex:0 0 auto;}
.sg-ficha-alert.is-ok{background:var(--color-success-50);color:var(--color-success-700);}
.sg-ficha-alert.is-aviso{background:var(--color-warning-50);color:var(--color-warning-700);}
.sg-ficha-alert.is-critico,.sg-ficha-alert.is-expirado{background:var(--color-danger-50);color:var(--color-danger-700);}
/* Secções */
.sg-ficha-sections{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--space-4);padding:var(--space-5) var(--space-6) var(--space-6);}
.sg-ficha-sec{background:var(--color-white);border:1px solid var(--color-ink-100);border-radius:var(--radius-lg);padding:var(--space-4) var(--space-5);min-width:0;}
.sg-ficha-sec.is-wide{grid-column:1 / -1;}
.sg-ficha-sec h4{display:flex;align-items:center;gap:var(--space-2);margin:0 0 var(--space-3);font-size:var(--fs-sm);font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--color-slate-500);}
.sg-ficha-sec h4 svg{width:16px;height:16px;color:var(--color-slate-400);}
.sg-ficha-field{display:flex;justify-content:space-between;gap:var(--space-3);padding:7px 0;border-bottom:1px solid var(--color-ink-50);}
.sg-ficha-field:last-child{border-bottom:0;}
.sg-ficha-field .k{font-size:var(--fs-sm);color:var(--color-slate-500);flex:0 0 auto;}
.sg-ficha-field .v{font-size:var(--fs-sm);font-weight:600;color:var(--color-slate-800);text-align:right;word-break:break-word;}
.sg-ficha-field .v.is-empty{color:var(--color-slate-400);font-weight:500;font-style:italic;}
/* Documentos */
.sg-ficha-docs{display:flex;flex-wrap:wrap;gap:var(--space-2);}
.sg-ficha-doc{display:inline-flex;align-items:center;gap:6px;font-size:var(--fs-sm);font-weight:600;padding:7px 12px;border-radius:var(--radius-md);background:var(--sg-theme-soft,var(--color-brand-50));color:var(--sg-theme-primary,var(--color-brand-700));text-decoration:none;border:1px solid transparent;}
.sg-ficha-doc:hover{border-color:var(--sg-theme-primary,var(--color-brand-300));}
.sg-ficha-doc svg{width:15px;height:15px;}
.sg-ficha-doc.is-missing{background:var(--color-ink-50);color:var(--color-slate-400);cursor:default;}
/* Completude */
.sg-ficha-comp{display:flex;align-items:center;gap:var(--space-3);}
.sg-ficha-comp-track{position:relative;flex:1 1 auto;height:8px;border-radius:var(--radius-pill);background:var(--color-ink-100);overflow:hidden;}
.sg-ficha-comp-fill{position:absolute;inset:0 auto 0 0;height:100%;border-radius:var(--radius-pill);background:var(--color-success-500);}
.sg-ficha-comp-fill.is-warn{background:var(--color-warning-500);}
.sg-ficha-comp-fill.is-bad{background:var(--color-danger-500);}
.sg-ficha-comp-val{font-size:var(--fs-sm);font-weight:700;color:var(--color-slate-800);}
/* Estado de carregamento */
.sg-ficha-loading{padding:var(--space-8);text-align:center;color:var(--color-slate-400);font-size:var(--fs-sm);}
@media (max-width:720px){.sg-ficha-sections{grid-template-columns:1fr;}.sg-ficha-field{flex-direction:column;gap:2px;}.sg-ficha-field .v{text-align:left;}}



/* ========================================
   RH - Modal de Colaborador V2 alinhado ao Painel Principal
   ======================================== */
#box-equipa.sige-modal,
#box-ficha.sige-modal{
    background:rgba(18,22,40,.54)!important;
    backdrop-filter:blur(14px)!important;
    -webkit-backdrop-filter:blur(14px)!important;
    padding:var(--space-6)!important;
    z-index:1000000!important;
}
#box-equipa .sg-rh-staff-modal{
    width:min(1080px,calc(100vw - 44px))!important;
    max-width:1080px!important;
    max-height:92vh!important;
    border-radius:var(--radius-xl)!important;
    border:1px solid rgba(255,255,255,.86)!important;
    background:var(--color-white)!important;
    box-shadow:var(--shadow-lg);
    overflow:hidden!important;
    transform:translateY(16px) scale(.985);
    /* Coluna flex: cabecalho + separadores fixos, corpo rola, rodape fixo.
       Sem isto o conteudo passava de 92vh e ficava cortado (sem scroll). */
    display:flex!important;
    flex-direction:column!important;
}
/* Cabecalho e separadores nao encolhem; o corpo e que absorve o overflow. */
#box-equipa .modal-header,
#box-ficha .modal-header,
#box-equipa .modal-tabs{flex:0 0 auto!important;}
/* O formulario ocupa o espaco restante e delega o scroll ao corpo. */
#box-equipa #form-staff{
    display:flex!important;
    flex-direction:column!important;
    flex:1 1 auto!important;
    min-height:0!important;
    overflow:hidden!important;
}
#box-equipa .modal-header,
#box-ficha .modal-header{
    position:relative!important;
    overflow:hidden!important;
    min-height:124px!important;
    padding:var(--space-8) var(--space-8)!important;
    background:linear-gradient(135deg,var(--color-white) 0%,var(--color-white) 52%,var(--sg-theme-primary-50,var(--color-brand-50)) 100%)!important;
    color:var(--color-black)!important;
    border-bottom:1px solid rgba(30,34,60,.06)!important;
}
#box-equipa .modal-header:after,
#box-ficha .modal-header:after{
    content:'';
    position:absolute;
    right:-110px;
    top:-150px;
    width:360px;
    height:360px;
    border-radius:var(--radius-pill);
    background:rgba(var(--sg-theme-primary-rgb,90,63,214),.10);
    pointer-events:none;
}
#box-equipa .sg-rh-modal-title-wrap,
#box-ficha .sg-rh-modal-title-wrap{
    position:relative;
    z-index:1;
    display:flex;
    align-items:center;
    gap:var(--space-5);
    min-width:0;
}
#box-equipa .sg-rh-modal-icon,
#box-ficha .sg-rh-modal-icon{
    width:58px;
    height:58px;
    border-radius:var(--radius-lg);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    flex:0 0 auto;
    color:var(--sg-theme-primary,var(--color-brand-500));
    background:var(--sg-theme-primary-50,var(--color-brand-50));
    box-shadow:var(--shadow-sm);
}
#box-equipa .sg-rh-modal-icon svg,
#box-ficha .sg-rh-modal-icon svg{width:26px;height:26px;stroke:currentColor;}
#box-equipa .modal-header h3,
#box-ficha .modal-header h3{
    margin:0 0 var(--space-2)!important;
    color:var(--color-black)!important;
    font-family:var(--font-display,'Plus Jakarta Sans',system-ui,sans-serif)!important;
    font-size:var(--fs-3xl)!important;
    line-height:1.08!important;
    font-weight:700!important;
    letter-spacing:-.045em!important;
}
#box-equipa .modal-header p,
#box-ficha .modal-header p{
    margin:0!important;
    max-width:620px;
    color:var(--color-slate-600)!important;
    font-size:var(--fs-base)!important;
    line-height:1.55!important;
    font-weight:600!important;
}
#box-equipa .modal-close,
#box-ficha .modal-close{
    position:relative!important;
    z-index:2!important;
    width:46px!important;
    height:46px!important;
    border-radius:var(--radius-lg)!important;
    background:var(--color-white)!important;
    color:var(--color-ink-500)!important;
    border:1px solid rgba(30,34,60,.08)!important;
    box-shadow:var(--shadow-md);
}
#box-equipa .modal-close:hover,
#box-ficha .modal-close:hover{
    background:var(--sg-theme-primary-50,var(--color-brand-50))!important;
    color:var(--sg-theme-primary,var(--color-brand-500))!important;
    transform:none!important;
}
#box-equipa .modal-tabs{
    display:grid!important;
    grid-template-columns:repeat(4,minmax(0,1fr))!important;
    gap:var(--space-3)!important;
    padding:var(--space-4)!important;
    background:var(--color-slate-50)!important;
    border-bottom:1px solid rgba(30,34,60,.06)!important;
}
#box-equipa .tab-btn{
    min-height:62px!important;
    border-radius:var(--radius-lg)!important;
    border:1px solid rgba(30,34,60,.06)!important;
    background:var(--color-white)!important;
    color:var(--color-slate-600)!important;
    justify-content:flex-start!important;
    padding:var(--space-4) var(--space-4)!important;
    gap:var(--space-3)!important;
    font-size:var(--fs-base)!important;
    font-weight:700!important;
    box-shadow:var(--shadow-sm);
}
#box-equipa .tab-btn:after{display:none!important;}
#box-equipa .tab-btn:hover{
    color:var(--sg-theme-primary,var(--color-brand-500))!important;
    border-color:rgba(var(--sg-theme-primary-rgb,90,63,214),.18)!important;
    transform:translateY(-1px);
}
#box-equipa .tab-btn.active{
    background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-900,var(--color-brand-700)))!important;
    color:var(--color-white)!important;
    border-color:transparent!important;
    box-shadow:var(--shadow-md);
}
#box-equipa .tab-step{
    width:32px!important;
    height:32px!important;
    border-radius:var(--radius-md)!important;
    flex:0 0 32px!important;
    background:var(--color-slate-100)!important;
    color:var(--color-slate-600)!important;
    font-size:var(--fs-sm)!important;
    font-weight:700!important;
}
#box-equipa .tab-btn.active .tab-step{
    background:rgba(255,255,255,.18)!important;
    color:var(--color-white)!important;
}
#box-equipa .modal-body{
    background:linear-gradient(180deg,var(--color-white) 0%,var(--color-slate-50) 100%)!important;
    padding:var(--space-6) var(--space-8)!important;
    flex:1 1 auto!important;
    min-height:0!important;
    overflow-y:auto!important;
    -webkit-overflow-scrolling:touch!important;
}
#box-equipa .tab-content.active{
    display:block!important;
    padding:var(--space-6)!important;
    background:var(--color-white)!important;
    border:1px solid rgba(30,34,60,.06)!important;
    border-radius:var(--radius-xl)!important;
    box-shadow:var(--shadow-md);
}
#box-equipa .sg-rh-personal-layout{
    display:grid!important;
    grid-template-columns:minmax(0,1fr) 250px!important;
    gap:var(--space-6)!important;
    align-items:start!important;
}
#box-equipa .sg-rh-personal-fields{min-width:0!important;}
#box-equipa .form-grid{gap:var(--space-5)!important;}
#box-equipa .form-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))!important;}
#box-equipa .form-grid-3{grid-template-columns:repeat(3,minmax(0,1fr))!important;}
#box-equipa .field-group label{
    margin-bottom:9px!important;
    color:var(--color-slate-700)!important;
    font-size:var(--fs-xs)!important;
    font-weight:700!important;
    letter-spacing:.09em!important;
}
#box-equipa .field-group input,
#box-equipa .field-group select{
    height:54px!important;
    border-radius:var(--radius-lg)!important;
    border:1px solid var(--color-ink-100)!important;
    background:var(--color-white)!important;
    color:var(--color-ink-500)!important;
    font-size:var(--fs-base)!important;
    font-weight:600!important;
    box-shadow:var(--shadow-sm);
}
#box-equipa .field-group input::placeholder{color:var(--color-slate-400)!important;font-weight:600!important;}
#box-equipa .field-group input:focus,
#box-equipa .field-group select:focus{
    border-color:rgba(var(--sg-theme-primary-rgb,90,63,214),.42)!important;
    box-shadow:var(--shadow-xs);
}
#box-equipa .field-hint{
    color:var(--color-slate-500)!important;
    font-size:var(--fs-sm)!important;
    line-height:1.45!important;
    font-weight:600!important;
}
#box-equipa .photo-upload-area{
    position:sticky!important;
    top:0!important;
    min-height:100%!important;
    padding:var(--space-6)!important;
    border-radius:var(--radius-xl)!important;
    border:1px dashed rgba(var(--sg-theme-primary-rgb,90,63,214),.32)!important;
    background:linear-gradient(180deg,var(--sg-theme-primary-50,var(--color-brand-50)) 0%,var(--color-white) 100%)!important;
    box-shadow:inset 0 0 0 1px rgba(255,255,255,.80)!important;
}
#box-equipa .sg-rh-photo-label{
    font-size:var(--fs-xs)!important;
    font-weight:700!important;
    color:var(--color-slate-700)!important;
    text-transform:uppercase!important;
    letter-spacing:.12em!important;
    margin-bottom:4px!important;
}
#box-equipa .photo-preview{
    width:132px!important;
    height:132px!important;
    border-radius:var(--radius-xl)!important;
    background:var(--color-white)!important;
    border:4px solid var(--color-white)!important;
    box-shadow:var(--shadow-md);
}
#box-equipa .btn-upload{
    min-height:46px!important;
    border-radius:var(--radius-lg)!important;
    background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-900,var(--color-brand-700)))!important;
    color:var(--color-white)!important;
    border:0!important;
    box-shadow:var(--shadow-sm);
    font-weight:700!important;
}
#box-equipa .sg-rh-doc-section{
    border-top:1px solid rgba(30,34,60,.08)!important;
    padding-top:24px!important;
}
#box-equipa .sg-rh-section-title{
    display:block!important;
    font-size:var(--fs-sm)!important;
    font-weight:700!important;
    color:var(--color-slate-700)!important;
    text-transform:uppercase!important;
    letter-spacing:.12em!important;
    margin-bottom:16px!important;
}
#box-equipa .sg-rh-upload-row{
    display:grid!important;
    grid-template-columns:minmax(0,1fr) auto!important;
    gap:var(--space-3)!important;
    align-items:center!important;
}
#box-equipa .btn-upload-doc{
    min-width:128px!important;
    height:54px!important;
    padding:0 var(--space-4)!important;
    justify-content:center!important;
    white-space:nowrap!important;
}
#box-equipa .btn-upload-doc.uploaded{
    background:linear-gradient(135deg,var(--sg-theme-accent,var(--color-success-700)),var(--color-success-800))!important;
    box-shadow:var(--shadow-sm);
}
#box-equipa .btn-upload-doc span{
    display:inline-block!important;
    line-height:1!important;
}
#box-equipa .sg-rh-upload-hint{
    display:block!important;
    margin-top:8px!important;
    color:var(--color-slate-500)!important;
    font-size:var(--fs-sm)!important;
    font-weight:600!important;
}
@media (max-width: 760px){
    #box-equipa .sg-rh-upload-row{grid-template-columns:1fr!important;}
    #box-equipa .btn-upload-doc{width:100%!important;}
}
#box-equipa .info-box{
    border-left:0!important;
    border:1px solid rgba(var(--sg-theme-primary-rgb,90,63,214),.10)!important;
    background:var(--sg-theme-primary-50,var(--color-brand-50))!important;
    border-radius:var(--radius-lg)!important;
    color:var(--color-ink-800)!important;
}
#box-equipa .highlighted-section{
    background:linear-gradient(135deg,rgba(var(--sg-theme-accent-rgb,52,168,83),.08),var(--color-white))!important;
    border:1px solid rgba(var(--sg-theme-accent-rgb,52,168,83),.14)!important;
    border-left:0!important;
    border-radius:var(--radius-xl)!important;
}
#box-equipa .modal-footer{
    flex:0 0 auto!important;
    z-index:3!important;
    padding:var(--space-5) var(--space-8)!important;
    background:rgba(255,255,255,.94)!important;
    backdrop-filter:blur(10px)!important;
    border-top:1px solid rgba(30,34,60,.08)!important;
    box-shadow:var(--shadow-md);
}
#box-equipa .btn-modal{
    height:50px!important;
    border-radius:var(--radius-lg)!important;
    padding:0 var(--space-6)!important;
    font-weight:700!important;
    font-size:var(--fs-base)!important;
}
#box-equipa .btn-modal.btn-cancel{
    background:var(--color-white)!important;
    color:var(--color-ink-800)!important;
    border:1px solid var(--color-ink-100)!important;
    box-shadow:var(--shadow-sm);
}
#box-equipa .btn-modal.btn-submit{
    background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-900,var(--color-brand-700)))!important;
    color:var(--color-white)!important;
    box-shadow:var(--shadow-md);
}
#box-equipa .btn-modal.btn-submit:hover{transform:translateY(-1px)!important;}
@media (max-width:980px){
    #box-equipa .modal-tabs{grid-template-columns:repeat(2,minmax(0,1fr))!important;}
    #box-equipa .sg-rh-personal-layout{grid-template-columns:1fr!important;}
    #box-equipa .photo-upload-area{position:relative!important;top:auto!important;}
    #box-equipa .form-grid-3{grid-template-columns:1fr!important;}
}
@media (max-width:720px){
    #box-equipa.sige-modal{padding:var(--space-3)!important;align-items:flex-start!important;}
    #box-equipa .sg-rh-staff-modal{width:calc(100vw - 24px)!important;max-height:calc(100vh - 24px)!important;border-radius:var(--radius-xl)!important;}
    #box-equipa .modal-header{padding:var(--space-6) var(--space-5)!important;min-height:auto!important;}
    #box-equipa .sg-rh-modal-icon{width:48px;height:48px;border-radius:var(--radius-lg);}
    #box-equipa .modal-header h3{font-size:var(--fs-xl)!important;}
    #box-equipa .modal-tabs{display:flex!important;overflow-x:auto!important;padding:var(--space-3)!important;scroll-snap-type:x proximity;}
    #box-equipa .tab-btn{min-width:190px!important;scroll-snap-align:start;}
    #box-equipa .modal-body{padding:var(--space-4)!important;}
    #box-equipa .tab-content.active{padding:var(--space-4)!important;border-radius:var(--radius-xl)!important;}
    #box-equipa .form-grid-2{grid-template-columns:1fr!important;}
    #box-equipa .field-group.span-2,#box-equipa .field-group.span-3{grid-column:auto!important;}
    #box-equipa .modal-footer{display:grid!important;grid-template-columns:1fr!important;padding:var(--space-4)!important;}
    #box-equipa .btn-modal{width:100%!important;justify-content:center!important;}
}




/* v12.11.9.9 - Arquivo RH de colaboradores removidos */
#rh-arquivo-removidos{margin-top:24px!important;padding:var(--space-6)!important;border-radius:var(--radius-xl)!important;border:1px solid rgba(30,34,60,.08)!important;background:linear-gradient(180deg,var(--color-white) 0%,var(--color-white) 100%)!important;box-shadow:var(--shadow-md);}
#rh-arquivo-removidos .sg-rh-archive-head{display:flex!important;align-items:flex-start!important;justify-content:space-between!important;gap:var(--space-5)!important;margin-bottom:18px!important;}
#rh-arquivo-removidos .sg-rh-archive-kicker{display:inline-flex!important;align-items:center!important;gap:var(--space-2)!important;font-size:var(--fs-sm)!important;font-weight:700!important;letter-spacing:.08em!important;text-transform:uppercase!important;color:var(--sg-theme-primary,var(--color-brand-500))!important;margin-bottom:6px!important;}
#rh-arquivo-removidos .sg-rh-archive-title{margin:0!important;font-size:var(--fs-xl)!important;font-weight:700!important;color:var(--color-black)!important;}
#rh-arquivo-removidos .sg-rh-archive-desc{margin:var(--space-2) 0 0!important;color:var(--color-slate-600)!important;font-size:var(--fs-base)!important;line-height:1.5!important;}
#rh-arquivo-removidos .sg-rh-archive-count{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-width:42px!important;height:42px!important;padding:0 var(--space-4)!important;border-radius:var(--radius-pill)!important;background:var(--color-brand-50)!important;color:var(--sg-theme-primary,var(--color-brand-500))!important;font-weight:700!important;box-shadow:inset 0 0 0 1px rgba(90,63,214,.12)!important;}
#rh-arquivo-removidos .sg-rh-archive-table-wrap{overflow-x:auto!important;border-radius:var(--radius-lg)!important;border:1px solid var(--color-slate-100)!important;background:var(--color-white)!important;}
#rh-arquivo-removidos table{width:100%!important;border-collapse:collapse!important;min-width:760px!important;}
#rh-arquivo-removidos th{background:var(--color-slate-50)!important;color:var(--color-slate-700)!important;font-size:var(--fs-xs)!important;text-transform:uppercase!important;letter-spacing:.05em!important;text-align:left!important;padding:var(--space-3) var(--space-4)!important;}
#rh-arquivo-removidos td{padding:var(--space-4)!important;border-top:1px solid var(--color-slate-100)!important;color:var(--color-slate-800)!important;font-size:var(--fs-sm)!important;vertical-align:middle!important;}
#rh-arquivo-removidos .sg-rh-archive-user{display:flex!important;align-items:center!important;gap:var(--space-3)!important;}
#rh-arquivo-removidos .sg-rh-archive-avatar{width:36px!important;height:36px!important;border-radius:var(--radius-md)!important;object-fit:cover!important;background:var(--color-ink-50)!important;}
#rh-arquivo-removidos .sg-rh-archive-name{font-weight:700!important;color:var(--color-black)!important;display:block!important;}
#rh-arquivo-removidos .sg-rh-archive-email{font-size:var(--fs-sm)!important;color:var(--color-slate-600)!important;display:block!important;margin-top:2px!important;}
#rh-arquivo-removidos .sg-rh-archive-status{display:inline-flex!important;align-items:center!important;gap:var(--space-2)!important;border-radius:var(--radius-pill)!important;padding:var(--space-2) var(--space-3)!important;background:var(--color-danger-50)!important;color:var(--color-danger-700)!important;font-weight:700!important;font-size:var(--fs-sm)!important;}
#rh-arquivo-removidos .sg-rh-archive-note{margin-top:14px!important;border-radius:var(--radius-lg)!important;background:var(--color-warning-50)!important;border:1px solid var(--color-warning-300)!important;color:var(--color-warning-800)!important;padding:var(--space-3) var(--space-4)!important;font-size:var(--fs-sm)!important;line-height:1.45!important;}
#rh-arquivo-removidos.sg-rh-archive-focus{box-shadow:var(--shadow-md);}
@media (max-width:720px){#rh-arquivo-removidos{padding:var(--space-4)!important;border-radius:var(--radius-xl)!important;}#rh-arquivo-removidos .sg-rh-archive-head{display:grid!important;grid-template-columns:1fr!important;}#rh-arquivo-removidos .sg-rh-archive-count{justify-self:start!important;}}

/* v12.11.9.5 - Equipa: failsafe anti-regressão para modais dentro do App Shell.
   O CSS global do App Shell esconde .sige-modal por defeito; este módulo abre por classe
   e por inline style para garantir que Novo/Editar/Reset/Remover/Desactivar respondem ao clique.
   [v12.37.2] A visibilidade passa a depender SÓ de .active (aberto pelas funções
   open/close, que a gerem atomicamente). Antes dependia também de aria-hidden;
   se este dessincronizasse de .active, um modal fechado podia ficar VISÍVEL e
   invisível (opacity 0) mas a CAPTURAR todos os cliques -> a página parecia
   congelada até dar refresh. Invariante clara agora: com .active mostra; sem
   .active esconde (e não captura cliques). */
body.sige-admin-app #box-equipa.sige-modal.active,
body.sige-admin-app #sige-rh-confirm.sige-modal.active,
body.sige-admin-app #box-ficha.sige-modal.active{
    display:flex!important;
    opacity:1!important;
    visibility:visible!important;
    pointer-events:auto!important;
}
body.sige-admin-app #box-equipa.sige-modal:not(.active),
body.sige-admin-app #sige-rh-confirm.sige-modal:not(.active),
body.sige-admin-app #box-ficha.sige-modal:not(.active){
    display:none!important;
    opacity:0!important;
    visibility:hidden!important;
    pointer-events:none!important;
}

/* Utilitárias da consolidação CSS (v105+); substituem style= inline equivalentes */
.sg-staff-th-nome{width:35%;}
.sg-staff-th-cargo{width:18%;}
.sg-staff-th-validade{width:15%;}
.sg-staff-th-status{width:12%;}
.sg-staff-th-accoes{width:20%;text-align:right;}
.sg-sep-vert{width:1px;background:var(--sige-slate-300);margin:var(--space-1) var(--space-1);}
.sg-mb-20{margin-bottom:20px;}
.sg-mb-24{margin-bottom:24px;}
.sg-mt-24{margin-top:24px;}
.sg-pad-empty{padding:var(--space-8) var(--space-4);}
.sg-text-center{text-align:center;}
</style>
<!-- Toast Container -->
<div class="sige-toast-container" id="toastContainer"></div>
<div class="wrap sige-rh sg-dashboard-v2">
    <div class="sg-dash-shell">
    <!-- ========================================
         HERO - padrão visual do Painel Principal
         ======================================== -->
        <section class="sg-dash-hero" aria-label="Resumo de equipa e professores">
            <div class="sg-hero-copy">
                <div class="sg-hero-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('users') : ''; ?> Recursos Humanos</div>
                <h1 class="sg-hero-title">Equipa e Professores</h1>
                <p class="sg-hero-subtitle">Colaboradores, docentes, contratos e documentos da equipa escolar num só lugar.</p>
                <?php if ($can_manage_equipe): ?>
                <div class="sg-hero-actions">
                    <button data-sige-act="novoFuncionario" data-sige-noargs class="sg-v2-btn sg-v2-btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Novo colaborador
                    </button>
                    <button data-sige-act="imprimirLote" data-sige-noargs class="sg-v2-btn sg-v2-btn-secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2"/></svg>
                        Crachás
                    </button>
                    <?php if (!empty($can_manage_equipe)): ?>
                    <button data-sige-act="abrirModeloCrachaStaff" data-sige-noargs class="sg-v2-btn sg-v2-btn-secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="13.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="10.5" r="2.5"/><circle cx="8.5" cy="7.5" r="2.5"/><circle cx="6.5" cy="12.5" r="2.5"/><path d="M12 2a10 10 0 1 0 0 20 1.5 1.5 0 0 0 1.06-2.56A1.5 1.5 0 0 1 14 17.5a1.5 1.5 0 0 1 1.5-1.5H17a5 5 0 0 0 5-5 9 9 0 0 0-10-9z"/></svg>
                        Modelo de Crachá
                    </button>
                    <?php endif; ?>
                    <button data-sige-act="exportarFolhaSalario" data-sige-noargs class="sg-v2-btn sg-v2-btn-secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Folha salarial
                    </button>
                    <?php if (!empty($total_removidos)): ?>
                    <button data-sige-act="mostrarArquivoRh" data-sige-noargs class="sg-v2-btn sg-v2-btn-secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>
                        Arquivo removidos
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>
    <!-- ========================================
         ABAS RH: Equipa (gestão) / Relatórios (análise) - v12.35.0
         ======================================== -->
        <div class="sg-rh-tabs" role="tablist" aria-label="Secções de Recursos Humanos">
            <button type="button" class="sg-rh-tab is-active" id="sg-rh-tabbtn-equipa" role="tab" aria-selected="true" aria-controls="sg-rh-panel-equipa" data-sige-act="sgRhSwitchTab" data-sige-args='["equipa"]'>
                <span class="sg-rh-tab-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                Equipa
                <span class="sg-rh-tab-count"><?php echo (int) $total_funcionarios; ?></span>
            </button>
            <button type="button" class="sg-rh-tab" id="sg-rh-tabbtn-relatorios" role="tab" aria-selected="false" aria-controls="sg-rh-panel-relatorios" data-sige-act="sgRhSwitchTab" data-sige-args='["relatorios"]'>
                <span class="sg-rh-tab-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
                Relatórios
            </button>
        </div>

    <!-- ===== PAINEL: EQUIPA (gestão operacional) ===== -->
    <div class="sg-rh-tabpanel is-active" id="sg-rh-panel-equipa" role="tabpanel" aria-labelledby="sg-rh-tabbtn-equipa" data-rh-panel="equipa">
    <!-- ========================================
         INDICADORES - padrão Painel Principal
         ======================================== -->
        <section class="sg-kpi-grid" aria-label="Indicadores de equipa e professores">
            <article class="sg-kpi-card" style="--kpi-color:var(--sg-theme-primary,#5a3fd6);--kpi-soft:var(--sg-theme-soft,var(--color-brand-50));">
                <div class="sg-kpi-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div><div class="sg-kpi-label">Total Colaboradores</div><div class="sg-kpi-value"><?php echo $total_funcionarios; ?></div><div class="sg-kpi-note">Activos na equipa escolar</div></div>
            </article>
            <article class="sg-kpi-card" style="--kpi-color:var(--color-success-500);--kpi-soft:var(--color-success-50);">
                <div class="sg-kpi-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                </div>
                <div><div class="sg-kpi-label">Docentes</div><div class="sg-kpi-value"><?php echo $total_docentes; ?></div><div class="sg-kpi-note">Admin: <strong><?php echo $total_administrativos; ?></strong> · Apoio: <strong><?php echo $total_apoio; ?></strong></div></div>
            </article>
            <article class="sg-kpi-card" style="--kpi-color:var(--color-info-500);--kpi-soft:var(--color-info-50);">
                <div class="sg-kpi-icon">
                    <?php if ($can_manage_equipe): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"/><path d="M21 12a9 9 0 1 1-9-9"/></svg>
                    <?php endif; ?>
                </div>
                <?php if ($can_manage_equipe): ?>
                    <div><div class="sg-kpi-label">Folha Salarial</div><div class="sg-kpi-value"><?php echo number_format($custo_salarial_mensal, 0, ',', '.'); ?></div><div class="sg-kpi-note"><?php echo function_exists('sige_moeda') ? esc_html(sige_moeda()) : 'MT'; ?> / mês</div></div>
                <?php else: ?>
                    <div><div class="sg-kpi-label">Activos / Inactivos</div><div class="sg-kpi-value"><?php echo (int)$total_activos; ?> / <?php echo (int)$total_inactivos; ?></div><div class="sg-kpi-note">Sem exposição de dados salariais</div></div>
                <?php endif; ?>
            </article>
            <article class="sg-kpi-card" style="--kpi-color:var(--color-warning-600);--kpi-soft:var(--color-warning-50);">
                <div class="sg-kpi-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
                </div>
                <div><div class="sg-kpi-label">Efectivos / Contratos</div><div class="sg-kpi-value"><?php echo $efectivos; ?> / <?php echo $contratos; ?></div><div class="sg-kpi-note">Contratos em acompanhamento</div></div>
            </article>
        </section>

    <!-- ========================================
         ALERTAS DE CONTRATO (RH) - só leitura de fim_contrato/tipo/estado
         ======================================== -->
    <?php
    $sige_rh_alertas = function_exists('sige_rh_evaluate_contract_alerts')
        ? sige_rh_evaluate_contract_alerts((array) $_profs_raw, function_exists('wp_date') ? wp_date('Y-m-d') : date('Y-m-d'))
        : ['items' => [], 'counts' => ['total' => 0, 'expirado' => 0, 'critico' => 0, 'aviso' => 0], 'thresholds' => ['critico' => 30, 'aviso' => 90]];
    $sige_rh_al_c  = $sige_rh_alertas['counts'];
    $sige_rh_al_th = $sige_rh_alertas['thresholds'];
    ?>
    <section class="sg-rh-alertas <?php echo $sige_rh_al_c['total'] > 0 ? 'has-alerts' : 'is-clear'; ?>" aria-label="Alertas de contrato">
        <div class="sg-rh-alertas-head">
            <div class="sg-rh-alertas-title">
                <span class="sg-rh-alertas-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
                <div>
                    <h2>Alertas de Contrato</h2>
                    <p>Contratos a terminar nos próximos <?php echo (int) $sige_rh_al_th['aviso']; ?> dias.</p>
                </div>
            </div>
            <?php if ($sige_rh_al_c['total'] > 0): ?>
            <div class="sg-rh-alertas-chips">
                <?php if ($sige_rh_al_c['expirado'] > 0): ?><span class="sg-rh-chip is-exp"><?php echo (int) $sige_rh_al_c['expirado']; ?> expirado<?php echo $sige_rh_al_c['expirado'] === 1 ? '' : 's'; ?></span><?php endif; ?>
                <?php if ($sige_rh_al_c['critico'] > 0): ?><span class="sg-rh-chip is-crit"><?php echo (int) $sige_rh_al_c['critico']; ?> crítico<?php echo $sige_rh_al_c['critico'] === 1 ? '' : 's'; ?></span><?php endif; ?>
                <?php if ($sige_rh_al_c['aviso'] > 0): ?><span class="sg-rh-chip is-warn"><?php echo (int) $sige_rh_al_c['aviso']; ?> a expirar</span><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($sige_rh_al_c['total'] === 0): ?>
        <div class="sg-rh-alertas-ok">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
            Sem contratos a expirar nos próximos <?php echo (int) $sige_rh_al_th['aviso']; ?> dias.
        </div>
        <?php else:
            $sige_rh_al_shown = array_slice($sige_rh_alertas['items'], 0, 8);
        ?>
        <ul class="sg-rh-alertas-list">
            <?php foreach ($sige_rh_al_shown as $al):
                $al_fim_fmt = function_exists('wp_date') ? wp_date('d/m/Y', strtotime($al['fim_contrato'])) : date('d/m/Y', strtotime($al['fim_contrato']));
            ?>
            <li class="sg-rh-alert is-<?php echo esc_attr($al['estado']); ?>">
                <span class="sg-rh-alert-dot" aria-hidden="true"></span>
                <div class="sg-rh-alert-main">
                    <span class="sg-rh-alert-nome"><?php echo esc_html($al['nome'] !== '' ? $al['nome'] : 'Colaborador'); ?></span>
                    <span class="sg-rh-alert-meta"><?php echo esc_html(sige_rh_contract_label($al['tipo_contrato'])); ?> &middot; termina <?php echo esc_html($al_fim_fmt); ?></span>
                </div>
                <span class="sg-rh-alert-badge"><?php echo esc_html(sige_rh_contract_alert_phrase((int) $al['dias'])); ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if (count($sige_rh_alertas['items']) > count($sige_rh_al_shown)): ?>
        <div class="sg-rh-alertas-more">+ <?php echo (int) (count($sige_rh_alertas['items']) - count($sige_rh_al_shown)); ?> outro(s) contrato(s) a expirar</div>
        <?php endif; ?>
        <?php endif; ?>
    </section>

    <!-- ========================================
         TOOLBAR
         ======================================== -->
    <div class="sige-toolbar sg-dash-card">
        <div class="toolbar-filters">
            <button class="filter-btn active" data-sige-act="filtrarTabela" data-sige-args='["todos"]' data-sige-self>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>
                Todos <span class="filter-count"><?php echo $total_funcionarios; ?></span>
            </button>
            <button class="filter-btn" data-sige-act="filtrarTabela" data-sige-args='["prof"]' data-sige-self>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                Docentes <span class="filter-count"><?php echo $total_docentes; ?></span>
            </button>
            <button class="filter-btn" data-sige-act="filtrarTabela" data-sige-args='["admin"]' data-sige-self>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                Admin <span class="filter-count"><?php echo $total_administrativos; ?></span>
            </button>
            <button class="filter-btn" data-sige-act="filtrarTabela" data-sige-args='["apoio"]' data-sige-self>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                Apoio <span class="filter-count"><?php echo $total_apoio; ?></span>
            </button>
            <?php if($total_inactivos > 0): ?>
            <span class="sg-sep-vert"></span>
            <button class="filter-btn" data-sige-act="filtrarTabela" data-sige-args='["inactive-row"]' data-sige-self>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                Inactivos <span class="filter-count"><?php echo $total_inactivos; ?></span>
            </button>
            <?php endif; ?>
        </div>
        
        <div class="sige-search">
            <input type="text" id="staffSearch" placeholder="Pesquisar por nome ou email..." autocomplete="off">
            <svg class="sige-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <button class="sige-search-clear" data-sige-act="limparPesquisa" data-sige-noargs title="Limpar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
    </div>
    <!-- ========================================
         TABLE
         ======================================== -->
    <div class="sige-table-card sg-dash-card">
        <table class="sige-table" id="tabela-staff">
            <thead>
                <tr>
                    <th class="sg-staff-th-nome">Colaborador</th>
                    <th class="sg-staff-th-cargo">Cargo</th>
                    <th class="sg-staff-th-validade">Validade ID</th>
                    <th class="sg-staff-th-status">Status</th>
                    <?php if ($can_manage_equipe): ?>
                    <th class="sg-staff-th-accoes">Ações</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if($staff): ?>
                    <?php foreach($staff as $s):
                        // Cargo segue o perfil SIGE actual (consistente com Permissoes);
                        // recurso ao papel WP legado e, por fim, a capability gravada.
                        $role_slug = $sige_rh_eff_role($s);
                        if (!$role_slug) {
                            $caps = get_user_meta($s->ID, $wpdb->prefix . 'capabilities', true);
                            $role_slug = is_array($caps) ? (string)array_key_first($caps) : '';
                        }

                        $_roles_doc = ['sige_professor','sige_educador','sige_pedagogico'];
                        $_roles_apoio = ['sige_motorista','sige_limpeza','sige_recepcao','sige_guarda'];
                        $filter_class = in_array($role_slug, $_roles_doc, true) ? 'prof' : (in_array($role_slug, $_roles_apoio, true) ? 'apoio' : 'admin');
                        
                        // [PERF] Use preloaded map instead of per-row query
                        $prof_meta_id = (int)get_user_meta($s->ID, 'sige_professor_id', true);
                        $rh = ($prof_meta_id > 0 && isset($_profs_by_id[$prof_meta_id])) ? $_profs_by_id[$prof_meta_id] : ($_profs_map[strtolower($s->user_email)] ?? null);
                        
                        $foto_def = defined('SIGE_URL') ? SIGE_URL . 'assets/img/avatar-default.svg' : '';
                        $foto_url = ($rh && !empty($rh->foto_perfil)) ? $rh->foto_perfil : $foto_def;
                        $tel = $rh ? $rh->telemovel : (get_user_meta($s->ID, 'billing_phone', true) ?: '---');
                        $nuit = $rh ? $rh->nuit : '';
                        
                        $validade_show = '31/12/' . ($escola->ano_lectivo ?: wp_date('Y')); 
                        if($rh && !empty($rh->fim_contrato) && $rh->fim_contrato != '0000-00-00') {
                            $validade_show = wp_date('d/m/Y', strtotime($rh->fim_contrato));
                        }
                        
                        $banco = ($rh && $rh->dados_bancarios) ? sige_hr_json_array($rh->dados_bancarios, ['banco_nome'=>'','nib'=>'','mpesa'=>'']) : ['banco_nome'=>'','nib'=>'','mpesa'=>''];
                        $docs = ($rh && $rh->documentos_urls) ? sige_hr_json_array($rh->documentos_urls, ['doc_bi'=>'','doc_cv'=>'','doc_cert'=>'']) : ['doc_bi'=>'','doc_cv'=>'','doc_cert'=>''];
                        $sal_base = $rh ? floatval($rh->salario_base) : 0;
                        $sal_sub = $rh ? floatval($rh->subsidio) : 0;
                        $sal_total = $sal_base + $sal_sub;
                        
                        $status_ativo = 1;
                        if ($rh) {
                            $status_ativo = isset($rh->status_ativo) ? (int)$rh->status_ativo : 1;
                        }
                        
                        $json_data = htmlspecialchars(json_encode([
                            'id'=>$s->ID, 'nome'=>$s->display_name, 'email'=>$s->user_email, 'role'=>$role_slug,
                            'status_ativo'=>$status_ativo
                        ]), ENT_QUOTES, 'UTF-8');
                        
                        $cracha_data = htmlspecialchars(json_encode([
                            'nome' => $s->display_name, 
                            // [v12.37.2] Cargo REAL do perfil SIGE actual (mesma fonte da lista/badge),
                            // em vez do genérico "DOCENTE/STAFF". Ex.: Direcção, Gestor RH, Secretaria.
                            'cargo' => sige_hr_role_label($role_slug),
                            // Minimização de dados: NUIT não fica embutido no DOM dos crachás.
                            'foto' => $foto_url, 'nuit' => '', 'validade' => $validade_show,
                            'escola' => $escola->nome_escola, 'logo' => $logo_final
                        ]), ENT_QUOTES, 'UTF-8');
                        
                        $badge_map = [
                            'sige_professor' => ['class' => 'docente', 'label' => 'Professor'],
                            'sige_admin_ti' => ['class' => 'admin', 'label' => 'Admin TI'],
                            'sige_director' => ['class' => 'director', 'label' => 'Direcção'],
                            'sige_financeiro' => ['class' => 'financeiro', 'label' => 'Tesouraria'],
                            'sige_secretaria_geral' => ['class' => 'admin', 'label' => 'Secretaria'],
                            'sige_assistente' => ['class' => 'admin', 'label' => 'Assistente'],
                            'sige_educador'  => ['class' => 'docente', 'label' => 'Educador'],
                            'sige_motorista' => ['class' => 'apoio', 'label' => 'Motorista'],
                            'sige_limpeza'   => ['class' => 'apoio', 'label' => 'Limpeza'],
                            'sige_secretario' => ['class' => 'admin', 'label' => 'Secretário'],
                            'sige_gestor_rh'  => ['class' => 'director', 'label' => 'Gestor RH'],
                            'sige_pedagogico' => ['class' => 'director', 'label' => 'Dir. Pedagógico'],
                            'sige_recepcao'   => ['class' => 'apoio', 'label' => 'Recepção'],
                            'sige_guarda'     => ['class' => 'apoio', 'label' => 'Guarda / Portaria']
                        ];
                        $badge_info = $badge_map[$role_slug] ?? ['class' => 'admin', 'label' => ucfirst(str_replace('sige_', '', $role_slug))];
                    ?>
                    <tr class="staff-row <?php echo $filter_class; ?><?php echo !$status_ativo ? ' inactive-row' : ''; ?>" data-cracha='<?php echo $cracha_data; ?>'>
                        <td>
                            <div class="staff-cell">
                                <img src="<?php echo esc_url($foto_url); ?>" class="staff-avatar" alt="<?php echo esc_attr($s->display_name); ?>">
                                <div class="staff-info">
                                    <span class="staff-name"><?php echo esc_html($s->display_name); ?></span>
                                    <span class="staff-email"><?php echo esc_html($s->user_email); ?></span>
                                    <?php // Dados salariais/bancários não são renderizados no DOM; a exportação usa endpoint autorizado. ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="role-badge <?php echo esc_attr($badge_info['class']); ?>">
                                <?php echo esc_html($badge_info['label']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="validity-cell"><?php echo esc_html($validade_show); ?></span>
                        </td>
                        <td>
                            <span id="status-badge-<?php echo esc_attr($s->ID); ?>" class="status-badge <?php echo $status_ativo ? 'active' : 'inactive'; ?>">
                                <span class="status-dot"></span>
                                <?php echo $status_ativo ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </td>
                        <?php if ($can_manage_equipe): ?>
                        <td>
                            <div class="table-actions">
                                <button class="btn-action btn-ficha" data-sige-act="sigeExecutarJsonData" data-sige-json-fn="verFichaColaborador" data-sige-json='<?php echo $json_data; ?>' data-tooltip="Ver ficha">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                </button>
                                <button class="btn-action btn-cracha" data-sige-act="sigeExecutarJsonData" data-sige-json-fn="gerarCracha" data-sige-json='<?php echo $cracha_data; ?>' data-tooltip="Imprimir Crachá">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                                <button class="btn-action btn-edit" data-sige-act="sigeExecutarJsonData" data-sige-json-fn="editarStaff" data-sige-json='<?php echo $json_data; ?>' data-tooltip="Editar">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <button class="btn-action btn-key" data-sige-act="resetSenha" data-sige-args="[<?php echo (int)$s->ID; ?>]" data-tooltip="Reset Senha">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                                </button>
                                <button id="btn-toggle-<?php echo $s->ID; ?>" class="btn-action <?php echo $status_ativo ? 'btn-toggle-off' : 'btn-toggle-on'; ?>" data-sige-act="toggleStatus" data-sige-args="<?php echo esc_attr(wp_json_encode([(int)$s->ID, (int)$status_ativo, $s->user_email])); ?>" data-tooltip="<?php echo $status_ativo ? 'Desactivar' : 'Activar'; ?>">
                                    <?php if($status_ativo): ?>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                    <?php else: ?>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    <?php endif; ?>
                                </button>
                                <button class="btn-action btn-delete" data-sige-act="removerUser" data-sige-args="<?php echo esc_attr(wp_json_encode([(int)$s->ID, $s->display_name])); ?>" data-tooltip="Remover">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                </button>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $can_manage_equipe ? 5 : 4; ?>">
                            <div class="sige-empty-state">
                                <div class="sige-empty-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="8" x2="23" y2="8"/></svg>
                                </div>
                                <h3 class="sige-empty-title">Nenhum colaborador encontrado</h3>
                                <p class="sige-empty-desc">Comece por adicionar o primeiro membro da equipa.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($can_manage_equipe): ?>
    <section id="rh-arquivo-removidos" class="sg-rh-archive-card sg-dash-card" aria-label="Arquivo de colaboradores removidos">
        <div class="sg-rh-archive-head">
            <div>
                <div class="sg-rh-archive-kicker">
                    <?php echo function_exists('sige_ui_icon') ? sige_ui_icon('archive') : ''; ?> Histórico preservado
                </div>
                <h2 class="sg-rh-archive-title">Arquivo de colaboradores removidos</h2>
                <p class="sg-rh-archive-desc">Colaboradores removidos deixam de operar no SIGE, mas permanecem preservados para histórico, auditoria, notas, turmas e rastreabilidade institucional.</p>
            </div>
            <div class="sg-rh-archive-count" title="Total removidos"><?php echo (int)$total_removidos; ?></div>
        </div>
        <?php if (!empty($removed_staff)): ?>
        <div class="sg-rh-archive-table-wrap">
            <table class="sg-rh-archive-table">
                <thead>
                    <tr>
                        <th>Colaborador</th>
                        <th>Último perfil</th>
                        <th>Removido em</th>
                        <th>Removido por</th>
                        <th>Estado operacional</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($removed_staff as $ru):
                    $removed_at_raw = (string)get_user_meta((int)$ru->ID, 'sige_staff_removed_at', true);
                    $removed_by_id = (int)get_user_meta((int)$ru->ID, 'sige_staff_removed_by', true);
                    $removed_by = $removed_by_id > 0 ? get_user_by('ID', $removed_by_id) : null;
                    $removed_by_name = $removed_by ? $removed_by->display_name : 'Sistema';
                    $previous_roles = get_user_meta((int)$ru->ID, 'sige_staff_previous_roles', true);
                    $previous_roles = is_array($previous_roles) ? array_values($previous_roles) : [];
                    $last_role = !empty($previous_roles) ? (string)$previous_roles[0] : (reset($ru->roles) ?: '');
                    $role_label = sige_hr_role_label($last_role);
                    $avatar = get_avatar_url((int)$ru->ID, ['size' => 72]);
                    $removed_at_show = $removed_at_raw !== '' ? wp_date('d/m/Y H:i', strtotime($removed_at_raw)) : '-';
                ?>
                    <tr>
                        <td>
                            <div class="sg-rh-archive-user">
                                <img src="<?php echo esc_url($avatar); ?>" class="sg-rh-archive-avatar" alt="<?php echo esc_attr($ru->display_name); ?>">
                                <div>
                                    <span class="sg-rh-archive-name"><?php echo esc_html($ru->display_name); ?></span>
                                    <span class="sg-rh-archive-email"><?php echo esc_html($ru->user_email); ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?php echo esc_html($role_label); ?></td>
                        <td><?php echo esc_html($removed_at_show); ?></td>
                        <td><?php echo esc_html($removed_by_name); ?></td>
                        <td><span class="sg-rh-archive-status">Acesso revogado</span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="sg-rh-archive-note"><strong>Nota de controlo:</strong> este arquivo é apenas consultivo. A reactivação de colaboradores removidos não acontece neste fluxo normal, para evitar reentrada acidental de acessos antigos.</div>
        <?php else: ?>
        <div class="sige-empty-state sg-pad-empty">
            <div class="sige-empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>
            </div>
            <h3 class="sige-empty-title">Sem colaboradores removidos</h3>
            <p class="sige-empty-desc">Quando um colaborador for removido da equipa, aparecerá aqui para consulta histórica.</p>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    </div><!-- /#sg-rh-panel-equipa -->

    <!-- ========================================
         PAINEL: RELATÓRIOS (análise de RH) - só leitura/agregação
         ======================================== -->
    <div class="sg-rh-tabpanel" id="sg-rh-panel-relatorios" role="tabpanel" aria-labelledby="sg-rh-tabbtn-relatorios" data-rh-panel="relatorios" hidden>
    <?php
    $REL = function_exists('sige_rh_build_reports')
        ? sige_rh_build_reports($sige_rh_people, ['today' => function_exists('wp_date') ? wp_date('Y-m-d') : date('Y-m-d')])
        : null;
    if ($REL):
        $rel_ativos = (int) $REL['ativos'];
        $rel_moeda  = function_exists('sige_moeda') ? sige_moeda() : 'MT';
        // Barra horizontal reutilizável (largura relativa ao máximo; % sobre activos).
        $rel_bar = function (string $label, int $count, int $max, string $barvar = '') use ($rel_ativos) {
            $w   = $max > 0 ? max(3, (int) round($count / $max * 100)) : 3;
            $pct = $rel_ativos > 0 ? (int) round($count / $rel_ativos * 100) : 0;
            $style = 'width:' . $w . '%' . ($barvar !== '' ? ';--bar:' . $barvar : '');
            echo '<div class="sg-rh-bar-row">';
            echo '<span class="sg-rh-bar-label">' . esc_html($label) . '</span>';
            echo '<span class="sg-rh-bar-track"><span class="sg-rh-bar-fill" style="' . esc_attr($style) . '"></span></span>';
            echo '<span class="sg-rh-bar-val">' . (int) $count . ' <small>' . $pct . '%</small></span>';
            echo '</div>';
        };
        // Listas de distribuição.
        $cat_max = max(1, max(array_map(fn($x) => (int) $x['count'], $REL['categoria'])));
        $vin_max = max(1, max(array_map(fn($x) => (int) $x['count'], $REL['vinculo'])));
        $cat_cores = ['docente' => 'var(--color-success-500)', 'administrativo' => 'var(--sg-theme-primary,var(--color-brand-500))', 'apoio' => 'var(--color-info-400)'];
        $vin_cores = ['efectivo' => 'var(--color-success-500)', 'contrato' => 'var(--color-warning-500)', 'estagio' => 'var(--color-info-400)', '' => 'var(--color-slate-400)'];
        $vin_efe = (int) $REL['vinculo'][0]['count'];
        $vin_prazo = (int) $REL['vinculo'][1]['count'];
    ?>
        <div class="sg-rh-rep-intro">
            <span class="sg-rh-rep-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg></span>
            <div>
                <h2>Relatórios de RH</h2>
                <p>Análise da equipa activa (<?php echo (int) $rel_ativos; ?> colaborador<?php echo $rel_ativos === 1 ? '' : 'es'; ?><?php echo $REL['inativos'] > 0 ? ' · ' . (int) $REL['inativos'] . ' inactivo' . ($REL['inativos'] === 1 ? '' : 's') : ''; ?>). Reconcilia com as KPIs e a lista da Equipa.</p>
            </div>
        </div>

        <div class="sg-rh-rep-stats">
            <div class="sg-rh-rep-stat">
                <span class="v"><?php echo (int) $rel_ativos; ?></span>
                <span class="l">Colaboradores activos</span>
                <span class="n"><?php echo (int) $REL['inativos']; ?> inactivo(s) fora da análise</span>
            </div>
            <div class="sg-rh-rep-stat">
                <span class="v"><?php echo number_format((float) $REL['antiguidade_media'], 1, ',', '.'); ?> <small data-sige-style="font-size:var(--fs-sm);color:var(--color-slate-400)">anos</small></span>
                <span class="l">Antiguidade média</span>
                <span class="n">Com base na data de admissão</span>
            </div>
            <div class="sg-rh-rep-stat">
                <span class="v"><?php echo (int) $vin_efe; ?> <small data-sige-style="font-size:var(--fs-sm);color:var(--color-slate-400)"><?php echo sige_rh_pct($vin_efe, $rel_ativos); ?>%</small></span>
                <span class="l">Efectivos (quadro)</span>
                <span class="n">Vínculo permanente</span>
            </div>
            <div class="sg-rh-rep-stat">
                <span class="v"><?php echo (int) $vin_prazo; ?> <small data-sige-style="font-size:var(--fs-sm);color:var(--color-slate-400)"><?php echo sige_rh_pct($vin_prazo, $rel_ativos); ?>%</small></span>
                <span class="l">Contratos a prazo</span>
                <span class="n">Requerem acompanhamento</span>
            </div>
        </div>

        <div class="sg-rh-rep-grid">
            <!-- Categoria -->
            <div class="sg-rh-rep-card">
                <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg> Por categoria</h3>
                <div class="sg-rh-bars">
                    <?php foreach ($REL['categoria'] as $c) { $rel_bar($c['label'], (int) $c['count'], $cat_max, $cat_cores[$c['slug']] ?? ''); } ?>
                </div>
            </div>
            <!-- Vínculo -->
            <div class="sg-rh-rep-card">
                <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg> Por vínculo contratual</h3>
                <div class="sg-rh-bars">
                    <?php foreach ($REL['vinculo'] as $v) { $rel_bar($v['label'], (int) $v['count'], $vin_max, $vin_cores[$v['slug']] ?? ''); } ?>
                </div>
            </div>
            <!-- Regime de trabalho -->
            <div class="sg-rh-rep-card">
                <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Por regime de trabalho</h3>
                <?php if (!empty($REL['regime'])) { $reg_max = max(1, max(array_map(fn($x) => (int) $x['count'], $REL['regime']))); ?>
                <div class="sg-rh-bars">
                    <?php foreach (array_slice($REL['regime'], 0, 6) as $r) { $rel_bar($r['label'], (int) $r['count'], $reg_max); } ?>
                </div>
                <?php } else { ?><div class="sg-rh-rep-empty">Sem regime de trabalho registado.</div><?php } ?>
            </div>
            <!-- Nível de carreira -->
            <div class="sg-rh-rep-card">
                <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> Por nível de carreira</h3>
                <?php if (!empty($REL['carreira'])) { $car_max = max(1, max(array_map(fn($x) => (int) $x['count'], $REL['carreira']))); ?>
                <div class="sg-rh-bars">
                    <?php foreach (array_slice($REL['carreira'], 0, 6) as $c) { $rel_bar($c['label'], (int) $c['count'], $car_max); } ?>
                </div>
                <?php } else { ?><div class="sg-rh-rep-empty">Sem nível de carreira registado.</div><?php } ?>
            </div>
            <!-- Admissões por ano -->
            <div class="sg-rh-rep-card is-wide">
                <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Admissões por ano</h3>
                <?php if (!empty($REL['admissoes'])) { $adm_max = max(1, max(array_map('intval', $REL['admissoes']))); ?>
                <div class="sg-rh-cols">
                    <?php foreach ($REL['admissoes'] as $ano => $n) { $h = max(4, (int) round($n / $adm_max * 100)); ?>
                    <div class="sg-rh-col">
                        <span class="sg-rh-col-val"><?php echo (int) $n; ?></span>
                        <span class="sg-rh-col-bar" style="height:<?php echo (int) $h; ?>%"></span>
                        <span class="sg-rh-col-year"><?php echo esc_html((string) $ano); ?></span>
                    </div>
                    <?php } ?>
                </div>
                <?php } else { ?><div class="sg-rh-rep-empty">Sem datas de admissão registadas.</div><?php } ?>
            </div>
            <?php if ($can_manage_equipe): ?>
            <!-- Massa salarial (sensível: só gestão) -->
            <div class="sg-rh-rep-card">
                <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg> Massa salarial mensal</h3>
                <div class="sg-rh-bars">
                    <div class="sg-rh-bar-row"><span class="sg-rh-bar-label">Total / mês</span><span class="sg-rh-bar-val" data-sige-style="min-width:auto;text-align:left"><strong><?php echo number_format((float) $REL['salario']['massa'], 0, ',', '.'); ?></strong> <small><?php echo esc_html($rel_moeda); ?></small></span></div>
                    <div class="sg-rh-bar-row"><span class="sg-rh-bar-label">Média por colaborador</span><span class="sg-rh-bar-val" data-sige-style="min-width:auto;text-align:left"><strong><?php echo number_format((float) $REL['salario']['media'], 0, ',', '.'); ?></strong> <small><?php echo esc_html($rel_moeda); ?></small></span></div>
                    <div class="sg-rh-bar-row"><span class="sg-rh-bar-label">Com salário definido</span><span class="sg-rh-bar-val" data-sige-style="min-width:auto;text-align:left"><strong><?php echo (int) $REL['salario']['com_valor']; ?></strong> <small>de <?php echo (int) $rel_ativos; ?></small></span></div>
                </div>
            </div>
            <?php endif; ?>
            <!-- Qualidade dos dados -->
            <div class="sg-rh-rep-card is-wide">
                <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Qualidade e completude dos dados</h3>
                <div class="sg-rh-qual">
                    <?php
                    $qual_labels = [
                        'ficha'     => 'Ficha de RH preenchida',
                        'nuit'      => 'NUIT',
                        'telemovel' => 'Telemóvel',
                        'email'     => 'E-mail',
                        'foto'      => 'Fotografia',
                        'admissao'  => 'Data de admissão',
                    ];
                    foreach ($qual_labels as $qk => $qlabel):
                        $ok = (int) ($REL['qualidade'][$qk]['ok'] ?? 0);
                        $falta = (int) ($REL['qualidade'][$qk]['falta'] ?? 0);
                        $den = $ok + $falta;
                        $pct = sige_rh_pct($ok, $den);
                        $cls = $pct >= 80 ? '' : ($pct >= 50 ? ' is-warn' : ' is-bad');
                    ?>
                    <div class="sg-rh-qual-row">
                        <span class="sg-rh-qual-label"><?php echo esc_html($qlabel); ?></span>
                        <span class="sg-rh-qual-track"><span class="sg-rh-qual-fill<?php echo $cls; ?>" style="width:<?php echo (int) $pct; ?>%"></span></span>
                        <span class="sg-rh-qual-val"><?php echo $pct; ?>% <small><?php echo (int) $ok; ?>/<?php echo (int) $den; ?></small></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="sg-rh-rep-empty">Relatórios indisponíveis (módulo de RH não carregado).</div>
    <?php endif; ?>
    </div><!-- /#sg-rh-panel-relatorios -->
    </div>
</div>
<!-- ========================================
     MODAL: CADASTRO / EDIÇÃO
     ======================================== -->
<div class="sige-modal" id="box-equipa" aria-hidden="true">
    <div class="modal-content sg-rh-staff-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="modal-header">
            <div class="sg-rh-modal-title-wrap">
                <span class="sg-rh-modal-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
                </span>
                <div>
                    <h3 id="modal-title">Novo colaborador</h3>
                    <p id="modal-subtitle">Organize os dados pessoais, contrato, remuneração e documentos do colaborador.</p>
                </div>
            </div>
            <button type="button" class="modal-close" data-sige-act="fecharForm" data-sige-noargs aria-label="Fechar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        
        <div class="modal-tabs">
            <button type="button" class="tab-btn active" data-sige-act="activarTab" data-sige-args="[1]">
                <span class="tab-step">1</span>
                Dados Pessoais
            </button>
            <button type="button" class="tab-btn" data-sige-act="activarTab" data-sige-args="[2]">
                <span class="tab-step">2</span>
                Contrato
            </button>
            <button type="button" class="tab-btn" data-sige-act="activarTab" data-sige-args="[3]">
                <span class="tab-step">3</span>
                Remuneração
            </button>
            <button type="button" class="tab-btn" data-sige-act="activarTab" data-sige-args="[4]">
                <span class="tab-step">4</span>
                Banco e documentos
            </button>
        </div>
        
        <form id="form-staff" data-sige-on-submit="guardarStaff(event)">
            <input type="hidden" name="staff_id" id="staff_id">
            
            <div class="modal-body">
                <!-- Tab 1: Dados Pessoais -->
                <div id="tab-1" class="tab-content active">
                    <div class="sg-rh-personal-layout">
                        <div class="sg-rh-personal-fields">
                            <div class="form-grid form-grid-2 sg-mb-20">
                                <div class="field-group span-2">
                                    <label for="nome_staff">Nome Completo <span class="required">*</span></label>
                                    <input type="text" name="nome_staff" id="nome_staff" required placeholder="Nome completo do colaborador">
                                    <span class="field-error"></span>
                                </div>
                                
                                <div class="field-group">
                                    <label for="cargo_staff">Cargo / Perfil <span class="required">*</span></label>
                                    <select name="cargo_staff" id="cargo_staff" required>
                                        <option value="">Seleccione...</option>
                                        <optgroup label="Direcção">
                                            <option value="sige_director">Direcção Geral</option>
                                            <option value="sige_pedagogico">Dir. Pedagógico</option>
                                            <option value="sige_gestor_rh">Gestor de RH</option>
                                        </optgroup>
                                        <optgroup label="Docentes">
                                            <option value="sige_professor">Professor</option>
                                            <option value="sige_educador">Educador</option>
                                        </optgroup>
                                        <optgroup label="Administrativos">
                                            <option value="sige_secretaria_geral">Secretaria Geral</option>
                                            <option value="sige_secretario">Secretário</option>
                                            <option value="sige_financeiro">Tesouraria</option>
                                            <option value="sige_assistente">Assistente</option>
                                            <option value="sige_recepcao">Recepção</option>
                                            <option value="sige_guarda">Guarda / Portaria</option>
                                            <?php if ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && in_array('administrator', (array)wp_get_current_user()->roles, true)): ?>
                                            <option value="sige_admin_ti">Admin TI</option>
                                            <?php endif; ?>
                                        </optgroup>
                                        <optgroup label="Apoio">
                                            <option value="sige_motorista">Motorista</option>
                                            <option value="sige_limpeza">Limpeza</option>
                                        </optgroup>
                                    </select>
                                    <span class="field-error"></span>
                                </div>
                                
                                <div class="field-group">
                                    <label for="email_staff">E-mail <span class="required">*</span></label>
                                    <input type="email" name="email_staff" id="email_staff" required placeholder="email@exemplo.com">
                                    <span class="field-error"></span>
                                </div>
                            </div>
                            
                            <div class="form-grid form-grid-2 sg-mb-20">
                                <div class="field-group">
                                    <label for="telemovel">Telemóvel</label>
                                    <input type="tel" name="telemovel" id="telemovel" placeholder="+258 XX XXX XXXX">
                                </div>
                                
                                <div class="field-group">
                                    <label for="nuit">NUIT</label>
                                    <input type="text" name="nuit" id="nuit" placeholder="9 dígitos" maxlength="9">
                                    <span class="field-hint">Número Único de Identificação Tributária</span>
                                </div>
                            </div>
                            
                            <div class="form-grid">
                                <div class="field-group">
                                    <label for="formacao">Formação Académica</label>
                                    <input type="text" name="formacao" id="formacao" placeholder="Ex: Licenciatura em Educação">
                                </div>
                            </div>
                        </div>
                        
                        <div class="photo-upload-area">
                            <label class="sg-rh-photo-label">Foto passe</label>
                            <img id="preview_foto" src="<?php echo defined('SIGE_URL') ? esc_url(SIGE_URL . 'assets/img/avatar-default.svg') : ''; ?>" class="photo-preview" alt="Preview">
                            <input type="hidden" name="foto_perfil" id="foto_perfil">
                            <button type="button" data-sige-act="uploadFoto" data-sige-noargs class="btn-upload">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                Carregar
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Tab 2: Contrato -->
                <div id="tab-2" class="tab-content">
                    <div class="form-grid form-grid-2">
                        <div class="field-group">
                            <label for="tipo_contrato">Tipo de Vínculo</label>
                            <select name="tipo_contrato" id="tipo_contrato">
                                <option value="efectivo">Efectivo (Quadro)</option>
                                <option value="contrato">Contrato a Prazo</option>
                                <option value="estagio">Estagiário</option>
                            </select>
                        </div>
                        
                        <div class="field-group">
                            <label for="fim_contrato">Data de Término</label>
                            <input type="date" name="fim_contrato" id="fim_contrato">
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <p>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            Para contratos efectivos, deixe a data de término em branco. A validade do crachá será automaticamente definida para 31/12 do ano lectivo.
                        </p>
                    </div>
                </div>
                
                <!-- Tab 3: Remuneração -->
                <div id="tab-3" class="tab-content">
                    <div class="highlighted-section">
                        <div class="form-grid form-grid-2">
                            <div class="field-group">
                                <label for="salario_base">Salário Base (MT)</label>
                                <input type="number" name="salario_base" id="salario_base" placeholder="0.00" step="0.01" min="0">
                            </div>
                            
                            <div class="field-group">
                                <label for="subsidio">Subsídios (MT)</label>
                                <input type="number" name="subsidio" id="subsidio" placeholder="0.00" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-box sg-mt-24">
                        <p>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            Estes valores são utilizados para cálculo da folha salarial mensal. Certifique-se de que estão correctos.
                        </p>
                    </div>
                </div>
                
                <!-- Tab 4: Banco e documentos -->
                <div id="tab-4" class="tab-content">
                    <div class="form-grid form-grid-3 sg-mb-24">
                        <div class="field-group">
                            <label for="banco_nome">Nome do Banco</label>
                            <input type="text" name="banco_nome" id="banco_nome" placeholder="Ex: BCI, Millennium BIM">
                        </div>
                        
                        <div class="field-group">
                            <label for="banco_nib">NIB / IBAN</label>
                            <input type="text" name="banco_nib" id="banco_nib" placeholder="21 dígitos">
                        </div>
                        
                        <div class="field-group">
                            <label for="banco_mpesa">M-Pesa</label>
                            <input type="text" name="banco_mpesa" id="banco_mpesa" placeholder="+258 84 XXX XXXX">
                        </div>
                    </div>
                    
                    <div class="sg-rh-doc-section">
                        <label class="sg-rh-section-title">Documentos</label>
                        
                        <div class="form-grid form-grid-3">
                            <div class="field-group">
                                <label for="doc_bi">BI / Passaporte</label>
                                <div class="sg-rh-upload-row">
                                    <input type="text" name="doc_bi" id="doc_bi" placeholder="URL do documento">
                                    <button type="button" class="btn-upload btn-upload-doc" data-upload-target="doc_bi" data-sige-act="uploadDoc" data-sige-arg="doc_bi" aria-label="Carregar BI ou passaporte">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                        <span>Carregar</span>
                                    </button>
                                </div>
                                <span class="sg-rh-upload-hint">Guarde aqui o ficheiro digital do documento.</span>
                            </div>
                            
                            <div class="field-group">
                                <label for="doc_cv">CV / Currículo</label>
                                <div class="sg-rh-upload-row">
                                    <input type="text" name="doc_cv" id="doc_cv" placeholder="URL do documento">
                                    <button type="button" class="btn-upload btn-upload-doc" data-upload-target="doc_cv" data-sige-act="uploadDoc" data-sige-arg="doc_cv" aria-label="Carregar CV ou currículo">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                        <span>Carregar</span>
                                    </button>
                                </div>
                                <span class="sg-rh-upload-hint">Anexe o currículo actualizado do colaborador.</span>
                            </div>
                            
                            <div class="field-group">
                                <label for="doc_cert">Certificados</label>
                                <div class="sg-rh-upload-row">
                                    <input type="text" name="doc_cert" id="doc_cert" placeholder="URL do documento">
                                    <button type="button" class="btn-upload btn-upload-doc" data-upload-target="doc_cert" data-sige-act="uploadDoc" data-sige-arg="doc_cert" aria-label="Carregar certificados">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                        <span>Carregar</span>
                                    </button>
                                </div>
                                <span class="sg-rh-upload-hint">Anexe diplomas, certificados ou comprovativos.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-modal btn-cancel" data-sige-act="fecharForm" data-sige-noargs>
                    Cancelar
                </button>
                <button type="submit" class="btn-modal btn-submit" id="btn-submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Pop-up real de confirmação -->
<div class="sige-modal sige-rh-confirm" id="sige-rh-confirm" aria-hidden="true">
    <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="sige-rh-confirm-title">
        <div class="sige-confirm-panel">
            <div class="sige-confirm-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
            </div>
            <h3 class="sige-confirm-title" id="sige-rh-confirm-title">Confirmar acção</h3>
            <p class="sige-confirm-message" id="sige-rh-confirm-message">Confirme para continuar.</p>
            <div class="sige-confirm-detail" id="sige-rh-confirm-detail" style="display:none"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal btn-cancel" id="sige-rh-confirm-cancel">Voltar</button>
            <button type="button" class="btn-modal btn-submit" id="sige-rh-confirm-ok">Confirmar</button>
        </div>
    </div>
</div>

<!-- ========================================
     MODAL: FICHA DO COLABORADOR (perfil 360, só leitura) - v12.36.0
     ======================================== -->
<div class="sige-modal sg-ficha-modal" id="box-ficha" aria-hidden="true">
    <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="ficha-title">
        <div class="modal-header">
            <div class="sg-rh-modal-title-wrap">
                <span class="sg-rh-modal-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </span>
                <div>
                    <h3 id="ficha-title">Ficha do colaborador</h3>
                    <p>Consulta consolidada dos dados de RH. Só leitura.</p>
                </div>
            </div>
            <button type="button" class="modal-close" data-sige-act="fecharFicha" data-sige-noargs aria-label="Fechar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="sg-ficha" id="ficha-conteudo">
                <div class="sg-ficha-loading">A carregar ficha…</div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal btn-cancel" data-sige-act="fecharFicha" data-sige-noargs>Fechar</button>
            <button type="button" class="btn-modal btn-submit" data-sige-act="imprimirFicha" data-sige-noargs>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Imprimir / Exportar (PDF)
            </button>
        </div>
    </div>
</div>

<!-- ExcelJS para exportação -->
<?php echo sige_cdn_script("exceljs"); ?>
<?php
// Registo de modelos de crachá entregue INLINE a partir do filesystem (à prova
// de falhas de HTTP). Mesmo ficheiro do estudante; expõe SigeCrachaStaffTemplates.
$sige_cracha_tpl_file = '';
if (defined('SIGE_PATH')) {
    foreach (['assets/cracha/sige-cracha-templates.js', 'assets/views/sige-cracha-templates.js'] as $sige_cracha_rel) {
        if (is_file(SIGE_PATH . $sige_cracha_rel)) { $sige_cracha_tpl_file = SIGE_PATH . $sige_cracha_rel; break; }
    }
}
if ($sige_cracha_tpl_file) {
    echo '<script ' . sige_csp_script_attr() . ">\n";
    readfile($sige_cracha_tpl_file);
    echo "\n</script>\n";
}
?>
<script <?php echo sige_csp_script_attr(); ?>>
// ========================================
// AJAX CONFIG - Nonce e URL
// ========================================
// Configuração AJAX isolada do módulo Equipa.
// Não usa o objecto global `sigeAjax` como fonte de verdade, porque o App Shell
// pode localizá-lo no footer e sobrescrever propriedades específicas da view.
window.sigeEquipeAjax = Object.assign({}, window.sigeEquipeAjax || {}, {
    nonce: '<?php echo esc_js(wp_create_nonce("sige_equipe_action")); ?>',
    ajaxurl: '<?php echo esc_url(admin_url("admin-ajax.php")); ?>',
    avatarUrl: '<?php echo defined("SIGE_URL") ? esc_url(SIGE_URL . "assets/img/avatar-default.svg") : ""; ?>',
    moeda: '<?php echo function_exists("sige_moeda") ? esc_js(sige_moeda()) : "MT"; ?>',
    escola: '<?php echo isset($escola->nome_escola) ? esc_js($escola->nome_escola) : ""; ?>',
    // Modelo de crachá da equipa (config + catálogo) e nonce/asset para o seletor.
    cracha: <?php echo wp_json_encode(function_exists('sige_cracha_staff_config_for_js') ? sige_cracha_staff_config_for_js() : ['config' => [], 'templates' => [], 'social' => []]); ?>,
    csp_nonce: '<?php echo function_exists("sige_csp_nonce") ? esc_js(sige_csp_nonce()) : ""; ?>',
    cracha_asset: '<?php echo defined("SIGE_URL") ? esc_js(SIGE_URL . ((defined("SIGE_PATH") && !is_file(SIGE_PATH . "assets/cracha/sige-cracha-templates.js") && is_file(SIGE_PATH . "assets/views/sige-cracha-templates.js")) ? "assets/views/" : "assets/cracha/") . "sige-cracha-templates.js?ver=" . (defined("SIGE_VERSION") ? SIGE_VERSION : "1")) : ""; ?>'
});
// ========================================
// TOAST NOTIFICATIONS
// ========================================
function sigeEquipeEscapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function(ch) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[ch];
    });
}
function showToast(title, message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    type = ['success','error','warning','info'].includes(type) ? type : 'info';
    const toast = document.createElement('div');
    toast.className = `sige-toast ${type}`;
    const safeTitle = sigeEquipeEscapeHtml(title);
    const safeMessage = sigeEquipeEscapeHtml(message);
    
    const icons = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
    };
    
    toast.innerHTML = `
        <div class="sige-toast-icon">${icons[type]}</div>
        <div class="sige-toast-content">
            <div class="sige-toast-title">${safeTitle}</div>
            <div class="sige-toast-message">${safeMessage}</div>
        </div>
        <button class="sige-toast-close" data-sige-act="sigeRemoverPai">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    `;
    
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

function sigeEquipeAjaxFailMessage(xhr, fallback) {
    const status = xhr && xhr.status ? String(xhr.status) : '';
    let detail = '';
    if (xhr && xhr.responseJSON && xhr.responseJSON.data) {
        detail = String(xhr.responseJSON.data);
    } else if (xhr && xhr.responseText) {
        detail = String(xhr.responseText).replace(/<script[\s\S]*?<\/script>/gi, '')
            .replace(/<style[\s\S]*?<\/style>/gi, '')
            .replace(/<[^>]*>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .slice(0, 160);
    }
    if (detail) return (fallback || 'Erro de comunicação com o servidor') + (status ? ' (HTTP ' + status + '): ' : ': ') + detail;
    return (fallback || 'Erro de comunicação com o servidor') + (status ? ' (HTTP ' + status + ')' : '');
}

// ========================================
// CONFIRMAÇÕES EM POP-UP REAL
// ========================================
function sigeConfirm(options) {
    options = options || {};
    return new Promise(function(resolve) {
        const modal = document.getElementById('sige-rh-confirm');
        const title = document.getElementById('sige-rh-confirm-title');
        const msg = document.getElementById('sige-rh-confirm-message');
        const detail = document.getElementById('sige-rh-confirm-detail');
        const cancel = document.getElementById('sige-rh-confirm-cancel');
        const ok = document.getElementById('sige-rh-confirm-ok');
        if (!modal || !title || !msg || !cancel || !ok) {
            resolve(window.confirm(options.message || 'Confirmar esta acção?'));
            return;
        }
        title.textContent = options.title || 'Confirmar acção';
        msg.textContent = options.message || 'Confirme para continuar.';
        ok.textContent = options.okText || 'Confirmar';
        cancel.textContent = options.cancelText || 'Voltar';
        if (options.detail) {
            detail.textContent = options.detail;
            detail.style.display = 'block';
        } else {
            detail.textContent = '';
            detail.style.display = 'none';
        }
        let done = false;
        function close(value) {
            if (done) return;
            done = true;
            sigeEquipeCloseModal(modal);
            cancel.removeEventListener('click', onCancel);
            ok.removeEventListener('click', onOk);
            modal.removeEventListener('click', onOverlay);
            document.removeEventListener('keydown', onKey);
            resolve(value);
        }
        function onCancel(){ close(false); }
        function onOk(){ close(true); }
        function onOverlay(e){ if (e.target === modal) close(false); }
        function onKey(e){ if (e.key === 'Escape') close(false); }
        cancel.addEventListener('click', onCancel);
        ok.addEventListener('click', onOk);
        modal.addEventListener('click', onOverlay);
        document.addEventListener('keydown', onKey);
        sigeEquipeOpenModal(modal);
        setTimeout(function(){ ok.focus(); }, 50);
    });
}

// ========================================
// TABS
// ========================================
function activarTab(num) {
    document.querySelectorAll('.tab-btn').forEach((btn, i) => {
        btn.classList.toggle('active', i === num - 1);
    });
    document.querySelectorAll('.tab-content').forEach((content, i) => {
        content.classList.toggle('active', i === num - 1);
    });
}
// ========================================
// MODAL
// ========================================
function actualizarEstadoUploadDocs() {
    ['doc_bi','doc_cv','doc_cert'].forEach(function(fieldId) {
        const input = document.getElementById(fieldId);
        const btn = document.querySelector('[data-upload-target="' + fieldId + '"]');
        if (!input || !btn) return;
        const label = btn.querySelector('span');
        const preenchido = (input.value || '').trim() !== '';
        btn.classList.toggle('uploaded', preenchido);
        if (label) label.textContent = preenchido ? 'Alterar' : 'Carregar';
    });
}

document.addEventListener('input', function(e) {
    if (e.target && ['doc_bi','doc_cv','doc_cert'].includes(e.target.id)) {
        actualizarEstadoUploadDocs();
    }
});

function sigeEquipeOpenModal(modal) {
    if (!modal) return;
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    modal.style.display = 'flex';
    modal.style.opacity = '1';
    modal.style.visibility = 'visible';
    modal.style.pointerEvents = 'auto';
    document.body.style.overflow = 'hidden';
    // Eleva o conteudo acima da barra lateral enquanto o modal esta aberto
    // (ver regra .sige-rh-modal-open .sg-app-content no <style> da view).
    document.body.classList.add('sige-rh-modal-open');
}
function sigeEquipeCloseModal(modal) {
    if (!modal) return;
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
    modal.style.display = 'none';
    modal.style.opacity = '0';
    modal.style.visibility = 'hidden';
    modal.style.pointerEvents = 'none';
    // So restaura o empilhamento normal quando nenhum modal da Equipa fica aberto.
    if (!document.querySelector('.sige-modal.active')) {
        document.body.style.overflow = '';
        document.body.classList.remove('sige-rh-modal-open');
    }
}
function novoFuncionario() {
    document.getElementById('modal-title').textContent = 'Novo colaborador';
    document.getElementById('modal-subtitle').textContent = 'Registe os dados necessários para integrar o colaborador na equipa escolar.';
    document.getElementById('form-staff').reset();
    document.getElementById('staff_id').value = '';
    document.getElementById('preview_foto').src = sigeEquipeAjax.avatarUrl;
    document.getElementById('foto_perfil').value = '';
    actualizarEstadoUploadDocs();
    activarTab(1);
    sigeEquipeOpenModal(document.getElementById('box-equipa'));
}
function fecharForm() {
    sigeEquipeCloseModal(document.getElementById('box-equipa'));
}
function preencherModalStaff(data) {
    document.getElementById('staff_id').value = data.id || '';
    document.getElementById('nome_staff').value = data.nome || '';
    document.getElementById('email_staff').value = data.email || '';
    document.getElementById('cargo_staff').value = data.role || '';
    document.getElementById('telemovel').value = data.tel || '';
    document.getElementById('nuit').value = data.nuit || '';
    document.getElementById('formacao').value = data.formacao || '';
    document.getElementById('tipo_contrato').value = data.tipo_contrato || 'efectivo';
    document.getElementById('fim_contrato').value = data.fim_contrato || '';
    document.getElementById('salario_base').value = data.salario_base || '';
    document.getElementById('subsidio').value = data.subsidio || '';

    if(data.foto_perfil) {
        document.getElementById('preview_foto').src = data.foto_perfil;
        document.getElementById('foto_perfil').value = data.foto_perfil;
    } else {
        document.getElementById('preview_foto').src = sigeEquipeAjax.avatarUrl;
        document.getElementById('foto_perfil').value = '';
    }

    document.getElementById('banco_nome').value = data.banco ? (data.banco.banco_nome || '') : '';
    document.getElementById('banco_nib').value = data.banco ? (data.banco.nib || '') : '';
    document.getElementById('banco_mpesa').value = data.banco ? (data.banco.mpesa || '') : '';

    document.getElementById('doc_bi').value = data.docs ? (data.docs.doc_bi || '') : '';
    document.getElementById('doc_cv').value = data.docs ? (data.docs.doc_cv || '') : '';
    document.getElementById('doc_cert').value = data.docs ? (data.docs.doc_cert || '') : '';
    actualizarEstadoUploadDocs();
}

function editarStaff(data) {
    document.getElementById('modal-title').textContent = 'Editar colaborador';
    document.getElementById('modal-subtitle').textContent = 'A carregar ficha segura do colaborador...';
    document.getElementById('form-staff').reset();
    preencherModalStaff(data || {});
    activarTab(1);
    sigeEquipeOpenModal(document.getElementById('box-equipa'));

    jQuery.post(sigeEquipeAjax.ajaxurl, {
        action: 'sige_get_staff_secure',
        id: data.id,
        _sige_nonce: sigeEquipeAjax.nonce
    }, function(res) {
        if(res.success) {
            preencherModalStaff(res.data || {});
            document.getElementById('modal-subtitle').textContent = 'Actualize apenas os dados necessários e guarde quando estiver tudo confirmado.';
        } else {
            showToast('Erro', res.data || 'Não foi possível carregar a ficha segura.', 'error');
            fecharForm();
        }
    }).fail(function() {
        showToast('Erro', sigeEquipeAjaxFailMessage(arguments[0], 'Erro de comunicação ao carregar ficha segura'), 'error');
        fecharForm();
    });
}
// ========================================
// SEARCH & FILTER
// ========================================
let searchTimeout;
document.getElementById('staffSearch').addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const val = e.target.value.toLowerCase();
        document.querySelectorAll('#tabela-staff tbody tr.staff-row').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(val) ? '' : 'none';
        });
    }, 200);
});
function limparPesquisa() {
    document.getElementById('staffSearch').value = '';
    document.querySelectorAll('#tabela-staff tbody tr.staff-row').forEach(row => {
        row.style.display = '';
    });
}
function mostrarArquivoRh() {
    const el = document.getElementById('rh-arquivo-removidos');
    if (!el) return;
    el.scrollIntoView({behavior: 'smooth', block: 'start'});
    el.classList.add('sg-rh-archive-focus');
    setTimeout(function(){ el.classList.remove('sg-rh-archive-focus'); }, 900);
}
function filtrarTabela(tipo, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    document.querySelectorAll('#tabela-staff tbody tr.staff-row').forEach(row => {
        if(tipo === 'todos') {
            row.style.display = '';
        } else {
            row.style.display = row.classList.contains(tipo) ? '' : 'none';
        }
    });
}
// ========================================
// FORM VALIDATION
// ========================================
function validarForm() {
    let valido = true;
    let primeiroTabComErro = null;
    
    // Limpar erros anteriores
    document.querySelectorAll('.field-group input.error, .field-group select.error').forEach(el => {
        el.classList.remove('error');
    });
    document.querySelectorAll('.field-error').forEach(el => {
        el.style.display = 'none';
    });
    
    // Campos obrigatórios
    const campos = [
        {id: 'nome_staff', tab: 1, msg: 'Nome é obrigatório'},
        {id: 'email_staff', tab: 1, msg: 'Email é obrigatório'},
        {id: 'cargo_staff', tab: 1, msg: 'Seleccione um cargo'}
    ];
    
    campos.forEach(function(c) {
        const el = document.getElementById(c.id);
        if(!el || !el.value.trim()) {
            el.classList.add('error');
            // Mostrar mensagem de erro se existir span.field-error
            const errSpan = el.parentElement.querySelector('.field-error');
            if(errSpan) {
                errSpan.textContent = c.msg;
                errSpan.style.display = 'block';
            }
            valido = false;
            if(!primeiroTabComErro) primeiroTabComErro = c.tab;
        }
    });
    
    // Validar formato de email
    const emailField = document.getElementById('email_staff');
    if(emailField && emailField.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailField.value.trim())) {
        emailField.classList.add('error');
        valido = false;
        if(!primeiroTabComErro) primeiroTabComErro = 1;
    }
    
    // Validar NUIT (9 dígitos, se preenchido)
    const nuitField = document.getElementById('nuit');
    if(nuitField && nuitField.value.trim() && !/^\d{9}$/.test(nuitField.value.trim().replace(/\D/g, ''))) {
        nuitField.classList.add('error');
        valido = false;
        if(!primeiroTabComErro) primeiroTabComErro = 1;
    }
    
    // Navegar para o tab com erro
    if(!valido && primeiroTabComErro) {
        activarTab(primeiroTabComErro);
        // Shake animation no primeiro campo com erro
        const primeiroErro = document.querySelector('.field-group input.error, .field-group select.error');
        if(primeiroErro) {
            primeiroErro.focus();
            primeiroErro.style.animation = 'shake 0.4s ease';
            setTimeout(() => primeiroErro.style.animation = '', 400);
        }
        showToast('Campos obrigatórios', 'Preencha todos os campos marcados com *', 'warning');
    }
    
    return valido;
}
// Limpar erro ao digitar
document.querySelectorAll('#form-staff input, #form-staff select').forEach(function(el) {
    el.addEventListener('input', function() {
        this.classList.remove('error');
        const errSpan = this.parentElement.querySelector('.field-error');
        if(errSpan) errSpan.style.display = 'none';
    });
});
// ========================================
// FORM SUBMIT
// ========================================
function guardarStaff(e) {
    e.preventDefault();
    
    if(!validarForm()) return;
    
    const btn = document.getElementById('btn-submit');
    btn.classList.add('loading');
    btn.disabled = true;
    
    const formData = new FormData(document.getElementById('form-staff'));
    formData.append('action', 'sige_salvar_funcionario');
    formData.append('_sige_nonce', sigeEquipeAjax.nonce);
    
    // Adicionar dados bancários como JSON
    formData.append('dados_bancarios', JSON.stringify({
        banco_nome: document.getElementById('banco_nome').value,
        nib: document.getElementById('banco_nib').value,
        mpesa: document.getElementById('banco_mpesa').value
    }));
    
    // Adicionar documentos como JSON e manter campos individuais para compatibilidade segura.
    formData.append('documentos_urls', JSON.stringify({
        doc_bi: document.getElementById('doc_bi').value,
        doc_cv: document.getElementById('doc_cv').value,
        doc_cert: document.getElementById('doc_cert').value
    }));
    formData.set('banco_nib', document.getElementById('banco_nib').value || '');
    formData.set('banco_mpesa', document.getElementById('banco_mpesa').value || '');
    formData.set('doc_bi', document.getElementById('doc_bi').value || '');
    formData.set('doc_cv', document.getElementById('doc_cv').value || '');
    formData.set('doc_cert', document.getElementById('doc_cert').value || '');
    
    jQuery.post(sigeEquipeAjax.ajaxurl, Object.fromEntries(formData), function(res) {
        btn.classList.remove('loading');
        btn.disabled = false;
        
        if(res.success) {
            showToast('Sucesso', 'Colaborador guardado com sucesso!', 'success');
            fecharForm();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Erro', res.data || 'Falha ao guardar', 'error');
        }
    }).fail(function() {
        btn.classList.remove('loading');
        btn.disabled = false;
        showToast('Erro', sigeEquipeAjaxFailMessage(arguments[0], 'Erro de comunicação com o servidor'), 'error');
    });
}
// ========================================
// UPLOAD FOTO
// ========================================
function uploadFoto() {
    if(typeof wp !== 'undefined' && wp.media) {
        const frame = wp.media({
            title: 'Seleccionar Foto',
            button: { text: 'Usar esta foto' },
            multiple: false,
            library: { type: 'image' }
        });
        
        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            document.getElementById('foto_perfil').value = attachment.url;
            document.getElementById('preview_foto').src = attachment.url;
        });
        
        frame.open();
    } else {
        showToast('Atenção', 'Media Library não disponível', 'warning');
    }
}
function uploadDoc(fieldId) {
    if(typeof wp !== 'undefined' && wp.media) {
        const frame = wp.media({
            title: 'Seleccionar documento',
            button: { text: 'Usar este ficheiro' },
            multiple: false,
            library: { type: ['application/pdf', 'image', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'] }
        });
        
        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            const input = document.getElementById(fieldId);
            if (input) {
                input.value = attachment.url;
            }
            actualizarEstadoUploadDocs();
            showToast('Documento carregado', 'O ficheiro foi associado ao colaborador. Clique em Guardar para concluir.', 'success');
        });
        
        frame.open();
    } else {
        showToast('Atenção', 'Biblioteca de ficheiros não disponível', 'warning');
    }
}
// ========================================
// TOGGLE STATUS
// ========================================
function toggleStatus(userId, statusAtual, email) {
    const novoStatus = statusAtual ? 0 : 1;
    const acao = novoStatus ? 'Activar' : 'Desactivar';
    const detalhe = novoStatus ? 'O colaborador voltará a aparecer como activo no sistema.' : 'O colaborador ficará inactivo, mantendo o histórico preservado.';

    sigeConfirm({
        title: acao + ' colaborador?',
        message: 'Confirme se pretende ' + acao.toLowerCase() + ' este colaborador.',
        detail: detalhe,
        okText: acao,
        cancelText: 'Voltar'
    }).then(function(ok){
        if(!ok) return;

        const btn = document.getElementById('btn-toggle-' + userId);
        const badgeEl = document.getElementById('status-badge-' + userId);
        const row = btn ? btn.closest('tr') : null;

        if(badgeEl) {
            badgeEl.className = 'status-badge ' + (novoStatus ? 'active' : 'inactive');
            badgeEl.innerHTML = '<span class="status-dot"></span> ' + (novoStatus ? 'Activo' : 'Inactivo');
        }

        if(row) row.classList.toggle('inactive-row', !novoStatus);
        if(btn) btn.disabled = true;

        jQuery.post(sigeEquipeAjax.ajaxurl, {
            action: 'sige_toggle_status_staff',
            user_id: userId,
            email: email,
            status_ativo: novoStatus,
            _sige_nonce: sigeEquipeAjax.nonce
        }, function(res) {
            if(btn) btn.disabled = false;

            if(res.success) {
                showToast('Sucesso', `Colaborador ${novoStatus ? 'activado' : 'desactivado'}`, 'success');
                if(btn) {
                    btn.className = 'btn-action ' + (novoStatus ? 'btn-toggle-off' : 'btn-toggle-on');
                    btn.setAttribute('data-tooltip', novoStatus ? 'Desactivar' : 'Activar');
                    btn.removeAttribute('onclick');
                    btn.setAttribute('data-sige-act', 'toggleStatus');
                    btn.setAttribute('data-sige-args', JSON.stringify([userId, novoStatus, email]));
                    btn.innerHTML = novoStatus
                        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
                        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
                }
            } else {
                if(badgeEl) {
                    badgeEl.className = 'status-badge ' + (statusAtual ? 'active' : 'inactive');
                    badgeEl.innerHTML = '<span class="status-dot"></span> ' + (statusAtual ? 'Activo' : 'Inactivo');
                }
                if(row) row.classList.toggle('inactive-row', !statusAtual);
                showToast('Erro', res.data || 'Falha na operação', 'error');
            }
        }).fail(function() {
            if(btn) btn.disabled = false;
            showToast('Erro', sigeEquipeAjaxFailMessage(arguments[0], 'Erro de comunicação com o servidor'), 'error');
        });
    });
}
// ========================================
// REMOVER FUNCIONÁRIO
// ========================================
function removerUser(id, nome) {
    sigeConfirm({
        title: 'Remover colaborador da equipa?',
        message: 'Esta acção revoga o acesso e oculta o colaborador da equipa, preservando o histórico no sistema.',
        detail: nome || 'Colaborador seleccionado',
        okText: 'Remover',
        cancelText: 'Voltar'
    }).then(function(ok){
        if(!ok) return;
        jQuery.post(sigeEquipeAjax.ajaxurl, {
            action: 'sige_remover_usuario_staff',
            id: id,
            _sige_nonce: sigeEquipeAjax.nonce
        }, function(res) {
            if(res.success) {
                showToast('Sucesso', res.data || 'Colaborador removido da equipa com histórico preservado.', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Erro', res.data || 'Falha ao remover', 'error');
            }
        }).fail(function(){
            showToast('Erro', sigeEquipeAjaxFailMessage(arguments[0], 'Erro de comunicação com o servidor'), 'error');
        });
    });
}
// ========================================
// RESET SENHA
// ========================================
function resetSenha(id) {
    sigeConfirm({
        title: 'Enviar link seguro?',
        message: 'Confirme apenas se pretende enviar um link único para o colaborador definir nova senha.',
        detail: 'Por segurança, a senha não será mostrada no painel nem copiada para a área de transferência.',
        okText: 'Enviar link',
        cancelText: 'Voltar'
    }).then(function(ok){
        if(!ok) return;
        showToast('Aguarde', 'A preparar link seguro...', 'info');

        jQuery.post(sigeEquipeAjax.ajaxurl, {
            action: 'sige_resetar_senha',
            id: id,
            _sige_nonce: sigeEquipeAjax.nonce
        }, function(res) {
            if(res.success) {
                showToast('Link seguro enviado', res.data, 'success');
            } else {
                showToast('Erro', res.data || 'Falha ao gerar senha', 'error');
            }
        }).fail(function(xhr, status, error) {
            console.error('Reset senha erro:', xhr, status, error);
            showToast('Erro', sigeEquipeAjaxFailMessage(xhr, 'Erro de comunicação com o servidor'), 'error');
        });
    });
}
function sgRhEsc(value) {
    return sigeEquipeEscapeHtml(value);
}
function sgRhSafeUrl(value, fallback) {
    fallback = fallback || '';
    try {
        const url = new URL(String(value || fallback), window.location.origin);
        if (!['http:', 'https:'].includes(url.protocol)) return fallback;
        return url.href.replace(/"/g, '%22').replace(/</g, '').replace(/>/g, '');
    } catch(e) {
        return fallback;
    }
}

// ========================================
// GERAR CRACHÁ INDIVIDUAL
// ========================================
// ========================================
// CRACHÁ DA EQUIPA - usa o registo de modelos (SigeCrachaStaffTemplates),
// partilhado com a pré-visualização do seletor. Conjunto de modelos próprio.
// ========================================
function sgRhLiveNonce() {
    try { var e = document.querySelector('script[nonce]') || document.querySelector('style[nonce]'); if (e && e.nonce) return e.nonce; } catch (x) {}
    return (window.sigeEquipeAjax && sigeEquipeAjax.csp_nonce) ? sigeEquipeAjax.csp_nonce : '';
}
function sgRhEnsureTemplates(cb) {
    if (window.SigeCrachaStaffTemplates) { cb(true); return; }
    var url = (window.sigeEquipeAjax && sigeEquipeAjax.cracha_asset) ? sigeEquipeAjax.cracha_asset : '';
    if (!url) { cb(false); return; }
    if (window.__sigeCrachaLoading) {
        var iv = setInterval(function () { if (window.SigeCrachaStaffTemplates) { clearInterval(iv); cb(true); } }, 80);
        setTimeout(function () { clearInterval(iv); cb(!!window.SigeCrachaStaffTemplates); }, 4000);
        return;
    }
    window.__sigeCrachaLoading = true;
    var s = document.createElement('script'); s.src = url; var n = sgRhLiveNonce(); if (n) s.setAttribute('nonce', n);
    s.onload = function () { cb(!!window.SigeCrachaStaffTemplates); };
    s.onerror = function () { window.__sigeCrachaLoading = false; cb(false); };
    document.head.appendChild(s);
}
function sgRhCrachaCtx(data) {
    var cfg = (window.sigeEquipeAjax && sigeEquipeAjax.cracha && sigeEquipeAjax.cracha.config) ? sigeEquipeAjax.cracha.config : {};
    return {
        escolaNome: (data && data.escola) ? data.escola : '',
        logoUrl: sgRhSafeUrl((data && data.logo) || '', sigeEquipeAjax.avatarUrl),
        template: cfg.template || 'corporate',
        accent: cfg.accent || '#1e3a8a',
        showSocial: !!cfg.show_social,
        social: cfg.social || {},
        batch: false,
        nonce: sgRhLiveNonce()
    };
}
function sgRhCrachaItem(data) {
    if (!data) data = {};
    return {
        nome: data.nome || '',
        cargo: data.cargo || 'Funcionário',
        validade: data.validade || '---',
        foto: sgRhSafeUrl(data.foto || '', sigeEquipeAjax.avatarUrl)
    };
}
function sgRhPrintWindow(w, html) {
    if (!w) { showToast('Pop-up bloqueado', 'Permita pop-ups para imprimir crachás.', 'warning'); return; }
    try { w.document.open(); w.document.write(html); w.document.close(); }
    catch (e) { showToast('Erro', 'Não foi possível preparar a impressão.', 'error'); return; }
    var printed = false; function go() { if (printed) return; printed = true; try { w.focus(); w.print(); } catch (e) {} }
    var imgs = []; try { imgs = Array.prototype.slice.call(w.document.images || []); } catch (e) {}
    var pend = imgs.filter(function (im) { return !im.complete; });
    if (!pend.length) { setTimeout(go, 200); return; }
    var left = pend.length;
    pend.forEach(function (im) { im.addEventListener('load', function () { if (--left <= 0) go(); }); im.addEventListener('error', function () { if (--left <= 0) go(); }); });
    setTimeout(go, 6000);
}
function sgRhFallbackDoc(list, ctx) {
    var n = ctx && ctx.nonce ? ' nonce="' + sgRhEsc(ctx.nonce) + '"' : '';
    var body = '';
    for (var i = 0; i < list.length; i++) { var a = list[i] || {}; body += '<div class="c"><div class="hd">' + sgRhEsc(ctx.escolaNome || '') + '</div><div class="nm">' + sgRhEsc(a.nome || '') + '</div><div class="mt">' + sgRhEsc(a.cargo || 'Funcionário') + '</div><div class="vl">Válido até: ' + sgRhEsc(a.validade || '---') + '</div></div>'; }
    return '<!doctype html><html><head><meta charset="utf-8"><title>Crachá</title><style' + n + '>body{font-family:Segoe UI,Arial,sans-serif;margin:0;padding:14px;display:flex;flex-wrap:wrap;gap:14px;color:#475569}.c{width:240px;height:384px;border:1px solid #cbd5e1;border-radius:14px;padding:18px;box-sizing:border-box;display:flex;flex-direction:column;align-items:center;text-align:center}.hd{font-size:9px;font-weight:700;text-transform:uppercase}.nm{margin-top:90px;font-size:15px;font-weight:800;color:#0f172a}.mt{margin-top:8px;font-size:11px}.vl{margin-top:auto;font-size:10px}</style></head><body>' + body + '</body></html>';
}
function sgRhBuildStaffDoc(list, ctx) {
    if (window.SigeCrachaStaffTemplates && typeof window.SigeCrachaStaffTemplates.buildDocument === 'function') { return window.SigeCrachaStaffTemplates.buildDocument(list, ctx); }
    return sgRhFallbackDoc(list, ctx);
}

function gerarCracha(data) {
    if (!data) data = {};
    var ctx = sgRhCrachaCtx(data); ctx.batch = false;
    var item = sgRhCrachaItem(data);
    var w = window.open('', '', 'width=400,height=650');
    if (!w) { showToast('Pop-up bloqueado', 'Permita pop-ups para imprimir crachás.', 'warning'); return; }
    try { w.document.write('<!doctype html><meta charset="utf-8"><title>A preparar…</title><body style="font:14px sans-serif;padding:20px">A preparar o crachá…</body>'); } catch (e) {}
    sgRhEnsureTemplates(function () { sgRhPrintWindow(w, sgRhBuildStaffDoc([item], ctx)); });
}
// ========================================
// IMPRESSÃO EM LOTE
// ========================================
function imprimirLote() {
    var rows = document.querySelectorAll("#tabela-staff tbody tr.staff-row");
    var items = [], ctx = null;
    for (var i = 0; i < rows.length; i++) {
        if (rows[i].style.display !== 'none') {
            var raw = rows[i].getAttribute('data-cracha');
            if (raw) { var data; try { data = JSON.parse(raw); } catch (e) { continue; } if (!ctx) ctx = sgRhCrachaCtx(data); items.push(sgRhCrachaItem(data)); }
        }
    }
    if (items.length === 0) { showToast('Atenção', 'Nenhum funcionário visível para impressão', 'warning'); return; }
    ctx.batch = true;
    var w = window.open('', '', 'width=1000,height=800');
    if (!w) { showToast('Pop-up bloqueado', 'Permita pop-ups para imprimir crachás.', 'warning'); return; }
    try { w.document.write('<!doctype html><meta charset="utf-8"><title>A preparar…</title><body style="font:14px sans-serif;padding:20px">A preparar ' + items.length + ' crachás…</body>'); } catch (e) {}
    sgRhEnsureTemplates(function () { sgRhPrintWindow(w, sgRhBuildStaffDoc(items, ctx)); showToast('Impressão', items.length + ' crachás preparados', 'info'); });
}
// ========================================
// EXPORTAR FOLHA SALARIAL
// ========================================
async function exportarFolhaSalario() {
    const btn = document.querySelector('[data-sige-act="exportarFolhaSalario"]');
    const txtOrig = btn.innerHTML;
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> A gerar...';
    btn.disabled = true;
    
    try {
        const nomeEscola = <?php echo json_encode($escola->nome_escola ?: 'ESCOLA'); ?>;
        const anoLectivo = <?php echo $escola->ano_lectivo ?: wp_date('Y'); ?>;
        const hoje = new Date();
        const meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        const mesAtual = meses[hoje.getMonth()] + ' ' + hoje.getFullYear();
        const dataEmissao = hoje.toLocaleDateString('pt-PT');
        const visibleEmails = new Set(Array.from(document.querySelectorAll('#tabela-staff tbody tr.staff-row'))
            .filter(row => row.style.display !== 'none')
            .map(row => ((row.querySelector('.staff-email') || {innerText:''}).innerText || '').trim().toLowerCase())
            .filter(Boolean));

        const exportRes = await new Promise(function(resolve, reject) {
            jQuery.post(sigeEquipeAjax.ajaxurl, {
                action: 'sige_exportar_folha_staff',
                _sige_nonce: sigeEquipeAjax.nonce
            }).done(resolve).fail(reject);
        });
        if(!exportRes || !exportRes.success) {
            throw new Error((exportRes && exportRes.data) ? exportRes.data : 'Sem permissão ou dados indisponíveis');
        }
        const dados = (exportRes.data.rows || []).filter(function(row) {
            if (!visibleEmails.size) return true;
            return visibleEmails.has(String(row.email || '').toLowerCase());
        });
        
        const wb = new ExcelJS.Workbook();
        wb.creator = 'SIGE SoftGenial';
        wb.created = hoje;
        const ws = wb.addWorksheet('Folha de Salários', {
            pageSetup: { paperSize: 9, orientation: 'landscape', fitToPage: true, fitToWidth: 1 }
        });
        
        const AZUL   = 'FF1A237E';
        const AZULM  = 'FF3F51B5';
        const VERDE  = 'FF2E7D32';
        const CINZA  = 'FFE8EAF6';
        const BRANCO = 'FFFFFFFF';
        const LINHA2 = 'FFF5F7FF';
        
        ws.columns = [
            {width:5},{width:32},{width:16},{width:14},
            {width:16},{width:22},{width:16},
            {width:17},{width:17},{width:19},{width:28}
        ];
        
        // Linha 1 - Nome da escola
        ws.mergeCells('A1:K1');
        Object.assign(ws.getCell('A1'), {
            value: nomeEscola.toUpperCase(),
            font: {name:'Calibri', bold:true, size:16, color:{argb:AZUL}},
            alignment: {horizontal:'center', vertical:'middle'}
        });
        ws.getRow(1).height = 36;
        
        // Linha 2 - Título colorido
        ws.mergeCells('A2:K2');
        Object.assign(ws.getCell('A2'), {
            value: 'FOLHA DE SALÁRIOS  ·  ' + mesAtual.toUpperCase() + '  ·  ANO LECTIVO ' + anoLectivo,
            fill: {type:'pattern', pattern:'solid', fgColor:{argb:AZUL}},
            font: {name:'Calibri', bold:true, size:13, color:{argb:BRANCO}},
            alignment: {horizontal:'center', vertical:'middle'}
        });
        ws.getRow(2).height = 30;
        
        // Linha 3 - Metadados
        ws.mergeCells('A3:F3');
        Object.assign(ws.getCell('A3'), {
            value: 'Data de emissão: ' + dataEmissao + '   |   Processado por SIGE SoftGenial',
            font: {name:'Calibri', italic:true, size:9, color:{argb:'FF666666'}},
            alignment: {horizontal:'left', vertical:'middle'}
        });
        ws.mergeCells('G3:K3');
        Object.assign(ws.getCell('G3'), {
            value: 'Total de funcionários: ' + dados.length,
            font: {name:'Calibri', bold:true, size:9, color:{argb:'FF1A237E'}},
            alignment: {horizontal:'right', vertical:'middle'}
        });
        ws.getRow(3).height = 16;
        ws.getRow(4).height = 8;
        
        // Linha 5 - Cabeçalhos
        const headers = ['#','Nome Completo','Cargo / Perfil','NUIT','Banco','NIB / Nº Conta','M-Pesa / E-Mola','Salário Base','Subsídios','Total a Pagar','Assinatura'];
        const thRow = ws.getRow(5);
        thRow.height = 24;
        headers.forEach(function(h, i) {
            const cell = thRow.getCell(i+1);
            cell.value = h;
            cell.fill = {type:'pattern', pattern:'solid', fgColor:{argb:AZULM}};
            cell.font = {name:'Calibri', bold:true, size:10, color:{argb:BRANCO}};
            cell.alignment = {horizontal: i >= 7 && i <= 9 ? 'right' : (i===0 ? 'center' : 'left'), vertical:'middle'};
            cell.border = {top:{style:'thin',color:{argb:AZUL}},left:{style:'thin',color:{argb:AZUL}},bottom:{style:'thin',color:{argb:AZUL}},right:{style:'thin',color:{argb:AZUL}}};
        });
        
        // Dados
        let totBase = 0, totSub = 0, totGeral = 0;
        dados.forEach(function(d, idx) {
            const rn = 6 + idx;
            const dr = ws.getRow(rn);
            dr.height = 20;
            const bg = idx % 2 === 0 ? BRANCO : LINHA2;
            const vals = [idx+1, d.nome, d.cargo, d.nuit, d.banco, d.nib, d.mpesa, d.base, d.sub, d.total, ''];
            vals.forEach(function(v, i) {
                const cell = dr.getCell(i+1);
                cell.value = v;
                cell.fill = {type:'pattern', pattern:'solid', fgColor:{argb:bg}};
                cell.font = {name:'Calibri', size:10};
                const isNum = i >= 7 && i <= 9;
                cell.alignment = {horizontal: i===0 ? 'center' : isNum ? 'right' : 'left', vertical:'middle'};
                if (isNum) cell.numFmt = '#,##0.00 "' + sigeEquipeAjax.moeda + '"';
                cell.border = {top:{style:'hair',color:{argb:'FFDDDDDD'}},left:{style:'hair',color:{argb:'FFDDDDDD'}},bottom:{style:'hair',color:{argb:'FFDDDDDD'}},right:{style:'hair',color:{argb:'FFDDDDDD'}}};
            });
            // Linha de assinatura
            const ac = dr.getCell(11);
            ac.border = {bottom:{style:'thin',color:{argb:'FF333333'}},left:{style:'hair',color:{argb:'FFDDDDDD'}},right:{style:'hair',color:{argb:'FFDDDDDD'}}};
            totBase += d.base; totSub += d.sub; totGeral += d.total;
        });
        
        // Linha de totais
        const tr2 = ws.getRow(6 + dados.length);
        tr2.height = 26;
        ws.mergeCells('A'+(6+dados.length)+':G'+(6+dados.length));
        const tlbl = tr2.getCell(1);
        tlbl.value = 'TOTAL GERAL DA FOLHA';
        tlbl.fill = {type:'pattern',pattern:'solid',fgColor:{argb:AZUL}};
        tlbl.font = {name:'Calibri',bold:true,size:11,color:{argb:BRANCO}};
        tlbl.alignment = {horizontal:'right',vertical:'middle'};
        
        [[8,totBase],[9,totSub],[10,totGeral]].forEach(function(pair) {
            const tc = tr2.getCell(pair[0]);
            tc.value = pair[1];
            tc.fill  = {type:'pattern',pattern:'solid',fgColor:{argb:AZUL}};
            tc.font  = {name:'Calibri',bold:true,size:11,color:{argb:BRANCO}};
            tc.numFmt = '#,##0.00 "' + sigeEquipeAjax.moeda + '"';
            tc.alignment = {horizontal:'right',vertical:'middle'};
        });
        tr2.getCell(11).fill = {type:'pattern',pattern:'solid',fgColor:{argb:AZUL}};
        
        // Espaço + caixa resumo
        const rS = 6 + dados.length + 2;
        ws.getRow(rS-1).height = 12;
        ws.mergeCells('A'+rS+':E'+(rS+2));
        const sb = ws.getCell('A'+rS);
        sb.value = 'RESUMO\nTotal bruto: ' + totGeral.toLocaleString('pt-PT',{minimumFractionDigits:2}) + ' ' + sigeEquipeAjax.moeda + '  |  Nº de Trabalhadores: ' + dados.length + '\nRef.: ' + mesAtual;
        sb.fill = {type:'pattern',pattern:'solid',fgColor:{argb:CINZA}};
        sb.font = {name:'Calibri',bold:true,size:10,color:{argb:'FF1A237E'}};
        sb.alignment = {horizontal:'left',vertical:'middle',wrapText:true};
        sb.border = {top:{style:'medium',color:{argb:AZUL}},left:{style:'medium',color:{argb:AZUL}},bottom:{style:'medium',color:{argb:AZUL}},right:{style:'medium',color:{argb:AZUL}}};
        [rS,rS+1,rS+2].forEach(function(r){ws.getRow(r).height=18;});
        
        // Assinaturas
        const rA = rS + 4;
        [['B','C','O Responsável Financeiro'],['E','F','O Director da Escola'],['H','I','O Chefe da Secretaria']].forEach(function(s) {
            ws.mergeCells(s[0]+(rA+1)+':'+s[1]+(rA+1));
            const lc = ws.getCell(s[0]+(rA+1));
            lc.border = {bottom:{style:'medium',color:{argb:'FF333333'}}};
            ws.mergeCells(s[0]+(rA+2)+':'+s[1]+(rA+2));
            const nc = ws.getCell(s[0]+(rA+2));
            nc.value = s[2];
            nc.font = {name:'Calibri',bold:true,size:10,color:{argb:'FF1A237E'}};
            nc.alignment = {horizontal:'center'};
        });
        [rA,rA+1,rA+2].forEach(function(r){ws.getRow(r).height=22;});
        
        ws.headerFooter.oddFooter = '&L&8SIGE SoftGenial - ' + dataEmissao + '&C&8Folha de Salários - CONFIDENCIAL&R&8Pág. &P / &N';
        
        const buffer = await wb.xlsx.writeBuffer();
        const blob = new Blob([buffer], {type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'Folha_Salarios_' + nomeEscola.replace(/\s+/g,'_') + '_' + hoje.toISOString().slice(0,7) + '.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(a.href);
        
        showToast('Sucesso', 'Folha salarial exportada!', 'success');
        btn.innerHTML = txtOrig;
        btn.disabled = false;
        
    } catch(err) {
        console.error(err);
        showToast('Erro', 'Falha ao gerar ficheiro: ' + err.message, 'error');
        btn.innerHTML = txtOrig;
        btn.disabled = false;
    }
}
// ========================================
// CLOSE MODAL ON ESC / CLICK OUTSIDE
// ========================================
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    // Fecha o modal aberto mais relevante (a ficha tem prioridade se estiver aberta).
    var ficha = document.getElementById('box-ficha');
    if (ficha && ficha.classList.contains('active')) { fecharFicha(); return; }
    fecharForm();
});
document.getElementById('box-equipa').addEventListener('click', e => {
    if(e.target.id === 'box-equipa') fecharForm();
});
// [v12.36.1] Fechar a ficha ao clicar fora (mesmo padrão dos outros modais).
(function () {
    var ficha = document.getElementById('box-ficha');
    if (ficha) { ficha.addEventListener('click', function (e) { if (e.target === ficha) fecharFicha(); }); }
})();

// [v12.37.1] Auto-recuperação anti-"congelamento". Se um handler falhar a meio,
// um modal podia ficar visível/sem estado a capturar TODOS os cliques, parecendo
// a página congelada até dar refresh. Em fase de CAPTURA (corre antes de qualquer
// overlay), se NENHUM modal está legitimamente aberto (sem .active e sem .is-open),
// fechamos overlays órfãos e libertamos o body. É inócuo no funcionamento normal
// (quando há um modal aberto, sai logo).
document.addEventListener('click', function () {
    if (document.querySelector('.sige-modal.active, .is-open')) return;
    // Nenhum modal aberto: reconciliar quaisquer modais sem .active que tenham
    // ficado com aria-hidden dessincronizado e garantir que estão escondidos.
    var orfaos = document.querySelectorAll('.sige-modal:not(.active)[aria-hidden="false"]');
    if (orfaos.length) {
        Array.prototype.forEach.call(orfaos, function (m) {
            m.setAttribute('aria-hidden', 'true');
            m.style.display = 'none';
            m.style.opacity = '0';
            m.style.visibility = 'hidden';
            m.style.pointerEvents = 'none';
        });
    }
    if (document.body.classList.contains('sige-rh-modal-open')) {
        document.body.classList.remove('sige-rh-modal-open');
    }
    if (document.body.style.overflow === 'hidden') {
        document.body.style.overflow = '';
    }
}, true);

// v12.11.9.5 - Failsafe: tornar funções explicitamente globais para onclick inline e fluxos do App Shell.
// ========================================
// [v12.36.0] FICHA DO COLABORADOR (perfil 360, só leitura)
// Reutiliza o endpoint autorizado sige_get_staff_secure (mesma fonte do editar),
// respeitando a minimização de dados: nada sensível fica no DOM da lista.
// ========================================
function sgFichaEsc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
function sgFichaNum(n) { n = Math.round(parseFloat(n) || 0); return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
function sgFichaMoney(n) { return sgFichaNum(n) + ' ' + ((window.sigeEquipeAjax && sigeEquipeAjax.moeda) || 'MT'); }
function sgFichaTipoLabel(t) { var m = { efectivo: 'Efectivo (Quadro)', contrato: 'Contrato a Prazo', estagio: 'Estagiário' }; t = String(t || '').trim(); return t ? (m[t] || (t.charAt(0).toUpperCase() + t.slice(1))) : 'Vínculo não definido'; }
function sgFichaPretty(v) { v = String(v || '').trim(); if (!v || v === '0') return ''; return v.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }); }
function sgFichaDateBR(s) { s = String(s || '').trim(); if (!s || s === '0000-00-00') return ''; var p = s.split('-'); return p.length === 3 ? (p[2] + '/' + p[1] + '/' + p[0]) : s; }
function sgFichaAntiguidade(adm) { adm = String(adm || '').trim(); if (!adm || adm === '0000-00-00') return ''; var d = new Date(adm + 'T00:00:00'); if (isNaN(d.getTime())) return ''; var anos = (Date.now() - d.getTime()) / (365.25 * 864e5); if (anos < 0) return ''; return (Math.round(anos * 10) / 10).toString().replace('.', ',') + ' anos'; }
function sgFichaContrato(fim) {
    fim = String(fim || '').trim();
    if (!fim || fim === '0000-00-00') return null;
    var d = new Date(fim + 'T00:00:00'); if (isNaN(d.getTime())) return null;
    var now = new Date(); now.setHours(0, 0, 0, 0);
    var dias = Math.floor((d.getTime() - now.getTime()) / 864e5);
    var estado, frase;
    if (dias < 0) { estado = 'expirado'; frase = (dias === -1 ? 'expirou ontem' : 'expirou há ' + Math.abs(dias) + ' dias'); }
    else if (dias === 0) { estado = 'critico'; frase = 'expira hoje'; }
    else if (dias === 1) { estado = 'critico'; frase = 'expira amanhã'; }
    else if (dias <= 30) { estado = 'critico'; frase = 'faltam ' + dias + ' dias'; }
    else if (dias <= 90) { estado = 'aviso'; frase = 'faltam ' + dias + ' dias'; }
    else { estado = 'ok'; frase = 'faltam ' + dias + ' dias'; }
    return { estado: estado, frase: frase };
}
function sgFichaField(k, v) { var empty = (v == null || String(v).trim() === ''); return '<div class="sg-ficha-field"><span class="k">' + sgFichaEsc(k) + '</span><span class="v' + (empty ? ' is-empty' : '') + '">' + (empty ? 'Não informado' : sgFichaEsc(v)) + '</span></div>'; }
function sgFichaDoc(label, url) {
    var ic = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
    if (url) { return '<a class="sg-ficha-doc" href="' + sgFichaEsc(url) + '" target="_blank" rel="noopener">' + ic + sgFichaEsc(label) + '</a>'; }
    return '<span class="sg-ficha-doc is-missing">' + ic + sgFichaEsc(label) + ' (em falta)</span>';
}
function sgFichaRender(d, blob) {
    d = d || {}; blob = blob || {};
    var nome = d.nome || blob.nome || 'Colaborador';
    var foto = d.foto_perfil || (window.sigeEquipeAjax && sigeEquipeAjax.avatarUrl) || '';
    var activo = parseInt(d.status_ativo != null ? d.status_ativo : (blob.status_ativo != null ? blob.status_ativo : 1), 10) !== 0;
    var banco = d.banco || {}; var docs = d.docs || {}; var docsSec = d.docs_secure || {};
    var docUrl = function (k) { return (docsSec && docsSec[k]) ? docsSec[k] : (docs[k] || ''); };

    var html = '';
    // Cabeçalho de identidade.
    html += '<div class="sg-ficha-head">';
    html += '<img class="sg-ficha-photo" src="' + sgFichaEsc(foto) + '" alt="' + sgFichaEsc(nome) + '">';
    html += '<div class="sg-ficha-idwrap">';
    html += '<p class="sg-ficha-name">' + sgFichaEsc(nome) + '</p>';
    html += '<p class="sg-ficha-email">' + sgFichaEsc(d.email || blob.email || '') + '</p>';
    html += '<div class="sg-ficha-badges">';
    html += '<span class="sg-ficha-badge ' + (activo ? 'is-on' : 'is-off') + '"><span class="dot"></span>' + (activo ? 'Activo' : 'Inactivo') + '</span>';
    html += '<span class="sg-ficha-badge">' + sgFichaEsc(sgFichaTipoLabel(d.tipo_contrato)) + '</span>';
    html += '</div></div></div>';

    // Banner de estado do contrato.
    var ct = sgFichaContrato(d.fim_contrato);
    if (ct) {
        var okIc = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>';
        var alIc = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
        html += '<div class="sg-ficha-alert is-' + ct.estado + '">' + (ct.estado === 'ok' ? okIc : alIc) + '<span>Contrato ' + ct.frase + ' (termina ' + sgFichaEsc(sgFichaDateBR(d.fim_contrato)) + ').</span></div>';
    }

    html += '<div class="sg-ficha-sections">';
    // Identificação & contacto.
    html += '<div class="sg-ficha-sec"><h4><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Identificação &amp; contacto</h4>';
    html += sgFichaField('E-mail', d.email || blob.email);
    html += sgFichaField('Telemóvel', d.tel);
    html += sgFichaField('NUIT', d.nuit);
    html += sgFichaField('Formação académica', d.formacao);
    html += '</div>';
    // Vínculo & carreira.
    html += '<div class="sg-ficha-sec"><h4><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> Vínculo &amp; carreira</h4>';
    html += sgFichaField('Tipo de vínculo', sgFichaTipoLabel(d.tipo_contrato));
    html += sgFichaField('Regime de trabalho', sgFichaPretty(d.regime_trabalho));
    html += sgFichaField('Nível de carreira', sgFichaPretty(d.nivel_carreira));
    html += sgFichaField('Data de admissão', sgFichaDateBR(d.data_admissao));
    html += sgFichaField('Antiguidade', sgFichaAntiguidade(d.data_admissao));
    html += sgFichaField('Fim de contrato', sgFichaDateBR(d.fim_contrato) || (String(d.tipo_contrato || '') === 'efectivo' ? 'Sem termo' : ''));
    html += '</div>';
    // Remuneração (o endpoint só devolve a quem pode gerir; daí ser seguro aqui).
    var total = (parseFloat(d.salario_base) || 0) + (parseFloat(d.subsidio) || 0);
    html += '<div class="sg-ficha-sec"><h4><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg> Remuneração</h4>';
    html += sgFichaField('Salário base', sgFichaMoney(d.salario_base));
    html += sgFichaField('Subsídios', sgFichaMoney(d.subsidio));
    html += sgFichaField('Total mensal', sgFichaMoney(total));
    html += sgFichaField('Banco', banco.banco_nome);
    html += sgFichaField('NIB', banco.nib);
    html += sgFichaField('M-Pesa', banco.mpesa);
    html += '</div>';
    // Documentos.
    html += '<div class="sg-ficha-sec"><h4><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg> Documentos</h4>';
    html += '<div class="sg-ficha-docs">' + sgFichaDoc('BI', docUrl('doc_bi')) + sgFichaDoc('CV', docUrl('doc_cv')) + sgFichaDoc('Certificado', docUrl('doc_cert')) + '</div>';
    html += '</div>';
    // Completude da ficha.
    var campos = [d.tel, d.nuit, d.formacao, d.tipo_contrato, d.data_admissao, d.nivel_carreira, d.regime_trabalho, d.foto_perfil, (d.salario_base > 0 ? '1' : ''), docUrl('doc_bi')];
    var ok = campos.filter(function (x) { return x != null && String(x).trim() !== ''; }).length;
    var pct = Math.round(ok / campos.length * 100);
    var cls = pct >= 80 ? '' : (pct >= 50 ? ' is-warn' : ' is-bad');
    html += '<div class="sg-ficha-sec is-wide"><h4><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Completude da ficha</h4>';
    html += '<div class="sg-ficha-comp"><span class="sg-ficha-comp-track"><span class="sg-ficha-comp-fill' + cls + '" style="width:' + pct + '%"></span></span><span class="sg-ficha-comp-val">' + pct + '%</span></div>';
    html += '</div>';

    html += '</div>'; // /sections
    return html;
}
function verFichaColaborador(data) {
    data = data || {};
    var modal = document.getElementById('box-ficha');
    var box = document.getElementById('ficha-conteudo');
    if (!modal || !box) return;
    sgFichaUltima = null;
    box.innerHTML = '<div class="sg-ficha-loading">A carregar ficha segura…</div>';
    sigeEquipeOpenModal(modal);
    jQuery.post(sigeEquipeAjax.ajaxurl, { action: 'sige_get_staff_secure', id: data.id, _sige_nonce: sigeEquipeAjax.nonce }, function (res) {
        if (res && res.success) { sgFichaUltima = { d: res.data || {}, blob: data }; box.innerHTML = sgFichaRender(res.data || {}, data); }
        else { box.innerHTML = '<div class="sg-ficha-loading">' + sgFichaEsc((res && res.data) || 'Não foi possível carregar a ficha.') + '</div>'; }
    }).fail(function () {
        box.innerHTML = '<div class="sg-ficha-loading">' + sgFichaEsc(sigeEquipeAjaxFailMessage(arguments[0], 'Erro de comunicação ao carregar a ficha.')) + '</div>';
    });
}
function fecharFicha() { sgFichaUltima = null; sigeEquipeCloseModal(document.getElementById('box-ficha')); }

// [v12.37.0] Impressão / exportação (PDF) da ficha. Documento autónomo: lê os
// VALORES dos tokens já computados na página (respeita o tema da escola) e
// injecta-os no :root do documento - sem cores mágicas no .php e sem depender
// de HTTP para carregar CSS. Reutiliza sgRhPrintWindow (espera imagens + print).
var sgFichaUltima = null;
function sgFichaTokenVars() {
    var cs = getComputedStyle(document.documentElement);
    var nomes = [
        '--color-white', '--color-black', '--color-ink-50', '--color-ink-100', '--color-ink-200',
        '--color-slate-400', '--color-slate-500', '--color-slate-600', '--color-slate-700', '--color-slate-800',
        '--color-brand-50', '--color-brand-500', '--color-brand-600', '--color-brand-700',
        '--color-success-50', '--color-success-500', '--color-success-700',
        '--color-warning-50', '--color-warning-500', '--color-warning-700',
        '--color-danger-50', '--color-danger-500', '--color-danger-700',
        '--sg-theme-primary', '--sg-theme-primary-800', '--sg-theme-soft',
        '--radius-md', '--radius-lg', '--radius-pill'
    ];
    var out = '';
    nomes.forEach(function (n) { var v = cs.getPropertyValue(n); if (v && v.trim()) out += n + ':' + v.trim() + ';'; });
    return out;
}
function sgFichaPRow(k, v) { var e = (v == null || String(v).trim() === ''); return '<div class="row"><span class="k">' + sgFichaEsc(k) + '</span><span class="v">' + (e ? '—' : sgFichaEsc(v)) + '</span></div>'; }
function sgFichaBuildPrintDoc(d, blob) {
    d = d || {}; blob = blob || {};
    var nome = d.nome || blob.nome || 'Colaborador';
    var foto = d.foto_perfil || (window.sigeEquipeAjax && sigeEquipeAjax.avatarUrl) || '';
    var escola = (window.sigeEquipeAjax && sigeEquipeAjax.escola) || '';
    var activo = parseInt(d.status_ativo != null ? d.status_ativo : 1, 10) !== 0;
    var banco = d.banco || {}; var docs = d.docs || {};
    var total = (parseFloat(d.salario_base) || 0) + (parseFloat(d.subsidio) || 0);
    var ct = sgFichaContrato(d.fim_contrato);
    var docTxt = function (k) { return (docs[k] && String(docs[k]).trim() !== '') ? 'Presente' : 'Em falta'; };
    var hoje = new Date();
    var dataGer = hoje.toLocaleDateString('pt-PT') + ' ' + hoje.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
    var n = sgRhLiveNonce(); var natt = n ? ' nonce="' + sgFichaEsc(n) + '"' : '';

    var alertHtml = '';
    if (ct) { alertHtml = '<div class="alert ' + ct.estado + '">Contrato ' + sgFichaEsc(ct.frase) + ' (termina ' + sgFichaEsc(sgFichaDateBR(d.fim_contrato)) + ').</div>'; }

    var html = '<!doctype html><html lang="pt"><head><meta charset="utf-8"><title>Ficha - ' + sgFichaEsc(nome) + '</title>';
    html += '<style' + natt + '>';
    html += ':root{' + sgFichaTokenVars() + '}';
    html += '*{box-sizing:border-box}'
        + 'body{font-family:"Segoe UI",Arial,sans-serif;color:var(--color-slate-800);margin:0;padding:32px;background:var(--color-white);}'
        + '.doc{max-width:760px;margin:0 auto;}'
        + '.hd{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;border-bottom:3px solid var(--sg-theme-primary,var(--color-brand-600));padding-bottom:14px;margin-bottom:18px;}'
        + '.hd .t{font-size:21px;font-weight:800;color:var(--color-black);letter-spacing:-.02em;}'
        + '.hd .s{font-size:11px;color:var(--color-slate-500);margin-top:4px;}'
        + '.hd .esc{font-size:13px;font-weight:700;color:var(--sg-theme-primary,var(--color-brand-700));text-align:right;max-width:240px;}'
        + '.person{display:flex;gap:16px;align-items:center;margin-bottom:16px;}'
        + '.person img{width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid var(--color-ink-100);}'
        + '.person .nm{font-size:18px;font-weight:800;color:var(--color-black);}'
        + '.person .meta{font-size:12px;color:var(--color-slate-500);margin-top:3px;}'
        + '.badge{display:inline-block;font-size:10px;font-weight:700;padding:2px 9px;border-radius:var(--radius-pill);background:var(--color-ink-50);color:var(--color-slate-700);margin-right:6px;}'
        + '.badge.on{background:var(--color-success-50);color:var(--color-success-700);}'
        + '.alert{padding:8px 12px;border-radius:var(--radius-lg);font-size:12px;font-weight:600;margin-bottom:16px;}'
        + '.alert.ok{background:var(--color-success-50);color:var(--color-success-700);}'
        + '.alert.aviso{background:var(--color-warning-50);color:var(--color-warning-700);}'
        + '.alert.critico,.alert.expirado{background:var(--color-danger-50);color:var(--color-danger-700);}'
        + '.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}'
        + '.sec{border:1px solid var(--color-ink-100);border-radius:var(--radius-lg);padding:11px 14px;break-inside:avoid;}'
        + '.sec h3{margin:0 0 7px;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--color-slate-500);}'
        + '.row{display:flex;justify-content:space-between;gap:12px;padding:4px 0;border-bottom:1px solid var(--color-ink-50);font-size:12px;}'
        + '.row:last-child{border-bottom:0;}.row .k{color:var(--color-slate-500);}.row .v{font-weight:600;color:var(--color-slate-800);text-align:right;}'
        + '.ft{margin-top:22px;padding-top:10px;border-top:1px solid var(--color-ink-100);font-size:10px;color:var(--color-slate-400);display:flex;justify-content:space-between;}'
        + '@media print{body{padding:0;}@page{margin:15mm;}}';
    html += '</style></head><body><div class="doc">';
    html += '<div class="hd"><div><div class="t">Ficha do Colaborador</div><div class="s">Documento interno de Recursos Humanos</div></div><div class="esc">' + sgFichaEsc(escola) + '</div></div>';
    html += '<div class="person"><img src="' + sgFichaEsc(foto) + '" alt=""><div><div class="nm">' + sgFichaEsc(nome) + '</div><div class="meta"><span class="badge ' + (activo ? 'on' : '') + '">' + (activo ? 'Activo' : 'Inactivo') + '</span><span class="badge">' + sgFichaEsc(sgFichaTipoLabel(d.tipo_contrato)) + '</span></div></div></div>';
    html += alertHtml;
    html += '<div class="grid">';
    html += '<div class="sec"><h3>Identificação &amp; contacto</h3>' + sgFichaPRow('E-mail', d.email || blob.email) + sgFichaPRow('Telemóvel', d.tel) + sgFichaPRow('NUIT', d.nuit) + sgFichaPRow('Formação académica', d.formacao) + '</div>';
    html += '<div class="sec"><h3>Vínculo &amp; carreira</h3>' + sgFichaPRow('Tipo de vínculo', sgFichaTipoLabel(d.tipo_contrato)) + sgFichaPRow('Regime de trabalho', sgFichaPretty(d.regime_trabalho)) + sgFichaPRow('Nível de carreira', sgFichaPretty(d.nivel_carreira)) + sgFichaPRow('Data de admissão', sgFichaDateBR(d.data_admissao)) + sgFichaPRow('Antiguidade', sgFichaAntiguidade(d.data_admissao)) + sgFichaPRow('Fim de contrato', sgFichaDateBR(d.fim_contrato) || (String(d.tipo_contrato || '') === 'efectivo' ? 'Sem termo' : '')) + '</div>';
    html += '<div class="sec"><h3>Remuneração</h3>' + sgFichaPRow('Salário base', sgFichaMoney(d.salario_base)) + sgFichaPRow('Subsídios', sgFichaMoney(d.subsidio)) + sgFichaPRow('Total mensal', sgFichaMoney(total)) + sgFichaPRow('Banco', banco.banco_nome) + sgFichaPRow('NIB', banco.nib) + sgFichaPRow('M-Pesa', banco.mpesa) + '</div>';
    html += '<div class="sec"><h3>Documentos</h3>' + sgFichaPRow('BI', docTxt('doc_bi')) + sgFichaPRow('CV', docTxt('doc_cv')) + sgFichaPRow('Certificado', docTxt('doc_cert')) + '</div>';
    html += '</div>';
    html += '<div class="ft"><span>Gerado em ' + sgFichaEsc(dataGer) + '</span><span>Confidencial — uso interno</span></div>';
    html += '</div></body></html>';
    return html;
}
function imprimirFicha() {
    if (!sgFichaUltima) { showToast('Aguarde', 'A ficha ainda está a carregar.', 'info'); return; }
    var w = window.open('', '', 'width=820,height=900');
    if (!w) { showToast('Pop-up bloqueado', 'Permita pop-ups para imprimir/exportar a ficha.', 'warning'); return; }
    try { w.document.write('<!doctype html><meta charset="utf-8"><title>A preparar…</title><body style="font:14px sans-serif;padding:20px">A preparar a ficha…</body>'); } catch (e) {}
    sgRhPrintWindow(w, sgFichaBuildPrintDoc(sgFichaUltima.d, sgFichaUltima.blob));
}

// [v12.35.0] Abas RH (Equipa / Relatórios). Troca de painel sem recarregar,
// CSP-safe (despachada por data-sige-act). Sincroniza estado ARIA.
function sgRhSwitchTab(tab) {
    var wrap = document.querySelector('.sige-rh');
    if (!wrap) return;
    var target = String(tab || 'equipa');
    var panels = wrap.querySelectorAll('[data-rh-panel]');
    Array.prototype.forEach.call(panels, function (p) {
        var on = p.getAttribute('data-rh-panel') === target;
        p.hidden = !on;
        p.classList.toggle('is-active', on);
    });
    var tabs = wrap.querySelectorAll('.sg-rh-tab');
    Array.prototype.forEach.call(tabs, function (b) {
        var on = b.getAttribute('aria-controls') === 'sg-rh-panel-' + target;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
}

Object.assign(window, {
    novoFuncionario,
    fecharForm,
    editarStaff,
    guardarStaff,
    toggleStatus,
    removerUser,
    resetSenha,
    imprimirLote,
    gerarCracha,
    exportarFolhaSalario,
    filtrarTabela,
    limparPesquisa,
    mostrarArquivoRh,
    activarTab,
    sgRhSwitchTab,
    verFichaColaborador,
    fecharFicha,
    imprimirFicha,
    uploadFoto,
    uploadDoc
});

// v12.11.9.5 - Failsafe UX: valida no carregamento que os botões críticos têm handlers activos.
document.addEventListener('DOMContentLoaded', function() {
    const required = ['novoFuncionario','editarStaff','resetSenha','toggleStatus','removerUser'];
    const missing = required.filter(function(name){ return typeof window[name] !== 'function'; });
    if (missing.length && window.console) {
        console.error('SIGE Equipa: handlers indisponíveis:', missing.join(', '));
    }
});

</script>
<?php if (!empty($can_manage_equipe)): ?>
<style>
/* Modelo de Crachá da Equipa - estilos só com tokens (sem cores/raios mágicos). */
.sige-cracha-modal{position:fixed;inset:0;z-index:140000;display:none;align-items:center;justify-content:center;padding:var(--space-4);}
.sige-cracha-modal.is-open{display:flex;}
.sige-cracha-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.55);}
.sige-cracha-dialog{position:relative;background:var(--color-white);border-radius:var(--radius-xl);box-shadow:var(--shadow-lg);width:min(940px,96vw);max-height:92vh;display:flex;flex-direction:column;overflow:hidden;}
.sige-cracha-head{display:flex;align-items:center;justify-content:space-between;gap:var(--space-4);padding:var(--space-5) var(--space-6);border-bottom:1px solid var(--color-ink-100);}
.sige-cracha-head h2{margin:0;font-size:var(--fs-lg);font-weight:700;color:var(--color-ink-700);}
.sige-cracha-x{background:none;border:none;font-size:26px;line-height:1;cursor:pointer;color:var(--color-slate-500);padding:0 var(--space-2);}
.sige-cracha-x:hover{color:var(--color-ink-700);}
.sige-cracha-body{padding:var(--space-6);overflow-y:auto;min-height:0;}
.sige-cracha-grid{display:grid;grid-template-columns:1fr 300px;gap:var(--space-6);align-items:start;}
.sige-cracha-label{margin:0 0 var(--space-3);font-size:var(--fs-sm);font-weight:700;color:var(--color-slate-600);text-transform:uppercase;letter-spacing:.4px;}
.sige-cracha-templates{display:grid;grid-template-columns:1fr 1fr;gap:var(--space-3);margin-bottom:var(--space-5);}
.sige-cracha-tpl{border:1.5px solid var(--color-ink-100);border-radius:var(--radius-md);padding:var(--space-4);cursor:pointer;transition:border-color .15s ease,background .15s ease;}
.sige-cracha-tpl:hover{border-color:var(--color-brand-300);}
.sige-cracha-tpl.is-active{border-color:var(--color-brand-500);background:var(--color-brand-50);}
.sige-cracha-tpl-nome{font-weight:700;color:var(--color-ink-700);font-size:var(--fs-base);}
.sige-cracha-tpl-desc{font-size:var(--fs-sm);color:var(--color-slate-500);margin-top:var(--space-1);line-height:1.35;}
.sige-cracha-accent{display:flex;align-items:center;gap:var(--space-3);margin-bottom:var(--space-5);}
.sige-cracha-accent input[type=color]{width:48px;height:38px;border:1px solid var(--color-ink-200);border-radius:var(--radius-md);background:var(--color-white);cursor:pointer;padding:2px;}
.sige-cracha-accent input[type=text]{flex:1;height:38px;border:1.5px solid var(--color-ink-200);border-radius:var(--radius-md);padding:0 var(--space-3);font-family:'Courier New',monospace;color:var(--color-ink-700);}
.sige-cracha-toggle{display:flex;align-items:center;gap:var(--space-3);font-size:var(--fs-base);color:var(--color-ink-700);cursor:pointer;margin-bottom:var(--space-4);}
.sige-cracha-toggle input{width:18px;height:18px;cursor:pointer;}
.sige-cracha-social{display:grid;gap:var(--space-3);}
.sige-cracha-social.is-hidden{display:none;}
.sige-cracha-social input{height:38px;border:1.5px solid var(--color-ink-200);border-radius:var(--radius-md);padding:0 var(--space-3);color:var(--color-ink-700);font-size:var(--fs-base);}
.sige-cracha-social input:focus,.sige-cracha-accent input:focus{outline:none;border-color:var(--color-brand-400);}
.sige-cracha-preview-wrap{position:sticky;top:0;}
.sige-cracha-frame{width:100%;height:410px;border:1px solid var(--color-ink-100);border-radius:var(--radius-lg);background:var(--color-slate-50);}
.sige-cracha-foot{display:flex;align-items:center;justify-content:flex-end;gap:var(--space-3);padding:var(--space-4) var(--space-6);border-top:1px solid var(--color-ink-100);}
.sige-cracha-msg{margin-right:auto;font-size:var(--fs-sm);color:var(--color-success-700);font-weight:600;}
.sige-cracha-msg.is-error{color:var(--color-danger-500);}
.sige-cracha-btn-cancel,.sige-cracha-btn-save{height:42px;padding:0 var(--space-6);border-radius:var(--radius-md);font-weight:700;font-size:var(--fs-base);cursor:pointer;border:1.5px solid transparent;}
.sige-cracha-btn-cancel{background:var(--color-white);border-color:var(--color-ink-200);color:var(--color-ink-700);}
.sige-cracha-btn-cancel:hover{border-color:var(--color-slate-300);}
.sige-cracha-btn-save{background:var(--color-brand-500);color:var(--color-white);}
.sige-cracha-btn-save:hover{background:var(--color-brand-600);}
.sige-cracha-btn-save[disabled]{opacity:.6;cursor:default;}
@media(max-width:760px){.sige-cracha-grid{grid-template-columns:1fr;}.sige-cracha-templates{grid-template-columns:1fr;}.sige-cracha-preview-wrap{position:static;}.sige-cracha-frame{height:380px;}}
</style>
<div id="sige-cracha-staff-modal" class="sige-cracha-modal" aria-hidden="true">
    <div class="sige-cracha-backdrop" data-sige-act="fecharModeloCrachaStaff" data-sige-noargs></div>
    <div class="sige-cracha-dialog" role="dialog" aria-modal="true" aria-labelledby="sige-cracha-staff-title">
        <div class="sige-cracha-head">
            <h2 id="sige-cracha-staff-title">Modelo de Crachá da Equipa</h2>
            <button type="button" class="sige-cracha-x" data-sige-act="fecharModeloCrachaStaff" data-sige-noargs aria-label="Fechar">&times;</button>
        </div>
        <div class="sige-cracha-body">
            <div class="sige-cracha-grid">
                <div class="sige-cracha-controls">
                    <p class="sige-cracha-label">Modelo</p>
                    <div class="sige-cracha-templates" id="sige-cracha-staff-templates"></div>
                    <p class="sige-cracha-label">Cor de destaque</p>
                    <div class="sige-cracha-accent">
                        <input type="color" id="sige-cracha-staff-accent" value="#1e3a8a" aria-label="Cor de destaque">
                        <input type="text" id="sige-cracha-staff-accent-hex" maxlength="7" placeholder="#1e3a8a" aria-label="Cor de destaque (hex)">
                    </div>
                    <label class="sige-cracha-toggle">
                        <input type="checkbox" id="sige-cracha-staff-show-social"> Mostrar redes sociais no crachá
                    </label>
                    <div class="sige-cracha-social is-hidden" id="sige-cracha-staff-social">
                        <input type="text" id="sige-cracha-staff-ig" placeholder="Instagram (ex.: @minhaescola)" maxlength="80">
                        <input type="text" id="sige-cracha-staff-fb" placeholder="Facebook (ex.: /minhaescola)" maxlength="80">
                        <input type="text" id="sige-cracha-staff-web" placeholder="Website (ex.: minhaescola.co.mz)" maxlength="80">
                    </div>
                </div>
                <div class="sige-cracha-preview-wrap">
                    <p class="sige-cracha-label">Pré-visualização</p>
                    <iframe id="sige-cracha-staff-frame" class="sige-cracha-frame" title="Pré-visualização do crachá"></iframe>
                </div>
            </div>
        </div>
        <div class="sige-cracha-foot">
            <span class="sige-cracha-msg" id="sige-cracha-staff-msg" aria-live="polite"></span>
            <button type="button" class="sige-cracha-btn-cancel" data-sige-act="fecharModeloCrachaStaff" data-sige-noargs>Cancelar</button>
            <button type="button" class="sige-cracha-btn-save" id="sige-cracha-staff-save" data-sige-act="guardarModeloCrachaStaff" data-sige-noargs>Guardar modelo</button>
        </div>
    </div>
</div>
<script <?php echo sige_csp_script_attr(); ?>>
(function () {
    var SAMPLE = { nome: 'João Mausse', cargo: 'Professor', validade: '31/12/<?php echo (int)($escola->ano_lectivo ?: wp_date("Y")); ?>', foto: '' };
    var ESCOLA = <?php echo json_encode($escola->nome_escola ?: 'ESCOLA'); ?>;
    var LOGO = <?php echo json_encode($logo_final ?: ''); ?>;
    function cfg() { return (window.sigeEquipeAjax && sigeEquipeAjax.cracha) ? sigeEquipeAjax.cracha : { config: {}, templates: {}, social: [] }; }
    function el(id) { return document.getElementById(id); }
    var modal = null, frame = null, estado = null, inited = false;
    function ctxAtual() {
        return { escolaNome: ESCOLA, logoUrl: LOGO || sigeEquipeAjax.avatarUrl, template: estado.template, accent: estado.accent, showSocial: estado.show_social, social: estado.social, batch: false, nonce: (typeof sgRhLiveNonce === 'function') ? sgRhLiveNonce() : '' };
    }
    function escreverNaFrame(doc) { if (!frame) return; try { var d = frame.contentWindow.document; d.open(); d.write(doc); d.close(); } catch (e) { try { frame.srcdoc = doc; } catch (e2) {} } }
    function renderPreview() {
        if (!frame) return;
        sgRhEnsureTemplates(function (ok) {
            if (!ok || !window.SigeCrachaStaffTemplates) { escreverNaFrame('<!doctype html><meta charset="utf-8"><body style="font:13px sans-serif;color:slategray;display:flex;align-items:center;justify-content:center;height:100%;text-align:center;padding:16px">Pré-visualização indisponível. Verifique a ligação e tente reabrir.</body>'); return; }
            var doc; try { doc = window.SigeCrachaStaffTemplates.buildDocument([SAMPLE], ctxAtual()); } catch (e) { return; }
            escreverNaFrame(doc);
        });
    }
    function marcar() { Array.prototype.forEach.call(document.querySelectorAll('#sige-cracha-staff-templates .sige-cracha-tpl'), function (n) { n.classList.toggle('is-active', n.getAttribute('data-tpl') === estado.template); }); }
    function renderOptions() {
        var wrap = el('sige-cracha-staff-templates'); if (!wrap) return;
        var metas = cfg().templates || {}, html = '';
        Object.keys(metas).forEach(function (id) { var m = metas[id] || {}, a = (id === estado.template) ? ' is-active' : ''; html += '<div class="sige-cracha-tpl' + a + '" data-tpl="' + sigeEquipeEscapeHtml(id) + '" role="button" tabindex="0"><div class="sige-cracha-tpl-nome">' + sigeEquipeEscapeHtml(m.nome || id) + '</div><div class="sige-cracha-tpl-desc">' + sigeEquipeEscapeHtml(m.descricao || '') + '</div></div>'; });
        wrap.innerHTML = html;
        Array.prototype.forEach.call(wrap.querySelectorAll('.sige-cracha-tpl'), function (node) { function pick() { estado.template = node.getAttribute('data-tpl'); marcar(); renderPreview(); } node.addEventListener('click', pick); node.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); pick(); } }); });
    }
    function syncSocial() { var box = el('sige-cracha-staff-social'); if (box) box.classList.toggle('is-hidden', !estado.show_social); }
    function msg(t, e) { var m = el('sige-cracha-staff-msg'); if (!m) return; m.textContent = t || ''; m.classList.toggle('is-error', !!e); }
    function lerForm() { estado.show_social = el('sige-cracha-staff-show-social').checked; estado.social = { instagram: el('sige-cracha-staff-ig').value || '', facebook: el('sige-cracha-staff-fb').value || '', website: el('sige-cracha-staff-web').value || '' }; }
    function init() {
        if (inited) return; modal = el('sige-cracha-staff-modal'); frame = el('sige-cracha-staff-frame'); if (!modal) return; inited = true;
        var c = cfg().config || {};
        estado = { template: c.template || 'corporate', accent: c.accent || '#1e3a8a', show_social: !!c.show_social, social: { instagram: (c.social && c.social.instagram) || '', facebook: (c.social && c.social.facebook) || '', website: (c.social && c.social.website) || '' } };
        el('sige-cracha-staff-accent').value = estado.accent; el('sige-cracha-staff-accent-hex').value = estado.accent;
        el('sige-cracha-staff-show-social').checked = estado.show_social;
        el('sige-cracha-staff-ig').value = estado.social.instagram; el('sige-cracha-staff-fb').value = estado.social.facebook; el('sige-cracha-staff-web').value = estado.social.website;
        renderOptions(); syncSocial();
        el('sige-cracha-staff-accent').addEventListener('input', function () { estado.accent = this.value; el('sige-cracha-staff-accent-hex').value = this.value; renderPreview(); });
        el('sige-cracha-staff-accent-hex').addEventListener('input', function () { var v = String(this.value || '').trim(); if (/^#?[0-9a-fA-F]{6}$/.test(v)) { if (v[0] !== '#') v = '#' + v; estado.accent = v; el('sige-cracha-staff-accent').value = v; renderPreview(); } });
        el('sige-cracha-staff-show-social').addEventListener('change', function () { estado.show_social = this.checked; syncSocial(); renderPreview(); });
        var map = { ig: 'instagram', fb: 'facebook', web: 'website' };
        ['ig', 'fb', 'web'].forEach(function (k) { el('sige-cracha-staff-' + k).addEventListener('input', function () { estado.social[map[k]] = this.value; renderPreview(); }); });
    }
    window.abrirModeloCrachaStaff = function () { init(); if (!modal) { showToast('Indisponível', 'Seletor de modelo indisponível.', 'error'); return; } msg('', false); modal.classList.add('is-open'); modal.setAttribute('aria-hidden', 'false'); document.body.classList.add('sige-rh-modal-open'); renderPreview(); };
    window.fecharModeloCrachaStaff = function () { if (!modal) return; modal.classList.remove('is-open'); modal.setAttribute('aria-hidden', 'true'); document.body.classList.remove('sige-rh-modal-open'); };
    window.guardarModeloCrachaStaff = function () {
        init(); if (!modal) return; lerForm();
        var btn = el('sige-cracha-staff-save'); if (btn) btn.setAttribute('disabled', 'disabled');
        msg('A guardar...', false);
        var fd = new FormData();
        fd.append('action', 'sige_save_cracha_staff_config'); fd.append('_sige_nonce', sigeEquipeAjax.nonce);
        fd.append('template', estado.template); fd.append('accent', estado.accent); fd.append('show_social', estado.show_social ? '1' : '0');
        fd.append('social_instagram', estado.social.instagram); fd.append('social_facebook', estado.social.facebook); fd.append('social_website', estado.social.website);
        fetch(sigeEquipeAjax.ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd }).then(function (r) { return r.json(); }).then(function (r) {
            if (r && r.success && r.data && r.data.config) {
                sigeEquipeAjax.cracha.config = r.data.config;
                estado.template = r.data.config.template; estado.accent = r.data.config.accent; estado.show_social = !!r.data.config.show_social; estado.social = r.data.config.social || estado.social;
                marcar(); renderPreview(); msg('Guardado.', false);
                var nm = (cfg().templates && cfg().templates[estado.template] && cfg().templates[estado.template].nome) ? cfg().templates[estado.template].nome : estado.template;
                showToast('Modelo guardado', 'O modelo "' + nm + '" passa a ser usado nos crachás da equipa.', 'success');
                setTimeout(window.fecharModeloCrachaStaff, 900);
            } else {
                var em = (r && r.data && (r.data.msg || r.data)) ? (r.data.msg || r.data) : 'Não foi possível guardar.';
                msg(em, true); showToast('Não foi possível guardar', String(em), 'error');
            }
        }).catch(function () { msg('Falha de ligação.', true); showToast('Erro de ligação', 'Verifique a internet e tente novamente.', 'error'); }).then(function () { if (btn) btn.removeAttribute('disabled'); });
    };
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && modal && modal.classList.contains('is-open')) window.fecharModeloCrachaStaff(); });
})();
</script>
<?php endif; ?>
