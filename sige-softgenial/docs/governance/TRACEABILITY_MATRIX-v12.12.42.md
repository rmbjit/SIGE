# TRACEABILITY_MATRIX - v12.12.42 - MFA de Operacao (Step-up)

Rastreio de cada criterio de seguranca (SK) ate a evidencia que o comprova.

| ID | Criterio | Evidencia |
|---|---|---|
| SK-001 | Modulo MFA carregado apos o escudo de login | sige-softgenial.php require de includes/security-mfa-stepup.php; gate check-mfa-stepup |
| SK-002 | 12 primitivas definidas | smoke-mfa-stepup-v12-12-10 (12 primitivas definidas) |
| SK-003 | Step-up desligado por defeito | sige_mfa_stepup_enabled le option sige_mfa_stepup com defeito off |
| SK-004 | Desactivacao de emergencia | SIGE_MFA_STEPUP_OFF curto-circuita sige_mfa_stepup_enabled |
| SK-005 | Guard permite quando desligado | smoke (desligado: guard permite) |
| SK-006 | Guard permite fora do perfil | smoke (fora do perfil: permite) |
| SK-007 | Guard bloqueia fail-closed | smoke (no perfil, nao verificado: BLOQUEIA) |
| SK-008 | Janela de verificacao recente | smoke (verificacao recente: permite) |
| SK-009 | Anti-lockout de SMTP | smoke (email falha: permite) |
| SK-010 | Verificacao de codigo (ok/errado) | smoke (verify codigo certo/errado) |
| SK-011 | 6 operacoes de servico protegidas | check-mfa-stepup (contextos fin_*) |
| SK-012 | 2 handlers de credenciais protegidos | check-mfa-stepup (cfg_emola, cfg_mpesa) |
| SK-013 | 2 handlers de caixa protegidos | check-mfa-stepup (caixa_reabrir, caixa_fechar) |
| SK-014 | 10 contextos protegidos no total | check-mfa-stepup (10 operacoes criticas) |
| SK-015 | Endpoint de confirmacao com nonce | regra de Kernel admin_post:sige_mfa_confirm intent nonce |
| SK-016 | Regra de Kernel em observe; enforce=30 | check-security-kernel-rules (enforce=30) |
| SK-017 | Calculo intacto e baselines congelados | smoke (calculo intacto; baselines 138/139); check-tenant-fallbacks |
| SK-018 | Sem em-dashes; versao sincronizada; gates verdes | smoke-release-gate; run-gates |

Evidencia consolidada: a execucao de tools/run-gates.php cobre todos os criterios acima; o smoke dedicado fornece a verificacao de runtime do comportamento fail-closed.

## v12.12.11 (incremento TOTP)

- SK-TOTP-1 (nucleo RFC 6238): Evidencia: tools/check-mfa-totp.php e tools/smoke-mfa-totp-v12-12-11.php (vectores RFC).
- SK-TOTP-2 (QR correcto): Evidencia: validacao celula a celula contra referencia (versoes 1 a 10) e decodificacao por leitor real.
- SK-TOTP-3 (inscricao com confirmacao): Evidencia: ciclo de inscricao no smoke (guardar, nao inscrito, confirmar errado, confirmar certo, inscrito, desactivar).
- SK-TOTP-4 (step-up aceita TOTP, inscrito sem email): Evidencia: integracao no smoke (issue_challenge sem email, verify ok/errado, tecto esgotado).
- SK-TOTP-5 (governanca): Evidencia: regra 195 no kernel e no manifesto; gate de regras verde.

## v12.12.12 (reposicao automatica)

- SK-REP-1 (captura nas 6 operacoes): Evidencia: tools/check-mfa-autoreplay.php e smoke (6 capturas em fin-action-service.php).
- SK-REP-2 (uso unico, sem execucao dupla): Evidencia: smoke (segundo consumo devolve null, contador fica em 1).
- SK-REP-3 (re-validacao de tenant e permissao): Evidencia: sige_tenant_write_guard e self::userCan dentro de cada metodo re-executado.
- SK-REP-4 (gating): Evidencia: opcao sige_mfa_autoreplay e kill-switch SIGE_MFA_AUTOREPLAY_OFF; smoke do caminho off.
- SK-REP-5 (sem novo endpoint): Evidencia: manifesto mantem-se em 195; gate de regras verde.

## v12.12.13 (painel de controlo de seguranca MFA)

- SK-SET-1 (acesso so super admin): Evidencia: smoke e gate (Admin IT recusado, administrator aceite) com o gate real sige_is_real_wp_admin_user.
- SK-SET-2 (CSRF): Evidencia: check_admin_referer e wp_nonce_field('sige_mfa_settings').
- SK-SET-3 (auditoria de alteracoes): Evidencia: sige_security_log mfa_settings_change e mfa_stepup_disabled no handler.
- SK-SET-4 (endpoint governado): Evidencia: manifesto 196 e regra admin_post:sige_mfa_settings_save (observe); gate de regras verde.
- SK-SET-5 (sem superficie nova alem do endpoint): Evidencia: sem $_GET prefixado sige_; admin_menu nao e superficie rastreada.

## v12.12.14 (Secret Vault, incremento 1)

- SK-VAULT-1 (cifra em repouso): Evidencia: smoke (valor guardado cifrado, difere do claro) e gate; helper de pagamentos sela as chaves-segredo.
- SK-VAULT-2 (transparencia): Evidencia: leitura revela; cliente recebe o valor em claro; webhook valida.
- SK-VAULT-3 (compatibilidade): Evidencia: passagem de texto em claro; auto-reparacao so no admin.
- SK-VAULT-4 (registo auditavel): Evidencia: sige_vault_secret_registry cataloga pagamentos, SMTP e WhatsApp; gate verifica ausencia de gravacao em claro.
- SK-VAULT-5 (redaccao): Evidencia: campos de segredo sao password sem value pre-preenchido.

## v12.12.15 (Ledger financeiro, incremento 1)

- SK-LED-1 (imutabilidade): Evidencia: HMAC encadeado; smoke deteta adulteracao de conteudo.
- SK-LED-2 (remocao/insercao no meio): Evidencia: sequencia + prev_hash; smoke deteta salto.
- SK-LED-3 (forja exige chave): Evidencia: hash depende da chave dos salts; smoke confirma.
- SK-LED-4 (cobertura): Evidencia: 6 operacoes criticas instrumentadas (gate verifica os 6 eventos).
- SK-LED-5 (nao quebra operacao): Evidencia: append apos sucesso, try/catch, function_exists.
- SK-LED-6 (so leitura/super admin): Evidencia: ecra gated por sige_is_real_wp_admin_user, sem POST.

## v12.12.16 (patch correctivo do Ledger)

- COR-1 (criacao da tabela): Evidencia: SCHEMA_VERSION subida; gate falha se ficar no valor antigo com a tabela adicionada.
- COR-2 (resiliencia sem tabela): Evidencia: sige_ledger_table_exists nos 4 pontos; smoke prova append=false, verify vazio, lista vazia, sem erro.
- COR-3 (sem regressao): Evidencia: corredor 56/56; md5 intactos; manifesto 196.

## v12.12.17 (Ledger incr 2: pagamentos e ancora)

- I2-1 (pagamentos no ledger): Evidencia: sige_ledger_append('fin_registar_pagamento') no choke point; gate verifica.
- I2-2 (cobertura do anual): Evidencia: o anual delega em sige_fin_registar_pagamento (sem duplicar).
- I2-3 (truncagem da cauda): Evidencia: ancora externa; smoke deteta truncagem.
- I2-4 (adulteracao da cabeca): Evidencia: hash da ancora confrontado; smoke deteta divergencia.
- I2-5 (ancora ausente): Evidencia: estado distinto, nao fatal; smoke confirma sem falso positivo.
- I2-6 (resiliencia/atomicidade): Evidencia: temp+rename dentro do bloqueio; best-effort.

## v12.12.18 (Ledger incr 3: lancamentos)

- I3-1 (criacao de cobranca): Evidencia: sige_ledger_record_charge('fin_criar_lancamento') no ponto criado; gate verifica.
- I3-2 (alteracao de valor): Evidencia: sige_ledger_record_charge('fin_actualizar_lancamento') no ponto actualizado.
- I3-3 (escrita em bloco): Evidencia: sige_ledger_append_many com um bloqueio e uma ancora; smoke encadeia ao genesis e entre si.
- I3-4 (diferimento): Evidencia: buffer + flush no shutdown; smoke confirma que acumula sem gravar e grava no flush.
- I3-5 (mistura imediato/diferido): Evidencia: smoke confirma a mesma cadeia integra.
- I3-6 (sem tocar calculo/gerador): Evidencia: md5 intactos; sem cirurgia no gerador.

## v12.12.19 (Ledger incr 4: despesas, creditos, fechos)

- I4-1 (despesa criada): Evidencia: fin_criar_despesa no ponto de criacao; gate verifica.
- I4-2 (despesa transitada): Evidencia: fin_despesa_transitar apos a transicao.
- I4-3 (credito criado): Evidencia: fin_criar_credito apos sucesso.
- I4-4 (fecho fechado): Evidencia: fin_fechar_turno apos a insercao (fecho_id capturado antes).
- I4-5 (fecho reaberto): Evidencia: fin_reabrir_turno apos a reabertura.
- I4-6 (cadeia integra): Evidencia: smoke confirma os 5 tipos numa cadeia integra com ancora confirmada.

## v12.12.21 (Fase 7 incr 1: reconciliacao e divergencias)

- F7-1-1 (recebido por aplicar): Evidencia: helper classe nao_conciliadas; smoke conta 2; view lista.
- F7-1-2 (divergencia de montante): Evidencia: helper classe divergencias_montante; smoke gateway 1000 vs pagamento 900.
- F7-1-3 (pagamento sem gateway): Evidencia: helper classe pagamentos_sem_gateway; smoke conta 1.
- F7-1-4 (totais): Evidencia: helper bloco totais; smoke confirma recebido/conciliado/limbo/moveis/diferenca.
- F7-1-5 (idempotencia e-Mola): Evidencia: emola-webhook pre-verificacao por referencia + chave unica (igual ao M-Pesa).
- F7-1-6 (so leitura): Evidencia: gate confirma ausencia de POST/escrita na view e no helper.

## Actualizacao v12.12.21 (Fase 7 incremento 2)

- Requisito (regra de quatro-olhos) -> Regra SK view_action:financeiro-aprovacoes:sige_fin_aprovacao_decidir (enforce) -> Evidencia: tools/check-aprovacoes.php e tools/smoke-aprovacoes.php (19 OK), regra no Security Kernel (197 == 197).
- Requisito (separacao de funcoes) -> codigo sige_fin_aprovacao_decidir (decisor != solicitante) -> Evidencia: smoke "auto-aprovacao bloqueada".

## Actualizacao v12.12.22 (release correctiva)

Esta versao e uma correccao de roteamento da Fase 7, construida sobre a v12.12.21. As views financeiro-reconciliacao (Incremento 1) e financeiro-aprovacoes (Incremento 2, regra de quatro-olhos), entregues na Fase 7 mas em falta na allowlist anti-LFI e na matriz de permissoes do admin-shell, ficavam inalcancaveis: a guarda reescrevia o pedido para o painel inicial. Foram repostas em ambas as listas, com as permissoes identicas as guardas internas de cada view. Nao ha alteracao da superficie de accao (manifesto e regras do Security Kernel mantem-se em 197), nao ha migracao de dados (SCHEMA_VERSION inalterada) e as regras de calculo financeiro nao sao tocadas.

A rastreabilidade SK-001 a SK-018 mantem-se sem alteracao. O defeito de roteamento desta release fica rastreado pela Evidencia do gate check-view-permission-map (invariante: mapa de despacho subconjunto da allowlist) e pelos gates check-aprovacoes e check-reconciliacao.

## Actualizacao v12.12.24 (Fase 8 Incr 1)

| Item | Evidencia |
| --- | --- |
| Inventario de PII (catalogo declarado) | includes/privacy/pii-catalog.php; gate tools/check-privacidade.php (integridade do catalogo) |
| Inventario so de leitura (sem escrita) | includes/privacy/pii-inventario.php; gate verifica ausencia de INSERT/UPDATE/DELETE |
| Deteccao de desvios e lacunas | tools/smoke-privacidade.php (teste negativo: temperatura ausente e coluna PII por classificar) |
| Fail-closed por escola | smoke-privacidade.php (escola menor ou igual a zero devolve agregados vazios) |
| Rota governada | admin-shell.php (allowlist + matriz + navegacao); invariante SK-001 a SK-018 mantido |
| Permissao com privilegio minimo | permissions-layer.php (migrate_121223); negada a tesouraria, secretaria e docencia |

## Actualizacao v12.12.24 - Fase 8 incremento 2

- SK-001 a SK-018 (principios do Security Kernel) rastreados para a nova superficie de exportacao. Evidencia: regra admin_post:sige_privacidade_exportar em enforce no Kernel; manifesto a 198; gates check-action-surface-manifest, check-security-kernel-rules, check-acesso e smoke-acesso verdes.
- Requisito acesso (ver dossie) -> includes/privacy/pii-dossier.php + admin/system/privacidade-acesso-view.php. Evidencia: smoke-acesso (dossie reune dados reais; fail-closed).
- Requisito portabilidade (exportar) -> includes/privacy/pii-dossier-export.php (admin_post enforce). Evidencia: check-acesso (permissao, nonce, auditoria, cabecalhos, exit) e smoke-acesso (JSON valido).
- Requisito auditoria -> privacidade.acesso_exportar na lista sempre-auditada de sige_permission_audit. Evidencia: check-acesso.


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


## Actualizacao v12.12.33 - Correccao do numero de chamada no MAP (varredura canonica)

Rastreio da correccao do numero de chamada ate a evidencia que a comprova.

| ID | Criterio | Evidencia |
|---|---|---|
| NC-001 | Variavel de utilizador (@rn := @rn + 1) eliminada do codigo de producao | Busca sem ocorrencias de @rn := em codigo (apenas comentarios); includes/map-pdf-handler.php passa a usar sige_turma_numero_chamada |
| NC-002 | Auxiliares canonicos deterministicos definidos | includes/core-helpers.php: sige_turma_ordem_chamada_order_sql e sige_turma_numero_chamada, ambos sob function_exists; php -l limpo |
| NC-003 | Rolo canonico unico (mesma populacao e mesma ordem total) | sige_aluno_matricula_activa_sql mais nome_completo ASC, id ASC, aplicado ao MAP, a modal e as pautas |
| NC-004 | MAP individual e em lote numeram pela ordem da pauta | sige_map_build_data usa o auxiliar; ORDER BY do lote com desempate por id |
| NC-005 | Modal, pauta-pdf, pauta-excel, dec-view e pauta-final-view convergidos | ajax-handlers e os quatro handlers usam populacao e ordem canonicas, com escola_id no join |
| NC-006 | Numero do MAP igual ao da modal e da pauta, incluindo desistencias | Simulacao de equivalencia (todos coincidem, com caso sintetico de desistencia) |
| NC-007 | Calculo intacto, sem nova superficie, versao sincronizada, zero travessoes | smoke-release-gate; run-gates; manifesto e Kernel 199/33; views 60 |

Evidencia consolidada: tools/smoke-release-gate.php verde a partir de pasta limpa; busca de @rn := sem ocorrencias em codigo; php -l limpo nos sete ficheiros tocados; simulacao de equivalencia do numero de chamada.


## Actualizacao v12.12.34 - Blindagem de uploads e ficheiros

Rastreio da blindagem de uploads ate a evidencia que a comprova.

| ID | Criterio | Evidencia |
|---|---|---|
| UP-001 | Modulo de seguranca de uploads existe e carrega | includes/security-uploads.php; require em sige-softgenial.php; php -l limpo |
| UP-002 | Directorio de uploads ganha proteccao idempotente | sige_uploads_write_protection_files escreve .htaccess e web.config com marcador; ligado a admin_init; smoke (escrita e idempotencia) |
| UP-003 | Execucao de PHP negada no directorio | .htaccess com FilesMatch Require all denied e php_admin_flag dentro de IfModule mod_php; smoke (.htaccess nega PHP) |
| UP-004 | Tipos perigosos recusados a entrada | sige_uploads_prefilter em wp_handle_upload_prefilter; smoke (PHP disfarcado de .jpg, SVG malicioso, imagem invalida recusados) |
| UP-005 | Deteccao por extensao, bytes magicos e inicio de ficheiro | sige_uploads_filename_has_dangerous_ext, sige_uploads_head_is_executable_or_script, sige_uploads_detect_real_mime; smoke (dupla extensao, MZ, abertura PHP) |
| UP-006 | Importacao de alunos valida o conteudo real | aluno-fetch-ajax.php chama sige_uploads_validate_import_file; smoke (.xlsx tem de ser ZIP; PHP disfarcado recusado) |
| UP-007 | Sem nova superficie, sem opcoes, versao sincronizada, zero travessoes | extractor de superficie em 199; manifesto e Kernel 199/33; smoke-release-gate; run-gates |

Evidencia consolidada: tools/smoke-uploads-hardening.php (26 verificacoes) e tools/check-uploads-hardening.php verdes; extractor de superficie confirma 199 itens (admin_init deduplicado); smoke-release-gate verde a partir de pasta limpa.


## Actualizacao v12.12.35 - Armazenamento privado dos documentos sensiveis

Rastreio do armazenamento privado ate a evidencia que o comprova.

| ID | Criterio | Evidencia |
|---|---|---|
| PRIV-001 | Modulo de armazenamento privado existe e carrega | includes/security-uploads-private.php; require em sige-softgenial.php; php -l limpo |
| PRIV-002 | Directorio privado com negacao total do acesso web | sige_uploads_ensure_private_dir e sige_uploads_write_deny_all escrevem .htaccess Require all denied, web.config e index.php com marcador SIGE-PRIVATE-DOCS; smoke (negacao total escrita) |
| PRIV-003 | Documentos sensiveis movidos para fora do directorio publico | sige_uploads_move_to_private; smoke (documento e miniatura movidos, original removido, documento no directorio privado) |
| PRIV-004 | Movimento seguro contra perda | so consuma apos remover o original (if (!@unlink($src)) aborta apagando a copia); smoke (URL sem ficheiro degrada sem alterar) |
| PRIV-005 | Documentos novos privatizados na gravacao | choke-point sige_uploads_privatize_doc_value ligado em db-handler (aluno) e ajax-handlers (equipa); gate (campos privatizados) |
| PRIV-006 | Documentos existentes migrados por dreno idempotente | sige_uploads_private_migration_drain e sige_uploads_maybe_run_private_migration no admin_init; gate (dreno ligado); smoke (choke-point move documento real) |
| PRIV-007 | Endpoint serve o ficheiro privado sem alteracao | ficheiro sob basedir de uploads; secure-document-download intocado; .htaccess nega o acesso directo |
| PRIV-008 | Foto preservada (mostrada em linha) | gate (foto nao privatizada e linha original intacta) |
| PRIV-009 | Sem nova superficie, sem opcoes, versao sincronizada, zero travessoes | extractor de superficie em 199; manifesto e Kernel 199/33; smoke-release-gate; run-gates |

Evidencia consolidada: tools/smoke-uploads-private.php (15 verificacoes) e tools/check-uploads-private.php verdes; extractor de superficie confirma 199 itens (admin_init deduplicado); smoke-release-gate verde a partir de pasta limpa.


## Actualizacao v12.12.36 - Correccao do despacho do dialogo de confirmacao

Rastreio da correccao ate a evidencia que a comprova.

| ID | Criterio | Evidencia |
|---|---|---|
| FIX-CONF-001 | Handler respeita o botao premido | assets/sige-ui.js: if (ev.submitter) decide pelo submitter; recuo por querySelector so sem submitter; gate (ausencia do ternario, presenca de if (ev.submitter)) |
| FIX-CONF-002 | Nenhum formulario com padrao misto | gate varre admin e includes e exige zero formularios com botao confirm ao lado de nao-confirm |
| FIX-CONF-003 | Botao Aprovar com dialogo proprio | admin/academic/aprovar_notas-view.php: botao value aprovar com data-sige-confirm e titulo Aprovar notas; gate |
| FIX-CONF-004 | Backend intocado | nenhuma alteracao ao handler POST de aprovar_notas; aprovar continua a definir aprovado e rejeitar a definir rejeitado |
| FIX-CONF-005 | Sem nova superficie, sem opcoes, versao sincronizada, zero travessoes | extractor de superficie em 199; smoke-release-gate; run-gates |

Evidencia consolidada: tools/check-confirm-dispatch.php verde; extractor de superficie confirma 199 itens; smoke-release-gate verde a partir de pasta limpa.


## Actualizacao v12.12.37 - Fase 1 Incremento 3 - Ciclo de vida dos documentos

Rastreio das quatro frentes ate a evidencia que as comprova.

| ID | Criterio | Evidencia |
|---|---|---|
| LINK-001 | Links de documento com expiracao assinada | includes/secure-document-download.php: helpers link_ttl, link_secret, link_sig, link_valid; construtores de URL com exp e sig nos dois fluxos; gate e smoke (fresco aceite, expirado, campo, escopo, vazio e alterado recusados) |
| LINK-002 | Recusa em tempo constante | hash_equals na verificacao; handlers recusam 403 nos dois fluxos |
| LOG-001 | Registo de download reforcado | audit com IP e utilizador nos dois fluxos (documento_baixado_seguro e documento_rh_baixado_seguro); gate exige 'ip' nos dois |
| ORPHAN-001 | Limpeza de orfaos segura | includes/security-uploads-private.php: orphan_sweep com periodo de graca, opera so em sige-private/docs, protege ficheiros de proteccao, aborta se referencias nulas; smoke remove 1 orfao antigo, mantem referenciado, recente e proteccao, aborta sem apagar quando as referencias falham |
| ORPHAN-002 | Accionada sem nova superficie | chamada pela rotina existente de admin_init (maybe_run_private_migration); extractor de superficie em 199 |
| QR-001 | QR gerado localmente | admin/academic/alunos_lista.php: qrious carregado (catalogo com SRI) e sigeQrDataUri; api.qrserver.com ausente de todo o codigo; gate varre admin, includes e assets |
| QR-002 | Dependencia externa removida | sige_gov_external_hosts em 11; baseline de dependencias com 11 itens (api.qrserver.com removido) |

Evidencia consolidada: tools/check-doc-lifecycle.php e tools/smoke-doc-lifecycle.php verdes; extractor de superficie confirma 199 itens; extractor de dependencias confirma 11 hosts; smoke-release-gate verde a partir de pasta limpa.


## Actualizacao v12.12.38 - Fase 2 - Cofre de segredos - cobertura, mascaramento e nao-vazamento

Rastreio das quatro frentes ate a evidencia que as comprova.

| ID | Criterio | Evidencia |
|---|---|---|
| MASK-001 | Mascaramento central de segredos | includes/security-vault.php: sige_secret_mask revela so os ultimos caracteres; smoke (longo mascara o resto, curto totalmente mascarado, vazio devolve vazio) |
| SCRUB-001 | Segredos nao chegam aos logs | includes/security-vault.php: sige_secret_scrub; includes/security-hardening.php: sige_security_log redige antes de registar; smoke (token selado e chave=valor viram [SEGREDO], texto normal intacto) |
| URL-001 | Segredos nao colocados em URL | gate varre includes e exige que nenhuma chave-credencial passe por add_query_arg |
| AUDIT-001 | Alteracao de segredo auditada sem valor | includes/settings/class-sige-settings-repository.php e includes/payments/mobile-tenant-options.php: evento segredo_alterado com a chave; gate exige a presenca nos dois pontos |
| COVER-001 | Registo de segredos alargado | includes/security-vault.php: registo cobre a chave de licenca alem de pagamentos, SMTP e WhatsApp; smoke confirma |
| CANON-001 | Ficheiros canonicos intocados | finance-core.php nao alterado; o scrub liga-se ao canal de seguranca |

Evidencia consolidada: tools/check-vault-coverage.php e tools/smoke-vault-coverage.php verdes; gates de cofre anteriores (check-vault, smoke-vault) continuam verdes; extractor de superficie em 199; smoke-release-gate verde a partir de pasta limpa.


## Actualizacao v12.12.39 - Fase 2 - Cofre de segredos - rotacao e segredos por escola

Rastreio das quatro frentes ate a evidencia que as comprova.

| ID | Criterio | Evidencia |
|---|---|---|
| SCHOOL-001 | Segredos isolados por escola e cifrados | includes/security-vault.php: set/get/delete_for_school com opcao por escola (sufixo _esc) e selagem; smoke (escola 5 e 7 leem o seu, valor guardado cifrado, isolamento) |
| SCHOOL-002 | Texto em claro legado passa intacto | sige_secret_get_for_school passa texto em claro; smoke confirma |
| ROTATE-001 | Rastreio de antiguidade e decisao de rotacao | sige_secret_rotated_at e sige_secret_rotation_due (constante SIGE_SECRET_ROTATION_DAYS); smoke (nunca rodado e antigo precisam, acabado de gravar nao, idade ajustavel) |
| ROTATE-002 | Geracao forte e rotacao auditada | sige_secret_generate_token (random_bytes, url-safe) e sige_secret_rotate_for_school (gera, guarda, marca, audita segredo_rodado, devolve novo); smoke confirma |
| ROTATE-003 | Rotacao concreta do webhook de pagamento por escola | includes/payments/mobile-tenant-options.php: sige_mobile_payment_rotate_webhook_token gera e guarda por escola e audita; gate exige geracao, gravacao por escola e auditoria |
| RESEAL-001 | Re-selagem sem perda | sige_vault_reseal sela texto em claro e mantem o valor; valor nao decifravel fica intacto; smoke confirma |

Evidencia consolidada: tools/check-secret-rotation.php e tools/smoke-secret-rotation.php verdes; gates de cofre anteriores (check-vault, smoke-vault, check-vault-coverage, smoke-vault-coverage) continuam verdes; extractor de superficie em 199 e de opcoes em 132; smoke-release-gate verde a partir de pasta limpa.


## Actualizacao v12.12.40 - Fase 3 - Reconciliacao de pagamentos digitais - deteccao de duplicados

Rastreio ate a evidencia que comprova cada criterio.

| ID | Criterio | Evidencia |
|---|---|---|
| DUP-001 | Deteccao de referencia repetida | includes/payments/reconciliacao-divergencias.php: classe referencia_repetida; smoke confirma |
| DUP-002 | Deteccao de duplo credito | classe pagamento_duplo (mesmo pagamento_id em conciliadas); smoke confirma |
| DUP-003 | Deteccao de pagamento repetido por engano | classe mesmo_pagador_valor dentro da janela; smoke confirma |
| DUP-004 | Sem falsos positivos em mensalidades | janela de tempo separa pagamentos afastados; smoke confirma que mensalidades distantes nao sao marcadas |
| DUP-005 | Involucro por escola fail-closed e so leitura | sige_reconciliacao_duplicados devolve estrutura vazia para escola invalida; o ficheiro de dominio nao tem insert/update/delete; gate confirma |
| DUP-006 | Integracao so de leitura na vista | admin/finance/reconciliacao-view.php apresenta a seccao de possiveis duplicados e nao tem POST; gate confirma |

Evidencia consolidada: tools/check-payments-dedup.php e tools/smoke-payments-dedup.php verdes; o smoke de reconciliacao existente (tres classes de divergencia) continua verde; extractor de superficie em 199; smoke-release-gate verde a partir de pasta limpa.


## Actualizacao v12.12.41 - Fase 3 - Reconciliacao de pagamentos digitais - reconciliacao viva

Rastreio ate a evidencia que comprova cada criterio.

| ID | Criterio | Evidencia |
|---|---|---|
| VIVA-001 | Classificacao contra o gateway | includes/payments/reconciliacao-viva.php: sige_pagamentos_reconciliar_estado (confirmadas, divergentes, inconclusivas); smoke confirma |
| VIVA-002 | Normalizador conservador | sige_pagamentos_normalizar_estado_gateway: confirmada so em sucesso claro, falhada so por codigo conhecido (filtro sige_mpesa_codigos_falha), resto desconhecida; smoke confirma que codigo desconhecido nao vira falha |
| VIVA-003 | Adaptador defensivo | sige_pagamentos_consultar_gateway com try/catch e class_exists; smoke confirma erro quando o cliente esta ausente |
| VIVA-004 | Orquestracao fail-closed e so leitura | sige_reconciliacao_viva devolve vazio para escola invalida, regista divergencias e nao escreve em pagamentos; gate confirma a ausencia de insert/update/delete |
| VIVA-005 | Cron guardado e limitado | add_action sige_evento_diario dentro do guard de modo de teste, com limite e verificacao de gateway configurado; gate confirma |

Evidencia consolidada: tools/check-reconciliacao-viva.php e tools/smoke-reconciliacao-viva.php verdes; os gates de reconciliacao e deteccao de duplicados anteriores continuam verdes; extractor de superficie em 199 (cron num evento ja existente); smoke-release-gate verde a partir de pasta limpa.


## Actualizacao v12.12.42 - Fase 3 - Reconciliacao de pagamentos digitais - resolucao com escrita (quatro-olhos)

Rastreio ate a evidencia que comprova cada criterio.

| ID | Criterio | Evidencia |
|---|---|---|
| RES-001 | Conciliacao pelo caminho canonico | includes/payments/reconciliacao-accoes.php: sige_recon_executar_conciliar delega em sige_fin_registar_pagamento; smoke confirma o lancamento e o valor |
| RES-002 | Re-validacao de estado na execucao | sige_recon_executar_conciliar exige estado recebida ou pendente_manual; sige_recon_executar_rejeitar recusa transacao ja conciliada; smoke confirma |
| RES-003 | Idempotencia e fail-closed | rejeitar ja rejeitada devolve ok sem efeito; ambas as execucoes recusam escola invalida; smoke confirma |
| RES-004 | Maker propoe, nao executa | sige_recon_propor_conciliacao e sige_recon_propor_rejeicao chamam sige_fin_aprovacao_solicitar; smoke confirma o tipo e os parametros |
| RES-005 | Separacao de funcoes (maker diferente de checker) | imposta por sige_fin_aprovacao_decidir (framework existente); regra do kernel para sige_fin_aprovacao_decidir ja presente |
| RES-006 | Tipos e despacho sem nova permissao | finance-aprovacoes.php regista recon_conciliar e recon_rejeitar com financeiro.mobile_payments_gerir e encaminha para a execucao; smoke confirma o despacho |

Evidencia consolidada: tools/check-recon-resolucao.php e tools/smoke-recon-resolucao.php verdes; os gates de reconciliacao viva, deteccao de duplicados e reconciliacao anteriores continuam verdes; extractor de superficie em 199 e vistas em 60 (UI do maker server-side, sem nova vista); smoke-release-gate verde a partir de pasta limpa.
