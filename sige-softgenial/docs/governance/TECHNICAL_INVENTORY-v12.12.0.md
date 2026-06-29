# TECHNICAL INVENTORY - v12.12.0

Inventario gerado de forma reproduzivel por `php tools/inventory-surface.php --write`.

## Superficie

- Ficheiros totais: 1014
- PHP runtime: 322
- Superficie de accao total: 162

### Distribuicao por tipo

- admin_post: 36
- admin_post_nopriv: 1
- cron_hook: 7
- rest_route: 5
- shortcode: 1
- wp_ajax: 112

## Views

- Views na allowlist: 54
- Views com permissao mapeada: 54
- Views sem permissao apos correcao: 0

Views corrigidas nesta fase: `comunicacoes_central`, `financeiro-mpesa`, `presencas`, `whatsapp_central`, `whatsapp_circulares`, `whatsapp_diag`.

## Permissoes

- `current_user_can`: 477
- `sige_can`: 54

O objectivo desta versao e congelar a divida, nao migrar toda a autorizacao. A migracao para policy engine pertence as Fases 1 e 2.

## Tenant

- Candidatos a fallback `escola_id = 1`: 194
- Registo canonico: `docs/security/TENANT_FALLBACK_BASELINE-v12.12.0.json`

A remocao definitiva pertence a Fase 3.

## Segredos

- Opcoes WordPress inventariadas: 137
- Candidatos sensiveis: 46

A migracao para Secret Vault pertence a Fase 5.

## Dependencias

- Hosts externos detectados: 13
- Registo canonico: `docs/security/EXTERNAL_DEPENDENCIES_BASELINE-v12.12.0.json`

## Evidencia

Os JSONs gerados nesta fase sao a fonte reproduzivel para os gates de governacao.

## Correcao de permissao das views

A correcao P1 foi feita de forma declarativa: registry de permissoes, mapa de views, helpers internos alinhados a `sige_can` e migracao aditiva de permissoes para perfis padrao. Nao ha alteracao de schema.
