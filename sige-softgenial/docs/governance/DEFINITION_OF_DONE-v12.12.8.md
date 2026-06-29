# DEFINITION OF DONE - v12.12.8

- DoD-001: Resolvedor `sige_require_escola_id(string $contexto): int` existe em `includes/multitenancy.php`.
- DoD-002: O resolvedor devolve o id quando a escola resolve (> 0).
- DoD-003: O resolvedor faz `wp_die` 403 quando o id vier <= 0 (contexto web).
- DoD-004: O bloqueio de escrita regista auditoria `tenant_write_blocked`.
- DoD-005: Os 25 call-sites de escrita em contexto de request usam `sige_require_escola_id`.
- DoD-006: As 14 funcoes de biblioteca abortam a escrita (return tipado) quando o id vier <= 0, sem `wp_die`.
- DoD-007: Cada aborto de biblioteca respeita o contrato de retorno (void, bool, int, array, string).
- DoD-008: Nenhuma regra de calculo financeiro ou academico foi alterada.
- DoD-009: Em mono-escola/relaxado o comportamento nao muda (resolvedor devolve a escola unica).
- DoD-010: Os dois pontos mantidos por design (super-admin em permissions-ui; relaxado gated em permissions-layer) estao documentados.
- DoD-011: Baseline de fallbacks desce de 178 para 139 (menos 39).
- DoD-012: O baseline congelado da v12.12.7.1 permanece a 178 (historico intacto).
- DoD-013: Versao sincronizada (header, SIGE_VERSION, BUILD.json, SIGE_GOV_VERSION) em 12.12.8.
- DoD-014: Baselines e regras do Kernel da v12.12.8 gerados; ids do Kernel identicos a v12.12.7.1 (193).
- DoD-015: 12 documentos versionados da v12.12.8 presentes com os marcadores exigidos.
- DoD-016: Smoke dedicado `smoke-tenant-hardening-v12-12-8.php` verde e ligado ao corredor de gates.
- DoD-017: PHP lint verde e `php tools/run-gates.php` verde (40 gates).
- DoD-018: Rediagnostico adversarial com Zero P0/P1.

## Zero P0/P1
A versao so pode ser aceite se P0=0 e P1=0 apos rediagnostico adversarial.
