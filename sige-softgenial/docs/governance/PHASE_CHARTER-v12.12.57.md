# Carta da fase - v12.12.57 - Fase 4 incr 2 - Nonce CSP nas paginas autonomas

Continuacao do incremento 2 da Fase 4 (CSP e front-end seguro): aplicacao do nonce CSP as tags script ao nivel do template das paginas autonomas. Conclui a aplicacao do nonce a todas as tags script inline alcancaveis (vistas no shell na v12.12.55, redireccionamentos por echo na v12.12.56, paginas autonomas agora).

## Objectivo

Concluir a aplicacao do nonce CSP a todas as tags script inline alcancaveis, cobrindo as paginas autonomas, sem alterar o comportamento (o nonce e ignorado sem CSP que o referencie; o CSP existente do motor de documentos mantem unsafe-inline) e sob a catraca, que desce de 14 para 7 blocos script.

## Incluido

- Conversao de 7 tags script ao nivel do template em 5 ficheiros: pauta-pdf-template (1), boletim-pdf-template (1), jardim_boletim-view (1), documents-engine (3) e portaria-camera-safe-page (1), para a forma com nonce.
- Aperto da catraca check-inline-frontend: maximo de blocos script de 14 para 7.

## Excluido

- O enforcement do CSP nestas paginas. Estas paginas usam onclick inline (botoes de impressao), que um CSP estrito baseado em nonce bloqueia; impor o CSP exige primeiro converter esses onclick. Fica para incremento dedicado, que tratara a conversao desses onclick e o cabecalho CSP (no motor de documentos, substituir unsafe-inline por nonce; nas restantes, acrescentar o cabecalho).
- Portal publico, ficheiro canonico finance-core, pagina de login (pre-auth) e popup document.write: abordagem propria.

## Riscos

- O auxiliar nao estar disponivel onde uma pagina autonoma renderiza. Mitigacao: os templates PDF sao incluidos por handlers (pauta-pdf-handler, boletim-pdf-handler) apos o bootstrap; a vista do jardim e os includes do motor de documentos e da camara renderizam tambem apos o bootstrap, onde o core-helpers ja foi carregado.
- Partir o CSP existente do motor de documentos. Mitigacao: o CSP nao foi tocado; mantem unsafe-inline sem nonce na directiva, pelo que o unsafe-inline continua activo e os scripts e os onclick de impressao continuam a funcionar. Acrescentar o atributo nonce nao altera o comportamento.
- Partir os verificadores de JavaScript embebido (que varrem os modelos PDF em admin). Mitigacao: a tag com nonce e normalizada para extraccao desde a v12.12.55; confirmado verde.

## Criterios de aceitacao

- As 7 tags renderizam como script com nonce, com o mesmo nonce por pedido; php -l limpo nos 5 ficheiros.
- Os cabecalhos de seguranca existentes mantem-se (tres Content-Security-Policy no motor de documentos, Permissions-Policy na camara); os botoes de impressao continuam a funcionar.
- Os verificadores de JavaScript embebido mantem-se verdes.
- A catraca trava blocos script em 7 (sem folga); onclick 162 e style 2051 inalterados.
- Superficie 199, vistas 60, opcoes 132, deps 9. Versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 99 e release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
