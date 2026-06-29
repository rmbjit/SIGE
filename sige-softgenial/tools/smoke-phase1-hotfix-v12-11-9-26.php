<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/** Smoke test estático - Fase 1 Hotfix Capability Recursion v12.11.9.26 */
$root = dirname(__DIR__);
$ok = 0; $fail = 0;
$check = function($cond, $msg) use (&$ok, &$fail) {
    if ($cond) { $ok++; echo "OK  - {$msg}\n"; }
    else { $fail++; echo "FAIL- {$msg}\n"; }
};
$main = file_get_contents($root . '/sige-softgenial.php');
$build = file_get_contents($root . '/BUILD.json');
$hard = file_get_contents($root . '/includes/security-hardening.php');
$page = file_get_contents($root . '/includes/page-guard.php');
$perm = file_get_contents($root . '/includes/permissions-layer.php');
$check(strpos($main, "define('SIGE_VERSION', '12.11.9.26')") !== false || strpos($main, "define('SIGE_VERSION', '12.11.9.27')") !== false, 'SIGE_VERSION actualizado para 12.11.9.26+');
$check(strpos($build, 'phase1-hotfix-capability-recursion') !== false || strpos($build, 'phase2-p2-1-tenant-schema') !== false, 'BUILD.json identifica hotfix de recursão ou build sucessor');
$check(strpos($hard, 'function sige_is_real_wp_admin_user_raw') !== false, 'helper raw não-recursivo existe');

$tokens = token_get_all($hard);
$has_is_super_admin_call = false;
for ($i = 0; $i < count($tokens); $i++) {
    if (is_array($tokens[$i]) && $tokens[$i][0] === T_STRING && strtolower($tokens[$i][1]) === 'is_super_admin') {
        $j = $i + 1;
        while ($j < count($tokens) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
        if ($j < count($tokens) && $tokens[$j] === '(') { $has_is_super_admin_call = true; break; }
    }
}
$check(!$has_is_super_admin_call, 'security-hardening.php não chama is_super_admin() em código executável');
$check(strpos($hard, 'current_user_can(') === false || strpos($hard, "current_user_can('manage_options')") !== false, 'security-hardening.php sem chamada nova perigosa de current_user_can() no helper raw');
$check(strpos($hard, 'static $inside_filter') !== false, 'user_has_cap tem guarda anti-recursão');
$check(strpos($hard, 'sige_is_real_wp_admin_user_raw($user)') !== false, 'filtro usa objecto WP_User recebido');
$check(strpos($page, 'sige_is_real_wp_admin_user($user_id)') !== false, 'page-guard usa helper seguro');
$check(strpos($perm, 'sige_is_real_wp_admin_user($user_id)') !== false, 'permissions-layer usa helper seguro');
$check(strpos($hard, 'add_filter(\'user_has_cap\'') !== false, 'filtro user_has_cap continua registado');

echo "\nResumo: {$ok} OK, {$fail} FAIL\n";
exit($fail > 0 ? 1 : 0);
