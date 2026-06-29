# Public Endpoints Policy v12.12.4

Esta politica cobre as superficies publicas declaradas no manifesto `ACTION_SURFACE_MANIFEST-v12.12.4.json`.
Na Fase 1, estes endpoints permanecem em `observe` no Security Kernel, salvo decisao explicita futura, para evitar regressao em webhooks, portal e crons externos. O lockdown fica para a fase propria de Critical Actions Lockdown.

## Regras obrigatorias

1. Endpoint publico nao deve depender de nonce WordPress quando for chamado por sistemas externos.
2. Webhooks devem usar token, HMAC, assinatura ou segredo partilhado do dominio.
3. Endpoints publicos de alto/critico devem ter rate limit antes de passar para `enforce`.
4. Endpoints publicos de alto/critico devem produzir auditoria ou log de seguranca.
5. Qualquer endpoint publico novo deve aparecer neste documento, no manifesto e nas regras do Security Kernel.

## Superficies publicas cobertas

### admin_post_nopriv:sige_process_queue_now

- Tipo: `admin_post_nopriv`
- Finalidade: disparo controlado de processamento de fila.
- Estado nesta fase: `observe`.
- Proteccao esperada: chave/segredo de cron, rate limit e auditoria antes de enforcement.
- Risco: medio/alto operacional.

### query_handler:sige_recibo

- Tipo: query handler publico/tokenizado.
- Finalidade: visualizacao publica de recibo quando existir token/parametro valido.
- Estado nesta fase: `observe`.
- Proteccao esperada: token forte, validacao de escopo, expiração quando aplicavel e auditoria de acesso sensivel.
- Risco: alto por poder expor documento financeiro.

### rest_route:sige/v1:/emola/callback

- Tipo: REST webhook.
- Finalidade: callback e-Mola.
- Estado nesta fase: `observe`.
- Proteccao esperada: token/HMAC/assinatura do provedor, idempotencia, rate limit e auditoria financeira.
- Risco: critico financeiro.

### rest_route:sige/v1:/hub/instant-refresh

- Tipo: REST endpoint publico/controlado.
- Finalidade: refresh/coordenação com Hub.
- Estado nesta fase: `observe`.
- Proteccao esperada: token do hub, validação de origem quando aplicavel, rate limit e log operacional.
- Risco: medio/alto operacional.

### rest_route:sige/v1:/mpesa/callback

- Tipo: REST webhook.
- Finalidade: callback M-Pesa.
- Estado nesta fase: `observe`.
- Proteccao esperada: token/HMAC/assinatura do provedor, idempotencia, rate limit e auditoria financeira.
- Risco: critico financeiro.

### rest_route:sige/v1:/process-queue

- Tipo: REST endpoint publico/controlado.
- Finalidade: processamento remoto/controlado de fila.
- Estado nesta fase: `observe`.
- Proteccao esperada: chave/segredo de cron/API, rate limit e auditoria.
- Risco: medio/alto operacional.

### rest_route:sige/v1:/whatsapp-webhook

- Tipo: REST webhook.
- Finalidade: recepcao de eventos WhatsApp.
- Estado nesta fase: `observe`.
- Proteccao esperada: token/assinatura do provedor, rate limit e logs de seguranca sem expor mensagens sensiveis.
- Risco: alto por tratar comunicacoes externas.

### shortcode:sige_portal

- Tipo: shortcode publico/autenticavel.
- Finalidade: portal do encarregado/aluno.
- Estado nesta fase: `observe`.
- Proteccao esperada: autenticacao propria do portal, sessao valida, escopo de aluno/encarregado e auditoria de acesso a dados sensiveis.
- Risco: alto por expor dados escolares.

## Decisao da Fase 1

Todos os endpoints publicos listados ficam cobertos por manifesto, politica e regras do Security Kernel. O enforcement especifico por token/HMAC sera tratado na fase de lockdown critico, para evitar aplicar nonces WordPress indevidos a webhooks externos.
