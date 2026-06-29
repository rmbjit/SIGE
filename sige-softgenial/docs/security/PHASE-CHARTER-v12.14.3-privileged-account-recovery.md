# Phase Charter - v12.14.3 - Privileged Account Recovery & Staff Visibility

## Objectivo
Corrigir o cenário em que uma conta privilegiada deixa de aparecer em **Perfis e Permissões** por estar como `subscriber` no WordPress, mesmo quando o SIGE usa perfil operacional próprio; e adicionar mecanismo preventivo para que perfis privilegiados não desapareçam/downgradeem sem restauração auditada.

## Escopo
- Listagem de utilizadores na UI de Perfis e Permissões.
- Recuperação break-glass auditada de contas privilegiadas.
- Snapshot de integridade para perfis SIGE privilegiados.
- Espelho controlado de WP role para Admin TI/Direcção.
- Reconciliador automático de snapshots activos.

## Não-escopo
- Investigação forense completa do servidor, logs do hosting, banco de dados e WordPress core.
- Alteração de política de MFA, excepto preservação da blindagem já entregue na v12.14.2.
- Reset automático de passwords.

## Critérios de aceitação
- Uma conta com perfil SIGE activo aparece na UI mesmo se o WP role nativo for `subscriber`.
- Admin WordPress real consegue recuperar uma conta por ID, username ou e-mail.
- Perfis privilegiados autorizados geram snapshot e espelho WP.
- Downgrade/desaparecimento posterior é revertido por reconciliador se o snapshot não foi aposentado.
- Remoção autorizada por owner aposenta o snapshot para evitar restauração indevida.
