# SECURITY KERNEL POLICY v12.12.6

O Security Kernel opera com dois modos: `enforce` e `observe`.

## enforce
Bloqueia falhas de login, nonce/intencao, tenant, permissao, rate limit e auditoria obrigatoria.

## observe
Regista e classifica superficie sem bloquear. Usado para reduzir regressao antes do lockdown amplo.

## rate limit
Regras high/critical em enforce exigem rate limit.

## auditoria
Regras high/critical em enforce exigem auditoria. Eventos: `security_kernel.allowed`, `security_kernel.denied`, `security_kernel.observed` quando observe log esta activo.

## v12.12.6
Query handlers sao despachados em `admin_init`, `parse_request` e `template_redirect` com prioridade -1000. `settings_save` exige tenant.
