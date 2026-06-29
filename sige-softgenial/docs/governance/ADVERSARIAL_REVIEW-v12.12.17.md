# Rediagnostico adversarial - v12.12.17 (pagamentos e ancora externa)

Base: v12.12.16. Alvo: a cobertura dos movimentos de dinheiro e a deteccao de
truncagem da cauda. Metodo: leitura adversarial e execucao real (smoke com 19
verificacoes, incluindo truncagem da cauda, ancora divergente e ancora ausente;
gate reforcado), ambos verdes.

## Resultado

Zero defeitos P0 e zero defeitos P1. Os pagamentos ficam imutaveis no ledger e a
truncagem da cauda passa a ser detectavel contra um atacante so com base de dados.

## Analise

### A-P1 Pagamentos no ledger (P1 candidato) - MITIGADO E VERIFICADO
O registo de pagamento alimenta o ledger apos a transacao suceder. Cobre M-Pesa,
e-Mola, admin e planos, e o pagamento anual delega nesta funcao, pelo que ha um
unico ponto sem duplicacao. O pagamento_id e capturado antes do append. Sem defeito.

### A-P2 Truncagem da cauda (P2 do incremento 1) - FECHADO E VERIFICADO
A ancora externa regista a cabeca real da cadeia fora da base de dados. Apagar as
entradas mais recentes deixa a base de dados atras da ancora, e a verificacao
assinala truncagem da cauda na seq seguinte. Verificado por smoke. Limite que ficou
honesto no incremento 1, agora fechado contra o atacante so com base de dados.

### A-P3 Adulteracao da cabeca via ancora - VERIFICADO
Se a cabeca da base de dados for trocada, o hash deixa de bater com a ancora e a
verificacao assinala divergencia. Verificado.

### A-P4 Ancora ausente (falso positivo?) - TRATADO
Ancora ausente e um estado distinto, nao fatal (a cadeia interna continua a ser
verificada por HMAC). Nao gera falso positivo de adulteracao. Verificado.

### A-P5 Exposicao web da ancora - DECISAO: MITIGADO
index.php e .htaccess a negar acesso; e o conteudo (seq mais hash) nao e segredo,
nao permite forjar sem a chave HMAC. Nao-defeito.

### A-P6 Persistencia da ancora - DECISAO: DOCUMENTADO
A ancora vive em uploads e deve entrar nos backups. Uma ancora atrasada (falha de
escrita pontual) e tolerada (confere o hash no ponto registado). A ancora persiste
com o ledger na remocao suave do plugin. Se a ancora se perder, a deteccao de
truncagem fica cega ate ser reconstruida, mas a cadeia interna continua a detectar
modificacao e remocao no meio. Nao-defeito; melhoria real face ao incremento 1.

### A-P7 Compromisso de ficheiros (ancora mais salts) - MODELO DECLARADO
Um atacante com acesso aos ficheiros pode adulterar a ancora e ler os salts. E a
brecha mais ampla ja declarada, fora do ambito da tamper-evidence ao nivel da base
de dados. Documentado.

### A-P8 Nao quebrar operacoes - VERIFICADO
Pagamento e ancora registados apos sucesso, com try/catch e best-effort. Falha do
ledger ou da escrita da ancora nao altera o resultado financeiro. Sem defeito.

### A-P9 Sem tocar calculo, sem novo endpoint - VERIFICADO
md5 das regras de calculo intactos; manifesto 196 inalterado (a ancora e ficheiro,
o ecra e so leitura). Sem regressao.

### A-P10 Concorrencia da ancora - COERENTE
A escrita da ancora ocorre dentro do bloqueio por escola (GET_LOCK) e e atomica
(temp mais rename). Sem corrida. Nao-defeito.

## Decisao

Decisao: APROVADO para entrega. Pagamentos cobertos e truncagem da cauda fechada
contra o atacante so com base de dados. Lancamentos (com desenho de lote) ficam para
o incremento 3, conforme o ambito. Zero P0/P1.
