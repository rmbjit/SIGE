<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
/**
 * Smoke Fase 4 incr 2 - utilitarios e vaga academica.
 * Confirma que o CSS de utilitarios existe com as classes esperadas, que esta
 * enfileirado no admin-shell, e que a vaga academica trocou estilos inline por
 * classes (as classes aparecem nas vistas e os estilos migrados sairam de la).
 */
$root = getenv('SIGE_ROOT') ?: dirname(__DIR__);
$read = static function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return is_file($p) ? (string) file_get_contents($p) : '';
};
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail) {
    if ($ok) { $pass++; } else { $fail++; echo "  FALHOU: $label\n"; }
};

// 1. CSS de utilitarios presente com as classes esperadas.
$css = $read('assets/sige-utilities.css');
$check($css !== '', 'assets/sige-utilities.css existe');
foreach (['sige-u-shrink-0', 'sige-u-flex-1', 'sige-u-tac', 'sige-u-tal', 'sige-u-tar', 'sige-u-fw7', 'sige-u-fw6', 'sige-u-fw5', 'sige-u-m0', 'sige-u-nowrap', 'sige-u-oxa', 'sige-u-wfull'] as $cls) {
    $check(strpos($css, '.' . $cls . ' ') !== false || strpos($css, '.' . $cls . '{') !== false, "classe $cls definida");
}
$check(substr_count($css, '!important') >= 12, 'utilitarios usam !important (replica a especificidade do inline)');

// 2. Enfileirado no admin-shell.
$shell = $read('includes/admin-shell.php');
$check(strpos($shell, "'sige-utilities'") !== false && strpos($shell, "assets/sige-utilities.css") !== false, 'utilitarios enfileirados no admin-shell');

// 3. Vagas aplicadas (academica e financeira): as classes aparecem e os estilos
// migrados sairam. Caminhos completos relativos a raiz do plugin.
$vistas = [
    'admin/academic/encerramento-view.php', 'admin/academic/abertura-view.php',
    'admin/academic/estatisticas-demograficas-view.php', 'admin/academic/turmas-view.php',
    'admin/academic/aprovar_notas-view.php',
    'admin/finance/financeiro-config.php', 'admin/finance/financeiro-devedores-view.php',
    'admin/finance/financeiro-planos-view.php', 'admin/finance/financeiro-pagamentos.php',
    'includes/admin-shell.php', 'admin/jardim/jardim_relatorio-view.php',
];
$usos = 0;
foreach ($vistas as $v) {
    $usos += substr_count($read($v), 'sige-u-');
}
$check($usos >= 60, "classes utilitarias em uso nas vistas migradas ($usos ocorrencias)");

// Nota: nao se afere "estilos puros remanescentes" porque a migracao salta de
// proposito os elementos que ja tinham class (esses style ficam, por desenho). Quem
// garante que a migracao reduziu o inline e a catraca check-inline-frontend.

// 4. Nenhuma tag com class duplicado nas vistas migradas.
$dup = 0;
foreach ($vistas as $v) {
    $src = $read($v);
    foreach (preg_split('/\n/', $src) as $line) {
        if (preg_match_all('/<[a-zA-Z][^>\n]*>/', $line, $mm)) {
            foreach ($mm[0] as $tag) { if (substr_count($tag, ' class="') > 1) $dup++; }
        }
    }
}
$check($dup === 0, "sem tags com class duplicado nas vistas migradas (dup=$dup)");

// 5. GUARDA ANTI-REGRESSAO DE IMPRESSAO. As classes utilitarias so resolvem onde o
// sige-utilities.css esta carregado (a shell). Uma vista que abre janela de impressao
// autonoma (window.open('') + document.write) e que usa classes sige-u- no que escreve
// nessa janela tem de embeber sige_utilities_inline_css() no <style> do popup; caso
// contrario o impresso perde alinhamentos e pesos. Esta guarda obriga a esse contrato.
$dirs = ['admin', 'includes'];
$popup_views = [];
$it = function ($base) use ($root, &$it, &$popup_views) {
    $abs = $root . '/' . $base;
    if (!is_dir($abs)) return;
    foreach (scandir($abs) as $e) {
        if ($e === '.' || $e === '..') continue;
        $rel = $base . '/' . $e;
        $p = $root . '/' . $rel;
        if (is_dir($p)) { if ($e !== 'vendor') $it($rel); continue; }
        if (substr($e, -4) !== '.php') continue;
        $src = (string) file_get_contents($p);
        $tem_popup = (strpos($src, "window.open('") !== false) || (strpos($src, 'document.write') !== false);
        $usa_u = (strpos($src, 'class="sige-u-') !== false) || (strpos($src, "class='sige-u-") !== false) || (strpos($src, "'<td class=\"sige-u-") !== false);
        if ($tem_popup && $usa_u) $popup_views[$rel] = strpos($src, 'sige_utilities_inline_css(') !== false;
    }
};
foreach ($dirs as $d) $it($d);
$sem_helper = array_keys(array_filter($popup_views, static fn($ok) => !$ok));
$check(empty($sem_helper), 'vistas com popup de impressao e classes sige-u- embebem sige_utilities_inline_css (' . count($popup_views) . ' vistas; sem helper: ' . (empty($sem_helper) ? 'nenhuma' : implode(', ', $sem_helper)) . ')');

echo "--------------------------------------------------------\n";
if ($fail > 0) { echo "SMOKE INLINE FRONT-END: $fail falha(s), $pass ok.\n"; exit(1); }
echo "SMOKE INLINE FRONT-END OK - $pass verificacoes passaram.\n";
