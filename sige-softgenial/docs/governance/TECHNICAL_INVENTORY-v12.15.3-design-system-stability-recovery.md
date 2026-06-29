# Inventário Técnico - v12.15.3

## Base analisada
- Última base aprovada em staging: v12.14.4.
- Regressões reportadas nas versões v12.15.0-v12.15.2: scroll principal, menus de aluno, cards comprimidos e efeitos laterais em páginas não auditadas visualmente.

## Pontos afectados
- `includes/ui-kit.php`: fonte de enqueue do UI Kit. Mantido no estado v12.14.4, sem carregar `sige-design-system-pro.*`.
- `admin/finance/financeiro-pagamentos.php`: preservada validação de confirmação de pagamento.
- `sige-softgenial.php`: versão para 12.15.3.
- `BUILD.json`, `CHANGELOG.md`, docs e smoke.

## Pontos explicitamente não alterados
- `includes/admin-shell.php` e CSP zero-inline.
- `includes/security-user-integrity.php` e recuperação de contas privilegiadas.
- `assets/sige-ui.js`, `assets/sige-ui.css`, `assets/mobile-tablet-ux.*`.
- Views de alunos: não receberam nova sobreposição CSS nesta versão.

## Risco identificado
A camada global de normalização visual actuava sobre padrões legados heterogéneos. Mesmo selectors aparentemente conservadores geravam efeitos colaterais. A próxima fase deve migrar por módulo, não por enhancer global.
