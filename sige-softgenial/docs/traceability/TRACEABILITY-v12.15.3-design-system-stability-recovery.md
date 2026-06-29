# Matriz de Rastreabilidade - v12.15.3

| Requisito | Implementação | Teste |
|---|---|---|
| Recuperar estabilidade visual | Base v12.14.4 restaurada para UI Kit; sem enqueue PRO global | `tools/smoke-design-system-stability-v12-15-3.php` |
| Não reintroduzir `unsafe-inline` | CSP e assets da v12.14.4 preservados | `tools/smoke-csp-enforcement-v12-14-0.php`, `tools/check-inline-frontend.php` |
| Impedir confirmação de pagamento inválida | Guard JS em `financeiro-pagamentos.php` | `php -l`, smoke v12.15.3 |
| Manter gates existentes | `tools/run-gates.php` com smoke v12.15.3 | `php tools/run-gates.php` |
| Documentar rollback | Notas de migração/rollback v12.15.3 | Revisão documental |
