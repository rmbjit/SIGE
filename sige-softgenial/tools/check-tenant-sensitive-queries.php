<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/governance-lib.php';
$findings = sige_gov_expense_print_tenant_findings();
if ($findings) {
    foreach ($findings as $finding) {
        fwrite(STDERR, ($finding['id'] ?? 'tenant_sensitive_query') . ': ' . ($finding['message'] ?? 'falha') . "\n");
    }
    exit(1);
}
echo 'TENANT SENSITIVE QUERIES OK - sige_desp_print com escola_id, nonce e auditoria.' . "\n";
