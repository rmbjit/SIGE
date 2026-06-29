# Revisao adversarial - v12.12.36 - Despacho do dialogo de confirmacao

Rediagnostico adversarial da correccao do defeito no view aprovar_notas. O exercicio assume a postura de um revisor hostil e procura partir a correccao antes de a declarar pronta.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. Premir Aprovar e verificar a accao submetida. O botao Aprovar passa a ter o seu proprio [data-sige-confirm]; o click e o submit sao atendidos por esse botao (sgConfirmado evita duplo pedido) e a submissao envia value aprovar. O dialogo mostrado e Aprovar notas. Coberto pelo teste de aceitacao.
2. Premir Rejeitar e verificar a accao submetida. O botao Rejeitar continua com o seu [data-sige-confirm]; mostra Rejeitar notas e submete value rejeitar. Sem regressao.
3. Forcar o handler a herdar o dialogo de outro botao. Com o submitter conhecido e sem confirmacao, o handler agora devolve cedo (return) e submete normalmente, sem recorrer ao querySelector; logo nao ha heranca. So sem submitter e que o querySelector e usado.
4. Submeter por teclado (Enter). Em navegadores modernos o submitter e o botao por omissao do formulario e a sua confirmacao e respeitada; em navegadores antigos sem submitter, o recuo por querySelector mantem a confirmacao do primeiro botao com [data-sige-confirm], preservando o comportamento de seguranca por teclado.
5. Introduzir um novo formulario com o padrao misto no futuro. A correccao do handler trata-o correctamente (cada botao com a sua accao e o seu dialogo), e o gate exige zero formularios com o padrao misto, travando regressoes.
6. Alterar a accao no servidor. O backend de aprovacao e rejeicao nao foi tocado; aprovar define aprovado e rejeitar define rejeitado, com restricao a notas pendentes e por escola. A correccao e apenas no despacho do dialogo no cliente.
7. Inflar a superficie. So foram tocados um ficheiro JS e os atributos de um botao; o extractor confirma 199 itens, sem opcoes novas (132) nem dependencias novas (12).

## P0

Nenhum apos a correccao. O caminho que submetia a accao errada foi eliminado na origem.

## P1

Nenhum. O backend mantem-se intacto e a submissao por teclado mantem a sua seguranca.

## P2 e P3

Nenhum novo.

## Decisao

Aprovado. Zero P0 e zero P1. A correccao e definitiva: o handler respeita o botao premido (fechando a classe de erro para qualquer formulario), o botao Aprovar ganha o seu proprio dialogo, e o invariante fica trancado por um gate. Backend intocado, sem nova superficie, sem opcoes novas, sem alteracao de esquema, calculo byte-identico. Corredor 83/83, gate do despacho de confirmacao verde, release gate verde a partir de pasta limpa. Pronto para entrega.
