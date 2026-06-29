# Cenarios de teste live - SIGE SoftGenial v12.12.34
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
