# FINAL_RELEASE-v12.16.0

## Estado

Release final preparada apos aprovacao humana em staging.

## Versao

- Plugin header: 12.16.0
- SIGE_VERSION: 12.16.0
- BUILD.json: 12.16.0

## Escopo entregue

- Mapa operacional por perfil.
- Checklists operacionais no dashboard.
- Faixa Comece aqui no shell.
- Fluxos Guiados para operacoes criticas.
- Hotfix Professor/Minhas Turmas contra URL #038;view.
- Hotfix visual do header mobile.
- Documentacao, matriz, riscos e gates da fase.

## Fora do escopo

- Alteracao de formulas financeiras.
- Alteracao de formulas academicas.
- Migracao de base de dados.
- Refactor profundo de financeiro, alunos, permissoes ou security kernel.
- Mudanca comercial de produto.

## Evidencia local final

- PHP lint: 490/490.
- Gates: 136/136.
- Release gate: 54/54.
- Ficheiros P0 protegidos: hashes iguais a baseline.

## Validacao humana

O staging foi validado e aprovado pelo utilizador apos o RC7.

## Condicoes de instalacao

1. Fazer backup da pasta actual do plugin.
2. Instalar o ZIP final.
3. Confirmar que o painel mostra a versao 12.16.0.
4. Validar Professor, Minhas Turmas e header mobile.
5. Validar Tesouraria, Secretaria, Direccao, Academico e Portaria em smoke real.

## Condicoes de rollback imediato

Executar rollback se aparecer:

- erro fatal PHP;
- loop de carregamento;
- permissao indevida;
- falha em pagamento, recibo, divida ou extracto;
- falha em notas, pautas ou boletins;
- bloqueio de acesso a portaria;
- perda de navegacao mobile critica.
