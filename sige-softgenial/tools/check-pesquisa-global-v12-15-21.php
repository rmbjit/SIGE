<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.15.21 - Gate da Pesquisa Global (P3).
 *
 * Protege os invariantes da caixa de pesquisa unica da barra de topo:
 *  (a) backend regista o handler AJAX e e carregado no bootstrap;
 *  (b) handler com nonce global, escola_id, $wpdb->prepare e esc_like (so leitura);
 *  (c) resultados escopados por permissao (alunos.ver, academico.turmas_ver,
 *      financeiro.extractos_ver/financeiro.pagar);
 *  (d) frontend: markup na shell, JS e CSS enfileirados;
 *  (e) JS sem dialogos nativos nem onclick (so addEventListener), com a accao certa;
 *  (f) CSS so com var() nas categorias de drift (sem px literais nessas categorias).
 */
$root = dirname(__DIR__);
$errors = [];

$be    = $root . '/includes/sige-pesquisa-global.php';
$boot  = $root . '/sige-softgenial.php';
$shell = $root . '/includes/admin-shell.php';
$js    = $root . '/assets/views/pesquisa-global.js';
$css   = $root . '/assets/views/pesquisa-global.css';

foreach ([
    $be => 'includes/sige-pesquisa-global.php',
    $boot => 'sige-softgenial.php',
    $shell => 'includes/admin-shell.php',
    $js => 'assets/views/pesquisa-global.js',
    $css => 'assets/views/pesquisa-global.css',
] as $path => $label) {
    if (!is_file($path)) {
        fwrite(STDERR, "check-pesquisa-global-v12-15-21: FALHOU\n- Ficheiro em falta: {$label}\n");
        exit(1);
    }
}

$sBe    = (string) file_get_contents($be);
$sBoot  = (string) file_get_contents($boot);
$sShell = (string) file_get_contents($shell);
$sJs    = (string) file_get_contents($js);
$sCss   = (string) file_get_contents($css);

// (a) Handler registado e ficheiro carregado.
if (strpos($sBe, "add_action('wp_ajax_sige_pesquisa_global'") === false) {
    $errors[] = 'backend nao regista wp_ajax_sige_pesquisa_global.';
}
if (strpos($sBoot, "includes/sige-pesquisa-global.php") === false) {
    $errors[] = 'sige-pesquisa-global.php nao e carregado no bootstrap do plugin.';
}

// (b) Guarda do handler: nonce, escola_id, prepare, esc_like, so leitura.
$reqBe = [
    'sige_check_nonce_global'   => 'nonce global',
    'sige_get_escola_id'        => 'isolamento multi-tenant (escola_id)',
    'escola_id = %d'            => 'filtro escola_id nas queries',
    '->prepare('               => 'queries preparadas',
    'esc_like('                 => 'escape do termo de pesquisa',
];
foreach ($reqBe as $needle => $desc) {
    if (strpos($sBe, $needle) === false) {
        $errors[] = "handler sem {$desc} ({$needle}).";
    }
}
foreach (['INSERT ', 'UPDATE ', 'DELETE ', '->query('] as $escrita) {
    if (strpos($sBe, $escrita) !== false) {
        $errors[] = "backend de pesquisa contem escrita/DDL ({$escrita}); deve ser so leitura.";
    }
}

// (c) Escopo por permissao.
foreach (["'alunos.ver'", "'academico.turmas_ver'", "'financeiro.extractos_ver'", "'financeiro.pagar'", "'financeiro.despesas_ver'", "'financeiro.planos_ver'"] as $perm) {
    if (strpos($sBe, $perm) === false) {
        $errors[] = "backend nao escopa pelo grupo de permissao {$perm}.";
    }
}

// (d) Frontend: markup na shell + enqueues.
foreach ([
    'id="sgGSearchInput"'  => 'input da pesquisa na topbar',
    'id="sgGSearchPanel"'  => 'painel de resultados na topbar',
    'class="sg-gsearch"'   => 'contentor da pesquisa',
    "wp_enqueue_script('sige-pesquisa-global'" => 'enqueue do JS',
    "wp_enqueue_style('sige-pesquisa-global'"  => 'enqueue do CSS',
] as $needle => $desc) {
    if (strpos($sShell, $needle) === false) {
        $errors[] = "shell sem {$desc} ({$needle}).";
    }
}

// (e) JS: sem dialogos nativos nem onclick, com a accao certa.
if (preg_match('/(?<![\w.])(?:alert|confirm|prompt)\s*\(/', $sJs)) {
    $errors[] = 'pesquisa-global.js usa dialogo nativo (alert/confirm/prompt em chamada nua).';
}
if (strpos($sJs, 'onclick') !== false) {
    $errors[] = 'pesquisa-global.js contem onclick (proibido: addEventListener apenas).';
}
if (strpos($sJs, 'addEventListener') === false) {
    $errors[] = 'pesquisa-global.js nao usa addEventListener.';
}
if (strpos($sJs, "action: 'sige_pesquisa_global'") === false) {
    $errors[] = 'pesquisa-global.js nao chama a accao sige_pesquisa_global.';
}

// (f) CSS: sem px literais nas categorias de drift (font-size/padding/margin/gap),
// box-shadow so var(), z-index so var().
if (preg_match('/font-size\s*:\s*[^;{}]*\b\d+px/i', $sCss)) {
    $errors[] = 'pesquisa-global.css tem font-size em px literal (usar var(--fs-*)).';
}
if (preg_match('/\b(?:padding|margin|gap)\s*:\s*[^;{}]*\b\d+px/i', $sCss)) {
    $errors[] = 'pesquisa-global.css tem padding/margin/gap em px literal (usar var(--space-*)).';
}
if (preg_match('/box-shadow\s*:\s*[^;{}]*(?:rgba?\(|#[0-9a-fA-F]{3,6})/i', $sCss)) {
    $errors[] = 'pesquisa-global.css tem box-shadow literal (usar var(--shadow-*)).';
}
if (preg_match('/z-index\s*:\s*\d{2,}/i', $sCss)) {
    $errors[] = 'pesquisa-global.css tem z-index numerico literal (usar var(--z-*)).';
}

if ($errors) {
    fwrite(STDERR, "check-pesquisa-global-v12-15-21: FALHOU\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
printf("check-pesquisa-global-v12-15-21: OK - pesquisa global escopada por permissao e por escola, so leitura com queries preparadas, frontend sem dialogos nativos nem onclick, CSS so com tokens.\n");
