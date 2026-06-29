# Revisao adversarial - v12.12.50 - Fase 4 incr 2 - Onclick para data-sige-act (vistas limpas)

Rediagnostico adversarial. O exercicio assume a postura de um revisor hostil e procura partir cada criterio antes de o declarar pronto. O foco e a fidelidade da substituicao do onclick e a retro-compatibilidade com a vaga 1.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. Passar o elemento a uma funcao que o usa como flag. O despachante, por omissao, chama fn(elemento). Uma funcao como plToggleNovoPlano(force) usa o 1o parametro: convertida com fn(elemento), force ficaria truthy e o comportamento mudava. Resolvido: o padrao onclick="fn()" converte-se com data-sige-noargs, que chama fn() (nunca o elemento). Confirmado: plToggleNovoPlano ficou com data-sige-noargs.
2. Partir a vaga 1. As conversoes da vaga 1 nao usam data-sige-noargs; o caso por omissao (fn(elemento)) mantem-se inalterado. Funcoes como sigeOpenDetails(btn), que dependem do elemento, continuam a recebe-lo. O marcador novo e estritamente aditivo.
3. Trocar fn(this) por algo que nao passe o elemento. onclick="fn(this)" converte-se para data-sige-act sem marcador, caindo no caso por omissao fn(elemento); o elemento e o mesmo no que o this apontava (closest do alvo do clique e o proprio elemento com data-sige-act). Fiel.
4. Mudar o tipo de um argumento. So se converteram fn('texto') (um argumento de texto, via data-sige-arg) e nunca numeros nem PHP interpolado. Os onclick com numeros (por exemplo activarTab(1)), multiplos argumentos ou PHP ficam de fora desta vaga, para nao trocar tipos sem um despachante mais rico.
5. Converter onclick que vai para uma janela de impressao (onde o despachante nem carrega). As vistas com popup ficaram de fora desta vaga; so se converteram dez vistas sem popup e nao-autonomas.
6. Deixar onclick por converter a fingir que esta feito. A catraca trava onclick em 201 (sem folga); o gate falha se subir. As vistas acta-view e financeiro-despesas ficaram a zero onclick; as restantes reduziram.
7. Despachante sem o contrato esperado. O gate passa a exigir, em assets/sige-ui.js, o tratamento de data-sige-arg e de data-sige-noargs (mais o caso por omissao); node -c limpo.
8. Acrescentar superficie, vista ou opcao, ou partir incrementos anteriores. Nada disso muda: superficie 199, vistas 60, opcoes 132, deps 9; gates de pagamentos, cofre, Fase 1, Fase 4 incr 1 e vagas anteriores continuam verdes; release gate verde a partir de pasta limpa.

## P0

Nenhum. Vaga de interface; nao toca em regras de calculo nem em ficheiros canonicos. So muda a forma de ligar o handler.

## P1

Nenhum em aberto. O unico risco real (passar o elemento a uma funcao com 1o parametro significativo) fica eliminado pelo data-sige-noargs; a retro-compatibilidade com a vaga 1 esta preservada.

## P2 e P3

Nenhum novo. Conversoes so de padroes seguros; vistas com popup, PDF, portal e canonicos de fora; numeros, multi-arg e PHP adiados; catraca sem folga; contrato do despachante verificado.

## Decisao

Aprovado. Zero P0 e zero P1 em aberto. A versao inicia a migracao de onclick para data-sige-act, com um despachante agora fiel ao onclick (data-sige-arg para fn(arg), data-sige-noargs para fn(), caso contrario fn(elemento)), 49 conversoes seguras em 10 vistas limpas, e a catraca a travar onclick em 201 sem folga. Aditivo, retro-compativel com a vaga 1, sem tocar em regras de calculo nem em ficheiros canonicos, sem nova superficie, vista ou opcoes, sem alteracao de esquema, calculo byte-identico, deps 9. Corredor 99; gates anteriores verdes; release gate verde a partir de pasta limpa. Pronto para entrega. Seguem-se os onclick complexos (numeros, multiplos argumentos, PHP) com despachante mais rico, depois os blocos script inline, e por fim o CSP em enforcement.
