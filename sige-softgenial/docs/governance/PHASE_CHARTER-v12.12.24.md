# Phase Charter - SIGE SoftGenial v12.12.24

Fase 8 (Dados, privacidade e retencao) - Incremento 2: Direito de acesso e portabilidade (dossie do titular).

Data: 2026-06-21. Base: v12.12.23.

## Objectivo

Permitir a quem de direito (delegados do responsavel pelo tratamento) reunir, para um aluno identificado da escola activa, o conjunto dos seus dados pessoais (direito de acesso) e exporta-lo num formato estruturado e reutilizavel (portabilidade), sempre dentro da escola e com registo de quem acedeu.

## Incluido

- Helper de leitura includes/privacy/pii-dossier.php que, dado um aluno da escola activa, percorre o catalogo da Incr 1 e reune os valores reais dos seus dados pessoais, agrupados por tabela e categoria (ficha do aluno por id; saude, matriculas, pagamentos, contactos de cobranca, transacoes moveis, acessos e historico de encarregados por aluno_id). So SELECT, sem escrita. Fail-closed por escola.
- Ecra view=privacidade-acesso: procura de aluno por numero de processo ou nome (so leitura), apresentacao do dossie no ecra e botao de exportacao.
- Endpoint admin_post de exportacao (admin_post:sige_privacidade_exportar), governado pelo Security Kernel em modo enforce: valida o aluno na escola activa, monta o dossie e transmite um ficheiro JSON estruturado. Com nonce, rate limit (10/300s), isolamento por escola e auditoria de cada exportacao.
- Nova permissao privacidade.acesso_exportar (risco alto), que governa o ecra e o endpoint, semeada so a administracao e direccao, e adicionada a lista de auditoria sempre-registada.
- Gate e smoke dedicados (check-acesso, smoke-acesso).

## Excluido

- Apagamento e anonimizacao (Incr 3); retencao, expurgo e log dedicado de acessos a PII (Incr 4); consentimento e base legal por finalidade (Incr 5).
- Dossie do titular funcionario (a Incr 2 cobre o aluno, que ja inclui encarregado e agregado por estarem na sua ficha).
- Sem alterar regras de calculo. Sem migracao de esquema (SCHEMA_VERSION inalterada).

## Riscos

- Exportacao de dados pessoais e operacao sensivel. Mitigacao: permissao de risco alto restrita a administracao/direccao, nonce, rate limit, isolamento por escola e auditoria obrigatoria de cada exportacao (quem, que aluno, quando).
- Fuga entre escolas. Mitigacao: fail-closed por escola_id em toda a leitura; aluno de outra escola e recusado sem dados.
- Injeccao por identificador de coluna. Mitigacao: lista branca estrita de identificadores antes do SQL; valores de aluno e escola sempre preparados.
- Crescimento da superficie. Mitigacao: um unico endpoint novo, ja em enforce; manifesto e Kernel regenerados e alinhados (198 == 198).

## Criterios de aceitacao

- O dossie reune, para um aluno da escola activa, os campos catalogados com valores reais agrupados por categoria; aluno de outra escola e recusado.
- O ecra abre so para quem tem privacidade.acesso_exportar; consulta no ecra e exportacao JSON funcionam; a exportacao transmite ficheiro com cabecalhos correctos.
- O endpoint e protegido por nonce, rate limit e isolamento por escola, governado pelo Kernel em enforce; cada exportacao fica registada na auditoria de permissoes.
- Permissao com privilegio minimo; negada a tesouraria, secretaria e docencia.
- Manifesto e Kernel em 198 (enforce 32), coerentes entre si; SCHEMA inalterada; tres funcoes de calculo byte-identicas; baselines de design sem regressao; zero estilo inline; zero travessoes.
- Gate e smoke dedicados verdes; corredor 64/64; release gate verde a partir de pasta limpa; rediagnostico adversarial Zero P0/P1.
