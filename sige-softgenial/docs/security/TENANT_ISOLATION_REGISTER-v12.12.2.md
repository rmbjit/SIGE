# TENANT ISOLATION REGISTER v12.12.2

## escola_id
O extracto detalhado usa `escola_id` em todas as consultas de aluno, lancamentos, pagamentos, turma e configuracao.

## fallback
Os fallbacks historicos continuam registados no baseline JSON. Esta versao nao cria fallback novo para escola 1 no documento alterado.

## Fase 3
A remocao global de fallbacks e o modo tenant fail-closed pertencem a Fase 3 - Tenant Isolation Definitivo.
