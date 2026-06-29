# Migracao e Rollback - v12.19.0

## Migracao
Nao ha migracao de base de dados.
Nao ha alteracao de schema.
Nao ha normalizacao, apagamento ou transformacao de historico.
Nao ha alteracao de permissoes reais.

## Instalacao
Instalar o ZIP v12.19.0 sobre a v12.18.0 aprovada em staging.
Depois validar Painel Principal, mobile e modulos sensiveis.

## Rollback
Se houver regressao visual ou operacional:
1. Reinstalar ZIP v12.18.0 aprovado.
2. Limpar cache do navegador e cache WordPress, se existir.
3. Validar Painel Principal, Financeiro, Academico, Portaria e Alunos.

## Risco residual
O bloco depende do DOM do dashboard para inserir a camada depois do hero. Se o dashboard for profundamente alterado no futuro, o JS deve ser revisto. Na v12.19.0 o dashboard core permanece intocado.
