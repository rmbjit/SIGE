# Matriz de Rastreabilidade - v12.17.0 Alunos Performance & Modularization Contract

| Problema | Causa | Solucao | Ficheiro/modulo afectado | Teste obrigatorio | Criterio de aceitacao |
|---|---|---|---|---|---|
| Pagina de Alunos historicamente lenta | Listagem carregava mais campos do que precisava | SELECT explicito e leve para cards | admin/academic/alunos_lista.php, includes/alunos-performance-contract.php | check-v12-17-0-alunos-performance-contract | Sem SELECT a.* directo na listagem |
| HTML pesado por payload inline | Card embutia objecto completo do aluno para documentos | Payload minimo para cartao/boletim/declaracao | admin/academic/alunos_lista.php, includes/alunos-performance-contract.php | smoke-v12-17-0-alunos-performance | Payload inline sem campos sensiveis desnecessarios |
| Exportacao carregava linha completa | Endpoint usava payload amplo para Excel | SELECT minimo por finalidade | includes/aluno-fetch-ajax.php | check-v12-17-0-alunos-performance-contract | Excel recebe apenas campos necessarios |
| Risco de quebrar edicao | Campos completos ainda necessarios no modal | Manter `sige_get_aluno_full` com aluno completo | includes/aluno-fetch-ajax.php | Teste manual de edicao | Modal continua completo |
| Risco de regressao em modulos sensiveis | Fase mexe em modulo escolar critico | Hash gate preserva financeiro, academico, permissoes e shell | tools/check-v12-17-0-no-sensitive-regression.php | run-gates | Hashes P0/P1 preservados |
| Risco de regressao futura | Mudanca de performance poderia ser revertida sem notar | Gates v12.17.0 no corredor oficial | tools/run-gates.php | run-gates | 148 gates verdes |
