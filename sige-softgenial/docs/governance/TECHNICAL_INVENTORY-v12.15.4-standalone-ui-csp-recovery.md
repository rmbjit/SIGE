# Inventário Técnico - v12.15.4 - Standalone UI/CSP Recovery

## Superfícies afectadas

| Superfície | Ficheiro | Risco detectado | Correcção |
|---|---|---|---|
| Portaria Digital / Leitor de QR | `includes/portaria-camera-safe-page.php` | `<style>` sem nonce sob CSP; `style=` residual | `<style>` com `sige_csp_style_attr()`; classes `qr-file-reader` e `manual-card` |
| CSP guard | `includes/csp-zero-inline.php` | Só cobria admin shell / algumas actions; páginas front-end autónomas ficavam fora do buffer | cobertura `template_redirect` para `sige_portaria_camera`, `sige_print`, `sige_dev_print`, `sige_desp_print` |
| CSP helper | `includes/core-helpers.php` | havia helper para `<script>` mas não para `<style>` | novo `sige_csp_style_attr()` |
| Documents engine | `includes/documents-engine.php` | renderer limpava buffers com `ob_end_clean()` e podia sair sem sanitização CSP | rearmar guard apenas para HTML, excluindo `.xlsx`/binários |
| Templates PDF/HTML | `admin/boletim-pdf-template.php`, `admin/pauta-pdf-template.php`, `admin/jardim/jardim_boletim-view.php`, `includes/documents-engine.php` | `<style>` sob CSP directo sem nonce | aplicação de nonce helper |
| Popups de impressão | `assets/sige-ui.js` | `about:blank`/popups herdam CSP do shell; `<style>`/`<script>` escritos por `document.write()` podem ser bloqueados | wrapper seguro de `window.open()` e `document.write()` que acrescenta nonce |
| Gates | `tools/smoke-standalone-csp-ui-v12-15-4.php`, `tools/run-gates.php` | ausência de gate específico para esta classe de regressão | novo smoke integrado no corredor principal |

## Tabelas/opções
Sem alterações de schema, tabelas ou opções persistentes.

## Permissões/tenant
Sem alteração de autorização. O patch não amplia permissões; actua apenas na renderização segura de HTML já autorizado.

## Assets
- Alterado: `assets/sige-ui.js`.
- Não foram adicionadas dependências externas.
