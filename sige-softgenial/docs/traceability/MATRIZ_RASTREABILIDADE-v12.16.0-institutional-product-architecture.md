# Matriz de Rastreabilidade - v12.16.0 - Institutional Product Architecture & Operational UX Hardening

## Matriz principal

| Diagnostico | Problema | Solucao aplicada ou planeada | Ficheiros afectados | Teste/Gate | Estado RC5 | Prioridade |
|---|---|---|---|---|---|---:|
| D-16-01 | Falta de artefactos formais da fase | Criar inventario, Charter, DoD, QA, rollback e matriz | `docs/governance/*`, `docs/qa/*`, `docs/traceability/*`, `docs/migration/*` | `tools/check-v12-16-0-governance.php` | Fechado em RC0 | P3 |
| D-16-02 | Sistema forte, mas organizado demais por modulos | Criar arquitectura operacional por rotina/perfil antes de alterar visual | `includes/institutional-product-map.php`, `admin/system/dashboard-view.php` | `check/smoke-v12-16-0-operational-map` | Fechado em RC1 | P2 |
| D-16-03 | Menus extensos podem aumentar carga cognitiva | Criar faixa operacional antes do menu completo, sem alterar rotas/permissoes | `includes/admin-shell.php`, `includes/institutional-product-map.php` | `check/smoke-v12-16-0-operational-navigation` | Fechado em RC3 | P1/P2 |
| D-16-04 | Dashboards precisam responder ao que o utilizador faz agora | Atalhos e checklists por perfil, sem regra nova | `admin/system/dashboard-view.php`, `includes/institutional-product-map.php` | `check/smoke-v12-16-0-operational-checklists` | Fechado em RC2 | P2 |
| D-16-05 | Fluxos criticos precisam ser mais guiados | Microcopy e orientacao de fluxos guiados antes de alteracoes profundas nos modulos | `admin/system/dashboard-view.php`, `includes/institutional-product-map.php` | `check/smoke-v12-16-0-flow-guidance` | Fechado em RC4 | P1/P2 |
| D-16-06 | Financeiro e P0 e nao pode sofrer regressao | Manter hashes, gates financeiros e diff sem formulas | `includes/finance-core.php`, `admin/finance/*` | Gates financeiros existentes | Protegido e sem alteracao | P0 |
| D-16-07 | Academico e P0 e nao pode sofrer regressao | Nao alterar formulas, pauta ou boletim nesta fase | `admin/academic/notas*`, `pautas*`, `boletim*` | Gates existentes e QA | Protegido e sem alteracao | P0 |
| D-16-08 | Portaria deve permanecer simples | Manter rota directa e orientacao unica para guarda | `admin/system/portaria-view.php`, `includes/institutional-product-map.php` | QA portaria + smoke RC4 | Protegido | P1 |
| D-16-09 | Pesquisa global reduz cliques e deve continuar segura | Preservar so leitura, escola_id e permissao por grupo | `includes/sige-pesquisa-global.php` | `check-pesquisa-global-v12-15-21` | Protegido e sem alteracao | P1 |
| D-16-10 | Alunos e monolito de risco | Separacao gradual apenas apos contrato proprio | `admin/academic/alunos_lista.php` | Gate de estabilidade alunos | Planeado para fase futura | P2 |
| D-16-11 | RC final pode ficar sem contrato de readiness | Criar gate de RC readiness, smoke final, docs de staging e riscos residuais | `tools/check-v12-16-0-rc-readiness.php`, `tools/smoke-v12-16-0-rc-readiness.php`, docs RC5 | `check/smoke-v12-16-0-rc-readiness` | Fechado em RC5 | P1/P3 |

## Rastreabilidade dos documentos RC0

| Artefacto | Caminho | Criterio |
|---|---|---|
| Inventario tecnico | `docs/governance/TECHNICAL_INVENTORY-v12.16.0-institutional-product-architecture.md` | Deve conter baseline, metricas, risco e ficheiros criticos |
| Inventario JSON | `docs/governance/INVENTORY_SUMMARY-v12.16.0-institutional-product-architecture.json` | Deve conter metricas e hashes criticos |
| Phase Charter | `docs/governance/PHASE_CHARTER-v12.16.0-institutional-product-architecture.md` | Deve declarar escopo incluido/excluido, riscos, ordem RC e rollback |
| Definition of Done | `docs/governance/DEFINITION_OF_DONE-v12.16.0-institutional-product-architecture.md` | Deve declarar bloqueadores P0/P1 e evidencias |
| QA Plan | `docs/qa/QA_PLAN-v12.16.0-institutional-product-architecture.md` | Deve cobrir lint, gates, financeiro, academico, portaria, permissoes e mobile |
| Rollback | `docs/migration/MIGRATION_ROLLBACK-v12.16.0-institutional-product-architecture.md` | Deve prever rollback por RC e baseline intacta |

## Rastreabilidade RC1-RC5

| RC | Entrega | Ficheiros centrais | Gates | Estado |
|---|---|---|---|---|
| RC1 | Mapa operacional por perfil no dashboard | `includes/institutional-product-map.php`, `admin/system/dashboard-view.php` | `check-v12-16-0-operational-map.php`, `smoke-v12-16-0-operational-map.php` | Fechado localmente |
| RC2 | Checklists operacionais por perfil | `includes/institutional-product-map.php`, `admin/system/dashboard-view.php` | `check-v12-16-0-operational-checklists.php`, `smoke-v12-16-0-operational-checklists.php` | Fechado localmente |
| RC3 | Faixa operacional no shell antes do menu completo | `includes/institutional-product-map.php`, `includes/admin-shell.php` | `check-v12-16-0-operational-navigation.php`, `smoke-v12-16-0-operational-navigation.php` | Fechado localmente |
| RC4 | Fluxos guiados e microcopy de seguranca no dashboard | `includes/institutional-product-map.php`, `admin/system/dashboard-view.php` | `check-v12-16-0-flow-guidance.php`, `smoke-v12-16-0-flow-guidance.php` | Fechado localmente |
| RC5 | Hardening final, readiness, riscos residuais e staging pack | `docs/governance/*RC5*`, `docs/qa/*RC5*`, `docs/deploy/*RC5*`, `tools/*rc-readiness*` | `check-v12-16-0-rc-readiness.php`, `smoke-v12-16-0-rc-readiness.php` | Fechado localmente quando gates verdes |

## Rastreabilidade RC6 - hotfix de staging

| Diagnostico | Problema | Solucao | Ficheiros afectados | Teste | Estado | Prioridade |
|---|---|---|---|---|---|---:|
| D-16-12 | Perfil Professor entra em loop ao abrir Minhas Turmas por URL `#038;view` | Serializar redirect JavaScript com `wp_json_encode()` e bloquear regressao por gate | `admin/system/dashboard-view.php`, `tools/check-v12-16-0-professor-route-hotfix.php`, `tools/smoke-v12-16-0-professor-route-hotfix.php` | `check/smoke-v12-16-0-professor-route-hotfix` | Fechado localmente em RC6, pendente staging | P1 |
