# Rollback - v12.17.0 Alunos Performance & Modularization Contract

## Condicao de rollback
Executar rollback se, apos instalar em staging, ocorrer qualquer uma destas situacoes:
- erro fatal;
- tela branca;
- loop de navegacao;
- perda de acesso a Alunos;
- modal de edicao incompleto;
- Ficha 360 sem abrir;
- exportacao Excel indisponivel;
- cartoes ou declaracoes sem dados essenciais;
- regressao em financeiro, academico, permissoes ou portaria.

## Caminho de rollback
1. Desactivar a v12.17.0.
2. Reinstalar o ZIP v12.16.2 aprovado.
3. Limpar cache do browser e cache de pagina, se existir.
4. Validar Alunos, Financeiro, Academico, Permissoes e Portaria.
5. Registar o sintoma exacto antes de nova tentativa.

## Reversibilidade
Esta fase nao altera schema, nao grava dados historicos e nao muda formulas. O rollback e operacional por ficheiros/plugin.

## Ficheiros alterados nesta fase
- sige-softgenial.php
- includes/alunos-performance-contract.php
- includes/aluno-fetch-ajax.php
- admin/academic/alunos_lista.php
- tools/check-v12-17-0-alunos-performance-contract.php
- tools/smoke-v12-17-0-alunos-performance.php
- tools/check-v12-17-0-no-sensitive-regression.php
- tools/check-v12-17-0-package-manifest.php
- tools/run-gates.php
- BUILD.json
- CHANGELOG.md
- docs/changelog/CHANGELOG-v12-17-0.txt
- docs/governance/PHASE_CHARTER-v12.17.0-alunos-performance-modularization-contract.md
- docs/qa/QA-v12.17.0-alunos-performance-modularization-contract.md
- docs/traceability/MATRIZ_RASTREABILIDADE-v12.17.0-alunos-performance-modularization-contract.md
