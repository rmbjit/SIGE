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
        'sige_institutional_checklist_catalog_v121600',
        'sige_institutional_operational_checklist_v121600',
        'sige_institutional_signal_int_v121600',
        'alunos_com_divida',
        'pagamentos_hoje',
        'alunos_sem_doc',
        'alunos_sem_encarregado',
        'alunos_sem_turma',
        'financeiro-devedores',
        'alunos_lista',
        'turmas',
        'portaria',
        'sige_core_status',
    ],
    $dashboard_rel => [
        '$__sg_operational_signals',
        '$__sg_operational_checklist',
        'sige_institutional_operational_checklist_v121600',
        'Checklist Operacional',
        'sg-card-checklist',
        'sg-check-item',
    ],
    $runner_rel => [
        'v12.16.0 Operational Checklist Contract',
        'v12.16.0 Operational Checklist Smoke',
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

$catalog_count = preg_match_all("/'metric_key'\s*=>\s*'[^']*'/", $helper);
if ($catalog_count < 8) {
    $errors[] = "Catalogo de checklists insuficiente: {$catalog_count} itens encontrados.";
}

if (strpos($helper, 'sige_ipu_can_any_v121600($permissions, $legacy_caps)') === false) {
    $errors[] = 'Checklist nao passa pela funcao central de filtragem por permissao.';
}

if (preg_match('/sige_institutional_operational_checklist_v121600\([^\)]*\)/', $dashboard) !== 1) {
    $errors[] = 'Dashboard nao invoca o checklist operacional.';
}

if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR, "ERRO: {$error}\n"); }
    exit(1);
}

echo "v12.16.0 OPERATIONAL CHECKLIST CONTRACT OK - checklist read-only, filtrado por permissao e integrado no dashboard.\n";
