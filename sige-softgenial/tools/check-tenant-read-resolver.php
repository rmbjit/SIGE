<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * GATE - Tenant Read / Resolver (v12.12.9+)
 *
 * Impede a reintroducao de qualquer fallback CEGO para a escola 1. Apos a
 * v12.12.9, a resolucao de escola e determinista (escola unica activa) ou
 * fail-closed (0). Este gate falha se reaparecer:
 *  (1) o ternario cego de resolucao: function_exists('sige_get_escola_id') ? ... : 1
 *  (2) um fallback fixo cego: $escola_id = 1;  ou  $eid = 1;
 *  (3) o resolvedor a devolver a constante SIGE_ESCOLA_MALISA como fallback
 *      (return SIGE_ESCOLA_MALISA).
 *
 * A linha de DEFINICAO da constante (define('SIGE_ESCOLA_MALISA', 1)) e
 * permitida (mantida por retrocompatibilidade, marcada como obsoleta).
 */
$root = dirname(__DIR__);

$reTernario = "/function_exists\('sige_get_escola_id'\)\s*\?\s*\(int\)\s*sige_get_escola_id\(\)\s*:\s*1\b/";
$reTernario2 = "/function_exists\('sige_get_escola_id'\)\s*\?\s*sige_get_escola_id\(\)\s*:\s*1\b/";
$reFixo = '/\$(escola_id|escola_id_contexto|eid)\s*=\s*1\s*;/';
$reConst = '/return\s+SIGE_ESCOLA_MALISA\s*;/';
$reLinha = '/escola_id\s*\?\?\s*1\b/';

$files = [];
foreach (['includes', 'admin'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $fileObj) {
        if ($fileObj->isFile() && strtolower($fileObj->getExtension()) === 'php') $files[] = $fileObj->getPathname();
    }
}

$fails = [];
foreach ($files as $abs) {
    $rel = ltrim(str_replace($root, '', $abs), '/');
    $lines = file($abs, FILE_IGNORE_NEW_LINES);
    foreach ($lines as $i => $line) {
        $ln = $i + 1;
        if (preg_match($reTernario, $line) || preg_match($reTernario2, $line)) { $fails[] = "[ternario cego : 1] {$rel}:{$ln}"; }
        if (preg_match($reFixo, $line))      { $fails[] = "[fallback fixo = 1] {$rel}:{$ln}"; }
        if (preg_match($reConst, $line))     { $fails[] = "[return SIGE_ESCOLA_MALISA] {$rel}:{$ln}"; }
        if (preg_match($reLinha, $line))     { $fails[] = "[fallback de linha escola_id ?? 1] {$rel}:{$ln}"; }
    }
}

if ($fails) {
    fwrite(STDERR, "TENANT READ/RESOLVER FALHOU (" . count($fails) . "):\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo "TENANT READ/RESOLVER OK - sem fallback cego para escola 1 (resolucao determinista por escola unica activa, senao fail-closed).\n";
