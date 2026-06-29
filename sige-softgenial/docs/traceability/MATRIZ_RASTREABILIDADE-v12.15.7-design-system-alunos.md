# Matriz de Rastreabilidade - v12.15.7

| Requisito | Implementação | Teste/Gate |
|---|---|---|
| Escopo apenas Alunos | `includes/ui-kit.php`, `assets/views/alunos-design-pro.css/js` | `check-alunos-design-scope-v12-15-7.php` |
| Sem camada global | Não usa `sige-design-system-pro.*`; selectores escopados | `check-design-system-safety-contract-v12-15-5.php`, `check-alunos-design-scope-v12-15-7.php` |
| Cards consistentes | CSS de grid e anti-compressão | `smoke-alunos-design-pro-v12-15-7.php` + QA browser |
| Três pontinhos vertical | CSS `details.sige-card-actions[open] > .sige-actions-menu` | `check-alunos-design-scope-v12-15-7.php` + QA browser |
| Mobile sem sobreposição | Menu em fluxo até 900px | `smoke-alunos-design-pro-v12-15-7.php` + QA browser |
| Acessibilidade | JS `aria-expanded`, Escape, foco visível | `node --check`, `smoke-alunos-design-pro-v12-15-7.php` |
| Reversibilidade | option `sige_design_alunos_v12157_enabled` | `check-alunos-design-scope-v12-15-7.php` |
| CSP preservada | Sem inline/eval/new Function | `check-inline-frontend.php`, `smoke-csp-enforcement-v12-14-0.php` |
