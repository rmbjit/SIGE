# Rediagnostico adversarial - v12.12.15 (Ledger financeiro, incremento 1)

Base: v12.12.14. Alvo: a integridade e a imutabilidade do livro-razao das
operacoes criticas. Metodo: leitura adversarial e verificacao por execucao real
(smoke com 11 verificacoes e gate proprio, ambos verdes), incluindo deteccao de
adulteracao, de remocao no meio e de mudanca de chave.

## Resultado

Zero defeitos P0 e zero defeitos P1. A cadeia deteta modificacao, e remocao ou
insercao no meio da historia, e exige a chave (salts) para ser forjada. Os limites
honestos (truncagem da cauda, compromisso de ficheiros) estao no ambito declarado
do incremento e ficam documentados; achados residuais sao decisoes documentadas.

## Modelo de ameaca e analise

### A-L1 Adulteracao de uma entrada (P1 candidato) - MITIGADO E VERIFICADO
O hash HMAC cobre o conteudo canonico. Alterar montante, motivo ou qualquer campo
faz o hash recalculado deixar de corresponder. Verificado: alterar o montante de
uma entrada e detectado na seq exacta. Sem defeito.

### A-L2 Remocao ou insercao no meio (P1 candidato) - MITIGADO E VERIFICADO
A sequencia monotonica por escola e o encadeamento prev_hash detectam saltos.
Verificado: apagar uma entrada do meio e detectado como salto de sequencia. Sem
defeito.

### A-L3 Forjar uma entrada (P1 candidato) - MITIGADO
Inserir uma entrada valida exige um HMAC valido, que precisa da chave (salts). Sem
a chave, o hash nao verifica e o encadeamento quebra. Verificado que o hash depende
mesmo da chave. Sem defeito.

### A-L4 Truncagem da cauda (P2) - LIMITE CONHECIDO, ADIADO PARA ANCORAGEM
Apagar as entradas mais recentes de forma contigua nao deixa salto de sequencia,
pelo que uma cadeia simples nao o deteta sem uma ancora externa (uma marca de agua
guardada fora da base de dados). Guardar a marca na propria base de dados nao
protege contra um atacante com acesso a base de dados. Por isso a ancoragem externa
(SigeHub, ficheiro ou email) fica para um incremento posterior, conforme o ambito
declarado. Documentado; nao e defeito desta entrega.

### A-L5 Chave a partir dos salts (compromisso de ficheiros) - MODELO DECLARADO
Um atacante que leia os salts em wp-config.php pode recalcular a cadeia. E uma
brecha mais ampla (ficheiros), fora do ambito da tamper-evidence ao nivel da base
de dados. Mesmo modelo do Secret Vault. Documentado.

### A-L6 Concorrencia sob timeout de bloqueio (P3) - DECISAO: ACEITE
GET_LOCK serializa por escola; a chave unica (escola_id, seq) impede duplicados. Se
o bloqueio expirar sob contencao extrema, no maximo um evento nao e registado, sem
criar salto nem corromper a cadeia. Retry e uma melhoria posterior. Nao-defeito.

### A-L7 O ledger nunca quebra a operacao financeira - VERIFICADO
O registo no ledger e feito apos a operacao suceder, com try/catch e guardado por
function_exists. Se a tabela faltar ou o insert falhar, a operacao financeira
mantem-se consistente e devolve ok. Sem defeito.

### A-L8 Sem tocar no calculo - VERIFICADO
A instrumentacao e apenas adicao de registo nos pontos de sucesso; os md5 das
funcoes de calculo (sige_fin_saldo_lancamento, sige_fin_saldo_sql,
sige_fin_total_bruto_sql) mantem-se. No bloquearMes, o insert_id e capturado antes
do registo no ledger para nao ser sobreposto. Sem regressao.

### A-L9 Sem novo endpoint - COERENTE
O ecra de integridade e so de leitura (sem POST); o escritor e interno. Manifesto
mantem-se em 196; regras inalteradas.

## Conclusao

Zero P0 e zero P1. A imutabilidade e a deteccao de adulteracao/remocao no meio
estao provadas em execucao real. A truncagem da cauda e um limite conhecido,
explicitamente adiado para a ancoragem externa, conforme o ambito. Pronto a entregar
como v12.12.15.
