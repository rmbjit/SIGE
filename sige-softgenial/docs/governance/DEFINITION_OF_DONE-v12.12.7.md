# DEFINITION OF DONE - v12.12.7

- DoD-001: Todas as mutações directas críticas de views estão inventariadas.
- DoD-002: Manifesto inclui `view_action`.
- DoD-003: Scan encontra zero acção crítica em observe.
- DoD-004: Toda acção crítica está enforce, delegated ou retired.
- DoD-005: Toda mutação crítica privada tem nonce.
- DoD-006: Todo endpoint público crítico usa token/HMAC ou validator formal.
- DoD-007: Toda mutação crítica tenant-aware exige escola válida.
- DoD-008: Objectos críticos são validados por escola quando há ID no request.
- DoD-009: M-Pesa manual não consulta/altera transacção fora da escola.
- DoD-010: Despesas não escolhem a primeira escola da base.
- DoD-011: Perfil financeiro.ver não substitui permissão de escrita.
- DoD-012: Matriz de permissões escolar não altera templates globais por acidente.
- DoD-013: Remover aluno arquiva e preserva histórico.
- DoD-014: Webhooks M-Pesa/e-Mola estão em enforce tokenizado.
- DoD-015: Gates antigos continuam verdes.
- DoD-016: Novos gates verdes.
- DoD-017: PHP lint verde.
- DoD-018: Rediagnóstico adversarial com Zero P0/P1.

## Zero P0/P1
A versão só pode ser aceite se P0=0 e P1=0 após rediagnóstico adversarial.
