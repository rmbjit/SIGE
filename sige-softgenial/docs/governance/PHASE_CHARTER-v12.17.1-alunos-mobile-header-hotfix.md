# Phase Charter - v12.17.1 Alunos Mobile Header Hotfix

## Objectivo

Corrigir regressao visual localizada no cabecalho mobile do modulo Alunos, onde a foto de perfil era cortada e o chip do ano lectivo competia com o avatar na area direita do header.

## Escopo incluido

- CSS escopado a body.sige-admin-app.sige-view-alunos_lista.
- Apenas media query mobile ate 760px.
- Ocultar chip do ano lectivo no header mobile de Alunos.
- Reservar coluna fixa para avatar.
- Garantir box sizing e overflow seguro da foto.

## Escopo excluido

- Financeiro.
- Academico.
- Permissoes.
- Schema.
- Dados historicos.
- Portaria.
- Shell global.
- Regras de negocio.

## Riscos principais

| Risco | Gravidade | Mitigacao |
|---|---:|---|
| Alterar header global sem necessidade | P1 | Patch restrito a sige-view-alunos_lista |
| Esconder informacao importante no mobile | P3 | Ano lectivo continua disponivel fora do header compacto |
| Cortar avatar em larguras pequenas | P2 | Coluna fixa 42px e 40px em telas estreitas |
| Regressao desktop | P2 | Patch limitado a max-width 760px |

## Definition of Done

- Header mobile de Alunos nao mostra chip do ano lectivo.
- Avatar aparece inteiro.
- Sem erro fatal.
- Sem tela branca.
- Sem regressao em Alunos desktop.
- Gates v12.17.1 verdes.
