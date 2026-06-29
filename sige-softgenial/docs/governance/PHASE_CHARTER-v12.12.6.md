# PHASE CHARTER v12.12.6 - Security Kernel Query Runtime & Tenant Guard Closure

## Objectivo
Fechar os P1 detectados na segunda reverificacao adversarial da v12.12.5: query handlers precisam passar pelo Security Kernel antes dos handlers funcionais, e `wp_ajax:sige_settings_save` precisa falhar fechado quando o tenant/escola nao esta resolvido.

## Incluido
- Dispatch antecipado de query handlers em `admin_init`, `parse_request` e `template_redirect`.
- Prioridade runtime negativa formal via `SIGE_SECURITY_KERNEL_EARLY_PRIORITY`.
- `wp_hook`, REST e shortcode interceptados com prioridade antecipada quando aplicavel.
- `wp_ajax:sige_settings_save` com `tenant_required=true` e `tenant_scope=required_for_sige_config_writes`.
- `SIGE_Settings_Repository` sem fallback para escola 1 em escritas tenant-scoped.
- Gates e smokes adversariais para ordem runtime real e tenant guard.

## Excluido
- MFA obrigatorio.
- Secret Vault definitivo.
- Financial Ledger.
- Lockdown total das 170 superficies em observe.
- Tenant ownership por objecto.
- CSP enforcement.

## Riscos
- P1: kernel decorativo se query handlers nao correrem antes dos handlers funcionais.
- P1: settings_save cair para escola 1 quando o contexto tenant falha.
- P2: 170 superficies continuam em observe ate Critical Actions Lockdown.
- P2: REST publico ainda fica sem enforcement token/HMAC nesta fase.

## Criterios de aceitacao
- Query handlers registados em `admin_init`, `parse_request` e `template_redirect` com prioridade negativa.
- `sige_print` observado em `admin_init`.
- `sige_portaria_camera` observado antes do render em `template_redirect`.
- `sige_desp_print` em enforce continua valido com nonce, permissao, tenant, rate e auditoria.
- `settings_save` exige tenant no kernel.
- Repository nao executa update/insert em `sige_config` sem escola resolvida.
- PHP lint verde, run-gates verde, P0/P1 aberto igual a zero.
