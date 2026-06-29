<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$files = [
    'plugin' => $root . '/sige-softgenial.php',
    'shield' => $root . '/includes/security-login-shield.php',
    'integrity' => $root . '/includes/security-user-integrity.php',
    'permissions' => $root . '/includes/permissions-layer.php',
    'permissions_ui' => $root . '/admin/system/permissions-ui.php',
    'ajax' => $root . '/includes/ajax-handlers.php',
    'db' => $root . '/includes/db-handler.php',
    'build' => $root . '/BUILD.json',
];
$fail = [];
foreach ($files as $k => $f) {
    if (!is_file($f)) $fail[] = "Ficheiro em falta: {$k} {$f}";
}
$read = static fn($k) => file_get_contents($files[$k]);
$assert = static function ($cond, $msg) use (&$fail) { if (!$cond) $fail[] = $msg; };

$plugin = $read('plugin');
$shield = $read('shield');
$integrity = $read('integrity');
$perm = $read('permissions');
$pui = $read('permissions_ui');
$ajax = $read('ajax');
$db = $read('db');
$build = $read('build');

$assert(strpos($plugin, "Version: 12.14.2") !== false, 'Plugin header deve estar em 12.14.2.');
$assert(strpos($plugin, "define('SIGE_VERSION', '12.14.2')") !== false, 'Constante SIGE_VERSION deve estar em 12.14.2.');
$assert(strpos($plugin, 'security-user-integrity.php') !== false, 'Novo guard de utilizadores deve ser carregado.');
$assert(strpos($build, '12.14.2') !== false, 'BUILD.json deve declarar 12.14.2.');

$assert(strpos($shield, 'SIGE_SHIELD_USER_GLOBAL_MAX') !== false, 'Login shield deve ter limite por username global.');
$assert(strpos($shield, "'user_global'") !== false, 'Login shield deve criar chave user_global.');
$assert(substr_count($shield, "sige_shield_register_failure((string)$" ) >= 1 || strpos($shield, 'sige_shield_register_failure((string)$user->user_login') !== false, 'Tentativas erradas de MFA devem alimentar rate-limit.');
$assert(strpos($shield, 'SIGE_SHIELD_IP_MAX\', 12') !== false || strpos($shield, 'SIGE_SHIELD_IP_MAX\'),     define(\'SIGE_SHIELD_IP_MAX\', 12') !== false || strpos($shield, "define('SIGE_SHIELD_IP_MAX', 12)") !== false, 'Limite por IP deve estar endurecido para 12.');

foreach (['login_errors','template_redirect','rest_endpoints','xmlrpc_enabled','set_user_role','added_user_role','removed_user_role'] as $hook) {
    $assert(strpos($integrity, $hook) !== false, "security-user-integrity deve cobrir hook {$hook}.");
}
foreach (['sige_user_integrity_can_change_sige_role','sige_user_integrity_wp_role_guard','sige_user_integrity_restore_wp_roles','sige_user_integrity_mark_sige_role_change'] as $fn) {
    $assert(strpos($integrity, 'function ' . $fn) !== false, "Função {$fn} deve existir.");
}
$assert(strpos($integrity, 'sige_admin_ti') !== false && strpos($integrity, 'sige_director') !== false, 'Roles WP críticos devem estar declarados.');
$assert(strpos($integrity, '/wp/v2/users') !== false, 'REST users deve ser protegido.');
$assert(strpos($integrity, '?author') === false || strpos($integrity, "_GET['author']") !== false, 'Enumeração por author deve ser tratada.');

$assert(strpos($perm, 'sige_user_integrity_can_change_sige_role') !== false, 'permissions-layer deve chamar guarda de integridade antes de persistir role SIGE.');
$assert(strpos($perm, 'sige_user_integrity_mark_sige_role_change') !== false, 'permissions-layer deve marcar mudança autorizada.');
$assert(strpos($pui, '$sync_ok') !== false && strpos($pui, 'ui_assign_integrity_blocked') !== false, 'UI de permissões deve falhar fechado se sync for bloqueado.');
$assert(strpos($ajax, 'sige_ajax_equipe_apply_access_role') !== false, 'RH/Equipe deve aplicar role por helper central.');
$assert(strpos($db, 'sige_user_integrity_can_change_sige_role') !== false && strpos($db, 'sige_permissions_sync_user_role') !== false, 'Handlers legados devem respeitar guarda e sync.');

if ($fail) {
    echo "SMOKE v12.14.2 FALHOU\n" . implode("\n", $fail) . "\n";
    exit(1);
}
echo "SMOKE v12.14.2 OK - User Security & Role Integrity coberto.\n";
