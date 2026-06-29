# Carta da fase - v12.12.40 - Fase 3 - Reconciliacao de pagamentos digitais - deteccao de duplicados

Primeiro incremento da Fase 3 (reconciliacao de pagamentos digitais, que completa a Fase 7 do plano original). O circuito ja tinha idempotencia de webhooks (M-Pesa e e-Mola dedupam por escola e referencia, com chave unica que protege de re-entregas e corridas) e um relatorio de divergencias so de leitura (nao conciliadas, divergencias de montante, pagamentos moveis sem gateway).

## Objectivo

Apanhar pagamentos digitais que podem ser o mesmo pagamento feito mais do que uma vez e que a idempotencia por referencia nao apanha, assinalando-os para revisao humana. Tudo sem nunca apagar ou alterar pagamentos, sem tocar em regras de calculo nem em ficheiros canonicos.

## Incluido

Nucleo puro testavel (sige_pagamentos_detectar_duplicados) que recebe as transacoes de gateway e devolve grupos de possiveis duplicados em tres classes:
- Referencia repetida: mesma provider e referencia em duas ou mais transacoes.
- Mesmo pagamento conciliado mais de uma vez: mesmo pagamento_id em duas ou mais transacoes conciliadas (indicio de duplo credito).
- Mesmo pagador e valor proximos no tempo: mesmo msisdn e valor dentro de uma janela (provavel pagamento repetido por engano). A janela de tempo evita falsos positivos em mensalidades legitimas.

Involucro de dominio por escola (sige_reconciliacao_duplicados), fail-closed e so de leitura, que carrega as transacoes da escola e devolve os grupos com totais. A vista de reconciliacao passa a apresentar uma seccao de possiveis duplicados, so de leitura e sem formularios.

## Excluido

- Reconciliacao viva (consulta ao gateway para confirmar o estado de transacoes ou descobrir transacoes em falta): depende do sandbox M-Pesa/e-Mola e fica para um incremento seguinte.
- Resolucao de divergencias com escrita (juntar ou anular pagamentos a partir do relatorio): a deteccao desta versao e so de leitura; a accao fica para um incremento seguinte, com quatro-olhos.
- Nenhuma alteracao a regras de calculo, a ficheiros canonicos ou ao esquema. A idempotencia de webhooks ja existente nao e tocada.

## Riscos

- Falsos positivos. Mitigacao: a classe de mesmo pagador e valor exige proximidade no tempo (janela), pelo que mensalidades legitimas afastadas no tempo nao sao marcadas; a seccao e apresentada como ajuda a revisao, a confirmar antes de agir.
- Tornar-se uma porta de escrita. Mitigacao: a camada e estritamente so de leitura; o gate verifica que o ficheiro de dominio nao tem insert, update nem delete e que a vista nao tem POST.
- Custo em escolas com muitas transacoes. Mitigacao: o involucro carrega as transacoes da escola e agrupa em memoria; o nucleo e linear nas transacoes.

## Criterios de aceitacao

- Duas transacoes com a mesma referencia, ou o mesmo pagamento conciliado duas vezes, ou o mesmo pagador e valor proximos no tempo aparecem assinalados como possiveis duplicados.
- O mesmo pagador e valor afastados no tempo (mensalidades) nao sao assinalados.
- O involucro por escola e fail-closed (escola invalida devolve vazio) e so de leitura.
- A vista de reconciliacao mostra a seccao de possiveis duplicados e nao tem formularios.
- Nada e apagado nem alterado pela deteccao.
- Superficie de accao inalterada (199, enforce 33; views 60), sem opcoes novas (132), dependencias externas inalteradas (11), versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 91/91 e release gate verde a partir de pasta limpa. O smoke de reconciliacao existente continua verde. Rediagnostico adversarial Zero P0/P1.
