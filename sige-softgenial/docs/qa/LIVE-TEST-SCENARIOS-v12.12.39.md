# Cenarios de teste live - SIGE SoftGenial v12.12.39
Fase 9 incremento 5: Sobreposicao puramente aditiva por capacidades

Objectivo: confirmar, num ambiente real, que o modulo deixou de substituir o papel WordPress, que as capacidades vem do filtro (mesmo efeito de antes), que os colaboradores legados sao repostos ao papel original, que a proteccao do Admin TI se mantem, e que os fluxos de portal e o administrador real ficam intactos.

Preparacao: um administrador WordPress real (A), um colaborador de teste (C) num papel WordPress comum (por exemplo, Assinante), e, se possivel, um colaborador legado (L) que ja estivesse num papel sige_* antes desta versao.

## 1. Atribuir perfil nao troca o papel WordPress
- Como A, atribua a C um perfil de staff (por exemplo, Professor) no ecra de Perfis e Permissoes.
- Esperado: C passa a ver as areas do perfil Professor.
- Na gestao de utilizadores do WordPress, o papel de C continua a ser o original (Assinante), NAO sige_professor.

## 2. As capacidades vem do filtro
- Ainda como C, confirme que as areas e menus do perfil aparecem normalmente.
- Esperado: o acesso e o mesmo de antes desta versao, apesar de o papel WordPress nao ter mudado.

## 3. Colaborador legado e reposto ao papel original
- Para L (que estava num papel sige_*), peca-lhe para iniciar sessao uma vez.
- Esperado: o papel WordPress de L volta ao original (ou ao papel por omissao, se nao havia original guardado), sem perder o acesso as areas do perfil. Limpeza unica.

## 4. Proteccao do Admin TI mantem-se
- De a C o perfil de Admin TI. Como um gestor que NAO seja administrador WordPress real, tente editar ou remover C.
- Esperado: bloqueado. So um administrador WordPress real edita ou remove um Admin TI.

## 5. Remover perfil retira o acesso
- Como A, remova o perfil de C.
- Esperado: C deixa de ver as areas do perfil. O papel WordPress de C permanece o original (ou e reposto, no caso legado).

## 6. Contas de aluno e encarregado intactas
- Confirme que uma conta de aluno e uma de encarregado continuam a funcionar como antes (login, portal).
- Esperado: sem alteracao; estes fluxos sao proprios e fora do ambito de staff.

## 7. Administrador real intacto
- Confirme que A continua com acesso total.
- Esperado: o filtro nao afecta administradores WordPress reais.

## 8. Isolacao por escola inalterada
- Num ambiente com mais de uma escola, confirme que cada colaborador continua a ver apenas os dados da sua escola.
- Esperado: a isolacao de dados por escola e separada das capacidades e nao muda.

## Resultado esperado global
- O papel WordPress dos colaboradores nunca e alterado pelo modulo.
- As capacidades vem do filtro, com o mesmo efeito de antes.
- Colaboradores legados sao repostos ao papel original sem perder acesso.
- A proteccao do Admin TI, os fluxos de portal e o administrador real ficam intactos.

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
