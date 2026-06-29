<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Gate dos Design Tokens (9º gate)
 *
 * ORIGEM (13 Jun 2026): diagnóstico encontrou 936 cores hex distintas
 * (10.445 usos), 29 raios de borda, 500 sombras espalhados pelas views.
 * Criou-se a fonte da verdade (assets/sige-tokens.css). Este gate impede
 * o regresso ao caos: conta os valores MÁGICOS (hex literais e raios
 * soltos) fora do ficheiro de tokens e do ficheiro do kit, e FALHA se o
 * total subir acima da baseline registada. A migração só pode reduzir.
 *
 * Conforme cada view migra para var(--token), a baseline desce (commit
 * da nova baseline incluído na mesma entrega). Nunca sobe.
 *
 * Uso:
 *   php tools/check-design-tokens.php           verifica contra baseline
 *   php tools/check-design-tokens.php --set      regista a baseline actual
 *   php tools/check-design-tokens.php --report   lista os piores ficheiros
 */

$raiz = dirname(__DIR__);
$baseline_file = $raiz . '/tools/.design-tokens-baseline.json';

// Ficheiros isentos: a própria fonte da verdade e os CSS de sistema que
// DEFINEM a paleta. Aqui os hex são legítimos (é onde vivem).
$isentos = [
    'assets/sige-tokens.css',   // a fonte da verdade
    'assets/sige-ui.css',       // kit (paleta sgk- própria, já consolidada)
    // Contextos SEM tokens: estas saidas nao carregam sige-tokens.css, por isso
    // os literais sao legitimos (var(--token) ali ficaria por resolver).
    'includes/login-page.php',          // wp-login (injeccao via login_head)
    'includes/portal-logic.php',        // portal do aluno (shortcode front-end)
    'includes/passagem-docs-handler.php',
    'includes/email-engine.php',          // HTML de email: estilos inline literais sao obrigatorios
    'includes/email-queue-templates.php', // documento standalone (proprio :root)
    'includes/finance-devedores-pdf.php', // documento standalone de cobranca (nao carrega sige-tokens.css)
    'includes/security-mfa-totp.php',     // QR de inscricao em SVG autonomo: preto/branco literais obrigatorios para leitura fiavel (nao carrega sige-tokens.css)
    // Templates de impressao / documentos standalone: tem DOCTYPE proprio e NAO
    // carregam sige-tokens.css, por isso var(--token) ali ficaria por resolver
    // (a conversao partiria a saida). Os literais sao obrigatorios, mesma
    // categoria de finance-devedores-pdf.php.
    'includes/documents-engine.php',          // motor de recibos/extractos imprimiveis
    'admin/boletim-pdf-template.php',         // boletim A4 imprimivel
    'admin/pauta-pdf-template.php',           // pauta A4 imprimivel
    'admin/acta-oficial-template.php',        // acta oficial A4 imprimivel
    'includes/portaria-camera-safe-page.php', // pagina autonoma de camara (headers proprios)
    'assets/documents/financeiro-historico-aluno.css', // CSS de documento de historico standalone
];

/**
 * Conta valores mágicos num pedaço de CSS/HTML/PHP:
 *  - cores hex literais (#fff, #aabbcc)
 *  - border-radius com px solto (não var())
 * Exclui: linhas que já usam var(--...), comentários óbvios.
 */
function contar_magicos(string $s): array {
    // Remover comentários CSS e PHP para não contar exemplos/documentação.
    $s = preg_replace('#/\*.*?\*/#s', '', $s);
    $s = preg_replace('#//[^\n]*#', '', $s);

    $hex = 0;
    if (preg_match_all('/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b/', $s, $m)) {
        $hex = count($m[0]);
    }
    // border-radius com valor px literal (ignora os que usam var())
    $raios = 0;
    if (preg_match_all('/border-radius\s*:\s*[^;{}]*\b\d+px/i', $s, $mr)) {
        foreach ($mr[0] as $decl) {
            if (strpos($decl, 'var(') === false) $raios++;
        }
    }
    return ['hex' => $hex, 'raios' => $raios];
}

// Varrer todas as views e CSS do plugin (não os tokens/kit isentos).
$alvos = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $ext = $f->getExtension();
    if (!in_array($ext, ['php', 'css'], true)) continue;
    $rel = str_replace($raiz . '/', '', $f->getPathname());
    if (strpos($rel, 'tools/') === 0) continue;       // utilitários
    if (strpos($rel, 'docs/') === 0) continue;        // documentação
    if (strpos($rel, 'vendor/') !== false) continue;
    if (in_array($rel, $isentos, true)) continue;
    // Os 2 CSS legados (style.css / style-consolidado.css) são o grande
    // reservatório; contam para a baseline e devem descer com a migração.
    $alvos[] = $rel;
}
sort($alvos);

$por_ficheiro = [];
$total_hex = 0;
$total_raios = 0;
foreach ($alvos as $rel) {
    $s = (string)file_get_contents($raiz . '/' . $rel);
    $c = contar_magicos($s);
    if ($c['hex'] + $c['raios'] > 0) {
        $por_ficheiro[$rel] = $c;
        $total_hex += $c['hex'];
        $total_raios += $c['raios'];
    }
}
$total = $total_hex + $total_raios;

$modo = $argv[1] ?? '';

if ($modo === '--report') {
    printf("Valores mágicos: %d hex + %d raios = %d (em %d ficheiros)\n", $total_hex, $total_raios, $total, count($por_ficheiro));
    echo str_repeat('-', 64) . "\n";
    uasort($por_ficheiro, fn($a, $b) => ($b['hex'] + $b['raios']) - ($a['hex'] + $a['raios']));
    $i = 0;
    foreach ($por_ficheiro as $rel => $c) {
        printf("%5d  %s\n", $c['hex'] + $c['raios'], $rel);
        if (++$i >= 25) break;
    }
    exit(0);
}

if ($modo === '--set') {
    file_put_contents($baseline_file, json_encode([
        'total' => $total, 'hex' => $total_hex, 'raios' => $total_raios,
        'registado_em' => date('Y-m-d H:i'),
    ], JSON_PRETTY_PRINT) . "\n");
    printf("Baseline registada: %d valores mágicos (%d hex + %d raios).\n", $total, $total_hex, $total_raios);
    exit(0);
}

// Modo verificação (default): comparar com a baseline.
if (!is_file($baseline_file)) {
    fwrite(STDERR, "Baseline inexistente. Corra: php tools/check-design-tokens.php --set\n");
    exit(1);
}
$base = json_decode((string)file_get_contents($baseline_file), true);
$limite = (int)($base['total'] ?? 0);

printf("Valores mágicos actuais: %d (baseline: %d)\n", $total, $limite);
if ($total > $limite) {
    echo str_repeat('=', 64) . "\n";
    fwrite(STDERR, sprintf(
        "REGRESSÃO DE TOKENS: %d > baseline %d. Cores/raios mágicos novos não são permitidos; use var(--token) de assets/sige-tokens.css.\n",
        $total, $limite
    ));
    exit(1);
}
if ($total < $limite) {
    printf("PROGRESSO: %d abaixo da baseline. Corra --set para registar o novo mínimo.\n", $limite - $total);
}
echo "DESIGN TOKENS OK - sem regressão de valores mágicos.\n";
exit(0);
