<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test v12.11.9.70 - Alunos Bottom Nav Mais Deep Smoke Hotfix
 * Estático/estrutural. Não toca na BD.
 */
$root = dirname(__DIR__);
$read = static function(string $rel) use ($root): string {
    $path = $root . '/' . ltrim($rel, '/');
    return is_file($path) ? (string)file_get_contents($path) : '';
};
$main = $read('sige-softgenial.php');
$build = $read('BUILD.json');
$changelog = $read('CHANGELOG-v12-11-9-70-alunos-bottom-nav-mais-deep-smoke-hotfix.txt');
$alunos = $read('admin/academic/alunos_lista.php');
$ajax = $read('includes/aluno-fetch-ajax.php');
$secure = $read('includes/secure-document-download.php');
$roles = $read('includes/security-roles.php');
$perms = $read('includes/permissions-layer.php');
$asset = $read('assets/mobile-tablet-ux.js');
$shell = $read('includes/admin-shell.php');

$checks = [
    'version_header_12_11_9_70' => strpos($main, 'Version: 12.11.9.70') !== false,
    'version_constant_12_11_9_70' => strpos($main, "define('SIGE_VERSION', '12.11.9.70');") !== false,
    'build_version_12_11_9_70' => strpos($build, '12.11.9.70') !== false,
    'build_slug_bottom_more' => strpos($build, 'alunos-bottom-nav-mais-deep-smoke-hotfix') !== false,
    'changelog_70_present' => strpos($changelog, 'Alunos Bottom Nav Mais Deep Smoke Hotfix') !== false,

    'bottom_more_permission_var_present' => strpos($alunos, '$sige_alunos_can_more_nav') !== false,
    'bottom_more_not_config_gate' => strpos($alunos, '<?php if ($sige_alunos_can_config_nav): ?>\n    <a href="<?php echo esc_url(admin_url(\'admin.php?page=sige-app&view=config_center\')); ?>"') === false,
    'bottom_more_is_button' => strpos($alunos, '<button type="button" class="sige-mobile-more-trigger"') !== false,
    'bottom_more_has_data_marker' => strpos($alunos, 'data-sige-mobile-more="1"') !== false,
    'bottom_more_controls_sidebar' => strpos($alunos, 'aria-controls="sige-sidebar"') !== false,
    'bottom_more_aria_expanded_initial' => strpos($alunos, 'aria-expanded="false" aria-haspopup="true"') !== false,
    'bottom_more_visible_label_preserved' => strpos($alunos, '<span>Mais</span>') !== false,
    'bottom_more_no_direct_config_link' => strpos($alunos, '<span>Mais</span>\n    </a>') === false,

    'bottom_nav_css_styles_button_like_anchor' => strpos($alunos, '.sige-mobile-bottom-nav a,') !== false && strpos($alunos, '.sige-mobile-bottom-nav button{') !== false,
    'bottom_nav_css_button_svg' => strpos($alunos, '.sige-mobile-bottom-nav button svg') !== false,
    'bottom_nav_css_button_span' => strpos($alunos, '.sige-mobile-bottom-nav button span') !== false,
    'bottom_nav_css_auto_fit' => strpos($alunos, 'grid-template-columns:repeat(auto-fit,minmax(64px,1fr))') !== false,
    'bottom_nav_css_focus_visible' => strpos($alunos, '.sige-mobile-bottom-nav button:focus-visible') !== false,
    'bottom_nav_css_expanded_state' => strpos($alunos, '.sige-mobile-more-trigger[aria-expanded="true"]') !== false,

    'bottom_more_script_id' => strpos($alunos, 'sige-alunos-bottom-more-menu-v1211970') !== false,
    'bottom_more_script_global_function' => strpos($alunos, 'window.sigeAlunosToggleMoreMenu = toggleSidebar') !== false,
    'bottom_more_script_click_binding' => strpos($alunos, "closest('.sige-mobile-bottom-nav button[aria-controls=\"sige-sidebar\"]')") !== false,
    'bottom_more_script_toggles_sidebar_open' => strpos($alunos, "sidebar.classList.toggle('open', open)") !== false,
    'bottom_more_script_toggles_overlay' => strpos($alunos, "overlay.classList.toggle('show', open)") !== false,
    'bottom_more_script_toggles_body_open' => strpos($alunos, "document.body.classList.toggle('sg-app-menu-open', open)") !== false,
    'bottom_more_script_sets_sidebar_aria_hidden' => strpos($alunos, "sidebar.setAttribute('aria-hidden', open ? 'false' : 'true')") !== false,
    'bottom_more_script_sets_expanded' => strpos($alunos, "btn.setAttribute('aria-expanded', open ? 'true' : 'false')") !== false,
    'bottom_more_script_closes_on_escape' => strpos($alunos, "if (ev.key === 'Escape') toggleSidebar(false)") !== false,
    'bottom_more_script_overlay_sync' => strpos($alunos, "ev.target.closest('#sige-overlay')") !== false,
    'bottom_more_script_menu_item_sync' => strpos($alunos, "ev.target.closest('#sige-sidebar .sige-menu-item[href]')") !== false,
    'bottom_more_script_ready_class' => strpos($alunos, 'sige-alunos-more-menu-v70-ready') !== false,
    'bottom_more_focuses_menu' => strpos($alunos, 'first.focus({preventScroll:true})') !== false,

    'global_asset_knows_alunos_bottom_button' => substr_count($asset, '.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]') >= 3,
    'global_asset_toggles_custom_bottom_button' => strpos($asset, "sidebarTrigger.matches('.sige-mobile-bottom-nav button[aria-controls=\"sige-sidebar\"]')") !== false && strpos($asset, 'sidebarState(!(side && side.classList.contains(\'open\')))') !== false,
    'admin_shell_a11y_knows_alunos_bottom_button' => substr_count($shell, '.sige-mobile-bottom-nav button[aria-controls=\\"sige-sidebar\\"]') >= 2 || substr_count($shell, '.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]') >= 1,

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
    'admin_bi_uses_documents_view' => strpos($alunos, '$sige_alunos_can_documents_view && $has_bi') !== false,
    'admin_emit_buttons_use_emit_scope' => substr_count($alunos, '$sige_alunos_can_documents_emit') >= 4,
    'frontend_documents_view_flag' => strpos($alunos, 'window.sigeAlunosCanDocumentsView') !== false,
    'frontend_export_flag' => strpos($alunos, 'window.sigeAlunosCanExport') !== false,

    'fetch_helper_accepts_purpose' => strpos($alunos, 'async function sigeGetTodosAlunos(purpose)') !== false,
    'fetch_helper_posts_purpose' => strpos($alunos, 'purpose: purpose') !== false,
    'fetch_cache_by_purpose' => strpos($alunos, '_sigeTodosAlunosCache = {}') !== false,
    'excel_uses_excel_purpose' => strpos($alunos, "sigeGetTodosAlunos('excel')") !== false,
    'cards_uses_cards_purpose' => strpos($alunos, "sigeGetTodosAlunos('cards')") !== false,
    'export_filters_shared' => strpos($alunos, 'sigeAlunosFiltrarPorFiltrosActuais') !== false,
    'export_filters_server_payload' => strpos($alunos, 'filtro_turma: filtrosExport.filtro_turma') !== false && strpos($alunos, 'filtro_search: filtrosExport.filtro_search') !== false,

    'ficha360_perms_payload_frontend' => strpos($alunos, 'var perms = data.permissoes || {};') !== false,
    'ficha360_sensitive_scope_frontend' => strpos($alunos, 'var canSeeSensitive360') !== false && strpos($alunos, 'perms.dados_sensiveis !== false') !== false,
    'ficha360_quality_gated' => strpos($alunos, 'if (canSeeQuality360) html += sigeAluno360Card') !== false,
    'ficha360_docs_card_gated' => strpos($alunos, "if (canSeeDocs360) html += sigeAluno360Card('Documentos'") !== false,

    'mobile_fit_marker_69' => strpos($alunos, 'Deep Audit Alunos: blindagem extra de fit-on-screen') !== false,
    'mobile_page_overflow_clipped' => strpos($alunos, 'overflow-x:clip') !== false,
    'mobile_min_width_zero' => strpos($alunos, 'min-width:0') !== false,
    'mobile_overflow_wrap_anywhere' => strpos($alunos, 'overflow-wrap:anywhere') !== false,
    'birthday_hidden_css' => strpos($alunos, 'is-birthday-hidden') !== false,
    'birthday_v69_script' => strpos($alunos, 'sige-alunos-deep-audit-fix-v1211969') !== false,

    'ajax_view_documents_helper' => strpos($ajax, 'sige_alunos_user_can_view_documents') !== false,
    'ajax_emit_documents_helper' => strpos($ajax, 'sige_alunos_user_can_emit_documents') !== false,
    'ajax_export_students_helper' => strpos($ajax, 'sige_alunos_user_can_export_students') !== false,
    'ajax_export_scope_no_alunos_editar' => strpos($ajax, "['documentos.emitir','documentos.reemitir','documentos.emitir_finais','academico.estatisticas_ver','alunos.editar']") === false,
    'ajax_purpose_whitelist' => strpos($ajax, "['excel','export','cards']") !== false,
    'ajax_cards_permission_error' => strpos($ajax, 'Sem permissão para imprimir cartões em lote') !== false,
    'ajax_export_permission_error' => strpos($ajax, 'Sem permissão para exportar listas de alunos') !== false,
    'ajax_cards_minimization' => strpos($ajax, 'minimização de dados para cartões') !== false,
    'ajax_ficha360_docs_scope' => strpos($ajax, '$can_view_docs_360') !== false,
    'ajax_ficha360_sensitive_payload_alias' => strpos($ajax, "'dados_sensiveis' => \$can_view_sensitive_360") !== false && strpos($ajax, "'dados_sensiveis' => false") !== false,
    'ajax_guard_payload_portaria_only' => strpos($ajax, 'Modo portaria') !== false && strpos($ajax, "'guard_readonly' => true") !== false,

    'secure_document_not_alunos_ver' => strpos($secure, "['documentos.ver','documentos.emitir','documentos.emitir_finais','alunos.ver']") === false,
    'secure_document_uses_document_permissions' => strpos($secure, "['documentos.ver','documentos.emitir','documentos.emitir_finais']") !== false,

    'guard_role_preserved' => strpos($roles, 'sige_guarda') !== false,
    'guard_permissions_preserved' => strpos($perms, 'portaria.validar_acesso') !== false && strpos($perms, "'guarda'") !== false,
    'guard_alunos_readonly_preserved' => strpos($alunos, '$sige_alunos_is_guarda') !== false && strpos($alunos, '$sige_alunos_read_only') !== false,
];

$failed = [];
foreach ($checks as $name => $ok) {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) $failed[] = $name;
}
if ($failed) {
    fwrite(STDERR, 'Smoke Alunos Bottom More v12.11.9.70 falhou: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo 'Smoke Alunos Bottom More v12.11.9.70 OK: ' . count($checks) . '/' . count($checks) . ' checks.' . PHP_EOL;
