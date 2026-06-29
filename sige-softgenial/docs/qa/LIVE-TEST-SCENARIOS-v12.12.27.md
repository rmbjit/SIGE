# Cenarios de teste live - SIGE SoftGenial v12.12.27

Fase 8 incremento 4: Retencao e Expurgo (so leitura).

Este ecra e so de leitura: nao altera nem elimina dados. Mostra a politica de
retencao e o que ja excedeu o prazo.

## 1. Acesso e visibilidade do menu

1. Inicie sessao como administracao ou direccao. Confirme que aparece, na seccao
   PRIVACIDADE E DADOS, o item "Retencao e Expurgo".
2. Inicie sessao como tesouraria, secretaria ou docente: o item nao deve aparecer
   e o acesso directo ao ecra deve mostrar a mensagem de area reservada.

## 2. Calendario de retencao

1. Abra "Retencao e Expurgo".
2. Confirme que ve, por categoria, o prazo (em anos/meses), a base legal e o
   modo de expurgo (Anonimizar pelo Apagamento, Retido, ou Funcionario).
3. Confirme que o registo de acessos (portaria) e a auditoria de alteracoes
   constam como Retido, com a nota de que sao fonte das presencas ou retidos por
   dever legal.

## 3. Contagens alem do prazo

1. Confirme os tres cartoes no topo: categorias no calendario, registos alem do
   prazo e anonimizaveis por titular.
2. Por categoria, confirme a contagem de registos que ja excederam o prazo.
3. Confirme que nenhuma linha mostra dados individuais (so contagens).
4. Se uma categoria nao tiver coluna de data de aferir, deve aparecer sem data,
   e nao um zero.

## 4. Encaminhamento para o Apagamento (sem eliminacao aqui)

1. Confirme que o ecra nao tem qualquer botao de eliminar ou expurgar.
2. Confirme que o texto encaminha para o Apagamento, e que ao seguir a ligacao
   chega ao ecra de Apagamento por anonimizacao (Incr 3), onde o expurgo de um
   titular e feito com confirmacao em dois passos.

## 5. Isolamento por escola

1. Estando numa escola, confirme que as contagens sao dessa escola.
2. Sem escola activa, o ecra deve indicar que e preciso seleccionar uma escola.

## Notas

- O calendario (prazos e bases legais) e classificacao por omissao e deve ser
  revisto pela instituicao enquanto responsavel pelo tratamento. Nao e parecer
  juridico.
- Neste sistema o expurgo nao e feito por eliminacao em massa, para nao quebrar a
  integridade (presencas, retencao financeira e academica, auditoria). O expurgo
  de um titular faz-se pela anonimizacao (Apagamento).
