# DEFINITION OF DONE - v12.12.7.1

- DoD-001: Botao Imprimir lista (PDF) presente na Central de Cobrancas.
- DoD-002: Botao usa URL com nonce e abre em nova aba.
- DoD-003: Query handler `sige_dev_print` lê `$_GET['sige_dev_print']` e valida o tipo.
- DoD-004: Handler exige utilizador autenticado.
- DoD-005: Handler verifica o nonce `sige_dev_print`.
- DoD-006: Handler exige permissao SIGE com fallback de papeis.
- DoD-007: Handler e tenant fail-closed (escola_id <= 0 nao gera documento).
- DoD-008: Dataset usa sige_fin_saldo_lancamento (fonte de verdade do ecra e dos pagamentos) e nao reimplementa formulas.
- DoD-009: Dataset usa os mesmos estados ('pendente','parcial') e a mesma populacao (activos) do ecra; total igual ao ecra, sem fan-out.
- DoD-010: Todas as juncoes do dataset filtram `escola_id`.
- DoD-011: O handler nunca escreve em tabelas financeiras (leitura pura).
- DoD-012: Regra `query_handler:sige_dev_print` existe no Kernel em enforce.
- DoD-013: A regra tem runtime_hooks (admin_init, parse_request, template_redirect) e prioridade antecipada.
- DoD-014: A regra tem nonce, permissoes, tenant, rate limit e auditoria.
- DoD-015: Manifesto e regras do Kernel alinhados (mesmo conjunto de ids).
- DoD-016: Smoke dedicado verde e ligado ao corredor de gates.
- DoD-017: PHP lint verde e `php tools/run-gates.php` verde.
- DoD-018: Rediagnostico adversarial com Zero P0/P1.

## Zero P0/P1
A versao so pode ser aceite se P0=0 e P1=0 apos rediagnostico adversarial.
