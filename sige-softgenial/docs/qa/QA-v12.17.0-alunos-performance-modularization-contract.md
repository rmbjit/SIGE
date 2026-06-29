# QA - v12.17.0 Alunos Performance & Modularization Contract

## Testes automatizados obrigatorios
- `php -l` em todos os ficheiros PHP.
- `php tools/smoke-release-gate.php`.
- `php tools/run-gates.php`.
- `php tools/check-v12-17-0-alunos-performance-contract.php`.
- `php tools/smoke-v12-17-0-alunos-performance.php`.
- `php tools/check-v12-17-0-no-sensitive-regression.php`.
- `php tools/check-v12-17-0-package-manifest.php`.

## Testes manuais em staging

### Secretaria
- Abrir Alunos.
- Pesquisar por nome, processo e turma.
- Abrir Ficha 360.
- Editar aluno existente.
- Confirmar que todos os separadores do modal continuam a abrir.
- Validar importacao sem executar importacao real, salvo se necessario.

### Direccao
- Abrir listagem.
- Filtrar por estado.
- Abrir Ficha 360.
- Confirmar que KPIs e accoes continuam compreensiveis.

### Financeiro
- Confirmar badge financeiro no card.
- Abrir pagamentos do aluno.
- Abrir historico financeiro.
- Confirmar que nenhum saldo ou formula foi recalculado por esta fase.

### Guarda
- Abrir alunos em modo consulta.
- Confirmar que nao ve accoes financeiras indevidas.
- Confirmar que a Ficha 360 respeita minimizacao de dados.

### Mobile
- Abrir em largura 360px, 390px e 430px.
- Confirmar header, filtros, chips, cards e accoes.
- Confirmar ausencia de overflow horizontal.

## Criterios de aceitacao
- Sem erro fatal.
- Sem tela branca.
- Sem loop.
- Sem `#038;view`.
- Mobile funcional.
- Pesquisa e paginacao funcionam.
- Edicao continua completa.
- Ficha 360 abre.
- Excel exporta com filtros actuais.
- Cartao individual e lote funcionam.
- Financeiro, academico, permissoes e portaria sem regressao.

## Risco residual
A validacao browser autenticada deve ser feita em staging porque o ambiente local de pacote nao tem sessao WordPress real.
