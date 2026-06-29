# Phase Charter - v12.15.8 Alunos Action Menu Layering Closure

## Objectivo
Corrigir a regressão residual da v12.15.7 em que o menu vertical dos três pontinhos dos cards de Alunos podia ficar por baixo de um card vizinho durante hover.

## Escopo
- CSS/JS escopado de Alunos.
- Enqueue/handle versionado dos assets de Alunos.
- Gates específicos de layering.

## Não escopo
- Design System dos demais módulos.
- Reformulação de cards fora de Alunos.
- Portaria, Financeiro, PDF/documentos, CSP, shell/sidebar.

## Critério de aceitação
O menu de acções aberto deve permanecer por cima dos cards vizinhos e continuar clicável em desktop/laptop/tablet, preservando comportamento mobile no fluxo do card.
