# QA / Definition of Done - v12.15.3

## Executado
- [x] Phase Charter criado.
- [x] Inventário técnico criado.
- [x] Matriz de rastreabilidade criada.
- [x] CHANGELOG e BUILD.json actualizados.
- [x] Notas de migração e rollback criadas.
- [x] Rediagnóstico adversarial criado.
- [x] Smoke específico v12.15.3 criado.
- [x] Lint PHP executado.
- [x] Gates existentes executados.
- [x] CSP preservada sem relaxamento.

## Gates esperados
- `php -l sige-softgenial.php`
- `php -l admin/finance/financeiro-pagamentos.php`
- `php tools/smoke-design-system-stability-v12-15-3.php`
- `php tools/smoke-csp-enforcement-v12-14-0.php`
- `php tools/check-inline-frontend.php`
- `php tools/run-gates.php`

## Validação manual obrigatória em staging
- Alunos e Matrículas: scroll, cards, três pontinhos e acções.
- Financeiro > Pagamentos: pesquisa, selecção de dívida, método, confirmação.
- Página longa em tablet: scroll vertical.
- Mobile: menu lateral e fluxo de pagamento.
