# QA - v12.12.18 (Ledger incr 3: lancamentos)

Execucao real:

- php -l a todos os ficheiros alterados: sem erros (finance-core, finance-ledger, gate, smoke).
- Smoke do ledger: 25/25. Inclui (incr 1 e 2) cadeia HMAC, adulteracao/remocao/chave, resiliencia, pagamentos e ancora (truncagem da cauda, divergente, ausente); e agora escrita em bloco (encadeia ao genesis e entre si), buffer diferido (acumula sem gravar; grava no flush) e cadeia mista imediato mais diferido.
- Gate do ledger: verde, reforcado (exige lancamentos instrumentados, a escrita em bloco, o flush no shutdown, e um unico bloqueio e ancora por bloco).
- Corredor completo: 56/56 verde.
- Superficie de accoes: 196 (inalterada; o shutdown nao e superficie rastreada). md5 das regras de calculo intactos.
- Zero travessoes. Raiz canonica.

## Decisoes documentadas (nao-defeitos)
- Escrita diferida no shutdown para a geracao em massa: um bloqueio e uma ancora por escola e por pedido, mantendo a granularidade por cobranca.
- Limite da escrita diferida: falha catastrofica antes do shutdown perde o buffer do pedido (cobrancas tambem podem ficar incompletas; gerador idempotente); pagamentos e ajustes sao imediatos.
- Despesas, creditos e fechos: incremento seguinte.
