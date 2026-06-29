# DEFINITION OF DONE - v12.12.9

Criterios verificaveis (DoD-001 a DoD-018). O incremento so se considera concluido com TODOS verdes e Zero P0/P1.

- DoD-001: Helper `sige_multitenancy_single_active_school_id()` existe e devolve o id real sse ha exactamente uma escola activa; senao 0. Testado para 0/1/varias.
- DoD-002: Resolvedor `sige_get_escola_id()` passo 5 nao devolve a constante `SIGE_ESCOLA_MALISA`; resolve por escola unica activa ou 0 (auditado).
- DoD-003: Constante `SIGE_ESCOLA_MALISA` marcada como obsoleta; so referenciada na definicao e na documentacao (zero `return SIGE_ESCOLA_MALISA`).
- DoD-004: Zero ternarios `function_exists('sige_get_escola_id') ? [(int)] sige_get_escola_id() : 1` (com ou sem cast) em codigo de produccao.
- DoD-005: Zero fallbacks fixos `$escola_id = 1;`, `$escola_id_contexto = 1;`, `$eid = 1;` em codigo de produccao.
- DoD-006: Zero defaults de linha `escola_id ?? 1` (incluindo `max(1, ...)`) em codigo de produccao; substituidos por fail-closed `?? 0`.
- DoD-007: Baseline de fallbacks de tenant (`TENANT_FALLBACK_BASELINE-v12.12.9.json`) = 0.
- DoD-008: Novo gate `tools/check-tenant-read-resolver.php` existe, esta ligado ao corredor e impede a reintroducao das quatro classes (testado por injeccao).
- DoD-009: Callbacks publicos M-Pesa/e-Mola confirmados a resolver a escola pelo payload/token, com o resolvedor apenas como secundario fail-closed.
- DoD-010: Cron de email confirmado a usar escola por linha (coluna), sem dependencia do fallback do resolvedor.
- DoD-011: Funcoes de calculo (`sige_fin_saldo_lancamento`, `sige_fin_saldo_sql`, `sige_fin_total_bruto_sql`) byte-identicas a v12.12.8.1.
- DoD-012: Smokes congeladas v12.12.8 e v12.12.8.1 a passar; guards de escrita (v12.12.8.1) intactos.
- DoD-013: Lint integral 0 falhas.
- DoD-014: Zero em-dash/en-dash em codigo (.php/.js/.css).
- DoD-015: Versao sincronizada nas 4 fontes (cabecalho, SIGE_VERSION, BUILD.json, SIGE_GOV_VERSION).
- DoD-016: 12 documentos de governanca v12.12.9 presentes com os marcadores exigidos; 7 JSON de governanca presentes.
- DoD-017: Smoke `tools/smoke-tenant-read-resolver-v12-12-9.php` verde e ligado ao corredor.
- DoD-018: `php tools/run-gates.php` totalmente verde.

Conclusao: Zero P0/P1 (ver ADVERSARIAL_REVIEW-v12.12.9.md). Com P0 ou P1 em aberto, a versao NAO esta concluida.
