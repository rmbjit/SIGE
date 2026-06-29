# Carta da fase - v12.12.42 - Fase 3 - Reconciliacao de pagamentos digitais - resolucao com escrita (quatro-olhos)

Terceiro incremento da Fase 3 (reconciliacao de pagamentos digitais, que completa a Fase 7 do plano original), sobre o incremento 1 (deteccao de duplicados) e o incremento 2 (reconciliacao viva). Os dois primeiros incrementos eram so de leitura. Este e a primeira escrita da Fase 3: torna a reconciliacao accionavel, mas sob controlo duplo.

## Objectivo

Permitir resolver transacoes de gateway - conciliar uma transacao confirmada, ou rejeitar uma rejeitada pelo gateway - exigindo que um segundo utilizador autorizado aprove antes de qualquer escrita. A escrita financeira passa sempre pelo caminho canonico; o sistema nunca calcula valores nem altera regras financeiras nesta camada.

## Incluido

- Execucao (sige_recon_executar_conciliar, sige_recon_executar_rejeitar): chamada pelo despacho de aprovacoes quando um pedido e aprovado. A conciliacao regista o pagamento pelo caminho canonico sige_fin_registar_pagamento (com correspondencia automatica, ou um lancamento escolhido). A execucao re-valida o estado da transacao no momento da aprovacao (pode ter mudado entre a proposta e a decisao), e idempotente, respeita o tenant e e fail-closed. A rejeicao nunca toca numa transacao ja conciliada.
- Proposta (sige_recon_propor_conciliacao, sige_recon_propor_rejeicao): cria o pedido pendente (maker) via sige_fin_aprovacao_solicitar e nao executa nada.
- Dois novos tipos de aprovacao (recon_conciliar, recon_rejeitar) e o despacho correspondente em finance-aprovacoes.php, reutilizando a permissao existente financeiro.mobile_payments_gerir.
- UI do maker so server-side na vista de gestao (mpesa-view.php), com nonce, que propoe e remete para a fila generica de aprovacoes.
- O checker usa a vista de aprovacoes ja existente (lista qualquer tipo de pedido).

## Excluido

- A escolha de um lancamento especifico na proposta de conciliacao com quatro-olhos: a proposta usa a correspondencia automatica; quem precisa de escolher um lancamento usa o fluxo directo ja existente. Pode vir a ser acrescentada.
- O ajuste fino dos codigos de falha do gateway: depende do sandbox das operadoras (continua do incremento anterior).
- Qualquer alteracao a regras de calculo, a ficheiros canonicos ou ao esquema. Os botoes directos de conciliar e rejeitar nao sao tocados.

## Riscos

- Escrever sem controlo. Mitigacao: nada executa sem a aprovacao de um segundo utilizador; a separacao de funcoes (maker diferente de checker) e imposta pelo framework de aprovacoes.
- Calcular ou adulterar valores. Mitigacao: a conciliacao delega no caminho canonico sige_fin_registar_pagamento; esta camada nao calcula valores.
- Executar duas vezes ou sobre um estado mudado. Mitigacao: a execucao re-valida o estado no momento da aprovacao e e idempotente; a rejeicao nunca toca numa transacao ja conciliada.
- Escalar privilegios com nova permissao. Mitigacao: reutiliza a permissao existente; sem nova permissao nem nova regra do kernel.

## Criterios de aceitacao

- A conciliacao executada delega no caminho canonico, com o lancamento e o valor certos, e marca a transacao conciliada registando o aprovador.
- A rejeicao executada marca rejeitada, e idempotente quando ja rejeitada, e falha quando a transacao ja foi conciliada.
- Ambas as execucoes sao fail-closed para escola invalida.
- As propostas criam apenas o pedido pendente e nao executam nada.
- Os dois tipos estao registados com a permissao reutilizada e o despacho encaminha para a execucao correcta.
- Superficie de accao inalterada (199, enforce 33), sem nova vista (60), sem opcoes novas (132), dependencias externas inalteradas (11), versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 95/95 e release gate verde a partir de pasta limpa. Os botoes directos continuam a funcionar. Rediagnostico adversarial Zero P0/P1.
