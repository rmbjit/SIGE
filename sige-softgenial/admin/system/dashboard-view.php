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
// DADOS DE APRESENTAÇÃO - apenas leitura/visualização, sem alterar regras
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

$__sg_operational_context = function_exists('sige_institutional_profile_context_v121600') ? sige_institutional_profile_context_v121600(8) : [];
$__sg_operational_actions = (isset($__sg_operational_context['actions']) && is_array($__sg_operational_context['actions'])) ? $__sg_operational_context['actions'] : [];
$__sg_operational_profile = isset($__sg_operational_context['label']) ? (string)$__sg_operational_context['label'] : '';
$__sg_operational_focus = isset($__sg_operational_context['focus']) ? (string)$__sg_operational_context['focus'] : '';
$__sg_operational_signals = [
    'alunos_com_divida' => (int)$alunos_com_divida,
    'pagamentos_hoje' => (int)$pagamentos_hoje,
    'alunos_sem_doc' => (int)$alunos_sem_doc,
    'alunos_sem_encarregado' => (int)$alunos_sem_encarregado,
    'alunos_sem_turma' => (int)$alunos_sem_turma,
    'profs_sem_nuit' => (int)$profs_sem_nuit,
];
$__sg_operational_checklist = function_exists('sige_institutional_operational_checklist_v121600') ? sige_institutional_operational_checklist_v121600($__sg_operational_signals, 5) : [];
$__sg_operational_flows = function_exists('sige_institutional_flow_guidance_v121600') ? sige_institutional_flow_guidance_v121600($__sg_operational_signals, 3) : [];

$__sg_alertas = [
    ['label' => 'alunos sem Nº Documento (BI/NUIT)', 'val' => $alunos_sem_doc, 'tipo' => $alunos_sem_doc > 0 ? 'warn' : 'ok', 'icon' => 'shield', 'href' => $__sg_can_alunos ? $__sg_url('alunos_lista') : '', 'action' => 'Rever alunos', 'enabled' => ($__sg_can_alunos || $__sg_can_academico)],
    ['label' => 'alunos sem encarregado registado', 'val' => $alunos_sem_encarregado, 'tipo' => $alunos_sem_encarregado > 0 ? 'warn' : 'ok', 'icon' => 'users', 'href' => $__sg_can_alunos ? $__sg_url('alunos_lista') : '', 'action' => 'Completar dados', 'enabled' => ($__sg_can_alunos || $__sg_can_academico)],
    ['label' => 'alunos activos sem turma em ' . $ano, 'val' => $alunos_sem_turma, 'tipo' => $alunos_sem_turma > 0 ? 'err' : 'ok', 'icon' => 'school', 'href' => $__sg_can_turmas ? $__sg_url('turmas') : '', 'action' => 'Alocar turmas', 'enabled' => ($__sg_can_turmas || $__sg_can_academico)],
    ['label' => 'professores sem NUIT no perfil', 'val' => $profs_sem_nuit, 'tipo' => $profs_sem_nuit > 0 ? 'warn' : 'ok', 'icon' => 'file', 'href' => $__sg_can_equipa ? $__sg_url('equipe') : '', 'action' => 'Rever equipa', 'enabled' => $__sg_can_equipa],
    ['label' => 'alunos com propinas em atraso', 'val' => $alunos_com_divida, 'tipo' => $alunos_com_divida > 0 ? 'warn' : 'ok', 'icon' => 'wallet', 'href' => $__sg_can_devedores ? $__sg_url('financeiro-devedores') : '', 'action' => 'Cobrar agora', 'enabled' => $__sg_can_financeiro],
];
$__sg_alertas = array_values(array_filter($__sg_alertas, static function ($__sg_a): bool { return !empty($__sg_a['enabled']); }));
$__sg_alertas_total = 0;
foreach ($__sg_alertas as $__sg_a) { if (($__sg_a['tipo'] ?? '') !== 'ok') { $__sg_alertas_total += (int)($__sg_a['val'] ?? 0); } }

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

$__sg_media_sala_pct = $media_alunos_sala > 0 ? min(100, round(($media_alunos_sala / 35) * 100)) : 0;
$__sg_cadastro_pct = $total_alunos_activos > 0 ? max(0, min(100, 100 - round((($alunos_sem_doc + $alunos_sem_encarregado) / max(1, $total_alunos_activos)) * 100))) : 0;
$__sg_equipa_pct = $total_docentes > 0 ? max(0, min(100, 100 - round(($profs_sem_nuit / max(1, $total_docentes)) * 100))) : 0;
$__sg_taxa_recebimento = max(0, min(100, (int)$taxa_recebimento));
$__sg_ratio_docente = ($total_docentes > 0 && $total_alunos_activos > 0) ? round($total_alunos_activos / max(1, $total_docentes), 1) : 0;
$__sg_today_label = wp_date('d/m/Y');
$__sg_alert_state_label = $__sg_alertas_total > 0 ? 'Atenção necessária' : 'Tudo em ordem';
$__sg_recebimento_label = $lancado_mes > 0
    ? ($__sg_taxa_recebimento >= 80 ? 'Ritmo saudável' : ($__sg_taxa_recebimento >= 50 ? 'A acompanhar' : 'Cobrança baixa'))
    : 'Sem lançamento no mês';
$__sg_debt_label = $alunos_com_divida > 0 ? 'Requer cobrança' : 'Sem devedores pendentes';
$__sg_data_quality_label = $__sg_cadastro_pct >= 90 ? 'Fichas saudáveis' : ($__sg_cadastro_pct >= 70 ? 'Rever fichas' : 'Prioridade alta');
$__sg_dashboard_actions = [
    ['label' => 'Pagamento', 'hint' => 'Registar agora', 'href' => $__sg_url('financeiro-pagamentos'), 'icon' => 'wallet', 'color' => 'var(--color-info-500)', 'soft' => 'var(--color-info-50)', 'enabled' => $__sg_can_pagamentos],
    ['label' => 'Novo aluno', 'hint' => 'Abrir ficha de aluno', 'href' => $__sg_url('alunos_lista'), 'icon' => 'users', 'color' => 'var(--color-brand-500)', 'soft' => 'var(--color-brand-50)', 'enabled' => $__sg_can_alunos],
    ['label' => 'Devedores', 'hint' => 'Cobranças', 'href' => $__sg_url('financeiro-devedores'), 'icon' => 'trending', 'color' => 'var(--color-danger-500)', 'soft' => 'var(--color-danger-50)', 'enabled' => $__sg_can_devedores],
    ['label' => 'Turmas', 'hint' => 'Organizar salas', 'href' => $__sg_url('turmas'), 'icon' => 'school', 'color' => 'var(--color-success-500)', 'soft' => 'var(--color-success-50)', 'enabled' => $__sg_can_turmas],
];
$__sg_dashboard_actions = array_values(array_filter($__sg_dashboard_actions, static function ($__sg_action): bool { return !empty($__sg_action['enabled']); }));
$__sg_focus_cards = [
    ['label' => 'Cobrança do mês', 'value' => $__sg_taxa_recebimento . '%', 'hint' => $__sg_recebimento_label . ' · ' . dash_fmt($receita_mes) . ' MT recebidos', 'href' => $__sg_url('financeiro-dashboard'), 'icon' => 'chart', 'color' => 'var(--color-success-500)', 'soft' => 'var(--color-success-50)', 'enabled' => $__sg_can_fin_dashboard],
    ['label' => 'Pendências financeiras', 'value' => (string)(int)$alunos_com_divida, 'hint' => $__sg_debt_label . ' · ' . dash_fmt($total_divida) . ' MT', 'href' => $__sg_url('financeiro-devedores'), 'icon' => 'wallet', 'color' => 'var(--color-warning-600)', 'soft' => 'var(--color-warning-50)', 'enabled' => $__sg_can_devedores],
    ['label' => 'Qualidade dos dados', 'value' => $__sg_cadastro_pct . '%', 'hint' => $__sg_data_quality_label . ' · documentos e encarregados', 'href' => $__sg_url('alunos_lista'), 'icon' => 'shield', 'color' => 'var(--color-brand-500)', 'soft' => 'var(--color-brand-50)', 'enabled' => $__sg_can_alunos],
];
$__sg_focus_cards = array_values(array_filter($__sg_focus_cards, static function ($__sg_focus): bool { return !empty($__sg_focus['enabled']); }));
$__sg_quick_links = [
    ['label' => 'Gerir Alunos', 'href' => $__sg_url('alunos_lista'), 'icon' => 'users', 'qbg' => 'var(--color-brand-50)', 'qcolor' => 'var(--color-brand-500)', 'enabled' => $__sg_can_alunos],
    ['label' => 'Gerir Turmas', 'href' => $__sg_url('turmas'), 'icon' => 'school', 'qbg' => 'var(--color-success-50)', 'qcolor' => 'var(--color-success-500)', 'enabled' => $__sg_can_turmas],
    ['label' => 'Gerir Docentes', 'href' => $__sg_url('equipe'), 'icon' => 'users', 'qbg' => 'var(--color-warning-50)', 'qcolor' => 'var(--color-warning-500)', 'enabled' => $__sg_can_equipa],
    ['label' => 'Registar Pagamento', 'href' => $__sg_url('financeiro-pagamentos'), 'icon' => 'wallet', 'qbg' => 'var(--color-info-50)', 'qcolor' => 'var(--color-info-500)', 'enabled' => $__sg_can_pagamentos],
    ['label' => 'Devedores', 'href' => $__sg_url('financeiro-devedores'), 'icon' => 'trending', 'qbg' => 'var(--color-danger-50)', 'qcolor' => 'var(--color-danger-500)', 'enabled' => $__sg_can_devedores],
    ['label' => 'Configurações', 'href' => $__sg_url('config_center'), 'icon' => 'settings', 'qbg' => 'var(--color-info-50)', 'qcolor' => 'var(--color-info-400)', 'enabled' => $__sg_can_config],
];
$__sg_quick_links = array_values(array_filter($__sg_quick_links, static function ($__sg_link): bool { return !empty($__sg_link['enabled']); }));

if (!empty($__sg_operational_actions)) {
    $__sg_context_actions = [];
    $__sg_context_links = [];
    foreach ($__sg_operational_actions as $__sg_op_action) {
        $__sg_label = (string)($__sg_op_action['label'] ?? '');
        $__sg_href = (string)($__sg_op_action['href'] ?? '');
        if ($__sg_label === '' || $__sg_href === '') { continue; }
        $__sg_context_links[] = [
            'label' => $__sg_label,
            'href' => $__sg_href,
            'icon' => (string)($__sg_op_action['icon'] ?? 'dashboard'),
            'qbg' => (string)($__sg_op_action['soft'] ?? 'var(--color-brand-50)'),
            'qcolor' => (string)($__sg_op_action['color'] ?? 'var(--color-brand-500)'),
            'enabled' => true,
        ];
        if (count($__sg_context_actions) < 4) {
            $__sg_context_actions[] = [
                'label' => $__sg_label,
                'hint' => (string)($__sg_op_action['hint'] ?? 'Abrir area'),
                'href' => $__sg_href,
                'icon' => (string)($__sg_op_action['icon'] ?? 'dashboard'),
                'color' => (string)($__sg_op_action['color'] ?? 'var(--color-brand-500)'),
                'soft' => (string)($__sg_op_action['soft'] ?? 'var(--color-brand-50)'),
                'enabled' => true,
            ];
        }
    }
    if (!empty($__sg_context_actions)) {
        $__sg_dashboard_actions = $__sg_context_actions;
    }
    if (!empty($__sg_context_links)) {
        $__sg_seen_links = [];
        $__sg_merged_links = [];
        foreach (array_merge($__sg_context_links, $__sg_quick_links) as $__sg_link) {
            $__sg_href_key = (string)($__sg_link['href'] ?? '');
            if ($__sg_href_key === '' || isset($__sg_seen_links[$__sg_href_key])) { continue; }
            $__sg_seen_links[$__sg_href_key] = true;
            $__sg_merged_links[] = $__sg_link;
            if (count($__sg_merged_links) >= 6) { break; }
        }
        $__sg_quick_links = $__sg_merged_links;
    }
}
$__sg_progress_items = [
    ['label' => 'Cobrança do mês', 'value' => $__sg_taxa_recebimento . '%', 'p' => $__sg_taxa_recebimento . '%', 'color' => 'var(--color-success-600)', 'enabled' => $__sg_can_financeiro],
    ['label' => 'Cadastro completo', 'value' => $__sg_cadastro_pct . '%', 'p' => $__sg_cadastro_pct . '%', 'color' => 'var(--color-brand-400)', 'enabled' => ($__sg_can_alunos || $__sg_can_academico)],
    ['label' => 'Equipa documentada', 'value' => $__sg_equipa_pct . '%', 'p' => $__sg_equipa_pct . '%', 'color' => 'var(--color-info-400)', 'enabled' => $__sg_can_equipa],
    ['label' => 'Ocupação média', 'value' => (string)$media_alunos_sala, 'p' => $__sg_media_sala_pct . '%', 'color' => 'var(--color-warning-500)', 'enabled' => ($__sg_can_turmas || $__sg_can_academico)],
];
$__sg_progress_items = array_values(array_filter($__sg_progress_items, static function ($__sg_item): bool { return !empty($__sg_item['enabled']); }));
$__sg_day_rows = [
    ['icon' => 'wallet', 'title' => 'Pagamentos registados hoje', 'text' => 'Movimentos recebidos no dia actual.', 'meta' => (string)(int)$pagamentos_hoje, 'ibg' => 'var(--color-info-50)', 'icolor' => 'var(--color-info-500)', 'enabled' => $__sg_can_financeiro],
    ['icon' => 'users', 'title' => 'Novas matrículas no mês', 'text' => 'Entradas registadas em ' . $nome_mes . '.', 'meta' => (string)(int)$novos_mes, 'ibg' => 'var(--color-success-50)', 'icolor' => 'var(--color-success-500)', 'enabled' => ($__sg_can_alunos || $__sg_can_academico)],
    ['icon' => 'school', 'title' => 'Turmas com alunos', 'text' => 'Turmas activas com ocupação registada.', 'meta' => (string)(int)$turmas_com_alunos, 'ibg' => 'var(--color-warning-50)', 'icolor' => 'var(--color-warning-500)', 'enabled' => ($__sg_can_turmas || $__sg_can_academico)],
    ['icon' => 'chart', 'title' => 'Rácio alunos/docente', 'text' => 'Indicador operacional do corpo docente.', 'meta' => (string)($__sg_ratio_docente ?: '-'), 'ibg' => 'var(--color-brand-50)', 'icolor' => 'var(--color-brand-500)', 'enabled' => (($__sg_can_alunos || $__sg_can_academico) && ($__sg_can_equipa || $__sg_can_academico))],
];
$__sg_day_rows = array_values(array_filter($__sg_day_rows, static function ($__sg_row): bool { return !empty($__sg_row['enabled']); }));
$__sg_has_kpis = (($__sg_can_alunos || $__sg_can_academico) || ($__sg_can_equipa || $__sg_can_academico) || $__sg_can_financeiro);
$__sg_hero_subtitle = $__sg_can_financeiro
    ? 'Aqui está o que está a acontecer na escola hoje: indicadores principais, cobrança, conformidade e atalhos para as operações mais usadas.'
    : 'Aqui está o que está a acontecer na escola hoje: indicadores principais, conformidade e atalhos compatíveis com o seu perfil.';
if ($__sg_operational_profile !== '' && $__sg_operational_focus !== '') {
    $__sg_hero_subtitle = 'Foco operacional para ' . $__sg_operational_profile . ': ' . $__sg_operational_focus . '. Indicadores e atalhos abaixo respeitam as permissões do seu perfil.';
}
?>

<style id="sg-dashboard-v2-mjs-grade">
/* ============================================================================
   SoftGenial Produto PRO - Dashboard V2 MJS-grade
   Escopo: camada visual do painel principal. Não altera cálculos nem regras.
   ============================================================================ */
body.sige-view-dashboard .sg-product-page-head{display:none!important;}
.sg-dashboard-v2{--sgv2-purple:var(--color-brand-500);--sgv2-purple-dark:var(--color-brand-700);--sgv2-purple-soft:var(--color-brand-50);--sgv2-ink:var(--color-ink-500);--sgv2-muted:var(--color-slate-500);--sgv2-line:var(--color-ink-100);--sgv2-bg:var(--color-ink-50);--sgv2-green:var(--color-success-700);--sgv2-red:var(--color-danger-500);--sgv2-amber:var(--color-warning-500);--sgv2-blue:var(--color-info-400);font-family:'Poppins','Inter','Segoe UI',system-ui,sans-serif;color:var(--sgv2-ink);}
.sg-dashboard-v2 *{box-sizing:border-box;}
.sg-dash-shell{display:flex;flex-direction:column;gap:var(--space-5);}
.sg-dash-hero{position:relative;overflow:hidden;min-height:178px;border-radius:var(--radius-xl);background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-brand-100) 100%);border:1px solid rgba(92,64,187,.12);box-shadow:var(--shadow-lg);padding:var(--space-8) var(--space-8);display:grid;grid-template-columns:minmax(0,1.04fr) minmax(340px,.96fr);gap:var(--space-6);align-items:center;}
.sg-dash-hero:before{content:"";position:absolute;inset:auto -80px -130px auto;width:420px;height:300px;background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);pointer-events:none;}
.sg-hero-kicker{font-size:var(--fs-sm);font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:var(--sgv2-purple);margin-bottom:10px;}
.sg-hero-copy{min-width:0;position:relative;z-index:1;}
.sg-hero-title{margin:0;font-size:var(--fs-3xl);line-height:1.08;font-weight:700;letter-spacing:-.04em;color:var(--color-black);max-width:100%;overflow-wrap:anywhere;word-break:normal;text-wrap:balance;}
.sg-hero-subtitle{max-width:650px;margin:var(--space-3) 0 0;font-size:var(--fs-md);line-height:1.65;color:var(--color-slate-700);font-weight:500;overflow-wrap:anywhere;}
.sg-hero-actions{display:flex;flex-wrap:wrap;gap:var(--space-3);margin-top:24px;}
.sg-v2-btn{min-height:46px;display:inline-flex;align-items:center;justify-content:center;gap:var(--space-3);border-radius:var(--radius-md);padding:0 var(--space-6);font-size:var(--fs-base);font-weight:700;text-decoration:none;border:1px solid transparent;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease;background:var(--color-white);color:var(--color-slate-900);}
.sg-v2-btn svg{width:18px;height:18px;stroke:currentColor;color:currentColor;fill:none;opacity:1;}
.sg-v2-btn-primary{background:linear-gradient(135deg,var(--color-brand-400),var(--color-brand-600));color:var(--color-white);box-shadow:var(--shadow-md);}
.sg-v2-btn-secondary{background:var(--color-white);color:var(--color-ink-900);border-color:var(--color-ink-100);box-shadow:var(--shadow-sm);}
.sg-v2-btn:hover{transform:translateY(-1px);box-shadow:var(--shadow-md);}
.sg-hero-art{position:relative;min-height:148px;border-radius:var(--radius-xl);background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.18));overflow:hidden;}
.sg-hero-school{position:absolute;right:62px;bottom:22px;width:230px;height:96px;color:var(--color-brand-300);}
.sg-school-roof{position:absolute;left:40px;top:8px;width:150px;height:55px;border-top:9px solid currentColor;border-left:9px solid currentColor;transform:skewX(-18deg) rotate(0deg);opacity:.85;}
.sg-school-body{position:absolute;left:36px;bottom:0;width:158px;height:72px;border-radius:12px 14px 8px 8px;background:rgba(109,93,252,.28);box-shadow:inset 0 0 0 2px rgba(109,93,252,.18);}
.sg-school-body:before{content:"";position:absolute;left:67px;bottom:0;width:28px;height:42px;border-radius:12px 14px 0 0;background:rgba(109,93,252,.42);}
.sg-school-window{position:absolute;top:18px;width:22px;height:18px;border-radius:var(--radius-xs);background:rgba(255,255,255,.54);box-shadow:var(--shadow-lg);}
.sg-school-window:first-of-type{left:18px;}
.sg-school-flag{position:absolute;left:118px;top:-20px;width:3px;height:42px;background:rgba(109,93,252,.72);}
.sg-school-flag:after{content:"";position:absolute;left:3px;top:2px;width:34px;height:18px;border-radius:var(--radius-xs);background:rgba(109,93,252,.62);clip-path:polygon(0 0,100% 20%,0 100%);}
.sg-hero-cloud,.sg-hero-tree,.sg-hero-dot{position:absolute;opacity:.55;}
.sg-hero-cloud{width:86px;height:28px;border-radius:var(--radius-pill);background:rgba(255,255,255,.58);right:235px;top:30px;box-shadow:var(--shadow-xs);}
.sg-hero-tree{right:24px;bottom:24px;width:54px;height:72px;border-radius:22px 38px 12px 12px;background:rgba(109,93,252,.28);}
.sg-hero-tree:after{content:"";position:absolute;left:25px;bottom:-18px;width:4px;height:34px;border-radius:var(--radius-pill);background:rgba(109,93,252,.42);}
.sg-hero-dot{width:90px;height:90px;border-radius:50%;background:rgba(109,93,252,.16);left:42px;bottom:24px;}
.sg-kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--space-4);}
.sg-kpi-card{position:relative;overflow:hidden;display:grid;grid-template-columns:auto minmax(0,1fr);gap:var(--space-4);align-items:center;min-height:104px;padding:var(--space-5) var(--space-5);border-radius:var(--radius-xl);background:var(--color-white);border:1px solid rgba(28,32,54,.08);box-shadow:var(--shadow-md);}
.sg-kpi-card:after{content:"";position:absolute;right:-28px;top:-34px;width:92px;height:92px;border-radius:50%;background:var(--kpi-soft,var(--color-brand-50));}
.sg-kpi-icon{width:52px;height:52px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;background:var(--kpi-soft,var(--color-brand-50));color:var(--kpi-color,var(--sgv2-purple));position:relative;z-index:1;}
.sg-kpi-icon svg{width:24px;height:24px;stroke:currentColor;color:currentColor;fill:none;opacity:1;}
.sg-kpi-label{font-size:var(--fs-sm);font-weight:600;color:var(--color-slate-600);margin-bottom:6px;}
.sg-kpi-value{font-size:var(--fs-2xl);line-height:1;font-weight:700;letter-spacing:-.03em;color:var(--color-black);}
.sg-kpi-note{margin-top:7px;font-size:var(--fs-sm);font-weight:600;color:var(--color-ink-400);}
.sg-kpi-note strong{color:var(--kpi-color,var(--sgv2-purple));}
.sg-dash-grid{display:grid;grid-template-columns:1.08fr 1fr 1.08fr;gap:var(--space-5);align-items:stretch;}
.sg-dash-card{background:var(--color-white);border:1px solid rgba(30,34,60,.08);border-radius:var(--radius-xl);box-shadow:var(--shadow-md);overflow:hidden;min-width:0;}
.sg-dash-card-header{display:flex;align-items:center;justify-content:space-between;gap:var(--space-4);padding:var(--space-5) var(--space-6) var(--space-4);}
.sg-dash-title{display:flex;align-items:center;gap:var(--space-3);min-width:0;}
.sg-dash-title-icon{width:40px;height:40px;border-radius:var(--radius-md);background:var(--icon-bg,var(--color-brand-50));color:var(--icon-color,var(--sgv2-purple));display:flex;align-items:center;justify-content:center;flex:0 0 auto;}
.sg-dash-title-icon svg{width:20px;height:20px;stroke:currentColor;color:currentColor;fill:none;opacity:1;}
.sg-dash-title h3{margin:0;font-size:var(--fs-md);line-height:1.1;font-weight:700;letter-spacing:-.03em;color:var(--color-ink-500);}
.sg-dash-title p{margin:var(--space-1) 0 0;font-size:var(--fs-sm);font-weight:600;color:var(--color-ink-400);}
.sg-card-link{font-size:var(--fs-sm);font-weight:700;color:var(--sgv2-purple);text-decoration:none;background:var(--color-brand-50);border-radius:var(--radius-pill);padding:var(--space-2) var(--space-3);white-space:nowrap;}
.sg-dash-card-body{padding:var(--space-3) var(--space-6) var(--space-6);}
.sg-finance-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:var(--space-3);margin-bottom:18px;}
.sg-fin-mini{border-radius:var(--radius-md);background:var(--color-slate-50);border:1px solid var(--color-slate-100);padding:var(--space-3) var(--space-4);}
.sg-fin-mini span{display:block;font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--color-slate-500);margin-bottom:7px;}
.sg-fin-mini strong{display:block;font-size:var(--fs-lg);font-weight:700;color:var(--color-ink-500);letter-spacing:-.03em;}
.sg-fin-chart{height:150px;border-radius:var(--radius-lg);background:linear-gradient(180deg,var(--color-white) 0,var(--color-white) 100%);border:1px solid var(--color-slate-100);padding:var(--space-5) var(--space-4) var(--space-3);display:flex;align-items:flex-end;gap:var(--space-3);overflow:hidden;}
.sg-fin-bar{flex:1;min-width:14px;border-radius:999px 999px 6px 6px;background:linear-gradient(180deg,var(--color-success-600),var(--color-success-100));height:var(--h,50%);position:relative;}
.sg-fin-bar:after{content:"";position:absolute;left:50%;bottom:calc(var(--r,20%) * -1);transform:translateX(-50%);width:54%;height:var(--r,20%);border-radius:999px 999px 4px 4px;background:linear-gradient(180deg,var(--color-danger-400),var(--color-danger-100));}
.sg-fin-labels{display:flex;justify-content:space-between;margin-top:9px;color:var(--color-slate-400);font-size:var(--fs-xs);font-weight:600;}
.sg-donut-wrap{display:grid;grid-template-columns:190px minmax(0,1fr);gap:var(--space-6);align-items:center;}
.sg-donut{width:174px;height:174px;border-radius:50%;position:relative;box-shadow:inset 0 0 0 1px rgba(0,0,0,.04);}
.sg-donut:after{content:"";position:absolute;inset:36px;background:var(--color-white);border-radius:50%;box-shadow:var(--shadow-sm);}
.sg-donut-center{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:2;text-align:center;}
.sg-donut-center strong{font-size:var(--fs-2xl);font-weight:700;color:var(--color-ink-500);line-height:1;}
.sg-donut-center span{font-size:var(--fs-sm);color:var(--color-ink-400);font-weight:600;margin-top:4px;}
.sg-class-list{display:flex;flex-direction:column;gap:var(--space-3);}
.sg-class-row{display:grid;grid-template-columns:auto 1fr auto;gap:var(--space-2);align-items:center;font-size:var(--fs-sm);font-weight:600;color:var(--color-ink-800);}
.sg-class-dot{width:10px;height:10px;border-radius:50%;background:var(--dot,var(--color-brand-400));}
.sg-class-bar{height:7px;border-radius:var(--radius-pill);background:var(--color-slate-100);overflow:hidden;}
.sg-class-bar i{display:block;height:100%;width:var(--w,0%);border-radius:inherit;background:var(--dot,var(--color-brand-400));}
.sg-alert-stack{display:flex;flex-direction:column;gap:var(--space-3);}
.sg-alert-item{display:grid;grid-template-columns:auto 1fr auto;gap:var(--space-3);align-items:center;border-radius:var(--radius-md);padding:var(--space-3) var(--space-4);background:var(--alert-bg,var(--color-slate-50));border:1px solid var(--alert-line,var(--color-slate-100));}
.sg-alert-item .sg-alert-ico{width:34px;height:34px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;color:var(--alert-color,var(--color-brand-400));background:var(--color-white);box-shadow:var(--shadow-sm);}
.sg-alert-item svg{width:18px;height:18px;stroke:currentColor;color:currentColor;fill:none;opacity:1;}
.sg-alert-copy{font-size:var(--fs-sm);line-height:1.3;font-weight:600;color:var(--color-ink-800);}
.sg-alert-badge{min-width:31px;height:27px;border-radius:var(--radius-pill);display:flex;align-items:center;justify-content:center;padding:0 var(--space-2);font-size:var(--fs-sm);font-weight:700;background:var(--alert-color,var(--color-brand-400));color:var(--color-white);}
.sg-progress-stack{display:flex;flex-direction:column;gap:var(--space-4);}
.sg-progress-item{display:grid;grid-template-columns:minmax(145px,.8fr) 1fr auto;gap:var(--space-3);align-items:center;}
.sg-progress-label{font-size:var(--fs-sm);font-weight:700;color:var(--color-slate-800);}
.sg-progress-track{height:9px;border-radius:var(--radius-pill);background:var(--color-slate-100);overflow:hidden;}
.sg-progress-fill{height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--pcolor,var(--color-brand-400)),rgba(109,93,252,.45));width:var(--p,0%);}
.sg-progress-value{font-size:var(--fs-sm);font-weight:700;color:var(--color-ink-500);min-width:38px;text-align:right;}
.sg-day-summary{display:flex;flex-direction:column;gap:0;}
.sg-day-row{display:grid;grid-template-columns:auto 1fr auto;gap:var(--space-3);align-items:center;padding:var(--space-3) 0;border-bottom:1px solid var(--color-slate-100);}
.sg-day-row:last-child{border-bottom:0;}
.sg-day-icon{width:36px;height:36px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;background:var(--ibg,var(--color-brand-50));color:var(--icolor,var(--color-brand-400));}
.sg-day-icon svg{width:18px;height:18px;stroke:currentColor;color:currentColor;fill:none;opacity:1;}
.sg-day-text strong{display:block;font-size:var(--fs-sm);font-weight:700;color:var(--color-ink-800);}
.sg-day-text span{display:block;margin-top:3px;font-size:var(--fs-sm);font-weight:600;color:var(--color-ink-400);}
.sg-day-meta{font-size:var(--fs-sm);font-weight:700;color:var(--sgv2-purple);background:var(--color-brand-50);border-radius:var(--radius-pill);padding:var(--space-2) var(--space-2);}
.sg-quick-grid-v2{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:var(--space-3);}
.sg-quick-v2{min-height:76px;border:1px solid var(--color-slate-100);border-radius:var(--radius-lg);background:var(--color-white);display:flex;align-items:center;gap:var(--space-3);padding:var(--space-3);text-decoration:none;color:var(--color-ink-800);font-size:var(--fs-sm);font-weight:700;transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease;}
.sg-quick-v2:hover{transform:translateY(-1px);box-shadow:var(--shadow-sm);border-color:var(--color-info-100);color:var(--sgv2-purple);}
.sg-quick-v2 .sg-quick-ico{width:38px;height:38px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;background:var(--qbg,var(--color-brand-50));color:var(--qcolor,var(--sgv2-purple));flex:0 0 auto;}
.sg-quick-v2 svg{width:19px;height:19px;stroke:currentColor;color:currentColor;fill:none;opacity:1;}
.sg-span-2{grid-column:span 2;}
@media (max-width:1500px){.sg-dash-grid{grid-template-columns:1fr 1fr}.sg-span-2{grid-column:span 1}.sg-dash-hero{grid-template-columns:1fr}.sg-hero-art{display:none}}
@media (max-width:1100px){.sg-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.sg-dash-grid{grid-template-columns:1fr}.sg-donut-wrap{grid-template-columns:1fr}.sg-donut{margin:auto}.sg-quick-grid-v2{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:720px){.sg-dash-hero{padding:var(--space-6) var(--space-5)}.sg-hero-title{font-size:var(--fs-xl)}.sg-kpi-grid{grid-template-columns:1fr}.sg-finance-row{grid-template-columns:1fr}.sg-progress-item{grid-template-columns:1fr auto}.sg-progress-track{grid-column:1/-1}.sg-quick-grid-v2{grid-template-columns:1fr}}

/* v12.10.96 - Ajuste laptop 17" para cartões do dashboard */
@media (min-width:1101px) and (max-width:1600px){
    .sg-kpi-grid{grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:var(--space-4)!important;}
    .sg-kpi-card{padding:var(--space-4) var(--space-5)!important;gap:var(--space-3)!important;min-width:0!important;}
    .sg-kpi-label,.sg-kpi-note{overflow-wrap:anywhere!important;line-height:1.35!important;}
    .sg-dash-grid{grid-template-columns:1fr 1fr!important;}
    .sg-span-2{grid-column:span 1!important;}
    .sg-quick-grid-v2{grid-template-columns:repeat(2,minmax(0,1fr))!important;}
    .sg-quick-v2{min-width:0!important;white-space:normal!important;line-height:1.3!important;}
}


/* v12.11.9.64 - Painel Principal Mobile + Tablet UX PRO
   Reorganização visual e táctil sem alterar queries, cálculos ou regras de negócio. */
body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro{width:100%;max-width:100%;overflow-x:hidden;overflow-x:clip;}
body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-dash-shell,
body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-dash-hero,
body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-hero-copy{min-width:0;max-width:100%;}
body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro a{text-decoration:none;}
body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro a:focus-visible,
body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro button:focus-visible{outline:3px solid rgba(90,63,214,.28)!important;outline-offset:3px!important;}
.sg-hero-status-row{display:flex;flex-wrap:wrap;gap:var(--space-2);margin-top:16px;max-width:100%;min-width:0;}
.sg-hero-status-pill{display:inline-flex;align-items:center;gap:var(--space-2);min-height:34px;max-width:100%;min-width:0;padding:0 var(--space-3);border-radius:var(--radius-pill);background:rgba(255,255,255,.78);border:1px solid rgba(90,63,214,.10);box-shadow:var(--shadow-sm);color:var(--color-slate-800);font-size:var(--fs-sm);font-weight:700;white-space:nowrap;}
.sg-hero-status-pill strong{color:var(--sgv2-purple);font-weight:700;min-width:0;overflow-wrap:anywhere;}
.sg-hero-status-pill.is-warning strong{color:var(--color-warning-600);}
.sg-hero-status-pill.is-good strong{color:var(--color-success-500);}
.sg-dash-mobile-actions{display:none;}
.sg-dash-focus-strip{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:var(--space-4);}
.sg-focus-card{position:relative;overflow:hidden;min-height:104px;border-radius:var(--radius-xl);background:var(--color-white);border:1px solid rgba(28,32,54,.08);box-shadow:var(--shadow-md);padding:var(--space-4) var(--space-4);display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:var(--space-3);color:var(--color-ink-500);transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease;}
.sg-focus-card:before{content:"";position:absolute;right:-36px;top:-42px;width:110px;height:110px;border-radius:var(--radius-pill);background:var(--focus-soft,var(--color-brand-50));opacity:.9;}
.sg-focus-card:hover{transform:translateY(-1px);box-shadow:var(--shadow-md);border-color:rgba(90,63,214,.16);}
.sg-focus-icon{width:46px;height:46px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;background:var(--focus-soft,var(--color-brand-50));color:var(--focus-color,var(--sgv2-purple));position:relative;z-index:1;}
.sg-focus-icon svg{width:22px;height:22px;stroke:currentColor;color:currentColor;fill:none;}
.sg-focus-copy{position:relative;z-index:1;min-width:0;}
.sg-focus-copy span{display:block;font-size:var(--fs-sm);font-weight:700;color:var(--color-slate-500);margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.sg-focus-copy strong{display:block;font-size:var(--fs-xl);line-height:1;font-weight:700;letter-spacing:-.035em;color:var(--color-black);}
.sg-focus-copy small{margin-top:6px;font-size:var(--fs-sm);line-height:1.35;font-weight:600;color:var(--color-slate-500);display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;}
.sg-focus-arrow{position:relative;z-index:1;width:30px;height:30px;border-radius:var(--radius-pill);display:flex;align-items:center;justify-content:center;background:var(--focus-soft,var(--color-brand-50));color:var(--focus-color,var(--sgv2-purple));font-size:var(--fs-lg);font-weight:700;}
.sg-kpi-card{transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease;}
.sg-kpi-card:hover{transform:translateY(-1px);box-shadow:var(--shadow-md);border-color:rgba(90,63,214,.14);}
.sg-dash-card.sg-card-finance{order:1;}
.sg-dash-card.sg-card-classes{order:2;}
.sg-dash-card.sg-card-alerts{order:3;}
.sg-dash-card.sg-card-operational{order:4;}
.sg-dash-card.sg-card-checklist{order:5;}
.sg-card-checklist .sg-dash-title-icon{background:var(--color-success-50);color:var(--color-success-600);}
.sg-dash-card.sg-card-flow-guidance{order:6;}
.sg-card-flow-guidance .sg-dash-title-icon{background:var(--color-info-50);color:var(--color-info-500);}
.sg-flow-stack{display:flex;flex-direction:column;gap:var(--space-3);}
.sg-flow-card{display:grid;grid-template-columns:var(--space-10) minmax(0,1fr);gap:var(--space-3);border:1px solid var(--color-ink-100);border-radius:var(--radius-lg);padding:var(--space-3);background:var(--color-white);}
.sg-flow-card.is-attention{border-color:var(--color-warning-200);background:var(--color-warning-50);}
.sg-flow-ico{width:var(--space-10);height:var(--space-10);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;background:var(--flow-icon-bg,var(--color-info-50));color:var(--flow-icon-color,var(--color-info-500));}
.sg-flow-card.is-attention .sg-flow-ico{--flow-icon-bg:var(--color-warning-100);--flow-icon-color:var(--color-warning-600);}
.sg-flow-copy{min-width:0;}
.sg-flow-head{display:flex;align-items:center;justify-content:space-between;gap:var(--space-2);}
.sg-flow-head strong{font-size:var(--fs-sm);line-height:1.25;color:var(--color-ink-800);}
.sg-flow-badge{white-space:nowrap;border-radius:var(--radius-pill);padding:var(--space-1) var(--space-2);font-size:var(--fs-xs);font-weight:700;background:var(--color-slate-100);color:var(--color-slate-700);}
.sg-flow-card.is-attention .sg-flow-badge{background:var(--color-warning-100);color:var(--color-warning-600);}
.sg-flow-lead{margin:calc(var(--space-1) / 2) 0 0;font-size:var(--fs-xs);line-height:1.35;font-weight:600;color:var(--color-slate-600);}
.sg-flow-steps{margin:var(--space-2) 0 0;padding:0;list-style:none;display:grid;gap:var(--space-1);}
.sg-flow-steps li{display:flex;align-items:flex-start;gap:var(--space-2);font-size:var(--fs-xs);line-height:1.3;color:var(--color-ink-700);}
.sg-flow-step-num{flex:0 0 var(--space-5);height:var(--space-5);border-radius:var(--radius-pill);display:inline-flex;align-items:center;justify-content:center;background:var(--color-slate-100);color:var(--color-slate-700);font-weight:800;font-size:var(--fs-xs);}
.sg-flow-guardrail{margin-top:var(--space-2);font-size:var(--fs-xs);line-height:1.35;font-weight:600;color:var(--color-slate-600);}
.sg-flow-action{display:inline-flex;align-items:center;justify-content:center;margin-top:var(--space-2);min-height:var(--space-8);border-radius:var(--radius-pill);padding:0 var(--space-3);background:var(--color-white);color:var(--color-brand-500);box-shadow:var(--shadow-sm);font-size:var(--fs-xs);font-weight:800;text-decoration:none;}
.sg-flow-action:hover{color:var(--color-brand-600);box-shadow:var(--shadow-md);}
.sg-checklist-stack{display:flex;flex-direction:column;gap:var(--space-2);}
.sg-check-item{display:grid;grid-template-columns:var(--space-10) minmax(0,1fr) auto;gap:var(--space-3);align-items:center;text-decoration:none;border:1px solid var(--color-ink-100);border-radius:var(--radius-md);padding:var(--space-3);background:var(--color-white);color:inherit;transition:box-shadow var(--duration-fast) var(--ease-out),transform var(--duration-fast) var(--ease-out),border-color var(--duration-fast) var(--ease-out);}
.sg-check-item:hover{transform:translateY(calc(-1 * var(--space-1) / 4));box-shadow:var(--shadow-sm);border-color:var(--color-brand-100);}
.sg-check-ico{width:var(--space-10);height:var(--space-10);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;background:var(--check-bg,var(--color-brand-50));color:var(--check-color,var(--color-brand-500));}
.sg-check-copy{min-width:0;display:flex;flex-direction:column;gap:calc(var(--space-1) / 2);}
.sg-check-copy strong{font-size:var(--fs-sm);line-height:1.25;color:var(--color-ink-700);}
.sg-check-copy span{font-size:var(--fs-xs);line-height:1.35;color:var(--color-slate-600);}
.sg-check-badge{white-space:nowrap;border-radius:var(--radius-pill);padding:var(--space-1) var(--space-2);font-size:var(--fs-xs);font-weight:700;background:var(--check-badge-bg,var(--color-slate-100));color:var(--check-badge-color,var(--color-slate-700));}
.sg-check-status-attention{--check-bg:var(--color-warning-50);--check-color:var(--color-warning-600);--check-badge-bg:var(--color-warning-100);--check-badge-color:var(--color-warning-600);}
.sg-check-status-ok{--check-bg:var(--color-success-50);--check-color:var(--color-success-600);--check-badge-bg:var(--color-success-50);--check-badge-color:var(--color-success-600);}
.sg-check-status-next{--check-bg:var(--color-info-50);--check-color:var(--color-info-500);--check-badge-bg:var(--color-info-50);--check-badge-color:var(--color-info-500);}
.sg-dash-card.sg-card-day{order:5;}
.sg-dash-card.sg-card-quick{order:6;}
.sg-alert-item{color:inherit;text-decoration:none;transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease;grid-template-columns:auto minmax(0,1fr) auto auto;}
a.sg-alert-item:hover{transform:translateY(-1px);box-shadow:var(--shadow-sm);}
.sg-alert-copy strong{display:block;font-size:var(--fs-sm);line-height:1.3;font-weight:700;color:var(--color-ink-800);}
.sg-alert-copy span{display:block;margin-top:4px;font-size:var(--fs-xs);line-height:1.25;font-weight:600;color:var(--color-slate-500);}
.sg-alert-action{display:inline-flex;align-items:center;justify-content:center;min-height:27px;border-radius:var(--radius-pill);padding:0 var(--space-3);background:var(--color-white);color:var(--alert-color,var(--color-brand-400));font-size:var(--fs-xs);font-weight:700;box-shadow:var(--shadow-sm);white-space:nowrap;}
.sg-quick-label{min-width:0;overflow:hidden;text-overflow:ellipsis;}
.sg-card-link{min-height:34px;display:inline-flex;align-items:center;justify-content:center;}

@media (min-width:761px) and (max-width:1100px){
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-dash-shell{gap:var(--space-5)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-dash-hero{min-height:0!important;padding:var(--space-6) var(--space-6)!important;border-radius:var(--radius-xl)!important;grid-template-columns:1fr!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-hero-title{font-size:clamp(26px,3.6vw,34px)!important;line-height:1.1!important;max-width:100%!important;overflow-wrap:anywhere!important;text-wrap:balance!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-hero-subtitle{max-width:100%!important;font-size:var(--fs-base)!important;line-height:1.55!important;overflow-wrap:anywhere!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-hero-status-row{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:var(--space-3)!important;overflow:visible!important;margin:var(--space-4) 0 0!important;padding:0!important;scroll-snap-type:none!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-hero-status-pill{width:100%!important;min-width:0!important;justify-content:center!important;text-align:center!important;white-space:normal!important;line-height:1.25!important;padding:var(--space-2) var(--space-3)!important;min-height:38px!important;height:auto!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-hero-status-pill strong{white-space:normal!important;overflow-wrap:anywhere!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-hero-actions{display:none!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-dash-mobile-actions{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:var(--space-3)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-mobile-action-chip{min-height:78px;border-radius:var(--radius-xl);background:var(--color-white);border:1px solid rgba(28,32,54,.08);box-shadow:var(--shadow-md);display:flex;align-items:center;gap:var(--space-3);padding:var(--space-3);color:var(--color-ink-500);}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-mobile-action-ico{width:42px;height:42px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;background:var(--action-soft,var(--color-brand-50));color:var(--action-color,var(--sgv2-purple));flex:0 0 auto;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-mobile-action-ico svg{width:21px;height:21px;stroke:currentColor;color:currentColor;fill:none;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-mobile-action-chip strong{display:block;font-size:var(--fs-sm);font-weight:700;color:var(--color-ink-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-mobile-action-chip small{display:block;margin-top:4px;font-size:var(--fs-xs);font-weight:600;color:var(--color-ink-400);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-dash-focus-strip{grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:var(--space-3)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-focus-card{grid-template-columns:auto minmax(0,1fr)!important;padding:var(--space-4)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-focus-arrow{display:none!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:var(--space-4)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-dash-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:var(--space-4)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-span-2{grid-column:span 2!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-donut-wrap{grid-template-columns:160px minmax(0,1fr)!important;gap:var(--space-5)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-donut{width:156px!important;height:156px!important;}
}

@media (max-width:760px){
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro{margin:0!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-dash-shell{gap:var(--space-4)!important;padding-bottom:calc(96px + env(safe-area-inset-bottom,0px))!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-dash-hero{min-height:0!important;max-width:100%!important;overflow:clip!important;padding:var(--space-5) var(--space-4) var(--space-4)!important;border-radius:var(--radius-xl)!important;grid-template-columns:minmax(0,1fr)!important;background:linear-gradient(150deg,var(--color-white) 0%,var(--color-white) 58%,var(--color-brand-50) 100%)!important;box-shadow:var(--shadow-md);}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-hero-copy{min-width:0!important;max-width:100%!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-hero-kicker{font-size:10.5px!important;line-height:1.2!important;margin-bottom:8px!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-hero-title{font-size:clamp(22px,6.2vw,28px)!important;line-height:1.12!important;letter-spacing:-.035em!important;max-width:100%!important;overflow-wrap:anywhere!important;text-wrap:balance!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-hero-subtitle{font-size:13.5px!important;line-height:1.48!important;margin-top:10px!important;max-width:100%!important;overflow-wrap:anywhere!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-hero-status-row{display:grid!important;grid-template-columns:repeat(auto-fit,minmax(min(100%,148px),1fr))!important;overflow:visible!important;margin:var(--space-4) 0 0!important;padding:0!important;gap:var(--space-2)!important;scroll-snap-type:none!important;-webkit-overflow-scrolling:auto!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-hero-status-pill{width:100%!important;max-width:100%!important;min-width:0!important;scroll-snap-align:unset!important;justify-content:flex-start!important;min-height:36px!important;height:auto!important;font-size:10.8px!important;line-height:1.24!important;padding:var(--space-2) var(--space-3)!important;border-radius:var(--radius-md)!important;white-space:normal!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-hero-status-pill strong{min-width:0!important;overflow-wrap:anywhere!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-hero-actions{display:none!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-dash-mobile-actions{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:var(--space-3)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-mobile-action-chip{min-height:72px;border-radius:var(--radius-lg);background:var(--color-white);border:1px solid rgba(28,32,54,.08);box-shadow:var(--shadow-sm);display:flex;align-items:center;gap:var(--space-3);padding:var(--space-3);color:var(--color-ink-500);}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-mobile-action-ico{width:40px;height:40px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;background:var(--action-soft,var(--color-brand-50));color:var(--action-color,var(--sgv2-purple));flex:0 0 auto;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-mobile-action-ico svg{width:20px;height:20px;stroke:currentColor;color:currentColor;fill:none;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-mobile-action-chip strong{display:block;font-size:var(--fs-sm);font-weight:700;color:var(--color-ink-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-mobile-action-chip small{display:block;margin-top:3px;font-size:10.5px;font-weight:600;color:var(--color-ink-400);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-dash-focus-strip{display:grid!important;grid-template-columns:repeat(auto-fit,minmax(min(100%,230px),1fr))!important;gap:var(--space-3)!important;overflow:visible!important;margin:0!important;padding:0!important;scroll-snap-type:none!important;-webkit-overflow-scrolling:auto!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-focus-card{width:100%!important;min-width:0!important;scroll-snap-align:unset!important;grid-template-columns:auto minmax(0,1fr) auto!important;min-height:92px!important;padding:var(--space-4)!important;border-radius:var(--radius-lg)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-focus-icon{width:42px!important;height:42px!important;border-radius:var(--radius-lg)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-focus-copy strong{font-size:var(--fs-lg)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:var(--space-3)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-kpi-card{display:flex!important;flex-direction:column!important;align-items:flex-start!important;justify-content:space-between!important;min-height:132px!important;padding:var(--space-4)!important;border-radius:var(--radius-lg)!important;gap:var(--space-3)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-kpi-icon{width:42px!important;height:42px!important;border-radius:var(--radius-md)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-kpi-icon svg{width:21px;height:21px;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-kpi-label{font-size:var(--fs-xs)!important;line-height:1.25!important;margin-bottom:6px!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-kpi-value{font-size:var(--fs-lg)!important;line-height:1.05!important;overflow-wrap:anywhere!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-kpi-note{font-size:10.5px!important;line-height:1.25!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-dash-grid{grid-template-columns:1fr!important;gap:var(--space-4)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-span-2{grid-column:span 1!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-card-quick{order:1!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-card-alerts{order:2!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-card-operational{order:3!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-card-checklist{order:4!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-card-flow-guidance{order:5!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-flow-card{grid-template-columns:var(--space-8) minmax(0,1fr)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-flow-ico{width:var(--space-8)!important;height:var(--space-8)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-flow-head{align-items:flex-start!important;flex-direction:column!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-check-item{grid-template-columns:var(--space-8) minmax(0,1fr);}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-check-badge{grid-column:2;justify-self:start;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-card-finance{order:4!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-card-day{order:5!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-card-classes{order:6!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-dash-card{border-radius:var(--radius-xl)!important;box-shadow:var(--shadow-md);}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-dash-card-header{align-items:flex-start!important;gap:var(--space-3)!important;padding:var(--space-4) var(--space-4) var(--space-3)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-dash-card-body{padding:var(--space-2) var(--space-4) var(--space-4)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-dash-title h3{font-size:var(--fs-md)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-dash-title p{font-size:var(--fs-xs)!important;line-height:1.35!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-card-link{min-height:32px!important;font-size:var(--fs-xs)!important;padding:var(--space-2) var(--space-3)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-finance-row{grid-template-columns:1fr!important;gap:var(--space-2)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-fin-chart{height:126px!important;gap:var(--space-2)!important;padding:var(--space-4) var(--space-3) var(--space-3)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-donut-wrap{grid-template-columns:1fr!important;gap:var(--space-4)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-donut{width:148px!important;height:148px!important;margin:auto!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-alert-item{grid-template-columns:auto minmax(0,1fr) auto!important;gap:var(--space-3)!important;padding:var(--space-3)!important;border-radius:var(--radius-md)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-alert-action{grid-column:2 / -1!important;justify-self:start!important;min-height:25px!important;font-size:10.5px!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-progress-item{grid-template-columns:1fr auto!important;gap:var(--space-2)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-progress-track{grid-column:1/-1!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-day-row{grid-template-columns:auto 1fr!important;align-items:flex-start!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-day-meta{grid-column:2!important;justify-self:start!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-quick-grid-v2{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:var(--space-2)!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-quick-v2{min-height:72px!important;border-radius:var(--radius-lg)!important;padding:var(--space-3)!important;flex-direction:column!important;align-items:flex-start!important;justify-content:center!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-quick-v2 .sg-quick-ico{width:36px!important;height:36px!important;border-radius:var(--radius-md)!important;}
}

@media (max-width:380px){
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-kpi-grid,
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-dash-mobile-actions,
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-quick-grid-v2{grid-template-columns:1fr!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-hero-status-row{grid-template-columns:1fr!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-focus-card{width:100%!important;min-width:0!important;}
}

@media (max-width:460px){
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-hero-status-row{grid-template-columns:1fr!important;}
    body.sige-admin-app .sg-dashboard-v2.sg-dashboard-mobile-pro .sg-hero-title{font-size:clamp(21px,6.6vw,26px)!important;}
}


/* Utilitárias semânticas (consolidação CSS v104+; substituem style= inline equivalentes) */
.sg-val-pos{color:var(--color-success-500);}   /* valor positivo: receitas recebidas */
.sg-val-neg{color:var(--color-danger-500);}   /* valor de atenção: lançado no mês */
.sg-val-hi{color:var(--color-brand-500);}    /* valor de destaque: taxa de cobrança */
.sg-class-bar{grid-column:2 / 4;}  /* barra de distribuição ocupa colunas 2-4 */
</style>

<div class="sg-dashboard-v2 sg-dashboard-mobile-pro" data-sg-dashboard-ux="12.11.9.67">
    <div class="sg-dash-shell">
        <section class="sg-dash-hero" aria-label="Resumo do painel principal">
            <div class="sg-hero-copy">
                <div class="sg-hero-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('dashboard') : ''; ?> Painel Principal</div>
                <h1 class="sg-hero-title">Bem-vindo de volta, <?php echo esc_html(wp_get_current_user()->first_name ?: wp_get_current_user()->display_name ?: 'Admin'); ?>! 👋</h1>
                <p class="sg-hero-subtitle"><?php echo esc_html($__sg_hero_subtitle); ?></p>
                <div class="sg-hero-status-row" aria-label="Estado rápido do painel">
                    <span class="sg-hero-status-pill">Hoje · <strong><?php echo esc_html($__sg_today_label); ?></strong></span>
                    <span class="sg-hero-status-pill <?php echo $__sg_alertas_total > 0 ? 'is-warning' : 'is-good'; ?>">Alertas · <strong><?php echo esc_html($__sg_alert_state_label); ?></strong></span>
                    <?php if ($__sg_can_financeiro): ?><span class="sg-hero-status-pill">Cobrança · <strong><?php echo esc_html($__sg_recebimento_label); ?></strong></span><?php endif; ?>
                </div>
                <?php if ($__sg_can_pagamentos || $__sg_can_alunos): ?>
                <div class="sg-hero-actions">
                    <?php if ($__sg_can_pagamentos): ?>
                    <a href="<?php echo esc_url($__sg_url('financeiro-pagamentos')); ?>" class="sg-v2-btn sg-v2-btn-primary">
                        <?php echo function_exists('sige_ui_icon') ? sige_ui_icon('wallet') : ''; ?> Registar Pagamento
                    </a>
                    <?php endif; ?>
                    <?php if ($__sg_can_alunos): ?>
                    <a href="<?php echo esc_url($__sg_url('alunos_lista')); ?>" class="sg-v2-btn sg-v2-btn-secondary">
                        <?php echo function_exists('sige_ui_icon') ? sige_ui_icon('users') : ''; ?> Gerir Alunos
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="sg-hero-art" aria-hidden="true">
                <div class="sg-hero-dot"></div>
                <div class="sg-hero-cloud"></div>
                <div class="sg-hero-tree"></div>
                <div class="sg-hero-school">
                    <div class="sg-school-roof"></div>
                    <div class="sg-school-flag"></div>
                    <div class="sg-school-body"><div class="sg-school-window"></div></div>
                </div>
            </div>
        </section>

        <?php if (!empty($__sg_dashboard_actions)): ?>
        <section class="sg-dash-mobile-actions" aria-label="Acções rápidas prioritárias">
            <?php foreach ($__sg_dashboard_actions as $__sg_action): ?>
                <a class="sg-mobile-action-chip" href="<?php echo esc_url((string)$__sg_action['href']); ?>" style="--action-color:<?php echo esc_attr((string)$__sg_action['color']); ?>;--action-soft:<?php echo esc_attr((string)$__sg_action['soft']); ?>;" aria-label="<?php echo esc_attr((string)$__sg_action['label'] . ' - ' . (string)$__sg_action['hint']); ?>">
                    <span class="sg-mobile-action-ico"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon((string)$__sg_action['icon']) : ''; ?></span>
                    <span><strong><?php echo esc_html((string)$__sg_action['label']); ?></strong><small><?php echo esc_html((string)$__sg_action['hint']); ?></small></span>
                </a>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <?php if (!empty($__sg_focus_cards)): ?>
        <section class="sg-dash-focus-strip" aria-label="Prioridades rápidas do painel">
            <?php foreach ($__sg_focus_cards as $__sg_focus): ?>
                <a class="sg-focus-card" href="<?php echo esc_url((string)$__sg_focus['href']); ?>" style="--focus-color:<?php echo esc_attr((string)$__sg_focus['color']); ?>;--focus-soft:<?php echo esc_attr((string)$__sg_focus['soft']); ?>;" aria-label="<?php echo esc_attr((string)$__sg_focus['label'] . ': ' . (string)$__sg_focus['value']); ?>">
                    <span class="sg-focus-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon((string)$__sg_focus['icon']) : ''; ?></span>
                    <span class="sg-focus-copy"><span><?php echo esc_html((string)$__sg_focus['label']); ?></span><strong><?php echo esc_html((string)$__sg_focus['value']); ?></strong><small><?php echo esc_html((string)$__sg_focus['hint']); ?></small></span>
                    <span class="sg-focus-arrow" aria-hidden="true">›</span>
                </a>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <?php if ($__sg_has_kpis): ?>
        <section class="sg-kpi-grid" aria-label="Indicadores principais">
            <?php if ($__sg_can_alunos || $__sg_can_academico): ?>
            <article class="sg-kpi-card" style="--kpi-color:var(--color-brand-500);--kpi-soft:var(--color-brand-50);">
                <div class="sg-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('users') : ''; ?></div>
                <div><div class="sg-kpi-label">Alunos Activos</div><div class="sg-kpi-value"><?php echo (int)$total_alunos_activos; ?></div><div class="sg-kpi-note">♂ <strong><?php echo (int)$masculino; ?></strong> · ♀ <strong><?php echo (int)$feminino; ?></strong></div></div>
            </article>
            <?php endif; ?>
            <?php if ($__sg_can_equipa || $__sg_can_academico): ?>
            <article class="sg-kpi-card" style="--kpi-color:var(--color-success-500);--kpi-soft:var(--color-success-100);">
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
            <article class="sg-kpi-card" style="--kpi-color:var(--color-warning-600);--kpi-soft:var(--color-warning-100);">
                <div class="sg-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('trending') : ''; ?></div>
                <div><div class="sg-kpi-label">Dívida Total</div><div class="sg-kpi-value"><?php echo esc_html(dash_fmt($total_divida)); ?> MT</div><div class="sg-kpi-note">Devedores: <strong><?php echo (int)$alunos_com_divida; ?></strong></div></div>
            </article>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="sg-dash-grid" aria-label="Áreas do painel">
            <?php if ($__sg_can_financeiro): ?>
            <article class="sg-dash-card sg-span-2 sg-card-finance">
                <div class="sg-dash-card-header">
                    <div class="sg-dash-title"><div class="sg-dash-title-icon" style="--icon-bg:var(--color-brand-50);--icon-color:var(--color-brand-400);"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('chart') : ''; ?></div><div><h3>Resumo Financeiro</h3><p>Este mês · <?php echo esc_html($nome_mes); ?> <?php echo esc_html($ano); ?></p></div></div>
                    <?php if ($__sg_can_fin_dashboard): ?><a class="sg-card-link" href="<?php echo esc_url($__sg_url('financeiro-dashboard')); ?>">Ver relatório →</a><?php endif; ?>
                </div>
                <div class="sg-dash-card-body">
                    <div class="sg-finance-row">
                        <div class="sg-fin-mini"><span>Receitas</span><strong class="sg-val-pos"><?php echo esc_html(dash_fmt($receita_mes)); ?> MT</strong></div>
                        <div class="sg-fin-mini"><span>Lançado no mês</span><strong class="sg-val-neg"><?php echo esc_html(dash_fmt($lancado_mes)); ?> MT</strong></div>
                        <div class="sg-fin-mini"><span>Taxa de cobrança</span><strong class="sg-val-hi"><?php echo (int)$__sg_taxa_recebimento; ?>%</strong></div>
                    </div>
                    <div class="sg-fin-chart" aria-hidden="true">
                        <div class="sg-fin-bar" style="--h:45%;--r:16%;"></div>
                        <div class="sg-fin-bar" style="--h:55%;--r:20%;"></div>
                        <div class="sg-fin-bar" style="--h:74%;--r:34%;"></div>
                        <div class="sg-fin-bar" style="--h:64%;--r:24%;"></div>
                        <div class="sg-fin-bar" style="--h:<?php echo max(12,(int)$__sg_taxa_recebimento); ?>%;--r:<?php echo max(8,100-(int)$__sg_taxa_recebimento); ?>%;"></div>
                    </div>
                    <div class="sg-fin-labels"><span>Jan</span><span>Fev</span><span>Mar</span><span>Abr</span><span><?php echo esc_html(substr($nome_mes,0,3)); ?></span></div>
                </div>
            </article>
            <?php endif; ?>

            <?php if ($__sg_can_alunos || $__sg_can_turmas || $__sg_can_academico): ?>
            <article class="sg-dash-card sg-card-classes">
                <div class="sg-dash-card-header">
                    <div class="sg-dash-title"><div class="sg-dash-title-icon" style="--icon-bg:var(--color-info-50);--icon-color:var(--color-info-400);"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('grid') : ''; ?></div><div><h3>Distribuição por Classe</h3><p>Alunos activos por classe</p></div></div>
                </div>
                <div class="sg-dash-card-body">
                    <div class="sg-donut-wrap">
                        <div class="sg-donut" style="<?php echo esc_attr($__sg_donut_style); ?>"><div class="sg-donut-center"><strong><?php echo (int)$__sg_total_dist; ?></strong><span>alunos</span></div></div>
                        <div class="sg-class-list">
                            <?php foreach ($__sg_dist_top as $__sg_i => $__sg_d): $__sg_pct = $__sg_total_dist > 0 ? round(((int)($__sg_d->n_alunos ?? 0) / max(1,$__sg_total_dist)) * 100) : 0; $__sg_color = $__sg_dist_colors[$__sg_i % count($__sg_dist_colors)]; ?>
                                <div class="sg-class-row" style="--dot:<?php echo esc_attr($__sg_color); ?>;--w:<?php echo (int)$__sg_pct; ?>%;"><span class="sg-class-dot"></span><span><?php echo esc_html($__sg_d->classe ?? 'Classe'); ?></span><strong><?php echo (int)($__sg_d->n_alunos ?? 0); ?></strong><div class="sg-class-bar"><i></i></div></div>
                            <?php endforeach; ?>
                            <?php if (empty($__sg_dist_top)): ?><div class="sg-day-text"><strong>Sem distribuição disponível</strong><span>Quando houver turmas com alunos, o resumo aparece aqui.</span></div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </article>
            <?php endif; ?>

            <?php if (!empty($__sg_alertas)): ?>
            <article class="sg-dash-card sg-card-alerts">
                <div class="sg-dash-card-header">
                    <div class="sg-dash-title"><div class="sg-dash-title-icon" style="--icon-bg:var(--color-warning-100);--icon-color:var(--color-warning-500);"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('shield') : ''; ?></div><div><h3>Alertas de Conformidade</h3><p>Situações que pedem atenção</p></div></div>
                    <span class="sg-card-link"><?php echo (int)$__sg_alertas_total; ?> alerta(s)</span>
                </div>
                <div class="sg-dash-card-body">
                    <div class="sg-alert-stack">
                        <?php foreach ($__sg_alertas as $__sg_a):
                            $__sg_tipo = $__sg_a['tipo'] ?? 'ok';
                            $__sg_color = $__sg_tipo === 'err' ? 'var(--color-danger-500)' : ($__sg_tipo === 'warn' ? 'var(--color-warning-500)' : 'var(--color-success-600)');
                            $__sg_bg = $__sg_tipo === 'err' ? 'var(--color-danger-50)' : ($__sg_tipo === 'warn' ? 'var(--color-warning-50)' : 'var(--color-success-50)');
                            $__sg_line = $__sg_tipo === 'err' ? 'var(--color-danger-200)' : ($__sg_tipo === 'warn' ? 'var(--color-warning-200)' : 'var(--color-success-200)');
                            $__sg_alert_href = (string)($__sg_a['href'] ?? '');
                            $__sg_alert_action = (string)($__sg_a['action'] ?? 'Abrir');
                        ?>
                            <?php if ($__sg_alert_href !== ''): ?>
                                <a class="sg-alert-item" href="<?php echo esc_url($__sg_alert_href); ?>" style="--alert-color:<?php echo esc_attr($__sg_color); ?>;--alert-bg:<?php echo esc_attr($__sg_bg); ?>;--alert-line:<?php echo esc_attr($__sg_line); ?>;" aria-label="<?php echo esc_attr($__sg_alert_action . ': ' . (string)$__sg_a['label']); ?>">
                                    <span class="sg-alert-ico"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon((string)$__sg_a['icon']) : ''; ?></span>
                                    <span class="sg-alert-copy"><strong><?php echo esc_html((string)$__sg_a['label']); ?></strong><span><?php echo (int)$__sg_a['val'] > 0 ? esc_html($__sg_alert_action) : 'Sem pendência activa'; ?></span></span>
                                    <span class="sg-alert-badge"><?php echo (int)$__sg_a['val']; ?></span>
                                    <span class="sg-alert-action"><?php echo (int)$__sg_a['val'] > 0 ? 'Abrir' : 'Ver'; ?></span>
                                </a>
                            <?php else: ?>
                                <div class="sg-alert-item" style="--alert-color:<?php echo esc_attr($__sg_color); ?>;--alert-bg:<?php echo esc_attr($__sg_bg); ?>;--alert-line:<?php echo esc_attr($__sg_line); ?>;">
                                    <span class="sg-alert-ico"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon((string)$__sg_a['icon']) : ''; ?></span>
                                    <span class="sg-alert-copy"><strong><?php echo esc_html((string)$__sg_a['label']); ?></strong><span>Sem acção directa</span></span>
                                    <span class="sg-alert-badge"><?php echo (int)$__sg_a['val']; ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>
            <?php endif; ?>

            <?php if (!empty($__sg_operational_checklist)): ?>
            <article class="sg-dash-card sg-card-checklist">
                <div class="sg-dash-card-header">
                    <div class="sg-dash-title"><div class="sg-dash-title-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('check') : ''; ?></div><div><h3>Checklist Operacional</h3><p>Próximos passos do seu perfil</p></div></div>
                </div>
                <div class="sg-dash-card-body">
                    <div class="sg-checklist-stack">
                        <?php foreach ($__sg_operational_checklist as $__sg_check):
                            $__sg_check_status = (string)($__sg_check['status'] ?? 'next');
                            if (!in_array($__sg_check_status, ['attention', 'ok', 'next'], true)) { $__sg_check_status = 'next'; }
                        ?>
                            <a class="sg-check-item sg-check-status-<?php echo esc_attr($__sg_check_status); ?>" href="<?php echo esc_url((string)($__sg_check['href'] ?? '')); ?>">
                                <span class="sg-check-ico"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon((string)($__sg_check['icon'] ?? 'check')) : ''; ?></span>
                                <span class="sg-check-copy"><strong><?php echo esc_html((string)($__sg_check['label'] ?? 'Tarefa operacional')); ?></strong><span><?php echo esc_html((string)($__sg_check['state_label'] ?? 'Abrir tarefa')); ?></span></span>
                                <span class="sg-check-badge"><?php echo esc_html((string)($__sg_check['badge'] ?? 'Abrir')); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>
            <?php endif; ?>

            <?php if (!empty($__sg_operational_flows)): ?>
            <article class="sg-dash-card sg-card-flow-guidance">
                <div class="sg-dash-card-header">
                    <div class="sg-dash-title"><div class="sg-dash-title-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('activity') : ''; ?></div><div><h3>Fluxos Guiados</h3><p>Orientação segura para tarefas críticas</p></div></div>
                </div>
                <div class="sg-dash-card-body">
                    <div class="sg-flow-stack">
                        <?php foreach ($__sg_operational_flows as $__sg_flow):
                            $__sg_flow_status = (string)($__sg_flow['status'] ?? 'guided');
                            if (!in_array($__sg_flow_status, ['attention', 'guided'], true)) { $__sg_flow_status = 'guided'; }
                            $__sg_flow_steps = isset($__sg_flow['steps']) && is_array($__sg_flow['steps']) ? $__sg_flow['steps'] : [];
                        ?>
                            <div class="sg-flow-card <?php echo $__sg_flow_status === 'attention' ? 'is-attention' : 'is-guided'; ?>">
                                <span class="sg-flow-ico"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon((string)($__sg_flow['icon'] ?? 'check')) : ''; ?></span>
                                <div class="sg-flow-copy">
                                    <div class="sg-flow-head"><strong><?php echo esc_html((string)($__sg_flow['title'] ?? 'Fluxo guiado')); ?></strong><span class="sg-flow-badge"><?php echo esc_html((string)($__sg_flow['badge'] ?? 'Guiado')); ?></span></div>
                                    <p class="sg-flow-lead"><?php echo esc_html((string)($__sg_flow['lead'] ?? 'Orientação operacional')); ?></p>
                                    <?php if (!empty($__sg_flow_steps)): ?>
                                        <ol class="sg-flow-steps">
                                            <?php foreach ($__sg_flow_steps as $__sg_i => $__sg_step): ?>
                                                <li><span class="sg-flow-step-num"><?php echo (int)($__sg_i + 1); ?></span><span><?php echo esc_html((string)$__sg_step); ?></span></li>
                                            <?php endforeach; ?>
                                        </ol>
                                    <?php endif; ?>
                                    <?php if (!empty($__sg_flow['guardrail'])): ?><div class="sg-flow-guardrail"><?php echo esc_html((string)$__sg_flow['guardrail']); ?></div><?php endif; ?>
                                    <?php if (!empty($__sg_flow['href'])): ?><a class="sg-flow-action" href="<?php echo esc_url((string)$__sg_flow['href']); ?>"><?php echo esc_html((string)($__sg_flow['action_label'] ?? 'Abrir')); ?></a><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>
            <?php endif; ?>

            <?php if (!empty($__sg_progress_items)): ?>
            <article class="sg-dash-card sg-card-operational">
                <div class="sg-dash-card-header">
                    <div class="sg-dash-title"><div class="sg-dash-title-icon" style="--icon-bg:var(--color-info-50);--icon-color:var(--color-info-500);"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('activity') : ''; ?></div><div><h3>Visão Operacional</h3><p>Indicadores de acompanhamento</p></div></div>
                </div>
                <div class="sg-dash-card-body">
                    <div class="sg-progress-stack">
                        <?php foreach ($__sg_progress_items as $__sg_item): ?>
                            <div class="sg-progress-item" style="--p:<?php echo esc_attr((string)$__sg_item['p']); ?>;--pcolor:<?php echo esc_attr((string)$__sg_item['color']); ?>;"><div class="sg-progress-label"><?php echo esc_html((string)$__sg_item['label']); ?></div><div class="sg-progress-track"><div class="sg-progress-fill"></div></div><div class="sg-progress-value"><?php echo esc_html((string)$__sg_item['value']); ?></div></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>
            <?php endif; ?>

            <?php if (!empty($__sg_day_rows)): ?>
            <article class="sg-dash-card sg-card-day">
                <div class="sg-dash-card-header">
                    <div class="sg-dash-title"><div class="sg-dash-title-icon" style="--icon-bg:var(--color-brand-50);--icon-color:var(--color-brand-400);"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('calendar') : ''; ?></div><div><h3>Resumo do Dia</h3><p>Leitura rápida da operação</p></div></div>
                </div>
                <div class="sg-dash-card-body">
                    <div class="sg-day-summary">
                        <?php foreach ($__sg_day_rows as $__sg_row): ?>
                            <div class="sg-day-row"><div class="sg-day-icon" style="--ibg:<?php echo esc_attr((string)$__sg_row['ibg']); ?>;--icolor:<?php echo esc_attr((string)$__sg_row['icolor']); ?>;"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon((string)$__sg_row['icon']) : ''; ?></div><div class="sg-day-text"><strong><?php echo esc_html((string)$__sg_row['title']); ?></strong><span><?php echo esc_html((string)$__sg_row['text']); ?></span></div><div class="sg-day-meta"><?php echo esc_html((string)$__sg_row['meta']); ?></div></div>
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
                        <?php if (empty($__sg_quick_links)): ?><div class="sg-day-text"><strong>Sem atalhos disponíveis</strong><span>O painel está em modo de consulta conforme as permissões do seu perfil.</span></div><?php endif; ?>
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
