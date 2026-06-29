# Migração e Rollback - v12.19.1

## Migração

Instalar o ZIP v12.19.1 sobre a v12.19.0 em staging. Não há migração de base de dados, schema, permissões ou dados históricos.

## Rollback

Se houver regressão visual ou comportamento inesperado no Painel Principal, reinstalar o ZIP v12.19.0 aprovado.

## Risco residual

Baixo. A alteração é localizada no copy da camada read-only do dashboard por perfil e não toca no dashboard core.
