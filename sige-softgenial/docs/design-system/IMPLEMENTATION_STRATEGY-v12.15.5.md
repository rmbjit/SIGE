# Estrategia de Implementacao - Design System PRO - v12.15.5

## Principio central
O Design System deve entrar por contrato e por modulo, nao por override global.

## Ordem recomendada

| Versao | Escopo | Tipo |
|---|---|---|
| v12.15.5 | Diagnostico e QA harness | Sem alteracao visual |
| v12.15.6 | Shell, scroll, topbar, sidebar | Fundacao controlada |
| v12.15.7 | Alunos e Matriculas | Modulo critico isolado |
| v12.15.8 | Financeiro: pagamentos, devedores, extratos | Fluxos financeiros |
| v12.15.9 | Portaria e paginas autonomas | CSP e cliente-facing |
| v12.15.10 | RH / Equipe / Professores | Modulo medio risco |
| v12.15.11 | Academico: turmas, disciplinas, notas | Alto volume |
| v12.15.12 | Sistema, permissoes, auditoria | Gestao e seguranca |

## Regras de CSS
- Preferir `assets/views/<view>.css`.
- Escopar por `body.sige-view-*` ou wrapper proprio da view.
- Evitar selectores genericos `button`, `input`, `table`, `.card`, `.modal`.
- Evitar aumento de `!important`.
- Nao usar CSS global para alterar layout de cards existentes.

## Regras de JS
- Nao usar enhancer global que percorre o DOM inteiro.
- Nao transformar botoes/cards/menus automaticamente.
- Usar scripts por view quando a view precisa de comportamento.
- Preservar CSP: sem inline, sem eval, sem new Function.

## Feature flag
Cada vaga visual deve poder ser desligada por modulo, para evitar rollback total do SIGE quando um cliente detectar uma regressao localizada.

## QA visual minimo
Cada vaga que altera layout precisa de evidencias em:
- 1440x900 desktop.
- 1366x768 laptop.
- 1024x768 tablet horizontal.
- 768x1024 tablet vertical.
- 430x932 mobile grande.
- 390x844 mobile medio.
- 360x740 mobile pequeno.
