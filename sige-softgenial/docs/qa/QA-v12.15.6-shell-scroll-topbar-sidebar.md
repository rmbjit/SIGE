# QA / Definition of Done - v12.15.6 - Shell, Scroll, Topbar e Sidebar

## Validacoes executadas

- `php -l sige-softgenial.php`
- `php -l includes/ui-kit.php`
- `php -l includes/admin-shell.php`
- `php -l tools/check-shell-scope-v12-15-6.php`
- `php -l tools/smoke-shell-scroll-v12-15-6.php`
- `node --check assets/sige-shell-stability.js`
- `php tools/check-shell-scope-v12-15-6.php`
- `php tools/smoke-shell-scroll-v12-15-6.php`
- `php tools/check-design-system-safety-contract-v12-15-5.php`
- `php tools/smoke-design-system-diagnostic-v12-15-5.php`
- `php tools/smoke-standalone-csp-ui-v12-15-4.php`
- `php tools/check-inline-frontend.php`
- `php tools/run-gates.php`

## Definition of Done

- [x] Phase Charter criado.
- [x] Inventario tecnico concluido antes da implementacao.
- [x] Plano fechado no escopo shell/topbar/sidebar/scroll.
- [x] Matriz de rastreabilidade preenchida.
- [x] CSP zero-inline preservada.
- [x] Sem JS normalizador global.
- [x] Sem CSS global agressivo de cards, tabelas ou botoes internos.
- [x] Sem relaxar seguranca.
- [x] Lint executado.
- [x] Smoke minimo executado.
- [x] Gates existentes executados.
- [x] CHANGELOG actualizado.
- [x] BUILD.json actualizado.
- [x] Notas de migracao e rollback escritas.
- [x] Rediagnostico adversarial executado.
- [x] Riscos residuais declarados.

## QA visual obrigatorio em staging

1. Desktop 1440x900: scroll da area principal e sidebar.
2. Laptop 1366x768: scroll da area principal e topbar.
3. Tablet horizontal 1024x768: pagina deve rolar naturalmente.
4. Tablet vertical 768x1024: topbar sticky e sidebar off-canvas.
5. Mobile 430x932, 390x844 e 360x740: scroll natural com sidebar fechada.
6. Abrir e fechar sidebar por hamburger, overlay, Escape e clique em menu.
7. Confirmar que Alunos, Financeiro e Portaria nao mudaram internamente.
