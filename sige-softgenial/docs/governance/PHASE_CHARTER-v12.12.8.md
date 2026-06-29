# PHASE CHARTER - v12.12.8

Incremento: Tenant Isolation Hardening (Fase 3), primeiro lote: caminhos de ESCRITA.
Versao base: 12.12.7.1 (ultimo estado validado).

## Objectivo
Reduzir definitivamente a superficie de "default para a escola 1" nos caminhos de escrita, fazendo-os falhar fechado quando nao ha escola valida em modo multi-escola estrito, sem regressao e sem quebrar contextos publicos ou cron, e sem tocar em regras de calculo financeiro ou academico aprovadas.

## Incluido
- Resolvedor canonico fail-closed `sige_require_escola_id(string $contexto): int` em `includes/multitenancy.php` (devolve a escola resolvida; bloqueia com 403 e regista `tenant_write_blocked` quando o id vier <= 0).
- Migracao de 39 dos 41 call-sites de escrita:
  - 25 em contexto de request (handlers AJAX/admin-post e topo de views) para `sige_require_escola_id`.
  - 14 funcoes de biblioteca passam a abortar a escrita (return tipado) quando o id vier <= 0, sem `wp_die`.
- Instrumentacao: auditoria `tenant_write_blocked` no bloqueio de escrita; `tenant_context_missing` ja existente na leitura estrita.
- Regra de controlo monotonica: o baseline de fallbacks so pode descer (gate `check-tenant-fallbacks.php` reforcado).

## Excluido
- Os 137 call-sites de LEITURA (incremento posterior).
- Remocao do fallback final do resolvedor (`SIGE_ESCOLA_MALISA`) para contextos publicos/cron, que exige tratamento proprio desses contextos (incremento seguinte).
- Dois pontos de escrita ja protegidos, mantidos por design: super-admin em `permissions-ui.php` e fallback relaxado gated em `permissions-layer.php`.
- Qualquer alteracao a regras de calculo financeiro ou academico.

## Riscos
- Quebrar uma escrita legitima caso o retorno de aborto de uma funcao de biblioteca nao respeite o contrato. Mitigacao: aborto tipado por funcao (void, bool, int, array, string) verificado por lint e revisao.
- Falso bloqueio em contexto sem utilizador mas com escola resolvida por subdominio/subdiretorio. Mitigacao: o resolvedor so devolve 0 em modo estrito sem qualquer sinal; em mono-escola devolve sempre 1.
- Em cron, `wp_die` mataria o processo. Mitigacao: funcoes de biblioteca nunca usam `sige_require_escola_id`; abortam por return.

## Criterios de aceitacao
- Lint integral 0 falhas.
- Baseline de fallbacks desce de 178 para 139 (menos 39).
- Resolvedor testado: devolve id quando resolvido; bloqueia e audita quando 0.
- Os 40 gates verdes (39 herdados mais o smoke novo `smoke-tenant-hardening-v12-12-8.php`).
- Rediagnostico adversarial com Zero P0/P1.
- Comportamento mono-escola inalterado; sem alteracoes a calculo financeiro ou academico.
