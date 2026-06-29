# QA / Definition of Done - v12.15.4 - Standalone UI/CSP Recovery

## Validações executadas

- `php -l includes/core-helpers.php`
- `php -l includes/csp-zero-inline.php`
- `php -l includes/portaria-camera-safe-page.php`
- `php -l includes/documents-engine.php`
- `php -l admin/boletim-pdf-template.php`
- `php -l admin/pauta-pdf-template.php`
- `php -l admin/jardim/jardim_boletim-view.php`
- `node --check assets/sige-ui.js`
- `php tools/smoke-standalone-csp-ui-v12-15-4.php`
- `php tools/check-inline-frontend.php`
- `php tools/smoke-inline-frontend.php`
- `php tools/smoke-csp-enforcement-v12-14-0.php`
- `php tools/smoke-design-system-stability-v12-15-3.php`
- `php tools/run-gates.php`

## Definition of Done

- [x] Corrigido o sintoma directo da Portaria/Leitor QR sem CSS.
- [x] Criado helper CSP para `<style>` com nonce.
- [x] Páginas autónomas cobertas antes da renderização.
- [x] Popups de impressão protegidos contra CSP herdada.
- [x] Documentos HTML cobertos sem afectar Excel/binários.
- [x] CSP forte preservada.
- [x] Sem novo Design System global.
- [x] Gates executados.
- [x] BUILD e CHANGELOG actualizados.
- [x] Riscos residuais declarados.

## Teste manual recomendado

1. Abrir Portaria Digital / Leitor de QR no URL autónomo e confirmar layout visual completo.
2. Testar câmara/foto/manual.
3. Abrir recibo, factura, extracto e histórico financeiro.
4. Testar boletim, pauta e boletim Jardim.
5. Testar popups de impressão gerados por Alunos, Turmas, Financeiro e RH.
6. Confirmar no DevTools que não existem violações bloqueantes de `style-src`, `style-src-attr`, `script-src` ou `script-src-attr` nos fluxos testados.
