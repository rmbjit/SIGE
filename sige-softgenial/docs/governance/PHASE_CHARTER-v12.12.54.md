# Carta da fase - v12.12.54 - Fase 4 incr 2 - Cadeias jQuery e IIFE em onclick reescritas em vanilla

Continuacao do incremento 2 da Fase 4 (CSP e front-end seguro): reescrita das ULTIMAS cadeias jQuery e IIFE em onclick para vanilla, em funcoes nomeadas. Conclui a migracao dos onclick em ficheiros limpos. Sequencia as vagas anteriores (v12.12.46 a v12.12.53).

## Objectivo

Remover as ultimas cadeias jQuery e IIFE embutidas em onclick nas vistas limpas, reescrevendo-as em vanilla em funcoes nomeadas ligadas pelo despachante, sem alterar a logica nem o comportamento, sob a catraca.

## Incluido

- Duas funcoes nomeadas globais, definidas na propria vista (co-localizadas com o uso, em vez de poluir o kit global com selectores especificos), expostas em window para o despachante a encontrar via window[accao]:
  - sigeReabrirPainelPendentes (central de WhatsApp): fecha e reabre o painel de pendentes (elemento details) apos um instante, como a funcao imediatamente invocada original.
  - sigeFecharModalClone (matriz curricular): esconde o modal de clonagem, marca aria-hidden e, se nenhum modal estiver visivel, remove a classe do corpo, reproduzindo o teste de visibilidade do jQuery por offsetWidth, offsetHeight e getClientRects.
- Conversao dos dois onclick correspondentes para data-sige-act mais data-sige-noargs (o primeiro gerado em template JS).
- Aperto da catraca check-inline-frontend: maximo de onclick de 164 para 162.

## Excluido

- Onclick em ficheiros com janela autonoma, em popups ou em ficheiros canonicos: o despachante nao se aplica; ficam para abordagem propria.
- Os blocos script inline (81) e o CSP em enforcement: incrementos seguintes.

## Riscos

- O teste de visibilidade vanilla divergir do :visible do jQuery. Mitigacao: reproduz-se a regra do jQuery (offsetWidth maior que zero, ou offsetHeight maior que zero, ou getClientRects com comprimento maior que zero).
- A reabertura do painel de pendentes nao repetir a tentativa. Mitigacao: a funcao replica a sequencia exacta (remover open, e voltar a por open apos um instante).
- A funcao nao ser encontrada pelo despachante. Mitigacao: e exposta em window e o despachante procura via window[accao]; corre no carregamento da vista, antes de qualquer clique.
- Partir as vagas anteriores. Mitigacao: aditivo; todas as regras anteriores do despachante ficam inalteradas.

## Criterios de aceitacao

- As duas funcoes vanilla estao expostas em window e o despachante encontra-as; node -c limpo (funcoes extraidas).
- O painel de pendentes reabre e o modal de clonagem fecha exactamente como antes; php -l limpo nas duas vistas.
- A catraca trava onclick em 162 (sem folga); nao resta nenhum onclick jQuery ou IIFE em ficheiros limpos; style 2051 e script 81 inalterados.
- Superficie 199, vistas 60, opcoes 132, deps 9. Versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 99 e release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
