# LIVE-TEST: Pesquisa Global (P3, v12.15.21)

Confirma a caixa de pesquisa unica da barra de topo: resultados agrupados,
permissoes, multi-tenant e deep-links.

## Cenario 1 - Pesquisar um aluno

1. Em qualquer ecra da aplicacao, reparar na caixa de pesquisa no topo (entre o
   titulo e o ano lectivo). Em ecra pequeno (telemovel) a caixa esconde-se.
2. Escrever parte do nome de um aluno (pelo menos 2 letras).
   **Esperado:** aparece um painel com o grupo "Alunos" e ate 6 resultados, cada
   um com nome e (numero de processo / turma). Os totais aparecem enquanto se
   escreve, com pequeno atraso (debounce).
3. Clicar num aluno.
   **Esperado:** abre a lista de alunos ja filtrada por esse aluno (pelo numero
   de processo).

## Cenario 2 - Pesquisar um recibo

1. Escrever um numero de recibo (ou parte) ou o nome do aluno do recibo.
   **Esperado:** grupo "Recibos" com o numero, aluno, valor e data. A data
   respeita a data efectiva (igual ao recibo).
2. Clicar num recibo.
   **Esperado:** abre o recibo (janela de impressao).

## Cenario 3 - Pesquisar uma turma

1. Escrever o nome ou a classe de uma turma.
   **Esperado:** grupo "Turmas" com o nome, classe e numero de alunos. Clicar
   abre a lista de turmas.

## Cenario 4 - Teclado

1. Com resultados no painel, usar as setas para baixo/cima para mover o
   destaque, Enter para abrir o destacado, Esc para fechar.
2. Com o foco fora de qualquer campo, premir `/`.
   **Esperado:** o foco salta para a caixa de pesquisa.

## Cenario 5 - Permissoes

1. Entrar com um papel que so ve alunos (sem financeiro).
   **Esperado:** a pesquisa devolve apenas o grupo "Alunos"; nunca aparecem
   recibos. Um papel sem nenhum ambito de pesquisa nem ve a caixa.

## Cenario 6 - Isolamento por escola (multi-tenant)

1. Confirmar que os resultados sao apenas da escola activa (nenhum aluno, turma
   ou recibo de outra escola aparece).

## Gates

1. `php tools/run-gates.php`.
   **Esperado:** `Pesquisa Global (v12.15.21)` VERDE, release gate VERDE para
   12.15.21, e o conjunto de vermelhos pre-existentes inalterado (drift
   2439/7527/478 byte a byte).

---

## Adenda v12.15.23 - Planos e Despesas

### Cenario 7 - Recibos separados de Planos

1. Pesquisar um aluno que tenha recibos (REC-) e planos (PLN-).
   **Esperado:** os REC- aparecem no grupo "Recibos" e os PLN- no grupo "Planos
   de pagamento", separados. Um PLN- nunca aparece em "Recibos".

### Cenario 8 - Planos de pagamento

1. Pesquisar por um numero de plano (ex. "PLN-2026") ou o nome do aluno.
   **Esperado:** grupo "Planos de pagamento" com numero, aluno, total e data.
2. Clicar num plano.
   **Esperado:** abre o modulo Planos de Pagamento.

### Cenario 9 - Despesas

1. Pesquisar por parte da descricao, fornecedor ou categoria de uma despesa.
   **Esperado:** grupo "Despesas" com descricao, fornecedor, valor e data.
2. Clicar numa despesa.
   **Esperado:** abre o modulo Despesas.

### Cenario 10 - Permissoes dos novos grupos

1. Papel so com financeiro.despesas_ver: a pesquisa devolve "Despesas"; nao
   devolve planos nem recibos. A caixa de pesquisa continua visivel.
2. Papel so com financeiro.planos_ver: devolve "Planos de pagamento"; nao
   devolve despesas nem recibos.
