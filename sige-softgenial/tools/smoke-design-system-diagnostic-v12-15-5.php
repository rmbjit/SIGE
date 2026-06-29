<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$ok = 0;

function sige_ds55_assert(bool $condition, string $message): void {
    global $errors, $ok;
    if ($condition) {
        $ok++;
        echo "OK   {$message}\n";
    } else {
        $errors[] = $message;
        echo "FALHOU {$message}\n";
    }
}

function sige_ds55_file(string $path): string {
    $txt = @file_get_contents($path);
    return is_string($txt) ? $txt : '';
}

$plugin = sige_ds55_file($root . '/sige-softgenial.php');
$build = json_decode(sige_ds55_file($root . '/BUILD.json'), true);
$baseline = json_decode(sige_ds55_file($root . '/docs/design-system/DESIGN_SYSTEM_BASELINE-v12.15.5.json'), true);
$runGates = sige_ds55_file($root . '/tools/run-gates.php');
$changelog = sige_ds55_file($root . '/CHANGELOG.md');

$verHeader = ''; if (preg_match('/Version:\s*([0-9.]+)/', $plugin, $m)) { $verHeader = $m[1]; }
sige_ds55_assert($verHeader !== '' && version_compare($verHeader, '12.15.5', '>='), 'header do plugin em 12.15.5 ou superior');
$verConst = ''; if (preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\);/", $plugin, $m2)) { $verConst = $m2[1]; }
sige_ds55_assert($verConst !== '' && version_compare($verConst, '12.15.5', '>='), 'constante SIGE_VERSION em 12.15.5 ou superior');
sige_ds55_assert(is_array($build) && version_compare((string)($build['version'] ?? '0'), '12.15.5', '>='), 'BUILD.json em 12.15.5 ou superior');
sige_ds55_assert(is_array($baseline), 'baseline JSON legivel');
sige_ds55_assert(($baseline['intent'] ?? '') === 'diagnostic-only-no-layout-change', 'baseline declara modo diagnostico sem layout');
sige_ds55_assert(($baseline['production_layout_assets_added'] ?? true) === false, 'baseline declara zero assets de layout em producao');
sige_ds55_assert(isset($baseline['counts']['style_attr_occurrences']), 'baseline inclui contagem de style=');
sige_ds55_assert(isset($baseline['counts']['on_attr_occurrences']), 'baseline inclui contagem de on*=');
sige_ds55_assert(isset($baseline['counts']['important_occurrences']), 'baseline inclui contagem de !important');
sige_ds55_assert(strpos($runGates, 'Design System PRO Diagnostic Baseline (v12.15.5)') !== false, 'run-gates inclui smoke v12.15.5');
sige_ds55_assert(strpos($runGates, 'Design System Safety Contract (v12.15.5)') !== false, 'run-gates inclui contrato de seguranca v12.15.5');
sige_ds55_assert(strpos($changelog, '## v12.15.5') !== false, 'CHANGELOG.md contem v12.15.5');
sige_ds55_assert(is_file($root . '/docs/changelog/CHANGELOG-v12-15-5.txt'), 'changelog detalhado v12-15-5 existe');
sige_ds55_assert(is_file($root . '/docs/qa/QA-v12.15.5-design-system-pro-diagnostic-baseline.md'), 'QA formal v12.15.5 existe');
sige_ds55_assert(is_file($root . '/docs/rediagnostico/REDIAGNOSTICO-v12.15.5-design-system-pro-diagnostic-baseline.md'), 'rediagnostico formal v12.15.5 existe');

if ($errors) {
    fwrite(STDERR, "SMOKE DESIGN SYSTEM DIAGNOSTIC v12.15.5 FALHOU - " . count($errors) . " erro(s).\n");
    exit(1);
}

echo "SMOKE DESIGN SYSTEM DIAGNOSTIC v12.15.5 OK - {$ok} verificacoes passaram.\n";
exit(0);
