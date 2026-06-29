<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$baselineFile = $root . '/docs/design-system/DESIGN_SYSTEM_BASELINE-v12.15.5.json';

function sige_ds55_read_file(string $file): string {
    $content = @file_get_contents($file);
    return is_string($content) ? $content : '';
}

function sige_ds55_scan_files(string $root): array {
    $dirs = ['admin', 'includes', 'assets'];
    $files = [];
    foreach ($dirs as $dir) {
        $base = $root . '/' . $dir;
        if (!is_dir($base)) { continue; }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) { continue; }
            $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
            if (in_array($ext, ['php', 'css', 'js', 'html', 'txt', 'md'], true)) {
                $files[] = $f->getPathname();
            }
        }
    }
    sort($files);
    return $files;
}

function sige_ds55_count_pattern(array $files, string $pattern): int {
    $total = 0;
    foreach ($files as $file) {
        $txt = sige_ds55_read_file($file);
        $total += preg_match_all($pattern, $txt);
    }
    return $total;
}

$baseline = json_decode(sige_ds55_read_file($baselineFile), true);
if (!is_array($baseline)) {
    fwrite(STDERR, "Baseline JSON em falta ou invalida: {$baselineFile}\n");
    exit(1);
}

$files = sige_ds55_scan_files($root);
$current = [
    'style_attr_occurrences' => sige_ds55_count_pattern($files, '/\bstyle\s*=/i'),
    'on_attr_occurrences' => sige_ds55_count_pattern($files, '/\bon[a-zA-Z][a-zA-Z0-9_-]*\s*=/'),
    'style_blocks_occurrences' => sige_ds55_count_pattern($files, '/<style\b/i'),
    'script_blocks_occurrences' => sige_ds55_count_pattern($files, '/<script\b/i'),
    'important_occurrences' => sige_ds55_count_pattern($files, '/!important/'),
    'overflow_hidden_occurrences' => sige_ds55_count_pattern($files, '/overflow\s*:\s*hidden/i'),
    'position_fixed_occurrences' => sige_ds55_count_pattern($files, '/position\s*:\s*fixed/i'),
];

$stored = $baseline['counts'] ?? [];
$required = array_keys($current);
$missing = [];
foreach ($required as $key) {
    if (!array_key_exists($key, $stored)) { $missing[] = $key; }
}
if ($missing) {
    fwrite(STDERR, 'Baseline sem chaves obrigatorias: ' . implode(', ', $missing) . "\n");
    exit(1);
}

echo "DESIGN SYSTEM INVENTARIO v12.15.5\n";
foreach ($current as $key => $value) {
    $base = (int)($stored[$key] ?? -1);
    $delta = $value - $base;
    printf("%-32s current=%d baseline=%d delta=%+d\n", $key, $value, $base, $delta);
}

echo "Baseline JSON OK: docs/design-system/DESIGN_SYSTEM_BASELINE-v12.15.5.json\n";
exit(0);
