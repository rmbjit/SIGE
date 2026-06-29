# Carta da fase - v12.12.51 - Fase 4 incr 2 - Onclick complexos para data-sige-args (despachante mais rico)

Continuacao do incremento 2 da Fase 4 (CSP e front-end seguro): migracao dos onclick COMPLEXOS para o despachante declarativo, com um despachante mais rico. O CSP em enforcement bloqueia handlers inline; removelos por vagas e pre-requisito. Sequencia as vagas anteriores (v12.12.46 criou o despachante; v12.12.50 tratou os onclick simples).

## Objectivo

Remover os onclick que levam argumentos nao triviais (numeros, booleanos, varios valores e PHP interpolado), substituindo-os por data-sige-act com argumentos declarativos, sem alterar a logica dos handlers nem o comportamento, sob a catraca. Reforcar o despachante para preservar os tipos e anexar o elemento quando preciso.

## Incluido

- Despachante mais rico (assets/sige-ui.js), de forma aditiva:
  - data-sige-args: recebe uma lista JSON e chama fn aplicando esses argumentos, preservando tipos (numeros, booleanos, multiplos). JSON lido com try/catch.
  - data-sige-self: em conjunto com data-sige-args, anexa o elemento como ultimo argumento (substitui onclick="fn('x', this)").
  - data-sige-arg, data-sige-noargs e o caso por omissao fn(elemento) ficam inalterados (retro-compatibilidade total).
- Conversao de 20 onclick complexos em oito vistas limpas: PHP 1-arg (data-sige-arg), PHP 2-arg id mais nome (data-sige-args com wp_json_encode), PHP string ou id mais this (data-sige-args mais data-sige-self), literais booleanos, e uma string codificada.
- Para o PHP, o atributo e construido com esc_attr(wp_json_encode([...])), escapando aspas, e comercial, maior e menor e acentos, com JSON.parse fiel ao valor original (validado por simulacao).
- Aperto da catraca check-inline-frontend: maximo de onclick de 201 para 181; o gate passa tambem a exigir o contrato rico data-sige-args/data-sige-self/JSON.

## Excluido

- Expressoes inline (window.print, this.style, this.classList, IIFE, jQuery, window.open(this.href), window.location): nao sao argumentos, sao logica embutida; ficam para a vaga de funcoes nomeadas.
- Onclick gerados em template JS (com chavetas): a interpolacao e do lado do JS; ficam para vaga propria.
- Uma chamada de quatro argumentos: diferida (o conversor cobre ate dois argumentos mais this).
- Ficheiros com janela de impressao autonoma, templates de PDF, portal e canonicos: de fora, por desenho.
- Os blocos script inline (81) e o CSP em enforcement: incrementos seguintes.

## Riscos

- PHP no atributo gerar HTML ou JSON invalido, ou abrir porta a injeccao. Mitigacao: esc_attr(wp_json_encode([...])) escapa todos os caracteres sensiveis; simulacao de renderizacao confirma JSON.parse fiel com nomes contendo aspas e simbolos.
- Trocar o tipo de um argumento (numero virar texto). Mitigacao: data-sige-args usa JSON, que preserva numeros e booleanos.
- Partir as vagas anteriores. Mitigacao: data-sige-args e data-sige-self sao aditivos; as regras anteriores ficam inalteradas.
- Um JSON invalido disparar erro. Mitigacao: o despachante le o JSON com try/catch e nao dispara a funcao se falhar.
- O conversor atravessar fronteiras de blocos PHP (caso de chamadas multi-arg). Mitigacao: a captura usa lookahead que para no fecho do bloco, pelo que o conversor ignora o que nao trata (ex.: a chamada de quatro argumentos).

## Criterios de aceitacao

- O despachante honra data-sige-args (tipos preservados), data-sige-self (elemento anexado), data-sige-arg, data-sige-noargs e o caso por omissao; node -c limpo; o gate verifica o contrato rico.
- As oito vistas reduziram os onclick com php -l limpo; a simulacao de renderizacao do PHP para JSON e fiel.
- A catraca trava onclick em 181 (sem folga); style 2051 e script 81 inalterados.
- Superficie 199, vistas 60, opcoes 132, deps 9. Versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 99 e release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
