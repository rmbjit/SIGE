# Inventário Técnico - v12.14.3

## Ficheiros afectados
- `includes/security-user-integrity.php`
- `admin/system/permissions-ui.php`
- `sige-softgenial.php`
- `BUILD.json`
- `CHANGELOG.md`
- `docs/*v12.14.3*`
- `tools/smoke-user-security-v12-14-3.php`

## Tabelas/opções afectadas
- `{prefix}sige_user_roles`
- `{prefix}sige_roles`
- `wp_usermeta` / roles WordPress
- Option nova: `sige_user_integrity_privileged_snapshots`
- Option nova: `sige_user_integrity_seeded_12143`

## Superfícies afectadas
- Menu **Perfis e Permissões**.
- Hooks de `admin_init` para reconciliação de perfis privilegiados.
- Atribuição/remoção de perfil SIGE activo.
- Auditoria `user_integrity_*`.

## Permissões/tenant
- A recuperação break-glass é exclusiva de administrador WordPress real/protegido.
- A listagem continua tenant-scoped por `escola_id`.
- O reconciliador restaura apenas snapshots com `user_id + escola_id + role` previamente autorizados.
