# PUBLIC ENDPOINTS POLICY - v12.12.1

Esta politica lista cada endpoint publico do manifesto. endpoint publico nao significa livre; significa superficie acessivel sem sessao WordPress obrigatoria ou embutida em shortcode/REST/token.

| Endpoint | Modulo | Risco | autenticacao e rate limit |
|---|---|---|---|
| `admin_post_nopriv:sige_process_queue_now` | sistema | high | autenticacao/token conforme handler; rate limit quando aplicavel |
| `query_handler:sige_recibo` | financeiro | high | autenticacao/token conforme handler; rate limit quando aplicavel |
| `rest_route:sige/v1:/emola/callback` | financeiro | critical | autenticacao/token conforme handler; rate limit quando aplicavel |
| `rest_route:sige/v1:/hub/instant-refresh` | sistema | medium | autenticacao/token conforme handler; rate limit quando aplicavel |
| `rest_route:sige/v1:/mpesa/callback` | financeiro | critical | autenticacao/token conforme handler; rate limit quando aplicavel |
| `rest_route:sige/v1:/process-queue` | sistema | high | autenticacao/token conforme handler; rate limit quando aplicavel |
| `rest_route:sige/v1:/whatsapp-webhook` | comunicacao | medium | autenticacao/token conforme handler; rate limit quando aplicavel |
| `shortcode:sige_portal` | portal | medium | autenticacao/token conforme handler; rate limit quando aplicavel |

## Regras

- Webhooks de pagamento devem usar token/HMAC/assinatura e idempotencia.
- `query_handler:sige_recibo` deve usar token, TTL, rate limit e validacao de contexto.
- Shortcode publico deve limitar dados expostos e delegar autorizacao aos handlers internos.
- Qualquer novo endpoint publico sem politica deve falhar gate.
