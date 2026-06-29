# DEFINITION OF DONE v12.12.3

- DoD-001: `financeiro-mpesa` marcado com `status=beta` e `beta_note`.
- DoD-002: `whatsapp_circulares` marcado com `status=beta` e `beta_note`.
- DoD-003: `comunicacoes_central` marcado com `status=beta` e `beta_note`.
- DoD-004: `presencas` marcado com `status=beta` e `beta_note`.
- DoD-005: Cabecalho central renderiza selo `BETA`.
- DoD-006: Cabecalho central renderiza nota Beta com `role=note`.
- DoD-007: CSS do selo e nota Beta existe em `assets/style.css`.
- DoD-008: Views alvo continuam mapeados em `includes/admin-shell.php`.
- DoD-009: Views alvo nao estao na lista de cabecalho proprio.
- DoD-010: Smoke test `tools/smoke-beta-views-v12-12-3.php` verde.
- DoD-011: PHP lint verde.
- DoD-012: `php tools/run-gates.php` verde.
- DoD-013: Metadados de release sincronizados.
- DoD-014: Rediagnostico adversarial executado.
- DoD-015: Zero P0/P1 aberto.

## Zero P0/P1
A versao so pode ser aceite se o rediagnostico adversarial nao encontrar P0/P1 no escopo desta intervencao.
