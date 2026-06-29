<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test v12.11.9.70 - Alunos Bottom Nav Mais Deep Smoke Hotfix
 * Verifica invariantes estáticos/estruturais. Não toca na BD.
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

$maisAnchorToConfig = preg_match('/<a\s+href="<\?php\s+echo\s+esc_url\(admin_url\(\'admin\.php\?page=sige-app&view=config_center\'\)\);\s*\?>"[^>]*>\s*<svg[\s\S]*?<span>Mais<\/span>\s*<\/a>/m', $alunos) === 1;
$moreButtonRegex = preg_match('/<button\s+type="button"\s+class="sige-mobile-more-trigger"[^>]*aria-controls="sige-sidebar"[^>]*aria-expanded="false"[^>]*aria-haspopup="true"/m', $alunos) === 1;

$checks = [
    // Versão e artefactos
    'version_header_12_11_9_70' => strpos($main, 'Version: 12.11.9.70') !== false,
    'version_constant_12_11_9_70' => strpos($main, "define('SIGE_VERSION', '12.11.9.70');") !== false,
    'build_version_12_11_9_70' => strpos($build, '12.11.9.70') !== false,
    'build_slug_bottom_more' => strpos($build, 'alunos-deep-smoke-bottom-more-fix') !== false,
    'changelog_70_present' => strpos($changelog, 'Alunos Bottom Nav Mais Deep Smoke Hotfix') !== false,

    // Causa corrigida: botão Mais da navegação própria de Alunos
    'more_nav_uses_permission_scope' => strpos($alunos, '$sige_alunos_can_more_nav') !== false,
    'more_nav_excludes_guarda' => strpos($alunos, '$sige_alunos_can_more_nav = !$sige_alunos_is_guarda') !== false,
    'more_nav_not_config_only' => strpos($alunos, "'rh.equipe_ver'") !== false && strpos($alunos, "'academico.turmas_ver'") !== false && strpos($alunos, "'financeiro.dashboard_ver'") !== false,
    'more_button_markup_present' => $moreButtonRegex,
    'more_button_not_anchor_to_config' => !$maisAnchorToConfig,
    'more_button_has_sidebar_control' => strpos($alunos, 'class="sige-mobile-more-trigger"') !== false && strpos($alunos, 'aria-controls="sige-sidebar"') !== false,
    'more_button_has_expanded_state' => strpos($alunos, 'aria-expanded="false"') !== false,
    'more_button_has_accessible_label' => strpos($alunos, 'aria-label="Abrir mais opções"') !== false,
    'more_button_has_haspopup' => strpos($alunos, 'aria-haspopup="true"') !== false,

    // CSS: links e botões têm o mesmo tratamento visual/táctil
    'bottom_nav_css_targets_buttons' => strpos($alunos, '.sige-mobile-bottom-nav button') !== false,
    'bottom_nav_button_icon_css' => strpos($alunos, '.sige-mobile-bottom-nav button svg') !== false,
    'bottom_nav_button_active_css' => strpos($alunos, '.sige-mobile-bottom-nav button.is-active') !== false,
    'bottom_nav_button_reset_css' => strpos($alunos, 'background:transparent!important') !== false && strpos($alunos, 'font-family:inherit!important') !== false,
    'bottom_nav_focus_visible_css' => strpos($alunos, '.sige-mobile-bottom-nav button:focus-visible') !== false,
    'bottom_nav_expanded_css' => strpos($alunos, '.sige-mobile-more-trigger[aria-expanded="true"]') !== false,

    // JS específico do módulo Alunos para abrir o menu lateral global
    'v70_script_present' => strpos($alunos, 'sige-alunos-bottom-more-menu-v1211970') !== false,
    'v70_script_no_jquery_dependency' => strpos($alunos, '(function(window, document){') !== false,
    'v70_click_selector_custom_bottom' => strpos($alunos, '.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]') !== false,
    'v70_toggles_sidebar_open' => strpos($alunos, "sidebar.classList.toggle('open', open)") !== false,
    'v70_toggles_overlay_show' => strpos($alunos, "overlay.classList.toggle('show', open)") !== false,
    'v70_toggles_body_menu_open' => strpos($alunos, "document.body.classList.toggle('sg-app-menu-open', open)") !== false,
    'v70_syncs_aria_expanded' => strpos($alunos, 'setExpandedState(open)') !== false && strpos($alunos, "btn.setAttribute('aria-expanded'") !== false,
    'v70_sets_sidebar_aria_hidden' => strpos($alunos, "sidebar.setAttribute('aria-hidden'") !== false,
    'v70_escape_closes_sidebar' => strpos($alunos, "ev.key === 'Escape'") !== false && strpos($alunos, 'toggleSidebar(false)') !== false,
    'v70_overlay_close_sync' => strpos($alunos, "ev.target.closest('#sige-overlay')") !== false,
    'v70_menu_item_close_sync' => strpos($alunos, "ev.target.closest('#sige-sidebar .sige-menu-item[href]')") !== false,
    'v70_focus_first_sidebar_item' => strpos($alunos, "sidebar.querySelector('.sige-menu-item[href]") !== false && strpos($alunos, 'preventScroll:true') !== false,
    'v70_ready_class' => strpos($alunos, 'sige-alunos-more-menu-v70-ready') !== false,

    // Infraestrutura global que o botão controla continua presente
    'admin_shell_sidebar_present' => strpos($shell, 'id="sige-sidebar"') !== false,
    'admin_shell_overlay_present' => strpos($shell, 'id="sige-overlay"') !== false,
    'admin_shell_global_more_button_reference' => strpos($shell, 'aria-label="Abrir mais opções"') !== false && strpos($shell, 'aria-controls="sige-sidebar"') !== false,

    // Regressões essenciais v12.11.9.69 - Alunos/Ficha 360/segurança
    'server_search_includes_turma_nome' => strpos($alunos, 't.nome LIKE %s') !== false,
    'server_search_includes_classe' => strpos($alunos, 't.classe LIKE %s') !== false,
    'count_uses_distinct_student' => strpos($alunos, 'COUNT(DISTINCT a.id)') !== false,
    'matricula_join_scoped_school' => strpos($alunos, 'm.escola_id = a.escola_id') !== false,
    'turma_join_scoped_school' => strpos($alunos, 't.escola_id = a.escola_id') !== false,
    'admin_documents_view_scope' => strpos($alunos, '$sige_alunos_can_documents_view') !== false,
    'admin_documents_emit_scope' => strpos($alunos, '$sige_alunos_can_documents_emit') !== false,
    'admin_export_scope' => strpos($alunos, '$sige_alunos_can_export') !== false,
    'admin_no_overbroad_documents_non_guard' => strpos($alunos, '$sige_alunos_can_documents = !$sige_alunos_is_guarda;') === false,
    'fetch_helper_accepts_purpose' => strpos($alunos, 'async function sigeGetTodosAlunos(purpose)') !== false,
    'excel_uses_excel_purpose' => strpos($alunos, "sigeGetTodosAlunos('excel')") !== false,
    'cards_uses_cards_purpose' => strpos($alunos, "sigeGetTodosAlunos('cards')") !== false,
    'export_filters_shared' => strpos($alunos, 'sigeAlunosFiltrarPorFiltrosActuais') !== false,
    'ficha360_perms_payload_frontend' => strpos($alunos, 'var perms = data.permissoes || {};') !== false,
    'ficha360_sensitive_scope_frontend' => strpos($alunos, 'var canSeeSensitive360') !== false,
    'ficha360_docs_card_gated' => strpos($alunos, "if (canSeeDocs360) html += sigeAluno360Card('Documentos'") !== false,
    'all_turmas_zero_match' => strpos($alunos, "tr === '0'") !== false,
    'clear_filters_uses_zero_turma' => strpos($alunos, "#filtro-turma').val('0')") !== false,
    'mobile_fit_marker_69' => strpos($alunos, 'Deep Audit Alunos: blindagem extra de fit-on-screen') !== false,
    'mobile_min_width_zero' => strpos($alunos, 'min-width:0') !== false,
    'mobile_overflow_wrap_anywhere' => strpos($alunos, 'overflow-wrap:anywhere') !== false,
    'birthday_v69_script' => strpos($alunos, 'sige-alunos-deep-audit-fix-v1211969') !== false,

    // AJAX e documentos continuam blindados
    'ajax_view_documents_helper' => strpos($ajax, 'sige_alunos_user_can_view_documents') !== false,
    'ajax_emit_documents_helper' => strpos($ajax, 'sige_alunos_user_can_emit_documents') !== false,
    'ajax_export_students_helper' => strpos($ajax, 'sige_alunos_user_can_export_students') !== false,
    'ajax_purpose_whitelist' => strpos($ajax, "['excel','export','cards']") !== false,
    'ajax_cards_minimization' => strpos($ajax, 'minimização de dados para cartões') !== false,
    'ajax_ficha360_scope_payload' => strpos($ajax, "'can_view_documents' => \$can_view_docs_360") !== false,
    'ajax_guard_payload_portaria_only' => strpos($ajax, 'Modo portaria') !== false && strpos($ajax, "'guard_readonly' => true") !== false,
    'secure_document_not_alunos_ver' => strpos($secure, "['documentos.ver','documentos.emitir','documentos.emitir_finais','alunos.ver']") === false,
    'secure_document_uses_document_permissions' => strpos($secure, "['documentos.ver','documentos.emitir','documentos.emitir_finais']") !== false,

    // Guarda/Portaria e asset global preservados
    'guard_role_preserved' => strpos($roles, 'sige_guarda') !== false,
    'guard_permissions_preserved' => strpos($perms, 'portaria.validar_acesso') !== false && strpos($perms, "'guarda'") !== false,
    'global_mobile_asset_preserved' => strpos($asset, 'sige-ux-mobile') !== false && strpos($asset, 'sige-ux-tablet') !== false,
];

$failed = [];
foreach ($checks as $name => $ok) {
    if (!$ok) $failed[] = $name;
}
if ($failed) {
    fwrite(STDERR, 'Smoke Alunos Bottom Mais v12.11.9.70 falhou: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo 'Smoke Alunos Bottom Mais v12.11.9.70 OK: ' . count($checks) . '/' . count($checks) . ' checks.' . PHP_EOL;
