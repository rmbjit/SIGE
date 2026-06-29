# Carta da fase - v12.12.50 - Fase 4 incr 2 - Onclick para data-sige-act (vistas limpas)

Incremento 2 da Fase 4 (CSP e front-end seguro): inicio da migracao dos manipuladores de evento inline (onclick) para o despachante declarativo data-sige-act. O CSP em enforcement bloqueia handlers inline, pelo que removelos por vagas e um pre-requisito. Sequencia a vaga 1 (v12.12.46), que criou o despachante e tratou os onclick do financeiro-lancamentos.

## Objectivo

Comecar a substituir os onclick por data-sige-act sem alterar a logica dos handlers nem o comportamento, sob a catraca que garante que o inline so desce. Reforcar o despachante para que a substituicao seja fiel ao onclick original.

## Incluido

- Contrato fiel no despachante (assets/sige-ui.js): data-sige-arg chama fn(arg); data-sige-noargs chama fn() (sem passar nada); caso contrario chama fn(elemento), equivalente ao this. O marcador data-sige-noargs e novo e e o que torna seguro converter onclick="fn()" sem passar o elemento a funcoes cujo 1o parametro e significativo (por exemplo plToggleNovoPlano(force)).
- Retro-compatibilidade total: as conversoes da vaga 1 (sem data-sige-noargs) continuam a receber o elemento, exactamente como antes.
- Conversao de 49 onclick SEGUROS em dez vistas sem popup e nao-autonomas: fn() (42, com data-sige-noargs), fn(this) (6) e fn('texto') (1).
- Aperto da catraca check-inline-frontend: maximo de onclick de 250 para 201; o gate passa tambem a exigir o contrato data-sige-arg/data-sige-noargs/elemento.

## Excluido

- Ficheiros com janela de impressao autonoma (onde o despachante nem carrega), templates de PDF, portal e ficheiros canonicos: nao se convertem, por desenho.
- Onclick com numeros, multiplos argumentos ou PHP interpolado: ficam para vagas com despachante mais rico (data-sige-args ou semelhante).
- Os blocos script inline (81) e os estilos remanescentes: vagas proprias.
- CSP em enforcement (nonce por pedido e flip do cabecalho): incremento final da fase.
- Qualquer alteracao a regras de calculo, a ficheiros canonicos ou ao esquema.

## Riscos

- Mudar o comportamento de uma funcao ao passar o elemento onde antes nao se passava nada. Mitigacao: o padrao fn() converte-se com data-sige-noargs, que chama fn() (nunca o elemento); confirmado para plToggleNovoPlano(force), que usa o 1o parametro.
- Partir a vaga 1. Mitigacao: o data-sige-noargs e aditivo; o caso por omissao (fn(elemento)) mantem-se, pelo que as conversoes da vaga 1 (ex.: sigeOpenDetails(btn)) continuam a funcionar.
- Converter onclick em conteudo que vai para janela de impressao (onde o despachante nao carrega). Mitigacao: as vistas com popup ficam de fora desta vaga.
- O inline voltar a subir. Mitigacao: a catraca trava onclick em 201 (sem folga).

## Criterios de aceitacao

- O despachante honra data-sige-arg, data-sige-noargs e o caso por omissao; node -c limpo; o gate verifica o contrato.
- As dez vistas reduziram os onclick com php -l limpo; plToggleNovoPlano com data-sige-noargs.
- A catraca trava onclick em 201 (sem folga); style 2051 e script 81 inalterados.
- Superficie 199, vistas 60, opcoes 132, deps 9. Versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 99 e release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
