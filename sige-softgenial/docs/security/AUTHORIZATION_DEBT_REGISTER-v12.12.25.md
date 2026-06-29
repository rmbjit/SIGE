# AUTHORIZATION DEBT REGISTER - v12.12.25

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

## Actualizacao v12.12.22 (release correctiva)

Esta versao e uma correccao de roteamento da Fase 7, construida sobre a v12.12.21. As views financeiro-reconciliacao (Incremento 1) e financeiro-aprovacoes (Incremento 2, regra de quatro-olhos), entregues na Fase 7 mas em falta na allowlist anti-LFI e na matriz de permissoes do admin-shell, ficavam inalcancaveis: a guarda reescrevia o pedido para o painel inicial. Foram repostas em ambas as listas, com as permissoes identicas as guardas internas de cada view. Nao ha alteracao da superficie de accao (manifesto e regras do Security Kernel mantem-se em 197), nao ha migracao de dados (SCHEMA_VERSION inalterada) e as regras de calculo financeiro nao sao tocadas.

Os totais de current_user_can e de sige_can mantem-se face a baseline. As duas views repostas passam a ter permissao declarada na matriz (via sige_can: reconciliacao com financeiro.mobile_payments_gerir; aprovacoes com financeiro.estornar ou financeiro.caixa_reabrir), o que reduz a divida de autorizacao em vez de a aumentar.

## Actualizacao v12.12.24 (Fase 8 Incr 1)

O novo ecra de inventario de dados pessoais e governado por sige_can('privacidade.inventario_ver'), com recurso a verificacao de administrador real apenas como salvaguarda. Nao introduz nova divida de current_user_can: a baseline de autorizacao mantem-se. A guarda fail-closed: sem a permissao, a area mostra aviso de acesso reservado e nao consulta dados.

## Actualizacao v12.12.24 - Fase 8 incremento 2

- O novo ecra e o endpoint de exportacao autorizam por sige_can('privacidade.acesso_exportar') (com fallback explicito para administrador real do WordPress), nao por current_user_can de capacidade nativa. Nao acrescentam divida de autorizacao.
- baseline de divida de autorizacao inalterado face a v12.12.23.


## Actualizacao v12.12.25 - Fase 8 incremento 3 (apagamento por anonimizacao)

Este incremento acrescenta a primeira operacao destrutiva do produto: o apagamento por anonimizacao (direito ao apagamento). Foi adicionado um endpoint admin_post governado em modo enforce e risco critico (admin_post:sige_privacidade_apagar), com confirmacao em dois passos por numero de processo, nonce, rate limit (5/300s), isolamento por escola e auditoria antes e depois. A superficie de accao passou de 198 para 199 e o enforce de 32 para 33. Nova permissao critica privacidade.apagamento_executar, semeada so a administracao e direccao e sempre auditada. Sem eliminacao fisica de linhas e sem migracao de esquema (SCHEMA_VERSION inalterada). Manifesto e Kernel mantem-se alinhados (199 == 199).
