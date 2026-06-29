<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$shell = is_file($root . '/includes/admin-shell.php') ? (string) file_get_contents($root . '/includes/admin-shell.php') : '';
if ($shell === '') {
    $errors[] = 'Admin shell indisponivel para smoke.';
}

$start = strpos($shell, '<style id="sige-mobile-header-professor-rc7-v121600">');
$end = strpos($shell, '</style>', $start === false ? 0 : $start);
$style = ($start !== false && $end !== false) ? substr($shell, $start, $end - $start) : '';
if ($style === '') {
    $errors[] = 'Style RC7 nao encontrado.';
}

$expectations = [
    'header em grid de tres zonas' => 'grid-template-columns:var(--space-10) minmax(0,1fr) var(--space-10)!important;',
    'pesquisa global escondida no mobile' => 'body.sige-admin-app .sg-product-pro-shell .sg-gsearch,',
    'ano lectivo escondido no mobile' => 'body.sige-admin-app .sg-product-pro-shell .sg-app-chip-year,',
    'nome do utilizador escondido no mobile' => 'body.sige-admin-app .sg-product-pro-shell .sg-app-user-meta{',
    'avatar reduzido por token' => 'body.sige-admin-app .sg-product-pro-shell .sg-app-avatar{',
    'hamburger reduzido por token' => 'body.sige-admin-app .sg-product-pro-shell .sg-app-topbar .sg-app-hamburger{',
    'logo reduzido por token' => 'body.sige-admin-app .sg-product-pro-shell .sg-app-topbar-logo{',
];
foreach ($expectations as $label => $needle) {
    if (strpos($style, $needle) === false) {
        $errors[] = 'Smoke RC7 falhou: ' . $label;
    }
}

if (preg_match('/sg-app-chip-year\{[^}]*display:inline-flex/is', $style)) {
    $errors[] = 'Smoke RC7: Ano Lectivo continua visivel no bloco tardio.';
}
if (strpos($style, 'font-weight:650') !== false || strpos($style, 'font-weight:850') !== false || strpos($style, 'font-weight:950') !== false) {
    $errors[] = 'Smoke RC7: peso tipografico nao canonico detectado.';
}
if (preg_match('/#[0-9a-f]{3,6}\b/i', $style)) {
    $errors[] = 'Smoke RC7: cor hex literal detectada.';
}

if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR, "ERRO: {$error}\n"); }
    exit(1);
}

echo "v12.16.0 MOBILE HEADER HOTFIX SMOKE OK - header mobile compacto, sem chip de ano lectivo, sem pesquisa e sem nome de utilizador no topo.\n";
