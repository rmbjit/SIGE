<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$read = static function(string $rel) use ($root): string {
    $path = $root . '/' . $rel;
    return is_file($path) ? file_get_contents($path) : '';
};
$main = $read('sige-softgenial.php');
$build = $read('BUILD.json');
$alunos = $read('admin/academic/alunos_lista.php');
$fetch = $read('includes/aluno-fetch-ajax.php');
$dash = $read('admin/system/dashboard-view.php');
$shell = $read('includes/admin-shell.php');
$roles = $read('includes/security-roles.php');
$perm = $read('includes/permissions-layer.php');
$css = $read('assets/mobile-tablet-ux.css');
$js = $read('assets/mobile-tablet-ux.js');
$changelog = $read('CHANGELOG-v12-11-9-68-alunos-mobile-tablet-ux-pro.txt');

$strip_comments = static function(string $text): string {
    return preg_replace('/\/\*.*?\*\/|\/\/.*|#.*$/ms', '', $text) ?? $text;
};
$alunos_no_comments = $strip_comments($alunos);
$mutation_free_alunos = !preg_match('/\b(UPDATE|INSERT|DELETE|ALTER|DROP|TRUNCATE|CREATE)\b/i', $alunos_no_comments);

$bottom_nav = '';
if (preg_match('/<nav class="sige-mobile-bottom-nav".*?<\/nav>/s', $alunos, $m)) {
    $bottom_nav = $m[0];
}

$checks = [
    // Versionamento e artefactos
    'version_main_header_12_11_9_68' => strpos($main, 'Version: 12.11.9.68') !== false,
    'version_constant_12_11_9_68' => strpos($main, "define('SIGE_VERSION', '12.11.9.68');") !== false,
    'build_version_12_11_9_68' => strpos($build, '12.11.9.68') !== false && strpos($build, 'alunos-mobile-tablet-ux-pro') !== false,
    'changelog_exists_and_mentions_blindagem' => strpos($changelog, 'Alunos Mobile + Tablet UX PRO') !== false && strpos($changelog, 'Sem alteração de schema') !== false && strpos($changelog, 'Sem alteração de cálculos financeiros') !== false,

    // Guard e permissões no módulo Alunos
    'alunos_page_guard_still_allows_view_only' => strpos($alunos, "['alunos.ver']") !== false && strpos($alunos, "'sige_guarda'") !== false,
    'alunos_create_edit_delete_flags_preserved' => strpos($alunos, '$sige_alunos_can_create') !== false && strpos($alunos, '$sige_alunos_can_edit') !== false && strpos($alunos, '$sige_alunos_can_delete') !== false,
    'alunos_read_only_banner_preserved' => strpos($alunos, '$sige_alunos_read_only') !== false && strpos($alunos, 'sige-alunos-readonly-banner') !== false,
    'alunos_guarda_document_export_portal_whatsapp_blocked' => strpos($alunos, '$sige_alunos_can_documents = !$sige_alunos_is_guarda') !== false && strpos($alunos, '$sige_alunos_can_export = !$sige_alunos_is_guarda') !== false && strpos($alunos, '$sige_alunos_can_portal_access = !$sige_alunos_is_guarda') !== false && strpos($alunos, '$sige_alunos_can_whatsapp = !$sige_alunos_is_guarda') !== false,
    'alunos_permission_helper_uses_page_guard_as_source_of_truth' => strpos($alunos, '$sige_alunos_can_any') !== false && strpos($alunos, 'return (bool)sige_page_guard_allows($permissions, $legacy_caps);') !== false && strpos($alunos, 'matriz SIGE é soberana') !== false,
    'alunos_finance_view_and_pay_scopes' => strpos($alunos, '$sige_alunos_can_finance_view') !== false && strpos($alunos, '$sige_alunos_can_finance_pay') !== false && strpos($alunos, "'financeiro.pagar'") !== false,
    'alunos_dashboard_nav_matches_dashboard_guard' => strpos($alunos, '$sige_alunos_can_dashboard_nav') !== false && strpos($alunos, "'academico.dashboard_ver','financeiro.dashboard_ver','rh.equipe_ver','sistema.estado_ver'") !== false,

    // Fit-on-screen e UX mobile/tablet
    'alunos_ux68_css_marker_present' => strpos($alunos, 'v12.11.9.68 - Alunos Mobile + Tablet UX PRO') !== false,
    'alunos_global_overflow_hidden' => strpos($alunos, 'overflow-x:hidden!important') !== false,
    'alunos_content_max_width_guard' => strpos($alunos, 'max-width:100%!important') !== false && strpos($alunos, 'min-width:0!important') !== false,
    'alunos_hero_art_hidden_mobile_tablet' => substr_count($alunos, '.sige-hero-art') >= 2 && strpos($alunos, 'display:none!important') !== false,
    'alunos_flow_guide_present' => strpos($alunos, 'sige-alunos-flow-guide') !== false && strpos($alunos, 'Pesquisar') !== false && strpos($alunos, 'Abrir Ficha 360º') !== false,
    'alunos_tablet_breakpoint_present' => strpos($alunos, '@media (min-width:761px) and (max-width:1100px)') !== false,
    'alunos_mobile_breakpoint_present' => strpos($alunos, '@media (max-width:760px)') !== false,
    'alunos_very_small_breakpoints_present' => strpos($alunos, '@media (max-width:390px)') !== false && strpos($alunos, '@media (max-width:340px)') !== false,
    'alunos_mobile_chips_grid_no_horizontal_carousel' => strpos($alunos, '.sige-mobile-status-chips') !== false && strpos($alunos, 'grid-template-columns:repeat(auto-fit,minmax(74px,1fr))!important') !== false && strpos($alunos, 'overflow:visible!important') !== false,
    'alunos_bottom_nav_variable_grid' => strpos($alunos, '.sige-mobile-bottom-nav') !== false && strpos($alunos, 'grid-template-columns:repeat(auto-fit,minmax(64px,1fr))!important') !== false,
    'alunos_modal_fit_uses_dvh' => strpos($alunos, 'height:calc(100dvh - 16px)!important') !== false && strpos($alunos, 'overflow-x:hidden!important') !== false,

    // Filtros, acessibilidade e cards
    'mobile_status_chips_have_toolbar_and_aria' => strpos($alunos, 'role="toolbar"') !== false && strpos($alunos, 'aria-pressed="true"') !== false && strpos($alunos, 'aria-pressed="false"') !== false,
    'mobile_live_region_present' => strpos($alunos, 'sige-alunos-mobile-live') !== false && strpos($alunos, 'aria-live="polite"') !== false,
    'devedores_chip_permission_gated' => strpos($alunos, 'data-sige-mobile-chip="devedores"') !== false && strpos($alunos, 'if ($sige_alunos_can_finance_view):') !== false,
    'finance_query_permission_gated' => strpos($alunos, 'if ($sige_alunos_can_finance_view && !empty($alunos) && sige_table_exists') !== false,
    'finance_badge_permission_gated' => strpos($alunos, 'class="sige-finance-badge') !== false && strpos($alunos, 'if ($sige_alunos_can_finance_view):') !== false,
    'student_cards_have_has_debt_data_attr' => strpos($alunos, 'data-has-debt="<?php echo ($sige_alunos_can_finance_view') !== false,
    'student_cards_hidden_classes_declared' => strpos($alunos, 'is-search-hidden') !== false && strpos($alunos, 'is-mobile-chip-hidden') !== false,
    'actions_menu_escape_close_present' => strpos($alunos, "ev.key === 'Escape'") !== false && strpos($alunos, "details.sige-card-actions[open]") !== false,

    // JS UX v68
    'alunos_ux68_js_marker_present' => strpos($alunos, 'sige-alunos-mobile-tablet-ux-v1211968') !== false,
    'js_combines_search_and_quick_filter' => strpos($alunos, 'activeQuickFilter') !== false && strpos($alunos, 'cardMatchesSearch') !== false && strpos($alunos, 'cardMatchesQuickFilter') !== false && strpos($alunos, 'applyAllClientFilters') !== false,
    'js_wraps_legacy_filters' => strpos($alunos, 'window.filtrarAlunosClient') !== false && strpos($alunos, 'window.filtrarAlunos = window.filtrarAlunosClient') !== false,
    'js_updates_visible_count_live_region' => strpos($alunos, 'updateVisibleCount') !== false && strpos($alunos, "$('.sige-alunos-mobile-live').text(msg)") !== false,
    'js_clamps_action_menu' => strpos($alunos, 'clampOpenActionMenu') !== false && strpos($alunos, 'getBoundingClientRect') !== false,
    'js_marks_body_ready' => strpos($alunos, 'sige-alunos-ux68-ready') !== false,

    // Ficha 360º e payload AJAX
    'ficha360_intro_adaptive_to_finance_scope' => strpos($alunos, '$sige_alunos_can_finance_view ?') !== false && strpos($alunos, 'sem escopo financeiro') === false,
    'ficha360_ajax_finance_helper_present' => strpos($fetch, 'sige_alunos_user_can_view_finance') !== false && strpos($fetch, 'Quem só pode consultar alunos não recebe resumo financeiro') !== false,
    'ficha360_ajax_finance_query_gated' => strpos($fetch, 'if ($can_view_finance_360 && sige_aluno_360_table_exists($tbl_lanc))') !== false,
    'ficha360_finance_payload_has_permission_flag' => strpos($fetch, "'oculto_por_permissao' => !\$can_view_finance_360") !== false && strpos($fetch, "'oculto_por_permissao' => true") !== false,
    'ficha360_js_hides_finance_when_not_allowed' => strpos($alunos, 'canSeeFinance360') !== false && strpos($alunos, "Resumo financeiro reservado ao perfil financeiro") !== false && strpos($alunos, "if (canSeeFinance360) html += sigeAluno360Card('Finanças'") !== false,
    'ficha360_guarda_payload_minimization_preserved' => strpos($fetch, 'sige_alunos_user_is_guarda') !== false && strpos($fetch, "\$payload['financeiro'] = [") !== false && strpos($fetch, "\$payload['academico'] = [") !== false,

    // Bottom nav permission alignment
    'bottom_nav_exists' => $bottom_nav !== '',
    'bottom_nav_alunos_active_aria_current' => strpos($bottom_nav, 'view=alunos_lista') !== false && strpos($bottom_nav, 'aria-current="page"') !== false,
    'bottom_nav_dashboard_is_conditional' => strpos($bottom_nav, 'if ($sige_alunos_can_dashboard_nav):') !== false && strpos($bottom_nav, 'view=dashboard') !== false,
    'bottom_nav_portaria_is_conditional' => strpos($bottom_nav, 'if ($sige_alunos_can_portaria_nav):') !== false && strpos($bottom_nav, 'view=portaria') !== false,
    'bottom_nav_payments_is_conditional' => strpos($bottom_nav, 'if ($sige_alunos_can_finance_pay):') !== false && strpos($bottom_nav, 'view=financeiro-pagamentos') !== false,
    'bottom_nav_config_is_conditional' => strpos($bottom_nav, 'if ($sige_alunos_can_config_nav):') !== false && strpos($bottom_nav, 'view=config_center') !== false,

    // Regressão Dashboard/Guarda/asset global
    'dashboard_mobile_fit_marker_still_present' => strpos($dash, 'data-sg-dashboard-ux="12.11.9.67"') !== false && strpos($dash, 'sg-hero-status-row') !== false,
    'guard_role_still_registered' => strpos($roles, "add_role('sige_guarda'") !== false && strpos($roles, "'portaria.validar_acesso' => true") !== false,
    'guard_permission_allowlist_still_present' => strpos($perm, '$guarda_allowlist') !== false && strpos($perm, "'portaria.ver','portaria.validar_acesso','alunos.ver'") !== false,
    'guard_shell_redirect_still_present' => strpos($shell, '$is_guarda') !== false && strpos($shell, "\$view = 'portaria';") !== false,
    'global_mobile_tablet_assets_still_present' => strpos($css, '@media (min-width: 761px) and (max-width: 1100px)') !== false && strpos($js, 'sg-app-menu-open') !== false,

    // Blindagem de negócio no ficheiro de view
    'alunos_view_no_sql_mutation_keywords' => $mutation_free_alunos,
    'no_schema_migration_added_in_alunos_or_fetch' => stripos($alunos, 'ALTER TABLE') === false && stripos($fetch, 'ALTER TABLE') === false,
];

$failed = [];
foreach ($checks as $name => $ok) {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) $failed[] = $name;
}
if ($failed) {
    fwrite(STDERR, 'Smoke Alunos Mobile + Tablet UX v12.11.9.68 falhou: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Smoke Alunos Mobile + Tablet UX v12.11.9.68 OK: ' . count($checks) . '/' . count($checks) . ' checks.' . PHP_EOL;
