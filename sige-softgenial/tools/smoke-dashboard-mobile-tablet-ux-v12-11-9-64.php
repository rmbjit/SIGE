<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$main = file_get_contents($root . '/sige-softgenial.php');
$build = file_get_contents($root . '/BUILD.json');
$dash = file_get_contents($root . '/admin/system/dashboard-view.php');
$changelog = file_get_contents($root . '/CHANGELOG-v12-11-9-64-painel-principal-mobile-tablet-ux-pro.txt');

$checks = [
    'version_main_header_12_11_9_64' => strpos($main, 'Version: 12.11.9.64') !== false,
    'version_constant_12_11_9_64' => strpos($main, "define('SIGE_VERSION', '12.11.9.64');") !== false,
    'build_version_12_11_9_64' => strpos($build, '12.11.9.64') !== false && strpos($build, 'painel-principal-mobile-tablet-ux-pro') !== false,
    'dashboard_ux_marker' => strpos($dash, 'data-sg-dashboard-ux="12.11.9.64"') !== false,
    'dashboard_mobile_actions' => strpos($dash, 'sg-dash-mobile-actions') !== false && strpos($dash, '$__sg_dashboard_actions') !== false,
    'dashboard_focus_strip' => strpos($dash, 'sg-dash-focus-strip') !== false && strpos($dash, '$__sg_focus_cards') !== false,
    'dashboard_alerts_actionable' => strpos($dash, 'class="sg-alert-item" href=') !== false && strpos($dash, 'sg-alert-action') !== false,
    'dashboard_tablet_two_column' => strpos($dash, '@media (min-width:761px) and (max-width:1100px)') !== false && strpos($dash, 'sg-dash-grid{grid-template-columns:repeat(2') !== false,
    'dashboard_mobile_order' => strpos($dash, 'sg-card-quick{order:1') !== false && strpos($dash, 'sg-card-alerts{order:2') !== false,
    'dashboard_mobile_kpi_2x2' => strpos($dash, 'sg-kpi-grid{grid-template-columns:repeat(2') !== false,
    'dashboard_no_sql_mutation_keywords' => !preg_match('/\b(UPDATE|INSERT|DELETE|ALTER|DROP|TRUNCATE|CREATE)\b/i', $dash),
    'business_rules_guarded' => strpos($changelog, 'Sem alterações em regras académicas') !== false && strpos($changelog, 'Sem alterações em regras financeiras') !== false,
];

$failed = [];
foreach ($checks as $name => $ok) {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$ok) $failed[] = $name;
}

if ($failed) {
    fwrite(STDERR, 'Smoke dashboard mobile/tablet v12.11.9.64 falhou: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Smoke dashboard mobile/tablet v12.11.9.64 concluido com sucesso.' . PHP_EOL;
