<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test v12.11.9.70 - Alunos Bottom Nav Deep Smoke Hotfix
 * Invariantes estáticos/estruturais; não toca em BD nem chama WordPress.
 */
$root = dirname(__DIR__);
$read = static function(string $rel) use ($root): string {
    $path = $root . '/' . ltrim($rel, '/');
    return is_file($path) ? (string)file_get_contents($path) : '';
};
$main = $read('sige-softgenial.php');
$build = $read('BUILD.json');
$changelog70 = $read('CHANGELOG-v12-11-9-70-alunos-bottom-nav-mais-deep-smoke-hotfix.txt');
$alunos = $read('admin/academic/alunos_lista.php');
$asset = $read('assets/mobile-tablet-ux.js');
$ajax = $read('includes/aluno-fetch-ajax.php');
$secure = $read('includes/secure-document-download.php');
$roles = $read('includes/security-roles.php');
$perms = $read('includes/permissions-layer.php');
$shell = $read('includes/admin-shell.php');

$navStart = strpos($alunos, '<nav class="sige-mobile-bottom-nav"');
$navEnd = $navStart === false ? false : strpos($alunos, '</nav>', $navStart);
$navBlock = ($navStart !== false && $navEnd !== false) ? substr($alunos, $navStart, $navEnd - $navStart + 6) : '';

$checks = [
    // Versionamento
    'version_header_12_11_9_70' => strpos($main, 'Version: 12.11.9.70') !== false,
    'version_constant_12_11_9_70' => strpos($main, "define('SIGE_VERSION', '12.11.9.70');") !== false,
    'build_version_12_11_9_70' => strpos($build, '12.11.9.70') !== false,
    'build_slug_bottom_more_fix' => strpos($build, 'alunos-deep-smoke-bottom-more-fix') !== false,
    'changelog70_present' => strpos($changelog70, 'Alunos Bottom Nav') !== false && strpos($changelog70, 'Deep Smoke') !== false,

    // Correcção principal reportada pelo utilizador
    'alunos_mobile_nav_exists' => $navBlock !== '',
    'mais_is_button_not_config_link' => strpos($navBlock, '<button type="button" class="sige-mobile-more-trigger"') !== false,
    'mais_no_longer_anchor_to_config' => strpos($navBlock, 'view=config_center') === false && strpos($navBlock, '<span>Mais</span>') !== false,
    'mais_has_sidebar_controls' => strpos($navBlock, 'aria-controls="sige-sidebar"') !== false && strpos($navBlock, 'aria-expanded="false"') !== false,
    'mais_has_mobile_more_marker' => strpos($navBlock, 'data-sige-mobile-more="1"') !== false,
    'mais_is_not_conditioned_by_config_scope' => strpos($navBlock, '$sige_alunos_can_config_nav') === false,
    'more_scope_variable_guarded' => strpos($alunos, '$sige_alunos_can_more_nav = !$sige_alunos_is_guarda') !== false,
    'mais_conditioned_by_more_scope' => strpos($navBlock, '$sige_alunos_can_more_nav') !== false,
    'mais_has_aria_haspopup' => strpos($navBlock, 'aria-haspopup="true"') !== false,

    // CSS: button deve ter o mesmo tratamento visual/táctil dos links
    'bottom_nav_css_styles_button_base' => strpos($alunos, '.sige-mobile-bottom-nav a,') !== false && strpos($alunos, '.sige-mobile-bottom-nav button{') !== false,
    'bottom_nav_css_styles_button_svg' => strpos($alunos, '.sige-mobile-bottom-nav button svg') !== false,
    'bottom_nav_css_styles_button_label' => strpos($alunos, '.sige-mobile-bottom-nav button span') !== false,
    'bottom_nav_button_native_reset' => strpos($alunos, 'background:transparent!important') !== false && strpos($alunos, 'appearance:none!important') !== false,
    'bottom_nav_responsive_auto_fit_preserved' => strpos($alunos, 'grid-template-columns:repeat(auto-fit,minmax(64px,1fr))') !== false,
    'bottom_nav_narrow_fit_preserved' => strpos($alunos, 'grid-template-columns:repeat(auto-fit,minmax(56px,1fr))') !== false,

    // JS: clique deve abrir sidebar/overlay e sincronizar ARIA
    'js_selector_includes_local_alunos_more' => strpos($asset, '.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]') !== false,
    'js_sidebar_state_updates_local_more_aria' => substr_count($asset, '.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]') >= 3,
    'js_click_handler_detects_sidebar_trigger' => strpos($asset, 'var sidebarTrigger = target.closest') !== false,
    'js_click_handler_toggles_local_button' => strpos($asset, 'sidebarTrigger.matches(\'.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]\')') !== false && strpos($asset, 'sidebarState(!(side && side.classList.contains(\'open\')))') !== false,
    'js_prevents_default_for_local_more' => strpos($asset, 'ev.preventDefault();') !== false && strpos($asset, 'ev.stopPropagation();') !== false,
    'js_overlay_body_state_preserved' => strpos($asset, "overlay) overlay.classList.toggle('show', open)") !== false && strpos($asset, "body().classList.toggle('sg-app-menu-open', open)") !== false,
    'js_escape_close_preserved' => strpos($asset, "ev.key !== 'Escape'") !== false && strpos($asset, 'sidebarState(false);') !== false,
    'inline_fallback_script_present' => strpos($alunos, 'sige-alunos-bottom-more-menu-v1211970') !== false,
    'inline_fallback_exports_toggle' => strpos($alunos, 'window.sigeAlunosToggleMoreMenu = toggleSidebar') !== false,
    'inline_fallback_ready_class' => strpos($alunos, 'sige-alunos-more-menu-v70-ready') !== false,

    // Regressões críticas de v12.11.9.69 preservadas
    'search_includes_turma_nome' => strpos($alunos, 't.nome LIKE %s') !== false,
    'search_includes_classe' => strpos($alunos, 't.classe LIKE %s') !== false,
    'count_distinct_preserved' => strpos($alunos, 'COUNT(DISTINCT a.id)') !== false,
    'joins_scoped_school_preserved' => strpos($alunos, 'm.escola_id = a.escola_id') !== false && strpos($alunos, 't.escola_id = a.escola_id') !== false,
    'export_cards_purpose_preserved' => strpos($alunos, "sigeGetTodosAlunos('excel')") !== false && strpos($alunos, "sigeGetTodosAlunos('cards')") !== false,
    'ficha360_scope_payload_preserved' => strpos($alunos, 'var perms = data.permissoes || {};') !== false && strpos($ajax, "'can_view_documents' => \$can_view_docs_360") !== false,
    'secure_document_not_alunos_ver' => strpos($secure, "['documentos.ver','documentos.emitir','documentos.emitir_finais','alunos.ver']") === false,
    'guard_role_preserved' => strpos($roles, 'sige_guarda') !== false,
    'guard_permissions_preserved' => strpos($perms, 'portaria.validar_acesso') !== false && strpos($perms, "'guarda'") !== false,
    'shell_sidebar_exists' => strpos($shell, 'id="sige-sidebar"') !== false && strpos($shell, 'id="sige-overlay"') !== false,
    'global_more_button_preserved' => strpos($shell, 'Abrir mais opções') !== false && strpos($shell, 'aria-controls="sige-sidebar"') !== false,
];

$failed = [];
foreach ($checks as $name => $ok) {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) $failed[] = $name;
}
if ($failed) {
    fwrite(STDERR, 'Smoke Alunos Bottom Nav v12.11.9.70 falhou: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo 'Smoke Alunos Bottom Nav v12.11.9.70 OK: ' . count($checks) . '/' . count($checks) . ' checks.' . PHP_EOL;
