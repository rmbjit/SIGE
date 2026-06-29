# TECHNICAL_INVENTORY - v12.12.25 - MFA de Operacao (Step-up)

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

## v12.12.18 (Ledger incr 3: lancamentos)

- Alterado: includes/finance-core.php (sige_fin_upsert_lancamento instrumentado em criacao e alteracao de valor, via gravacao diferida).
- Alterado: includes/finance-ledger.php (escrita em bloco sige_ledger_append_many; buffer sige_ledger_record_charge; flush no shutdown sige_ledger_flush_charges).
- Reforcado: tools/check-ledger.php (exige lancamentos instrumentados e a escrita em bloco diferida) e tools/smoke-ledger-v12-12-15.php (escrita em bloco, buffer e cadeia mista).
- Superficie: 196 (inalterada; o shutdown nao e superficie rastreada). Calculo: md5 intactos.

## v12.12.19 (Ledger incr 4: despesas, creditos, fechos)

- Alterado: admin/finance/financeiro-despesas-view.php (despesa criada instrumentada; despesa_id capturado antes).
- Alterado: includes/despesa-state-machine.php (transicao de estado de despesa instrumentada).
- Alterado: includes/finance-core.php (criacao de credito instrumentada; credito_id capturado antes).
- Alterado: includes/fin-fecho-turno.php (fecho e reabertura de turno instrumentados; fecho_id capturado antes no fecho).
- Reforcado: tools/check-ledger.php (exige os 5 eventos) e tools/smoke-ledger-v12-12-15.php (5 eventos numa cadeia integra).
- Superficie: 196 (inalterada). Calculo: md5 intactos.

## v12.12.21 (Fase 7 incr 1: reconciliacao e divergencias)

- Novo: includes/payments/reconciliacao-divergencias.php (funcao de dominio so leitura sige_reconciliacao_divergencias, fail-closed por escola).
- Novo: admin/finance/reconciliacao-view.php (ecra so de leitura, sem POST; usa o helper).
- Novo: assets/views/reconciliacao.css (estilo so com tokens).
- Alterado: includes/admin-shell.php (rota financeiro-reconciliacao e link de navegacao).
- Alterado: includes/ui-kit.php (enfileira reconciliacao.css).
- Alterado: sige-softgenial.php (carrega o helper de reconciliacao).
- Novo: tools/check-reconciliacao.php e tools/smoke-reconciliacao.php (no corredor).
- Superficie: 196 (inalterada, sem endpoint de escrita). Calculo: md5 intactos. Design: sem regressao.

## Actualizacao v12.12.21 (Fase 7 incremento 2)

- Superficie: novo view_action financeiro-aprovacoes:sige_fin_aprovacao_decidir. Manifesto 196 para 197 (view_action 18 para 19).
- Views: novo ecra admin/finance/aprovacoes-view.php (Aprovacoes Pendentes), so com tokens.
- Permissoes: o endpoint de decisao exige financeiro.estornar OU financeiro.caixa_reabrir; o codigo aplica a permissao especifica por tipo.
- Tenant: nova tabela sige_fin_aprovacoes, sempre filtrada por escola_id; fail-closed.
- Segredos: sem novos segredos nem opcoes.
- Dependencias: sem novos hosts externos.
- Security Kernel: regra nova em enforce (enforce 30 para 31); manifesto igual a regras (197).

## Actualizacao v12.12.22 (release correctiva)

Esta versao e uma correccao de roteamento da Fase 7, construida sobre a v12.12.21. As views financeiro-reconciliacao (Incremento 1) e financeiro-aprovacoes (Incremento 2, regra de quatro-olhos), entregues na Fase 7 mas em falta na allowlist anti-LFI e na matriz de permissoes do admin-shell, ficavam inalcancaveis: a guarda reescrevia o pedido para o painel inicial. Foram repostas em ambas as listas, com as permissoes identicas as guardas internas de cada view. Nao ha alteracao da superficie de accao (manifesto e regras do Security Kernel mantem-se em 197), nao ha migracao de dados (SCHEMA_VERSION inalterada) e as regras de calculo financeiro nao sao tocadas.

As contagens da superficie mantem-se: 197 superficies de accao (enforce 31). As Views na allowlist e na matriz de Permissoes sobem de 54 para 56, porque as duas views da Fase 7 (reconciliacao e aprovacoes) passam a estar cobertas. Tenant, Segredos, Dependencias e Security Kernel sem alteracao.

## Actualizacao v12.12.24 (Fase 8 Incr 1)

- Superficie de accao: 197 (inalterada). O novo ecra de inventario de dados pessoais e so de leitura, sem POST nem view_action, logo nao acrescenta superficie.
- Views: 57 (mais uma: privacidade-dados), coerente na allowlist, na matriz e no mapa de despacho.
- Permissoes: registo passa a 123 com a nova privacidade.inventario_ver (modulo sistema, risco medio), concedida a administracao e direccao.
- Tenant: agregados por escola sao fail-closed (escola_id menor ou igual a zero devolve vazio); nenhum novo fallback.
- Segredos: sem novos segredos; so a opcao sige_permissions_engine_version e elevada para 12.12.23.
- Dependencias: sem novos hosts externos (mantem-se 12).
- Security Kernel: regras inalteradas (197, enforce 31).
- Novo modulo includes/privacy/ (pii-catalog.php declara 76 campos PII em 10 tabelas, 19 sensiveis; pii-inventario.php confronta com o esquema vivo). SCHEMA_VERSION inalterada (20260621.1).

## Actualizacao v12.12.24 - Fase 8 incremento 2 (Direito de acesso e portabilidade)

- Superficie de accao: 199 (sobe de 197). O unico item novo e o endpoint admin_post:sige_privacidade_exportar (exportacao do dossie de dados pessoais). admin_post passa de 39 para 40.
- Views: 58 na allowlist e na matriz de permissoes (sobe de 57); novo view=privacidade-acesso exige privacidade.acesso_exportar.
- Permissoes: nova privacidade.acesso_exportar (modulo sistema, risco alto). Total do catalogo: 124. Semeada so a administracao e direccao por migracao idempotente (motor de permissoes em 12.12.24).
- Tenant: o dossie e a exportacao sao sempre filtrados por escola_id e fail-closed; aluno de outra escola e recusado sem dados. Sem novo fallback de tenant.
- Segredos: nenhum segredo ou opcao novos; Secret Vault inalterado.
- Dependencias: nenhuma dependencia ou host externos novos.
- Security Kernel: nova regra em enforce para a exportacao (total 198, enforce 32). Manifesto e Kernel alinhados (198 == 198). Sem migracao de esquema (SCHEMA_VERSION inalterada).


## Actualizacao v12.12.25 - Fase 8 incremento 3 (apagamento por anonimizacao)

Este incremento acrescenta a primeira operacao destrutiva do produto: o apagamento por anonimizacao (direito ao apagamento). Foi adicionado um endpoint admin_post governado em modo enforce e risco critico (admin_post:sige_privacidade_apagar), com confirmacao em dois passos por numero de processo, nonce, rate limit (5/300s), isolamento por escola e auditoria antes e depois. A superficie de accao passou de 198 para 199 e o enforce de 32 para 33. Nova permissao critica privacidade.apagamento_executar, semeada so a administracao e direccao e sempre auditada. Sem eliminacao fisica de linhas e sem migracao de esquema (SCHEMA_VERSION inalterada). Manifesto e Kernel mantem-se alinhados (199 == 199).
