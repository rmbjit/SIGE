# TRACEABILITY MATRIX - v12.12.7

| ID | Requisito | Ficheiros | Teste/Gate | Evidencia |
|---|---|---|---|---|
| SK-001 | Runtime Security Kernel com view_action | includes/security-kernel.php | check-security-kernel.php | Dispatcher `sige_security_kernel_dispatch_view_actions` em admin_init. |
| CAL-001 | Manifesto com view_action | tools/governance-lib.php, ACTION_SURFACE_MANIFEST-v12.12.7.json | check-action-surface-manifest.php | 192 superfícies declaradas. |
| CAL-002 | Zero critical observe | includes/security-kernel-rules.php | check-security-kernel-rules.php | enforce=29, delegated=17, observe=146, critical observe=0. |
| CAL-003 | M-Pesa manual tenant-scoped | includes/payments/mpesa-conciliacao.php | smoke-critical-actions-lockdown-v12-12-7.php | SELECT/UPDATE com `id` + `escola_id`. |
| CAL-004 | Despesas no tenant correcto | admin/finance/financeiro-despesas-view.php | smoke-critical-actions-lockdown-v12-12-7.php | Sem `SELECT escola_id FROM`, com `sige_get_escola_id`. |
| CAL-005 | Pagamentos exigem permissão de escrita | admin/finance/financeiro-pagamentos.php | smoke-critical-actions-lockdown-v12-12-7.php | `financeiro.pagar` antes do processamento. |
| CAL-006 | Permissões por escola | includes/permissions-layer.php, permissions-ui.php | smoke-critical-actions-lockdown-v12-12-7.php | Tabelas escolares e UI por escola. |
| CAL-007 | Arquivar aluno | includes/ajax-handlers.php | smoke-critical-actions-lockdown-v12-12-7.php | Sem DELETE do histórico no handler canónico. |
| CAL-008 | Webhooks em enforce | includes/security-kernel-rules.php | check-security-kernel-rules.php | REST M-Pesa/e-Mola com token validator. |
| SK-018 | Rediagnóstico adversarial | docs/governance/ADVERSARIAL_REVIEW-v12.12.7.md | revisão final | Decisão P0=0/P1=0. |

## Evidencia
Evidencia final: `docs/qa/GATES-v12.12.7.txt`, `docs/qa/PHP-LINT-v12.12.7.json`, `docs/qa/QA-SMOKE-v12-12-7-critical-actions-lockdown.md`.
