# TECHNICAL INVENTORY v12.12.3

## Superficie
A versao nao adiciona endpoints AJAX, admin-post, REST, cron, shortcode, wp_hook ou query_handler. A superficie de execucao continua inventariada pelo manifesto gerado em `docs/security/ACTION_SURFACE_MANIFEST-v12.12.3.json`.

## Views
Views marcados como Beta no catalogo UI:

- `financeiro-mpesa`.
- `whatsapp_circulares`.
- `comunicacoes_central`.
- `presencas`.

O pedido duplicou `whatsapp_circulares`; a implementacao mantem apenas uma declaracao real para evitar nota duplicada.

## Permissoes
Nao foram alteradas permissoes. As permissoes continuam governadas por `includes/admin-shell.php`, `includes/permissions-layer.php` e os guards especificos ja existentes nos modulos.

## Tenant
Nao foram alteradas queries nem filtros `escola_id`. Esta versao e UI informativa e nao altera tenant isolation.

## Segredos
Nao foram adicionadas opcoes nem segredos. Secret Vault permanece fora do escopo desta microversao.

## Dependencias
Nao foram adicionadas dependencias externas. O CSS foi incluido no asset local `assets/style.css`.

## query_handler
Nao foram criados novos query handlers. O manifesto v12.12.3 preserva os query handlers ja inventariados pela baseline de governacao.

## Ficheiros alterados principais
- `includes/ui-components.php`.
- `assets/style.css`.
- `tools/smoke-beta-views-v12-12-3.php`.
- `tools/run-gates.php`.
- `sige-softgenial.php`.
- `BUILD.json`.
- `CHANGELOG.md`.
- `docs/changelog/CHANGELOG-v12-12-3.txt`.
- `docs/deploy/DEPLOY-v12-12-3.txt`.
- `docs/governance/*v12.12.3*`.
