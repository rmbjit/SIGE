# QA Smoke - v12.11.9.88.2 Financeiro Mensalidade Virtual & Classe Canonical Hotfix

## Resultado
**Gate aprovado.**

## Checks executados

| Check | Resultado |
|---|---:|
| `php -l` em `admin/finance/financeiro-pagamentos.php` | OK |
| `php -l` em `includes/finance-core.php` | OK |
| `php -l` em `includes/fin-classe-helper.php` | OK |
| `php -l` em `sige-softgenial.php` | OK |
| Lint completo em todos os PHP do plugin | OK - 245 ficheiros |
| Smoke específico v12.11.9.88.2 | OK |
| ZIP final íntegro | OK |
| Smoke executado a partir do ZIP extraído | OK |

## Cenários cobertos no smoke específico

- Versão do plugin actualizada para `12.11.9.88.2`.
- `BUILD.json` actualizado com o build id correcto.
- `SIGE_FinanceClasseHelper` tem fallback seguro para ambientes sem `mbstring`.
- `sige_fin_servico_permitido_para_classe()` delega para helper canónico.
- Existe helper `sige_fin_find_lancamento_aberto_por_tipo_mes()`.
- `MENS_MM`, `TRAN_MM` e `PACK_MM` procuram dívida aberta por tipo+mês antes de criar nova dívida.
- `MENS_MM` deixa diagnóstico quando falha a resolução/materialização da mensalidade.
- A validação de classe no upsert usa resolução canónica da classe actual do aluno.
- Casos comportamentais de classe:
  - `2º/3º Ano` aceita `2`.
  - `2º/3º Ano` aceita `3º Ano`.
  - `1ª Classe` aceita `1º Ano`.
  - `todas` aceita `11B`.
  - `4ª` rejeita `5ª`.

## Risco residual
Baixo. A build não altera cálculos, recibos, estornos, fecho/reconciliação ou base de dados. A alteração actua na compatibilidade de classe e na conversão segura de itens virtuais para lançamentos reais.
