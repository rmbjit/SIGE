<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/governance-lib.php';
$views = sige_gov_extract_views();
$missing = array_values(array_diff($views['allowlist'], array_keys($views['permission_map'])));
$empty = [];
foreach ($views['permission_map'] as $view => $perms) {
    if (!$perms) $empty[] = $view;
}
// v12.12.22 - Invariante de roteamento: toda a rota no mapa de despacho TEM de estar na
// allowlist. Caso contrario, a guarda anti-LFI reescreve o pedido para o painel inicial e a
// view fica inalcancavel na UI mesmo estando mapeada (defeito que matou as views da Fase 7).
$dispatch_off_allowlist = array_values(array_diff($views['dispatch_map'] ?? [], $views['allowlist']));
if ($missing || $empty || $dispatch_off_allowlist) {
    if ($missing) fwrite(STDERR, 'Views na allowlist sem permissao: ' . implode(', ', $missing) . "\n");
    if ($empty) fwrite(STDERR, 'Views com permissao vazia: ' . implode(', ', $empty) . "\n");
    if ($dispatch_off_allowlist) fwrite(STDERR, 'Views no mapa de despacho fora da allowlist (rota morta, cai no painel): ' . implode(', ', $dispatch_off_allowlist) . "\n");
    exit(1);
}
echo 'VIEW PERMISSION MAP OK - ' . count($views['allowlist']) . ' views cobertas por matriz; ' . count($views['dispatch_map'] ?? []) . ' rotas de despacho na allowlist.' . "\n";
