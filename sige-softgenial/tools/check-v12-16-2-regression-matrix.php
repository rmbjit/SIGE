<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$matrix_path = $root . '/docs/qa/REGRESSION_MATRIX-v12.16.2-baseline-preservation-operational-readiness.md';
$matrix = is_file($matrix_path) ? (string) file_get_contents($matrix_path) : '';
if ($matrix === '') $errors[] = 'matriz de regressao ausente.';
foreach (['Financeiro','Academico','Permissoes','Mobile header','Portaria','Alunos','Portal','Shell','Manifesto','ZIP'] as $area) {
    if (strpos($matrix, $area) === false) $errors[] = 'area ausente na matriz: ' . $area;
}
foreach (['Financeiro','Professor','Director','Administrador','Guarda','Secretaria','Encarregado','Aluno'] as $perfil) {
    if (strpos($matrix, $perfil) === false) $errors[] = 'perfil ausente na matriz: ' . $perfil;
}
foreach (['360','390','430','768','1366'] as $width) {
    $all = $matrix . "\n" . (string) file_get_contents($root . '/docs/qa/QA_PLAN-v12.16.2-baseline-preservation-operational-readiness.md');
    if (strpos($all, $width) === false) $errors[] = 'viewport ausente na matriz/plano: ' . $width;
}
foreach (['Sem erro','Sem loop','Sem fatal','v12.16.1'] as $needle) {
    if (strpos($matrix, $needle) === false) $errors[] = 'criterio/rollback ausente na matriz: ' . $needle;
}
if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR, "ERRO: {$error}\n"); }
    exit(1);
}

echo "v12.16.2 REGRESSION MATRIX OK - areas, perfis, criterios, viewports e rollback cobertos.\n";
