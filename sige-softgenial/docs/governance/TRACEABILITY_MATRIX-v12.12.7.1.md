# TRACEABILITY MATRIX - v12.12.7.1

| ID | Requisito | Ficheiros | Teste/Gate | Evidencia |
|---|---|---|---|---|
| SK-001 | Runtime Security Kernel despacha query handlers | includes/security-kernel.php | check-security-kernel.php | Dispatcher de query handlers em admin_init, parse_request e template_redirect a -1000. |
| DEV-001 | Botao Imprimir lista (PDF) na Central de Cobrancas | admin/finance/financeiro-devedores-view.php | smoke-devedores-pdf-v12-12-7-1.php | `add_query_arg(['sige_dev_print'=>'lista'])` com `wp_nonce_url`. |
| DEV-002 | Query handler protegido (nonce) | includes/finance-devedores-pdf.php | smoke-devedores-pdf-v12-12-7-1.php | `wp_verify_nonce($nonce,'sige_dev_print')`. |
| DEV-003 | Permissao SIGE com fallback de papeis | includes/finance-devedores-pdf.php | smoke-devedores-pdf-v12-12-7-1.php | `sige_can('financeiro.cobrancas_ver',...)` e fallback. |
| DEV-004 | Tenant fail-closed | includes/finance-devedores-pdf.php | smoke-devedores-pdf-v12-12-7-1.php | `escola_id <= 0` faz wp_die antes de qualquer leitura. |
| DEV-005 | Total igual ao ecra (fonte de verdade) | includes/finance-devedores-pdf.php | smoke-devedores-pdf-v12-12-7-1.php | `sige_fin_saldo_lancamento()` sobre lancamentos em aberto, populacao de activos, sem fan-out. |
| DEV-006 | Leitura pura, sem escrita financeira | includes/finance-devedores-pdf.php | smoke-devedores-pdf-v12-12-7-1.php | Sem INSERT/UPDATE/DELETE em tabelas financeiras. |
| DEV-007 | Regra no Kernel em enforce com runtime antecipado | includes/security-kernel-rules.php | check-security-kernel-rules.php | `query_handler:sige_dev_print` mode=enforce, runtime_hooks completos. |
| DEV-008 | Manifesto e Kernel alinhados | ACTION_SURFACE_MANIFEST-v12.12.7.1.json, SECURITY_KERNEL_RULES-v12.12.7.1.json | check-action-surface-manifest.php, check-security-kernel-rules.php | 193 superficies = 193 regras. |
| SK-018 | Rediagnostico adversarial | docs/governance/ADVERSARIAL_REVIEW-v12.12.7.1.md | revisao final | Decisao P0=0/P1=0. |

## Evidencia
Evidencia final: `docs/qa/GATES-v12.12.7.1.txt`, `docs/qa/PHP-LINT-v12.12.7.1.json`, `docs/qa/QA-SMOKE-v12-12-7-1-mapa-cobranca-pdf.md`.
