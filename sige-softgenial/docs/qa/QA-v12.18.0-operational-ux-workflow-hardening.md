# QA - v12.18.0 Operational UX & Workflow Hardening

## Gates obrigatórios
- `php tools/check-v12-18-0-operational-workflow-contract.php`
- `php tools/smoke-v12-18-0-operational-workflow.php`
- `php tools/check-v12-18-0-no-sensitive-regression.php`
- `php tools/check-v12-18-0-package-manifest.php`
- `php tools/run-gates.php`
- `php tools/smoke-release-gate.php`

## Validação manual em staging
1. Abrir Alunos.
2. Abrir Pagamentos.
3. Abrir Devedores.
4. Abrir Gerador financeiro.
5. Abrir Extractos.
6. Abrir Notas.
7. Abrir Minhas Turmas.
8. Abrir Aprovação de Notas.
9. Abrir Pautas.
10. Abrir Portaria.
11. Abrir Comunicação.
12. Abrir Turmas.
13. Confirmar que aparece orientação curta de fluxo seguro.
14. Fechar a orientação e confirmar que a página continua funcional.
15. Reabrir noutra sessão para confirmar que o fecho não grava dado permanente.

## Regressão obrigatória
- Financeiro: pagamentos, devedores e extractos abrem sem erro.
- Académico: notas, pautas e minhas turmas abrem sem erro.
- Alunos: listagem, pesquisa, edição e Ficha 360º preservadas.
- Portaria: leitura/validação preservada.
- Permissões: perfil não vê rotas sem autorização.
- Mobile: header sem regressão e faixa compacta.

## Critério de aprovação
Sem erro fatal, sem tela branca, sem loop, sem `#038;view`, mobile funcional, sem regressão em financeiro, académico, permissões, portaria ou Alunos.
