# SECRETS OPTIONS REGISTER - v12.12.12

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
