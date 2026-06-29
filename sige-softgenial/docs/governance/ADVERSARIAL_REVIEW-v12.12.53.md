# Revisao adversarial - v12.12.53 - Fase 4 incr 2 - Onclick de navegacao e template JS para funcoes nomeadas

Rediagnostico adversarial. O exercicio assume a postura de um revisor hostil e procura partir cada criterio antes de o declarar pronto. O foco e a fidelidade da navegacao (links que nao devem navegar, mailto) e dos onclick gerados em template JS.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. O link de recibo passar a navegar a pagina. O onclick original tinha return false (nao navegava); o novo usa data-sige-prevent, que chama ev.preventDefault() na fase de bolha antes de disparar a funcao, o que impede a accao por omissao do link. O href fica preservado para acessibilidade, clique do meio e menu de contexto. sigeAbrirReciboJanela abre o href numa janela de recibo, como antes.
2. O preventDefault na fase de bolha nao surtir efeito. Para o evento click, a accao por omissao do link ocorre apos a propagacao completa; chamar preventDefault num ouvinte de bolha impede-a. O enhancer de confirmacao corre em captura e nao interfere com estes links (que nao usam data-sige-confirm).
3. O endereco mailto quebrar com o assunto. O endereco e construido com esc_attr e passado em data-sige-arg; o assunto usa rawurlencode como no original. sigeIrPara navega para o endereco.
4. Os botoes de adiar deixarem de chamar a funcao certa. window.sigeHubBillSnooze ja era uma funcao global (definida no script do aviso); o data-sige-act mais data-sige-noargs chama-a sem argumentos, exactamente como o onclick original.
5. A interpolacao do template JS perder-se ou trocar tipos. Os onclick gerados em template JS (delCriterio com o id e o elemento, removerDaMatriz com o id) foram reescritos para data-sige-args com a interpolacao do lado do JS preservada (por exemplo a lista JSON usa ${c.id}); o data-sige-self anexa o elemento onde a chamada usava this. Os handlers recebem os mesmos argumentos.
6. Converter logica complexa (jQuery, IIFE) sem cuidado. As cadeias jQuery (fecho do modal de clonagem) e as funcoes imediatamente invocadas (reabertura do painel de pendentes) ficaram de fora; precisam de reescrita vanilla fiel, numa vaga propria.
7. Converter onclick onde o despachante nao carrega. Os onclick em ficheiros com janela autonoma ou em popups ficaram de fora; so se converteram vistas que correm no shell admin.
8. O inline voltar a subir, ou a vaga fingir progresso. A catraca trava onclick em 164 (sem folga); o gate falha se subir, e passa a exigir o data-sige-prevent no despachante. As quatro vistas reduziram efectivamente os seus onclick.
9. Acrescentar superficie, vista ou opcao, ou partir incrementos anteriores. Nada disso muda: superficie 199, vistas 60, opcoes 132, deps 9; gates de pagamentos, cofre, Fase 1, Fase 4 incr 1 e vagas anteriores verdes; release gate verde a partir de pasta limpa. As regras anteriores do despachante (data-sige-args, data-sige-arg, data-sige-noargs, data-sige-self, caso por omissao) ficam inalteradas.

## P0

Nenhum. Vaga de interface; nao toca em regras de calculo nem em ficheiros canonicos. So muda a forma de ligar a logica de interface.

## P1

Nenhum em aberto. A navegacao dos links de recibo e impedida por data-sige-prevent (com o href preservado); o mailto e os botoes de adiar funcionam; os onclick de template JS preservam a interpolacao e o elemento.

## P2 e P3

Nenhum novo. jQuery, IIFE, ficheiros autonomos ou popups e canonicos de fora; catraca sem folga; contrato data-sige-prevent verificado.

## Decisao

Aprovado. Zero P0 e zero P1 em aberto. A versao converte sete onclick de navegacao e de template JS para funcoes nomeadas globais, com data-sige-prevent a substituir o return false dos links de recibo (mantendo o href), e a catraca a travar onclick em 164 sem folga. Aditivo, sem tocar em regras de calculo nem em ficheiros canonicos, sem nova superficie, vista ou opcoes, sem alteracao de esquema, calculo byte-identico, deps 9. Corredor 99; gates anteriores verdes; release gate verde a partir de pasta limpa. Pronto para entrega. Seguem-se as cadeias jQuery e IIFE reescritas em vanilla, depois os blocos script inline, e por fim o CSP em enforcement.
