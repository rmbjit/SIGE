# PUBLIC ENDPOINTS POLICY v12.12.2

Esta politica documenta cada endpoint publico declarado no manifesto. Todo endpoint publico deve ter autenticacao/token, rate limit esperado, auditoria quando aplicavel e revisao futura pelo Security Kernel.

## endpoint publico: admin_post_nopriv:sige_process_queue_now
- Finalidade: processamento controlado da fila.
- autenticacao: chave/cron token esperado.
- rate limit: esperado.
- auditoria: esperada quando houver efeito operacional.

## endpoint publico: query_handler:sige_recibo
- Finalidade: recibo publico tokenizado.
- autenticacao: token/hash documental esperado.
- rate limit: esperado.
- auditoria: esperada no acesso a documento financeiro.

## endpoint publico: rest_route:sige/v1:/emola/callback
- Finalidade: callback e-Mola.
- autenticacao: assinatura/HMAC/token do provedor esperado.
- rate limit: esperado.
- auditoria: obrigatoria por efeito financeiro.

## endpoint publico: rest_route:sige/v1:/hub/instant-refresh
- Finalidade: refrescamento do Hub.
- autenticacao: token do Hub esperado.
- rate limit: esperado.
- auditoria: esperada.

## endpoint publico: rest_route:sige/v1:/mpesa/callback
- Finalidade: callback M-Pesa.
- autenticacao: assinatura/HMAC/token do provedor esperado.
- rate limit: esperado.
- auditoria: obrigatoria por efeito financeiro.

## endpoint publico: rest_route:sige/v1:/process-queue
- Finalidade: processamento de fila.
- autenticacao: token/cron key esperado.
- rate limit: esperado.
- auditoria: esperada.

## endpoint publico: rest_route:sige/v1:/whatsapp-webhook
- Finalidade: webhook WhatsApp.
- autenticacao: token/assinatura do canal esperado.
- rate limit: esperado.
- auditoria: esperada.

## endpoint publico: shortcode:sige_portal
- Finalidade: renderizar portal conforme autenticacao/escopo interno.
- autenticacao: WordPress/portal conforme contexto.
- rate limit: nao aplicavel ao shortcode renderizado, mas acoes internas devem ser protegidas.
- auditoria: conforme acao interna.
