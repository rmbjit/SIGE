# Rediagnostico adversarial - v12.12.16 (patch correctivo do Ledger)

Base: v12.12.15. Alvo: a criacao da tabela do ledger e a resiliencia do ecra.
Metodo: leitura adversarial e execucao real (smoke com 14 verificacoes, incluindo a
degradacao sem tabela; gate reforcado), ambos verdes.

## Resultado

Zero defeitos P0 e zero defeitos P1. A causa-raiz (gate de schema nao subido) e a
fragilidade (erro cru sem tabela) estao corrigidas e travadas por gate.

## Analise

### A-P1 A migracao nao criava a tabela (P1) - CORRIGIDO E TRAVADO
maybe_upgrade so re-corre se SCHEMA_VERSION mudar. Subiu-se a SCHEMA_VERSION; agora
qualquer instalacao desactualizada corre a migracao e cria sige_fin_ledger
(dbDelta, idempotente). O gate passa a falhar se a SCHEMA_VERSION ficar no valor
antigo enquanto a tabela e adicionada. Sem defeito.

### A-P2 Erro cru no ecra sem tabela (P2) - CORRIGIDO E TRAVADO
sige_ledger_table_exists() (SHOW TABLES com supressao de erros) guarda o escritor,
o verificador, a lista e o ecra. Verificado por smoke: sem tabela, append devolve
false, verify devolve vazio e a lista e vazia, sem erro. O ecra mostra uma mensagem
clara. O gate exige o guard aplicado nos quatro pontos. Sem defeito.

### A-P3 Sem regressao - VERIFICADO
Nenhuma alteracao a logica do ledger, ao HMAC, as 6 operacoes, ao calculo (md5
intactos) ou a superficie (manifesto 196). dbDelta nao apaga dados. A reactivacao
continua a funcionar. Sem regressao.

### A-P4 Criacao da tabela durante o pedido - COERENTE
maybe_upgrade corre no admin_init (antes do render). O guard so memoriza o
resultado positivo (re-verifica enquanto ausente), pelo que, criada a tabela no
mesmo pedido, o ecra ja a ve. Sem defeito.

## Conclusao

Zero P0 e zero P1. A correccao resolve o erro observado e impede a sua repeticao por
gate. Pronto a entregar como v12.12.16.

## Decisao

Decisao: APROVADO para entrega. Os dois achados (gate de schema nao subido; erro cru
sem tabela) ficam corrigidos e travados por gate e por smoke. Sem regressao e sem
alteracao de superficie. Nenhum item adiado.
