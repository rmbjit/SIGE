<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$ajax = file_get_contents($root . '/includes/aluno-fetch-ajax.php');
$view = file_get_contents($root . '/admin/academic/alunos_lista.php');
$main = file_get_contents($root . '/sige-softgenial.php');
$build = file_get_contents($root . '/BUILD.json');

function assert_contains($haystack, $needle, $label) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "[FAIL] {$label}\nMissing: {$needle}\n");
        exit(1);
    }
    echo "[OK] {$label}\n";
}

assert_contains($main, "Version: 12.11.9.46", 'Plugin header actualizado');
assert_contains($main, "define('SIGE_VERSION', '12.11.9.46');", 'Constante SIGE_VERSION actualizada');
assert_contains($build, 'ficha-360-aluno-qualidade-dados-pro', 'BUILD.json actualizado');

assert_contains($ajax, "wp_ajax_sige_get_aluno_360", 'Endpoint AJAX da Ficha 360 registado');
assert_contains($ajax, "function sige_ajax_get_aluno_360", 'Handler da Ficha 360 existe');
assert_contains($ajax, "function sige_aluno_360_quality_index", 'Função do Índice de Qualidade existe');
assert_contains($ajax, "sige_check_nonce_global();", 'Nonce global preservado no endpoint');
assert_contains($ajax, "sige_alunos_user_can_view()", 'Permissões de Alunos aplicadas no endpoint');
assert_contains($ajax, "a.escola_id = %d", 'Escopo por escola_id preservado');
assert_contains($ajax, "wp_send_json_success($" . "payload)", 'Payload 360 devolvido com sucesso JSON');

assert_contains($view, "modal-aluno-360", 'Modal Ficha 360 presente na UI');
assert_contains($view, "abrirFichaAluno360", 'Função JS para abrir Ficha 360 presente');
assert_contains($view, "renderFichaAluno360", 'Função JS para renderizar Ficha 360 presente');
assert_contains($view, "sige-data-quality-badge", 'Badge de qualidade presente no card');
assert_contains($view, "sige_get_aluno_360", 'Frontend chama endpoint da Ficha 360');
assert_contains($view, "sige_importar_alunos_csv", 'Importação de alunos preservada');
assert_contains($view, "anularLoteImportacaoAlunos", 'Anulação segura de lote preservada');

if (preg_match('/sige_get_aluno_360[\s\S]{0,1200}wpdb->insert/i', $ajax)) {
    fwrite(STDERR, "[FAIL] Endpoint 360 não deve fazer insert próximo do handler.\n");
    exit(1);
}
echo "[OK] Endpoint 360 sem insert directo próximo do handler\n";

echo "Smoke test v12.11.9.46 concluído com sucesso.\n";
