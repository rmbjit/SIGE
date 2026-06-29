# Phase Charter - v12.12.19

Fase 6 (Ledger), incremento 4. Despesas, creditos e fechos de caixa no ledger.
Ultimo bloco da cobertura financeira. Base: v12.12.18. Estado: entregue.

## Objectivo

Estender o livro-razao imutavel aos restantes movimentos financeiros institucionais
(despesas, creditos e fechos de caixa), de forma que fabricar ou alterar uma despesa,
um credito ou um fecho fique detectavel, completando a cobertura financeira do ledger.

## Incluido

Cinco eventos discretos, com gravacao imediata (sige_ledger_append), reutilizando o
encadeamento HMAC e a ancora ja existentes:
- Despesa criada (fin_criar_despesa): no ponto de criacao da despesa, com categoria e valor. O despesa_id e capturado antes do registo.
- Transicao de estado de despesa (fin_despesa_transitar): em sige_fin_despesa_transitar_status, apos a transicao (aprovada, paga, cancelada), com o estado e o motivo.
- Credito criado (fin_criar_credito): em sige_fin_criar_credito_pendente, apos sucesso, com aluno e valor. O credito_id e capturado antes do registo.
- Fecho de turno (fin_fechar_turno): em fecharTurno, apos a insercao, com o total liquido (bruto e estornos no payload). O fecho_id e capturado antes do registo.
- Reabertura de turno (fin_reabrir_turno): em reabrirTurno, apos a reabertura, com o fecho e o motivo (evento sensivel).

Sem tocar no calculo (md5 intactos).

## Excluido (adiado)

- Consumo/aplicacao de credito e edicao de valor de despesa: sao inline, sem ponto unico limpo; ficam como refinamento posterior. A criacao (que fixa o valor) ja fica coberta.
- Ancora em SigeHub: posterior.

## Riscos e mitigacao

- insert_id sobreposto pelo append: despesa_id, credito_id e fecho_id sao capturados antes do registo no ledger (padrao do bloquearMes).
- Nao quebrar operacoes: todos os registos apos a operacao suceder, com guarda function_exists; o escritor e best-effort e nunca altera o resultado financeiro.
- Volume: eventos discretos (uma despesa, um credito, um fecho de cada vez); gravacao imediata, sem necessidade de lote.

## Criterios de aceitacao

1. Os 5 eventos alimentam o ledger apos sucesso, sem tocar no calculo (md5 intactos; ids capturados antes).
2. Reutilizam o escritor imediato, o encadeamento HMAC e a ancora; a cadeia continua verificavel e integra.
3. Sem endpoint novo (manifesto 196 inalterado); o ecra mostra os novos eventos.
4. Gate e smoke estendidos verdes; corredor completo verde; zero travessoes; versao sincronizada; raiz canonica.
