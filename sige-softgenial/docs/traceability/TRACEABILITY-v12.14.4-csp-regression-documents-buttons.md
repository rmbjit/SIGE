# Matriz de Rastreabilidade - v12.14.4

| Requisito | Implementação | Teste/Gate |
|---|---|---|
| PDF/histórico financeiro sem layout cru | CSS movido para `assets/documents/financeiro-historico-aluno.css` e carregado por `<link>` | `php tools/smoke-csp-regression-documents-buttons-v12-14-4.php` |
| Botão Guardar PDF sem onclick | `data-sige-print` + `assets/sige-document-actions.js` | smoke v12.14.4 + `node --check` |
| Botão Fechar sem onclick | `data-sige-close-back` + JS externo | smoke v12.14.4 + `node --check` |
| Não reintroduzir unsafe-inline | CSP zero-inline mantida; sem alteração para `unsafe-inline` | `grep` e `php tools/check-inline-frontend.php` |
| Botões/admin legados com mais padrões | `assets/sige-ui.js` expandido com eventos e expressões seguras | `node --check assets/sige-ui.js` + smoke v12.14.4 |
| Artefactos de release | BUILD/CHANGELOG/docs actualizados | revisão documental |
