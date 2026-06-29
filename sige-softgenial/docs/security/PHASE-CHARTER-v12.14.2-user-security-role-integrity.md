# Phase Charter - v12.14.2 User Security & Privileged Role Integrity

## Fase
Hardening complementar da Fase 10, após validação da v12.14.1 em staging.

## Objectivo
Reduzir o risco de comprometimento de contas por username targeting, password spray e abuso de MFA; impedir que perfis/papéis privilegiados sejam atribuídos ou despromovidos silenciosamente por actor não-autorizado.

## Escopo
- Login Shield e rate-limit.
- Anti-enumeração de utilizadores.
- XML-RPC e REST users.
- Auditoria de mudanças de role WordPress.
- Guarda central para perfis SIGE privilegiados.
- Integração com Perfis e Permissões, RH/Equipe e handlers legados de staff.

## Não-escopo
- Forense completa do servidor/hosting.
- Alteração de WordPress core.
- Implementação de WAF externo/Cloudflare rules.
- Migração de base de dados ou criação de novas tabelas.
- Remoção física de contas antigas.

## Critérios de aceitação
- Tentativas falhadas repetidas contra o mesmo username passam a bloquear mesmo que o IP varie.
- Tentativas erradas de MFA contam para o bloqueio.
- Login não revela se o username existe.
- REST users e author enumeration não expõem usernames a visitantes.
- Alterações de role WordPress críticas são auditadas e revertidas quando não vêm de administrador WP real.
- Perfis SIGE privilegiados não podem ser atribuídos/despromovidos por fluxos laterais sem autorização.
- Gates e lint passam.

## Riscos
- Lock temporário por username pode ser provocado por atacante contra conta conhecida; é trade-off consciente para proteger contas críticas.
- XML-RPC desligado pode afectar integrações externas antigas; existe escape hatch `SIGE_XMLRPC_ALLOW`.
- Alterações directas na base de dados fora do runtime PHP precisam de auditoria de hosting/DB.
