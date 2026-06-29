# QA Results v12.16.0 RC1 - Operational Map

## Testes executados

| Teste | Resultado | Evidencia |
| --- | --- | --- |
| PHP lint global | Verde | `php-lint-after-rc1-v12.16.0.log` |
| Gate RC1 contract | Verde | `run-gates-after-rc1-v12.16.0-122-124.log` |
| Smoke RC1 operational map | Verde | `run-gates-after-rc1-v12.16.0-122-124.log` |
| Corredor oficial 1-22 | Verde | `run-gates-after-rc1-v12.16.0-1-22.log` |
| Corredor oficial 23-45 | Verde | `run-gates-after-rc1-v12.16.0-23-45.log` |
| Corredor oficial 46-75 | Verde | `run-gates-after-rc1-v12.16.0-46-75.log` |
| Corredor oficial 76-105 | Verde | `run-gates-after-rc1-v12.16.0-76-105.log` |
| Corredor oficial 106-124 | Verde | `run-gates-after-rc1-v12.16.0-106-124.log` |

## Cobertura de smoke RC1

- Sem permissao: nenhum atalho operacional exposto.
- Tesouraria: `financeiro-pagamentos` exposto sem abrir `alunos_lista` indevidamente.
- Secretaria: `alunos_lista` e `turmas` expostos sem abrir pagamento.
- Portaria: `portaria` exposto e perfil mantido limpo.
- Admin tecnico: `sige_core_status` e `config_center` expostos.
- Perfil transversal: convertido para contexto de gestao.

## Riscos residuais

Nenhum P0 ou P1 aberto neste RC. Mantem-se P2 controlado para refinamento progressivo da arquitectura operacional e UX institucional.
