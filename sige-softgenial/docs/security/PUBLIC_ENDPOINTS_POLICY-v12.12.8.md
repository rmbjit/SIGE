# PUBLIC ENDPOINTS POLICY - v12.12.8

## endpoint publico
A v12.12.8 nao adiciona nem remove endpoints publicos. O incremento endurece caminhos de ESCRITA autenticados; nenhum dos pontos migrados e publico. Os endpoints publicos declarados no manifesto permanecem os herdados:

- `admin_post_nopriv:sige_process_queue_now` - processamento controlado da fila; deve usar chave/token operacional e rate limit.
- `query_handler:sige_recibo` - documento publico tokenizado; deve validar token assinado e nao expor documentos por ID simples.
- `rest_route:sige/v1:/emola/callback` - callback e-Mola; em enforcement do Security Kernel com token validator tenant-scoped.
- `rest_route:sige/v1:/hub/instant-refresh` - refresh do hub; deve autenticar origem autorizada.
- `rest_route:sige/v1:/mpesa/callback` - callback M-Pesa; em enforcement do Security Kernel com token validator tenant-scoped.
- `rest_route:sige/v1:/process-queue` - processamento de fila por REST; deve usar token operacional e rate limit.
- `rest_route:sige/v1:/whatsapp-webhook` - webhook WhatsApp; deve validar token/origem e aplicar rate limit.
- `shortcode:sige_portal` - portal do aluno/encarregado; publico por natureza, mas requer autenticacao/identificacao propria no fluxo do portal.

## autenticacao
M-Pesa/e-Mola usam validator tokenizado no Security Kernel. Os demais endpoints mantem politica formal documentada e devem entrar em enforcement especifico por fase, sem aceitar ID simples como autenticacao. Nota relevante para tenant: os endpoints publicos resolvem a escola por token ou por contexto de pedido; quando uma escrita interna nao resolve a escola em modo multi-escola estrito, o novo resolvedor `sige_require_escola_id` fecha a operacao em vez de cair para a escola 1.

## rate limit
Endpoints publicos criticos em enforcement possuem rate limit no Kernel. Endpoints publicos ainda fora do enforcement estrito ficam registados para lockdown progressivo. O incremento v12.12.8 nao altera limites existentes.
