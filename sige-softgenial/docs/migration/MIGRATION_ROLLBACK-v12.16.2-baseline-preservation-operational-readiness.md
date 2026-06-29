# Migration and Rollback - v12.16.2

## Tipo de fase
Fase sem migracao de dados. Nao altera schema, tabelas, options historicas, metadados de alunos, lancamentos, notas, permissoes reais ou tenant isolation.

## ZIP de retorno
Rollback preferencial: v12.16.1 - Runtime Evidence & Technical Debt Closure.
SHA256 de origem: 73355d8081fe8a4c2f764002cbcc1ba8f9413c22ebe0f95bee5853c04ffeb6f1.

## Condicoes de rollback imediato
- Erro fatal apos instalacao.
- Tela branca.
- Login bloqueado.
- Professor nao consegue abrir Minhas Turmas.
- URL com #038;view ou loop de redirect.
- Financeiro abre com erro.
- Notas, pautas ou boletins abrem com erro.
- Portaria fica inoperacional.
- Menu mostra areas indevidas para perfil nao autorizado.

## Procedimento de rollback
1. Desactivar v12.16.2 se necessario.
2. Instalar novamente v12.16.1 aprovada.
3. Limpar cache do WordPress e do navegador.
4. Validar administrador, financeiro, professor, guarda e portal.
5. Registar evidencia do erro que motivou rollback.

## Rollback parcial
Nao recomendado. Como a fase e pequena e nao altera dados, o rollback mais seguro e voltar o plugin inteiro para v12.16.1.
