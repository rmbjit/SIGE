# Definition of Done - v12.16.0 - Institutional Product Architecture & Operational UX Hardening

## DoD global da fase

A v12.16.0 só pode ser considerada concluída quando todos os critérios abaixo estiverem cumpridos.

| Código | Critério | Evidência exigida |
|---|---|---|
| DoD-16-01 | Baseline `12.15.25` congelada | SHA-256, gates baseline e lint baseline documentados |
| DoD-16-02 | Inventário técnico formal criado | `TECHNICAL_INVENTORY-v12.16.0-institutional-product-architecture.md` |
| DoD-16-03 | Phase Charter criado | `PHASE_CHARTER-v12.16.0-institutional-product-architecture.md` |
| DoD-16-04 | Matriz de rastreabilidade criada | `MATRIZ_RASTREABILIDADE-v12.16.0-institutional-product-architecture.md` |
| DoD-16-05 | Plano de QA criado | `QA_PLAN-v12.16.0-institutional-product-architecture.md` |
| DoD-16-06 | Plano de rollback criado | `MIGRATION_ROLLBACK-v12.16.0-institutional-product-architecture.md` |
| DoD-16-07 | Gate de governança ligado ao corredor | `tools/check-v12-16-0-governance.php` em `tools/run-gates.php` |
| DoD-16-08 | PHP lint total verde | `php -l` em todos os ficheiros PHP |
| DoD-16-09 | Corredor completo verde | `php tools/run-gates.php` sem falhas |
| DoD-16-10 | Financeiro sem regressão | `includes/finance-core.php` e ficheiros financeiros críticos sem alteração não autorizada |
| DoD-16-11 | Académico sem regressão | Nenhuma fórmula ou regra de pauta/boletim alterada sem fase dedicada |
| DoD-16-12 | Portaria preservada | Nenhuma complexidade administrativa nova no perfil guarda |
| DoD-16-13 | Permissões preservadas | Nenhum perfil ganha acesso por efeito visual ou navegação |
| DoD-16-14 | Pesquisa global preservada | Gate de pesquisa global verde |
| DoD-16-15 | Mobile sem regressão crítica | QA manual recomendado e gates de assets sem falhas |
| DoD-16-16 | Documentação de versão final | BUILD, changelog e instruções de validação em staging no RC final |
| DoD-16-17 | ZIP final só após P0/P1 zero | Evidência de gates, lint e riscos residuais |

## Critérios bloqueadores

A fase fica bloqueada se ocorrer qualquer uma das situações abaixo:

1. Alteração de fórmula financeira.
2. Alteração de fórmula académica.
3. Alteração de schema ou migração sem decisão explícita.
4. Regressão de permissão ou exposição cross-tenant.
5. Regressão em registo de pagamento, recibo, dívida, transporte, desconto, multa ou fecho de caixa.
6. Regressão em notas, pautas, boletins ou encerramento.
7. Regressão em portaria que atrase ou confunda validação de entrada.
8. Falha no corredor completo de gates.
9. Falha em lint PHP.
10. Falta de evidência para algum teste declarado.

## Testes mínimos por RC

| RC | Testes mínimos |
|---|---|
| RC0 | Gate de governança, lint do novo gate, corredor completo |
| RC1 | Gate dedicado do helper/orientação, lint, pesquisa global, shell, permissões |
| RC2 | Gate de menu sem acesso novo, mobile bottom nav, pesquisa global |
| RC3 | QA por perfil, dashboards, permissões, performance básica |
| RC4 | Fluxos guiados, financeiro/académico só se protegidos, auditoria |
| RC5 | Lint total, corredor total, build sync, changelog, ZIP e staging checklist |

## Regra de honestidade de QA

Qualquer teste não executado deve ser registado explicitamente como não executado. Não é permitido declarar validação em browser autenticado, staging ou dados reais sem evidência externa.
