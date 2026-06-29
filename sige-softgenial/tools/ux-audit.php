<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Auditor UX (medição objectiva de inconsistências)
 *
 * Varre todas as views de admin/ e quantifica, por ficheiro:
 *   estilos inline, alert()/confirm() nativos, botões sem classe,
 *   tabelas sem estado vazio, fugas AO90/BR nos rótulos e terminologia
 *   divergente (Salvar vs Guardar, Excluir vs Remover, Buscar vs
 *   Pesquisar). O score heurístico ordena o programa de correcção;
 *   correr antes e depois de cada sprint UX prova o progresso.
 *
 * Uso:
 *   php tools/ux-audit.php             ranking em texto
 *   php tools/ux-audit.php --md        ranking em tabela markdown
 *   php tools/ux-audit.php --view X    métricas de uma view específica
 */

$raiz = dirname(__DIR__);
$alvo_md = in_array('--md', $argv, true);
$so_view = null;
$pos = array_search('--view', $argv, true);
if ($pos !== false && isset($argv[$pos + 1])) $so_view = $argv[$pos + 1];

$views = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz . '/admin', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->getExtension() !== 'php') continue;
    $rel = str_replace($raiz . '/', '', $f->getPathname());
    if (substr($rel, -9) === 'index.php' || strpos($rel, 'template') !== false) continue;
    $views[] = $rel;
}
sort($views);

function ux_medir(string $caminho): array {
    $s = (string)file_get_contents($caminho);
    $m = [];
    $m['linhas'] = substr_count($s, "\n") + 1;
    // v2.7: distingue inline de ESTILO real de injecção de CUSTOM PROPERTIES.
    // style="--x:valor" é o padrão recomendado para passar valores dinâmicos
    // do PHP ao stylesheet (cor de KPI, percentagem de barra): NÃO é dívida.
    // Conta apenas style= que define propriedades CSS normais.
    $m['inline_style'] = 0;
    if (preg_match_all('/style="([^"]*)"/', $s, $__ms)) {
        foreach ($__ms[1] as $__decl) {
            // Remove blocos PHP de interpolação para avaliar a forma da declaração.
            $__d = preg_replace('/<\?(?:php|=)?.*?\?' . '>/s', 'X', $__decl);
            // Se TODAS as declarações são custom properties (--algo:...), não conta.
            $__limpo = trim($__d);
            if ($__limpo === '') continue;
            $__so_vars = preg_match('/^\s*(--[\w-]+\s*:[^;]*;?\s*)+$/', $__limpo) === 1;
            // style cujo conteúdo é INTEIRAMENTE uma expressão PHP (ex.: um
            // conic-gradient gerado em runtime) é estilo dinâmico legítimo,
            // não inline escrito à mão: a forma original (antes de remover o
            // PHP) é uma única tag de abertura-fecho PHP, sem estilo escrito a mao.
            $__so_php = preg_match('/^\s*<\?(?:php|=)?.*?\?' . '>\s*$/s', trim($__decl)) === 1;
            if (!$__so_vars && !$__so_php) { $m['inline_style']++; }
        }
    }
    $m['alert'] = preg_match_all('/(?<![\w.])alert\(/', $s);
    $m['confirm'] = preg_match_all('/(?<![\w.])confirm\(/', $s);
    $m['prompt'] = preg_match_all('/(?<![\\w.])prompt\\(/', $s);
    // v2.4: o fecho de tag PHP dentro de atributos enganava o parser; medir sobre HTML puro.
    $html_puro = preg_replace('/<\?(?:php|=)?.*?\?' . '>/s', '', $s);
    $m['btn_sem_classe'] = preg_match_all('/<button\b(?![^>]*\bclass=)[^>]*>/s', $html_puro);
    $m['tabelas'] = preg_match_all('/<table\b/', $s);
    $m['vazios'] = preg_match_all('/Nenhum|Nenhuma|Sem registos|Ainda sem|Ainda não|Sem dados|Sem resultados|Sem lançamentos|está vazi|[\w-]*-empty/u', $s);
    // v2.4: comentários de código não são interface; termos medem-se sem eles.
    $s_termos = preg_replace('#/\*.*?\*/#s', '', $s);
    $s_termos = preg_replace('#//[^\n]*#', '', $s_termos);
    $m['t_salvar'] = preg_match_all('/\bSalvar\b/', $s_termos);
    $m['t_guardar'] = preg_match_all('/\bGuardar\b/', $s_termos);
    $m['t_excluir'] = preg_match_all('/\bExcluir\b/u', $s_termos) + preg_match_all('/\bDeletar\b/u', $s);
    $m['t_apagar'] = preg_match_all('/\bApagar\b/u', $s_termos);
    $m['t_remover'] = preg_match_all('/\bRemover\b/u', $s_termos);
    $m['t_eliminar'] = preg_match_all('/\bEliminar\b/u', $s);
    $m['t_buscar'] = preg_match_all('/\bBuscar\b/u', $s_termos);
    $m['t_pesquisar'] = preg_match_all('/\bPesquisar\b/u', $s_termos) + preg_match_all('/\bProcurar\b/u', $s);
    // v2: AO90 conta apenas contexto visível ao utilizador. Linhas com SQL
    // ou $wpdb são excluídas (ativo=1 é coluna de schema, não rótulo) e
    // identificadores ($var, nome_de_funcao) não contam.
    $linhas_visiveis = implode("\n", array_filter(explode("\n", $s), function ($l) {
        return !preg_match('/\$wpdb|\bSELECT\s|\bWHERE\s|\bFROM\s|\bJOIN\s|\bIN\s*\(|->prepare\(|->get_(row|results|var|col)\(|[\'\"][a-z_]*ativ[oa]s?[\'\"]\s*=>|name=[\'\"][a-z_]*ativ|in_array\s*\(|value=\"|selected\s*\(|[=!]==?\s*[\'\"][a-z_]*ativ|\bIS\s+NULL|column_exists|\$[A-Za-z_][\w]*(cadastr|ativ)[\w]*|\bAND\s+ativ[oa]?\s*=|\bativ[oa]?\s*=\s*1\b/i', $l);
    }));
    $m['ao90'] = preg_match_all('/(?<![\w$_])[Uu]su[áa]rio(?![\w_])/u', $linhas_visiveis)
               + preg_match_all('/(?<![\w$_>])[Aa]tiv[oa]s?\b(?![\w_(=])/u', $linhas_visiveis)
               + preg_match_all('/(?<![\w$_])[Cc]adastr/u', $linhas_visiveis)
               + preg_match_all('/>\s*Login\s*</u', $linhas_visiveis);
    $m['score'] = round(
        $m['alert'] * 4 + $m['confirm'] * 4 + $m['prompt'] * 4 + $m['btn_sem_classe'] * 2 + $m['ao90'] * 3
        + $m['t_salvar'] * 3 + $m['t_excluir'] * 3 + $m['t_buscar'] * 2
        + ($m['inline_style'] / 10) + max(0, $m['tabelas'] - $m['vazios']) * 3,
        1
    );
    return $m;
}

$linhas = [];
$tot = ['score' => 0.0, 'inline_style' => 0, 'alert' => 0, 'confirm' => 0, 'prompt' => 0, 'btn_sem_classe' => 0, 'ao90' => 0];
foreach ($views as $v) {
    if ($so_view !== null && strpos($v, $so_view) === false) continue;
    $m = ux_medir($raiz . '/' . $v);
    $linhas[] = ['view' => $v, 'm' => $m];
    $tot['score'] += $m['score'];
    foreach (['inline_style', 'alert', 'confirm', 'prompt', 'btn_sem_classe', 'ao90'] as $k) $tot[$k] += $m[$k];
}
usort($linhas, fn($a, $b) => $b['m']['score'] <=> $a['m']['score']);

if ($alvo_md) {
    echo "| Score | View | Linhas | Inline | alert | confirm | btn s/cl | AO90 |\n";
    echo "|---:|---|---:|---:|---:|---:|---:|---:|\n";
    foreach ($linhas as $L) {
        $m = $L['m'];
        printf("| %.1f | %s | %d | %d | %d | %d | %d | %d |\n",
            $m['score'], $L['view'], $m['linhas'], $m['inline_style'], $m['alert'], $m['confirm'], $m['btn_sem_classe'], $m['ao90']);
    }
    printf("| **%.1f** | **TOTAL (%d views)** | | **%d** | **%d** | **%d** | **%d** | **%d** |\n",
        $tot['score'], count($linhas), $tot['inline_style'], $tot['alert'], $tot['confirm'], $tot['btn_sem_classe'], $tot['ao90']);
    exit(0);
}

printf("%6s  %-48s %5s %5s %4s %4s %4s %5s\n", 'SCORE', 'VIEW', 'lin', 'inl', 'alr', 'cnf', 'b/s', 'AO90');
foreach ($linhas as $L) {
    $m = $L['m'];
    printf("%6.1f  %-48s %5d %5d %4d %4d %4d %5d\n",
        $m['score'], $L['view'], $m['linhas'], $m['inline_style'], $m['alert'], $m['confirm'], $m['btn_sem_classe'], $m['ao90']);
}
printf("\nTOTAL: score=%.1f | inline=%d | alert=%d | confirm=%d | prompt=%d | btn_sem_classe=%d | ao90=%d | views=%d\n",
    $tot['score'], $tot['inline_style'], $tot['alert'], $tot['confirm'], $tot['prompt'], $tot['btn_sem_classe'], $tot['ao90'], count($linhas));
exit(0);
