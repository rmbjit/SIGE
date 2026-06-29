# Design System PRO - Financeiro Core - v12.15.12

## Estratégia adoptada
A v12.15.12 aplica apenas uma camada visual conservadora ao Financeiro Core. A implementação evita a repetição das regressões anteriores da Fase 11: não há CSS global agressivo, não há normalizador JS, não há alteração de DOM e não há interceptação de eventos financeiros.

## Assets adicionados
- `assets/views/financeiro-core-design-pro.css`
- `assets/views/financeiro-core-design-pro.js`

## Carregamento
O carregamento é feito em `includes/ui-kit.php`, apenas quando `view` corresponde a uma view financeira incluída na whitelist.

## Feature flag
A camada pode ser desligada com:

```sql
sige_design_financeiro_core_v121512_enabled = 0
```

## Contrato técnico
- CSS escopado por `body.sige-view-*`.
- JS passivo, apenas marca `data-sige-financeiro-core-design="12.15.12"` no body.
- Sem `MutationObserver`.
- Sem `appendChild`, `innerHTML`, `document.write`, `eval` ou `new Function`.
- Sem alteração de nomes, valores ou atributos `name` de formulários.
- Sem alteração de PHP financeiro.

## Submódulos abrangidos
- Painel financeiro.
- Registar pagamento.
- Devedores.
- Extratos.
- Lançamentos.
- Relatório mensal.
- Centros financeiros.
- Configuração financeira.
- Planos.
- Despesas.
- Auditoria.
- Inscrições.
- Gerador.
- Pagamentos por turma.
- M-Pesa/e-Mola.
- Reconciliação.
- Aprovações.

## Protecção de fórmulas
A versão inclui `docs/design-system/FINANCEIRO_CORE_PHP_BASELINE-v12.15.12.json` e gate CLI para confirmar que ficheiros financeiros PHP críticos permanecem intactos.
