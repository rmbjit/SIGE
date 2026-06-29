# Pacote de Validacao em Staging - v12.16.0 RC5

## Natureza do pacote

Este pacote e uma Release Candidate para validacao em staging autenticado.
Nao e ZIP final de producao.

## Identificacao

- Fase: v12.16.0 - Institutional Product Architecture and Operational UX Hardening.
- Estado: RC5 local com pre-validacao verde.
- Versao declarada no plugin: 12.15.25.
- Motivo da versao declarada permanecer 12.15.25: evitar declarar release final antes da validacao humana em staging.
- Nome do pacote de staging: sige-softgenial-v12_16_0-rc5-staging-validation.zip.
- ZIP final de producao: bloqueado ate validacao humana.

## Pre-validacao local executada

| Teste | Resultado |
|---|---:|
| PHP lint global | 486/486 sem erro |
| Corredor oficial completo por intervalos | 132/132 gates verdes |
| Release gate | 54/54 verificacoes verdes |
| RC readiness contract | Verde |
| RC readiness smoke | Verde |

## Regras de uso no staging

1. Instalar apenas em ambiente de staging.
2. Fazer backup do plugin actual antes de substituir ficheiros.
3. Validar os perfis obrigatorios no checklist `docs/deploy/STAGING_VALIDATION-v12.16.0-RC5.md`.
4. Nao instalar directamente em producao.
5. Se aparecer P0 ou P1, parar a validacao e voltar para correcao.
6. So preparar ZIP final depois de staging aprovado.

## Ficheiros criticos protegidos

Os ficheiros financeiros, academicos, permissoes e security kernel protegidos permanecem sem alteracao de hash em relacao a baseline RC5.

## Decisao

Pacote autorizado apenas para validacao em staging. ZIP final permanece bloqueado.
