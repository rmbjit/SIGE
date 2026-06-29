# TESTES LIVE - v12.11.9.96 (Sprint UX-3)

Fazer primeiro na demo. Tempo total: ~8 minutos.

## 1. Pagamentos Móveis: Rejeitar com dignidade (3 min)
1. Numa transacção pendente_manual > Rejeitar: abre o modal do SIGE
   com a REFERÊNCIA da transacção no texto e campo de motivo;
2. Tentar confirmar com o motivo vazio: o campo fica vermelho e o
   modal NÃO fecha; Esc cancela sem efeitos;
3. Preencher motivo > Rejeitar: toast verde "Transacção rejeitada." e
   reload suave; badge passa a Rejeitada (cinza);
4. Conciliar manual numa pendente: no sucesso, toast "Pagamento
   registado; o recibo segue na fila WhatsApp." antes do reload.

## 2. Pagamentos Móveis: visual do kit (2 min)
- Cartões (Estado, Credenciais, e-Mola, Cobrar agora) com o cartão do
  kit; banners verdes de "Configuração guardada" com o novo estilo;
- Badges: canal M-Pesa (vermelho suave) / e-Mola (laranja); estados
  Conciliada=verde, Recebida=âmbar, Pendente manual=vermelho,
  Rejeitada=cinza; Cobrar agora com push continua igual.

## 3. Configuração Financeira (1 min)
Secção de descontos: os três textos dizem "na ficha do aluno";
guardar uma alteração qualquer funciona como sempre.

## 4. Inocuidade (2 min)
Dashboard, Pagamentos (balcão), Central WhatsApp: idênticos à v95.

## Critério de aprovação
Tudo verde = v96 validada; UX-4 pode atacar jardim_relatorio +
estatisticas + turmas-view (onde vivem os 2 últimos prompts nativos).
