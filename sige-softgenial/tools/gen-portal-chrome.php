<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Gerador da folha de chrome do portal.
 *
 * Reextrai assets/style-portal-chrome.css a partir de assets/style.css, removendo
 * os modulos de view. Correr sempre que o chrome do style.css mudar, depois validar
 * com 'php tools/run-gates.php'.
 *
 * Uso: php tools/gen-portal-chrome.php
 */
$root = dirname(__DIR__);
require_once $root . '/tools/lib-portal-chrome.php';

$stylePath  = $root . '/assets/style.css';
$chromePath = $root . '/assets/style-portal-chrome.css';

if (!is_file($stylePath)) {
    fwrite(STDERR, "gen-portal-chrome: assets/style.css nao encontrado.\n");
    exit(1);
}

try {
    $out = sige_portal_chrome_render($stylePath);
} catch (Throwable $e) {
    fwrite(STDERR, "gen-portal-chrome: FALHOU - " . $e->getMessage() . "\n");
    exit(1);
}

if (file_put_contents($chromePath, $out) === false) {
    fwrite(STDERR, "gen-portal-chrome: nao foi possivel escrever a folha de chrome.\n");
    exit(1);
}

$full = strlen((string) file_get_contents($stylePath));
$chrome = strlen($out);
printf(
    "gen-portal-chrome: OK - escrita assets/style-portal-chrome.css (%d KB de %d KB; -%d%%).\n",
    (int) round($chrome / 1024),
    (int) round($full / 1024),
    (int) round((1 - $chrome / $full) * 100)
);
