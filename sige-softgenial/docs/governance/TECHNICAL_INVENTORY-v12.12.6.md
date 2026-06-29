# TECHNICAL INVENTORY v12.12.6

## Superficie
Inventario v12.12.6: 174 superficies de execucao.

- admin_post: 36
- admin_post_nopriv: 1
- cron_hook: 9
- query_handler: 5
- rest_route: 5
- shortcode: 1
- wp_ajax: 113
- wp_hook: 4

## Views
Views allowlist: 54. Views com permissao explicita: 54.

## Permissoes
A divida `current_user_can` continua registada em baseline. `sige_can` e a matriz SIGE continuam a ser a direccao de convergencia. Esta versao nao migra toda autorizacao legada; fecha o piloto do Security Kernel.

## Tenant
Query handlers agora tem `runtime_hooks` e `runtime_priority=-1000`. `settings_save` passa a exigir tenant. O repository falha fechado em escritas `sige_config` sem `escola_id` resolvido, sem fallback para escola 1 em escrita.

## Segredos
Segredos/opcoes continuam registados nos baselines. Secret Vault fica para fase propria.

## Dependencias
Dependencias externas continuam registadas e classificadas no baseline da versao. Reducao de dependencias fica para fases futuras.

## Security Kernel
Security Kernel: 174 regras; 4 em enforce; 170 em observe. Enforce piloto: M-Pesa config, e-Mola config, `sige_desp_print`, `sige_settings_save`.
