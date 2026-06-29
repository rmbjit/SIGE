# Phase Charter - v12.12.18

Fase 6 (Ledger), incremento 3. Lancamentos no ledger, com escrita em lote diferida.
Base: v12.12.17. Estado: entregue.

## Objectivo

Estender o livro-razao imutavel a criacao e a alteracao de valor de lancamentos
(cobrancas), de forma que fabricar ou alterar uma cobranca para manipular o que um
aluno deve fique detectavel, sem penalizar a geracao mensal em massa nem mexer no
gerador procedimental.

## Incluido

- Instrumentacao da funcao unica sige_fin_upsert_lancamento em dois pontos de sucesso: criacao (acao criado) como fin_criar_lancamento, e alteracao de valor de um lancamento existente (acao actualizado) como fin_actualizar_lancamento, com dados estruturados (aluno, servico, mes, valor). Sem tocar no calculo (md5 intactos).
- Escrita em lote diferida: os eventos de cobranca sao acumulados num buffer do pedido (sige_ledger_record_charge) e gravados de uma so vez por escola no shutdown (sige_ledger_flush_charges), via a nova escrita em bloco sige_ledger_append_many (um bloqueio e uma escrita de ancora por escola e por pedido), encadeando a cadeia HMAC em memoria e inserindo em serie. Resiliente e best-effort; sem tabela, nao grava.
- Pagamentos e as 6 operacoes criticas mantem-se com gravacao imediata (inalterados).
- Sem alteracao ao ecra nem a ancora: reutiliza a verificacao e a ancora existentes.

## Excluido (adiado)

- Despesas, creditos e fechos de caixa: incremento posterior (ultimo da cobertura financeira do ledger).
- Ancora em SigeHub: posterior.

## Riscos e mitigacao

- Limite da escrita diferida: os eventos de cobranca sao gravados no fim do pedido. Uma falha catastrofica antes do shutdown perde os registos de cobranca em buffer desse pedido (as proprias cobrancas tambem podem ficar incompletas; o gerador e idempotente e pode ser re-corrido). Pagamentos e ajustes, por serem imediatos, nao tem este risco. Documentado.
- Volume em memoria na geracao: o buffer guarda eventos pequenos e grava uma vez por escola. Aceitavel.
- Ordem na cadeia: a seq reflecte a ordem de escrita, nao o tempo do evento (o tempo real fica em ocorrido_em). Sem impacto na prova de adulteracao.
- Nao quebrar operacoes nem o gerador: gravacao diferida e best-effort, sem cirurgia no gerador.

## Criterios de aceitacao

1. sige_fin_upsert_lancamento alimenta o ledger na criacao e na alteracao de valor, sem tocar no calculo (md5 intactos).
2. Escrita em lote diferida por escola no shutdown, via sige_ledger_append_many (um bloqueio e uma ancora por escola e por pedido); encadeamento HMAC correcto em bloco; resiliente.
3. Pagamentos e as 6 operacoes criticas continuam imediatos e intactos.
4. A cadeia apos um lote verifica como integra e a ancora confirma; sem endpoint novo (manifesto 196 inalterado).
5. Gate e smoke estendidos verdes; corredor completo verde; zero travessoes; versao sincronizada; raiz canonica.
