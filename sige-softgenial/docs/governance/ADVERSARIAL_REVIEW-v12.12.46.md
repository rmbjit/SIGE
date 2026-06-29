# Revisao adversarial - v12.12.46 - Fase 4 incr 2 - Migracao de inline (vaga 1) e catraca

Rediagnostico adversarial do segundo incremento da Fase 4. O exercicio assume a postura de um revisor hostil e procura partir cada criterio antes de o declarar pronto.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. Um botao deixar de funcionar apos perder o onclick. O despachante data-sige-act chama a mesma funcao global; o smoke do ficheiro confirma 0 onclick e 10 data-sige-act, e as assinaturas batem certo: os tres handlers de id recebem-no por data-sige-arg (usado como valor de campo), o de detalhes recebe o elemento (le data-details) e os de fechar e exportar nao usam argumento.
2. Passar o argumento errado. O despachante passa data-sige-arg quando existe, e o elemento quando nao existe. Para sigeOpenCancel, sigeOpenExempt e sigeOpenReactivate ha data-sige-arg com o id; para sigeOpenDetails nao ha, pelo que recebe o elemento; os de fechar e exportar ignoram o argumento.
3. Colidir com o enhancer de confirmacao. O despachante corre em bolha; o de confirmacao corre em captura (com stopPropagation), pelo que um elemento com ambos os atributos e tratado primeiro pela confirmacao.
4. Disparar funcao inexistente. O despachante so chama se window[accao] for uma funcao; caso contrario nao faz nada.
5. Tocar em regra de calculo ou ficheiro canonico. Nao ha: so se alterou a forma de ligar o clique a funcao no ficheiro alvo e adicionou-se um ouvinte ao sige-ui.js. As funcoes dos handlers e os ficheiros canonicos ficam intactos.
6. Deixar o inline crescer. O gate de catraca conta onclick=, style=" e blocos <script> e falha se qualquer categoria exceder a baseline; nesta versao confirma 250, 2191 e 81.
7. Mudar superficie, vistas, opcoes ou dependencias. As conversoes sao em HTML e o despachante e JS; o extractor mantem 199, vistas 60, opcoes 132, dependencias 9.
8. Partir os incrementos anteriores. Os gates de pagamentos, cofre, Fase 1 e Fase 4 incr 1 continuam verdes; node -c ao sige-ui.js limpo; o release gate passa a partir de pasta limpa.

## P0

Nenhum. So muda a forma de ligar o clique a funcao; nao toca em regras de calculo nem em ficheiros canonicos.

## P1

Nenhum. A logica dos handlers e identica; o despachante apenas chama a funcao global com o id ou o elemento.

## P2 e P3

Nenhum novo. A catraca impede o inline de aumentar; o despachante em bolha nao colide com o enhancer de confirmacao.

## Decisao

Aprovado. Zero P0 e zero P1. O mecanismo de delegacao e a primeira vaga estao completos e provados por gate (corredor 98): despachante data-sige-act em sige-ui.js, dez onclick convertidos no ficheiro alvo sem mudar a logica dos handlers, e catraca a impedir que o inline aumente. Aditivo, sem tocar em regras de calculo nem em ficheiros canonicos, sem alteracao de superficie, vista, opcoes nem dependencias, sem alteracao de esquema, calculo byte-identico. Os gates anteriores continuam verdes. Release gate verde a partir de pasta limpa. Pronto para entrega. Seguem-se as vagas seguintes, que vao baixando a catraca ate ser possivel impor o CSP.
