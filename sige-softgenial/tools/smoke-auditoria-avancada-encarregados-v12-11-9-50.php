<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test - SIGE SoftGenial v12.11.9.50
 * Auditoria Avançada de Encarregados PRO
 */
$base = dirname(__DIR__);
$checks = [];
$check = function ($condition, $label) use (&$checks) {
    $checks[] = [$condition ? 'OK' : 'FAIL', $label];
};
$read = function ($file) use ($base) {
    $path = $base . '/' . $file;
    return file_exists($path) ? file_get_contents($path) : '';
};

$main = $read('sige-softgenial.php');
$mig = $read('includes/class-sige-migration.php');
$guard = $read('includes/encarregados-advanced.php');
$db = $read('includes/db-handler.php');
$fetch = $read('includes/aluno-fetch-ajax.php');
$view = $read('admin/academic/alunos_lista.php');

$check(strpos($main, 'Version: 12.11.9.50') !== false, 'Plugin header actualizado para 12.11.9.50');
$check(strpos($main, "define('SIGE_VERSION', '12.11.9.50')") !== false, 'Constante SIGE_VERSION actualizada');
$check(strpos($mig, "const SCHEMA_VERSION = '20260606.2'") !== false, 'Schema version incrementado');
$check(strpos($mig, 'CREATE TABLE {$p}sige_alunos_encarregados_historico') !== false, 'Tabela própria de histórico definida na migração centralizada');
$check(strpos($mig, 'idx_escola_aluno') !== false && strpos($mig, 'idx_escola_criado') !== false, 'Índices principais do histórico definidos');
$check(strpos($guard, 'function sige_guardian_adv_audit_log') !== false, 'Helper de logging da auditoria existe');
$check(strpos($guard, 'sige_guardian_adv_mask_phone') !== false, 'Mascaramento de telefone implementado');
$check(strpos($guard, 'sige_guardian_adv_mask_email') !== false, 'Mascaramento de e-mail implementado');
$check(strpos($guard, 'hash_hmac') !== false, 'Hashes de integridade/privacidade usados');
$check(strpos($db, 'sige_guardian_audit_before') !== false, 'Snapshot anterior capturado no save do aluno');
$check(strpos($db, 'sige_guardian_adv_audit_log($aluno_id') !== false, 'Logging chamado após gravação do aluno');
$check(strpos($fetch, 'encarregados_historico') !== false, 'Ficha 360º expõe histórico minimizado');
$check(strpos($view, 'Auditoria dos encarregados') !== false, 'Ficha 360º renderiza card de auditoria');
$check(strpos($view, 'sige-360-mini-changes') !== false, 'CSS/HTML de detalhes resumidos da auditoria implementado');

$fail = false;
foreach ($checks as [$status, $label]) {
    echo "[$status] $label\n";
    if ($status !== 'OK') $fail = true;
}
if ($fail) exit(1);
echo "\nSmoke test v12.11.9.50 concluído com sucesso.\n";
