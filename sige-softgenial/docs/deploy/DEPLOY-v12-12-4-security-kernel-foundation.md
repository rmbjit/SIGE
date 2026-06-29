# Deploy v12.12.4 - Security Kernel Foundation

Esta versao nao altera schema. O principal risco operacional e bloqueio indevido nas 4 superficies piloto caso nonce, tenant ou permissoes estejam desalinhados.

## Validar em staging
- Guardar configuracao M-Pesa.
- Guardar configuracao e-Mola.
- Imprimir comprovativo/relatorio de despesas com nonce valido.
- Guardar configuracoes no Centro de Configuracao.
- Confirmar que webhooks REST continuam operacionais, pois permanecem em observe.
