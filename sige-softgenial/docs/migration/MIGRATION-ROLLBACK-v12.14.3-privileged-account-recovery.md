# Migração e Rollback - v12.14.3

## Migração
1. Fazer backup de ficheiros e base de dados.
2. Instalar o ZIP da versão.
3. Entrar como administrador WordPress real.
4. Abrir **Perfis e Permissões** para disparar seed/reconcile dos snapshots privilegiados.
5. Validar a conta Admin TI reportada.

## Recuperação do caso Edilson/Edmilson
- Se a conta reaparecer com perfil SIGE activo: confirmar perfil e testar login.
- Se não reaparecer: usar **Recuperação segura de utilizador**, informar e-mail/username/ID e seleccionar **Admin TI**.
- Trocar/confirmar password e MFA já activo.

## Rollback
1. Repor ZIP anterior v12.14.2.
2. Se necessário, apagar a option `sige_user_integrity_privileged_snapshots` apenas depois de exportar evidência.
3. Revalidar login e Perfis e Permissões.
