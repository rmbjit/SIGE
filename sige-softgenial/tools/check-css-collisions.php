<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Gate de colisões CSS (7º gate)
 *
 * ORIGEM (12 Jun 2026): o UI Kit foi criado no prefixo sg- sem verificar
 * que o style.css JÁ ERA DONO desse namespace (.sg-modal nasce com
 * opacity:0 à espera de .active; .sg-btn tem 13 definições com
 * !important). Resultado em teste.softgenial: modal do horário invisível
 * sobre overlay visível. Este gate torna essa classe de erro impossível:
 *
 *   1. extrai as CLASSES e VARIÁVEIS definidas nos CSS "meus"
 *      (assets/sige-ui.css + assets/views/*.css);
 *   2. extrai as definidas nos CSS "da casa"
 *      (assets/style.css + assets/mobile-tablet-ux.css);
 *   3. FALHA se houver qualquer intersecção;
 *   4. política extra: o kit (sige-ui.css) só pode definir classes
 *      sgk-* e variáveis --sgk-* (namespace reservado).
 *
 * Uso: php tools/check-css-collisions.php
 * Sai com código 1 em qualquer colisão: pertence aos gates de release.
 */

$raiz = dirname(__DIR__);

function css_sem_comentarios(string $s): string {
    return (string)preg_replace('#/\*.*?\*/#s', '', $s);
}

/** Classes definidas em selectores (antes de cada '{'). */
function classes_definidas(string $css): array {
    $css = css_sem_comentarios($css);
    $classes = [];
    foreach (explode('}', $css) as $bloco) {
        $p = strpos($bloco, '{');
        if ($p === false) continue;
        $selector = substr($bloco, 0, $p);
        if (preg_match_all('/\.([A-Za-z_][\w-]*)/', $selector, $m)) {
            foreach ($m[1] as $c) $classes[$c] = true;
        }
    }
    return array_keys($classes);
}

/** Variáveis CSS declaradas (--nome: valor). */
function variaveis_definidas(string $css): array {
    $css = css_sem_comentarios($css);
    if (!preg_match_all('/--([A-Za-z_][\w-]*)\s*:/', $css, $m)) return [];
    return array_values(array_unique($m[1]));
}

$meus = array_merge(
    [$raiz . '/assets/sige-ui.css'],
    glob($raiz . '/assets/views/*.css') ?: []
);

// v12.15.7: Alunos e Matriculas usa uma camada de override por view
// deliberadamente escopada a body.sige-view-alunos_lista. O contrato desse
// ficheiro e validado por check-alunos-design-scope-v12-15-7.php; por isso
// nao deve falhar neste gate historico, que foi criado para impedir colisao
// acidental de namespaces em componentes reutilizaveis.
$view_override_scoped = [
    realpath($raiz . '/assets/views/alunos-design-pro.css') ?: $raiz . '/assets/views/alunos-design-pro.css',
    // v12.15.12: Financeiro Core usa override por view, escopado por
    // body.sige-view-financeiro-* e validado por check-financeiro-core-design-scope-v12-15-12.php.
    realpath($raiz . '/assets/views/financeiro-core-design-pro.css') ?: $raiz . '/assets/views/financeiro-core-design-pro.css',
];
$meus = array_values(array_filter($meus, function ($f) use ($view_override_scoped) {
    $r = realpath($f) ?: $f;
    return !in_array($r, $view_override_scoped, true);
}));
$casa = array_filter([
    $raiz . '/assets/style.css',
    $raiz . '/assets/mobile-tablet-ux.css',
], 'is_file');

$casa_classes = [];
$casa_vars = [];
foreach ($casa as $f) {
    $s = (string)file_get_contents($f);
    foreach (classes_definidas($s) as $c) $casa_classes[$c][] = basename($f);
    foreach (variaveis_definidas($s) as $v) $casa_vars[$v][] = basename($f);
}

$erros = [];
$tot_classes = $tot_vars = 0;

foreach ($meus as $f) {
    if (!is_file($f)) continue;
    $s = (string)file_get_contents($f);
    $nome = basename($f);
    $cls = classes_definidas($s);
    $vrs = variaveis_definidas($s);
    $tot_classes += count($cls);
    $tot_vars += count($vrs);

    foreach ($cls as $c) {
        if (isset($casa_classes[$c])) {
            $erros[] = "COLISÃO classe .{$c}: definida em {$nome} E em " . implode('+', $casa_classes[$c]);
        }
    }
    foreach ($vrs as $v) {
        if (isset($casa_vars[$v])) {
            $erros[] = "COLISÃO variável --{$v}: definida em {$nome} E em " . implode('+', $casa_vars[$v]);
        }
    }

    // Política do kit: namespace reservado sgk-
    if ($nome === 'sige-ui.css') {
        foreach ($cls as $c) {
            if (strpos($c, 'sgk-') !== 0) {
                $erros[] = "POLÍTICA kit: classe .{$c} fora do namespace sgk-";
            }
        }
        foreach ($vrs as $v) {
            if (strpos($v, 'sgk-') !== 0) {
                $erros[] = "POLÍTICA kit: variável --{$v} fora do namespace --sgk-";
            }
        }
    }
}

printf(
    "CSS meus: %d ficheiro(s) | classes definidas: %d | variáveis: %d | casa: %d ficheiro(s)\n",
    count($meus), $tot_classes, $tot_vars, count($casa)
);
if ($erros) {
    echo str_repeat('=', 64) . "\n";
    foreach ($erros as $e) echo "ERRO  {$e}\n";
    echo str_repeat('=', 64) . "\n";
    fwrite(STDERR, 'COLISÕES CSS: ' . count($erros) . " problema(s).\n");
    exit(1);
}
echo "COLISÕES CSS OK - namespaces limpos (kit em sgk-, zero intersecções com a casa).\n";
exit(0);
