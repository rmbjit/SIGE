# Matriz de Rastreabilidade - v12.15.8

| Requisito | Implementação | Validação |
|---|---|---|
| Menu dos três pontinhos deve permanecer vertical | `assets/views/alunos-design-pro.css` mantém grid 1 coluna para `.sige-actions-menu` | `smoke-alunos-action-menu-layering-v12-15-8.php` |
| Card vizinho não deve sobrepor menu aberto | `sige-card-actions-open`, `sige-alunos-actions-open`, z-index 1080/1100 | `check-alunos-design-scope-v12-15-8.php` |
| Menu deve continuar clicável | `pointer-events:auto` no menu aberto | `check-alunos-design-scope-v12-15-8.php` + staging |
| Escopo limitado a Alunos | selector `body.sige-admin-app.sige-view-alunos_lista` e enqueue por `view=alunos_lista` | `check-alunos-design-scope-v12-15-8.php` |
| CSP forte preservada | sem `unsafe-inline`, `eval` ou `new Function` | `check-inline-frontend.php` e smoke específico |
