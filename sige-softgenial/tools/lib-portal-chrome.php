<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Biblioteca do split chrome vs views do portal.
 *
 * Fonte unica da transformacao: a folha enxuta do portal (style-portal-chrome.css)
 * e GERADA a partir do assets/style.css, removendo os modulos de view que o portal
 * nunca renderiza. O gerador (tools/gen-portal-chrome.php) e o gate
 * (tools/check-portal-chrome-split-v12-15-15.php) usam ambos esta funcao, por isso
 * o ficheiro comprometido tem de ser, byte-a-byte, igual ao que esta funcao produz.
 * Se divergirem, ha drift e o gate fica vermelho.
 *
 * O style.css NAO e editado: as seccoes de view sao localizadas pelos seus titulos.
 */
if (!defined('SIGE_PORTAL_CHROME_HEADER')) {
    // Cabecalho canonico, partilhado com o gerador, num ficheiro de texto unico,
    // para que o conteudo seja byte-a-byte igual independentemente de quem gera.
    $sige_pc_header_file = __DIR__ . '/portal-chrome-header.txt';
    $sige_pc_header = is_file($sige_pc_header_file) ? (string) file_get_contents($sige_pc_header_file) : '';
    define('SIGE_PORTAL_CHROME_HEADER', $sige_pc_header);
}

if (!function_exists('sige_portal_chrome_view_anchors')) {
    /**
     * Pares [inicio_da_view, inicio_da_seccao_de_chrome_seguinte].
     * O segundo elemento a null significa "ate ao fim do ficheiro".
     * Os textos sao subcadeias unicas dos titulos das seccoes no style.css.
     */
    function sige_portal_chrome_view_anchors(): array {
        return [
            ['Financeiro MJS-grade', 'App Shell Scroll Inteligente'],
            ['Pagamentos por Turma: Compliance', 'Failsafe global para modais'],
            ['Extractos/Caixa UX-first', null],
        ];
    }
}

if (!function_exists('sige_portal_chrome_header_open_index')) {
    /** Dado o indice da linha do titulo, recua ate a linha que abre o comentario. */
    function sige_portal_chrome_header_open_index(array $lines, int $titleIdx): int {
        for ($j = $titleIdx; $j >= 0; $j--) {
            if (strpos($lines[$j], '/*') !== false) return $j;
        }
        return $titleIdx;
    }
}

if (!function_exists('sige_portal_chrome_build')) {
    /**
     * Recebe o conteudo do style.css e devolve apenas as seccoes de chrome.
     * Lanca RuntimeException se algum ancora desaparecer (forca actualizacao consciente).
     */
    function sige_portal_chrome_build(string $css): string {
        $lines = explode("\n", $css);
        $n = count($lines);

        $findTitle = function (string $needle) use ($lines, $n): int {
            for ($i = 0; $i < $n; $i++) {
                if (strpos($lines[$i], $needle) !== false) return $i;
            }
            return -1;
        };

        $drop = array_fill(0, $n, false);
        foreach (sige_portal_chrome_view_anchors() as [$startTitle, $endTitle]) {
            $st = $findTitle($startTitle);
            if ($st < 0) {
                throw new RuntimeException("Ancora de inicio de view ausente no style.css: {$startTitle}");
            }
            $start = sige_portal_chrome_header_open_index($lines, $st);

            if ($endTitle === null) {
                $end = $n; // ate ao fim
            } else {
                $et = $findTitle($endTitle);
                if ($et < 0) {
                    throw new RuntimeException("Ancora de fim de view ausente no style.css: {$endTitle}");
                }
                $end = sige_portal_chrome_header_open_index($lines, $et);
            }

            for ($k = $start; $k < $end; $k++) {
                $drop[$k] = true;
            }
        }

        $kept = [];
        for ($i = 0; $i < $n; $i++) {
            if (!$drop[$i]) $kept[] = $lines[$i];
        }
        return implode("\n", $kept);
    }
}

if (!function_exists('sige_portal_chrome_render')) {
    /** Conteudo final da folha de chrome: cabecalho canonico + corpo extraido. */
    function sige_portal_chrome_render(string $stylePath): string {
        $css = file_get_contents($stylePath);
        if ($css === false) {
            throw new RuntimeException("Nao foi possivel ler {$stylePath}");
        }
        return SIGE_PORTAL_CHROME_HEADER . "\n" . sige_portal_chrome_build($css);
    }
}
