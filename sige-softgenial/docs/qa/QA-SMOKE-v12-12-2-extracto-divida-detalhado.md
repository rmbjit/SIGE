# QA Smoke - v12.12.2 - Extracto de Dívida Detalhado

## Objectivo
Validar que o documento de dívida emitido a partir da Central de Devedores apresenta decomposição suficiente para encarregados, sem alterar schema, sem escrever na base de dados e sem divergir da fórmula financeira canónica.

## Comandos executados

```bash
php tools/smoke-devedores-extracto-detalhado-v12-12-2.php
php tools/run-gates.php
find . -name '*.php' -print0 | xargs -0 -n1 -P8 php -l
```

## Resultado

- Smoke específico: OK - 25 verificações passaram.
- Gates oficiais: OK - 28/28 verdes.
- PHP lint: OK - 332 ficheiros PHP, 0 falhas.

## Cobertura principal

- `sige_gerar_html_factura()` imprime agora `EXTRATO DE DÍVIDA DETALHADO`.
- Inclui status `pendente`, `parcial` e `em_plano`.
- Usa helper documental de decomposição financeira.
- Usa fórmula canónica quando `sige_fin_total_lancamento()` e `sige_fin_saldo_lancamento()` estão disponíveis.
- Mostra base/propina, transporte, extras, multa, descontos, desconto especial, total lançado, já pago e saldo.
- Mostra recibos e último pagamento por lançamento, quando existem.
- Mantém queries com `aluno_id` e `escola_id`.
- Não chama recalculadores nem executa escritas na base de dados.
- Central de Devedores passa a apresentar botões semanticamente claros: `Extracto dívida` e `Histórico`.
- Links gerados pela Central de Devedores usam nonce.
