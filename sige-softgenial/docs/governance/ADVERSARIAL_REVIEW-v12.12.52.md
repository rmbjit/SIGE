# Revisao adversarial - v12.12.52 - Fase 4 incr 2 - Expressoes inline em onclick para funcoes nomeadas

Rediagnostico adversarial. O exercicio assume a postura de um revisor hostil e procura partir cada criterio antes de o declarar pronto. O foco e a fidelidade das funcoes nomeadas as expressoes originais, em especial o menu lateral movel do shell, presente em todas as paginas.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. Quebrar o menu lateral movel do shell. As tres funcoes (sigeAlternarSidebar, sigeFecharSidebar, sigeAlternarSidebarMais) replicam exactamente as expressoes originais: o hamburguer alterna as classes da sidebar, da sobreposicao e do corpo; a sobreposicao remove essas classes (e a classe show do proprio elemento); o botao de mais opcoes alterna as classes em funcao do estado aberto e actualiza o aria-expanded no proprio elemento. O hamburguer nao usava this, pelo que vai com data-sige-noargs; os outros dois usavam this e recebem o elemento. Corredor e gate de inline verdes.
2. Passar ou nao passar o elemento incorrectamente. As funcoes que usavam this (sigeAlternarQuebraTexto, sigeAlternarClasseProximo, sigeFecharPopupBackdrop, sigeFecharSidebar, sigeAlternarSidebarMais) recebem o elemento pelo caso por omissao do despachante; as que nao usavam this (sigeImprimirPagina, sigeAlternarSidebar) vao com data-sige-noargs e sao chamadas sem argumentos. Fiel ao onclick original.
3. As funcoes nao serem encontradas pelo despachante. Estao expostas em window (e nao em sigeUi), que e onde o despachante procura via window[accao]; o IIFE atribui-as a window dentro do seu corpo. node -c limpo.
4. Converter uma expressao onde o despachante nao carrega. As paginas autonomas (passagem de documentos e historico do aluno, que usam history.back e window.close e tem o seu proprio HTML minimo) ficaram de fora; so se converteram vistas que correm no shell admin, confirmado pela ausencia de sinais autonomos.
5. Converter expressoes com navegacao a fingir que sao seguras. Os onclick com window.open(this.href) e window.location envolvem a accao por omissao do link e ficaram de fora desta vaga, para uma vaga propria que trate a accao por omissao.
6. Converter logica de terceiros (jQuery, IIFE) sem cuidado. As cadeias jQuery e as funcoes imediatamente invocadas ficaram de fora; so se converteram expressoes simples e auto-contidas.
7. O inline voltar a subir, ou a vaga fingir progresso. A catraca trava onclick em 171 (sem folga); o gate falha se subir. As cinco vistas reduziram efectivamente os seus onclick.
8. Acrescentar superficie, vista ou opcao, ou partir incrementos anteriores. Nada disso muda: superficie 199, vistas 60, opcoes 132, deps 9; gates de pagamentos, cofre, Fase 1, Fase 4 incr 1 e vagas anteriores verdes; release gate verde a partir de pasta limpa. As regras anteriores do despachante (data-sige-args, data-sige-arg, data-sige-noargs, caso por omissao) ficam inalteradas.

## P0

Nenhum. Vaga de interface; nao toca em regras de calculo nem em ficheiros canonicos. So muda a forma de ligar a logica de interface.

## P1

Nenhum em aberto. As funcoes nomeadas sao fieis as expressoes; o menu lateral movel do shell esta preservado; a passagem do elemento corresponde ao uso original do this.

## P2 e P3

Nenhum novo. Navegacao, jQuery, IIFE, template JS e paginas autonomas de fora; catraca sem folga.

## Decisao

Aprovado. Zero P0 e zero P1 em aberto. A versao converte 10 expressoes inline em onclick para sete funcoes nomeadas globais, ligadas pelo despachante data-sige-act e fieis as expressoes que substituem, incluindo o menu lateral movel do shell, com a catraca a travar onclick em 171 sem folga. Aditivo, sem tocar em regras de calculo nem em ficheiros canonicos, sem nova superficie, vista ou opcoes, sem alteracao de esquema, calculo byte-identico, deps 9. Corredor 99; gates anteriores verdes; release gate verde a partir de pasta limpa. Pronto para entrega. Seguem-se as expressoes com navegacao (window.open(this.href), window.location), as cadeias jQuery e IIFE, e os onclick de template JS, depois os blocos script inline, e por fim o CSP em enforcement.
