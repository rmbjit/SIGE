# Migração e Rollback - v12.14.2 User Security & Privileged Role Integrity

## Migração
1. Fazer backup de ficheiros e base de dados.
2. Instalar o ZIP v12.14.2 em staging.
3. Limpar cache/opcache se existir.
4. Validar login normal de administrador WordPress real.
5. Validar login normal de Director/Admin TI/Financeiro.
6. Abrir Auditoria e confirmar novos eventos `user_integrity_*` quando aplicável.
7. Confirmar que a lista nativa de Users pode mostrar `subscriber`, mas o acesso efectivo deve ser validado pela matriz SIGE.

## Rollback
1. Repor ZIP v12.14.1.
2. Limpar cache/opcache.
3. Se XML-RPC for necessário temporariamente, antes do rollback pode-se usar `define('SIGE_XMLRPC_ALLOW', true)`.
4. Rever logs criados em `wp_sige_logs_auditoria`; não é necessário apagar.

## Escape hatches
- `define('SIGE_USER_GUARD_OFF', true)` desactiva a guarda de integridade em emergência.
- `define('SIGE_XMLRPC_ALLOW', true)` reabre XML-RPC explicitamente.

## Pós-instalação recomendado
- Trocar passwords das contas visadas.
- Confirmar MFA/TOTP em todas as contas críticas.
- Verificar se houve `login_sucesso` suspeito no mesmo período das falhas.
- Verificar alteração de role/perfil no período do incidente.
