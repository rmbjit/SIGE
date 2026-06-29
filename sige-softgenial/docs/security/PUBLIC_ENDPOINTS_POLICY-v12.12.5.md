# PUBLIC ENDPOINTS POLICY v12.12.5

Cada endpoint publico exige autenticacao adequada ao canal, rate limit quando o risco for alto/critico e auditoria quando tratar dados sensiveis. Os endpoints REST publicos permanecem em observe no Security Kernel ate lockdown especifico por dominio, mas ficam no contrato runtime.

## endpoint publico

| Endpoint | Autenticacao | Rate limit | Auditoria | Estado |
|---|---|---|---|---|
| `admin_post_nopriv:sige_process_queue_now` | chave cron/token interno | esperado | esperado | observe |
| `query_handler:sige_recibo` | token contextual de recibo | avaliado por dominio | esperado | observe |
| `rest_route:sige/v1:/mpesa/callback` | token scoped por escola, com `escola_id` quando disponivel | esperado | esperado | observe |
| `rest_route:sige/v1:/emola/callback` | token scoped por escola, com `escola_id` quando disponivel | esperado | esperado | observe |
| `rest_route:sige/v1:/hub/instant-refresh` | token/HMAC do Hub | esperado por dominio | esperado por dominio | observe |
| `rest_route:sige/v1:/process-queue` | chave cron/token interno | esperado | esperado | observe |
| `rest_route:sige/v1:/whatsapp-webhook` | token/HMAC/provider signature | esperado por dominio | esperado por dominio | observe |
| `shortcode:sige_portal` | contrato de portal/credencial do encarregado | nao aplicavel por request render | auditoria por operacao interna | observe |

## Decisao
Nenhum endpoint publico entra em enforce sem token/HMAC runtime especifico e teste negativo correspondente. M-Pesa/e-Mola receberam correccao de token scoped por escola nesta versao, mas o REST enforcement publico fica para a fase de lockdown por dominio.
