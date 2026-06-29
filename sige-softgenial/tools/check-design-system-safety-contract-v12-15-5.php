<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$okCount = 0;

function sige_ds55_ok(bool $condition, string $message): void {
    global $errors, $okCount;
    if ($condition) {
        $okCount++;
        echo "OK   {$message}\n";
    } else {
        $errors[] = $message;
        echo "FALHOU {$message}\n";
    }
}

function sige_ds55_read(string $path): string {
    $txt = @file_get_contents($path);
    return is_string($txt) ? $txt : '';
}

$uiKit = sige_ds55_read($root . '/includes/ui-kit.php');
$bootstrap = sige_ds55_read($root . '/sige-softgenial.php');
$adminShell = sige_ds55_read($root . '/includes/admin-shell.php');
$coreHelpers = sige_ds55_read($root . '/includes/core-helpers.php');
$baselinePath = $root . '/docs/design-system/DESIGN_SYSTEM_BASELINE-v12.15.5.json';
$baseline = json_decode(sige_ds55_read($baselinePath), true);

sige_ds55_ok(is_file($baselinePath), 'baseline JSON do Design System existe');
sige_ds55_ok(is_array($baseline) && ($baseline['version'] ?? '') === '12.15.5', 'baseline JSON identifica a versao 12.15.5');
sige_ds55_ok(!is_file($root . '/assets/sige-design-system-pro.css'), 'CSS global agressivo sige-design-system-pro.css ausente');
sige_ds55_ok(!is_file($root . '/assets/sige-design-system-pro.js'), 'JS global agressivo sige-design-system-pro.js ausente');
sige_ds55_ok(strpos($uiKit, 'sige-design-system-pro.css') === false && strpos($uiKit, "wp_enqueue_style('sige-design-system-pro'") === false, 'ui-kit nao enfileira CSS PRO global');
sige_ds55_ok(strpos($uiKit, 'sige-design-system-pro.js') === false && strpos($uiKit, "wp_enqueue_script('sige-design-system-pro'") === false, 'ui-kit nao enfileira JS PRO global');
sige_ds55_ok(strpos($bootstrap, 'sige-design-system-pro') === false, 'bootstrap nao referencia camada PRO global removida');
sige_ds55_ok(is_file($root . '/docs/design-system/IMPLEMENTATION_STRATEGY-v12.15.5.md'), 'estrategia incremental documentada');
sige_ds55_ok(is_file($root . '/docs/governance/TECHNICAL_INVENTORY-v12.15.5-design-system-pro-diagnostic-baseline.md'), 'inventario tecnico formal existe');
sige_ds55_ok(is_file($root . '/docs/traceability/TRACEABILITY-v12.15.5-design-system-pro-diagnostic-baseline.md'), 'matriz de rastreabilidade existe');
sige_ds55_ok(strpos($coreHelpers, 'function sige_csp_nonce') !== false && strpos($adminShell, 'wp_add_inline_script') !== false, 'core e shell continuam preparados para CSP/nonce');
sige_ds55_ok(is_file($root . '/tools/smoke-standalone-csp-ui-v12-15-4.php'), 'smoke de paginas autonomas v12.15.4 preservado');

if ($errors) {
    fwrite(STDERR, "CONTRATO DESIGN SYSTEM v12.15.5 FALHOU - " . count($errors) . " erro(s).\n");
    exit(1);
}

echo "CONTRATO DESIGN SYSTEM v12.15.5 OK - {$okCount} verificacoes passaram.\n";
exit(0);
