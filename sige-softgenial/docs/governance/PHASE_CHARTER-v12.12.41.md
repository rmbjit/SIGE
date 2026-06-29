# Carta da fase - v12.12.41 - Fase 3 - Reconciliacao de pagamentos digitais - reconciliacao viva

Segundo incremento da Fase 3 (reconciliacao de pagamentos digitais, que completa a Fase 7 do plano original), sobre o incremento 1 (deteccao de duplicados). Os clientes de gateway ja tinham a consulta de estado (M-Pesa via queryTransactionStatus na porta de consulta; e-Mola via o seu endpoint de consulta). O que faltava era a orquestracao que usa essa consulta para verificar as transacoes locais contra o gateway e reportar divergencias.

## Objectivo

Confirmar contra o gateway se as transacoes pendentes sao reais e bem-sucedidas, apanhando webhooks falsos ou falhados e valores divergentes, sem nunca alterar pagamentos nem transacoes e sem acusar uma transacao real de falsa. A accao (conciliar ou rejeitar) fica para o fluxo manual ja existente ou para um incremento de escrita com quatro-olhos.

## Incluido

- Nucleo puro testavel (sige_pagamentos_reconciliar_estado): recebe as transacoes locais e uma funcao de consulta ao gateway, e classifica em confirmadas, divergentes (valor diferente do gateway, ou rejeitada pelo gateway) e inconclusivas.
- Normalizador (sige_pagamentos_normalizar_estado_gateway): traduz a resposta do cliente para um estado simples, de forma deliberadamente conservadora. So afirma confirmada num sucesso claro e falhada num codigo de falha conhecido; a lista de codigos de falha e vazia por omissao e ajustavel pelo filtro sige_mpesa_codigos_falha. Todo o resto fica desconhecida, pelo que nunca acusa uma transacao real de falsa.
- Adaptador (sige_pagamentos_consultar_gateway): liga a consulta aos clientes reais de forma defensiva, com class_exists e try/catch; cliente indisponivel ou erro inesperado devolvem sempre erro, nunca lancam.
- Orquestracao por escola (sige_reconciliacao_viva): fail-closed e so de leitura, verifica as transacoes pendentes da escola e regista as divergencias no log de seguranca. Aceita injeccao da consulta para testes.
- Passagem diaria no cron (sige_evento_diario): guardada pelo modo de teste, limitada a poucas transacoes por passagem, e so corre se algum gateway estiver configurado. Liga-se a um evento ja existente, sem novo gancho.

## Excluido

- A accao a partir do resultado (conciliar uma transacao confirmada, ou rejeitar uma rejeitada pelo gateway): fica para um incremento de escrita com quatro-olhos. Esta versao apenas classifica e regista.
- O ajuste fino dos codigos de falha do gateway (que codigos significam falha e quais significam ainda a processar) depende do sandbox das operadoras; por omissao a lista e vazia, o que mantem o comportamento conservador.
- Nenhuma alteracao a regras de calculo, a ficheiros canonicos ou ao esquema. A idempotencia de webhooks e a deteccao de duplicados ja existentes nao sao tocadas.

## Riscos

- Acusar uma transacao real de falsa. Mitigacao: o normalizador e conservador; um codigo de resposta desconhecido fica desconhecida (inconclusiva), e a falha so e afirmada por codigo conhecido, cuja lista e vazia por omissao.
- Quebrar o cron com chamadas ao gateway. Mitigacao: o adaptador nunca lanca; a passagem e limitada a poucas transacoes e so corre se algum gateway estiver configurado.
- Tornar-se uma porta de escrita. Mitigacao: a camada e estritamente so de leitura; o gate verifica que o ficheiro nao tem insert, update, delete nem query, e que so regista no log.

## Criterios de aceitacao

- Com uma consulta simulada: confirmada com o mesmo valor entra em confirmadas; valor diferente ou rejeitada pelo gateway entram em divergentes; desconhecida ou erro ficam inconclusivas; transacao sem referencia fica inconclusiva.
- Um codigo de resposta desconhecido nunca e tratado como falha.
- O cliente ausente ou um erro devolvem sempre estado erro (defensivo).
- A orquestracao e fail-closed (escola invalida devolve vazio), regista as divergencias e nao escreve em pagamentos nem transacoes.
- O cron esta guardado pelo modo de teste, e limitado e so corre se configurado.
- Superficie de accao inalterada (199, enforce 33; views 60; cron num evento ja existente), sem opcoes novas (132), dependencias externas inalteradas (11), versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 93/93 e release gate verde a partir de pasta limpa. Os gates de reconciliacao e deteccao de duplicados anteriores continuam verdes. Rediagnostico adversarial Zero P0/P1.
