# QA Smoke - v12.12.1 Governance Baseline Corrective Audit

## Resultado

- PHP lint: 331 ficheiros PHP verificados; 0 falhas.
- Gates: 27/27 verdes conforme `docs/qa/GATES-v12.12.1.txt`.
- Manifesto: 174 superficies declaradas.
- Query handlers criticos: cobertos.
- Testes negativos: detectores apanham hook dinamico, query handler e query financeira sem tenant.
- P0/P1 conhecidos da v12.12.0: fechados.

## Gates correctivos principais

- `php tools/check-action-surface-manifest.php`
- `php tools/check-query-handler-surface.php`
- `php tools/check-tenant-sensitive-queries.php`
- `php tools/check-governance-negative-tests.php`
- `php tools/check-public-endpoints-policy.php`
- `php tools/check-governance-docs.php`

## Observacao operacional

O lint foi validado em execucao por blocos no ambiente de container para evitar timeouts do runner, mantendo `php -l` em todos os ficheiros listados no relatorio `docs/qa/PHP-LINT-v12.12.1.json`.
