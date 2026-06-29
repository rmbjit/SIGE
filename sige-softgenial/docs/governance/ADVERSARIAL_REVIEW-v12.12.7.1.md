# Rediagnostico adversarial - v12.12.7.1

## Escopo revisto
Assumiu-se que a entrega continha falhas escondidas. Foram caçadas em: itens do Phase Charter nao implementados, ficheiros/endpoints fora do manifesto, permissoes/nonces/tenant/auditoria esquecidos, regressao funcional, seguranca/performance/dados/operacao, testes em falta, e solucoes paliativas ou fallbacks perigosos. Todas as verificacoes foram executadas (nao apenas declaradas).

## Achados encontrados e tratados

### P1 (encontrado e CORRIGIDO) - Identidade da escola pela fonte errada
O handler resolvia o nome e o logotipo da escola por `sige_get_config('nome_escola')` e `sige_get_config('logo_url')`. A funcao `sige_get_config` NAO EXISTE no codigo. O ramo nunca corria e o documento caia sempre em `get_bloginfo('name')` (nome do site WP, igual para todas as escolas do mesmo site) e nunca mostrava o logotipo. Isto contrariava o Phase Charter ("identidade visual da escola, mesma fonte das demais impressoes"). O teste de render inicial mascarou o defeito porque passava o nome directamente.
Correccao: passou a usar a fonte canonica `sige_get_escola_perfil()` com `->nome_escola` e `->logotipo` (o mesmo que `sige_desp_print`), tenant-scoped por construcao. Verificado por teste focado (nome real, logotipo `<img>`, e fallback correcto quando nao ha perfil).

### P2 (encontrado e CORRIGIDO) - Permissao mais larga do que a pagina
O handler e a regra do Kernel aceitavam `financeiro.ver` alem de `financeiro.cobrancas_ver/gerir`. A pagina Central de Cobrancas exige apenas `cobrancas_ver` ou `cobrancas_gerir`. Logo, um utilizador com `financeiro.ver` mas sem `cobrancas_*` poderia imprimir a lista de devedores apesar de nao poder ver a propria pagina.
Correccao: removido `financeiro.ver` do handler e da regra. Verificado por teste de runtime: `financeiro.ver` sozinho passa agora a BLOQUEAR; `cobrancas_ver` e `cobrancas_gerir` permitem.

### P1 (reportado em producao e CORRIGIDO) - Total do PDF acima do ecra
O utilizador reportou que o total e a contagem de devedores do PDF ficavam SEMPRE acima dos do ecra. Causa raiz dupla: (1) o ecra mostra por defeito apenas alunos ACTIVOS com matricula ACTIVA (situacao_aluno = 'activos'), enquanto o dataset do PDF incluia toda a gente; (2) o dataset somava `SUM(sige_fin_saldo_sql)` com `LEFT JOIN sige_matriculas`, o que multiplicava cada lancamento pelo numero de matriculas do aluno (fan-out), inflando o total. O ecra evita ambos: filtra activos e calcula o total recalculando por aluno com `sige_fin_saldo_lancamento()` numa query SEM JOIN.
Fonte da verdade: `sige_fin_saldo_lancamento()` (a mesma funcao que os pagamentos usam), somada sobre os lancamentos em aberto dos alunos activos com matricula activa, sem fan-out. E o valor que o ecra exibe em "Divida total acumulada".
Correccao: o dataset passou a replicar exactamente o ecra, em dois passos (populacao de activos; soma por lancamento sem JOIN). Verificado por teste: aluno com 3 lancamentos soma o valor real (sem multiplicar), inactivos sao excluidos, e o total bate certo com a logica do ecra.

## Achados aceites (documentados, nao bloqueantes)

### P2 - Documento imprime a lista completa (impressao filtrada fora de ambito)
Quando o utilizador aplica um filtro na pagina (mes, situacao do aluno, ou centro de custo via `?centro_id=`), o PDF continua a mostrar a lista completa da escola. O pedido de impressao e um pedido separado a `home_url('/')` que nao leva os filtros. No caso por defeito (sem filtro) os totais coincidem com o ecra; so divergem quando ha filtro explicito. A impressao filtrada foi explicitamente excluida do Phase Charter e fica para fase posterior.

### P3 - Contacto preferido
A coluna de contacto escolhe o primeiro disponivel (WhatsApp das notificacoes, depois telemovel do pai); pode nao ser o preferido em todos os casos.

### P3 - Sem limite de linhas
O documento nao impoe LIMIT. Uma escola com milhares de devedores gera um documento grande; a query e a mesma do ecra (sem custo novo) e a paginacao e do browser.

### P3 - Strings de versao fixas no gerador
`tools/inventory-surface.php` mantem `base_version` e `purpose` fixos no codigo, que precisam de actualizacao manual a cada versao. Divida pre-existente, nao introduzida por esta versao.

## Verificacoes que confirmaram ausencia de falhas
- Itens do Phase Charter: todos implementados apos a correccao do P1.
- Manifesto: a unica superficie nova `query_handler:sige_dev_print` esta declarada; o handler nao introduz outros `$_GET['sige_*']`; o botao usa `add_query_arg` (nao cria superficie); extractor primario e independente alinhados (193 = 193).
- Nonce, tenant e auditoria: provados em runtime. Sem nonce, sem tenant e sem permissao o Kernel BLOQUEIA em enforce, antes do handler; o handler tambem auto-protege.
- Tenant: a query filtra `escola_id` nas quatro juncoes; sem escola resolvida nao corre nenhuma query (fail-closed, com auditoria).
- Canonicidade: reutiliza `sige_fin_saldo_sql` (sem `%` que partisse o prepare); 5 placeholders para 5 argumentos na ordem certa; mesmos estados do ecra.
- Leitura pura: sem INSERT/UPDATE/DELETE em tabelas financeiras.
- XSS: todos os `echo` do documento usam `esc_html`/`esc_url`/`esc_attr`.
- Regressao: o template JS antigo (Lista de Cobranca) ficou intacto; ordem de carregamento correcta (require apos finance-core); zero `risk=critical` em observe; sem regressao real de autorizacao vs v12.12.7 (sige_can 58->60, current_user_can 471->475 pelo padrao canonico de fallback).
- Operacao: o botao e um link com target=_blank (nao window.open), imune a bloqueio de popups.

## Decisao
Decisao: a versao tinha um P1 (identidade da escola) e um P2 (permissao alargada), ambos CORRIGIDOS e verificados por teste. Apos as correccoes, P0=0 e P1=0 no conjunto de testes automatizados executados. A versao pode ser considerada concluida para validacao em staging. Producao depende de validacao humana por perfil e escola.
