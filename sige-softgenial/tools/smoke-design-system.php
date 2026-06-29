<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Smoke do Design System (fonte da verdade + ponte)
 *
 * Protege a peça mais transversal do sistema: o ficheiro de tokens
 * canónico e a ponte de compatibilidade --sg-* -> --color-* no
 * style.css. Garante que:
 *  - a fonte da verdade define todas as 7 famílias completas;
 *  - a ponte não deixa nenhum --sg-* usado por resolver (órfão);
 *  - nenhuma referência var(--color-*) aponta para token inexistente;
 *  - o style.css não recai em cores hex mágicas;
 *  - a cascata carrega tokens antes de tudo.
 *
 * Sem WordPress: análise estática dos ficheiros CSS/PHP.
 */

$raiz = dirname(__DIR__);
$total = 0; $ok = 0;
function checa(bool $c, string $m): void {
    global $total, $ok;
    $total++;
    if ($c) { $ok++; echo "OK   {$m}\n"; }
    else { echo "FALHOU  {$m}\n"; }
}
function ler(string $p): string { return @file_get_contents($p) ?: ''; }

$tokens = ler("$raiz/assets/sige-tokens.css");
$style  = ler("$raiz/assets/style.css");
$uikit  = ler("$raiz/includes/ui-kit.php");

// ── 1. Fonte da verdade: 7 famílias completas (50..900) ──────────────────────
$familias = ['brand', 'ink', 'slate', 'success', 'danger', 'warning', 'info'];
$passos = ['50','100','200','300','400','500','600','700','800','900'];
$paleta_completa = true;
foreach ($familias as $f) {
    foreach ($passos as $p) {
        if (strpos($tokens, "--color-{$f}-{$p}:") === false) {
            $paleta_completa = false;
            echo "    (em falta: --color-{$f}-{$p})\n";
        }
    }
}
checa($paleta_completa, 'Fonte da verdade: 7 famílias completas (70 tokens de cor 50-900)');
checa(strpos($tokens, '--color-brand-500: #7c3aed;') !== false, 'Fonte da verdade: marca ancorada em #7c3aed');
checa(strpos($tokens, '--color-info-500: #2563eb;') !== false, 'Fonte da verdade: info (azul) ancorada em #2563eb');

// Estrutura: raios, sombras, espaço, durações, z-index, easings
$estrut = ['--radius-md:', '--shadow-md:', '--space-4:', '--duration-normal:',
           '--z-modal:', '--ease-bounce:', '--shadow-brand:'];
$tem_estrut = true;
foreach ($estrut as $e) { if (strpos($tokens, $e) === false) { $tem_estrut = false; echo "    (em falta: {$e})\n"; } }
checa($tem_estrut, 'Fonte da verdade: primitivos estruturais (raios/sombras/espaço/z/easing)');

// Animações canónicas
checa(strpos($tokens, '@keyframes sige-rise-in') !== false
   && strpos($tokens, '@keyframes sige-fade-in') !== false
   && strpos($tokens, '@keyframes sige-spin') !== false, 'Fonte da verdade: animações de entrada canónicas');

// ── 2. Ponte de compatibilidade no style.css ─────────────────────────────────
checa(strpos($style, 'PONTE DE COMPATIBILIDADE') !== false, 'Ponte: bloco de compatibilidade presente no style.css');

// As --sg-* USADAS estão todas definidas na ponte (exceto tema dinâmico injectado por PHP)?
preg_match_all('/var\(\s*(--sg-[a-z0-9-]+)/i', $style, $mu);
$usadas = array_unique($mu[1]);
preg_match_all('/(--sg-[a-z0-9-]+)\s*:/', $style, $md);
$definidas = array_flip($md[1]);
// tema dinâmico: injectado em runtime por theme-engine / centros (não vive na ponte)
$dinamicas = ['theme', 'school', 'center-color', 'finpro', '-q-bg', '-q-fg', '-rate'];
$orfas = [];
foreach ($usadas as $u) {
    if (isset($definidas[$u])) continue;
    $eh_dinamica = false;
    foreach ($dinamicas as $d) { if (strpos($u, $d) !== false) { $eh_dinamica = true; break; } }
    if (!$eh_dinamica) $orfas[] = $u;
}
if ($orfas) { echo '    (órfãs: ' . implode(', ', $orfas) . ")\n"; }
checa(empty($orfas), 'Ponte: zero variáveis --sg-* órfãs (todas resolvem para token canónico)');

// A ponte aponta para o canónico (cores referenciam --color-*)
checa(strpos($style, '--sg-primary-500: var(--color-brand-500);') !== false, 'Ponte: --sg-primary mapeia para a marca canónica');
checa(strpos($style, '--sg-error-500: var(--color-danger-500);') !== false, 'Ponte: --sg-error mapeia para danger canónico');
// dourado eliminado: accent -> warning (decisão de produto)
checa(strpos($style, '--sg-accent-500: var(--color-warning-500);') !== false, 'Ponte: dourado (accent) unificado em warning');

// ── 3. Referências a tokens canónicos resolvem (nenhuma inexistente) ─────────
preg_match_all('/var\(\s*(--color-[a-z]+-[0-9]+)/i', $style, $mr);
$refs = array_unique($mr[1]);
$inexistentes = [];
foreach ($refs as $r) {
    if (strpos($tokens, "{$r}:") === false) $inexistentes[] = $r;
}
if ($inexistentes) { echo '    (inexistentes: ' . implode(', ', $inexistentes) . ")\n"; }
checa(empty($inexistentes), 'Resolução: toda referência var(--color-*) no style.css existe no canónico');

// ── 4. style.css não recai em cores hex mágicas ──────────────────────────────
$hex = preg_match_all('/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b/', $style);
checa($hex === 0, 'Higiene: style.css tem ZERO cores hex mágicas (100% via tokens)' . ($hex ? " ({$hex} restantes)" : ''));

// CSS bem-formado (chavetas balanceadas)
checa(substr_count($style, '{') === substr_count($style, '}'), 'Integridade: chavetas balanceadas no style.css');

// ── 5. Cascata: tokens carregam antes do kit ─────────────────────────────────
checa(strpos($uikit, "wp_enqueue_style('sige-tokens'") !== false
   && strpos($uikit, "'sige-tokens'") < strpos($uikit, "'sige-ui-kit'"),
   'Cascata: tokens carregam ANTES do kit e das views');

// ── 6. Código morto não regressa ─────────────────────────────────────────────
checa(!is_file("$raiz/assets/style-consolidado.css")
   && !is_file("$raiz/tools/css-consolidar.php"),
   'Higiene: CSS morto (consolidado candidato) continua removido');

echo str_repeat('-', 64) . "\n";
if ($ok === $total) {
    echo "SMOKE DESIGN SYSTEM OK - {$total} verificações passaram.\n";
    exit(0);
}
fwrite(STDERR, 'FALHARAM ' . ($total - $ok) . " de {$total} verificações.\n");
exit(1);
