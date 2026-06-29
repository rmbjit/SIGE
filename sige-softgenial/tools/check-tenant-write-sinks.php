<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * GATE - Tenant Write Sinks (v12.12.8.1+)
 *
 * Governa a superficie REAL de escrita por tenant, que o baseline ": 1" nao
 * capturava. Falha se existir:
 *  (A) uma funcao que ESCREVE e recebe escola_id/eid por parametro SEM guard
 *      fail-closed;
 *  (B) uma funcao com escrita inline ('escola_id' => sige_get_escola_id()) SEM
 *      guard fail-closed na propria funcao.
 *
 * Guards reconhecidos: sige_tenant_write_guard(), sige_require_escola_id(), ou
 * guard manual sobre escola_id/eid (<= 0) com aborto.
 *
 * EXCECOES (logging resiliente, por design): funcoes de auditoria/log devem
 * registar o evento mesmo sem escola resolvida (a anomalia fica auditada com
 * escola 0, em vez de se perder o registo). Estao na allowlist abaixo.
 */
$root = dirname(__DIR__);

// Allowlist de logging resiliente (documentada no TENANT_ISOLATION_REGISTER).
$ALLOW = [
    'sige_audit_log',    // includes/finance-core.php - auditoria central
    'sige_registar_log', // includes/db-handler.php - log tecnico
];

$directWriteRe = '/\b(INSERT\s+INTO|UPDATE\s+\w|DELETE\s+FROM|REPLACE\s+INTO)\b|->(insert|update|delete|replace)\s*\(/i';

$isGuarded = static function (string $body): bool {
    if (preg_match('/sige_tenant_write_guard\s*\(/', $body)) return true;
    if (preg_match('/sige_require_escola_id\s*\(/', $body)) return true;
    if (preg_match('/\b(escola_id|eid)\s*<=\s*0\b/', $body) && preg_match('/\breturn\b|wp_die|wp_send_json/', $body)) return true;
    return false;
};

$files = [];
foreach (['includes', 'admin'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $fileObj) {
        if ($fileObj->isFile() && strtolower($fileObj->getExtension()) === 'php') $files[] = $fileObj->getPathname();
    }
}

$failA = [];
$failB = [];
foreach ($files as $abs) {
    $rel = ltrim(str_replace($root, '', $abs), '/');
    $lines = file($abs, FILE_IGNORE_NEW_LINES);
    $n = count($lines);
    for ($i = 0; $i < $n; $i++) {
        if (!preg_match('/function\s+([A-Za-z0-9_]+)\s*\(([^)]*)\)/', $lines[$i], $m)) continue;
        $name = $m[1];
        $params = $m[2];
        if (in_array($name, $ALLOW, true)) continue;
        $depth = 0; $started = false; $end = $i;
        for ($j = $i; $j < $n; $j++) {
            $depth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');
            if (strpos($lines[$j], '{') !== false) $started = true;
            if ($started && $depth <= 0) { $end = $j; break; }
        }
        $body = implode("\n", array_slice($lines, $i, $end - $i + 1));
        $writesDirect = (bool) preg_match($directWriteRe, $body);
        if (!$writesDirect) continue;
        $guarded = $isGuarded($body);
        // (A) sumidouro com parametro de escola
        $hasEscolaParam = (bool) preg_match('/\$(escola_id|eid)\b/', $params);
        if ($hasEscolaParam && !$guarded) { $failA[] = "{$rel}:" . ($i + 1) . " {$name}"; }
        // (B) escrita inline com resolucao directa
        $inline = (bool) preg_match("/'escola_id'\s*=>\s*sige_get_escola_id\(\)/", $body);
        if ($inline && !$guarded) { $failB[] = "{$rel}:" . ($i + 1) . " {$name}"; }
    }
}

$fails = array_merge(
    array_map(static fn($x) => "[A sumidouro sem guard] {$x}", $failA),
    array_map(static fn($x) => "[B escrita inline sem guard] {$x}", $failB)
);
if ($fails) {
    fwrite(STDERR, "TENANT WRITE SINKS FALHOU (" . count($fails) . "):\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo "TENANT WRITE SINKS OK - todos os sumidouros de escrita por tenant tem guard fail-closed (excecoes de logging: " . implode(', ', $ALLOW) . ").\n";
