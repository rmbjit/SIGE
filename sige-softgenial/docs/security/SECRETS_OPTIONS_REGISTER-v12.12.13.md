# SECRETS OPTIONS REGISTER - v12.12.13

## segredo
Nenhum segredo novo na v12.12.11 (Tenant Write Isolation). Os guards de escrita e o helper sige_tenant_write_guard nao manipulam tokens nem credenciais.

## opcoes
Nenhuma opcao sensivel nova. Baseline em SECRETS_OPTIONS_BASELINE-v12.12.11.json, igual a v12.12.8.

## Secret Vault
Fase Secret Vault universal continua planeada. Sem impacto neste incremento.

## v12.12.11 (incremento TOTP)

- Novo segredo: TOTP por utilizador. Onde: user meta _sige_mfa_totp_secret (cifrado com sige_encrypt_token, sodium secretbox autenticado). Flag de confirmacao separada: _sige_mfa_totp_confirmed.
- O segredo nunca e persistido em claro; so e exibido em texto no ecra de inscricao para introducao manual na aplicacao autenticadora.
- Candidato natural ao Secret Vault (Fase 5), a par dos restantes segredos operacionais.
- opcoes WordPress: sem novas opcoes globais introduzidas por este incremento.

## v12.12.12 (reposicao automatica)

- Nenhum segredo novo. A reposicao usa um descritor efemero (transient por utilizador, TTL curto) que contem contexto e argumentos da operacao, nunca segredos.
- Nova opcao de controlo: sige_mfa_autoreplay (on/off, defeito off). Nao e um segredo; e um interruptor operacional. Kill-switch por constante SIGE_MFA_AUTOREPLAY_OFF. Sem relacao com o Secret Vault (Fase 5).

## v12.12.13 (painel de controlo de seguranca MFA)

- Nenhum segredo novo. O painel le e grava opcoes ja existentes (sige_mfa_stepup, sige_mfa_autoreplay, sige_mfa_stepup_strict, sige_mfa_stepup_roles), nenhuma das quais e um segredo.
- Nova opcao operacional sige_admin_hide_php_notices (on/off, defeito on): controla apenas a exibicao de avisos do PHP no wp-admin em producao. Nao e um segredo. Sem relacao com o Secret Vault (Fase 5).
