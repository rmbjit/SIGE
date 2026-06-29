# QA - SIGE SoftGenial v12.12.34
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
