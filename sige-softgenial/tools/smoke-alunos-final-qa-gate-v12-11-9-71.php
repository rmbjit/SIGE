<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test v12.11.9.71 - Alunos Final QA Gate
 * Estático/estrutural. Não toca na BD.
 */
$root = dirname(__DIR__);
$read = static function(string $rel) use ($root): string {
    $path = $root . '/' . ltrim($rel, '/');
    return is_file($path) ? (string)file_get_contents($path) : '';
};
$slice = static function(string $text, string $start, string $end = ''): string {
    $p = strpos($text, $start);
    if ($p === false) return '';
    if ($end === '') return substr($text, $p);
    $q = strpos($text, $end, $p + strlen($start));
    return $q === false ? substr($text, $p) : substr($text, $p, $q - $p);
};
$main = $read('sige-softgenial.php');
$build = $read('BUILD.json');
$changelog71 = $read('CHANGELOG-v12-11-9-71-alunos-final-qa-gate.txt');
$changelog70 = $read('CHANGELOG-v12-11-9-70-alunos-bottom-nav-mais-deep-smoke-hotfix.txt');
$alunos = $read('admin/academic/alunos_lista.php');
$ajax = $read('includes/aluno-fetch-ajax.php');
$secure = $read('includes/secure-document-download.php');
$roles = $read('includes/security-roles.php');
$perms = $read('includes/permissions-layer.php');
$asset = $read('assets/mobile-tablet-ux.js');
$shell = $read('includes/admin-shell.php');
$db = $read('includes/db-handler.php');
$port = $read('admin/system/portaria-view.php');
$rh = $read('admin/hr/equipe-view.php');
$pui = $read('admin/system/permissions-ui.php');
$validarPort = $slice($db, 'function sige_ajax_validar_acesso()', '$codigo_lido');
$saveAluno = $slice($db, 'function sige_ajax_salvar_aluno()', 'function sige_ajax_remover_aluno()');
$removeAluno = $slice($db, 'function sige_ajax_remover_aluno()', 'function sige_ajax_validar_acesso()');

$checks = [
    // Gate/versioning
    'version_header_12_11_9_71' => strpos($main, 'Version: 12.11.9.71') !== false,
    'version_constant_12_11_9_71' => strpos($main, "define('SIGE_VERSION', '12.11.9.71');") !== false,
    'build_version_12_11_9_71' => strpos($build, '12.11.9.71') !== false,
    'build_slug_final_qa_gate' => strpos($build, 'alunos-final-qa-gate') !== false,
    'changelog_71_present' => strpos($changelog71, 'Alunos Final QA Gate') !== false,
    'changelog_70_preserved' => strpos($changelog70, 'Alunos Bottom Nav Mais Deep Smoke Hotfix') !== false,
    'main_changelog_71_line' => strpos($main, 'Alunos Final QA Gate') !== false,

    // Bottom nav Mais - UI, permissão e comportamento
    'bottom_nav_exists' => strpos($alunos, 'sige-mobile-bottom-nav') !== false,
    'bottom_more_permission_var_present' => strpos($alunos, '$sige_alunos_can_more_nav') !== false,
    'bottom_more_not_config_gate' => strpos($alunos, '<?php if ($sige_alunos_can_config_nav): ?>\n    <a href="<?php echo esc_url(admin_url(\'admin.php?page=sige-app&view=config_center\')); ?>"') === false,
    'bottom_more_is_button' => strpos($alunos, '<button type="button" class="sige-mobile-more-trigger"') !== false,
    'bottom_more_has_data_marker' => strpos($alunos, 'data-sige-mobile-more="1"') !== false,
    'bottom_more_controls_sidebar' => strpos($alunos, 'aria-controls="sige-sidebar"') !== false,
    'bottom_more_aria_expanded_initial' => strpos($alunos, 'aria-expanded="false" aria-haspopup="true"') !== false,
    'bottom_more_label_preserved' => strpos($alunos, '<span>Mais</span>') !== false,
    'bottom_more_no_direct_config_link' => strpos($alunos, '<span>Mais</span>\n    </a>') === false,
    'bottom_more_not_shown_for_guarda' => strpos($alunos, '$sige_alunos_can_more_nav = !$sige_alunos_is_guarda') !== false,
    'bottom_more_scope_not_config_only' => strpos($alunos, '$sige_alunos_can_config_nav') !== false && strpos($alunos, '$sige_alunos_can_more_nav') !== false,

    // CSS fit/accessibility
    'bottom_nav_css_styles_button_like_anchor' => strpos($alunos, '.sige-mobile-bottom-nav a,') !== false && strpos($alunos, '.sige-mobile-bottom-nav button{') !== false,
    'bottom_nav_css_button_svg' => strpos($alunos, '.sige-mobile-bottom-nav button svg') !== false,
    'bottom_nav_css_button_span' => strpos($alunos, '.sige-mobile-bottom-nav button span') !== false,
    'bottom_nav_css_focus_visible' => strpos($alunos, '.sige-mobile-bottom-nav button:focus-visible') !== false,
    'bottom_nav_css_expanded_state' => strpos($alunos, '.sige-mobile-more-trigger[aria-expanded="true"]') !== false,
    'bottom_nav_css_auto_fit' => strpos($alunos, 'grid-template-columns:repeat(auto-fit,minmax(64px,1fr))') !== false,
    'bottom_nav_css_narrow_safe' => strpos($alunos, 'max-width:430px') !== false || strpos($alunos, 'max-width:420px') !== false,

    // JS routing: global layer intercepts; local fallback remains available; no double-toggle by design.
    'global_asset_knows_alunos_button' => substr_count($asset, '.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]') >= 3,
    'global_asset_local_button_branch' => strpos($asset, "sidebarTrigger.matches('.sige-mobile-bottom-nav button[aria-controls=\"sige-sidebar\"]')") !== false,
    'global_asset_prevents_default_for_local_button' => strpos($asset, 'ev.preventDefault();') !== false,
    'global_asset_stops_propagation_for_local_button' => strpos($asset, 'ev.stopPropagation();') !== false,
    'global_asset_toggles_sidebar_state' => strpos($asset, 'sidebarState(!(side && side.classList.contains(\'open\')))') !== false,
    'global_asset_capture_listener' => strpos($asset, '}, true);') !== false,
    'inline_fallback_script_present' => strpos($alunos, 'sige-alunos-bottom-more-menu-v1211970') !== false,
    'inline_fallback_global_function' => strpos($alunos, 'window.sigeAlunosToggleMoreMenu = toggleSidebar') !== false,
    'inline_fallback_click_binding' => strpos($alunos, "closest('.sige-mobile-bottom-nav button[aria-controls=\"sige-sidebar\"]')") !== false,
    'inline_fallback_bubble_listener' => strpos($alunos, '}, false);') !== false,
    'inline_fallback_sets_overlay' => strpos($alunos, "overlay.classList.toggle('show', open)") !== false,
    'inline_fallback_sets_body' => strpos($alunos, "document.body.classList.toggle('sg-app-menu-open', open)") !== false,
    'inline_fallback_sets_sidebar_aria_hidden' => strpos($alunos, "sidebar.setAttribute('aria-hidden', open ? 'false' : 'true')") !== false,
    'inline_fallback_closes_escape' => strpos($alunos, "if (ev.key === 'Escape') toggleSidebar(false)") !== false,
    'admin_shell_a11y_syncs_local_bottom_button' => substr_count($shell, '.sige-mobile-bottom-nav button[aria-controls=\\"sige-sidebar\\"]') >= 2 || substr_count($shell, '.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]') >= 1,
    'admin_shell_sidebar_present' => strpos($shell, 'id="sige-sidebar"') !== false,
    'admin_shell_overlay_present' => strpos($shell, 'id="sige-overlay"') !== false,

    // Alunos deep audit preserved
    'server_search_includes_turma_nome' => strpos($alunos, 't.nome LIKE %s') !== false,
    'server_search_includes_classe' => strpos($alunos, 't.classe LIKE %s') !== false,
    'count_uses_distinct_student' => strpos($alunos, 'COUNT(DISTINCT a.id)') !== false,
    'matricula_join_scoped_school' => strpos($alunos, 'm.escola_id = a.escola_id') !== false,
    'turma_join_scoped_school' => strpos($alunos, 't.escola_id = a.escola_id') !== false,
    'admin_documents_view_scope' => strpos($alunos, '$sige_alunos_can_documents_view') !== false,
    'admin_documents_emit_scope' => strpos($alunos, '$sige_alunos_can_documents_emit') !== false,
    'admin_export_scope' => strpos($alunos, '$sige_alunos_can_export') !== false,
    'admin_no_overbroad_documents_non_guard' => strpos($alunos, '$sige_alunos_can_documents = !$sige_alunos_is_guarda;') === false,
    'admin_no_overbroad_export_non_guard' => strpos($alunos, '$sige_alunos_can_export = !$sige_alunos_is_guarda;') === false,
    'fetch_helper_accepts_purpose' => strpos($alunos, 'async function sigeGetTodosAlunos(purpose)') !== false,
    'excel_uses_excel_purpose' => strpos($alunos, "sigeGetTodosAlunos('excel')") !== false,
    'cards_uses_cards_purpose' => strpos($alunos, "sigeGetTodosAlunos('cards')") !== false,
    'export_filters_server_payload' => strpos($alunos, 'filtro_turma: filtrosExport.filtro_turma') !== false && strpos($alunos, 'filtro_search: filtrosExport.filtro_search') !== false,
    'ficha360_perms_payload_frontend' => strpos($alunos, 'var perms = data.permissoes || {};') !== false,
    'ficha360_sensitive_scope_frontend' => strpos($alunos, 'var canSeeSensitive360') !== false && strpos($alunos, 'perms.dados_sensiveis !== false') !== false,
    'mobile_fit_marker_69' => strpos($alunos, 'Deep Audit Alunos: blindagem extra de fit-on-screen') !== false,
    'mobile_page_overflow_clipped' => strpos($alunos, 'overflow-x:clip') !== false,
    'mobile_min_width_zero' => strpos($alunos, 'min-width:0') !== false,
    'mobile_overflow_wrap_anywhere' => strpos($alunos, 'overflow-wrap:anywhere') !== false,

    // AJAX/security preserved
    'ajax_view_documents_helper' => strpos($ajax, 'sige_alunos_user_can_view_documents') !== false,
    'ajax_emit_documents_helper' => strpos($ajax, 'sige_alunos_user_can_emit_documents') !== false,
    'ajax_export_students_helper' => strpos($ajax, 'sige_alunos_user_can_export_students') !== false,
    'ajax_export_scope_no_alunos_editar' => strpos($ajax, "['documentos.emitir','documentos.reemitir','documentos.emitir_finais','academico.estatisticas_ver','alunos.editar']") === false,
    'ajax_purpose_whitelist' => strpos($ajax, "['excel','export','cards']") !== false,
    'ajax_cards_permission_error' => strpos($ajax, 'Sem permissão para imprimir cartões em lote') !== false,
    'ajax_export_permission_error' => strpos($ajax, 'Sem permissão para exportar listas de alunos') !== false,
    'ajax_cards_minimization' => strpos($ajax, 'minimização de dados para cartões') !== false,
    'ajax_ficha360_docs_scope' => strpos($ajax, '$can_view_docs_360') !== false,
    'ajax_guard_payload_portaria_only' => strpos($ajax, 'Modo portaria') !== false && strpos($ajax, "'guard_readonly' => true") !== false,
    'secure_document_not_alunos_ver' => strpos($secure, "['documentos.ver','documentos.emitir','documentos.emitir_finais','alunos.ver']") === false,
    'secure_document_uses_document_permissions' => strpos($secure, "['documentos.ver','documentos.emitir','documentos.emitir_finais']") !== false,

    // Guarda/Portaria preserved
    'wp_role_sige_guarda_registered' => strpos($roles, "add_role('sige_guarda'") !== false,
    'wp_role_sige_guarda_caps_minimas' => strpos($roles, "'portaria.ver' => true") !== false && strpos($roles, "'portaria.validar_acesso' => true") !== false && strpos($roles, "'alunos.ver' => true") !== false,
    'perfil_guarda_allowlist' => strpos($perms, "'guarda'") !== false && strpos($perms, 'portaria.validar_acesso') !== false,
    'perfil_guarda_sem_financeiro' => preg_match("/'guarda'\s*=>\s*\[[^\n]*'permissions'\s*=>\s*\[[^\]]*financeiro\./", $perms) !== 1,
    'shell_guarda_redirect_portaria' => strpos($shell, 'sige_guarda') !== false && strpos($shell, 'view=portaria') !== false,
    'portaria_guard_permission' => strpos($port, 'portaria.ver') !== false,
    'portaria_ajax_permission' => strpos($validarPort, 'portaria.validar_acesso') !== false,
    'alunos_guard_allows_guard_view' => strpos($alunos, "'sige_guarda'") !== false && strpos($alunos, "['alunos.ver']") !== false,
    'alunos_guard_read_only_flag' => strpos($alunos, '$sige_alunos_is_guarda') !== false,
    'salvar_aluno_requires_permission' => strpos($saveAluno, 'alunos.criar') !== false || strpos($saveAluno, 'alunos.editar') !== false,
    'remover_aluno_requires_permission' => strpos($removeAluno, 'alunos.apagar') !== false,
    'rh_can_assign_guard' => strpos($rh, 'sige_guarda') !== false && strpos($rh, 'Guarda') !== false,
    'permissions_ui_guarda_present' => strpos($pui, 'guarda') !== false && strpos($pui, 'portaria.validar_acesso') !== false,
];

$failed = [];
foreach ($checks as $name => $ok) {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) $failed[] = $name;
}
if ($failed) {
    fwrite(STDERR, 'Smoke Alunos Final QA Gate v12.11.9.71 falhou: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo 'Smoke Alunos Final QA Gate v12.11.9.71 OK: ' . count($checks) . '/' . count($checks) . ' checks.' . PHP_EOL;
