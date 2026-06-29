# SECRETS OPTIONS REGISTER - v12.12.59

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

## Actualizacao v12.12.24 (Fase 8 Incr 1)

Sem novos segredos. O Secret Vault nao e tocado. A unica opcao do WordPress afectada e sige_permissions_engine_version, elevada para 12.12.23 pela migracao idempotente de permissoes, que apenas regista a nova permissao e a concede aos perfis de administracao e direccao.

## Actualizacao v12.12.24 - Fase 8 incremento 2

- Nenhum segredo novo e nenhuma opcoes nova introduzidos por este incremento.
- Secret Vault inalterado.


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

## Actualizacao v12.12.45 - Fase 4 incr 1 - Self-host das bibliotecas e infra de enqueue

Incremento 1 da Fase 4 (CSP e front-end seguro). As sete bibliotecas de front-end que vinham de CDN (Chart.js, xlsx, exceljs, Sortable, qrious, FileSaver e html5-qrcode) passam a ser servidas pela propria instalacao a partir de assets/vendor/, com SRI (integrity) recalculado a partir dos ficheiros locais. O catalogo central includes/cdn-scripts.php (sige_cdn_catalog) passa a construir as URLs a partir de SIGE_URL, sem literais https:// de CDN no codigo; os treze ecrans que chamam sige_cdn_script continuam a funcionar sem alteracao, agora a carregar localmente. A referencia directa a unpkg na portaria e o fallback do bootstrap tambem passam a local.

Novo includes/assets-registry.php: registador central que regista as bibliotecas locais como handles do WordPress (sige_assets_libs, sige_assets_base_url, sige_assets_registar no admin_enqueue_scripts, sige_enqueue_lib) e injecta SRI (integrity e crossorigin) nas tags enfileiradas, atraves do filtro script_loader_tag. E a base para a migracao dos blocos inline para wp_enqueue nas vagas seguintes e para o CSP em enforcement.

Governanca: o scan passa a excluir assets/vendor/, por o codigo vendorizado ser first-party self-hosted e nao uma chamada externa nossa. Com isso e a remocao dos literais de CDN, as dependencias externas descem de 11 para 9 (cdnjs e unpkg removidos); os hosts restantes sao api.z-api.io, fonts.googleapis.com, purl.org, rmbjconsulting.com, schemas.openxmlformats.org, softgenial.edu.mz, ui-avatars.com, wa.me e www.w3.org. Sem nova superficie de accao (199, enforce 33; o registo usa o hook admin_enqueue_scripts, ja rastreado), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo academico e financeiro byte-identico. Esta e a camada habilitadora: nao muda o comportamento dos ecrans.

## Actualizacao v12.12.46 - Fase 4 incr 2 - Migracao de inline (vaga 1) e catraca

Segundo incremento da Fase 4 (CSP e front-end seguro): comeca a migracao do front-end inline para fora do HTML, por vagas, com um mecanismo de delegacao e um gate de catraca.

Mecanismo: novo despachante declarativo data-sige-act em assets/sige-ui.js (ja enfileirado nas paginas sige-app, junto do enhancer data-sige-confirm ja existente). Um unico ouvinte delegado dispara a funcao global indicada em data-sige-act, passando data-sige-arg como argumento ou, na sua ausencia, o proprio elemento (equivalente ao this do onclick). E reutilizavel e permite remover onclick das views por vagas, sem JS por ecra e sem alterar a logica dos handlers.

Primeira vaga: admin/finance/financeiro-lancamentos-view.php passa os seus dez onclick para data-sige-act (exportacao, abrir e fechar modais de detalhe, anulacao, isencao e reactivacao). Os handlers que recebiam um id passam-no por data-sige-arg; o de detalhes recebe o elemento. Apenas muda a forma de ligar o clique a funcao; nenhuma logica de handler nem regra financeira e tocada, e os ficheiros canonicos ficam intactos.

Catraca: novo gate tools/check-inline-frontend.php conta as ocorrencias de onclick=, style=" e blocos <script> sem src nas views e includes e exige que nunca aumentem face a baseline (onclick 260 -> 250; style 2191; script 81). Cada vaga futura baixa estes maximos, nunca os sobe. Impacto na postura: aditivo, mais seguro no navegador, sem alteracao de superficie (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem dependencias novas (9), sem alteracao de esquema, calculo academico e financeiro byte-identico.

## Actualizacao v12.12.47 - Fase 4 incr 2 vaga 2 - Estilos academicos para utilitarios

Segunda vaga do incremento 2 da Fase 4. Sequencia a vaga 1 (v12.12.46), que introduziu o despachante declarativo data-sige-act e migrou os onclick da area financeira. Esta vaga migra o estilo inline da area academica para classes utilitarias.

Novo assets/sige-utilities.css com doze utilitarios: alinhamento (esquerda, centro, direita), peso de letra (700, 600, 500), margin a zero, flex-shrink a zero, flex a um, white-space nowrap, overflow-x auto e width a 100 por cento. Cada utilitario leva !important para replicar a especificidade do estilo inline (1000) e evitar regressao de cascata. O ficheiro e enfileirado no admin-shell, dependente do sige-design-system, nas paginas do SIGE.

Migracao conservadora aplicada a dez vistas academicas (abertura, acta, alunos, aprovar notas, auditoria de notas, boletim, encerramento, estatisticas demograficas, matriz e turmas): 49 trocas, so em atributos style 100 por cento compostos por declaracoes seguras, sem PHP interpolado e sem class ja existente no elemento. Os casos com class existente e os display ficam para vagas futuras, para nao arriscar fusao de classes nem alternancia por JavaScript. Outros atributos do elemento (colspan, method, etc.) sao preservados.

A catraca check-inline-frontend desce o maximo de style de 2191 para 2142, travando esta reducao; onclick mantem-se em 250 e blocos script em 81. O mecanismo foi consolidado: existe um unico gate de catraca (o duplicado foi removido) e o smoke smoke-inline-frontend passou a estar registado no corredor. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo academico e financeiro byte-identico. Dependencias externas mantem-se em 9. Sem mudanca de comportamento visual: o aspecto e o mesmo, agora a partir de classes reutilizaveis.

## Actualizacao v12.12.48 - Fase 4 incr 2 vaga 3 - Estilos financeiros para utilitarios

Terceira vaga do incremento 2 da Fase 4. Sequencia a vaga 1 (v12.12.46, despachante data-sige-act e onclick financeiros) e a vaga 2 (v12.12.47, estilos academicos). Reutiliza o assets/sige-utilities.css introduzido na vaga 2 e migra agora o estilo inline da area financeira.

Migracao conservadora de 32 atributos style em sete vistas financeiras (config, devedores, gerador, pagamentos, planos, relatorio mensal e mpesa), so quando o style e 100 por cento composto por declaracoes do mapa seguro, estaticas, sem PHP interpolado e sem class ja existente no elemento. Os casos com class continuam por migrar, por desenho, para nao arriscar fusao de classes. Outros atributos do elemento sao preservados.

A catraca check-inline-frontend desce o maximo de style de 2142 para 2110, travando a reducao; onclick mantem-se em 250 e blocos script em 81. Uma das trocas move uma guarda de scroll de tabela (style overflow-x auto) para a classe sige-u-oxa em financeiro-pagamentos; para nao ler isso como tabela a estourar, o diag-responsivo (ja na vaga 2) e agora tambem o smoke-regression-pack passam a contar a classe sige-u-oxa como guarda valida, mantendo a deteccao de tabelas genuinamente desprotegidas. O smoke da catraca foi estendido as vistas financeiras.

Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9. Sem mudanca de comportamento visual.

## Actualizacao v12.12.49 - Fase 4 incr 2 - Fixe de impressao utilitarios e vaga admin-shell e jardim

Duas partes no mesmo incremento.

PARTE A - correccao de impressao das classes utilitarias. As vagas 2 (v12.12.47) e 3 (v12.12.48) migraram estilos inline para classes utilitarias (sige-u-), que so resolvem onde o assets/sige-utilities.css esta carregado, ou seja na shell do admin. Cinco vistas, porem, abrem janelas de impressao autonomas (window.open mais document.write) que clonam ou geram marcacao com essas classes e levam o seu proprio <style>, sem o sige-utilities.css. No impresso, as classes ficavam sem efeito (alinhamentos e pesos perdidos). Introduziu-se o helper sige_utilities_inline_css() em includes/assets-registry.php, que devolve as 12 regras com !important, e embebeu-se no <style> de cada popup afectado: boletim, alunos_lista, turmas (dois popups), financeiro-devedores e, por uniformidade defensiva, estatisticas (cujo clone ja remove o atributo class). Uma guarda nova no smoke-inline-frontend obriga a esse contrato: qualquer vista com popup de impressao e classes sige-u- tem de embeber o helper. Isto trava regressoes futuras.

PARTE B - vaga admin-shell e jardim. Migracao conservadora de 59 estilos inline para classes utilitarias: includes/admin-shell.php (55, sobretudo icones com flex-shrink) e admin/jardim/jardim_relatorio-view.php (4), ambos sem popup. Ficheiros que produzem saida autonoma (includes/documents-engine.php com geracao de PDF/HTML, includes/portal-logic.php com vista de portal, admin/jardim/jardim_boletim-view.php com popup de impressao) e ficheiros canonicos ficam de fora, por desenho, porque a saida deles nao carrega o sige-utilities.css.

A catraca check-inline-frontend desce o maximo de style de 2110 para 2051; onclick mantem-se em 250 e blocos script em 81. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9. Sem mudanca de comportamento visual.

## Actualizacao v12.12.50 - Fase 4 incr 2 - Onclick para data-sige-act (vistas limpas)

Inicio da migracao dos manipuladores de evento inline (onclick) para o despachante declarativo data-sige-act, a caminho de poder impor o CSP. Sequencia a vaga 1 (v12.12.46, que criou o despachante e tratou os onclick do financeiro-lancamentos).

Primeiro, o despachante em assets/sige-ui.js passa a ter um contrato fiel ao onclick que substitui:
- data-sige-arg chama fn(arg) (substitui onclick com um argumento de texto);
- data-sige-noargs chama fn() (sem passar nada, para funcoes cujo 1o parametro e significativo, por exemplo plToggleNovoPlano(force));
- caso contrario chama fn(elemento), equivalente ao this.
O marcador data-sige-noargs e novo e mantem total retro-compatibilidade: as conversoes da vaga 1 (que nao o usam) continuam a receber o elemento, exactamente como antes.

Depois, converteram-se 49 onclick SEGUROS (sem risco de tipo nem de contexto) em dez vistas sem popup e nao-autonomas: padrao fn() (42, agora com data-sige-noargs), fn(this) (6) e fn('texto') (1). Vistas com janela de impressao autonoma (onde o despachante nem carrega), templates de PDF, portal e ficheiros canonicos ficam de fora, por desenho. Os onclick com numeros, multiplos argumentos ou PHP interpolado ficam para vagas com despachante mais rico.

A catraca check-inline-frontend desce o maximo de onclick de 250 para 201, travando a reducao; o gate passa tambem a exigir o contrato data-sige-arg/data-sige-noargs/elemento no despachante. style mantem-se em 2051 e blocos script em 81. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9. Sem mudanca de comportamento.

## Actualizacao v12.12.51 - Fase 4 incr 2 - Onclick complexos para data-sige-args (despachante mais rico)

Migracao dos onclick COMPLEXOS (numeros, booleanos, multiplos argumentos e PHP interpolado) para o despachante declarativo, com um despachante mais rico. Sequencia as vagas de onclick anteriores (v12.12.46 criou o despachante; v12.12.50 tratou os onclick simples).

Primeiro, o despachante em assets/sige-ui.js ganha duas capacidades novas e aditivas:
- data-sige-args: recebe uma lista JSON e chama fn aplicando esses argumentos, preservando tipos (numeros, booleanos, varios argumentos). O JSON e lido com try/catch, pelo que um valor invalido nao dispara a funcao.
- data-sige-self: em conjunto com data-sige-args, anexa o proprio elemento como ultimo argumento (substitui o padrao onclick="fn('x', this)").
As regras anteriores (data-sige-arg para fn(arg), data-sige-noargs para fn() e o caso por omissao fn(elemento)) ficam inalteradas, mantendo total retro-compatibilidade.

Depois, converteram-se 20 onclick complexos em oito vistas limpas: PHP de um argumento (data-sige-arg), PHP de dois argumentos id mais nome (data-sige-args com wp_json_encode), PHP string ou id mais this (data-sige-args mais data-sige-self), literais booleanos (data-sige-args preservando o tipo) e uma string codificada. Para o PHP, o atributo e construido com esc_attr(wp_json_encode([...])), o que escapa correctamente aspas, e comercial, sinais de maior e menor e acentos, sendo o JSON.parse no navegador fiel ao valor original (validado por simulacao de renderizacao).

As expressoes inline (window.print, this.style, this.classList, IIFE, jQuery, window.open(this.href), window.location) e os onclick gerados em template JS NAO se convertem nesta vaga: ficam para a vaga de funcoes nomeadas. Uma chamada de quatro argumentos fica diferida (o conversor cobre ate dois argumentos mais this).

A catraca check-inline-frontend desce o maximo de onclick de 201 para 181; o gate passa tambem a exigir o contrato rico data-sige-args/data-sige-self/JSON no despachante. style mantem-se em 2051 e blocos script em 81. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9. Sem mudanca de comportamento.

## Actualizacao v12.12.52 - Fase 4 incr 2 - Expressoes inline em onclick para funcoes nomeadas

Conversao das EXPRESSOES inline em onclick para funcoes nomeadas globais, ligadas pelo despachante data-sige-act. Sequencia as vagas anteriores (v12.12.46 criou o despachante; v12.12.50 tratou os onclick simples; v12.12.51 tratou os complexos com argumentos). Estes onclick nao eram chamadas de funcao com argumentos, eram logica embutida (imprimir, alternar classe ou estilo do elemento, abrir ou fechar o menu lateral movel, fechar um aviso).

Acrescentaram-se sete funcoes nomeadas em assets/sige-ui.js, expostas em window para o despachante as encontrar via window[accao]:
- sigeImprimirPagina: window.print (data-sige-noargs).
- sigeAlternarQuebraTexto(el): alterna o whiteSpace do elemento.
- sigeAlternarClasseProximo(el): alterna a classe do elemento seguinte.
- sigeFecharPopupBackdrop(el): fecha o aviso ao subir ate ao backdrop.
- sigeAlternarSidebar (hamburguer, data-sige-noargs), sigeFecharSidebar(el) (sobreposicao) e sigeAlternarSidebarMais(el) (botao de mais opcoes, com gestao do aria-expanded): menu lateral movel do shell.
Cada funcao e fiel a expressao que substitui e recebe o elemento (equivalente ao this) quando a expressao o usava.

Converteram-se 10 onclick-expressao em cinco vistas limpas: abertura (imprimir, alternar grupo de alunos), encerramento (imprimir), pagamentos por turma (imprimir), auditoria de notas (alternar quebra de texto, duas ocorrencias) e o shell admin (menu lateral movel e fecho de aviso, quatro ocorrencias).

As expressoes em ficheiros com janela autonoma (que nem carregam o despachante, por exemplo paginas de impressao com history.back ou window.close), os onclick com window.open(this.href) ou window.location (que envolvem navegacao), as cadeias jQuery e IIFE, os onclick gerados em template JS, e os ficheiros canonicos ficam de fora desta vaga.

A catraca check-inline-frontend desce o maximo de onclick de 181 para 171; style mantem-se em 2051 e blocos script em 81. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9. Sem mudanca de comportamento.

## Actualizacao v12.12.53 - Fase 4 incr 2 - Onclick de navegacao e template JS para funcoes nomeadas

Conversao dos onclick de NAVEGACAO e de TEMPLATE JS para funcoes nomeadas, e introducao do data-sige-prevent. Sequencia as vagas anteriores (v12.12.46 a v12.12.52).

Primeiro, o despachante em assets/sige-ui.js ganha o marcador data-sige-prevent, que chama ev.preventDefault() antes de disparar a funcao, substituindo o return false de onclick em links (por exemplo abrir o recibo numa janela sem navegar a pagina). E aditivo: as regras anteriores (data-sige-args, data-sige-arg, data-sige-noargs, data-sige-self e o caso por omissao) ficam inalteradas.

Acrescentaram-se duas funcoes nomeadas globais em assets/sige-ui.js:
- sigeAbrirReciboJanela(el): abre o href do proprio link numa janela de recibo.
- sigeIrPara(url): navega para um endereco (por exemplo mailto).

Depois, converteram-se sete onclick em quatro vistas limpas:
- Dois links de recibo no portal do aluno: window.open(this.href) com return false, agora sigeAbrirReciboJanela mais data-sige-prevent, mantendo o href para acessibilidade.
- Um botao de contacto por mailto no aviso de cobranca do hub: window.location, agora sigeIrPara com o endereco em data-sige-arg construido com esc_attr.
- Dois botoes que ja chamavam a funcao global window.sigeHubBillSnooze: agora data-sige-act mais data-sige-noargs.
- Dois onclick gerados em template JS (delCriterio com o id e o elemento, e removerDaMatriz com o id): reescritos para data-sige-args com a interpolacao do lado do JS preservada (data-sige-self no primeiro).

As cadeias jQuery e IIFE (fecho de modal de clonagem, reabertura de painel de pendentes), os onclick em ficheiros com janela autonoma ou em popups, e os ficheiros canonicos ficam de fora desta vaga.

A catraca check-inline-frontend desce o maximo de onclick de 171 para 164; o gate passa tambem a exigir o contrato data-sige-prevent no despachante. style mantem-se em 2051 e blocos script em 81. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9. Sem mudanca de comportamento.

## Actualizacao v12.12.54 - Fase 4 incr 2 - Cadeias jQuery e IIFE em onclick reescritas em vanilla

Reescrita das ULTIMAS cadeias jQuery e IIFE em onclick para vanilla, em funcoes nomeadas. Conclui a migracao dos onclick em ficheiros limpos (sem janela autonoma, sem popup e nao canonicos): a partir desta versao, nao resta nenhum onclick jQuery ou IIFE nessas vistas. Sequencia as vagas anteriores (v12.12.46 a v12.12.53).

Converteram-se dois onclick, cada um numa vista, com a funcao nomeada definida na propria vista (co-localizada com o uso, em vez de poluir o kit global com selectores especificos), exposta em window para o despachante a encontrar via window[accao]:
- Central de WhatsApp: o botao de tentar novamente, gerado em template JS, usava uma funcao imediatamente invocada que fecha e reabre o painel de pendentes (um elemento details) apos um instante; passou a data-sige-act mais data-sige-noargs, com a funcao sigeReabrirPainelPendentes a fazer o mesmo em vanilla.
- Matriz curricular: o botao de cancelar do modal de clonagem usava uma cadeia jQuery (esconder o modal, marcar aria-hidden e, se nenhum modal estiver visivel, remover a classe do corpo); passou a data-sige-act mais data-sige-noargs, com a funcao sigeFecharModalClone a fazer o mesmo em vanilla, reproduzindo o teste de visibilidade do jQuery por offsetWidth, offsetHeight e getClientRects.

Os onclick que restam estao em ficheiros com janela autonoma, em popups ou em ficheiros canonicos, onde o despachante nao se aplica; ficam para abordagem propria.

A catraca check-inline-frontend desce o maximo de onclick de 164 para 162; style mantem-se em 2051 e blocos script em 81. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9. Sem mudanca de comportamento.

## Actualizacao v12.12.55 - Fase 4 incr 2 - Infraestrutura de nonce CSP e nonce nas tags script inline

Introducao da infraestrutura de nonce CSP e aplicacao do nonce as tags script inline das vistas administrativas, a caminho de poder impor o CSP (Content Security Policy). Sequencia as vagas de onclick (v12.12.46 a v12.12.54) e abre a frente dos blocos script.

Acrescentou-se ao core-helpers (carregado incondicionalmente no bootstrap, disponivel em todos os pedidos onde o plugin carrega):
- sige_csp_nonce: nonce unico por pedido, gerado uma so vez e reutilizado durante o pedido (base64 de bytes aleatorios, com recurso a wp_generate_password se random_bytes nao estiver disponivel).
- sige_csp_script_attr: devolve o atributo nonce ja escapado para uma tag script inline, para uso como <script <?php echo sige_csp_script_attr(); ?>>.

Acrescentar o nonce as tags e inocuo enquanto o cabecalho CSP nao for activado: os navegadores ignoram o nonce sem uma politica CSP. Nao ha alteracao de comportamento.

Converteram-se 55 tags script inline para a forma com nonce: 54 tags ao nivel do template (do tipo script seguido de quebra de linha, nas vistas academicas, financeiras, de jardim, de sistema, de WhatsApp, de recursos humanos e nos includes de contexto administrativo, incluindo o admin-shell), mais um bloco de uma so linha no admin-shell que trata as etiquetas do menu lateral.

Por desenho ficaram de fora, para abordagem propria: os redireccionamentos construidos por echo (echo de uma tag script com window.location), as paginas autonomas (modelos PDF de pauta e boletim, boletim do jardim, motor de documentos, pagina segura da camara da portaria), o portal publico, o gerador de tags CDN e o ficheiro canonico finance-core, alem da pagina de login (pre-autenticacao, anterior ao carregamento do auxiliar).

Os dois verificadores de JavaScript embebido nas vistas passaram a normalizar a tag com nonce antes da extraccao, para que o node continue a validar a sintaxe dos blocos sem tropecar no PHP do proprio tag (so na copia em memoria; os ficheiros nao mudam).

A catraca check-inline-frontend desce o maximo de blocos script de 81 para 25 e passa a exigir que a infraestrutura de nonce exista (sige_csp_nonce e sige_csp_script_attr no core-helpers); onclick mantem-se em 162 e style em 2051. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9.

## Actualizacao v12.12.56 - Fase 4 incr 2 - Nonce CSP nos redireccionamentos echo

Aplicacao do nonce CSP aos redireccionamentos construidos por echo (echo de uma tag script com window.location ou location) nas vistas autenticadas. Continua a vaga v12.12.55 (nonce nas tags script ao nivel do template) e usa a mesma infraestrutura de nonce do core-helpers (sige_csp_nonce e sige_csp_script_attr).

A conversao e por concatenacao em string, mantendo o atributo nonce no proprio tag emitido: uma tag passa de echo de script para echo de script com o auxiliar concatenado, produzindo script com nonce. Acrescentar o nonce e inocuo enquanto o cabecalho CSP nao for activado: os navegadores ignoram o nonce sem uma politica CSP, pelo que nao ha alteracao de comportamento e os redireccionamentos continuam a funcionar como antes.

Converteram-se 11 redireccionamentos em 6 vistas: dashboard (2), disciplinas (2, com aspas duplas), encerramento (2), financeiro-config (3), notas (1) e transporte (1). Trataram-se os dois tipos de aspa (simples e dupla) com a concatenacao correspondente.

A conversao so altera a tag de abertura, nunca o conteudo extraido pelos verificadores de JavaScript embebido nas vistas: o motor de extraccao absorve a concatenacao ate ao fecho do tag e o conteudo apos o tag mantem-se igual, pelo que os dois verificadores se mantem verdes sem qualquer alteracao.

Por desenho ficaram de fora, para abordagem propria: as paginas autonomas (modelos PDF de pauta e boletim, boletim do jardim, motor de documentos, pagina segura da camara da portaria), que emitem HTML proprio e merecem cabecalho CSP proprio; o portal publico; o ficheiro canonico finance-core; a pagina de login (pre-autenticacao); e o popup construido por document.write em financeiro-extratos.

A catraca check-inline-frontend desce o maximo de blocos script de 25 para 14; onclick mantem-se em 162 e style em 2051. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9.

## Actualizacao v12.12.57 - Fase 4 incr 2 - Nonce CSP nas paginas autonomas

Aplicacao do nonce CSP as tags script ao nivel do template das paginas autonomas (modelos PDF de pauta e boletim, boletim do jardim, motor de documentos, pagina segura da camara da portaria). Conclui a aplicacao do nonce a todas as tags script inline alcancaveis: apos esta vaga, todos os scripts inline convertiveis (vistas no shell, redireccionamentos por echo e paginas autonomas) levam o nonce por pedido. Usa a mesma infraestrutura do core-helpers (sige_csp_nonce e sige_csp_script_attr).

Acrescentar o nonce e inocuo: nestas paginas ainda nao ha um CSP que referencie o nonce, e no motor de documentos, que ja envia um CSP, esse CSP usa unsafe-inline sem nonce na directiva, pelo que o unsafe-inline continua activo e os scripts correm como antes (acrescentar o atributo nonce nao altera o comportamento).

Converteram-se 7 tags em 5 ficheiros: pauta-pdf-template (1), boletim-pdf-template (1), jardim_boletim-view (1), documents-engine (3) e portaria-camera-safe-page (1).

Os cabecalhos de seguranca existentes nao foram tocados: o motor de documentos mantem os seus tres cabecalhos Content-Security-Policy e a pagina da camara mantem o seu Permissions-Policy.

Os modelos PDF em admin sao varridos pelos verificadores de JavaScript embebido, que ja normalizam a tag com nonce desde a v12.12.55, pelo que se mantem verdes. O motor de documentos e a pagina da camara estao em includes, fora do alcance desses verificadores.

Nota para o passo seguinte: estas paginas tambem usam onclick inline (botoes de impressao), que um CSP estrito baseado em nonce bloqueia; impor o CSP nestas paginas exige primeiro converter esses onclick, pelo que o enforcement do CSP fica para incremento dedicado.

A catraca check-inline-frontend desce o maximo de blocos script de 14 para 7; onclick mantem-se em 162 e style em 2051. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9.

## Actualizacao v12.12.58 - Fase 4 incr 2 - onclick de impressao das autonomas em addEventListener

Conversao dos onclick de impressao das paginas autonomas para addEventListener, preparando o caminho para o CSP enforce nessas paginas. Na v12.12.57 todas as tags script inline alcancaveis passaram a levar nonce; faltava remover os handlers inline (onclick), que um CSP estrito baseado em nonce bloqueia.

Como estas paginas nao carregam o despachante global, a ligacao e feita por addEventListener local dentro do script ja com nonce de cada pagina. Converteram-se 13 handlers em 4 paginas:
- pauta-pdf-template: 2 (imprimir e fechar)
- boletim-pdf-template: 2 (imprimir e fechar)
- jardim_boletim-view: 2 (imprimir todos e imprimir um; o imprimir um passa o indice por atributo data e e reconvertido a numero com parseInt, para preservar o tipo esperado pela funcao)
- documents-engine: 7 (imprimir e fechar nos tres caminhos de render)

A pagina da camara da portaria ja nao tinha handlers inline.

Os botoes passam a usar atributos data (data-sige-print, data-sige-close, data-sige-print-all, data-sige-print-one) e a ligacao addEventListener corre ao nivel de topo do script, fora dos IIFE, para correr mesmo quando o IIFE de QRious retorna cedo por falta da biblioteca. O comportamento e identico: os botoes continuam a imprimir, fechar e imprimir boletins como antes.

Nenhum cabecalho de seguranca existente foi tocado: o motor de documentos mantem os seus tres cabecalhos Content-Security-Policy e a camara o seu Permissions-Policy. O CSP enforce e a substituicao de unsafe-inline por nonce ficam para o incremento seguinte.

Os modelos PDF em admin sao varridos pelos verificadores de JavaScript embebido, que se mantem verdes com a nova ligacao.

A catraca check-inline-frontend desce o maximo de onclick de 162 para 149; style mantem-se em 2051 e blocos script em 7. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9.

## Actualizacao v12.12.59 - Fase 4 incr 2 - CSP enforce com nonce nas paginas autonomas

Primeiro CSP enforce com nonce, nas paginas autonomas. Conclui a sequencia de preparacao (nonce em todos os scripts inline na v12.12.55 a v12.12.57, onclick de impressao das autonomas em addEventListener na v12.12.58) impondo agora um script-src baseado em nonce nessas paginas.

Esta vaga altera comportamento, e a primeira que o faz nesta frente: o script-src deixa de aceitar inline arbitrario e passa a exigir o nonce por pedido. Como pre-requisito ja verificado, todas estas paginas tem zero handlers inline, zero URLs javascript, todos os scripts inline com nonce e todos os scripts externos servidos da propria origem (QRious e html5-qrcode em assets/vendor do plugin), pelo que o script-src self mais nonce nao bloqueia nada. Confirmou-se ainda que o nonce do cabecalho casa exactamente com o nonce das tags, porque o esc_attr nao altera o base64.

Alteracoes por pagina:
- Motor de documentos: os tres cabecalhos Content-Security-Policy passam o script-src de unsafe-inline para self mais nonce por pedido, mantendo style-src unsafe-inline para os estilos inline e img-src self data https para as imagens.
- Modelos PDF de pauta e boletim: passam a enviar um cabecalho CSP com nonce no respectivo handler, antes do output (default-src self, script-src self mais nonce, style-src self unsafe-inline, img-src self data, font-src self data).
- Boletim do jardim: envia o seu cabecalho no topo da vista, protegido por headers_sent, com a mesma politica dos modelos PDF.
- Pagina segura da camara da portaria: acrescenta o CSP a sua funcao de cabecalhos ja existente, que so corre no pedido da camara, com connect-src self para a validacao via admin-ajax, media-src self para os audios e img-src self data https para as fotos.

Cada CSP esta isolado a sua pagina, sem fuga para outras (a funcao da camara guarda o pedido; os handlers so correm na impressao; o jardim e o motor de documentos so emitem nas suas paginas).

Acrescentou-se a catraca check-inline-frontend uma asserccao que exige script-src baseado em nonce nestas paginas e proibe a regressao a unsafe-inline no script-src.

Sem alteracao de cabecalhos de seguranca de outras paginas, sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9 (tudo self). onclick mantem-se em 149, style em 2051 e blocos script em 7.
