# TENANT ISOLATION REGISTER - v12.12.1

## escola_id

Fallbacks candidatos registados: 194.

## fallback

A v12.12.1 nao elimina todos os fallbacks globais; ela corrige imediatamente o P0 comprovado em despesas e mantém o restante registado para Fase 3.

## Fase 3

A Fase 3 deve substituir fallback `escola_id = 1` por comportamento fail-closed em producao multi-escola.

## P0 fechado

`includes/finance-core.php` agora usa `escola_id = %d` no comprovativo e relatorio de despesas.
