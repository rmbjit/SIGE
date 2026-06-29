# ADVERSARIAL REVIEW - v12.12.9

Rediagnostico adversarial executado apos a implementacao, com execucao real. Objectivo: tentar provar que o incremento esta incompleto ou inseguro.

## Resultado

Zero P0. Zero P1. Um achado P2 (classe de integridade de linha) foi encontrado E corrigido durante o proprio rediagnostico (nao foi diferido). Estado final: definitivo, sem fallback cego para escola 1 em codigo de produccao.

## Verificacoes

### (a) O novo gate apanha regressao
Injectadas as quatro classes de id=1 cego (ternario sem cast, fallback fixo, default de linha `?? 1`, e fixo noutra variavel). O gate `check-tenant-read-resolver.php` falhou com exit 1 e listou as ocorrencias. Apos remocao, exit 0. Confirmado.

### (b) Enumeracao completa das formas de escola=1 cego
Primeira tentativa de limpeza apanhou apenas os ternarios COM cast `(int)`. O rediagnostico revelou:
- 12 ternarios SEM cast (`? sige_get_escola_id() : 1`) que o regex inicial nao cobria. Corrigidos.
- 1 fallback fixo noutra variavel (`$escola_id_contexto = 1;`). Corrigido.
- 9 defaults de linha `escola_id ?? 1` (leitura de escola a partir de linhas de BD, incluindo `max(1, ...)`). Esta classe NAO estava no baseline original (o detector contava `: 1` e `= 1;`, nao `?? 1`) e estava fora do ambito declarado. Decisao: dado o principio de "definitivo, nao paliativo", NAO diferir; corrigidos para fail-closed `?? 0`. Os `max(1, ...)` chegavam a forcar escola 0 para 1, o que e exactamente o risco a eliminar (processar fila/estorno sob a escola errada). Os sumidouros de escrita (v12.12.8.1) bloqueiam escola 0 a jusante.
Varrimento final: zero formas de escola=1 cego em codigo de produccao (os unicos matches sao as strings de regex dentro do proprio gate). Baseline de fallbacks = 0.

### (c) O resolvedor nao quebra activacao/seeding
`sige_get_escola_id()` nao e usado em paths de `register_activation_hook`/install/seed. Onde e usado em funcoes de dados (`db-handler.php`, `alertas-core.php`), o fallback e ja `: 0`. Os seeders criam a escola antes; o resolvedor resolve-a como escola unica activa.

### (d) Helper de escola unica
Testado: 1 escola id=5 -> 5; 1 escola id=1 -> 1; 0 escolas -> 0; varias escolas -> 0. Trata tabela ausente (devolve 0). Static-cached.

### (e) Em-dash/en-dash em codigo
Varrimento python3 em .php/.js/.css: 0 ocorrencias.

### (f) Integridade de calculo
`sige_fin_saldo_lancamento`, `sige_fin_saldo_sql`, `sige_fin_total_bruto_sql`: md5 byte-identico ao ZIP v12.12.8.1 entregue. Nenhuma regra de calculo tocada.

### (g) Contextos publicos e cron
M-Pesa e e-Mola webhooks: resolvem por token + `escola_id`/`school_id` do payload primeiro; o resolvedor e apenas secundario e agora fail-closed (escola unica ou 0), abortando se 0. Cron de email: escola por linha (coluna). Hub: opera por site; `hub-commands` usa o resolvedor (agora escola unica para sites mono-escola cliente).

### (h) Diff de arvore
75 ficheiros de produto alterados (limpeza id=1 + resolvedor + integridade de linha), 7 novos (6 baselines auto-gerados + o novo gate). Sem ficheiros temporarios ou de teste deixados.

### (i) Smokes congeladas
`smoke-tenant-write-isolation-v12-12-8-1.php`: 23 verificacoes OK. `check-tenant-write-sinks.php`: guards intactos. Baselines congelados (138/139) nao tocados.

## Decisao

A versao v12.12.9 esta CONCLUIDA: cumpre o objectivo do charter (remocao definitiva do fallback cego para escola 1, resolucao determinista por escola unica activa, fail-closed quando ambiguo), com Zero P0/P1 e QA por execucao real. O achado P2 de integridade de linha foi corrigido no mesmo incremento, nao diferido. Residuais P3 (centro_id, redundancia dos guards) documentados no RISK_REGISTER.
