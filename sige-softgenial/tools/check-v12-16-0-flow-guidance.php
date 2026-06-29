<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];

$helper_rel = 'includes/institutional-product-map.php';
$dashboard_rel = 'admin/system/dashboard-view.php';
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
$runner = $read($runner_rel);

$markers = [
    $helper_rel => [
        'sige_institutional_flow_guidance_catalog_v121600',
        'sige_institutional_flow_guidance_v121600',
        'Registar pagamento',
        'Lançar mensalidades',
        'Fecho de caixa',
        'Novo aluno',
        'Lançar notas',
        'Aprovar pauta',
        'Validar entrada',
        'Enviar comunicação',
        'guardrail',
        'steps',
        'sige_ipu_can_any_v121600($permissions, $legacy_caps)',
    ],
    $dashboard_rel => [
        '$__sg_operational_flows',
        'sige_institutional_flow_guidance_v121600',
        'Fluxos Guiados',
        'sg-card-flow-guidance',
        'sg-flow-card',
        'sg-flow-steps',
        'sg-flow-guardrail',
        'sg-flow-action',
    ],
    $runner_rel => [
        'v12.16.0 Flow Guidance Contract',
        'v12.16.0 Flow Guidance Smoke',
    ],
];

foreach ($markers as $rel => $needles) {
    $src = $rel === $helper_rel ? $helper : ($rel === $dashboard_rel ? $dashboard : $runner);
    foreach ($needles as $needle) {
        if (strpos($src, $needle) === false) {
            $errors[] = "Marcador ausente em {$rel}: {$needle}";
        }
    }
}

$forbidden_helper_patterns = [
    '/\$wpdb\b/' => 'helper nao pode aceder directamente ao banco',
    '/\bINSERT\b/i' => 'helper nao pode fazer escrita SQL directa',
    '/\bUPDATE\b/i' => 'helper nao pode fazer escrita SQL directa',
    '/\bDELETE\b/i' => 'helper nao pode fazer escrita SQL directa',
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

$flow_count = preg_match_all("/'steps'\s*=>\s*\[/", $helper);
if ($flow_count < 7) {
    $errors[] = "Catalogo de fluxos guiados insuficiente: {$flow_count} itens encontrados.";
}

if (preg_match('/sige_institutional_flow_guidance_v121600\([^\)]*\)/', $dashboard) !== 1) {
    $errors[] = 'Dashboard nao invoca a orientacao de fluxos.';
}

if (strpos($dashboard, 'href="<?php echo esc_url((string)$__sg_flow[\'href\']); ?>"') === false) {
    $errors[] = 'Link dos fluxos guiados deve escapar URL.';
}

if (preg_match('/<form|<button[^>]+type="submit"|wp_nonce_field|admin-post\.php|wp_ajax_/i', $helper) === 1) {
    $errors[] = 'Orientacao de fluxos nao pode introduzir superficie de escrita.';
}

if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR, "ERRO: {$error}\n"); }
    exit(1);
}

echo "v12.16.0 FLOW GUIDANCE CONTRACT OK - microcopy de fluxos read-only, filtrada por permissao e integrada no dashboard.\n";
