# RISK REGISTER - v12.12.9

Severidade: P0 (critico, bloqueia), P1 (alto, bloqueia fecho), P2 (medio, entrega com plano), P3 (baixo, backlog).

## P0 (critico)

Nenhum. O incremento nao introduz risco critico. A resolucao de tenant passa a ser determinista ou fail-closed; nunca adivinha escola 1.

## P1 (alto)

Nenhum em aberto. Risco potencial avaliado e mitigado:

- Mudanca no resolvedor afecta toda a leitura/escrita em modo relaxado. MITIGADO: a resolucao por escola unica e determinista e correcta para mono-escola; mono-escola com escola id=1 nao tem qualquer alteracao (verificado); smokes congeladas v12.12.8/v12.12.8.1 a passar; rollback para a v12.12.8.1.

## P2 (medio)

Nenhum em aberto.

- ACHADO E RESOLVIDO neste incremento: classe de integridade de linha `escola_id ?? 1` (9 ocorrencias) que lia escola a partir de linhas de base de dados com default cego para 1 (incluindo `max(1, ...)` que forcava ate escola 0 para 1). Surgiu no rediagnostico adversarial. Decisao: NAO diferir; corrigido para fail-closed `?? 0`, com os sumidouros de escrita (v12.12.8.1) a bloquear escola 0 a jusante. Coberto pelo novo gate.

## P3 (baixo)

- `centro_id ?? 1` (ex.: `includes/fin-action-service.php:507`): default cego para o centro de custo 1. NAO e tenant (escola); fora do ambito de isolamento de escola. Mantido. Candidato a um passe futuro de integridade de sub-entidades (centro_id).
- Guards `function_exists('sige_get_escola_id')` nos call-sites: tecnicamente redundantes (o resolvedor esta sempre carregado), mas mantidos por defesa nos 4 ficheiros carregados antes do resolvedor (core-helpers, curriculum-engine, permissions-layer, whatsapp-destinatarios-policy). Sem risco; o fallback do guard e agora `: 0` (fail-closed).
- Mudanca de comportamento (nao e risco, e correccao): site mono-escola cuja escola tenha id diferente de 1 passa a resolver correctamente (antes resolvia para 1, vazio/errado). Documentado em DEPLOY.
