# TESTES LIVE - v12.11.9.95 (Sprint UX-2)

Fazer primeiro na demo. Tempo total: ~10 minutos.
O teste nº 1 é o que importa: o balcão não pode mudar.

## 1. Fluxo de pagamento intacto + escudo (5 min)
1. Registar um pagamento normal (valor pequeno, dinheiro): seleccionar
   dívida > Registar > modal de confirmação com método/referência/total
   IGUAL ao de sempre > "Sim, confirmar pagamento";
2. ESCUDO: repetir noutro pagamento e fazer DUPLO-CLIQUE rápido no
   "Sim, confirmar": o botão desactiva ao primeiro clique e mostra
   "A processar..."; no fim, confirmar nos extractos que existe UM
   pagamento e UM recibo na fila WhatsApp (não dois);
3. Esc e clique fora do modal continuam a fechar sem registar.

## 2. Desconto especial (2 min)
1. Aplicar desconto especial > deixar o motivo VAZIO > Registar:
   toast laranja "Preencha o motivo do desconto especial para
   continuar." + foco no campo; o botão do modal NÃO fica preso em
   "A processar...";
2. Preencher o motivo > registar normalmente.

## 3. Botões e selecção (2 min)
1. Dívidas: "Seleccionar Tudo / Limpar" com visual do kit; contador de
   seleccionados funciona como antes;
2. Pagamento em família: idem, com o destaque violeta preservado no
   Seleccionar Tudo.

## 4. Inocuidade (1 min)
Dashboard, Alunos, Central WhatsApp: visual idêntico à v94.

## Critério de aprovação
Tudo verde (em especial 1.2 com UM só pagamento) = v95 validada.
