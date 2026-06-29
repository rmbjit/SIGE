# Security Kernel Policy v12.12.4

O Security Kernel e a camada transversal que orquestra controlos por superficie.

## Modos
- `enforce`: bloqueia quando falham autenticacao, intent, tenant, permissao ou rate limit.
- `observe`: regista a superficie no contrato e prepara lockdown futuro, sem bloquear.

## Piloto enforce
- `admin_post:sige_mpesa_guardar_config`.
- `admin_post:sige_emola_guardar_config`.
- `query_handler:sige_desp_print`.
- `wp_ajax:sige_settings_save`.

## Regras
Todas as superficies devem ter risco, modulo, public/private, tenant, intent, permissao, auditoria e rate limit quando aplicavel. Settings Save preserva a policy fina `SIGE_Settings_Policy::can_edit`.
