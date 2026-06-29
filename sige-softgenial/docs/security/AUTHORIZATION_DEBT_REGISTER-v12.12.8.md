# AUTHORIZATION DEBT REGISTER - v12.12.8

## current_user_can
A divida legada de `current_user_can` permanece registada em baseline JSON e nao foi aumentada nesta versao (continua em 475). O incremento de tenant nao introduz `current_user_can` como autorizacao primaria; apenas substitui resolucao de escola por um resolvedor fail-closed.

## sige_can
A contagem de `sige_can` mantem-se em 60. Nenhuma verificacao de permissao foi alterada neste incremento. O endurecimento e ortogonal a autorizacao: garante que a escrita so prossegue com escola valida, depois de a permissao ja ter sido verificada pelo handler ou pela pagina.

## baseline
Baseline de autorizacao em `AUTHORIZATION_DEBT_BASELINE-v12.12.8.json` (sige_can 60, current_user_can 475), igual a v12.12.7.1. A divida remanescente sera reduzida em fases posteriores.
