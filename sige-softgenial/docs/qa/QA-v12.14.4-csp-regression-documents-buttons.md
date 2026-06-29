# QA / Definition of Done - v12.14.4

## Validações executadas
- `php -l includes/financeiro-historico-aluno-pro.php`
- `php -l includes/csp-zero-inline.php`
- `php -l sige-softgenial.php`
- `node --check assets/sige-ui.js`
- `node --check assets/sige-document-actions.js`
- `php tools/smoke-csp-regression-documents-buttons-v12-14-4.php`
- `php tools/check-inline-frontend.php`
- `php tools/run-gates.php`

## Definition of Done
- [x] Phase Charter criado.
- [x] Inventário técnico concluído antes da implementação.
- [x] Plano fechado: corrigir documentos e botões sem enfraquecer CSP.
- [x] CSS/JS documental externalizado para o histórico financeiro.
- [x] Hidratador de eventos reforçado.
- [x] Sem `unsafe-inline`, `eval` ou `new Function` novo.
- [x] BUILD.json actualizado.
- [x] CHANGELOG actualizado.
- [x] Notas de migração/rollback escritas.
- [x] Rediagnóstico adversarial executado.

## Teste manual recomendado
1. Abrir Alunos e testar: novo aluno, editar, ficha 360º, arquivo digital, importar alunos, baixar modelo e histórico financeiro.
2. Abrir o histórico financeiro do aluno e verificar layout profissional.
3. Clicar em Guardar PDF / Imprimir e Fechar.
4. Confirmar no DevTools que não há violações CSP bloqueantes para o documento.
