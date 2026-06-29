<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.11.9.5 - Deep Smoke Test Estático
 */
$root = dirname(__DIR__);
$files = [
    $root . '/sige-softgenial.php',
    $root . '/BUILD.json',
    $root . '/admin/hr/equipe-view.php',
    $root . '/includes/ajax-handlers.php',
    $root . '/includes/class-sige-migration.php',
];
foreach ($files as $f) {
    if (!is_file($f)) { fwrite(STDERR, "Ficheiro ausente: $f\n"); exit(1); }
}
$main = file_get_contents($files[0]);
$build = file_get_contents($files[1]);
$view = file_get_contents($files[2]);
$ajax = file_get_contents($files[3]);
$mig = file_get_contents($files[4]);
$must = [
    [$main, 'Version: 12.11.9.5'],
    [$main, "define('SIGE_VERSION', '12.11.9.5');"],
    [$build, '12.11.9.5'],
    [$view, 'function sigeEquipeOpenModal(modal)'],
    [$view, 'modal.style.display = \'flex\';'],
    [$view, 'body.sige-admin-app #box-equipa.sige-modal.active'],
    [$view, 'body.sige-admin-app #sige-rh-confirm.sige-modal.active'],
    [$view, 'Object.assign(window, {'],
    [$view, 'novoFuncionario,'],
    [$view, 'editarStaff,'],
    [$view, 'resetSenha,'],
    [$view, 'toggleStatus,'],
    [$view, 'removerUser,'],
    [$ajax, 'add_action(\'wp_ajax_sige_get_staff_secure\''],
    [$ajax, 'add_action(\'wp_ajax_sige_salvar_funcionario\''],
    [$ajax, 'sige_ajax_equipe_audit('],
    [$mig, 'function add_unique_index_if_no_duplicates'],
];
foreach ($must as [$hay, $needle]) {
    if (strpos($hay, $needle) === false) { fwrite(STDERR, "Smoke FAIL: falta $needle\n"); exit(1); }
}
// Anti-regressão: os fluxos críticos devem abrir via helper que aplica display:flex inline.
if (strpos($view, "function novoFuncionario()") === false || strpos($view, "sigeEquipeOpenModal(document.getElementById('box-equipa'))") === false) {
    fwrite(STDERR, "Smoke FAIL: Novo colaborador não usa o helper robusto de abertura do modal\n"); exit(1);
}
if (strpos($view, "sigeEquipeOpenModal(modal);") === false) {
    fwrite(STDERR, "Smoke FAIL: confirmação não usa o helper robusto de abertura do modal\n"); exit(1);
}
echo "DEEP SMOKE TEST OK - Equipa e Professores v12.11.9.5\n";
