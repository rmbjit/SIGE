# TRACEABILITY MATRIX v12.12.3

| ID | Requisito | Ficheiros | Teste | Evidencia |
|---|---|---|---|---|
| GOV-001 | Preservar inventario de governacao | `tools/inventory-surface.php`, `docs/governance/INVENTORY_SUMMARY-v12.12.3.json` | `php tools/inventory-surface.php --check` | Inventario OK em `docs/qa/GATES-v12.12.3.txt`. |
| BETA-001 | Marcar financeiro-mpesa como Beta | `includes/ui-components.php` | `tools/smoke-beta-views-v12-12-3.php` | Catalogo contem `status=beta` e `beta_note`. |
| BETA-002 | Marcar whatsapp_circulares como Beta | `includes/ui-components.php` | `tools/smoke-beta-views-v12-12-3.php` | Catalogo contem `status=beta` e `beta_note`. |
| BETA-003 | Marcar comunicacoes_central como Beta | `includes/ui-components.php` | `tools/smoke-beta-views-v12-12-3.php` | Catalogo contem `status=beta` e `beta_note`. |
| BETA-004 | Marcar presencas como Beta | `includes/ui-components.php` | `tools/smoke-beta-views-v12-12-3.php` | Catalogo contem `status=beta` e `beta_note`. |
| BETA-005 | Renderizar selo e nota Beta | `includes/ui-components.php`, `assets/style.css` | Smoke Beta | Renderizador e CSS presentes. |
| BETA-006 | Preservar router | `includes/admin-shell.php` | Smoke Beta | Rotas dos quatro views preservadas. |
| BETA-007 | Integrar gate | `tools/run-gates.php` | `php tools/run-gates.php` | Gate `Views beta` incluido no corredor oficial. |
| QA-001 | Executar lint PHP completo | Todos os ficheiros PHP | `php -l` em 334 ficheiros PHP | `docs/qa/PHP-LINT-v12.12.3.json`: sem falhas. |
| QA-002 | Executar gates oficiais | Todos os gates | `php tools/run-gates.php` completo | `docs/qa/GATES-v12.12.3.txt`: 29/29 gates verdes. |
| GOV-015 | Fecho sem P0/P1 | `docs/governance/ADVERSARIAL_REVIEW-v12.12.3.md` | Revisao adversarial | P0=0, P1=0. |
