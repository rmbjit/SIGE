# PUBLIC ENDPOINTS POLICY - v12.12.0

Politica para cada endpoint publico detectado no manifesto. A palavra-chave deste documento e endpoint publico.

Todo endpoint publico deve ter autenticacao, assinatura, token, nonce, HMAC ou permission callback adequada ao seu contexto; deve ter rate limit quando processa escrita, webhook, fila ou integracao externa; e deve ter auditoria quando o risco for alto ou critico.

## PUBLIC-ENDPOINT: admin_post_nopriv:sige_process_queue_now

- Tipo: admin_post_nopriv
- Modulo: sistema
- Risco: high
- Registos: includes/cron-tasks.php:497
- Mecanismo de autenticacao esperado: hmac_token_or_signature_expected
- Rate limit: True
- Auditoria: True
- Teste: coberto por `php tools/check-public-endpoints-policy.php`; enforcement definitivo nas Fases 1 e 2.

## PUBLIC-ENDPOINT: rest_route:sige/v1:/emola/callback

- Tipo: rest_route
- Modulo: financeiro
- Risco: critical
- Registos: includes/payments/emola-webhook.php:53
- Mecanismo de autenticacao esperado: hmac_token_or_signature_expected
- Rate limit: True
- Auditoria: True
- Teste: coberto por `php tools/check-public-endpoints-policy.php`; enforcement definitivo nas Fases 1 e 2.

## PUBLIC-ENDPOINT: rest_route:sige/v1:/hub/instant-refresh

- Tipo: rest_route
- Modulo: sistema
- Risco: medium
- Registos: includes/hub/class-sige-hub-client.php:47
- Mecanismo de autenticacao esperado: hmac_token_or_signature_expected
- Rate limit: False
- Auditoria: False
- Teste: coberto por `php tools/check-public-endpoints-policy.php`; enforcement definitivo nas Fases 1 e 2.

## PUBLIC-ENDPOINT: rest_route:sige/v1:/mpesa/callback

- Tipo: rest_route
- Modulo: financeiro
- Risco: critical
- Registos: includes/payments/mpesa-webhook.php:18
- Mecanismo de autenticacao esperado: hmac_token_or_signature_expected
- Rate limit: True
- Auditoria: True
- Teste: coberto por `php tools/check-public-endpoints-policy.php`; enforcement definitivo nas Fases 1 e 2.

## PUBLIC-ENDPOINT: rest_route:sige/v1:/process-queue

- Tipo: rest_route
- Modulo: sistema
- Risco: high
- Registos: includes/cron-tasks.php:499
- Mecanismo de autenticacao esperado: hmac_token_or_signature_expected
- Rate limit: True
- Auditoria: True
- Teste: coberto por `php tools/check-public-endpoints-policy.php`; enforcement definitivo nas Fases 1 e 2.

## PUBLIC-ENDPOINT: rest_route:sige/v1:/whatsapp-webhook

- Tipo: rest_route
- Modulo: comunicacao
- Risco: medium
- Registos: includes/whatsapp-guardian.php:191, includes/whatsapp-recovery-mode.php:787
- Mecanismo de autenticacao esperado: hmac_token_or_signature_expected
- Rate limit: False
- Auditoria: False
- Teste: coberto por `php tools/check-public-endpoints-policy.php`; enforcement definitivo nas Fases 1 e 2.

## PUBLIC-ENDPOINT: shortcode:sige_portal

- Tipo: shortcode
- Modulo: portal
- Risco: medium
- Registos: includes/portal-logic.php:2076, includes/portal-logic.php:2086
- Mecanismo de autenticacao esperado: hmac_token_or_signature_expected
- Rate limit: False
- Auditoria: False
- Teste: coberto por `php tools/check-public-endpoints-policy.php`; enforcement definitivo nas Fases 1 e 2.
