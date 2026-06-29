# Definition of Done - v12.16.2

## Regra central
A v12.16.2 e uma fase de preservacao de baseline e readiness operacional. Ela nao deve introduzir comportamento novo em producao.

## Criterios obrigatorios
- Versao sincronizada nas 3 fontes oficiais.
- BUILD.json com origem v12.16.1 e SHA256 da origem.
- Protected contracts declarados para financeiro, academico, permissoes e dados.
- Matriz de regressao presente.
- Checklist pos-instalacao presente.
- Staging validation por perfis presente.
- Risk Register actualizado.
- Rollback documentado.
- Todos os gates locais verdes.
- ZIP final com integridade validada.

## Criterios bloqueadores
- Qualquer alteracao em formulas financeiras.
- Qualquer alteracao em formulas academicas.
- Qualquer alteracao em permissoes reais.
- Qualquer migracao ou escrita em dados historicos.
- Qualquer mudanca de schema.
- Qualquer quebra de hash em ficheiro protegido.
- Qualquer gate vermelho sem justificacao tecnica formal.

## Criterios de QA staging
- Administrador entra no painel sem erro fatal.
- Director abre dashboard e mapa operacional.
- Financeiro abre pagamentos, extractos e devedores.
- Secretaria abre alunos e ficha 360.
- Professor abre Minhas Turmas sem loop e sem #038;view.
- Guarda abre Portaria em mobile.
- Encarregado abre portal sem peso indevido evidente.
- Aluno abre portal e documentos basicos.

## Regra de honestidade de QA
Browser autenticado so pode ser declarado executado quando houver sessao real de staging. Sem sessao autenticada, o estado correcto e prepared_not_executed_here.
