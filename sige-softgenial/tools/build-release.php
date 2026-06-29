<?php
/**
 * SIGE SoftGenial - Build Release Tool
 *
 * Uso:
 *   php tools/build-release.php 12.9.39 "Resumo curto da versão"
 *
 * O script actualiza automaticamente:
 * - Header Version do sige-softgenial.php
 * - define('SIGE_VERSION', '...')
 * - BUILD.json
 * - CHANGELOG-vX-Y-Z.txt
 *
 * Deve ser executado ANTES de compactar o ZIP final.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script só deve ser executado via CLI.\n");
    exit(1);
}
$version = $argv[1] ?? '';
$summary = $argv[2] ?? 'Actualização incremental SIGE SoftGenial.';
if (!preg_match('/^\d+\.\d+\.\d+(?:\.\d+)?$/', $version)) {
    fwrite(STDERR, "Uso: php tools/build-release.php 12.9.39 \"Resumo\"\n");
    exit(1);
}
$root = dirname(__DIR__);
$main = $root . '/sige-softgenial.php';
if (!file_exists($main)) {
    fwrite(STDERR, "Ficheiro principal não encontrado: $main\n");
    exit(1);
}
$src = file_get_contents($main);
$src = preg_replace('/(\*\s*Version:\s*)[^\r\n]+/i', '${1}' . $version, $src, 1);
$src = preg_replace("/define\('SIGE_VERSION',\s*'[^']+'\);/", "define('SIGE_VERSION', '" . $version . "');", $src, 1);
file_put_contents($main, $src);
$scope = $argv[3] ?? 'Geral';
$label = "SIGE SoftGenial v{$version} - {$summary}";
if (function_exists('mb_strlen') ? mb_strlen($label) > 110 : strlen($label) > 110) {
    $label = "SIGE SoftGenial v{$version}";
}
$build_id = 'sige-' . str_replace('.', '-', $version) . '-' . gmdate('YmdHis');
$manifest = [
    'name' => $label,
    'product' => 'SIGE SoftGenial - Gestão Escolar Moçambique',
    'version' => $version,
    'date' => gmdate('Y-m-d'),
    'build_id' => $build_id,
    'build_time' => date('c'),
    'channel' => 'validated-incremental',
    'label' => $label,
    'scope' => $scope,
    'build' => $build_id,
    'summary' => $summary,
];
file_put_contents($root . '/BUILD.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
$slug = str_replace('.', '-', $version);
$changelog = "SIGE SoftGenial v{$version}\n" . str_repeat('=', 28) . "\n\n" . $summary . "\n\n" .
             "Checklist obrigatório:\n" .
             "- Header Version actualizado.\n" .
             "- SIGE_VERSION actualizado.\n" .
             "- BUILD.json actualizado.\n" .
             "- php -l executado nos ficheiros alterados.\n";
$changelog_dir = $root . '/docs/changelog';
if (!is_dir($changelog_dir)) {
    mkdir($changelog_dir, 0755, true);
}
file_put_contents($changelog_dir . "/CHANGELOG-v{$slug}.txt", $changelog);

// Prepende a entrada no CHANGELOG.md consolidado (mantém o cabeçalho no topo).
$md_path = $root . '/CHANGELOG.md';
$entry = "## v{$version}\n{$summary}\nDetalhe: `docs/changelog/CHANGELOG-v{$slug}.txt`\n\n";
if (file_exists($md_path)) {
    $md = file_get_contents($md_path);
    $pos = strpos($md, "\n## ");
    if ($pos !== false) {
        $md = substr($md, 0, $pos + 1) . $entry . substr($md, $pos + 1);
    } else {
        $md .= "\n" . $entry;
    }
} else {
    $md = "# CHANGELOG - SIGE SoftGenial\n\n" . $entry;
}
file_put_contents($md_path, $md);
echo "OK - versão {$version} aplicada.\n";
