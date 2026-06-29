# TENANT ISOLATION REGISTER v12.12.6

Registo de `escola_id`, fallback e itens para Fase 3.

Fecho desta versao:
- `settings_save` exige tenant no kernel.
- `SIGE_Settings_Repository` falha fechado em escrita `sige_config` sem escola resolvida.

Pendencias para Fase 3:
- Remover fallbacks legados remanescentes.
- Validar ownership por objecto.
- Converter todos os fluxos sensiveis para fail-closed.
