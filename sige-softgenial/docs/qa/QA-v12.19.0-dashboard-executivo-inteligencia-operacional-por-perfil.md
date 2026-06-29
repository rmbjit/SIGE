# QA - v12.19.0 Dashboard Executivo & Inteligencia Operacional por Perfil

## Testes locais obrigatorios
- PHP lint integral.
- tools/smoke-release-gate.php.
- tools/run-gates.php.
- tools/check-v12-19-0-dashboard-intelligence-contract.php.
- tools/smoke-v12-19-0-dashboard-intelligence.php.
- tools/check-v12-19-0-no-sensitive-regression.php.
- tools/check-v12-19-0-package-manifest.php.
- unzip -t do pacote final.

## Testes em staging
1. Abrir Painel Principal como administrador ou perfil de gestao.
2. Confirmar que o bloco de inteligencia por perfil aparece depois do hero do dashboard.
3. Confirmar que os atalhos respeitam o perfil logado.
4. Confirmar que o bloco nao aparece em Alunos, Financeiro, Academico, Portaria ou views internas.
5. Confirmar mobile funcional.
6. Confirmar que a faixa Fluxo seguro continua funcional nas views v12.18.0.
7. Confirmar sem erro fatal, sem tela branca, sem loop e sem #038;view.
8. Confirmar sem regressao em financeiro, academico, permissoes, Portaria e Alunos.

## Criterios de aceitacao
- Dashboard mostra inteligencia por perfil sem atrapalhar.
- Atalhos visiveis respeitam permissoes reais.
- Mobile continua legivel.
- Nenhum modulo sensivel sofre regressao.
- Nenhuma regra financeira ou academica muda.
