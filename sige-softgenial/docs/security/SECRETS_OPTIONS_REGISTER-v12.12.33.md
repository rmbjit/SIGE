# SECRETS OPTIONS REGISTER - v12.12.33

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
