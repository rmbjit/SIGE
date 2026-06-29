<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test estático - Fase 1 Blindagem Final/Hotfix v12.11.9.25+
 * Executar: php tools/smoke-phase1-final-v12-11-9-25.php
 */
$root = dirname(__DIR__);
$ok = 0; $fail = 0;
$check = function(bool $cond, string $msg) use (&$ok, &$fail) {
    if ($cond) { $ok++; echo "OK   - {$msg}\n"; }
    else { $fail++; echo "FAIL - {$msg}\n"; }
};
$read = function(string $rel) use ($root): string {
    $p = $root . DIRECTORY_SEPARATOR . $rel;
    return is_file($p) ? file_get_contents($p) : '';
};

$main = $read('sige-softgenial.php');
$roles = $read('includes/security-roles.php');
$hard = $read('includes/security-hardening.php');
$scope = $read('includes/security-scope-guard.php');
$secureDoc = $read('includes/secure-document-download.php');
$email = $read('includes/email-engine.php');
$docs = $read('includes/documents-engine.php');
$portal = $read('includes/portal-logic.php');
$backup = $read('includes/backup-safety.php');
$ajax = $read('includes/ajax-handlers.php');
$build = $read('BUILD.json');

$check(strpos($main, "define('SIGE_VERSION', '12.11.9.25')") !== false || strpos($main, "define('SIGE_VERSION', '12.11.9.26')") !== false || strpos($main, "define('SIGE_VERSION', '12.11.9.27')") !== false, 'SIGE_VERSION actualizado para Fase 1 final/hotfix');
$check(strpos($build, 'phase1-blindagem-final-candidate') !== false || strpos($build, 'phase1-hotfix-capability-recursion') !== false || strpos($build, 'phase2-p2-1-tenant-schema') !== false, 'BUILD.json identifica final candidate/hotfix da Fase 1 ou build sucessor');
$check(strpos($roles, "'manage_options' => true") === false, 'Roles SIGE não recebem manage_options explicitamente');
$check(strpos($hard, "add_filter('user_has_cap', 'sige_security_strip_technical_caps_from_sige_users'") !== false, 'Filtro defensivo remove capabilities técnicas de roles SIGE');
$check(strpos($scope, 'sige_phase1_scope_guard_wp_user_belongs_to_school') !== false, 'Tenant guard valida WP users/staff/portal por escola');
$check(strpos($scope, "'sige_resetar_senha'") !== false && strpos($scope, "'user_id' => 'wp_user'") !== false, 'Tenant guard cobre IDs genéricos de equipa/RH');
$check(strpos($scope, "'sige_wppc_cancel'") !== false && strpos($scope, 'sige_whatsapp_queue') !== false, 'Tenant guard cobre fila WhatsApp');
$check(strpos($scope, '$sige_print') !== false && strpos($scope, 'recibo_massa') !== false, 'Tenant guard conhece impressões internas ?sige_print');
$check(strpos($docs, 'template_redirect') !== false && strpos($docs, 'sige_phase1_scope_guard_validate_id') !== false, 'Documents engine valida tenant em ?sige_print fora de admin_init');
$check(strpos($secureDoc, 'sige_secure_document_download_handler') !== false, 'Endpoint seguro de documentos do aluno existe');
$check(strpos($secureDoc, 'sige_secure_staff_document_download_handler') !== false, 'Endpoint seguro de documentos RH/equipa existe');
$check(strpos($secureDoc, 'sige_secure_document_resolve_local_path') !== false, 'Downloads protegidos aceitam apenas ficheiros locais controlados');
$check(strpos($portal, 'sige_secure_document_url') !== false, 'Portal antigo usa endpoint seguro para documentos do aluno');
$check(strpos($ajax, 'docs_secure') !== false && strpos($ajax, 'sige_secure_staff_document_url') !== false, 'AJAX de equipa devolve URLs seguras para documentos RH');
$check(strpos($email, 'nunca gerar novo link público ambíguo') !== false, 'Recibos públicos não geram links novos ambíguos');
$check(strpos($email, '\'e\'           => max(0, $escola_id)') !== false || strpos($email, "'e'           => max(0, \$escola_id)") !== false, 'Links de recibo mantêm parâmetro de escola');
$check(strpos($email, '\'a\'           => max(0, $aluno_id)') !== false || strpos($email, "'a'           => max(0, \$aluno_id)") !== false, 'Links de recibo mantêm parâmetro de aluno');
$check(strpos($email, 'Link Antigo') !== false && strpos($email, 'multiple_alunos') !== false, 'Links antigos ambíguos são bloqueados com segurança');
$check(strpos($backup, 'phase1-backup-safety-v2') !== false, 'Backup safety usa manifest v2');
$check(strpos($backup, 'sige_backup_school_light_snapshot') !== false, 'Backup safety possui snapshot leve da escola');
$check(strpos($backup, 'sige_backup_cleanup_old_snapshots') !== false, 'Backup safety possui retenção/limpeza de snapshots');

// Garantia contra regressão específica: renderer do recibo público não deve ter fallback que mostra tudo quando aluno_id não bate.
$check(strpos($docs, "Recibo não encontrado para este aluno") !== false, 'Recibo público falha fechado quando aluno_id não tem itens');

if ($fail > 0) {
    echo "\nResultado: {$ok} OK, {$fail} FAIL\n";
    exit(1);
}
echo "\nResultado: {$ok} OK, {$fail} FAIL\n";
exit(0);
