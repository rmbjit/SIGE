# Revisao adversarial - v12.12.43 - Fase 3 - Afinacao dos codigos do gateway e escolha de lancamento

Rediagnostico adversarial dos dois afinamentos da reconciliacao. O exercicio assume a postura de um revisor hostil e procura partir cada criterio antes de o declarar pronto.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. Tomar por confirmada uma transacao que falhou. O normalizador passa a ler o estado da transacao (output_ResponseTransactionStatus) e a classificar por ele; um INS-0 com estado Failed e falhada. O smoke confirma.
2. Acusar uma transacao real de falsa por causa de um estado desconhecido. Um estado nao mapeado fica desconhecida (inconclusiva), nunca falhada. O smoke confirma com o estado Pending.
3. Partir o comportamento anterior. Sem campo de estado, o normalizador recai na semantica do codigo de resposta, exactamente como antes; os casos antigos do smoke da reconciliacao viva continuam verdes.
4. Forcar uma falha por um codigo de resposta inesperado. A falha por codigo continua limitada a sige_mpesa_codigos_falha (vazio por omissao); um codigo desconhecido fica desconhecida.
5. Conciliar um lancamento de outro aluno ou de outra escola atraves do seletor. A funcao de execucao valida que o lancamento pertence a escola e ao aluno da transacao antes de registar; e o registo e pelo caminho canonico. O smoke da resolucao confirma a validacao e a delegacao.
6. Usar o seletor para escrever sem quatro-olhos. O seletor apenas alimenta a PROPOSTA; nada executa sem a aprovacao de um segundo utilizador (regra do framework, inalterada).
7. Inflar a superficie, criar uma vista ou uma opcao. A afinacao e por filtro (sem opcao nova) e o seletor e server-side na vista de gestao ja existente; o extractor confirma 199 accoes, 132 opcoes e 60 vistas.
8. Partir os incrementos anteriores. Os gates de deteccao de duplicados, reconciliacao e resolucao com quatro-olhos continuam verdes.

## P0

Nenhum. A escolha de lancamento usa o caminho canonico ja existente; a afinacao do normalizador nao toca em regras de calculo nem em ficheiros canonicos.

## P1

Nenhum. A afinacao torna a verificacao mais exacta (uma transacao Failed deixa de ser tomada por confirmada) e a execucao da conciliacao continua a validar escola e aluno.

## P2 e P3

Nenhum novo. O risco de acusar uma transacao real de falsa fica eliminado pelo conservadorismo; a mudanca do normalizador e retro-compativel e o seletor de lancamento e aditivo, mantendo a correspondencia automatica.

## Decisao

Aprovado. Zero P0 e zero P1. Os dois afinamentos estao completos e provados por gates e smokes actualizados (corredor 95/95): o normalizador da prioridade ao estado da transacao com listas ajustaveis e conservadorismo preservado, de forma retro-compativel, e a proposta de conciliacao com quatro-olhos passa a aceitar um lancamento escolhido, validado e registado pelo caminho canonico. Aditivo, sem tocar em regras de calculo nem em ficheiros canonicos, sem nova superficie, sem nova vista, sem opcoes novas, sem alteracao de esquema, calculo byte-identico. Os gates anteriores continuam verdes. Release gate verde a partir de pasta limpa. Pronto para entrega. O ajuste fino final dos codigos e estados especificos de cada operadora segue com o sandbox.
