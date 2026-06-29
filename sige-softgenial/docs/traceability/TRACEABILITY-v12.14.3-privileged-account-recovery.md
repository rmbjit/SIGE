# Matriz de Rastreabilidade - v12.14.3

| Requisito | Implementação | Teste |
|---|---|---|
| Conta com perfil SIGE activo não desaparece se WP role for subscriber | `permissions-ui.php` lista união de WP staff roles + active SIGE user roles | `smoke-user-security-v12-14-3.php` |
| Recuperar Admin TI por incidente | Formulário `break_glass_restore_user_role` | `php -l`, smoke estrutural |
| Criar baseline de integridade para privilegiados | `sige_user_integrity_update_snapshot()` | smoke estrutural |
| Repor desaparecimento/downgrade | `sige_user_integrity_reconcile_privileged_snapshots()` | smoke estrutural |
| Não restaurar remoção autorizada | `sige_user_integrity_retire_privileged_snapshot()` | smoke estrutural |
| Espelho WP controlado para privilegiados | `sige_user_integrity_mirror_wp_role()` | smoke estrutural |
