# Migração e Rollback - v12.15.12 - Financeiro Core

## Instalação
Instalar o ZIP por cima da v12.15.11 aprovada.

## Limpeza pós-instalação
- Limpar cache do navegador.
- Limpar cache de servidor/CDN, se existir.

## Teste mínimo pós-instalação
- Abrir Financeiro Dashboard.
- Abrir Registar Pagamento.
- Pesquisar aluno.
- Confirmar que seleção de dívida/método/valor continua normal.
- Abrir Devedores e Extratos.
- Confirmar que Alunos e Portaria continuam iguais.

## Rollback funcional sem reinstalar
Definir a option:

```sql
sige_design_financeiro_core_v121512_enabled = 0
```

Isto desactiva apenas os assets visuais financeiros desta vaga.

## Rollback total
Reinstalar a v12.15.11 aprovada.
