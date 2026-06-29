<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.11.9.6 - Smoke Test Estático
 * Foco: desactivação efectiva do módulo Equipa e Professores.
 */
$root = dirname(__DIR__);
$main = file_get_contents($root . '/sige-softgenial.php');
$build = file_get_contents($root . '/BUILD.json');
$ajax = file_get_contents($root . '/includes/ajax-handlers.php');
$perm = file_get_contents($root . '/includes/permissions-layer.php');
$guard = file_get_contents($root . '/includes/page-guard.php');
$view = file_get_contents($root . '/admin/hr/equipe-view.php');

$checks = [
    [$main, 'Version: 12.11.9.6'],
    [$main, "define('SIGE_VERSION', '12.11.9.6');"],
    [$build, '12.11.9.6'],
    [$ajax, 'update_user_meta($user_id, \'sige_staff_status_ativo\', $status_ativo);'],
    [$ajax, 'WP_Session_Tokens::get_instance($user_id)->destroy_all();'],
    [$ajax, 'sige_rh_user_is_active_for_school($user_id, $escola_id)'],
    [$ajax, 'Preserva o estado activo/inactivo durante edição normal'],
    [$perm, 'function sige_rh_user_is_active_for_school'],
    [$perm, "add_filter('authenticate', 'sige_rh_block_inactive_staff_authenticate', 35, 3);"],
    [$perm, "add_action('admin_init', 'sige_rh_enforce_inactive_staff_admin_access', 0);"],
    [$perm, "'rh_staff_inactive'"],
    [$guard, 'Esta verificação vem antes do bypass legado'],
    [$guard, 'colaborador RH desactivado não deve entrar por matriz nem fallback legado'],
    [$perm, 'mesmo que a role legada ainda tenha capabilities fortes como manage_options'],
    [$view, 'ORDER BY id ASC'],
    [$view, '$_profs_by_id'],
    [$view, 'sige_professor_id'],
];
foreach ($checks as $idx => [$haystack, $needle]) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL check {$idx}: missing {$needle}\n");
        exit(1);
    }
}
echo "SMOKE TEST OK - Equipa e Professores v12.11.9.6 desactivação efectiva\n";
