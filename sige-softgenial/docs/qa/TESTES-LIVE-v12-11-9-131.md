# TESTES LIVE - v12.11.9.131 (auditoria de design: financeiro-pagamentos)

Fazer em teste.softgenial.edu.mz. Tempo: ~5 minutos.
DINHEIRO - a semântica e os cálculos são sagrados. Console: zero vermelhos.

## 1. Hierarquia (1 min)
1. Meses e valores (propinas) destacam-se;
2. Descrições (tipo de propina, etc.) em texto LEVE, recuadas.

## 2. Cores de estado de pagamento (1 min) - semântica CRÍTICA
1. Propina paga: VERDE;
2. Propina vencida: VERMELHO;
3. Propina pendente: LARANJA.

## 3. Registar pagamento e recibo (2 min) - CRÍTICO
1. Registar um pagamento de teste;
2. O recibo gera-se com os valores CORRETOS;
3. Os totais, descontos, adiantamentos calculados CORRETAMENTE (não
   mudaram);
4. As mensagens (info azul, aviso laranja, erro vermelho) com as cores
   certas.

## 4. Funcionalidade geral (1 min)
1. Pesquisar aluno, ver histórico: funciona;
2. Os botões e modais respondem normalmente.

## Critério de aprovação
Meses/valores destacam-se, descrições recuam, cores de estado corretas,
RECIBO e CÁLCULOS intactos, mensagens corretas, console limpo = v131
validada.
