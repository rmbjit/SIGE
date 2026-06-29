# LIVE-TEST: data efectiva no recibo (v12.15.20)

Confirma que o recibo passa a mostrar a data efectiva como data do pagamento,
em vez da data de hoje, coerente com o extracto.

## Pre-requisito

Uma data passada com caixa ABERTA (ex. ontem). Se a caixa do dia estiver
fechada, reabrir primeiro (ou usar outro dia aberto).

## Cenario 1 - Recibo individual com data efectiva passada

1. Academico/Financeiro > Registar Pagamento. Escolher um aluno com divida.
2. No cartao "Data efectiva do pagamento", escolher uma data passada com caixa
   aberta (deve aparecer "Caixa aberta nesta data - pode registar").
3. Seleccionar a divida e registar o pagamento.
4. Abrir o recibo gerado.
   **Esperado:** o campo DATA DO PAGAMENTO mostra a data efectiva escolhida (a
   data passada), e por baixo, em pequeno, "(registado em DD/MM/YYYY HH:MM)" com
   a data de hoje. NAO mostra hoje como data do pagamento.

## Cenario 2 - Pagamento de hoje (sem data efectiva)

1. Registar um pagamento deixando a data efectiva em hoje.
2. Abrir o recibo.
   **Esperado:** DATA DO PAGAMENTO mostra hoje com hora, sem a nota "(registado
   em ...)". Comportamento igual ao de antes.

## Cenario 3 - Coerencia com o extracto

1. Para o pagamento do Cenario 1, abrir o Extracto do dia da data efectiva.
   **Esperado:** o pagamento aparece nesse dia (data efectiva), tal como no
   recibo. As duas datas coincidem.

## Cenario 4 - Recibo consolidado (familia / multi-lancamento)

1. Registar um pagamento consolidado (varios lancamentos ou familia) com data
   efectiva passada.
2. Abrir o recibo consolidado.
   **Esperado:** a coluna de data mostra a data efectiva por linha; nas linhas
   em que difere do registo aparece uma marca pequena "ef." e, ao passar o rato,
   o title indica "Registado em ...".

## Gates

1. `php tools/run-gates.php`.
   **Esperado:** `Recibo respeita data efectiva (v12.15.20)` VERDE, os gates
   financeiros (Ledger, Formula Integrity, Core Design) VERDES, release gate
   VERDE para 12.15.20, e o conjunto de vermelhos pre-existentes inalterado.
