# TENANT ISOLATION REGISTER - v12.12.8

## escola_id
Este incremento introduz o resolvedor canonico fail-closed `sige_require_escola_id(string $contexto): int` em `includes/multitenancy.php`. Devolve o id da escola resolvida e, quando nao ha escola valida (modo multi-escola estrito sem contexto, em que `sige_get_escola_id()` devolve 0), bloqueia a operacao em vez de cair silenciosamente para a escola 1.

Comportamento por contexto:
- Caminhos de ESCRITA autenticados (handlers AJAX/admin-post e codigo de topo de views): passam a usar `sige_require_escola_id`, que faz `wp_die` 403 quando o id vier <= 0, registando `tenant_write_blocked`.
- Funcoes de biblioteca chamaveis por cron/CLI: resolvem com `sige_get_escola_id()` e abortam a escrita (return tipado conforme o contrato) quando o id vier <= 0, sem `wp_die` (para nao matar o processo de cron).

Em mono-escola ou ambiente relaxado, `sige_get_escola_id()` devolve a escola unica (>= 1), pelo que o comportamento nao muda. So fecha o acesso quando o isolamento estrito esta activo e o contexto nao resolve.

## fallback
O padrao de fallback nos call-sites era `function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1`. Como o resolvedor esta sempre carregado, o ramo `: 1` era codigo morto; o risco real estava no proprio resolvedor, que devolve `SIGE_ESCOLA_MALISA` (1) como ultimo recurso em modo relaxado, e ja devolve 0 em modo estrito (`sige_multitenancy_strict_enabled()`).

Reducao alcancada neste incremento (escritas): o baseline de fallbacks desce de 178 (v12.12.7.1) para 139 (v12.12.8), menos 39 pontos. Foram endurecidos 39 dos 41 call-sites de escrita:
- 25 em contexto de request migrados para `sige_require_escola_id` (fail-closed 403).
- 14 funcoes de biblioteca passaram a abortar a escrita quando o id vier <= 0.

Dois pontos ficam intencionalmente registados (nao silenciosos, ja protegidos):
- `admin/system/permissions-ui.php` (gestao de permissoes): ja fecha o acesso para nao super-admins sem escola; o fallback para 1 e exclusivo de super-admin, por design.
- `includes/permissions-layer.php` em `sige_permissions_sync_user_role`: o fallback relaxado para 1 esta condicionado por `sige_multitenancy_strict_enabled()`, que ja devolve false (bloqueia) em modo estrito.

Os 137 call-sites de LEITURA permanecem no baseline e serao tratados num incremento posterior. A regra de controlo passa a ser monotonica: o baseline de fallbacks so pode descer, nunca subir (gate `check-tenant-fallbacks.php`).

## Fase 3
Tenant Isolation Hardening global (Fase 3) avanca por incrementos. Este e o primeiro: escritas. Os proximos incrementos cobrem (a) as leituras remanescentes e (b) a remocao do fallback final do resolvedor para contextos publicos/cron, com tratamento proprio para esses contextos. O ownership por objecto critico (documentos financeiros, acta, downloads seguros) ja foi fechado em v12.12.7.
