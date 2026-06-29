# QA - v12.12.15 (Ledger financeiro, incremento 1)

Execucao real. Resultados:

- php -l a todos os ficheiros alterados/novos: sem erros (migracao, finance-ledger, fin-action-service, bootstrap, gate, smoke).
- Smoke do ledger (tools/smoke-ledger-v12-12-15.php): 11/11 verificacoes.
  - Cadeia HMAC valida (genesis + encadeamento das 3 entradas).
  - Deteccao de adulteracao de conteudo (montante alterado e apanhado na seq exacta).
  - Deteccao de remocao no meio (salto de sequencia apanhado).
  - Deteccao de mudanca de chave (apos rotacao de salts a verificacao assinala).
  - Lista de escolas e dependencia real do HMAC face a chave.
- Gate do ledger (tools/check-ledger.php): verde. Primitivas presentes; HMAC com salts; append-only (sem UPDATE/DELETE sobre a tabela no codigo); 6 operacoes instrumentadas; tabela na migracao com chave unica; ecra so super admin; manifesto 196.
- Corredor completo (tools/run-gates.php): 56/56 verde.
- Superficie de accoes: 196 itens (inalterada; sem endpoint novo). Regras do Kernel: 196; enforce 30.
- Regras de calculo financeiro: md5 de sige_fin_saldo_lancamento, sige_fin_saldo_sql e sige_fin_total_bruto_sql intactos.
- Zero travessoes (em-dash/en-dash) em codigo e documentos.
- Raiz canonica: 7 ficheiros.

## Decisoes documentadas (nao-defeitos)
- Truncagem da cauda: limite conhecido (P2), adiado para ancoragem externa.
- Compromisso de ficheiros (salts): modelo de ameaca declarado.
- Concorrencia sob timeout de bloqueio: P3 aceite (no maximo um evento nao registado; sem corromper a cadeia).
