# TRACEABILITY MATRIX v12.12.2

| ID | Requisito | Ficheiros | Teste | Evidencia |
|---|---|---|---|---|
| GOV-001 | Preservar inventario de governacao | `tools/inventory-surface.php`, `docs/governance/INVENTORY_SUMMARY-v12.12.2.json` | `php tools/inventory-surface.php --check` | Inventario OK em `docs/qa/GATES-v12.12.2.txt`. |
| EXT-001 | Melhorar documento de divida | `includes/documents-engine.php` | `tools/smoke-devedores-extracto-detalhado-v12-12-2.php` | Titulo `EXTRATO DE DÍVIDA DETALHADO` e secoes detalhadas validadas. |
| EXT-002 | Usar formula canonica | `includes/documents-engine.php` | Smoke dedicado | Helper chama `sige_fin_total_lancamento` e `sige_fin_saldo_lancamento`. |
| EXT-003 | Mostrar decomposicao por item | `includes/documents-engine.php` | Smoke dedicado | Base, transporte, extras, multa, descontos, desconto especial, pago e saldo presentes. |
| EXT-004 | Incluir `em_plano` | `includes/documents-engine.php` | Smoke dedicado | Query contem `status IN ('pendente','parcial','em_plano')`. |
| EXT-005 | Mostrar pagamentos abatidos | `includes/documents-engine.php` | Smoke dedicado | Consulta `sige_fin_pagamentos` por `lancamento_id`; inclui recibos e ultimo pagamento. |
| EXT-006 | Melhorar botoes | `admin/finance/financeiro-devedores-view.php` | Smoke dedicado | Labels `Extracto dívida` e `Histórico` presentes; label antigo `Factura` removido da lista. |
| EXT-007 | Evitar side effects | `includes/documents-engine.php` | Smoke dedicado | Ausencia de `sige_fin_recalcular_lancamento` no documento. |
| EXT-008 | Garantir tenant | `includes/documents-engine.php` | Smoke dedicado + `tools/check-tenant-sensitive-queries.php` | Queries com `aluno_id` e `escola_id`; gates de tenant verdes. |
| EXT-009 | Adicionar nonce nos links novos | `admin/finance/financeiro-devedores-view.php` | Smoke dedicado | `wp_nonce_url()` presente para impressao a partir da Central de Devedores. |
| EXT-010 | Integrar gate dedicado | `tools/run-gates.php` | `php tools/run-gates.php` | Gate `Extracto de dívida detalhado` incluido; 28/28 gates verdes. |
| EXT-011 | Estabilizar gate JS sem mascarar erros | `tools/run-gates.php`, `tools/check-js-views.php` | `php tools/run-gates.php` | Verificador JS executado por 14 lotes isolados; 58 views e 65 blocos verificados. |
| QA-001 | Executar lint PHP global | Todos os PHP | `find . -name '*.php' -print0 \| xargs -0 -n1 -P8 php -l` | `docs/qa/PHP-LINT-v12.12.2.json`: 332 ficheiros, 0 falhas. |
| QA-002 | Executar gates oficiais | Todos os gates | `php tools/run-gates.php` | `docs/qa/GATES-v12.12.2.txt`: 28/28 gates verdes. |
| GOV-015 | Fecho sem P0/P1 | `docs/governance/ADVERSARIAL_REVIEW-v12.12.2.md` | Revisao adversarial | P0=0, P1=0. |
