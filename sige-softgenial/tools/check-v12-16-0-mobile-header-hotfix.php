<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];

$shell_rel = 'includes/admin-shell.php';
$shell_path = $root . '/' . $shell_rel;
$shell = is_file($shell_path) ? (string) file_get_contents($shell_path) : '';
if ($shell === '') {
    $errors[] = 'Admin shell em falta.';
}

$start = strpos($shell, '// v12.16.0 RC7 - Mobile Header Hotfix.');
$end = strpos($shell, '// ============================================================================', $start === false ? 0 : $start + 1);
$block = ($start !== false && $end !== false) ? substr($shell, $start, $end - $start) : '';
if ($block === '') {
    $errors[] = 'Bloco RC7 do header mobile ausente.';
}

$required = [
    'sige-mobile-header-professor-rc7-v121600',
    '@media (max-width:760px)',
    'grid-template-columns:var(--space-10) minmax(0,1fr) var(--space-10)!important;',
    'min-height:calc(var(--space-10) + var(--space-8))!important;',
    'body.sige-admin-app .sg-product-pro-shell .sg-gsearch,',
    'body.sige-admin-app .sg-product-pro-shell .sg-app-chip-year,',
    'body.sige-admin-app .sg-product-pro-shell .sg-app-user-meta{',
    'display:none!important;',
    'width:var(--space-10)!important;',
    'height:var(--space-10)!important;',
    'border:var(--space-1) solid var(--color-white)!important;',
    'box-shadow:var(--shadow-sm)!important;',
    '}, 100);',
];
foreach ($required as $needle) {
    if (strpos($block, $needle) === false) {
        $errors[] = 'Marcador RC7 ausente: ' . $needle;
    }
}

$forbidden = [
    '$wpdb',
    '$_POST',
    '$_REQUEST',
    'wp_ajax_',
    'admin_post_',
    'register_rest_route',
    'INSERT ',
    'UPDATE ',
    'DELETE ',
    'window.location',
    'admin.php?page=sige-app&view=',
];
foreach ($forbidden as $bad) {
    if (stripos($block, $bad) !== false) {
        $errors[] = 'Padrao proibido no hotfix visual RC7: ' . $bad;
    }
}

$token_required_properties = [
    'grid-template-columns',
    'gap',
    'min-height',
    'padding',
    'width',
    'height',
    'font-size',
    'box-shadow',
    'border',
];
foreach ($token_required_properties as $prop) {
    if (preg_match('/(?<![-\w])' . preg_quote($prop, '/') . '\s*:\s*[^;{}]*(?:#[0-9a-f]{3,6}|\b\d+px\b)/i', $block)) {
        $errors[] = 'Valor literal detectado em propriedade tokenizada: ' . $prop;
    }
}

$runner = is_file($root . '/tools/run-gates.php') ? (string) file_get_contents($root . '/tools/run-gates.php') : '';
foreach ([
    'v12.16.0 Mobile Header Hotfix Contract',
    'v12.16.0 Mobile Header Hotfix Smoke',
] as $needle) {
    if (strpos($runner, $needle) === false) {
        $errors[] = 'Gate RC7 ausente no corredor: ' . $needle;
    }
}

if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR, "ERRO: {$error}\n"); }
    exit(1);
}

echo "v12.16.0 MOBILE HEADER HOTFIX CONTRACT OK - header mobile usa grelha tokenizada e esconde elementos que causavam aperto visual.\n";
