# PUBLIC ENDPOINTS POLICY - v12.12.7.1

## endpoint publico
A v12.12.7.1 nao adiciona endpoints publicos. O novo `query_handler:sige_dev_print` e privado: exige utilizador autenticado, nonce e permissao. Os endpoints publicos declarados no manifesto permanecem os herdados da v12.12.7:

- `admin_post_nopriv:sige_process_queue_now` - processamento controlado da fila; deve usar chave/token operacional e rate limit.
- `query_handler:sige_recibo` - documento publico tokenizado; deve validar token assinado e nao expor documentos por ID simples.
- `rest_route:sige/v1:/emola/callback` - callback e-Mola; em enforcement do Security Kernel com token validator tenant-scoped.
- `rest_route:sige/v1:/hub/instant-refresh` - refresh do hub; deve autenticar origem autorizada.
- `rest_route:sige/v1:/mpesa/callback` - callback M-Pesa; em enforcement do Security Kernel com token validator tenant-scoped.
- `rest_route:sige/v1:/process-queue` - processamento de fila por REST; deve usar token operacional e rate limit.
- `rest_route:sige/v1:/whatsapp-webhook` - webhook WhatsApp; deve validar token/origem e aplicar rate limit.
- `shortcode:sige_portal` - portal do aluno/encarregado; publico por natureza, mas requer autenticacao/identificacao propria no fluxo do portal.

## autenticacao
M-Pesa/e-Mola usam validator tokenizado no Security Kernel. Os demais endpoints mantem politica formal documentada e devem entrar em enforcement especifico por fase, sem aceitar ID simples como autenticacao. O documento de cobranca `sige_dev_print` nao e publico: autentica por sessao WordPress, nonce e permissao SIGE.

## rate limit
Endpoints publicos criticos em enforcement possuem rate limit no Kernel. O `query_handler:sige_dev_print`, embora privado, tambem tem rate limit (sk_query_handler_sige_dev_print, 30 pedidos por 300 segundos). Endpoints publicos ainda fora do enforcement estrito ficam registados para lockdown progressivo.
