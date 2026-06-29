<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$errors = [];
$versionFile = file_get_contents($root . '/sige-softgenial.php');
$build = json_decode(file_get_contents($root . '/BUILD.json'), true);
$ui = file_get_contents($root . '/includes/ui-kit.php');
$css = file_get_contents($root . '/assets/views/alunos-design-pro.css');
$js = file_get_contents($root . '/assets/views/alunos-design-pro.js');
$changelog = file_get_contents($root . '/CHANGELOG.md');

$pluginVersion = '';
$constVersion = '';
if (preg_match('/Version:\s*([0-9.]+)/', $versionFile, $m)) $pluginVersion = $m[1];
if (preg_match('/define\(\'SIGE_VERSION\',\s*\'([0-9.]+)\'\)/', $versionFile, $m)) $constVersion = $m[1];
if ($pluginVersion === '' || $constVersion === '' || version_compare($pluginVersion, '12.15.8', '<') || version_compare($constVersion, '12.15.8', '<')) {
    $errors[] = 'Versao 12.15.8 ou superior nao sincronizada em sige-softgenial.php.';
}
if (version_compare((string)($build['version'] ?? ''), '12.15.8', '<')) $errors[] = 'BUILD.json nao aponta para 12.15.8 ou superior.';
if (strpos($changelog, 'v12.15.8 - Alunos Action Menu Layering Closure') === false) $errors[] = 'CHANGELOG principal sem entrada v12.15.8.';
foreach ([
    'docs/design-system/ALUNOS_ACTION_MENU_LAYERING-v12.15.8.md',
    'docs/changelog/CHANGELOG-v12-15-8-alunos-action-menu-layering.txt',
    'docs/qa/QA-v12.15.8-alunos-action-menu-layering.md',
    'docs/traceability/MATRIZ_RASTREABILIDADE-v12.15.8-alunos-action-menu-layering.md',
    'docs/rediagnostico/REDIAGNOSTICO-v12.15.8-alunos-action-menu-layering.md',
] as $rel) {
    if (!is_file($root . '/' . $rel)) $errors[] = 'Artefacto em falta: ' . $rel;
}
if (strpos($ui, "sige_view === 'alunos_lista'") === false || strpos($ui, 'sige-alunos-design-pro-v12158') === false) {
    $errors[] = 'ui-kit nao limita enqueue v12.15.8 a alunos_lista.';
}
foreach ([
    'data-sige-alunos-design-pro' => 'marcador JS de versao',
    'card.classList.toggle(\'sige-card-actions-open\'' => 'classe no card aberto',
    'document.body.classList.toggle(\'sige-alunos-actions-open\'' => 'classe global de estado aberto',
    'z-index: var(--z-popover) !important' => 'card/menu aberto acima de cards vizinhos',
        'pointer-events: auto !important' => 'menu aberto clicavel'
] as $needle => $label) {
    if (strpos($css . $js, $needle) === false) $errors[] = 'Contrato ausente: ' . $label;
}
if (preg_match('/\b(eval|Function)\s*\(/', $js)) $errors[] = 'JS contem eval/Function proibido.';
if (strpos($css, 'body.sige-admin-app.sige-view-alunos_lista.sige-alunos-actions-open') === false) {
    $errors[] = 'CSS nao neutraliza hover elevado de cards vizinhos enquanto ha menu aberto.';
}

if ($errors) {
    fwrite(STDERR, "smoke-alunos-action-menu-layering-v12-15-8: FALHOU\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
echo "smoke-alunos-action-menu-layering-v12-15-8: OK - menu de accoes de Alunos protegido contra sobreposicao por hover de cards vizinhos.\n";
