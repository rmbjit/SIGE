# TECHNICAL_INVENTORY - v12.12.10 - MFA de Operacao (Step-up)

Inventario tecnico do incremento 1 da Fase 4. Base: v12.12.9.

## Superficie

A superficie de accoes do manifesto cresce de 193 para 194 com uma unica accao nova: admin_post:sige_mfa_confirm (endpoint de confirmacao do codigo). O estado do resultado e conduzido por transient, pelo que nao se introduz qualquer query_handler novo (sem leitura de $_GET). As 10 operacoes criticas protegidas ja existiam na superficie; passam a invocar o guard de step-up antes de executar.

## Views

Os pontos de operacao critica em camada de view sao os 2 handlers inline de caixa em admin/finance/financeiro-extratos.php (reabrir e fechar), que renderizam o formulario de confirmacao inline quando o step-up e exigido. As restantes operacoes passam pela camada de servico (AJAX) ou por handlers admin_post.

## Permissoes

O endpoint sige_mfa_confirm exige apenas sessao iniciada (is_user_logged_in) e nonce sige_mfa_confirm; na pratica so o atingem utilizadores dos perfis criticos com desafio pendente. Os perfis abrangidos pelo step-up sao configuraveis (option sige_mfa_stepup_roles, defeito sige_director e sige_admin_ti). A matriz de permissoes existente nao e alterada (current_user_can=475, sige_can=60, inalterados).

## Tenant

O step-up e ortogonal ao isolamento de tenant da Fase 3. O guard corre depois do guard de tenant em cada metodo de servico (a operacao so chega ao step-up se o contexto de escola for valido). A janela de verificacao e por utilizador (transient sige_mfa_ok_{user_id}), nao por escola. Baseline de fallbacks de tenant: 0 (inalterado); baselines congelados 138/139 intactos.

## Segredos

Nao ha segredos novos. As primitivas OTP guardam apenas o hash SHA-256 do codigo em transient, com TTL e maximo de tentativas. O codigo em claro existe so no email enviado e nunca e persistido. A opcao sige_mfa_stepup (on/off) e de configuracao, nao um segredo. O Secret Vault universal continua na Fase 5.

## Dependencias

Sem dependencias externas novas. A entrega do codigo usa wp_mail (o mesmo canal do 2FA de login). Falha de SMTP e tratada com anti-lockout (a operacao e permitida nessa tentativa, com registo). Sem migracao de base de dados.

## Security Kernel

Acrescenta-se a regra admin_post:sige_mfa_confirm em modo observe (risco high, intent nonce sige_mfa_confirm, auditoria activa, rate limit sk_admin_post_sige_mfa_confirm 20/300). O conjunto enforce mantem-se nas mesmas 30 operacoes da Fase 3. Total de regras: 194 (enforce 30, delegated 17, observe 147). O JSON de regras e gerado a partir das regras PHP, garantindo ids e ordem identicos.
