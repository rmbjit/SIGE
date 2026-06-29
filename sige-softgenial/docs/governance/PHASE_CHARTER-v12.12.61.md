# Carta da fase - v12.12.61 - Fase 4 incr 2 - uploadDoc e fecho de modais das vistas admin em data-sige-act

Continuacao do incremento 2 da Fase 4 (CSP e front-end seguro): mais onclick seguros das vistas admin para o despachante data-sige-act, continuando a preparar o CSP enforce do shell admin.

## Objectivo

Remover mais handlers inline seguros das vistas admin (uploadDoc com string estatico e fecho de modais jQuery), convertendo-os para o despachante data-sige-act, reduzindo os bloqueadores de um futuro script-src baseado em nonce no shell admin. Comportamento identico; catraca onclick desce de 93 para 79.

## Incluido

- 6 onclick uploadDoc com argumento string estatico passam a data-sige-act mais data-sige-arg.
- 8 onclick de fecho de modais jQuery passam a data-sige-act mais data-sige-arg com o novo wrapper global sigeFecharModalJq.
- Novo wrapper window.sigeFecharModalJq em assets/sige-ui.js, fiel a jQuery(sel).fadeOut(200) e com guarda de jQuery, seguindo o padrao das funcoes globais ja expostas para o despachante.
- Total 14 conversoes em 3 vistas: alunos_lista (3 uploadDoc), equipe-view (3 uploadDoc), turmas-view (8 fecho de modais).
- Aperto da catraca check-inline-frontend: maximo de onclick de 93 para 79.

## Excluido

- switchTab(event, ...): usa event.target para marcar a aba activa e exige refactor da assinatura para receber o elemento; fica para incremento proprio.
- Onclick com codigo inline, jQuery generico fora de fadeOut, e argumentos string via esc_js: incrementos proprios.
- Onclick gerados dentro de blocos script: poupados por mascara dos blocos script.
- O cabecalho CSP do shell admin: so vem depois de tratados os onclick (79) e estilos inline (2051) restantes.

## Riscos

- Mudar o comportamento de um botao uploadDoc. Mitigacao: data-sige-arg passa a mesma cadeia estatica; uploadDoc continua a receber o mesmo valor.
- O fecho de modal nao funcionar. Mitigacao: o wrapper sigeFecharModalJq faz exactamente jQuery(sel).fadeOut(200) e guarda a presenca de jQuery; o despachante chama-o com o mesmo seletor.
- Converter um onclick gerado por JavaScript. Mitigacao: o conversor mascara os blocos script.

## Criterios de aceitacao

- Os botoes uploadDoc convertidos recebem a mesma cadeia; os botoes de fecho de modal fecham o mesmo modal; php -l limpo nas 3 vistas; sige-ui.js passa node -c.
- O despachante data-sige-act mantem o contrato (smoke-inline-frontend verde) e o wrapper novo nao o quebra.
- Os verificadores de JavaScript embebido mantem-se verdes.
- A catraca trava onclick em 79 (sem folga); style 2051 e blocos script 7 inalterados.
- Superficie 199, vistas 60, opcoes 132, deps 9. Versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 99 e release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
