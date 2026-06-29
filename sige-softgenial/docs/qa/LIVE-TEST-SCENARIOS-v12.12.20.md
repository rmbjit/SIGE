# Cenarios de teste live - v12.12.20 (Fase 7 incr 1: reconciliacao e divergencias)

Objectivo: confirmar, num ambiente real, que o ecra "Reconciliacao e Divergencias"
mostra corretamente as divergencias por escola e que e so de leitura. Nenhum passo
altera dados; o ecra nao tem accoes.

Pre-requisitos: instalar o ZIP v12.12.20; entrar como utilizador com acesso financeiro
(financeiro, Direccao, Secretaria Geral ou administrador) numa escola que use pagamentos
moveis (M-Pesa ou e-Mola).

## Cenario 1 - A entrada existe e abre
1. Entrar no menu financeiro (Tesouraria).
2. Confirmar a entrada "Reconciliacao", logo abaixo de "Pagamentos Moveis".
3. Abrir. Esperado: a pagina "Reconciliacao e Divergencias" abre, com quatro totais no topo
   (Recebido pelo gateway, Conciliado, Recebido por aplicar, Pagamentos moveis registados)
   e tres seccoes por baixo.

## Cenario 2 - Escola sem pagamentos moveis
1. Abrir a Reconciliacao numa escola que ainda nao tenha transacoes moveis.
2. Esperado: aviso "Ainda nao ha registos de pagamentos moveis nesta escola"; sem tabelas.

## Cenario 3 - Recebido mas nao conciliado (dinheiro em limbo)
1. Garantir que existe pelo menos uma transacao recebida e ainda nao conciliada
   (em Pagamentos Moveis, uma transacao no estado "recebida" ou "pendente manual",
   sem pagamento associado).
2. Abrir a Reconciliacao.
3. Esperado: essa transacao aparece na seccao "Recebido mas nao conciliado", com gateway,
   referencia, telemovel, valor, estado e data; o total "Recebido por aplicar" reflecte-a;
   o contador da seccao e maior que zero.

## Cenario 4 - Sem divergencias
1. Numa escola onde todas as transacoes recebidas estejam conciliadas e os valores coincidam.
2. Abrir a Reconciliacao.
3. Esperado: faixa verde "Sem divergencias"; as tres seccoes indicam que nao ha nada por
   conciliar, nenhuma divergencia de montante e todos os pagamentos moveis tem transacao.

## Cenario 5 - Pagamento movel sem transacao de gateway
1. Registar um pagamento manual com metodo "mpesa" ou "emola" (ou identificar um existente)
   que nao tenha transacao de gateway associada.
2. Abrir a Reconciliacao.
3. Esperado: esse pagamento aparece na seccao "Pagamento movel sem transacao de gateway",
   com numero do pagamento, metodo, referencia externa, valor e data.

## Cenario 6 - So leitura
1. Percorrer toda a pagina.
2. Esperado: nao existem botoes de accao, formularios nem alteracoes; a pagina apenas mostra
   informacao. As correccoes fazem-se na pagina de Pagamentos Moveis, nao aqui.

## Cenario 7 - Acesso negado
1. Entrar com um utilizador sem perfil financeiro.
2. Tentar abrir ?page=sige-app&view=financeiro-reconciliacao.
3. Esperado: a entrada nao aparece no menu; o acesso directo mostra o aviso de area reservada.

## Cenario 8 - Isolamento por escola
1. Com acesso a duas escolas, abrir a Reconciliacao em cada uma.
2. Esperado: cada escola so mostra as suas proprias transacoes e pagamentos; nunca os da outra.
