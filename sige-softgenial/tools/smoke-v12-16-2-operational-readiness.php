<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$get = static function (string $rel) use ($root): string {
    $path = $root . '/' . $rel;
    return is_file($path) ? (string) file_get_contents($path) : '';
};
$required = [
    'docs/deploy/POST_INSTALL_CHECKLIST-v12.16.2-baseline-preservation-operational-readiness.md' => ['Administrador','Director','Financeiro','Secretaria','Professor','Guarda','Encarregado','Aluno','360 px','390 px','430 px'],
    'docs/deploy/STAGING_VALIDATION-v12.16.2-operational-readiness.md' => ['Perfis obrigatorios','Criterio de aceite','prepared_not_executed_here'],
    'docs/migration/MIGRATION_ROLLBACK-v12.16.2-baseline-preservation-operational-readiness.md' => ['Condicoes de rollback imediato','ZIP de retorno','v12.16.1'],
    'docs/governance/DEFINITION_OF_DONE-v12.16.2-baseline-preservation-operational-readiness.md' => ['Criterios bloqueadores','Regra de honestidade de QA','Criterios de QA staging'],
    'docs/governance/RISK_REGISTER-v12.16.2-baseline-preservation-operational-readiness.md' => ['R-16-2-01','R-16-2-10','Alternativas rejeitadas'],
];
foreach ($required as $rel => $needles) {
    $content = $get($rel);
    if ($content === '') { $errors[] = 'documento de readiness em falta: ' . $rel; continue; }
    foreach ($needles as $needle) {
        if (strpos($content, $needle) === false) $errors[] = $rel . ' sem marcador: ' . $needle;
    }
}

$config = json_decode($get('tools/runtime-evidence/config.example.json'), true);
if (!is_array($config)) {
    $errors[] = 'config runtime invalido.';
} else {
    $roles = array_map(static fn($r) => (string)($r['role'] ?? ''), $config['roles'] ?? []);
    foreach (['administrador','director','financeiro','secretaria','professor','guarda','encarregado','aluno'] as $role) {
        if (!in_array($role, $roles, true)) $errors[] = 'perfil runtime ausente: ' . $role;
    }
    $widths = array_map(static fn($v) => (int)($v['width'] ?? 0), $config['viewports'] ?? []);
    foreach ([360,390,430,768,1366] as $width) {
        if (!in_array($width, $widths, true)) $errors[] = 'viewport runtime ausente: ' . $width;
    }
}

if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR, "ERRO: {$error}\n"); }
    exit(1);
}

echo "v12.16.2 OPERATIONAL READINESS OK - checklist, staging validation, rollback e perfis reais cobertos.\n";
