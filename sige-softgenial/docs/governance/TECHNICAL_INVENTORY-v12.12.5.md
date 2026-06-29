# TECHNICAL INVENTORY v12.12.5

## Superficie
174 superficies: 113 wp_ajax, 36 admin_post, 1 admin_post_nopriv, 5 rest_route, 5 query_handler, 9 cron_hook, 1 shortcode e 4 wp_hook.

## Views
54 views allowlisted e 54 views com permissao explicita.

## Permissoes
Baseline: current_user_can congelado sem aumento; sige_can preservado. O Security Kernel continua a usar a matriz SIGE e authorization_mode/delegated quando aplicavel.

## Tenant
Fase correctiva foca M-Pesa/e-Mola tenant-scoped storage e mantem o restante para Fase 3. Fallbacks tenant continuam inventariados.

## Segredos
Credenciais de pagamentos moveis continuam sem Secret Vault definitivo, mas a escrita ja nao e global em contexto multi-escola; fica scoped por escola.

## Dependencias
Dependencias externas mantidas no registo de supply chain da Fase 0.

## Security Kernel
Runtime corrigido para REST route metadata, shortcode via pre_do_shortcode_tag, wp_hook via add_action dinamico, query_handler e piloto enforce.
