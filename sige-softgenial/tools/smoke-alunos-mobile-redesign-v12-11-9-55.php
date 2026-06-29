<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$file = $root . '/admin/academic/alunos_lista.php';
$main = $root . '/sige-softgenial.php';
$build = $root . '/BUILD.json';
$src = file_get_contents($file);
$mainSrc = file_get_contents($main);
$buildSrc = file_get_contents($build);
$checks = [
    'version main' => strpos($mainSrc, "12.11.9.55") !== false,
    'build version' => strpos($buildSrc, "12.11.9.55") !== false,
    'mobile school head markup' => strpos($src, 'sige-alunos-mobile-school-head') !== false,
    'mobile bottom nav markup' => strpos($src, 'sige-mobile-bottom-nav') !== false,
    'mobile status chips markup' => strpos($src, 'data-sige-mobile-chip="devedores"') !== false,
    'quick action row markup' => strpos($src, 'sige-mobile-card-actions-row') !== false,
    'quick action ver ficha' => strpos($src, '<span>Ver ficha</span>') !== false,
    'quick action matricula' => strpos($src, '<span>Matrícula</span>') !== false,
    'hero subtitle screenshot' => strpos($src, 'Consulte alunos, matrículas e documentos.') !== false,
    'birthday kpi mobile text' => strpos($src, '<div class="sige-stat-label">Aniversários</div>') !== false && strpos($src, '<div class="sige-stat-note">Hoje</div>') !== false,
    'placeholder screenshot' => strpos($src, 'Pesquisar aluno, processo ou turma') !== false,
    'processo label' => strpos($src, 'Processo: <strong>') !== false,
    'search includes turma' => strpos($src, '$turma_label') !== false && strpos($src, '$status_label') !== false,
    'mobile redesign css marker' => strpos($src, 'v12.11.9.55 - Smoke visual/funcional') !== false,
    'topbar mobile gradient' => strpos($src, 'background:linear-gradient(135deg,#003f8f') !== false,
    'safe area bottom nav' => strpos($src, 'env(safe-area-inset-bottom') !== false,
    'chip filter js' => strpos($src, 'function applyMobileStatusChipFilter') !== false,
    'chip hidden class' => strpos($src, 'is-mobile-chip-hidden') !== false,
    'hero art mobile visible' => strpos($src, '.sige-hero-art{') !== false,
    'bottom nav active alunos' => strpos($src, 'view=alunos_lista') !== false && strpos($src, 'class="is-active"') !== false,
];
$fail = 0;
foreach ($checks as $name => $ok) {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$ok) $fail++;
}
if ($fail) {
    fwrite(STDERR, "Smoke v12.11.9.55 FAILED with {$fail} failure(s)" . PHP_EOL);
    exit(1);
}
echo "Smoke v12.11.9.55 OK" . PHP_EOL;
