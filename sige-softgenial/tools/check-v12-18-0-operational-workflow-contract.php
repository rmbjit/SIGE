<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$include = $root . '/includes/operational-workflow-hardening.php';
$css = $root . '/assets/operational-workflow-v12-18-0.css';
$js = $root . '/assets/operational-workflow-v12-18-0.js';
$main = (string) file_get_contents($root . '/sige-softgenial.php');

foreach ([$include, $css, $js] as $path) {
    if (!is_file($path)) $errors[] = 'ficheiro v12.18.0 em falta: ' . str_replace($root . '/', '', $path);
}
if (strpos($main, "includes/operational-workflow-hardening.php") === false) {
    $errors[] = 'bootstrap principal nao carrega operational-workflow-hardening.php.';
}

$src = is_file($include) ? (string) file_get_contents($include) : '';
foreach ([
    'sige_operational_workflow_catalog_v121800',
    'sige_operational_workflow_context_v121800',
    'sige_operational_workflow_current_view_v121800',
    'sige_operational_workflow_url_v121800',
    'admin_enqueue_scripts',
    'operational-workflow-v12-18-0.css',
    'operational-workflow-v12-18-0.js',
] as $needle) {
    if (strpos($src, $needle) === false) $errors[] = 'contrato operacional ausente: ' . $needle;
}

$requiredViews = ['alunos_lista','financeiro-pagamentos','financeiro-devedores','financeiro-gerador','financeiro-extratos','notas','minhas_turmas','aprovar_notas','pautas','portaria','whatsapp_central','comunicacoes_central','turmas'];
foreach ($requiredViews as $view) {
    if (strpos($src, "'" . $view . "'") === false) $errors[] = 'view obrigatoria ausente do catalogo: ' . $view;
}

$writeNeedles = ['update_option', 'add_option', 'delete_option', 'wp_insert_post', 'wp_delete_post', 'ALTER TABLE', 'CREATE TABLE', 'DROP TABLE', 'INSERT INTO', 'DELETE FROM', 'UPDATE '];
foreach ($writeNeedles as $needle) {
    if (stripos($src, $needle) !== false) $errors[] = 'include operacional contem escrita proibida: ' . $needle;
}

$jsSrc = is_file($js) ? (string) file_get_contents($js) : '';
foreach (['fetch(', 'XMLHttpRequest', 'jQuery.ajax', '$.ajax', 'document.cookie'] as $needle) {
    if (strpos($jsSrc, $needle) !== false) $errors[] = 'JS operacional contem comportamento proibido: ' . $needle;
}
foreach (['SIGEOperationalWorkflowV121800', 'data-sg-operational-workflow-v121800', 'sessionStorage', 'insertBefore'] as $needle) {
    if (strpos($jsSrc, $needle) === false) $errors[] = 'JS operacional sem contrato esperado: ' . $needle;
}

$cssSrc = is_file($css) ? (string) file_get_contents($css) : '';
foreach (['sg-operational-workflow-v121800', 'data-tone="financeiro"', 'data-tone="academico"', 'max-width:760px'] as $needle) {
    if (strpos($cssSrc, $needle) === false) $errors[] = 'CSS operacional sem contrato esperado: ' . $needle;
}

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "ERRO: {$error}\n");
    exit(1);
}

echo "v12.18.0 OPERATIONAL WORKFLOW CONTRACT OK - include, assets, catalogo e escopo sem escrita validados.\n";
