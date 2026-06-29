# DEFINITION_OF_DONE - v12.12.44 - MFA de Operacao (Step-up)

Criterios de pronto do incremento 1 da Fase 4. Cada item e verificavel por gate, smoke ou execucao real.

- DoD-001: Modulo includes/security-mfa-stepup.php existe e passa php -l.
- DoD-002: As 12 primitivas sige_mfa_* estao definidas e protegidas por function_exists.
- DoD-003: O guard sige_mfa_require_step_up devolve true quando o step-up esta desligado.
- DoD-004: O guard devolve true quando o utilizador nao pertence aos perfis abrangidos.
- DoD-005: O guard bloqueia (devolve false) e emite desafio quando aplicavel e sem verificacao recente.
- DoD-006: O guard devolve true quando ha verificacao dentro da janela (SIGE_MFA_STEPUP_WINDOW).
- DoD-007: Anti-lockout: falha de wp_mail permite a operacao nessa tentativa, com registo.
- DoD-008: sige_mfa_verify_challenge com codigo correcto devolve ok e abre a janela; codigo errado devolve errado.
- DoD-009: As 6 operacoes de SIGE_FinanceActionService invocam o guard apos o guard de tenant.
- DoD-010: Os 2 handlers de credenciais (e-Mola, M-Pesa) invocam o guard apos validacao de escola.
- DoD-011: Os 2 handlers de caixa (reabrir, fechar) invocam o guard e renderizam o formulario inline.
- DoD-012: Total de 10 contextos de operacao critica protegidos.
- DoD-013: Endpoint admin_post:sige_mfa_confirm com login e nonce; resultado por transient (sem $_GET).
- DoD-014: Regra de Kernel admin_post:sige_mfa_confirm presente em modo observe, intent nonce; enforce mantem-se em 30.
- DoD-015: Versao sincronizada nas 4 fontes (cabecalho, SIGE_VERSION, SIGE_GOV_VERSION, base_version do inventario).
- DoD-016: Funcoes de calculo financeiro byte-identicas a v12.12.8.1 (sige_fin_saldo_lancamento, sige_fin_saldo_sql, sige_fin_total_bruto_sql).
- DoD-017: Baselines de tenant congelados intactos (138/139) e baseline corrente 0; zero em-dashes no codigo.
- DoD-018: Gate check-mfa-stepup e smoke smoke-mfa-stepup-v12-12-10 ligados ao corredor e verdes; todos os gates verdes.

Zero P0/P1: o incremento so e dado por pronto com rediagnostico adversarial sem achados P0 nem P1.

## Criterios adicionais da correccao (v12.12.11)

- DoD-019: As 10 operacoes renderizam o formulario de confirmacao (inline em POST, aviso em GET), sem formulario duplicado.
- DoD-020: Modo estrito opt-in (sige_mfa_stepup_strict) bloqueia a operacao quando o email falha; defeito mantem anti-lockout.
- DoD-021: Cada operacao autorizada pela janela e auditada (satisfied_window).
- DoD-022: Pacote inteiro a zero em/en-dashes (codigo e documentos); gate de release verifica documentos.

## v12.12.11 (incremento TOTP) - estado: cumprido

- TOTP-DoD-1: nucleo RFC 6238 byte-identico aos vectores de referencia. Cumprido.
- TOTP-DoD-2: QR validado celula a celula contra referencia independente (versoes 1 a 10) e por decodificacao. Cumprido.
- TOTP-DoD-3: inscricao completa (segredo cifrado, QR e segredo manual, confirmacao por codigo de teste). Cumprido.
- TOTP-DoD-4: step-up aceita TOTP ou OTP por email; inscrito nao gera email (A2 fechado para esses). Cumprido.
- TOTP-DoD-5: endpoint governado pelo Security Kernel (regra 195) e no manifesto (195 itens). Cumprido.
- TOTP-DoD-6: desligado por defeito; sem alteracao para quem nao inscrever. Cumprido.
- TOTP-DoD-7: Zero P0/P1 no rediagnostico adversarial; achados P2 aceites e documentados. Cumprido.
- TOTP-DoD-8: gate (check-mfa-totp) e smoke (smoke-mfa-totp-v12-12-11) verdes; zero travessoes em codigo e documentos. Cumprido.

## v12.12.12 (reposicao automatica) - estado: cumprido

- REP-DoD-1: descritor de uso unico capturado nas 6 operacoes de servico quando bloqueadas; nunca em config/caixa. Cumprido.
- REP-DoD-2: reposicao re-executa exactamente uma vez, com tenant e permissoes re-validados (mesmo metodo). Cumprido.
- REP-DoD-3: nao-execucao-dupla provada por execucao real (consumo atomico; segundo consumo nao dispara). Cumprido.
- REP-DoD-4: funciona com TOTP e com email; aviso de resultado ao utilizador. Cumprido.
- REP-DoD-5: desligado por defeito (opcao + kill-switch); repeticao manual intacta. Cumprido.
- REP-DoD-6: sem novo endpoint (manifesto 195); regras de Kernel inalteradas (enforce 30). Cumprido.
- REP-DoD-7: Zero P0/P1 no rediagnostico; achados P2 aceites e documentados. Cumprido.
- REP-DoD-8: gate (check-mfa-autoreplay) e smoke (smoke-mfa-autoreplay-v12-12-12) verdes; zero travessoes. Cumprido.

## v12.12.13 (painel de controlo de seguranca MFA) - estado: cumprido

- SET-DoD-1: ecra acessivel so ao super admin real; sige_admin_ti e outros nativos recusados no menu, no render e na gravacao (provado por smoke e gate em runtime). Cumprido.
- SET-DoD-2: alterna e persiste step-up, reposicao, estrito e perfis, com sanitizacao. Cumprido.
- SET-DoD-3: cada alteracao auditada via sige_security_log, com evento distinto ao desligar o step-up. Cumprido.
- SET-DoD-4: faixa de deprecation do PHP deixa de aparecer no wp-admin em producao (display off fora de WP_DEBUG; log mantido); opcional. Cumprido.
- SET-DoD-5: endpoint governado: manifesto 196, regra nova em observe, enforce 30; manifestIds == ruleIds; phpIds === jsonIds. Cumprido.
- SET-DoD-6: gate (check-mfa-settings) e smoke (smoke-mfa-settings-v12-12-13) verdes; zero travessoes. Cumprido.
- SET-DoD-7: Zero P0/P1 no rediagnostico; achados P3 aceites e documentados. Cumprido.

## v12.12.14 (Secret Vault, incremento 1) - estado: cumprido

- VAULT-DoD-1: credenciais M-Pesa e e-Mola e webhook_token cifradas em repouso; clientes recebem o valor em claro (provado por smoke). Cumprido.
- VAULT-DoD-2: cofre unificado sige_vault_seal/reveal/is_sealed com passagem de texto em claro e auto-reparacao so no admin (nunca no webhook publico). Cumprido.
- VAULT-DoD-3: registo de segredos cataloga SMTP, WhatsApp e pagamentos; gate verifica ausencia de gravacao em claro. Cumprido.
- VAULT-DoD-4: ecras de configuracao nao pre-preenchem o segredo (campos password sem value). Cumprido.
- VAULT-DoD-5: gate (check-vault) e smoke (smoke-vault-v12-12-14, 26 verificacoes) verdes; manifesto 196 inalterado (sem endpoint novo). Cumprido.
- VAULT-DoD-6: Zero P0/P1 no rediagnostico; achados P3 e decisao de rotacao adiada documentados. Cumprido.

## v12.12.15 (Ledger financeiro, incremento 1) - estado: cumprido

- LED-DoD-1: tabela append-only sige_fin_ledger criada idempotentemente; chave unica (escola_id, seq). Cumprido.
- LED-DoD-2: sige_ledger_append encadeia por HMAC (chave dos salts), serializado por escola (GET_LOCK); so insere. Cumprido.
- LED-DoD-3: sige_ledger_verify deteta adulteracao de conteudo, remocao no meio (salto) e mudanca de chave; aponta a primeira seq. Cumprido (smoke, 11 verificacoes).
- LED-DoD-4: as 6 operacoes criticas registam no ledger apos sucesso, sem tocar calculo (md5 intactos; insert_id capturado antes no bloquearMes). Cumprido.
- LED-DoD-5: ecra de integridade so super admin, so leitura. Sem endpoint novo (manifesto 196 inalterado). Cumprido.
- LED-DoD-6: gate (check-ledger) e smoke verdes; zero travessoes. Cumprido.
- LED-DoD-7: Zero P0/P1; truncagem da cauda como limite conhecido adiado para ancoragem; achados P3 documentados. Cumprido.

## v12.12.16 (patch correctivo do Ledger) - estado: cumprido

- COR-DoD-1: SCHEMA_VERSION subida; maybe_upgrade cria sige_fin_ledger em instalacoes desactualizadas (dbDelta idempotente). Cumprido.
- COR-DoD-2: guard sige_ledger_table_exists() aplicado a escritor, verificador, lista e ecra; sem tabela degrada sem erro cru. Cumprido (smoke, 14 verificacoes).
- COR-DoD-3: gate reforcado (exige SCHEMA_VERSION subida e guard aplicado); smoke e corredor verdes. Cumprido.
- COR-DoD-4: sem regressao; md5 das regras de calculo intactos; manifesto 196 inalterado. Cumprido.

## v12.12.17 (Ledger incr 2: pagamentos e ancora) - estado: cumprido

- I2-DoD-1: registo de pagamento alimenta o ledger apos sucesso (cobre M-Pesa/e-Mola/admin/planos e o anual por delegacao), sem tocar calculo (md5 intactos; pagamento_id capturado antes). Cumprido.
- I2-DoD-2: ancora por escola em ficheiro fora da BD, atomica (temp+rename), dentro do bloqueio por escola, resiliente. Cumprido.
- I2-DoD-3: verificador deteta truncagem da cauda, adulteracao da ancora e assinala ancora ausente. Cumprido (smoke, 19 verificacoes).
- I2-DoD-4: ecra reflecte o estado da ancora; sem endpoint novo (manifesto 196 inalterado). Cumprido.
- I2-DoD-5: gate e smoke estendidos verdes; corredor completo verde. Cumprido.
- I2-DoD-6: Zero P0/P1; lancamentos (desenho de lote) adiados para o incremento 3. Cumprido.

## v12.12.18 (Ledger incr 3: lancamentos) - estado: cumprido

- I3-DoD-1: sige_fin_upsert_lancamento alimenta o ledger na criacao (fin_criar_lancamento) e na alteracao de valor (fin_actualizar_lancamento), sem tocar calculo (md5 intactos). Cumprido.
- I3-DoD-2: escrita em lote diferida por escola no shutdown via sige_ledger_append_many (um bloqueio e uma ancora por escola e por pedido); encadeamento HMAC correcto em bloco; resiliente. Cumprido (smoke, 25 verificacoes).
- I3-DoD-3: pagamentos e as 6 operacoes criticas continuam imediatos e intactos. Cumprido.
- I3-DoD-4: cadeia apos lote integra e ancora confirma; sem endpoint novo (manifesto 196 inalterado). Cumprido.
- I3-DoD-5: gate e smoke estendidos verdes; corredor completo verde. Cumprido.
- I3-DoD-6: Zero P0/P1; limite da escrita diferida documentado; despesas/creditos/fechos adiados. Cumprido.

## v12.12.19 (Ledger incr 4: despesas, creditos, fechos) - estado: cumprido

- I4-DoD-1: os 5 eventos (despesa criada e transitada, credito criado, fecho fechado e reaberto) alimentam o ledger apos sucesso, sem tocar calculo (md5 intactos; ids capturados antes). Cumprido.
- I4-DoD-2: reutilizam o escritor imediato, o encadeamento HMAC e a ancora; cadeia verificavel e integra. Cumprido (smoke, 27 verificacoes).
- I4-DoD-3: sem endpoint novo (manifesto 196 inalterado); o ecra mostra os novos eventos. Cumprido.
- I4-DoD-4: gate e smoke estendidos verdes; corredor completo verde. Cumprido.
- I4-DoD-5: Zero P0/P1; consumo de credito e edicao de despesa documentados como adiados. Cumprido.

## v12.12.21 (Fase 7 incr 1: reconciliacao e divergencias) - estado: cumprido

- F7-1-DoD-1: ecra so de leitura mostra, por escola, as tres classes de divergencia (recebido por aplicar, divergencia de montante, pagamento sem gateway) e os totais; acesso por sige_mpesa_pode_gerir; sem endpoint novo (manifesto 196). Cumprido.
- F7-1-DoD-2: logica de dominio (sige_reconciliacao_divergencias) separada da apresentacao, so leitura, fail-closed por escola, testavel. Cumprido (smoke, 12 verificacoes).
- F7-1-DoD-3: idempotencia do e-Mola confirmada (pre-verificacao por referencia + chave unica) e documentada; criterios ja cumpridos da Fase 7 fechados. Cumprido.
- F7-1-DoD-4: gate e smoke verdes; corredor completo verde; sem regressao de design (tokens). Cumprido.
- F7-1-DoD-5: Zero P0/P1; quatro-olhos documentado como adiado para o incremento 2. Cumprido.

## Actualizacao v12.12.21 (Fase 7 incremento 2)

DoD do incremento 2 cumprido: estorno e reabertura passam por pedido e aprovacao de utilizador diferente (sem auto-aprovacao); execucao com MFA do aprovador; tudo no Ledger; tabela migra; endpoint governado (197); gate e smoke verdes; corredor completo verde; Zero P0/P1. Fecha a Fase 7.

## Actualizacao v12.12.22 (release correctiva)

Esta versao e uma correccao de roteamento da Fase 7, construida sobre a v12.12.21. As views financeiro-reconciliacao (Incremento 1) e financeiro-aprovacoes (Incremento 2, regra de quatro-olhos), entregues na Fase 7 mas em falta na allowlist anti-LFI e na matriz de permissoes do admin-shell, ficavam inalcancaveis: a guarda reescrevia o pedido para o painel inicial. Foram repostas em ambas as listas, com as permissoes identicas as guardas internas de cada view. Nao ha alteracao da superficie de accao (manifesto e regras do Security Kernel mantem-se em 197), nao ha migracao de dados (SCHEMA_VERSION inalterada) e as regras de calculo financeiro nao sao tocadas.

Todos os criterios DoD-001 a DoD-018 se aplicam na integra e a entrega esta a Zero P0/P1. Reforco do criterio de roteamento: toda a rota do mapa de despacho TEM de constar da allowlist anti-LFI, agora garantido por invariante estrutural no gate de views.

## Actualizacao v12.12.24 (Fase 8 Incr 1)

Todos os criterios de DoD-001 a DoD-018 verificados para o incremento 1 da Fase 8: inventario de PII so de leitura, catalogo integro, ecra governado (allowlist, matriz, navegacao), permissao semeada com privilegio minimo, manifesto e Kernel em 197, SCHEMA inalterada, regras de calculo byte-identicas, baselines de design sem regressao, gate e smoke dedicados verdes. Rediagnostico adversarial Zero P0/P1.

## Actualizacao v12.12.24 - Fase 8 incremento 2

- DoD-001 a DoD-018 reavaliados para este incremento: dossie so de leitura e fail-closed por escola; exportacao governada em enforce (permissao, nonce, rate limit, isolamento por escola, auditoria obrigatoria); permissao com privilegio minimo; manifesto e Kernel alinhados (198 == 198); SCHEMA inalterada; tres funcoes de calculo byte-identicas; baselines de design sem regressao; zero estilo inline; zero travessoes.
- Gate e smoke dedicados (check-acesso, smoke-acesso) verdes; corredor 64/64; release gate verde a partir de pasta limpa.
- Rediagnostico adversarial: Zero P0/P1.


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

## Actualizacao v12.12.41 - Fase 3 - Reconciliacao de pagamentos digitais - reconciliacao viva

Segundo incremento da Fase 3, sobre o incremento 1 (deteccao de duplicados). Confirma as transacoes locais contra o estado real no gateway (M-Pesa via queryTransactionStatus; e-Mola via o seu endpoint de consulta, ambos ja existentes nos clientes). E so de leitura: nunca apaga, cria nem altera pagamentos nem transacoes; apenas classifica e regista divergencias para revisao humana.

Camadas: (1) nucleo puro testavel (sige_pagamentos_reconciliar_estado), que classifica em confirmadas, divergentes (valor diferente do gateway, ou rejeitada pelo gateway) e inconclusivas; (2) normalizador (sige_pagamentos_normalizar_estado_gateway) deliberadamente conservador, que so afirma confirmada num sucesso claro e falhada num codigo de falha conhecido (lista vazia por omissao, ajustavel pelo filtro sige_mpesa_codigos_falha), ficando todo o resto desconhecida, pelo que nunca acusa uma transacao real de falsa; (3) adaptador (sige_pagamentos_consultar_gateway) defensivo, que liga aos clientes reais e nunca lanca; (4) orquestracao por escola (sige_reconciliacao_viva), fail-closed e so leitura, que regista as divergencias; (5) passagem diaria no cron sige_evento_diario, guardada pelo modo de teste, limitada e so corre se algum gateway estiver configurado.

Impacto na postura: aditivo e so de leitura. Nao toca em regras de calculo nem em ficheiros canonicos. Sem nova superficie de accao (199, enforce 33; views 60; o cron liga-se a um evento ja existente, sem novo gancho), sem opcoes novas (132), sem dependencias externas novas (11), sem alteracao de esquema, calculo academico e financeiro byte-identico. A accao (conciliar ou rejeitar) fica para um incremento de escrita com quatro-olhos; o ajuste fino dos codigos de falha depende do sandbox das operadoras.

## Actualizacao v12.12.42 - Fase 3 - Reconciliacao de pagamentos digitais - resolucao com escrita (quatro-olhos)

Terceiro incremento da Fase 3, sobre o incremento 1 (deteccao de duplicados) e o incremento 2 (reconciliacao viva). Torna a reconciliacao accionavel sob controlo duplo: conciliar uma transacao confirmada, ou rejeitar uma rejeitada pelo gateway, atraves do framework de quatro-olhos ja existente (sige_fin_aprovacao_*). Um utilizador propoe (maker) e um SEGUNDO utilizador autorizado aprova (checker); so entao a accao e executada. A separacao de funcoes (maker diferente de checker) e imposta pelo framework.

A escrita financeira passa SEMPRE pelo caminho canonico sige_fin_registar_pagamento; este incremento nunca calcula valores nem altera regras financeiras. A execucao re-valida o estado da transacao no momento da aprovacao (pode ter mudado entre a proposta e a decisao), e idempotente, respeita o tenant e e fail-closed; a rejeicao nunca toca numa transacao ja conciliada. Os dois tipos novos (recon_conciliar, recon_rejeitar) reutilizam a permissao existente financeiro.mobile_payments_gerir, pelo que nao ha nova permissao nem nova regra do kernel. A UI do maker e so server-side na vista de gestao; o checker usa a fila generica de aprovacoes ja existente. Os botoes directos de conciliar e rejeitar continuam intactos.

Impacto na postura: aditivo e controlado. Sem nova superficie de accao (199, enforce 33; a UI do maker e server-side e o checker reutiliza accoes existentes), sem nova vista (60; a vista de gestao foi estendida, nao criada), sem opcoes novas (132), sem dependencias externas novas (11), sem alteracao de esquema, calculo academico e financeiro byte-identico. A seguir: ajuste fino dos codigos de falha do gateway (depende do sandbox) e, opcionalmente, escolha de lancamento especifico na proposta de conciliacao.

## Actualizacao v12.12.43 - Fase 3 - Afinacao dos codigos do gateway e escolha de lancamento

Dois afinamentos entregues juntos, sobre os incrementos 1 a 3 da Fase 3.

(A) Afinacao dos codigos de falha do gateway. O normalizador da reconciliacao viva passa a dar prioridade ao estado da transacao reportado pelo gateway (o queryTransactionStatus do M-Pesa devolve-o em output_ResponseTransactionStatus). Um INS-0 apenas diz que a consulta foi processada, nao que a transacao teve sucesso; por isso, uma transacao com estado Failed deixa de ser tomada por confirmada. As listas de estados de sucesso e de falha sao ajustaveis pelos filtros sige_mpesa_estados_sucesso e sige_mpesa_estados_falha (defaults sensatos em ingles e portugues); a lista de codigos de resposta de falha continua ajustavel por sige_mpesa_codigos_falha. Mantem-se o conservadorismo: um estado nao mapeado fica desconhecida e nunca acusa uma transacao real de falsa. A mudanca e retro-compativel: sem campo de estado, o comportamento e o anterior (por codigo de resposta).

(B) Escolha de lancamento especifico na proposta de conciliacao com quatro-olhos. A vista de gestao passa a carregar os lancamentos em aberto do aluno e a oferecer um seletor na proposta; a funcao de execucao ja validava e usava o lancamento escolhido pelo caminho canonico. Mantem-se a opcao de correspondencia automatica.

Impacto na postura: aditivo e mais exacto. Sem nova superficie de accao (199, enforce 33), sem nova vista (60; a vista de gestao foi estendida), sem opcoes novas (132; a afinacao e por filtro, nao por opcao), sem dependencias externas novas (11), sem alteracao de esquema, calculo academico e financeiro byte-identico. O ajuste fino final dos codigos depende do sandbox das operadoras.

## Actualizacao v12.12.44 - Fase 3 - Ajuste fino dos codigos e estados por operadora

Ajuste fino final da reconciliacao viva: a classificacao de uma transacao contra o gateway passa a ser POR OPERADORA. Nova funcao sige_pagamentos_mapa_estados_gateway(provider) que devolve, para cada provider, as listas de estados de sucesso e de falha e os codigos de resposta de falha. O normalizador sige_pagamentos_normalizar_estado_gateway passa a receber o provider e a usar esse mapa, e o adaptador passa o provider (mpesa ou emola) ao normalizador.

Defaults fundamentados na documentacao publica: o M-Pesa (Vodacom, OpenAPI) reporta o estado da transacao em ResponseTransactionStatus (ex.: Completed) e um INS-0 confirma apenas a consulta, pelo que os codigos de resposta de falha ficam vazios por omissao (a falha e expressa pelo estado); o e-Mola (Movitel) mantem-se conservador com termos genericos enquanto a API de consulta nao esta documentada. As tres listas sao sobreponiveis por filtro COM o provider como contexto (sige_mpesa_estados_sucesso, sige_mpesa_estados_falha e sige_mpesa_codigos_falha recebem agora um segundo argumento, o provider), permitindo afinacao por operadora num unico filtro e garantindo isolamento: um codigo ou estado de falha de uma operadora nao contamina a outra.

Impacto na postura: aditivo e mais exacto, por operadora. Mantem-se o conservadorismo (estado nao mapeado -> desconhecida) e a retro-compatibilidade (chamada sem provider usa os defaults comuns; filtros antigos de um argumento continuam a funcionar). Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132; a afinacao e por filtro), sem dependencias externas novas (11), sem alteracao de esquema, calculo academico e financeiro byte-identico. O ajuste fino definitivo dos valores exactos depende do sandbox de cada operadora; a estrutura por operadora ja esta pronta para os receber.
