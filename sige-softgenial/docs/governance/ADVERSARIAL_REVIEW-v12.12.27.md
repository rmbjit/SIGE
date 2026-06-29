# ADVERSARIAL_REVIEW - SIGE SoftGenial v12.12.27

Fase 8 incremento 4: retencao e expurgo (so leitura).

## Rediagnostico adversarial

Revisao hostil do ecra de retencao, procurando escrita indevida, contagem enganosa, vazamento ou deriva de superficie.

1. Escrita disfarcada de leitura. O motor so executa SELECT COUNT; o gate proibe qualquer insert/update/delete/drop/alter/truncate no ficheiro. Verificado pelo gate estatico.

2. Eliminacao em massa. Nao existe: o ecra e so leitura e nao tem formularios nem endpoints. O gate verifica que o ecra nao contem admin-post.php, accao de apagar nem nonce, e que nao ha regra do Kernel para retencao. Verificado.

3. Contagem enganosa de alunos activos. A contagem de sige_alunos restringe-se a alunos nao activos (status <> activo). O smoke prova que a query inclui esse filtro e que tabelas sem so_inactivos nao o tem. Verificado.

4. Tabela sem coluna de data conta zero silenciosamente. O motor marca essas categorias como nao mensuraveis (devolve null) e o ecra mostra sem data, em vez de um zero enganoso. O smoke cobre o caso. Verificado.

5. Registo de acessos apresentado como expurgavel. O calendario classifica o registo de acessos como retido (fonte das presencas, derivadas ao vivo) e a auditoria como retida (dever legal). O gate e o smoke exigem que os acessos estejam retidos. Verificado.

6. Vazamento entre escolas. Cada contagem inclui escola_id no WHERE e e fail-closed sem escola. O smoke confirma que outra escola conta zero e que escola = 0 nao conta. Verificado.

7. Politica tomada como parecer juridico. O ecra e o calendario avisam que e classificacao por omissao a rever pela instituicao. Verificado no ecra.

8. Deriva de superficie ou de calculo. Sem novos endpoints: manifesto e Kernel mantem-se em 199 (enforce 33), alinhados. Regras de calculo byte-identicas. SCHEMA inalterada. Baselines de design sem regressao. Verificado.

## P0

Nenhum. O ecra e so leitura, fail-closed por escola, sem qualquer accao destrutiva; o expurgo efectivo permanece na anonimizacao por titular, ja governada.

## P1

Nenhum. Um defeito real de duplo prefixo na leitura de colunas foi detectado pelo smoke durante o desenvolvimento e corrigido na mesma sessao (sem ele, o ecra mostraria sem data em todas as categorias).

## Decisao

Aprovado para entrega como v12.12.27. Sem P0 nem P1 em aberto. Decisoes de desenho documentadas: nao ha expurgo por eliminacao em massa porque a eliminacao quebraria a integridade (presencas derivadas dos acessos, retencao financeira/academica/auditoria); o expurgo de individuos faz-se pela anonimizacao (Apagamento); a anonimizacao em lote fica deferida por falta de um modelo limpo de data de saida. As bases legais e prazos sao classificacao por omissao a rever pela instituicao. Conclui o arco da Fase 8 (Dados, privacidade e retencao).
