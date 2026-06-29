# TECHNICAL INVENTORY - v12.12.1

## Superficie

Total de superficie inventariada: 174.

- admin_post: 36
- admin_post_nopriv: 1
- cron_hook: 9
- query_handler: 5
- rest_route: 5
- shortcode: 1
- wp_ajax: 113
- wp_hook: 4

A v12.12.1 corrige a lacuna da v12.12.0: o inventario agora inclui `query_handler`, `wp_hook` e hooks dinamicos resolvidos por constantes.

## Views

- Views na allowlist: 54
- Views com permissao: 54

## Permissoes

- `current_user_can`: 477
- `sige_can`: 57
- baseline: `docs/security/AUTHORIZATION_DEBT_BASELINE-v12.12.1.json`

## Tenant

- Fallbacks candidatos registados: 194
- Gate correctivo especifico: `tools/check-tenant-sensitive-queries.php`
- Registo canonico: `docs/security/TENANT_FALLBACK_BASELINE-v12.12.1.json`

## Segredos

- Opcoes WordPress registadas: 137
- Candidatos sensiveis: 46
- Registo canonico: `docs/security/SECRETS_OPTIONS_BASELINE-v12.12.1.json`

## Dependencias

- Hosts externos registados: 13
- Registo canonico: `docs/security/EXTERNAL_DEPENDENCIES_BASELINE-v12.12.1.json`

## query_handler

Query handlers obrigatorios na versao correctiva:

- `query_handler:sige_desp_print`
- `query_handler:sige_recibo`
- `query_handler:sige_portaria_camera`
- `query_handler:sige_print`
- `query_handler:sige_billing_bypass`

## P0 corrigido

`includes/finance-core.php` passou a filtrar comprovativo e relatorio de despesas por `escola_id = %d`, com nonce, permissao e auditoria.
