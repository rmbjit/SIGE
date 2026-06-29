# QA SMOKE - v12.12.0

## Comandos obrigatorios

1. `find . -name '*.php' -print0 | xargs -0 -n1 php -l`
2. `php tools/inventory-surface.php --check`
3. `php tools/check-view-permission-map.php`
4. `php tools/check-action-surface-manifest.php`
5. `php tools/check-public-endpoints-policy.php`
6. `php tools/check-authorization-baseline.php`
7. `php tools/check-tenant-fallbacks.php`
8. `php tools/check-secrets-options-register.php`
9. `php tools/check-external-dependencies-register.php`
10. `php tools/run-gates.php`

## Resultado esperado

Todos verdes. Evidencia desta entrega: PHP lint em 328 ficheiros com 0 falhas; `php tools/run-gates.php` com 24/24 gates verdes. Sem alteracao de schema; inclui migracao aditiva de permissoes SIGE para cobrir as views corrigidas.
