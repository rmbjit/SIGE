# DEFINITION OF DONE - v12.12.8.1

- DoD-001: Helper auditado sige_tenant_write_guard existe em multitenancy.php.
- DoD-002: Helper devolve true para escola > 0.
- DoD-003: Helper devolve false e regista tenant_write_blocked para escola <= 0.
- DoD-004: Categoria A: 24 sumidouros com parametro de escola tem guard fail-closed.
- DoD-005: Cada aborto de Categoria A respeita o contrato (array ok/error, array ok/message, bool, void, null).
- DoD-006: Categoria B: 22 funcoes com escrita inline tem guard fail-closed.
- DoD-007: Handlers AJAX de Categoria B abortam com wp_send_json_error; biblioteca com retorno tipado (null, WP_Error).
- DoD-008: Excecoes de logging (sige_audit_log, sige_registar_log) documentadas e na allowlist do gate.
- DoD-009: Handler delegado acta:1610 fechado (require na origem mais guard no sumidouro).
- DoD-010: Auditoria presente em todos os abortos (fecha a lacuna silenciosa da v12.12.8).
- DoD-011: Novo gate check-tenant-write-sinks criado, ligado e a falhar contra sumidouros sem guard.
- DoD-012: Gate verifica 0 sumidouros A sem guard e 0 funcoes B sem guard fora das excecoes.
- DoD-013: Baseline ": 1" desce de 139 para 138; baseline v12.12.8 congelado a 139.
- DoD-014: Nenhuma regra de calculo financeiro ou academico foi alterada.
- DoD-015: Em mono-escola o comportamento nao muda.
- DoD-016: Versao sincronizada (header, SIGE_VERSION, BUILD.json, SIGE_GOV_VERSION) em 12.12.8.1; Kernel 193 ids identicos.
- DoD-017: 12 documentos versionados v12.12.8.1; smoke dedicado verde; lint e run-gates verdes.
- DoD-018: Rediagnostico adversarial com Zero P0/P1.

## Zero P0/P1
A versao so pode ser aceite se P0=0 e P1=0 apos rediagnostico adversarial.
