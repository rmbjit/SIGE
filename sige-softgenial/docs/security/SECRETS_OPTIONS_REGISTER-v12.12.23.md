# SECRETS OPTIONS REGISTER - v12.12.23

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

## v12.12.14 (Secret Vault, incremento 1) - cifra de segredos em repouso

Cofre unificado includes/security-vault.php (sige_vault_seal/reveal/is_sealed)
sobre a cifra forte existente (sige_encrypt_token: sodium secretbox, recuo
AES-256-GCM; chave dos salts do WordPress; prefixos sige2:/gcm1:).

Registo de segredos (sige_vault_secret_registry), por area:
- mpesa: api_key, public_key. Estado: CIFRADO em repouso nesta fase.
- emola: api_key, api_secret. Estado: CIFRADO em repouso nesta fase.
- payment_webhook: webhook_token (M-Pesa e e-Mola). Estado: CIFRADO em repouso nesta fase.
- smtp: password (sige_smtp_config). Estado: ja cifrado (catalogado).
- whatsapp: whatsapp_token (db-handler). Estado: ja cifrado (catalogado).

Garantias:
- Selar nunca perde o segredo (se a cifra falhar, mantem o original); idempotente.
- Revelar passa o texto em claro intacto (instalacoes pre-cofre) e so decifra os nossos formatos; nunca expoe ciphertext.
- Gravacao das credenciais dos gateways via sige_mobile_payment_update_option sela; leitura via sige_mobile_payment_get_option revela; o webhook (find_school_by_token) revela antes do hash_equals.
- Migracao sem operacao em massa: passagem de texto em claro + auto-reparacao na leitura no admin (uma vez por segredo; nunca no webhook publico).

Redaccao: os ecras de configuracao dos gateways nao pre-preenchem o segredo (campos password sem value; apenas estado configurado/nao). Nenhum segredo novo. Nova opcao operacional: nenhuma.

Adiado para incremento posterior do cofre: ferramenta de rotacao de chave e comando de re-cifragem em massa.

## v12.12.17 (Ledger incr 2: pagamentos e ancora)

- A ancora (seq + hash + contagem) nao e segredo: nao permite forjar a cadeia sem a chave HMAC (que deriva dos salts). Guardada em ficheiro protegido por index.php e .htaccess. Sem novos segredos nem opcoes sensiveis.

## Actualizacao v12.12.21 (Fase 7 incremento 2)

Sem novos segredos nem novas opcoes WordPress. O modulo de aprovacoes nao usa Secret Vault nem persiste credenciais.

## Actualizacao v12.12.22 (release correctiva)

Esta versao e uma correccao de roteamento da Fase 7, construida sobre a v12.12.21. As views financeiro-reconciliacao (Incremento 1) e financeiro-aprovacoes (Incremento 2, regra de quatro-olhos), entregues na Fase 7 mas em falta na allowlist anti-LFI e na matriz de permissoes do admin-shell, ficavam inalcancaveis: a guarda reescrevia o pedido para o painel inicial. Foram repostas em ambas as listas, com as permissoes identicas as guardas internas de cada view. Nao ha alteracao da superficie de accao (manifesto e regras do Security Kernel mantem-se em 197), nao ha migracao de dados (SCHEMA_VERSION inalterada) e as regras de calculo financeiro nao sao tocadas.

Sem alteracao de qualquer segredo nem das opcoes WordPress; o Secret Vault permanece inalterado. A correccao de roteamento nao introduz, le nem altera segredos.

## Actualizacao v12.12.23 (Fase 8 Incr 1)

Sem novos segredos. O Secret Vault nao e tocado. A unica opcao do WordPress afectada e sige_permissions_engine_version, elevada para 12.12.23 pela migracao idempotente de permissoes, que apenas regista a nova permissao e a concede aos perfis de administracao e direccao.
