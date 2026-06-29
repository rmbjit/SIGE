# TESTES LIVE - v12.11.9.102 (Calendário de Devedores)

Fazer em teste.softgenial.edu.mz. Tempo total: ~7 minutos.
F12 > Console aberto: zero linhas vermelhas.

## 1. O teste que importa: os números batem (3 min)
1. Financeiro > Devedores SEM filtros: a faixa mostra os 12 meses do
   ano lectivo, com calor (cinza = 0; vermelho = pior mês) e o mês
   corrente com anel;
2. Clicar num mês com devedores: a lista filtra e o NÚMERO DO TILE é
   exactamente igual ao número de alunos listados; o tile fica com
   contorno navy e aparece o tile "Ano inteiro";
3. "Ano inteiro": filtro limpa e a lista volta ao total;
4. Tooltip de um tile: mostra "X devedor(es) · Y MT em aberto".

## 2. Coerência com os outros filtros (2 min)
1. Filtrar uma TURMA: o calendário recalcula só para essa turma
   (clicar num mês continua a bater com a lista);
2. Situação do aluno (activos/transferidos): idem.

## 3. Robustez e inocuidade (2 min)
1. Mobile/janela estreita: a grelha quebra em 4 colunas e continua
   legível;
2. O resto do ecrã (cards, régua de cobrança, envio em massa com a
   checkbox) continua exactamente como na v101.

## Critério de aprovação
1.2 verde (tile = lista) é o coração da feature. Tudo verde + console
limpo = v102 validada; voltamos ao UX-8 (sprint-vassoura dos diálogos).
