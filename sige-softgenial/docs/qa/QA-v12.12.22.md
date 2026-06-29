# QA - v12.12.22 (Correccao de roteamento da Fase 7)

## Automatico (corredor)
- Gate Views com permissoes (check-view-permission-map): alem de exigir que toda a view da allowlist tenha permissao declarada, passa a exigir o invariante de roteamento: toda a rota do mapa de despacho TEM de constar da allowlist. Reporta agora as rotas de despacho cobertas. Verde. Provado por teste negativo (remover os slugs faz o gate falhar com "rota morta, cai no painel"; repor volta a verde).
- Gate Aprovacoes (check-aprovacoes): mantem as 14 verificacoes anteriores e acrescenta a verificacao explicita de que financeiro-aprovacoes consta da allowlist E da matriz de permissoes. Verde.
- Gate Reconciliacao (check-reconciliacao): mantem as verificacoes anteriores e acrescenta a verificacao explicita de que financeiro-reconciliacao consta da allowlist E da matriz. Verde.
- Corredor completo: 60 gates verdes. Manifesto 197. Regras do Kernel 197 (enforce 31, observe 149, delegated 17). Views na allowlist e na matriz: 56 (as duas views da Fase 7 agora cobertas). Design sem regressao; colisoes CSS limpas.
- Higiene: raiz com 7 ficheiros canonicos; lint a todo o PHP sem erros; zero travessoes em codigo e em documentos; tres funcoes de calculo byte-identicas a v12.12.21.

## Manual (live) - ver docs/qa/LIVE-TEST-SCENARIOS-v12.12.22.md
1. Como utilizador com permissao de pagamentos moveis, abrir "Reconciliacao": deve abrir o relatorio de divergencias (antes caia no painel).
2. Como utilizador com permissao de estorno ou de reabertura, abrir "Aprovacoes": deve abrir a lista de pedidos (antes caia no painel).
3. Como utilizador sem essas permissoes, abrir cada uma: deve ver a mensagem de area reservada (e nao o painel inicial).
4. Confirmar que as restantes paginas financeiras continuam a abrir normalmente (sem regressao).
5. Confirmar que o menu financeiro mostra as duas entradas e que a pagina activa fica assinalada.

## Limites assumidos
- A correccao e de roteamento na shell de administracao; nao altera a logica nem os dados das duas paginas.
- O acesso por papel (Direccao, Secretaria Geral) na Reconciliacao mantem-se identico ao da pagina M-Pesa, que ja operava com a mesma matriz e guarda.
