<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
/**
 * Gate Fase 10 / v12.14.1 - CSP zero-inline efectivo.
 *
 * A partir desta versão a catraca deixa de aceitar permissões inline no CSP.
 * O legado style=/on*= pode ainda existir no código fonte histórico, mas é
 * removido da resposta HTML por includes/csp-zero-inline.php antes de chegar ao
 * browser e hidratado por assets/sige-ui.js externo, sem eval/Function.
 */
const SIGE_INLINE_ONCLICK_MAX = 17;
const SIGE_INLINE_STYLE_MAX   = 2051;
const SIGE_INLINE_SCRIPT_MAX  = 7;

$root = getenv('SIGE_ROOT') ?: dirname(__DIR__);
$read = static function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return is_file($p) ? (string) file_get_contents($p) : '';
};

$onclick = $style = $script = 0;
foreach (['admin', 'includes'] as $dir) {
    $base = $root . '/' . $dir;
    if (!is_dir($base)) continue;
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $f) {
        $path = str_replace('\\', '/', $f->getPathname());
        if (strpos($path, '/assets/vendor/') !== false) continue;
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') continue;
        $src = (string) file_get_contents($path);
        $onclick += substr_count($src, 'onclick=');
        $style   += substr_count($src, 'style="');
        $script  += substr_count($src, '<script>');
    }
}

$fails = [];
if ($onclick > SIGE_INLINE_ONCLICK_MAX) $fails[] = "onclick= subiu: $onclick > baseline " . SIGE_INLINE_ONCLICK_MAX;
if ($style > SIGE_INLINE_STYLE_MAX)     $fails[] = "style= subiu: $style > baseline " . SIGE_INLINE_STYLE_MAX;
if ($script > SIGE_INLINE_SCRIPT_MAX)   $fails[] = "blocos <script> subiram: $script > baseline " . SIGE_INLINE_SCRIPT_MAX;

$guard = $read('includes/csp-zero-inline.php');
foreach ([
    'function sige_csp_zero_inline_policy' => 'falta função canónica da política zero-inline',
    "script-src-attr 'none'" => 'CSP não bloqueia handlers inline por atributo',
    "style-src-attr 'none'" => 'CSP não bloqueia estilos inline por atributo',
    "style-src 'self'" => 'CSP sem style-src self',
    "script-src 'self'" => 'CSP sem script-src self',
    'sige_csp_zero_inline_sanitise_html' => 'falta sanitizador HTML zero-inline',
    'data-sige-style=$1' => 'sanitizador não converte style= em data-sige-style',
    'data-sige-on-' => 'sanitizador não converte on*= em data-sige-on-*',
    "header('Content-Security-Policy: ' . sige_csp_zero_inline_policy()" => 'guard não envia CSP enforcement zero-inline',
] as $needle => $label) {
    if (strpos($guard, $needle) === false) $fails[] = $label;
}
if (strpos($guard, 'unsafe-inline') !== false) {
    $fails[] = 'csp-zero-inline.php contém unsafe-inline';
}

$prod_files = [];
foreach (['admin','includes','assets'] as $dir) {
    $base = $root . '/' . $dir;
    if (!is_dir($base)) continue;
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $f) {
        if (!$f->isFile()) continue;
        $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['php','js','css'], true)) continue;
        $src = (string) file_get_contents($f->getPathname());
        if (strpos($src, 'unsafe-inline') !== false) {
            $prod_files[] = str_replace($root . '/', '', $f->getPathname());
        }
    }
}
if ($prod_files) $fails[] = 'ficheiros de produção ainda contêm unsafe-inline: ' . implode(', ', $prod_files);

$ui = $read('assets/sige-ui.js');
foreach ([
    'CSP Zero-Inline Hydrator' => 'falta hidratador CSP zero-inline no JS externo',
    'data-sige-style' => 'JS não hidrata data-sige-style',
    'data-sige-on-' => 'JS não hidrata data-sige-on-*',
    'MutationObserver' => 'JS não cobre DOM injectado dinamicamente',
    'resolverAcao' => 'JS não resolve funções globais/dotted sem eval',
] as $needle => $label) {
    if (strpos($ui, $needle) === false) $fails[] = $label;
}
foreach (['eval(', 'new Function', 'Function('] as $bad) {
    if (strpos($ui, $bad) !== false) $fails[] = "JS zero-inline usa API proibida: $bad";
}

foreach (['includes/documents-engine.php','includes/pauta-pdf-handler.php','includes/boletim-pdf-handler.php','includes/portaria-camera-safe-page.php','admin/jardim/jardim_boletim-view.php','includes/financeiro-historico-aluno-pro.php'] as $rel) {
    $src = $read($rel);
    if (strpos($src, 'sige_csp_zero_inline_policy') === false) {
        $fails[] = "página autónoma sem política zero-inline: $rel";
    }
}

if ($fails) {
    echo "CHECK INLINE FRONT-END / CSP ZERO-INLINE FALHOU:\n";
    foreach ($fails as $f) echo "  - $f\n";
    exit(1);
}
echo "CHECK INLINE FRONT-END OK - CSP zero-inline activo; onclick=$onclick, style=$style, <script>=$script; guard central e hidratador externo verificados.\n";
