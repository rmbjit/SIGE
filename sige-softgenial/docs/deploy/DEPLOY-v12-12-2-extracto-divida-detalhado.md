# Deploy v12.12.2 - Extracto de Divida Detalhado

## Tipo de release
Melhoria funcional urgente com controlo de governacao preservado.

## Passos
1. Fazer backup antes da instalacao.
2. Instalar em staging.
3. Testar a Central de Devedores.
4. Comparar o saldo do extracto detalhado com o saldo apresentado na lista de devedores e no lancamento financeiro.
5. So promover para producao apos validacao por utilizador financeiro real.

## Rollback
Reinstalar o ZIP v12.12.1 se o documento apresentar divergencia financeira, erro visual impeditivo ou bloqueio de acesso indevido.

## Schema
Sem alteracao de schema.
