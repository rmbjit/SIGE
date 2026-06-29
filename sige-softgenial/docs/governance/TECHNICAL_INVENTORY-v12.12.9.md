# TECHNICAL INVENTORY - v12.12.9

Inventario tecnico realizado ANTES da implementacao (Fase 0 do protocolo).

## Superficie de accao

Inventario regenerado por `tools/inventory-surface.php --write` para a versao 12.12.9 (base 12.12.8.1). Manifesto de superficie em `docs/security/ACTION_SURFACE_MANIFEST-v12.12.9.json`. Sem novos endpoints ou accoes introduzidos: o incremento e de endurecimento de resolucao de tenant, nao de superficie.

## Views

Cerca de 50 views admin (admin/academic, admin/finance, admin/jardim, admin/hr, admin/system, admin/whatsapp) continham o ternario morto `function_exists('sige_get_escola_id') ? [(int)] sige_get_escola_id() : 1`. Sao contextos de leitura com utilizador autenticado, que resolvem a escola pela meta do utilizador (passo 2 do resolvedor). O ramo `: 1` era codigo morto (o resolvedor esta sempre carregado). Limpos para `: 0` (fail-closed), tornando o resolvedor a unica fonte de verdade.

## Permissoes

Baseline de autorizacao inalterado: current_user_can = 475, sige_can = 60. Os fallbacks fixos gated em `includes/permissions-layer.php` (sincronizacao de papeis) e `admin/system/permissions-ui.php` deixaram de assumir escola 1: passam a resolucao por escola unica activa, com os sumidouros de escrita ja protegidos (v12.12.8.1) a bloquear escola 0.

## Tenant

Nucleo do incremento. Resolvedor `sige_get_escola_id()` (cadeia de 5 passos): constante explicita, meta do utilizador, subdominio/subdirectorio por slug, e fallback final. O fallback final era cego (`return SIGE_ESCOLA_MALISA`, constante = 1), assumindo que a escola unica tem id 1. Substituido por `sige_multitenancy_single_active_school_id()` (id real da unica escola activa, ou 0). Constante `SIGE_ESCOLA_MALISA` so referenciada nesse ponto (multitenancy.php), agora obsoleta. Identificadas e tratadas quatro classes de id=1 cego: ternario (132), fixo (7), default de linha `?? 1` (9), return da constante (1). Baseline de fallbacks: 138 -> 0. Registo em `docs/security/TENANT_ISOLATION_REGISTER-v12.12.9.md`.

## Segredos

Sem alteracao. Registo de segredos e opcoes em `docs/security/SECRETS_OPTIONS_REGISTER-v12.12.9.md`; baseline em `docs/security/SECRETS_OPTIONS_BASELINE-v12.12.9.json`. Nenhum segredo introduzido ou exposto por este incremento.

## Dependencias

Sem alteracao. Dependencias externas (Z-API, M-Pesa, e-Mola, SigeHub, IMAP/SMTP) inalteradas. Confirmado que os callbacks M-Pesa/e-Mola resolvem a escola pelo payload e tokens scoped (`UNIQUE (escola_id, referencia)`), nao pelo resolvedor de utilizador. Registo em `docs/security/EXTERNAL_DEPENDENCIES_REGISTER-v12.12.9.md`.

## Security Kernel

193 regras (enforce=30, delegated=17, observe=146) inalteradas em numero e ids. Manifesto sincronizado em `docs/security/SECURITY_KERNEL_RULES-v12.12.9.json`. Politica em `docs/security/SECURITY_KERNEL_POLICY-v12.12.9.md`.
