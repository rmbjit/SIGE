<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.16.0 - Gate de governanca da fase.
 *
 * Garante que a fase Institutional Product Architecture & Operational UX
 * Hardening nao comeca por codigo solto: exige inventario, charter, DoD,
 * matriz, QA, rollback e registo de riscos antes das intervencoes funcionais.
 */
$root = dirname(__DIR__);
$errors = [];

$required = [
    'docs/governance/TECHNICAL_INVENTORY-v12.16.0-institutional-product-architecture.md' => [
        'Baseline congelada',
        'Métricas da superfície técnica',
        'Ficheiros críticos com hash de referência',
        'Riscos classificados',
    ],
    'docs/governance/INVENTORY_SUMMARY-v12.16.0-institutional-product-architecture.json' => [
        '"php_files"',
        '"critical"',
        '"views_ok_count"',
    ],
    'docs/governance/PHASE_CHARTER-v12.16.0-institutional-product-architecture.md' => [
        'Escopo incluído',
        'Escopo excluído',
        'Ordem de intervenção',
        'Plano de rollback',
    ],
    'docs/governance/DEFINITION_OF_DONE-v12.16.0-institutional-product-architecture.md' => [
        'DoD global da fase',
        'Critérios bloqueadores',
        'Regra de honestidade de QA',
    ],
    'docs/governance/RISK_REGISTER-v12.16.0-institutional-product-architecture.md' => [
        'R-16-01',
        'R-16-03',
        'R-16-10',
    ],
    'docs/traceability/MATRIZ_RASTREABILIDADE-v12.16.0-institutional-product-architecture.md' => [
        'D-16-01',
        'D-16-06',
        'D-16-09',
    ],
    'docs/qa/QA_PLAN-v12.16.0-institutional-product-architecture.md' => [
        'Validações CLI obrigatórias',
        'Testes por domínio',
        'QA manual obrigatório em staging',
    ],
    'docs/migration/MIGRATION_ROLLBACK-v12.16.0-institutional-product-architecture.md' => [
        'Baseline de retorno',
        'Rollback por RC',
        'Condições de rollback imediato',
    ],
];

foreach ($required as $rel => $needles) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        $errors[] = 'ficheiro em falta: ' . $rel;
        continue;
    }
    $content = (string) file_get_contents($path);
    if (strpos($content, "\xE2\x80\x94") !== false || strpos($content, "\xE2\x80\x93") !== false) {
        $errors[] = 'travessao tipografico encontrado em ' . $rel . ' (usar hifen simples).';
    }
    foreach ($needles as $needle) {
        if (strpos($content, $needle) === false) {
            $errors[] = $rel . ' sem marcador obrigatorio: ' . $needle;
        }
    }
}

$inventoryPath = $root . '/docs/governance/INVENTORY_SUMMARY-v12.16.0-institutional-product-architecture.json';
if (is_file($inventoryPath)) {
    $json = json_decode((string) file_get_contents($inventoryPath), true);
    if (!is_array($json)) {
        $errors[] = 'inventario JSON invalido.';
    } else {
        $expected = [
            'php_files' => 474,
            'admin_views_php' => 71,
            'includes_php' => 155,
            'views_ok_count' => 60,
            'route_map_count' => 60,
            'perm_map_count' => 60,
        ];
        foreach ($expected as $key => $value) {
            if (($json[$key] ?? null) !== $value) {
                $errors[] = 'inventario baseline com valor inesperado para ' . $key . ': esperado ' . $value;
            }
        }
        $critical = $json['critical'] ?? [];
        if (!is_array($critical) || count($critical) < 7) {
            $errors[] = 'inventario JSON sem lista minima de ficheiros criticos.';
        }
    }
}

$runGates = (string) file_get_contents($root . '/tools/run-gates.php');
if (strpos($runGates, 'tools/check-v12-16-0-governance.php') === false) {
    $errors[] = 'gate v12.16.0 nao esta ligado ao corredor oficial.';
}

if ($errors) {
    fwrite(STDERR, "check-v12-16-0-governance: FALHOU\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "check-v12-16-0-governance: OK - artefactos formais da v12.16.0 existem, baseline documentada, QA/rollback/riscos/matriz rastreados e gate ligado ao corredor.\n";
