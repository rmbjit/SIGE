# Matriz de Rastreabilidade - v12.14.0 - Front-end Security & CSP Enforcement

| Requisito | Implementação | Ficheiros | Testes/Evidências |
|---|---|---|---|
| Remover `onclick` inline | Conversão dos padrões críticos para `data-sige-act` e helpers globais seguros | `assets/sige-ui.js`, views financeiras, académicas e HR | `php tools/check-inline-frontend.php` mostra `onclick=17` |
| Reduzir/eliminar `style=` inline | Sem aumento; mantido baseline por compatibilidade | Views existentes | `php tools/check-inline-frontend.php` mostra `style=2051` sem regressão |
| Mover scripts para ficheiros JS | Novas pontes declarativas concentradas em `assets/sige-ui.js` | `assets/sige-ui.js` | Smoke CSP verifica helpers |
| Mover CSS para ficheiros próprios | Não concluído nesta vaga; mantido como risco P2 | N/A | Declarado no rediagnóstico |
| Usar `wp_enqueue_script` | Mantido padrão existente do shell; novas funções ficam no asset global já carregado | `assets/sige-ui.js` | Smoke assets existente |
| Usar `wp_enqueue_style` | Mantido padrão existente; sem novo CSS inline crítico | N/A | Sem novo CSS inline |
| Criar nonces/hashes CSP | Nonce por pedido e filtros para scripts inline WP | `includes/core-helpers.php`, `includes/admin-shell.php` | `php tools/smoke-csp-enforcement-v12-14-0.php` |
| Activar CSP real | Header `Content-Security-Policy` no shell admin | `includes/admin-shell.php` | `php tools/check-inline-frontend.php` exige enforcement |
| Reduzir CDNs externos | Google Fonts removido do login/portal | `includes/login-page.php`, `includes/portal-logic.php` | Smoke CSP verifica ausência de `fonts.googleapis`/`fonts.gstatic` |
| Self-host de fontes/bibliotecas críticas | Bibliotecas críticas já em `assets/vendor`; fontes externas removidas no login/portal | `assets/vendor`, login/portal | Gate assets + smoke CSP |
| Operar com CSP enforcement sem quebrar UI | Política enforcement com compatibilidade declarada para attrs/style inline legados | `includes/admin-shell.php` | Gates + rediagnóstico |
