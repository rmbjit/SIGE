# Phase Charter - v12.15.6 - Shell, Scroll, Topbar e Sidebar

## Contexto
A v12.15.5 criou a baseline diagnostica e bloqueou a abordagem global agressiva. Esta versao inicia a Fase 11 com uma vaga pequena e reversivel, limitada ao shell principal do SIGE.

## Objectivo
Estabilizar a experiencia base do produto: scroll da area principal, topbar, sidebar e menu lateral em desktop, tablet e telemovel, sem tocar nos cards internos dos modulos.

## Escopo
- Carregar `assets/sige-shell-stability.css` por `wp_enqueue_style`.
- Carregar `assets/sige-shell-stability.js` por `wp_enqueue_script`.
- Definir contrato de scroll para desktop, tablet e mobile.
- Garantir que a sidebar tem scroll proprio e que o footer de terminar sessao permanece acessivel.
- Garantir que tablet e mobile usam scroll natural da pagina quando a sidebar esta fechada.
- Sincronizar estado da sidebar, overlay e `aria-expanded`.
- Actualizar o restore de scroll independente para desktop real a partir de 1101px.
- Adicionar gates anti-regressao para o escopo do shell.

## Nao-escopo
- Modernizar cards de alunos.
- Alterar menus de tres pontinhos ou dropdowns internos dos modulos.
- Normalizar tabelas, formularios, botoes internos, badges ou modais.
- Reintroduzir `sige-design-system-pro.css` ou `sige-design-system-pro.js`.
- Relaxar CSP zero-inline.

## Criterios de aceitacao
- Versao em 12.15.6.
- Assets de shell presentes e enfileirados.
- CSS escopado ao shell, sem selectores globais sobre cards, tabelas ou botoes internos.
- Tablet entre 861px e 1100px nao fica bloqueado por `overflow:hidden` da camada desktop.
- Mobile ate 860px usa scroll natural quando a sidebar esta fechada.
- Sidebar fecha em desktop apos resize e sincroniza overlay e `aria-expanded`.
- Run-gates verde.

## Regra de seguranca da vaga
Qualquer melhoria visual fora do shell fica explicitamente bloqueada nesta versao. Se Alunos, Financeiro, Portaria ou RH mudarem internamente, isso deve ser tratado como regressao.
