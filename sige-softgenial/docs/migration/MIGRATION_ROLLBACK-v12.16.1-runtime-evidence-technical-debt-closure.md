# MIGRATION AND ROLLBACK v12.16.1 - Runtime Evidence & Technical Debt Closure

## Natureza da fase
Esta fase nao cria migração de base de dados, nao altera schema, nao escreve dados e nao altera regras de negócio. O risco operacional principal esta no shell e no empacotamento.

## Baseline de retorno
- ZIP de retorno: sige-softgenial-v12_16_0-final.
- SHA256 de origem: f02cd9260f33bfc454b40bf5fe4b4629b97d147ac70eee158b0851283e53ff2a.

## Condicoes de rollback imediato
- Erro fatal em qualquer perfil.
- Professor volta a cair em dashboard ou loop.
- Portaria deixa de abrir para guarda.
- Financeiro nao abre ou apresenta erro em pagamentos, extratos ou devedores.
- Académico nao abre notas, pautas ou boletins.
- Permissao real fica mais ampla do que antes.
- Mobile fica inutilizavel em 360px ou 390px.

## Plano de rollback
1. Desactivar plugin v12.16.1.
2. Instalar novamente v12.16.0 final.
3. Limpar cache do WordPress e browser.
4. Validar login de administrador.
5. Validar professor, financeiro, secretaria e guarda.
6. Guardar evidência do motivo do rollback.

## Rollback parcial
Se apenas a limpeza de allowlist causar problema, reverter includes/admin-shell.php para a baseline v12.16.0 e manter docs/tools se necessario. Como a mudança preserva o conjunto unico de views, este risco e baixo.
