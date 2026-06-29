# Carta da fase - v12.12.56 - Fase 4 incr 2 - Nonce CSP nos redireccionamentos echo

Continuacao do incremento 2 da Fase 4 (CSP e front-end seguro): aplicacao do nonce CSP aos redireccionamentos construidos por echo nas vistas autenticadas. Continua a vaga v12.12.55 (nonce nas tags script ao nivel do template) e usa a mesma infraestrutura de nonce do core-helpers.

## Objectivo

Estender o nonce CSP aos redireccionamentos construidos por echo (echo de uma tag script com window.location ou location) nas vistas autenticadas, sem alterar o comportamento (o nonce e ignorado sem cabecalho CSP) e sob a catraca, que desce de 25 para 14 blocos script.

## Incluido

- Conversao por concatenacao em string de 11 redireccionamentos em 6 vistas: dashboard (2), disciplinas (2, com aspas duplas), encerramento (2), financeiro-config (3), notas (1) e transporte (1).
- Tratamento dos dois tipos de aspa (simples e dupla) com a concatenacao correspondente do auxiliar sige_csp_script_attr, produzindo script com nonce.
- Aperto da catraca check-inline-frontend: maximo de blocos script de 25 para 14.

## Excluido

- Paginas autonomas (modelos PDF de pauta e boletim, boletim do jardim, motor de documentos, pagina segura da camara da portaria): emitem HTML proprio e merecem cabecalho CSP proprio. Incremento seguinte, ja com cabecalho CSP por pagina (a portaria, por exemplo, ja gere os seus proprios cabecalhos).
- Portal publico: surface propria.
- Ficheiro canonico finance-core: inviolavel.
- Pagina de login: pre-autenticacao, anterior ao carregamento do auxiliar.
- Popup construido por document.write em financeiro-extratos: documento proprio.
- O cabecalho CSP em si (Report-Only e depois enforce): incremento posterior.

## Riscos

- Quebrar a sintaxe da concatenacao em PHP. Mitigacao: php -l limpo nas 6 vistas; render simulado em ambos os tipos de aspa.
- Partir os verificadores de JavaScript embebido. Mitigacao: a conversao so altera a tag de abertura; o motor de extraccao absorve a concatenacao ate ao fecho do tag e o conteudo apos o tag mantem-se igual, pelo que os verificadores ficam verdes sem alteracao (confirmado).
- O auxiliar nao estar disponivel. Mitigacao: as 6 vistas renderizam apos o bootstrap, onde o core-helpers ja foi carregado.
- Alterar o comportamento dos redireccionamentos. Mitigacao: acrescentar o nonce e inocuo sem cabecalho CSP (os navegadores ignoram-no); os redireccionamentos continuam a redireccionar.

## Criterios de aceitacao

- A conversao produz, em ambos os tipos de aspa, uma tag script com atributo nonce base64 e sem script puro; o mesmo nonce e usado em todos os scripts do pedido.
- Os 11 redireccionamentos continuam a redireccionar; php -l limpo nas 6 vistas.
- Os dois verificadores de JavaScript embebido mantem-se verdes sem alteracao.
- A catraca trava blocos script em 14 (sem folga); onclick 162 e style 2051 inalterados.
- Superficie 199, vistas 60, opcoes 132, deps 9. Versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 99 e release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
