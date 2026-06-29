<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.15.14 - Gate do split chrome vs views (Portal).
 *
 * Garante que a folha enxuta do portal:
 *   1. Existe e tem dimensao sensata (muito menor que o style.css completo).
 *   2. Contem o chrome/core que o portal precisa (tokens, app shell, topbar, failsafe).
 *   3. NAO carrega estilo de views financeiras (body.sige-view-financeiro), nem o
 *      hero financeiro .sg-finpro-wrap.
 *   4. So e servida no portal enxuto E com a flag ligada; a flag esta DESLIGADA por
 *      defeito; o style.css completo permanece o default para todos os outros casos.
 */
$root = dirname(__DIR__);
$errors = [];

$chrome_path = $root . '/assets/style-portal-chrome.css';
$full_path   = $root . '/assets/style.css';
$shell_path  = $root . '/includes/admin-shell.php';

if (!is_file($chrome_path)) {
    fwrite(STDERR, "check-portal-chrome-split-v12-15-14: FALHOU\n- Folha de chrome ausente: assets/style-portal-chrome.css\n");
    exit(1);
}
$chrome = (string) file_get_contents($chrome_path);
$full   = (string) file_get_contents($full_path);
$shell  = (string) file_get_contents($shell_path);

// 1. Dimensao sensata: menor que o completo, mas nao vazia. Banda 80 KB .. 160 KB.
$chrome_bytes = strlen($chrome);
$full_bytes   = strlen($full);
if ($chrome_bytes < 80000) $errors[] = "Folha de chrome demasiado pequena ({$chrome_bytes} bytes): extraccao incompleta?";
if ($chrome_bytes > 160000) $errors[] = "Folha de chrome demasiado grande ({$chrome_bytes} bytes): modulos de view a mais?";
if ($chrome_bytes >= $full_bytes) $errors[] = 'Folha de chrome nao e menor que o style.css completo: split nao aconteceu.';

// 2. Chrome/core presente (ancoras que o portal exige).
$anchors = [
    '.sg-app-topbar', '.sg-app-sidebar', 'body.sige-admin-app',
    '1. DESIGN TOKENS', '11. APP SHELL', 'Failsafe global',
];
foreach ($anchors as $a) {
    if (strpos($chrome, $a) === false) $errors[] = "Ancora de chrome em falta na folha enxuta: {$a}";
}

// 3. Estilo de views financeiras AUSENTE. body.sige-view-financeiro so existe nas
// regras escopadas a views financeiras; .sg-finpro-wrap e o hero financeiro. Ambos
// devem estar a zero na folha do portal. (Nota: uma unica regra de reset
// .sg-finpro-kicker:before vive na seccao do topbar e e inofensiva no portal, por
// isso a verificacao usa o wrapper e o escopo de view, nao o namespace inteiro.)
if (strpos($chrome, 'body.sige-view-financeiro') !== false) {
    $errors[] = 'A folha de chrome contem estilo escopado a views financeiras (body.sige-view-financeiro).';
}
if (strpos($chrome, '.sg-finpro-wrap') !== false) {
    $errors[] = 'A folha de chrome contem o hero financeiro (.sg-finpro-wrap): modulo de view vazou.';
}
// Pagamentos por Turma e Extractos: confirmar que os seus selectores proprios nao entraram.
if (strpos($chrome, '.sg-turma-pay') !== false || strpos($chrome, 'sg-pagturma') !== false) {
    $errors[] = 'A folha de chrome contem estilo de Pagamentos por Turma.';
}

// 4. Ligacao na shell: flag desligada por defeito, opt-in, e style.css preservado como default.
if (strpos($shell, "get_option('sige_portal_chrome_css_v121514_enabled', '0')") === false) {
    $errors[] = 'Flag de chrome ausente ou sem default desligado (esperado default \'0\').';
}
if (strpos($shell, "get_option('sige_portal_chrome_css_v121514_enabled', '0') === '1'") === false) {
    $errors[] = 'A folha de chrome nao e estritamente opt-in (esperado comparacao === \'1\').';
}
if (strpos($shell, '$sige_portal_chrome = $sige_portal_lean') === false) {
    $errors[] = 'A folha de chrome nao esta condicionada ao portal enxuto ($sige_portal_lean).';
}
if (strpos($shell, "SIGE_URL . 'assets/style-portal-chrome.css'") === false) {
    $errors[] = 'A shell nao enfileira a folha de chrome no ramo do portal.';
}
if (strpos($shell, "SIGE_URL . 'assets/style.css'") === false) {
    $errors[] = 'A shell deixou de enfileirar o style.css completo (default para todos os outros casos).';
}
// Defesa: o style.css completo nao pode ter sido reduzido por engano.
if ($full_bytes < 250000) {
    $errors[] = "O style.css completo parece ter encolhido ({$full_bytes} bytes): o split deve ser aditivo, nunca destrutivo.";
}

if ($errors) {
    fwrite(STDERR, "check-portal-chrome-split-v12-15-14: FALHOU\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
printf("check-portal-chrome-split-v12-15-14: OK - folha de chrome %d KB (vs %d KB completo), sem views financeiras, opt-in e desligada por defeito.\n", (int) round($chrome_bytes / 1024), (int) round($full_bytes / 1024));
