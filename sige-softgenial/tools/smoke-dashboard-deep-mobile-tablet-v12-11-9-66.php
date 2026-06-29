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
$dash = $read('admin/system/dashboard-view.php');
$shell = $read('includes/admin-shell.php');
$css = $read('assets/mobile-tablet-ux.css');
$js = $read('assets/mobile-tablet-ux.js');
$changelog = $read('CHANGELOG-v12-11-9-66-dashboard-deep-smoke-permission-aligned-ux.txt');

preg_match_all("/__sg_url\('([^']+)'\)/", $dash, $m);
$dash_views = array_values(array_unique($m[1] ?? []));
$missing_views = [];
foreach ($dash_views as $view) {
    if (strpos($shell, "'" . $view . "'") === false && strpos($shell, '"' . $view . '"') === false) {
        $missing_views[] = $view;
    }
}

$mutation_free = !preg_match('/\b(UPDATE|INSERT|DELETE|ALTER|DROP|TRUNCATE|CREATE)\b/i', preg_replace('/\/\*.*?\*\/|\/\/.*|#.*$/ms', '', $dash));

$checks = [
    'version_main_header_12_11_9_66' => strpos($main, 'Version: 12.11.9.66') !== false,
    'version_constant_12_11_9_66' => strpos($main, "define('SIGE_VERSION', '12.11.9.66');") !== false,
    'build_version_12_11_9_66' => strpos($build, '12.11.9.66') !== false && strpos($build, 'dashboard-deep-smoke-permission-aligned-ux') !== false,
    'dashboard_ux_marker_12_11_9_66' => strpos($dash, 'data-sg-dashboard-ux="12.11.9.66"') !== false,

    'dashboard_guard_preserved' => strpos($dash, 'sige_page_guard_allows') !== false && strpos($dash, 'academico.dashboard_ver') !== false && strpos($dash, 'financeiro.dashboard_ver') !== false,
    'dashboard_no_sql_mutation_keywords' => $mutation_free,
    'dashboard_cache_key_user_scoped' => strpos($dash, 'get_current_user_id()') !== false && strpos($dash, 'sige_sys_dash_html_') !== false,

    'dashboard_staff_counts_include_guarda' => strpos($dash, "'sige_guarda'") !== false && strpos($dash, "'sige_motorista','sige_limpeza','sige_recepcao','sige_guarda'") !== false,
    'dashboard_permission_helper_exists' => strpos($dash, '$__sg_can_any') !== false && strpos($dash, 'sige_page_guard_allows($permissions, $legacy_caps)') !== false,
    'dashboard_permission_scopes_declared' => strpos($dash, '$__sg_can_academico') !== false && strpos($dash, '$__sg_can_financeiro') !== false && strpos($dash, '$__sg_can_config') !== false,

    'hero_finance_text_is_conditional' => strpos($dash, '$__sg_hero_subtitle') !== false && strpos($dash, 'if ($__sg_can_financeiro): ?><span class="sg-hero-status-pill">Cobrança') !== false,
    'hero_actions_permission_gated' => strpos($dash, 'if ($__sg_can_pagamentos || $__sg_can_alunos)') !== false && strpos($dash, 'if ($__sg_can_pagamentos):') !== false && strpos($dash, 'if ($__sg_can_alunos):') !== false,

    'mobile_actions_permission_filtered' => strpos($dash, '$__sg_dashboard_actions = array_values(array_filter') !== false && strpos($dash, "'enabled' => \$__sg_can_pagamentos") !== false && strpos($dash, "'enabled' => \$__sg_can_turmas") !== false,
    'focus_cards_permission_filtered' => strpos($dash, '$__sg_focus_cards = array_values(array_filter') !== false && strpos($dash, "'enabled' => \$__sg_can_fin_dashboard") !== false && strpos($dash, "'enabled' => \$__sg_can_alunos") !== false,
    'quick_links_permission_filtered' => strpos($dash, '$__sg_quick_links = array_values(array_filter') !== false && strpos($dash, "'Configurações'") !== false && strpos($dash, "'enabled' => \$__sg_can_config") !== false,
    'alerts_permission_filtered' => strpos($dash, '$__sg_alertas = array_values(array_filter') !== false && strpos($dash, "'enabled' => \$__sg_can_financeiro") !== false,
    'kpis_permission_wrapped' => strpos($dash, '$__sg_has_kpis') !== false && strpos($dash, 'if ($__sg_has_kpis):') !== false && strpos($dash, 'if ($__sg_can_financeiro):') !== false,
    'finance_card_permission_wrapped' => strpos($dash, '<article class="sg-dash-card sg-span-2 sg-card-finance">') !== false && strpos($dash, 'if ($__sg_can_fin_dashboard): ?><a class="sg-card-link"') !== false,
    'classes_alerts_progress_day_wrapped' => strpos($dash, 'if ($__sg_can_alunos || $__sg_can_turmas || $__sg_can_academico):') !== false && strpos($dash, 'if (!empty($__sg_alertas)):') !== false && strpos($dash, 'if (!empty($__sg_progress_items)):') !== false && strpos($dash, 'if (!empty($__sg_day_rows)):') !== false,
    'progress_rows_are_scope_filtered' => strpos($dash, '$__sg_progress_items') !== false && strpos($dash, 'foreach ($__sg_progress_items as $__sg_item)') !== false,
    'day_rows_are_scope_filtered' => strpos($dash, '$__sg_day_rows') !== false && strpos($dash, 'foreach ($__sg_day_rows as $__sg_row)') !== false,

    'mobile_tablet_css_asset_loaded' => strpos($shell, 'assets/mobile-tablet-ux.css') !== false && strpos($css, '@media (min-width: 761px) and (max-width: 1100px)') !== false,
    'mobile_tablet_js_asset_loaded' => strpos($shell, 'assets/mobile-tablet-ux.js') !== false && strpos($js, 'sg-app-menu-open') !== false,
    'dashboard_tablet_breakpoint_present' => strpos($dash, '@media (min-width:761px) and (max-width:1100px)') !== false,
    'dashboard_mobile_breakpoint_present' => strpos($dash, '@media (max-width:760px)') !== false,
    'dashboard_mobile_order_preserved' => strpos($dash, 'sg-card-quick{order:1') !== false && strpos($dash, 'sg-card-alerts{order:2') !== false,
    'dashboard_very_small_screen_fallback' => strpos($dash, '@media (max-width:380px)') !== false,

    'dashboard_views_resolve_in_router' => empty($missing_views),
    'guard_portaria_redirect_still_present' => strpos($shell, '$is_guarda') !== false && strpos($shell, "\$view = 'portaria';") !== false && strpos($shell, 'portaria.validar_acesso') !== false,
    'changelog_blindagem_business_rules' => strpos($changelog, 'Sem alterações em cálculos financeiros') !== false && strpos($changelog, 'Sem alterações em notas, pautas') !== false && strpos($changelog, 'Sem alterações na Portaria Digital') !== false,
];

$failed = [];
foreach ($checks as $name => $ok) {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) $failed[] = $name;
}
if (!empty($missing_views)) {
    echo 'Views dashboard sem resolução no router: ' . implode(', ', $missing_views) . PHP_EOL;
}
if ($failed) {
    fwrite(STDERR, 'Smoke Painel Principal v12.11.9.66 falhou: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Smoke Painel Principal Mobile/Tablet v12.11.9.66 OK: ' . count($checks) . '/' . count($checks) . ' checks.' . PHP_EOL;
