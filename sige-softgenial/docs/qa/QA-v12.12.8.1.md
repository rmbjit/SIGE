# QA - v12.12.8.1 (Tenant Write Isolation)

Evidencia de execucao real (nao apenas inspeccao). Ambiente: PHP 8.3.6 CLI.

## Lint
- Lint integral: 352 ficheiros PHP, 0 falhas.
- Lint individual de todos os 14 ficheiros de produto alterados: OK.

## Helper sige_tenant_write_guard (teste comportamental)
- escola 7 -> devolve true (permite).
- escola 0 -> devolve false (bloqueia) e regista tenant_write_blocked.
- escola negativa -> devolve false (bloqueia).
- Resultado: GUARD OK.

## Cobertura da superficie (worklist de fluxo)
- Categoria A (sumidouros com parametro de escola) sem guard: 0.
- Categoria B (escrita inline) sem guard: 2 (apenas as excecoes de logging sige_audit_log e sige_registar_log).
- Total de guards inseridos: 46 (24 A + 22 B).

## Gate check-tenant-write-sinks
- Estado normal: OK (0 sumidouros sem guard fora da allowlist).
- Teste de regressao: com sumidouro sem guard injectado, exit 1 e identificacao do ponto; apos remocao, exit 0. Bloqueia regressao.

## Integridade dos calculos
- Conjunto de funcoes de finance-core identico a v12.12.8.
- Apenas 2 linhas adicionadas (guards de criar_credito_pendente e registar_pagamento_anual).
- md5 identico para sige_fin_saldo_lancamento, sige_fin_saldo_sql, sige_fin_total_bruto_sql.

## Baselines
- TENANT_FALLBACK v12.12.8.1 = 138.
- TENANT_FALLBACK v12.12.8 (congelado) = 139. Ratchet: 138 < 139.
- Security Kernel: 193 regras, ids identicos a v12.12.8.

## Em/en-dash
- Scan python3 em .php/.js/.css: 0 ficheiros de codigo com em/en-dash.

## Smoke dedicado
- smoke-tenant-write-isolation-v12-12-8-1.php: 23 verificacoes OK.

## Corredor completo
- php tools/run-gates.php: 42 de 42 gates verdes.
- check-governance-docs.php: OK (12 docs v12.12.8.1 + baselines).
- smoke-release-gate.php: OK (53 verificacoes, versao sincronizada em 12.12.8.1).

## Ambito
- Diff de arvore contra v12.12.8: 14 ficheiros de produto (todos planeados) + 3 raiz (sige-softgenial.php, BUILD.json, CHANGELOG.md). Nenhum ficheiro fora do escopo.

## Rediagnostico adversarial
- Zero P0/P1. Uma inconsistencia latente (aborto de enc_criar_snapshot_final) corrigida durante o rediagnostico. Detalhe em ADVERSARIAL_REVIEW-v12.12.8.1.md.
