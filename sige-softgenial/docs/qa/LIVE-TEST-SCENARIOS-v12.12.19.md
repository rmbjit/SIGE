# Cenarios de teste live - v12.12.19 (Ledger incr 4: despesas, creditos, fechos)

Testes reproduziveis em producao ou staging. Confirmam que despesas, creditos e
fechos de caixa ficam no livro-razao. Com este incremento, a cobertura financeira do
ledger fica completa.

Substitua wp_ pelo prefixo real das tabelas e ESCOLA pelo id da escola.
"super admin" = utilizador com papel WordPress administrator.

---

## Cenario 1 - Registar uma despesa deixa rasto

1. Anotar a contagem actual de eventos da escola:

   SELECT COUNT(*) FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA;

2. Registar uma despesa (categoria, descricao, valor), pelo fluxo habitual.
3. Confirmar a entrada nova:

   SELECT seq, event_type, entidade_id, montante
   FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA
     AND event_type = 'fin_criar_despesa' ORDER BY seq DESC LIMIT 1;

   Esperado: uma entrada fin_criar_despesa com o id da despesa e o valor.

Criterio: a despesa criada tem a sua entrada no ledger.

---

## Cenario 2 - Mudar o estado de uma despesa deixa rasto

1. Aprovar ou marcar como paga (ou cancelar) a despesa do cenario 1, pelo fluxo
   habitual.
2. Confirmar:

   SELECT seq, event_type, entidade_id, payload
   FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA
     AND event_type = 'fin_despesa_transitar' ORDER BY seq DESC LIMIT 1;

   Esperado: uma entrada fin_despesa_transitar com o id da despesa e, no payload, o
   estado de origem e de destino (por exemplo de registado para pago).

Criterio: a mudanca de estado da despesa fica registada.

---

## Cenario 3 - Criar um credito deixa rasto

1. Provocar a criacao de um credito a favor de um aluno (por exemplo, um pagamento
   com excedente gera um credito pendente, ou criar um credito pelo fluxo proprio).
2. Confirmar:

   SELECT seq, event_type, entidade_id, montante
   FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA
     AND event_type = 'fin_criar_credito' ORDER BY seq DESC LIMIT 1;

   Esperado: uma entrada fin_criar_credito com o id do credito e o valor.

Criterio: o credito criado tem a sua entrada no ledger.

---

## Cenario 4 - Fechar e reabrir um turno deixam rasto

1. Fechar o turno de caixa do dia, pelo fluxo habitual.
2. Confirmar:

   SELECT seq, event_type, entidade_id, montante
   FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA
     AND event_type = 'fin_fechar_turno' ORDER BY seq DESC LIMIT 1;

   Esperado: uma entrada fin_fechar_turno com o id do fecho e o total liquido.
3. Como Director, reabrir esse turno com um motivo.
4. Confirmar:

   SELECT seq, event_type, entidade_id, payload
   FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA
     AND event_type = 'fin_reabrir_turno' ORDER BY seq DESC LIMIT 1;

   Esperado: uma entrada fin_reabrir_turno com o id do fecho e, no payload, o motivo.

Criterio: o fecho e a reabertura de turno ficam registados (a reabertura e um evento
sensivel, agora rastreado).

---

## Cenario 5 - A cadeia continua integra com toda a cobertura

1. Abrir Definicoes > Integridade do Ledger.

   Esperado: "Cadeia integra" e ancora "Confirmada".
2. (Opcional) Ver a cobertura financeira completa por tipo de evento:

   SELECT event_type, COUNT(*) FROM wp_sige_fin_ledger
   WHERE escola_id = ESCOLA GROUP BY event_type ORDER BY event_type;

   Esperado: contagens para ajustes, pagamentos, cobrancas, despesas, creditos e
   fechos (por exemplo fin_registar_pagamento, fin_criar_lancamento,
   fin_criar_despesa, fin_criar_credito, fin_fechar_turno, etc.).

Criterio: todos os movimentos financeiros numa unica cadeia integra.

---

## Cenario 6 - Tudo funciona na mesma

1. Registar despesas, criar creditos, fechar e reabrir turnos normalmente.
2. Confirmar que os valores, totais e estados se comportam como antes. O ledger e
   adicional e nunca quebra a operacao.

Criterio: nenhuma diferenca funcional.

---

## Resumo

| Cenario | Prova | Resultado esperado |
|--------|-------|--------------------|
| 1 | Despesa criada | Entrada fin_criar_despesa com valor |
| 2 | Despesa transitada | Entrada fin_despesa_transitar com de/para |
| 3 | Credito criado | Entrada fin_criar_credito com valor |
| 4 | Fechar e reabrir turno | Entradas fin_fechar_turno e fin_reabrir_turno |
| 5 | Verificacao | Cobertura financeira completa numa cadeia integra; ancora confirmada |
| 6 | Regressao | Operacoes normais, sem mudanca de calculo |

Nota: os cenarios de truncagem da cauda, adulteracao e remocao no meio estao em
LIVE-TEST-SCENARIOS-v12.12.15.md e v12.12.17.md e continuam validos.
