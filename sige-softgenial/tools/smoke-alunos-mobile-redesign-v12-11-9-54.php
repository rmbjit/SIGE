<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$file = $root . '/admin/academic/alunos_lista.php';
$code = file_get_contents($file);
$checks = [
    'mobile school head markup' => 'sige-alunos-mobile-school-head',
    'mobile bottom nav markup' => 'sige-mobile-bottom-nav',
    'mobile status chips markup' => 'sige-mobile-status-chips',
    'quick action row markup' => 'sige-mobile-card-actions-row',
    'quick action ver ficha' => 'Ver ficha',
    'quick action matricula' => 'Matrícula',
    'mobile redesign css marker' => 'v12.11.9.54 - Mobile App Redesign Alunos PRO',
    'topbar mobile gradient' => '.sg-app-topbar',
    'chip filter js' => 'applyMobileStatusChipFilter',
    'chip hidden class' => 'is-mobile-chip-hidden',
    'hero art mobile visible' => '.sige-hero-art',
    'bottom nav active alunos' => 'class="is-active"',
];
$fail = 0;
foreach ($checks as $label => $needle) {
    $ok = strpos($code, $needle) !== false;
    echo ($ok ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $fail++;
}
if ($fail > 0) exit(1);
echo "Smoke v12.11.9.54 OK\n";
