<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];

$required_docs = [
    'docs/governance/PHASE_CHARTER-v12.16.1-runtime-evidence-technical-debt-closure.md' => ['RE-16-01', 'Escopo excluido', 'Plano de rollback'],
    'docs/governance/DEFINITION_OF_DONE-v12.16.1-runtime-evidence-technical-debt-closure.md' => ['Regra de honestidade de QA', 'Critérios bloqueadores', 'Critérios de QA staging'],
    'docs/governance/RISK_REGISTER-v12.16.1-runtime-evidence-technical-debt-closure.md' => ['R-16-1-01', 'R-16-1-10', 'Alternativas rejeitadas'],
    'docs/qa/QA_PLAN-v12.16.1-runtime-evidence-technical-debt-closure.md' => ['Validações CLI obrigatórias', 'Viewports obrigatorios', 'Como validar em staging'],
    'docs/traceability/MATRIZ_RASTREABILIDADE-v12.16.1-runtime-evidence-technical-debt-closure.md' => ['RE-16-01', 'RE-16-08', 'Hashes protegidos'],
    'docs/migration/MIGRATION_ROLLBACK-v12.16.1-runtime-evidence-technical-debt-closure.md' => ['Condicoes de rollback imediato', 'ZIP de retorno', 'Rollback parcial'],
    'docs/deploy/STAGING_VALIDATION-v12.16.1-runtime-evidence.md' => ['Perfis obrigatórios', 'Critério de aceite', 'Honestidade de execução'],
];
foreach ($required_docs as $rel => $needles) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) { $errors[] = 'documento em falta: ' . $rel; continue; }
    $content = (string) file_get_contents($path);
    if (strpos($content, "â") !== false || strpos($content, "â") !== false) {
        $errors[] = 'travessao tipografico em documento: ' . $rel;
    }
    foreach ($needles as $needle) {
        if (strpos($content, $needle) === false) $errors[] = $rel . ' sem marcador: ' . $needle;
    }
}

$runtime_files = [
    'tools/runtime-evidence/README.md',
    'tools/runtime-evidence/config.example.json',
    'tools/runtime-evidence/run-browser-evidence.mjs',
];
foreach ($runtime_files as $rel) {
    if (!is_file($root . '/' . $rel)) $errors[] = 'suite runtime incompleta: ' . $rel;
}
$runner = is_file($root . '/tools/runtime-evidence/run-browser-evidence.mjs') ? (string) file_get_contents($root . '/tools/runtime-evidence/run-browser-evidence.mjs') : '';
foreach (['SIGE_EVIDENCE_BASE_URL', 'SIGE_EVIDENCE_ALLOW_PRODUCTION', 'storageState', 'screenshot', 'playwright', 'Topbar horizontal overflow'] as $needle) {
    if (strpos($runner, $needle) === false) $errors[] = 'runner runtime sem marcador: ' . $needle;
}
foreach (['SIGE_PASSWORD', 'password:', 'senha:', 'user_pass'] as $forbidden) {
    if (stripos($runner, $forbidden) !== false) $errors[] = 'runner runtime contem possivel segredo ou senha: ' . $forbidden;
}
$config_path = $root . '/tools/runtime-evidence/config.example.json';
$config = is_file($config_path) ? json_decode((string) file_get_contents($config_path), true) : null;
if (!is_array($config)) {
    $errors[] = 'config.example.json invalido.';
} else {
    $roles = array_map(static fn($r) => (string)($r['role'] ?? ''), $config['roles'] ?? []);
    foreach (['administrador','director','financeiro','secretaria','professor','guarda','encarregado','aluno'] as $role) {
        if (!in_array($role, $roles, true)) $errors[] = 'perfil ausente na suite runtime: ' . $role;
    }
    $widths = array_map(static fn($v) => (int)($v['width'] ?? 0), $config['viewports'] ?? []);
    foreach ([360,390,430,768,1366] as $width) {
        if (!in_array($width, $widths, true)) $errors[] = 'viewport ausente na suite runtime: ' . $width;
    }
}

$protected_hashes = [
    'includes/finance-core.php' => 'bcb51dcfaf93f8c45f378b825d734df2a3dea8dfe23e26b5364fc12fb5ad7266',
    'admin/finance/financeiro-pagamentos.php' => '3afab797c29cd261d25c53410ac482f267d043232b98f31c02c323caebd9a823',
    'admin/finance/financeiro-extratos.php' => 'e251917d82564372c5a779152ef6a9640754a394e95298396ac48bf53eb293d1',
    'admin/system/dashboard-view.php' => '480999e77c77f842b002707fca2665ed39221e9a264326662710c20b737f138c',
    'includes/permissions-layer.php' => '3661ef0d427790f413b2148092bd2f340c4c064a9913d8eb1264301cd5728c47',
    'includes/security-kernel-rules.php' => '774436262ffab70665dcb61cca1f3c14494e7adde846d6b269665cae028d6869',
];
foreach ($protected_hashes as $rel => $expected) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) { $errors[] = 'ficheiro protegido em falta: ' . $rel; continue; }
    $actual = hash_file('sha256', $path);
    if ($actual !== $expected) $errors[] = 'hash protegido alterado em ' . $rel . ': ' . $actual;
}

$run_gates = is_file($root . '/tools/run-gates.php') ? (string) file_get_contents($root . '/tools/run-gates.php') : '';
foreach (['v12.16.1 Runtime Evidence Contract', 'v12.16.1 Runtime Evidence Smoke', 'tools/check-v12-16-1-runtime-evidence.php', 'tools/smoke-v12-16-1-runtime-evidence.php'] as $needle) {
    if (strpos($run_gates, $needle) === false) $errors[] = 'run-gates sem marcador v12.16.1: ' . $needle;
}

if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR, "ERRO: {$error}
"); }
    exit(1);
}

echo "v12.16.1 RUNTIME EVIDENCE CONTRACT OK - suite browser, documentos, perfis, viewports, honestidade de QA e hashes P0 protegidos.
";
