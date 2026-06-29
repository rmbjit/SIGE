<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$files = [
    'core' => $root . '/includes/core-helpers.php',
    'db' => $root . '/includes/db-handler.php',
    'import' => $root . '/includes/aluno-fetch-ajax.php',
    'gerador' => $root . '/admin/finance/financeiro-gerador.php',
    'pagamentos' => $root . '/admin/finance/financeiro-pagamentos.php',
    'pagamentos_turma' => $root . '/admin/finance/pagamentos-turma-view.php',
    'main' => $root . '/sige-softgenial.php',
    'build' => $root . '/BUILD.json',
];
foreach ($files as $name => $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "FAIL {$name}: ficheiro em falta {$file}\n");
        exit(1);
    }
}
$get = fn($key) => file_get_contents($files[$key]);
$checks = [
    ['version main', strpos($get('main'), "Version: 12.11.9.88.1") !== false && strpos($get('main'), "SIGE_VERSION', '12.11.9.88.1'") !== false],
    ['version build', strpos($get('build'), '12.11.9.88.1') !== false && strpos($get('build'), 'financeiro-status-sync-hotfix') !== false],
    ['status normalizer', strpos($get('core'), 'function sige_status_operacional_normalizar') !== false && strpos($get('core'), 'function sige_status_operacional_activo') !== false],
    ['inactive auto-heal', strpos($get('core'), 'reactivou o aluno') !== false && strpos($get('core'), 'sige_sync_matricula_status_from_aluno($aluno_id') !== false],
    ['db matricula sync on save', strpos($get('db'), 'Sincronização aluno ↔ matrícula') !== false && strpos($get('db'), 'status_matricula') !== false && strpos($get('db'), 'sige_matricula_status_from_aluno_status') !== false],
    ['import status respects row', strpos($get('import'), 'A matrícula importada deve reflectir') !== false && strpos($get('import'), "dados_brutos['status']") !== false],
    ['gerador canonical active', strpos($get('gerador'), '$__sige_a_activo_gerador') !== false && strpos($get('gerador'), "a.status = 'activo'") === false],
    ['gerador joins scoped matricula', strpos($get('gerador'), 'm.escola_id = a.escola_id AND m.ano_lectivo = %d') !== false],
    ['pagamentos render auto-heal', strpos($get('pagamentos'), 'Auto-cura leve') !== false && strpos($get('pagamentos'), 'sige_aluno_financeiramente_inactivo((int)$aluno_id') !== false],
    ['pagamentos search scoped join', strpos($get('pagamentos'), 'm.aluno_id = a.id AND m.escola_id = a.escola_id AND m.ano_lectivo = %d') !== false],
    ['pagamentos turma canonical active', strpos($get('pagamentos_turma'), '$__sige_a_activo_pag_turma') !== false && strpos($get('pagamentos_turma'), "a.status = 'activo'") === false],
    ['pagamentos turma no exact matricula gate', strpos($get('pagamentos_turma'), "m.status_matricula = 'activa'") === false],
];
foreach ($checks as [$label, $ok]) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}
echo "SMOKE OK - financeiro status sync hotfix v12.11.9.88.1 validado.\n";
