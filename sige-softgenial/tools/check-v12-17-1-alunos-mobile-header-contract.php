<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$file = $root . '/admin/academic/alunos_lista.php';
$src = is_file($file) ? (string) file_get_contents($file) : '';
$errors = [];

$hotfix = 'v12.17.1 - Alunos mobile header containment hotfix';
$legacy = 'v12.11.9.56 - Header & Card Alignment Mobile PRO';
$posHotfix = strpos($src, $hotfix);
$posLegacy = strpos($src, $legacy);
if ($posHotfix === false) $errors[] = 'bloco hotfix v12.17.1 ausente em alunos_lista.php.';
if ($posLegacy === false) $errors[] = 'marcador legado do header mobile de Alunos ausente.';
if ($posHotfix !== false && $posLegacy !== false && $posHotfix < $posLegacy) {
    $errors[] = 'hotfix v12.17.1 deve vir depois das regras historicas de header mobile.';
}

$required = [
    'body.sige-admin-app.sige-view-alunos_lista .sg-product-pro-shell .sg-app-topbar' => 'escopo topbar Alunos presente',
    'grid-template-columns:var(--space-10) minmax(0,1fr) var(--space-10)!important;' => 'header mobile com tres colunas fixas',
    'body.sige-admin-app.sige-view-alunos_lista .sg-app-chip-year{' => 'selector do chip de ano lectivo no hotfix',
    'display:none!important;' => 'chip de ano lectivo escondido no mobile Alunos',
    'max-width:var(--space-10)!important;' => 'meta direita limitada a 42px',
    'box-sizing:border-box!important;' => 'avatar com box sizing seguro',
    'body.sige-admin-app.sige-view-alunos_lista .sg-app-avatar img' => 'imagem do avatar controlada',
    '@media (max-width:390px)' => 'contrato para telas muito estreitas presente',
];
foreach ($required as $needle => $label) {
    if (strpos($src, $needle) === false) $errors[] = $label . ' ausente.';
}
if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "ERRO: {$error}\n");
    exit(1);
}

echo "v12.17.1 ALUNOS MOBILE HEADER CONTRACT OK - chip do ano oculto e avatar isolado no mobile de Alunos.\n";
