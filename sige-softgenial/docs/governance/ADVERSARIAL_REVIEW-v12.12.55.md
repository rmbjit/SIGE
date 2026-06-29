# Revisao adversarial - v12.12.55 - Fase 4 incr 2 - Infraestrutura de nonce CSP e nonce nas tags script inline

Rediagnostico adversarial do incremento que introduz a infraestrutura de nonce CSP e aplica o nonce as tags script inline das vistas administrativas.

## Rediagnostico adversarial

Tentou-se quebrar a entrega pelos seguintes vectores:

1. O auxiliar de nonce nao estar definido onde uma tag renderiza, causando erro fatal. Verificou-se que o core-helpers e carregado incondicionalmente no bootstrap (primeiro modulo da seccao de carregamento), antes de qualquer vista renderizar, e fora de qualquer guard is_admin. Logo sige_csp_nonce e sige_csp_script_attr estao disponiveis em admin e frontend. A unica fonte excluida por ser anterior ao auxiliar (pagina de login, pre-autenticacao) nao foi convertida.

2. O nonce variar dentro do mesmo pedido, o que invalidaria o futuro cabecalho CSP. Confirmou-se por teste que sige_csp_nonce guarda o valor em variavel estatica e devolve sempre o mesmo valor dentro do pedido.

3. random_bytes lancar excepcao em ambientes sem CSPRNG. O auxiliar tem try/catch com recurso a wp_generate_password, garantindo um nonce mesmo nesse caso.

4. Acrescentar o nonce alterar o comportamento das paginas. Os navegadores ignoram o atributo nonce sem uma politica CSP, pelo que as 55 tags convertidas continuam a comportar-se exactamente como antes. O calculo financeiro mantem-se byte-identico e o ficheiro canonico finance-core nao foi tocado.

5. A conversao corromper literais ou comentarios que contenham a sequencia de uma tag script dentro de strings. O conversor so substituiu o padrao de tag ao nivel do template (script seguido de quebra de linha), pelo que os redireccionamentos construidos por echo (tag script com window.location na mesma linha) e os comentarios com a palavra script ficaram intactos. Os dois unicos casos em que um comentario novo no core-helpers e um bloco de uma so linha no admin-shell mereciam atencao foram corrigidos na mesma sessao: o comentario foi reescrito sem o literal e o bloco do admin-shell recebeu o nonce.

6. Os verificadores de JavaScript embebido deixarem de validar a sintaxe por tropecarem no PHP do proprio tag. Detectou-se que a extraccao parava no fecho do PHP dentro do tag, deixando um sinal solto no JavaScript. Corrigiu-se: check-js-views e check-js-views-combined normalizam a tag com nonce para a forma simples antes de extrair (so na copia em memoria; os ficheiros nao mudam). Ambos validam agora 66 blocos sem erro de sintaxe e o corredor voltou aos dois vermelhos pre-existentes.

7. A catraca afrouxar. Pelo contrario: o maximo de blocos script desceu de 81 para 25 (sem folga) e passou a exigir que a infraestrutura de nonce exista no core-helpers, falhando se sige_csp_nonce ou sige_csp_script_attr desaparecerem.

8. Regressao de inventario. Verificou-se: superficie 199 e enforce 33, vistas 60, opcoes 132, dependencias externas 9, sem alteracao de esquema. Versao sincronizada nas cinco fontes.

## P0

Nenhum. A vaga prepara o CSP sem tocar em regras de calculo nem no ficheiro canonico, e acrescentar o nonce e inocuo sem cabecalho CSP.

## P1

Nenhum. O nonce e por pedido e disponivel em todos os pedidos onde o plugin carrega; o auxiliar tem recurso de seguranca; as tags convertidas comportam-se como antes; php -l limpo nas 48 fontes tocadas.

## P2 e P3

Nenhum novo. Os redireccionamentos echo, as paginas autonomas (modelos PDF, motor de documentos, camara da portaria), o portal publico, o gerador de tags CDN e o finance-core ficam para abordagem propria; a pagina de login fica de fora por ser pre-autenticacao. A catraca (blocos script 25, sem folga) e os verificadores de JS embebido normalizados impedem regressao.

## Decisao

Aprovado. Zero P0 e zero P1. A infraestrutura de nonce CSP esta no lugar, 55 tags script inline das vistas levam nonce por pedido (inocuo ate o CSP ser activado), os dois verificadores de JS embebido lidam com as tags com nonce, a catraca de blocos script desceu de 81 para 25 e passou a proteger a infraestrutura de nonce. Os redireccionamentos echo, as paginas autonomas, o portal, o CDN e o finance-core canonico ficam para as proprias vagas, a caminho do cabecalho CSP (Report-Only e depois enforce). Pronto para empacotar.
