# RISK_REGISTER - v12.12.10 - MFA de Operacao (Step-up)

Registo de riscos do incremento 1 da Fase 4.

## Bloqueadores

| Severidade | Risco | Estado |
|---|---|---|
| P0 | Nenhum. | - |
| P1 | Nenhum. | - |

## Nao bloqueadores

| Severidade | Risco residual | Mitigacao / Fase futura |
|---|---|---|
| P2 | A reposicao automatica da operacao apos confirmacao ainda nao existe; o utilizador repete a operacao manualmente. | Incremento seguinte da Fase 4. |
| P2 | So ha entrega por email; sem TOTP nem aplicacao autenticadora. | Incremento seguinte da Fase 4. |
| P2 | O step-up cobre as operacoes que movem dinheiro e as credenciais; outras operacoes enforce ainda nao exigem step-up. | Incremento seguinte da Fase 4. |
| P3 | Na caixa, o formulario do codigo depende da ordenacao do admin_notices; resolve-se com render inline (ja aplicado). | Resolvido nesta versao. |
| P3 | Secret Vault universal e Ledger financeiro continuam no roteiro. | Fases 5 e 6. |

## Notas

O incremento e desligado por defeito e nao altera comportamento em instalacoes que nao o liguem. O risco operacional de lock-out e contido por opt-in, desactivacao de emergencia, isencao implicita de quem nao tem o perfil e anti-lockout de SMTP. Nenhuma regra de calculo financeiro ou academico foi tocada.
