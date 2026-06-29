# Revisao adversarial - v12.12.42 - Fase 3 - Reconciliacao de pagamentos digitais - resolucao com escrita (quatro-olhos)

Rediagnostico adversarial do terceiro incremento da reconciliacao, a primeira escrita da Fase 3. O exercicio assume a postura de um revisor hostil e procura partir cada criterio antes de o declarar pronto, com atencao redobrada por se tratar de escrita financeira.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. Escrever sem a aprovacao de um segundo utilizador. A proposta (maker) apenas cria um pedido pendente; a execucao so e chamada pelo despacho de aprovacoes quando o pedido e aprovado por sige_fin_aprovacao_decidir. O smoke confirma que propor nao executa.
2. Aprovar o proprio pedido (auto-aprovacao). O framework de aprovacoes recusa quando o decisor e o solicitante (separacao de funcoes), regra ja existente e testada.
3. Calcular ou adulterar o valor pago. A conciliacao delega no caminho canonico sige_fin_registar_pagamento, com o valor da transacao; esta camada nao faz aritmetica de valores. O smoke confirma a delegacao com o lancamento e valor certos.
4. Executar duas vezes (proposta aprovada, transacao ja resolvida). A execucao re-valida o estado: a conciliacao exige recebida ou pendente_manual; a rejeicao recusa transacao ja conciliada e e idempotente quando ja rejeitada. O smoke confirma.
5. Conciliar um lancamento de outro aluno ou de outra escola. A execucao valida que o lancamento pertence a escola e ao aluno da transacao; e tenant-guarded e fail-closed. O smoke confirma o fail-closed.
6. Rejeitar uma transacao ja conciliada (destruir um pagamento registado). A rejeicao recusa explicitamente o estado conciliada. O smoke confirma.
7. Escalar privilegios com uma permissao nova. Os tipos reutilizam a permissao existente financeiro.mobile_payments_gerir; sem nova permissao, sem nova regra do kernel. O gate confirma a reutilizacao.
8. Inflar a superficie ou criar uma vista nova. A UI do maker e so server-side (nao e add_action) e o checker reutiliza a vista de aprovacoes ja existente; o extractor confirma 199 accoes e 60 vistas.
9. Partir o fluxo directo existente. Os botoes directos de conciliar e rejeitar nao foram tocados; a resolucao com quatro-olhos e uma seccao aditiva.
10. Partir os incrementos anteriores. Os gates de reconciliacao viva, deteccao de duplicados e reconciliacao continuam verdes.

## P0

Nenhum. A escrita financeira passa sempre pelo caminho canonico; nao ha calculo de valores nem alteracao de regras; a execucao re-valida o estado e e fail-closed.

## P1

Nenhum. A separacao de funcoes e imposta pelo framework; nada executa sem a aprovacao de um segundo utilizador; a rejeicao nunca toca numa transacao ja conciliada.

## P2 e P3

Nenhum novo. O risco de dupla execucao fica eliminado pela idempotencia e pela re-validacao de estado; o risco de escalonamento fica eliminado pela reutilizacao da permissao existente; a UI do maker nao adiciona accao e o fluxo directo continua a funcionar.

## Decisao

Aprovado. Zero P0 e zero P1. A resolucao de divergencias com escrita esta completa e provada por gate e smoke dedicados (corredor 95/95): execucao que delega no caminho canonico e re-valida o estado, propostas que so criam pedidos pendentes, separacao de funcoes imposta pelo framework, tipos e despacho ligados sem nova permissao, e UI do maker so server-side. Aditivo e controlado, sem tocar em regras de calculo nem em ficheiros canonicos, sem nova superficie, sem nova vista, sem alteracao de esquema, calculo byte-identico. Os botoes directos continuam intactos. Release gate verde a partir de pasta limpa. Pronto para entrega. Seguem-se o ajuste fino dos codigos de falha do gateway (dependente do sandbox) e, opcionalmente, a escolha de lancamento especifico na proposta de conciliacao.
