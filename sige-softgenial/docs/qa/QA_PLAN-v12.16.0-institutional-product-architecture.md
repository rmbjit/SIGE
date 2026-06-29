# Plano de QA - v12.16.0 - Institutional Product Architecture & Operational UX Hardening

## Objectivo

Garantir que a evolucao de produto melhora rotina, clareza e confianca sem regressoes em areas criticas.

## Validações CLI obrigatórias

1. `php -l` em todos os ficheiros PHP.
2. `php tools/run-gates.php` completo ou por intervalos oficiais quando o ambiente limitar execucao unica.
3. Gate de governanca `tools/check-v12-16-0-governance.php`.
4. Gates RC1 a RC5 da v12.16.0.
5. Gates financeiros existentes sempre que o bloco tocar shell/assets de areas financeiras.
6. Gates de pesquisa global sempre que a topbar, shell ou assets globais forem alterados.
7. Gates de alunos sempre que `alunos_lista.php` ou assets de alunos forem alterados.
8. Gates de portaria sempre que a view ou assets de portaria forem alterados.
9. Gates de permissoes sempre que menu, shell, mapa de views ou matriz forem alterados.
10. Release gate antes de qualquer ZIP final.

## Testes por domínio

| Dominio | Cenarios minimos | Tipo |
|---|---|---|
| Financeiro | pagamento parcial, pagamento total, recibo, extracto, devedores, transporte, desconto, multa, anulacao, fecho de caixa | Gate + staging |
| Academico | lancamento de notas, pauta, aprovacao, boletim, DEC, pauta final, encerramento | Gate + staging |
| Alunos | listagem, pesquisa, filtros, ficha 360, matricula, conta de aluno, modal e mobile | Gate + staging |
| Portaria | leitura, autorizado, bloqueado, motivo, nova leitura, historico minimo | Gate + staging |
| Permissoes | director, tesouraria, secretaria, professor, pedagogico, guarda, encarregado, admin tecnico | Gate + staging |
| Pesquisa global | alunos, turmas, recibos, planos, despesas, sem permissao, sem escola | Gate + staging |
| Mobile | menu, bottom nav, tabelas, modais, formularios, portaria | Staging |

## QA manual obrigatório em staging no RC final

1. Login como Direccao.
2. Login como Tesouraria.
3. Login como Secretaria.
4. Login como Professor.
5. Login como Direccao Pedagogica.
6. Login como Guarda.
7. Login como Encarregado/Aluno.
8. Validar que cada perfil ve apenas o que deve ver.
9. Validar que tarefas frequentes estao mais faceis de localizar.
10. Validar que nenhum fluxo financeiro ou academico mudou regra.
11. Validar em mobile: shell, dashboard, faixa Comece aqui, Checklist Operacional, Fluxos Guiados e Portaria.
12. Registar evidencias de aceite ou rejeicao por perfil.

## Critérios de falha

1. Qualquer P0/P1 aberto bloqueia ZIP.
2. Qualquer gate vermelho bloqueia RC.
3. Qualquer erro de lint bloqueia RC.
4. Qualquer mudanca de formula sem autorizacao bloqueia fase.
5. Qualquer acesso indevido por perfil bloqueia fase.
6. Qualquer alteracao de schema sem aprovacao bloqueia fase.
7. Qualquer divergencia entre documentacao RC e implementacao bloqueia RC final.

## Testes não executáveis neste ambiente

Este ambiente nao executa browser autenticado em staging nem confirma dados reais da escola. Esses testes devem ser feitos em staging e registados na validacao humana final.
