# Inventário Técnico - v12.14.2 User Security & Privileged Role Integrity

## Ficheiros analisados/alterados
- `includes/security-login-shield.php`
- `includes/security-user-integrity.php` novo
- `includes/permissions-layer.php`
- `admin/system/permissions-ui.php`
- `includes/ajax-handlers.php`
- `includes/db-handler.php`
- `sige-softgenial.php`
- `BUILD.json`
- `CHANGELOG.md`
- `docs/changelog/CHANGELOG-v12-14-2-user-security-role-integrity.txt`

## Hooks WordPress afectados
- `authenticate`
- `wp_login_failed`
- `wp_login`
- `login_errors`
- `template_redirect`
- `rest_endpoints`
- `xmlrpc_enabled`
- `wp_headers`
- `set_user_role`
- `added_user_role`
- `removed_user_role`

## Endpoints/fluxos afectados
- `wp-login.php`
- XML-RPC (`xmlrpc.php`), bloqueado por defeito
- REST `/wp/v2/users`, removido para não-admins
- `admin.php?page=sige-app&view=sige_permissoes`
- AJAX RH/Equipe `sige_salvar_staff_secure`
- AJAX legados `sige_criar_usuario_staff`, `sige_editar_usuario_staff`

## Tabelas afectadas
Sem novas tabelas. Escritas já existentes em:
- `wp_sige_logs_auditoria`
- `wp_sige_user_roles`
- `wp_usermeta`
- `wp_users`
- `wp_sige_professores`

## Opções/metadados afectados
- Transients do login shield.
- `_sige_last_authorized_role_change` em `wp_usermeta`.
- Escape hatch opcional: `define('SIGE_XMLRPC_ALLOW', true)`.

## Permissões/perfis críticos
- `sige_admin_ti` / `admin_ti`
- `sige_director` / `direccao_geral` / `director`
- Perfis que concedem `usuarios.gerir_permissoes`

## Tenant isolation
Sem nova superfície cross-tenant. As mudanças de perfil SIGE continuam tenant-scoped via `escola_id` em `sige_permissions_sync_user_role()`.
