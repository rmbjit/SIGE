# QA / Definition of Done - v12.15.5 - Design System PRO Diagnostic Baseline

## Validacoes executadas

- `php -l sige-softgenial.php`
- `php -l tools/inventory-design-system-pro-v12-15-5.php`
- `php -l tools/check-design-system-safety-contract-v12-15-5.php`
- `php -l tools/smoke-design-system-diagnostic-v12-15-5.php`
- `php tools/inventory-design-system-pro-v12-15-5.php`
- `php tools/check-design-system-safety-contract-v12-15-5.php`
- `php tools/smoke-design-system-diagnostic-v12-15-5.php`
- `php tools/smoke-standalone-csp-ui-v12-15-4.php`
- `php tools/smoke-design-system-stability-v12-15-3.php`
- `php tools/check-inline-frontend.php`
- `php tools/run-gates.php`

## Definition of Done

- [x] Phase Charter criado.
- [x] Inventario tecnico concluido antes de qualquer implementacao visual.
- [x] Matriz de rastreabilidade preenchida.
- [x] Baseline JSON criada.
- [x] Estrategia de implementacao incremental documentada.
- [x] Sem novo CSS/JS global de Design System em producao.
- [x] Sem alteracao de layout intencional.
- [x] CSP zero-inline preservada.
- [x] Gates existentes executados.
- [x] Lint executado.
- [x] Smoke minimo executado.
- [x] CHANGELOG actualizado.
- [x] BUILD.json actualizado.
- [x] Notas de migracao e rollback escritas.
- [x] Rediagnostico adversarial executado.
- [x] Riscos residuais declarados.

## QA visual obrigatorio antes de v12.15.6

1. Capturar baseline actual de Alunos, Financeiro, Portaria, Dashboard, RH, Sistema e Academico.
2. Confirmar scroll em desktop, tablet e mobile.
3. Confirmar tres pontinhos e menus de accoes.
4. Confirmar modais de pagamento.
5. Confirmar documentos e Portaria.
6. Guardar screenshots antes de qualquer modernizacao visual.
