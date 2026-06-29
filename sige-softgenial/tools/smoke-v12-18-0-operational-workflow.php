<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];

if (!defined('ABSPATH')) define('ABSPATH', $root . '/');
if (!defined('SIGE_PATH')) define('SIGE_PATH', $root . '/');
if (!defined('SIGE_URL')) define('SIGE_URL', 'https://example.test/wp-content/plugins/sige-softgenial/');
if (!defined('SIGE_VERSION')) define('SIGE_VERSION', '12.18.0');
if (!function_exists('is_admin')) { function is_admin() { return true; } }
if (!function_exists('sanitize_key')) { function sanitize_key($key) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$key)); } }
if (!function_exists('admin_url')) { function admin_url($path = '') { return 'https://example.test/wp-admin/' . ltrim((string)$path, '/'); } }
if (!function_exists('add_query_arg')) { function add_query_arg($args, $url) { return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args); } }
if (!function_exists('add_action')) { function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { return true; } }
if (!function_exists('wp_enqueue_style')) { function wp_enqueue_style() { return true; } }
if (!function_exists('wp_enqueue_script')) { function wp_enqueue_script() { return true; } }
if (!function_exists('wp_add_inline_script')) { function wp_add_inline_script() { return true; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($data) { return json_encode($data); } }

require_once $root . '/includes/operational-workflow-hardening.php';

$catalog = sige_operational_workflow_catalog_v121800();
if (count($catalog) < 12) $errors[] = 'catalogo tem menos de 12 fluxos.';

foreach (['alunos_lista','financeiro-pagamentos','notas','portaria','turmas'] as $view) {
    $ctx = sige_operational_workflow_context_v121800($view);
    if (!is_array($ctx)) { $errors[] = 'contexto ausente para ' . $view; continue; }
    if (($ctx['version'] ?? '') !== '12.18.0') $errors[] = 'versao errada em ' . $view;
    if (($ctx['view'] ?? '') !== $view) $errors[] = 'view errada em ' . $view;
    if (empty($ctx['title']) || empty($ctx['lead']) || empty($ctx['guardrail'])) $errors[] = 'microcopy incompleto em ' . $view;
    if (empty($ctx['steps']) || !is_array($ctx['steps']) || count($ctx['steps']) !== 3) $errors[] = 'passos invalidos em ' . $view;
    if (empty($ctx['dismissKey']) || strpos((string)$ctx['dismissKey'], 'v121800') === false) $errors[] = 'dismissKey invalido em ' . $view;
}

foreach (['dashboard','aluno_portal','view_inexistente'] as $view) {
    if (sige_operational_workflow_context_v121800($view) !== null) $errors[] = 'contexto deveria ficar oculto em ' . $view;
}

$_GET = ['page' => 'sige-app', 'view' => 'financeiro-pagamentos'];
if (sige_operational_workflow_current_view_v121800() !== 'financeiro-pagamentos') $errors[] = 'current_view nao resolveu financeiro-pagamentos.';
$ctx = sige_operational_workflow_context_v121800();
if (!is_array($ctx) || ($ctx['view'] ?? '') !== 'financeiro-pagamentos') $errors[] = 'contexto automatico nao resolveu view actual.';

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "ERRO: {$error}\n");
    exit(1);
}

echo "v12.18.0 OPERATIONAL WORKFLOW SMOKE OK - catalogo, contextos e exclusoes funcionam sem WordPress real.\n";
