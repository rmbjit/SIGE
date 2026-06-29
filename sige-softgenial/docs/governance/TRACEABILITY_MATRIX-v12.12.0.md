# TRACEABILITY MATRIX - v12.12.0

| ID | Requisito | Ficheiros | Testes | Evidencia |
|---|---|---|---|---|
| GOV-001 | Inventario tecnico | tools/inventory-surface.php; docs/governance/TECHNICAL_INVENTORY-v12.12.0.md | php tools/inventory-surface.php --check | Inventario OK |
| GOV-002 | Phase Charter | docs/governance/PHASE_CHARTER-v12.12.0.md | php tools/check-governance-docs.php | Documento com Objectivo, Incluido, Excluido, Riscos |
| GOV-003 | Definition of Done | docs/governance/DEFINITION_OF_DONE-v12.12.0.md | php tools/check-governance-docs.php | DoD-001 ate DoD-015 |
| GOV-004 | Matriz de rastreabilidade | docs/governance/TRACEABILITY_MATRIX-v12.12.0.md | php tools/check-governance-docs.php | GOV-001 ate GOV-015 |
| GOV-005 | Views com permissao | includes/admin-shell.php | php tools/check-view-permission-map.php | 54 views cobertas |
| GOV-006 | Manifesto de acoes | docs/security/ACTION_SURFACE_MANIFEST-v12.12.0.json | php tools/check-action-surface-manifest.php | 162 superficies declaradas |
| GOV-007 | Politica de endpoints publicos | docs/security/PUBLIC_ENDPOINTS_POLICY-v12.12.0.md | php tools/check-public-endpoints-policy.php | Endpoints publicos cobertos |
| GOV-008 | Baseline de autorizacao | docs/security/AUTHORIZATION_DEBT_BASELINE-v12.12.0.json; docs/security/AUTHORIZATION_DEBT_REGISTER-v12.12.0.md | php tools/check-authorization-baseline.php | Divida congelada |
| GOV-009 | Baseline tenant | docs/security/TENANT_FALLBACK_BASELINE-v12.12.0.json; docs/security/TENANT_ISOLATION_REGISTER-v12.12.0.md | php tools/check-tenant-fallbacks.php | Nenhum fallback novo |
| GOV-010 | Registo de opcoes | docs/security/SECRETS_OPTIONS_BASELINE-v12.12.0.json; docs/security/SECRETS_OPTIONS_REGISTER-v12.12.0.md | php tools/check-secrets-options-register.php | Candidatos sensiveis registados |
| GOV-011 | Registo de dependencias | docs/security/EXTERNAL_DEPENDENCIES_BASELINE-v12.12.0.json; docs/security/EXTERNAL_DEPENDENCIES_REGISTER-v12.12.0.md | php tools/check-external-dependencies-register.php | Hosts registados |
| GOV-012 | Integrar gates | tools/run-gates.php | php tools/run-gates.php | Gates antigos e novos verdes |
| GOV-013 | Metadados de release | sige-softgenial.php; BUILD.json; CHANGELOG.md; docs/changelog/CHANGELOG-v12-12-0.txt | php tools/smoke-release-gate.php | Versao sincronizada |
| GOV-014 | Lint PHP | todos os PHP | find ... php -l | 0 erros de sintaxe |
| GOV-015 | Rediagnostico adversarial | docs/governance/ADVERSARIAL_REVIEW-v12.12.0.md | php tools/check-governance-docs.php | P0/P1 zero |
