# Rediagnostico adversarial - v12.12.18 (lancamentos no ledger, escrita em lote)

Base: v12.12.17. Alvo: a cobertura da criacao e alteracao de cobrancas e a escrita
em lote. Metodo: leitura adversarial e execucao real (smoke com 25 verificacoes,
incluindo escrita em bloco, buffer diferido e cadeia mista imediato mais diferido;
gate reforcado), ambos verdes.

## Resultado

Zero defeitos P0 e zero defeitos P1. As cobrancas ficam imutaveis no ledger por
cobranca, sem penalizar a geracao em massa. O limite da escrita diferida e honesto e
fica documentado.

## Analise

### A-C1 Cobrancas no ledger (P1 candidato) - MITIGADO E VERIFICADO
A criacao e a alteracao de valor de cobranca alimentam o ledger pela funcao unica
sige_fin_upsert_lancamento. Cada cobranca fica registada individualmente (prova de
adulteracao por cobranca). Verificado por smoke. Sem defeito.

### A-C2 Desempenho da geracao em massa (P2 candidato) - RESOLVIDO POR DESENHO
A escrita em lote diferida acumula os eventos e grava uma vez por escola no shutdown,
com um unico bloqueio e uma unica escrita de ancora por escola. Evita centenas de
bloqueios e escritas de ficheiro na geracao mensal, sem perder granularidade.
Verificado por smoke (escrita em bloco e buffer). Nao-defeito.

### A-C3 Encadeamento correcto em bloco - VERIFICADO
A escrita em bloco encadeia a cadeia HMAC em memoria (cada evento sobre o anterior) e
escreve a ancora uma vez no fim. A cadeia resultante verifica como integra e a ancora
confirma. Verificado por smoke (encadeia ao genesis e entre si). Sem defeito.

### A-C4 Limite da escrita diferida (P2) - HONESTO E DOCUMENTADO
Os eventos de cobranca sao gravados no fim do pedido. Uma falha catastrofica antes do
shutdown perde os registos de cobranca em buffer desse pedido. As proprias cobrancas
tambem podem ficar incompletas (a geracao nao e atomica entre alunos), e o gerador e
idempotente. Pagamentos e ajustes sao imediatos e nao tem este risco. Documentado;
nao e defeito desta entrega.

### A-C5 Mistura imediato e diferido - VERIFICADO
Um evento imediato (pagamento ou ajuste) e um evento diferido (cobranca) no mesmo
pedido continuam a mesma cadeia, sem corromper a sequencia. Verificado por smoke.

### A-C6 Nao quebrar operacoes nem o gerador - VERIFICADO
A gravacao diferida e best-effort; nao houve cirurgia no gerador. Falha do ledger nao
altera o resultado financeiro. md5 das regras de calculo intactos. Sem regressao.

### A-C7 Sem novo endpoint - COERENTE
O shutdown nao e superficie rastreada; o ecra e a ancora nao mudaram. Manifesto 196
inalterado; regras inalteradas.

## Decisao

Decisao: APROVADO para entrega. Cobrancas cobertas por cobranca, geracao em massa sem
penalizacao, limite da escrita diferida documentado. Despesas, creditos e fechos
ficam para o incremento seguinte. Zero P0/P1.
