# Phase Charter - v12.12.20

Fase 7 (Caixa, estornos e reconciliacao), incremento 1. Relatorio de divergencias
de reconciliacao. Base: v12.12.19. Estado: entregue.

## Objectivo

Dar visibilidade consolidada e a prova de auditoria das divergencias entre o
dinheiro reportado pelos gateways moveis (M-Pesa e e-Mola) e os pagamentos
registados, para que dinheiro recebido e nao aplicado, ou aplicado com montante
diferente, deixe de passar despercebido. Confirmar e fechar nos registos de
governanca os criterios da Fase 7 ja cumpridos.

## Incluido

- Ecra so de leitura "Reconciliacao e Divergencias" (acesso por sige_mpesa_pode_gerir:
  financeiro, Direccao, Secretaria Geral, administrador), sem POST e sem endpoint de
  escrita novo. Por escola mostra:
  - Transacoes recebidas mas nao conciliadas (dinheiro em limbo): estado em
    recebida/pendente/pendente_manual e sem pagamento associado.
  - Divergencias de montante: transacao conciliada cujo valor do gateway difere do
    valor do pagamento (ABS > 0.01).
  - Pagamentos moveis sem transacao de gateway correspondente.
  - Totais: recebido pelo gateway, conciliado, recebido por aplicar, pagamentos
    moveis registados, e a diferenca.
- Logica de dominio separada da apresentacao: sige_reconciliacao_divergencias()
  (so leitura, fail-closed por escola) em includes/payments/; a view so apresenta.
- Estilo so com tokens (assets/views/reconciliacao.css), sem primitivos magicos.
- Confirmacao e documentacao da idempotencia do e-Mola (pre-verificacao por
  referencia mais chave unica, igual ao M-Pesa).
- Fecho, nos registos, dos criterios da Fase 7 ja cumpridos.

## Excluido (adiado)

- Regra de quatro-olhos (dupla aprovacao) para estornos e reaberturas: incremento 2
  (alteracao de fluxo).
- Accoes correctivas a partir do relatorio: o relatorio so expoe; a accao continua
  pelos fluxos existentes (Pagamentos Moveis).

## Modelo de ameaca

Dinheiro recebido pelo gateway e nao aplicado, ou aplicado com valor divergente,
fica invisivel sem um relatorio consolidado. O relatorio torna estas divergencias
explicitas e auditaveis, sem alterar dados nem fluxos (so leitura).

## Riscos e mitigacao

- So leitura: sem risco operacional; nao altera dados nem fluxos. A view nao tem
  POST, formularios nem escrita (verificado por gate).
- Desempenho: consultas por escola, sobre indices de estado/referencia.
- Acesso: restrito por sige_mpesa_pode_gerir; sem endpoint de escrita novo
  (manifesto 196 inalterado).
- Design: estilo so com tokens; sem regressao de primitivos magicos.

## Criterios de aceitacao

1. Ecra so de leitura mostra, por escola, as tres classes de divergencia e os totais;
   acesso restrito; sem endpoint novo (manifesto 196 inalterado).
2. Logica de dominio separada e testavel; fail-closed por escola.
3. Idempotencia do e-Mola confirmada e documentada; criterios ja cumpridos fechados.
4. Gate e smoke verdes; corredor completo verde; zero travessoes; versao sincronizada;
   raiz canonica; sem regressao de design.
