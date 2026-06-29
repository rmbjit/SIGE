# AUTHORIZATION DEBT REGISTER - v12.12.7.1

## current_user_can
A divida legada de `current_user_can` permanece registada em baseline JSON e nao foi aumentada nesta versao. O novo handler de cobranca nao introduz `current_user_can` como autorizacao primaria.

## sige_can
O documento de cobranca autoriza por `sige_can` (financeiro.cobrancas_ver ou financeiro.cobrancas_gerir), com fallback compativel para papeis WP. Usa exactamente as permissoes da pagina e mantem `sige_can` como fonte central de permissoes SIGE.

## baseline
Baseline actualizado em `AUTHORIZATION_DEBT_BASELINE-v12.12.7.1.json`. A divida remanescente sera reduzida em fases posteriores.
