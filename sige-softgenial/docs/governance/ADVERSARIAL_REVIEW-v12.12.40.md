# Revisao adversarial - v12.12.40 - Fase 3 - Reconciliacao de pagamentos digitais - deteccao de duplicados

Rediagnostico adversarial do primeiro incremento da reconciliacao. O exercicio assume a postura de um revisor hostil e procura partir cada criterio antes de o declarar pronto.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. Marcar uma mensalidade legitima como duplicado. A classe de mesmo pagador e valor exige proximidade no tempo (janela). O smoke confirma que o mesmo pagador e valor afastados um mes nao sao marcados, e que uma janela curta separa pagamentos afastados.
2. Deixar passar um duplo credito. A classe pagamento_duplo apanha o mesmo pagamento_id em duas ou mais transacoes conciliadas. O smoke confirma.
3. Deixar passar uma referencia repetida. A classe referencia_repetida apanha a mesma provider e referencia em duas ou mais transacoes. O smoke confirma.
4. Usar a deteccao para apagar ou alterar pagamentos. A camada e estritamente so de leitura: o ficheiro de dominio nao tem insert, update nem delete, e a vista nao tem POST. O gate verifica ambos.
5. Quebrar com escola invalida. O involucro e fail-closed: escola invalida devolve a estrutura vazia. O smoke confirma.
6. Marcar uma transacao isolada como duplicado. Cada classe exige duas ou mais transacoes; uma lista vazia ou uma transacao unica nao produzem grupos. O smoke confirma.
7. Partir o relatorio de divergencias existente. A deteccao e uma camada nova e separada; o smoke de reconciliacao existente (tres classes de divergencia, totais e fail-closed) continua verde.
8. Tocar em regras de calculo ou ficheiros canonicos. Nada disso e tocado; a camada apenas le transacoes e agrupa em memoria.
9. Inflar a superficie ou as opcoes. As novas funcoes nao registam hooks nem opcoes; o extractor confirma 199 itens de superficie e 132 opcoes.

## P0

Nenhum. A camada nunca apaga nem altera pagamentos; nao toca em regras de calculo nem em ficheiros canonicos.

## P1

Nenhum. Apanha duplo credito e pagamentos repetidos por engano que a idempotencia por referencia nao apanha.

## P2 e P3

Nenhum novo. Falsos positivos mitigados pela janela de tempo; a seccao e ajuda a revisao, a confirmar antes de agir.

## Decisao

Aprovado. Zero P0 e zero P1. A deteccao de duplicados esta completa e provada por gate e smoke dedicados (corredor 91/91): tres classes de possiveis duplicados, janela que evita falsos positivos em mensalidades, involucro por escola fail-closed e so de leitura, e seccao so de leitura na vista. Aditivo, sem tocar em regras de calculo nem em ficheiros canonicos, sem nova superficie, sem opcoes novas, sem alteracao de esquema, calculo byte-identico. O smoke de reconciliacao existente continua verde. Release gate verde a partir de pasta limpa. Pronto para entrega. Reconciliacao viva e resolucao de divergencias com escrita seguem em incrementos seguintes.
