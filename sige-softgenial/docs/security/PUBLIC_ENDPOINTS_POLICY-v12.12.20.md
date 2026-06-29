# PUBLIC ENDPOINTS POLICY - v12.12.20

## endpoint publico
A v12.12.11 nao adiciona nem remove endpoints publicos. O incremento endurece a camada de escrita por tenant; nenhum sumidouro tocado e publico. Os endpoints publicos do manifesto permanecem:

- `admin_post_nopriv:sige_process_queue_now` - processamento controlado da fila; chave/token operacional e rate limit.
- `query_handler:sige_recibo` - documento publico tokenizado; valida token assinado.
- `rest_route:sige/v1:/emola/callback` - callback e-Mola; token validator tenant-scoped.
- `rest_route:sige/v1:/hub/instant-refresh` - refresh do hub; autentica origem.
- `rest_route:sige/v1:/mpesa/callback` - callback M-Pesa; token validator tenant-scoped.
- `rest_route:sige/v1:/process-queue` - processamento por REST; token operacional e rate limit.
- `rest_route:sige/v1:/whatsapp-webhook` - webhook WhatsApp; valida token/origem e rate limit.
- `shortcode:sige_portal` - portal do aluno/encarregado; autenticacao propria no fluxo.

## autenticacao
M-Pesa/e-Mola usam validator tokenizado no Kernel. Nota tenant: quando uma escrita interna (propria ou delegada) nao resolve a escola em modo estrito, os guards de sumidouro fecham a operacao em vez de gravar com escola 0 ou na escola 1.

## rate limit
Limites existentes mantidos. O incremento nao altera rate limits.

## v12.12.20 (Fase 7 incr 1: reconciliacao e divergencias)

- O ecra de reconciliacao e so de leitura, sem POST nem accao AJAX/admin-post: nao introduz endpoint publico nem superficie nova. Manifesto 196 inalterado.
