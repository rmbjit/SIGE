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

if (strpos($versionFile, "Version: 12.15.7") === false || strpos($versionFile, "define('SIGE_VERSION', '12.15.7')") === false) {
    $errors[] = 'Versao 12.15.7 nao sincronizada em sige-softgenial.php.';
}
if (($build['version'] ?? '') !== '12.15.7') $errors[] = 'BUILD.json nao aponta para 12.15.7.';
if (strpos($changelog, 'v12.15.7 - Design System PRO: Alunos e Matrículas') === false) $errors[] = 'CHANGELOG principal sem entrada v12.15.7.';
foreach ([
    'docs/design-system/ALUNOS_CONTRACT-v12.15.7.md',
    'docs/changelog/CHANGELOG-v12-15-7-design-system-alunos-matriculas.txt',
    'docs/qa/QA-v12.15.7-design-system-alunos.md',
    'docs/traceability/MATRIZ_RASTREABILIDADE-v12.15.7-design-system-alunos.md',
    'docs/rediagnostico/REDIAGNOSTICO-v12.15.7-design-system-alunos.md',
] as $rel) {
    if (!is_file($root . '/' . $rel)) $errors[] = 'Artefacto em falta: ' . $rel;
}
if (strpos($ui, "sige_view === 'alunos_lista'") === false) $errors[] = 'ui-kit nao limita enqueue a alunos_lista.';
if (strpos($css, '@media (max-width: 900px)') === false || strpos($css, '@media (max-width: 760px)') === false || strpos($css, '@media (max-width: 420px)') === false) {
    $errors[] = 'Breakpoints mobile/tablet de Alunos incompletos.';
}
if (strpos($css, 'overflow-wrap: break-word !important') === false || strpos($css, 'word-break: normal !important') === false) {
    $errors[] = 'Contrato anti texto letra por letra nao encontrado.';
}
if (strpos($css, 'details.sige-card-actions[open] > .sige-actions-menu') === false) {
    $errors[] = 'Contrato do menu aberto dos tres pontinhos ausente.';
}
if (strpos($js, 'aria-expanded') === false || strpos($js, "ev.key !== 'Escape'") === false) {
    $errors[] = 'JS de acessibilidade dos menus de accao incompleto.';
}
if (preg_match('/\b(eval|Function)\s*\(/', $js)) $errors[] = 'JS contem eval/Function proibido.';

if ($errors) {
    fwrite(STDERR, "smoke-alunos-design-pro-v12-15-7: FALHOU\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
echo "smoke-alunos-design-pro-v12-15-7: OK - Design System de Alunos versionado, escopado e reversivel.\n";
