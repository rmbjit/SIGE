# Carta da fase - v12.12.53 - Fase 4 incr 2 - Onclick de navegacao e template JS para funcoes nomeadas

Continuacao do incremento 2 da Fase 4 (CSP e front-end seguro): conversao dos onclick de NAVEGACAO e de TEMPLATE JS para funcoes nomeadas, com data-sige-prevent. Sequencia as vagas anteriores (v12.12.46 a v12.12.52).

## Objectivo

Remover os onclick de navegacao (abrir recibo, mailto) e os gerados em template JS, transferindo-os para funcoes nomeadas globais ligadas pelo despachante, com data-sige-prevent para os links que nao devem navegar, sem alterar a logica nem o comportamento, sob a catraca.

## Incluido

- data-sige-prevent no despachante (assets/sige-ui.js): chama ev.preventDefault() antes de disparar a funcao, substituindo o return false de onclick em links. Aditivo: as regras anteriores ficam inalteradas.
- Duas funcoes nomeadas globais em assets/sige-ui.js: sigeAbrirReciboJanela(el) (abre o href do proprio link numa janela de recibo) e sigeIrPara(url) (navega para um endereco, por exemplo mailto).
- Conversao de sete onclick em quatro vistas limpas: dois links de recibo no portal do aluno (sigeAbrirReciboJanela mais data-sige-prevent, href preservado), um mailto no aviso de cobranca do hub (sigeIrPara com data-sige-arg via esc_attr), dois botoes de adiar que ja chamavam window.sigeHubBillSnooze (data-sige-act mais data-sige-noargs), e dois onclick de template JS (delCriterio com id e elemento, removerDaMatriz com id) reescritos para data-sige-args com a interpolacao JS preservada.
- Aperto da catraca check-inline-frontend: maximo de onclick de 171 para 164; o gate passa tambem a exigir o contrato data-sige-prevent.

## Excluido

- Cadeias jQuery e IIFE (fecho de modal de clonagem, reabertura de painel de pendentes): precisam de reescrita vanilla fiel; ficam para vaga propria.
- Onclick em ficheiros com janela autonoma ou em popups: o despachante nem carrega; de fora.
- Ficheiros canonicos: de fora, por desenho.
- Os blocos script inline (81) e o CSP em enforcement: incrementos seguintes.

## Riscos

- O link de recibo passar a navegar a pagina (perda do return false). Mitigacao: data-sige-prevent chama preventDefault; o href fica preservado para acessibilidade, clique do meio e menu de contexto.
- O endereco mailto quebrar (subject com PHP). Mitigacao: o endereco e construido com esc_attr e passado em data-sige-arg; o assunto usa rawurlencode como no original.
- A interpolacao do template JS perder-se. Mitigacao: o data-sige-args mantem a interpolacao do lado do JS (por exemplo ${c.id}); o data-sige-self anexa o elemento onde a chamada usava this.
- Partir as vagas anteriores. Mitigacao: o data-sige-prevent e aditivo; todas as regras anteriores ficam inalteradas.

## Criterios de aceitacao

- O despachante honra data-sige-prevent (preventDefault antes de disparar); node -c limpo; o gate verifica o contrato.
- As quatro vistas reduziram os onclick com php -l limpo; os recibos abrem em janela sem navegar, o mailto abre o email, os botoes de adiar e os de template JS funcionam.
- A catraca trava onclick em 164 (sem folga); style 2051 e script 81 inalterados.
- Superficie 199, vistas 60, opcoes 132, deps 9. Versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 99 e release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
