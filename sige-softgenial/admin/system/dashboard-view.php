<?php
if (!defined('ABSPATH')) { exit; }

/**
 * SIGE SoftGenial - Painel Principal (Dashboard)
 * Ficheiro: admin/system/dashboard-view.php
 *
 * v2.1 - Abril 2026
 * - Paleta alinhada com o Design System (tokens sige-tokens.css, marca roxa)
 * - Removido @import Google Fonts duplicado (já carregado em style.css)
 * - Removido date_default_timezone_set() - usa wp_date()
 * - $eid usado consistentemente em todas as queries
 * - Ícones SVG, micro-animações, responsive
 */

// [FIX R-05] Guard - Dashboard apenas para gestão; docentes são redireccionados
// [12.9.6] Matriz SIGE manda; WP caps fallback. Os redirects para professor/
// educador continuam a basear-se em WP roles porque são UX hints, não guardas.
$_dash_allowed = sige_page_guard_allows(
    ['academico.dashboard_ver','financeiro.dashboard_ver','rh.equipe_ver','sistema.estado_ver'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_financeiro','sige_gestor_rh','sige_pedagogico','sige_admin_ti']
);

if (!$_dash_allowed) {
    if (current_user_can('sige_professor')) {
        // v12.16.0 RC6 - hotfix de staging: URL de redirect em JavaScript deve ser
        // serializada por JSON, nao por esc_url(). A entidade HTML do ampersand
        // em codigo JavaScript pode virar fragmento e perder o parametro view=.
        $sige_professor_redirect_url = admin_url('admin.php?page=sige-app&view=minhas_turmas');
        echo '<script ' . sige_csp_script_attr() . '>window.location.replace(' . wp_json_encode($sige_professor_redirect_url) . ');</script>';
        return;
    }
    if (current_user_can('sige_educador')) {
        // v12.16.0 RC6 - mesma proteccao para educador/jardim_diario.
        $sige_educador_redirect_url = admin_url('admin.php?page=sige-app&view=jardim_diario');
        echo '<script ' . sige_csp_script_attr() . '>window.location.replace(' . wp_json_encode($sige_educador_redirect_url) . ');</script>';
        return;
    }
    echo '<div class="sige-access-denied"><div class="sige-access-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><h2>Acesso Restrito</h2><p>O seu perfil SIGE não tem permissão para aceder a este módulo.</p></div>';
    return;
}

global $wpdb;
$p = $wpdb->prefix;
$eid = function_exists('sige_get_escola_id') ? sige_get_escola_id() : 0;
$__sige_sys_dash_cache_enabled = function_exists('get_transient') && function_exists('set_transient') && empty($_POST);

// ══════════════════════════════════════════════════════════════════════════════
// DADOS DO DASHBOARD (lógica PHP inalterada)
// ══════════════════════════════════════════════════════════════════════════════

$ano = function_exists('sige_fin_get_ano_letivo_master')
    ? (int)sige_fin_get_ano_letivo_master()
    : (int)($wpdb->get_var($wpdb->prepare("SELECT ano_lectivo FROM {$p}sige_config WHERE escola_id = %d LIMIT 1", $eid)) ?: wp_date('Y'));

if (!empty($__sige_sys_dash_cache_enabled)) {
    $__sige_sys_dash_cache_key = 'sige_sys_dash_html_' . md5((string)get_current_user_id() . '|' . (string)$eid . '|' . (string)$ano . '|' . (defined('SIGE_VERSION') ? SIGE_VERSION : ''));
    $__sige_sys_dash_cached = get_transient($__sige_sys_dash_cache_key);
    if (is_string($__sige_sys_dash_cached) && $__sige_sys_dash_cached !== '') { echo $__sige_sys_dash_cached; return; }
    ob_start();
}
$__sige_hoje = current_time('Y-m-d');
$__sige_amanha = date('Y-m-d', strtotime($__sige_hoje . ' +1 day'));
$__sige_inicio_mes = wp_date('Y-m-01');
$__sige_inicio_mes_seguinte = date('Y-m-d', strtotime($__sige_inicio_mes . ' +1 month'));
$__sige_inicio_ano = (string)$ano . '-01-01';
$__sige_inicio_ano_seguinte = (string)($ano + 1) . '-01-01';

$__sige_a_activo  = function_exists('sige_aluno_activo_sql') ? sige_aluno_activo_sql('a') : "(a.status IS NULL OR LOWER(a.status) IN ('activo','ativo','activa','ativa'))";
$__sige_m_activo  = function_exists('sige_matricula_activa_sql') ? sige_matricula_activa_sql('m') : "(m.status_matricula IS NULL OR LOWER(m.status_matricula) IN ('activa','ativa','activo','ativo'))";
$__sige_am_activo = function_exists('sige_aluno_matricula_activa_sql') ? sige_aluno_matricula_activa_sql('a', 'm') : ($__sige_a_activo . ' AND ' . $__sige_m_activo);



// 1. ALUNOS
$total_alunos_activos = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT a.id)
     FROM {$p}sige_alunos a
     INNER JOIN {$p}sige_matriculas m ON m.aluno_id = a.id AND m.ano_lectivo = %d
     WHERE {$__sige_am_activo} AND a.escola_id = %d",
    $ano, $eid
));

$generos = $wpdb->get_results($wpdb->prepare(
    "SELECT LOWER(a.genero) as genero, COUNT(DISTINCT a.id) as total
     FROM {$p}sige_alunos a
     INNER JOIN {$p}sige_matriculas m ON m.aluno_id = a.id AND m.ano_lectivo = %d
     WHERE {$__sige_am_activo} AND a.escola_id = %d
     GROUP BY a.genero",
    $ano, $eid
));

$masculino = $feminino = 0;
foreach ($generos as $g) {
    if (in_array($g->genero, ['m','masculino','masc'])) $masculino = (int)$g->total;
    if (in_array($g->genero, ['f','feminino','fem']))   $feminino  = (int)$g->total;
}

$aniversariantes_hoje = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT a.id)
     FROM {$p}sige_alunos a
     INNER JOIN {$p}sige_matriculas m ON m.aluno_id = a.id AND m.ano_lectivo = %d
     WHERE {$__sige_am_activo}
       AND DAY(a.data_nascimento) = DAY(CURDATE())
       AND MONTH(a.data_nascimento) = MONTH(CURDATE())
       AND a.escola_id = %d",
    $ano, $eid
));

$novos_mes = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT a.id)
     FROM {$p}sige_alunos a
     INNER JOIN {$p}sige_matriculas m ON m.aluno_id = a.id AND m.ano_lectivo = %d
     WHERE {$__sige_am_activo}
       AND m.data_matricula >= %s
       AND m.data_matricula < %s
       AND a.escola_id = %d",
    $ano, $__sige_inicio_mes, $__sige_inicio_mes_seguinte, $eid
));

// 2. TURMAS
$total_turmas = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}sige_turmas WHERE escola_id = %d", $eid));

$turmas_com_alunos = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT t.id)
     FROM {$p}sige_turmas t
     INNER JOIN {$p}sige_matriculas m ON m.turma_id = t.id AND m.ano_lectivo = %d
     INNER JOIN {$p}sige_alunos a ON a.id = m.aluno_id AND {$__sige_a_activo} AND {$__sige_m_activo}
     WHERE t.escola_id = %d",
    $ano, $eid
));

$media_alunos_sala = $turmas_com_alunos > 0 ? round($total_alunos_activos / $turmas_com_alunos, 1) : 0;

$distribuicao = $wpdb->get_results($wpdb->prepare(
    "SELECT t.classe,
            COUNT(DISTINCT t.id) as n_turmas,
            COUNT(DISTINCT CASE WHEN {$__sige_a_activo} AND {$__sige_m_activo} THEN a.id END) as n_alunos
     FROM {$p}sige_turmas t
     LEFT JOIN {$p}sige_matriculas m ON m.turma_id = t.id AND m.ano_lectivo = %d
     LEFT JOIN {$p}sige_alunos a ON a.id = m.aluno_id
     WHERE t.escola_id = %d
     GROUP BY t.classe
     ORDER BY t.classe ASC",
    $ano, $eid
));

// 3. CORPO DOCENTE
$staff_query = new WP_User_Query([
    'role__in' => ['administrator', 'sige_professor', 'sige_director', 'sige_secretaria_geral',
                   'sige_assistente', 'sige_financeiro', 'sige_recepcao', 'sige_secretario',
                   'sige_gestor_rh', 'sige_pedagogico', 'sige_educador', 'sige_motorista', 'sige_limpeza',
                   'sige_guarda'],
    'fields'   => ['ID', 'user_email', 'display_name'],
    'meta_query' => [['key' => 'sige_escola_id', 'value' => $eid, 'compare' => '=']],
]);

$staff_users = $staff_query->get_results();
$total_staff = count($staff_users);
$total_docentes = $total_admin_staff = $total_apoio = 0;
$_docente_roles = ['sige_professor','sige_educador','sige_pedagogico'];
$_apoio_roles   = ['sige_motorista','sige_limpeza','sige_recepcao','sige_guarda'];

foreach ($staff_users as $u) {
    $user_obj = new WP_User($u->ID);
    $roles = (array)$user_obj->roles;
    if (array_intersect($_docente_roles, $roles)) {
        $total_docentes++;
    } elseif (array_intersect($_apoio_roles, $roles)) {
        $total_apoio++;
    } else {
        $total_admin_staff++;
    }
}

$profs_sem_nuit = $profs_efectivos = $profs_contrato = 0;
$profs_table_exists = (bool)$wpdb->get_var(
    $wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like("{$p}sige_professores"))
);

if ($profs_table_exists) {
    $profs_sem_nuit = (int)$wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$p}sige_professores WHERE escola_id = %d AND status_ativo = 1 AND (nuit IS NULL OR nuit = '')", $eid)
    );
    $profs_efectivos = (int)$wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$p}sige_professores WHERE escola_id = %d AND status_ativo = 1 AND tipo_contrato = 'efectivo'", $eid)
    );
    $profs_contrato = max(0, $total_docentes - $profs_efectivos);
}

// 4. FINANCEIRO
$mes_actual = (int)wp_date('m');

$receita_mes = (float)$wpdb->get_var($wpdb->prepare(
    "SELECT COALESCE(SUM(valor_pago), 0)
     FROM {$p}sige_fin_pagamentos
     WHERE escola_id = %d AND data_pagamento >= %s AND data_pagamento < %s",
    $eid, $__sige_inicio_mes, $__sige_inicio_mes_seguinte
));

$receita_ano = (float)$wpdb->get_var($wpdb->prepare(
    "SELECT COALESCE(SUM(valor_pago), 0) 
     FROM {$p}sige_fin_pagamentos 
     WHERE escola_id = %d AND data_pagamento >= %s AND data_pagamento < %s",
    $eid, $__sige_inicio_ano, $__sige_inicio_ano_seguinte
));

$lancado_mes = (float)$wpdb->get_var($wpdb->prepare(
    "SELECT COALESCE(SUM(
        COALESCE(valor_original,0)
        + COALESCE(valor_transporte,0)
        + COALESCE(valor_extras,0)
    ), 0)
    FROM {$p}sige_fin_lancamentos
    WHERE escola_id = %d AND data_vencimento >= %s AND data_vencimento < %s",
    $eid, $__sige_inicio_mes, $__sige_inicio_mes_seguinte
));

$taxa_recebimento = $lancado_mes > 0 ? min(100, round($receita_mes / $lancado_mes * 100)) : 0;

$total_divida = (float)$wpdb->get_var($wpdb->prepare(
    "SELECT COALESCE(SUM(
        COALESCE(valor_original,0)
        + COALESCE(valor_transporte,0)
        + COALESCE(valor_extras,0)
        + COALESCE(valor_multa,0)
        - COALESCE(valor_desconto,0)
        - COALESCE(valor_desconto_especial,0)
        - COALESCE(valor_pago,0)
    ), 0)
    FROM {$p}sige_fin_lancamentos
    WHERE escola_id = %d AND status IN ('pendente','parcial')",
    $eid
));

$alunos_com_divida = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT aluno_id)
     FROM {$p}sige_fin_lancamentos
     WHERE escola_id = %d AND status IN ('pendente','parcial')",
    $eid
));

$pagamentos_hoje = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$p}sige_fin_pagamentos WHERE escola_id = %d AND data_pagamento >= %s AND data_pagamento < %s",
    $eid, $__sige_hoje, $__sige_amanha
));

// 5. ALERTAS DE CONFORMIDADE
$alunos_sem_doc = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT a.id)
     FROM {$p}sige_alunos a
     INNER JOIN {$p}sige_matriculas m ON m.aluno_id = a.id AND m.ano_lectivo = %d
     WHERE {$__sige_am_activo}
       AND (a.documento_nr IS NULL OR a.documento_nr = '')
       AND a.escola_id = %d",
    $ano, $eid
));

$alunos_sem_encarregado = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT a.id)
     FROM {$p}sige_alunos a
     INNER JOIN {$p}sige_matriculas m ON m.aluno_id = a.id AND m.ano_lectivo = %d
     WHERE {$__sige_am_activo}
       AND (a.nome_pai IS NULL OR a.nome_pai = '')
       AND (a.nome_mae IS NULL OR a.nome_mae = '')
       AND a.escola_id = %d",
    $ano, $eid
));

$alunos_sem_turma = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT a.id)
     FROM {$p}sige_alunos a
     LEFT JOIN {$p}sige_matriculas m ON m.aluno_id = a.id AND m.ano_lectivo = %d
     WHERE {$__sige_am_activo}
       AND m.id IS NULL
       AND a.escola_id = %d",
    $ano, $eid
));

// Mês em português
$meses_pt = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril',
             '05'=>'Maio','06'=>'Junho','07'=>'Julho','08'=>'Agosto',
             '09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
$nome_mes = $meses_pt[wp_date('m')] ?? wp_date('M');

// Helper para formatar valores
if (!function_exists('dash_fmt')) {
    function dash_fmt(float $v): string {
        if ($v >= 1_000_000) return number_format($v/1_000_000,1,',','.').'M';
        if ($v >= 1_000)     return number_format($v/1_000,1,',','.').'K';
        return number_format($v, 0, ',', '.');
    }
}

?>

<?php
// ══════════════════════════════════════════════════════════════════════════════
// CAMADA DE APRESENTAÇÃO - apenas leitura/visualização, sem alterar regras.
// v12.19.4 - Painel simplificado: menos blocos, menos distracção, foco no
// essencial (pulso da escola, dinheiro, conformidade e atalhos). Removidos os
// blocos redundantes e a camada de "inteligência"/coaching que enchiam o ecrã.
// Lógica, consultas e regras inalteradas.
// ══════════════════════════════════════════════════════════════════════════════
$__sg_url = static function (string $view): string {
    return add_query_arg(['page' => 'sige-app', 'view' => $view], admin_url('admin.php'));
};
$__sg_can_any = static function (array $permissions, array $legacy_caps = []): bool {
    if (function_exists('sige_page_guard_allows')) {
        return sige_page_guard_allows($permissions, $legacy_caps);
    }
    if (function_exists('sige_can')) {
        foreach ($permissions as $__sg_permission) {
            $__sg_permission = (string)$__sg_permission;
            if ($__sg_permission !== '' && sige_can($__sg_permission)) { return true; }
        }
    }
    foreach ($legacy_caps as $__sg_cap) {
        $__sg_cap = (string)$__sg_cap;
        if ($__sg_cap !== '' && current_user_can($__sg_cap)) { return true; }
    }
    return false;
};
$__sg_can_alunos = $__sg_can_any(['alunos.ver'], ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_recepcao']);
$__sg_can_turmas = $__sg_can_any(['academico.turmas_ver'], ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_pedagogico']);
$__sg_can_academico = $__sg_can_any(['academico.dashboard_ver','academico.ver','academico.turmas_ver'], ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_pedagogico']);
$__sg_can_equipa = $__sg_can_any(['rh.equipe_ver'], ['sige_director','sige_gestor_rh']);
$__sg_can_pagamentos = $__sg_can_any(['financeiro.pagar'], ['sige_director','sige_financeiro','sige_secretario']);
$__sg_can_fin_dashboard = $__sg_can_any(['financeiro.dashboard_ver','financeiro.ver'], ['sige_director','sige_financeiro','sige_secretario']);
$__sg_can_devedores = $__sg_can_any(['financeiro.cobrancas_ver'], ['sige_director','sige_financeiro','sige_secretario']);
$__sg_can_financeiro = ($__sg_can_fin_dashboard || $__sg_can_pagamentos || $__sg_can_devedores);
$__sg_can_config = $__sg_can_any(['sistema.estado_ver','configuracoes.ver'], ['administrator','sige_admin_ti']);

// Alertas de conformidade (accionáveis).
$__sg_alertas = [
    ['label' => 'Alunos sem Nº de Documento (BI/NUIT)', 'val' => $alunos_sem_doc, 'tipo' => $alunos_sem_doc > 0 ? 'warn' : 'ok', 'icon' => 'shield', 'href' => $__sg_can_alunos ? $__sg_url('alunos_lista') : '', 'action' => 'Rever alunos', 'enabled' => ($__sg_can_alunos || $__sg_can_academico)],
    ['label' => 'Alunos sem encarregado registado', 'val' => $alunos_sem_encarregado, 'tipo' => $alunos_sem_encarregado > 0 ? 'warn' : 'ok', 'icon' => 'users', 'href' => $__sg_can_alunos ? $__sg_url('alunos_lista') : '', 'action' => 'Completar dados', 'enabled' => ($__sg_can_alunos || $__sg_can_academico)],
    ['label' => 'Alunos activos sem turma em ' . $ano, 'val' => $alunos_sem_turma, 'tipo' => $alunos_sem_turma > 0 ? 'err' : 'ok', 'icon' => 'school', 'href' => $__sg_can_turmas ? $__sg_url('turmas') : '', 'action' => 'Alocar turmas', 'enabled' => ($__sg_can_turmas || $__sg_can_academico)],
    ['label' => 'Professores sem NUIT no perfil', 'val' => $profs_sem_nuit, 'tipo' => $profs_sem_nuit > 0 ? 'warn' : 'ok', 'icon' => 'file', 'href' => $__sg_can_equipa ? $__sg_url('equipe') : '', 'action' => 'Rever equipa', 'enabled' => $__sg_can_equipa],
    ['label' => 'Alunos com propinas em atraso', 'val' => $alunos_com_divida, 'tipo' => $alunos_com_divida > 0 ? 'warn' : 'ok', 'icon' => 'wallet', 'href' => $__sg_can_devedores ? $__sg_url('financeiro-devedores') : '', 'action' => 'Cobrar agora', 'enabled' => $__sg_can_financeiro],
];
$__sg_alertas = array_values(array_filter($__sg_alertas, static function ($__sg_a): bool { return !empty($__sg_a['enabled']); }));
$__sg_alertas_total = 0;
foreach ($__sg_alertas as $__sg_a) { if (($__sg_a['tipo'] ?? '') !== 'ok') { $__sg_alertas_total += (int)($__sg_a['val'] ?? 0); } }

// Distribuição por classe (donut).
$__sg_total_dist = 0;
foreach ((array)$distribuicao as $__sg_d) { $__sg_total_dist += (int)($__sg_d->n_alunos ?? 0); }
$__sg_dist_top = array_slice((array)$distribuicao, 0, 5);
$__sg_dist_colors = ['var(--color-brand-400)', 'var(--color-success-500)', 'var(--color-warning-400)', 'var(--color-danger-400)', 'var(--color-info-400)'];
$__sg_donut_segments = [];
$__sg_cursor = 0;
foreach ($__sg_dist_top as $__sg_i => $__sg_d) {
    $__sg_pct = $__sg_total_dist > 0 ? max(1, round(((int)($__sg_d->n_alunos ?? 0) / $__sg_total_dist) * 100)) : 0;
    $__sg_end = min(100, $__sg_cursor + $__sg_pct);
    $__sg_donut_segments[] = $__sg_dist_colors[$__sg_i % count($__sg_dist_colors)] . ' ' . $__sg_cursor . '% ' . $__sg_end . '%';
    $__sg_cursor = $__sg_end;
}
if ($__sg_cursor < 100) { $__sg_donut_segments[] = 'var(--color-slate-100) ' . $__sg_cursor . '% 100%'; }
$__sg_donut_style = $__sg_total_dist > 0 ? 'background: conic-gradient(' . implode(',', $__sg_donut_segments) . ');' : 'background:var(--color-slate-100);';

// Atalhos rápidos (rótulos fixos e acentuados).
$__sg_quick_links = [
    ['label' => 'Gerir Alunos', 'href' => $__sg_url('alunos_lista'), 'icon' => 'users', 'qbg' => 'var(--color-brand-50)', 'qcolor' => 'var(--color-brand-500)', 'enabled' => $__sg_can_alunos],
    ['label' => 'Gerir Turmas', 'href' => $__sg_url('turmas'), 'icon' => 'school', 'qbg' => 'var(--color-success-50)', 'qcolor' => 'var(--color-success-500)', 'enabled' => $__sg_can_turmas],
    ['label' => 'Gerir Docentes', 'href' => $__sg_url('equipe'), 'icon' => 'users', 'qbg' => 'var(--color-warning-50)', 'qcolor' => 'var(--color-warning-500)', 'enabled' => $__sg_can_equipa],
    ['label' => 'Registar Pagamento', 'href' => $__sg_url('financeiro-pagamentos'), 'icon' => 'wallet', 'qbg' => 'var(--color-info-50)', 'qcolor' => 'var(--color-info-500)', 'enabled' => $__sg_can_pagamentos],
    ['label' => 'Devedores', 'href' => $__sg_url('financeiro-devedores'), 'icon' => 'trending', 'qbg' => 'var(--color-danger-50)', 'qcolor' => 'var(--color-danger-500)', 'enabled' => $__sg_can_devedores],
    ['label' => 'Configurações', 'href' => $__sg_url('config_center'), 'icon' => 'settings', 'qbg' => 'var(--color-info-50)', 'qcolor' => 'var(--color-info-400)', 'enabled' => $__sg_can_config],
];
$__sg_quick_links = array_values(array_filter($__sg_quick_links, static function ($__sg_link): bool { return !empty($__sg_link['enabled']); }));

$__sg_today_label = wp_date('d/m/Y');
$__sg_alert_state_label = $__sg_alertas_total > 0 ? 'Atenção necessária' : 'Tudo em ordem';
$__sg_taxa_recebimento = max(0, min(100, (int)$taxa_recebimento));
$__sg_recebimento_label = $lancado_mes > 0
    ? ($__sg_taxa_recebimento >= 80 ? 'Ritmo saudável' : ($__sg_taxa_recebimento >= 50 ? 'A acompanhar' : 'Cobrança baixa'))
    : 'Sem lançamento no mês';
$__sg_has_kpis = (($__sg_can_alunos || $__sg_can_academico) || ($__sg_can_equipa || $__sg_can_academico) || $__sg_can_financeiro);
?>

<style id="sg-dashboard-v2-lean">
/* ============================================================================
   SIGE - Painel Principal (Dashboard) v12.19.4 - versão simplificada.
   Camada visual; não altera cálculos nem regras. Cores via tokens (marca roxa).
   ============================================================================ */
body.sige-view-dashboard .sg-product-page-head{display:none!important;}
.sg-dashboard-v2{--sgv2-purple:var(--color-brand-500);--sgv2-ink:var(--color-ink-500);--sgv2-muted:var(--color-slate-500);color:var(--sgv2-ink);}
.sg-dashboard-v2 *{box-sizing:border-box;}
.sg-dash-shell{display:flex;flex-direction:column;gap:var(--space-5);}

/* Herói compacto: uma faixa, sem ilustração nem parágrafo longo. */
.sg-dash-hero{border-radius:var(--radius-xl);background:linear-gradient(110deg,var(--color-white) 0%,var(--color-brand-50) 100%);border:1px solid rgba(92,64,187,.12);box-shadow:var(--shadow-md);padding:var(--space-6) var(--space-8);}
.sg-hero-kicker{display:inline-flex;align-items:center;gap:var(--space-2);font-size:var(--fs-sm);font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--sgv2-purple);margin-bottom:6px;}
.sg-hero-kicker svg{width:16px;height:16px;stroke:currentColor;fill:none;}
.sg-hero-title{margin:0;font-size:var(--fs-xl);line-height:1.15;font-weight:700;letter-spacing:-.02em;color:var(--color-ink-500);}
.sg-hero-status-row{display:flex;flex-wrap:wrap;gap:var(--space-2);margin-top:var(--space-4);}
.sg-hero-status-pill{display:inline-flex;align-items:center;gap:var(--space-2);min-height:32px;padding:0 var(--space-3);border-radius:var(--radius-pill);background:var(--color-white);border:1px solid var(--color-slate-200);color:var(--color-slate-700);font-size:var(--fs-sm);font-weight:500;}
.sg-hero-status-pill strong{color:var(--sgv2-purple);font-weight:600;}
.sg-hero-status-pill.is-warning strong{color:var(--color-warning-600);}
.sg-hero-status-pill.is-good strong{color:var(--color-success-600);}
.sg-hero-actions{display:flex;flex-wrap:wrap;gap:var(--space-3);margin-top:var(--space-4);}
.sg-v2-btn{min-height:42px;display:inline-flex;align-items:center;justify-content:center;gap:var(--space-2);border-radius:var(--radius-md);padding:0 var(--space-5);font-size:var(--fs-base);font-weight:600;text-decoration:none;border:1px solid transparent;transition:transform .16s ease,box-shadow .16s ease;}
.sg-v2-btn svg{width:18px;height:18px;stroke:currentColor;fill:none;}
.sg-v2-btn-primary{background:var(--color-brand-500);color:var(--color-white);box-shadow:var(--shadow-sm);}
.sg-v2-btn-primary:hover{background:var(--color-brand-600);color:var(--color-white);transform:translateY(-1px);}
.sg-v2-btn-secondary{background:var(--color-white);color:var(--color-ink-700);border-color:var(--color-slate-200);}
.sg-v2-btn-secondary:hover{border-color:var(--color-brand-300);color:var(--sgv2-purple);}

/* KPIs */
.sg-kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--space-4);}
.sg-kpi-card{display:grid;grid-template-columns:auto minmax(0,1fr);gap:var(--space-4);align-items:center;min-height:96px;padding:var(--space-5);border-radius:var(--radius-lg);background:var(--color-white);border:1px solid var(--color-slate-100);box-shadow:var(--shadow-sm);transition:box-shadow .16s ease,transform .16s ease;}
.sg-kpi-card:hover{box-shadow:var(--shadow-md);transform:translateY(-1px);}
.sg-kpi-icon{width:48px;height:48px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;background:var(--kpi-soft,var(--color-brand-50));color:var(--kpi-color,var(--sgv2-purple));}
.sg-kpi-icon svg{width:22px;height:22px;stroke:currentColor;fill:none;}
.sg-kpi-label{font-size:var(--fs-sm);font-weight:500;color:var(--color-slate-600);margin-bottom:4px;}
.sg-kpi-value{font-size:var(--fs-2xl);line-height:1;font-weight:700;letter-spacing:-.02em;color:var(--color-ink-500);}
.sg-kpi-note{margin-top:6px;font-size:var(--fs-sm);font-weight:500;color:var(--color-slate-500);}
.sg-kpi-note strong{color:var(--kpi-color,var(--sgv2-purple));font-weight:600;}

/* Grelha de cartões */
.sg-dash-grid{display:grid;grid-template-columns:2fr 1fr;gap:var(--space-5);align-items:start;}
.sg-dash-card{background:var(--color-white);border:1px solid var(--color-slate-100);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);overflow:hidden;min-width:0;}
.sg-dash-card-header{display:flex;align-items:center;justify-content:space-between;gap:var(--space-4);padding:var(--space-5) var(--space-6) var(--space-3);}
.sg-dash-title{display:flex;align-items:center;gap:var(--space-3);min-width:0;}
.sg-dash-title-icon{width:38px;height:38px;border-radius:var(--radius-md);background:var(--icon-bg,var(--color-brand-50));color:var(--icon-color,var(--sgv2-purple));display:flex;align-items:center;justify-content:center;flex:0 0 auto;}
.sg-dash-title-icon svg{width:19px;height:19px;stroke:currentColor;fill:none;}
.sg-dash-title h3{margin:0;font-size:var(--fs-md);line-height:1.1;font-weight:600;color:var(--color-ink-500);}
.sg-dash-title p{margin:var(--space-1) 0 0;font-size:var(--fs-sm);font-weight:500;color:var(--color-slate-500);}
.sg-card-link{font-size:var(--fs-sm);font-weight:600;color:var(--sgv2-purple);text-decoration:none;background:var(--color-brand-50);border-radius:var(--radius-pill);padding:var(--space-2) var(--space-3);white-space:nowrap;}
.sg-dash-card-body{padding:var(--space-2) var(--space-6) var(--space-6);}

/* Resumo financeiro: só números reais, sem gráfico decorativo. */
.sg-finance-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:var(--space-3);}
.sg-fin-mini{border-radius:var(--radius-md);background:var(--color-slate-50);border:1px solid var(--color-slate-100);padding:var(--space-3) var(--space-4);}
.sg-fin-mini span{display:block;font-size:var(--fs-xs);font-weight:500;text-transform:uppercase;letter-spacing:.03em;color:var(--color-slate-500);margin-bottom:6px;}
.sg-fin-mini strong{display:block;font-size:var(--fs-lg);font-weight:700;color:var(--color-ink-500);letter-spacing:-.02em;}
.sg-val-pos{color:var(--color-success-600);}
.sg-val-neg{color:var(--color-danger-500);}
.sg-val-hi{color:var(--color-brand-500);}

/* Distribuição por classe */
.sg-donut-wrap{display:grid;grid-template-columns:150px minmax(0,1fr);gap:var(--space-5);align-items:center;}
.sg-donut{width:140px;height:140px;border-radius:50%;position:relative;}
.sg-donut:after{content:"";position:absolute;inset:30px;background:var(--color-white);border-radius:50%;box-shadow:var(--shadow-xs);}
.sg-donut-center{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:2;text-align:center;}
.sg-donut-center strong{font-size:var(--fs-xl);font-weight:700;color:var(--color-ink-500);line-height:1;}
.sg-donut-center span{font-size:var(--fs-sm);color:var(--color-slate-500);margin-top:3px;}
.sg-class-list{display:flex;flex-direction:column;gap:var(--space-3);}
.sg-class-row{display:grid;grid-template-columns:auto 1fr auto;gap:var(--space-2);align-items:center;font-size:var(--fs-sm);font-weight:500;color:var(--color-ink-800);}
.sg-class-dot{width:10px;height:10px;border-radius:50%;background:var(--dot,var(--color-brand-400));}
.sg-class-bar{grid-column:2 / 4;height:6px;border-radius:var(--radius-pill);background:var(--color-slate-100);overflow:hidden;}
.sg-class-bar i{display:block;height:100%;width:var(--w,0%);border-radius:inherit;background:var(--dot,var(--color-brand-400));}

/* Alertas */
.sg-alert-stack{display:flex;flex-direction:column;gap:var(--space-3);}
.sg-alert-item{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:var(--space-3);align-items:center;border-radius:var(--radius-md);padding:var(--space-3) var(--space-4);background:var(--alert-bg,var(--color-slate-50));border:1px solid var(--alert-line,var(--color-slate-100));color:inherit;text-decoration:none;transition:box-shadow .15s ease,transform .15s ease;}
a.sg-alert-item:hover{transform:translateY(-1px);box-shadow:var(--shadow-sm);}
.sg-alert-ico{width:34px;height:34px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;color:var(--alert-color,var(--color-brand-400));background:var(--color-white);box-shadow:var(--shadow-xs);}
.sg-alert-ico svg{width:18px;height:18px;stroke:currentColor;fill:none;}
.sg-alert-copy strong{display:block;font-size:var(--fs-sm);line-height:1.3;font-weight:600;color:var(--color-ink-800);}
.sg-alert-copy span{display:block;margin-top:2px;font-size:var(--fs-xs);font-weight:500;color:var(--color-slate-500);}
.sg-alert-badge{min-width:28px;height:26px;border-radius:var(--radius-pill);display:flex;align-items:center;justify-content:center;padding:0 var(--space-2);font-size:var(--fs-sm);font-weight:600;background:var(--alert-color,var(--color-brand-400));color:var(--color-white);}

/* Acessos rápidos */
.sg-quick-grid-v2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--space-3);}
.sg-quick-v2{min-height:64px;border:1px solid var(--color-slate-100);border-radius:var(--radius-md);background:var(--color-white);display:flex;align-items:center;gap:var(--space-3);padding:var(--space-3);text-decoration:none;color:var(--color-ink-800);font-size:var(--fs-sm);font-weight:600;transition:box-shadow .16s ease,transform .16s ease,border-color .16s ease;}
.sg-quick-v2:hover{transform:translateY(-1px);box-shadow:var(--shadow-sm);border-color:var(--color-brand-100);color:var(--sgv2-purple);}
.sg-quick-ico{width:36px;height:36px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;background:var(--qbg,var(--color-brand-50));color:var(--qcolor,var(--sgv2-purple));flex:0 0 auto;}
.sg-quick-ico svg{width:18px;height:18px;stroke:currentColor;fill:none;}
.sg-quick-label{min-width:0;overflow:hidden;text-overflow:ellipsis;}
.sg-empty-note{font-size:var(--fs-sm);color:var(--color-slate-500);}
.sg-empty-note strong{display:block;color:var(--color-ink-700);margin-bottom:2px;}

@media (max-width:1100px){
    .sg-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
    .sg-dash-grid{grid-template-columns:1fr;}
    .sg-donut-wrap{grid-template-columns:1fr;}
    .sg-donut{margin:auto;}
}
@media (max-width:680px){
    .sg-dash-hero{padding:var(--space-5) var(--space-5);}
    .sg-kpi-grid{grid-template-columns:1fr;}
    .sg-finance-row{grid-template-columns:1fr;}
    .sg-quick-grid-v2{grid-template-columns:1fr;}
}
</style>

<div class="sg-dashboard-v2 sg-dashboard-mobile-pro" data-sg-dashboard-ux="12.19.4">
    <div class="sg-dash-shell">

        <section class="sg-dash-hero" aria-label="Resumo do painel principal">
            <div class="sg-hero-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('dashboard') : ''; ?> Painel Principal</div>
            <h1 class="sg-hero-title">Bem-vindo de volta, <?php echo esc_html(wp_get_current_user()->first_name ?: wp_get_current_user()->display_name ?: 'Admin'); ?> 👋</h1>
            <div class="sg-hero-status-row" aria-label="Estado rápido">
                <span class="sg-hero-status-pill">Hoje · <strong><?php echo esc_html($__sg_today_label); ?></strong></span>
                <span class="sg-hero-status-pill <?php echo $__sg_alertas_total > 0 ? 'is-warning' : 'is-good'; ?>">Alertas · <strong><?php echo esc_html($__sg_alert_state_label); ?></strong></span>
                <?php if ($__sg_can_financeiro): ?><span class="sg-hero-status-pill">Cobrança · <strong><?php echo esc_html($__sg_recebimento_label); ?></strong></span><?php endif; ?>
            </div>
            <?php if ($__sg_can_pagamentos || $__sg_can_alunos): ?>
            <div class="sg-hero-actions">
                <?php if ($__sg_can_pagamentos): ?>
                <a href="<?php echo esc_url($__sg_url('financeiro-pagamentos')); ?>" class="sg-v2-btn sg-v2-btn-primary"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('wallet') : ''; ?> Registar Pagamento</a>
                <?php endif; ?>
                <?php if ($__sg_can_alunos): ?>
                <a href="<?php echo esc_url($__sg_url('alunos_lista')); ?>" class="sg-v2-btn sg-v2-btn-secondary"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('users') : ''; ?> Gerir Alunos</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </section>

        <?php if ($__sg_has_kpis): ?>
        <section class="sg-kpi-grid" aria-label="Indicadores principais">
            <?php if ($__sg_can_alunos || $__sg_can_academico): ?>
            <article class="sg-kpi-card" style="--kpi-color:var(--color-brand-500);--kpi-soft:var(--color-brand-50);">
                <div class="sg-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('users') : ''; ?></div>
                <div><div class="sg-kpi-label">Alunos Activos</div><div class="sg-kpi-value"><?php echo (int)$total_alunos_activos; ?></div><div class="sg-kpi-note">♂ <strong><?php echo (int)$masculino; ?></strong> · ♀ <strong><?php echo (int)$feminino; ?></strong></div></div>
            </article>
            <?php endif; ?>
            <?php if ($__sg_can_equipa || $__sg_can_academico): ?>
            <article class="sg-kpi-card" style="--kpi-color:var(--color-success-600);--kpi-soft:var(--color-success-50);">
                <div class="sg-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('school') : ''; ?></div>
                <div><div class="sg-kpi-label">Docentes</div><div class="sg-kpi-value"><?php echo (int)$total_docentes; ?></div><div class="sg-kpi-note">Equipa total: <strong><?php echo (int)$total_staff; ?></strong></div></div>
            </article>
            <?php endif; ?>
            <?php if ($__sg_can_financeiro): ?>
            <article class="sg-kpi-card" style="--kpi-color:var(--color-info-500);--kpi-soft:var(--color-info-50);">
                <div class="sg-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('wallet') : ''; ?></div>
                <div><div class="sg-kpi-label">Pagamentos Hoje</div><div class="sg-kpi-value"><?php echo (int)$pagamentos_hoje; ?></div><div class="sg-kpi-note">Receita do mês: <strong><?php echo esc_html(dash_fmt($receita_mes)); ?> MT</strong></div></div>
            </article>
            <?php endif; ?>
            <?php if ($__sg_can_financeiro): ?>
            <article class="sg-kpi-card" style="--kpi-color:var(--color-warning-600);--kpi-soft:var(--color-warning-50);">
                <div class="sg-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('trending') : ''; ?></div>
                <div><div class="sg-kpi-label">Dívida Total</div><div class="sg-kpi-value"><?php echo esc_html(dash_fmt($total_divida)); ?> MT</div><div class="sg-kpi-note">Devedores: <strong><?php echo (int)$alunos_com_divida; ?></strong></div></div>
            </article>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="sg-dash-grid" aria-label="Áreas do painel">
            <?php if ($__sg_can_financeiro): ?>
            <article class="sg-dash-card sg-card-finance">
                <div class="sg-dash-card-header">
                    <div class="sg-dash-title"><div class="sg-dash-title-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('chart') : ''; ?></div><div><h3>Resumo Financeiro</h3><p>Este mês · <?php echo esc_html($nome_mes); ?> <?php echo esc_html($ano); ?></p></div></div>
                    <?php if ($__sg_can_fin_dashboard): ?><a class="sg-card-link" href="<?php echo esc_url($__sg_url('financeiro-dashboard')); ?>">Ver relatório →</a><?php endif; ?>
                </div>
                <div class="sg-dash-card-body">
                    <div class="sg-finance-row">
                        <div class="sg-fin-mini"><span>Receitas</span><strong class="sg-val-pos"><?php echo esc_html(dash_fmt($receita_mes)); ?> MT</strong></div>
                        <div class="sg-fin-mini"><span>Lançado no mês</span><strong class="sg-val-neg"><?php echo esc_html(dash_fmt($lancado_mes)); ?> MT</strong></div>
                        <div class="sg-fin-mini"><span>Taxa de cobrança</span><strong class="sg-val-hi"><?php echo (int)$__sg_taxa_recebimento; ?>%</strong></div>
                    </div>
                </div>
            </article>
            <?php endif; ?>

            <?php if ($__sg_can_alunos || $__sg_can_turmas || $__sg_can_academico): ?>
            <article class="sg-dash-card sg-card-classes">
                <div class="sg-dash-card-header">
                    <div class="sg-dash-title"><div class="sg-dash-title-icon" style="--icon-bg:var(--color-info-50);--icon-color:var(--color-info-500);"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('grid') : ''; ?></div><div><h3>Distribuição por Classe</h3><p>Alunos activos por classe</p></div></div>
                </div>
                <div class="sg-dash-card-body">
                    <div class="sg-donut-wrap">
                        <div class="sg-donut" style="<?php echo esc_attr($__sg_donut_style); ?>"><div class="sg-donut-center"><strong><?php echo (int)$__sg_total_dist; ?></strong><span>alunos</span></div></div>
                        <div class="sg-class-list">
                            <?php foreach ($__sg_dist_top as $__sg_i => $__sg_d): $__sg_pct = $__sg_total_dist > 0 ? round(((int)($__sg_d->n_alunos ?? 0) / max(1,$__sg_total_dist)) * 100) : 0; $__sg_color = $__sg_dist_colors[$__sg_i % count($__sg_dist_colors)]; ?>
                                <div class="sg-class-row" style="--dot:<?php echo esc_attr($__sg_color); ?>;--w:<?php echo (int)$__sg_pct; ?>%;"><span class="sg-class-dot"></span><span><?php echo esc_html($__sg_d->classe ?? 'Classe'); ?></span><strong><?php echo (int)($__sg_d->n_alunos ?? 0); ?></strong><div class="sg-class-bar"><i></i></div></div>
                            <?php endforeach; ?>
                            <?php if (empty($__sg_dist_top)): ?><div class="sg-empty-note"><strong>Sem distribuição disponível</strong>Quando houver turmas com alunos, o resumo aparece aqui.</div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </article>
            <?php endif; ?>

            <?php if (!empty($__sg_alertas)): ?>
            <article class="sg-dash-card sg-card-alerts">
                <div class="sg-dash-card-header">
                    <div class="sg-dash-title"><div class="sg-dash-title-icon" style="--icon-bg:var(--color-warning-100);--icon-color:var(--color-warning-600);"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('shield') : ''; ?></div><div><h3>Alertas de Conformidade</h3><p>Situações que pedem atenção</p></div></div>
                    <span class="sg-card-link"><?php echo (int)$__sg_alertas_total; ?> alerta(s)</span>
                </div>
                <div class="sg-dash-card-body">
                    <div class="sg-alert-stack">
                        <?php foreach ($__sg_alertas as $__sg_a):
                            $__sg_tipo = $__sg_a['tipo'] ?? 'ok';
                            $__sg_color = $__sg_tipo === 'err' ? 'var(--color-danger-500)' : ($__sg_tipo === 'warn' ? 'var(--color-warning-600)' : 'var(--color-success-600)');
                            $__sg_bg = $__sg_tipo === 'err' ? 'var(--color-danger-50)' : ($__sg_tipo === 'warn' ? 'var(--color-warning-50)' : 'var(--color-success-50)');
                            $__sg_line = $__sg_tipo === 'err' ? 'var(--color-danger-200)' : ($__sg_tipo === 'warn' ? 'var(--color-warning-200)' : 'var(--color-success-200)');
                            $__sg_alert_href = (string)($__sg_a['href'] ?? '');
                            $__sg_alert_action = (string)($__sg_a['action'] ?? 'Abrir');
                            $__sg_tag = $__sg_alert_href !== '' ? 'a' : 'div';
                        ?>
                            <<?php echo $__sg_tag; ?> class="sg-alert-item"<?php echo $__sg_alert_href !== '' ? ' href="' . esc_url($__sg_alert_href) . '"' : ''; ?> style="--alert-color:<?php echo esc_attr($__sg_color); ?>;--alert-bg:<?php echo esc_attr($__sg_bg); ?>;--alert-line:<?php echo esc_attr($__sg_line); ?>;">
                                <span class="sg-alert-ico"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon((string)$__sg_a['icon']) : ''; ?></span>
                                <span class="sg-alert-copy"><strong><?php echo esc_html((string)$__sg_a['label']); ?></strong><span><?php echo (int)$__sg_a['val'] > 0 ? esc_html($__sg_alert_action) : 'Sem pendência activa'; ?></span></span>
                                <span class="sg-alert-badge"><?php echo (int)$__sg_a['val']; ?></span>
                            </<?php echo $__sg_tag; ?>>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>
            <?php endif; ?>

            <article class="sg-dash-card sg-card-quick">
                <div class="sg-dash-card-header">
                    <div class="sg-dash-title"><div class="sg-dash-title-icon" style="--icon-bg:var(--color-brand-50);--icon-color:var(--color-brand-500);"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('bolt') : ''; ?></div><div><h3>Acessos Rápidos</h3><p>Operações mais usadas</p></div></div>
                </div>
                <div class="sg-dash-card-body">
                    <div class="sg-quick-grid-v2">
                        <?php foreach ($__sg_quick_links as $__sg_link): ?>
                            <a href="<?php echo esc_url((string)$__sg_link['href']); ?>" class="sg-quick-v2"><span class="sg-quick-ico" style="--qbg:<?php echo esc_attr((string)$__sg_link['qbg']); ?>;--qcolor:<?php echo esc_attr((string)$__sg_link['qcolor']); ?>;"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon((string)$__sg_link['icon']) : ''; ?></span><span class="sg-quick-label"><?php echo esc_html((string)$__sg_link['label']); ?></span></a>
                        <?php endforeach; ?>
                        <?php if (empty($__sg_quick_links)): ?><div class="sg-empty-note"><strong>Sem atalhos disponíveis</strong>O painel está em modo de consulta conforme as permissões do seu perfil.</div><?php endif; ?>
                    </div>
                </div>
            </article>
        </section>
    </div>
</div>
<?php
// sige_sys_dash_html_cache_store [12.9.8.7 PERFORMANCE]
if (isset($__sige_sys_dash_cache_key) && function_exists('set_transient') && ob_get_level() > 0) {
    $__sige_sys_dash_html = ob_get_contents();
    if (is_string($__sige_sys_dash_html) && $__sige_sys_dash_html !== '') { set_transient($__sige_sys_dash_cache_key, $__sige_sys_dash_html, 2 * MINUTE_IN_SECONDS); }
}
?>
