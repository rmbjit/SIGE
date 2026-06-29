# Revisao adversarial - v12.12.54 - Fase 4 incr 2 - Cadeias jQuery e IIFE em onclick reescritas em vanilla

Rediagnostico adversarial. O exercicio assume a postura de um revisor hostil e procura partir cada criterio antes de o declarar pronto. O foco e a fidelidade da reescrita vanilla das cadeias jQuery e IIFE.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. O teste de visibilidade vanilla divergir do :visible do jQuery. O jQuery considera visivel um elemento com offsetWidth maior que zero, ou offsetHeight maior que zero, ou getClientRects com comprimento maior que zero; sigeFecharModalClone reproduz exactamente essa regra, percorrendo os elementos com a classe de modal. Para o caso de uso (fechar o modal de clonagem e, se nenhum modal estiver visivel, retirar a classe do corpo), o comportamento e identico.
2. A reabertura do painel de pendentes nao repetir a tentativa. sigeReabrirPainelPendentes replica a sequencia exacta da funcao imediatamente invocada: seleciona o painel, remove o atributo open e volta a po-lo apos cem milissegundos, o que reabre o elemento details e dispara de novo o carregamento.
3. A funcao nao ser encontrada pelo despachante. Ambas estao expostas em window (e nao em variaveis locais) e o despachante procura via window[accao]. Sao definidas no script da propria vista, que corre no carregamento, antes de qualquer clique; o botao so usa data-sige-act, sem execucao no momento da renderizacao.
4. O onclick gerado em template JS ficar mal formado. No central de WhatsApp, o botao e construido numa cadeia JS; o onclick com a funcao imediatamente invocada (com aspas escapadas) foi substituido por data-sige-act mais data-sige-noargs, mais simples e sem aspas a escapar; o ficheiro linta limpo.
5. Co-localizar a funcao na vista poluir o ambiente global de forma perigosa. As funcoes sao expostas em window com nomes especificos (sigeReabrirPainelPendentes, sigeFecharModalClone) e selectores proprios da vista; ficam junto ao uso, o que e mais claro do que coloca-las no kit global. Nao colidem com nomes existentes.
6. Sobrar alguma cadeia jQuery ou IIFE em onclick de ficheiros limpos. Verificou-se que, apos esta vaga, nao resta nenhum onclick jQuery ou IIFE em ficheiros limpos (sem janela autonoma, sem popup e nao canonicos).
7. Converter onclick onde o despachante nao carrega. Os onclick que restam estao em ficheiros com janela autonoma, em popups ou em canonicos, e ficaram de fora, por desenho.
8. O inline voltar a subir, ou a vaga fingir progresso. A catraca trava onclick em 162 (sem folga); o gate falha se subir. As duas vistas reduziram efectivamente os seus onclick.
9. Acrescentar superficie, vista ou opcao, ou partir incrementos anteriores. Nada disso muda: superficie 199, vistas 60, opcoes 132, deps 9; gates de pagamentos, cofre, Fase 1, Fase 4 incr 1 e vagas anteriores verdes; release gate verde a partir de pasta limpa. As regras anteriores do despachante ficam inalteradas.

## P0

Nenhum. Vaga de interface; nao toca em regras de calculo nem em ficheiros canonicos. So muda a forma de ligar a logica de interface.

## P1

Nenhum em aberto. As funcoes vanilla sao fieis as cadeias originais (reabertura do painel, fecho do modal com o teste de visibilidade do jQuery reproduzido); estao co-localizadas e expostas em window.

## P2 e P3

Nenhum novo. Os onclick em ficheiros autonomos, popups ou canonicos de fora; catraca sem folga; nao resta jQuery ou IIFE em onclick de ficheiros limpos.

## Decisao

Aprovado. Zero P0 e zero P1 em aberto. A versao reescreve as ultimas cadeias jQuery e IIFE em onclick para vanilla, em funcoes nomeadas co-localizadas (reabrir painel de pendentes na central de WhatsApp, fechar modal de clonagem na matriz, com o teste de visibilidade do jQuery reproduzido fielmente), concluindo os onclick em ficheiros limpos, com a catraca a travar onclick em 162 sem folga. Aditivo, sem tocar em regras de calculo nem em ficheiros canonicos, sem nova superficie, vista ou opcoes, sem alteracao de esquema, calculo byte-identico, deps 9. Corredor 99; gates anteriores verdes; release gate verde a partir de pasta limpa. Pronto para entrega. Seguem-se os blocos script inline, e por fim o CSP em enforcement.
