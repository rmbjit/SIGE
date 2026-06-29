<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];

$dashboard_rel = 'admin/system/dashboard-view.php';
$dashboard_path = $root . '/' . $dashboard_rel;
$dashboard = is_file($dashboard_path) ? (string) file_get_contents($dashboard_path) : '';
if ($dashboard === '') {
    $errors[] = 'Dashboard em falta: ' . $dashboard_rel;
}

$required = [
    'v12.16.0 RC6 - hotfix de staging',
    '$sige_professor_redirect_url = admin_url(\'admin.php?page=sige-app&view=minhas_turmas\');',
    'window.location.replace(\' . wp_json_encode($sige_professor_redirect_url) . \');',
    '$sige_educador_redirect_url = admin_url(\'admin.php?page=sige-app&view=jardim_diario\');',
    'window.location.replace(\' . wp_json_encode($sige_educador_redirect_url) . \');',
];
foreach ($required as $needle) {
    if (strpos($dashboard, $needle) === false) {
        $errors[] = 'Redirect seguro ausente no dashboard: ' . $needle;
    }
}

$forbidden = [
    'window.location.href="\' . esc_url(admin_url(\'admin.php?page=sige-app&view=minhas_turmas\')) . \'"',
    'window.location.href="\' . esc_url(admin_url(\'admin.php?page=sige-app&view=jardim_diario\')) . \'"',
    '&#038;view=minhas_turmas',
    '#038;view=minhas_turmas',
    '&#038;view=jardim_diario',
    '#038;view=jardim_diario',
];
foreach ($forbidden as $bad) {
    if (strpos($dashboard, $bad) !== false) {
        $errors[] = 'Padrao de URL inseguro ainda presente no dashboard: ' . $bad;
    }
}

if (preg_match('/window\.location\.(?:href|assign)\s*=\s*["\']\s*[^"\']*&[#a-zA-Z0-9]+;view=/', $dashboard)) {
    $errors[] = 'Redirect JS com entidade HTML detectado no dashboard.';
}

$shell = is_file($root . '/includes/admin-shell.php') ? (string) file_get_contents($root . '/includes/admin-shell.php') : '';
foreach ([
    '"minhas_turmas" => ["academico.turmas_ver"]',
    "'minhas_turmas'   => 'admin/academic/minhas_turmas-view.php'",
    "'minhas_turmas'",
] as $needle) {
    if (strpos($shell, $needle) === false) {
        $errors[] = 'Contrato de rota Minhas Turmas ausente no shell: ' . $needle;
    }
}

if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR, "ERRO: {$error}\n"); }
    exit(1);
}

echo "v12.16.0 PROFESSOR ROUTE HOTFIX CONTRACT OK - redirects de professor/educador usam JSON seguro e preservam view=.\n";
