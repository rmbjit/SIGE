# TRACEABILITY MATRIX - v12.12.8.1

Liga cada requisito (DoD) aos ficheiros alterados e a evidencia de teste.

| ID | Requisito | Ficheiros/areas | Evidencia | Estado |
|----|-----------|-----------------|-----------|--------|
| SK-001 | Helper auditado sige_tenant_write_guard | includes/multitenancy.php | php -l + /tmp/test_guard.php | Concluido |
| SK-002 | Helper permite escola > 0 | includes/multitenancy.php | test_guard: escola 7 -> true | Concluido |
| SK-003 | Helper bloqueia e audita escola <= 0 | includes/multitenancy.php | test_guard: escola 0 -> false + tenant_write_blocked | Concluido |
| SK-004 | 24 sumidouros Cat. A com guard | fin-action-service, fin-familia-service, fin-fecho-turno, acta-pdf-handler, alertas-core, notification-policy, whatsapp-guardian, whatsapp-recovery-mode, abertura-view, encerramento-view | worklist2: Total A real = 0 | Concluido |
| SK-005 | Aborto Cat. A respeita contrato | mesmos ficheiros | inspeccao por funcao + lint | Concluido |
| SK-006 | 22 funcoes Cat. B com guard | ajax-handlers, db-handler, finance-core | worklist2: Total B real = 2 (so excecoes) | Concluido |
| SK-007 | AJAX aborta com wp_send_json_error; biblioteca com retorno tipado | db-handler, ajax-handlers, finance-core | inspeccao + lint | Concluido |
| SK-008 | Excecoes de logging documentadas e allowlisted | finance-core, db-handler, check-tenant-write-sinks | gate allowlist + TENANT_ISOLATION_REGISTER | Concluido |
| SK-009 | Handler delegado acta:1610 fechado | includes/acta-pdf-handler.php | baseline ": 1" 139 -> 138 | Concluido |
| SK-010 | Auditoria em todos os abortos | multitenancy.php (guard + require) | sige_security_log em ambos os caminhos | Concluido |
| SK-011 | Gate check-tenant-write-sinks criado e ligado | tools/check-tenant-write-sinks.php, tools/run-gates.php | execucao do gate (OK) | Concluido |
| SK-012 | Gate verifica 0 A e 0 B (fora excecoes) | tools/check-tenant-write-sinks.php | saida do gate | Concluido |
| SK-013 | Baseline ": 1" 138; v12.12.8 congelado 139 | docs/security/TENANT_FALLBACK_BASELINE-*.json | contagem 138 vs 139 | Concluido |
| SK-014 | Zero alteracao a regras de calculo | finance-core, fin-* | smoke calc + diff funcional | Pendente (smoke) |
| SK-015 | Mono-escola sem alteracao | includes/multitenancy.php (resolvedor) | guards dormentes com escola 1 | Concluido |
| SK-016 | Versao sincronizada e Kernel 193 | sige-softgenial.php, BUILD.json, governance-lib, SECURITY_KERNEL_RULES | smoke-release-gate + check-security-kernel-rules | Pendente (gates) |
| SK-017 | 12 docs v12.12.8.1, smoke verde, lint e gates verdes | docs/, tools/ | check-governance-docs + run-gates | Pendente (gates) |
| SK-018 | Rediagnostico adversarial Zero P0/P1 | docs/governance/ADVERSARIAL_REVIEW-v12.12.8.1.md | ADVERSARIAL_REVIEW | Pendente (adversarial) |
