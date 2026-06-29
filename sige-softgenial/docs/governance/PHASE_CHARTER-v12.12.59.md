# Carta da fase - v12.12.59 - Fase 4 incr 2 - CSP enforce com nonce nas paginas autonomas

Primeiro CSP enforce com nonce, nas paginas autonomas. Conclui a sequencia de preparacao (nonce em todos os scripts inline na v12.12.55 a v12.12.57, onclick de impressao das autonomas em addEventListener na v12.12.58).

## Objectivo

Impor o primeiro CSP enforce com nonce, nas paginas autonomas, agora que estao preparadas (scripts inline com nonce, sem handlers inline), passando o script-src a self mais nonce por pedido sem bloquear nenhum recurso, e travando a regressao a unsafe-inline com uma asserccao na catraca.

## Incluido

- Motor de documentos: os tres cabecalhos Content-Security-Policy passam o script-src de unsafe-inline para self mais nonce por pedido (style-src e img-src mantidos).
- Modelos PDF de pauta e boletim: cabecalho CSP com nonce no respectivo handler, antes do output.
- Boletim do jardim: cabecalho CSP no topo da vista, protegido por headers_sent.
- Pagina da camara: CSP acrescentado a funcao de cabecalhos da camara (que so corre no pedido da camara), com connect-src self, media-src self e img-src self data https.
- Asserccao na catraca check-inline-frontend: exige script-src com nonce nestas paginas e proibe unsafe-inline no script-src.

## Excluido

- O CSP enforce das vistas admin: ainda tem 149 onclick e 2051 estilos inline; e um esforco grande a parte (converter onclick restantes e externalizar ou autorizar os estilos inline antes de impor o script-src e o style-src).
- Portal publico, ficheiro canonico finance-core, pagina de login (pre-auth) e popup document.write: abordagem propria.

## Riscos

- O script-src estrito bloquear algum recurso. Mitigacao: verificou-se recurso a recurso que estas paginas tem zero handlers inline, zero URLs javascript, scripts inline com nonce e scripts externos servidos da propria origem; o script-src self mais nonce nao bloqueia nada.
- O nonce do cabecalho nao casar com o das tags. Mitigacao: ambos provem de sige_csp_nonce; o esc_attr nao altera o base64; teste confirma igualdade exacta.
- O cabecalho nao poder ser enviado (output ja iniciado). Mitigacao: nos modelos PDF o cabecalho vai no handler antes do output; no jardim e protegido por headers_sent; na camara a funcao ja guarda headers_sent.
- Fuga do CSP para outras paginas. Mitigacao: a funcao da camara guarda o pedido da camara; os handlers so correm na impressao; o jardim e o motor de documentos so emitem nas suas paginas.
- A validacao da camara ser bloqueada. Mitigacao: a validacao vai por fetch para admin-ajax na propria origem, coberta por connect-src self.

## Criterios de aceitacao

- O nonce do cabecalho casa exactamente com o das tags; nenhum recurso das paginas autonomas e bloqueado.
- Os botoes de impressao, o QR e a validacao de crachas continuam a funcionar.
- A catraca exige script-src com nonce nas paginas autonomas e proibe unsafe-inline no script-src; onclick 149, style 2051 e blocos script 7 inalterados.
- Superficie 199, vistas 60, opcoes 132, deps 9. Versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 99 e release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
