# Deploy - SIGE SoftGenial v12.11.9.88.1

## Build

**SIGE SoftGenial v12.11.9.88.1 - Financeiro Status Sync Hotfix**

Hotfix sobre `v12.11.9.88 - Financeiro Cash Reconciliation PRO`.

## Objectivo

Corrigir casos em que:

1. O lançamento de mensalidades ignora alunos importados/registados por Excel numa turma.
2. Um aluno anteriormente desistente/transferido continua a ser tratado como não-activo nos pagamentos após ser reactivado.

## Instalação recomendada

1. Fazer backup dos ficheiros do plugin actual.
2. Fazer backup da base de dados.
3. Instalar o ZIP `sige-softgenial-v12_11_9_88_1-financeiro-status-sync-hotfix.zip`.
4. Limpar cache do navegador/WordPress, caso exista.
5. Entrar novamente no SIGE.

## Teste funcional mínimo

### A. Aluno importado por Excel

1. Confirmar que o aluno está numa turma do ano lectivo actual.
2. Confirmar que o status do aluno está como `activo`.
3. Ir a **Financeiro → Lançar Mensalidades**.
4. Seleccionar a turma onde o aluno foi importado.
5. Gerar mensalidade para um mês de teste.
6. Confirmar que o aluno aparece no processamento e recebe lançamento.

### B. Aluno desistente reactivado

1. Abrir a ficha do aluno desistente.
2. Alterar a situação para `activo`.
3. Gravar.
4. Abrir **Financeiro → Pagamentos** para esse aluno.
5. Tentar lançar/pagar uma mensalidade futura.
6. Confirmar que o sistema já não mostra bloqueio por desistente/transferido/inactivo.

### C. Pagamentos por Turma

1. Abrir **Financeiro → Pagamentos por Turma**.
2. Seleccionar a turma do aluno reactivado/importado.
3. Confirmar que o aluno aparece na lista da turma.

## Observação importante

A build inclui auto-cura leve: quando um aluno já está activo, mas a matrícula do ano ficou com status antigo por causa de versões anteriores, o módulo de pagamentos tenta sincronizar a matrícula para `activa` automaticamente.

## Rollback

Em caso de anomalia:

1. Repor o ZIP anterior `v12.11.9.88`.
2. Repor backup de base de dados apenas se tiverem sido feitos testes de gravação que queira desfazer.

## Smoke tests incluídos

Executar na raiz do plugin:

```bash
php tools/smoke-financeiro-extratos-cash-reconciliation-v12-11-9-88.php
php tools/smoke-financeiro-status-sync-v12-11-9-88-1.php
```
