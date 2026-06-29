# Revisao adversarial - v12.12.44 - Fase 3 - Ajuste fino dos codigos e estados por operadora

Rediagnostico adversarial do ajuste fino final da reconciliacao. O exercicio assume a postura de um revisor hostil e procura partir cada criterio antes de o declarar pronto.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. Contaminar uma operadora com a configuracao de outra. O mapa e por provider e os filtros recebem o provider como contexto; o smoke confirma que um codigo (INS-700) e um estado (recusada_movitel) definidos para o e-Mola sao falhada no e-Mola mas desconhecida no M-Pesa.
2. Tomar por confirmada uma transacao que falhou. O estado da transacao continua a ter prioridade; um estado de falha da operadora e falhada, mesmo com a consulta a devolver INS-0. O smoke confirma.
3. Acusar uma transacao real de falsa por um estado desconhecido. Um estado nao mapeado fica desconhecida; o smoke confirma com Pending.
4. Partir o comportamento anterior. Sem provider, o mapa devolve os defaults comuns; os casos anteriores do smoke continuam verdes. Filtros antigos de um argumento continuam a funcionar (os argumentos extra sao ignorados).
5. Introduzir uma opcao ou dependencia nova pela afinacao. A afinacao e por filtro; o extractor confirma 132 opcoes e 11 dependencias, superficie 199 e vistas 60.
6. Afirmar uma falha de M-Pesa por um codigo que so deveria ser de e-Mola. O codigo so e falha se constar da lista da propria operadora; o smoke confirma o isolamento.
7. Inventar valores nao fundamentados. Os defaults seguem a documentacao publica do M-Pesa (estado em ResponseTransactionStatus; INS-0 confirma apenas a consulta) e mantem o e-Mola conservador ate a doc da Movitel; o ajuste fino definitivo fica explicitamente dependente do sandbox.
8. Partir os incrementos anteriores. Os gates de resolucao com quatro-olhos, deteccao de duplicados e reconciliacao continuam verdes.

## P0

Nenhum. Apenas afina a classificacao da verificacao (so leitura); nao toca em regras de calculo nem em ficheiros canonicos.

## P1

Nenhum. A verificacao fica mais exacta por operadora e o isolamento impede contaminacao entre operadoras.

## P2 e P3

Nenhum novo. O risco de acusar uma transacao real de falsa fica eliminado pelo conservadorismo; a mudanca e retro-compativel.

## Decisao

Aprovado. Zero P0 e zero P1. O ajuste fino por operadora esta completo e provado por gate e smoke actualizados (corredor 95/95): mapa por provider, normalizador e adaptador conscientes do provider, filtros com o provider como contexto e isolamento entre operadoras, defaults fundamentados na documentacao publica, conservadorismo e retro-compatibilidade preservados. Aditivo, sem tocar em regras de calculo nem em ficheiros canonicos, sem nova superficie, sem nova vista, sem opcoes novas, sem alteracao de esquema, calculo byte-identico. Os gates anteriores continuam verdes. Release gate verde a partir de pasta limpa. Pronto para entrega. O ajuste fino definitivo dos valores exactos faz-se com o sandbox de cada operadora; a estrutura ja esta pronta para os receber.
