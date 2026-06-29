<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Controlador de Consistencia Visual (placar de missao)
 *
 * O gate check-consistencia-visual.php impede REGRESSAO (a divida nunca
 * sobe). Este controlador mede o PROGRESSO: um placar unico que junta
 * TODAS as dimensoes de consistencia visual do sistema (as ja vigiadas
 * por outros gates mais as novas), calcula uma pontuacao 0-100 por
 * dimensao e global, e so declara MISSAO CUMPRIDA quando cada divida
 * chega a zero contra a meta.
 *
 * Como pontua: cada dimensao parte de um ponto de referencia (o numero
 * de valores magicos no momento em que o controlador foi calibrado, em
 * tools/.consistencia-meta.json). A pontuacao e quanto ja foi eliminado:
 *     pontos = 100 * (referencia - actual) / referencia
 * Dimensao sem divida (referencia 0) vale 100 automaticamente. O global
 * e a media ponderada pelo peso de cada divida na referencia, para que
 * limpar 4000 espacamentos pese mais do que limpar 90 z-index.
 *
 * Uso:
 *   php tools/controlador-consistencia-visual.php           mostra o placar
 *   php tools/controlador-consistencia-visual.php --calibrar  fixa o ponto de
 *                                                  referencia (estado inicial)
 *   php tools/controlador-consistencia-visual.php --gate      sai 1 enquanto
 *                                                  houver divida (>0); 0 ao zero
 *
 * O --gate so fica verde quando a consistencia for total. E o sino que
 * toca no fim da migracao: enquanto vermelho, ainda ha trabalho.
 */

$raiz = dirname(__DIR__);
$meta_file = $raiz . '/tools/.consistencia-meta.json';

$isentos = ['assets/sige-tokens.css', 'assets/sige-ui.css', 'includes/login-page.php', 'includes/portal-logic.php', 'includes/passagem-docs-handler.php', 'includes/email-engine.php', 'includes/email-queue-templates.php'];

// ---------------------------------------------------------------------------
// Recolha de ficheiros (mesma regra dos restantes gates).
// ---------------------------------------------------------------------------
function cv_alvos(string $raiz, array $isentos): array {
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
        $alvos[] = $raiz . '/' . $rel;
    }
    return $alvos;
}

function cv_sem_comentarios(string $s): string {
    $s = preg_replace('#/\*.*?\*/#s', '', $s);
    return preg_replace('#//[^\n]*#', '', $s);
}

// ---------------------------------------------------------------------------
// Medicao de cada dimensao no codigo actual.
// ---------------------------------------------------------------------------
function cv_medir(string $raiz, array $isentos): array {
    $d = [
        'cor_hex'    => 0, // -> --color-* (espelha check-design-tokens)
        'raio'       => 0, // -> --radius-*
        'fontsize'   => 0, // -> --fs-*
        'spacing'    => 0, // -> --space-*
        'boxshadow'  => 0, // -> --shadow-*
        'transition' => 0, // -> --duration-*
        'zindex'     => 0, // -> --z-*
        'inline'     => 0, // -> classe utilitaria / CSS
        'important'  => 0, // forca bruta de cascata (saude estrutural)
    ];
    foreach (cv_alvos($raiz, $isentos) as $abs) {
        $s = cv_sem_comentarios((string)file_get_contents($abs));

        if (preg_match_all('/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b/', $s, $m)) $d['cor_hex'] += count($m[0]);

        if (preg_match_all('/border-radius\s*:\s*[^;{}]*\b\d+px/i', $s, $m))
            foreach ($m[0] as $x) if (strpos($x, 'var(') === false) $d['raio']++;

        if (preg_match_all('/font-size\s*:\s*[^;{}]*\b\d+(?:\.\d+)?px/i', $s, $m))
            foreach ($m[0] as $x) if (strpos($x, 'var(') === false) $d['fontsize']++;

        if (preg_match_all('/\b(?:padding|margin|gap)\s*:\s*[^;{}]*\b\d+px/i', $s, $m))
            foreach ($m[0] as $x) if (strpos($x, 'var(') === false) $d['spacing']++;

        if (preg_match_all('/box-shadow\s*:\s*[^;{}]+/i', $s, $m))
            foreach ($m[0] as $x) { if (strpos($x, 'var(') !== false) continue;
                if (preg_match('/rgba?\(|#[0-9a-fA-F]{3,6}\b/', $x)) $d['boxshadow']++; }

        if (preg_match_all('/transition\s*:\s*[^;{}]+/i', $s, $m))
            foreach ($m[0] as $x) { if (strpos($x, 'var(') !== false) continue;
                if (preg_match('/\b\d+(?:\.\d+)?m?s\b/', $x)) $d['transition']++; }

        if (preg_match_all('/z-index\s*:\s*([^;{}]+)/i', $s, $m))
            foreach ($m[1] as $x) { if (strpos($x, 'var(') !== false) continue;
                if (preg_match('/\b\d{2,}\b/', $x)) $d['zindex']++; }

        if (preg_match_all('/\bstyle\s*=\s*["\']/i', $s, $m)) $d['inline'] += count($m[0]);

        $d['important'] += preg_match_all('/!important/i', $s);
    }
    return $d;
}

$rotulos = [
    'cor_hex'    => 'Cor          (hex literal -> --color-*)',
    'raio'       => 'Raio         (px literal  -> --radius-*)',
    'fontsize'   => 'Tipografia   (font-size px -> --fs-*)',
    'spacing'    => 'Espacamento  (padding/margin -> --space-*)',
    'boxshadow'  => 'Sombra       (box-shadow -> --shadow-*)',
    'transition' => 'Movimento    (transition -> --duration-*)',
    'zindex'     => 'Camadas      (z-index -> --z-*)',
    'inline'     => 'Estilo inline (style="..." -> classe)',
    'important'  => 'Cascata      (!important -> especificidade)',
];

$actual = cv_medir($raiz, $isentos);
$modo = $argv[1] ?? '';

if ($modo === '--calibrar') {
    $payload = $actual;
    $payload['calibrado_em'] = date('Y-m-d H:i');
    file_put_contents($meta_file, json_encode($payload, JSON_PRETTY_PRINT) . "\n");
    echo "Ponto de referencia da consistencia visual calibrado.\n";
    foreach ($rotulos as $k => $r) printf("   %-13s %d\n", $k, $actual[$k] ?? 0);
    exit(0);
}

if (!is_file($meta_file)) {
    fwrite(STDERR, "Referencia inexistente. Corra: php tools/controlador-consistencia-visual.php --calibrar\n");
    exit(1);
}
$ref = json_decode((string)file_get_contents($meta_file), true);

// Pontuacao por dimensao e global (ponderada pela divida inicial).
$linhas = [];
$soma_ref = 0; $soma_resolvido = 0; $divida_actual = 0;
foreach ($rotulos as $k => $r) {
    $r0 = (int)($ref[$k] ?? 0);
    $a  = (int)($actual[$k] ?? 0);
    $resolvido = max(0, $r0 - $a);
    $pts = $r0 > 0 ? 100.0 * $resolvido / $r0 : 100.0;
    $linhas[$k] = ['ref' => $r0, 'actual' => $a, 'pts' => $pts];
    $soma_ref += $r0;
    $soma_resolvido += min($resolvido, $r0);
    $divida_actual += $a;
}
$global = $soma_ref > 0 ? 100.0 * $soma_resolvido / $soma_ref : 100.0;

function cv_barra(float $pct): string {
    $n = (int)round($pct / 5);
    return '[' . str_repeat('#', $n) . str_repeat('.', 20 - $n) . ']';
}

if ($modo === '--gate') {
    // Sino de fim de missao: verde apenas quando divida = 0.
    if ($divida_actual === 0) {
        echo "MISSAO CUMPRIDA - consistencia visual total (100/100, zero divida).\n";
        exit(0);
    }
    fwrite(STDERR, sprintf(
        "Consistencia visual em %.1f/100; ainda %d primitivos por migrar. Gate de sucesso permanece vermelho.\n",
        $global, $divida_actual
    ));
    exit(1);
}

// Placar (default).
echo "==================================================================\n";
echo " CONTROLADOR DE CONSISTENCIA VISUAL - SIGE SoftGenial\n";
printf(" Referencia calibrada em: %s\n", $ref['calibrado_em'] ?? 'n/d');
echo "==================================================================\n";
printf(" %-38s %s %6s\n", 'DIMENSAO', str_pad('PROGRESSO', 22), 'SCORE');
echo str_repeat('-', 66) . "\n";
foreach ($linhas as $k => $v) {
    printf(" %-38s %s %5.1f  (%d->%d)\n",
        $rotulos[$k], cv_barra($v['pts']), $v['pts'], $v['ref'], $v['actual']);
}
echo str_repeat('-', 66) . "\n";
printf(" %-38s %s %5.1f\n", 'GLOBAL (ponderado pela divida inicial)', cv_barra($global), $global);
printf(" Divida total restante: %d primitivos magicos\n", $divida_actual);
echo "==================================================================\n";
if ($divida_actual === 0) {
    echo " MISSAO CUMPRIDA: consistencia visual total. O sino tocou.\n";
} else {
    printf(" Faltam %d para 100/100. Proximo alvo sugerido: %s\n",
        $divida_actual,
        (function () use ($linhas, $rotulos) {
            $pior = null; $max = -1;
            foreach ($linhas as $k => $v) {
                $resta = $v['actual'];
                if ($v['pts'] < 100 && $resta > $max) { $max = $resta; $pior = $k; }
            }
            return $pior ? $rotulos[$pior] : 'n/d';
        })()
    );
}
exit(0);
