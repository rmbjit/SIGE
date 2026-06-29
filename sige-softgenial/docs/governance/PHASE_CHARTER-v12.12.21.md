# Phase Charter v12.12.21

## Programa SIGE SoftGenial Alto Calibre - Fase 7 (Caixa, estornos e reconciliacao), incremento 2

Regra de quatro-olhos (dupla aprovacao) para estornos e reaberturas de caixa.
Base: v12.12.20. Com este incremento, a Fase 7 fica fechada por inteiro.

## Objectivo

Garantir que nenhum estorno de pagamento nem reabertura de caixa produz efeito
com base na decisao de uma so pessoa. Cada um passa a ser pedido por um
utilizador autorizado e aprovado por um utilizador diferente antes de executar,
com registo completo de quem pediu, quem decidiu, quando e porque (separacao de
funcoes). A integridade financeira deixa de depender de um unico operador.

## Incluido

- Nova tabela sige_fin_aprovacoes (pedido pendente: tipo, alvo, parametros,
  estado, solicitante, aprovador, motivos e datas). SCHEMA_VERSION sobe para
  20260621.1 para correr a migracao.
- Novo modulo includes/finance-aprovacoes.php: solicitar, decidir, executar,
  listar; fail-closed por escola; anti-duplicado de pedidos pendentes.
- Intercepcao dos dois handlers em financeiro-extratos.php (estorno e
  reabertura): passam a criar um pedido em vez de executar.
- Execucao da reabertura extraida para sige_fin_reabertura_caixa_executar (com
  MFA do aprovador); a execucao do estorno reutiliza estornarPagamento intacto.
- Novo ecra Aprovacoes Pendentes (admin/finance/aprovacoes-view.php) com um
  unico endpoint de decisao (campo sige_fin_aprovacao_decidir), so com tokens.
- Eventos de Ledger: fin_aprovacao_solicitada, fin_aprovacao_aprovada,
  fin_aprovacao_rejeitada, mais fin_reabrir_caixa na execucao da reabertura.
- Endpoint de decisao governado no Security Kernel (manifesto 196 para 197;
  regra nova em enforce; enforce passa de 30 para 31).
- Gate tools/check-aprovacoes.php e smoke tools/smoke-aprovacoes.php no corredor.

## Excluido

- Auto-expiracao de pedidos pendentes por cron: pertence a fase de
  infraestrutura; o pedido fica pendente ate ser decidido. Nada e adiado dentro
  do fluxo do quatro-olhos.
- Limiar por valor: explicitamente nao aplicavel. Todos os estornos e
  reaberturas exigem aprovacao, por serem operacoes raras e sensiveis.
- Alteracao das regras de calculo financeiro (proibido por contrato).

## Riscos

- R1: a intercepcao dos handlers podia partir o fluxo existente. Mitigacao:
  estornarPagamento mantem-se intacto (so muda quem o chama); a reabertura foi
  extraida para funcao chamavel e validada por smoke; dia_fechado e fecho_row
  sao re-obtidos a jusante na propria pagina.
- R2: bypass da separacao de funcoes. Mitigacao: o decisor nao pode ser o
  solicitante (verificado por smoke); o endpoint exige nonce e a permissao da
  accao; auto-aprovacao impossivel.
- R3: dupla execucao. Mitigacao: estornarPagamento mantem FOR UPDATE e guarda de
  duplo estorno; pedidos pendentes duplicados sao recusados.
- R4: crescimento do manifesto sem cobertura do Kernel. Mitigacao: regra nova
  registada em enforce; gate de regras confirma manifesto igual a regras (197).

## Criterios de aceitacao

- Estorno e reabertura criam pedido pendente, sem alteracao financeira ate a
  aprovacao.
- Aprovacao por utilizador diferente executa; rejeicao descarta; auto-aprovacao
  impossivel (smoke).
- Ecra de Aprovacoes lista os pedidos e permite decidir, com MFA do aprovador.
- Tudo auditado no Ledger; tabela migra; endpoint governado (197) e
  tenant-isolado.
- Corredor completo verde (60 gates); zero travessoes; versao sincronizada; md5
  de calculo intactos; sem regressao de design.
- Rediagnostico adversarial Zero P0/P1. Fase 7 completa, nada adiado.
