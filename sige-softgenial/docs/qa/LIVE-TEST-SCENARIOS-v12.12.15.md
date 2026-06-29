# Cenarios de teste live - v12.12.15 (Ledger financeiro, incremento 1)

Testes reproduziveis em producao ou staging. O ledger e um livro-razao imutavel e
a prova de adulteracao das 6 operacoes financeiras criticas. Cada evento e
encadeado por HMAC com chave dos salts; alterar, apagar no meio ou forjar uma
entrada passa a ser detectavel.

AVISO: os cenarios 3 e 4 adulteram de proposito uma linha do ledger para provar a
deteccao. Corra-os apenas em STAGING (uma copia), nunca na base de dados de
producao. Cada um inclui o passo de reposicao.

Conventions: "super admin" = um utilizador com papel WordPress administrator (nao um
papel nativo SIGE como sige_admin ou sige_admin_ti). SQL pode ser corrido em
phpMyAdmin, Adminer ou wp-cli. Substitua wp_ pelo prefixo real das tabelas e
ESCOLA pelo id da escola em teste.

---

## Cenario 0 - Actualizacao cria a tabela e o ecra

Objectivo: confirmar que a actualizacao corre sem reconfiguracao.

1. Substituir a pasta do plugin pelo conteudo do ZIP v12.12.15 e abrir o wp-admin
   uma vez (a migracao corre sozinha).
2. Confirmar que a tabela existe:

   SHOW TABLES LIKE 'wp_sige_fin_ledger';

   Esperado: uma linha (a tabela foi criada).
3. Como super admin, abrir Definicoes > Integridade do Ledger.

   Esperado: a pagina abre e, se ainda nao houve operacoes, mostra "Ainda nao ha
   eventos registados no ledger".

Criterio: a tabela existe e a pagina abre sem erro. Nenhum dado anterior foi
alterado.

---

## Cenario 1 - Uma operacao critica fica registada no ledger

Objectivo: ver um evento real entrar no livro-razao.

1. Anotar a contagem actual de eventos da escola:

   SELECT COUNT(*) FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA;

2. No painel financeiro, executar uma das operacoes criticas. Exemplos seguros:
   - Cancelar um lancamento que NAO tenha pagamento associado, com um motivo; ou
   - Estornar um pagamento existente, com um motivo.
   (Se a operacao pedir confirmacao de identidade MFA, confirme - faz parte do
   fluxo critico ja existente.)
3. Reabrir a contagem:

   SELECT seq, event_type, entidade, entidade_id, montante, actor_nome, ocorrido_em
   FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA ORDER BY seq DESC LIMIT 3;

   Esperado: aparece uma entrada nova no topo, com event_type correspondente
   (por exemplo fin_cancelar_lancamento ou fin_estornar_pagamento), o id da
   entidade, o montante e o nome de quem executou. A contagem subiu em 1.

Criterio: a operacao gerou exactamente uma entrada nova, com os dados certos.

---

## Cenario 2 - O ecra de integridade confirma "Cadeia integra"

Objectivo: validar a verificacao da cadeia pela interface.

1. Como super admin, abrir Definicoes > Integridade do Ledger.
2. Observar a linha da escola em teste.

   Esperado: a coluna Eventos mostra o numero de entradas e a coluna Estado mostra
   "Cadeia integra".

Criterio: a escola aparece com "Cadeia integra" e a contagem coincide com o
COUNT(*) do Cenario 1.

---

## Cenario 3 - Adulteracao de uma entrada e detectada (STAGING)

Objectivo: provar que alterar o conteudo de uma entrada quebra a cadeia.

1. Escolher uma entrada existente da escola (por exemplo a do meio):

   SELECT seq, montante FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA ORDER BY seq ASC;

   Anotar a seq e o montante originais de uma entrada (chamemos-lhe seq = N e
   montante = ORIGINAL).
2. Adulterar o montante dessa entrada, sem recalcular o hash (e isto que um atacante
   ao nivel da base de dados faria):

   UPDATE wp_sige_fin_ledger SET montante = 999999.99
   WHERE escola_id = ESCOLA AND seq = N;

3. Como super admin, reabrir Definicoes > Integridade do Ledger.

   Esperado: a escola passa a mostrar "QUEBRA na seq N (hash nao corresponde
   (conteudo adulterado))".
4. Repor o valor original para nao deixar a copia corrompida:

   UPDATE wp_sige_fin_ledger SET montante = ORIGINAL
   WHERE escola_id = ESCOLA AND seq = N;

   Reabrir o ecra: deve voltar a "Cadeia integra".

Criterio: a adulteracao foi assinalada na seq exacta e, reposto o valor, a cadeia
volta a integra.

---

## Cenario 4 - Remocao de uma entrada no meio e detectada (STAGING)

Objectivo: provar que apagar uma entrada deixa rasto.

1. Garantir pelo menos 3 entradas na escola (repetir o Cenario 1 se preciso) e
   listar as seq:

   SELECT seq FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA ORDER BY seq ASC;

2. Antes de apagar, guardar a linha do meio para a poder repor:

   SELECT * FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA AND seq = MEIO;

   (Copie todos os valores da linha.)
3. Apagar a entrada do meio:

   DELETE FROM wp_sige_fin_ledger WHERE escola_id = ESCOLA AND seq = MEIO;

4. Como super admin, reabrir Definicoes > Integridade do Ledger.

   Esperado: a escola passa a mostrar "QUEBRA na seq X (salto de sequencia ...)",
   onde X e a primeira seq apos a que foi apagada.
5. Repor a linha apagada com um INSERT usando exactamente os valores guardados no
   passo 2 (mesma seq, mesmo hash, mesmo prev_hash, mesmo conteudo). Reabrir o
   ecra: deve voltar a "Cadeia integra".

Criterio: a remocao foi assinalada como salto de sequencia e, reposta a linha
identica, a cadeia volta a integra.

Nota honesta: apagar as entradas MAIS RECENTES de forma contigua (a cauda) nao
deixa salto de sequencia e nao e detectado por esta cadeia simples; a deteccao de
truncagem da cauda chega com a ancoragem externa, num incremento posterior.

---

## Cenario 5 - So o super admin ve o ecra de integridade

Objectivo: confirmar a restricao de acesso.

1. Iniciar sessao com um utilizador de papel nativo SIGE (por exemplo sige_admin ou
   sige_admin_ti), que NAO seja administrator WordPress.
2. Confirmar que o item Definicoes > Integridade do Ledger nao aparece no menu. Se
   tentar abrir o endereco directo da pagina (admin.php?page=sige-ledger-integridade
   ou options-general...), recebe "Acesso restrito ao administrador WordPress"
   (403).
3. Iniciar sessao como super admin: o item aparece e abre normalmente.

Criterio: apenas o administrador WordPress real acede ao ecra.

---

## Cenario 6 - As operacoes financeiras continuam normais

Objectivo: confirmar que o ledger e adicional e nao altera o comportamento.

1. Executar normalmente as operacoes do dia a dia (lancamentos, pagamentos,
   cancelamentos, estornos).
2. Confirmar que os saldos, recibos e estados se comportam exactamente como na
   versao anterior. O ledger apenas regista por baixo; nenhum calculo muda.

Criterio: nenhuma diferenca funcional face a v12.12.14, com o registo imutavel a
acontecer em paralelo.

---

## Resumo

| Cenario | Prova | Resultado esperado |
|--------|-------|--------------------|
| 0 | Actualizacao | Tabela criada, ecra abre |
| 1 | Operacao critica | Uma entrada nova no ledger |
| 2 | Verificacao | "Cadeia integra" |
| 3 | Adulteracao (staging) | "QUEBRA na seq N (hash)" e reposicao |
| 4 | Remocao no meio (staging) | "QUEBRA na seq X (sequencia)" e reposicao |
| 5 | Acesso | So super admin ve o ecra |
| 6 | Regressao | Operacoes normais, sem mudanca de calculo |
