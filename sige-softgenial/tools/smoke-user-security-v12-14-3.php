<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$files = [
    'plugin' => $root . '/sige-softgenial.php',
    'integrity' => $root . '/includes/security-user-integrity.php',
    'permissions_ui' => $root . '/admin/system/permissions-ui.php',
    'build' => $root . '/BUILD.json',
    'changelog' => $root . '/CHANGELOG.md',
];
$fail = [];
foreach ($files as $k => $f) {
    if (!is_file($f)) $fail[] = "Ficheiro em falta: {$k} {$f}";
}
$read = static fn($k) => file_get_contents($files[$k]);
$assert = static function ($cond, $msg) use (&$fail) { if (!$cond) $fail[] = $msg; };

$plugin = $read('plugin');
$integrity = $read('integrity');
$pui = $read('permissions_ui');
$build = $read('build');
$changelog = $read('changelog');

$assert(strpos($plugin, "Version: 12.14.3") !== false, 'Plugin header deve estar em 12.14.3.');
$assert(strpos($plugin, "define('SIGE_VERSION', '12.14.3')") !== false, 'Constante SIGE_VERSION deve estar em 12.14.3.');
$assert(strpos($build, '12.14.3') !== false, 'BUILD.json deve declarar 12.14.3.');
$assert(strpos($changelog, 'v12.14.3 - Privileged Account Recovery') !== false, 'CHANGELOG deve incluir v12.14.3.');

foreach ([
    'sige_user_integrity_privileged_snapshots',
    'sige_user_integrity_update_snapshot',
    'sige_user_integrity_retire_privileged_snapshot',
    'sige_user_integrity_mirror_wp_role',
    'sige_user_integrity_force_restore_sige_role',
    'sige_user_integrity_seed_current_privileged_snapshots',
    'sige_user_integrity_reconcile_privileged_snapshots',
] as $fn) {
    $assert(strpos($integrity, 'function ' . $fn) !== false, "Função {$fn} deve existir.");
}
$assert(strpos($integrity, 'sige_user_integrity_privileged_snapshots') !== false, 'Option de snapshot deve existir.');
$assert(strpos($integrity, 'wp_role_mirror_added') !== false, 'Espelho WP deve ser auditado.');
$assert(strpos($integrity, 'sige_role_restored_from_snapshot') !== false, 'Restauração por snapshot deve ser auditada.');
$assert(strpos($integrity, "add_action('admin_init'") !== false, 'Reconciliador deve correr em admin_init.');

$assert(strpos($pui, 'break_glass_restore_user_role') !== false, 'UI deve ter acção break-glass.');
$assert(strpos($pui, 'Recuperação segura de utilizador') !== false, 'UI deve mostrar cartão de recuperação segura.');
$assert(strpos($pui, '__active_sige_ids') !== false, 'UI deve incluir utilizadores com perfil SIGE activo.');
$assert(strpos($pui, 'role__in') !== false && strpos($pui, 'include') !== false, 'UI deve combinar WP staff roles e active SIGE users.');
$assert(strpos($pui, 'ui_break_glass_restore_user_role') !== false, 'Recuperação break-glass deve auditar em permission_audit.');
$assert(strpos($pui, 'sige_user_integrity_retire_privileged_snapshot') !== false, 'Remoção autorizada deve aposentar snapshot.');

if ($fail) {
    echo "SMOKE v12.14.3 FALHOU\n" . implode("\n", $fail) . "\n";
    exit(1);
}
echo "SMOKE v12.14.3 OK - Privileged Account Recovery & Staff Visibility coberto.\n";
