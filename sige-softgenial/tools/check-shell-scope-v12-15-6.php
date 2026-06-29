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
$css = $read('assets/sige-shell-stability.css');
$js = $read('assets/sige-shell-stability.js');
$ui = $read('includes/ui-kit.php');

$check($css !== '', 'CSS de shell existe');
$check($js !== '', 'JS de shell existe');
$check(strpos($css, 'SIGE SoftGenial v12.15.6') !== false, 'CSS identifica v12.15.6');
$check(strpos($css, 'body.sige-admin-app #sige-layout.sg-product-pro-shell') !== false, 'CSS esta escopado ao shell principal');
$check(strpos($ui, "wp_enqueue_style('sige-shell-stability'") !== false, 'ui-kit enfileira CSS de shell');
$check(strpos($ui, "wp_enqueue_script('sige-shell-stability'") !== false, 'ui-kit enfileira JS de shell');

$forbidden = [
    '/(^|[\s,{])button\s*[,{]/i' => 'selector global de button',
    '/(^|[\s,{])table\s*[,{]/i' => 'selector global de table',
    '/(^|[\s,{])input\s*[,{]/i' => 'selector global de input',
    '/(^|[\s,{])select\s*[,{]/i' => 'selector global de select',
    '/(^|[\s,{])textarea\s*[,{]/i' => 'selector global de textarea',
    '/\.sige-aluno/i' => 'classes internas de aluno',
    '/\.sige-alunos-card/i' => 'cards internos de alunos',
    '/\.sige-student-card/i' => 'cards internos de estudantes',
    '/\.sg-aluno/i' => 'familia sg-aluno',
    '/\.sg-pay/i' => 'familia de pagamentos',
    '/\.sg-fin/i' => 'familia financeira',
    '/\.sige-card/i' => 'cards genericos do produto',
    '/\.sg-card/i' => 'cards genericos sg',
    '/\.button\b/i' => 'classe button interna',
    '/\.sige-btn\b/i' => 'classe sige-btn interna',
];
foreach ($forbidden as $rx => $label) {
    $check(!preg_match($rx, $css), 'CSS nao usa ' . $label);
}
$check(strpos($css, 'sige-design-system-pro') === false, 'CSS nao reintroduz Design System PRO global');
$check(strpos($js, 'sige-design-system-pro') === false, 'JS nao reintroduz Design System PRO global');
$check(strpos($js, 'eval(') === false && strpos($js, 'new Function') === false && strpos($js, 'Function(') === false, 'JS sem eval ou Function');
$check(strpos($js, 'document.querySelectorAll(\'*\'') === false && strpos($js, 'querySelectorAll("*")') === false, 'JS nao percorre todos os elementos da pagina');
$check(strpos($js, 'sige-sidebar') !== false && strpos($js, 'sige-layout') !== false, 'JS limitado a shell/sidebar');

if ($fails) {
    fwrite(STDERR, 'CHECK SHELL SCOPE v12.15.6 FALHOU: ' . implode('; ', $fails) . "\n");
    exit(1);
}
echo 'CHECK SHELL SCOPE v12.15.6 OK - ' . $ok . " verificacoes passaram.\n";
exit(0);
