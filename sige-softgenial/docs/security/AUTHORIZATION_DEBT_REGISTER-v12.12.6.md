# AUTHORIZATION DEBT REGISTER v12.12.6

A divida de autorizacao permanece registada como baseline: uso legado de `current_user_can`, uso progressivo de `sige_can` e migração futura para policy engine unica.

- `current_user_can`: baseline congelado.
- `sige_can`: baseline congelado.
- Security Kernel: piloto enforce preservado.

Esta versao nao declara migracao total; apenas impede regressao no piloto runtime.
