# QA Smoke Gate - Financeiro Extractos v12.11.9.87.1

## Resultado

**GO para preparar a Fase 2**, com a recomendação de instalar primeiro esta build hotfix.

## O que o gate encontrou

O smoke test profundo detectou um erro bloqueante potencial: o filtro por ciclo podia falhar em ambientes PHP sem extensão `mbstring`, porque `sige_extracto_ciclo_label()` chamava `mb_strtolower()` directamente.

Também foram detectados dois pontos de robustez em schemas/fixtures legados: despesas sem `categoria` e movimentos sem `recibo_numero`.

## Correcções aplicadas

- `sige_extracto_safe_lower()` com fallback seguro.
- Regex de ciclo com `/iu` para suporte Unicode e case-insensitive.
- Fallback visual para despesas sem categoria.
- Fallback de recibo para movimentos sem `recibo_numero`.

## Cenários automatizados renderizados

| Cenário | Resultado |
|---|---:|
| daily-empty | OK |
| student-empty | OK |
| student-loaded | OK |
| student-search | OK |
| density-invalid | OK |
| daily-closed | OK |
| daily-monthly-creche | OK |
| post-close | OK |
| post-reopen | OK |
| post-refund | OK |

## Checks técnicos

| Check | Resultado |
|---|---:|
| ZIP original íntegro | OK |
| PHP lint completo | OK |
| Smoke PRO UX Hardening | OK |
| Deep Smoke Gate | OK |
| Render smoke com stubs | OK |
| JS syntax check renderizado | OK |
| CSS brace balance | OK |
| ZIP final íntegro | OK |

## Conclusão

A Fase 1 fica mais segura para ambiente real. A Fase 2 pode avançar depois de validação manual rápida desta build em staging/produção controlada.
