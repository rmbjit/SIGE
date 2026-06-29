# Revisao adversarial - v12.12.62 - Fase 4 incr 2 - switchTab e args com esc_js das vistas admin em data-sige-act

Rediagnostico adversarial do incremento que converte o switchTab e os onclick com argumentos string via esc_js das vistas admin para o despachante data-sige-act.

## Rediagnostico adversarial

Tentou-se quebrar a entrega pelos seguintes vectores:

1. O switchTab marcar a aba errada ou nao trocar. Refez-se switchTab(event, tabId) para switchTab(el), que recebe o elemento que o despachante lhe passa (via por omissao) e le o tabId do atributo data-tab que as abas ja possuem. Como as abas sao div sem filhos, o event.target da versao antiga era ja o proprio elemento, pelo que jQuery(el).addClass('active') marca a mesma aba. Confirmou-se que switchTab so e chamado por esses quatro onclick e nao programaticamente, pelo que a mudanca de assinatura nao parte outras chamadas.

2. Os argumentos serializados perderem o tipo. Usou-se wp_json_encode, que serializa inteiros como numeros, decimais como numeros e cadeias como cadeias. Os inteiros foram convertidos com (int), os decimais com (float) e as cadeias passadas tal e qual, espelhando o que a expressao inline produzia.

3. Os argumentos partirem o atributo HTML ou nao sobreviverem ao JSON.parse. Envolveu-se o wp_json_encode em esc_attr, que codifica os caracteres HTML-especiais (aspa dupla, aspa simples, menor, maior e e-comercial) para entidades, pelo que o atributo de aspas duplas nunca e fechado prematuramente. Testou-se o round-trip ate JSON.parse com um valor dificil (aspa simples, aspa dupla, menor e e-comercial) e recuperou-se o array exacto, porque o getAttribute devolve o valor HTML-descodificado e o JSON.parse aceita-o.

4. A funcao alvo nao ser encontrada pelo despachante. Cada uma das nove funcoes e global: a maioria definida como function nome, e a sigeAbrirAnular como window.sigeAbrirAnular. O despachante resolve via window[accao], pelo que todas sao encontradas. A ordem dos argumentos no array espelha a ordem na chamada original.

5. Apanhar um onclick gerado dentro de JavaScript. As conversoes foram feitas sobre os onclick em contexto HTML; o switchTab e as nove chamadas estao no markup, nao dentro de blocos script.

6. Confundir esta vaga com o enforce do shell admin. Reconheceu-se que o cabecalho CSP do shell admin so vem depois de tratados os onclick e estilos inline restantes; as vistas admin ainda tem 66 onclick e 2051 estilos inline. Esta vaga e apenas a reducao desses onclick e mantem o comportamento.

7. A catraca afrouxar. Pelo contrario: o maximo de onclick desceu de 79 para 66 (sem folga).

8. Regressao de inventario. Verificou-se: superficie 199 e enforce 33, vistas 60, opcoes 132, dependencias externas 9, sem alteracao de esquema. Versao sincronizada nas cinco fontes.

## P0

Nenhum. A vaga reduz onclick sem tocar em regras de calculo nem no ficheiro canonico, e mantem o comportamento via o despachante ja em producao.

## P1

Nenhum. O switchTab marca a mesma aba (div sem filhos, tabId do data-tab); os argumentos sao serializados com tipos preservados e o atributo codificado, com round-trip testado; as funcoes alvo sao globais; php -l limpo nas 5 vistas.

## P2 e P3

Nenhum novo. O CSP enforce do shell admin so vem depois de tratados os onclick e estilos inline restantes. A catraca (onclick 66, sem folga) impede regressao e os verificadores de JS embebido mantem-se verdes.

## Decisao

Aprovado. Zero P0 e zero P1. O switchTab foi refeito para ler o data-tab e os seus 4 call-sites passaram a data-sige-act; 9 onclick com argumentos string via esc_js passaram a data-sige-args com esc_attr(wp_json_encode), preservando tipos e codificando o atributo, com round-trip testado ate JSON.parse para um valor dificil. Todas as funcoes alvo sao globais e o comportamento e identico. Os verificadores de JavaScript embebido mantem-se verdes e a catraca de onclick desceu de 79 para 66. Restam, para o enforce do shell admin, os onclick com codigo inline e os estilos inline. Pronto para empacotar.
