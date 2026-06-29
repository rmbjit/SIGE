# Carta da fase - v12.12.58 - Fase 4 incr 2 - onclick de impressao das autonomas em addEventListener

Continuacao do incremento 2 da Fase 4 (CSP e front-end seguro): conversao dos onclick de impressao das paginas autonomas para addEventListener, preparando o caminho para o CSP enforce nessas paginas.

## Objectivo

Remover os handlers inline (onclick) das paginas autonomas, convertendo-os para addEventListener local, para que um CSP estrito baseado em nonce os nao bloqueie, abrindo caminho ao CSP enforce nessas paginas. Comportamento identico; catraca onclick desce de 162 para 149.

## Incluido

- Conversao de 13 handlers em 4 paginas: pauta-pdf-template (2), boletim-pdf-template (2), jardim_boletim-view (2) e documents-engine (7).
- Botoes passam a usar atributos data (data-sige-print, data-sige-close, data-sige-print-all, data-sige-print-one); a ligacao addEventListener corre dentro do script ja com nonce de cada pagina, ao nivel de topo (fora dos IIFE de QRious).
- No imprimir um boletim, o indice e passado por atributo data e reconvertido a numero com parseInt, preservando o tipo esperado pela funcao.
- Aperto da catraca check-inline-frontend: maximo de onclick de 162 para 149.

## Excluido

- O CSP enforce em si (substituir unsafe-inline por nonce no motor de documentos, acrescentar cabecalho CSP as restantes paginas autonomas). Incremento seguinte, agora que estas paginas ja nao tem handlers inline.
- A pagina da camara da portaria nao tinha handlers inline, pelo que nao foi alterada nesta vaga.
- Portal publico, ficheiro canonico finance-core, pagina de login (pre-auth) e popup document.write: abordagem propria.

## Riscos

- A ligacao nao correr (botoes ficarem inertes). Mitigacao: a ligacao corre ao nivel de topo do script, com o script depois dos botoes no DOM; confirmado php -l limpo e verificadores de JS embebido verdes.
- A ligacao nao correr quando falta a biblioteca de QR. Mitigacao: a ligacao esta fora dos IIFE de QRious, que retornam cedo por falta da biblioteca; corre sempre.
- Mudar o tipo do indice no imprimir um boletim. Mitigacao: reconversao a numero com parseInt, que a funcao espera (testa typeof numero).
- Partir cabecalhos de seguranca existentes. Mitigacao: nenhum cabecalho foi tocado.

## Criterios de aceitacao

- Os botoes continuam a imprimir, fechar e imprimir boletins como antes; php -l limpo nos 4 ficheiros.
- Os cabecalhos de seguranca existentes mantem-se (tres Content-Security-Policy no motor de documentos, Permissions-Policy na camara).
- Os verificadores de JavaScript embebido mantem-se verdes.
- A catraca trava onclick em 149 (sem folga); style 2051 e blocos script 7 inalterados.
- Superficie 199, vistas 60, opcoes 132, deps 9. Versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 99 e release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
