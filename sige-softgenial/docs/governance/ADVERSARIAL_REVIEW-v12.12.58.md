# Revisao adversarial - v12.12.58 - Fase 4 incr 2 - onclick de impressao das autonomas em addEventListener

Rediagnostico adversarial do incremento que converte os onclick de impressao das paginas autonomas para addEventListener.

## Rediagnostico adversarial

Tentou-se quebrar a entrega pelos seguintes vectores:

1. Os botoes ficarem inertes apos a conversao. A ligacao addEventListener corre ao nivel de topo do script de cada pagina, e em todas as paginas o script encontra-se depois dos botoes no DOM, pelo que os elementos ja existem quando a ligacao corre. Confirmou-se php -l limpo nos 4 ficheiros e os verificadores de JavaScript embebido verdes para os modelos PDF em admin.

2. A ligacao nao correr por causa do IIFE de QRious. No motor de documentos e nos modelos com QR, parte do script esta dentro de um IIFE que pode retornar cedo quando a biblioteca QRious nao esta carregada. A ligacao foi colocada ao nivel de topo do script, fora desses IIFE, para correr sempre, independentemente da presenca da biblioteca. No caminho cujo IIFE testa a presenca de QRious de forma positiva, a ligacao foi colocada apos esse bloco mas ainda dentro do IIFE, que nao retorna cedo, pelo que corre na mesma; nos restantes, foi colocada fora do IIFE.

3. Mudar o tipo do indice no imprimir um boletim. A funcao sigeImprimirBoletins testa typeof igual a numero. O atributo data devolve uma cadeia, pelo que a ligacao reconverte com parseInt a numero (parseInt base 10), preservando o comportamento: imprimir todos quando sem indice, imprimir um quando com indice numerico.

4. Partir cabecalhos de seguranca existentes. Nenhum cabecalho foi tocado: o motor de documentos mantem os seus tres cabecalhos Content-Security-Policy e a pagina da camara mantem o seu Permissions-Policy. A vaga limita-se a remover os handlers inline, sem mexer em cabecalhos.

5. Apanhar ocorrencias que nao sao handlers de botao. A conversao dos botoes window.print e window.close foi feita sobre o padrao onclick com a chamada, que so aparece em botoes; a impressao automatica ao carregar a pagina usa addEventListener em window e nao foi tocada. Em documents-engine, as quatro chamadas de imprimir e tres de fechar foram convertidas; restam zero onclick.

6. Confundir esta vaga com o enforce. Reconheceu-se que o CSP enforce (substituir unsafe-inline por nonce no motor de documentos e acrescentar cabecalho as restantes) e o passo seguinte, agora possivel por estas paginas ja nao terem handlers inline. Esta vaga e apenas a preparacao e mantem o comportamento.

7. A catraca afrouxar. Pelo contrario: o maximo de onclick desceu de 162 para 149 (sem folga).

8. Regressao de inventario. Verificou-se: superficie 199 e enforce 33, vistas 60, opcoes 132, dependencias externas 9, sem alteracao de esquema. Versao sincronizada nas cinco fontes.

## P0

Nenhum. A vaga prepara o CSP enforce sem tocar em regras de calculo nem no ficheiro canonico, e mantem o comportamento dos botoes.

## P1

Nenhum. A ligacao corre sempre (ao nivel de topo, fora dos IIFE que retornam cedo); o tipo do indice e preservado; nenhum cabecalho de seguranca foi tocado; php -l limpo nos 4 ficheiros.

## P2 e P3

Nenhum novo. O CSP enforce e a substituicao de unsafe-inline por nonce ficam para o incremento seguinte. O portal publico, o finance-core, a pagina de login (pre-auth) e o popup document.write ficam para abordagem propria. A catraca (onclick 149, sem folga) impede regressao e os verificadores de JS embebido mantem-se verdes.

## Decisao

Aprovado. Zero P0 e zero P1. Os 13 onclick de impressao das paginas autonomas passaram a addEventListener local (botoes via atributos data, ligacao ao nivel de topo do script, fora dos IIFE de QRious), com comportamento identico e cabecalhos de seguranca existentes intactos. Os verificadores de JavaScript embebido mantem-se verdes e a catraca de onclick desceu de 162 para 149. As paginas autonomas ficam agora sem handlers inline, prontas para o CSP enforce, que e o incremento seguinte. Pronto para empacotar.
