# AUTHORIZATION DEBT REGISTER - v12.12.21

## current_user_can
Divida legada de current_user_can inalterada (475). Os guards de tenant nao introduzem current_user_can; sao ortogonais a autorizacao e correm depois da verificacao de permissao existente.

## sige_can
sige_can inalterado (60). Nenhuma verificacao de permissao foi alterada.

## baseline
Baseline em AUTHORIZATION_DEBT_BASELINE-v12.12.11.json (sige_can 60, current_user_can 475), igual a v12.12.8.

## v12.12.13 (painel de controlo de seguranca MFA)

- Sem nova divida de autorizacao. O acesso ao painel usa o gate canonico sige_is_real_wp_admin_user (so administrator/super admin real), e nao um perfil ou capacidade SIGE. O filtro existente que retira capacidades tecnicas aos perfis SIGE continua a aplicar-se.
- O endpoint admin_post:sige_mfa_settings_save tem legacy_caps apenas ['administrator'] (sem perfis SIGE) e o handler recusa qualquer nao-super-admin com wp_die 403.

## v12.12.14 (Secret Vault, incremento 1)

- Sem nova divida de autorizacao. O cofre e uma camada interna de cifra; nao altera quem pode configurar os pagamentos nem introduz endpoint. As permissoes de configuracao dos gateways mantem-se como estavam.

## v12.12.15 (Ledger financeiro, incremento 1)

- Sem nova divida de autorizacao. O escritor do ledger e interno (chamado pelas operacoes ja autorizadas e protegidas por MFA). O ecra de integridade usa o gate canonico sige_is_real_wp_admin_user (so super admin), sem endpoint novo.

## v12.12.16 (patch correctivo do Ledger)

- Sem alteracao de autorizacao. O ecra continua restrito ao super admin (sige_is_real_wp_admin_user). Sem endpoint novo.

## Actualizacao v12.12.21 (Fase 7 incremento 2)

O novo endpoint de decisao usa sige_can (financeiro.estornar / financeiro.caixa_reabrir) e nao introduz divida de autorizacao. A baseline de current_user_can mantem-se; nenhum novo current_user_can sem cobertura foi adicionado.
