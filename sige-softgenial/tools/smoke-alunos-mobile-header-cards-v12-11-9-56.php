<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$file = $root . '/admin/academic/alunos_lista.php';
$shell = $root . '/includes/admin-shell.php';
$main = $root . '/sige-softgenial.php';
$build = $root . '/BUILD.json';
$src = file_get_contents($file);
$shellSrc = file_get_contents($shell);
$mainSrc = file_get_contents($main);
$buildSrc = file_get_contents($build);
$checks = [
    'version main' => strpos($mainSrc, "12.11.9.56") !== false,
    'build version' => strpos($buildSrc, "12.11.9.56") !== false,
    'v56 css marker' => strpos($src, 'v12.11.9.56 - Header & Card Alignment Mobile PRO') !== false,
    'topbar logo markup' => strpos($shellSrc, 'sg-app-topbar-logo') !== false && strpos($shellSrc, 'logo_sistema_url') !== false,
    'topbar logo hidden default' => strpos($shellSrc, 'sg-app-topbar-logo{display:none') !== false,
    'mobile top reset wp toolbar' => strpos($src, 'html.wp-toolbar') !== false && strpos($src, '#wpbody-content') !== false,
    'hamburger reset static' => strpos($src, 'position:relative!important;') !== false && strpos($src, 'left:auto!important;') !== false,
    'topbar no wrap' => strpos($src, 'flex-wrap:nowrap!important') !== false,
    'headings padding reset' => strpos($src, 'padding-left:0!important') !== false && strpos($src, 'sg-app-topbar > div:first-of-type') !== false,
    'meta width auto right' => strpos($src, 'width:auto!important') !== false && strpos($src, 'margin-left:auto!important') !== false,
    'avatar aligned in header' => strpos($src, 'min-width:44px!important') !== false && strpos($src, 'border:3px solid rgba(255,255,255,.88)') !== false,
    'school head no negative margin' => strpos($src, 'margin:0 -16px 0!important') !== false,
    'cards grid aligned' => strpos($src, 'grid-template-columns:82px minmax(0,1fr)!important') !== false,
    'cards actions aligned' => strpos($src, 'grid-template-columns:repeat(3,minmax(0,1fr))!important') !== false,
    'official logo image CSS' => strpos($src, '.sg-app-topbar-logo img') !== false && strpos($src, 'object-fit:contain!important') !== false,
    'fallback safe mark' => strpos($src, '.sg-app-topbar-logo.is-fallback:before') !== false,
];
$fail = 0;
foreach ($checks as $name => $ok) {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$ok) $fail++;
}
if ($fail) {
    fwrite(STDERR, "Smoke v12.11.9.56 FAILED with {$fail} failure(s)" . PHP_EOL);
    exit(1);
}
echo "Smoke v12.11.9.56 OK" . PHP_EOL;
