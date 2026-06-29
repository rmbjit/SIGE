# DEPLOY - v12.12.0

## Natureza da versao

Engineering Governance Baseline. Versao de governacao, inventario, gates e correcao de views sem permissao explicita.

## Migracao

Nao cria tabelas e nao altera schema. Inclui seed/migracao aditiva de permissoes SIGE para as views antes sem matriz explicita.

## Validacao pos-instalacao

1. Confirmar versao 12.12.0 no painel do plugin.
2. Abrir Dashboard.
3. Validar acesso a Financeiro, Alunos, Portaria e Comunicacoes com perfis autorizados.
4. Executar smoke operacional em staging.

## Rollback

Rollback por ficheiros para v12.11.9.166. Como nao ha schema novo, nao ha rollback de base de dados.
