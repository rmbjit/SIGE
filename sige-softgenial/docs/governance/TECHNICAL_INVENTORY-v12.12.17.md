# TECHNICAL_INVENTORY - v12.12.17 - MFA de Operacao (Step-up)

Inventario tecnico do incremento 1 da Fase 4. Base: v12.12.11.

## Superficie

A superficie de accoes do manifesto cresce de 193 para 194 com uma unica accao nova: admin_post:sige_mfa_confirm (endpoint de confirmacao do codigo). O estado do resultado e conduzido por transient, pelo que nao se introduz qualquer query_handler novo (sem leitura de $_GET). As 10 operacoes criticas protegidas ja existiam na superficie; passam a invocar o guard de step-up antes de executar.

## Views

Os pontos de operacao critica em camada de view sao os 2 handlers inline de caixa em admin/finance/financeiro-extratos.php (reabrir e fechar), que renderizam o formulario de confirmacao inline quando o step-up e exigido. As restantes operacoes passam pela camada de servico (AJAX) ou por handlers admin_post.

## Permissoes

O endpoint sige_mfa_confirm exige apenas sessao iniciada (is_user_logged_in) e nonce sige_mfa_confirm; na pratica so o atingem utilizadores dos perfis criticos com desafio pendente. Os perfis abrangidos pelo step-up sao configuraveis (option sige_mfa_stepup_roles, defeito sige_director e sige_admin_ti). A matriz de permissoes existente nao e alterada (current_user_can=475, sige_can=60, inalterados).

## Tenant

O step-up e ortogonal ao isolamento de tenant da Fase 3. O guard corre depois do guard de tenant em cada metodo de servico (a operacao so chega ao step-up se o contexto de escola for valido). A janela de verificacao e por utilizador (transient sige_mfa_ok_{user_id}), nao por escola. Baseline de fallbacks de tenant: 0 (inalterado); baselines congelados 138/139 intactos.

## Segredos

Nao ha segredos novos. As primitivas OTP guardam apenas o hash SHA-256 do codigo em transient, com TTL e maximo de tentativas. O codigo em claro existe so no email enviado e nunca e persistido. A opcao sige_mfa_stepup (on/off) e de configuracao, nao um segredo. O Secret Vault universal continua na Fase 5.

## Dependencias

Sem dependencias externas novas. A entrega do codigo usa wp_mail (o mesmo canal do 2FA de login). Falha de SMTP e tratada com anti-lockout (a operacao e permitida nessa tentativa, com registo). Sem migracao de base de dados.

## Security Kernel

Acrescenta-se a regra admin_post:sige_mfa_confirm em modo observe (risco high, intent nonce sige_mfa_confirm, auditoria activa, rate limit sk_admin_post_sige_mfa_confirm 20/300). O conjunto enforce mantem-se nas mesmas 30 operacoes da Fase 3. Total de regras: 194 (enforce 30, delegated 17, observe 147). O JSON de regras e gerado a partir das regras PHP, garantindo ids e ordem identicos.

## Nota de correccao (v12.12.11)

O modulo passa a 13 primitivas (juntou-se sige_mfa_stepup_strict). O formulario de confirmacao e agora uniforme: os handlers renderizam-no inline em pedidos POST e o aviso renderiza-o em pedidos GET (com a condicao REQUEST_METHOD diferente de POST para nao duplicar). O guard audita satisfied_window em cada operacao autorizada pela janela. Sem novos endpoints (manifesto mantem-se em 194). O gate de release passa a verificar em/en-dashes em documentos (.md/.txt), nao apenas em codigo.

## v12.12.11 (incremento TOTP)

- Superficie de accoes: 195 itens (mais um, admin_post:sige_mfa_totp_enroll). Manifesto regenerado.
- Modulo novo: includes/security-mfa-totp.php (nucleo RFC 6238, armazenamento cifrado, codificador QR proprio em SVG, pagina de gestao e endpoint de inscricao).
- Permissoes: o endpoint espelha a regra de step-up (perfis criticos sige_director, sige_admin_ti); cada utilizador so altera a sua conta.
- Tenant: sem novos sumidouros de escrita tenant; o segredo e por utilizador (user meta), nao por escola.
- Segredos: novo segredo TOTP por utilizador, cifrado com sige_encrypt_token (sodium secretbox). Ver registo de segredos.
- Dependencias: nenhuma dependencia externa nova; QR e calculado no servidor, sem JS nem CDN.
- Security Kernel: regra admin_post:sige_mfa_totp_enroll em observe; enforce mantem-se em 30.

## v12.12.12 (reposicao automatica)

- Superficie de accoes: 195 itens (inalterada; a reposicao reutiliza admin_post:sige_mfa_confirm, nao adiciona endpoint).
- Modulo novo: includes/security-mfa-replay.php (captura de descritor de uso unico, registo das 6 operacoes, consumo atomico e re-execucao).
- Permissoes: re-validadas na reposicao por passar pelo mesmo metodo de servico (self::userCan). Sem nova divida de autorizacao.
- Tenant: re-validado na reposicao (sige_tenant_write_guard, primeira linha de cada metodo). Sem novos sumidouros de escrita.
- Segredos: nenhum segredo novo.
- Dependencias: nenhuma dependencia externa nova.
- Security Kernel: regras inalteradas; enforce mantem-se em 30.

## v12.12.13 (painel de controlo de seguranca MFA)

- Superficie de accoes: 196 itens (195 -> 196; novo endpoint admin_post:sige_mfa_settings_save).
- Modulo novo: includes/security-mfa-settings.php (ecra de definicoes + handler governado + supressao de display de avisos em producao).
- Acesso: gate canonico sige_is_real_wp_admin_user (so administrator/super admin real). Sem nova divida de autorizacao; reutiliza os helpers e o filtro de hardening existentes.
- Tenant: nao aplicavel (controlos globais de seguranca; modulo sistema, tenant_required false).
- Segredos: nenhum segredo novo. Nova opcao operacional sige_admin_hide_php_notices (display de avisos em producao).
- Dependencias: nenhuma dependencia externa nova.
- Security Kernel: regra nova em observe; enforce mantem-se em 30; total 196.

## v12.12.14 (Secret Vault, incremento 1)

- Superficie de accoes: 196 itens (inalterada; sem endpoint novo).
- Modulo novo: includes/security-vault.php (sige_vault_seal/reveal/is_sealed, registo de segredos). Camada sobre sige_encrypt_token/decrypt.
- Alterado: includes/payments/mobile-tenant-options.php (sela na gravacao, revela na leitura com auto-reparacao no admin; webhook revela antes do hash_equals).
- Segredos cifrados em repouso nesta fase: M-Pesa (api_key, public_key), e-Mola (api_key, api_secret), webhook_token. Ja cifrados e catalogados: SMTP, WhatsApp.
- Tenant: sem alteracao ao modelo; as opcoes de pagamento mantem o scoping por escola.
- Segredos: nenhum segredo novo; nenhuma opcao operacional nova.
- Security Kernel: regras inalteradas (196; enforce 30).

## v12.12.15 (Ledger financeiro, incremento 1)

- Superficie de accoes: 196 itens (inalterada; ecra so de leitura, sem endpoint).
- Modulo novo: includes/finance-ledger.php (sige_ledger_append/verify, HMAC dos salts, ecra de integridade so super admin).
- Tabela nova: sige_fin_ledger (append-only, chave unica escola_id+seq), na migracao.
- Alterado: includes/fin-action-service.php (6 operacoes criticas instrumentadas apos sucesso; insert_id capturado antes no bloquearMes).
- Calculo: nenhum toque; md5 de sige_fin_saldo_lancamento, sige_fin_saldo_sql, sige_fin_total_bruto_sql intactos.
- Security Kernel: regras inalteradas (196; enforce 30).

## v12.12.16 (patch correctivo do Ledger)

- Alterado: includes/class-sige-migration.php (SCHEMA_VERSION 20260611.1 -> 20260620.1, para a migracao re-correr e criar a tabela do ledger).
- Alterado: includes/finance-ledger.php (sige_ledger_table_exists() com SHOW TABLES e supressao de erros; guards no escritor, verificador, lista e ecra; mensagem clara no ecra sem tabela).
- Reforcado: tools/check-ledger.php (exige SCHEMA_VERSION subida e guard de existencia) e tools/smoke-ledger-v12-12-15.php (teste de resiliencia sem tabela).
- Superficie de accoes: 196 (inalterada). Calculo: md5 intactos.

## v12.12.17 (Ledger incr 2: pagamentos e ancora)

- Alterado: includes/finance-core.php (sige_fin_registar_pagamento instrumentado no ledger apos sucesso; cobre o anual por delegacao).
- Alterado: includes/finance-ledger.php (ancora externa: sige_ledger_anchor_dir/ensure_dir/path/read/write; escrita da ancora no append; verificacao da ancora contra truncagem da cauda; coluna de ancora no ecra).
- Reforcado: tools/check-ledger.php (exige pagamentos instrumentados e a ancora externa) e tools/smoke-ledger-v12-12-15.php (truncagem da cauda, ancora divergente, ancora ausente).
- Ancora: wp-content/uploads/sige-private/ledger/anchor-{escola}.json, protegida por index.php e .htaccess.
- Superficie: 196 (inalterada). Calculo: md5 intactos.
