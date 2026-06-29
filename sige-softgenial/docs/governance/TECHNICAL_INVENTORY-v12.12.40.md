# TECHNICAL_INVENTORY - v12.12.40 - MFA de Operacao (Step-up)

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


## Actualizacao v12.12.26 - Fase 8 incremento 3.2 (completar o catalogo de PII)

Este incremento classifica as 12 colunas com aspeto de dado pessoal que estavam fora do catalogo (lacunas detectadas pelo inventario da Incr 1), levando o catalogo de 76 para 88 campos e as lacunas de 12 para 0. As nove colunas identificaveis de sige_alunos (incluindo o documento de identidade digitalizado, a fotografia, os contactos de emergencia e os dados da pessoa autorizada a buscar o aluno) passam a ser tratadas pelo dossie de acesso/portabilidade e pelo motor de anonimizacao, fechando o buraco em que sobreviviam a um apagamento. As duas datas operacionais de cobranca sao classificadas mas preservadas na anonimizacao (lista de preservacao); a coluna de notas de funcionario e classificada mas fica fora do ambito do apagamento do aluno. Sem nova superficie: manifesto e Kernel mantem-se em 199 (enforce 33). Sem migracao de esquema (SCHEMA_VERSION inalterada).


## Actualizacao v12.12.27 - Fase 8 incremento 4 (retencao e expurgo)

Este incremento acrescenta um ecra de governanca de dados, so de leitura, que mostra o calendario de retencao declarado e quantos registos ja excederam o prazo, de forma agregada por escola. Decisao de seguranca central: neste sistema nao ha expurgo por eliminacao em massa, porque as presencas sao derivadas ao vivo do registo de acessos e os registos financeiros, academicos e de auditoria tem dever de retencao; o expurgo de um titular faz-se pela anonimizacao ja existente (Apagamento), que preserva a integridade. Nova permissao privacidade.retencao_ver (risco medio, so leitura), semeada a administracao e direccao. Sem nova superficie de accao: manifesto e Kernel mantem-se em 199 (enforce 33). Sem migracao de esquema (SCHEMA_VERSION inalterada).


## Actualizacao v12.12.28 - Fase 9 incremento 1 (blindagem do modulo de permissoes)

Abre a Fase 9 (seguranca e governanca de acessos). O modulo de Perfis e Permissoes passa a ser seguro por desenho atraves de um avaliador unico de operacao que aplica quatro guardas: alvo protegido (administradores WordPress reais nao sao geriveis pelo modulo, por qualquer actor), anti-escalada (um gestor que nao seja administrador WP real nao pode atribuir um perfil que confira a gestao de permissoes), auto-proteccao (um gestor nao se despromove nem se remove a si proprio) e ultimo gestor (nao se deixa a escola sem nenhum gestor). O handler chama o avaliador e bloqueia nos dois ramos, com auditoria, mesmo com nonce valido. As contas protegidas aparecem na lista so de leitura, com selo Protegido. A porta do menu fica coerente (link mostrado a quem tem usuarios.gerir_permissoes, com Saude do Sistema e Centro de Configuracao restritos ao core admin); migracao idempotente garante a concessao ao admin_ti na base de dados real. Sem novo ecra e sem nova superficie: manifesto e Kernel mantem-se em 199 (enforce 33). Sem migracao de esquema (reconciliacao em role_permissions).


## Actualizacao v12.12.29 - Fase 9 incremento 2 (hierarquia de perfis por nivel)

Continua a Fase 9. Cada perfil passa a ter um nivel (mapa em codigo, sem alteracao de esquema), com os tres gestores no topo (direccao_geral e admin_escola a 90, admin_ti a 80) e os restantes por responsabilidade; perfis desconhecidos ou personalizados ficam no nivel 0, o mais restritivo. O avaliador unico ganha a guarda nivel_insuficiente, por cima da regra anti-escalada do Incr 1: um actor nao protegido so atribui perfis estritamente abaixo do seu nivel e so mexe em utilizadores estritamente abaixo do seu nivel; a regra do Incr 1 mantem precedencia (nenhum nao-administrador atribui um perfil que confira gestao). A interface fica coerente: o selector lista so os perfis atribuiveis e as linhas de nivel igual ou superior ficam so de leitura; contas protegidas continuam so de leitura com selo e administradores WordPress reais mantem autoridade plena. Sem novo ecra e sem nova superficie: manifesto e Kernel mantem-se em 199 (enforce 33); allowlist de views em 60. Sem migracao de esquema.


## Actualizacao v12.12.30 - Fase 9 incremento 3 (sobreposicao reversivel do papel WordPress)

Continua a Fase 9. Converte a substituicao destrutiva do papel WordPress numa sobreposicao reversivel, fechando a raiz do antigo vector de bloqueio. A atribuicao preserva o papel original numa copia em user meta (idempotente, exclui papeis sige_*, com recurso ao papel por omissao quando nao ha original); a remocao repoe o original e limpa a copia; a sincronizacao no init repoe quando nao ha perfil activo mas o utilizador ainda esta num papel sige_* (auto-cura), preservando tambem antes de qualquer set_role. Os menus legados continuam a funcionar enquanto o perfil esta activo; o administrador WordPress real continua intocado. Sem novo ecra e sem nova superficie: manifesto e Kernel mantem-se em 199 (enforce 33); allowlist de views em 60. Sem migracao de esquema (usa user meta).


## Actualizacao v12.12.31 - Fase 9 incremento 4 (niveis de perfil editaveis)

Continua a Fase 9. A hierarquia de niveis (Incr 2, mapa em codigo) passa a ser editavel e persistida na base de dados, sem alteracao de esquema. O mapa em codigo continua a ser o valor por omissao; uma opcao guarda apenas os desvios, e role_niveis funde a base com os desvios (o avaliador e a filtragem do selector respeitam os niveis editados automaticamente). A gravacao valida 0 a 100 e guarda so desvios. Editar a hierarquia e accao de dono: o painel Hierarquia de niveis e o handler save_niveis ficam reservados ao administrador WordPress real, com nonce e auditoria. save_niveis e uma accao de formulario dentro da view ja listada, nao uma nova superficie: manifesto e Kernel mantem-se em 199 (enforce 33); allowlist de views em 60. Sem alteracao de esquema (opcao em wp_options). Sem aumento de current_user_can.


## Actualizacao v12.12.32 - Fase 9 incremento 5 (sobreposicao puramente aditiva por capacidades)

Conclui a Fase 9. Elimina-se a substituicao do papel WordPress: o modulo deixa de chamar set_role (na atribuicao e na sincronizacao do init) e passa a conceder as capacidades em tempo de execucao por um filtro user_has_cap, a partir do papel sige_* mapeado ao perfil SIGE activo, sem nunca tocar no papel guardado. As capacidades sao o conjunto completo do papel mapeado (incluindo a capacidade com o nome do papel, que e o que current_user_can(sige_*) usa). Para nao haver regressao face ao antigo set_role, as capacidades sao do perfil activo mais recente (independente do contexto de escola); a isolacao de dados por escola e separada e nao muda. O filtro ignora administradores reais e utilizadores sem perfil, com cache por pedido e guarda anti-recursao. A sincronizacao no init passa a so limpar: repoe o papel original quando o utilizador esta num papel sige_* de staff legado. As poucas verificacoes por slug de papel sige_* de staff foram convertidas para verificacao por capacidade. Garantia sem WordPress vivo: um gate que falha se existir qualquer verificacao por slug de papel sige_* de staff. Papeis de portal (sige_aluno, sige_encarregado) intocados. Sem nova superficie (199/33, views 60), sem alteracao de esquema, sem aumento de current_user_can.

## Actualizacao v12.12.33 - Correccao do numero de chamada no MAP

Patch de defeito (nao e nova fase). O numero de chamada (No) do Mapa de Aproveitamento Pedagogico nao coincidia com a posicao alfabetica do aluno na pauta da turma. A causa raiz era uma variavel de utilizador do MariaDB no padrao (@rn := @rn + 1) em includes/map-pdf-handler.php, que numerava por ordem de varrimento da tabela e nao por nome. Foi introduzido um rolo canonico unico e deterministico (populacao de aluno e matricula activos, ordem nome_completo ASC com desempate por id ASC) por dois auxiliares novos em includes/core-helpers.php (sige_turma_ordem_chamada_order_sql e sige_turma_numero_chamada), e o MAP, a modal de alunos da turma e as pautas (pauta-pdf, pauta-excel, dec-view, pauta-final-view) foram convergidos para essa definicao, com seguranca de tenant (escola_id) no join.

Impacto nesta postura: nulo. Esta versao nao altera a superficie de accoes (mantem 199, enforce 33), as views (60), o mapa de permissoes, o isolamento por escola (escola_id), os segredos, as opcoes nem as dependencias externas. Os auxiliares introduzidos sao funcoes simples, nao accoes registadas no Kernel. Calculo academico e financeiro byte-identico. current_user_can inalterado. A postura descrita acima mantem-se valida.

## Actualizacao v12.12.34 - Blindagem de uploads e ficheiros

Incremento 1 da fase de documentos, uploads e QR. Um novo modulo includes/security-uploads.php garante, de forma idempotente no admin_init, ficheiros de proteccao no directorio de uploads (.htaccess e web.config) que impedem a execucao e o acesso web a ficheiros perigosos (PHP e scripts), com as directivas de PHP guardadas dentro de IfModule mod_php para nao quebrar em LiteSpeed ou PHP-FPM. Um filtro wp_handle_upload_prefilter recusa ficheiros perigosos a entrada por extensao (incluindo duplas extensoes enganosas), por bytes magicos (finfo), por inicio de ficheiro (assinaturas MZ, ELF, shebang e abertura de PHP), por imagem declarada que nao e imagem real, e por SVG com script, eventos ou entidades; bloqueia apenas o comprovadamente perigoso, para nao recusar tipos legitimos. A mesma validacao de conteudo real protege a importacao de alunos (XLSX e CSV). O servico autenticado de documentos do aluno e de RH (secure-document-download) continua a funcionar porque le por readfile, do lado do servidor.

Impacto nesta postura: nulo. Sem nova superficie de accao (admin_init ja e superficie contabilizada e deduplicada, o prefilter e um filtro e nao um endpoint; o manifesto e as regras do Kernel mantem-se em 199, enforce 33; a lista de views em 60), sem opcoes novas (132), sem dependencias externas novas (12), sem alteracao de esquema. Calculo academico e financeiro byte-identico. current_user_can inalterado. A postura descrita acima mantem-se valida.

## Actualizacao v12.12.35 - Armazenamento privado dos documentos sensiveis

Incremento 2 da fase de documentos, uploads e QR. Os documentos sensiveis do aluno (doc_bi_url, doc_cert_url, doc_vacina_url) e da equipa (doc_bi, doc_cv, doc_cert dentro de documentos_urls) deixam de viver no directorio publico wp-content/uploads e passam para um directorio privado (wp-content/uploads/sige-private/docs/<escola_id>), com .htaccess e web.config de negacao total do acesso web, servidos apenas pelo endpoint autenticado (secure-document-download, que le por readfile). Um novo modulo includes/security-uploads-private.php trata disto por dois caminhos, sem opcao de estado: na gravacao, um choke-point move o documento novo para o directorio privado e guarda a URL ja la; para os existentes, um dreno idempotente e resumivel pendurado no admin_init das paginas do SIGE trata-os em lotes e fica inerte quando nada resta. O movimento e seguro contra perda (so consuma depois de remover o original; em falha, aborta sem alterar) e move tambem as miniaturas. A foto (campo foto e foto_perfil) NAO e abrangida por ser mostrada em linha como imagem.

Impacto nesta postura: nulo. O endpoint resolve o ficheiro no directorio privado sem alteracao, porque continua sob o basedir de uploads e o .htaccess nega o acesso directo. Sem nova superficie de accao (admin_init ja e superficie contabilizada e deduplicada; o manifesto e o Kernel mantem-se em 199, enforce 33; a lista de views em 60), sem opcoes novas (132), sem dependencias externas novas (12), sem alteracao de esquema. Calculo academico e financeiro byte-identico. current_user_can inalterado. A postura descrita acima mantem-se valida.

## Actualizacao v12.12.36 - Correccao do despacho do dialogo de confirmacao

Correccao de defeito (nao e nova funcionalidade). No view aprovar_notas, premir Aprovar mostrava o dialogo de confirmacao do botao Rejeitar e, ao confirmar, submetia a accao de rejeitar em vez de aprovar. Causa raiz no enhancer declarativo de confirmacao (assets/sige-ui.js): o interceptor do evento de submissao, quando o botao premido nao tinha [data-sige-confirm], recorria a form.querySelector e apanhava o primeiro botao de submissao com confirmacao do mesmo formulario (o Rejeitar), herdando o seu dialogo e submetendo a sua accao. A correccao faz o handler respeitar o botao realmente premido (so confirma quando esse botao a exige; o recuo por querySelector fica reservado ao caso sem submitter, como o Enter num campo em navegadores antigos) e da ao botao Aprovar o seu proprio dialogo de confirmacao.

Impacto nesta postura: nulo. So foram tocados assets/sige-ui.js (logica do enhancer) e admin/academic/aprovar_notas-view.php (atributos do botao Aprovar); o backend de aprovacao e rejeicao nao foi alterado. Sem nova superficie de accao (199, enforce 33; views 60), sem opcoes novas (132), sem dependencias externas novas (12), sem alteracao de esquema. Calculo academico e financeiro byte-identico. current_user_can inalterado. A postura descrita acima mantem-se valida.

## Actualizacao v12.12.37 - Fase 1 Incremento 3 - Ciclo de vida dos documentos

Incremento que fecha a Fase 1 (seguranca de documentos e ficheiros), com quatro frentes sobre o que ja existia (perimetro anti-execucao no Incremento 1; armazenamento privado e migracao no Incremento 2).

Frente 1 - Links com expiracao. Os links do endpoint seguro de documentos (fluxo do aluno e fluxo da equipa) passam a transportar exp (expiracao) e sig (HMAC-SHA256 de escopo, id, campo e expiracao, com segredo estavel da instalacao via wp_salt, sem opcao nova), alem do nonce. Os handlers recusam (403) links expirados ou com assinatura invalida, com comparacao em tempo constante. Validade ajustavel pela constante SIGE_SECURE_DOC_LINK_TTL (por omissao 3600s).

Frente 2 - Registo de download reforcado. O registo de auditoria de cada descarregamento passa a incluir o IP do cliente (saneado) e o utilizador, nos dois fluxos, alem do aluno ou professor, campo, rotulo e nome do ficheiro ja existentes.

Frente 3 - Limpeza de orfaos. Rotina segura, idempotente e resumivel que remove do armazenamento privado os ficheiros sem qualquer referencia em base de dados e mais antigos que um periodo de graca (constante SIGE_PRIVATE_ORPHAN_GRACE, por omissao 86400s). Opera apenas dentro de sige-private/docs, nunca toca nos ficheiros de proteccao, e aborta sem apagar nada se nao conseguir construir o conjunto de referencias. Corre na mesma passagem por admin_init que ja faz a migracao.

Frente 4 - QR local. Os cartoes de estudante deixam de gerar o codigo QR num servico externo (api.qrserver.com) e passam a gera-lo no proprio navegador com a biblioteca qrious (ja no catalogo com SRI). O numero de processo do aluno nunca sai do dispositivo. A dependencia externa api.qrserver.com e removida: as dependencias externas passam de 12 para 11.

Impacto na postura: sem nova superficie de accao (199, enforce 33; views 60), sem opcoes novas (132), sem alteracao de esquema, calculo academico e financeiro byte-identico. As dependencias externas reduzem-se de 12 para 11 (api.qrserver.com removido), uma melhoria de privacidade e de superficie. Com este incremento a Fase 1 fica completa.

## Actualizacao v12.12.38 - Fase 2 - Cofre de segredos - cobertura, mascaramento e nao-vazamento

Segundo incremento da Fase 2 (cofre de segredos completo), sobre o incremento 1 que cifrou as credenciais dos gateways de pagamento. Quatro frentes, todas aditivas e sem alterar a forma como os segredos vivos sao guardados ou lidos.

Frente 1 - Mascaramento central. Nova funcao sige_secret_mask que esconde um segredo para exibicao, revelando apenas os ultimos caracteres e sem revelar o comprimento real, alem do mascaramento ja existente no repositorio de definicoes.

Frente 2 - Proibicao de segredos em registos. Nova funcao sige_secret_scrub que redige de um texto os tokens selados pelo cofre (formatos sige2: e gcm1:) e os pares chave=valor de segredos conhecidos, aplicada no canal de seguranca (sige_security_log) antes de qualquer registo. Um segredo nunca chega aos logs em claro; o texto normal passa intacto.

Frente 3 - Proibicao de segredos em URL. Um gate estatico garante que nenhuma chave-credencial e colocada num URL via add_query_arg, e a mesma redaccao trata URLs que sejam registados.

Frente 4 - Auditoria de alteracao. A gravacao de um segredo (no repositorio de definicoes para campos cifrados e SMTP, e na gravacao de segredos de pagamento) regista um evento segredo_alterado com a chave (e o fornecedor, nos pagamentos), nunca o valor. O registo de segredos conhecidos foi alargado para cobrir a chave de licenca.

Impacto na postura: aditivo. Ficheiros canonicos (finance-core e outros) nao foram tocados; o scrub liga-se ao canal de seguranca, nao ao registo de auditoria financeiro. Sem nova superficie de accao (199, enforce 33; views 60), sem opcoes novas (132), sem dependencias externas novas (11), sem alteracao de esquema, calculo academico e financeiro byte-identico. Rotacao de segredos e segredos por escola ficam para o incremento seguinte da Fase 2.

## Actualizacao v12.12.39 - Fase 2 - Cofre de segredos - rotacao e segredos por escola

Terceiro incremento da Fase 2, sobre os incrementos 1 (gateways cifrados) e 2 (mascaramento, redaccao em registos e auditoria de alteracao). Camada de gestao sobre o cofre, totalmente aditiva.

Frente 1 - Segredos por escola. Novas funcoes sige_secret_set_for_school, sige_secret_get_for_school e sige_secret_delete_for_school guardam e leem um segredo isolado por escola, cifrado em repouso, com nome de opcao dinamico por escola (sufixo _esc). Texto em claro de instalacoes pre-cofre passa intacto. Generaliza o isolamento por escola que ja existia nos pagamentos.

Frente 2 - Rastreio de rotacao. Cada gravacao de segredo regista o instante da ultima rotacao; sige_secret_rotation_due indica se um segredo nunca foi rodado ou ja excedeu a idade maxima (constante SIGE_SECRET_ROTATION_DAYS, por omissao 180 dias).

Frente 3 - Geracao e rotacao. sige_secret_generate_token gera um segredo aleatorio forte e url-safe; sige_secret_rotate_for_school gera um novo segredo, guarda-o cifrado por escola, marca a rotacao, audita o evento segredo_rodado e devolve o novo segredo em claro para configurar no fornecedor.

Frente 4 - Rotacao concreta do webhook de pagamento. sige_mobile_payment_rotate_webhook_token gera e instala um novo webhook_token por escola, reutilizando o armazenamento cifrado e auditado que ja existia. Inclui ainda sige_vault_reseal, que re-sela um valor por higiene de formato sem nunca perder um valor nao decifravel.

Impacto na postura: aditivo. Nao altera o armazenamento nem a leitura dos segredos ja existentes, nao toca na cifra partilhada nem em ficheiros canonicos. Sem nova superficie de accao (199, enforce 33; views 60), sem opcoes novas (132; nomes de opcao por escola sao dinamicos), sem dependencias externas novas (11), sem alteracao de esquema, calculo academico e financeiro byte-identico. Com este incremento a cobertura central da Fase 2 fica completa.

## Actualizacao v12.12.40 - Fase 3 - Reconciliacao de pagamentos digitais - deteccao de duplicados

Primeiro incremento da Fase 3 (reconciliacao de pagamentos digitais, que completa a Fase 7 original). O circuito ja tinha idempotencia de webhooks (M-Pesa e e-Mola dedupam por escola e referencia) e um relatorio de divergencias so de leitura. Este incremento acrescenta uma camada de deteccao de duplicados logicos, totalmente so de leitura.

Nucleo puro testavel (sige_pagamentos_detectar_duplicados) que recebe as transacoes de gateway e devolve grupos de possiveis duplicados em tres classes: (1) referencia repetida; (2) mesmo pagamento conciliado mais de uma vez (indicio de duplo credito); (3) mesmo pagador e valor proximos no tempo (provavel pagamento repetido por engano). A janela de tempo evita falsos positivos em mensalidades legitimas.

Involucro de dominio por escola (sige_reconciliacao_duplicados), fail-closed e so de leitura, devolve os grupos com totais. A vista de reconciliacao passa a apresentar uma seccao de possiveis duplicados (so leitura, sem formularios).

Impacto na postura: aditivo e so de leitura. Nunca apaga nem altera pagamentos: so assinala para revisao. Nao toca em regras de calculo nem em ficheiros canonicos. Sem nova superficie de accao (199, enforce 33; views 60), sem opcoes novas (132), sem dependencias externas novas (11), sem alteracao de esquema, calculo academico e financeiro byte-identico. Reconciliacao viva e resolucao de divergencias com escrita ficam para incrementos seguintes.
