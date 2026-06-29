# Matriz de rastreabilidade - v12.14.1

| Requisito | Implementacao | Validacao |
|---|---|---|
| Remover `unsafe-inline` | `includes/csp-zero-inline.php`; headers autonomos actualizados | `tools/check-inline-frontend.php`; `grep unsafe-inline` |
| Bloquear handlers inline | `script-src-attr 'none'`; conversao `on*=` para `data-sige-on-*` | `tools/smoke-csp-enforcement-v12-14-0.php` |
| Bloquear estilos por atributo | `style-src-attr 'none'`; conversao `style=` para `data-sige-style` | `tools/check-inline-frontend.php` |
| Preservar interface | Hidratador em `assets/sige-ui.js` | `node --check`; `check-js-views`; staging recomendado |
| CSP enforcement | Header `Content-Security-Policy` real | smoke CSP |
| Sem eval/fail-open | Dispatcher sem `eval`/`new Function` | `tools/check-inline-frontend.php` |
| Release governada | BUILD, CHANGELOG e docs actualizados | `tools/smoke-release-gate.php` |
