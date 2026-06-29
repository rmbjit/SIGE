<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$read = static function(string $rel) use ($root): string {
    $path = $root . '/' . $rel;
    return is_file($path) ? file_get_contents($path) : '';
};
$main  = $read('sige-softgenial.php');
$core  = $read('includes/core-helpers.php');
$roles = $read('includes/security-roles.php');
$perm  = $read('includes/permissions-layer.php');
$shell = $read('includes/admin-shell.php');
$port  = $read('admin/system/portaria-view.php');
$aluno = $read('admin/academic/alunos_lista.php');
$db    = $read('includes/db-handler.php');
$fetch = $read('includes/aluno-fetch-ajax.php');
$rh    = $read('admin/hr/equipe-view.php');
$ajax  = $read('includes/ajax-handlers.php');
$pui   = $read('admin/system/permissions-ui.php');
$dash  = $read('admin/system/dashboard-view.php');
$slice = static function(string $text, string $start, string $end = ''): string {
    $p = strpos($text, $start);
    if ($p === false) return '';
    if ($end === '') return substr($text, $p);
    $q = strpos($text, $end, $p + strlen($start));
    return $q === false ? substr($text, $p) : substr($text, $p, $q - $p);
};
$saveAluno   = $slice($db, 'function sige_ajax_salvar_aluno()', 'function sige_ajax_remover_aluno()');
$removeAluno = $slice($db, 'function sige_ajax_remover_aluno()', 'function sige_ajax_validar_acesso()');
$validarPort = $slice($db, 'function sige_ajax_validar_acesso()', '$codigo_lido');

$checks = [
    'version_12_11_9_67' => strpos($main, 'Version: 12.11.9.67') !== false && strpos($main, "define('SIGE_VERSION', '12.11.9.67');") !== false,
    'wp_role_sige_guarda_registered' => strpos($roles, "add_role('sige_guarda'") !== false && strpos($roles, "'sige_guarda' => true") !== false,
    'wp_role_sige_guarda_caps_minimas' => strpos($roles, "'portaria.ver' => true") !== false && strpos($roles, "'portaria.validar_acesso' => true") !== false && strpos($roles, "'alunos.ver' => true") !== false,
    'core_staff_includes_sige_guarda' => strpos($core, "'sige_guarda'") !== false,
    'dashboard_staff_includes_sige_guarda' => strpos($dash, "'sige_guarda'") !== false,
    'permission_registry_portaria' => strpos($perm, "'portaria.ver'") !== false && strpos($perm, "'portaria.validar_acesso'") !== false,
    'permission_role_guarda_default' => strpos($perm, "'guarda' => ['nome'=>'Guarda / Portaria'") !== false && strpos($perm, "'permissions'=>['portaria.ver','portaria.validar_acesso','alunos.ver']") !== false,
    'permission_sige_can_guarda_allowlist' => strpos($perm, '$guarda_allowlist') !== false && strpos($perm, 'guarda_scope_denied') !== false,
    'permission_role_mapping_guarda' => strpos($perm, "'guarda'            => 'sige_guarda'") !== false && strpos($perm, "'sige_guarda'            => 'guarda'") !== false,
    'admin_shell_guard_redirects_to_portaria' => strpos($shell, '$is_guarda') !== false && strpos($shell, "\$view = 'portaria';") !== false,
    'admin_shell_portaria_menu_and_mobile' => strpos($shell, 'Portaria Digital') !== false && strpos($shell, "'label' => 'Portaria'") !== false,
    'portaria_page_guard_guarda' => strpos($port, "['portaria.ver','portaria.validar_acesso']") !== false && strpos($port, "'sige_guarda'") !== false,
    'portaria_nonce_and_ajax' => strpos($port, 'sige_portaria_acesso') !== false && strpos($port, "action: 'sige_validar_acesso'") !== false,
    'db_nonce_accepts_portaria' => strpos($db, "'sige_portaria_acesso'") !== false,
    'db_save_aluno_requires_create_or_edit' => strpos($saveAluno, '$sige_required_aluno_permission') !== false && strpos($saveAluno, "'alunos.criar'") !== false && strpos($saveAluno, "'alunos.editar'") !== false && strpos($saveAluno, "'sige_guarda'") === false,
    'db_remove_aluno_requires_delete' => strpos($removeAluno, "['alunos.apagar']") !== false && strpos($removeAluno, "'sige_guarda'") === false,
    'db_portaria_ajax_requires_validate' => strpos($validarPort, "['portaria.validar_acesso']") !== false && strpos($validarPort, "'sige_guarda'") !== false,
    'alunos_page_guard_allows_guarda_view' => strpos($aluno, "['alunos.ver']") !== false && strpos($aluno, "'sige_guarda'") !== false,
    'alunos_readonly_flags' => strpos($aluno, '$sige_alunos_can_create') !== false && strpos($aluno, '$sige_alunos_can_edit') !== false && strpos($aluno, '$sige_alunos_can_delete') !== false && strpos($aluno, '$sige_alunos_read_only') !== false,
    'alunos_guard_documents_export_blocked_ui' => strpos($aluno, '$sige_alunos_can_documents = !$sige_alunos_is_guarda') !== false && strpos($aluno, '$sige_alunos_can_export = !$sige_alunos_is_guarda') !== false,
    'aluno_fetch_export_denies_guarda' => strpos($fetch, 'sige_alunos_user_is_guarda') !== false && strpos($fetch, 'Sem permissão para exportar listas ou imprimir cartões em lote.') !== false,
    'rh_team_role_option_guarda' => strpos($rh, '<option value="sige_guarda">Guarda / Portaria</option>') !== false,
    'rh_ajax_allowed_roles_guarda' => strpos($ajax, "'sige_guarda'") !== false,
    'permissions_ui_locks_guarda' => strpos($pui, "'guarda',") !== false && strpos($pui, "['portaria.ver','portaria.validar_acesso','alunos.ver']") !== false,
];
$failed = [];
foreach ($checks as $name => $ok) {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) $failed[] = $name;
}
if ($failed) {
    fwrite(STDERR, 'Smoke regressão Guarda/Portaria v12.11.9.67 falhou: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo 'Smoke regressão Guarda/Portaria v12.11.9.67 OK: ' . count($checks) . '/' . count($checks) . ' checks.' . PHP_EOL;
