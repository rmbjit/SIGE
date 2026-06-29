# Carta da correccao - v12.12.36 - Despacho do dialogo de confirmacao (aprovar_notas)

Correccao de defeito antes de retomar a fase de documentos, uploads e QR (incremento 3). Nao e nova funcionalidade.

## Objectivo

Garantir que, no view aprovar_notas, premir Aprovar mostra o dialogo de Aprovar e submete a accao de aprovar, e premir Rejeitar mostra o dialogo de Rejeitar e submete a accao de rejeitar. Eliminar o caso em que um botao sem confirmacao herdava o dialogo e a accao de outro botao do mesmo formulario.

## Diagnostico (causa raiz)

O enhancer declarativo de confirmacao (assets/sige-ui.js) tem um interceptor do evento de submissao. Quando o botao premido (event.submitter) nao tinha [data-sige-confirm], o codigo recorria a form.querySelector e apanhava o primeiro botao de submissao com confirmacao do mesmo formulario. No formulario do aprovar_notas, esse primeiro botao e o Rejeitar. Resultado: premir Aprovar mostrava o dialogo Rejeitar notas e, ao confirmar, o handler chamava form.requestSubmit com o botao Rejeitar, enviando o valor da accao errada (rejeitar). O utilizador julgava aprovar mas as notas eram rejeitadas e devolvidas ao professor. O backend de aprovacao e rejeicao estava correcto; o defeito estava apenas no despacho do dialogo no cliente.

## Incluido

- Correccao do handler de submissao em assets/sige-ui.js: passa a respeitar o botao realmente premido. Se o submitter e conhecido, so pede confirmacao quando esse botao a exige; um botao sem [data-sige-confirm] submete normalmente e nunca herda o dialogo de outro botao. O recuo por form.querySelector fica reservado ao caso sem submitter conhecido (por exemplo, Enter num campo em navegadores antigos), preservando a seguranca de submissao por teclado.
- Dialogo proprio para o botao Aprovar em admin/academic/aprovar_notas-view.php (titulo Aprovar notas, texto proprio e botao Aprovar), tornando a confirmacao simetrica e clara.
- Gate estatico (tools/check-confirm-dispatch.php) que tranca o invariante, registado no corredor, que passa para 83 verificacoes.

## Excluido

- O backend de aprovacao e rejeicao nao e tocado (ja estava correcto).
- Nenhuma regra de calculo academico ou financeiro e tocada. Sem novo ecra. Sem alteracao de esquema.
- A foto e os itens da fase de documentos (links com expiracao, limpeza de orfaos, QR) ficam para o incremento 3.

## Riscos

- A correccao do handler podia alterar o comportamento de submissao por teclado. Mitigacao: o recuo por querySelector mantem-se para o caso sem submitter, que e o da submissao por teclado em navegadores antigos; nos navegadores modernos o submitter e o botao por omissao do formulario.
- Outros formularios podiam depender do comportamento antigo. Mitigacao: um varrimento de todos os formularios das views confirmou que aprovar_notas era o unico com o padrao misto; depender do comportamento antigo seria, ele proprio, um defeito.

## Criterios de aceitacao

- Premir Aprovar mostra o dialogo Aprovar notas e, ao confirmar, as notas ficam aprovadas.
- Premir Rejeitar mostra o dialogo Rejeitar notas e, ao confirmar, as notas sao rejeitadas.
- A accao submetida corresponde sempre ao botao premido.
- Nenhum formulario das views tem um botao de submissao com confirmacao ao lado de outro sem.
- Superficie de accao inalterada (199, enforce 33; views 60), sem opcoes novas (132), sem dependencias novas (12), versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 83/83 e release gate verde a partir de pasta limpa. Rediagnostico adversarial Zero P0/P1.
