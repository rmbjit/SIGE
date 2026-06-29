# Rollback - v12.17.1 Alunos Mobile Header Hotfix

## Plano de retorno

Se a correcao falhar em staging, voltar para o ZIP v12.17.0.

## Impacto do rollback

- Perde a correcao visual do header mobile de Alunos.
- Mantem a melhoria de performance da v12.17.0.
- Nao ha migracao de dados a reverter.
- Nao ha schema a reverter.

## Ficheiros alterados nesta fase

- admin/academic/alunos_lista.php
- sige-softgenial.php
- BUILD.json
- CHANGELOG.md
- docs/changelog/CHANGELOG-v12-17-1.txt
- docs/governance/PHASE_CHARTER-v12.17.1-alunos-mobile-header-hotfix.md
- docs/qa/QA-v12.17.1-alunos-mobile-header-hotfix.md
- docs/traceability/MATRIZ_RASTREABILIDADE-v12.17.1-alunos-mobile-header-hotfix.md
- docs/migration/MIGRACAO_ROLLBACK-v12.17.1-alunos-mobile-header-hotfix.md
- tools/check-v12-17-1-alunos-mobile-header-contract.php
- tools/smoke-v12-17-1-alunos-mobile-header.php
- tools/check-v12-17-1-no-sensitive-regression.php
- tools/check-v12-17-1-package-manifest.php
- tools/run-gates.php

## Sem alteracao

- Financeiro.
- Academico.
- Permissoes.
- Portaria.
- admin-shell.php.
- Schema.
- Dados historicos.
