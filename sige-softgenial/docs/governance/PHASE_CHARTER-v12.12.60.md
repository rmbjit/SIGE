# Carta da fase - v12.12.60 - Fase 4 incr 2 - onclick seguros das vistas admin em data-sige-act

Continuacao do incremento 2 da Fase 4 (CSP e front-end seguro): conversao dos onclick seguros das vistas admin para o despachante data-sige-act, preparando o CSP enforce do shell admin.

## Objectivo

Remover os handlers inline seguros das vistas admin, convertendo-os para o despachante data-sige-act, reduzindo os bloqueadores de um futuro script-src baseado em nonce no shell admin. Comportamento identico; catraca onclick desce de 149 para 93.

## Incluido

- 52 onclick sem argumentos (fn() vira data-sige-act mais data-sige-noargs).
- 4 onclick com um inteiro (fn(<?php echo (int)id ?>) vira data-sige-act mais data-sige-args com a lista JSON, que preserva o tipo numero).
- Total 56 conversoes em 8 vistas: alunos_lista (17 sem-arg, 4 inteiro), turmas-view (10), financeiro-extratos (10), equipe-view (8), dec-view (2), estatisticas-demograficas-view (2), pauta-final-view (2), boletim-view (1).
- Aperto da catraca check-inline-frontend: maximo de onclick de 149 para 93.

## Excluido

- Onclick com event (switchTab), com codigo inline (sessionStorage, location.reload, condicoes), com jQuery inline (fadeOut), com argumentos string via esc_js: ficam para incrementos proprios, por exigirem refactor ou codificacao cuidada dos argumentos.
- Onclick gerados dentro de blocos script (HTML montado por JavaScript): expressamente poupados por mascara dos blocos script no conversor.
- O cabecalho CSP do shell admin: so vem depois de tratados os onclick e estilos inline restantes. As vistas admin ainda tem 93 onclick e 2051 estilos inline.
- Portal publico, ficheiro canonico finance-core e pagina de login: abordagem propria.

## Riscos

- Mudar o comportamento de um botao. Mitigacao: usa-se o despachante data-sige-act, ja em producao e validado pelos gates; o despachante chama a mesma funcao com os mesmos argumentos.
- Mudar o tipo de um argumento inteiro. Mitigacao: data-sige-args interpreta a lista JSON preservando o tipo numero.
- Converter um onclick gerado por JavaScript. Mitigacao: o conversor mascara os blocos script, poupando-os.
- Converter um onclick que dependia de event ou de codigo inline. Mitigacao: esses padroes ficaram fora do subconjunto seguro e nao foram tocados.

## Criterios de aceitacao

- Os botoes convertidos continuam a chamar a mesma funcao com os mesmos argumentos; php -l limpo nas 8 vistas.
- O despachante data-sige-act mantem o contrato (smoke-inline-frontend verde).
- Os verificadores de JavaScript embebido mantem-se verdes.
- A catraca trava onclick em 93 (sem folga); style 2051 e blocos script 7 inalterados.
- Superficie 199, vistas 60, opcoes 132, deps 9. Versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 99 e release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
