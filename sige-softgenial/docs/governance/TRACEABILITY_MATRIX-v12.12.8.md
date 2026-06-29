# TRACEABILITY MATRIX - v12.12.8

Mapeia cada requisito de seguranca (SK) ao criterio (DoD) e a Evidencia de verificacao.

- SK-001 (resolvedor existe) -> DoD-001 -> Evidencia: lint de `multitenancy.php` e teste do resolvedor (`/tmp/test_resolver.php`: devolve 7 quando 7).
- SK-002 (resolvedor devolve id valido) -> DoD-002 -> Evidencia: teste do resolvedor caso escola 7.
- SK-003 (fail-closed 403) -> DoD-003 -> Evidencia: teste do resolvedor caso escola 0 (wp_die).
- SK-004 (auditoria de bloqueio) -> DoD-004 -> Evidencia: teste regista `tenant_write_blocked`.
- SK-005 (25 request migrados) -> DoD-005 -> Evidencia: smoke verifica ausencia de `: 1` e presenca de `sige_require_escola_id` nesses ficheiros.
- SK-006 (14 biblioteca abortam) -> DoD-006/007 -> Evidencia: smoke verifica os guards `if (... <= 0)` e o retorno tipado por funcao.
- SK-007 (sem alterar calculo) -> DoD-008 -> Evidencia: diff restrito a resolucao de escola; nenhuma funcao de saldo/nota tocada.
- SK-008 (mono-escola inalterado) -> DoD-009 -> Evidencia: resolvedor devolve 1 quando `sige_get_escola_id` devolve 1.
- SK-009 (pontos por design documentados) -> DoD-010 -> Evidencia: TENANT_ISOLATION_REGISTER lista permissions-ui e permissions-layer.
- SK-010 (baseline desce) -> DoD-011 -> Evidencia: `TENANT_FALLBACK_BASELINE-v12.12.8.json` = 139 vs 178.
- SK-011 (historico intacto) -> DoD-012 -> Evidencia: `TENANT_FALLBACK_BASELINE-v12.12.7.1.json` = 178.
- SK-012 (versao sincronizada) -> DoD-013 -> Evidencia: smoke-release-gate (header == SIGE_VERSION == BUILD.json).
- SK-013 (Kernel alinhado) -> DoD-014 -> Evidencia: check-security-kernel-rules (PHP ids == JSON ids == manifesto).
- SK-014 (documentos presentes) -> DoD-015 -> Evidencia: check-governance-docs verde.
- SK-015 (smoke ligado) -> DoD-016 -> Evidencia: `run-gates.php` inclui o smoke novo.
- SK-016 (lint e gates) -> DoD-017 -> Evidencia: lint 349/0 e run-gates 40/40.
- SK-017 (controlo monotonico) -> DoD-011 -> Evidencia: check-tenant-fallbacks bloqueia subida do baseline.
- SK-018 (adversarial Zero P0/P1) -> DoD-018 -> Evidencia: ADVERSARIAL_REVIEW-v12.12.8.md.
