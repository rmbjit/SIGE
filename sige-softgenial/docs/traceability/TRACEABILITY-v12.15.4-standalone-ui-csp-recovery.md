# Matriz de Rastreabilidade - v12.15.4 - Standalone UI/CSP Recovery

| Requisito | Implementação | Teste |
|---|---|---|
| Corrigir Portaria/QR em HTML cru | `includes/portaria-camera-safe-page.php` com `<style>` nonce e sem `style=` crítico | `tools/smoke-standalone-csp-ui-v12-15-4.php`; `php -l` |
| Cobrir páginas autónomas fora do admin shell | `sige_csp_zero_inline_is_standalone_request()` + hook `template_redirect` | `tools/smoke-standalone-csp-ui-v12-15-4.php` |
| Reaplicar guard após `ob_end_clean()` | `sige_csp_zero_inline_buffer_active()` e boot rearmável | smoke específico + lint |
| Evitar corrupção de Excel/binários | `documents-engine` só rearma CSP quando formato não é `xlsx/excel/csv` | smoke específico |
| Proteger popups `window.open()+document.write()` | `CSP Popup Guard v12.15.4` em `assets/sige-ui.js` | `node --check`; smoke específico |
| Preservar CSP forte | sem `unsafe-inline` em ficheiros de produção; sem `eval`/`new Function` | `tools/check-inline-frontend.php`; smoke específico |
| Integrar no corredor de gates | `tools/run-gates.php` | `php tools/run-gates.php` |
