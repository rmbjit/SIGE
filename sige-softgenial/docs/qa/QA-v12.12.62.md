# QA - SIGE SoftGenial v12.12.62
Fase 9 incremento 5: Sobreposicao puramente aditiva por capacidades

## Ambito verificado
Eliminacao da substituicao do papel WordPress: capacidades concedidas por filtro user_has_cap a partir do papel sige_* mapeado ao perfil activo (mais recente, fiel ao antigo set_role), sem tocar no papel guardado; init so limpa (repoe papel sige_* de staff legado); reposicao segura para o fluxo aditivo; verificacoes por slug de staff convertidas para capacidade; invariante por gate (zero verificacoes por slug de staff). Papeis de portal intocados. Sem nova superficie, sem alteracao de esquema.

## Resultado dos gates
- Gate estatico novo (check-permissoes-aditiva): OK. Verifica as funcoes da sobreposicao aditiva (staff_wp_roles, is_staff_wp_role, get_latest_active_role, caps_for_user, grant_caps_filter); o registo do filtro user_has_cap e a guarda anti-recursao; sync_user_role sem set_role; a sincronizacao no init so a limpar (restauro de papel sige_* de staff, sem set_role); as conversoes por capacidade no ajax-handlers e no equipe-view; o invariante (zero in_array com slug de papel sige_* de staff em includes/ e admin/); e o esquema inalterado.
- Smoke runtime novo (smoke-permissoes-aditiva): OK. A1 (caps_for_user devolve o conjunto completo, com capacidade do proprio nome e em cascata), A2 (filtro concede e preserva o papel guardado), A3 (ignora administrador real), A4 (nada concede sem perfil), A5 (guarda anti-recursao reposta por chamada), A6 (staff distingue-se de portal).
- Gate do incremento 3 (check-permissoes-sobreposicao), actualizado: OK. Agora verifica a seguranca da reposicao no fluxo aditivo (sem copia, so repoe o padrao quando ha papel sige_* de staff; nunca toca num papel real), a limpeza da copia, e o uso na remocao com mensagem.
- Smoke do incremento 3 (smoke-permissoes-sobreposicao), actualizado (cenario do init): OK.
- Smokes dos incrementos 1, 2 e 4: OK (a camada mudou, sem regressao).
- Corredor completo (run-gates): 78 de 78 verdes.
- Release gate (smoke-release-gate): 54 verificacoes, OK (v12.12.32).
- Baseline de autorizacao (check-authorization-baseline): OK. As conversoes usam user_can, funcao distinta de current_user_can, por isso o baseline nao sobe.
- Governanca (check-governance-docs, check-public-endpoints-policy): OK.

## Integridade e nao-regressao
- Lint PHP: 0 erros em todos os ficheiros.
- Zero travessoes (em dash / en dash) em todo o pacote.
- Nucleos de calculo (finance-core, academic-logic, whatsapp-engine): byte-identicos a baseline.
- Baselines de design (tokens, consistencia visual, colisoes CSS): sem regressao (as conversoes nao tocam UI/CSS).
- Superficie de accao: 199 (enforce 33); manifesto e Kernel alinhados; allowlist de views 60.
- Esquema: SCHEMA_VERSION inalterada.
- Sincronia de versao: header, SIGE_VERSION, BUILD.json e SIGE_GOV_VERSION em 12.12.32; base_version do inventario em 12.12.31.

## Testes negativos aos gates novos
- Removido o registo do filtro user_has_cap: o gate aditiva falha. Reposto byte a byte: verde.
- Reintroduzida uma verificacao por slug de staff (in_array com sige_professor): o invariante do gate aditiva falha. Removida: verde.
- Reintroduzido um set_role no sync_user_role: o gate aditiva falha. Removido: verde.

## Decisoes de desenho documentadas (nao sao defeitos)
- Capacidades pelo perfil activo mais recente (independente do contexto de escola), para replicar fielmente o antigo set_role; a isolacao de dados por escola e separada.
- Reposicao sem copia limitada ao caso de papel sige_* de staff, para nao resetar um utilizador novo ja num papel real.
- O gate e o smoke do incremento 3 foram actualizados para a realidade aditiva (a reversibilidade da troca deixou de aplicar-se porque a troca foi eliminada).
- backup_wp_roles mantem-se definido (utilidade), embora ja nao seja chamado, por o smoke do incremento 3 o exercitar e por documentar o conceito.

## Achados durante o desenvolvimento
- O smoke do incremento 3 acusou o cenario do init (S8) que assumia o comportamento antigo (nao mexer quando coincide); actualizado para a realidade aditiva (limpa sempre papel sige_* de staff legado) na mesma sessao.

## Conclusao
Zero P0 e zero P1. Aprovado para entrega como v12.12.32. Conclui o conjunto previsto para a Fase 9 (blindagem, hierarquia, sobreposicao reversivel, niveis editaveis, sobreposicao aditiva). A Fase 9 fica sem nada adiado. Refinamento futuro declarado (nao paliativo): uma coluna dedicada de nivel na tabela de perfis, apenas se necessario para consulta ou relatorios.

## Actualizacao v12.12.33 - Correccao do numero de chamada no MAP

Patch de defeito (nao e nova fase). O numero de chamada (No) do Mapa de Aproveitamento Pedagogico nao coincidia com a posicao alfabetica do aluno na pauta da turma. A causa raiz era uma variavel de utilizador do MariaDB no padrao (@rn := @rn + 1) em includes/map-pdf-handler.php, que numerava por ordem de varrimento da tabela e nao por nome. Foi introduzido um rolo canonico unico e deterministico (populacao de aluno e matricula activos, ordem nome_completo ASC com desempate por id ASC) por dois auxiliares novos em includes/core-helpers.php (sige_turma_ordem_chamada_order_sql e sige_turma_numero_chamada), e o MAP, a modal de alunos da turma e as pautas (pauta-pdf, pauta-excel, dec-view, pauta-final-view) foram convergidos para essa definicao, com seguranca de tenant (escola_id) no join.

Impacto nesta postura: nulo. Esta versao nao altera a superficie de accoes (mantem 199, enforce 33), as views (60), o mapa de permissoes, o isolamento por escola (escola_id), os segredos, as opcoes nem as dependencias externas. Os auxiliares introduzidos sao funcoes simples, nao accoes registadas no Kernel. Calculo academico e financeiro byte-identico. current_user_can inalterado. A postura descrita acima mantem-se valida.

Teste especifico desta versao (numero de chamada): abrir o Mapa de Aproveitamento de uma turma cujo primeiro aluno por ordem alfabetica nao seja o primeiro por ordem de insercao (por exemplo, a 4a Classe Turma A, onde Eluenny Ivan Barrama e alfabeticamente a primeira). Confirmar que o No apresentado no MAP e 1 e que coincide exactamente com a posicao na modal de alunos da turma e na pauta. Repetir para uma turma com pelo menos uma matricula inactiva (desistencia) e confirmar que o No do MAP, da pauta e da modal coincidem entre si em todos os alunos activos. Confirmar tambem que a numeracao das pautas (pauta-pdf, pauta-excel, DEC e pauta final) se mantem igual a anterior em dados saudaveis.

## Actualizacao v12.12.34 - Blindagem de uploads e ficheiros

Incremento 1 da fase de documentos, uploads e QR. Um novo modulo includes/security-uploads.php garante, de forma idempotente no admin_init, ficheiros de proteccao no directorio de uploads (.htaccess e web.config) que impedem a execucao e o acesso web a ficheiros perigosos (PHP e scripts), com as directivas de PHP guardadas dentro de IfModule mod_php para nao quebrar em LiteSpeed ou PHP-FPM. Um filtro wp_handle_upload_prefilter recusa ficheiros perigosos a entrada por extensao (incluindo duplas extensoes enganosas), por bytes magicos (finfo), por inicio de ficheiro (assinaturas MZ, ELF, shebang e abertura de PHP), por imagem declarada que nao e imagem real, e por SVG com script, eventos ou entidades; bloqueia apenas o comprovadamente perigoso, para nao recusar tipos legitimos. A mesma validacao de conteudo real protege a importacao de alunos (XLSX e CSV). O servico autenticado de documentos do aluno e de RH (secure-document-download) continua a funcionar porque le por readfile, do lado do servidor.

Impacto nesta postura: nulo. Sem nova superficie de accao (admin_init ja e superficie contabilizada e deduplicada, o prefilter e um filtro e nao um endpoint; o manifesto e as regras do Kernel mantem-se em 199, enforce 33; a lista de views em 60), sem opcoes novas (132), sem dependencias externas novas (12), sem alteracao de esquema. Calculo academico e financeiro byte-identico. current_user_can inalterado. A postura descrita acima mantem-se valida.

Teste especifico desta versao (blindagem de uploads): confirmar que o directorio wp-content/uploads passa a ter .htaccess e web.config com as regras de negacao; que um ficheiro PHP renomeado para .jpg e recusado tanto no upload pela Biblioteca de Media como na importacao de alunos; que um .xlsx que nao seja um pacote ZIP valido e recusado na importacao; e que ficheiros legitimos (imagens reais, PDF, .xlsx valido, CSV de texto) continuam a ser aceites. Confirmar tambem que os documentos do aluno e de RH continuam a abrir pelo botao seguro.

## Actualizacao v12.12.35 - Armazenamento privado dos documentos sensiveis

Incremento 2 da fase de documentos, uploads e QR. Os documentos sensiveis do aluno (doc_bi_url, doc_cert_url, doc_vacina_url) e da equipa (doc_bi, doc_cv, doc_cert dentro de documentos_urls) deixam de viver no directorio publico wp-content/uploads e passam para um directorio privado (wp-content/uploads/sige-private/docs/<escola_id>), com .htaccess e web.config de negacao total do acesso web, servidos apenas pelo endpoint autenticado (secure-document-download, que le por readfile). Um novo modulo includes/security-uploads-private.php trata disto por dois caminhos, sem opcao de estado: na gravacao, um choke-point move o documento novo para o directorio privado e guarda a URL ja la; para os existentes, um dreno idempotente e resumivel pendurado no admin_init das paginas do SIGE trata-os em lotes e fica inerte quando nada resta. O movimento e seguro contra perda (so consuma depois de remover o original; em falha, aborta sem alterar) e move tambem as miniaturas. A foto (campo foto e foto_perfil) NAO e abrangida por ser mostrada em linha como imagem.

Impacto nesta postura: nulo. O endpoint resolve o ficheiro no directorio privado sem alteracao, porque continua sob o basedir de uploads e o .htaccess nega o acesso directo. Sem nova superficie de accao (admin_init ja e superficie contabilizada e deduplicada; o manifesto e o Kernel mantem-se em 199, enforce 33; a lista de views em 60), sem opcoes novas (132), sem dependencias externas novas (12), sem alteracao de esquema. Calculo academico e financeiro byte-identico. current_user_can inalterado. A postura descrita acima mantem-se valida.

Teste especifico desta versao (armazenamento privado): apos navegar pelas paginas do SIGE (a migracao corre por lotes nessas paginas), confirmar que passou a existir wp-content/uploads/sige-private/docs com .htaccess de negacao total; escolher um aluno com BI carregado e confirmar que a URL publica directa desse BI passa a devolver 403 (acesso negado), enquanto o documento continua a abrir pelo botao seguro; confirmar que a foto do aluno continua a aparecer em linha normalmente; e confirmar que carregar um novo documento de aluno ou de equipa o coloca directamente no directorio privado (a sua URL directa tambem devolve 403, mas abre pelo botao seguro).

## Actualizacao v12.12.36 - Correccao do despacho do dialogo de confirmacao

Correccao de defeito (nao e nova funcionalidade). No view aprovar_notas, premir Aprovar mostrava o dialogo de confirmacao do botao Rejeitar e, ao confirmar, submetia a accao de rejeitar em vez de aprovar. Causa raiz no enhancer declarativo de confirmacao (assets/sige-ui.js): o interceptor do evento de submissao, quando o botao premido nao tinha [data-sige-confirm], recorria a form.querySelector e apanhava o primeiro botao de submissao com confirmacao do mesmo formulario (o Rejeitar), herdando o seu dialogo e submetendo a sua accao. A correccao faz o handler respeitar o botao realmente premido (so confirma quando esse botao a exige; o recuo por querySelector fica reservado ao caso sem submitter, como o Enter num campo em navegadores antigos) e da ao botao Aprovar o seu proprio dialogo de confirmacao.

Impacto nesta postura: nulo. So foram tocados assets/sige-ui.js (logica do enhancer) e admin/academic/aprovar_notas-view.php (atributos do botao Aprovar); o backend de aprovacao e rejeicao nao foi alterado. Sem nova superficie de accao (199, enforce 33; views 60), sem opcoes novas (132), sem dependencias externas novas (12), sem alteracao de esquema. Calculo academico e financeiro byte-identico. current_user_can inalterado. A postura descrita acima mantem-se valida.

Teste especifico desta versao (despacho de confirmacao): no view aprovar_notas, premir Aprovar deve mostrar o dialogo Aprovar notas (e nao o de rejeitar) e, ao confirmar, as notas seleccionadas devem ficar aprovadas; premir Rejeitar deve mostrar o dialogo Rejeitar notas e, ao confirmar, as notas devem ser rejeitadas e voltar ao professor. Confirmar que a accao submetida corresponde sempre ao botao premido.

## Actualizacao v12.12.37 - Fase 1 Incremento 3 - Ciclo de vida dos documentos

Incremento que fecha a Fase 1 (seguranca de documentos e ficheiros), com quatro frentes sobre o que ja existia (perimetro anti-execucao no Incremento 1; armazenamento privado e migracao no Incremento 2).

Frente 1 - Links com expiracao. Os links do endpoint seguro de documentos (fluxo do aluno e fluxo da equipa) passam a transportar exp (expiracao) e sig (HMAC-SHA256 de escopo, id, campo e expiracao, com segredo estavel da instalacao via wp_salt, sem opcao nova), alem do nonce. Os handlers recusam (403) links expirados ou com assinatura invalida, com comparacao em tempo constante. Validade ajustavel pela constante SIGE_SECURE_DOC_LINK_TTL (por omissao 3600s).

Frente 2 - Registo de download reforcado. O registo de auditoria de cada descarregamento passa a incluir o IP do cliente (saneado) e o utilizador, nos dois fluxos, alem do aluno ou professor, campo, rotulo e nome do ficheiro ja existentes.

Frente 3 - Limpeza de orfaos. Rotina segura, idempotente e resumivel que remove do armazenamento privado os ficheiros sem qualquer referencia em base de dados e mais antigos que um periodo de graca (constante SIGE_PRIVATE_ORPHAN_GRACE, por omissao 86400s). Opera apenas dentro de sige-private/docs, nunca toca nos ficheiros de proteccao, e aborta sem apagar nada se nao conseguir construir o conjunto de referencias. Corre na mesma passagem por admin_init que ja faz a migracao.

Frente 4 - QR local. Os cartoes de estudante deixam de gerar o codigo QR num servico externo (api.qrserver.com) e passam a gera-lo no proprio navegador com a biblioteca qrious (ja no catalogo com SRI). O numero de processo do aluno nunca sai do dispositivo. A dependencia externa api.qrserver.com e removida: as dependencias externas passam de 12 para 11.

Impacto na postura: sem nova superficie de accao (199, enforce 33; views 60), sem opcoes novas (132), sem alteracao de esquema, calculo academico e financeiro byte-identico. As dependencias externas reduzem-se de 12 para 11 (api.qrserver.com removido), uma melhoria de privacidade e de superficie. Com este incremento a Fase 1 fica completa.

Testes especificos desta versao (ciclo de vida dos documentos): (1) abrir um documento a partir da ficha e, apos a validade (constante SIGE_SECURE_DOC_LINK_TTL), reabrir o link antigo deve dar 403 (link expirado); reabrir a partir da ficha gera um link novo e funciona. (2) substituir um documento de um aluno e, apos o periodo de graca, confirmar que o ficheiro antigo desaparece do armazenamento privado e o novo permanece; ficheiros de proteccao e documentos referenciados permanecem sempre. (3) imprimir um cartao de estudante e confirmar que o QR aparece e que o numero de processo nao e enviado para nenhum servico externo. (4) no registo de auditoria de um descarregamento, confirmar que constam o IP e o utilizador.

## Actualizacao v12.12.38 - Fase 2 - Cofre de segredos - cobertura, mascaramento e nao-vazamento

Segundo incremento da Fase 2 (cofre de segredos completo), sobre o incremento 1 que cifrou as credenciais dos gateways de pagamento. Quatro frentes, todas aditivas e sem alterar a forma como os segredos vivos sao guardados ou lidos.

Frente 1 - Mascaramento central. Nova funcao sige_secret_mask que esconde um segredo para exibicao, revelando apenas os ultimos caracteres e sem revelar o comprimento real, alem do mascaramento ja existente no repositorio de definicoes.

Frente 2 - Proibicao de segredos em registos. Nova funcao sige_secret_scrub que redige de um texto os tokens selados pelo cofre (formatos sige2: e gcm1:) e os pares chave=valor de segredos conhecidos, aplicada no canal de seguranca (sige_security_log) antes de qualquer registo. Um segredo nunca chega aos logs em claro; o texto normal passa intacto.

Frente 3 - Proibicao de segredos em URL. Um gate estatico garante que nenhuma chave-credencial e colocada num URL via add_query_arg, e a mesma redaccao trata URLs que sejam registados.

Frente 4 - Auditoria de alteracao. A gravacao de um segredo (no repositorio de definicoes para campos cifrados e SMTP, e na gravacao de segredos de pagamento) regista um evento segredo_alterado com a chave (e o fornecedor, nos pagamentos), nunca o valor. O registo de segredos conhecidos foi alargado para cobrir a chave de licenca.

Impacto na postura: aditivo. Ficheiros canonicos (finance-core e outros) nao foram tocados; o scrub liga-se ao canal de seguranca, nao ao registo de auditoria financeiro. Sem nova superficie de accao (199, enforce 33; views 60), sem opcoes novas (132), sem dependencias externas novas (11), sem alteracao de esquema, calculo academico e financeiro byte-identico. Rotacao de segredos e segredos por escola ficam para o incremento seguinte da Fase 2.

Testes especificos desta versao (cofre de segredos): (1) exibir um segredo mascarado e confirmar que so os ultimos caracteres aparecem. (2) provocar um registo de seguranca com um texto que contenha um token selado e um par como token=valor e confirmar que ambos aparecem como [SEGREDO] no registo, e que o texto normal fica intacto. (3) alterar um segredo (SMTP, gateway de pagamento) e confirmar que o registo de auditoria mostra segredo_alterado com a chave, nunca o valor. (4) confirmar que nenhum ecra coloca um segredo no endereco (URL).

## Actualizacao v12.12.39 - Fase 2 - Cofre de segredos - rotacao e segredos por escola

Terceiro incremento da Fase 2, sobre os incrementos 1 (gateways cifrados) e 2 (mascaramento, redaccao em registos e auditoria de alteracao). Camada de gestao sobre o cofre, totalmente aditiva.

Frente 1 - Segredos por escola. Novas funcoes sige_secret_set_for_school, sige_secret_get_for_school e sige_secret_delete_for_school guardam e leem um segredo isolado por escola, cifrado em repouso, com nome de opcao dinamico por escola (sufixo _esc). Texto em claro de instalacoes pre-cofre passa intacto. Generaliza o isolamento por escola que ja existia nos pagamentos.

Frente 2 - Rastreio de rotacao. Cada gravacao de segredo regista o instante da ultima rotacao; sige_secret_rotation_due indica se um segredo nunca foi rodado ou ja excedeu a idade maxima (constante SIGE_SECRET_ROTATION_DAYS, por omissao 180 dias).

Frente 3 - Geracao e rotacao. sige_secret_generate_token gera um segredo aleatorio forte e url-safe; sige_secret_rotate_for_school gera um novo segredo, guarda-o cifrado por escola, marca a rotacao, audita o evento segredo_rodado e devolve o novo segredo em claro para configurar no fornecedor.

Frente 4 - Rotacao concreta do webhook de pagamento. sige_mobile_payment_rotate_webhook_token gera e instala um novo webhook_token por escola, reutilizando o armazenamento cifrado e auditado que ja existia. Inclui ainda sige_vault_reseal, que re-sela um valor por higiene de formato sem nunca perder um valor nao decifravel.

Impacto na postura: aditivo. Nao altera o armazenamento nem a leitura dos segredos ja existentes, nao toca na cifra partilhada nem em ficheiros canonicos. Sem nova superficie de accao (199, enforce 33; views 60), sem opcoes novas (132; nomes de opcao por escola sao dinamicos), sem dependencias externas novas (11), sem alteracao de esquema, calculo academico e financeiro byte-identico. Com este incremento a cobertura central da Fase 2 fica completa.

Testes especificos desta versao (rotacao e segredos por escola): (1) gravar um segredo para duas escolas diferentes e confirmar que cada escola le o seu, e que o valor guardado esta cifrado. (2) confirmar que um segredo nunca rodado ou antigo (alem da idade maxima) aparece como a precisar de rotacao, e um acabado de gravar nao. (3) rodar o webhook_token de um fornecedor de pagamento por escola e confirmar que o novo token e gerado, fica guardado cifrado e o evento segredo_rodado e auditado sem o valor. (4) confirmar que um segredo legado em claro continua a ser lido.

## Actualizacao v12.12.40 - Fase 3 - Reconciliacao de pagamentos digitais - deteccao de duplicados

Primeiro incremento da Fase 3 (reconciliacao de pagamentos digitais, que completa a Fase 7 original). O circuito ja tinha idempotencia de webhooks (M-Pesa e e-Mola dedupam por escola e referencia) e um relatorio de divergencias so de leitura. Este incremento acrescenta uma camada de deteccao de duplicados logicos, totalmente so de leitura.

Nucleo puro testavel (sige_pagamentos_detectar_duplicados) que recebe as transacoes de gateway e devolve grupos de possiveis duplicados em tres classes: (1) referencia repetida; (2) mesmo pagamento conciliado mais de uma vez (indicio de duplo credito); (3) mesmo pagador e valor proximos no tempo (provavel pagamento repetido por engano). A janela de tempo evita falsos positivos em mensalidades legitimas.

Involucro de dominio por escola (sige_reconciliacao_duplicados), fail-closed e so de leitura, devolve os grupos com totais. A vista de reconciliacao passa a apresentar uma seccao de possiveis duplicados (so leitura, sem formularios).

Impacto na postura: aditivo e so de leitura. Nunca apaga nem altera pagamentos: so assinala para revisao. Nao toca em regras de calculo nem em ficheiros canonicos. Sem nova superficie de accao (199, enforce 33; views 60), sem opcoes novas (132), sem dependencias externas novas (11), sem alteracao de esquema, calculo academico e financeiro byte-identico. Reconciliacao viva e resolucao de divergencias com escrita ficam para incrementos seguintes.

Testes especificos desta versao (deteccao de duplicados): (1) confirmar que duas transacoes com a mesma referencia, ou o mesmo pagamento conciliado duas vezes, ou o mesmo pagador e valor com poucos minutos de intervalo aparecem assinalados como possiveis duplicados. (2) confirmar que o mesmo pagador e valor afastados no tempo (mensalidades) nao sao assinalados. (3) confirmar que a vista de reconciliacao mostra a seccao de possiveis duplicados e nao tem formularios (so leitura). (4) confirmar que nada e apagado nem alterado pela deteccao.

## Actualizacao v12.12.41 - Fase 3 - Reconciliacao de pagamentos digitais - reconciliacao viva

Segundo incremento da Fase 3, sobre o incremento 1 (deteccao de duplicados). Confirma as transacoes locais contra o estado real no gateway (M-Pesa via queryTransactionStatus; e-Mola via o seu endpoint de consulta, ambos ja existentes nos clientes). E so de leitura: nunca apaga, cria nem altera pagamentos nem transacoes; apenas classifica e regista divergencias para revisao humana.

Camadas: (1) nucleo puro testavel (sige_pagamentos_reconciliar_estado), que classifica em confirmadas, divergentes (valor diferente do gateway, ou rejeitada pelo gateway) e inconclusivas; (2) normalizador (sige_pagamentos_normalizar_estado_gateway) deliberadamente conservador, que so afirma confirmada num sucesso claro e falhada num codigo de falha conhecido (lista vazia por omissao, ajustavel pelo filtro sige_mpesa_codigos_falha), ficando todo o resto desconhecida, pelo que nunca acusa uma transacao real de falsa; (3) adaptador (sige_pagamentos_consultar_gateway) defensivo, que liga aos clientes reais e nunca lanca; (4) orquestracao por escola (sige_reconciliacao_viva), fail-closed e so leitura, que regista as divergencias; (5) passagem diaria no cron sige_evento_diario, guardada pelo modo de teste, limitada e so corre se algum gateway estiver configurado.

Impacto na postura: aditivo e so de leitura. Nao toca em regras de calculo nem em ficheiros canonicos. Sem nova superficie de accao (199, enforce 33; views 60; o cron liga-se a um evento ja existente, sem novo gancho), sem opcoes novas (132), sem dependencias externas novas (11), sem alteracao de esquema, calculo academico e financeiro byte-identico. A accao (conciliar ou rejeitar) fica para um incremento de escrita com quatro-olhos; o ajuste fino dos codigos de falha depende do sandbox das operadoras.

Testes especificos desta versao (reconciliacao viva): (1) com uma consulta simulada, confirmar que uma transacao confirmada pelo gateway com o mesmo valor entra em confirmadas, valor diferente ou rejeitada pelo gateway entram em divergentes, e desconhecida ou erro ficam inconclusivas. (2) confirmar que um codigo de resposta desconhecido nunca e tratado como falha (conservador). (3) confirmar que o cliente ausente ou um erro devolvem sempre estado erro (defensivo). (4) confirmar que a orquestracao e fail-closed para escola invalida e que nada e apagado nem alterado.

## Actualizacao v12.12.42 - Fase 3 - Reconciliacao de pagamentos digitais - resolucao com escrita (quatro-olhos)

Terceiro incremento da Fase 3, sobre o incremento 1 (deteccao de duplicados) e o incremento 2 (reconciliacao viva). Torna a reconciliacao accionavel sob controlo duplo: conciliar uma transacao confirmada, ou rejeitar uma rejeitada pelo gateway, atraves do framework de quatro-olhos ja existente (sige_fin_aprovacao_*). Um utilizador propoe (maker) e um SEGUNDO utilizador autorizado aprova (checker); so entao a accao e executada. A separacao de funcoes (maker diferente de checker) e imposta pelo framework.

A escrita financeira passa SEMPRE pelo caminho canonico sige_fin_registar_pagamento; este incremento nunca calcula valores nem altera regras financeiras. A execucao re-valida o estado da transacao no momento da aprovacao (pode ter mudado entre a proposta e a decisao), e idempotente, respeita o tenant e e fail-closed; a rejeicao nunca toca numa transacao ja conciliada. Os dois tipos novos (recon_conciliar, recon_rejeitar) reutilizam a permissao existente financeiro.mobile_payments_gerir, pelo que nao ha nova permissao nem nova regra do kernel. A UI do maker e so server-side na vista de gestao; o checker usa a fila generica de aprovacoes ja existente. Os botoes directos de conciliar e rejeitar continuam intactos.

Impacto na postura: aditivo e controlado. Sem nova superficie de accao (199, enforce 33; a UI do maker e server-side e o checker reutiliza accoes existentes), sem nova vista (60; a vista de gestao foi estendida, nao criada), sem opcoes novas (132), sem dependencias externas novas (11), sem alteracao de esquema, calculo academico e financeiro byte-identico. A seguir: ajuste fino dos codigos de falha do gateway (depende do sandbox) e, opcionalmente, escolha de lancamento especifico na proposta de conciliacao.

Testes especificos desta versao (resolucao com escrita / quatro-olhos): (1) a conciliacao executada delega no caminho canonico sige_fin_registar_pagamento, com o lancamento e o valor certos, e marca a transacao conciliada registando o aprovador. (2) a rejeicao executada marca rejeitada, e idempotente quando ja rejeitada, e FALHA quando a transacao ja foi conciliada. (3) ambas as execucoes sao fail-closed para escola invalida. (4) as propostas criam apenas o pedido pendente (recon_conciliar ou recon_rejeitar) e nao executam nada. (5) os dois tipos estao registados com a permissao reutilizada e o despacho de aprovacoes encaminha para a execucao correcta.

## Actualizacao v12.12.43 - Fase 3 - Afinacao dos codigos do gateway e escolha de lancamento

Dois afinamentos entregues juntos, sobre os incrementos 1 a 3 da Fase 3.

(A) Afinacao dos codigos de falha do gateway. O normalizador da reconciliacao viva passa a dar prioridade ao estado da transacao reportado pelo gateway (o queryTransactionStatus do M-Pesa devolve-o em output_ResponseTransactionStatus). Um INS-0 apenas diz que a consulta foi processada, nao que a transacao teve sucesso; por isso, uma transacao com estado Failed deixa de ser tomada por confirmada. As listas de estados de sucesso e de falha sao ajustaveis pelos filtros sige_mpesa_estados_sucesso e sige_mpesa_estados_falha (defaults sensatos em ingles e portugues); a lista de codigos de resposta de falha continua ajustavel por sige_mpesa_codigos_falha. Mantem-se o conservadorismo: um estado nao mapeado fica desconhecida e nunca acusa uma transacao real de falsa. A mudanca e retro-compativel: sem campo de estado, o comportamento e o anterior (por codigo de resposta).

(B) Escolha de lancamento especifico na proposta de conciliacao com quatro-olhos. A vista de gestao passa a carregar os lancamentos em aberto do aluno e a oferecer um seletor na proposta; a funcao de execucao ja validava e usava o lancamento escolhido pelo caminho canonico. Mantem-se a opcao de correspondencia automatica.

Impacto na postura: aditivo e mais exacto. Sem nova superficie de accao (199, enforce 33), sem nova vista (60; a vista de gestao foi estendida), sem opcoes novas (132; a afinacao e por filtro, nao por opcao), sem dependencias externas novas (11), sem alteracao de esquema, calculo academico e financeiro byte-identico. O ajuste fino final dos codigos depende do sandbox das operadoras.

Testes especificos desta versao: (A) com o estado da transacao presente, INS-0 com Completed e confirmada, INS-0 com Failed e falhada (a consulta ok nao basta), e um estado nao mapeado (Pending) fica desconhecida; sem campo de estado, o comportamento anterior por codigo de resposta mantem-se (retro-compativel). (B) a proposta de conciliacao com quatro-olhos aceita um lancamento escolhido e a execucao usa-o pelo caminho canonico, validando escola e aluno; a correspondencia automatica continua disponivel.

## Actualizacao v12.12.44 - Fase 3 - Ajuste fino dos codigos e estados por operadora

Ajuste fino final da reconciliacao viva: a classificacao de uma transacao contra o gateway passa a ser POR OPERADORA. Nova funcao sige_pagamentos_mapa_estados_gateway(provider) que devolve, para cada provider, as listas de estados de sucesso e de falha e os codigos de resposta de falha. O normalizador sige_pagamentos_normalizar_estado_gateway passa a receber o provider e a usar esse mapa, e o adaptador passa o provider (mpesa ou emola) ao normalizador.

Defaults fundamentados na documentacao publica: o M-Pesa (Vodacom, OpenAPI) reporta o estado da transacao em ResponseTransactionStatus (ex.: Completed) e um INS-0 confirma apenas a consulta, pelo que os codigos de resposta de falha ficam vazios por omissao (a falha e expressa pelo estado); o e-Mola (Movitel) mantem-se conservador com termos genericos enquanto a API de consulta nao esta documentada. As tres listas sao sobreponiveis por filtro COM o provider como contexto (sige_mpesa_estados_sucesso, sige_mpesa_estados_falha e sige_mpesa_codigos_falha recebem agora um segundo argumento, o provider), permitindo afinacao por operadora num unico filtro e garantindo isolamento: um codigo ou estado de falha de uma operadora nao contamina a outra.

Impacto na postura: aditivo e mais exacto, por operadora. Mantem-se o conservadorismo (estado nao mapeado -> desconhecida) e a retro-compatibilidade (chamada sem provider usa os defaults comuns; filtros antigos de um argumento continuam a funcionar). Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132; a afinacao e por filtro), sem dependencias externas novas (11), sem alteracao de esquema, calculo academico e financeiro byte-identico. O ajuste fino definitivo dos valores exactos depende do sandbox de cada operadora; a estrutura por operadora ja esta pronta para os receber.

Testes especificos desta versao: o mapa por operadora separa os codigos e estados de falha (M-Pesa difere do e-Mola); um codigo ou estado de falha definido para uma operadora e falhada nessa operadora e desconhecida na outra (isolamento); o normalizador continua conservador (estado nao mapeado -> desconhecida) e retro-compativel (sem provider, defaults comuns); o smoke da reconciliacao viva sobe para 27 verificacoes.

## Actualizacao v12.12.45 - Fase 4 incr 1 - Self-host das bibliotecas e infra de enqueue

Incremento 1 da Fase 4 (CSP e front-end seguro). As sete bibliotecas de front-end que vinham de CDN (Chart.js, xlsx, exceljs, Sortable, qrious, FileSaver e html5-qrcode) passam a ser servidas pela propria instalacao a partir de assets/vendor/, com SRI (integrity) recalculado a partir dos ficheiros locais. O catalogo central includes/cdn-scripts.php (sige_cdn_catalog) passa a construir as URLs a partir de SIGE_URL, sem literais https:// de CDN no codigo; os treze ecrans que chamam sige_cdn_script continuam a funcionar sem alteracao, agora a carregar localmente. A referencia directa a unpkg na portaria e o fallback do bootstrap tambem passam a local.

Novo includes/assets-registry.php: registador central que regista as bibliotecas locais como handles do WordPress (sige_assets_libs, sige_assets_base_url, sige_assets_registar no admin_enqueue_scripts, sige_enqueue_lib) e injecta SRI (integrity e crossorigin) nas tags enfileiradas, atraves do filtro script_loader_tag. E a base para a migracao dos blocos inline para wp_enqueue nas vagas seguintes e para o CSP em enforcement.

Governanca: o scan passa a excluir assets/vendor/, por o codigo vendorizado ser first-party self-hosted e nao uma chamada externa nossa. Com isso e a remocao dos literais de CDN, as dependencias externas descem de 11 para 9 (cdnjs e unpkg removidos); os hosts restantes sao api.z-api.io, fonts.googleapis.com, purl.org, rmbjconsulting.com, schemas.openxmlformats.org, softgenial.edu.mz, ui-avatars.com, wa.me e www.w3.org. Sem nova superficie de accao (199, enforce 33; o registo usa o hook admin_enqueue_scripts, ja rastreado), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo academico e financeiro byte-identico. Esta e a camada habilitadora: nao muda o comportamento dos ecrans.

Testes especificos desta versao: o catalogo produz URLs locais (assets/vendor/) sem literais de CDN; sige_cdn_script emite src local com integrity; o registador regista os sete handles locais e o filtro injecta SRI nas tags dos nossos handles e deixa as outras inalteradas; todos os ficheiros vendor existem com tamanho; e zero literais cdnjs/unpkg em todo o codigo de runtime. Novos gate e smoke check/smoke-fase4-assets (26 verificacoes no smoke); corredor 97.

## Actualizacao v12.12.46 - Fase 4 incr 2 - Migracao de inline (vaga 1) e catraca

Segundo incremento da Fase 4 (CSP e front-end seguro): comeca a migracao do front-end inline para fora do HTML, por vagas, com um mecanismo de delegacao e um gate de catraca.

Mecanismo: novo despachante declarativo data-sige-act em assets/sige-ui.js (ja enfileirado nas paginas sige-app, junto do enhancer data-sige-confirm ja existente). Um unico ouvinte delegado dispara a funcao global indicada em data-sige-act, passando data-sige-arg como argumento ou, na sua ausencia, o proprio elemento (equivalente ao this do onclick). E reutilizavel e permite remover onclick das views por vagas, sem JS por ecra e sem alterar a logica dos handlers.

Primeira vaga: admin/finance/financeiro-lancamentos-view.php passa os seus dez onclick para data-sige-act (exportacao, abrir e fechar modais de detalhe, anulacao, isencao e reactivacao). Os handlers que recebiam um id passam-no por data-sige-arg; o de detalhes recebe o elemento. Apenas muda a forma de ligar o clique a funcao; nenhuma logica de handler nem regra financeira e tocada, e os ficheiros canonicos ficam intactos.

Catraca: novo gate tools/check-inline-frontend.php conta as ocorrencias de onclick=, style=" e blocos <script> sem src nas views e includes e exige que nunca aumentem face a baseline (onclick 260 -> 250; style 2191; script 81). Cada vaga futura baixa estes maximos, nunca os sobe. Impacto na postura: aditivo, mais seguro no navegador, sem alteracao de superficie (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem dependencias novas (9), sem alteracao de esquema, calculo academico e financeiro byte-identico.

Testes especificos desta versao: o ficheiro financeiro-lancamentos-view.php fica com zero onclick (passou de 10) e dez atributos data-sige-act; o despachante data-sige-act existe em assets/sige-ui.js; e a catraca confirma que onclick=250, style=2191 e <script>=81 nao excedem a baseline. As funcoes dos handlers mantem-se inalteradas (so muda a forma de as ligar ao clique).

## Actualizacao v12.12.47 - Fase 4 incr 2 vaga 2 - Estilos academicos para utilitarios

Segunda vaga do incremento 2 da Fase 4. Sequencia a vaga 1 (v12.12.46), que introduziu o despachante declarativo data-sige-act e migrou os onclick da area financeira. Esta vaga migra o estilo inline da area academica para classes utilitarias.

Novo assets/sige-utilities.css com doze utilitarios: alinhamento (esquerda, centro, direita), peso de letra (700, 600, 500), margin a zero, flex-shrink a zero, flex a um, white-space nowrap, overflow-x auto e width a 100 por cento. Cada utilitario leva !important para replicar a especificidade do estilo inline (1000) e evitar regressao de cascata. O ficheiro e enfileirado no admin-shell, dependente do sige-design-system, nas paginas do SIGE.

Migracao conservadora aplicada a dez vistas academicas (abertura, acta, alunos, aprovar notas, auditoria de notas, boletim, encerramento, estatisticas demograficas, matriz e turmas): 49 trocas, so em atributos style 100 por cento compostos por declaracoes seguras, sem PHP interpolado e sem class ja existente no elemento. Os casos com class existente e os display ficam para vagas futuras, para nao arriscar fusao de classes nem alternancia por JavaScript. Outros atributos do elemento (colspan, method, etc.) sao preservados.

A catraca check-inline-frontend desce o maximo de style de 2191 para 2142, travando esta reducao; onclick mantem-se em 250 e blocos script em 81. O mecanismo foi consolidado: existe um unico gate de catraca (o duplicado foi removido) e o smoke smoke-inline-frontend passou a estar registado no corredor. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo academico e financeiro byte-identico. Dependencias externas mantem-se em 9. Sem mudanca de comportamento visual: o aspecto e o mesmo, agora a partir de classes reutilizaveis.

Testes especificos desta versao: o sige-utilities.css existe com os doze utilitarios e pelo menos doze !important; esta enfileirado no admin-shell; as vistas academicas migradas usam classes sige-u- (mais de trinta usos) e nao tem atributos class duplicados; nao restam os estilos puros migrados (style text-align center, style margin zero) nessas vistas; e a catraca trava style em 2142 (sem folga), provando a reducao. Smoke smoke-inline-frontend (18 verificacoes) registado no corredor; corredor 99.

## Actualizacao v12.12.48 - Fase 4 incr 2 vaga 3 - Estilos financeiros para utilitarios

Terceira vaga do incremento 2 da Fase 4. Sequencia a vaga 1 (v12.12.46, despachante data-sige-act e onclick financeiros) e a vaga 2 (v12.12.47, estilos academicos). Reutiliza o assets/sige-utilities.css introduzido na vaga 2 e migra agora o estilo inline da area financeira.

Migracao conservadora de 32 atributos style em sete vistas financeiras (config, devedores, gerador, pagamentos, planos, relatorio mensal e mpesa), so quando o style e 100 por cento composto por declaracoes do mapa seguro, estaticas, sem PHP interpolado e sem class ja existente no elemento. Os casos com class continuam por migrar, por desenho, para nao arriscar fusao de classes. Outros atributos do elemento sao preservados.

A catraca check-inline-frontend desce o maximo de style de 2142 para 2110, travando a reducao; onclick mantem-se em 250 e blocos script em 81. Uma das trocas move uma guarda de scroll de tabela (style overflow-x auto) para a classe sige-u-oxa em financeiro-pagamentos; para nao ler isso como tabela a estourar, o diag-responsivo (ja na vaga 2) e agora tambem o smoke-regression-pack passam a contar a classe sige-u-oxa como guarda valida, mantendo a deteccao de tabelas genuinamente desprotegidas. O smoke da catraca foi estendido as vistas financeiras.

Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9. Sem mudanca de comportamento visual.

Testes especificos desta versao: o sige-utilities.css continua com os doze utilitarios e !important e enfileirado; as vistas financeiras migradas usam classes sige-u-, sem class duplicada; a catraca trava style em 2110 (sem folga); e as guardas de scroll do financeiro-pagamentos contam tanto o overflow-x auto inline como a classe sige-u-oxa, com o smoke-regression-pack verde (306 invariantes). Corredor 99.

## Actualizacao v12.12.49 - Fase 4 incr 2 - Fixe de impressao utilitarios e vaga admin-shell e jardim

Duas partes no mesmo incremento.

PARTE A - correccao de impressao das classes utilitarias. As vagas 2 (v12.12.47) e 3 (v12.12.48) migraram estilos inline para classes utilitarias (sige-u-), que so resolvem onde o assets/sige-utilities.css esta carregado, ou seja na shell do admin. Cinco vistas, porem, abrem janelas de impressao autonomas (window.open mais document.write) que clonam ou geram marcacao com essas classes e levam o seu proprio <style>, sem o sige-utilities.css. No impresso, as classes ficavam sem efeito (alinhamentos e pesos perdidos). Introduziu-se o helper sige_utilities_inline_css() em includes/assets-registry.php, que devolve as 12 regras com !important, e embebeu-se no <style> de cada popup afectado: boletim, alunos_lista, turmas (dois popups), financeiro-devedores e, por uniformidade defensiva, estatisticas (cujo clone ja remove o atributo class). Uma guarda nova no smoke-inline-frontend obriga a esse contrato: qualquer vista com popup de impressao e classes sige-u- tem de embeber o helper. Isto trava regressoes futuras.

PARTE B - vaga admin-shell e jardim. Migracao conservadora de 59 estilos inline para classes utilitarias: includes/admin-shell.php (55, sobretudo icones com flex-shrink) e admin/jardim/jardim_relatorio-view.php (4), ambos sem popup. Ficheiros que produzem saida autonoma (includes/documents-engine.php com geracao de PDF/HTML, includes/portal-logic.php com vista de portal, admin/jardim/jardim_boletim-view.php com popup de impressao) e ficheiros canonicos ficam de fora, por desenho, porque a saida deles nao carrega o sige-utilities.css.

A catraca check-inline-frontend desce o maximo de style de 2110 para 2051; onclick mantem-se em 250 e blocos script em 81. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9. Sem mudanca de comportamento visual.

Testes especificos desta versao: o helper sige_utilities_inline_css() devolve as 12 regras com !important, sem aspas simples nem backticks (seguro de embeber em qualquer janela de impressao); as cinco vistas com popup e classes sige-u- chamam o helper (guarda do smoke-inline-frontend verde); o admin-shell continua a enfileirar o sige-utilities.css e migrou 55 icones para sige-u-shrink-0 sem class duplicada; a catraca trava style em 2051 (sem folga). Corredor 99, regression-pack verde, Responsivo em 0.

## Actualizacao v12.12.50 - Fase 4 incr 2 - Onclick para data-sige-act (vistas limpas)

Inicio da migracao dos manipuladores de evento inline (onclick) para o despachante declarativo data-sige-act, a caminho de poder impor o CSP. Sequencia a vaga 1 (v12.12.46, que criou o despachante e tratou os onclick do financeiro-lancamentos).

Primeiro, o despachante em assets/sige-ui.js passa a ter um contrato fiel ao onclick que substitui:
- data-sige-arg chama fn(arg) (substitui onclick com um argumento de texto);
- data-sige-noargs chama fn() (sem passar nada, para funcoes cujo 1o parametro e significativo, por exemplo plToggleNovoPlano(force));
- caso contrario chama fn(elemento), equivalente ao this.
O marcador data-sige-noargs e novo e mantem total retro-compatibilidade: as conversoes da vaga 1 (que nao o usam) continuam a receber o elemento, exactamente como antes.

Depois, converteram-se 49 onclick SEGUROS (sem risco de tipo nem de contexto) em dez vistas sem popup e nao-autonomas: padrao fn() (42, agora com data-sige-noargs), fn(this) (6) e fn('texto') (1). Vistas com janela de impressao autonoma (onde o despachante nem carrega), templates de PDF, portal e ficheiros canonicos ficam de fora, por desenho. Os onclick com numeros, multiplos argumentos ou PHP interpolado ficam para vagas com despachante mais rico.

A catraca check-inline-frontend desce o maximo de onclick de 250 para 201, travando a reducao; o gate passa tambem a exigir o contrato data-sige-arg/data-sige-noargs/elemento no despachante. style mantem-se em 2051 e blocos script em 81. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9. Sem mudanca de comportamento.

Testes especificos desta versao: o despachante honra data-sige-arg (fn(arg)), data-sige-noargs (fn(), sem passar o elemento) e o caso por omissao (fn(elemento)); a funcao plToggleNovoPlano (que usa o 1o parametro) ficou com data-sige-noargs, sendo chamada fn() como no onclick original; a catraca trava onclick em 201 (sem folga); e dez vistas reduziram os seus onclick (acta-view e financeiro-despesas ficaram a zero). Corredor 99, regression-pack verde.

## Actualizacao v12.12.51 - Fase 4 incr 2 - Onclick complexos para data-sige-args (despachante mais rico)

Migracao dos onclick COMPLEXOS (numeros, booleanos, multiplos argumentos e PHP interpolado) para o despachante declarativo, com um despachante mais rico. Sequencia as vagas de onclick anteriores (v12.12.46 criou o despachante; v12.12.50 tratou os onclick simples).

Primeiro, o despachante em assets/sige-ui.js ganha duas capacidades novas e aditivas:
- data-sige-args: recebe uma lista JSON e chama fn aplicando esses argumentos, preservando tipos (numeros, booleanos, varios argumentos). O JSON e lido com try/catch, pelo que um valor invalido nao dispara a funcao.
- data-sige-self: em conjunto com data-sige-args, anexa o proprio elemento como ultimo argumento (substitui o padrao onclick="fn('x', this)").
As regras anteriores (data-sige-arg para fn(arg), data-sige-noargs para fn() e o caso por omissao fn(elemento)) ficam inalteradas, mantendo total retro-compatibilidade.

Depois, converteram-se 20 onclick complexos em oito vistas limpas: PHP de um argumento (data-sige-arg), PHP de dois argumentos id mais nome (data-sige-args com wp_json_encode), PHP string ou id mais this (data-sige-args mais data-sige-self), literais booleanos (data-sige-args preservando o tipo) e uma string codificada. Para o PHP, o atributo e construido com esc_attr(wp_json_encode([...])), o que escapa correctamente aspas, e comercial, sinais de maior e menor e acentos, sendo o JSON.parse no navegador fiel ao valor original (validado por simulacao de renderizacao).

As expressoes inline (window.print, this.style, this.classList, IIFE, jQuery, window.open(this.href), window.location) e os onclick gerados em template JS NAO se convertem nesta vaga: ficam para a vaga de funcoes nomeadas. Uma chamada de quatro argumentos fica diferida (o conversor cobre ate dois argumentos mais this).

A catraca check-inline-frontend desce o maximo de onclick de 201 para 181; o gate passa tambem a exigir o contrato rico data-sige-args/data-sige-self/JSON no despachante. style mantem-se em 2051 e blocos script em 81. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9. Sem mudanca de comportamento.

Testes especificos desta versao: o despachante honra data-sige-args (fn aplicando a lista JSON, com tipos preservados), data-sige-self (anexa o elemento como ultimo argumento), e mantem data-sige-arg, data-sige-noargs e o caso por omissao; um JSON invalido em data-sige-args nao dispara a funcao (try/catch). A simulacao de renderizacao confirma que esc_attr(wp_json_encode([...])) escapa aspas, e comercial, maior e menor e acentos, e que o JSON.parse no navegador devolve o valor original (testado com nomes contendo aspas e simbolos). A catraca trava onclick em 181 (sem folga). As vistas convertidas mantem o comportamento dos botoes. Corredor 99, regression-pack verde.

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

Testes especificos desta versao: as sete funcoes nomeadas estao expostas em window e o despachante encontra-as; sigeImprimirPagina e o hamburguer nao recebem o elemento (data-sige-noargs), enquanto as demais recebem o elemento e replicam a expressao original (alternar whiteSpace, alternar classe do irmao seguinte, fechar backdrop, alternar e fechar o menu lateral com gestao do aria-expanded). O menu lateral movel do shell continua a abrir e fechar como antes; o botao de imprimir abre a caixa de impressao; a alternancia de quebra de texto na auditoria funciona. A catraca trava onclick em 171 (sem folga). node -c limpo. Corredor 99, regression-pack verde.

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

Testes especificos desta versao: o despachante honra data-sige-prevent (chama preventDefault antes de disparar a funcao); os links de recibo no portal do aluno abrem a janela de recibo sem navegar a pagina, mantendo o href; o botao de contacto abre o cliente de email (mailto); os botoes de adiar o aviso de cobranca continuam a adiar; os botoes gerados em template JS (remover criterio, remover da matriz) continuam a funcionar com o id e o elemento correctos. A catraca trava onclick em 164 (sem folga). node -c limpo. Corredor 99, regression-pack verde.

## Actualizacao v12.12.54 - Fase 4 incr 2 - Cadeias jQuery e IIFE em onclick reescritas em vanilla

Reescrita das ULTIMAS cadeias jQuery e IIFE em onclick para vanilla, em funcoes nomeadas. Conclui a migracao dos onclick em ficheiros limpos (sem janela autonoma, sem popup e nao canonicos): a partir desta versao, nao resta nenhum onclick jQuery ou IIFE nessas vistas. Sequencia as vagas anteriores (v12.12.46 a v12.12.53).

Converteram-se dois onclick, cada um numa vista, com a funcao nomeada definida na propria vista (co-localizada com o uso, em vez de poluir o kit global com selectores especificos), exposta em window para o despachante a encontrar via window[accao]:
- Central de WhatsApp: o botao de tentar novamente, gerado em template JS, usava uma funcao imediatamente invocada que fecha e reabre o painel de pendentes (um elemento details) apos um instante; passou a data-sige-act mais data-sige-noargs, com a funcao sigeReabrirPainelPendentes a fazer o mesmo em vanilla.
- Matriz curricular: o botao de cancelar do modal de clonagem usava uma cadeia jQuery (esconder o modal, marcar aria-hidden e, se nenhum modal estiver visivel, remover a classe do corpo); passou a data-sige-act mais data-sige-noargs, com a funcao sigeFecharModalClone a fazer o mesmo em vanilla, reproduzindo o teste de visibilidade do jQuery por offsetWidth, offsetHeight e getClientRects.

Os onclick que restam estao em ficheiros com janela autonoma, em popups ou em ficheiros canonicos, onde o despachante nao se aplica; ficam para abordagem propria.

A catraca check-inline-frontend desce o maximo de onclick de 164 para 162; style mantem-se em 2051 e blocos script em 81. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9. Sem mudanca de comportamento.

Testes especificos desta versao: na central de WhatsApp, o botao de tentar novamente (gerado em template JS) reabre o painel de pendentes como antes; na matriz curricular, o botao de cancelar do modal de clonagem fecha o modal, marca aria-hidden e, se nenhum modal estiver visivel, retira a classe do corpo, exactamente como a cadeia jQuery anterior (o teste de visibilidade e reproduzido por offsetWidth, offsetHeight e getClientRects). As duas funcoes estao expostas em window e o despachante encontra-as. A catraca trava onclick em 162 (sem folga), e nao resta nenhum onclick jQuery ou IIFE em ficheiros limpos. node -c limpo (funcoes extraidas). Corredor 99, regression-pack verde.

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

Testes especificos desta versao: o nonce e consistente dentro do mesmo pedido (a mesma chamada devolve sempre o mesmo valor) e o atributo renderiza como nonce com um valor base64 sem aspas, seguro para atributo HTML; uma tag <script <?php echo sige_csp_script_attr(); ?>> renderiza como script com nonce. As 55 tags convertidas continuam a comportar-se como antes (o nonce e ignorado sem cabecalho CSP). A catraca trava blocos script em 25 (sem folga) e falha se a infraestrutura de nonce desaparecer. Os verificadores de JS embebido validam 66 blocos sem erro de sintaxe, ja com as tags com nonce normalizadas para extraccao. php -l limpo nas 48 fontes tocadas (47 vistas mais o core-helpers). Corredor 99, regression-pack verde.

## Actualizacao v12.12.56 - Fase 4 incr 2 - Nonce CSP nos redireccionamentos echo

Aplicacao do nonce CSP aos redireccionamentos construidos por echo (echo de uma tag script com window.location ou location) nas vistas autenticadas. Continua a vaga v12.12.55 (nonce nas tags script ao nivel do template) e usa a mesma infraestrutura de nonce do core-helpers (sige_csp_nonce e sige_csp_script_attr).

A conversao e por concatenacao em string, mantendo o atributo nonce no proprio tag emitido: uma tag passa de echo de script para echo de script com o auxiliar concatenado, produzindo script com nonce. Acrescentar o nonce e inocuo enquanto o cabecalho CSP nao for activado: os navegadores ignoram o nonce sem uma politica CSP, pelo que nao ha alteracao de comportamento e os redireccionamentos continuam a funcionar como antes.

Converteram-se 11 redireccionamentos em 6 vistas: dashboard (2), disciplinas (2, com aspas duplas), encerramento (2), financeiro-config (3), notas (1) e transporte (1). Trataram-se os dois tipos de aspa (simples e dupla) com a concatenacao correspondente.

A conversao so altera a tag de abertura, nunca o conteudo extraido pelos verificadores de JavaScript embebido nas vistas: o motor de extraccao absorve a concatenacao ate ao fecho do tag e o conteudo apos o tag mantem-se igual, pelo que os dois verificadores se mantem verdes sem qualquer alteracao.

Por desenho ficaram de fora, para abordagem propria: as paginas autonomas (modelos PDF de pauta e boletim, boletim do jardim, motor de documentos, pagina segura da camara da portaria), que emitem HTML proprio e merecem cabecalho CSP proprio; o portal publico; o ficheiro canonico finance-core; a pagina de login (pre-autenticacao); e o popup construido por document.write em financeiro-extratos.

A catraca check-inline-frontend desce o maximo de blocos script de 25 para 14; onclick mantem-se em 162 e style em 2051. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9.

Testes especificos desta versao: a conversao por concatenacao produz, em ambos os tipos de aspa, uma tag script com um atributo nonce com valor base64 e sem script puro; o mesmo nonce e usado em todos os scripts do mesmo pedido. Os 11 redireccionamentos continuam a redireccionar como antes (o nonce e ignorado sem cabecalho CSP). Os dois verificadores de JavaScript embebido validam os blocos sem erro de sintaxe, sem alteracao face a versao anterior, porque o conteudo extraido nao muda. A catraca trava blocos script em 14 (sem folga). php -l limpo nas 6 vistas tocadas. Corredor 99, regression-pack verde.

## Actualizacao v12.12.57 - Fase 4 incr 2 - Nonce CSP nas paginas autonomas

Aplicacao do nonce CSP as tags script ao nivel do template das paginas autonomas (modelos PDF de pauta e boletim, boletim do jardim, motor de documentos, pagina segura da camara da portaria). Conclui a aplicacao do nonce a todas as tags script inline alcancaveis: apos esta vaga, todos os scripts inline convertiveis (vistas no shell, redireccionamentos por echo e paginas autonomas) levam o nonce por pedido. Usa a mesma infraestrutura do core-helpers (sige_csp_nonce e sige_csp_script_attr).

Acrescentar o nonce e inocuo: nestas paginas ainda nao ha um CSP que referencie o nonce, e no motor de documentos, que ja envia um CSP, esse CSP usa unsafe-inline sem nonce na directiva, pelo que o unsafe-inline continua activo e os scripts correm como antes (acrescentar o atributo nonce nao altera o comportamento).

Converteram-se 7 tags em 5 ficheiros: pauta-pdf-template (1), boletim-pdf-template (1), jardim_boletim-view (1), documents-engine (3) e portaria-camera-safe-page (1).

Os cabecalhos de seguranca existentes nao foram tocados: o motor de documentos mantem os seus tres cabecalhos Content-Security-Policy e a pagina da camara mantem o seu Permissions-Policy.

Os modelos PDF em admin sao varridos pelos verificadores de JavaScript embebido, que ja normalizam a tag com nonce desde a v12.12.55, pelo que se mantem verdes. O motor de documentos e a pagina da camara estao em includes, fora do alcance desses verificadores.

Nota para o passo seguinte: estas paginas tambem usam onclick inline (botoes de impressao), que um CSP estrito baseado em nonce bloqueia; impor o CSP nestas paginas exige primeiro converter esses onclick, pelo que o enforcement do CSP fica para incremento dedicado.

A catraca check-inline-frontend desce o maximo de blocos script de 14 para 7; onclick mantem-se em 162 e style em 2051. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9.

Testes especificos desta versao: as 7 tags das paginas autonomas passam a renderizar como script com nonce, com o mesmo nonce por pedido. Os cabecalhos de seguranca existentes mantem-se (tres Content-Security-Policy no motor de documentos, Permissions-Policy na camara). No motor de documentos, o CSP mantem unsafe-inline sem nonce na directiva, pelo que os scripts continuam a correr e os botoes de impressao (onclick) continuam a funcionar. Os verificadores de JavaScript embebido validam os modelos PDF em admin sem erro de sintaxe. A catraca trava blocos script em 7 (sem folga). php -l limpo nos 5 ficheiros tocados. Corredor 99, regression-pack verde.

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

Testes especificos desta versao: os botoes de impressao, fecho e impressao de boletins das paginas autonomas continuam a funcionar, agora por addEventListener local; o imprimir um boletim passa o indice por atributo data e reconverte-o a numero com parseInt. A ligacao corre ao nivel de topo do script, fora dos IIFE de QRious, pelo que funciona mesmo sem a biblioteca de QR. Os cabecalhos de seguranca existentes mantem-se (tres Content-Security-Policy no motor de documentos, Permissions-Policy na camara). Os verificadores de JavaScript embebido validam os modelos PDF em admin sem erro de sintaxe. A catraca trava onclick em 149 (sem folga). php -l limpo nos 4 ficheiros tocados. Corredor 99, regression-pack verde.

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

Testes especificos desta versao: o nonce do cabecalho CSP e o nonce das tags script provem ambos de sige_csp_nonce e casam exactamente (esc_attr nao altera o base64, mesmo com os caracteres mais barra igual). As paginas autonomas tem zero handlers inline e zero URLs javascript, todos os scripts inline com nonce e os externos (QRious, html5-qrcode) servidos da propria origem, pelo que o script-src self mais nonce nao bloqueia nada. A validacao de crachas da camara vai por fetch para admin-ajax na propria origem, coberta por connect-src self. A catraca exige script-src com nonce nas paginas autonomas e falha se alguma regressar a unsafe-inline no script-src. php -l limpo nos ficheiros tocados. Corredor 99, regression-pack verde.

## Actualizacao v12.12.60 - Fase 4 incr 2 - onclick seguros das vistas admin em data-sige-act

Conversao dos onclick seguros das vistas admin para o despachante data-sige-act, preparando o CSP enforce do shell admin. Depois do enforce das paginas autonomas (v12.12.59), o passo para um script-src baseado em nonce no shell admin exige remover os handlers inline das vistas admin.

Estas vistas, ao contrario das paginas autonomas, correm no shell que carrega o despachante global, pelo que a conversao usa data-sige-act (o mesmo mecanismo ja em producao e validado pelos gates), e nao addEventListener local.

Esta vaga converte apenas o subconjunto inequivocamente seguro:
- 52 onclick sem argumentos: fn() passa a data-sige-act mais data-sige-noargs.
- 4 onclick com um inteiro: fn(<?php echo (int)id ?>) passa a data-sige-act mais data-sige-args com a lista JSON, que preserva o tipo numero.

Total 56 conversoes em 8 vistas: alunos_lista (17 sem-arg, 4 inteiro), turmas-view (10), financeiro-extratos (10), equipe-view (8), dec-view (2), estatisticas-demograficas-view (2), pauta-final-view (2), boletim-view (1). O comportamento e identico: o despachante chama a mesma funcao com os mesmos argumentos.

Excluidos desta vaga, por exigirem mais cuidado e ficarem para incrementos proprios: os onclick com event (switchTab), com codigo inline (sessionStorage, location.reload, condicoes), com jQuery inline (fadeOut), com argumentos string via esc_js, e os gerados dentro de blocos script (HTML montado por JavaScript). Estes ultimos foram expressamente poupados por mascara dos blocos script no conversor.

Os modelos PDF de pauta e o boletim em admin sao varridos pelos verificadores de JavaScript embebido, que se mantem verdes.

A catraca check-inline-frontend desce o maximo de onclick de 149 para 93; style mantem-se em 2051 e blocos script em 7. Sem cabecalho CSP novo nesta vaga: o enforce do shell admin so vem depois de tratados os onclick e estilos inline restantes. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9.

Testes especificos desta versao: os botoes convertidos continuam a chamar a mesma funcao com os mesmos argumentos atraves do despachante data-sige-act; os sem argumentos usam data-sige-noargs e os de inteiro usam data-sige-args com a lista JSON, preservando o tipo numero. O conversor mascara os blocos script, pelo que os onclick gerados por JavaScript nao foram tocados. Os onclick difíceis (event, codigo inline, jQuery, string esc_js) ficaram intactos para incrementos proprios. Os verificadores de JavaScript embebido validam as vistas admin sem erro de sintaxe. A catraca trava onclick em 93 (sem folga). php -l limpo nas 8 vistas tocadas. Corredor 99, regression-pack verde.

## Actualizacao v12.12.61 - Fase 4 incr 2 - uploadDoc e fecho de modais das vistas admin em data-sige-act

Mais onclick seguros das vistas admin para o despachante data-sige-act, continuando a preparar o CSP enforce do shell admin. Apos a v12.12.60 (52 sem-arg mais 4 inteiro), esta vaga converte mais dois padroes inequivocamente seguros:
- 6 onclick uploadDoc com argumento string estatico (uploadDoc('bi'), 'cert', 'vacina', 'doc_bi', 'doc_cert', 'doc_cv') passam a data-sige-act mais data-sige-arg.
- 8 onclick de fecho de modais jQuery (jQuery('#modal-...').fadeOut(200)) passam a data-sige-act mais data-sige-arg com o novo wrapper global sigeFecharModalJq, que faz exactamente jQuery(sel).fadeOut(200).

O wrapper segue o padrao ja existente em assets/sige-ui.js (funcoes nomeadas expostas em window para o despachante as encontrar via window[accao]) e guarda a presenca de jQuery. Total 14 conversoes em 3 vistas: alunos_lista (3 uploadDoc), equipe-view (3 uploadDoc), turmas-view (8 fecho de modais). O comportamento e identico: uploadDoc recebe a mesma cadeia; o fecho de modal faz o mesmo fadeOut.

Verificou-se que nao ha onclick window.print() nas vistas admin fora de blocos script (zero), pelo que nada havia a converter ai. O switchTab(event, ...) fica para incremento proprio, por usar event.target e exigir refactor da assinatura. O conversor mascara os blocos script, poupando os onclick gerados por JavaScript.

A catraca check-inline-frontend desce o maximo de onclick de 93 para 79; style mantem-se em 2051 e blocos script em 7. Sem cabecalho CSP novo nesta vaga. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9.

Testes especificos desta versao: os botoes uploadDoc convertidos recebem a mesma cadeia estatica via data-sige-arg; os botoes de fecho de modal chamam o novo wrapper sigeFecharModalJq, que faz jQuery(sel).fadeOut(200) com guarda da presenca de jQuery. O conversor mascara os blocos script, pelo que os onclick gerados por JavaScript nao foram tocados. O switchTab ficou intacto para incremento proprio. Os verificadores de JavaScript embebido validam as vistas admin sem erro de sintaxe e o sige-ui.js passa o node -c. A catraca trava onclick em 79 (sem folga). php -l limpo nas 3 vistas tocadas. Corredor 99, regression-pack verde.

## Actualizacao v12.12.62 - Fase 4 incr 2 - switchTab e args com esc_js das vistas admin em data-sige-act

switchTab e onclick com argumentos string via esc_js das vistas admin para o despachante data-sige-act, continuando a preparar o CSP enforce do shell admin. Duas conversoes que exigiam cuidado proprio.

Primeira, o switchTab das abas da ficha do aluno: era switchTab(event, tabId) e usava event.target para marcar a aba activa; foi refeito para switchTab(el), lendo o tabId do atributo data-tab que as abas ja possuem, e os quatro call-sites passam a data-sige-act (a via por omissao do despachante passa o proprio elemento). Como as abas sao div sem filhos, event.target era ja o elemento, pelo que o comportamento e identico. Confirmou-se que switchTab so e chamado por esses quatro onclick, nao programaticamente.

Segunda, nove onclick com argumentos string via esc_js (alocarProfessores, verAlunos, apagarTurma, verAcesso, apagarAluno, toggleStatus, removerUser, sigeAbrirAnular, plAbrirPagar) passam a data-sige-act mais data-sige-args, com os argumentos serializados por esc_attr(wp_json_encode([...])): o wp_json_encode preserva os tipos (inteiros, decimais e cadeias) e o esc_attr codifica para o atributo HTML, incluindo aspas, menor e e-comercial. Testou-se o round-trip ate JSON.parse com um valor dificil (aspa simples, aspa dupla, menor e e-comercial) e recuperou-se o valor exacto. Cada funcao alvo e global (definida como funcao ou como window.nome), encontrada pelo despachante via window.

Total 13 conversoes (4 switchTab mais 9 esc_js) em 5 vistas: alunos_lista (4 switchTab, verAcesso, apagarAluno), turmas-view (alocarProfessores, verAlunos, apagarTurma), equipe-view (toggleStatus, removerUser), financeiro-extratos (sigeAbrirAnular), financeiro-planos-view (plAbrirPagar). Comportamento identico.

A catraca check-inline-frontend desce o maximo de onclick de 79 para 66; style mantem-se em 2051 e blocos script em 7. Sem cabecalho CSP novo nesta vaga. Sem nova superficie de accao (199, enforce 33), sem nova vista (60), sem opcoes novas (132), sem alteracao de esquema, calculo financeiro byte-identico, dependencias externas em 9.

Testes especificos desta versao: as abas da ficha do aluno trocam de aba como antes; o switchTab le o tabId do data-tab do proprio elemento que o despachante lhe passa. Os nove botoes com argumentos string via esc_js recebem os mesmos argumentos: testou-se o round-trip esc_attr(wp_json_encode) ate JSON.parse com um valor dificil (aspa simples, aspa dupla, menor e e-comercial) e recuperou-se o valor exacto, preservando inteiros e decimais. As funcoes alvo sao globais, definidas como funcao ou como window.nome, encontradas pelo despachante via window. Os verificadores de JavaScript embebido validam as vistas admin sem erro de sintaxe. A catraca trava onclick em 66 (sem folga). php -l limpo nas 5 vistas tocadas. Corredor 99, regression-pack verde.
