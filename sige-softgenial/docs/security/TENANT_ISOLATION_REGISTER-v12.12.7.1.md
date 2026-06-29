# TENANT ISOLATION REGISTER - v12.12.7.1

## escola_id
O novo query handler de cobranca resolve `escola_id` por `sige_get_escola_id()` e e fail-closed. A query do dataset filtra `escola_id` em alunos, matriculas, turmas e lancamentos, garantindo que so a escola corrente e lida.

## fallback
Nenhum fallback global novo foi introduzido. Os fallbacks remanescentes para escola 1 continuam registados em baseline e nao devem ser usados em novas acoes.

## Fase 3
Tenant Isolation Hardening global continua para a Fase 3 (v12.12.8), incluindo ownership por objecto em todo o sistema.
