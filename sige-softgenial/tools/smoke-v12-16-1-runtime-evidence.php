<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];

$build_path = $root . '/BUILD.json';
$build = is_file($build_path) ? json_decode((string) file_get_contents($build_path), true) : null;
if (!is_array($build)) {
    $errors[] = 'BUILD.json invalido.';
} else {
    if (version_compare((string)($build['version'] ?? ''), '12.16.1', '<')) $errors[] = 'BUILD.json inferior a 12.16.1.';
    if (($build['source_package']['sha256'] ?? '') !== 'f02cd9260f33bfc454b40bf5fe4b4629b97d147ac70eee158b0851283e53ff2a' && version_compare((string)($build['version'] ?? ''), '12.16.2', '<')) $errors[] = 'BUILD.json sem SHA256 da origem v12.16.0.';
    if (($build['runtime_evidence']['browser_staging'] ?? '') !== 'prepared_not_executed_here') $errors[] = 'BUILD.json sem honestidade runtime.';
    foreach (['finance','academic','permissions'] as $contract) {
        if (empty($build['protected_contracts'][$contract])) $errors[] = 'BUILD.json sem contrato protegido: ' . $contract;
    }
}

$config_path = $root . '/tools/runtime-evidence/config.example.json';
$config = is_file($config_path) ? json_decode((string) file_get_contents($config_path), true) : null;
if (!is_array($config)) {
    $errors[] = 'config runtime invalido.';
} else {
    $fatal = $config['fatalMarkers'] ?? [];
    foreach (['Fatal error', 'Parse error', '#038;view', '&#038;view'] as $needle) {
        if (!in_array($needle, $fatal, true)) $errors[] = 'fatal marker ausente: ' . $needle;
    }
    $roleViews = [];
    foreach (($config['roles'] ?? []) as $row) { $roleViews[(string)($row['role'] ?? '')] = $row['views'] ?? []; }
    $expected = [
        'professor' => ['minhas_turmas','notas','pautas'],
        'guarda' => ['portaria'],
        'financeiro' => ['financeiro-dashboard','financeiro-pagamentos','financeiro-extratos','financeiro-devedores'],
        'secretaria' => ['alunos_lista','aluno_portal','turmas'],
    ];
    foreach ($expected as $role => $views) {
        foreach ($views as $view) {
            if (!in_array($view, $roleViews[$role] ?? [], true)) $errors[] = 'suite runtime sem view ' . $view . ' para ' . $role;
        }
    }
}

foreach (['includes/admin-shell.php', 'admin/system/dashboard-view.php', 'tools/runtime-evidence/run-browser-evidence.mjs'] as $rel) {
    $content = is_file($root . '/' . $rel) ? (string) file_get_contents($root . '/' . $rel) : '';
    foreach (['#038;view=minhas_turmas', '&#038;view=minhas_turmas', '#038;view=jardim_diario', '&#038;view=jardim_diario'] as $bad) {
        if (strpos($content, $bad) !== false) $errors[] = 'padrao de redirect HTML entity encontrado em ' . $rel . ': ' . $bad;
    }
}

if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR, "ERRO: {$error}
"); }
    exit(1);
}

echo "v12.16.1 RUNTIME EVIDENCE SMOKE OK - manifesto, perfis, viewports e marcadores anti-regressao preparados.
";
