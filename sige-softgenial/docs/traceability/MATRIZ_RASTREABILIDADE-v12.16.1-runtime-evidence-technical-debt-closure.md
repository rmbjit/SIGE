# MATRIZ DE RASTREABILIDADE v12.16.1 - Runtime Evidence & Technical Debt Closure

| Problema | Causa | Solucao | Ficheiro ou modulo afectado | Teste obrigatório | Critério de aceitacao |
|---|---|---|---|---|---|
| RE-16-01 | QA browser dependia de validacao humana | Preparar suite runtime por perfil | tools/runtime-evidence | check-v12-16-1-runtime-evidence.php | Suite existe, sem credenciais e com perfis reais |
| RE-16-02 | Shell e rotas sao superficie transversal | Criar gate de contrato shell | includes/admin-shell.php, tools/check-v12-16-1-shell-contract.php | check-v12-16-1-shell-contract.php | Allowlist, map e permissoes coerentes |
| RE-16-03 | Manifesto final nao tinha hash de origem nem honestidade de QA | Enriquecer BUILD.json | BUILD.json | check-v12-16-1-package-manifest.php | Origem, escopo e QA declarados |
| RE-16-04 | Risco mobile nao automatizado | Viewports minimos na suite | tools/runtime-evidence/config.example.json | smoke-v12-16-1-runtime-evidence.php | 360, 390, 430, 768 e 1366 presentes |
| RE-16-05 | Duplicados historicos na allowlist | Remover duplicados sem alterar conjunto unico | includes/admin-shell.php | check-v12-16-1-shell-contract.php | Conjunto unico preservado e sem duplicados |
| RE-16-06 | Risco de mexer em financeiro ou academico | Hashes protegidos | includes/finance-core.php, admin/finance, admin/academic | check-v12-16-1-runtime-evidence.php | Hashes P0 iguais a baseline |
| RE-16-07 | Falta de rollback explicito | Documento de rollback | docs/migration | check-v12-16-1-package-manifest.php | Condicoes e passos de retorno claros |
| RE-16-08 | Fecho sem evidência local | QA_RESULTS final | docs/qa | check-v12-16-1-package-manifest.php | Resultados locais registados |
