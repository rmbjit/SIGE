# TRACEABILITY MATRIX - v12.12.1

| ID | Requisito | Ficheiros | Teste/Gate | Evidencia |
|---|---|---|---|---|
| GOV-001 | Inventario tecnico correctivo | `tools/inventory-surface.php`; `docs/governance/TECHNICAL_INVENTORY-v12.12.1.md` | `php tools/inventory-surface.php --check` | Superficie 174, inclui `query_handler` e `wp_hook`. |
| GOV-002 | Phase Charter | `docs/governance/PHASE_CHARTER-v12.12.1.md` | `php tools/check-governance-docs.php` | Documento com Objectivo, Incluido, Excluido, Riscos. |
| GOV-003 | Definition of Done | `docs/governance/DEFINITION_OF_DONE-v12.12.1.md` | `php tools/check-governance-docs.php` | DoD-001 ate DoD-015. |
| GOV-004 | Matriz de rastreabilidade | `docs/governance/TRACEABILITY_MATRIX-v12.12.1.md` | `php tools/check-governance-docs.php` | GOV-001 ate GOV-015. |
| GOV-005 | Corrigir P0 despesas | `includes/finance-core.php`; `admin/finance/financeiro-despesas-view.php` | `php tools/check-tenant-sensitive-queries.php` | `sige_desp_print` com escola_id, nonce e auditoria. |
| GOV-006 | Manifesto adversarial | `tools/governance-lib.php`; `tools/check-action-surface-manifest.php`; `docs/security/ACTION_SURFACE_MANIFEST-v12.12.1.json` | `php tools/check-action-surface-manifest.php` | 174 superficies declaradas; extractor primario e independente alinhados. |
| GOV-007 | query handlers criticos | `tools/check-query-handler-surface.php` | `php tools/check-query-handler-surface.php` | `sige_desp_print`, `sige_recibo`, `sige_print`, `sige_portaria_camera` cobertos. |
| GOV-008 | Endpoints publicos | `docs/security/PUBLIC_ENDPOINTS_POLICY-v12.12.1.md` | `php tools/check-public-endpoints-policy.php` | 8 superficies publicas documentadas. |
| GOV-009 | Baseline de autorizacao | `docs/security/AUTHORIZATION_DEBT_BASELINE-v12.12.1.json` | `php tools/check-authorization-baseline.php` | Divida congelada. |
| GOV-010 | Baseline tenant | `docs/security/TENANT_FALLBACK_BASELINE-v12.12.1.json` | `php tools/check-tenant-fallbacks.php` | Nenhum fallback novo nao registado. |
| GOV-011 | Registo de opcoes | `docs/security/SECRETS_OPTIONS_BASELINE-v12.12.1.json` | `php tools/check-secrets-options-register.php` | Candidatos sensiveis registados. |
| GOV-012 | Registo de dependencias | `docs/security/EXTERNAL_DEPENDENCIES_BASELINE-v12.12.1.json` | `php tools/check-external-dependencies-register.php` | Hosts registados. |
| GOV-013 | Testes negativos | `tools/check-governance-negative-tests.php` | `php tools/check-governance-negative-tests.php` | Detectores adversariais exercitados. |
| GOV-014 | Gates integrados | `tools/run-gates.php` | `php tools/run-gates.php` | Gates antigos e correctivos verdes. |
| GOV-015 | Rediagnostico adversarial | `docs/governance/ADVERSARIAL_REVIEW-v12.12.1.md` | `php tools/check-governance-docs.php` | P0/P1 zero, riscos residuais declarados. |
