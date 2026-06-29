# DEFINITION OF DONE v12.12.2

- DoD-001: Documento de divida mostra decomposicao por item.
- DoD-002: Documento usa `sige_fin_total_lancamento` e `sige_fin_saldo_lancamento` quando disponiveis.
- DoD-003: Documento inclui base, transporte, extras, multa, descontos, desconto especial, pago e saldo.
- DoD-004: Documento inclui `pendente`, `parcial` e `em_plano`.
- DoD-005: Documento mostra resumo geral da divida.
- DoD-006: Documento nao escreve na base de dados.
- DoD-007: Documento nao chama recalculadores com side effects.
- DoD-008: Queries novas/alteradas usam `aluno_id` e `escola_id`.
- DoD-009: Botoes da Central de Devedores ficam semanticamente claros.
- DoD-010: PHP lint verde.
- DoD-011: `php tools/run-gates.php` verde.
- DoD-012: Smoke test especifico do extracto detalhado verde.
- DoD-013: Metadados de release sincronizados.
- DoD-014: Rediagnostico adversarial executado.
- DoD-015: Zero P0/P1 aberto.

## Zero P0/P1
A versao so pode ser aceite se o rediagnostico adversarial nao encontrar P0/P1.
