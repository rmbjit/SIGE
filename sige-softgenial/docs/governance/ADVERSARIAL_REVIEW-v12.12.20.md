# Rediagnostico adversarial - v12.12.20 (reconciliacao e divergencias)

Base: v12.12.19. Alvo: o relatorio de divergencias de reconciliacao e o fecho dos
criterios ja cumpridos da Fase 7. Metodo: leitura adversarial e execucao real
(smoke com 12 verificacoes sobre a logica de divergencias; gate dedicado; corredor
completo), tudo verde.

## Resultado

Zero defeitos P0 e zero defeitos P1. A regra de quatro-olhos fica documentada como
adiada para o incremento 2.

## Analise

### A-RC1 Dinheiro recebido e nao aplicado invisivel (P1 candidato) - MITIGADO
O relatorio expoe as transacoes recebidas pelo gateway sem pagamento associado
(dinheiro em limbo), por escola, com totais. Deixa de passar despercebido. Verificado.

### A-RC2 Divergencia de montante invisivel (P1 candidato) - MITIGADO
Transacoes conciliadas cujo valor difere do pagamento sao listadas com a diferenca.
Verificado pelo smoke (gateway 1000 vs pagamento 900).

### A-RC3 Pagamento movel sem evidencia de gateway (P2 candidato) - MITIGADO
Pagamentos registados como moveis sem transacao de gateway correspondente sao
listados (lancamento manual ou webhook em falta). Verificado.

### A-RC4 Idempotencia e-Mola (P1 candidato) - CONFIRMADO
O webhook e-Mola, tal como o M-Pesa, faz pre-verificacao por (escola, referencia) e
devolve "Transaccao ja registada"; a chave unica garante-o ao nivel da base de dados.
Sem dupla contagem. Documentado.

### A-RC5 So leitura, sem superficie nova - VERIFICADO
A view nao tem POST, formularios nem escrita; a logica de dominio so faz SELECT e e
fail-closed por escola. Manifesto 196 inalterado. Gate confirma.

### A-RC6 Acesso - VERIFICADO
Restrito por sige_mpesa_pode_gerir (financeiro, Direccao, Secretaria Geral, admin).
Sem fuga entre escolas (consultas por escola_id).

### A-RC7 Design - VERIFICADO
Estilo so com tokens; sem regressao de primitivos magicos nem de cores; namespaces
de CSS limpos.

### A-RC8 Quatro-olhos - DECISAO: ADIADO
A dupla aprovacao para estornos e reaberturas e uma alteracao de fluxo; fica para o
incremento 2 da Fase 7. Documentado.

## Decisao

Decisao: APROVADO para entrega. Relatorio de divergencias entregue, idempotencia
e-Mola confirmada, criterios ja cumpridos fechados. Quatro-olhos adiado. Zero P0/P1.
