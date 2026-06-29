# RISK REGISTER - v12.12.1

## P0

- P0-001 `sige_desp_print` cross-tenant em despesas: fechado nesta versao. Evidencia: `tools/check-tenant-sensitive-queries.php`.

## P1

- P1-001 Manifesto sem `wp_ajax:sige_settings_save`: fechado nesta versao.
- P1-002 Manifesto sem query handlers/template_redirect: fechado nesta versao.
- P1-003 Gate do manifesto com falso verde: fechado com extractor independente e testes negativos.

## P2

- P2-001 O scan de tenant global ainda nao cobre todas as queries sensiveis do produto. Mantido para Fase 3.
- P2-002 Divida `current_user_can` permanece registada para Fase 1/Fase 2.
- P2-003 Secret Vault completo permanece para Fase 5.
- P2-004 CSP enforcement permanece para fase propria.

## P3

- P3-001 Alguns registos de dependencias externas ainda misturam host runtime e referencias de namespace/documentacao. Mantido como melhoria de precisao.
