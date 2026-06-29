<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Smoke dos Ícones (consistência visual)
 *
 * Os ícones SVG eram desenhados preenchendo fracções diferentes do
 * viewBox: pareciam de tamanhos diferentes mesmo à mesma caixa em px
 * (dispersão de diagonal medida: 8.2px). Foram normalizados opticamente
 * (tools/normalizar-icones.py) para a MESMA diagonal aparente, com a tag
 * <svg> raiz canónica garantida em runtime por sige_ui_icon_canonizar().
 *
 * Este smoke (sem render) verifica estaticamente que a consistência se
 * mantém e não pode regredir:
 *  - todos os SVG têm os MESMOS atributos de invólucro (24x24, viewBox,
 *    fill none, stroke currentColor, linecaps);
 *  - todos têm o <g transform> de normalização óptica (não voltaram ao
 *    desenho cru);
 *  - a função de render canoniza a tag raiz.
 */

$raiz = dirname(__DIR__);
$pasta = $raiz . '/assets/icons/sg';
$total = 0; $ok = 0;
function checa(bool $c, string $m): void {
    global $total, $ok;
    $total++;
    if ($c) { $ok++; echo "OK   {$m}\n"; }
    else { echo "FALHOU  {$m}\n"; }
}

$ficheiros = glob($pasta . '/*.svg');
checa(count($ficheiros) >= 30, 'Conjunto de ícones presente (' . count($ficheiros) . ' SVG)');

// 1. Invólucro canónico uniforme em TODOS
$sem_24 = []; $sem_viewbox = []; $sem_fill_none = []; $sem_stroke = []; $sem_transform = []; $sem_linecap = [];
foreach ($ficheiros as $f) {
    $nome = basename($f, '.svg');
    $svg = (string)file_get_contents($f);
    // só a tag de abertura
    if (!preg_match('/<svg\b[^>]*>/i', $svg, $m)) { $sem_viewbox[] = $nome; continue; }
    $tag = $m[0];
    if (strpos($tag, 'width="24"') === false || strpos($tag, 'height="24"') === false) $sem_24[] = $nome;
    if (strpos($tag, 'viewBox="0 0 24 24"') === false) $sem_viewbox[] = $nome;
    if (strpos($tag, 'fill="none"') === false) $sem_fill_none[] = $nome;
    if (strpos($tag, 'stroke="currentColor"') === false) $sem_stroke[] = $nome;
    if (strpos($tag, 'stroke-linecap="round"') === false) $sem_linecap[] = $nome;
    // conteúdo normalizado: tem <g transform=...scale...>
    if (!preg_match('/<g\s+transform="[^"]*scale\([^"]*\)"/', $svg)) $sem_transform[] = $nome;
}
$lista = fn($a) => $a ? ' (' . implode(', ', array_slice($a, 0, 6)) . (count($a) > 6 ? '...' : '') . ')' : '';
checa(empty($sem_24), 'Todos os ícones: width/height = 24 (tamanho de invólucro uniforme)' . $lista($sem_24));
checa(empty($sem_viewbox), 'Todos os ícones: viewBox 0 0 24 24 canónico' . $lista($sem_viewbox));
checa(empty($sem_fill_none), 'Todos os ícones: fill="none" (traço, não sólido)' . $lista($sem_fill_none));
checa(empty($sem_stroke), 'Todos os ícones: stroke="currentColor" (herdam a cor do contexto)' . $lista($sem_stroke));
checa(empty($sem_linecap), 'Todos os ícones: stroke-linecap="round" (remates uniformes)' . $lista($sem_linecap));
checa(empty($sem_transform), 'Todos os ícones: normalizados opticamente (<g transform scale>)' . $lista($sem_transform));

// 2. stroke-width compensado presente no <g> (espessura óptica constante)
$sem_sw_compensado = [];
foreach ($ficheiros as $f) {
    $nome = basename($f, '.svg');
    $svg = (string)file_get_contents($f);
    if (!preg_match('/<g\s+transform="[^"]*"\s+stroke-width="[0-9.]+"/', $svg)) {
        $sem_sw_compensado[] = $nome;
    }
}
checa(empty($sem_sw_compensado), 'Todos os ícones: stroke-width compensado pela escala (espessura óptica constante)' . $lista($sem_sw_compensado));

// 3. A função de render garante a canonização do invólucro
$uic = (string)file_get_contents($raiz . '/includes/ui-components.php');
checa(strpos($uic, 'function sige_ui_icon_canonizar') !== false, 'Função sige_ui_icon_canonizar existe (blinda a tag <svg> raiz)');
checa(strpos($uic, 'sige_ui_icon_canonizar($svg, $name)') !== false, 'sige_ui_icon aplica a canonização ao servir cada ícone');

// 4. A ferramenta de normalização está versionada (reprodutível)
checa(is_file($raiz . '/tools/normalizar-icones.py'), 'Ferramenta de normalização versionada (build reproduzível)');

echo str_repeat('-', 64) . "\n";
if ($ok === $total) {
    echo "SMOKE ÍCONES OK - {$total} verificações passaram.\n";
    exit(0);
}
fwrite(STDERR, 'FALHARAM ' . ($total - $ok) . " de {$total} verificações.\n");
exit(1);
