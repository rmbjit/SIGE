<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Smoke Test Estático
 * v12.11.9.7 - RH / Equipa e Professores Senior Audit Hardening
 */
$root = dirname(__DIR__);
$checks = [];
$fail = function($msg) use (&$checks) { $checks[] = [false, $msg]; };
$ok   = function($msg) use (&$checks) { $checks[] = [true, $msg]; };
$read = function($rel) use ($root) {
    $path = $root . '/' . $rel;
    return file_exists($path) ? file_get_contents($path) : '';
};

$plugin = $read('sige-softgenial.php');
$ajax   = $read('includes/ajax-handlers.php');
$perm   = $read('includes/permissions-layer.php');
$guard  = $read('includes/page-guard.php');
$view   = $read('admin/hr/equipe-view.php');
$build  = $read('BUILD.json');

strpos($plugin, "Version: 12.11.9.7") !== false ? $ok('Header do plugin em 12.11.9.7') : $fail('Header do plugin não está em 12.11.9.7');
strpos($plugin, "define('SIGE_VERSION', '12.11.9.7')") !== false ? $ok('SIGE_VERSION em 12.11.9.7') : $fail('SIGE_VERSION não está em 12.11.9.7');
strpos($build, 'rh-senior-audit-hardening-pro-test') !== false ? $ok('BUILD.json alinhado com v12.11.9.7') : $fail('BUILD.json não está alinhado');

foreach ([
    'sige_ajax_equipe_normalize_decimal',
    'sige_ajax_equipe_sanitize_digits',
    'sige_ajax_equipe_date_ymd_or_empty',
    'sige_ajax_equipe_can_manage',
    'sige_ajax_equipe_user_belongs_to_school',
    'sige_get_staff_secure',
    'sige_exportar_folha_staff',
] as $needle) {
    strpos($ajax, $needle) !== false ? $ok("AJAX contém {$needle}") : $fail("AJAX sem {$needle}");
}

strpos($ajax, "NUIT inválido") !== false ? $ok('Validação server-side de NUIT detectada') : $fail('Sem validação server-side de NUIT');
strpos($ajax, "Tipo de vínculo inválido") !== false ? $ok('Validação server-side de tipo de vínculo detectada') : $fail('Sem validação server-side de tipo de vínculo');
strpos($ajax, "Data de término inválida") !== false ? $ok('Validação server-side de data detectada') : $fail('Sem validação server-side de data');
strpos($ajax, "Conta técnica WordPress real não deve ser desactivada") !== false ? $ok('Bloqueio de desactivação de WP admin real detectado') : $fail('Sem bloqueio de desactivação de WP admin real');

$rhFn = '';
if (preg_match('/function\s+sige_rh_user_is_active_for_school[\s\S]*?\n\s*}\n}/', $perm, $m)) { $rhFn = $m[0]; }
$hasManageOptionsBypass = preg_match('/\n\s*if\s*\(\s*user_can\s*\(\s*\$user_id\s*,\s*[\"\']manage_options[\"\']\s*\)/', $rhFn);
(!$hasManageOptionsBypass)
    ? $ok('Desactivação RH não é contornada por manage_options herdado')
    : $fail('sige_rh_user_is_active_for_school ainda usa user_can(manage_options)');

$guardFn = '';
if (preg_match('/function\s+sige_page_guard_is_real_admin[\s\S]*?\n\s*}\n}/', $guard, $m)) { $guardFn = $m[0]; }
strpos($guardFn, "user_can(") === false ? $ok('Page guard usa bypass estrito por role real') : $fail('Page guard ainda usa user_can para admin real');

foreach (['sigeEquipeEscapeHtml', 'safeTitle', 'safeMessage', 'novoFuncionario', 'editarStaff', 'toggleStatus', 'resetSenha', 'removerUser'] as $needle) {
    strpos($view, $needle) !== false ? $ok("View contém {$needle}") : $fail("View sem {$needle}");
}

$bad = array_values(array_filter($checks, fn($c) => !$c[0]));
foreach ($checks as [$pass, $msg]) {
    echo ($pass ? '[OK] ' : '[FAIL] ') . $msg . PHP_EOL;
}
if ($bad) {
    echo PHP_EOL . count($bad) . " falha(s) detectada(s)." . PHP_EOL;
    exit(1);
}
echo PHP_EOL . "Smoke RH v12.11.9.7 OK" . PHP_EOL;
