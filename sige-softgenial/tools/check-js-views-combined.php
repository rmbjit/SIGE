<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Verificador combinado de JS embebido nas views.
 *
 * Mantem a cobertura do gate de JavaScript sem abrir um processo Node por
 * bloco. Quando nenhum filtro e informado, a pasta admin e dividida em lotes
 * estaveis e cada lote e validado com um unico node --check.
 */

$raiz = dirname(__DIR__);
$filtro = $argv[1] ?? null;

function sige_js_views_admin_filters(string $raiz): array {
    $filters = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz . '/admin', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') continue;
        $rel = str_replace($raiz . '/', '', $f->getPathname());
        if (substr($rel, -9) === 'index.php') continue;
        $parts = explode('/', $rel);
        if (count($parts) >= 3) $filters['admin/' . $parts[1]] = true;
        else $filters[$rel] = true;
    }
    $filters = array_keys($filters);
    sort($filters);
    return $filters;
}

function sige_js_views_neutralize_php(string $js): string {
    if (!preg_match_all('/<\?(?:php\b|=)?(.*?)\?' . '>/s', $js, $tags, PREG_OFFSET_CAPTURE)) {
        return $js;
    }
    $neutro = '';
    $cursor = 0;
    foreach ($tags[0] as $k => $tag) {
        $antes = substr($js, $cursor, $tag[1] - $cursor);
        $neutro .= $antes;
        $cursor = $tag[1] + strlen($tag[0]);
        $inner = $tags[1][$k][0];
        $abre = substr($tag[0], 0, 3);
        $produz = ($abre === '<?=') || preg_match('/\b(?:echo|print)\b/', $inner);
        if (!$produz) continue;
        $tras = rtrim($neutro);
        $ult = $tras === '' ? '' : substr($tras, -1);
        $expressao = ($ult !== '' && strpos('=([{,:?+-*/%&|!<>', $ult) !== false)
            || preg_match('/\breturn\s*$/', $tras);
        $neutro .= $expressao ? '0' : '';
    }
    $neutro .= substr($js, $cursor);
    return $neutro;
}

function sige_js_views_collect(string $raiz, ?string $filtro): array {
    $views = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz . '/admin', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') continue;
        $rel = str_replace($raiz . '/', '', $f->getPathname());
        if (substr($rel, -9) === 'index.php') continue;
        if ($filtro !== null && strpos($rel, $filtro) === false) continue;
        $views[] = $rel;
    }
    sort($views);
    return $views;
}

function sige_js_views_check_filter(string $raiz, ?string $filtro): array {
    $views = sige_js_views_collect($raiz, $filtro);
    $blocks = [];
    $blocos_total = 0;
    foreach ($views as $v) {
        $s = (string)file_get_contents($raiz . '/' . $v);
        // Normaliza as tags <script> com nonce CSP para extraccao (o nonce e PHP
        // no proprio tag); nao altera o ficheiro, so a copia para o node -c.
        $s = str_replace('<script <?php echo sige_csp_script_attr(); ?>>', '<script>', $s);
        if (!preg_match_all('#<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>#si', $s, $m)) continue;
        foreach ($m[1] as $i => $js) {
            if (trim($js) === '') continue;
            $blocos_total++;
            $safeName = str_replace('*/', '* /', $v . ' bloco ' . ($i + 1));
            $blocks[] = "\n/* SIGE-JS-CHECK: {$safeName} */\n{\n" . sige_js_views_neutralize_php($js) . "\n}\n";
        }
    }

    $tmp = tempnam(sys_get_temp_dir(), 'sige-js-combined-');
    file_put_contents($tmp, implode("\n", $blocks));
    $out = [];
    $rc = 0;
    exec('node --check ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
    @unlink($tmp);
    return [$rc, count($views), $blocos_total, $out];
}

$totalViews = 0;
$totalBlocks = 0;
$filters = $filtro !== null ? [$filtro] : sige_js_views_admin_filters($raiz);
foreach ($filters as $filter) {
    [$rc, $viewsCount, $blocksCount, $out] = sige_js_views_check_filter($raiz, $filter);
    $totalViews += $viewsCount;
    $totalBlocks += $blocksCount;
    if ($filtro === null) {
        printf("[JS %s] Views varridas: %d | blocos <script> verificados: %d\n", $filter, $viewsCount, $blocksCount);
    }
    if ($rc !== 0) {
        echo str_repeat('=', 60) . "\n";
        echo 'Filtro: ' . $filter . "\n";
        foreach (array_slice($out, 0, 12) as $line) echo $line . "\n";
        echo str_repeat('=', 60) . "\n";
        fwrite(STDERR, 'JS-VIEWS COMBINED FALHOU: erro de sintaxe no JavaScript embebido.' . "\n");
        exit(1);
    }
    unset($out);
    if (function_exists('gc_collect_cycles')) gc_collect_cycles();
}

printf("Views varridas: %d | blocos <script> verificados: %d | lotes: %d\n", $totalViews, $totalBlocks, count($filters));
echo "JS-VIEWS COMBINED OK - sintaxe limpa em todos os blocos por lotes isolados.\n";
exit(0);
