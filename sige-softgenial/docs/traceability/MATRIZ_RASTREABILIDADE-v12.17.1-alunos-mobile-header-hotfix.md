# Matriz de Rastreabilidade - v12.17.1 Alunos Mobile Header Hotfix

| Problema | Causa | Solucao | Ficheiro afectado | Teste obrigatorio | Criterio de aceitacao |
|---|---|---|---|---|---|
| Avatar cortado no header mobile de Alunos | CSS historico da pagina reexibia ano lectivo e deixava chip e avatar disputar a mesma area | Esconder chip no mobile e reservar coluna fixa para avatar | admin/academic/alunos_lista.php | check-v12-17-1-alunos-mobile-header-contract | Avatar inteiro e sem ano sobreposto |
| Ano lectivo aparecia por baixo ou atras da foto | Meta direita com dois elementos em largura insuficiente | Meta direita avatar-only em 42px | admin/academic/alunos_lista.php | smoke-v12-17-1-alunos-mobile-header | Chip hidden e meta fixa |
| Risco de regressao global | Header global ja tinha hotfix aprovado | Patch escopado a sige-view-alunos_lista | admin/academic/alunos_lista.php | check-v12-17-1-no-sensitive-regression | admin-shell.php intocado |
