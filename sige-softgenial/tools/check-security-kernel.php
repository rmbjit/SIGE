<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$fails = [];
$read = static function (string $rel) use ($root): string {
    $path = $root . '/' . $rel;
    return is_file($path) ? (string)file_get_contents($path) : '';
};
$main = $read('sige-softgenial.php');
$kernel = $read('includes/security-kernel.php');
$rules = $read('includes/security-kernel-rules.php');
$settingsRepo = $read('includes/settings/class-sige-settings-repository.php');
if ($kernel === '') $fails[] = 'includes/security-kernel.php ausente';
if ($rules === '') $fails[] = 'includes/security-kernel-rules.php ausente';
foreach ([
    'sige_security_kernel_get_rule',
    'sige_security_kernel_current_surface',
    'sige_security_kernel_rest_route_from_rule',
    'sige_security_kernel_rest_surface_id_for_route',
    'sige_security_kernel_dispatch_query_handlers_on_hook',
    'sige_security_kernel_view_action_matches',
    'sige_security_kernel_matching_view_actions',
    'sige_security_kernel_dispatch_view_actions',
    'sige_security_kernel_enforce',
    'sige_security_kernel_verify_intent',
    'sige_security_kernel_require_permissions',
    'sige_security_kernel_require_tenant',
    'sige_security_kernel_rate_limit',
    'sige_security_kernel_audit',
    'sige_security_kernel_register',
    'sige_security_kernel_shortcode_pre',
    'sige_security_kernel_dispatch_wp_hook',
    'sige_security_kernel_bootstrap',
] as $fn) {
    if (strpos($kernel, 'function ' . $fn) === false) $fails[] = "kernel sem funcao {$fn}";
}
foreach ([
    'security_kernel.allowed',
    'security_kernel.denied',
    'security_kernel.unknown_surface',
] as $event) {
    if (strpos($kernel, $event) === false) $fails[] = "kernel sem evento {$event}";
}
$scope = strpos($main, "includes/security-scope-guard.php");
$kernelPos = strpos($main, "includes/security-kernel.php");
$migration = strpos($main, "includes/class-sige-migration.php");
$finance = strpos($main, "includes/finance-core.php");
if ($kernelPos === false) $fails[] = 'bootstrap nao carrega security-kernel.php';
if ($scope !== false && $kernelPos !== false && $kernelPos < $scope) $fails[] = 'kernel carregado antes do scope guard';
if ($migration !== false && $kernelPos !== false && $kernelPos > $migration) $fails[] = 'kernel carregado tarde demais, depois das migrations';
if ($finance !== false && $kernelPos !== false && $kernelPos > $finance) $fails[] = 'kernel carregado depois do finance-core';
if (strpos($kernel, 'SIGE_SECURITY_KERNEL_EARLY_PRIORITY') === false) $fails[] = 'kernel sem prioridade antecipada formal';
foreach (['admin_init','parse_request','template_redirect'] as $qh) {
    if (strpos($kernel, "add_action('{$qh}'") === false) $fails[] = 'kernel nao intercepta query handlers via ' . $qh;
}
if (strpos($kernel, 'sige_security_kernel_dispatch_query_handlers_on_hook') === false) $fails[] = 'kernel sem dispatcher multi-hook de query handlers';
if (strpos($kernel, 'sige_security_kernel_dispatch_view_actions') === false) $fails[] = 'kernel sem dispatcher de view_action';
if (strpos($kernel, 'add_action(\'admin_init\', static function (): void { sige_security_kernel_dispatch_view_actions(\'admin_init\'); }, $early - 1)') === false) $fails[] = 'kernel nao intercepta view_action antes dos handlers admin_init';
if (strpos($kernel, "add_action('wp_ajax_'") === false) $fails[] = 'kernel nao regista superficies AJAX';
if (strpos($kernel, "add_action('admin_post_'") === false) $fails[] = 'kernel nao regista superficies admin_post';
if (strpos($kernel, "add_filter('rest_pre_dispatch'") === false) $fails[] = 'kernel nao intercepta REST via rest_pre_dispatch';
if (strpos($kernel, '$early') === false) $fails[] = 'kernel nao usa prioridade antecipada nas intercepcoes runtime';
if (strpos($kernel, "add_filter('pre_do_shortcode_tag'") === false) $fails[] = 'kernel nao intercepta shortcodes via pre_do_shortcode_tag';
if (strpos($kernel, 'elseif ($type === \'wp_hook\')') === false) $fails[] = 'kernel nao regista wp_hook runtime';
if (strpos($kernel, 'sige_security_kernel_rest_surface_id_for_route') === false) $fails[] = 'kernel sem matching REST runtime';
if (strpos($rules, 'function sige_security_kernel_rules(): array') === false) $fails[] = 'rules sem funcao publica';
if (strpos($settingsRepo, 'current_escola_id(true)') === false) $fails[] = 'Settings repository sem current_escola_id strict em escritas tenant-scoped';
if (strpos($settingsRepo, 'Escola não resolvida para gravação tenant-scoped') === false) $fails[] = 'Settings repository nao falha fechado quando tenant nao resolve';

if ($fails) {
    fwrite(STDERR, "SECURITY KERNEL CHECK FALHOU\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo "SECURITY KERNEL OK - runtime, bootstrap e pontos de intercepcao presentes.\n";
exit(0);
