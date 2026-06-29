# Carta da fase - v12.12.52 - Fase 4 incr 2 - Expressoes inline em onclick para funcoes nomeadas

Continuacao do incremento 2 da Fase 4 (CSP e front-end seguro): conversao das EXPRESSOES inline em onclick para funcoes nomeadas globais. O CSP em enforcement bloqueia handlers inline; removelos por vagas e pre-requisito. Sequencia as vagas anteriores (v12.12.46 criou o despachante; v12.12.50 tratou os onclick simples; v12.12.51 tratou os complexos com argumentos).

## Objectivo

Remover as expressoes embutidas em onclick (logica de interface escrita na etiqueta), transferindo-as para funcoes nomeadas globais ligadas pelo despachante data-sige-act, sem alterar a logica nem o comportamento, sob a catraca.

## Incluido

- Sete funcoes nomeadas em assets/sige-ui.js, expostas em window para o despachante as encontrar via window[accao]:
  - sigeImprimirPagina: window.print (data-sige-noargs).
  - sigeAlternarQuebraTexto(el): alterna o whiteSpace do elemento.
  - sigeAlternarClasseProximo(el): alterna a classe do elemento seguinte.
  - sigeFecharPopupBackdrop(el): fecha o aviso ao subir ate ao backdrop.
  - sigeAlternarSidebar (data-sige-noargs), sigeFecharSidebar(el) e sigeAlternarSidebarMais(el): menu lateral movel do shell, com gestao do aria-expanded no ultimo.
- Conversao de 10 onclick-expressao em cinco vistas limpas: abertura (imprimir, alternar grupo de alunos), encerramento (imprimir), pagamentos por turma (imprimir), auditoria de notas (alternar quebra de texto, duas ocorrencias) e o shell admin (menu lateral movel e fecho de aviso, quatro ocorrencias).
- Aperto da catraca check-inline-frontend: maximo de onclick de 181 para 171.

## Excluido

- Expressoes em ficheiros com janela autonoma (que nem carregam o despachante, por exemplo paginas de impressao com history.back ou window.close): de fora.
- Onclick com navegacao (window.open(this.href), window.location): envolvem a accao por omissao do link; ficam para vaga propria.
- Cadeias jQuery e IIFE, e onclick gerados em template JS: ficam para vagas proprias.
- Ficheiros canonicos: de fora, por desenho.
- Os blocos script inline (81) e o CSP em enforcement: incrementos seguintes.

## Riscos

- Quebrar o menu lateral movel do shell (presente em todas as paginas). Mitigacao: sigeAlternarSidebar, sigeFecharSidebar e sigeAlternarSidebarMais replicam exactamente o toggle das classes (sidebar, sobreposicao, corpo) e a gestao do aria-expanded; node -c limpo; o gate de inline e o corredor verdes.
- Passar ou nao passar o elemento incorrectamente. Mitigacao: as funcoes que usavam this recebem o elemento (caso por omissao do despachante); o hamburguer e o window.print nao precisam do elemento (data-sige-noargs).
- Converter expressoes onde o despachante nao carrega. Mitigacao: as paginas autonomas (history.back, window.close) ficam de fora; so se converteram vistas do shell.
- O inline voltar a subir. Mitigacao: a catraca trava onclick em 171 (sem folga).

## Criterios de aceitacao

- As sete funcoes estao expostas em window e o despachante encontra-as; node -c limpo.
- As cinco vistas reduziram os onclick com php -l limpo; o menu lateral movel continua a abrir e fechar como antes.
- A catraca trava onclick em 171 (sem folga); style 2051 e script 81 inalterados.
- Superficie 199, vistas 60, opcoes 132, deps 9. Versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 99 e release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
