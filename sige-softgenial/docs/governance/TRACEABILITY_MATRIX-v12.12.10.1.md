# TRACEABILITY_MATRIX - v12.12.10.1 - MFA de Operacao (Step-up)

Rastreio de cada criterio de seguranca (SK) ate a evidencia que o comprova.

| ID | Criterio | Evidencia |
|---|---|---|
| SK-001 | Modulo MFA carregado apos o escudo de login | sige-softgenial.php require de includes/security-mfa-stepup.php; gate check-mfa-stepup |
| SK-002 | 12 primitivas definidas | smoke-mfa-stepup-v12-12-10 (12 primitivas definidas) |
| SK-003 | Step-up desligado por defeito | sige_mfa_stepup_enabled le option sige_mfa_stepup com defeito off |
| SK-004 | Desactivacao de emergencia | SIGE_MFA_STEPUP_OFF curto-circuita sige_mfa_stepup_enabled |
| SK-005 | Guard permite quando desligado | smoke (desligado: guard permite) |
| SK-006 | Guard permite fora do perfil | smoke (fora do perfil: permite) |
| SK-007 | Guard bloqueia fail-closed | smoke (no perfil, nao verificado: BLOQUEIA) |
| SK-008 | Janela de verificacao recente | smoke (verificacao recente: permite) |
| SK-009 | Anti-lockout de SMTP | smoke (email falha: permite) |
| SK-010 | Verificacao de codigo (ok/errado) | smoke (verify codigo certo/errado) |
| SK-011 | 6 operacoes de servico protegidas | check-mfa-stepup (contextos fin_*) |
| SK-012 | 2 handlers de credenciais protegidos | check-mfa-stepup (cfg_emola, cfg_mpesa) |
| SK-013 | 2 handlers de caixa protegidos | check-mfa-stepup (caixa_reabrir, caixa_fechar) |
| SK-014 | 10 contextos protegidos no total | check-mfa-stepup (10 operacoes criticas) |
| SK-015 | Endpoint de confirmacao com nonce | regra de Kernel admin_post:sige_mfa_confirm intent nonce |
| SK-016 | Regra de Kernel em observe; enforce=30 | check-security-kernel-rules (enforce=30) |
| SK-017 | Calculo intacto e baselines congelados | smoke (calculo intacto; baselines 138/139); check-tenant-fallbacks |
| SK-018 | Sem em-dashes; versao sincronizada; gates verdes | smoke-release-gate; run-gates |

Evidencia consolidada: a execucao de tools/run-gates.php cobre todos os criterios acima; o smoke dedicado fornece a verificacao de runtime do comportamento fail-closed.
