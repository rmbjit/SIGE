# QA v12.12.64 - Financeiro histórico de classe preservado

## Cenário coberto
Aluno pagou Janeiro-Abril com serviço de mensalidade da 4ª classe. Em Maio, a matrícula/turma actual passa para 2ª classe. O sistema deve reconhecer Janeiro-Abril como meses pagos e só tratar Maio em diante segundo a classe actual.

## Gates executados

| Gate | Resultado |
|---|---:|
| `php -l includes/finance-core.php` | OK |
| `php -l admin/finance/financeiro-pagamentos.php` | OK |
| `php -l admin/finance/financeiro-gerador.php` | OK |
| `php -l admin/finance/financeiro-devedores-view.php` | OK |
| `php -l admin/finance/financeiro-extratos.php` | OK |
| `php tools/smoke-financeiro-historico-classe-v12-12-64.php` | OK |

## Critérios de aceitação

- [x] Mensalidade paga com serviço de classe anterior é reconhecida por tipo canónico no mês.
- [x] `mensalidade` e `mensal` são equivalentes para evitar divergência entre catálogos legados e novos.
- [x] A UI de Pagamentos deixa de depender exclusivamente do `servico_id` actual para marcar mês como pago.
- [x] O upsert financeiro protege lançamentos históricos pagos/isentos/em plano antes da validação da classe actual.
- [x] Sem alteração de schema.

## Observação operacional
Depois de instalar, abrir o aluno afectado em **Financeiro → Pagamentos** e confirmar que Janeiro, Fevereiro, Março e Abril aparecem como pagos/isentos conforme o histórico real; Maio em diante deve seguir a mensalidade da classe actual.
