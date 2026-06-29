# TRACEABILITY MATRIX v12.12.6

| ID | Requisito | Ficheiros | Testes | Evidencia |
|---|---|---|---|---|
| SK-001 | Kernel com prioridade antecipada | `includes/security-kernel.php` | `tools/check-security-kernel.php` | `SIGE_SECURITY_KERNEL_EARLY_PRIORITY` presente. |
| SK-002 | Query handlers multi-hook | `includes/security-kernel.php`, `includes/security-kernel-rules.php` | `tools/smoke-security-kernel-v12-12-6.php` | admin_init/parse_request/template_redirect @ -1000. |
| SK-003 | `sige_print` passa no kernel | `includes/security-kernel.php` | smoke v12.12.6 | Observed log de `query_handler:sige_print` em admin_init. |
| SK-004 | `sige_portaria_camera` passa no kernel | `includes/security-kernel.php` | smoke v12.12.6 | Observed log em template_redirect. |
| SK-005 | `sige_desp_print` preserva enforce | `includes/security-kernel-rules.php` | smoke v12.12.4/v12.12.6 | Nonce/permissao/tenant/rate/audit. |
| SK-006 | `settings_save` exige tenant | `includes/security-kernel-rules.php` | `tools/smoke-settings-tenant-guard-v12-12-6.php` | `tenant_required=true`. |
| SK-007 | Repository fail-closed | `includes/settings/class-sige-settings-repository.php` | smoke tenant guard | Sem update/insert com tenant ausente. |
| SK-008 | Gates negativos reforcados | `tools/check-security-kernel-negative-tests.php`, `tools/security-kernel-gate-lib.php` | negative tests | Mutacoes falham. |
| SK-009 | Baselines regenerados | `docs/security/*v12.12.6*` | governance docs/checks | JSON valido. |
| SK-010 | Changelog/release metadata | `sige-softgenial.php`, `BUILD.json`, `CHANGELOG.md` | release gate | Versao 12.12.6 sincronizada. |
| SK-011 | QA evidencia | `docs/qa/*v12.12.6*` | run-gates/lint | Evidencia anexada. |
| SK-018 | Rediagnostico adversarial | `docs/governance/ADVERSARIAL_REVIEW-v12.12.6.md` | revisao final | P0/P1 zero. |
