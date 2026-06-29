<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];

$helper_rel = 'includes/institutional-product-map.php';
$shell_rel = 'includes/admin-shell.php';
$runner_rel = 'tools/run-gates.php';

$read = static function (string $rel) use ($root, &$errors): string {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        $errors[] = "Ficheiro em falta: {$rel}";
        return '';
    }
    $src = file_get_contents($path);
    return is_string($src) ? $src : '';
};

$helper = $read($helper_rel);
$shell = $read($shell_rel);
$runner = $read($runner_rel);

$markers = [
    $helper_rel => [
        'sige_institutional_area_meta_v121600',
        'sige_institutional_navigation_groups_v121600',
        'sige_institutional_actions_v121600(0)',
        'sige_institutional_profile_context_v121600(0)',
        'is_primary',
        'limit_per_group',
        'max_groups',
    ],
    $shell_rel => [
        'sige_institutional_navigation_groups_v121600',
        '$sige_v121600_nav_groups',
        '$sige_v121600_primary_nav',
        'sg-operational-nav-rail',
        'Navegação operacional prioritária',
        'Comece aqui',
        'Outras áreas disponíveis',
    ],
    $runner_rel => [
        'v12.16.0 Operational Navigation Contract',
        'v12.16.0 Operational Navigation Smoke',
    ],
];

foreach ($markers as $rel => $needles) {
    $src = $rel === $helper_rel ? $helper : ($rel === $shell_rel ? $shell : $runner);
    foreach ($needles as $needle) {
        if (strpos($src, $needle) === false) {
            $errors[] = "Marcador ausente em {$rel}: {$needle}";
        }
    }
}

$forbidden_helper_patterns = [
    '/\$wpdb\b/' => 'helper nao pode aceder directamente ao banco',
    '/\bINSERT\b/i' => 'helper nao pode fazer INSERT',
    '/\bUPDATE\b/i' => 'helper nao pode fazer UPDATE',
    '/\bDELETE\b/i' => 'helper nao pode fazer DELETE',
    '/->\s*query\s*\(/' => 'helper nao pode executar query directa',
    '/\badd_action\s*\(/' => 'helper nao pode registar hooks',
    '/\bwp_ajax_/' => 'helper nao pode abrir superficie AJAX',
    '/\badmin_post_/' => 'helper nao pode abrir superficie admin_post',
    '/\bregister_rest_route\s*\(/' => 'helper nao pode abrir REST route',
    '/\bcurrent_user_can\s*\(/' => 'helper nao pode usar capabilities WP directamente',
];
foreach ($forbidden_helper_patterns as $pattern => $message) {
    if (preg_match($pattern, $helper)) { $errors[] = $message; }
}

if (preg_match('/<nav\s+class="sg-app-nav">/', $shell) !== 1) {
    $errors[] = 'Navegacao lateral principal deve permanecer intacta.';
}
if (strpos($shell, '<section class="sg-operational-nav-rail"') === false || strpos($shell, '<nav class="sg-app-nav">') === false) {
    $errors[] = 'Faixa operacional e nav principal devem coexistir.';
}
if (strpos($shell, '<section class="sg-operational-nav-rail"') > strpos($shell, '<nav class="sg-app-nav">')) {
    $errors[] = 'Faixa operacional deve orientar antes da lista completa de menus.';
}
if (preg_match('/sg-operational-nav-link[^\n]+href="<\?php echo esc_url\(/', $shell) !== 1) {
    $errors[] = 'Links da faixa operacional devem escapar URL.';
}
if (preg_match('/sige_institutional_navigation_groups_v121600\(3, 3\)/', $shell) !== 1) {
    $errors[] = 'Shell deve limitar a navegacao operacional para evitar poluicao visual.';
}

if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR, "ERRO: {$error}\n"); }
    exit(1);
}

echo "v12.16.0 OPERATIONAL NAVIGATION CONTRACT OK - faixa read-only no shell, nav principal intacta e helper filtrado por permissao.\n";
