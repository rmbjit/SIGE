# Rollback - v12.18.0 Operational UX & Workflow Hardening

## Baseline de retorno
v12.17.1 - Alunos Mobile Header Hotfix aprovado em staging pelo utilizador.

## Quando reverter
- Erro fatal.
- Tela branca.
- Loop.
- Regressão visual forte em mobile.
- Interferência em botões/formulários.
- Regressão em financeiro, académico, permissões, portaria ou Alunos.

## Como reverter
1. Desactivar/remover v12.18.0.
2. Reinstalar ZIP v12.17.1 aprovado.
3. Limpar cache do navegador/WordPress/CDN se existir.
4. Validar Alunos, Financeiro, Académico, Permissões e Portaria.

## Ficheiros novos a remover numa reversão manual
- `includes/operational-workflow-hardening.php`
- `assets/operational-workflow-v12-18-0.css`
- `assets/operational-workflow-v12-18-0.js`
- `tools/check-v12-18-0-operational-workflow-contract.php`
- `tools/smoke-v12-18-0-operational-workflow.php`
- `tools/check-v12-18-0-no-sensitive-regression.php`
- `tools/check-v12-18-0-package-manifest.php`
- Documentação v12.18.0 em `docs/`

## Nota técnica
A v12.18.0 não cria tabelas, não altera schema, não grava dados históricos e não adiciona endpoints. A reversão é operacionalmente simples.
