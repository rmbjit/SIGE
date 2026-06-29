# PUBLIC ENDPOINTS POLICY - v12.12.7

## endpoint publico
Endpoints publicos declarados no manifesto v12.12.7:

- `admin_post_nopriv:sige_process_queue_now` - processamento controlado da fila; deve usar chave/token operacional e rate limit.
- `query_handler:sige_recibo` - documento público tokenizado; deve validar token assinado e não expor documentos por ID simples.
- `rest_route:sige/v1:/emola/callback` - callback e-Mola; em enforcement do Security Kernel com token validator tenant-scoped.
- `rest_route:sige/v1:/hub/instant-refresh` - refresh do hub; deve autenticar origem autorizada.
- `rest_route:sige/v1:/mpesa/callback` - callback M-Pesa; em enforcement do Security Kernel com token validator tenant-scoped.
- `rest_route:sige/v1:/process-queue` - processamento de fila por REST; deve usar token operacional e rate limit.
- `rest_route:sige/v1:/whatsapp-webhook` - webhook WhatsApp; deve validar token/origem e aplicar rate limit.
- `shortcode:sige_portal` - portal do aluno/encarregado; público por natureza, mas requer autenticação/identificação própria no fluxo do portal.

## autenticacao
M-Pesa/e-Mola usam validator tokenizado no Security Kernel. Os demais endpoints mantêm política formal documentada e devem entrar em enforcement específico por fase, sem aceitar ID simples como autenticação.

## rate limit
Endpoints públicos críticos em enforcement possuem rate limit no Kernel. Endpoints públicos ainda fora do enforcement estrito ficam registados para lockdown progressivo.
