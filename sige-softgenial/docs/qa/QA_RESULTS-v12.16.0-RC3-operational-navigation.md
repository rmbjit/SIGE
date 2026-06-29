# QA Results - v12.16.0 RC3 Operational Navigation Rail

## Escopo testado

- Helper read-only de grupos de navegação operacional.
- Renderização da faixa `Comece aqui` no shell.
- Preservação da navegação principal.
- Limites de acções por grupo e número máximo de grupos.
- Filtragem por permissão via guarda central.

## Testes executados

| Teste | Resultado |
|---|---:|
| PHP lint global | Verde |
| `tools/check-v12-16-0-operational-navigation.php` | Verde |
| `tools/smoke-v12-16-0-operational-navigation.php` | Verde |
| Corredor oficial por intervalos | Verde |
| Release gate | Verde |

## Evidências previstas

- `/mnt/data/sige_v121525_work/php-lint-after-rc3-v12.16.0.log`
- `/mnt/data/sige_v121525_work/run-gates-after-rc3-v12.16.0-1-22.log`
- `/mnt/data/sige_v121525_work/run-gates-after-rc3-v12.16.0-23-45.log`
- `/mnt/data/sige_v121525_work/run-gates-after-rc3-v12.16.0-46-75.log`
- `/mnt/data/sige_v121525_work/run-gates-after-rc3-v12.16.0-76-105.log`
- `/mnt/data/sige_v121525_work/run-gates-after-rc3-v12.16.0-106-128.log`
- `/mnt/data/sige_v121525_work/run-gates-after-rc3-v12.16.0-chunked-complete.log`
- `/mnt/data/sige_v121525_work/release-gate-after-rc3-v12.16.0.log`

## Testes não executados

- Validação humana em staging.
- Teste visual em navegador autenticado.
- Teste com base de dados real da escola.

## Conclusão

RC3 está tecnicamente verde em ambiente local/CLI. A alteração é pequena, reversível e não toca áreas P0 de regra financeira ou académica.
