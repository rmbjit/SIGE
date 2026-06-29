<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Gate de Consistencia Visual (primitivos sem token)
 *
 * ORIGEM (14 Jun 2026): o diagnostico 360 mostrou que a fonte da verdade
 * (assets/sige-tokens.css) ja define a escala completa de espacamento,
 * tipografia, sombras, transicoes e camadas, MAS a adopcao fora da cor
 * era residual:
 *     --fs-*      0 usos  para 2488 font-size px literais
 *     --space-*   8 usos  para 4792 padding/margin/gap px literais
 *     --shadow-* 13 usos  para 1147 box-shadow literais
 *     --duration-* 31     para  178 transition com tempo literal
 *     --z-*       8 usos  para   95 z-index numerico literal
 *
 * O gate check-design-tokens.php ja blinda cor (hex) e raio. Este gate
 * irmao blinda os PRIMITIVOS RESTANTES, com a mesma filosofia: conta os
 * valores magicos fora da fonte da verdade e FALHA se o total subir
 * acima da baseline. A migracao so pode reduzir. Conforme cada view
 * troca px por var(--token), a baseline desce (--set na mesma entrega).
 *
 * Dimensoes vigiadas (todas tem token canonico equivalente):
 *   - font-size  px literal      -> --fs-*
 *   - padding/margin/gap px lit.  -> --space-*
 *   - box-shadow literal          -> --shadow-*
 *   - transition com tempo literal-> --duration-*
 *   - z-index numerico literal    -> --z-*
 *   - atributo style="..." inline -> classe utilitaria / CSS
 *
 * Uso:
 *   php tools/check-consistencia-visual.php           verifica contra baseline
 *   php tools/check-consistencia-visual.php --set      regista a baseline actual
 *   php tools/check-consistencia-visual.php --report   piores ficheiros por dimensao
 */

$raiz = dirname(__DIR__);
$baseline_file = $raiz . '/tools/.consistencia-visual-baseline.json';

// Ficheiros isentos: a propria fonte da verdade e o kit definem a escala.
$isentos = [
    'assets/sige-tokens.css',
    'assets/sige-ui.css',
    // Contextos SEM tokens (nao carregam sige-tokens.css): literais legitimos.
    'includes/login-page.php',
    'includes/portal-logic.php',
    'includes/passagem-docs-handler.php',
    'includes/email-engine.php',          // HTML de email: estilos inline literais sao obrigatorios
    'includes/email-queue-templates.php',
    'includes/finance-devedores-pdf.php', // documento standalone de cobranca (nao carrega sige-tokens.css)
    // Templates de impressao / documentos standalone (DOCTYPE proprio, sem
    // sige-tokens.css): literais obrigatorios, mesma categoria dos de cima.
    'includes/documents-engine.php',
    'admin/boletim-pdf-template.php',
    'admin/pauta-pdf-template.php',
    'admin/acta-oficial-template.php',
    'includes/portaria-camera-safe-page.php',
    'assets/documents/financeiro-historico-aluno.css',
];

/**
 * Conta valores magicos de primitivos NAO-cor num pedaco de CSS/HTML/PHP.
 * Remove comentarios antes de contar para nao apanhar documentacao.
 */
function consistencia_contar(string $s): array {
    $s = preg_replace('#/\*.*?\*/#s', '', $s);
    $s = preg_replace('#//[^\n]*#', '', $s);

    $c = [
        'fontsize'   => 0,
        'spacing'    => 0,
        'boxshadow'  => 0,
        'transition' => 0,
        'zindex'     => 0,
        'inline'     => 0,
    ];

    // font-size com px literal (ignora os que ja usam var())
    if (preg_match_all('/font-size\s*:\s*[^;{}]*\b\d+(?:\.\d+)?px/i', $s, $m)) {
        foreach ($m[0] as $d) { if (strpos($d, 'var(') === false) $c['fontsize']++; }
    }
    // padding / margin / gap com px literal
    if (preg_match_all('/\b(?:padding|margin|gap)\s*:\s*[^;{}]*\b\d+px/i', $s, $m)) {
        foreach ($m[0] as $d) { if (strpos($d, 'var(') === false) $c['spacing']++; }
    }
    // box-shadow literal (rgb/rgba/hex direto, nao var())
    if (preg_match_all('/box-shadow\s*:\s*[^;{}]+/i', $s, $m)) {
        foreach ($m[0] as $d) {
            if (strpos($d, 'var(') !== false) continue;
            if (preg_match('/rgba?\(|#[0-9a-fA-F]{3,6}\b/', $d)) $c['boxshadow']++;
        }
    }
    // transition com tempo literal (xxms / x.xs)
    if (preg_match_all('/transition\s*:\s*[^;{}]+/i', $s, $m)) {
        foreach ($m[0] as $d) {
            if (strpos($d, 'var(') !== false) continue;
            if (preg_match('/\b\d+(?:\.\d+)?m?s\b/', $d)) $c['transition']++;
        }
    }
    // z-index numerico literal (>= 2 digitos para ignorar 0/1/2 de empilhamento local)
    if (preg_match_all('/z-index\s*:\s*([^;{}]+)/i', $s, $m)) {
        foreach ($m[1] as $d) {
            if (strpos($d, 'var(') !== false) continue;
            if (preg_match('/\b\d{2,}\b/', $d)) $c['zindex']++;
        }
    }
    // atributo style="..." inline (HTML em PHP/views)
    if (preg_match_all('/\bstyle\s*=\s*["\']/i', $s, $m)) {
        $c['inline'] = count($m[0]);
    }

    return $c;
}

// Varrer views, includes e CSS (excepto tokens/kit isentos, tools e docs).
$alvos = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $ext = $f->getExtension();
    if (!in_array($ext, ['php', 'css'], true)) continue;
    $rel = str_replace($raiz . '/', '', $f->getPathname());
    if (strpos($rel, 'tools/') === 0) continue;
    if (strpos($rel, 'docs/') === 0) continue;
    if (strpos($rel, '.github/') === 0) continue;
    if (strpos($rel, 'vendor/') !== false) continue;
    if (in_array($rel, $isentos, true)) continue;
    $alvos[] = $rel;
}
sort($alvos);

$dims = ['fontsize', 'spacing', 'boxshadow', 'transition', 'zindex', 'inline'];
$rotulos = [
    'fontsize'   => 'font-size px literal      (-> --fs-*)',
    'spacing'    => 'padding/margin/gap px lit. (-> --space-*)',
    'boxshadow'  => 'box-shadow literal         (-> --shadow-*)',
    'transition' => 'transition tempo literal   (-> --duration-*)',
    'zindex'     => 'z-index numerico literal   (-> --z-*)',
    'inline'     => 'atributo style="..." inline (-> classe/CSS)',
];

$tot = array_fill_keys($dims, 0);
$por_ficheiro = [];
foreach ($alvos as $rel) {
    $s = (string)file_get_contents($raiz . '/' . $rel);
    $c = consistencia_contar($s);
    $soma = array_sum($c);
    if ($soma > 0) $por_ficheiro[$rel] = $c;
    foreach ($dims as $d) $tot[$d] += $c[$d];
}
$total = array_sum($tot);

$modo = $argv[1] ?? '';

if ($modo === '--report') {
    printf("Primitivos magicos (sem token): %d  em %d ficheiros\n", $total, count($por_ficheiro));
    echo str_repeat('-', 64) . "\n";
    foreach ($dims as $d) printf("  %6d  %s\n", $tot[$d], $rotulos[$d]);
    echo str_repeat('-', 64) . "\n";
    echo "Piores 20 ficheiros:\n";
    uasort($por_ficheiro, fn($a, $b) => array_sum($b) - array_sum($a));
    $i = 0;
    foreach ($por_ficheiro as $rel => $c) {
        printf("  %5d  fs=%-4d sp=%-4d sh=%-4d tr=%-3d z=%-3d in=%-4d  %s\n",
            array_sum($c), $c['fontsize'], $c['spacing'], $c['boxshadow'],
            $c['transition'], $c['zindex'], $c['inline'], $rel);
        if (++$i >= 20) break;
    }
    exit(0);
}

if ($modo === '--set') {
    $payload = ['total' => $total];
    foreach ($dims as $d) $payload[$d] = $tot[$d];
    $payload['ficheiros'] = count($por_ficheiro);
    $payload['registado_em'] = date('Y-m-d H:i');
    file_put_contents($baseline_file, json_encode($payload, JSON_PRETTY_PRINT) . "\n");
    printf("Baseline de consistencia visual registada: %d primitivos magicos.\n", $total);
    foreach ($dims as $d) printf("   %-11s %d\n", $d, $tot[$d]);
    exit(0);
}

// Verificacao (default): comparar com baseline. Total nao sobe E nenhuma
// dimensao sobe (impede trocar uma divida por outra).
if (!is_file($baseline_file)) {
    fwrite(STDERR, "Baseline inexistente. Corra: php tools/check-consistencia-visual.php --set\n");
    exit(1);
}
$base = json_decode((string)file_get_contents($baseline_file), true);
$limite = (int)($base['total'] ?? 0);

printf("Primitivos magicos actuais: %d (baseline: %d)\n", $total, $limite);

$regressoes = [];
foreach ($dims as $d) {
    $b = (int)($base[$d] ?? 0);
    if ($tot[$d] > $b) $regressoes[] = sprintf('%s %d>%d', $d, $tot[$d], $b);
}

if ($total > $limite || $regressoes) {
    echo str_repeat('=', 64) . "\n";
    $msg = "REGRESSAO DE CONSISTENCIA VISUAL: ";
    if ($total > $limite) $msg .= sprintf("total %d > baseline %d. ", $total, $limite);
    if ($regressoes) $msg .= 'dimensoes que subiram: ' . implode('; ', $regressoes) . '. ';
    $msg .= "Use os tokens de assets/sige-tokens.css (--fs-*, --space-*, --shadow-*, --duration-*, --z-*) em vez de px literais.\n";
    fwrite(STDERR, $msg);
    exit(1);
}
if ($total < $limite) {
    printf("PROGRESSO: %d abaixo da baseline. Corra --set para registar o novo minimo.\n", $limite - $total);
}
echo "CONSISTENCIA VISUAL OK - sem regressao de primitivos magicos.\n";
exit(0);
