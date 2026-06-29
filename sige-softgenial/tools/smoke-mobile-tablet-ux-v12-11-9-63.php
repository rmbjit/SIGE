<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$main = file_get_contents($root . '/sige-softgenial.php');
$admin = file_get_contents($root . '/includes/admin-shell.php');
$build = file_get_contents($root . '/BUILD.json');
$css = file_exists($root . '/assets/mobile-tablet-ux.css') ? file_get_contents($root . '/assets/mobile-tablet-ux.css') : '';
$js = file_exists($root . '/assets/mobile-tablet-ux.js') ? file_get_contents($root . '/assets/mobile-tablet-ux.js') : '';

$checks = [
    'version_main_header_12_11_9_63' => strpos($main, 'Version: 12.11.9.63') !== false,
    'version_constant_12_11_9_63' => strpos($main, "define('SIGE_VERSION', '12.11.9.63');") !== false,
    'build_version_12_11_9_63' => strpos($build, '12.11.9.63') !== false,
    'css_asset_exists' => $css !== '',
    'js_asset_exists' => $js !== '',
    'admin_enqueue_css' => strpos($admin, "'sige-mobile-tablet-ux'") !== false && strpos($admin, 'assets/mobile-tablet-ux.css') !== false,
    'admin_enqueue_js' => strpos($admin, 'assets/mobile-tablet-ux.js') !== false,
    'late_tablet_overrides' => strpos($admin, 'sige-mobile-tablet-ux-late-v1211963') !== false && strpos($admin, '.sg-app-topbar-logo{display:inline-flex!important;}') !== false,
    'tablet_breakpoint_css' => strpos($css, '@media (min-width: 761px) and (max-width: 1100px)') !== false,
    'tablet_bottom_nav_css' => strpos($css, '.sg-mobile-global-bottom-nav') !== false && strpos($css, 'grid-template-columns: repeat(auto-fit, minmax(92px, 1fr))') !== false,
    'table_scroll_hint_css' => strpos($css, 'data-sg-scroll-hint') !== false,
    'device_classes_js' => strpos($js, 'sige-ux-tablet') !== false && strpos($js, 'data-sige-ux-viewport') !== false,
    'table_wrapper_js' => strpos($js, 'sg-mobile-table-scroll') !== false && strpos($js, 'tabindex') !== false,
    'sidebar_a11y_js' => strpos($js, 'aria-expanded') !== false && strpos($js, 'aria-hidden') !== false,
    'mutation_observer_js' => strpos($js, 'MutationObserver') !== false,
    'business_rules_guarded' => strpos(file_get_contents($root . '/CHANGELOG-v12-11-9-63-mobile-tablet-ux-jornada-pro.txt'), 'Sem alterações em regras académicas') !== false,
];

$ok = 0;
foreach ($checks as $name => $pass) {
    echo ($pass ? 'OK   ' : 'FAIL ') . $name . PHP_EOL;
    if ($pass) $ok++;
}
echo "RESULT: {$ok}/" . count($checks) . " checks OK" . PHP_EOL;
exit($ok === count($checks) ? 0 : 1);
