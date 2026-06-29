# Phase Charter v12.12.4 - Security Kernel Foundation

## Objectivo
Criar o Security Kernel Foundation do SIGE SoftGenial como nucleo central de seguranca para superficies de execucao. A cadeia alvo e: superficie declarada -> autenticacao -> permissao -> tenant -> nonce/HMAC/token -> rate limit -> auditoria -> allow/deny.

## Incluido
- `includes/security-kernel.php` como runtime central.
- `includes/security-kernel-rules.php` como contrato operacional.
- `docs/security/SECURITY_KERNEL_RULES-v12.12.4.json` como evidencia legivel.
- Cobertura das 174 superficies do manifesto.
- Enforcement piloto em 4 superficies: M-Pesa config, e-Mola config, `sige_desp_print` e `sige_settings_save`.
- Observe mode para 170 superficies restantes.
- Gates novos para runtime, regras, smoke e testes negativos.

## Excluido
- MFA completo.
- Secret Vault universal.
- Financial Ledger.
- Tenant fail-closed global.
- Lockdown completo de todas as acoes criticas.
- CSP enforcement e Design System PRO.
- Refactor modular amplo.

## Riscos
- P1: bloquear AJAX legitimo por nonce errado.
- P1: quebrar webhooks se enforcement for aplicado cedo demais.
- P1: kernel decorativo que nao intercepta nada.
- P2: excesso de ruido se observe gerar logs em massa.
- P2: duplicacao com guards existentes.

## Criterios de aceitacao
- Todas as superficies do manifesto estao no contrato do kernel.
- Cada regra tem `mode=observe` ou `mode=enforce`.
- As 4 superficies piloto estao em enforce com nonce, rate limit e auditoria.
- `sige_settings_save` preserva autorizacao fina por `SIGE_Settings_Policy::can_edit`.
- PHP lint, run-gates e testes negativos verdes.
- Zero P0/P1 aberto no rediagnostico adversarial.
