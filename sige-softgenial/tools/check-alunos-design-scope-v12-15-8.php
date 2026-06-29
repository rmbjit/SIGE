<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$cssPath = $root . '/assets/views/alunos-design-pro.css';
$jsPath = $root . '/assets/views/alunos-design-pro.js';
$uiPath = $root . '/includes/ui-kit.php';
$errors = [];
foreach ([$cssPath, $jsPath, $uiPath] as $path) {
    if (!is_file($path)) $errors[] = 'Ficheiro em falta: ' . str_replace($root . '/', '', $path);
}
if ($errors) { fwrite(STDERR, implode("\n", $errors) . "\n"); exit(1); }
$css = file_get_contents($cssPath);
$js = file_get_contents($jsPath);
$ui = file_get_contents($uiPath);

if (strpos($ui, "sige_design_alunos_v12157_enabled") === false) {
    $errors[] = 'Feature flag reversivel de Alunos ausente no ui-kit.';
}
if (strpos($ui, "'alunos_lista'") === false || strpos($ui, 'sige-alunos-design-pro-v12158') === false) {
    $errors[] = 'Assets de Alunos v12.15.8 nao estao enfileirados apenas para alunos_lista.';
}
if (preg_match('/sige-design-system-pro\.(css|js)/', $ui)) {
    $errors[] = 'Camada global antiga sige-design-system-pro foi reintroduzida no ui-kit.';
}
foreach (['eval(', 'new Function', 'unsafe-inline'] as $bad) {
    if (stripos($js, $bad) !== false || stripos($css, $bad) !== false) {
        $errors[] = 'Padrao proibido encontrado em assets de Alunos: ' . $bad;
    }
}

$flat = preg_replace('!/\*.*?\*/!s', '', $css);
$forbiddenGlobal = [
    '/(^|\})\s*button\s*[,{]/m',
    '/(^|\})\s*input\s*[,{]/m',
    '/(^|\})\s*select\s*[,{]/m',
    '/(^|\})\s*textarea\s*[,{]/m',
    '/(^|\})\s*table\s*[,{]/m',
    '/(^|\})\s*\.card\s*[,{]/m',
    '/(^|\})\s*\.modal\s*[,{]/m',
];
foreach ($forbiddenGlobal as $rx) {
    if (preg_match($rx, $flat)) $errors[] = 'Selector global proibido encontrado no CSS de Alunos: ' . $rx;
}

$rules = preg_split('/}/', $flat);
foreach ($rules as $rule) {
    $pos = strpos($rule, '{');
    if ($pos === false) continue;
    $selector = trim(substr($rule, 0, $pos));
    if ($selector === '' || $selector[0] === '@') continue;
    $parts = array_map('trim', explode(',', $selector));
    foreach ($parts as $part) {
        if ($part === '' || $part[0] === '@') continue;
        if (strpos($part, 'body.sige-admin-app.sige-view-alunos_lista') !== 0) {
            $errors[] = 'Selector nao escopado ao modulo Alunos: ' . $part;
            break 2;
        }
    }
}

foreach ([
    '.sige-card-actions-open' => 'classe de layering no card aberto',
    '.sige-alunos-actions-open' => 'classe de estado no body quando existe menu aberto',
    'z-index: var(--z-popover) !important' => 'z-index elevado no card/menu aberto',
        'pointer-events: auto !important' => 'menu aberto clicavel por cima de cards vizinhos',
    'isolation: isolate !important' => 'contexto de stacking isolado na grelha/card'
] as $needle => $label) {
    if (strpos($css, $needle) === false) $errors[] = 'Contrato ausente: ' . $label;
}

if (strpos($js, 'sige-card-actions-open') === false || strpos($js, 'sige-alunos-actions-open') === false) {
    $errors[] = 'JS nao sincroniza classes de layering para menu de accoes aberto.';
}
if (strpos($css, 'details.sige-card-actions[open] > .sige-actions-menu') === false || strpos($css, 'grid-template-columns: 1fr !important') === false) {
    $errors[] = 'Contrato de menu vertical dos tres pontinhos nao encontrado.';
}

if ($errors) {
    fwrite(STDERR, "check-alunos-design-scope-v12-15-8: FALHOU\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
echo "check-alunos-design-scope-v12-15-8: OK - escopo de Alunos preservado e layering do menu de accoes protegido.\n";
