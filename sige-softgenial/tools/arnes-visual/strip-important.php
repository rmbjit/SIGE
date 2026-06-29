<?php
// Acesso restrito: utilitario de linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Codemod de remocao de !important (parceiro do arnes visual)
 *
 * Remove !important dos blocos <style> de VIEW de um ficheiro, deixando intactos
 * os blocos de impressao standalone (window.print, que montam o seu proprio
 * documento e nao carregam sige-tokens.css). Pode limitar-se a um ambito por
 * substring de selector. Cria sempre backup .important-bak antes de aplicar.
 *
 * A SEGURANCA vem da VERIFICACAO VISUAL do arnes (screenshot antes/depois), nao
 * deste codemod sozinho. Fluxo:
 *   1) arnes captura "antes" -> 2) este codemod --apply -> 3) arnes captura
 *   "depois" e compara -> 4) se diferenca de pixels: --restore; senao, fica.
 *
 * Uso:
 *   php strip-important.php <ficheiro> [--scope=".sige-rh"] [--dry]
 *   php strip-important.php <ficheiro> --apply [--scope=...]
 *   php strip-important.php <ficheiro> --restore
 */

$args = array_slice($argv, 1);
$file = null; $scope = null; $apply = false; $restore = false; $dry = false;
foreach ($args as $a) {
    if ($a === '--apply') $apply = true;
    elseif ($a === '--restore') $restore = true;
    elseif ($a === '--dry') $dry = true;
    elseif (strpos($a, '--scope=') === 0) $scope = substr($a, 8);
    elseif ($a !== '' && $a[0] !== '-') $file = $a;
}
if (!$file || !is_file($file)) {
    fwrite(STDERR, "Uso: php strip-important.php <ficheiro> [--scope=SEL] [--apply|--dry|--restore]\n");
    exit(2);
}
$bak = $file . '.important-bak';

if ($restore) {
    if (!is_file($bak)) { fwrite(STDERR, "Sem backup para restaurar ($bak).\n"); exit(2); }
    copy($bak, $file); unlink($bak);
    echo "Restaurado de backup (alteracao revertida): $file\n"; exit(0);
}

$src = (string)file_get_contents($file);

/** True se o bloco <style> for um documento de impressao standalone (a excluir). */
function arnes_e_impressao(string $s, int $start, int $end): bool {
    $after  = strtolower(substr($s, $end, 150));
    $before = strtolower(substr($s, max(0, $start - 400), min(400, $start)));
    return strpos($after, '</head>') !== false || strpos($after, '<body') !== false
        || strpos($before, '<!doctype') !== false || strpos($before, '<html') !== false
        || strpos($before, '<head>') !== false;
}

$out = preg_replace_callback('/<style([^>]*)>(.*?)<\/style>/is', function ($m) use ($scope) {
    // Offsets reais no documento (PREG_OFFSET_CAPTURE nao usado; recalcular pela tag).
    static $cursor = 0;
    return $m[0]; // placeholder; substituido abaixo por versao com offsets
}, $src);

// Implementacao com offsets para deteccao fiavel de impressao:
$out = '';
$last = 0;
if (preg_match_all('/<style([^>]*)>(.*?)<\/style>/is', $src, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
    foreach ($mm as $m) {
        $full = $m[0][0]; $start = $m[0][1]; $end = $start + strlen($full);
        $attrs = $m[1][0]; $css = $m[2][0];
        $out .= substr($src, $last, $start - $last);
        if (arnes_e_impressao($src, $start, $end)) {
            $out .= $full; // impressao: intacto
        } elseif ($scope === null) {
            $out .= '<style' . $attrs . '>' . str_replace('!important', '', $css) . '</style>';
        } else {
            $css2 = preg_replace_callback('/([^{}]*)\{([^{}]*)\}/s', function ($r) use ($scope) {
                if (strpos($r[1], $scope) !== false) {
                    return $r[1] . '{' . str_replace('!important', '', $r[2]) . '}';
                }
                return $r[0];
            }, $css);
            $out .= '<style' . $attrs . '>' . $css2 . '</style>';
        }
        $last = $end;
    }
    $out .= substr($src, $last);
} else {
    $out = $src;
}

$removidos = substr_count($src, '!important') - substr_count($out, '!important');

if ($dry || !$apply) {
    printf("[dry] %s%s: removeria %d !important (blocos de impressao preservados).\n",
        $file, $scope ? " ambito='$scope'" : '', $removidos);
    if (!$apply) exit(0);
}
if (!is_file($bak)) copy($file, $bak);
file_put_contents($file, $out);
printf("Aplicado: %d !important removidos de %s%s. Backup: %s\n",
    $removidos, $file, $scope ? " (ambito '$scope')" : '', $bak);
exit(0);
