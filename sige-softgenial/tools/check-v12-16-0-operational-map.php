<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];

$helper_rel = 'includes/institutional-product-map.php';
$dashboard_rel = 'admin/system/dashboard-view.php';
$bootstrap_rel = 'sige-softgenial.php';
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
$dashboard = $read($dashboard_rel);
$bootstrap = $read($bootstrap_rel);
$runner = $read($runner_rel);

$must_contain = [
    $helper_rel => [
        'sige_institutional_action_catalog_v121600',
        'sige_institutional_actions_v121600',
        'sige_institutional_profile_context_v121600',
        'sige_page_guard_allows',
        'financeiro-pagamentos',
        'financeiro-devedores',
        'alunos_lista',
        'minhas_turmas',
        'portaria',
        'sige_core_status',
    ],
    $dashboard_rel => [
        'sige_institutional_profile_context_v121600',
        '$__sg_operational_actions',
        '$__sg_context_actions',
        'Foco operacional para',
    ],
    $bootstrap_rel => [
        "includes/institutional-product-map.php",
    ],
    $runner_rel => [
        'v12.16.0 Operational Map Contract',
        'v12.16.0 Operational Map Smoke',
    ],
];

foreach ($must_contain as $rel => $markers) {
    $src = ${$rel === $helper_rel ? 'helper' : ($rel === $dashboard_rel ? 'dashboard' : ($rel === $bootstrap_rel ? 'bootstrap' : 'runner'))};
    foreach ($markers as $marker) {
        if (strpos($src, $marker) === false) {
            $errors[] = "Marcador ausente em {$rel}: {$marker}";
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
    '/\bcurrent_user_can\s*\(/' => 'helper nao pode aumentar uso directo de capabilities WP',
];
foreach ($forbidden_helper_patterns as $pattern => $message) {
    if (preg_match($pattern, $helper)) { $errors[] = $message; }
}

$catalog_count = preg_match_all("/'view'\s*=>\s*'[^']+'/", $helper);
if ($catalog_count < 10) {
    $errors[] = "Catalogo operacional insuficiente: {$catalog_count} views encontradas.";
}

if (strpos($helper, 'sige_ipu_can_any_v121600($permissions, $legacy_caps)') === false) {
    $errors[] = 'Catalogo nao passa pela funcao central de filtragem por permissao.';
}

if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR, "ERRO: {$error}\n"); }
    exit(1);
}

echo "v12.16.0 OPERATIONAL MAP CONTRACT OK - helper read-only, dashboard integrado e gates registados.\n";
