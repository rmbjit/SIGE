# PHASE CHARTER - v12.12.7 Critical Actions Lockdown

## Objectivo
Colocar acções críticas do SIGE sob lockdown real do Security Kernel, fechando os P0 encontrados no inventário: M-Pesa manual cross-tenant, despesas no tenant errado, pagamento por perfil apenas leitura e matriz de permissões global entre escolas.

## Incluido
- Inventário e runtime de `view_action` para mutações directas em views administrativas.
- Enforce/delegated/retired no Security Kernel, proibindo `risk=critical` com `mode=observe`.
- Lockdown de pagamentos, despesas, M-Pesa/e-Mola, permissões por escola e arquivamento de alunos.
- Object guards para transacções M-Pesa e aluno removido/arquivado.
- Webhooks M-Pesa/e-Mola em enforcement tokenizado e com rate limit.
- Migração aditiva para `sige_school_role_permissions` e `sige_school_user_permission_overrides`.
- Auditoria e gates de validação da fase.

## Excluido
- MFA completo.
- Secret Vault universal.
- Financial Ledger integral.
- Tenant Isolation Hardening global.
- CSP enforcement.
- Refactor modular amplo.
- Observabilidade completa.

## Riscos
- P1: bloqueio indevido de operação legítima por regra de nonce/permissão mal calibrada.
- P1: migração de permissões por escola alterar acessos existentes sem intenção.
- P1: callback legado duplicado contornar handler canónico.
- P2: 146 superfícies não críticas permanecem em observe para fases posteriores.

## Criterios de aceitacao
- Zero P0/P1 aberto no rediagnóstico adversarial.
- Toda acção crítica fica `enforce`, `delegated` ou `retired`.
- Zero `risk=critical` com `mode=observe`.
- `view_action` em runtime antes da inclusão da view.
- PHP lint verde e `php tools/run-gates.php` verde.
