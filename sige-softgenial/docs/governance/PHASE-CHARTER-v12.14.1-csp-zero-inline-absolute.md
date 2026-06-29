# Phase Charter - v12.14.1 - CSP Zero-Inline Absolute Guard

## Objectivo
Fechar a lacuna residual da v12.14.0, removendo permissoes inline do CSP e garantindo que handlers e estilos por atributo nao sejam necessarios para a operacao do shell SIGE.

## Escopo
- Politica CSP canonica sem `unsafe-inline`.
- `script-src-attr 'none'`.
- `style-src-attr 'none'`.
- Sanitizacao central de `style=` e `on*=` na resposta HTML.
- Hidratacao por JS externo versionado.
- Headers CSP autonomos alinhados com a politica canonica.
- Gates actualizados para bloquear regressao.

## Nao-escopo
- Reescrita fisica de todas as 2.000+ ocorrencias historicas de `style=` no codigo fonte.
- Extraccao completa de todos os blocos CSS/JS inline legados para ficheiros por view.
- Alteracao de schema ou regras de negocio.

## Riscos
- Algum handler legado muito especifico pode exigir mapeamento adicional no hidratador.
- Estilos sao aplicados por CSSOM depois do carregamento, podendo causar micro flash visual em maquinas lentas.

## Criterio de aceitacao
- Nenhum ficheiro de producao contem `unsafe-inline`.
- CSP efectivo bloqueia atributos inline.
- Gates passam.
- Staging confirma interface operacional sem violacoes CSP criticas.
