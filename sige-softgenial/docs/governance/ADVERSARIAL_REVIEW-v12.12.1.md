# Rediagnóstico Adversarial - v12.12.1

Marcador: Rediagnostico adversarial

## Veredicto

A v12.12.1 foi tratada como versão correctiva da v12.12.0, não como nova fase funcional.

Estado após revisão:

```text
P0 aberto: 0
P1 aberto: 0
P2 aberto: residual documentado
P3 aberto: residual documentado
Decisão: aprovada para staging
```

## Achados bloqueadores da v12.12.0 e estado

| ID | Achado | Estado v12.12.1 | Evidência |
|---|---|---|---|
| P0-001 | `sige_desp_print` sem isolamento por escola em despesas | Corrigido | `includes/finance-core.php` exige nonce, permissão, `escola_id`, consulta `id + escola_id` e auditoria. |
| P1-001 | `wp_ajax:sige_settings_save` fora do manifesto | Corrigido | `ACTION_SURFACE_MANIFEST-v12.12.1.json` inclui `wp_ajax:sige_settings_save`. |
| P1-002 | `template_redirect`/query handlers fora do manifesto | Corrigido | Manifesto inclui `wp_hook:template_redirect` e query handlers `sige_desp_print`, `sige_recibo`, `sige_portaria_camera`, `sige_print`, `sige_billing_bypass`. |
| P1-003 | Gate do manifesto com falso-verde estrutural | Corrigido | `check-action-surface-manifest.php` compara extractor primário com extractor independente e `check-governance-negative-tests.php` exercita falhas intencionais. |

## Testes executados

- `php tools/run-gates.php` - 27/27 gates verdes.
- PHP lint de todos os ficheiros PHP do pacote - 331/331 OK.
- Gates individuais de governação correctiva - OK.
- Testes negativos de manifesto/query/tenant - OK.

## Verificações adversariais específicas

1. Superfície dinâmica AJAX: `wp_ajax_ . self::ACTION_SAVE` detectada como `wp_ajax:sige_settings_save`.
2. Superfície de query string: `sige_desp_print` detectado e classificado como high risk.
3. Superfície `template_redirect`: registada como `wp_hook:template_redirect`.
4. Despesas: bloqueio sem nonce, escola inválida e consulta sem `escola_id` cobertos por gate.
5. Gate negativo: amostra temporária sem tenant é detectada e removida.

## Riscos residuais não bloqueadores

| Severidade | Risco residual | Fase futura |
|---|---|---|
| P2 | A migração total de `current_user_can` para policy engine única ainda não foi feita. | Fase 1/2 |
| P2 | Tenant fail-closed global ainda não foi aplicado a todo o sistema. | Fase 3 |
| P2 | Secret Vault universal ainda não foi implementado. | Fase 5 |
| P2 | MFA crítico ainda não foi implementado. | Fase 4 |
| P2 | Ledger financeiro institucional ainda não foi implementado. | Fase 6 |
| P2 | CSP enforcement e remoção de inline JS/CSS ainda pendentes. | Fase 10 |

## Decisão

Marcador: Decisao


A versão correctiva fecha os bloqueadores P0/P1 encontrados na v12.12.0. A versão pode ser instalada em staging para validação funcional controlada.
