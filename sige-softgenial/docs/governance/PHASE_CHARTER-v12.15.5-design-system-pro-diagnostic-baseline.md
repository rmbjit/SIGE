# Phase Charter - v12.15.5 - Design System PRO Diagnostic Baseline

## Contexto
A tentativa inicial de Design System PRO provocou regressões visuais e funcionais em Alunos, scroll, menus de accoes e paginas autonomas. A v12.15.4 estabilizou CSP, Portaria e documentos. Esta fase reabre a Fase 11 de modo seguro: diagnostico primeiro, implementacao visual depois.

## Objectivo
Criar uma baseline tecnica e de QA para que as proximas vagas de Design System, UX e responsividade sejam incrementais, escopadas por modulo, reversiveis e verificaveis.

## Escopo
- Inventario tecnico visual da base v12.15.4.
- Contrato anti-regressao para scroll, accoes, paginas autonomas, CSP e assets globais.
- Matriz de risco por modulo.
- Estrategia de rollout incremental por view/modulo.
- Checklist de QA visual em desktop, laptop, tablet e mobile.
- Gates CLI que impedem reintroducao da camada global agressiva.

## Nao-escopo
- Alterar layout de producao.
- Reintroduzir `sige-design-system-pro.css` ou `sige-design-system-pro.js` como camada global.
- Normalizar automaticamente botoes, cards, tabelas ou menus por JS global.
- Resolver toda a divida de `style=`, `on*=` e `!important` nesta versao.

## Criterios de aceitacao
- Versao actualizada para 12.15.5.
- Baseline JSON gerada em `docs/design-system/`.
- Inventario tecnico e matriz de risco documentados.
- Gates novos adicionados ao corredor principal.
- Zero assets de layout novos carregados em producao.
- CSP zero-inline preservada.
- Run-gates verde.

## Regras de seguranca para as proximas vagas
1. Nenhum CSS/JS global agressivo entra em producao.
2. Cada modernizacao deve ser limitada por body class, view class ou asset por view.
3. Nenhum modulo fora do escopo pode mudar visualmente sem declaracao.
4. Cada vaga deve ter rollback de modulo.
5. QA visual em browser autenticado e obrigatorio para fechar qualquer alteracao de layout.
