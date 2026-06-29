# Carta da fase - v12.12.43 - Fase 3 - Afinacao dos codigos do gateway e escolha de lancamento

Dois afinamentos da Fase 3 (reconciliacao de pagamentos digitais) entregues juntos, sobre os incrementos 1 a 3. Nao introduzem nova capacidade de raiz: tornam a verificacao contra o gateway mais exacta e dao ao maker mais controlo na proposta de conciliacao.

## Objectivo

(A) Tornar a classificacao de uma transacao contra o gateway mais exacta, dando prioridade ao estado real da transacao em vez de assumir sucesso so porque a consulta correu bem. (B) Permitir que a proposta de conciliacao com quatro-olhos escolha um lancamento especifico do aluno, alem da correspondencia automatica.

## Incluido

- (A) O normalizador da reconciliacao viva (sige_pagamentos_normalizar_estado_gateway) passa a ler o estado da transacao reportado pelo gateway (o queryTransactionStatus do M-Pesa devolve-o em output_ResponseTransactionStatus) e a classificar por ele: estados de sucesso -> confirmada, estados de falha -> falhada, estado nao mapeado -> desconhecida. As listas sao ajustaveis pelos filtros sige_mpesa_estados_sucesso e sige_mpesa_estados_falha (defaults sensatos); a lista de codigos de resposta de falha continua em sige_mpesa_codigos_falha. Um INS-0 deixa de bastar para confirmar.
- (B) A vista de gestao (mpesa-view.php) carrega os lancamentos em aberto do aluno e oferece um seletor na proposta de conciliacao; o handler le o lancamento escolhido e a funcao de execucao (ja existente) valida-o e regista pelo caminho canonico. A correspondencia automatica continua disponivel (lancamento automatico).

## Excluido

- O ajuste fino final dos codigos e estados especificos de cada operadora: depende do sandbox; por isso as listas sao ajustaveis por filtro, com defaults conservadores.
- Persistencia das listas em opcao de base de dados: a afinacao e por filtro, para nao introduzir opcoes novas. Pode vir a ser exposta em ecra mais tarde.
- Qualquer alteracao a regras de calculo, a ficheiros canonicos ou ao esquema.

## Riscos

- Acusar uma transacao real de falsa. Mitigacao: conservadorismo preservado; um estado nao mapeado fica desconhecida, e a falha so e afirmada por estado ou codigo conhecido.
- Quebrar o comportamento anterior. Mitigacao: a mudanca do normalizador e retro-compativel; sem campo de estado, o comportamento e o anterior (por codigo de resposta), o que os testes dos casos antigos confirmam.
- Conciliar um lancamento errado. Mitigacao: a execucao valida que o lancamento pertence a escola e ao aluno da transacao, e regista pelo caminho canonico.

## Criterios de aceitacao

- Com o estado presente: INS-0 com Completed e confirmada; INS-0 com Failed e falhada; um estado nao mapeado (Pending) fica desconhecida.
- Sem campo de estado: o comportamento anterior por codigo de resposta mantem-se.
- A proposta de conciliacao aceita um lancamento escolhido e a execucao usa-o pelo caminho canonico, validando escola e aluno; a correspondencia automatica continua disponivel.
- Superficie de accao inalterada (199, enforce 33), sem nova vista (60), sem opcoes novas (132), dependencias externas inalteradas (11), versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 95/95 e release gate verde a partir de pasta limpa. Os gates anteriores continuam verdes. Rediagnostico adversarial Zero P0/P1.
