# QA - v12.12.21 (Fase 7 incr 2: regra de quatro-olhos)

## Automatico (corredor)
- Gate Aprovacoes: modulo com solicitar/decidir/executar/listar; separacao de funcoes (decisor != solicitante); dois tipos (estorno_pagamento, reabertura_caixa) com as permissoes; fail-closed por escola; anti-duplicado de pendentes; execucao reutiliza estornarPagamento e a reabertura extraida; ledger nos tres momentos; reabertura executar com MFA; os dois handlers em extratos criam pedido (estornarPagamento ja nao corre inline); view com guarda + nonce + sem estilo inline; rota e nav registadas; modulo carregado; tabela e SCHEMA_VERSION; endpoint na lista do manifesto; CSS tokenizado enfileirado. Verde.
- Smoke Aprovacoes: 19 verificacoes (criar pedido, ledger solicitada, anti-duplicado, auto-aprovacao bloqueada e pedido continua pendente, aprovacao por outro executa e persiste, aprovador registado, ledger aprovada, nao redecidir, rejeicao e ledger, fail-closed, reabertura aprovada executa, MFA pendente devolvido e pedido continua pendente). Verde.
- Corredor completo: 60 gates verdes. Manifesto 197. Regras do Kernel 197 (enforce 31, observe 149, delegated 17). Design: sem regressao (tokens, consistencia visual estaveis). Colisoes CSS limpas.
- Higiene: raiz com 7 ficheiros canonicos; lint a todo o PHP sem erros; zero travessoes; md5 de calculo intactos (3 funcoes byte-identicas a v12.12.20).

## Manual (live) - ver docs/qa/LIVE-TEST-SCENARIOS-v12.12.21.md
1. Pedir um estorno: confirmar que NAO executa e que aparece em Aprovacoes como pendente.
2. Como o mesmo utilizador, tentar aprovar o proprio pedido: deve ser recusado.
3. Como segundo utilizador autorizado, aprovar: o estorno executa (com MFA) e o saldo corrige.
4. Pedir e rejeitar: confirmar que nada muda nas financas.
5. Reabertura de caixa: mesmo fluxo de pedido e aprovacao.
6. Acesso negado a quem nao detem as permissoes; isolamento por escola.

## Limites assumidos
- Pedidos pendentes nao expiram automaticamente (cron pertence a infraestrutura).
- Todos os estornos e reaberturas exigem aprovacao (sem limiar por valor).
- Numa escola com um so utilizador autorizado, a operacao fica a aguardar um segundo aprovador (comportamento pretendido do quatro-olhos).
