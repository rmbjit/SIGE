<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$read = static function (string $rel) use ($root): string {
    $path = $root . '/' . $rel;
    return is_file($path) ? (string)file_get_contents($path) : '';
};
$has = static function (string $haystack, string $needle): bool {
    return strpos($haystack, $needle) !== false;
};

$main = $read('sige-softgenial.php');
$view = $read('admin/academic/alunos_lista.php');
$ajax = $read('includes/aluno-fetch-ajax.php');
$helper = $read('includes/alunos-performance-contract.php');
$build = json_decode($read('BUILD.json'), true);

preg_match('/^\s*\*\s*Version:\s*([0-9.]+)/mi', $main, $mH);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $main, $mC);
$vH = $mH[1] ?? '';
$vC = $mC[1] ?? '';
$vB = is_array($build) ? (string)($build['version'] ?? '') : '';
if ($vH === '' || $vH !== $vC || $vC !== $vB || version_compare($vC, '12.17.0', '<')) {
    $errors[] = 'versao nao sincronizada ou inferior a 12.17.0: header=' . $vH . ' const=' . $vC . ' build=' . $vB;
}

if ($helper === '') $errors[] = 'includes/alunos-performance-contract.php ausente.';
foreach (['sige_alunos_perf_list_select_sql','sige_alunos_perf_export_select_sql','sige_alunos_perf_card_document_payload','sige_alunos_perf_defer_script_tag'] as $fn) {
    if (!$has($helper, 'function ' . $fn)) $errors[] = 'helper ausente: ' . $fn;
}

$helper_pos = strpos($main, "includes/alunos-performance-contract.php");
$ajax_pos = strpos($main, "includes/aluno-fetch-ajax.php");
if ($helper_pos === false || $ajax_pos === false || $helper_pos > $ajax_pos) {
    $errors[] = 'helper de performance deve carregar antes de aluno-fetch-ajax.php.';
}

if (!$has($view, 'sige_alunos_perf_list_select_sql($tbl_alunos, \'a\')')) {
    $errors[] = 'lista de alunos nao usa SELECT leve partilhado.';
}
if (preg_match('/SELECT\s+a\.\*/i', $view)) {
    $errors[] = 'alunos_lista.php ainda tem SELECT a.* directo.';
}
if (!$has($view, 'sige_alunos_perf_card_document_payload($a)')) {
    $errors[] = 'card de aluno ainda nao usa payload minimo para documentos.';
}
if (!$has($view, 'sige_alunos_perf_defer_script_tag')) {
    $errors[] = 'scripts pesados da lista nao usam tag defer local.';
}
if (!$has($view, 'LIMIT %d OFFSET %d')) {
    $errors[] = 'paginacao server-side LIMIT/OFFSET ausente.';
}
if (preg_match('/^\s*var\s+sigeTodosAlunos\s*=\s*<\?php/m', $view)) {
    $errors[] = 'JSON global gigante regressou a alunos_lista.php.';
}

if (!$has($ajax, 'sige_alunos_perf_export_select_sql($tbl_alunos, \'a\', $purpose)')) {
    $errors[] = 'endpoint de exportacao nao usa SELECT minimo por finalidade.';
}
if (!$has($ajax, "['excel','export','cards']") || !$has($ajax, 'in_array($purpose')) {
    $errors[] = 'validacao de purpose do endpoint de exportacao ausente.';
}
if (!$has($ajax, "wp_ajax_sige_get_alunos_export")) {
    $errors[] = 'endpoint de exportacao de alunos ausente.';
}

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "ERRO: {$error}\n");
    exit(1);
}

echo "v12.17.0 ALUNOS PERFORMANCE CONTRACT OK - lista e exportacao usam payload minimo controlado.\n";
