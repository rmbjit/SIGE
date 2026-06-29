# Matriz de Rastreabilidade - v12.14.2 User Security & Privileged Role Integrity

| Requisito | Implementação | Teste/Evidência |
|---|---|---|
| Bloquear ataque distribuído ao mesmo username | `includes/security-login-shield.php` com `user_global` bucket | `tools/smoke-user-security-v12-14-2.php` |
| Contar tentativa MFA errada como risco | `sige_otp_verify` falhado chama `sige_shield_register_failure()` | `tools/smoke-user-security-v12-14-2.php` |
| Não revelar existência de username | `login_errors` em `includes/security-user-integrity.php` | `tools/smoke-user-security-v12-14-2.php` |
| Bloquear enumeração por author archive | `template_redirect` em `security-user-integrity.php` | `tools/smoke-user-security-v12-14-2.php` |
| Bloquear REST users para não-admins | `rest_endpoints` em `security-user-integrity.php` | `tools/smoke-user-security-v12-14-2.php` |
| Reduzir brute-force via XML-RPC | `xmlrpc_enabled => false` por defeito | `tools/smoke-user-security-v12-14-2.php` |
| Auditar mudanças de WP role | hooks `set_user_role`, `added_user_role`, `removed_user_role` | `tools/smoke-user-security-v12-14-2.php` |
| Reverter downgrade de papel crítico não-autorizado | `sige_user_integrity_wp_role_guard()` | `tools/smoke-user-security-v12-14-2.php` |
| Guardar atribuição/despromoção SIGE privilegiada | `sige_user_integrity_can_change_sige_role()` em `sige_permissions_sync_user_role()` | `tools/smoke-user-security-v12-14-2.php` |
| Fail-closed na UI de permissões | `admin/system/permissions-ui.php` verifica retorno de sync | `tools/smoke-user-security-v12-14-2.php` |
| Fail-closed no RH/Equipe | `sige_ajax_equipe_apply_access_role()` | `php -l includes/ajax-handlers.php` |
| Fail-closed nos handlers legados | `includes/db-handler.php` verifica integridade/sync | `php -l includes/db-handler.php` |
