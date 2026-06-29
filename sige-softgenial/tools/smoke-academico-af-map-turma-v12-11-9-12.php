<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.11.9.12 - Smoke estático Académico / AF 3.ª classe + MAP por turma
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
$notas    = $read('admin/academic/notas-view.php');
$bol      = $read('admin/academic/boletim-view.php');
$pauta    = $read('admin/academic/pauta-final-view.php');
$mapH     = $read('includes/map-pdf-handler.php');
$mapTpl   = $read('admin/map-oficial-template.php');
$rh       = $read('admin/hr/equipe-view.php');
$transp   = $read('admin/logistics/transporte-view.php');

$contains($main, 'Version: 12.11.9.12', 'Header do plugin em 12.11.9.12');
$contains($main, "define('SIGE_VERSION', '12.11.9.12');", 'SIGE_VERSION em 12.11.9.12');
(($build['version'] ?? '') === '12.11.9.12') ? $ok('BUILD.json em 12.11.9.12') : $fail('BUILD.json não está em 12.11.9.12');
$contains($main, "add_action('admin_post_sige_map_turma_pdf', 'sige_map_turma_pdf_handler');", 'Handler MAP por turma registado');

$contains($logic, 'function sige_classe_usa_af($classe_num)', 'Helper canónico sige_classe_usa_af() existe');
$contains($logic, 'return (int) $classe_num === 3;', 'AF exclusiva da 3.ª classe no helper');
$contains($logic, 'function sige_label_avaliacao_final($classe_num)', 'Helper de label da avaliação final existe');
$contains($logic, 'function sige_label_nota_final($classe_num)', 'Helper de label MF/NF existe');

$contains($notas, '$show_af = ($eh_fim_ciclo && $exige_avaliacao_final && $is_nuclear_disc);', 'Notas mostra avaliação final apenas quando regra/turma/disciplina exigem');
$contains($notas, '$label_avaliacao_final', 'Notas usa label dinâmica AF/Exame');
$notContains($notas, 'Exame/AF', 'Notas não expõe Exame/AF genérico');

$contains($bol, "'action' => 'sige_map_turma_pdf'", 'Aproveitamento tem URL de MAP oficial por turma');
$contains($bol, 'Mapa oficial da turma', 'Aproveitamento mostra botão de impressão por turma');
$contains($bol, '$lbl_at = \'AT\';', 'Boletim mantém AT como avaliação trimestral');
$notContains($bol, 'Exame/AF', 'Boletim não expõe Exame/AF genérico');

$contains($pauta, 'sige_label_avaliacao_final', 'Pauta final usa label dinâmica da avaliação final');
$notContains($pauta, 'AF/MF', 'Pauta final não expõe AF/MF para todas as classes');

$contains($mapH, 'function sige_map_build_data', 'MAP usa motor único individual/massa');
$contains($mapH, 'function sige_map_turma_pdf_handler()', 'Handler MAP por turma existe');
$contains($mapH, '$label_mapa_final      = ($classe_num === 3) ? \'AF\'', 'MAP só usa AF como label da 3.ª classe');
$contains($mapTpl, 'foreach ($data_pages as $data)', 'Template MAP suporta múltiplas páginas/alunos');
$contains($mapTpl, 'Imprimir todos', 'Template MAP tem impressão em massa');
$contains($mapTpl, '$map_final_col_label', 'Template MAP tem label final dinâmica');

$contains($rh, 'rh-arquivo-removidos', 'RH validado preservado');
$contains($transp, 'Transporte Escolar', 'Transportes harmonizado preservado');

if ($failures) {
    fwrite(STDERR, "\nFalhas:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "OK - smoke Académico AF/MAP turma v12.11.9.12 concluído sem falhas.\n";
