# PUBLIC ENDPOINTS POLICY v12.12.6

Cada endpoint publico deve ter autenticacao adequada, token/HMAC quando aplicavel, rate limit e auditoria proporcional ao risco.

## endpoint publico
- REST callbacks M-Pesa/e-Mola: observe nesta fase, enforcement token/HMAC fica para lockdown especifico.
- Webhooks WhatsApp/Hub/process-queue: observe nesta fase.
- `sige_recibo`: query handler publico tokenizado a reforcar em fase futura.

## autenticacao
Endpoints publicos nao usam nonce WordPress; devem usar token, HMAC ou assinatura de dominio.

## rate limit
Rate limit deve ser activado quando cada endpoint migrar de observe para enforce.

## Cobertura por ID publico

- `admin_post_nopriv:sige_process_queue_now`: endpoint publico de fila/cron; deve usar chave operacional e rate limit quando migrar para enforce.
- `query_handler:sige_recibo`: recibo publico tokenizado; deve preservar token/HMAC e auditoria ao migrar para enforce.
- `rest_route:sige/v1:/emola/callback`: callback financeiro e-Mola; observe nesta fase, enforcement token/HMAC na fase de lockdown financeiro.
- `rest_route:sige/v1:/hub/instant-refresh`: endpoint Hub; observe nesta fase.
- `rest_route:sige/v1:/mpesa/callback`: callback financeiro M-Pesa; observe nesta fase, enforcement token/HMAC na fase de lockdown financeiro.
- `rest_route:sige/v1:/process-queue`: processamento de fila; observe nesta fase.
- `rest_route:sige/v1:/whatsapp-webhook`: webhook WhatsApp; observe nesta fase.
- `shortcode:sige_portal`: shortcode publico do portal; observe nesta fase para evitar regressao no portal.
