# Revisao adversarial - v12.12.60 - Fase 4 incr 2 - onclick seguros das vistas admin em data-sige-act

Rediagnostico adversarial do incremento que converte os onclick seguros das vistas admin para o despachante data-sige-act.

## Rediagnostico adversarial

Tentou-se quebrar a entrega pelos seguintes vectores:

1. O botao deixar de funcionar apos a conversao. O despachante data-sige-act ja esta em producao e e validado pelos gates (smoke-inline-frontend confirma o contrato). Para os onclick sem argumentos, o data-sige-noargs chama fn(); para os de inteiro, o data-sige-args interpreta a lista JSON e chama fn com o mesmo inteiro. O despachante delega no documento, pelo que apanha tambem os botoes dentro de modais. Confirmou-se php -l limpo nas 8 vistas e os verificadores de JavaScript embebido verdes.

2. Mudar o tipo de um argumento inteiro. O data-sige-args interpreta a lista JSON, pelo que o inteiro continua a ser passado como numero, nao como cadeia. Foi por isso que se usou data-sige-args (lista JSON) e nao data-sige-arg (cadeia) para os de inteiro.

3. Converter por engano um onclick gerado dentro de JavaScript. O conversor mascara os blocos script antes de converter e restaura-os no fim, pelo que nenhum onclick dentro de um bloco script foi tocado. A contagem confirma-o: tres onclick sem argumentos que estavam dentro de blocos script foram poupados (55 candidatos sem-arg, 52 convertidos).

4. Converter um onclick que dependia de event ou de codigo inline. Esses padroes (switchTab com event, sessionStorage mais location.reload, condicoes, jQuery fadeOut, argumentos string via esc_js) ficaram expressamente fora do subconjunto seguro e nao foram tocados; permanecem como onclick para tratamento proprio.

5. Apanhar argumentos string com escape delicado. Os onclick com argumentos string via esc_js ficaram de fora porque a sua conversao exige codificar o argumento para atributo de forma diferente do esc_js; serao tratados num incremento proprio com a codificacao adequada.

6. Confundir esta vaga com o enforce do shell admin. Reconheceu-se que o cabecalho CSP do shell admin so vem depois de tratados os onclick e estilos inline restantes; as vistas admin ainda tem 93 onclick e 2051 estilos inline. Esta vaga e apenas a reducao dos onclick seguros e mantem o comportamento.

7. A catraca afrouxar. Pelo contrario: o maximo de onclick desceu de 149 para 93 (sem folga).

8. Regressao de inventario. Verificou-se: superficie 199 e enforce 33, vistas 60, opcoes 132, dependencias externas 9, sem alteracao de esquema. Versao sincronizada nas cinco fontes.

## P0

Nenhum. A vaga reduz os onclick seguros sem tocar em regras de calculo nem no ficheiro canonico, e mantem o comportamento dos botoes via o despachante ja em producao.

## P1

Nenhum. So foi convertido o subconjunto inequivocamente seguro; os de inteiro preservam o tipo numero; o conversor poupou os onclick gerados por JavaScript e os difíceis; php -l limpo nas 8 vistas.

## P2 e P3

Nenhum novo. O CSP enforce do shell admin so vem depois de tratados os onclick e estilos inline restantes. O portal publico, o finance-core e a pagina de login ficam para abordagem propria. A catraca (onclick 93, sem folga) impede regressao e os verificadores de JS embebido mantem-se verdes.

## Decisao

Aprovado. Zero P0 e zero P1. 56 onclick seguros das vistas admin (52 sem argumentos via data-sige-noargs, 4 de inteiro via data-sige-args) passaram ao despachante data-sige-act, com comportamento identico. Os onclick gerados por JavaScript foram poupados por mascara dos blocos script, e os difíceis (event, codigo inline, jQuery, string esc_js) ficaram intactos para incrementos proprios. Os verificadores de JavaScript embebido mantem-se verdes e a catraca de onclick desceu de 149 para 93. O caminho para o enforce do shell admin continua, faltando os onclick difíceis e os estilos inline. Pronto para empacotar.
