# Traceability Matrix v12.12.4

| ID | Requisito | Ficheiros | Teste/Gate | Evidencia |
|---|---|---|---|---|
| GOV-001 | Inventario tecnico actualizado | `tools/inventory-surface.php`, `docs/governance/INVENTORY_SUMMARY-v12.12.4.json` | `php tools/inventory-surface.php` | 174 superficies inventariadas. |
| SK-001 | Criar runtime do kernel | `includes/security-kernel.php` | `tools/check-security-kernel.php` | Runtime e bootstrap presentes. |
| SK-002 | Criar regras operacionais | `includes/security-kernel-rules.php`, `docs/security/SECURITY_KERNEL_RULES-v12.12.4.json` | `tools/check-security-kernel-rules.php` | 174 regras cobrem manifesto. |
| SK-003 | Enforcement M-Pesa/e-Mola | `includes/security-kernel-rules.php` | `tools/smoke-security-kernel-v12-12-4.php` | 2 regras admin_post em enforce. |
| SK-004 | Enforcement `sige_desp_print` | `includes/security-kernel.php`, `includes/security-kernel-rules.php` | smoke + query-handler gate | query_handler em enforce com nonce/tenant. |
| SK-005 | Enforcement parcial settings | `includes/security-kernel-rules.php` | smoke | `authorization_mode=delegated`. |
| SK-006 | Testes negativos | `tools/check-security-kernel-negative-tests.php` | gate dedicado | mutacoes adversariais detectadas. |
| SK-007 | Integrar no corredor | `tools/run-gates.php` | `php tools/run-gates.php` | Gates antigos + novos verdes. |
| GOV-015 | Rediagnostico adversarial | `docs/governance/ADVERSARIAL_REVIEW-v12.12.4.md` | revisao final | P0=0/P1=0 declarado. |
| SK-018 | Zero P0/P1 aberto | `docs/governance/RISK_REGISTER-v12.12.4.md`, `docs/governance/ADVERSARIAL_REVIEW-v12.12.4.md` | rediagnostico + run-gates | Decisao final sem P0/P1. |

## Evidencia
A evidencia final deve incluir `docs/qa/GATES-v12.12.4.txt`, `docs/qa/PHP-LINT-v12.12.4.json` e smoke do Security Kernel.
