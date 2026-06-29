# Rediagnóstico Adversarial - v12.14.0 - Front-end Security & CSP Enforcement

## Pergunta adversarial 1: a CSP é mesmo enforcement?
Sim. O shell admin passa a enviar `Content-Security-Policy` e o gate rejeita regressão para `Content-Security-Policy-Report-Only`.

## Pergunta adversarial 2: `script-src` ainda permite `unsafe-inline`?
Não no `script-src` principal. O `script-src` e `script-src-elem` usam `'self'` e nonce por pedido. O gate rejeita `script-src 'self' 'unsafe-inline'`.

## Pergunta adversarial 3: há ainda inline no sistema?
Sim. Ainda existem `style="..."` e alguns handlers `on*` legados. Para não quebrar a interface, a política mantém:
- `script-src-attr 'unsafe-inline'` como compatibilidade temporária.
- `style-src 'self' 'unsafe-inline'` como compatibilidade temporária.

## Classificação
- P0/P1: nenhum conhecido no escopo entregue, porque `script-src` de blocos/scripts do shell já opera com nonce e enforcement.
- P2: dívida técnica residual de `script-src-attr` e `style-src` inline, exigindo vaga posterior de refactor por módulo.

## Pergunta adversarial 4: a remoção de CDN é completa?
Parcial. Google Fonts foi removido do login e portal. As bibliotecas críticas já presentes em `assets/vendor` continuam self-hosted. Qualquer dependência externa remanescente deve continuar sob o gate específico de dependências externas.

## Pergunta adversarial 5: houve alteração de regra de negócio?
Não. A fase mexe na camada de execução front-end/CSP e não altera cálculo financeiro, schema, tenant ownership, permissões ou regras académicas.

## Riscos residuais
1. Uma view legada pode ainda depender de atributo `onchange/oninput/onkeyup/onsubmit`, por isso `script-src-attr 'unsafe-inline'` ainda não pode ser removido sem vaga dedicada.
2. O volume de `style="..."` obriga `style-src 'unsafe-inline'` até migração CSS modular.
3. A validação final de browser autenticado deve ser feita em staging, com DevTools aberto, porque o ambiente CLI não executa a interface real.

## Achado adicional de gate herdado
Antes do fecho, o gate de consistencia visual falhava tambem na fonte v12.12.64 original por baseline dimensional desactualizada. Foi confirmado que as contagens actuais eram iguais as da fonte original e que o total ja estava abaixo da baseline antiga. A baseline foi sincronizada para o minimo real actual, sem alterar estilos ou layout.
