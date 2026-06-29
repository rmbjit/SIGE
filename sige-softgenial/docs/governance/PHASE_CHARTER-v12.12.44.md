# Carta da fase - v12.12.44 - Fase 3 - Ajuste fino dos codigos e estados por operadora

Ajuste fino final da Fase 3 (reconciliacao de pagamentos digitais). Os afinamentos anteriores ja tinham tornado a verificacao sensivel ao estado da transacao, mas com listas globais. Esta versao torna a classificacao POR OPERADORA, para que o M-Pesa (Vodacom) e o e-Mola (Movitel) possam ser afinados de forma independente.

## Objectivo

Permitir afinar os codigos e estados de falha de forma independente para cada operadora, com defaults fundamentados na documentacao publica e sobreposicao por filtro com isolamento, mantendo o conservadorismo e a retro-compatibilidade.

## Incluido

- Nova funcao sige_pagamentos_mapa_estados_gateway(provider): devolve, para cada provider, as listas de estados de sucesso e de falha e os codigos de resposta de falha.
- O normalizador sige_pagamentos_normalizar_estado_gateway passa a receber o provider e a usar esse mapa; o adaptador sige_pagamentos_consultar_gateway passa o provider (mpesa ou emola) ao normalizador.
- Defaults fundamentados: o M-Pesa reporta o estado da transacao em ResponseTransactionStatus (ex.: Completed) e um INS-0 confirma apenas a consulta, pelo que os codigos de resposta de falha ficam vazios por omissao (a falha e expressa pelo estado); o e-Mola mantem-se conservador com termos genericos enquanto a API de consulta da Movitel nao esta documentada.
- As tres listas sao sobreponiveis por filtro COM o provider como contexto: sige_mpesa_estados_sucesso, sige_mpesa_estados_falha e sige_mpesa_codigos_falha recebem agora um segundo argumento, o provider. Isto permite afinar por operadora num unico filtro e garante isolamento.

## Excluido

- Os valores exactos definitivos de cada operadora: dependem do sandbox; a estrutura por operadora ja esta pronta para os receber, mas os defaults sao conservadores ate a confirmacao.
- Persistencia das listas em opcao de base de dados: a afinacao continua por filtro, para nao introduzir opcoes novas.
- Qualquer alteracao a regras de calculo, a ficheiros canonicos ou ao esquema.

## Riscos

- Contaminar uma operadora com a configuracao de outra. Mitigacao: o mapa e por provider e os filtros recebem o provider; um codigo ou estado de falha de uma operadora nao afecta a outra (isolamento testado).
- Acusar uma transacao real de falsa. Mitigacao: conservadorismo preservado; estado nao mapeado fica desconhecida, e a falha so e afirmada por estado ou codigo conhecido da propria operadora.
- Quebrar o comportamento anterior. Mitigacao: retro-compativel; sem provider, usa os defaults comuns, e filtros antigos de um argumento continuam a funcionar.

## Criterios de aceitacao

- O mapa por operadora separa os codigos e estados de falha (M-Pesa difere do e-Mola).
- Um codigo ou estado de falha definido para uma operadora e falhada nessa operadora e desconhecida na outra.
- O normalizador continua conservador (estado nao mapeado -> desconhecida) e retro-compativel (sem provider, defaults comuns).
- Superficie de accao inalterada (199, enforce 33), sem nova vista (60), sem opcoes novas (132), dependencias externas inalteradas (11), versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 95/95 e release gate verde a partir de pasta limpa. Os gates anteriores continuam verdes. Rediagnostico adversarial Zero P0/P1.
