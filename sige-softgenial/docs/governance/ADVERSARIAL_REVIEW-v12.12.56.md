# Revisao adversarial - v12.12.56 - Fase 4 incr 2 - Nonce CSP nos redireccionamentos echo

Rediagnostico adversarial do incremento que aplica o nonce CSP aos redireccionamentos construidos por echo nas vistas autenticadas.

## Rediagnostico adversarial

Tentou-se quebrar a entrega pelos seguintes vectores:

1. A concatenacao em string partir a sintaxe PHP de alguma vista. Verificou-se php -l limpo nas 6 vistas tocadas. Os dois tipos de aspa foram tratados com a concatenacao correspondente: aspa simples passa a abrir com script mais aspa, ponto, auxiliar, ponto, aspa, fecho; aspa dupla idem com aspas duplas. O render simulado de ambos os tipos produziu uma tag script com atributo nonce base64 e sem script puro, com o mesmo nonce em todos os scripts do pedido.

2. Os verificadores de JavaScript embebido nas vistas deixarem de validar a sintaxe. Analisou-se o motor de extraccao: a expressao regular absorve tudo o que nao seja fecho de tag, pelo que a concatenacao introduzida na tag de abertura e consumida ate ao fecho do tag, e o conteudo extraido (o JavaScript apos o tag) mantem-se exactamente igual ao da versao anterior. Confirmou-se que check-js-views e check-js-views-combined continuam verdes, sem alteracao e sem necessidade de novo ajuste.

3. O auxiliar de nonce nao estar definido onde um redireccionamento e emitido. As 6 vistas (dashboard, disciplinas, encerramento, financeiro-config, notas, transporte) renderizam dentro do shell administrativo, apos o bootstrap, onde o core-helpers ja foi carregado incondicionalmente. O auxiliar esta disponivel.

4. Acrescentar o nonce alterar o comportamento dos redireccionamentos. Os navegadores ignoram o atributo nonce sem uma politica CSP, pelo que os 11 redireccionamentos continuam a redireccionar exactamente como antes. O calculo financeiro mantem-se byte-identico e o ficheiro canonico finance-core nao foi tocado.

5. A conversao apanhar literais que nao sao redireccionamentos. Confirmou-se que, nas 6 vistas-alvo, os unicos literais de tag script em string sao os proprios redireccionamentos (as tags ao nivel do template ja levavam nonce desde a v12.12.55, na forma com PHP no tag, que nao corresponde ao padrao de literal em aspa). A conversao limitou-se as 6 vistas e atingiu exactamente 11 ocorrencias.

6. Confundir o popup de financeiro-extratos com um redireccionamento. O popup construido por document.write emite um documento proprio e foi deixado de fora, a par das paginas autonomas, por merecer abordagem propria.

7. A catraca afrouxar. Pelo contrario: o maximo de blocos script desceu de 25 para 14 (sem folga).

8. Regressao de inventario. Verificou-se: superficie 199 e enforce 33, vistas 60, opcoes 132, dependencias externas 9, sem alteracao de esquema. Versao sincronizada nas cinco fontes.

## P0

Nenhum. A vaga prepara o CSP sem tocar em regras de calculo nem no ficheiro canonico, e acrescentar o nonce e inocuo sem cabecalho CSP.

## P1

Nenhum. Os redireccionamentos estao em vistas autenticadas renderizadas apos o bootstrap; a conversao so altera a tag de abertura; o comportamento e identico; php -l limpo nas 6 vistas.

## P2 e P3

Nenhum novo. As paginas autonomas (modelos PDF, motor de documentos, camara da portaria), o portal publico, o finance-core, a pagina de login (pre-auth) e o popup document.write ficam para abordagem propria. A catraca (blocos script 14, sem folga) impede regressao e os verificadores de JS embebido mantem-se verdes.

## Decisao

Aprovado. Zero P0 e zero P1. Os 11 redireccionamentos construidos por echo nas vistas autenticadas passaram a emitir a tag script com nonce por pedido (inocuo ate o CSP ser activado), os verificadores de JavaScript embebido mantem-se verdes sem alteracao, e a catraca de blocos script desceu de 25 para 14. As paginas autonomas (que emitem HTML proprio e merecem cabecalho CSP proprio), o portal, o finance-core canonico, a pagina de login e o popup document.write ficam para as proprias vagas, a caminho do cabecalho CSP. Pronto para empacotar.
