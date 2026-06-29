# Matriz de Rastreabilidade - v12.16.2

| Problema | Causa | Solucao | Ficheiro/modulo afectado | Teste obrigatorio | Criterio de aceitacao |
|---|---|---|---|---|---|
| BP-16-02-01 Baseline nao congelado | Fase anterior aprovada sem pacote de preservacao | Registar v12.16.1 como origem aprovada | BUILD.json | check-v12-16-2-baseline-preservation.php | Origem e SHA256 presentes |
| BP-16-02-02 Risco de mexer em ficheiros P0 | Evolucao futura sem hashes | Hash gate para ficheiros sensiveis | tools | check-v12-16-2-baseline-preservation.php | Hashes iguais ao baseline |
| OR-16-02-03 Validacao pos-instalacao dispersa | Falta de checklist operacional | Checklist por perfil | docs/deploy | smoke-v12-16-2-operational-readiness.php | Perfis e criterios presentes |
| OR-16-02-04 Regressao mobile futura | Testes variaveis | Matriz com viewports | docs/qa | check-v12-16-2-regression-matrix.php | 360, 390, 430, 768, 1366 presentes |
| PM-16-02-05 Manifesto divergente | Actualizacao manual | Package manifest gate | BUILD.json, CHANGELOG.md | check-v12-16-2-package-manifest.php | 3 fontes sincronizadas |
| RB-16-02-06 Rollback improvisado | Falta de plano formal | Documento de rollback | docs/migration | smoke-v12-16-2-operational-readiness.php | Condicoes de rollback presentes |
| QA-16-02-07 Fecho sem evidencias | Pressa operacional | QA Results final | docs/qa | run-gates.php | Resultados locais registados |
| ST-16-02-08 Staging sem perfis reais | Validacao so admin | Staging Validation por perfil | docs/deploy | smoke-v12-16-2-operational-readiness.php | 8 perfis presentes |
