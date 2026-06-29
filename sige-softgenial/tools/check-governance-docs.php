<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/governance-lib.php';
$v = sige_gov_version();

$required = [
    "docs/governance/PHASE_CHARTER-v{$v}.md" => ['Objectivo', 'Incluido', 'Excluido', 'Riscos', 'Criterios de aceitacao'],
    "docs/governance/TECHNICAL_INVENTORY-v{$v}.md" => ['Superficie', 'Views', 'Permissoes', 'Tenant', 'Segredos', 'Dependencias', 'Security Kernel'],
    "docs/governance/DEFINITION_OF_DONE-v{$v}.md" => ['DoD-001', 'DoD-018', 'Zero P0/P1'],
    "docs/governance/TRACEABILITY_MATRIX-v{$v}.md" => ['SK-001', 'SK-018', 'Evidencia'],
    "docs/governance/RISK_REGISTER-v{$v}.md" => ['P0', 'P1', 'P2', 'P3'],
    "docs/governance/ADVERSARIAL_REVIEW-v{$v}.md" => ['Rediagnostico adversarial', 'P0', 'P1', 'Decisao'],
    "docs/security/AUTHORIZATION_DEBT_REGISTER-v{$v}.md" => ['current_user_can', 'sige_can', 'baseline'],
    "docs/security/TENANT_ISOLATION_REGISTER-v{$v}.md" => ['escola_id', 'fallback', 'Fase 3'],
    "docs/security/SECRETS_OPTIONS_REGISTER-v{$v}.md" => ['segredo', 'opcoes', 'Secret Vault'],
    "docs/security/EXTERNAL_DEPENDENCIES_REGISTER-v{$v}.md" => ['host', 'dependencia', 'risco'],
    "docs/security/PUBLIC_ENDPOINTS_POLICY-v{$v}.md" => ['endpoint publico', 'autenticacao', 'rate limit'],
    "docs/security/SECURITY_KERNEL_POLICY-v{$v}.md" => ['Security Kernel', 'enforce', 'observe', 'rate limit', 'auditoria'],
];
$fails = [];
foreach ($required as $rel => $needles) {
    $txt = sige_gov_read($rel);
    if ($txt === '') { $fails[] = "$rel ausente"; continue; }
    foreach ($needles as $needle) {
        if (stripos($txt, $needle) === false) $fails[] = "$rel sem marcador $needle";
    }
}
foreach (["docs/security/ACTION_SURFACE_MANIFEST-v{$v}.json","docs/security/SECURITY_KERNEL_RULES-v{$v}.json","docs/governance/INVENTORY_SUMMARY-v{$v}.json","docs/security/AUTHORIZATION_DEBT_BASELINE-v{$v}.json","docs/security/TENANT_FALLBACK_BASELINE-v{$v}.json","docs/security/SECRETS_OPTIONS_BASELINE-v{$v}.json","docs/security/EXTERNAL_DEPENDENCIES_BASELINE-v{$v}.json"] as $rel) {
    $txt = sige_gov_read($rel);
    if ($txt === '') { $fails[] = "$rel ausente"; continue; }
    if (json_decode($txt, true) === null) $fails[] = "$rel JSON invalido";
}
if ($fails) {
    fwrite(STDERR, "GOVERNANCE DOCS FALHOU\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo 'GOVERNANCE DOCS OK - documentos e baselines obrigatorios presentes.' . "\n";
