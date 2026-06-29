# QA SMOKE v12.12.6

## Smokes novos

- `tools/smoke-security-kernel-v12-12-6.php`
  - confirma query handlers em `admin_init`, `parse_request`, `template_redirect` com prioridade -1000;
  - confirma `sige_print` observado em `admin_init`;
  - confirma `sige_portaria_camera` observado em `template_redirect` antes do render standalone;
  - confirma `sige_desp_print` valido passando pelo dispatcher antecipado.

- `tools/smoke-settings-tenant-guard-v12-12-6.php`
  - confirma que `SIGE_Settings_Repository` bloqueia escrita `sige_config` sem escola resolvida;
  - confirma ausencia de `update`/`insert` quando tenant falta;
  - confirma `tenant_required=true` e `tenant_scope=required_for_sige_config_writes` na regra `wp_ajax:sige_settings_save`.

## Resultado

- P0 aberto: 0.
- P1 aberto: 0.
- Gates: 37/37 verdes.
- PHP lint: 346 ficheiros, 0 falhas.
