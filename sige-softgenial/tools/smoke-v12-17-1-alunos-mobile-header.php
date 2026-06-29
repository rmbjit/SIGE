<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$src = (string) file_get_contents($root . '/admin/academic/alunos_lista.php');
$errors = [];

$start = strpos($src, 'v12.17.1 - Alunos mobile header containment hotfix');
$end = strpos($src, '</style>', $start === false ? 0 : $start);
$block = ($start !== false && $end !== false) ? substr($src, $start, $end - $start) : '';

if ($block === '') $errors[] = 'bloco CSS v12.17.1 nao encontrado antes do fecho do style principal.';

$expectations = [
    '/@media \(max-width:760px\)/' => 'media query mobile 760px',
    '/\.sg-app-topbar\s*\{[^}]*display:grid!important;/s' => 'topbar em grid',
    '/\.sg-app-topbar\s*\{[^}]*grid-template-columns:var\(--space-10\) minmax\(0,1fr\) var\(--space-10\)!important;/s' => 'colunas 42 1fr 42',
    '/\.sg-app-chip-year\s*\{\s*display:none!important;\s*\}/s' => 'ano lectivo escondido',
    '/\.sg-app-topbar-meta\s*\{[^}]*width:var\(--space-10\)!important;[^}]*max-width:var\(--space-10\)!important;/s' => 'meta direita com largura fixa',
    '/\.sg-app-user\s*\{[^}]*width:var\(--space-10\)!important;[^}]*height:var\(--space-10\)!important;/s' => 'user container fixo',
    '/\.sg-app-avatar\s*\{[^}]*width:var\(--space-10\)!important;[^}]*height:var\(--space-10\)!important;[^}]*overflow:hidden!important;/s' => 'avatar contido',
    '/\.sg-app-avatar img\s*\{[^}]*object-fit:cover!important;[^}]*display:block!important;/s' => 'imagem do avatar controlada',
];
foreach ($expectations as $pattern => $label) {
    if (!preg_match($pattern, $block)) $errors[] = 'smoke falhou: ' . $label;
}

if (preg_match('/@media \(min-width:761px\).*v12\.17\.1/s', $block)) {
    $errors[] = 'hotfix nao deve afectar desktop/tablet acima de 760px.';
}

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "ERRO: {$error}\n");
    exit(1);
}

echo "v12.17.1 ALUNOS MOBILE HEADER SMOKE OK - estrutura CSS mobile evita avatar cortado.\n";
