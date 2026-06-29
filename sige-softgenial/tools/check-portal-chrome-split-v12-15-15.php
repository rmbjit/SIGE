<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.15.15 - Gate drift-proof do split chrome vs views.
 *
 * Productionizacao da Fase 2: a folha enxuta do portal e GERADA a partir do
 * style.css. Este gate garante que o ficheiro comprometido e EXACTAMENTE a saida
 * do gerador (sem drift), alem das verificacoes de seguranca e de ligacao.
 *
 * Se o chrome do style.css mudar sem regenerar, este gate fica vermelho.
 * Regenerar com: php tools/gen-portal-chrome.php
 */
$root = dirname(__DIR__);
$errors = [];

$lib = $root . '/tools/lib-portal-chrome.php';
$hdr = $root . '/tools/portal-chrome-header.txt';
$chromePath = $root . '/assets/style-portal-chrome.css';
$stylePath  = $root . '/assets/style.css';
$shellPath  = $root . '/includes/admin-shell.php';

foreach ([$lib => 'tools/lib-portal-chrome.php', $hdr => 'tools/portal-chrome-header.txt', $chromePath => 'assets/style-portal-chrome.css'] as $path => $label) {
    if (!is_file($path)) {
        fwrite(STDERR, "check-portal-chrome-split-v12-15-15: FALHOU\n- Ficheiro em falta: {$label}\n");
        exit(1);
    }
}
require_once $lib;

$committed = (string) file_get_contents($chromePath);
$full      = (string) file_get_contents($stylePath);
$shell     = (string) file_get_contents($shellPath);

// 1. DRIFT: o ficheiro comprometido tem de ser, byte-a-byte, a saida do gerador.
$expected = null;
try {
    $expected = sige_portal_chrome_render($stylePath);
} catch (Throwable $e) {
    $errors[] = 'Gerador falhou (ancora de seccao em falta no style.css?): ' . $e->getMessage();
}
if ($expected !== null && $committed !== $expected) {
    $errors[] = 'A folha de chrome esta dessincronizada do style.css (drift). Regenerar: php tools/gen-portal-chrome.php';
}

// 2. Dimensao sensata e menor que o completo (defesa contra geracao vazia ou inflada).
$chrome_bytes = strlen($committed);
$full_bytes   = strlen($full);
if ($chrome_bytes < 80000) $errors[] = "Folha de chrome demasiado pequena ({$chrome_bytes} bytes).";
if ($chrome_bytes >= $full_bytes) $errors[] = 'Folha de chrome nao e menor que o style.css completo.';
if ($full_bytes < 250000) $errors[] = "O style.css completo parece ter encolhido ({$full_bytes} bytes): o split deve ser nao-destrutivo.";

// 3. Chrome presente, estilo de views financeiras ausente.
foreach (['.sg-app-topbar', '.sg-app-sidebar', 'body.sige-admin-app', '11. APP SHELL'] as $a) {
    if (strpos($committed, $a) === false) $errors[] = "Ancora de chrome em falta: {$a}";
}
if (strpos($committed, 'body.sige-view-financeiro') !== false) $errors[] = 'Estilo de view financeira presente na folha de chrome.';
if (strpos($committed, '.sg-finpro-wrap') !== false) $errors[] = 'Hero financeiro (.sg-finpro-wrap) presente na folha de chrome.';

// 4. Ligacao na shell: flag desligada por defeito, opt-in, style.css preservado como default.
if (strpos($shell, "get_option('sige_portal_chrome_css_v121514_enabled', '0') === '1'") === false) {
    $errors[] = 'A folha de chrome nao esta opt-in e desligada por defeito na shell.';
}
if (strpos($shell, '$sige_portal_chrome = $sige_portal_lean') === false) {
    $errors[] = 'A folha de chrome nao esta condicionada ao portal enxuto.';
}
if (strpos($shell, "SIGE_URL . 'assets/style-portal-chrome.css'") === false) {
    $errors[] = 'A shell nao enfileira a folha de chrome no ramo do portal.';
}
if (strpos($shell, "SIGE_URL . 'assets/style.css'") === false) {
    $errors[] = 'A shell deixou de enfileirar o style.css completo (default).';
}

if ($errors) {
    fwrite(STDERR, "check-portal-chrome-split-v12-15-15: FALHOU\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
printf("check-portal-chrome-split-v12-15-15: OK - folha gerada sincronizada (%d KB de %d KB), sem views financeiras, opt-in e desligada por defeito.\n", (int) round($chrome_bytes / 1024), (int) round($full_bytes / 1024));
