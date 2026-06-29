# QA - v12.15.8 Alunos Action Menu Layering

## Testes CLI executáveis
- `php -l sige-softgenial.php`
- `php -l includes/ui-kit.php`
- `php -l tools/check-alunos-design-scope-v12-15-8.php`
- `php -l tools/smoke-alunos-action-menu-layering-v12-15-8.php`
- `node --check assets/views/alunos-design-pro.js`
- `php tools/check-alunos-design-scope-v12-15-8.php`
- `php tools/smoke-alunos-action-menu-layering-v12-15-8.php`
- `php tools/smoke-shell-scroll-v12-15-6.php`
- `php tools/smoke-standalone-csp-ui-v12-15-4.php`
- `php tools/check-inline-frontend.php`
- `php tools/run-gates.php`

## Teste visual obrigatório em staging
1. Abrir **Alunos e Matrículas** em desktop.
2. Abrir o menu dos três pontinhos do primeiro card.
3. Mover o cursor sobre o card imediatamente atrás/abaixo.
4. Confirmar que o menu continua acima do card vizinho.
5. Clicar em cada acção visível do menu.
6. Repetir em laptop e tablet horizontal.
7. Confirmar que em mobile o menu continua no fluxo do card, sem cobrir o card seguinte.

## Critério de aprovação
O menu aberto nunca deve ficar por baixo de outro card enquanto estiver aberto.
