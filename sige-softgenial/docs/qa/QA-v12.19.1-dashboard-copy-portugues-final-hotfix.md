# QA - v12.19.1 Dashboard Copy & Português Final Hotfix

## Testes obrigatórios

- PHP lint integral.
- tools/smoke-release-gate.php.
- tools/run-gates.php.
- tools/check-v12-19-1-dashboard-copy-contract.php.
- tools/smoke-v12-19-1-dashboard-copy.php.
- tools/check-v12-19-1-no-sensitive-regression.php.
- tools/check-v12-19-1-package-manifest.php.

## Validação humana em staging

1. Abrir Painel Principal.
2. Confirmar que o bloco aparece com português acentuado.
3. Confirmar que o copy parece orientação de produto para utilizador final.
4. Confirmar que não há erro fatal, tela branca, loop ou #038;view.
5. Confirmar mobile funcional.
6. Confirmar sem regressão em financeiro, académico, permissões, Portaria ou Alunos.
