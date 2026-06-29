# Migração e Rollback - v12.14.4

## Migração
1. Instalar o ZIP da v12.14.4 em staging.
2. Limpar cache do navegador/CDN se aplicável.
3. Validar módulos com botões declarativos, especialmente Alunos.
4. Validar histórico financeiro do aluno e impressão/guardar PDF.

## Rollback
- Reverter para v12.14.3 se ocorrer regressão P0/P1.
- Como não há alteração de schema, o rollback é por substituição de ficheiros do plugin.
- Se apenas um documento falhar, preferir hotfix sobre os assets `assets/documents/*` ou `assets/sige-document-actions.js`, sem relaxar CSP.

## Nota sobre PDF server-side
Não instalar motor PDF no meio desta correcção. Criar fase própria para seleccionar biblioteca self-hosted, empacotar fontes, validar paginação A4, assinaturas visuais e consumo de memória.
