<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$fails = [];
$ok = 0;
$check = function (bool $cond, string $msg) use (&$fails, &$ok): void {
    if ($cond) { $ok++; echo "OK   {$msg}\n"; }
    else { $fails[] = $msg; echo "FALHOU {$msg}\n"; }
};
$read = function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return is_file($p) ? (string) file_get_contents($p) : '';
};
$plugin = $read('sige-softgenial.php');
$build = json_decode($read('BUILD.json'), true);
$ui = $read('includes/ui-kit.php');
$shell = $read('includes/admin-shell.php');
$css = $read('assets/sige-shell-stability.css');
$js = $read('assets/sige-shell-stability.js');
$run = $read('tools/run-gates.php');
$changelog = $read('CHANGELOG.md');

$verHeader = ''; if (preg_match('/Version:\s*([0-9.]+)/', $plugin, $m)) { $verHeader = $m[1]; }
$check($verHeader !== '' && version_compare($verHeader, '12.15.6', '>='), 'header do plugin em 12.15.6 ou superior');
$verConst = ''; if (preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\);/", $plugin, $m2)) { $verConst = $m2[1]; }
$check($verConst !== '' && version_compare($verConst, '12.15.6', '>='), 'constante SIGE_VERSION em 12.15.6 ou superior');
$check(is_array($build) && version_compare((string)($build['version'] ?? '0'), '12.15.6', '>='), 'BUILD.json em 12.15.6 ou superior');
$check(is_file($root . '/assets/sige-shell-stability.css'), 'asset CSS de shell presente');
$check(is_file($root . '/assets/sige-shell-stability.js'), 'asset JS de shell presente');
$check(strpos($ui, "wp_enqueue_style('sige-shell-stability'") !== false, 'CSS enfileirado via wp_enqueue_style');
$check(strpos($ui, "wp_enqueue_script('sige-shell-stability'") !== false, 'JS enfileirado via wp_enqueue_script');
$check(strpos($css, '@media (min-width: 1101px)') !== false, 'desktop real definido em 1101px ou mais');
$check(strpos($css, '@media (min-width: 861px) and (max-width: 1100px)') !== false, 'tablet definido entre 861px e 1100px');
$check(strpos($css, '@media (max-width: 860px)') !== false, 'mobile definido ate 860px');
$check(strpos($css, 'body.sige-admin-app.sg-app-menu-open') !== false, 'body so e bloqueado quando sidebar esta aberta');
$check(strpos($css, 'overflow-y: auto !important') !== false, 'contrato de scroll vertical explicito');
$check(strpos($css, 'overflow: visible !important') !== false, 'tablet e mobile desbloqueiam overflow do wrapper');
$check(strpos($css, 'height: 100dvh') !== false, 'usa viewport dinamico para sidebar/conteudo');
$check(strpos($css, '.sg-app-nav') !== false && strpos($css, 'overflow-y: auto !important') !== false, 'sidebar nav tem scroll proprio');
$check(strpos($shell, "window.matchMedia('(min-width: 1101px)')") !== false, 'restore de scroll independente limitado a desktop real');
$check(strpos($js, "data-sige-shell-stability', '12.15.6'") !== false, 'JS marca shell com versao');
$check(strpos($js, 'aria-expanded') !== false, 'JS sincroniza aria-expanded');
$check(strpos($js, 'sg-app-menu-open') !== false, 'JS sincroniza classe de menu aberto');
$check(strpos($js, 'Escape') !== false, 'JS fecha sidebar com Escape');
$check(strpos($js, 'addEventListener') !== false, 'JS externo usa eventos nao inline');
$check(strpos($run, 'Shell Scroll Contract (v12.15.6)') !== false, 'run-gates inclui smoke v12.15.6');
$check(strpos($run, 'Shell Scope Guard (v12.15.6)') !== false, 'run-gates inclui check de escopo v12.15.6');
$check(strpos($changelog, '## v12.15.6') !== false, 'CHANGELOG.md contem v12.15.6');
$check(is_file($root . '/docs/changelog/CHANGELOG-v12-15-6.txt'), 'changelog detalhado v12.15.6 existe');
$check(is_file($root . '/docs/design-system/SHELL_CONTRACT-v12.15.6.md'), 'contrato de shell documentado');
$check(is_file($root . '/docs/deploy/MIGRATION_ROLLBACK-v12.15.6-shell-scroll-topbar-sidebar.md'), 'notas de migracao e rollback existem');
$check(strpos($css, 'sige-design-system-pro') === false && strpos($js, 'sige-design-system-pro') === false, 'sem Design System PRO global agressivo');

if ($fails) {
    fwrite(STDERR, 'SMOKE SHELL SCROLL v12.15.6 FALHOU: ' . implode('; ', $fails) . "\n");
    exit(1);
}
echo 'SMOKE SHELL SCROLL v12.15.6 OK - ' . $ok . " verificacoes passaram.\n";
exit(0);
