<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Verificador de JS embebido nas views
 *
 * PONTO CEGO FECHADO (12 Jun 2026): o lint PHP valida o PHP das views,
 * mas o JavaScript dentro de <script>...</script> (misturado com echos
 * PHP) nunca era verificado. Um erro de sintaxe aí mata TODO o script
 * do ecrã em silêncio. Este utilitário:
 *   1. extrai cada bloco <script> de cada view;
 *   2. neutraliza o PHP embebido (echo em expressão vira 0; tags de
 *      controlo viram vazio);
 *   3. valida a sintaxe com `node --check`.
 *
 * Uso:
 *   php tools/check-js-views.php            todas as views
 *   php tools/check-js-views.php caminho    apenas as que contenham 'caminho'
 *
 * Sai com código 1 se houver QUALQUER erro: pertence aos gates.
 */

$raiz = dirname(__DIR__);
$filtro = $argv[1] ?? null;

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

$tmp = sys_get_temp_dir() . '/sige-js-check';
if (!is_dir($tmp)) mkdir($tmp, 0777, true);

$erros = [];
$blocos_total = 0;

foreach ($views as $v) {
    $s = (string)file_get_contents($raiz . '/' . $v);
    // Normaliza as tags <script> com nonce CSP para extraccao (o nonce e PHP no
    // proprio tag); nao altera o ficheiro, so a copia em memoria para o node -c.
    $s = str_replace('<script <?php echo sige_csp_script_attr(); ?>>', '<script>', $s);
    if (!preg_match_all('#<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>#si', $s, $m)) continue;
    foreach ($m[1] as $i => $js) {
        if (trim($js) === '') continue;
        $blocos_total++;
        // Neutralizar PHP embebido:
        //  - echo/print em contexto de EXPRESSÃO vira 0
        //  - blocos de controlo (if/endif/foreach...) viram vazio
        // Neutralizador sensível ao CONTEXTO: cada tag PHP é avaliada pelo
        // carácter útil que a antecede. Depois de operador/abertura
        // (= ( [ { , : ? + - * / % & | ! < > ou 'return'), o output vira 0
        // (lugar de expressão); caso contrário vira vazio (fragmento).
        if (!preg_match_all('/<\?(?:php\b|=)?(.*?)\?' . '>/s', $js, $tags, PREG_OFFSET_CAPTURE)) {
            $neutro = $js;
        } else {
            $neutro = '';
            $cursor = 0;
            foreach ($tags[0] as $k => $tag) {
                $antes = substr($js, $cursor, $tag[1] - $cursor);
                $neutro .= $antes;
                $cursor = $tag[1] + strlen($tag[0]);
                $inner = $tags[1][$k][0];
                $abre = substr($tag[0], 0, 3);
                $produz = ($abre === '<?=') || preg_match('/\b(?:echo|print)\b/', $inner);
                if (!$produz) continue; // bloco de controlo: some
                $tras = rtrim($neutro);
                $ult = $tras === '' ? '' : substr($tras, -1);
                $expressao = ($ult !== '' && strpos('=([{,:?+-*/%&|!<>', $ult) !== false)
                    || preg_match('/\breturn\s*$/', $tras);
                $neutro .= $expressao ? '0' : '';
            }
            $neutro .= substr($js, $cursor);
        }
        $ficheiro = $tmp . '/' . preg_replace('/[^a-z0-9]+/i', '_', $v) . "_b{$i}.js";
        file_put_contents($ficheiro, $neutro);
        $out = [];
        $rc = 0;
        for ($tentativa = 0; $tentativa < 3; $tentativa++) {
            $out = [];
            $rc = 0;
            exec('node --check ' . escapeshellarg($ficheiro) . ' 2>&1', $out, $rc);
            if ($rc === 0 || trim(implode('', $out)) !== '') { break; }
            // Em ambientes CI restritivos, o processo node pode ser morto sem stderr.
            // Repetir evita falso vermelho sem mascarar erros reais de sintaxe.
            usleep(200000);
        }
        if ($rc !== 0) {
            $erros[] = ['view' => $v, 'bloco' => $i + 1, 'msg' => implode("\n", array_slice($out, 0, 3))];
        }
    }
}

printf("Views varridas: %d | blocos <script> verificados: %d\n", count($views), $blocos_total);
if ($erros) {
    echo str_repeat('=', 60) . "\n";
    foreach ($erros as $e) {
        echo "ERRO  {$e['view']} (bloco {$e['bloco']}):\n";
        foreach (explode("\n", $e['msg']) as $l) echo "      {$l}\n";
    }
    echo str_repeat('=', 60) . "\n";
    fwrite(STDERR, 'JS-VIEWS FALHOU: ' . count($erros) . " bloco(s) com erro de sintaxe.\n");
    exit(1);
}
echo "JS-VIEWS OK - sintaxe limpa em todos os blocos.\n";
exit(0);
