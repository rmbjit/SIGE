# Revisao adversarial - v12.12.61 - Fase 4 incr 2 - uploadDoc e fecho de modais das vistas admin em data-sige-act

Rediagnostico adversarial do incremento que converte mais onclick seguros das vistas admin para o despachante data-sige-act.

## Rediagnostico adversarial

Tentou-se quebrar a entrega pelos seguintes vectores:

1. O botao uploadDoc deixar de funcionar ou receber outro valor. A conversao passa de onclick uploadDoc com cadeia estatica para data-sige-act mais data-sige-arg, e o despachante chama fn(arg) com a mesma cadeia. uploadDoc e uma funcao global definida na vista, encontrada pelo despachante via window. As seis cadeias (bi, cert, vacina, doc_bi, doc_cert, doc_cv) sao estaticas, sem PHP nem escape, pelo que passam inalteradas.

2. O fecho de modal nao funcionar. O novo wrapper window.sigeFecharModalJq faz exactamente jQuery(sel).fadeOut(200), igual a expressao inline que substitui, e guarda a presenca de jQuery antes de a usar. O despachante chama-o com o mesmo seletor (#modal-turma, #modal-docentes, #modal-horario, #modal-alunos) via data-sige-arg. O wrapper segue o padrao das funcoes globais ja expostas para o despachante em assets/sige-ui.js.

3. Apanhar um onclick window.print() nas vistas admin. Verificou-se que nao existem onclick window.print() nas vistas admin fora de blocos script (zero), pelo que nada havia a converter; os que existem estao dentro de blocos script e foram poupados pela mascara.

4. Converter por engano um onclick gerado dentro de JavaScript. O conversor mascara os blocos script antes de converter e restaura-os no fim, pelo que nenhum onclick dentro de um bloco script foi tocado.

5. Tocar no switchTab sem o cuidado devido. O switchTab usa event.target para marcar a aba activa; converte-lo exige refactor da assinatura para receber o elemento do despachante. Foi deixado intacto para incremento proprio, e nao foi tocado nesta vaga.

6. O wrapper novo quebrar o despachante ou o gate. O sige-ui.js passa o node -c e o smoke-inline-frontend continua verde (18 verificacoes), pelo que o wrapper novo nao quebra o contrato do despachante.

7. A catraca afrouxar. Pelo contrario: o maximo de onclick desceu de 93 para 79 (sem folga).

8. Regressao de inventario. Verificou-se: superficie 199 e enforce 33, vistas 60, opcoes 132, dependencias externas 9, sem alteracao de esquema. Versao sincronizada nas cinco fontes.

## P0

Nenhum. A vaga reduz onclick seguros sem tocar em regras de calculo nem no ficheiro canonico, e mantem o comportamento dos botoes via o despachante ja em producao.

## P1

Nenhum. So foram convertidos padroes inequivocamente seguros (uploadDoc com cadeia estatica e fecho de modais jQuery via wrapper fiel); o conversor poupou os onclick gerados por JavaScript; o switchTab ficou intacto; php -l limpo nas 3 vistas; sige-ui.js passa node -c.

## P2 e P3

Nenhum novo. O CSP enforce do shell admin so vem depois de tratados os onclick e estilos inline restantes. A catraca (onclick 79, sem folga) impede regressao e os verificadores de JS embebido mantem-se verdes.

## Decisao

Aprovado. Zero P0 e zero P1. 14 onclick seguros das vistas admin (6 uploadDoc com cadeia estatica via data-sige-arg, 8 fecho de modais jQuery via o novo wrapper sigeFecharModalJq) passaram ao despachante data-sige-act, com comportamento identico. Os onclick gerados por JavaScript foram poupados por mascara dos blocos script, nao ha onclick window.print() nas vistas admin, e o switchTab ficou intacto para incremento proprio. Os verificadores de JavaScript embebido mantem-se verdes, o sige-ui.js passa node -c e a catraca de onclick desceu de 93 para 79. O caminho para o enforce do shell admin continua, faltando o switchTab, os onclick com codigo inline e os estilos inline. Pronto para empacotar.
