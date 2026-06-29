# QA / Definition of Done - v12.14.3

## Gates executados
- `php -l includes/security-user-integrity.php`
- `php -l admin/system/permissions-ui.php`
- `php -l includes/security-login-shield.php`
- `php -l includes/permissions-layer.php`
- `php -l sige-softgenial.php`
- `php tools/smoke-user-security-v12-14-3.php`
- `php tools/smoke-login-shield.php`
- `php tools/run-gates.php`

## Regressão crítica a validar em staging
1. Instalar a versão.
2. Abrir **Perfis e Permissões**.
3. Confirmar se Edilson/Edmilson reaparece caso ainda tenha perfil SIGE activo.
4. Se não aparecer ou aparecer sem perfil, usar **Recuperação segura de utilizador** com username/e-mail/ID e perfil **Admin TI**.
5. Confirmar que aparece em Perfis e Permissões.
6. Confirmar no WordPress raiz que a conta tem espelho `sige_admin_ti` quando aplicável.
7. Filtrar auditoria por `user_integrity_`.

## Estado
Sem P0/P1 conhecido no escopo entregue.
