# QA - v12.12.19 (Ledger incr 4: despesas, creditos, fechos)

Execucao real:

- php -l a todos os ficheiros alterados: sem erros (despesas view, despesa state machine, finance-core, fecho de turno, gate, smoke).
- Smoke do ledger: 27/27. Inclui (incr 1 a 3) cadeia HMAC, adulteracao/remocao/chave, resiliencia, pagamentos, ancora (truncagem/divergente/ausente), escrita em bloco, buffer diferido e cadeia mista; e agora os 5 eventos de despesas, creditos e fechos numa cadeia integra.
- Gate do ledger: verde, reforcado (exige os 5 eventos de despesa, credito e fecho instrumentados).
- Corredor completo: 56/56 verde.
- Superficie de accoes: 196 (inalterada). md5 das regras de calculo intactos.
- Zero travessoes. Raiz canonica.

## Decisoes documentadas (nao-defeitos)
- Consumo/aplicacao de credito e edicao de valor de despesa: inline, sem ponto unico limpo; adiados como refinamento posterior. A criacao (que fixa o valor) fica coberta.
- ids (despesa, credito, fecho) capturados antes do append para nao serem sobrepostos pelo insert do ledger.
