# RISK_REGISTER - v12.12.43 - MFA de Operacao (correccao pos-rediagnostico)

## Bloqueadores

| Severidade | Risco | Estado |
|---|---|---|
| P0 | Nenhum. | - |
| P1 | Nenhum. | - |

## Achados do rediagnostico (todos fechados nesta versao)

| ID | Achado | Resolucao |
|---|---|---|
| A1 (P2) | Formulario do codigo nao uniforme entre operacoes. | Corrigido: formulario nas 10 operacoes (inline em POST, aviso em GET, sem duplicacao). |
| A2 (P3) | Bypass de MFA sob falha de SMTP. | Corrigido: modo estrito opt-in que bloqueia; defeito anti-lockout explicito. |
| A5 (P3) | Operacoes pela janela nao auditadas. | Corrigido: auditoria satisfied_window por operacao. |
| A7 (P3) | Em-dashes em documentos historicos do pacote. | Corrigido: pacote a zero em/en-dashes; gate passa a verificar documentos. |
| A3 (P3) | Fail-open por function_exists. | Documentado como design defensivo (fail-closed congelaria a financa se faltasse o ficheiro); coberto pelo gate. |
| A4 (P3) | Rate limit do endpoint em observe. | Documentado: tecto de 5 tentativas do OTP e o controlo activo; nao praticavel forcar 6 digitos. |
| A6 (P3) | Janela por utilizador, nao por escola. | Documentado: semantica sudo correcta; o tenant e garantido por guard separado. |

## Residuais nao bloqueadores (roteiro)

| Severidade | Risco residual | Fase futura |
|---|---|---|
| P2 | Reposicao automatica da operacao apos confirmacao (hoje o utilizador repete manualmente). | Incremento seguinte da Fase 4. |
| P2 | So ha entrega por email; sem TOTP nem aplicacao autenticadora. | Incremento seguinte da Fase 4. |
| P3 | Ledger financeiro: incremento 1 entregue (operacoes criticas); pagamentos/lancamentos e ancoragem em incrementos seguintes. | Fase 6 (em curso). |

## Notas

A versao mantem-se desligada por defeito e sem alteracao de comportamento em instalacoes que nao a liguem. Nenhuma regra de calculo financeiro ou academico foi tocada.

## v12.12.11 (incremento TOTP)

- A2 (dependencia de email para autorizar a operacao): FECHADO para utilizadores inscritos em TOTP (confirmam com o codigo da aplicacao, sem email). Severidade residual P3 para quem nao inscrever (mitigado pelo modo estrito opt-in da v12.12.10.1).
- P2 (A-T6): codigo TOTP reutilizavel dentro da validade. Aceite; endurecimento futuro (contador de uso unico).
- P2 (A-T7): desactivar TOTP nao exige step-up. Aceite; endurecimento futuro (gating de definicoes de seguranca).
- P0: nenhum. P1: nenhum.

## v12.12.12 (reposicao automatica)

- Execucao dupla: MITIGADA (consumo atomico + guardas de estado das operacoes). P0 candidato fechado.
- Contorno de permissao/tenant pela reposicao: MITIGADO (re-validados dentro do metodo). P0 candidato fechado.
- P2 (A-R5): multiplas operacoes pendentes, vence a ultima. Aceite; repeticao manual para as anteriores.
- P2 (A-R6): descritor obsoleto. Mitigado por TTL curto e consumo na confirmacao.
- P0: nenhum. P1: nenhum.

## v12.12.13 (painel de controlo de seguranca MFA)

- Acesso indevido de perfil SIGE ao painel: MITIGADO (tripla camada + filtro de hardening; provado em runtime). P0 candidato fechado.
- POST directo ao endpoint por perfil SIGE: MITIGADO (gate duro no handler, wp_die 403). P0 candidato fechado.
- CSRF na gravacao: MITIGADO (nonce). P1 candidato fechado.
- Desligar step-up sem rasto: MITIGADO (evento de auditoria distinto). P1 candidato fechado.
- P3 (A-S6): supressao de display de avisos em producao. Aceite (so display, log mantido, opcional).
- P3 (A-S7): perfis abrangidos vazios com step-up ligado. Aceite (respeita intencao; UI avisa).
- P0: nenhum. P1: nenhum.

## v12.12.14 (Secret Vault, incremento 1)

- P2-003 (opcoes sensiveis fora do cofre): credenciais dos gateways em claro. FECHADO para o inventario conhecido (M-Pesa, e-Mola, webhook_token cifrados; SMTP e WhatsApp ja cifrados).
- Perder credencial na transicao: MITIGADO (passagem de texto em claro; selar nunca perde). P1 candidato fechado.
- Webhook deixar de validar: MITIGADO (revela antes do hash_equals). P1 candidato fechado.
- Escrita inesperada na leitura: MITIGADO (auto-reparacao so no admin, uma vez; nunca no webhook). P2 candidato fechado.
- P3 (A-V7): cobertura do registo; decisao de rotacao de chave adiada para incremento posterior. Aceite.
- P0: nenhum. P1: nenhum.

## v12.12.15 (Ledger financeiro, incremento 1)

- Historia financeira mutavel sem deteccao: MITIGADO para as 6 operacoes criticas (cadeia HMAC append-only; deteta modificacao e remocao/insercao no meio). P2 do roteiro parcialmente fechado.
- Truncagem da cauda: LIMITE CONHECIDO (P2), adiado para ancoragem externa (incremento posterior).
- Compromisso de ficheiros (salts): modelo declarado (fora do ambito da tamper-evidence ao nivel da base de dados).
- Concorrencia sob timeout de bloqueio: P3 aceite (no maximo um evento nao registado; sem corromper a cadeia).
- P0: nenhum. P1: nenhum.

## v12.12.16 (patch correctivo do Ledger)

- Tabela do ledger nao criada por actualizacao de ficheiros: CORRIGIDO (SCHEMA_VERSION subida; maybe_upgrade cria a tabela). Travado por gate.
- Erro cru no ecra sem tabela: CORRIGIDO (guard de existencia; mensagem clara). Travado por gate.
- P0: nenhum. P1: nenhum.

## v12.12.17 (Ledger incr 2: pagamentos e ancora)

- Movimentos de dinheiro fora do ledger: MITIGADO (pagamentos instrumentados, cobrem todos os fluxos).
- Truncagem da cauda: FECHADO contra o atacante so com base de dados (ancora externa em ficheiro). P2 do incremento 1 resolvido.
- Compromisso de ficheiros (ancora mais salts): modelo declarado (fora de ambito).
- Perda da ancora: deteccao de truncagem cega ate reconstruir; cadeia interna mantem deteccao de modificacao e remocao no meio. Documentado.
- P0: nenhum. P1: nenhum.

## v12.12.18 (Ledger incr 3: lancamentos)

- Cobrancas fora do ledger: MITIGADO (criacao e alteracao de valor instrumentadas, por cobranca).
- Desempenho da geracao em massa: RESOLVIDO por desenho (escrita em lote diferida, um bloqueio e uma ancora por escola e por pedido).
- Limite da escrita diferida: HONESTO (eventos de cobranca gravados no fim do pedido; falha catastrofica antes do shutdown perde o buffer desse pedido; cobrancas tambem podem ficar incompletas e o gerador e idempotente). Documentado.
- P0: nenhum. P1: nenhum.

## v12.12.19 (Ledger incr 4: despesas, creditos, fechos)

- Despesas, creditos e fechos fora do ledger: MITIGADO (5 eventos instrumentados). Cobertura financeira do ledger completa.
- Consumo de credito e edicao de despesa: adiados (inline, sem ponto unico). Documentado.
- P0: nenhum. P1: nenhum.

## v12.12.21 (Fase 7 incr 1: reconciliacao e divergencias)

- Divergencias de reconciliacao invisiveis: MITIGADO (relatorio so leitura: recebido por aplicar, divergencia de montante, pagamento sem gateway, com totais).
- Idempotencia e-Mola: CONFIRMADO (pre-verificacao por referencia + chave unica, igual ao M-Pesa).
- Estorno com razao e inverso, reabertura com permissao, auditoria via Ledger: criterios da Fase 7 ja cumpridos, fechados.
- Regra de quatro-olhos (dupla aprovacao): adiada para o incremento 2. Documentado.
- P0: nenhum. P1: nenhum.

## Actualizacao v12.12.21 (Fase 7 incremento 2)

- P1 mitigado: bypass da separacao de funcoes (decisor != solicitante, nonce, permissao, MFA).
- P1 mitigado: dupla execucao (FOR UPDATE, guarda de duplo estorno, anti-duplicado de pedidos).
- P2 aceite: pedidos pendentes sem auto-expiracao (cron pertence a infraestrutura); documentado.
- P3 aceite: dupla confirmacao de identidade (requerente e aprovador) mantida como defesa em profundidade.

## Actualizacao v12.12.22 (release correctiva)

Esta versao e uma correccao de roteamento da Fase 7, construida sobre a v12.12.21. As views financeiro-reconciliacao (Incremento 1) e financeiro-aprovacoes (Incremento 2, regra de quatro-olhos), entregues na Fase 7 mas em falta na allowlist anti-LFI e na matriz de permissoes do admin-shell, ficavam inalcancaveis: a guarda reescrevia o pedido para o painel inicial. Foram repostas em ambas as listas, com as permissoes identicas as guardas internas de cada view. Nao ha alteracao da superficie de accao (manifesto e regras do Security Kernel mantem-se em 197), nao ha migracao de dados (SCHEMA_VERSION inalterada) e as regras de calculo financeiro nao sao tocadas.

Esta release correctiva fecha um risco de nivel P1 (duas views da Fase 7 inalcancaveis na interface por omissao na allowlist e na matriz). Nao ha novos riscos P0, P1, P2 nem P3 introduzidos; ver Adversarial Review v12.12.22.

## Actualizacao v12.12.24 (Fase 8 Incr 1)

- P0: nenhum.
- P1: nenhum.
- P2: classificacao de base legal por omissao pode nao reflectir a realidade de cada escola. Mitigacao: marcada explicitamente como revisivel pela instituicao, responsavel pelo tratamento.
- P3: catalogo de PII pode ficar incompleto com a evolucao do esquema. Mitigacao: deteccao automatica de lacunas (colunas PII fora do catalogo) denuncia o que falta classificar, em vez de o esconder.

## Actualizacao v12.12.24 - Fase 8 incremento 2

- P0: nenhum.
- P1: nenhum.
- P2: exportacao de dados pessoais e operacao sensivel; mitigada por permissao de risco alto restrita a administracao/direccao, nonce, rate limit (10/300s), isolamento por escola e auditoria obrigatoria de cada exportacao.
- P3: a base legal e as finalidades exibidas sao classificacao por omissao do catalogo, a rever pela instituicao; o dossie limita-se a tabelas ligadas ao aluno (encarregado e agregado constam por estarem na ficha do aluno; o titular funcionario fica para incremento posterior).


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

Patch de defeito (nao e nova fase). O numero de chamada (No) do Mapa de Aproveitamento Pedagogico nao coincidia com a posicao alfabetica do aluno na pauta da turma (Eluenny Ivan Barrama, 4a Classe Turma A, aparecia com No 6 em vez de No 1). A causa raiz era uma variavel de utilizador do MariaDB no padrao (@rn := @rn + 1) em includes/map-pdf-handler.php, que numerava por ordem de varrimento da tabela e nao por nome. Foi introduzido um rolo canonico unico e deterministico (populacao de aluno e matricula activos, ordem nome_completo ASC com desempate por id ASC) por dois auxiliares novos em includes/core-helpers.php, e o MAP, a modal de alunos da turma e as pautas (pauta-pdf, pauta-excel, dec-view, pauta-final-view) foram convergidos para essa definicao, com seguranca de tenant (escola_id) no join. Numero do MAP igual ao da modal igual ao da pauta em todos os casos, incluindo desistencias. O numero de chamada e apresentacao, nao entra em calculo academico nem financeiro; nenhuma formula foi tocada.

| Severidade | Estado nesta versao |
|---|---|
| P0 | Nenhum. |
| P1 | Nenhum. |
| P2 | Nenhum novo. A modal de alunos passa a excluir matriculas inactivas, alinhando com os documentos oficiais (desejado). |
| P3 | Nenhum novo. COUNT (nao COUNT DISTINCT) para paridade com a multiplicidade do join INNER da modal. |

Sem nova superficie (199/33, views 60), sem alteracao de esquema, sem aumento de current_user_can, calculo byte-identico. Release gate verde a partir de pasta limpa. Rediagnostico adversarial Zero P0/P1.


## Actualizacao v12.12.34 - Blindagem de uploads e ficheiros

Incremento 1 da fase de documentos, uploads e QR (nao e correccao de defeito; e seguranca preventiva). Fecha dois vectores: a execucao de ficheiros no directorio de uploads e a entrada de tipos perigosos. Um novo modulo escreve, de forma idempotente, .htaccess e web.config no directorio de uploads que negam a execucao e o acesso web a PHP e scripts (regras de PHP guardadas dentro de IfModule mod_php para nao quebrar em LiteSpeed ou FPM; negacao por FilesMatch como proteccao transversal). Um filtro wp_handle_upload_prefilter recusa ficheiros perigosos a entrada por extensao, bytes magicos, inicio de ficheiro, imagem invalida e SVG malicioso, e a mesma validacao protege a importacao de alunos. O servico autenticado de documentos nao e afectado (le por readfile).

| Severidade | Estado nesta versao |
|---|---|
| P0 | Nenhum. |
| P1 | Nenhum. |
| P2 | Nenhum novo. A entrega da blindagem por .htaccess cobre Apache e LiteSpeed; em Nginx a regra equivalente fica documentada no DEPLOY. |
| P3 | Nenhum novo. O prefilter bloqueia so o comprovadamente perigoso, para nao recusar tipos legitimos. |

Residual conhecido (proximos incrementos desta fase): os documentos sensiveis ja existentes continuam no directorio publico (a URL directa ainda responde ate o incremento 2 os mover para armazenamento privado e bloquear o acesso directo). Sem nova superficie (199/33, views 60), sem opcoes novas (132), sem dependencias novas (12), sem alteracao de esquema, calculo byte-identico. Corredor 80/80. Rediagnostico adversarial Zero P0/P1.


## Actualizacao v12.12.35 - Armazenamento privado dos documentos sensiveis

Incremento 2 da fase de documentos, uploads e QR. Fecha o vector da URL publica directa dos documentos sensiveis, que ficou em aberto no incremento 1. Os documentos do aluno e da equipa passam para wp-content/uploads/sige-private/docs/<escola_id>, com negacao total do acesso web, servidos apenas pelo endpoint autenticado. A migracao dos existentes corre por um dreno idempotente e resumivel nas paginas do SIGE; os documentos novos sao movidos na gravacao por um choke-point. A foto fica de fora (mostrada em linha).

| Severidade | Estado nesta versao |
|---|---|
| P0 | Nenhum. O movimento e seguro contra perda: so consuma depois de remover o original; em qualquer falha aborta sem duplicado e o valor guardado mantem-se. |
| P1 | Nenhum. O endpoint resolve o ficheiro privado sem alteracao; o acesso pelo botao seguro nao muda. |
| P2 | Nenhum novo. A negacao por .htaccess cobre Apache e LiteSpeed; em Nginx a regra equivalente (negar /sige-private/) fica documentada no DEPLOY. |
| P3 | Nenhum novo. A migracao corre por lotes nas paginas do SIGE; documentos ainda nao migrados permanecem acessiveis pela URL directa ate o lote os alcancar, melhorando a cada navegacao. |

Residual conhecido (incremento 3): links com expiracao, reforco do registo de download, limpeza de orfaos (ficheiros publicos que tenham ficado sem referencia) e confirmacao do QR local. A foto permanece publica por desenho (mostrada em linha); qualquer decisao sobre a foto fica para fora desta fase. Sem nova superficie (199/33, views 60), sem opcoes novas (132), sem dependencias novas (12), sem alteracao de esquema, calculo byte-identico. Corredor 82/82. Rediagnostico adversarial Zero P0/P1.


## Actualizacao v12.12.36 - Correccao do despacho do dialogo de confirmacao

Correccao de defeito de integridade de accao no view aprovar_notas: premir Aprovar mostrava o dialogo de Rejeitar e submetia a accao de rejeitar, pelo que notas que se pretendia aprovar eram rejeitadas e devolvidas ao professor. Causa raiz no enhancer de confirmacao (assets/sige-ui.js), cujo interceptor de submissao herdava o dialogo e a accao de outro botao quando o botao premido nao pedia confirmacao. Corrigido em duas frentes: o handler passa a respeitar o botao realmente premido (recuo por querySelector so quando nao ha submitter) e o botao Aprovar recebe o seu proprio dialogo.

| Severidade | Estado nesta versao |
|---|---|
| P0 | Nenhum apos a correccao. Antes da correccao, a accao errada podia ser submetida; o backend ja restringia a transicao a notas pendentes e por escola, e a correccao elimina a submissao errada na origem. |
| P1 | Nenhum. O backend nao foi tocado; a correccao e apenas no despacho do dialogo no cliente. |
| P2 | Nenhum novo. |
| P3 | Nenhum novo. |

Cobertura: um varrimento de todos os formularios das views confirmou que aprovar_notas era o unico com o padrao misto (um botao com confirmacao ao lado de outro sem). A correccao do handler fecha a classe de erro para qualquer formulario futuro, e um gate dedicado tranca o invariante. Sem nova superficie (199/33, views 60), sem opcoes novas (132), sem dependencias novas (12), sem alteracao de esquema, calculo byte-identico. Corredor 83/83. Rediagnostico adversarial Zero P0/P1.


## Actualizacao v12.12.37 - Fase 1 Incremento 3 - Ciclo de vida dos documentos

Fecho da Fase 1 com quatro frentes: links com expiracao assinados, registo de download reforcado, limpeza de orfaos segura e QR gerado localmente.

| Severidade | Estado nesta versao |
|---|---|
| P0 | Nenhum. Um link de documento que tenha fugado deixa de funcionar ao fim da validade e nao pode ser forjado (HMAC com hash_equals). A limpeza de orfaos nunca apaga ficheiros referenciados nem de proteccao e aborta se nao conseguir ler as referencias. |
| P1 | Nenhum. O QR passa a ser gerado localmente, pelo que o numero de processo do aluno deixa de sair para um servico externo. O registo de download passa a permitir identificar quem descarregou e de onde. |
| P2 | Nenhum novo. Risco de bloqueio por expiracao mitigado por validade generosa (1h por omissao) e regeneracao do link a cada abertura da ficha. |
| P3 | Nenhum novo. Risco de o qrious nao carregar mitigado por dependencia ja no catalogo (cdnjs, com SRI) e por pixel transparente de recurso para a imagem nao ficar partida. |

Dependencias externas reduzidas de 12 para 11 (api.qrserver.com removido), melhoria de privacidade e de superficie. Sem nova superficie de accao (199/33, views 60), sem opcoes novas (132), sem alteracao de esquema, calculo byte-identico. Corredor 85/85. Rediagnostico adversarial Zero P0/P1.


## Actualizacao v12.12.38 - Fase 2 - Cofre de segredos - cobertura, mascaramento e nao-vazamento

Segundo incremento do cofre: mascaramento, redaccao de segredos em registos e URLs, e auditoria de alteracao.

| Severidade | Estado nesta versao |
|---|---|
| P0 | Nenhum. Os segredos vivos continuam a ser guardados e lidos como antes; estas alteracoes sao aditivas e nao tocam o armazenamento nem a leitura. |
| P1 | Nenhum. Um segredo deixa de poder chegar aos logs em claro pelo canal de seguranca (redaccao); a alteracao de um segredo passa a ser auditavel sem expor o valor. |
| P2 | Nenhum novo. Risco de sobre-redaccao mitigado por padroes especificos (formatos selados e nomes de chave de segredo); o texto normal passa intacto, comprovado por smoke. |
| P3 | Nenhum novo. |

Ficheiros canonicos intocados (o scrub liga-se ao canal de seguranca, nao ao registo de auditoria financeiro). Sem nova superficie (199/33, views 60), sem opcoes novas (132), sem dependencias novas (11), sem alteracao de esquema, calculo byte-identico. Corredor 87/87. Rediagnostico adversarial Zero P0/P1.


## Actualizacao v12.12.39 - Fase 2 - Cofre de segredos - rotacao e segredos por escola

Terceiro incremento do cofre: segredos isolados por escola, rastreio e rotacao de segredos, e rotacao concreta do webhook de pagamento por escola.

| Severidade | Estado nesta versao |
|---|---|
| P0 | Nenhum. A camada e aditiva: nao altera o armazenamento nem a leitura dos segredos ja existentes, nem a cifra partilhada. Os segredos por escola sao cifrados em repouso. |
| P1 | Nenhum. A rotacao gera segredos fortes (random_bytes), audita sem o valor, e o isolamento por escola evita partilha de credenciais entre escolas. |
| P2 | Nenhum novo. Risco de perda de um segredo na re-selagem mitigado: um valor nao decifravel fica intacto. A rotacao dos salts do WordPress continua a exigir re-introducao dos segredos (documentado no guia de instalacao). |
| P3 | Nenhum novo. |

Aditivo, sem tocar na cifra partilhada nem em ficheiros canonicos. Sem nova superficie (199/33, views 60), sem opcoes novas (132), sem dependencias novas (11), sem alteracao de esquema, calculo byte-identico. Corredor 89/89. Rediagnostico adversarial Zero P0/P1.


## Actualizacao v12.12.40 - Fase 3 - Reconciliacao de pagamentos digitais - deteccao de duplicados

Primeiro incremento da reconciliacao: camada de deteccao de duplicados logicos, so de leitura.

| Severidade | Estado nesta versao |
|---|---|
| P0 | Nenhum. A camada nunca apaga nem altera pagamentos; so assinala para revisao. Nao toca em regras de calculo nem em ficheiros canonicos. |
| P1 | Nenhum. Apanha duplo credito (mesmo pagamento conciliado mais de uma vez) e pagamentos repetidos por engano, que a idempotencia por referencia nao apanha. |
| P2 | Nenhum novo. Risco de falsos positivos mitigado pela janela de tempo: mesmo pagador e valor afastados no tempo (mensalidades) nao sao marcados; a seccao e apresentada como ajuda a revisao, a confirmar antes de agir. |
| P3 | Nenhum novo. |

Aditivo e so de leitura. Sem nova superficie (199/33, views 60), sem opcoes novas (132), sem dependencias novas (11), sem alteracao de esquema, calculo byte-identico. Corredor 91/91. Rediagnostico adversarial Zero P0/P1.


## Actualizacao v12.12.41 - Fase 3 - Reconciliacao de pagamentos digitais - reconciliacao viva

Segundo incremento da reconciliacao: verificacao das transacoes contra o gateway, so de leitura.

| Severidade | Estado nesta versao |
|---|---|
| P0 | Nenhum. A camada nunca apaga, cria nem altera pagamentos nem transacoes; so classifica e regista. Nao toca em regras de calculo nem em ficheiros canonicos. |
| P1 | Nenhum. Apanha webhooks falsos ou falhados e valores divergentes; o adaptador e defensivo (cliente indisponivel nao quebra o cron). |
| P2 | Nenhum novo. Risco de acusar uma transacao real de falsa eliminado pelo normalizador conservador: codigo desconhecido fica desconhecida, e a falha so e afirmada por codigo conhecido (lista vazia por omissao). |
| P3 | Nenhum novo. Risco de o cron prender com chamadas ao gateway mitigado por limite pequeno por passagem e por so correr se configurado. |

Aditivo e so de leitura. Sem nova superficie (199/33, views 60; cron num evento ja existente), sem opcoes novas (132), sem dependencias novas (11), sem alteracao de esquema, calculo byte-identico. Corredor 93/93. Rediagnostico adversarial Zero P0/P1.


## Actualizacao v12.12.42 - Fase 3 - Reconciliacao de pagamentos digitais - resolucao com escrita (quatro-olhos)

Terceiro incremento da reconciliacao: resolucao accionavel sob controlo duplo (a primeira escrita da Fase 3).

| Severidade | Estado nesta versao |
|---|---|
| P0 | Nenhum. A escrita financeira passa sempre pelo caminho canonico sige_fin_registar_pagamento; nao ha calculo de valores nem alteracao de regras. A execucao re-valida o estado e e fail-closed. |
| P1 | Nenhum. A separacao de funcoes (maker diferente de checker) e imposta pelo framework de aprovacoes; nada executa sem a aprovacao de um segundo utilizador autorizado. A rejeicao nunca toca numa transacao ja conciliada. |
| P2 | Nenhum novo. Risco de dupla execucao eliminado pela idempotencia e pela re-validacao de estado no momento da aprovacao. Risco de escalonamento eliminado pela reutilizacao da permissao existente (sem nova permissao). |
| P3 | Nenhum novo. A UI do maker e so server-side (sem nova accao), e os botoes directos continuam a funcionar (sem regressao). |

Aditivo e controlado. Sem nova superficie (199/33), sem nova vista (60), sem opcoes novas (132), sem dependencias novas (11), sem alteracao de esquema, calculo byte-identico. Corredor 95/95. Rediagnostico adversarial Zero P0/P1.


## Actualizacao v12.12.43 - Fase 3 - Afinacao dos codigos do gateway e escolha de lancamento

Dois afinamentos da reconciliacao: estado da transacao com prioridade na verificacao, e escolha de lancamento na proposta com quatro-olhos.

| Severidade | Estado nesta versao |
|---|---|
| P0 | Nenhum. A escolha de lancamento usa o caminho canonico ja existente; a afinacao do normalizador nao toca em regras de calculo. |
| P1 | Nenhum. A afinacao torna a verificacao MAIS exacta: uma transacao Failed deixa de ser tomada por confirmada. A execucao da conciliacao continua a validar escola e aluno. |
| P2 | Nenhum novo. Risco de acusar uma transacao real de falsa continua eliminado: um estado nao mapeado fica desconhecida, e a falha so e afirmada por estado ou codigo conhecido. |
| P3 | Nenhum novo. A mudanca do normalizador e retro-compativel (sem campo de estado, comportamento anterior); o seletor de lancamento e aditivo e mantem a correspondencia automatica. |

Aditivo. Sem nova superficie (199/33), sem nova vista (60), sem opcoes novas (132; afinacao por filtro), sem dependencias novas (11), sem alteracao de esquema, calculo byte-identico. Corredor 95/95. Rediagnostico adversarial Zero P0/P1.
