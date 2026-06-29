<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];

$helper_rel = 'includes/institutional-product-map.php';
$helper = is_file($root . '/' . $helper_rel) ? (string) file_get_contents($root . '/' . $helper_rel) : '';
if ($helper === '') {
    $errors[] = 'Helper institucional em falta.';
}

$required_functions = [
    'sige_institutional_actions_v121600',
    'sige_institutional_profile_context_v121600',
    'sige_institutional_operational_checklist_v121600',
    'sige_institutional_navigation_groups_v121600',
    'sige_institutional_flow_guidance_v121600',
];
foreach ($required_functions as $function) {
    if (strpos($helper, 'function ' . $function . '(') === false) {
        $errors[] = 'Funcao operacional ausente: ' . $function;
    }
}

$forbidden_helper_patterns = [
    '/\$wpdb\b/' => 'helper institucional nao pode aceder directamente ao banco',
    '/\bINSERT\b/i' => 'helper institucional nao pode fazer escrita SQL',
    '/\bUPDATE\b/i' => 'helper institucional nao pode fazer escrita SQL',
    '/\bDELETE\b/i' => 'helper institucional nao pode fazer escrita SQL',
    '/->\s*query\s*\(/' => 'helper institucional nao pode executar query directa',
    '/\badd_action\s*\(/' => 'helper institucional nao pode registar hooks',
    '/\bwp_ajax_/' => 'helper institucional nao pode abrir AJAX',
    '/\badmin_post_/' => 'helper institucional nao pode abrir admin_post',
    '/\bregister_rest_route\s*\(/' => 'helper institucional nao pode abrir REST route',
];
foreach ($forbidden_helper_patterns as $pattern => $message) {
    if (preg_match($pattern, $helper) === 1) { $errors[] = $message; }
}

$dashboard = is_file($root . '/admin/system/dashboard-view.php') ? (string) file_get_contents($root . '/admin/system/dashboard-view.php') : '';
foreach (['sige_institutional_profile_context_v121600', 'sige_institutional_operational_checklist_v121600', 'sige_institutional_flow_guidance_v121600'] as $needle) {
    if (strpos($dashboard, $needle) === false) {
        $errors[] = 'Dashboard sem bloco operacional esperado: ' . $needle;
    }
}

$shell = is_file($root . '/includes/admin-shell.php') ? (string) file_get_contents($root . '/includes/admin-shell.php') : '';
foreach (['sige_institutional_navigation_groups_v121600', 'Comece aqui', 'sg-operational-nav-rail'] as $needle) {
    if (strpos($shell, $needle) === false) {
        $errors[] = 'Shell sem faixa operacional esperada: ' . $needle;
    }
}

$rc_manifest = is_file($root . '/docs/governance/RELEASE_CANDIDATE-v12.16.0-RC5.md') ? (string) file_get_contents($root . '/docs/governance/RELEASE_CANDIDATE-v12.16.0-RC5.md') : '';
if (strpos($rc_manifest, 'ZIP final: nao gerado') === false) {
    $errors[] = 'Manifesto RC5 deve bloquear ZIP final explicitamente.';
}
if (strpos($rc_manifest, 'Validacao humana em staging: pendente') === false) {
    $errors[] = 'Manifesto RC5 deve declarar staging pendente.';
}

if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR, "ERRO: {$error}\n"); }
    exit(1);
}

echo "v12.16.0 RC READINESS SMOKE OK - superficie operacional permanece read-only, integrada e bloqueada para ZIP final sem staging.\n";
