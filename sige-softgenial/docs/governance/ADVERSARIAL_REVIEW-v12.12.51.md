# Revisao adversarial - v12.12.51 - Fase 4 incr 2 - Onclick complexos para data-sige-args (despachante mais rico)

Rediagnostico adversarial. O exercicio assume a postura de um revisor hostil e procura partir cada criterio antes de o declarar pronto. O foco e a seguranca e fidelidade da construcao do JSON com PHP no atributo, e a retro-compatibilidade do despachante.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. PHP no atributo gerar HTML invalido ou abrir injeccao. O atributo e construido com esc_attr(wp_json_encode([...])): wp_json_encode produz JSON valido (escapa aspas duplas internas) e esc_attr escapa para o contexto de atributo HTML (aspas, e comercial, maior e menor, plica). A simulacao de renderizacao confirma que nomes com plica, aspas, e comercial, sinais de maior e menor e acentos resultam num atributo valido e num JSON.parse fiel ao valor original. E inclusive mais robusto do que o esc_js usado no onclick original.
2. Trocar o tipo de um argumento. data-sige-args usa uma lista JSON; JSON.parse devolve numeros como numeros e booleanos como booleanos. Os ids inteiros foram convertidos com (int) e os booleanos com data-sige-args='[true|false]'. Tipos preservados.
3. Um JSON invalido disparar erro ou comportamento inesperado. O despachante le data-sige-args com try/catch; se o JSON.parse falhar, a funcao nao e disparada.
4. O elemento (this) perder-se na conversao de fn('x', this). data-sige-self, em conjunto com data-sige-args, anexa o proprio elemento como ultimo argumento. Confirmado nos handlers afectados (por exemplo carregarMatriz(classe, el) e provisionarAluno(id, el)).
5. Partir as vagas 1 e 2. data-sige-args e data-sige-self sao aditivos; as regras anteriores (data-sige-arg para fn(arg), data-sige-noargs para fn() e o caso por omissao fn(elemento)) ficam inalteradas. As conversoes anteriores continuam a funcionar.
6. O conversor atravessar fronteiras de blocos PHP. Houve exactamente esse risco: uma chamada de quatro argumentos terminava em fecho de bloco mais parentesis, e a captura nao gulosa do PHP atravessava varios blocos. Resolvido: a captura usa um lookahead que para na sequencia de fecho do bloco, pelo que o conversor ignora o que nao trata; a chamada de quatro argumentos ficou diferida, e os oito ficheiros lintam limpos.
7. Converter expressoes inline a fingir que sao argumentos. As expressoes (window.print, this.style, this.classList, IIFE, jQuery, window.open(this.href), window.location) e os onclick gerados em template JS NAO foram convertidos: ficam para a vaga de funcoes nomeadas. So se converteram chamadas de funcao com argumentos.
8. Despachante sem o contrato rico esperado, ou catraca com folga. O gate passa a exigir data-sige-args, data-sige-self e JSON.parse no despachante, alem do contrato anterior; a catraca trava onclick em 181 (sem folga). node -c limpo.
9. Acrescentar superficie, vista ou opcao, ou partir incrementos anteriores. Nada disso muda: superficie 199, vistas 60, opcoes 132, deps 9; gates de pagamentos, cofre, Fase 1, Fase 4 incr 1 e vagas anteriores verdes; release gate verde a partir de pasta limpa.

## P0

Nenhum. Vaga de interface; nao toca em regras de calculo nem em ficheiros canonicos. So muda a forma de ligar o handler.

## P1

Nenhum em aberto. O ponto delicado (PHP no atributo) fica coberto por esc_attr(wp_json_encode([...])), validado por simulacao de renderizacao com dados-limite; a retro-compatibilidade com as vagas anteriores esta preservada; o JSON invalido nao dispara a funcao.

## P2 e P3

Nenhum novo. Expressoes inline e template JS de fora; chamada de quatro argumentos diferida; catraca sem folga; contrato rico do despachante verificado.

## Decisao

Aprovado. Zero P0 e zero P1 em aberto. A versao migra os onclick complexos (numeros, booleanos, multiplos argumentos, PHP) para um despachante mais rico (data-sige-args com JSON e tipos preservados, mais data-sige-self para anexar o elemento), com 20 conversoes seguras em 8 vistas, PHP construido com esc_attr(wp_json_encode([...])) e validado por simulacao, e a catraca a travar onclick em 181 sem folga. Aditivo, retro-compativel com as vagas anteriores, sem tocar em regras de calculo nem em ficheiros canonicos, sem nova superficie, vista ou opcoes, sem alteracao de esquema, calculo byte-identico, deps 9. Corredor 99; gates anteriores verdes; release gate verde a partir de pasta limpa. Pronto para entrega. Seguem-se as expressoes inline convertidas em funcoes nomeadas e os onclick de template JS, depois os blocos script inline, e por fim o CSP em enforcement.
