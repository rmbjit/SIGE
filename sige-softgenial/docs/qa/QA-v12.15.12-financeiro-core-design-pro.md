# QA - v12.15.12 - Design System PRO: Financeiro Core

## Validações executadas em CLI
- `php -l` nos ficheiros PHP alterados e gates novos.
- `node --check assets/views/financeiro-core-design-pro.js`.
- `tools/check-financeiro-core-design-scope-v12-15-12.php`.
- `tools/check-financeiro-formula-integrity-v12-15-12.php`.
- `tools/smoke-financeiro-core-design-pro-v12-15-12.php`.
- `tools/check-inline-frontend.php`.
- `tools/smoke-shell-scroll-v12-15-6.php`.
- `tools/smoke-alunos-stability-recovery-v12-15-11.php`.
- `tools/run-gates.php`.

## Protecção financeira
A validação principal desta versão é a integridade do PHP financeiro. O gate de hashes confirma que os ficheiros protegidos não foram modificados.

## QA visual recomendado em staging
Testar em desktop, laptop, tablet e mobile:

1. Financeiro Dashboard.
2. Registar pagamento.
3. Pesquisa de aluno em pagamentos.
4. Selecção de dívida e método.
5. Confirmação de pagamento.
6. Devedores.
7. Extratos.
8. Lançamentos.
9. Relatório mensal.
10. Planos.
11. Reconciliação.
12. Aprovações.

## Critério de bloqueio
Qualquer alteração de saldo, valor, dívida, Finance Score, recibo, reconciliação ou fórmula bloqueia a versão.
