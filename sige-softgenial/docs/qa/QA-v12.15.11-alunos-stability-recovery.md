# QA - v12.15.11 Alunos Stability Recovery

## Gates executados
- php lint.
- node --check de `assets/views/alunos-design-pro.js`.
- smoke específico `smoke-alunos-stability-recovery-v12-15-11.php`.
- scope guard `check-alunos-stability-scope-v12-15-11.php`.
- CSP checks.
- run-gates.

## QA browser obrigatório em staging
1. Abrir Alunos e Matrículas e confirmar carregamento imediato.
2. Abrir Registar Aluno.
3. Confirmar que modal fica acima da sidebar.
4. Clicar tabs do modal.
5. Cancelar/fechar.
6. Abrir Importar Lista.
7. Abrir Ficha 360º.
8. Abrir três pontinhos e confirmar menu vertical clicável.
9. Confirmar scroll da área principal.
