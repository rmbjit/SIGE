# Cenarios de teste live - v12.12.18 (Ledger incr 3: lancamentos)

Testes reproduziveis em producao ou staging. Confirmam que a criacao e a alteracao
de valor de cobrancas ficam no livro-razao, gravadas em lote no fim do pedido.

Substitua wp_ pelo prefixo real das tabelas e ESCOLA pelo id da escola.
"super admin" = utilizador com papel WordPress administrator.

Nota importante sobre o diferimento: os eventos de cobranca sao gravados no FIM do
pedido (no shutdown). Por isso, depois de gerar ou criar cobrancas, abra (ou
recarregue) o ecra de integridade num NOVO pedido para os ver. Pagamentos e ajustes
continuam imediatos.

---

## Cenario 1 - Gerar cobrancas e ve-las no ledger como um lote

1. Anotar a contagem actual de eventos da escola:

   SELECT COUNT(*) FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA;

2. Correr o gerador de cobrancas para um mes (a operacao normal que gera as propinas
   dos alunos). Deixar concluir.
3. Num novo pedido, confirmar as entradas novas:

   SELECT seq, event_type, entidade_id, montante
   FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA
     AND event_type = 'fin_criar_lancamento' ORDER BY seq DESC LIMIT 10;

   Esperado: uma entrada fin_criar_lancamento por cada cobranca criada, com o id do
   lancamento e o valor. A contagem subiu no numero de cobrancas geradas.

Criterio: cada cobranca gerada tem a sua entrada no ledger.

---

## Cenario 2 - Alterar o valor de uma cobranca deixa rasto

1. Re-gerar (ou editar) de modo a alterar o valor de uma cobranca ja existente e
   ainda nao paga (por exemplo, mudar o preco de um servico e re-gerar o mes).
2. Num novo pedido, confirmar:

   SELECT seq, event_type, entidade_id, montante
   FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA
     AND event_type = 'fin_actualizar_lancamento' ORDER BY seq DESC LIMIT 5;

   Esperado: uma entrada fin_actualizar_lancamento com o id da cobranca e o novo
   valor.

Criterio: a alteracao do valor de uma cobranca fica registada.

---

## Cenario 3 - Criar uma cobranca avulsa e ve-la

1. Criar uma cobranca avulsa para um aluno (lancamento individual), pelo fluxo
   habitual.
2. Num novo pedido, abrir Definicoes > Integridade do Ledger.

   Esperado: a escola mostra mais um evento e "Cadeia integra", com a ancora
   "Confirmada". A entrada e fin_criar_lancamento.

Criterio: a cobranca avulsa tem a sua entrada e a cadeia mantem-se integra.

---

## Cenario 4 - A cadeia continua integra apos um lote

1. Apos os cenarios anteriores, abrir Definicoes > Integridade do Ledger.

   Esperado: "Estado da cadeia" = "Cadeia integra" e "Ancora externa" = "Confirmada",
   com a contagem a reflectir cobrancas mais pagamentos mais ajustes.
2. (Opcional) Confirmar que pagamentos e cobrancas convivem na mesma cadeia:

   SELECT event_type, COUNT(*) FROM wp_sige_fin_ledger
   WHERE escola_id = ESCOLA GROUP BY event_type;

   Esperado: contagens por tipo (fin_criar_lancamento, fin_registar_pagamento,
   fin_estornar_pagamento, etc.).

Criterio: cobrancas, pagamentos e ajustes numa unica cadeia integra.

---

## Cenario 5 - A geracao e a criacao funcionam na mesma

1. Gerar cobrancas e criar cobrancas avulsas normalmente.
2. Confirmar que os valores, saldos e estados se comportam como antes. O ledger e a
   escrita em lote sao adicionais e nunca quebram a operacao.

Criterio: nenhuma diferenca funcional na geracao nem na criacao de cobrancas.

---

## Resumo

| Cenario | Prova | Resultado esperado |
|--------|-------|--------------------|
| 1 | Gerar cobrancas | Uma entrada fin_criar_lancamento por cobranca (apos o pedido) |
| 2 | Alterar valor | Entrada fin_actualizar_lancamento com o novo valor |
| 3 | Cobranca avulsa | Entrada no ledger; "Cadeia integra" |
| 4 | Verificacao | Cobrancas, pagamentos e ajustes numa cadeia integra; ancora confirmada |
| 5 | Regressao | Geracao e criacao normais, sem mudanca de calculo |

Nota: os cenarios de truncagem da cauda, adulteracao e remocao no meio estao em
LIVE-TEST-SCENARIOS-v12.12.15.md e v12.12.17.md e continuam validos.
