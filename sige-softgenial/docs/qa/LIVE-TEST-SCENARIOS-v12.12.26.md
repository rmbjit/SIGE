# Cenarios de teste live - SIGE SoftGenial v12.12.26

Fase 8 incremento 3.2: Completar o catalogo de PII (fechar as lacunas).

Este incremento e de classificacao: nao acrescenta ecras novos, mas completa o
que os ecras de Inventario, Acesso e Apagamento ja faziam.

## 1. Inventario de Dados (as lacunas fecham)

1. Abra Privacidade e Dados > Inventario de Dados.
2. Confirme que o cartao CAMPOS CATALOGADOS passa a 88 e PRESENTES NO ESQUEMA a 88.
3. Confirme que o cartao LACUNAS (PII SEM CLASSIFICAR) passa a 0 e que a caixa
   amarela de lacunas deixa de listar colunas.
4. Confirme que DESVIOS (CATALOGO VS ESQUEMA) se mantem a 0 e CAMPOS SENSIVEIS
   passa a 24.

## 2. Acesso e Portabilidade (o dossie fica completo)

1. Abra Privacidade e Dados > Acesso e Portabilidade e procure um aluno com
   fotografia, documento digitalizado e contactos de emergencia preenchidos.
2. Confirme que o dossie passa a incluir esses campos (foto, documento de
   identidade digitalizado, contactos de emergencia, dados da pessoa autorizada
   a buscar o aluno, notas do encarregado).
3. Exporte o JSON e confirme que esses campos constam do ficheiro.

## 3. Apagamento de Dados (o buraco fecha)

1. Abra Privacidade e Dados > Apagamento de Dados e pre-visualize um aluno de
   teste com esses campos preenchidos.
2. Confirme que a pre-visualizacao passa a listar tambem foto, documento de
   identidade digitalizado, contactos de emergencia e dados da pessoa autorizada
   a buscar como campos a redigir.
3. Confirme que as datas de cobranca (data do contacto, proximo contacto) NAO
   aparecem como a redigir (sao preservadas, registo operacional).
4. Se quiser confirmar o efeito, anonimize um aluno de teste (escrevendo o numero
   de processo) e verifique depois que a fotografia, o documento e os contactos
   de emergencia ficaram redigidos, e que os valores e datas de cobranca se
   mantiveram.

## Notas

- A classificacao das finalidades e bases legais e por omissao e deve ser revista
  pela instituicao enquanto responsavel pelo tratamento. Nao e parecer juridico.
- As notas de funcionario (sige_professores) ficam classificadas mas so serao
  apagadas no futuro incremento do titular funcionario.
