# QA - v12.12.20 (Fase 7 incr 1: reconciliacao e divergencias)

## Automatico (corredor)
- Gate Reconciliacao: helper so leitura (sem INSERT/UPDATE/DELETE), tres classes de divergencia + totais, fail-closed por escola, usa a tabela unica; view com guarda de acesso e sem POST/escrita; rota e link registados; helper carregado. Verde.
- Smoke Reconciliacao: 12 verificacoes (2 nao conciliadas, 1 divergencia de montante gateway 1000 vs pagamento 900, 1 pagamento sem gateway, totais recebido 1800/conciliado 1000/limbo 800/moveis 1250/diferenca -250, contadores, fail-closed). Verde.
- Corredor completo: 58 gates verdes. Manifesto 196. Regras do Kernel 196 (enforce 30). Design: sem regressao (tokens 2414, consistencia 7497). Colisoes CSS limpas.
- Higiene: raiz com 7 ficheiros canonicos; lint a todo o PHP sem erros; zero travessoes; md5 de calculo intactos.

## Manual (live) - ver docs/qa/LIVE-TEST-SCENARIOS-v12.12.20.md
1. Abrir o menu financeiro e confirmar a entrada "Reconciliacao".
2. Confirmar os totais e as tres seccoes de divergencia.
3. Confirmar que o ecra e so de leitura (sem botoes de accao).

## Limites assumidos
- O relatorio expoe; nao resolve. A correccao continua pelos fluxos existentes.
- Regra de quatro-olhos adiada para o incremento 2.
