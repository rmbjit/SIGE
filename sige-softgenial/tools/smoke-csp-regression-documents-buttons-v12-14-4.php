<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
$root = dirname(__DIR__);
$checks = [];
function chk(&$checks, $label, $ok) { $checks[] = [$label, (bool)$ok]; }
$hist = file_get_contents($root . '/includes/financeiro-historico-aluno-pro.php');
$css = file_exists($root . '/assets/documents/financeiro-historico-aluno.css') ? file_get_contents($root . '/assets/documents/financeiro-historico-aluno.css') : '';
$docjs = file_exists($root . '/assets/sige-document-actions.js') ? file_get_contents($root . '/assets/sige-document-actions.js') : '';
$ui = file_get_contents($root . '/assets/sige-ui.js');
$csp = file_get_contents($root . '/includes/csp-zero-inline.php');

chk($checks, 'Historico financeiro carrega CSS externo', strpos($hist, 'assets/documents/financeiro-historico-aluno.css') !== false && strpos($hist, '<style>') === false);
chk($checks, 'Historico financeiro carrega JS documental externo', strpos($hist, 'assets/sige-document-actions.js') !== false);
chk($checks, 'Historico financeiro usa data-sige-print', strpos($hist, 'data-sige-print') !== false && strpos($hist, 'onclick="window.print') === false);
chk($checks, 'Historico financeiro usa data-sige-close-back', strpos($hist, 'data-sige-close-back') !== false && strpos($hist, 'onclick="window.close') === false);
chk($checks, 'CSS documental tem regras A4/print', strpos($css, '@media print') !== false && strpos($css, '@page') !== false && strpos($css, '.sheet') !== false);
chk($checks, 'JS documental sem eval/new Function', strpos($docjs, 'eval(') === false && strpos($docjs, 'new Function') === false);
chk($checks, 'JS documental liga print/close/back', strpos($docjs, 'data-sige-print') !== false && strpos($docjs, 'data-sige-close-back') !== false);
chk($checks, 'UI central suporta acções documentais', strpos($ui, 'data-sige-close-back') !== false && strpos($ui, 'data-sige-print') !== false);
chk($checks, 'UI central suporta eventos adicionais', strpos($ui, 'data-sige-on-load') !== false && strpos($ui, "'blur'") !== false && strpos($ui, "'focus'") !== false);
chk($checks, 'UI central sem eval/new Function', strpos($ui, 'eval(') === false && strpos($ui, 'new Function') === false);
chk($checks, 'CSP continua sem unsafe-inline', strpos($csp, "unsafe-inline") === false);

$failed = 0;
foreach ($checks as [$label, $ok]) {
    echo ($ok ? "OK   " : "FAIL ") . $label . PHP_EOL;
    if (!$ok) $failed++;
}
if ($failed) {
    fwrite(STDERR, "Falhas v12.14.4: {$failed}\n");
    exit(1);
}
echo "v12.14.4 CSP regression smoke OK (" . count($checks) . " checks)\n";
