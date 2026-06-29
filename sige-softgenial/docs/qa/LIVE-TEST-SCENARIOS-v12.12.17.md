# Cenarios de teste live - v12.12.17 (Ledger incr 2: pagamentos e ancora externa)

Testes reproduziveis em producao ou staging. Confirmam que os pagamentos ficam no
livro-razao e que a ancora externa deteta a truncagem das entradas mais recentes.

AVISO: os cenarios 3 e 4 adulteram de proposito o ledger ou a ancora para provar a
deteccao. Corra-os apenas em STAGING. Cada um inclui a reposicao.

Substitua wp_ pelo prefixo real das tabelas e ESCOLA pelo id da escola.
"super admin" = utilizador com papel WordPress administrator.

---

## Cenario 1 - Um pagamento fica no ledger

1. Anotar a contagem actual de eventos da escola:

   SELECT COUNT(*) FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA;

2. Registar um pagamento normal (qualquer metodo: numerario, M-Pesa, e-Mola), pelo
   fluxo habitual do painel financeiro.
3. Confirmar a entrada nova:

   SELECT seq, event_type, entidade, entidade_id, montante, actor_nome
   FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA ORDER BY seq DESC LIMIT 1;

   Esperado: uma entrada com event_type = fin_registar_pagamento, o id do pagamento,
   o montante aplicado e o nome de quem recebeu. A contagem subiu em 1.

Nota: o pagamento anual usa o mesmo registo por baixo, por isso tambem gera uma
entrada fin_registar_pagamento.

Criterio: o pagamento gerou exactamente uma entrada nova no ledger.

---

## Cenario 2 - O ecra mostra cadeia integra e ancora confirmada

1. Como super admin, abrir Definicoes > Integridade do Ledger.
2. Observar a linha da escola.

   Esperado: "Estado da cadeia" = "Cadeia integra" e "Ancora externa" = "Confirmada".

3. Confirmar que a ancora existe no disco (fora da base de dados):
   wp-content/uploads/sige-private/ledger/anchor-ESCOLA.json
   Deve conter a ultima seq e o ultimo hash.

Criterio: cadeia integra e ancora confirmada; o ficheiro de ancora existe.

---

## Cenario 3 - A ancora deteta truncagem da cauda (STAGING)

Este e o cenario que prova o que o incremento 1 nao detectava.

1. Garantir pelo menos 3 entradas na escola. Listar as seq:

   SELECT seq FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA ORDER BY seq ASC;

   Anote a ultima seq (chamemos-lhe U). Guarde a linha U para a poder repor:

   SELECT * FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA AND seq = U;

2. Apagar a ENTRADA MAIS RECENTE (a da seq U) so na base de dados (a ancora fica
   intacta, porque o atacante so tem acesso a base de dados):

   DELETE FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA AND seq = U;

3. Como super admin, reabrir Definicoes > Integridade do Ledger.

   Esperado: "QUEBRA na seq U (truncagem da cauda: a ancora regista ate a seq U mas a
   base de dados so tem ate U-1)". A coluna "Ancora externa" mostra "A frente da BD".

   Repare: sem ancora, apagar a entrada mais recente NAO deixava salto de sequencia e
   passava despercebido. A ancora torna isto detectavel.
4. Repor a linha U com um INSERT usando exactamente os valores guardados no passo 1
   (mesma seq, mesmo hash, mesmo prev_hash, mesmo conteudo). Reabrir o ecra: volta a
   "Cadeia integra" e ancora "Confirmada".

Criterio: a truncagem da cauda foi assinalada e, reposta a linha, a cadeia volta a
integra.

---

## Cenario 4 - A ancora deteta adulteracao da cabeca (STAGING)

1. Anotar o hash actual da cabeca (ultima seq) na ancora:
   abrir wp-content/uploads/sige-private/ledger/anchor-ESCOLA.json e guardar o campo
   hash.
2. Alterar o campo hash da ancora para um valor diferente (simular adulteracao da
   ancora ou divergencia da cabeca).
3. Reabrir Definicoes > Integridade do Ledger.

   Esperado: a coluna "Ancora externa" mostra "Divergente" e o estado assinala que a
   ancora nao corresponde a cabeca.
4. Repor o hash original no ficheiro de ancora (ou registar um novo pagamento, que
   reescreve a ancora correctamente). O ecra volta ao normal.

Criterio: a divergencia entre a ancora e a cabeca e assinalada.

---

## Cenario 5 - O pagamento funciona na mesma

1. Fazer pagamentos normais (numerario, M-Pesa, e-Mola, planos, anual).
2. Confirmar que recibos, saldos e estados se comportam como antes. O ledger e a
   ancora sao adicionais e nunca quebram o pagamento.

Criterio: nenhuma diferenca funcional; o registo imutavel acontece em paralelo.

---

## Resumo

| Cenario | Prova | Resultado esperado |
|--------|-------|--------------------|
| 1 | Pagamento | Uma entrada fin_registar_pagamento no ledger |
| 2 | Verificacao | "Cadeia integra" e ancora "Confirmada" |
| 3 | Truncagem da cauda (staging) | "QUEBRA ... truncagem da cauda"; ancora "A frente da BD"; reposicao |
| 4 | Ancora divergente (staging) | Ancora "Divergente"; reposicao |
| 5 | Regressao | Pagamentos normais, sem mudanca de calculo |

Nota: os cenarios de adulteracao e remocao no meio estao em
LIVE-TEST-SCENARIOS-v12.12.15.md e continuam validos.

Backups: inclua a pasta wp-content/uploads nos backups, para preservar a ancora
(anchor-*.json) junto com o ledger.
