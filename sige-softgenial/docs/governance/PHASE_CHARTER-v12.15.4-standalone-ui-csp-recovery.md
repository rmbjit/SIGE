# Phase Charter - v12.15.4 - Standalone UI/CSP Recovery

## Contexto
Após a recuperação de estabilidade v12.15.3, o teste em staging mostrou a página pública/autónoma da Portaria Digital / Leitor de crachás renderizada como HTML cru. O sintoma é compatível com CSP enforcement a bloquear blocos `<style>` sem nonce e estilos inline em páginas fora do shell `wp-admin`.

## Objectivo
Eliminar a classe de regressão em que páginas autónomas, documentos ou popups de impressão perdem CSS/JS sob CSP forte e aparecem como HTML simples para utilizadores/clientes.

## Escopo
- Portaria Digital / Leitor de QR (`sige_portaria_camera`).
- Documentos HTML autónomos de `sige_print`.
- Mapas/listas de cobrança `sige_dev_print`.
- Documentos de despesas `sige_desp_print`.
- Popups legados criados por `window.open()` + `document.write()` dentro do shell SIGE.
- Gates estáticos para impedir regressão desta classe.

## Não-escopo
- Reintroduzir Design System PRO global.
- Relaxar CSP com permissões inline fracas.
- Instalar motor PDF server-side.
- Refazer visual completo de todos os documentos.

## Critérios de aceitação
- A Portaria/Leitor QR deixa de depender de `<style>` sem nonce.
- Páginas autónomas HTML têm guard CSP antes da renderização.
- Estilos/handlers inline legados em páginas autónomas são convertidos e hidratados por JS externo quando necessário.
- Popups de impressão recebem nonce automaticamente em `<style>`/`<script>` escritos via `document.write()`.
- Downloads binários/Excel não são passados pelo buffer HTML.
- Gates e smoke específicos verdes.

## Riscos
- Alguns documentos muito antigos podem continuar visualmente diferentes, mas não devem ficar em HTML cru.
- Teste visual em browser autenticado continua obrigatório para confirmar os fluxos reais de Portaria/documentos.
