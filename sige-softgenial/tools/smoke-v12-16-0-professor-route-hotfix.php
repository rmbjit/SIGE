<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];

$dashboard = is_file($root . '/admin/system/dashboard-view.php') ? (string) file_get_contents($root . '/admin/system/dashboard-view.php') : '';
if ($dashboard === '') {
    $errors[] = 'Dashboard indisponivel para smoke.';
}

$prof_line_ok = strpos($dashboard, 'admin.php?page=sige-app&view=minhas_turmas') !== false
    && strpos($dashboard, 'wp_json_encode($sige_professor_redirect_url)') !== false
    && strpos($dashboard, 'window.location.replace(') !== false;
if (!$prof_line_ok) {
    $errors[] = 'Smoke professor: redirect nao esta serializado com wp_json_encode.';
}

$edu_line_ok = strpos($dashboard, 'admin.php?page=sige-app&view=jardim_diario') !== false
    && strpos($dashboard, 'wp_json_encode($sige_educador_redirect_url)') !== false
    && strpos($dashboard, 'window.location.replace(') !== false;
if (!$edu_line_ok) {
    $errors[] = 'Smoke educador: redirect nao esta serializado com wp_json_encode.';
}

foreach (['#038;view', '&#038;view', 'window.location.href="' . "' . esc_url(admin_url("] as $bad) {
    if (strpos($dashboard, $bad) !== false) {
        $errors[] = 'Smoke URL: padrao inseguro encontrado no dashboard: ' . $bad;
    }
}

$simulated_url = 'https://teste.softgenial.edu.mz/wp-admin/admin.php?page=sige-app&view=minhas_turmas';
if (parse_url($simulated_url, PHP_URL_FRAGMENT) !== null) {
    $errors[] = 'Smoke URL: rota canonica contem fragmento indevido.';
}
parse_str((string) parse_url($simulated_url, PHP_URL_QUERY), $query);
if (($query['view'] ?? '') !== 'minhas_turmas') {
    $errors[] = 'Smoke URL: query view=minhas_turmas nao sobreviveu.';
}

$broken_url = 'https://teste.softgenial.edu.mz/wp-admin/admin.php?page=sige-app#038;view=minhas_turmas';
if (parse_url($broken_url, PHP_URL_FRAGMENT) !== '038;view=minhas_turmas') {
    $errors[] = 'Smoke URL: caso quebrado de staging nao foi reconhecido.';
}

if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR, "ERRO: {$error}\n"); }
    exit(1);
}

echo "v12.16.0 PROFESSOR ROUTE HOTFIX SMOKE OK - URL canonica mantem &view e o caso #038 fica bloqueado por contrato.\n";
