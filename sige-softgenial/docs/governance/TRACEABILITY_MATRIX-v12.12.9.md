# TRACEABILITY MATRIX - v12.12.9

Rastreio de requisitos (SK-001 a SK-018) para evidencia de implementacao e verificacao.

| ID | Requisito | Evidencia |
|----|-----------|-----------|
| SK-001 | Helper de escola unica activa | `includes/multitenancy.php` `sige_multitenancy_single_active_school_id()`; teste 0/1/varias |
| SK-002 | Resolvedor sem constante cega | `includes/multitenancy.php` passo 5; `grep` 0 `return SIGE_ESCOLA_MALISA` |
| SK-003 | Constante obsoleta | `includes/multitenancy.php:22` comentario OBSOLETO |
| SK-004 | Ternarios `: 1` limpos | 132 substituicoes `: 1` -> `: 0`; baseline 0 |
| SK-005 | Fallbacks fixos limpos | 7 substituicoes para escola unica activa |
| SK-006 | Defaults de linha `?? 1` | 9 substituicoes `?? 1` -> `?? 0` (fail-closed) |
| SK-007 | Baseline de fallbacks = 0 | `docs/security/TENANT_FALLBACK_BASELINE-v12.12.9.json` |
| SK-008 | Gate de leitura/resolvedor | `tools/check-tenant-read-resolver.php`; teste de injeccao das 4 classes |
| SK-009 | Gate ligado ao corredor | `tools/run-gates.php` entrada 'Governacao (leitura/resolvedor tenant)' |
| SK-010 | Callbacks publicos por payload | `includes/payments/mpesa-webhook.php`, `emola-webhook.php` (token + escola_id do payload) |
| SK-011 | Cron de email por linha | `includes/email-queue-templates.php` coluna `escola_id` |
| SK-012 | Calculo intacto | md5 identico vs ZIP v12.12.8.1 (3 funcoes) |
| SK-013 | Smokes congeladas | `smoke-tenant-write-isolation-v12-12-8-1.php` 23 checks; write-sinks OK |
| SK-014 | Lint 0 | `lint` integral 353/0 |
| SK-015 | Sem em-dash em codigo | varrimento python3 (.php/.js/.css) = 0 |
| SK-016 | Versao sincronizada | cabecalho + SIGE_VERSION + BUILD.json + SIGE_GOV_VERSION = 12.12.9 |
| SK-017 | Docs de governanca | 12 markdown + 7 JSON v12.12.9 |
| SK-018 | Corredor verde | `php tools/run-gates.php` |

Evidencia consolidada de execucao real em `docs/qa/QA-v12.12.9.md` e `docs/governance/ADVERSARIAL_REVIEW-v12.12.9.md`.
