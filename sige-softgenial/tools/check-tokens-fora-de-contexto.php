<?php
// Acesso restrito: utilitario de linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Gate: tokens fora de contexto (AUTO-DESCOBERTA)
 *
 * LICAO (14 Jun 2026): a tokenizacao em massa referenciou var(--space-*),
 * var(--fs-*), etc. em saidas que NAO carregam assets/sige-tokens.css. Uma
 * custom property indefinida dentro de um shorthand invalida a declaracao
 * INTEIRA (ex. margin:0 auto var(--space-3) deixou de centrar o logotipo do
 * login). Estes tokens de DIMENSAO nunca podem aparecer em contextos sem tokens.
 *
 * Em vez de uma lista fixa de ficheiros, este gate DESCOBRE sozinho os contextos
 * sem tokens pelos seus marcadores estruturais inequivocos:
 *   - login_head / login_enqueue_scripts  -> pagina de wp-login
 *   - add_shortcode(...)                   -> saida front-end (ex. portal)
 *   - document.write(`...<!DOCTYPE...`)    -> popup standalone (impressao)
 * mais uma SEMENTE explicita para contextos sem marcador (documentos echo com
 * :root proprio). Assim, qualquer NOVA pagina de login, shortcode ou popup fica
 * automaticamente protegida, sem ninguem editar listas.
 */

$raiz = dirname(__DIR__);

// Semente: contextos sem tokens que nao tem marcador estrutural detectavel
// (ex. documento standalone construido por echo com :root proprio).
$semente = [
    'includes/passagem-docs-handler.php',
];

$re_dim = '/var\(\s*--(?:space|fs|radius|shadow|duration)-[a-z0-9]+/i';
$re_marca = '/add_action\(\s*[\'"]login_(?:head|enqueue_scripts)[\'"]|login_enqueue_scripts|add_shortcode\s*\(/i';

// Recolher todos os PHP do plugin (excepto tools/docs/vendor).
$todos = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->getExtension() !== 'php') continue;
    $rel = str_replace($raiz . '/', '', $f->getPathname());
    if (strpos($rel, 'tools/') === 0 || strpos($rel, 'docs/') === 0 || strpos($rel, 'vendor/') !== false) continue;
    $todos[$rel] = (string)file_get_contents($f->getPathname());
}

$falhas = [];

// 1) Contextos descobertos por marcador: proibir tokens de dimensao no ficheiro.
foreach ($todos as $rel => $s) {
    if (preg_match($re_marca, $s) && preg_match_all($re_dim, $s, $m)) {
        $falhas[$rel] = ['n' => count($m[0]), 'motivo' => 'login/shortcode'];
    }
}

// 2) Semente explicita.
foreach ($semente as $rel) {
    if (isset($todos[$rel]) && preg_match_all($re_dim, $todos[$rel], $m)) {
        $falhas[$rel] = ['n' => count($m[0]), 'motivo' => 'documento standalone'];
    }
}

// 3) Popups de impressao: tokens dentro de document.write(`...<!DOCTYPE...`).
foreach ($todos as $rel => $s) {
    if (preg_match_all('/document\.write\(\s*`(.*?)`\s*\)/is', $s, $mm)) {
        foreach ($mm[1] as $seg) {
            if (stripos($seg, '<!DOCTYPE') !== false && preg_match_all($re_dim, $seg, $m)) {
                $falhas[$rel] = ['n' => ($falhas[$rel]['n'] ?? 0) + count($m[0]), 'motivo' => 'popup impressao'];
            }
        }
    }
}

// Contar quantos contextos foram vigiados (para a mensagem de OK).
$vigiados = 0;
foreach ($todos as $rel => $s) if (preg_match($re_marca, $s)) $vigiados++;
$vigiados += count($semente);

if ($falhas) {
    echo str_repeat('=', 64) . "\n";
    fwrite(STDERR, "TOKENS FORA DE CONTEXTO: estas saidas nao carregam sige-tokens.css,\n"
        . "logo var(--space/fs/radius/shadow/duration) fica por resolver e parte o CSS.\n");
    foreach ($falhas as $rel => $info) {
        fwrite(STDERR, sprintf("  %-44s %d ocorrencia(s)  [%s]\n", $rel, $info['n'], $info['motivo']));
    }
    fwrite(STDERR, "Use valores literais (px) nestes contextos, nao tokens de dimensao.\n");
    exit(1);
}

echo "TOKENS FORA DE CONTEXTO OK - " . $vigiados . " contexto(s) sem tokens vigiado(s) (auto-descoberta login/shortcode/popup + semente), todos a usar literais.\n";
exit(0);
