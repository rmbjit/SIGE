# TENANT ISOLATION REGISTER - v12.12.8.1

## escola_id
A v12.12.8.1 corrige o P1 do rediagnostico da v12.12.8: a enumeracao de escritas era estreita (so o padrao ": 1" directo). Este incremento desloca o endurecimento para a camada de SUMIDOURO, onde a escrita acontece, de modo que nenhuma escrita possa visar escola_id <= 0, independentemente de como o chamador resolveu a escola.

Mecanismos:
- Helper auditado `sige_tenant_write_guard(int $escola_id, string $contexto): bool` (multitenancy.php): devolve true se escola_id > 0; caso contrario regista `tenant_write_blocked` e devolve false para o chamador abortar com o retorno tipado adequado. Sem wp_die, seguro para cron.
- `sige_require_escola_id` (v12.12.8) continua para handlers de request (wp_die 403).

Em mono-escola/relaxado o resolvedor devolve a escola unica (>= 1), pelo que os guards nunca disparam. So fecham em modo multi-escola estrito sem contexto.

## fallback
Reclassificacao completa da superficie de escrita por tenant (analise de fluxo, substituindo "escrita = INSERT no corpo"):

- Categoria A (sumidouros: funcao que escreve e recebe escola_id/eid por parametro): 24 sem guard -> 24 endurecidas com `sige_tenant_write_guard` e aborto tipado por contrato (array ok/error, array ok/message, bool, void, null, WP_Error).
- Categoria B (escrita inline `'escola_id' => sige_get_escola_id()`): 24 funcoes -> 22 endurecidas (handlers AJAX com wp_send_json_error; biblioteca com retorno tipado). 2 ficam por design como EXCECAO de logging resiliente: `sige_audit_log` e `sige_registar_log` (devem registar o evento mesmo sem escola, ficando a anomalia auditada com escola 0 em vez de se perder).
- Categoria C (call-sites ": 1"): o handler delegado `acta-pdf-handler.php:1610` (aprovacao de nota votada), perdido na v12.12.8, foi fechado (resolvedor fail-closed na origem mais guard no sumidouro). Baseline ": 1" desce de 139 para 138. Os 137 restantes da Categoria C sao leitura, diferidos.

Resultado: 0 sumidouros de Categoria A sem guard; 0 funcoes de Categoria B sem guard fora das 2 excecoes de logging. A lacuna de auditoria silenciosa da v12.12.8 (abortos de biblioteca sem registo) fica fechada: todo bloqueio passa por `sige_tenant_write_guard` ou `sige_require_escola_id`, ambos auditados.

Novo gate `check-tenant-write-sinks.php` governa esta superficie real e impede regressao: falha se surgir um sumidouro com parametro de escola sem guard, ou uma escrita inline sem guard (fora da allowlist de logging).

## Fase 3
Tenant Isolation Hardening (Fase 3) avanca por incrementos. v12.12.8 cobriu os call-sites ": 1" directos de escrita. v12.12.8.1 fecha a superficie real de escrita ao nivel do sumidouro (Categorias A e B) e o handler delegado em falta. Diferido para incrementos seguintes: os 137 call-sites de LEITURA e a remocao do fallback final `SIGE_ESCOLA_MALISA` do resolvedor para contextos publicos/cron. Pontos mantidos por design (gated, documentados): `permissions-ui.php:70` (super-admin) e `permissions-layer.php:1014` (relaxado gated).
