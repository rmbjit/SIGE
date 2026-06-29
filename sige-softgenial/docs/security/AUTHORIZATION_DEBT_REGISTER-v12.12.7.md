# AUTHORIZATION DEBT REGISTER - v12.12.7

## current_user_can
A dívida legada de `current_user_can` permanece registada em baseline JSON. Esta fase não migrou todo o legado; fechou acções críticas via Kernel e permissões SIGE.

## sige_can
`sige_can` continua fonte central para permissões SIGE e agora consulta overrides tenant-scoped antes dos templates globais.

## baseline
Baseline actualizado em `AUTHORIZATION_DEBT_BASELINE-v12.12.7.json`. A dívida remanescente será reduzida em fases posteriores.
