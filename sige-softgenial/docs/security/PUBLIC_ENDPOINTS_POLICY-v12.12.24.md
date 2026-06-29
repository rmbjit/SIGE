# PUBLIC ENDPOINTS POLICY - v12.12.24

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

## v12.12.21 (Fase 7 incr 1: reconciliacao e divergencias)

- O ecra de reconciliacao e so de leitura, sem POST nem accao AJAX/admin-post: nao introduz endpoint publico nem superficie nova. Manifesto 196 inalterado.

## Actualizacao v12.12.21 (Fase 7 incremento 2)

O endpoint publico (sem autenticacao) nao aumenta. O endpoint de decisao (sige_fin_aprovacao_decidir) e autenticado, com nonce e rate limit (sk_view_action_financeiro_aprovacoes_sige_fin_aprovacao_decidir, max 20 / 300s).

## Actualizacao v12.12.22 (release correctiva)

Esta versao e uma correccao de roteamento da Fase 7, construida sobre a v12.12.21. As views financeiro-reconciliacao (Incremento 1) e financeiro-aprovacoes (Incremento 2, regra de quatro-olhos), entregues na Fase 7 mas em falta na allowlist anti-LFI e na matriz de permissoes do admin-shell, ficavam inalcancaveis: a guarda reescrevia o pedido para o painel inicial. Foram repostas em ambas as listas, com as permissoes identicas as guardas internas de cada view. Nao ha alteracao da superficie de accao (manifesto e regras do Security Kernel mantem-se em 197), nao ha migracao de dados (SCHEMA_VERSION inalterada) e as regras de calculo financeiro nao sao tocadas.

Nao ha qualquer endpoint publico novo. A autenticacao e o rate limit das superficies existentes mantem-se exactamente como na v12.12.21. As views repostas sao de administracao, atras de autenticacao e das guardas de permissao.

## Actualizacao v12.12.24 (Fase 8 Incr 1)

Nenhum endpoint publico novo. O inventario de dados pessoais e um ecra interno de administracao, sujeito a autenticacao e a permissao privacidade.inventario_ver, sem POST nem AJAX. Por nao expor superficie publica, nao se aplica rate limit adicional; a politica de endpoints publicos mantem-se sem alteracoes.

## Actualizacao v12.12.24 - Fase 8 incremento 2

- O novo endpoint admin_post:sige_privacidade_exportar NAO e um endpoint publico: exige sessao autenticada e a permissao privacidade.acesso_exportar. Nao ha endpoint publico novo nesta versao.
- autenticacao: obrigatoria (nonce verificado e permissao aplicada pelo Kernel em enforce). rate limit: 10 pedidos por 300 segundos.
