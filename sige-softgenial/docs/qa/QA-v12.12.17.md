# QA - v12.12.17 (Ledger incr 2: pagamentos e ancora)

Execucao real:

- php -l a todos os ficheiros alterados: sem erros (finance-core, finance-ledger, gate, smoke).
- Smoke do ledger: 19/19. Inclui: cadeia HMAC e deteccao de adulteracao/remocao/chave (incr 1), resiliencia sem tabela, e agora ancora escrita apos append, truncagem da cauda detectada pela ancora, ancora divergente detectada, e ancora ausente assinalada sem falso positivo.
- Gate do ledger: verde, reforcado (exige pagamentos instrumentados e a ancora externa: wp_upload_dir, escrita atomica com rename, integracao no escritor/verificador, deteccao de truncagem).
- Corredor completo: 56/56 verde.
- Superficie de accoes: 196 (inalterada). md5 das regras de calculo intactos.
- Zero travessoes. Raiz canonica.

## Decisoes documentadas (nao-defeitos)
- Pagamento anual coberto por delegacao em sige_fin_registar_pagamento (um unico ponto, sem duplicar).
- Persistencia da ancora: incluir uploads nos backups; ancora atrasada tolerada; perda da ancora cega so a deteccao de truncagem (a cadeia interna mantem a deteccao de modificacao e remocao no meio).
- Compromisso de ficheiros (ancora mais salts): modelo de ameaca declarado.
- Lancamentos (desenho de lote): incremento 3.
