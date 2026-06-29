# RISK REGISTER - v12.12.8

Classificacao: P0 (critico, bloqueia entrega), P1 (alto), P2 (medio), P3 (baixo).

## P0
Nenhum risco P0 aberto. Mitigado por construcao: o resolvedor so devolve 0 (e bloqueia) em modo multi-escola estrito sem qualquer sinal de escola; em mono-escola/relaxado devolve sempre a escola unica, pelo que as instalacoes actuais (uma escola por site) nao sao afectadas.

## P1
Nenhum risco P1 aberto.
- Risco potencial: aborto de biblioteca com retorno incompativel com o contrato da funcao. Estado: mitigado. Cada aborto foi tipado por funcao (void: `return;`; bool: `return false;`; int: `return 0;`; array: `return [];` ou `['ok' => false, ...]`; string: `return '';`) e validado por lint.
- Risco potencial: `wp_die` em cron mataria o processo. Estado: mitigado. Funcoes chamaveis por cron nunca usam `sige_require_escola_id`; abortam por return.

## P2
- Cobertura parcial: apenas as escritas foram endurecidas neste incremento; 137 call-sites de leitura permanecem no baseline. Estado: aceite e planeado para incremento seguinte. Risco residual baixo porque leituras com escola 0 tendem a devolver conjuntos vazios, nao a contaminar dados.
- Fallback final do resolvedor (escola 1) permanece para contextos publicos/cron em modo relaxado. Estado: aceite; remocao planeada com tratamento proprio desses contextos.

## P3
- Dois pontos de escrita mantidos por design (super-admin em permissions-ui; relaxado gated em permissions-layer) continuam a permitir escola 1 em condicoes especificas e ja protegidas. Estado: documentado; sem accao neste incremento.
- Mensagens de bloqueio ao utilizador final podem ser melhoradas com orientacao especifica por modulo. Estado: cosmetico; futuro.
