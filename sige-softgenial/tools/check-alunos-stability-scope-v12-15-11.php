<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$errors = [];
$jsPath = $root . '/assets/views/alunos-design-pro.js';
$cssPath = $root . '/assets/views/alunos-design-pro.css';
$uiPath = $root . '/includes/ui-kit.php';
$js = is_file($jsPath) ? file_get_contents($jsPath) : '';
$css = is_file($cssPath) ? file_get_contents($cssPath) : '';
$ui = is_file($uiPath) ? file_get_contents($uiPath) : '';

if (strpos($js, "data-sige-alunos-design-pro', '12.15.11'") === false) {
    $errors[] = 'JS de Alunos nao marca versao 12.15.11.';
}
foreach (['MutationObserver', 'fallbackAlunosActionDispatcher', 'ensureModalLayerRoot', 'initModalLayerObserver', 'appendChild(modal)', 'wrapOpenFunction', 'wrapCloseFunction'] as $forbidden) {
    if (strpos($js, $forbidden) !== false) {
        $errors[] = 'JS de Alunos reintroduziu padrao proibido: ' . $forbidden;
    }
}
foreach (['eval(', 'new Function'] as $forbidden) {
    if (strpos($js, $forbidden) !== false) {
        $errors[] = 'JS de Alunos contem execucao dinamica proibida por CSP: ' . $forbidden;
    }
}
$requiredCss = [
    'v12.15.11 - Hotfix profissional' => 'marcador do hotfix',
    'sige-aluno-modal-open .sg-product-pro-shell .sg-app-content' => 'elevacao scoped da area de conteudo',
    'z-index: calc(var(--z-modal, 1000) + 149000) !important' => 'z-index da area acima da sidebar',
    'z-index: calc(var(--z-modal, 1000) + 159000) !important' => 'z-index dos modais',
    'pointer-events: none !important' => 'sidebar sem captura de clique no modal desktop'
];
foreach ($requiredCss as $needle => $label) {
    if (strpos($css, $needle) === false) $errors[] = 'CSS de Alunos sem ' . $label . '.';
}
if (substr_count($css, 'sige-view-alunos_lista') < 5) {
    $errors[] = 'CSS de Alunos parece nao estar suficientemente escopado a alunos_lista.';
}
if (strpos($ui, "alunos-design-pro.css") === false || strpos($ui, "alunos-design-pro.js") === false) {
    $errors[] = 'ui-kit nao enfileira assets especificos de Alunos.';
}
if (strpos($ui, "'alunos_lista'") === false) {
    $errors[] = 'ui-kit nao limita assets de Alunos a view=alunos_lista.';
}
if ($errors) {
    fwrite(STDERR, "check-alunos-stability-scope-v12-15-11: FALHOU\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
echo "check-alunos-stability-scope-v12-15-11: OK - estabilidade de Alunos protegida sem portal/observer/dispatcher.\n";
