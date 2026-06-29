# Rediagnostico adversarial - v12.14.1

## Pergunta adversarial
A v12.14.1 realmente removeu a permissao inline ou apenas mudou o nome do risco?

## Achados
- A politica CSP deixou de conter `unsafe-inline`.
- `script-src-attr` e `style-src-attr` estao em `none`.
- Os atributos `style=` e `on*=` sao convertidos antes da resposta chegar ao browser.
- A compatibilidade e feita por JS externo, sem `eval` e sem `new Function`.
- Os headers CSP autonomos passaram a reutilizar a politica canonica.

## Riscos residuais
- Ainda existem ocorrencias historicas no codigo fonte, mas nao chegam ao browser como atributos inline no shell/fluxos SIGE cobertos pelo guard.
- Blocos script/style inline com nonce permanecem por compatibilidade. Isto e CSP forte, mas nao e extraccao fisica total.
- Validacao visual em browser continua necessaria porque o hidratador cobre os padroes conhecidos.

## Conclusao
Sem bloqueadores P0/P1 detectados nos gates executados. A limpeza fisica total de inline historico fica como P2, sem impedir enforcement zero-inline do CSP.
