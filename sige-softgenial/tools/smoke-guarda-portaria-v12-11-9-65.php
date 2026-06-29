<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$read = static function(string $rel) use ($root): string {
    $path = $root . '/' . $rel;
    return is_file($path) ? file_get_contents($path) : '';
};
$slice = static function(string $text, string $start, string $end = ''): string {
    $p = strpos($text, $start);
    if ($p === false) return '';
    if ($end === '') return substr($text, $p);
    $q = strpos($text, $end, $p + strlen($start));
    return $q === false ? substr($text, $p) : substr($text, $p, $q - $p);
};

$main  = $read('sige-softgenial.php');
$build = $read('BUILD.json');
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

$saveAluno   = $slice($db, 'function sige_ajax_salvar_aluno()', 'function sige_ajax_remover_aluno()');
$removeAluno = $slice($db, 'function sige_ajax_remover_aluno()', 'function sige_ajax_validar_acesso()');
$validarPort = $slice($db, 'function sige_ajax_validar_acesso()', '$codigo_lido');
$listLotes   = $slice($fetch, 'function sige_ajax_listar_lotes_importacao_alunos()', 'function sige_import_alunos_save_batch_history');

$checks = [
    'version_header_12_11_9_65' => strpos($main, 'Version: 12.11.9.65') !== false,
    'version_constant_12_11_9_65' => strpos($main, "define('SIGE_VERSION', '12.11.9.65');") !== false,
    'build_guard_portaria_12_11_9_65' => strpos($build, '12.11.9.65') !== false && strpos($build, 'guarda-portaria-readonly-pro') !== false,

    'wp_role_sige_guarda_registered' => strpos($roles, "add_role('sige_guarda'") !== false && strpos($roles, "'sige_guarda' => true") !== false,
    'wp_role_sige_guarda_caps_minimas' => strpos($roles, "'portaria.ver' => true") !== false && strpos($roles, "'portaria.validar_acesso' => true") !== false && strpos($roles, "'alunos.ver' => true") !== false,
    'core_staff_includes_sige_guarda' => strpos($core, "'sige_guarda'") !== false,

    'permission_registry_portaria' => strpos($perm, "'portaria.ver'") !== false && strpos($perm, "'portaria.validar_acesso'") !== false,
    'permission_role_guarda_default' => strpos($perm, "'guarda' => ['nome'=>'Guarda / Portaria'") !== false && strpos($perm, "'permissions'=>['portaria.ver','portaria.validar_acesso','alunos.ver']") !== false,
    'permission_migration_guarda' => strpos($perm, 'sige_permissions_migrate_1211965_guarda_portaria') !== false && strpos($perm, "'guarda', 'Guarda / Portaria'") !== false && strpos($perm, '$wpdb->delete($t[\'role_permissions\'], [\'role_id\' => $role_id], [\'%d\'])') !== false,
    'permission_sige_can_guarda_allowlist' => strpos($perm, '$guarda_allowlist') !== false && strpos($perm, 'guarda_scope_denied') !== false,
    'permission_role_mapping_guarda' => strpos($perm, "'guarda'            => 'sige_guarda'") !== false && strpos($perm, "'sige_guarda'            => 'guarda'") !== false,
    'legacy_map_guard_read_only' => strpos($perm, "'alunos.ver' => ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_recepcao','sige_guarda']") !== false && strpos($perm, "'alunos.editar' => ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente']") !== false,

    'admin_shell_portaria_permission_map' => strpos($shell, '"portaria" => ["portaria.ver","portaria.validar_acesso"]') !== false,
    'admin_shell_guard_redirects_to_portaria' => strpos($shell, '$is_guarda') !== false && strpos($shell, '$view = \'portaria\';') !== false,
    'admin_shell_portaria_menu_and_mobile' => strpos($shell, 'Portaria Digital') !== false && strpos($shell, "'label' => 'Portaria'") !== false,

    'portaria_page_guard_guarda' => strpos($port, "['portaria.ver','portaria.validar_acesso']") !== false && strpos($port, "'sige_guarda'") !== false,
    'portaria_nonce_and_ajax' => strpos($port, 'sige_portaria_acesso') !== false && strpos($port, "action: 'sige_validar_acesso'") !== false,

    'db_nonce_accepts_portaria' => strpos($db, "'sige_portaria_acesso'") !== false,
    'db_ajax_helper_respects_active_sige_role' => strpos($db, 'sige_page_guard_has_active_sige_role') !== false && strpos($db, 'não reabrimos a acção por capabilities antigas') !== false,
    'db_save_aluno_requires_create_or_edit' => strpos($saveAluno, '$sige_required_aluno_permission') !== false && strpos($saveAluno, "'alunos.criar'") !== false && strpos($saveAluno, "'alunos.editar'") !== false && strpos($saveAluno, "'sige_guarda'") === false,
    'db_remove_aluno_requires_delete' => strpos($removeAluno, "['alunos.apagar']") !== false && strpos($removeAluno, "'sige_guarda'") === false,
    'db_portaria_ajax_requires_validate' => strpos($validarPort, "['portaria.validar_acesso']") !== false && strpos($validarPort, "'sige_guarda'") !== false,

    'alunos_page_guard_allows_guarda_view' => strpos($aluno, "['alunos.ver']") !== false && strpos($aluno, "'sige_guarda'") !== false,
    'alunos_readonly_flags' => strpos($aluno, '$sige_alunos_can_create') !== false && strpos($aluno, '$sige_alunos_can_edit') !== false && strpos($aluno, '$sige_alunos_can_delete') !== false && strpos($aluno, '$sige_alunos_read_only') !== false,
    'alunos_readonly_ui_banner' => strpos($aluno, '<strong>Modo consulta.</strong>') !== false,
    'alunos_frontend_guards' => strpos($aluno, 'window.sigeAlunosCanCreate') !== false && strpos($aluno, 'window.sigeAlunosCanEdit') !== false && strpos($aluno, 'window.sigeAlunosCanDelete') !== false,
    'alunos_guard_documents_export_blocked_ui' => strpos($aluno, '$sige_alunos_can_documents = !$sige_alunos_is_guarda') !== false && strpos($aluno, '$sige_alunos_can_export = !$sige_alunos_is_guarda') !== false && strpos($aluno, 'não pode exportar listas de alunos') !== false && strpos($aluno, 'não pode imprimir cartões') !== false,
    'alunos_import_modal_guarded' => strpos($aluno, 'Não pode importar listas.') !== false,

    'aluno_fetch_guarda_can_view' => strpos($fetch, "'sige_guarda'") !== false && strpos($fetch, "current_user_can('sige_guarda')") !== false,
    'aluno_fetch_import_requires_manage' => strpos($fetch, 'function sige_alunos_user_can_manage') !== false && strpos($fetch, 'Sem permissão para importar alunos.') !== false,
    'aluno_fetch_export_denies_guarda' => strpos($fetch, 'sige_alunos_user_is_guarda') !== false && strpos($fetch, 'Sem permissão para exportar listas ou imprimir cartões em lote.') !== false,
    'aluno_fetch_history_requires_manage' => strpos($listLotes, 'sige_alunos_user_can_manage()') !== false,

    'rh_team_role_option_guarda' => strpos($rh, '<option value="sige_guarda">Guarda / Portaria</option>') !== false,
    'rh_ajax_allowed_roles_guarda' => strpos($ajax, "'sige_guarda'") !== false && strpos($ajax, "'sige_recepcao' => 'Recepção'") !== false,
    'permissions_ui_exposes_and_locks_guarda' => strpos($pui, "'guarda',") !== false && strpos($pui, '(string)($role->slug ?? \'\') === \'guarda\'') !== false && strpos($pui, "['portaria.ver','portaria.validar_acesso','alunos.ver']") !== false,
];

$failed = [];
foreach ($checks as $name => $ok) {
    if (!$ok) $failed[] = $name;
}
if ($failed) {
    fwrite(STDERR, 'Smoke Guarda/Portaria v12.11.9.65 falhou: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo 'Smoke Guarda/Portaria v12.11.9.65 concluído com sucesso (' . count($checks) . ' checks).' . PHP_EOL;
