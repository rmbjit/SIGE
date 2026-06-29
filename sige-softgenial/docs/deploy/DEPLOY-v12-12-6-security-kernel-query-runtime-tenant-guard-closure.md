# DEPLOY v12.12.6

Versao correctiva do Security Kernel. Sem alteracao de schema.

## Validar
- Centro de Configuracao (`sige_settings_save`).
- Impressao documental `sige_print`.
- Camera da portaria standalone.
- `sige_desp_print` com nonce valido.
- M-Pesa/e-Mola config.

## Rollback
Voltar para v12.12.5 caso staging detecte bloqueio indevido.
