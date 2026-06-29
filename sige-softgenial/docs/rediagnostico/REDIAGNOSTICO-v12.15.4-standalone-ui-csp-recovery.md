# Rediagnóstico Adversarial - v12.15.4 - Standalone UI/CSP Recovery

## Achado reportado
A página Portaria Digital / Leitor de crachás apareceu como HTML cru. A imagem de staging mostrava títulos, botões, inputs e resultados sem a camada visual. Isto apontou para bloqueio CSP de CSS/JS em página autónoma fora do shell admin.

## Causa raiz
A CSP zero-inline estava correcta como política, mas alguns renderers autónomos ainda emitiam `<style>` sem nonce ou dependiam de `style=`. Como estas páginas não passam pelo `wp_enqueue` normal do shell e algumas limpam buffers, a sanitização/hidratação não era garantida.

## Correcção aplicada
- Nonce helper para `<style>`.
- Guard CSP para páginas autónomas.
- Buffer rearmável.
- Excepção segura para formatos binários.
- Hidratador externo injectável em standalone pages.
- Popup guard para `window.open()+document.write()`.

## Tentativas de quebra avaliadas
- Página standalone com `<style>` sem nonce: bloqueada por smoke.
- Página standalone com `style=`: convertida para `data-sige-style` e hidratada por JS externo.
- Renderer que chama `ob_end_clean()`: guard rearmável.
- Download Excel por `sige_print`: excluído do buffer HTML.
- Popup de impressão via `document.write('<style>')`: recebe nonce automaticamente.
- Reintrodução de `unsafe-inline`: bloqueada por `check-inline-frontend`.

## Risco residual
Não foi executado browser real neste ambiente. A validação manual em staging continua obrigatória, especialmente Portaria, documentos financeiros, turmas/alunos e RH.
