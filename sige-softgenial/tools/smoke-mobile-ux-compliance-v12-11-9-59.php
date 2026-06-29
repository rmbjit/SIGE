<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$admin = file_get_contents($root . '/includes/admin-shell.php');
$main = file_get_contents($root . '/sige-softgenial.php');
$build = file_get_contents($root . '/BUILD.json');
$checks = [
    'version_main_header' => strpos($main, 'Version: 12.11.9.59') !== false,
    'version_constant' => strpos($main, "define('SIGE_VERSION', '12.11.9.59');") !== false,
    'build_version' => strpos($build, '12.11.9.59') !== false,
    'compliance_css_present' => strpos($admin, 'sige-mobile-ux-compliance-v1211959') !== false,
    'compliance_js_present' => strpos($admin, 'sige-mobile-ux-compliance-v1211959-js') !== false,
    'table_scroll_wrapper' => strpos($admin, 'sg-mobile-table-scroll') !== false,
    'modal_mobile_rules' => strpos($admin, 'max-height:calc(100dvh - 16px)') !== false,
    'footer_sticky_mobile' => strpos($admin, 'position:sticky!important') !== false,
    'touch_targets' => strpos($admin, 'min-height:44px!important') !== false,
    'focus_visible' => strpos($admin, ':focus-visible') !== false,
    'logo_contain' => strpos($admin, 'object-fit:contain') !== false,
    'business_rules_guarded' => strpos($admin, 'Não altera regras de negócio') !== false,
];
$ok = 0;
foreach ($checks as $name => $pass) {
    echo ($pass ? 'OK   ' : 'FAIL ') . $name . PHP_EOL;
    if ($pass) $ok++;
}
echo "RESULT: {$ok}/" . count($checks) . " checks OK" . PHP_EOL;
exit($ok === count($checks) ? 0 : 1);
