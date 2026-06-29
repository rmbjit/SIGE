<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.11.9.13 - Smoke estático Académico / Filtro de turma no Aproveitamento
 */
$root = dirname(__DIR__);
$failures = [];
$read = function($rel) use ($root) {
    $path = $root . '/' . $rel;
    return file_exists($path) ? file_get_contents($path) : '';
};
$ok = function($msg) { echo "OK - {$msg}\n"; };
$fail = function($msg) use (&$failures) { $failures[] = $msg; echo "FAIL - {$msg}\n"; };
$contains = function($haystack, $needle, $msg) use ($ok, $fail) {
    strpos($haystack, $needle) !== false ? $ok($msg) : $fail($msg);
};
$notContains = function($haystack, $needle, $msg) use ($ok, $fail) {
    strpos($haystack, $needle) === false ? $ok($msg) : $fail($msg);
};

$main     = $read('sige-softgenial.php');
$buildRaw = $read('BUILD.json');
$build    = json_decode($buildRaw, true);
$logic    = $read('includes/academic-logic.php');
$bol      = $read('admin/academic/boletim-view.php');
$mapH     = $read('includes/map-pdf-handler.php');
$rh       = $read('admin/hr/equipe-view.php');
$transp   = $read('admin/logistics/transporte-view.php');

$contains($main, 'Version: 12.11.9.13', 'Header do plugin em 12.11.9.13');
$contains($main, "define('SIGE_VERSION', '12.11.9.13');", 'SIGE_VERSION em 12.11.9.13');
(($build['version'] ?? '') === '12.11.9.13') ? $ok('BUILD.json em 12.11.9.13') : $fail('BUILD.json não está em 12.11.9.13');
$contains($main, "add_action('admin_post_sige_map_turma_pdf', 'sige_map_turma_pdf_handler');", 'Handler MAP por turma preservado');

$contains($logic, 'function sige_classe_usa_af($classe_num)', 'Regra canónica AF preservada');
$contains($logic, 'return (int) $classe_num === 3;', 'AF continua exclusiva da 3.ª classe');

$contains($bol, '$turma_busca_id', 'Aproveitamento recebe parâmetro turma_busca_id');
$contains($bol, 'Filtrar por turma', 'UI mostra filtro por turma');
$contains($bol, '$turmas_pesquisa', 'Aproveitamento carrega lista de turmas');
$contains($bol, '$turma_filtro', 'Aproveitamento valida turma filtrada');
$contains($bol, '$turma_alunos', 'Aproveitamento lista alunos da turma');
$contains($bol, 'Filtro pedagógico por turma activo', 'Painel da turma informa filtro activo');
$contains($bol, "'action'   => 'sige_map_turma_pdf'", 'Painel de turma gera URL MAP por turma');
$contains($bol, 'Mapa oficial da turma', 'Botão MAP oficial da turma visível');
$contains($bol, '$where_turma_pesquisa', 'Pesquisa por aluno pode ser restringida à turma');
$contains($bol, 'sige_aluno_matricula_activa_sql', 'Lista por turma usa regra central de matrícula activa quando disponível');
$notContains($bol, 'Exame/AF', 'Boletim não reintroduz Exame/AF genérico');

$contains($mapH, 'function sige_map_turma_pdf_handler()', 'Handler de MAP por turma preservado');
$contains($mapH, 'sige_map_build_data((int) $aid, $ano, $turma_id)', 'MAP por turma continua a usar motor único');

$contains($rh, 'rh-arquivo-removidos', 'RH validado preservado');
$contains($transp, 'Transporte Escolar', 'Transportes harmonizado preservado');

if ($failures) {
    fwrite(STDERR, "\nFalhas:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "OK - smoke Académico filtro de turma v12.11.9.13 concluído sem falhas.\n";
