<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/** Smoke regressão Guarda/Portaria para v12.11.9.70. */
$root = dirname(__DIR__);
$read = static function(string $rel) use ($root): string { $p = $root . '/' . ltrim($rel, '/'); return is_file($p) ? (string)file_get_contents($p) : ''; };
$main = $read('sige-softgenial.php');
$roles = $read('includes/security-roles.php');
$perms = $read('includes/permissions-layer.php');
$shell = $read('includes/admin-shell.php');
$db = $read('includes/db-handler.php') . "\n" . $read('includes/ajax-handlers.php');
$portaria = $read('admin/system/portaria-view.php');
$alunos = $read('admin/academic/alunos_lista.php');
$ajax = $read('includes/aluno-fetch-ajax.php');
$rh = $read('admin/hr/equipe-view.php');

$checks = [
    'version_12_11_9_70' => strpos($main, 'Version: 12.11.9.70') !== false && strpos($main, "SIGE_VERSION', '12.11.9.70'") !== false,
    'role_sige_guarda_registered' => strpos($roles, 'sige_guarda') !== false,
    'permission_portaria_ver' => strpos($perms, 'portaria.ver') !== false,
    'permission_portaria_validar_acesso' => strpos($perms, 'portaria.validar_acesso') !== false,
    'role_guarda_default' => strpos($perms, "'guarda'") !== false && strpos($perms, 'Guarda / Portaria') !== false,
    'guard_allowlist_present' => strpos($perms, "'portaria.ver'") !== false && strpos($perms, "'alunos.ver'") !== false,
    'admin_shell_redirect_portaria' => strpos($shell, 'portaria') !== false && strpos($shell, 'sige_guarda') !== false,
    'portaria_page_guard' => strpos($portaria, 'sige_page_guard') !== false && strpos($portaria, 'sige_guarda') !== false,
    'portaria_ajax_nonce' => strpos($db, 'sige_portaria_acesso') !== false,
    'db_save_aluno_permissioned' => strpos($db, 'alunos.criar') !== false && strpos($db, 'alunos.editar') !== false,
    'db_remove_aluno_permissioned' => strpos($db, 'alunos.apagar') !== false,
    'alunos_guard_detected' => strpos($alunos, '$sige_alunos_is_guarda') !== false,
    'alunos_guard_read_only' => strpos($alunos, '$sige_alunos_read_only') !== false,
    'alunos_no_non_guard_export_shortcut' => strpos($alunos, '$sige_alunos_can_export = !$sige_alunos_is_guarda;') === false,
    'alunos_no_non_guard_documents_shortcut' => strpos($alunos, '$sige_alunos_can_documents = !$sige_alunos_is_guarda;') === false,
    'ajax_guard_blocks_sensitive_scope' => strpos($ajax, 'sige_alunos_user_is_guarda') !== false && strpos($ajax, 'return false;') !== false,
    'ajax_cards_export_permissioned' => strpos($ajax, 'sige_alunos_user_can_emit_documents') !== false && strpos($ajax, 'sige_alunos_user_can_export_students') !== false,
    'rh_can_assign_guarda' => strpos($rh, 'guarda') !== false || strpos($rh, 'sige_guarda') !== false,
    'bottom_more_not_shown_for_guarda' => strpos($alunos, '$sige_alunos_can_more_nav = !$sige_alunos_is_guarda') !== false,
    'bottom_more_button_does_not_change_guard_scope' => strpos($alunos, 'sige-mobile-more-trigger') !== false && strpos($alunos, 'sige_guarda') !== false,
];
$failed = [];
foreach ($checks as $name => $ok) { echo ($ok ? 'OK   ' : 'FAIL ') . $name . PHP_EOL; if (!$ok) $failed[] = $name; }
if ($failed) { fwrite(STDERR, 'Smoke Guarda v12.11.9.70 falhou: ' . implode(', ', $failed) . PHP_EOL); exit(1); }
echo 'Smoke Guarda/Portaria v12.11.9.70 OK: ' . count($checks) . '/' . count($checks) . ' checks.' . PHP_EOL;
