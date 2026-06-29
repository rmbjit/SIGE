<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$shell_path = $root . '/includes/admin-shell.php';
$shell = is_file($shell_path) ? (string) file_get_contents($shell_path) : '';
if ($shell === '') $errors[] = 'admin-shell.php em falta.';

$allow = [];
$map = [];
$perm = [];
if ($shell !== '') {
    if (preg_match('/\$_views_ok\s*=\s*\[(.*?)\];/s', $shell, $m)) {
        preg_match_all("/'([a-z0-9_\-]+)'/", $m[1], $matches);
        $allow = $matches[1];
    } else { $errors[] = 'allowlist $_views_ok nao encontrada.'; }

    if (preg_match('/\$map\s*=\s*\[(.*?)
\s*\];/s', $shell, $m)) {
        preg_match_all("/'([a-z0-9_\-]+)'\s*=>\s*'([^']+)'/", $m[1], $matches, PREG_SET_ORDER);
        foreach ($matches as $row) $map[$row[1]] = $row[2];
    } else { $errors[] = 'mapa de rotas $map nao encontrado.'; }

    if (preg_match('/\$sige_view_permission_map\s*=\s*\[(.*?)
\s*\];/s', $shell, $m)) {
        preg_match_all('/"([a-z0-9_\-]+)"\s*=>\s*\[([^\]]*)\]/', $m[1], $double, PREG_SET_ORDER);
        foreach ($double as $row) $perm[$row[1]] = $row[2];
    } else { $errors[] = 'mapa de permissoes nao encontrado.'; }
}

$unique_allow = array_values(array_unique($allow));
if (count($allow) !== count($unique_allow)) {
    $dupes = array_values(array_unique(array_diff_assoc($allow, $unique_allow)));
    $errors[] = 'allowlist ainda contem duplicados: ' . implode(', ', $dupes);
}
$expected_count = 60;
if (count($unique_allow) !== $expected_count) $errors[] = 'allowlist unica com contagem inesperada: ' . count($unique_allow);

foreach ($unique_allow as $view) {
    if (!isset($perm[$view])) $errors[] = 'view sem permissao declarada: ' . $view;
    $rel = $map[$view] ?? ('admin/' . $view . '-view.php');
    if (!is_file($root . '/' . $rel) && !is_file($root . '/admin/' . basename($rel))) {
        $errors[] = 'view sem ficheiro resolvivel: ' . $view . ' => ' . $rel;
    }
}
foreach (array_keys($perm) as $view) {
    if (!in_array($view, $unique_allow, true)) $errors[] = 'permissao declarada fora da allowlist: ' . $view;
}
foreach (['minhas_turmas','portaria','aluno_portal','financeiro-pagamentos','financeiro-extratos','sige_permissoes'] as $critical) {
    if (!in_array($critical, $unique_allow, true)) $errors[] = 'view critica ausente da allowlist: ' . $critical;
    if (!isset($map[$critical])) $errors[] = 'view critica sem rota: ' . $critical;
    if (!isset($perm[$critical])) $errors[] = 'view critica sem permissao: ' . $critical;
}

if (strpos($shell, 'sige-mobile-header-professor-rc7-v121600') === false) $errors[] = 'hotfix mobile RC7 ausente.';
if (strpos($shell, "'minhas_turmas'   => 'admin/academic/minhas_turmas-view.php'") === false) $errors[] = 'rota Minhas Turmas ausente.';
if (strpos($shell, 'sige_institutional_navigation_groups_v121600') === false) $errors[] = 'navegacao operacional institucional ausente.';

if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR, "ERRO: {$error}
"); }
    exit(1);
}

echo "v12.16.1 SHELL CONTRACT OK - allowlist sem duplicados, 60 views unicas, rotas e permissoes coerentes.
";
