# SPEC-MPESA - Pagamentos M-Pesa / e-Mola com conciliação automática

**Estado:** especificado (v12.11.9.90). Implementação: Sprint 2.
**Valor:** o encarregado paga do telemóvel; o lançamento liquida-se sozinho;
a secretaria deixa de digitar pagamentos um a um.

## Fundações que JÁ existem (não reconstruir)
- Métodos de pagamento modelados desde a v12.11.9.31 (POS, bancos, numerário);
- Fórmulas canónicas de saldo (sige_fin_saldo_sql / sige_fin_saldo_lancamento)
  e o sincronizador sige_fin_atualizar_status_lancamento: a conciliação
  REGISTA pagamentos pelos caminhos existentes, nunca recalcula saldos;
- Recibo automático por WhatsApp já dispara na liquidação (motor actual);
- Auditoria (sige_security_log / sige_audit_log) e multi-tenancy por escola_id.

## Arquitectura (Vodacom M-Pesa Moçambique OpenAPI, C2B)

### Novos ficheiros
```
includes/payments/mpesa-config.php    opções por escola (api key, public key,
                                      service provider code, ambiente sandbox/prod)
includes/payments/mpesa-client.php    cliente HTTP: c2bPayment singleStage +
                                      queryTransactionStatus; assinatura RSA da
                                      API key com a public key da Vodacom
includes/payments/mpesa-webhook.php   rota REST sige/v1/mpesa/callback com token
                                      secreto por escola; valida, grava, concilia
includes/payments/mpesa-conciliacao.php  motor de matching
admin/finance/mpesa-view.php          ecrã: transacções recebidas, estado da
                                      conciliação, fila de pendentes manuais
```

### Tabela nova (via db-migration-engine, incremental)
```
sige_mpesa_transacoes
  id, escola_id, referencia_mpesa (UNIQUE com escola_id), msisdn,
  valor, moeda, estado ('recebida','conciliada','pendente_manual','rejeitada'),
  lancamento_id NULL, payload_json, criado_em, conciliado_em, conciliado_por
```

### Fluxo C2B (encarregado inicia no telemóvel)
1. Encarregado paga para o código da escola com REFERÊNCIA = código do aluno
   (o mesmo código do crachá da Portaria: já existe, já está impresso);
2. Vodacom chama o callback; o webhook valida o token + escola, grava a
   transacção como 'recebida' e dispara a conciliação;
3. Conciliação automática: referência identifica o aluno; o motor procura o
   lançamento em aberto mais antigo com saldo <= valor (ordem: mais vencido
   primeiro) e regista o pagamento pelo caminho normal do financeiro
   (mesma função que o ecrã de pagamentos usa). Sobra de valor: crédito em
   conta-corrente (mecanismo existente);
4. Sem match seguro (referência inválida, valor anómalo): estado
   'pendente_manual'; aparece no ecrã para a secretaria resolver em 2 cliques;
5. Recibo WhatsApp sai automaticamente pela via existente.

### Regras de segurança
- Webhook: HTTPS only, token por escola em header, IP allowlist da Vodacom
  opcional, idempotência por referencia_mpesa (re-entrega da Vodacom não
  duplica), rate limit pelo helper existente;
- NUNCA tocar nas fórmulas financeiras: a conciliação chama os handlers de
  pagamento já aprovados, com user "Sistema M-Pesa" auditável;
- Modo sandbox por defeito; produção exige preencher credenciais + toggle.

### e-Mola (Movitel)
Mesma arquitectura, segundo provider em includes/payments/emola-client.php,
mesma tabela (coluna provider). Fase 2 do módulo: primeiro M-Pesa estável.

## Critério de pronto
Pagamento sandbox liquida lançamento de teste de ponta a ponta com recibo
WhatsApp; pendente manual resolve-se no ecrã; idempotência provada com
callback duplicado; smoke próprio verde.
