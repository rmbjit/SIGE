# Technical Inventory v12.12.4 - Security Kernel Foundation

## Superficie
Total de superficies: 174.
Por tipo: {'admin_post': 36, 'admin_post_nopriv': 1, 'cron_hook': 9, 'query_handler': 5, 'rest_route': 5, 'shortcode': 1, 'wp_ajax': 113, 'wp_hook': 4}.
Enforce: 4.
Observe: 170.
Inclui `query_handler`, REST, AJAX, admin-post, cron hooks, shortcode e wp hooks.

## Views
Views allowlist: 54.
Views com Permissoes: 54.

## Permissoes
`current_user_can`: 471.
`sige_can`: 57.
A Fase 1 nao migra toda a autorizacao legada; congela baseline e cria kernel para novas fases.

## Tenant
Fallbacks tenant candidatos: 190.
O kernel exige tenant valido nas regras piloto com `tenant_required=true`.
A remocao global de fallbacks fica para a Fase 3.

## Segredos
Opcoes WordPress inventariadas: 136.
Segredos continuam registados para Secret Vault futuro, sem migracao nesta fase.

## Dependencias
Hosts externos inventariados: 12.
A reducao de dependencias externas fica para fases de documentos/CSP/supply chain.

## query_handler
`query_handler:sige_desp_print` esta em enforcement piloto com nonce `sige_desp_print`, permissao financeira, tenant e auditoria.
