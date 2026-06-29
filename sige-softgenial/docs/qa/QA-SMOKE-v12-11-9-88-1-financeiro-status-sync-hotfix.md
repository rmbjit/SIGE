# QA Smoke - v12.11.9.88.1 Financeiro Status Sync Hotfix

## Resultado

**Gate aprovado.**

## Validações executadas

| Área | Resultado |
|---|---:|
| PHP lint completo | OK |
| Total de ficheiros PHP lintados | 243 |
| Smoke Cash Reconciliation v12.11.9.88.x | OK |
| Smoke Status Sync v12.11.9.88.1 | OK |
| Integridade ZIP final | OK |

## Riscos cobertos

- Gerador de mensalidades com filtro rígido `a.status = 'activo'`.
- Pagamentos por Turma com filtro rígido `m.status_matricula = 'activa'`.
- Reactivação de aluno sem sincronização de matrícula.
- Importação Excel/CSV criando matrícula desalinhada do status do aluno.
- Pesquisa de pagamentos com JOIN de matrícula sem `escola_id`.

## Ficheiros alterados

- `includes/core-helpers.php`
- `includes/db-handler.php`
- `includes/aluno-fetch-ajax.php`
- `admin/finance/financeiro-gerador.php`
- `admin/finance/financeiro-pagamentos.php`
- `admin/finance/pagamentos-turma-view.php`
- `sige-softgenial.php`
- `BUILD.json`
- `tools/smoke-financeiro-extratos-cash-reconciliation-v12-11-9-88.php`
- `tools/smoke-financeiro-status-sync-v12-11-9-88-1.php`

## Garantias

Este hotfix não alterou:

- fórmulas financeiras;
- recibos;
- pagamentos já feitos;
- estornos;
- fecho/reabertura de caixa;
- reconciliação da Fase 2;
- exportações;
- permissões;
- estrutura de tabelas.
