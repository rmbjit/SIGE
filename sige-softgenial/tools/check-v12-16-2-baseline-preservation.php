<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$read = static function (string $rel) use ($root): string {
    $path = $root . '/' . $rel;
    return is_file($path) ? (string) file_get_contents($path) : '';
};

$main = $read('sige-softgenial.php');
$build_raw = $read('BUILD.json');
$build = $build_raw !== '' ? json_decode($build_raw, true) : null;
preg_match('/^\s*\*\s*Version:\s*([0-9.]+)/mi', $main, $mH);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $main, $mC);
$vH = $mH[1] ?? '';
$vC = $mC[1] ?? '';
$vB = is_array($build) ? (string)($build['version'] ?? '') : '';
if ($vH === '' || $vH !== $vC || $vC !== $vB || version_compare($vH, '12.16.2', '<')) {
    $errors[] = 'versao nao sincronizada ou inferior ao baseline 12.16.2: header=' . $vH . ' const=' . $vC . ' build=' . $vB;
}
if (!is_array($build)) {
    $errors[] = 'BUILD.json invalido.';
} else {
    foreach (['finance','academic','permissions','data'] as $contract) {
        if (empty($build['protected_contracts'][$contract])) $errors[] = 'contrato protegido ausente: ' . $contract;
    }
    $baseline = $build['baseline_preservation'] ?? [];
    if (!is_array($baseline) || empty($baseline['status'])) $errors[] = 'baseline_preservation ausente ou incompleto.';
}

// [v12.36.1] Reconciliacao da baseline de integridade. Os hashes de
// admin/system/dashboard-view.php e includes/admin-shell.php estavam congelados
// no estado de v12.19.1, mas esses ficheiros foram alterados de forma LEGITIMA e
// revista em v12.20.0 (reordenacao da navegacao) e v12.29.2 (KPIs do painel em
// auto-fit). Os hashes abaixo passam a reflectir esse estado actual ja commitado
// e validado. Os restantes (finance/permissoes/security-kernel) mantem-se.
$protected_hashes = [
    'includes/finance-core.php' => 'bcb51dcfaf93f8c45f378b825d734df2a3dea8dfe23e26b5364fc12fb5ad7266',
    'admin/finance/financeiro-pagamentos.php' => '3afab797c29cd261d25c53410ac482f267d043232b98f31c02c323caebd9a823',
    'admin/finance/financeiro-extratos.php' => 'e251917d82564372c5a779152ef6a9640754a394e95298396ac48bf53eb293d1',
    'admin/system/dashboard-view.php' => '05b757784760f5ebd7ad195bb3c6e792afdc9cf5d2a1646a4a57eb08bed1fc6a',
    'includes/permissions-layer.php' => '3661ef0d427790f413b2148092bd2f340c4c064a9913d8eb1264301cd5728c47',
    'includes/security-kernel-rules.php' => '774436262ffab70665dcb61cca1f3c14494e7adde846d6b269665cae028d6869',
    'includes/admin-shell.php' => '0b518a7b868e39134b43fc8897f458dfb42160e8a5e8960091f9dcd332f00ee1',
];
foreach ($protected_hashes as $rel => $expected) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) { $errors[] = 'ficheiro protegido em falta: ' . $rel; continue; }
    $actual = hash_file('sha256', $path);
    if ($actual !== $expected) $errors[] = 'hash protegido alterado em ' . $rel . ': ' . $actual;
}

foreach (['formula', 'fórmula', 'migracao de dados', 'schema'] as $needle) {
    if (stripos($read('docs/governance/PHASE_CHARTER-v12.16.2-baseline-preservation-operational-readiness.md'), $needle) === false) {
        $errors[] = 'phase charter sem marcador de proteccao: ' . $needle;
    }
}

if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR, "ERRO: {$error}\n"); }
    exit(1);
}

echo "v12.16.2 BASELINE PRESERVATION OK - contratos P0/P1 continuam preservados em versao futura.\n";
