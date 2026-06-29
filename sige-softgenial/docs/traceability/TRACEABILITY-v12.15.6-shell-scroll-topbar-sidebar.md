# Matriz de Rastreabilidade - v12.15.6 - Shell, Scroll, Topbar e Sidebar

| Requisito | Implementacao | Teste |
|---|---|---|
| Limitar vaga ao shell | `assets/sige-shell-stability.css` escopado ao shell | `tools/check-shell-scope-v12-15-6.php` |
| Carregar CSS via WordPress | `includes/ui-kit.php` enfileira `sige-shell-stability` | `tools/smoke-shell-scroll-v12-15-6.php` |
| Carregar JS via WordPress | `includes/ui-kit.php` enfileira `sige-shell-stability.js` | `tools/smoke-shell-scroll-v12-15-6.php` |
| Resolver scroll tablet | CSS tablet remove bloqueio de `overflow:hidden` | `tools/smoke-shell-scroll-v12-15-6.php` |
| Manter desktop com scroll independente | CSS desktop usa 1101px ou mais | `tools/smoke-shell-scroll-v12-15-6.php` |
| Evitar regressao em cards internos | Gate proibe selectores de cards, botoes e tabelas | `tools/check-shell-scope-v12-15-6.php` |
| Sincronizar sidebar | `assets/sige-shell-stability.js` fecha menu, overlay e ARIA | `node --check` e smoke |
| Preservar CSP | Sem inline novo, sem `eval`, sem `new Function` | `tools/check-inline-frontend.php` |
| Versionar release | `sige-softgenial.php`, `BUILD.json`, `CHANGELOG.md` | `tools/smoke-release-gate.php` |
