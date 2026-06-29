# TRACEABILITY MATRIX v12.12.5

| ID | Requisito | Ficheiros | Teste/Gate | Evidencia |
|---|---|---|---|---|
| SKC-001 | Corrigir REST runtime matching | includes/security-kernel.php, includes/security-kernel-rules.php, docs/security/ACTION_SURFACE_MANIFEST-v12.12.5.json | tools/smoke-security-kernel-v12-12-5.php | rest_surface_id_for_route resolve /sige/v1/mpesa/callback. |
| SKC-002 | Adicionar route metadata | tools/governance-lib.php, docs/security/*.json | tools/check-security-kernel-rules.php | Todas rest_route tem route. |
| SKC-003 | Interceptar shortcode | includes/security-kernel.php | tools/smoke-security-kernel-v12-12-5.php | pre_do_shortcode_tag registado e shortcode:sige_portal despachavel. |
| SKC-004 | Interceptar wp_hook | includes/security-kernel.php | tools/smoke-security-kernel-v12-12-5.php | wp_hook:template_redirect/admin_init/send_headers/parse_request registados. |
| SKC-005 | Tenant-scoped M-Pesa | includes/payments/mobile-tenant-options.php, includes/payments/mpesa-config.php | tools/smoke-mobile-tenant-options-v12-12-5.php | update escreve sige_mpesa_escola_7_api_key. |
| SKC-006 | Tenant-scoped e-Mola | includes/payments/mobile-tenant-options.php, includes/payments/emola-config.php | tools/smoke-mobile-tenant-options-v12-12-5.php | update escreve sige_emola_escola_7_api_key. |
| SKC-007 | Webhook resolve escola | includes/payments/mpesa-webhook.php, includes/payments/emola-webhook.php | smoke + grep gate | escola_id vem de token/hint e nao cai para 1 silencioso. |
| SKC-008 | Testes negativos reforcados | tools/check-security-kernel-negative-tests.php | tools/check-security-kernel-negative-tests.php | Mutacoes de REST/shortcode/wp_hook/tenant storage falham. |
| SKC-009 | Integrar gates | tools/run-gates.php | php tools/run-gates.php | Gates oficiais verdes. |
| SKC-010 | Release metadata | sige-softgenial.php, BUILD.json, CHANGELOG.md | tools/smoke-release-gate.php | v12.12.5 sincronizada. |
| SKC-011 | QA evidence | docs/qa/*v12.12.5* | revisao final | Evidencia de lint, gates e smoke. |
| SKC-012 | Rediagnostico adversarial | docs/governance/ADVERSARIAL_REVIEW-v12.12.5.md | revisao final | P0/P1 = 0. |
| SK-001 | Compatibilidade com charter original | includes/security-kernel.php | tools/check-security-kernel.php | Kernel runtime continua presente. |
| SK-018 | Evidencia final | docs/qa/GATES-v12.12.5.txt | php tools/run-gates.php | Evidencia consolidada. |
