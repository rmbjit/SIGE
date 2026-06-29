# Design System Shell Contract - v12.15.6

## Principio
Esta vaga so pode actuar sobre o casco do produto: shell, scroll, topbar e sidebar. O interior das paginas fica fora do escopo.

## Selectores permitidos
- `body.sige-admin-app #sige-layout.sg-product-pro-shell`
- `.sg-product-pro-shell .sg-app-content`
- `.sg-product-pro-shell .sg-app-page`
- `.sg-product-pro-shell .sg-app-topbar`
- `.sg-product-pro-shell .sg-app-topbar-*`
- `.sg-product-pro-shell .sg-app-sidebar`
- `.sg-product-pro-shell .sg-app-sidebar-*`
- `.sg-product-pro-shell .sg-app-nav`
- `.sg-product-pro-shell .sige-menu-item`
- `.sg-product-pro-shell .sg-app-overlay`
- `.sg-product-pro-shell .sg-app-hamburger`

## Selectores proibidos nesta vaga
- `.sige-alunos-card`
- `.sige-aluno-card`
- `.sige-student-card`
- `.sg-aluno-*`
- `.sg-pay*`
- `.sg-fin*`
- `.sg-card`
- `.sige-card`
- `.button`
- `button`
- `table`
- `input`
- `select`
- `textarea`

## Breakpoints
- Desktop real: `min-width: 1101px`.
- Tablet: `min-width: 861px` e `max-width: 1100px`.
- Mobile: `max-width: 860px`.

## Contrato de scroll
Desktop real usa scroll independente em `.sg-app-content`. Tablet e mobile usam scroll natural da pagina, salvo quando a sidebar esta aberta.

## Contrato de sidebar
A sidebar pode bloquear o body apenas quando `body.sg-app-menu-open` esta presente. Ao fechar sidebar, o body deve voltar a rolar.
