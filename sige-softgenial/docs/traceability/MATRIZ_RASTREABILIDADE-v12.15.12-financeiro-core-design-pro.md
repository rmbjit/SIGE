# Matriz de Rastreabilidade - v12.15.12 - Financeiro Core

| Requisito | Implementação | Teste/Gate |
|---|---|---|
| Escopo apenas financeiro | `includes/ui-kit.php` com whitelist de views financeiras | `tools/check-financeiro-core-design-scope-v12-15-12.php` |
| Não alterar fórmulas/Finance Score | Nenhum `admin/finance/*.php` alterado; baseline de hashes | `tools/check-financeiro-formula-integrity-v12-15-12.php` |
| CSS por view | `assets/views/financeiro-core-design-pro.css` | `tools/smoke-financeiro-core-design-pro-v12-15-12.php` |
| JS sem comportamento financeiro | `assets/views/financeiro-core-design-pro.js` passivo | `node --check` + scope gate |
| Feature flag reversível | option `sige_design_financeiro_core_v121512_enabled` | scope gate |
| CSP preservada | Sem inline novo, sem eval/new Function | `tools/check-inline-frontend.php` e gates existentes |
| Shell e Alunos preservados | Não altera assets de Alunos/Shell | gates v12.15.6 e v12.15.11 no corredor |
