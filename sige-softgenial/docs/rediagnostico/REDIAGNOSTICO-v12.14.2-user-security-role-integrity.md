# Rediagnóstico Adversarial - v12.14.2 User Security & Privileged Role Integrity

## Pergunta 1: A captura prova invasão?
Não. Ela prova tentativas falhadas em volume contra usernames conhecidos. Para provar invasão é necessário correlacionar `login_sucesso`, IP, user-agent, mudanças de role e actividade posterior.

## Pergunta 2: O downgrade para subscriber pode ter sido bug/arquitectura?
Sim. Desde a arquitectura aditiva de permissões, o perfil SIGE activo pode ser a fonte de verdade e o papel WordPress nativo pode ficar como papel neutro. Portanto, `subscriber` no WordPress não implica automaticamente perda de autoridade SIGE. A verificação correcta é o perfil activo em `wp_sige_user_roles`/Perfis e Permissões.

## Pergunta 3: O que a versão impede?
- Spray distribuído contra o mesmo username.
- Abuso repetido de MFA.
- Enumeração simples de utilizadores.
- Downgrade de WP role crítico por actor não-admin real.
- Atribuição/despromoção de perfil SIGE privilegiado por helper lateral sem guarda.

## Pergunta 4: O que fica fora?
- Forense do servidor, logs Apache/Nginx/PHP-FPM/Cloudflare.
- Alterações feitas directamente na BD fora do WordPress.
- WAF externo e bloqueio geográfico/IP reputation.

## Classificação final
- P0 aberto: 0 conhecido.
- P1 aberto: 0 conhecido.
- P2: criar painel dedicado de investigação de incidentes com correlação por IP/user-agent/role-change.
