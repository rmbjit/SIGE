# Revisao adversarial - v12.12.57 - Fase 4 incr 2 - Nonce CSP nas paginas autonomas

Rediagnostico adversarial do incremento que aplica o nonce CSP as tags script das paginas autonomas.

## Rediagnostico adversarial

Tentou-se quebrar a entrega pelos seguintes vectores:

1. O auxiliar de nonce nao estar definido onde uma pagina autonoma renderiza, causando erro fatal. Verificou-se que os modelos PDF (pauta e boletim) sao incluidos pelos respectivos handlers (pauta-pdf-handler e boletim-pdf-handler), que correm apos o bootstrap; a vista do boletim do jardim e os includes do motor de documentos e da pagina da camara renderizam tambem apos o bootstrap, onde o core-helpers ja foi carregado incondicionalmente. O auxiliar esta disponivel em todos os casos.

2. Partir o CSP existente do motor de documentos. O motor de documentos ja envia tres cabecalhos Content-Security-Policy, todos com unsafe-inline e sem nonce na directiva. Esses cabecalhos nao foram tocados. Como a directiva nao referencia nonce, o unsafe-inline continua activo: os scripts inline (agora com atributo nonce) continuam a correr e, sobretudo, os botoes de impressao em onclick continuam a funcionar. Acrescentar o atributo nonce as tags e, por isso, inocuo.

3. Partir a pagina da camara da portaria. A pagina mantem o seu cabecalho Permissions-Policy intacto. Nao se acrescentou nenhum CSP, pelo que o acesso a camara e a leitura de crachas nao sao afectados.

4. Partir os verificadores de JavaScript embebido. Os modelos PDF de pauta e boletim e a vista do jardim estao em admin e sao varridos por esses verificadores; a tag com nonce e normalizada para extraccao desde a v12.12.55. Confirmou-se check-js-views e check-js-views-combined verdes. O motor de documentos e a pagina da camara estao em includes, fora do alcance desses verificadores.

5. A conversao apanhar literais que nao sao tags ao nivel do template. Confirmou-se que, nos 5 ficheiros, todas as ocorrencias bare de tag script correspondem a tags ao nivel do template (o numero de tags com quebra de linha coincide com o numero total de ocorrencias bare). Os 13 document.write da vista do jardim, que constroem documentos de impressao proprios, nao contem tag script bare e nao foram tocados.

6. Confundir o enforce com a aplicacao do nonce. Reconheceu-se explicitamente que estas paginas usam onclick inline (botoes de impressao) e que um CSP estrito baseado em nonce os bloquearia; por isso o enforcement do CSP nestas paginas, que exige primeiro converter esses onclick, fica para incremento dedicado. Esta vaga limita-se ao nonce, que e inocuo.

7. A catraca afrouxar. Pelo contrario: o maximo de blocos script desceu de 14 para 7 (sem folga). Conclui a aplicacao do nonce a todos os scripts inline alcancaveis.

8. Regressao de inventario. Verificou-se: superficie 199 e enforce 33, vistas 60, opcoes 132, dependencias externas 9, sem alteracao de esquema. Versao sincronizada nas cinco fontes.

## P0

Nenhum. A vaga prepara o CSP sem tocar em regras de calculo nem no ficheiro canonico, e acrescentar o nonce e inocuo (nao ha CSP a referenciar o nonce e o CSP existente mantem unsafe-inline).

## P1

Nenhum. As paginas renderizam apos o bootstrap; os cabecalhos de seguranca existentes ficaram intactos; os botoes de impressao continuam a funcionar; php -l limpo nos 5 ficheiros.

## P2 e P3

Nenhum novo. O enforcement do CSP nestas paginas depende de converter os onclick inline e fica para incremento dedicado. O portal publico, o finance-core, a pagina de login (pre-auth) e o popup document.write ficam para abordagem propria. A catraca (blocos script 7, sem folga) impede regressao e os verificadores de JS embebido mantem-se verdes.

## Decisao

Aprovado. Zero P0 e zero P1. As 7 tags script das paginas autonomas passaram a renderizar com nonce por pedido (inocuo, com os cabecalhos de seguranca existentes intactos e os botoes de impressao a funcionar), os verificadores de JavaScript embebido mantem-se verdes, e a catraca de blocos script desceu de 14 para 7, concluindo a aplicacao do nonce a todos os scripts inline alcancaveis. O enforcement do CSP nestas paginas, que depende de converter os onclick inline de impressao, fica para incremento dedicado. Pronto para empacotar.
