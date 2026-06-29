# ADVERSARIAL REVIEW - v12.12.8

## Rediagnostico adversarial
Revisao adversarial do incremento de Tenant Isolation Hardening (escritas), conduzida apos a implementacao, por reexecucao de classificadores e inspeccao dirigida. Perguntas de ataque e respectivas evidencias:

1. Ficou algum call-site de ESCRITA por migrar?
   Reexecutei o classificador de escrita sobre os 139 fallbacks remanescentes. Apenas 2 estao em funcoes de escrita, e sao exactamente os 2 deixados por design: `admin/system/permissions-ui.php:70` (fallback so de super-admin, ja com fail-closed para os restantes nas linhas 66-69) e `includes/permissions-layer.php:1014` (fallback relaxado condicionado por `sige_multitenancy_strict_enabled()`, que ja devolve false e bloqueia em modo estrito). Nenhum write site escapou.

2. Os abortos de biblioteca respeitam o contrato de retorno?
   Verifiquei o tipo declarado de cada uma das 14 funcoes e o aborto inserido: int -> `return 0;`, void -> `return;`, bool -> `return false;`, array -> `return [];` ou `['ok' => false, ...]`, string -> `return '';`, sem tipo -> `return null;`. Todos compativeis.

3. Alguma funcao de request (que usa wp_die) e alcancavel por cron?
   Nenhuma das 10 funcoes handler/admin migradas esta agendada como cron (0 referencias a wp_schedule/cron). `sige_require_escola_id` so e invocado em ficheiros de request (views admin e handlers AJAX), nunca em codigo chamavel por cron, onde se usou aborto por return.

4. Alguma leitura foi quebrada por engano?
   O baseline desceu exactamente 39 (178 -> 139), igual ao numero de escritas endurecidas. As leituras permanecem no baseline. Lint integral 350/0.

5. Mexeu-se em regras de calculo financeiro ou academico?
   Nao. O diff limita-se a resolucao de escola. As funcoes canonicas `sige_fin_saldo_lancamento` e `sige_fin_saldo_sql` permanecem intactas (verificado no smoke).

6. Introduziram-se em/en-dashes no codigo?
   Zero ficheiros .php com em/en-dash.

7. Risco de funcao indefinida (`sige_require_escola_id`) em tempo de execucao?
   O resolvedor esta em `multitenancy.php`, carregado cedo no bootstrap (antes de finance-core e dos handlers), e protegido por `if (!function_exists(...))`. Os ficheiros que o usam so correm depois do init do plugin.

## P0
Zero P0. Mitigado por construcao: o resolvedor so devolve 0 (e bloqueia) em modo multi-escola estrito sem qualquer sinal de escola. Em mono-escola/relaxado devolve sempre a escola unica, pelo que as instalacoes actuais (uma escola por site) nao sofrem alteracao de comportamento.

## P1
Zero P1. Os riscos potenciais (aborto com tipo incompativel; wp_die em cron) foram fechados e verificados nos pontos 2 e 3 acima.

## Decisao
Aprovado para empacotamento. Criterios de aceitacao satisfeitos: lint 350/0, baseline 178 -> 139 com ratchet monotonico activo, resolvedor testado (permite com escola, bloqueia e audita sem escola), 40 gates verdes, dois pontos residuais documentados como seguros, sem alteracao a regras de calculo. Incremento seguinte: leituras remanescentes e remocao do fallback final do resolvedor para contextos publicos/cron.
