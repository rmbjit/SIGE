# Migração e Rollback - v12.15.8

## Instalação
Instalar o ZIP da v12.15.8 por cima da v12.15.7 ou superior da linha 12.15.x e limpar cache do navegador/servidor.

## Validação rápida
Abrir Alunos e Matrículas, abrir os três pontinhos de um card e mover o cursor sobre o card vizinho. O menu deve permanecer por cima e clicável.

## Rollback específico
A camada visual de Alunos continua reversível pelo option:

```sql
UPDATE wp_options SET option_value='0' WHERE option_name='sige_design_alunos_v12157_enabled';
```

Para reactivar:

```sql
UPDATE wp_options SET option_value='1' WHERE option_name='sige_design_alunos_v12157_enabled';
```

Alternativamente, voltar ao ZIP v12.15.7.
