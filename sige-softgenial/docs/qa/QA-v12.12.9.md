# QA - v12.12.9 (Tenant Read Isolation & Resolver Hardening)

Evidencia de QA por execucao real (nao teorica).

## Resolvedor e helper

- Teste do helper `sige_multitenancy_single_active_school_id()` (logica do passo 5): 1 escola id=5 -> 5; 1 escola id=1 -> 1; 0 escolas -> 0; varias escolas -> 0. PASSOU.
- `grep` ao codigo: zero `return SIGE_ESCOLA_MALISA`; o resolvedor usa `sige_multitenancy_single_active_school_id()`.

## Eliminacao de fallback cego (execucao)

- Ternarios `: 1` (com cast): 120 -> `: 0`. Remanescentes: 0.
- Ternarios `: 1` (sem cast): 12 -> `: 0`. Remanescentes: 0.
- Fallbacks fixos `= 1` (`$escola_id`, `$escola_id_contexto`, `$eid`): 7 -> escola unica activa. Remanescentes: 0.
- Defaults de linha `escola_id ?? 1` (inc. `max(1, ...)`): 9 -> `?? 0` (fail-closed). Remanescentes: 0.
- Baseline de fallbacks de tenant: 138 -> 0.
- Varrimento final por TODAS as formas de escola=1 cego em codigo de produccao: 0 (os unicos matches sao as strings de regex dentro do proprio gate).

## Lint

- Lint integral: TOTAL PHP 353 | FALHAS 0.

## Integridade de calculo

- md5 de `sige_fin_saldo_lancamento`, `sige_fin_saldo_sql`, `sige_fin_total_bruto_sql`: byte-identico ao ZIP v12.12.8.1 entregue. Nenhuma regra de calculo tocada.

## Gates e smokes

- Novo gate `check-tenant-read-resolver.php`: verde no estado limpo; falha (exit 1) quando injectadas as 4 classes de id=1 cego; volta a verde apos remocao.
- Nova smoke `smoke-tenant-read-resolver-v12-12-9.php`: 16 verificacoes OK.
- Smoke congelada `smoke-tenant-write-isolation-v12-12-8-1.php`: 23 verificacoes OK.
- `check-tenant-write-sinks.php`: guards de escrita (v12.12.8.1) intactos.
- `check-security-kernel-rules.php`: 193 regras (enforce=30, delegated=17, observe=146).
- `check-governance-docs.php`: 12 documentos + 7 JSON presentes.
- `php tools/run-gates.php`: totalmente verde.

## Em-dash

- Varrimento python3 em .php/.js/.css: 0 ocorrencias de em-dash/en-dash.

## Contextos publicos e cron

- M-Pesa/e-Mola webhooks: resolvem por token + payload; resolvedor apenas secundario fail-closed.
- Cron de email: escola por linha.

## Conclusao

Zero P0/P1. Versao definitiva: sem fallback cego para escola 1; resolucao determinista por escola unica activa, senao fail-closed.
