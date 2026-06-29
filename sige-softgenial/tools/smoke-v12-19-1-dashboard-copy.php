<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];

if (!defined('ABSPATH')) define('ABSPATH', $root . '/');
if (!defined('SIGE_PATH')) define('SIGE_PATH', $root . '/');
if (!defined('SIGE_URL')) define('SIGE_URL', 'https://example.test/wp-content/plugins/sige-softgenial/');
if (!defined('SIGE_VERSION')) define('SIGE_VERSION', '12.19.1');
if (!function_exists('is_admin')) { function is_admin() { return true; } }
if (!function_exists('sanitize_key')) { function sanitize_key($key) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$key)); } }
if (!function_exists('admin_url')) { function admin_url($path = '') { return 'https://example.test/wp-admin/' . ltrim((string)$path, '/'); } }
if (!function_exists('add_query_arg')) { function add_query_arg($args, $url) { return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args); } }
if (!function_exists('add_action')) { function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { return true; } }
if (!function_exists('wp_enqueue_style')) { function wp_enqueue_style() { return true; } }
if (!function_exists('wp_enqueue_script')) { function wp_enqueue_script() { return true; } }
if (!function_exists('wp_add_inline_script')) { function wp_add_inline_script() { return true; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($data) { return json_encode($data, JSON_UNESCAPED_UNICODE); } }

if (!function_exists('sige_institutional_profile_context_v121600')) {
    function sige_institutional_profile_context_v121600(int $limit = 8): array {
        return [
            'slug' => 'gestao',
            'label' => 'Gestao',
            'focus' => 'visao institucional e saude operacional',
            'areas' => ['gestao' => 3, 'portaria' => 1],
            'actions' => [
                ['label' => 'Portaria', 'hint' => 'Validar entrada e ver motivo de bloqueio', 'href' => 'https://example.test/wp-admin/admin.php?page=sige-app&view=portaria', 'area' => 'gestao', 'icon' => 'shield'],
            ],
        ];
    }
}
if (!function_exists('sige_institutional_navigation_groups_v121600')) {
    function sige_institutional_navigation_groups_v121600(int $limit_per_group = 3, int $max_groups = 4): array {
        return [
            ['label' => 'Gestao', 'focus' => 'indicadores e riscos', 'is_primary' => true],
            ['label' => 'Academico', 'focus' => 'notas e pautas', 'is_primary' => false],
        ];
    }
}

require_once $root . '/includes/profile-dashboard-intelligence.php';
$ctx = sige_profile_dashboard_context_v121900('dashboard');
if (!is_array($ctx)) $errors[] = 'contexto ausente.';
else {
    if (($ctx['version'] ?? '') !== '12.19.1') $errors[] = 'contexto deve estar em 12.19.1.';
    if (($ctx['profile']['label'] ?? '') !== 'Direcção executiva') $errors[] = 'label de gestão não está em português final.';
    if (strpos((string)($ctx['headline'] ?? ''), 'decisão hoje') === false) $errors[] = 'headline final ausente.';
    if (strpos((string)($ctx['summary'] ?? ''), 'Não altera dados nem regras') === false) $errors[] = 'summary final ausente.';
    if (($ctx['cards'][0]['label'] ?? '') !== 'Primeiro passo recomendado') $errors[] = 'card 1 não foi humanizado.';
    if (($ctx['cards'][1]['label'] ?? '') !== 'Atenção antes de agir') $errors[] = 'card 2 não foi humanizado.';
    if (($ctx['cards'][2]['label'] ?? '') !== 'Regra de segurança') $errors[] = 'card 3 não foi humanizado.';
    if (strpos((string)($ctx['meta']['areasLabel'] ?? ''), 'áreas operacionais visíveis') === false) $errors[] = 'meta áreas sem acentuação.';
    if (strpos((string)($ctx['dismissKey'] ?? ''), 'v121901') === false) $errors[] = 'dismissKey não força nova visualização do copy corrigido.';
    if (($ctx['groups'][0]['label'] ?? '') !== 'Gestão') $errors[] = 'grupo Gestão não foi acentuado.';
    if (($ctx['groups'][1]['label'] ?? '') !== 'Académico') $errors[] = 'grupo Académico não foi acentuado.';
}

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "ERRO: {$error}
");
    exit(1);
}

echo "v12.19.1 DASHBOARD COPY SMOKE OK - contexto devolve português final, acentos e labels humanizados.
";
