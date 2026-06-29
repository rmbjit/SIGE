# DEFINITION OF DONE v12.12.6

- DoD-001: Security Kernel contem prioridade antecipada formal.
- DoD-002: Query handlers sao interceptados em `admin_init`, `parse_request` e `template_redirect`.
- DoD-003: `sige_print` passa pelo kernel em `admin_init`.
- DoD-004: `sige_portaria_camera` passa pelo kernel antes do render standalone.
- DoD-005: `sige_desp_print` continua em enforce valido.
- DoD-006: `settings_save` exige tenant.
- DoD-007: Repository falha fechado em escrita `sige_config` sem escola resolvida.
- DoD-008: Gates negativos detectam ausencia de runtime_hooks, prioridade antecipada, tenant_required e tenant_scope.
- DoD-009: REST/shortcode/wp_hook mantem dispatch runtime.
- DoD-010: Manifesto e regras continuam alinhados.
- DoD-011: Baselines v12.12.6 existem.
- DoD-012: PHP lint verde.
- DoD-013: run-gates verde.
- DoD-014: ZIP final validado.
- DoD-015: Sem alteracao de schema.
- DoD-016: Evidencias QA geradas.
- DoD-017: Rediagnostico adversarial realizado.
- DoD-018: Zero P0/P1 aberto.

## Zero P0/P1
A versao so pode ser considerada concluida com P0=0 e P1=0.
