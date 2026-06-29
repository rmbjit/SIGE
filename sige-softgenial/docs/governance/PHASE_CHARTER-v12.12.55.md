# Carta da fase - v12.12.55 - Fase 4 incr 2 - Infraestrutura de nonce CSP e nonce nas tags script inline

Continuacao do incremento 2 da Fase 4 (CSP e front-end seguro): introducao da infraestrutura de nonce CSP e aplicacao do nonce as tags script inline das vistas administrativas. Sequencia as vagas de onclick (v12.12.46 a v12.12.54) e abre a frente dos blocos script.

## Objectivo

Preparar o enforcement do CSP introduzindo um nonce unico por pedido e aplicando-o as tags script inline das vistas, sem alterar o comportamento (o nonce e ignorado sem cabecalho CSP) e sob a catraca, que desce e passa a proteger a infraestrutura de nonce.

## Incluido

- Infraestrutura de nonce CSP no core-helpers (carregado incondicionalmente no bootstrap):
  - sige_csp_nonce: nonce unico por pedido, gerado uma so vez e reutilizado (base64 de bytes aleatorios, com recurso a wp_generate_password se random_bytes nao estiver disponivel).
  - sige_csp_script_attr: devolve o atributo nonce ja escapado para uma tag script inline.
- Aplicacao do nonce a 55 tags script inline: 54 ao nivel do template (script seguido de quebra de linha) nas vistas academicas, financeiras, de jardim, de sistema, de WhatsApp, de recursos humanos e nos includes de contexto administrativo, mais um bloco de uma so linha no admin-shell (etiquetas do menu lateral).
- Ajuste dos dois verificadores de JavaScript embebido (check-js-views e check-js-views-combined) para normalizar a tag com nonce antes da extraccao.
- Aperto da catraca check-inline-frontend: maximo de blocos script de 81 para 25, e passa a exigir que a infraestrutura de nonce exista.

## Excluido

- Redireccionamentos construidos por echo (echo de uma tag script com window.location): tem padrao proprio (concatenacao em string) e ficam para vaga seguinte.
- Paginas autonomas (modelos PDF de pauta e boletim, boletim do jardim, motor de documentos, pagina segura da camara da portaria), portal publico, gerador de tags CDN: CSP proprio, abordagem propria.
- Ficheiro canonico finance-core: inviolavel.
- Pagina de login: pre-autenticacao, anterior ao carregamento do auxiliar.
- O cabecalho CSP em si (Report-Only e depois enforce): incremento seguinte.

## Riscos

- O auxiliar nao estar disponivel onde uma tag renderiza. Mitigacao: o core-helpers e carregado incondicionalmente no bootstrap, antes de qualquer vista; o auxiliar esta disponivel em admin e frontend.
- O nonce variar dentro do mesmo pedido (e invalidar o CSP futuro). Mitigacao: e guardado em variavel estatica e gerado uma so vez por pedido.
- random_bytes falhar. Mitigacao: recurso a wp_generate_password.
- Os verificadores de JS embebido tropecarem no PHP do proprio tag. Mitigacao: passam a normalizar a tag com nonce antes de extrair, so na copia em memoria.
- Alterar o comportamento. Mitigacao: acrescentar o nonce e inocuo sem cabecalho CSP (os navegadores ignoram-no).

## Criterios de aceitacao

- O nonce e consistente no mesmo pedido e o atributo renderiza como nonce com valor base64 sem aspas; uma tag com o auxiliar renderiza como script com nonce.
- As 55 tags convertidas comportam-se como antes; php -l limpo nas 48 fontes tocadas.
- A catraca trava blocos script em 25 (sem folga) e falha se a infraestrutura de nonce desaparecer; onclick 162 e style 2051 inalterados.
- Os verificadores de JS embebido validam 66 blocos sem erro de sintaxe.
- Superficie 199, vistas 60, opcoes 132, deps 9. Versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 99 e release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
