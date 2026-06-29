# ADVERSARIAL REVIEW - SIGE SoftGenial v12.12.23

## Rediagnostico adversarial - Fase 8 Incremento 1 (Inventario de Dados Pessoais)

Rediagnostico conduzido apos a entrega, procurando activamente formas de o incremento falhar, vazar dados ou degradar invariantes. Cada hipotese e seguida da sua refutacao com evidencia.

### Vector 1: fuga de dados pessoais pelo ecra de governanca
Hipotese: um ecra que fala de PII acaba por mostrar PII. Refutacao: o helper sige_pii_inventario nunca executa SELECT de colunas de dados; so executa COUNT e SUM agregados e SHOW TABLES e SHOW COLUMNS (metadados). O gate tools/check-privacidade.php verifica a ausencia de INSERT, UPDATE, DELETE e tambem que o inventario nao devolve linhas. A view apresenta apenas numeros agregados e os nomes de coluna do catalogo, nunca valores. Sem achado.

### Vector 2: contorno do isolamento entre escolas
Hipotese: as contagens misturam dados de varias escolas. Refutacao: toda a contagem usa WHERE escola_id com valor preparado, e sige_pii_contar e fail-closed (escola_id menor ou igual a zero devolve zero sem consultar). O smoke confirma que sige_pii_agregados(0) e sige_pii_agregados(-3) devolvem vazio. Sem achado.

### Vector 3: rota inalcancavel ou nao governada (defeito da Fase 7)
Hipotese: a nova view repete o defeito de roteamento que afectou a Fase 7 (slug fora da allowlist ou da matriz). Refutacao: privacidade-dados consta do mapa de despacho, da allowlist anti-LFI e da matriz de permissoes (verificado: 57 == 57 == 57), e o invariante estrutural exige que toda a rota de despacho conste da allowlist. O gate verifica os quatro pontos (mapa, allowlist, matriz, navegacao). Sem achado.

### Vector 4: escalada de privilegios pela nova permissao
Hipotese: a permissao e concedida em demasia. Refutacao: privacidade.inventario_ver e concedida apenas a admin_escola, admin_ti, direccao_geral e director, e negada a tesouraria, secretaria e docencia (verificado por carregamento isolado do catalogo de permissoes). A guarda fail-closed: sem a permissao, a area mostra aviso e nao consulta dados. Sem achado.

### Vector 5: crescimento silencioso da superficie de accao
Hipotese: a view introduz um endpoint nao declarado. Refutacao: a view nao tem POST, formulario, wp_ajax nem admin-post; o manifesto de accao mantem-se em 197 e o Kernel em 197 (enforce 31), com itens identicos aos da v12.12.22. Sem achado.

### Vector 6: regressao de calculo financeiro ou de esquema
Hipotese: o incremento toca em calculo ou em dados. Refutacao: as funcoes canonicas de calculo financeiro permanecem byte-identicas a v12.12.21, e SCHEMA_VERSION mantem-se 20260621.1 (nenhuma migracao de esquema; so a versao do motor de permissoes e elevada). Sem achado.

### Vector 7: regressao de design (valores magicos ou estilo inline)
Hipotese: a nova interface introduz primitivos magicos. Refutacao: a view nao tem qualquer atributo style inline, o CSS usa so tokens da fonte da verdade, e o link de navegacao usa classes tokenizadas em vez de estilo inline. As baselines de design mantem-se (2414 valores e 7497 primitivos, sem subida). Sem achado.

### Vector 8: catalogo desalinhado do esquema real
Hipotese: o catalogo declara colunas que nao existem, ou ignora PII real. Refutacao: o helper deteta desvios (campo catalogado ausente) e lacunas (coluna PII por classificar) e expoe-os no ecra; o smoke prova ambos com um esquema simulado. O catalogo foi construido a partir das definicoes reais de CREATE TABLE da migracao. Sem achado.

## P0

Nenhum.

## P1

Nenhum.

## Decisao

Incremento aprovado para entrega como v12.12.23. Corredor 62 de 62 verde, release gate verde a partir de pasta limpa, lint sem erros, zero travessoes, manifesto e Kernel em 197, regras de calculo intactas. Avancar para o Incremento 2 da Fase 8 apenas apos validacao do utilizador.
