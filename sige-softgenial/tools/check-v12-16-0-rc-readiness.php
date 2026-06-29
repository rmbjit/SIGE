<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];

$required_docs = [
    'docs/governance/RC5-v12.16.0-hardening-final.md' => [
        'RC5',
        'Hardening Final da Fase',
        'nao gera ZIP',
        'Hash igual a baseline',
    ],
    'docs/governance/RELEASE_CANDIDATE-v12.16.0-RC5.md' => [
        'RC5 local preparado',
        'ZIP final: nao gerado',
        'Validacao humana em staging: pendente',
        'Bloqueio consciente de ZIP final',
    ],
    'docs/governance/RESIDUAL_RISKS-v12.16.0-RC5.md' => [
        'RR-16-01',
        'RR-16-04',
        'bloquear ZIP final',
    ],
    'docs/qa/QA_RESULTS-v12.16.0-RC5-hardening-final.md' => [
        'Hardening Final',
        'Validacao humana em staging',
        'Nao executado',
        'ZIP final permanece bloqueado',
    ],
    'docs/deploy/STAGING_VALIDATION-v12.16.0-RC5.md' => [
        'Perfis obrigatorios',
        'Tesouraria',
        'Guarda',
        'Criterio de aceite',
    ],
    'docs/changelog/CHANGELOG-v12-16-0-rc5-hardening-final.txt' => [
        'Release Candidate interna',
        'Nenhum ZIP final gerado',
    ],
    'docs/traceability/MATRIZ_RASTREABILIDADE-v12.16.0-institutional-product-architecture.md' => [
        'D-16-11',
        'RC5',
        'check-v12-16-0-rc-readiness.php',
    ],
    'docs/governance/RISK_REGISTER-v12.16.0-institutional-product-architecture.md' => [
        'R-16-11',
        'RR-16-04',
        'Fechado em RC5',
    ],
    'docs/migration/MIGRATION_ROLLBACK-v12.16.0-institutional-product-architecture.md' => [
        'RC1 | Mapa operacional',
        'RC2 | Checklists',
        'RC3 | Faixa operacional',
        'RC4 | Fluxos guiados',
        'RC5 | Hardening final',
    ],
];

foreach ($required_docs as $rel => $needles) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        $errors[] = 'Documento RC5 em falta: ' . $rel;
        continue;
    }
    $content = (string) file_get_contents($path);
    if (strpos($content, "\xE2\x80\x94") !== false || strpos($content, "\xE2\x80\x93") !== false) {
        $errors[] = 'Travessao tipografico encontrado em ' . $rel;
    }
    foreach ($needles as $needle) {
        if (strpos($content, $needle) === false) {
            $errors[] = $rel . ' sem marcador RC5 obrigatorio: ' . $needle;
        }
    }
}

$protected_hashes = [
    'includes/finance-core.php' => 'bcb51dcfaf93f8c45f378b825d734df2a3dea8dfe23e26b5364fc12fb5ad7266',
    'admin/finance/financeiro-pagamentos.php' => '3afab797c29cd261d25c53410ac482f267d043232b98f31c02c323caebd9a823',
    'admin/finance/financeiro-extratos.php' => 'e251917d82564372c5a779152ef6a9640754a394e95298396ac48bf53eb293d1',
    'includes/permissions-layer.php' => '3661ef0d427790f413b2148092bd2f340c4c064a9913d8eb1264301cd5728c47',
    'includes/security-kernel-rules.php' => '774436262ffab70665dcb61cca1f3c14494e7adde846d6b269665cae028d6869',
];
foreach ($protected_hashes as $rel => $expected) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        $errors[] = 'Ficheiro protegido em falta: ' . $rel;
        continue;
    }
    $actual = hash_file('sha256', $path);
    if ($actual !== $expected) {
        $errors[] = 'Hash protegido alterado em ' . $rel . ': ' . $actual;
    }
}

$run_gates = (string) file_get_contents($root . '/tools/run-gates.php');
foreach ([
    'v12.16.0 RC Readiness Contract',
    'v12.16.0 RC Readiness Smoke',
    'tools/check-v12-16-0-rc-readiness.php',
    'tools/smoke-v12-16-0-rc-readiness.php',
] as $needle) {
    if (strpos($run_gates, $needle) === false) {
        $errors[] = 'Corredor oficial sem marcador RC5: ' . $needle;
    }
}

foreach (['sige-softgenial-v12_16_0.zip', 'ZIP final: gerado'] as $forbidden) {
    foreach (array_keys($required_docs) as $rel) {
        $content = is_file($root . '/' . $rel) ? (string) file_get_contents($root . '/' . $rel) : '';
        if (strpos($content, $forbidden) !== false) {
            $errors[] = 'Marcador proibido em ' . $rel . ': ' . $forbidden;
        }
    }
}

if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR, "ERRO: {$error}\n"); }
    exit(1);
}

echo "v12.16.0 RC READINESS CONTRACT OK - hardening final, docs, riscos, staging pack e hashes P0 protegidos. ZIP final nao gerado.\n";
