# Revisao adversarial - v12.12.41 - Fase 3 - Reconciliacao de pagamentos digitais - reconciliacao viva

Rediagnostico adversarial do segundo incremento da reconciliacao. O exercicio assume a postura de um revisor hostil e procura partir cada criterio antes de o declarar pronto.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. Acusar uma transacao real de falsa. O normalizador e conservador: so afirma falhada num codigo de falha conhecido (lista vazia por omissao, ajustavel por filtro); qualquer codigo desconhecido fica desconhecida (inconclusiva). O smoke confirma que INS-9 (desconhecido) nao vira falha.
2. Marcar como confirmada algo que nao foi um sucesso. So um sucesso claro do cliente (ok verdadeiro) vira confirmada. O smoke confirma.
3. Deixar passar um valor adulterado. Quando o gateway confirma mas com valor diferente do local, a transacao entra em divergentes como valor_divergente. O smoke confirma.
4. Quebrar o cron com o gateway em baixo ou mal configurado. O adaptador nunca lanca (try/catch e class_exists); cliente indisponivel devolve erro, que o motor trata como inconclusiva. A passagem so corre se algum gateway estiver configurado e e limitada a poucas transacoes. O smoke confirma o erro defensivo.
5. Usar a reconciliacao viva para escrever em pagamentos. A camada e estritamente so de leitura: nao tem insert, update, delete nem query; so le transacoes e regista divergencias no log. O gate verifica.
6. Quebrar com escola invalida. A orquestracao e fail-closed: escola invalida devolve a estrutura vazia. O smoke confirma.
7. Inflar a superficie com o cron. O cron liga-se ao evento sige_evento_diario, ja existente; o extractor de superficie confirma 199 itens (o gancho deduplica).
8. Partir a deteccao de duplicados ou o relatorio de divergencias. A reconciliacao viva e uma camada nova e separada; os gates anteriores (deteccao de duplicados e reconciliacao) continuam verdes.
9. Tocar em regras de calculo ou ficheiros canonicos. Nada disso e tocado; a camada apenas le e classifica.

## P0

Nenhum. A camada nunca apaga, cria nem altera pagamentos nem transacoes; nao toca em regras de calculo nem em ficheiros canonicos.

## P1

Nenhum. Apanha webhooks falsos ou falhados e valores divergentes, com um adaptador que nao quebra o cron.

## P2 e P3

Nenhum novo. O risco de acusar uma transacao real de falsa fica eliminado pelo normalizador conservador; o risco de prender o cron fica mitigado pelo limite e pela verificacao de gateway configurado.

## Decisao

Aprovado. Zero P0 e zero P1. A reconciliacao viva esta completa e provada por gate e smoke dedicados (corredor 93/93): nucleo puro de classificacao, normalizador conservador que nunca acusa uma transacao real de falsa, adaptador defensivo aos clientes reais, orquestracao por escola fail-closed e so de leitura, e passagem diaria no cron guardada e limitada. Aditivo, sem tocar em regras de calculo nem em ficheiros canonicos, sem nova superficie, sem opcoes novas, sem alteracao de esquema, calculo byte-identico. Os gates anteriores continuam verdes. Release gate verde a partir de pasta limpa. Pronto para entrega. A accao a partir do resultado e o ajuste fino dos codigos de falha (dependente do sandbox das operadoras) seguem em incrementos seguintes.
