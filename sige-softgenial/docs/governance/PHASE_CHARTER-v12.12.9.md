# PHASE CHARTER - v12.12.9 (Tenant Read Isolation & Resolver Hardening)

Fase 3 (Isolamento de Tenant) - incremento final.

## Objectivo

Remover o fallback CEGO para a escola 1 em toda a resolucao de contexto de tenant, substituindo-o por resolucao determinista da escola unica activa, com fail-closed (0) quando o contexto e ambiguo. O resolvedor deixa de assumir que a escola unica tem id 1: passa a usar o id REAL da unica escola activa quando existe exactamente uma, e devolve 0 (auditado) quando ha zero ou varias escolas e nenhum contexto explicito. Os call-sites de leitura deixam de conter o ramo morto `: 1` e passam a fail-closed (`: 0`), tornando o resolvedor a unica fonte de verdade.

## Incluido

- Novo helper `sige_multitenancy_single_active_school_id(): int` (devolve o id sse existir exactamente uma escola activa; senao 0).
- Resolvedor `sige_get_escola_id()`: passo 5 final passa de `return SIGE_ESCOLA_MALISA` (constante = 1, cego) para resolucao por escola unica activa, senao 0 com auditoria `tenant_context_missing`.
- Constante `SIGE_ESCOLA_MALISA` marcada como OBSOLETA (mantida definida apenas por retrocompatibilidade, sem uso como fallback).
- Limpeza dos call-sites de leitura: 132 ternarios `function_exists('sige_get_escola_id') ? [(int)] sige_get_escola_id() : 1` passam a `: 0` (com ou sem cast); 7 fallbacks fixos (`$escola_id = 1;`, `$escola_id_contexto = 1;`, `$eid = 1;`) passam a resolucao por escola unica activa.
- Classe de integridade de linha: 9 defaults cegos `escola_id ?? 1` (leitura de escola a partir de linhas de base de dados, incluindo `max(1, ...)`) passam a `?? 0` (fail-closed), eliminando o risco de processar filas/estornos sob a escola errada.
- Auditoria de contextos publicos e cron (parte explicita do pedido): confirmacao de que os callbacks M-Pesa/e-Mola resolvem a escola pelo payload e por tokens scoped, com o resolvedor apenas como secundario fail-closed; cron de email usa escola por linha.
- Novo gate `tools/check-tenant-read-resolver.php` que impede a reintroducao das quatro classes de id=1 cego (ternario, fixo, return da constante, default de linha).
- Baseline de fallbacks de tenant desce para 0.

## Excluido

- UX de "sem escola" por leitura (o resolvedor a devolver 0 ja impede leitura cross-tenant em modo estrito; o polimento visual e separado).
- Remocao das vias de override explicito `SIGE_CURRENT_ESCOLA` e `SIGE_ALLOW_ESCOLA_FALLBACK` (sao overrides legitimos e continuam suportados).
- Eliminacao fisica da constante `SIGE_ESCOLA_MALISA` (mantida definida, obsoleta, por retrocompatibilidade).
- Default de `centro_id ?? 1` (centro de custo, nao e tenant: fora do ambito de isolamento de escola).

## Riscos

- A mudanca no resolvedor afecta toda a leitura e escrita em modo relaxado. Mitigacao: a resolucao por escola unica e determinista e correcta para mono-escola; num site mono-escola cuja escola tenha id=1 NAO ha qualquer alteracao; gate e smoke dedicados; rollback claro para a v12.12.8.1.
- Site mono-escola cuja escola tenha id diferente de 1: o comportamento muda de errado/vazio para correcto. E correccao, mas e mudanca de comportamento (documentada em DEPLOY).
- Superficie grande de limpeza (75 ficheiros de produto). Mitigacao: substituicao por padrao + lint integral + os 6 baselines + smokes congeladas v12.12.8/v12.12.8.1 a passar.

## Criterios de aceitacao

- Resolvedor sem fallback por constante cega; helper de escola unica testado (0/1/varias).
- Zero formas de escola=1 cego em codigo de produccao (ternario, fixo, return da constante, default de linha). Baseline de fallbacks = 0.
- Callbacks publicos confirmados a resolver por payload; cron sem dependencia do fallback cego.
- Novo gate verde e a impedir regressao das quatro classes; todos os gates verdes; lint 0.
- Funcoes de calculo byte-identicas; smokes congeladas v12.12.8/v12.12.8.1 intactas.
- Rediagnostico adversarial Zero P0/P1.
