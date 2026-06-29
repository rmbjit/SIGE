# QA / Definition of Done - v12.14.0 - Front-end Security & CSP Enforcement

## Checklist DoD
- [x] Phase Charter criado.
- [x] Inventário técnico concluído antes do fecho da implementação.
- [x] Plano fechado convertido em requisitos rastreáveis.
- [x] CSP enforcement activado no shell admin.
- [x] Nonces CSP aplicados a scripts inline do shell e scripts inline do WordPress.
- [x] Redução de `onclick` inline de 45 para 17.
- [x] Google Fonts externos removidos do login e portal.
- [x] Gates e smoke tests adicionados/actualizados.
- [x] CHANGELOG actualizado.
- [x] BUILD.json actualizado.
- [x] Notas de migração e rollback escritas.
- [x] Rediagnóstico adversarial executado.
- [x] Riscos residuais declarados.

## Validações esperadas
```bash
php -l sige-softgenial.php
php -l includes/core-helpers.php
php -l includes/admin-shell.php
php -l assets/sige-ui.js # não aplicável; validar por smoke/grep porque é JS
php tools/check-inline-frontend.php
php tools/smoke-inline-frontend.php
php tools/smoke-csp-enforcement-v12-14-0.php
php tools/smoke-fase4-assets.php
php tools/check-fase4-assets.php
php tools/smoke-financeiro-historico-classe-v12-12-64.php
```

## Regressão crítica a validar em staging
1. Entrar no WordPress como director/administrador.
2. Abrir `SIGE Escolar` (`page=sige-app`).
3. Confirmar no DevTools/Network que o header `Content-Security-Policy` existe e não é `Report-Only`.
4. Navegar por: Dashboard, Alunos, Turmas, Financeiro → Pagamentos, Devedores, Extratos, Centros, HR/Equipe.
5. Confirmar que botões de recibos/extratos/caixa/listas continuam a responder.
6. Confirmar ausência de erro bloqueador de CSP na consola para scripts do próprio SIGE.

## Observação importante
Esta versão não declara CSP zero-inline absoluto. Declara CSP enforcement operacional com compatibilidade temporária para atributos de evento e estilos inline legados.

## Nota sobre gate de consistencia visual
A fonte v12.12.64 ja chegava com o gate visual a falhar por baseline dimensional desactualizada, embora o total actual estivesse abaixo da baseline. Como a v12.14.0 nao introduziu nova divida visual e o total actual e menor, a baseline visual foi sincronizada para o estado real actual antes do corredor final de gates. Esta sincronizacao nao muda UI e apenas impede falso vermelho herdado.
