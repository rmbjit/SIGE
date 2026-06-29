# QA SMOKE - v12.12.7 Critical Actions Lockdown

## Resultado
- `tools/smoke-critical-actions-lockdown-v12-12-7.php`: 89 verificações OK.
- `tools/run-gates.php`: 38/38 gates verdes.
- PHP lint: 347 ficheiros, 0 falhas.

## Cobertura principal
- `view_action` inventariado e despachado antes das views.
- Zero acção crítica em observe.
- M-Pesa manual tenant-scoped.
- Despesas, lançamentos e relatório mensal sem primeira escola da base.
- Pagamentos exigem permissão de escrita.
- Permissões por escola.
- Arquivamento de aluno sem hard delete no handler canónico.

## Observação
Testes automatizados não substituem validação humana em staging com perfis reais e duas escolas.
