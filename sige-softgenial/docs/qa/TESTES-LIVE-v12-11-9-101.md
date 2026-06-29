# TESTES LIVE - v12.11.9.101 (Sprint UX-7)

Fazer em teste.softgenial.edu.mz. Tempo total: ~7 minutos.
F12 > Console aberto: zero linhas vermelhas.

## 1. Enturmação com nome próprio (3 min)
1. Turmas > Enturmação de uma turma > "x" num aluno: modal "Remover da
   turma" com o NOME do aluno e a nota de que a matrícula se mantém;
   Esc/Cancelar não remove;
2. Confirmar: toast verde "[Nome] removido da turma." e a lista
   actualiza; re-enturmar pelo "+": toast "Aluno enturmado.";
3. Turma esvaziada de propósito (se fácil): aparece "A turma ainda
   está vazia." (já existia; agora protegido).

## 2. Circulares (2 min)
1. Escopo "turma" sem escolher turma > Pré-visualizar: toast laranja;
2. Escolher turma > Pré-visualizar: resumo com contagem; o botão
   Enviar SÓ destrava com a checkbox "Confirmo o envio..." marcada
   (comportamento de sempre, agora cadeado);
3. Enviar uma circular curta de teste: segue para a fila como antes.

## 3. Jardim Diário (2 min)
Botões "Carregar" e os dois "Guardar" violeta com o visual do kit
(violeta mantido); guardar um diário funciona como sempre.

## Critério de aprovação
Tudo verde + console limpo = v101 validada; UX-8 proposto: SPRINT-
VASSOURA dos 11 alert + 5 confirm restantes em todo o sistema +
triagem AO90 (dashboard, alunos_lista), fechando com invariantes
GLOBAIS de extinção como o do prompt().
