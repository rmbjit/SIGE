<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
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
if (!function_exists('wp_json_encode')) { function wp_json_encode($data) { return json_encode($data); } }

if (!function_exists('sige_institutional_profile_context_v121600')) {
    function sige_institutional_profile_context_v121600(int $limit = 8): array {
        return [
            'slug' => 'tesouraria',
            'label' => 'Tesouraria',
            'focus' => 'recebimentos e dividas',
            'areas' => ['tesouraria' => 3, 'secretaria' => 1],
            'actions' => [
                ['label' => 'Registar pagamento', 'hint' => 'Ver divida e emitir recibo', 'href' => 'https://example.test/wp-admin/admin.php?page=sige-app&view=financeiro-pagamentos', 'area' => 'tesouraria', 'icon' => 'wallet'],
                ['label' => 'Devedores', 'hint' => 'Priorizar cobrancas', 'href' => 'https://example.test/wp-admin/admin.php?page=sige-app&view=financeiro-devedores', 'area' => 'tesouraria', 'icon' => 'trending'],
            ],
        ];
    }
}
if (!function_exists('sige_institutional_navigation_groups_v121600')) {
    function sige_institutional_navigation_groups_v121600(int $limit_per_group = 3, int $max_groups = 4): array {
        return [
            ['label' => 'Tesouraria', 'focus' => 'pagamentos e cobrancas', 'is_primary' => true],
            ['label' => 'Secretaria', 'focus' => 'alunos e fichas', 'is_primary' => false],
        ];
    }
}

require_once $root . '/includes/profile-dashboard-intelligence.php';

$catalog = sige_profile_dashboard_strategy_catalog_v121900();
foreach (['gestao','tesouraria','secretaria','academico','portaria','comunicacao'] as $profile) {
    if (empty($catalog[$profile]['headline']) || empty($catalog[$profile]['risk']) || empty($catalog[$profile]['rule'])) {
        $errors[] = 'estrategia incompleta para perfil ' . $profile;
    }
}

$ctx = sige_profile_dashboard_context_v121900('dashboard');
if (!is_array($ctx)) { $errors[] = 'contexto do dashboard ausente.'; }
else {
    if (version_compare((string)($ctx['version'] ?? '0'), '12.19.0', '<')) $errors[] = 'versao errada no contexto.';
    if (($ctx['profile']['slug'] ?? '') !== 'tesouraria') $errors[] = 'perfil esperado tesouraria nao resolvido.';
    if (empty($ctx['cards']) || !is_array($ctx['cards']) || count($ctx['cards']) !== 3) $errors[] = 'cards executivos invalidos.';
    if (empty($ctx['actions']) || !is_array($ctx['actions']) || count($ctx['actions']) < 2) $errors[] = 'accoes prioritarias invalidas.';
    if (empty($ctx['groups']) || !is_array($ctx['groups'])) $errors[] = 'grupos operacionais invalidos.';
    if (empty($ctx['dismissKey']) || preg_match('/v12190[01]/', (string)$ctx['dismissKey']) !== 1) $errors[] = 'dismissKey invalido.';
}
foreach (['alunos_lista','financeiro-pagamentos','portaria','notas','aluno_portal'] as $view) {
    if (sige_profile_dashboard_context_v121900($view) !== null) $errors[] = 'contexto deveria ficar oculto em ' . $view;
}

$_GET = ['page' => 'sige-app'];
if (sige_profile_dashboard_current_view_v121900() !== 'dashboard') $errors[] = 'current_view sem view deveria resolver dashboard.';
$_GET = ['page' => 'sige-app', 'view' => 'dashboard'];
if (sige_profile_dashboard_current_view_v121900() !== 'dashboard') $errors[] = 'current_view dashboard invalido.';
$_GET = ['page' => 'outra', 'view' => 'dashboard'];
if (sige_profile_dashboard_current_view_v121900() !== '') $errors[] = 'current_view deveria bloquear pagina fora do sige-app.';

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "ERRO: {$error}\n");
    exit(1);
}

echo "v12.19.0 DASHBOARD INTELLIGENCE SMOKE OK - perfis, contexto dashboard-only, accoes e exclusoes funcionam sem WordPress real.\n";
