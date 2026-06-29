<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/** Smoke regression v12.11.9.70 - Guarda/Portaria preservado após hotfix Alunos. */
$root = dirname(__DIR__);
$read = static function(string $rel) use ($root): string { $p = $root . '/' . $rel; return is_file($p) ? (string)file_get_contents($p) : ''; };
$slice = static function(string $text, string $start, string $end = ''): string {
    $p = strpos($text, $start); if ($p === false) return '';
    if ($end === '') return substr($text, $p);
    $q = strpos($text, $end, $p + strlen($start));
    return $q === false ? substr($text, $p) : substr($text, $p, $q - $p);
};
$roles = $read('includes/security-roles.php');
$perm = $read('includes/permissions-layer.php');
$shell = $read('includes/admin-shell.php');
$port = $read('admin/system/portaria-view.php');
$aluno = $read('admin/academic/alunos_lista.php');
$db = $read('includes/db-handler.php');
$fetch = $read('includes/aluno-fetch-ajax.php');
$rh = $read('admin/hr/equipe-view.php');
$ajax = $read('includes/ajax-handlers.php');
$pui = $read('admin/system/permissions-ui.php');
$saveAluno = $slice($db, 'function sige_ajax_salvar_aluno()', 'function sige_ajax_remover_aluno()');
$removeAluno = $slice($db, 'function sige_ajax_remover_aluno()', 'function sige_ajax_validar_acesso()');
$validarPort = $slice($db, 'function sige_ajax_validar_acesso()', '$codigo_lido');

$checks = [
    'wp_role_sige_guarda_registered' => strpos($roles, "add_role('sige_guarda'") !== false,
    'wp_role_sige_guarda_caps_minimas' => strpos($roles, "'portaria.ver' => true") !== false && strpos($roles, "'portaria.validar_acesso' => true") !== false && strpos($roles, "'alunos.ver' => true") !== false,
    'perfil_guarda_allowlist' => strpos($perm, "'guarda'") !== false && strpos($perm, 'portaria.validar_acesso') !== false,
    'perfil_guarda_sem_financeiro' => preg_match("/'guarda'\s*=>\s*\[[^\n]*'permissions'\s*=>\s*\[[^\]]*financeiro\./", $perm) !== 1,
    'shell_guarda_redirect_portaria' => strpos($shell, 'sige_guarda') !== false && strpos($shell, 'view=portaria') !== false,
    'portaria_guard_permission' => strpos($port, 'portaria.ver') !== false,
    'portaria_ajax_permission' => strpos($validarPort, 'portaria.validar_acesso') !== false,
    'portaria_nonce_preserved' => strpos($ajax, 'sige_portaria_acesso') !== false || strpos($db, 'sige_portaria_acesso') !== false,
    'alunos_guard_allows_guard_view' => strpos($aluno, "'sige_guarda'") !== false && strpos($aluno, "['alunos.ver']") !== false,
    'alunos_guard_read_only_flag' => strpos($aluno, '$sige_alunos_is_guarda') !== false,
    'alunos_guard_no_create' => strpos($aluno, '$sige_alunos_can_create') !== false && strpos($aluno, 'alunos.criar') !== false,
    'alunos_guard_no_edit' => strpos($aluno, '$sige_alunos_can_edit') !== false && strpos($aluno, 'alunos.editar') !== false,
    'alunos_guard_no_delete' => strpos($aluno, '$sige_alunos_can_delete') !== false && strpos($aluno, 'alunos.apagar') !== false,
    'salvar_aluno_requires_permission' => strpos($saveAluno, 'alunos.criar') !== false || strpos($saveAluno, 'alunos.editar') !== false,
    'remover_aluno_requires_permission' => strpos($removeAluno, 'alunos.apagar') !== false,
    'fetch_guard_payload_readonly' => strpos($fetch, "'guard_readonly' => true") !== false && strpos($fetch, 'Modo portaria') !== false,
    'fetch_guard_sensitive_minimized' => strpos($fetch, "'dados_sensiveis' => false") !== false,
    'more_nav_excludes_guard' => strpos($aluno, '$sige_alunos_can_more_nav = !$sige_alunos_is_guarda') !== false,
    'finance_nav_excludes_guard' => strpos($aluno, '$sige_alunos_can_finance_pay = !$sige_alunos_is_guarda') !== false,
    'dashboard_nav_excludes_guard' => strpos($aluno, '$sige_alunos_can_dashboard_nav = !$sige_alunos_is_guarda') !== false,
    'rh_can_assign_guard' => strpos($rh, 'sige_guarda') !== false && strpos($rh, 'Guarda') !== false,
    'permissions_ui_guarda_present' => strpos($pui, 'guarda') !== false && strpos($pui, 'portaria.validar_acesso') !== false,
    'bottom_more_hotfix_does_not_grant_guard' => strpos($aluno, 'sige-mobile-more-trigger') !== false && strpos($aluno, '$sige_alunos_can_more_nav') !== false,
    'scanner_preserved' => strpos($port, 'QR') !== false || strpos($port, 'cra') !== false || strpos($port, 'codigo') !== false,
];
$failed = [];
foreach ($checks as $name => $ok) { if (!$ok) $failed[] = $name; }
if ($failed) { fwrite(STDERR, 'Smoke Guarda/Portaria regressão v12.11.9.70 falhou: ' . implode(', ', $failed) . PHP_EOL); exit(1); }
echo 'Smoke Guarda/Portaria regressão v12.11.9.70 OK: ' . count($checks) . '/' . count($checks) . ' checks.' . PHP_EOL;
