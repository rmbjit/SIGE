<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$include = $root . '/includes/profile-dashboard-intelligence.php';
$css = $root . '/assets/profile-dashboard-intelligence-v12-19-0.css';
$js = $root . '/assets/profile-dashboard-intelligence-v12-19-0.js';
$main = (string) file_get_contents($root . '/sige-softgenial.php');

foreach ([$include, $css, $js] as $path) {
    if (!is_file($path)) $errors[] = 'ficheiro v12.19.0 em falta: ' . str_replace($root . '/', '', $path);
}
if (strpos($main, "includes/profile-dashboard-intelligence.php") === false) {
    $errors[] = 'bootstrap principal nao carrega profile-dashboard-intelligence.php.';
}
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $main, $mVersion);
if (version_compare($mVersion[1] ?? '0', '12.19.0', '<')) {
    $errors[] = 'SIGE_VERSION inferior a 12.19.0.';
}

$src = is_file($include) ? (string) file_get_contents($include) : '';
foreach ([
    'sige_profile_dashboard_current_view_v121900',
    'sige_profile_dashboard_context_v121900',
    'sige_profile_dashboard_strategy_catalog_v121900',
    'sige_profile_dashboard_sanitized_actions_v121900',
    'sige_institutional_profile_context_v121600',
    'sige_institutional_navigation_groups_v121600',
    'admin_enqueue_scripts',
    'profile-dashboard-intelligence-v12-19-0.css',
    'profile-dashboard-intelligence-v12-19-0.js',
] as $needle) {
    if (strpos($src, $needle) === false) $errors[] = 'contrato dashboard intelligence ausente: ' . $needle;
}
foreach (['gestao','tesouraria','secretaria','academico','portaria','comunicacao'] as $profile) {
    if (strpos($src, "'" . $profile . "'") === false) $errors[] = 'perfil obrigatorio ausente: ' . $profile;
}
$writeNeedles = ['update_option', 'add_option', 'delete_option', 'wp_insert_post', 'wp_delete_post', 'ALTER TABLE', 'CREATE TABLE', 'DROP TABLE', 'INSERT INTO', 'DELETE FROM', 'UPDATE '];
foreach ($writeNeedles as $needle) {
    if (stripos($src, $needle) !== false) $errors[] = 'include de dashboard intelligence contem escrita proibida: ' . $needle;
}

$jsSrc = is_file($js) ? (string) file_get_contents($js) : '';
foreach (['fetch(', 'XMLHttpRequest', 'jQuery.ajax', '$.ajax', 'document.cookie'] as $needle) {
    if (strpos($jsSrc, $needle) !== false) $errors[] = 'JS dashboard intelligence contem comportamento proibido: ' . $needle;
}
foreach (['SIGEProfileDashboardV121900', 'data-sg-profile-dashboard-v121900', 'sessionStorage', 'insertBefore', '.sg-dash-hero'] as $needle) {
    if (strpos($jsSrc, $needle) === false) $errors[] = 'JS dashboard intelligence sem contrato esperado: ' . $needle;
}

$cssSrc = is_file($css) ? (string) file_get_contents($css) : '';
foreach (['sg-profile-dashboard-v121900', 'data-tone="financeiro"', 'data-tone="academico"', 'max-width:760px'] as $needle) {
    if (strpos($cssSrc, $needle) === false) $errors[] = 'CSS dashboard intelligence sem contrato esperado: ' . $needle;
}

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "ERRO: {$error}\n");
    exit(1);
}

echo "v12.19.0 DASHBOARD INTELLIGENCE CONTRACT OK - include, assets, perfis, escopo dashboard-only e ausencia de escrita validados.\n";
