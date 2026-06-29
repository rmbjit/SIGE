# TRACEABILITY_MATRIX - v12.12.33 - MFA de Operacao (Step-up)

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
