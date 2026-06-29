# QA - v12.15.7 - Design System PRO: Alunos e Matrículas

## Gates executados
- `php -l sige-softgenial.php`
- `php -l includes/ui-kit.php`
- `php -l tools/check-alunos-design-scope-v12-15-7.php`
- `php -l tools/smoke-alunos-design-pro-v12-15-7.php`
- `node --check assets/views/alunos-design-pro.js`
- `php tools/check-alunos-design-scope-v12-15-7.php`
- `php tools/smoke-alunos-design-pro-v12-15-7.php`
- `php tools/check-inline-frontend.php`
- `php tools/smoke-shell-scroll-v12-15-6.php`
- `php tools/smoke-standalone-csp-ui-v12-15-4.php`
- `php tools/run-gates.php`

## QA visual obrigatório em staging
Esta versão altera uma página visualmente; por isso, gates CLI não substituem browser autenticado.

### Viewports mínimos
- 1440x900
- 1366x768
- 1024x768
- 768x1024
- 430x932
- 390x844
- 360x740

### Checklist
- Scroll da área principal funciona.
- Cards não comprimem texto letra por letra.
- Três pontinhos abrem verticalmente.
- Em mobile, menu não cobre o card seguinte.
- Botões rápidos mobile não aparecem no desktop.
- Filtros mobile abrem/fecham e preservam espaço.
- Ficha/modal do aluno mantém scroll interno.
- Editar aluno abre.
- Ficha 360º abre.
- Histórico financeiro abre.
- Sem violações CSP bloqueantes no DevTools.
