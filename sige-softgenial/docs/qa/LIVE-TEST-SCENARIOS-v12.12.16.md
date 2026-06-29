# Cenarios de teste live - v12.12.16 (Correccao: tabela do ledger e ecra resiliente)

Testes reproduziveis que confirmam que o erro "Table '..._sige_fin_ledger' doesn't
exist" desaparece: a tabela passa a ser criada na actualizacao e o ecra degrada com
elegancia quando a tabela ainda nao existe.

Substitua wp_ pelo prefixo real das tabelas e ESCOLA pelo id da escola em teste.
"super admin" = utilizador com papel WordPress administrator.

---

## Cenario 1 - A actualizacao cria a tabela (o erro desaparece)

Este e o cenario que reproduz e fecha o problema reportado.

1. Partir de uma instalacao onde a tabela ainda nao existe (por exemplo a que deu o
   erro na v12.12.15). Confirmar a ausencia:

   SHOW TABLES LIKE 'wp_sige_fin_ledger';

   Esperado: zero linhas (a tabela nao existe).
2. Substituir a pasta do plugin pelo conteudo do ZIP v12.12.16.
3. Abrir o wp-admin COMO ADMINISTRADOR (qualquer pagina do painel). Isto dispara a
   migracao automatica (admin_init).
4. Confirmar que a tabela passou a existir:

   SHOW TABLES LIKE 'wp_sige_fin_ledger';

   Esperado: uma linha (a tabela foi criada).
5. Abrir Definicoes > Integridade do Ledger.

   Esperado: a pagina abre SEM o erro da base de dados. Antes de qualquer operacao,
   mostra "Ainda nao ha eventos registados no ledger".

Criterio: a tabela e criada so por actualizacao de ficheiros (sem reactivar) e o ecra
abre limpo.

---

## Cenario 2 - O ecra ja nao mostra o erro cru sem tabela (STAGING)

Confirma a resiliencia, mesmo que a tabela falte por algum motivo.

1. Em STAGING, apagar a tabela de proposito para simular a ausencia:

   DROP TABLE wp_sige_fin_ledger;

2. Em vez de abrir o painel como administrador (que a recriaria), abrir directamente
   o ecra de integridade. Esperado: em vez do erro cru, aparece a mensagem
   "A tabela do ledger ainda nao existe nesta instalacao. Abra o painel de
   administracao como administrador para correr a actualizacao automatica da base de
   dados, ou reactive o plugin...".
3. Abrir qualquer pagina do painel como administrador. A migracao recria a tabela.
   Reabrir o ecra: volta ao estado normal ("Ainda nao ha eventos" ou a lista por
   escola).

Criterio: sem tabela, o ecra mostra a mensagem clara, nunca o erro cru.

---

## Cenario 3 - O fluxo completo do ledger funciona

Igual a v12.12.15, agora sobre a tabela ja criada.

1. Executar uma operacao critica (cancelar um lancamento sem pagamento, ou estornar
   um pagamento), com motivo. Confirmar a entrada:

   SELECT seq, event_type, entidade_id, montante, actor_nome
   FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA ORDER BY seq DESC LIMIT 1;

   Esperado: uma entrada nova com o event_type correspondente.
2. Abrir Definicoes > Integridade do Ledger. Esperado: a escola aparece com o numero
   de eventos e "Cadeia integra".

Criterio: operacao gera entrada e a cadeia verifica como integra.

---

## Cenario 4 - As operacoes financeiras continuam normais

1. Executar lancamentos, pagamentos, cancelamentos e estornos normais.
2. Confirmar que saldos, recibos e estados se comportam como antes. Nenhum calculo
   muda; o ledger apenas regista por baixo.

Criterio: nenhuma diferenca funcional face a v12.12.15.

---

## Resumo

| Cenario | Prova | Resultado esperado |
|--------|-------|--------------------|
| 1 | Actualizacao | Tabela criada sem reactivar; ecra abre sem erro |
| 2 | Resiliencia (staging) | Sem tabela, mensagem clara em vez do erro cru |
| 3 | Fluxo do ledger | Operacao gera entrada; "Cadeia integra" |
| 4 | Regressao | Operacoes normais, sem mudanca de calculo |

Nota: os cenarios de adulteracao e remocao (provar a deteccao da cadeia) estao em
LIVE-TEST-SCENARIOS-v12.12.15.md e continuam validos.
