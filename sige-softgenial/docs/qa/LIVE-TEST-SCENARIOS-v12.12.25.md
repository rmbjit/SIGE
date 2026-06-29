# Cenarios de teste live - SIGE SoftGenial v12.12.25

Fase 8 incremento 3: Apagamento por anonimizacao (direito ao apagamento).

Primeira operacao destrutiva do produto. Faca estes testes primeiro num aluno de
teste (ou numa copia), nao num aluno real, ate ganhar confianca. A anonimizacao
e permanente e nao pode ser desfeita.

## Preparacao

- Inicie sessao com um utilizador de administracao ou direccao.
- Tenha a mao um aluno de teste com numero de processo conhecido e, se possivel,
  com pagamentos e contactos registados, para ver a preservacao financeira.

## 1. Acesso e visibilidade do menu

1. No menu lateral, seccao PRIVACIDADE E DADOS, confirme que aparece "Apagamento
   de Dados".
2. Inicie sessao (noutro navegador ou perfil) como tesouraria, secretaria ou
   docente: o item nao deve aparecer e o acesso directo ao ecra deve mostrar a
   mensagem de area reservada.

## 2. Procura e pre-visualizacao de impacto

1. Abra "Apagamento de Dados". Procure o aluno de teste pelo numero de processo
   ou nome.
2. Clique em "Pre-visualizar apagamento".
3. Confirme que a pre-visualizacao lista, por tabela, os campos que serao
   redigidos (nome, contactos, documentos, morada, dados do encarregado, etc.) e
   a accao de cada um (marcador, anulado, data neutra).
4. Confirme a frase de preservacao: numero de processo, identificadores internos
   e valores financeiros e academicos sao preservados.
5. Note que esta etapa nao altera nada (e so leitura).

## 3. Confirmacao em dois passos (caminho de seguranca)

1. Na zona de perigo, deixe o campo do numero de processo vazio ou escreva um
   numero errado e clique em "Anonimizar definitivamente".
2. Confirme que volta ao ecra com o aviso de que o numero nao coincide e que
   nada foi alterado (o aluno continua com os dados intactos).

## 4. Apagamento por anonimizacao (caminho destrutivo)

1. Repita a pre-visualizacao do aluno de teste.
2. Na zona de perigo, escreva o numero de processo exacto do aluno e clique em
   "Anonimizar definitivamente".
3. Confirme a mensagem de sucesso com o numero de campos redigidos.
4. Procure o aluno de novo: o nome aparece como [apagado] e a pre-visualizacao
   indica que o aluno ja esta anonimizado.

## 5. Preservacao financeira e academica

1. Abra a area financeira do aluno anonimizado (ou um relatorio de pagamentos).
2. Confirme que os valores, saldos e historico de pagamentos se mantem
   inalterados (so os campos pessoais foram redigidos, nunca os valores).
3. Confirme que o numero de processo do aluno continua a existir e a ligar os
   registos.

## 6. Idempotencia

1. Volte a pre-visualizar e a anonimizar o mesmo aluno (ja anonimizado),
   escrevendo de novo o numero de processo.
2. Confirme que a operacao conclui sem erros e que nada muda (continua
   anonimizado).

## 7. Isolamento por escola (se gere mais de uma escola)

1. Estando numa escola, confirme que so encontra e anonimiza alunos dessa escola.
2. Um aluno de outra escola nao deve ser encontrado nem anonimizado a partir
   daqui.

## 8. Auditoria

1. Se tiver acesso ao registo de auditoria de permissoes, confirme que cada
   apagamento deixou dois registos (inicio e fim) com o utilizador, o aluno, o
   numero de campos e a data/hora.

## Notas

- A anonimizacao e permanente. Nao ha desfazer; a salvaguarda e a confirmacao em
  dois passos.
- O apagamento e por anonimizacao, nao por eliminacao: os registos continuam a
  existir para integridade e retencao, apenas sem os dados pessoais.
- As finalidades e bases legais mostradas vem do catalogo da Incr 1 e sao uma
  classificacao por omissao a rever pela instituicao. Nao e parecer juridico.
