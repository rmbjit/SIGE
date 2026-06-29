# AUTHORIZATION DEBT REGISTER - v12.12.12

## current_user_can
Divida legada de current_user_can inalterada (475). Os guards de tenant nao introduzem current_user_can; sao ortogonais a autorizacao e correm depois da verificacao de permissao existente.

## sige_can
sige_can inalterado (60). Nenhuma verificacao de permissao foi alterada.

## baseline
Baseline em AUTHORIZATION_DEBT_BASELINE-v12.12.11.json (sige_can 60, current_user_can 475), igual a v12.12.8.
